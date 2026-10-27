<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Administrative roles and permissions. Idempotent.
 *
 * Regular players have no role. super_admin passes every check (see AppServiceProvider).
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    public const ROLES = [
        'super_admin' => [],
        'admin' => ['access admin', 'manage users', 'manage games', 'manage settings', 'view audit log'],
        'moderator' => ['access admin', 'manage users', 'view audit log'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::ROLES)->flatten()->unique();
        $permissions->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        foreach (self::ROLES as $role => $granted) {
            Role::findOrCreate($role, 'web')->syncPermissions($granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
