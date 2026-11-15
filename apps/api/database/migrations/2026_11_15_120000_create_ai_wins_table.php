<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An AI player's winnings from one real game (owner 2026-11-15: Big Wins shows AI activity when people are few,
 * clearly marked). The amount is what the game gave that seat: its share of a stake pot (which goes back to the
 * house) or the chips it gained at a chip table. Kept apart from game_wins so leaderboards and achievements stay
 * people only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_wins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->string('room_public_id', 26);
            $table->unsignedTinyInteger('seat');
            $table->unsignedBigInteger('amount');
            $table->dateTime('won_at');
            $table->unique(['room_public_id', 'seat']);
            $table->index('won_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_wins');
    }
};
