<?php

namespace Tests\Fixtures\Slots;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/** A house slot for tests only: every round wins FixedHouseSlot::$win total bets. */
final class FixedHouseSlot implements HouseSlot
{
    public static float $win = 0.0;

    public static int $plays = 0;

    public function id(): string
    {
        return 'fixed';
    }

    public function maxWin(): int
    {
        return 100;
    }

    public function play(Closure $random): array
    {
        self::$plays++;

        return ['round' => ['totalWin' => self::$win, 'n' => self::$plays], 'total_win' => self::$win];
    }
}
