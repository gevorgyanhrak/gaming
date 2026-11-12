<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Matchmaking no longer offers a bot game: AI fills the empty seats (owner, 2026-11-12). Games set to
 * "offer" now fill automatically; "off" (keep waiting for people) stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('games')->where('bot_fallback_mode', 'offer')->update(['bot_fallback_mode' => 'auto']);
        DB::statement("ALTER TABLE games ALTER COLUMN bot_fallback_mode SET DEFAULT 'auto'");
    }

    public function down(): void
    {
        // The earlier choice between offer and auto isn't kept; only the column default goes back.
        DB::statement("ALTER TABLE games ALTER COLUMN bot_fallback_mode SET DEFAULT 'offer'");
    }
};
