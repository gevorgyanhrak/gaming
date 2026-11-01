<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Play groups (levels): who may sit at which tables, by Fishka balance, and what a table plays for.
 * One central set per kind: "chips" for games played with the players' own Fishkas as chips (poker:
 * the minimum bet is the big blind) and "stake" for the others (each player stakes the table's bet,
 * chosen within the group's range). The minimum balance is an entry check only; it is never charged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_groups', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 8);
            $table->string('slug', 32);
            $table->unsignedSmallInteger('position');
            $table->unsignedBigInteger('min_balance');
            $table->unsignedBigInteger('min_bet');
            $table->unsignedBigInteger('max_bet')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->datetimes();

            $table->unique(['kind', 'slug']);
        });
        DB::statement("ALTER TABLE play_groups ADD CONSTRAINT play_groups_kind_check CHECK (kind IN ('chips', 'stake'))");
        DB::statement('ALTER TABLE play_groups ADD CONSTRAINT play_groups_bets_check CHECK (min_bet >= 1 AND (max_bet IS NULL OR max_bet >= min_bet))');

        $now = now();
        $rows = [
            // Poker (owner's table, 2026-10-31).
            ['chips', 'beginner', 1, 100, 1, null],
            ['chips', 'intermediate', 2, 1_000, 5, null],
            ['chips', 'experienced', 3, 10_000, 10, null],
            ['chips', 'professional', 4, 50_000, 20, null],
            ['chips', 'master', 5, 500_000, 100, null],
            // Every other game.
            ['stake', 'beginner', 1, 500, 500, 2_000],
            ['stake', 'casual', 2, 5_000, 2_000, 10_000],
            ['stake', 'experienced', 3, 25_000, 10_000, 50_000],
            ['stake', 'professional', 4, 100_000, 50_000, 200_000],
            ['stake', 'master', 5, 500_000, 100_000, 500_000],
        ];
        DB::table('play_groups')->insert(array_map(fn (array $r) => [
            'kind' => $r[0], 'slug' => $r[1], 'position' => $r[2], 'min_balance' => $r[3], 'min_bet' => $r[4], 'max_bet' => $r[5],
            'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $rows));

        Schema::table('game_rooms', function (Blueprint $table) {
            $table->foreignId('play_group_id')->nullable()->after('game_mode_id')->constrained()->restrictOnDelete();
            // Stake games: what each player bets (the pot is stake × seats). 0 for poker.
            $table->unsignedBigInteger('stake')->default(0)->after('play_group_id');
        });

        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement("ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'game_stake', 'game_payout', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement("ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus'))");
        Schema::table('game_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('play_group_id');
            $table->dropColumn('stake');
        });
        Schema::dropIfExists('play_groups');
    }
};
