<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HayBet item 6 (owner, 2026-11-20; ADR 0027): chip packages and payment orders, ready for a bank or payment provider
 * the owner connects later. Nothing here takes money by itself: an order is paid only when a registered provider
 * confirms it, and only then are its Fishkas credited (ledger type `purchase`, once per order).
 */
return new class extends Migration
{
    private const TYPES = "'registration_bonus', 'daily_login_bonus', 'game_entry_fee', 'game_entry_refund', 'game_stake', 'game_payout', 'poker_bet', 'poker_winning', 'admin_credit', 'admin_debit', 'insufficient_balance_bonus', 'level_reward', 'free_gift', 'wheel_prize', 'slot_bet', 'slot_win', 'slot_refund'";

    public function up(): void
    {
        Schema::create('chip_packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 60);
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('chips');
            $table->unsignedInteger('bonus_chips')->default(0);
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetimes();
        });

        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('chip_package_id')->constrained();
            // What was bought, as it was at the time (packages can change later).
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3);
            $table->unsignedInteger('chips');
            $table->string('provider', 40);
            $table->string('provider_reference', 120)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('client_key', 64);
            $table->string('failure_reason', 255)->nullable();
            $table->foreignId('fishka_transaction_id')->nullable()->constrained('fishka_transactions');
            $table->dateTime('paid_at')->nullable();
            $table->datetimes();

            $table->unique(['user_id', 'client_key']);
            $table->unique(['provider', 'provider_reference']);
            $table->index(['user_id', 'created_at']);
        });
        DB::statement("ALTER TABLE payment_orders ADD CONSTRAINT payment_orders_status_check CHECK (status IN ('pending', 'processing', 'paid', 'failed', 'cancelled', 'expired', 'refunded'))");

        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement('ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('.self::TYPES.", 'purchase'))");

        $now = now();
        // About 5,000 Fishkas a dollar, more for the larger packages (owner: "larger packages give better value").
        foreach ([
            ['starter', 'Starter', 200, 10000, 0, false],
            ['small', 'Small', 500, 25000, 2500, false],
            ['medium', 'Medium', 800, 40000, 6000, false],
            ['large', 'Large', 1000, 50000, 10000, true],
            ['max', 'Maximum', 1500, 75000, 22500, false],
        ] as $i => [$slug, $name, $price, $chips, $bonus, $recommended]) {
            DB::table('chip_packages')->insert([
                'slug' => $slug, 'name' => $name, 'price_cents' => $price, 'currency' => 'USD', 'chips' => $chips,
                'bonus_chips' => $bonus, 'is_recommended' => $recommended, 'is_enabled' => true, 'sort_order' => $i,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('fishka_transactions')->where('type', 'purchase')->exists() && throw new RuntimeException('Purchases exist: not dropping.');
        DB::statement('ALTER TABLE fishka_transactions DROP CHECK fishka_transactions_type_check');
        DB::statement('ALTER TABLE fishka_transactions ADD CONSTRAINT fishka_transactions_type_check CHECK (type IN ('.self::TYPES.'))');
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('chip_packages');
    }
};
