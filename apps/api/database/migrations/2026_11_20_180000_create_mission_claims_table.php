<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HayBet item 9: daily missions and weekly challenges (Platform/Missions). Progress is computed from the games that
 * earned XP; a claimed reward is one row here (once per mission and period) and a `mission_reward` ledger entry.
 */
return new class extends Migration
{
    private const TYPES = "'registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'game_stake', 'game_payout', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus', 'level_reward', 'free_gift', 'wheel_prize', 'slot_bet', 'slot_win', 'slot_refund', 'purchase'";

    public function up(): void
    {
        Schema::create('mission_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('mission', 32);
            $table->string('period', 16); // 2026-11-20 or 2026-W47
            $table->unsignedInteger('reward');
            $table->dateTime('created_at');

            $table->unique(['user_id', 'mission', 'period']);
        });

        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement('ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('.self::TYPES.", 'mission_reward'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement('ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('.self::TYPES.'))');
        Schema::dropIfExists('mission_claims');
    }
};
