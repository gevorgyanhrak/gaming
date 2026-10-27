<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Two verified players for trying the app locally: player1@example.test and player2@example.test,
 * password "password". Never runs outside the local environment.
 *
 *     php artisan db:seed --class=LocalPlayersSeeder
 */
class LocalPlayersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalPlayersSeeder only runs in the local environment.');
        }

        foreach (['player1', 'player2'] as $username) {
            User::query()->firstOrCreate(['email' => "{$username}@example.test"], [
                'username' => $username,
                'password' => 'password',
                'email_verified_at' => now(),
            ]);
        }
    }
}
