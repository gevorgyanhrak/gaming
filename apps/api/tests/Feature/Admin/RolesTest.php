<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('seeds roles idempotently', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(['admin', 'moderator', 'super_admin']);
});

it('grants admin access by role only', function (?string $role, bool $canAccess) {
    $user = User::factory()->create();

    if ($role !== null) {
        $user->assignRole($role);
    }

    expect($user->canAccessAdmin())->toBe($canAccess);
    $this->actingAs($user)->getJson('/api/v1/me')->assertJsonPath('data.can_access_admin', $canAccess);
})->with([
    'player' => [null, false],
    'moderator' => ['moderator', true],
    'admin' => ['admin', true],
    'super admin' => ['super_admin', true],
]);

it('limits moderators to their permissions', function () {
    $moderator = User::factory()->create()->assignRole('moderator');

    expect($moderator->can('manage users'))->toBeTrue()
        ->and($moderator->can('manage settings'))->toBeFalse();
});

it('lets super admins pass every check', function () {
    Gate::define('anything', fn () => false);
    $root = User::factory()->create()->assignRole('super_admin');

    expect($root->can('anything'))->toBeTrue();
});

it('grants and revokes roles from the command line', function () {
    $user = User::factory()->create(['email' => 'boss@example.com']);

    $this->artisan('app:role', ['login' => 'boss@example.com', 'role' => 'admin'])->assertSuccessful();
    expect($user->fresh()->hasRole('admin'))->toBeTrue();

    $this->artisan('app:role', ['login' => 'boss@example.com', 'role' => 'admin', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->hasRole('admin'))->toBeFalse();

    $this->artisan('app:role', ['login' => 'boss@example.com', 'role' => 'emperor'])->assertExitCode(2);
    $this->artisan('app:role', ['login' => 'nobody@example.com', 'role' => 'admin'])->assertFailed();
});
