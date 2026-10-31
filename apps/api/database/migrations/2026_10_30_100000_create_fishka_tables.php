<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fishkas: the platform's free play currency. One wallet per user (the row that is locked for every
 * change) and an append-only ledger. Fishkas are never bought, sold, cashed out or sent to another user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fishka_wallets', function (Blueprint $table) {
            // RESTRICT: accounts with a ledger are never hard-deleted.
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('total_earned')->default(0);
            $table->unsignedBigInteger('total_spent')->default(0);
            // The calendar day (in the platform's Fishka time zone) of the last daily bonus.
            $table->date('daily_bonus_on')->nullable();
            $table->dateTime('refilled_at')->nullable();
            $table->datetimes();
        });

        Schema::create('fishka_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('description', 255)->nullable();
            // What the change belongs to, e.g. a game room's public id.
            $table->string('reference_type', 32)->nullable();
            $table->string('reference_id', 64)->nullable();
            // The administrator behind an admin change, and their reason.
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            // One-time operations (a bonus, a game's fee, a cash-out) carry a key; a repeat is refused.
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->dateTime('created_at');

            $table->index(['user_id', 'id']);
            $table->index(['type', 'created_at']);
        });

        DB::statement("ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus'))");
        DB::statement('ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_amount_check CHECK (amount <> 0 AND balance_after = balance_before + amount)');

        Schema::table('game_participants', function (Blueprint $table) {
            // Fishkas taken when the seat was taken: the entry fee, or a poker buy-in.
            $table->unsignedBigInteger('stake')->default(0)->after('bot_difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('game_participants', function (Blueprint $table) {
            $table->dropColumn('stake');
        });
        Schema::dropIfExists('fishka_transactions');
        Schema::dropIfExists('fishka_wallets');
    }
};
