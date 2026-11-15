<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-game ranking points (owner, 2026-11-16): one row per player — a person or an AI player — and game, and one
 * event per finished game and seat (unique), so points never change twice for the same game.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('bot_profile_id')->nullable()->constrained('bot_profiles');
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->datetimes();

            $table->unique(['game_id', 'user_id']);
            $table->unique(['game_id', 'bot_profile_id']);
            $table->index(['game_id', 'points']);
        });
        DB::statement('ALTER TABLE game_points ADD CONSTRAINT game_points_player_check CHECK ((user_id IS NULL) <> (bot_profile_id IS NULL))');

        Schema::create('game_point_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_points_id')->constrained('game_points');
            $table->string('room_public_id', 26);
            $table->unsignedTinyInteger('seat');
            $table->integer('delta');
            $table->dateTime('created_at');

            $table->unique(['room_public_id', 'seat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_point_events');
        Schema::dropIfExists('game_points');
    }
};
