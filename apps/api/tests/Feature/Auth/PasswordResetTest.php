<?php

use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('answers identically whether or not an account exists', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    $known = $this->postJson('/api/v1/auth/password/forgot', ['login' => 'alice@example.com'])->assertAccepted();
    $unknown = $this->postJson('/api/v1/auth/password/forgot', ['login' => 'nobody@example.com'])->assertAccepted();

    expect($known->json())->toBe($unknown->json());
    Notification::assertSentOnDemandTimes(VerificationCodeNotification::class, 1);
});

it('resets the password with the code', function () {
    $user = User::factory()->unverified()->create(['email' => 'alice@example.com']);
    $user->createToken('device');

    $this->postJson('/api/v1/auth/password/forgot', ['login' => 'alice@example.com']);

    $this->postJson('/api/v1/auth/password/reset', [
        'login' => 'alice@example.com',
        'code' => codeSentTo('alice@example.com'),
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertOk();

    $user->refresh();
    expect(Hash::check('brand-new-pass-1', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['login' => 'alice@example.com', 'password' => 'password1'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', ['login' => 'alice@example.com', 'password' => 'brand-new-pass-1'])->assertOk();
});

it('rejects a wrong code or an unknown account', function (string $login) {
    User::factory()->create(['email' => 'alice@example.com']);
    $this->postJson('/api/v1/auth/password/forgot', ['login' => 'alice@example.com']);

    $this->postJson('/api/v1/auth/password/reset', [
        'login' => $login,
        'code' => wrongCode(codeSentTo('alice@example.com')),
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
})->with(['alice@example.com', 'nobody@example.com']);

it('throttles reset requests per destination, even for unknown accounts', function () {
    $this->postJson('/api/v1/auth/password/forgot', ['login' => 'nobody@example.com'])->assertAccepted();
    $this->postJson('/api/v1/auth/password/forgot', ['login' => 'nobody@example.com'])->assertTooManyRequests();
});

it('signs out existing sessions when the password changes', function () {
    $user = User::factory()->create(['email' => 'alice@example.com']);

    $this->postJson('/api/v1/auth/login', ['login' => 'alice@example.com', 'password' => 'password1'])->assertOk();
    $this->getJson('/api/v1/me')->assertOk();

    // Another device resets the password.
    $user->forceFill(['password' => 'changed-elsewhere-1'])->save();

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
