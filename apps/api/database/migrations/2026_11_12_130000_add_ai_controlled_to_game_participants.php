<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's seat that AI plays for now: while they are away (disconnected), or to the end after they left
 * (AiTakeover, owner 2026-11-12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->boolean('ai_controlled')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->dropColumn('ai_controlled');
        });
    }
};
