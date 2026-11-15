<?php

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\SlotTransaction;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Tests\Fixtures\Slots\FakeSlotAdapter;

beforeEach(function () {
    $this->provider = SlotProvider::query()->create(['slug' => 'fakeslots', 'name' => 'Fake Slots', 'is_enabled' => true]);
    $this->game = SlotGame::query()->create([
        'slot_provider_id' => $this->provider->id, 'provider_game_id' => 'fruit-5', 'slug' => 'fruit',
        'name' => ['en' => 'Fruit'], 'min_bet' => 10, 'max_bet' => 500, 'max_multiplier' => 100, 'is_enabled' => true,
    ]);
    $this->user = User::factory()->create();
    app(FishkaWallets::class)->open($this->user); // 5,000 welcome Fishkas
});

function withAdapter(): void
{
    app()->tag([FakeSlotAdapter::class], 'slot-adapters');
}

/** Opens the game for the player through the API; returns the token the provider got in the address. */
function launch($test): string
{
    $url = $test->actingAs($test->user)->postJson('/api/v1/slots/fruit/launch')->assertOk()->json('data.url');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return $query['token'];
}

/** A signed wallet call, as the provider's server would send it. */
function walletCall($test, array $body, ?string $secret = FakeSlotAdapter::SECRET, ?int $at = null)
{
    $json = json_encode($body, JSON_THROW_ON_ERROR);
    $timestamp = (string) ($at ?? time());

    return $test->call('POST', '/api/v1/slots/wallet/fakeslots', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SLOT_TIMESTAMP' => $timestamp,
        'HTTP_X_SLOT_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$json, (string) $secret),
    ], $json);
}

function balanceNow(User $user): int
{
    return app(FishkaWallets::class)->wallet($user)->balance;
}

it('offers no slot and opens none while no provider adapter exists', function () {
    $this->getJson('/api/v1/slots')->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($this->user)->postJson('/api/v1/slots/fruit/launch')->assertUnprocessable()->assertJsonValidationErrors('game');
});

it('offers an enabled game of a connected provider and opens a session for the player', function () {
    withAdapter();
    $this->getJson('/api/v1/slots')->assertOk()->assertJsonPath('data.0.slug', 'fruit')->assertJsonPath('data.0.provider', 'Fake Slots');

    $token = launch($this);
    expect(strlen($token))->toBe(48);
    walletCall($this, ['action' => 'balance', 'session' => $token])->assertOk()->assertJson(['status' => 'ok', 'balance' => 5000]);
});

it('takes a bet and pays its win once, however often the provider repeats the calls', function () {
    withAdapter();
    $token = launch($this);

    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b1', 'round_id' => 'r1', 'amount' => 100])
        ->assertOk()->assertJson(['status' => 'ok', 'balance' => 4900]);
    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b1', 'round_id' => 'r1', 'amount' => 100])
        ->assertOk()->assertJson(['status' => 'ok', 'balance' => 4900]);
    walletCall($this, ['action' => 'win', 'session' => $token, 'tx_id' => 'w1', 'round_id' => 'r1', 'amount' => 750])
        ->assertOk()->assertJson(['status' => 'ok', 'balance' => 5650]);
    walletCall($this, ['action' => 'win', 'session' => $token, 'tx_id' => 'w1', 'round_id' => 'r1', 'amount' => 750])
        ->assertOk()->assertJson(['status' => 'ok', 'balance' => 5650]);

    expect(balanceNow($this->user))->toBe(5650)
        ->and(FishkaTransaction::query()->where('user_id', $this->user->id)->whereIn('type', [Type::SlotBet, Type::SlotWin])->pluck('amount')->all())
        ->toBe([-100, 750]);

    // The same transaction id with other details is a conflict, not a new movement.
    walletCall($this, ['action' => 'win', 'session' => $token, 'tx_id' => 'w1', 'round_id' => 'r1', 'amount' => 9000])
        ->assertOk()->assertJson(['status' => 'refused', 'error' => 'tx_conflict']);
    expect(balanceNow($this->user))->toBe(5650);
});

