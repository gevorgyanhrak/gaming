<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AI always plays at the strongest level and only bots set to it are seated (ADR 0021), so bots left at
 * Easy or Medium never played. Every bot now plays every game it knows at the strongest level (owner,
 * 2026-11-13: 100 bots, all in play). Not reversible: the old per-game levels had no effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bot_game_profiles')->where('difficulty', '!=', 'hard')->update(['difficulty' => 'hard']);
    }

    public function down(): void
    {
        //
    }
};
