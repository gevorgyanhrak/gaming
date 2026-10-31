<?php

use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Livewire;

it('bans a player, records the ban and audits it', function () {
    $admin = signInToAdmin('moderator');
    $player = User::factory()->create();
    $player->createToken('device');

    Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])
        ->callAction('ban', data: ['reason' => 'Cheating', 'expires_at' => null])
        ->assertNotified();

    $player->refresh();
    expect($player->status)->toBe(UserStatus::Banned)
        ->and($player->tokens()->count())->toBe(0)
        ->and($player->bans()->sole()->only(['reason', 'banned_by']))->toBe(['reason' => 'Cheating', 'banned_by' => $admin->id])
        ->and(AuditLog::query()->where('action', 'user.banned')->sole()->actor_id)->toBe($admin->id);
});

it('lifts a ban', function () {
    $admin = signInToAdmin('moderator');
    $player = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])
        ->callAction('ban', data: ['reason' => 'Spam'])
        ->callAction('unban');

    $player->refresh();
    expect($player->status)->toBe(UserStatus::Active)
        ->and($player->bans()->sole()->revoked_by)->toBe($admin->id);
});

it('deactivates and reactivates an account', function () {
    signInToAdmin('moderator');
    $player = User::factory()->create();

    $page = Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])->callAction('deactivate');
    expect($player->refresh()->status)->toBe(UserStatus::Deactivated);

    $page->callAction('activate');
    expect($player->refresh()->status)->toBe(UserStatus::Active);
});

it('lets only super admins moderate staff or change roles', function () {
    signInToAdmin('moderator');
    $otherModerator = User::factory()->create()->assignRole('moderator');

    Livewire::test(ViewUser::class, ['record' => $otherModerator->getRouteKey()])
        ->assertActionHidden('roles')
        ->callAction('ban', data: ['reason' => 'Abuse'])
        ->assertNotified('Only super admins can do this for staff accounts.');

    expect($otherModerator->refresh()->status)->toBe(UserStatus::Active);
});

it('changes roles as a super admin and audits it', function () {
    signInToAdmin('super_admin');
    $player = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])
        ->callAction('roles', data: ['roles' => ['moderator']]);

    expect($player->refresh()->hasRole('moderator'))->toBeTrue()
        // toEqual: MySQL's JSON type does not preserve object key order.
        ->and(AuditLog::query()->where('action', 'user.roles_changed')->sole()->changes)
        ->toEqual(['roles' => ['old' => [], 'new' => ['moderator']]]);
});

it('cannot moderate yourself', function () {
    $admin = signInToAdmin('super_admin');

    Livewire::test(ViewUser::class, ['record' => $admin->getRouteKey()])
        ->assertActionHidden('ban')
        ->assertActionHidden('deactivate');
});

it('reactivates users when a temporary ban expires', function () {
    signInToAdmin('moderator');
    $player = User::factory()->create();

    Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])
        ->callAction('ban', data: ['reason' => 'Cooling off', 'expires_at' => now()->addHour()->toDateTimeString()]);

    $this->artisan('app:lift-expired-bans')->assertSuccessful();
    expect($player->refresh()->status)->toBe(UserStatus::Banned);

    $this->travel(61)->minutes();
    $this->artisan('app:lift-expired-bans')->assertSuccessful();

    expect($player->refresh()->status)->toBe(UserStatus::Active)
        ->and(AuditLog::query()->where('action', 'user.ban_expired')->exists())->toBeTrue();
});

it('signs a banned player out of the app on their next request', function () {
    signInToAdmin('moderator');
    $player = User::factory()->create();
    $this->actingAs($player, 'web');

    Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])
        ->callAction('ban', data: ['reason' => 'Cheating']);

    $this->app['auth']->forgetGuards();
    $this->actingAs($player->refresh(), 'web')->getJson('/api/v1/me')->assertForbidden();
});

it('lets staff edit a player\'s details, asking to verify a new email again, audited', function () {
    $admin = signInToAdmin('moderator');
    $player = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

    Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])
        ->callAction('editDetails', data: [
            'username' => 'renamed_player', 'email' => 'New@Example.com', 'phone' => null,
            'locale' => 'hy', 'display_name' => 'Renamed', 'country' => 'am', 'bio' => 'Hello',
        ])
        ->assertNotified('Details saved.');

    $player->refresh();
    expect($player->only(['username', 'email', 'locale']))->toBe(['username' => 'renamed_player', 'email' => 'new@example.com', 'locale' => 'hy'])
        ->and($player->email_verified_at)->toBeNull()
        ->and($player->profile->only(['display_name', 'country', 'bio']))->toBe(['display_name' => 'Renamed', 'country' => 'AM', 'bio' => 'Hello'])
        ->and(AuditLog::query()->where('action', 'user.updated')->sole())
        ->actor_id->toBe($admin->id);
});

it('refuses details that clash with another account or leave no way to sign in', function () {
    signInToAdmin('moderator');
    User::factory()->create(['username' => 'taken_name']);
    $player = User::factory()->create(['username' => 'keeper']);

    Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()])
        ->callAction('editDetails', data: ['username' => 'taken_name', 'email' => $player->email])
        ->callAction('editDetails', data: ['username' => 'keeper', 'email' => null, 'phone' => null]);

    expect($player->refresh()->username)->toBe('keeper')
        ->and($player->email)->not->toBeNull();
});
