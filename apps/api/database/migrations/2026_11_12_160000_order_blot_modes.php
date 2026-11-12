<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Blot's modes in order of length: Quick (101), Standard (151), Bazar blot (301, the existing one). */
return new class extends Migration
{
    public function up(): void
    {
        $blot = DB::table('games')->where('slug', 'blot')->value('id');
        if ($blot !== null) {
            DB::table('game_modes')->where('game_id', $blot)->where('slug', 'bazar')->update(['sort_order' => 30]);
        }
    }

    public function down(): void {}
};
