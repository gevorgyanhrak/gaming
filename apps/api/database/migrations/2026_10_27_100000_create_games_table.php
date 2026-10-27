<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            // Translatable fields: {"en": "...", "ru": "...", "hy": "..."}
            $table->json('name');
            $table->json('description');
            $table->json('rules');
            $table->string('icon_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedTinyInteger('min_players');
            $table->unsignedTinyInteger('max_players');
            // Playable: switched on once the game's engine ships. Visible: listed in the catalog.
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetimes();

            $table->index(['is_visible', 'sort_order']);
        });

        DB::statement('ALTER TABLE games ADD CONSTRAINT games_player_counts_check CHECK (min_players >= 1 AND max_players >= min_players)');
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
