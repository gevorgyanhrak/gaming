<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Matchmaking with a 10-second countdown and AI at 7 seconds for every game (owner, 2026-11-12): the platform
 * default becomes 7 seconds and games drop their own longer AI times. Admins can change both afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getSchemaBuilder()->hasTable('platform_settings')) {
            DB::table('platform_settings')->where('key', 'matchmaking.default_bot_timeout_seconds')->delete();
        }
        DB::table('games')->update(['bot_fallback_timeout_seconds' => null]);
        Cache::forget('platform_settings');   // Settings' cache of stored values
    }

    public function down(): void
    {
        // Earlier per-game times aren't kept.
    }
};
