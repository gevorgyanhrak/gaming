<?php

use App\Enums\PaymentStatus;
use App\Filament\Resources\ChipPackages\Pages\EditChipPackage;
use App\Models\AuditLog;
use App\Models\ChipPackage;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Payments\Checkout;
use App\Platform\Payments\PaymentProvider;
use App\Platform\Payments\ProviderEvent;
use App\Platform\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** A provider for tests only: signs its webhooks with HMAC, as a real one would. */
class TestPaymentProvider implements PaymentProvider
{
    public const SECRET = 'test-secret';

    public function id(): string
    {
        return 'testpay';
    }

    public function checkout(PaymentOrder $order): Checkout
    {
        return new Checkout("https://pay.test/checkout/{$order->public_id}", "ref-{$order->public_id}");
    }

    public function parseWebhook(Request $request): ?ProviderEvent
    {
        if (! hash_equals(hash_hmac('sha256', $request->getContent(), self::SECRET), (string) $request->header('X-Signature'))) {
            return null;
        }

        return new ProviderEvent((string) $request->input('reference'), PaymentStatus::from((string) $request->input('status')), $request->input('reason'));
    }
}

function connectTestProvider(): void
{
    app()->tag([TestPaymentProvider::class], 'payment-providers');
    app(Settings::class)->update(['payments.enabled' => true, 'payments.provider' => 'testpay']);
}

/** A webhook call from the test provider, signed (or not). */
function providerSays(string $reference, string $status, bool $signed = true): TestResponse
{
    $body = json_encode(['reference' => $reference, 'status' => $status]);

    return test()->call('POST', '/api/v1/payments/webhook/testpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE' => $signed ? hash_hmac('sha256', $body, TestPaymentProvider::SECRET) : 'forged',
    ], $body);
}

function paymentBuyer(): User
{
    $user = User::factory()->create();
    app(FishkaWallets::class)->open($user); // 5,000 welcome Fishkas

    return $user;
}

function purchaserBalance(User $user): int
{
    return (int) DB::table('fishka_wallets')->where('user_id', $user->id)->value('balance');
}

it('lists five packages, larger ones worth more per dollar, the $10 one recommended — not buyable without a provider', function () {
    $response = $this->getJson('/api/v1/chip-packages')->assertOk()->assertJsonPath('purchasable', false);
    $packages = collect($response->json('data'));

    expect($packages->pluck('price_cents')->all())->toBe([200, 500, 800, 1000, 1500])
        ->and($packages->firstWhere('is_recommended', true)['price_cents'])->toBe(1000);
    $perDollar = $packages->map(fn ($p) => $p['total_chips'] / $p['price_cents'])->values()->all();
    expect($perDollar)->toBe(collect($perDollar)->sort()->values()->all());

    $user = paymentBuyer();
    $this->actingAs($user)->postJson('/api/v1/me/payments/orders', ['package' => 'large', 'key' => (string) Str::uuid()])
        ->assertStatus(422)->assertJsonPath('code', 'payments_unavailable');
    expect(PaymentOrder::query()->count())->toBe(0)->and(purchaserBalance($user))->toBe(5000);
});

it('creates one order per client key, sends the player to the provider, and credits nothing before payment', function () {
    connectTestProvider();
    $user = paymentBuyer();
    $key = (string) Str::uuid();

    $first = $this->actingAs($user)->postJson('/api/v1/me/payments/orders', ['package' => 'large', 'key' => $key])->assertCreated();
    $again = $this->actingAs($user)->postJson('/api/v1/me/payments/orders', ['package' => 'large', 'key' => $key])->assertCreated();

    expect($first->json('redirect_url'))->toStartWith('https://pay.test/checkout/')
        ->and($again->json('data.id'))->toBe($first->json('data.id'))
        ->and($first->json('data'))->toMatchArray(['status' => 'pending', 'price_cents' => 1000, 'currency' => 'USD', 'chips' => 60000])
        ->and(PaymentOrder::query()->count())->toBe(1)
        ->and(purchaserBalance($user))->toBe(5000);
});

