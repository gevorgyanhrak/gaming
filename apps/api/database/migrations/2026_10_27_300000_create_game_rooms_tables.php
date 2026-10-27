<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            // Exposed in URLs and channel names instead of the numeric id.
            $table->ulid('public_id')->unique();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('game_mode_id')->constrained()->restrictOnDelete();
            $table->string('type', 12);
            $table->string('status', 10)->default('waiting');
            $table->string('invite_code', 12)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('seats');
            $table->boolean('has_bot')->default(false);
            // Mode options the game was started with (kept so replays use the same rules).
            $table->json('options')->nullable();
            // Number of the last accepted move; the authoritative version of the room's state.
            $table->unsignedInteger('state_version')->default(0);
            $table->string('end_reason', 20)->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->datetimes();

            $table->index(['status', 'game_id']);
            $table->index(['type', 'created_at']);
            $table->index('finished_at');
        });

        DB::statement("ALTER TABLE game_rooms ADD CONSTRAINT game_rooms_type_check CHECK (type IN ('matchmaking', 'private', 'bot'))");
        DB::statement("ALTER TABLE game_rooms ADD CONSTRAINT game_rooms_status_check CHECK (status IN ('waiting', 'active', 'finished', 'aborted'))");

        Schema::create('game_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');
            // Exactly one of user / bot (CHECK below). RESTRICT: MySQL forbids cascading actions on
            // columns used in CHECK constraints; players with history are never hard-deleted anyway.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bot_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bot_difficulty', 10)->nullable();
            // True while the room is waiting or active: indexed lookup of a player's current games.
            $table->boolean('is_active')->default(true);
            $table->string('result', 10)->nullable();
            $table->integer('score')->nullable();
            $table->datetimes();

            $table->unique(['game_room_id', 'seat']);
            $table->unique(['game_room_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });

        DB::statement('ALTER TABLE game_participants ADD CONSTRAINT game_participants_player_check CHECK ((user_id IS NULL) <> (bot_profile_id IS NULL))');
        DB::statement("ALTER TABLE game_participants ADD CONSTRAINT game_participants_result_check CHECK (result IS NULL OR result IN ('win', 'loss', 'draw', 'abandoned'))");

        // Append-only move log: the game history, the replay source and the recovery source.
        Schema::create('game_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->foreignId('participant_id')->nullable()->constrained('game_participants')->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('payload')->nullable();
            // Random numbers drawn while applying the move (dice, shuffles), so replays are exact.
            $table->json('rng')->nullable();
            $table->uuid('client_action_id')->nullable();
            $table->dateTime('created_at');

            $table->unique(['game_room_id', 'seq']);
            $table->unique(['game_room_id', 'client_action_id']);
        });

        Schema::create('user_game_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('played')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('draws')->default(0);
            $table->unsignedInteger('abandoned')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('best_streak')->default(0);
            // Reserved for ratings (post-MVP).
            $table->unsignedSmallInteger('rating')->default(1200);
            $table->dateTime('last_played_at')->nullable();
            $table->datetimes();

            $table->primary(['user_id', 'game_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_game_stats');
        Schema::dropIfExists('game_moves');
        Schema::dropIfExists('game_participants');
        Schema::dropIfExists('game_rooms');
    }
};