it('refuses calls that are not signed with the provider secret, or are stale', function () {
    withAdapter();
    $token = launch($this);

    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b1', 'round_id' => 'r1', 'amount' => 100], secret: 'guess')
        ->assertUnauthorized();
    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b1', 'round_id' => 'r1', 'amount' => 100], at: time() - 600)
        ->assertUnauthorized();

    expect(balanceNow($this->user))->toBe(5000)->and(SlotTransaction::query()->count())->toBe(0);
});

it('never pays a win without a bet, or above the game’s limit for the round', function () {
    withAdapter();
    $token = launch($this);

    walletCall($this, ['action' => 'win', 'session' => $token, 'tx_id' => 'w0', 'round_id' => 'nothing', 'amount' => 1000])
        ->assertOk()->assertJson(['status' => 'refused', 'error' => 'no_bet']);

    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b1', 'round_id' => 'r1', 'amount' => 10]);
    walletCall($this, ['action' => 'win', 'session' => $token, 'tx_id' => 'w1', 'round_id' => 'r1', 'amount' => 1001])
        ->assertOk()->assertJson(['status' => 'refused', 'error' => 'win_over_limit']);
    walletCall($this, ['action' => 'win', 'session' => $token, 'tx_id' => 'w2', 'round_id' => 'r1', 'amount' => 1000])
        ->assertOk()->assertJson(['status' => 'ok']);

    expect(balanceNow($this->user))->toBe(5990)
        ->and(SlotTransaction::query()->where('status', 'refused')->pluck('error')->all())->toBe(['no_bet', 'win_over_limit']);
});

it('refuses bets outside the limits, above the balance, or in a closed session', function () {
    withAdapter();
    $token = launch($this);

    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b1', 'round_id' => 'r1', 'amount' => 501])
        ->assertJson(['status' => 'refused', 'error' => 'bet_limits']);
    walletCall($this, ['action' => 'bet', 'session' => 'not-a-token', 'tx_id' => 'b2', 'round_id' => 'r1', 'amount' => 100])
        ->assertJson(['status' => 'refused', 'error' => 'bad_session']);

    app(FishkaWallets::class)->charge($this->user, 4950, Type::AdminDebit);
    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b3', 'round_id' => 'r1', 'amount' => 100])
        ->assertJson(['status' => 'refused', 'error' => 'insufficient_fishkas', 'balance' => 50]);

    // Opening the game again closes the old session.
    launch($this);
    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b4', 'round_id' => 'r2', 'amount' => 10])
        ->assertJson(['status' => 'refused', 'error' => 'session_closed']);

    expect(balanceNow($this->user))->toBe(50);
});

it('gives back an unsettled bet once on rollback, and never a settled one', function () {
    withAdapter();
    $token = launch($this);

    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b1', 'round_id' => 'r1', 'amount' => 200]);
    walletCall($this, ['action' => 'rollback', 'session' => $token, 'tx_id' => 'x1', 'reference_tx_id' => 'b1'])
        ->assertJson(['status' => 'ok', 'balance' => 5000]);
    walletCall($this, ['action' => 'rollback', 'session' => $token, 'tx_id' => 'x2', 'reference_tx_id' => 'b1'])
        ->assertJson(['status' => 'refused', 'error' => 'not_rollbackable']);

    walletCall($this, ['action' => 'bet', 'session' => $token, 'tx_id' => 'b2', 'round_id' => 'r2', 'amount' => 200]);
    walletCall($this, ['action' => 'win', 'session' => $token, 'tx_id' => 'w2', 'round_id' => 'r2', 'amount' => 0]);
    walletCall($this, ['action' => 'rollback', 'session' => $token, 'tx_id' => 'x3', 'reference_tx_id' => 'b2'])
        ->assertJson(['status' => 'refused', 'error' => 'not_rollbackable']);

    expect(balanceNow($this->user))->toBe(4800);
});
