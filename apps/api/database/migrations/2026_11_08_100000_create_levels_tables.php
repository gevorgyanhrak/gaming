<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Levels and XP (ADR 0018): each player's total XP and level, the XP ledger, the reward per level, the
 * level-ups reached (the reward history; unclaimed ones are pending), bots' fixed levels, and the Fishka
 * ledger type for level rewards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->default(1)->after('status');
            $table->unsignedBigInteger('xp')->default(0)->after('level');
        });
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_level_check CHECK (level >= 1)');

        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('source', 16);
            // What the XP was for, e.g. a game room's public id or a day.
            $table->string('reference_type', 32)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->unsignedSmallInteger('level_before');
            $table->unsignedSmallInteger('level_after');
            // One game, one day: a repeat is refused.
            $table->string('idempotency_key', 100)->unique();
            $table->dateTime('created_at');

            $table->index(['user_id', 'id']);
        });
        DB::statement("ALTER TABLE xp_transactions ADD CONSTRAINT xp_transactions_source_check CHECK (source IN ('game', 'daily_bonus'))");
        DB::statement('ALTER TABLE xp_transactions ADD CONSTRAINT xp_transactions_amount_check CHECK (amount > 0 AND level_after >= level_before)');

        Schema::create('level_rewards', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->primary();
            $table->unsignedInteger('fishkas')->default(0);
            // Items from the code catalog (App\Platform\Levels\LevelItems): [{kind, key}, …].
            $table->json('items')->nullable();
            $table->boolean('is_milestone')->default(false);
            $table->datetimes();
        });
        DB::statement('ALTER TABLE level_rewards ADD CONSTRAINT level_rewards_level_check CHECK (level >= 2)');

        Schema::create('level_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('level');
            // The reward as configured when the level was reached.
            $table->unsignedInteger('fishkas');
            $table->json('items')->nullable();
            $table->boolean('is_milestone')->default(false);
            $table->foreignId('fishka_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('claimed_at')->nullable();
            $table->dateTime('created_at');

            $table->unique(['user_id', 'level']);
            $table->index(['user_id', 'claimed_at']);
        });

        Schema::table('bot_profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('level')->default(30)->after('avatar_path');
        });
        // Distinct, fairly high levels for the bots that exist (18–57; 37 and 40 are coprime).
        DB::statement('UPDATE bot_profiles SET level = 18 + MOD(id * 37, 40)');

        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement("ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'game_stake', 'game_payout', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus', 'level_reward'))");

        $now = now();
        $milestones = [
            5 => [2500, [['kind' => 'badge', 'key' => 'rising']]],
            10 => [5000, [['kind' => 'emoji_pack', 'key' => 'royal']]],
            15 => [7500, [['kind' => 'badge', 'key' => 'premium']]],
            20 => [10000, [['kind' => 'frame', 'key' => 'gold']]],
            25 => [12500, [['kind' => 'emoji_pack', 'key' => 'legend']]],
            30 => [25000, [['kind' => 'frame', 'key' => 'legend'], ['kind' => 'badge', 'key' => 'champion']]],
        ];
        DB::table('level_rewards')->insert(array_map(fn (int $level) => [
            'level' => $level,
            'fishkas' => $milestones[$level][0] ?? 250 + 50 * $level,
            'items' => isset($milestones[$level]) ? json_encode($milestones[$level][1]) : null,
            'is_milestone' => isset($milestones[$level]),
            'created_at' => $now,
            'updated_at' => $now,
        ], range(2, 30)));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement("ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'game_stake', 'game_payout', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus'))");
        Schema::table('bot_profiles', function (Blueprint $table) {
            $table->dropColumn('level');
        });
        Schema::dropIfExists('level_ups');
        Schema::dropIfExists('level_rewards');
        Schema::dropIfExists('xp_transactions');
        DB::statement('ALTER TABLE users DROP CHECK users_level_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['level', 'xp']);
        });
    }
};
