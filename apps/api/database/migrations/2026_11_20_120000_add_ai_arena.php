<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HayBet items 3–4 (owner, 2026-11-20): every AI player has one home game, the only game whose board it can appear
 * on, and earns its points there by really playing (the AI arena: whole games played by the real engine and bots on
 * the server, each one logged here). Ranking points move to a ten-times larger unit (a win 100, a loss 50): stored
 * totals and their events are multiplied by ten, so every standing stays exactly as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_profiles', function (Blueprint $table) {
            $table->foreignId('home_game_id')->nullable()->after('personality')->constrained('games')->nullOnDelete();
        });

        Schema::create('ai_arena_games', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('game_id')->constrained();
            $table->json('bots');          // bot_profile_id per seat
            $table->json('winners');       // seats; [] for a draw
            $table->string('reason', 40);
            $table->unsignedInteger('moves');
            $table->unsignedInteger('ms');
            $table->dateTime('created_at');

            $table->index(['game_id', 'created_at']);
        });

        DB::table('game_points')->update(['points' => DB::raw('points * 10')]);
        DB::table('game_point_events')->update(['delta' => DB::raw('delta * 10')]);
    }

    public function down(): void
    {
        DB::table('game_point_events')->update(['delta' => DB::raw('delta DIV 10')]);
        DB::table('game_points')->update(['points' => DB::raw('points DIV 10')]);
        Schema::dropIfExists('ai_arena_games');
        Schema::table('bot_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_game_id');
        });
    }
};
