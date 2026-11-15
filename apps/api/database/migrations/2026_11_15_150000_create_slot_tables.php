<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Casino slots (Phase B, docs/research/casino-slots.md): providers, their games, the sessions we open for a player,
 * and every wallet call a provider makes. Fishkas move only through FishkaWallets (ledger types slot_bet, slot_win,
 * slot_refund); a provider's transaction id is unique per provider, so a replayed or repeated call moves nothing.
 */
return new class extends Migration
{
    private const TYPES = "'registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'game_stake', 'game_payout', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus', 'level_reward', 'free_gift', 'wheel_prize'";

    public function up(): void
    {
        Schema::create('slot_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->boolean('is_enabled')->default(false);
            $table->text('notes')->nullable();
            $table->datetimes();
        });

        Schema::create('slot_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_provider_id')->constrained('slot_providers');
            $table->string('provider_game_id', 120);
            $table->string('slug', 60)->unique();
            $table->json('name');
            $table->string('image_path')->nullable();
            $table->unsignedInteger('min_bet');
            $table->unsignedInteger('max_bet');
            $table->unsignedInteger('max_multiplier');
            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetimes();

            $table->unique(['slot_provider_id', 'provider_game_id']);
        });
        DB::statement('ALTER TABLE slot_games ADD CONSTRAINT slot_games_bets_check CHECK (min_bet >= 1 AND max_bet >= min_bet AND max_multiplier >= 1)');

        Schema::create('slot_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('slot_game_id')->constrained('slot_games');
            $table->char('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('last_played_at')->nullable();
            $table->datetimes();

            $table->index(['user_id', 'last_played_at']);
        });

        Schema::create('slot_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_provider_id')->constrained('slot_providers');
            $table->string('provider_tx_id', 120);
            $table->foreignId('slot_session_id')->nullable()->constrained('slot_sessions');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('round_id', 120)->nullable();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount');
            $table->string('status', 16);
            $table->string('error', 40)->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->string('reference_tx_id', 120)->nullable();
            $table->datetimes();

            $table->unique(['slot_provider_id', 'provider_tx_id']);
            $table->index(['slot_session_id', 'round_id']);
            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE slot_transactions ADD CONSTRAINT slot_transactions_type_check CHECK (type IN ('bet', 'win', 'rollback'))");
        DB::statement("ALTER TABLE slot_transactions ADD CONSTRAINT slot_transactions_status_check CHECK (status IN ('ok', 'refused'))");

        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement('ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('.self::TYPES.", 'slot_bet', 'slot_win', 'slot_refund'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement('ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('.self::TYPES.'))');
        Schema::dropIfExists('slot_transactions');
        Schema::dropIfExists('slot_sessions');
        Schema::dropIfExists('slot_games');
        Schema::dropIfExists('slot_providers');
    }
};
