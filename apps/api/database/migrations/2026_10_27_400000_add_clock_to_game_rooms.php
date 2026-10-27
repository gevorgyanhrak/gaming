<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            // Chess-clock modes: {remaining: ms per seat, since: ms timestamp}. Null for per-turn timers.
            $table->json('clock')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropColumn('clock');
        });
    }
};
