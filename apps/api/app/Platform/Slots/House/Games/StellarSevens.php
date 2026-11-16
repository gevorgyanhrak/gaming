<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * STELLAR SEVENS, a 3x3, 5-line fruit slot: three of a kind on a line pays; no wild, scatter or feature. A PHP
 * port of APEXSLOTS' open-source engine (MIT, github.com/Z1000k/open-source-slots, stellar-sevens/src/engine),
 * whose maths is slotopol's "alwayshot" (MIT): the same strip, lines and pays, so its exact RTP of 95.504233 %
 * holds (StellarSevensTest enumerates every stop to prove it). The round has the client's RoundResult shape.
 */
final class StellarSevens implements HouseSlot
{
    public const REELS = 3;

    public const ROWS = 3;

    public const LINES = 5;

    /** slotopol's symbol ids 1…9, in order. */
    public const SYMBOLS = ['SEVEN', 'STAR', 'MELON', 'GRAPES', 'BELL', 'ORANGE', 'PLUM', 'LEMON', 'CHERRY'];

    /** The 95.504233 % set (the original Always Hot reels); all three reels are this strip. */
    public const STRIP = [1, 1, 1, 1, 8, 8, 8, 8, 8, 8, 6, 6, 6, 6, 6, 6, 2, 2, 2, 2, 5, 5, 5, 5, 5, 3, 3, 3, 3, 7, 7, 7, 7, 7, 7, 4, 4, 4, 4, 4, 9, 9, 9, 9, 9, 9];

    /** Row per reel for each line. */
    public const PAYLINES = [[1, 1, 1], [0, 0, 0], [2, 2, 2], [0, 1, 2], [2, 1, 0]];

    /** Three of a kind, per line bet (÷ LINES for total bets). */
    public const LINE_PAY = ['SEVEN' => 300, 'STAR' => 200, 'MELON' => 100, 'GRAPES' => 80, 'BELL' => 80, 'ORANGE' => 40, 'PLUM' => 40, 'LEMON' => 40, 'CHERRY' => 40];

    public function id(): string
    {
        return 'stellar-sevens';
    }

    public function maxWin(): int
    {
        return 300;
    }

    public function play(Closure $random): array
    {
        $strip = array_map(fn (int $n) => self::SYMBOLS[$n - 1], self::STRIP);
        $grid = [];
        for ($reel = 0; $reel < self::REELS; $reel++) {
            $stop = $random(count($strip));
            for ($row = 0; $row < self::ROWS; $row++) {
                $grid[$reel][$row] = $strip[($stop + $row) % count($strip)];
            }
        }
        $spin = self::evaluate($grid);

        return ['round' => ['baseSpin' => $spin, 'totalWin' => $spin['totalWin']], 'total_win' => $spin['totalWin']];
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array{grid: array<int, array<int, string>>, lineWins: list<array{line: int, symbol: string, count: int, amount: float, positions: list<array{int, int}>}>, totalWin: float}
     */
    public static function evaluate(array $grid): array
    {
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $symbol = $grid[0][$line[0]];
            if ($grid[1][$line[1]] !== $symbol || $grid[2][$line[2]] !== $symbol) {
                continue;
            }
            $wins[] = [
                'line' => $index,
                'symbol' => $symbol,
                'count' => self::REELS,
                'amount' => self::LINE_PAY[$symbol] / self::LINES,
                'positions' => array_map(fn (int $row, int $reel) => [$reel, $row], $line, array_keys($line)),
            ];
        }

        return ['grid' => $grid, 'lineWins' => $wins, 'totalWin' => (float) array_sum(array_column($wins, 'amount'))];
    }
}
