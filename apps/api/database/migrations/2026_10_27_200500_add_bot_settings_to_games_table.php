<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('bots_enabled')->default(true)->after('is_visible');
            // What happens when no human opponent is found in time: auto (start a bot game),
            // offer (ask the player) or off (keep waiting).
            $table->string('bot_fallback_mode', 10)->default('offer')->after('bots_enabled');
            // Null means the platform default (setting matchmaking.default_bot_timeout_seconds).
            $table->unsignedSmallInteger('bot_fallback_timeout_seconds')->nullable()->after('bot_fallback_mode');
        });

        DB::statement("ALTER TABLE games ADD CONSTRAINT games_bot_fallback_mode_check CHECK (bot_fallback_mode IN ('auto', 'offer', 'off'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE games DROP CHECK games_bot_fallback_mode_check');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['bots_enabled', 'bot_fallback_mode', 'bot_fallback_timeout_seconds']);
        });
    }
};
