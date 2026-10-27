<?php

use App\Models\User;
use App\Models\VerificationCode;
use App\Platform\Sms\LogSmsGateway;
use App\Platform\Sms\SmsGateway;
use Illuminate\Support\Facades\Notification;

it('verifies an email address with the code that was sent', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'alice@example.com']);
    $this->actingAs($user);

    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email'])->assertAccepted();

    $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => codeSentTo('alice@example.com')])
        ->assertOk()
        ->assertJsonPath('data.email_verified', true);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects wrong codes and locks the code after too many attempts', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'alice@example.com']);
    $this->actingAs($user);
    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email']);
    $code = codeSentTo('alice@example.com');

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => wrongCode($code)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => $code])->assertUnprocessable();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('rejects expired codes', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'alice@example.com']);
    $this->actingAs($user);
    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email']);

    $this->travel(11)->minutes();

    $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => codeSentTo('alice@example.com')])
        ->assertUnprocessable();
});

it('stores codes only as hashes and replaces outstanding codes', function () {
    Notification::fake();
    $this->actingAs(User::factory()->unverified()->create(['email' => 'alice@example.com']));

    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email']);
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email']);

    $record = VerificationCode::query()->sole();
    expect($record->code_hash)->not->toContain(codeSentTo('alice@example.com'))
        ->and(strlen($record->code_hash))->toBe(64);
});

it('throttles resending with a cooldown', function () {
    Notification::fake();
    $this->actingAs(User::factory()->unverified()->create());

    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email'])->assertAccepted();
    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('refuses to send for a missing or already verified contact', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email'])->assertJsonValidationErrors('channel');
    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'phone'])->assertJsonValidationErrors('channel');
});

it('delivers phone codes by SMS through the configured gateway', function () {
    $gateway = new class implements SmsGateway
    {
        /** @var list<array{string, string}> */
        public array $sent = [];

        public function send(string $to, string $message): void
        {
            $this->sent[] = [$to, $message];
        }
    };
    $this->app->instance(SmsGateway::class, $gateway);
    $this->actingAs(User::factory()->withPhone()->unverified()->create(['phone' => '+37477123456']));

    $this->postJson('/api/v1/auth/verification/send', ['channel' => 'phone'])->assertAccepted();

    expect($gateway->sent)->toHaveCount(1)
        ->and($gateway->sent[0][0])->toBe('+37477123456')
        ->and($gateway->sent[0][1])->toMatch('/\d{6}/');
});

it('never runs the log SMS driver in production', function () {
    $this->app['env'] = 'production';

    (new LogSmsGateway)->send('+37477123456', 'code 123456');
})->throws(RuntimeException::class);
