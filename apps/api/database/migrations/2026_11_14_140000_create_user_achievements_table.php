<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Achievements people earned (catalog in code: App\Platform\Achievements\AchievementCatalog). Once per person, key
 * and game ("big win" is per game); `period` tells the day or week of a daily/weekly title. People only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('key', 40);
            $table->foreignId('game_id')->nullable()->constrained()->restrictOnDelete();
            // The game's id, or '' (MySQL's unique index lets NULLs repeat): what makes an award "once".
            $table->string('scope', 20)->default('');
            $table->string('period', 10)->default('');
            $table->unsignedBigInteger('value')->nullable();
            $table->dateTime('earned_at');
            $table->unique(['user_id', 'key', 'scope', 'period']);
            $table->index(['user_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
    }
};
