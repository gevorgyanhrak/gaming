<?php

use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function registration(array $overrides = []): array
{
    return [
        'method' => 'email',
        'email' => 'Player@Example.com',
        'username' => 'player_one',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        ...$overrides,
    ];
}

it('registers with an email address, logs in and sends a code', function () {
    $this->postJson('/api/v1/auth/register', registration())
        ->assertCreated()
        ->assertJsonPath('data.username', 'player_one')
        ->assertJsonPath('data.email', 'player@example.com')
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonMissingPath('data.password');

    $user = User::query()->where('username', 'player_one')->firstOrFail();
    expect($user->profile->exists)->toBeTrue()
        ->and(codeSentTo('player@example.com'))->toMatch('/^\d{6}$/');

    // A follow-up request resolves the user from the session, as a real browser request would.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.username', 'player_one');
});

it('registers with a phone number only, normalized to E.164', function () {
    $this->postJson('/api/v1/auth/register', registration([
        'method' => 'phone',
        'phone' => '+374 77 123 456',
        'email' => 'ignored@example.com',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.phone', '+37477123456')
        ->assertJsonPath('data.email', null);

    Notification::assertSentOnDemand(
        VerificationCodeNotification::class,
        fn ($n, $channels, $notifiable) => ($notifiable->routes['sms'] ?? null) === '+37477123456',
    );
});

it('rejects invalid registrations', function (array $overrides, string $field) {
    User::factory()->create(['username' => 'Taken_Name', 'email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', registration($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'username taken (any case)' => [['username' => 'taken_name'], 'username'],
    'username reserved' => [['username' => 'Admin'], 'username'],
    'username format' => [['username' => 'bad name!'], 'username'],
    'username too short' => [['username' => 'ab'], 'username'],
    'email taken (any case)' => [['email' => 'TAKEN@example.com'], 'email'],
    'email invalid' => [['email' => 'not-an-email'], 'email'],
    'email missing' => [['email' => null], 'email'],
    'phone invalid' => [['method' => 'phone', 'phone' => '12345'], 'phone'],
    'phone missing' => [['method' => 'phone'], 'phone'],
    'unknown method' => [['method' => 'fax'], 'method'],
    'password unconfirmed' => [['password_confirmation' => 'different-1'], 'password'],
    'password weak' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
]);

it('rejects phone numbers from countries that are not allowed', function () {
    config(['platform.phone.allowed_countries' => ['GE']]);

    $this->postJson('/api/v1/auth/register', registration(['method' => 'phone', 'phone' => '+37477123456']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('refuses registration when it is closed', function () {
    config(['platform.registration.enabled' => false]);

    $this->postJson('/api/v1/auth/register', registration())->assertForbidden();

    expect(User::query()->count())->toBe(0);
});

it('reports username availability', function (string $username, bool $available) {
    User::factory()->create(['username' => 'existing']);

    $this->getJson('/api/v1/auth/username-available?username='.urlencode($username))
        ->assertOk()
        ->assertJsonPath('data.available', $available);
})->with([
    ['fresh_name', true],
    ['EXISTING', false],
    ['admin', false],
    ['no spaces', false],
]);