it('credits the Fishkas once when the provider confirms the payment, however often it says so', function () {
    connectTestProvider();
    $user = paymentBuyer();
    $id = $this->actingAs($user)->postJson('/api/v1/me/payments/orders', ['package' => 'small', 'key' => (string) Str::uuid()])->json('data.id');

    providerSays("ref-{$id}", 'paid')->assertOk();
    providerSays("ref-{$id}", 'paid')->assertOk();

    $order = PaymentOrder::query()->where('public_id', $id)->sole();
    expect($order->status)->toBe(PaymentStatus::Paid)
        ->and(purchaserBalance($user))->toBe(5000 + 27500)
        ->and(DB::table('fishka_transactions')->where('user_id', $user->id)->where('type', 'purchase')->count())->toBe(1);
    $this->actingAs($user)->getJson("/api/v1/me/payments/orders/{$id}")->assertOk()->assertJsonPath('data.status', 'paid');

    // A late "failed" can't undo a payment; a refund is recorded for the admin (Fishkas aren't taken back).
    providerSays("ref-{$id}", 'failed')->assertOk();
    expect($order->fresh()->status)->toBe(PaymentStatus::Paid);
    providerSays("ref-{$id}", 'refunded')->assertOk();
    expect($order->fresh()->status)->toBe(PaymentStatus::Refunded)->and(purchaserBalance($user))->toBe(32500);
});

it('turns down forged or unknown webhooks, and a failed payment credits nothing', function () {
    connectTestProvider();
    $user = paymentBuyer();
    $id = $this->actingAs($user)->postJson('/api/v1/me/payments/orders', ['package' => 'starter', 'key' => (string) Str::uuid()])->json('data.id');

    providerSays("ref-{$id}", 'paid', signed: false)->assertStatus(400);
    $this->postJson('/api/v1/payments/webhook/nobody', [])->assertNotFound();
    providerSays("ref-{$id}", 'failed')->assertOk();
    providerSays("ref-{$id}", 'paid')->assertOk(); // final: a failed order stays failed

    expect(PaymentOrder::query()->where('public_id', $id)->sole()->status)->toBe(PaymentStatus::Failed)
        ->and(purchaserBalance($user))->toBe(5000);
});

it('shows players only their own orders, and sells only enabled packages', function () {
    connectTestProvider();
    $user = paymentBuyer();
    $id = $this->actingAs($user)->postJson('/api/v1/me/payments/orders', ['package' => 'medium', 'key' => (string) Str::uuid()])->json('data.id');

    $this->actingAs(paymentBuyer())->getJson("/api/v1/me/payments/orders/{$id}")->assertNotFound();
    ChipPackage::query()->where('slug', 'max')->update(['is_enabled' => false]);
    $this->actingAs($user)->postJson('/api/v1/me/payments/orders', ['package' => 'max', 'key' => (string) Str::uuid()])
        ->assertStatus(422)->assertJsonValidationErrors('package');
    $this->getJson('/api/v1/chip-packages')->assertJsonCount(4, 'data');
});

it('lets admins edit packages (audited) and only read orders', function () {
    $admin = signInToAdmin('admin');
    $package = ChipPackage::query()->where('slug', 'large')->sole();

    $this->get('/admin/chip-packages')->assertOk()->assertSee('Large');
    $this->get('/admin/payment-orders')->assertOk();
    $this->get('/admin/payment-orders/create')->assertNotFound();

    Livewire\Livewire::test(EditChipPackage::class, ['record' => $package->getRouteKey()])
        ->fillForm(['bonus_chips' => 12000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($package->fresh()->bonus_chips)->toBe(12000)
        ->and(AuditLog::query()->where('action', 'chip_package.updated')->sole()->actor_id)->toBe($admin->id);
});
