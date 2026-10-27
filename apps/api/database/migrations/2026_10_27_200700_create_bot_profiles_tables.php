<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('avatar_path')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->datetimes();
        });

        // Which games a bot plays, and how strongly.
        Schema::create('bot_game_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('difficulty', 10);
            $table->boolean('is_enabled')->default(true);
            $table->datetimes();

            $table->unique(['bot_profile_id', 'game_id']);
            $table->index(['game_id', 'difficulty', 'is_enabled']);
        });

        DB::statement("ALTER TABLE bot_game_profiles ADD CONSTRAINT bot_game_profiles_difficulty_check CHECK (difficulty IN ('easy', 'medium', 'hard'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_game_profiles');
        Schema::dropIfExists('bot_profiles');
    }
};
