<?php

namespace App\Console\Commands;

use App\Platform\Auth\LoginIdentifier;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

#[Signature('app:role {login : Email address or phone number of the user} {role : super_admin, admin or moderator} {--revoke : Remove the role instead of granting it}')]
#[Description('Grant or revoke an administrative role')]
class AssignRole extends Command
{
    public function handle(): int
    {
        $role = (string) $this->argument('role');

        if (! array_key_exists($role, RoleSeeder::ROLES)) {
            $this->error("Unknown role [{$role}]. Available: ".implode(', ', array_keys(RoleSeeder::ROLES)).'.');

            return self::INVALID;
        }

        $user = LoginIdentifier::parse((string) $this->argument('login'))?->findUser();

        if ($user === null) {
            $this->error('No user found with that email address or phone number.');

            return self::FAILURE;
        }

        $this->option('revoke') ? $user->removeRole($role) : $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf('%s [%s] %s %s.', $this->option('revoke') ? 'Revoked' : 'Granted', $role, $this->option('revoke') ? 'from' : 'to', $user->username));

        return self::SUCCESS;
    }
}
