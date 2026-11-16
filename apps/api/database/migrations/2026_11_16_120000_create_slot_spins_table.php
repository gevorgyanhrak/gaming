<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * House slot rounds (ADR 0024): every round our server dealt, with the bet, the win and the result the client was
 * shown. A player's spin key is unique, so a retried request gets the same round back and is charged once; the
 * Fishkas themselves move through slot_transactions and FishkaWallets as for any provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_spins', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('slot_game_id')->constrained('slot_games');
            $table->foreignId('slot_session_id')->constrained('slot_sessions');
            $table->string('spin_key', 64);
            $table->unsignedInteger('bet');
            $table->unsignedBigInteger('win');
            $table->json('result');
            $table->datetimes();

            $table->unique(['user_id', 'spin_key']);
            $table->index(['slot_game_id', 'created_at']);
        });
        DB::statement('ALTER TABLE slot_spins ADD CONSTRAINT slot_spins_bet_check CHECK (bet >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_spins');
    }
};
