<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Only reference data that every environment needs belongs here; it must be safe
     * to run repeatedly, including in production.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            GameSeeder::class,
            GameModeSeeder::class,
            BotSeeder::class,
            ContentPageSeeder::class,
        ]);
    }
}
