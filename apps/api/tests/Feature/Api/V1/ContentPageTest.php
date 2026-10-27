<?php

use App\Models\ContentPage;
use App\Models\User;

it('shows published pages only', function () {
    ContentPage::factory()->create(['slug' => 'privacy', 'title' => ['en' => 'Privacy'], 'body' => ['en' => 'We respect it.']]);
    ContentPage::factory()->draft()->create(['slug' => 'terms']);

    $this->getJson('/api/v1/pages/privacy')->assertOk()->assertJsonPath('data.body', 'We respect it.');
    $this->getJson('/api/v1/pages/terms')->assertNotFound();

    $this->getJson('/api/v1/config')
        ->assertJsonPath('data.pages', [['slug' => 'privacy', 'title' => 'Privacy']])
        ->assertJsonPath('data.registration.requires_terms', false);
});

it('requires accepting published terms to register', function () {
    ContentPage::factory()->create(['slug' => ContentPage::TERMS]);
    $registration = [
        'method' => 'email', 'email' => 'new@example.com', 'username' => 'newcomer',
        'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
    ];

    $this->getJson('/api/v1/config')->assertJsonPath('data.registration.requires_terms', true);
    $this->postJson('/api/v1/auth/register', $registration)->assertJsonValidationErrors('accept_terms');
    $this->postJson('/api/v1/auth/register', [...$registration, 'accept_terms' => true])->assertCreated();

    expect(User::query()->where('username', 'newcomer')->sole()->terms_accepted_at)->not->toBeNull();
});
