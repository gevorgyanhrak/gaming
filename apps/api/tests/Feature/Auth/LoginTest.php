<?php

use App\Models\User;

it('logs in with an email address in any case', function () {
    User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);

    $this->postJson('/api/v1/auth/login', ['login' => 'ALICE@example.com', 'password' => 'password1'])
        ->assertOk()
        ->assertJsonPath('data.username', 'alice');

    $this->getJson('/api/v1/me')->assertOk();
});

it('logs in with a phone number in any international format', function () {
    User::factory()->withPhone()->create(['username' => 'bob', 'phone' => '+37477123456']);

    $this->postJson('/api/v1/auth/login', ['login' => '+374 (77) 12-34-56', 'password' => 'password1'])
        ->assertOk()
        ->assertJsonPath('data.username', 'bob');
});

it('rejects wrong credentials with a generic message', function (string $login) {
    User::factory()->create(['email' => 'alice@example.com']);

    $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login' => __('auth.failed')]);

    $this->getJson('/api/v1/me')->assertUnauthorized();
})->with(['alice@example.com', 'nobody@example.com']);

it('rejects input that is neither an email nor a phone number', function () {
    $this->postJson('/api/v1/auth/login', ['login' => 'alice', 'password' => 'password1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('login');
});

it('does not log in banned or deactivated users', function (string $state) {
    User::factory()->{$state}()->create(['email' => 'alice@example.com']);

    $this->postJson('/api/v1/auth/login', ['login' => 'alice@example.com', 'password' => 'password1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login' => __('platform.auth.inactive')]);

    $this->getJson('/api/v1/me')->assertUnauthorized();
})->with(['banned', 'deactivated']);

it('locks out after repeated failures', function () {
    User::factory()->create(['email' => 'alice@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['login' => 'alice@example.com', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['login' => 'alice@example.com', 'password' => 'password1'])
        ->assertTooManyRequests();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('returns 401 JSON for guests', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});
