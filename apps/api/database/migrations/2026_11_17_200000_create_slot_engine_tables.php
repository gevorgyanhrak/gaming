<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Engine slots (ADR 0026): each game's studio and description from the engine, and each player's game state between
 * spins (free games, cascades, expanding symbols) with the round its free spins belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot_games', function (Blueprint $table) {
            $table->string('studio', 60)->nullable()->after('name');
            $table->json('meta')->nullable()->after('studio');
            $table->index('studio');
        });

        Schema::create('slot_engine_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('slot_game_id')->constrained('slot_games');
            $table->json('state');
            // The paid spin whose free games / cascade falls are still being played (their wins join its round).
            $table->string('round_key', 64)->nullable();
            $table->unsignedInteger('round_bet')->default(0);
            $table->datetimes();

            $table->unique(['user_id', 'slot_game_id']);
        });

        // A free game or cascade fall is a spin with no bet (it belongs to the paid spin's round).
        DB::statement('ALTER TABLE slot_spins DROP CHECK slot_spins_bet_check');
        DB::statement('ALTER TABLE slot_spins ADD CONSTRAINT slot_spins_bet_check CHECK (bet >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE slot_spins DROP CHECK slot_spins_bet_check');
        DB::statement('ALTER TABLE slot_spins ADD CONSTRAINT slot_spins_bet_check CHECK (bet >= 1)');
        Schema::dropIfExists('slot_engine_states');
        Schema::table('slot_games', function (Blueprint $table) {
            $table->dropIndex(['studio']);
            $table->dropColumn(['studio', 'meta']);
        });
    }
};
