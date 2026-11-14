<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a game's page shows how many people are playing it now (owner, 2026-11-14): on by default, off for
 * Poker and Blackjack (busy by design: the number is noise there). Administrators change it per game.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('show_online')->default(true)->after('maintenance_message');
        });
        DB::table('games')->whereIn('slug', ['poker', 'blackjack'])->update(['show_online' => false]);
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('show_online');
        });
    }
};
