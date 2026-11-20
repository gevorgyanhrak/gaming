<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HayBet item 12 (ADR 0028): a player who disconnects has a few seconds to return (disconnected_at); after that AI
 * takes their seat for the rest of the game (replaced_at) and they can't reclaim it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->dateTime('disconnected_at', 3)->nullable()->after('ai_controlled');
            $table->dateTime('replaced_at')->nullable()->after('disconnected_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->dropColumn(['disconnected_at', 'replaced_at']);
        });
    }
};
