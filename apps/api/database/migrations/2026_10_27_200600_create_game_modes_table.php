<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 50);
            $table->json('name');
            $table->json('description')->nullable();
            $table->unsignedTinyInteger('min_players');
            $table->unsignedTinyInteger('max_players');
            // Game-specific options (time control, points to win, variant), validated by the game's
            // engine definition once it exists.
            $table->json('config')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('matchmaking_enabled')->default(true);
            $table->boolean('private_rooms_enabled')->default(true);
            $table->boolean('bots_allowed')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetimes();

            $table->unique(['game_id', 'slug']);
            $table->index(['game_id', 'is_enabled', 'sort_order']);
        });

        DB::statement('ALTER TABLE game_modes ADD CONSTRAINT game_modes_player_counts_check CHECK (min_players >= 1 AND max_players >= min_players)');
    }

    public function down(): void
    {
        Schema::dropIfExists('game_modes');
    }
};
