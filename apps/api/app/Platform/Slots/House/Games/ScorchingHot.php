<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * SCORCHING HOT, a 5x3, 5-line fruit slot: leftmost runs on a line pay (cherries from 2, the rest from 3), and 3+
 * stars anywhere pay the total bet on top. No wild, no free games. Port of APEXSLOTS' scorching-hot engine (MIT,
 * github.com/Z1000k/open-source-slots), maths slotopol "supremehot" (MIT): the original 95.655629 % reels. Rounds
 * have the client's RoundResult shape: {baseSpin: {grid, lineWins, scatterWin?, totalWin}, totalWin}.
 */
final class ScorchingHot implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const LINES = 5;

    public const SCATTER = 'STAR';

    public const SYMBOLS = ['SEVEN', 'MELON', 'GRAPES', 'PLUM', 'ORANGE', 'LEMON', 'CHERRY', 'STAR'];

    /** The original reels (lengths 25), slotopol symbol ids. */
    public const STRIPS = [
        [1, 4, 4, 4, 3, 1, 3, 3, 8, 6, 6, 6, 7, 7, 7, 6, 6, 2, 2, 5, 2, 5, 5, 5, 4],
        [1, 6, 6, 6, 2, 2, 1, 2, 7, 7, 7, 7, 8, 4, 4, 4, 4, 5, 5, 5, 3, 5, 3, 3, 6],
        [1, 6, 7, 7, 7, 8, 5, 5, 5, 1, 5, 2, 2, 4, 2, 4, 4, 4, 3, 3, 7, 3, 6, 6, 6],
        [1, 5, 5, 5, 5, 1, 5, 4, 4, 4, 8, 3, 3, 6, 6, 6, 7, 6, 7, 7, 7, 4, 4, 2, 2],
        [1, 4, 4, 6, 6, 6, 2, 2, 5, 8, 5, 5, 5, 8, 5, 4, 4, 4, 6, 1, 7, 7, 7, 3, 3],
    ];

    public const PAYLINES = [[1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2]];

    /** Per line bet, for a run of 1…5. */
    public const LINE_PAY = [
        'SEVEN' => [0, 0, 100, 1000, 5000],
        'MELON' => [0, 0, 50, 200, 500],
        'GRAPES' => [0, 0, 50, 200, 500],
        'PLUM' => [0, 0, 20, 50, 200],
        'ORANGE' => [0, 0, 20, 50, 200],
        'LEMON' => [0, 0, 20, 50, 200],
        'CHERRY' => [0, 5, 20, 50, 200],
    ];

    /** Total bets for 1…5 stars anywhere. */
    public const SCAT_PAY = [0, 0, 2, 10, 50];

    public function id(): string
    {
        return 'scorching-hot';
    }

    public function maxWin(): int
    {
        return 5000;
    }

    /** @return list<list<string>> */
    public static function reels(): array
    {
        return array_map(fn (array $strip) => array_map(fn (int $n) => self::SYMBOLS[$n - 1], $strip), self::STRIPS);
    }

    public function play(Closure $random): array
    {
        $grid = [];
        foreach (self::reels() as $reel => $strip) {
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
     * @return array<string, mixed>
     */
    public static function evaluate(array $grid): array
    {
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $first = $grid[0][$line[0]];
            $count = self::REELS;
            for ($reel = 1; $reel < self::REELS; $reel++) {
                if ($grid[$reel][$line[$reel]] !== $first) {
                    $count = $reel;
                    break;
                }
            }
            if ($first === self::SCATTER) {
                continue;
            }
            $pay = self::LINE_PAY[$first][$count - 1];
            if ($pay > 0) {
                $positions = [];
                for ($reel = 0; $reel < $count; $reel++) {
                    $positions[] = [$reel, $line[$reel]];
                }
                $wins[] = ['line' => $index, 'symbol' => $first, 'count' => $count, 'amount' => $pay / self::LINES, 'positions' => $positions];
            }
        }

        $stars = [];
        foreach ($grid as $reel => $column) {
            foreach ($column as $row => $symbol) {
                if ($symbol === self::SCATTER) {
                    $stars[] = [$reel, $row];
                }
            }
        }

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        $total = (float) array_sum(array_column($wins, 'amount'));
        if (count($stars) >= 3) {
            $amount = self::SCAT_PAY[min(count($stars), 5) - 1];
            $spin['scatterWin'] = ['count' => count($stars), 'amount' => $amount, 'positions' => $stars];
            $total += $amount;
        }
        $spin['totalWin'] = $total;

        return $spin;
    }
}
