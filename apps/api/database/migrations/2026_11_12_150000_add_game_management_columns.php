<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game Management (owner, 2026-11-12): a game can be in maintenance (shown, not playable, with a message) and
 * archived (removed from the platform, its history kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('maintenance')->default(false)->after('is_visible');
            $table->json('maintenance_message')->nullable()->after('maintenance');
            $table->dateTime('archived_at')->nullable()->after('maintenance_message');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['maintenance', 'maintenance_message', 'archived_at']);
        });
    }
};
