<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A player's favourite games and slots (the ♥ on a catalogue card). A row points at exactly one of them; the
 * uniques keep a favourite once per player, so a repeated tap changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('game_id')->nullable()->constrained();
            $table->foreignId('slot_game_id')->nullable()->constrained('slot_games');
            $table->datetimes();

            $table->unique(['user_id', 'game_id']);
            $table->unique(['user_id', 'slot_game_id']);
        });
        DB::statement('ALTER TABLE favorites ADD CONSTRAINT favorites_one_item_check CHECK ((game_id IS NULL) <> (slot_game_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
