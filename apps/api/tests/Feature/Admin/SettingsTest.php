<?php

use App\Filament\Pages\ManageSettings;
use App\Models\AuditLog;
use App\Models\User;
use App\Platform\Settings\Settings;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('falls back to configured defaults until a value is stored', function () {
    config(['app.name' => 'From Config']);

    expect(app(Settings::class)->get('general.platform_name'))->toBe('From Config');
});

it('stores changed values only and reports the changes', function () {
    $settings = app(Settings::class);

    $changes = $settings->update(['general.platform_name' => 'Nardi Club', 'access.registration_enabled' => true]);

    expect($changes)->toBe(['general.platform_name' => ['old' => config('app.name'), 'new' => 'Nardi Club']])
        ->and(app(Settings::class)->get('general.platform_name'))->toBe('Nardi Club');
});

it('validates values', function (array $values, string $error) {
    expect(fn () => app(Settings::class)->update($values))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($error));
})->with([
    'default language not offered' => [['general.supported_locales' => ['en', 'ru'], 'general.default_locale' => 'hy'], 'general.default_locale'],
    'unknown language' => [['general.supported_locales' => ['en', 'de']], 'general.supported_locales.1'],
    'unknown country' => [['access.phone_allowed_countries' => ['XX']], 'access.phone_allowed_countries.0'],
    'timeout too short' => [['matchmaking.default_bot_timeout_seconds' => 1], 'matchmaking.default_bot_timeout_seconds'],
]);

it('saves settings from the admin page and audits the change', function () {
    $admin = signInToAdmin('admin');

    Livewire::test(ManageSettings::class)
        ->fillForm(['general' => ['platform_name' => 'Nardi Club'], 'access' => ['registration_enabled' => false]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved.');

    expect(app(Settings::class)->get('general.platform_name'))->toBe('Nardi Club')
        ->and(app(Settings::class)->get('access.registration_enabled'))->toBeFalse();

    $log = AuditLog::query()->where('action', 'settings.updated')->sole();
    expect($log->actor_id)->toBe($admin->id)
        ->and($log->changes)->toHaveKeys(['general.platform_name', 'access.registration_enabled']);
});

it('shows validation errors on the admin form', function () {
    signInToAdmin('admin');

    Livewire::test(ManageSettings::class)
        ->fillForm(['general' => ['supported_locales' => ['en'], 'default_locale' => 'ru']])
        ->call('save')
        ->assertHasFormErrors(['general.default_locale']);
});

it('exposes only public settings to the app', function () {
    app(Settings::class)->update([
        'general.platform_name' => 'Nardi Club',
        'access.phone_allowed_countries' => ['AM'],
        'matchmaking.max_concurrent_games' => 3,
    ]);

    $body = $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('data.name', 'Nardi Club')->getContent();

    expect($body)->not->toContain('phone_allowed_countries')
        ->not->toContain('max_concurrent_games')
        ->not->toContain('session_lifetime');
});

it('applies registration, country and session settings', function () {
    app(Settings::class)->update([
        'access.registration_enabled' => false,
        'security.session_lifetime_minutes' => 30,
    ]);

    $this->postJson('/api/v1/auth/register', [])->assertForbidden();
    expect(config('session.lifetime'))->toBe(30);

    app(Settings::class)->update(['access.registration_enabled' => true, 'access.phone_allowed_countries' => ['GE']]);

    $this->postJson('/api/v1/auth/register', ['method' => 'phone', 'phone' => '+37477123456'])
        ->assertJsonValidationErrors('phone');
});

it('puts the app in maintenance mode, except for staff', function () {
    app(Settings::class)->update(['access.maintenance_mode' => true, 'access.maintenance_message' => 'Back at noon']);

    $this->getJson('/api/v1/games')->assertServiceUnavailable()->assertJson(['maintenance' => true, 'message' => 'Back at noon']);
    $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('data.maintenance.enabled', true);

    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole('moderator'));

    $this->getJson('/api/v1/games')->assertOk();
});
