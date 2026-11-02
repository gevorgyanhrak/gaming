<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily bonus's streak (TASK-044): which day (1–5) the last claimed bonus was. Additive; existing
 * wallets start at 0 (their next bonus is day 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fishka_wallets', function (Blueprint $table) {
            $table->unsignedTinyInteger('daily_streak')->default(0)->after('daily_bonus_on');
        });
    }

    public function down(): void
    {
        Schema::table('fishka_wallets', function (Blueprint $table) {
            $table->dropColumn('daily_streak');
        });
    }
};
