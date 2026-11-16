<?php

namespace App\Platform\Slots\House\Games;

/**
 * STELLAR SEVENS, a 3x3, 5-line fruit slot: three of a kind on a line pays; no wild, scatter or feature. Port of
 * APEXSLOTS' stellar-sevens engine, maths slotopol "alwayshot": the 95.504233 % reels (exact, see its test).
 */
final class StellarSevens extends ClassicThreeReels
{
    public const SYMBOLS = ['SEVEN', 'STAR', 'MELON', 'GRAPES', 'BELL', 'ORANGE', 'PLUM', 'LEMON', 'CHERRY'];

    /** The original Always Hot reels; all three reels are this strip. */
    public const STRIP = [1, 1, 1, 1, 8, 8, 8, 8, 8, 8, 6, 6, 6, 6, 6, 6, 2, 2, 2, 2, 5, 5, 5, 5, 5, 3, 3, 3, 3, 7, 7, 7, 7, 7, 7, 4, 4, 4, 4, 4, 9, 9, 9, 9, 9, 9];

    public const LINE_PAY = ['SEVEN' => 300, 'STAR' => 200, 'MELON' => 100, 'GRAPES' => 80, 'BELL' => 80, 'ORANGE' => 40, 'PLUM' => 40, 'LEMON' => 40, 'CHERRY' => 40];

    public function id(): string
    {
        return 'stellar-sevens';
    }

    public function maxWin(): int
    {
        return 300;
    }

    protected function symbols(): array
    {
        return self::SYMBOLS;
    }

    protected function strip(): array
    {
        return self::STRIP;
    }

    protected function linePay(): array
    {
        return self::LINE_PAY;
    }
}
