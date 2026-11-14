<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blot's full game to 301 comes first, so the lobby's PLAY and preselected mode start it (owner, 2026-11-14:
 * a win needs 301); Standard (151) and Quick (101) follow.
 */
return new class extends Migration
{
    private const ORDER = ['bazar' => 10, 'standard' => 20, 'quick' => 30];

    public function up(): void
    {
        $this->order(self::ORDER);
    }

    public function down(): void
    {
        $this->order(['quick' => 10, 'standard' => 20, 'bazar' => 30]);
    }

    /**
     * @param  array<string, int>  $order
     */
    private function order(array $order): void
    {
        $blot = DB::table('games')->where('slug', 'blot')->value('id');
        foreach ($order as $slug => $sort) {
            DB::table('game_modes')->where('game_id', $blot)->where('slug', $slug)->update(['sort_order' => $sort]);
        }
    }
};
