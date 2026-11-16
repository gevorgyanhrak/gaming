<?php

namespace App\Platform\Slots\House\Games;

/**
 * NITRO HOT, a 3x3, 5-line fruit slot: three of a kind pays, and a full screen of plums, oranges, lemons or cherries
 * doubles every line. Port of APEXSLOTS' nitro-hot engine, maths slotopol "ultrahot": the 95.658244 % reels.
 */
final class NitroHot extends ClassicThreeReels
{
    public const SYMBOLS = ['SEVEN', 'STAR', 'BAR', 'PLUM', 'ORANGE', 'LEMON', 'CHERRY', 'X'];

    /** The original Ultra Hot reels; all three reels are this strip. */
    public const STRIP = [8, 8, 8, 6, 6, 6, 1, 3, 2, 4, 4, 4, 7, 7, 7, 2, 8, 8, 8, 3, 3, 3, 6, 6, 6, 2, 5, 5, 5, 4, 4, 4, 7, 7, 7, 1, 2, 8, 3, 5, 5, 5, 2];

    public const LINE_PAY = ['SEVEN' => 750, 'STAR' => 200, 'BAR' => 60, 'PLUM' => 40, 'ORANGE' => 40, 'LEMON' => 40, 'CHERRY' => 40, 'X' => 5];

    public function id(): string
    {
        return 'nitro-hot';
    }

    public function maxWin(): int
    {
        return 202;
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

    protected function doublingFruits(): array
    {
        return ['PLUM', 'ORANGE', 'LEMON', 'CHERRY'];
    }
}
