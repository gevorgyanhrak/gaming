<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nardi has two modes now (TASK-046): the existing one is Short nardi. Its seeded name "Classic" says
 * nothing next to "Long nardi", so it is renamed — only while it still has the seeded name (an
 * administrator's own name is kept). The Long mode itself comes from GameModeSeeder (db:seed).
 */
return new class extends Migration
{
    private const SEEDED = ['en' => 'Classic', 'ru' => 'Классика', 'hy' => 'Դասական'];

    private const SHORT = ['en' => 'Short nardi', 'ru' => 'Короткие нарды', 'hy' => 'Կարճ նարդի'];

    public function up(): void
    {
        $this->rename(self::SEEDED, self::SHORT);
    }

    public function down(): void
    {
        $this->rename(self::SHORT, self::SEEDED);
    }

    /**
     * @param  array<string, string>  $from
     * @param  array<string, string>  $to
     */
    private function rename(array $from, array $to): void
    {
        $game = DB::table('games')->where('slug', 'nardi')->value('id');
        if ($game === null) {
            return;
        }

        $mode = DB::table('game_modes')->where('game_id', $game)->where('slug', 'classic')->first();
        if ($mode !== null && json_decode((string) $mode->name, true) == $from) {
            DB::table('game_modes')->where('id', $mode->id)->update(['name' => json_encode($to, JSON_UNESCAPED_UNICODE)]);
        }
    }
};
