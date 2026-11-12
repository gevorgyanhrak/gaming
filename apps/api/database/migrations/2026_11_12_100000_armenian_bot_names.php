<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The sample bots get Armenian names (owner, 2026-11-12): at the table they look like any other player.
 * A bot is renamed only while it still has its seeded name and the new name is free, so administrators'
 * own names stay.
 */
return new class extends Migration
{
    /** Seeded name => Armenian name (unchanged names are already Armenian). */
    private const NAMES = [
        'Alex' => 'Armen',
        'David' => 'Davit',
        'Anna' => 'Ani',
        'Igor' => 'Hayk',
        'Michael' => 'Mikayel',
        'Daniel' => 'Narek',
        'Nina' => 'Lilit',
        'Sergey' => 'Tigran',
        'Robert' => 'Vardan',
        'Elena' => 'Anahit',
        'Viktor' => 'Sevak',
    ];

    public function up(): void
    {
        $this->rename(self::NAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::NAMES));
    }

    /**
     * @param  array<string, string>  $names
     */
    private function rename(array $names): void
    {
        foreach ($names as $from => $to) {
            if (DB::table('bot_profiles')->where('name', $to)->doesntExist()) {
                DB::table('bot_profiles')->where('name', $from)->update(['name' => $to]);
            }
        }
    }
};
