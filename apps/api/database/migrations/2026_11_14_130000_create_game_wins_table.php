<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's winnings from one game (what the ledger moved for them in that room, when it ended above zero) — the
 * Big Wins feed and the leaderboards read it. People only: AI has no wallet, so it never appears here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_wins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->string('room_public_id', 26);
            $table->unsignedBigInteger('amount');
            $table->dateTime('won_at');
            $table->unique(['room_public_id', 'user_id']);
            $table->index(['won_at', 'amount']);
            $table->index(['game_id', 'won_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_wins');
    }
};
