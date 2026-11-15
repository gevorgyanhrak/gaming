<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Casino shelf (Phase B): first in the catalog, with Blackjack and Poker. Games an administrator already moved
 * elsewhere stay where they are (only those still on the launch "card" shelf, or on none, move). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('game_categories')->where('slug', 'casino')->exists()) {
            return;
        }

        $now = now();
        $id = DB::table('game_categories')->insertGetId([
            'slug' => 'casino',
            'name' => json_encode(['en' => 'Casino', 'ru' => 'Казино', 'hy' => 'Կազինո'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'sort_order' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $card = DB::table('game_categories')->where('slug', 'card')->value('id');
        DB::table('games')->whereIn('slug', ['blackjack', 'poker'])
            ->where(fn ($q) => $q->whereNull('game_category_id')->when($card !== null, fn ($q) => $q->orWhere('game_category_id', $card)))
            ->update(['game_category_id' => $id]);
    }

    public function down(): void
    {
        $id = DB::table('game_categories')->where('slug', 'casino')->value('id');
        if ($id === null) {
            return;
        }
        $card = DB::table('game_categories')->where('slug', 'card')->value('id');
        DB::table('games')->where('game_category_id', $id)->update(['game_category_id' => $card]);
        DB::table('game_categories')->where('id', $id)->delete();
    }
};
