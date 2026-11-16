<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * The classic 3x3, 5-line fruit slots of APEXSLOTS (MIT, github.com/Z1000k/open-source-slots), maths from slotopol
 * (MIT): every reel is the same strip, three of a kind on a line pays, nothing carries over. A game may double
 * every line when the whole screen is one of its doubling fruits (slotopol FillMult). Rounds have the client's
 * RoundResult shape: {baseSpin: {grid, lineWins, totalWin, fillMult?}, totalWin}.
 */
abstract class ClassicThreeReels implements HouseSlot
{
    public const REELS = 3;

    public const ROWS = 3;

    public const LINES = 5;

    /** Row per reel for each line (slotopol BetLinesHot3x3). */
    public const PAYLINES = [[1, 1, 1], [0, 0, 0], [2, 2, 2], [0, 1, 2], [2, 1, 0]];

    /** @return list<string> slotopol's symbol ids 1…n, in order */
    abstract protected function symbols(): array;

    /** @return list<int> the dealt strip, as slotopol symbol ids */
    abstract protected function strip(): array;

    /** @return array<string, int> three of a kind, per line bet */
    abstract protected function linePay(): array;

    /** @return list<string> symbols whose full screen doubles the lines; none by default */
    protected function doublingFruits(): array
    {
        return [];
    }

    public function play(Closure $random): array
    {
        $symbols = $this->symbols();
        $strip = array_map(fn (int $n) => $symbols[$n - 1], $this->strip());
        $grid = [];
        for ($reel = 0; $reel < self::REELS; $reel++) {
            $stop = $random(count($strip));
            for ($row = 0; $row < self::ROWS; $row++) {
                $grid[$reel][$row] = $strip[($stop + $row) % count($strip)];
            }
        }
        $spin = $this->evaluate($grid);

        return ['round' => ['baseSpin' => $spin, 'totalWin' => $spin['totalWin']], 'total_win' => $spin['totalWin']];
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array<string, mixed>
     */
    public function evaluate(array $grid): array
    {
        $first = $grid[0][0];
        $fill = 1;
        if (in_array($first, $this->doublingFruits(), true)
            && count(array_unique(array_merge(...$grid))) === 1) {
            $fill = 2;
        }

        $pays = $this->linePay();
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
                'amount' => $pays[$symbol] * $fill / self::LINES,
                'positions' => array_map(fn (int $row, int $reel) => [$reel, $row], $line, array_keys($line)),
            ];
        }

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        if ($this->doublingFruits() !== []) {
            $spin['fillMult'] = $fill;
        }
        $spin['totalWin'] = (float) array_sum(array_column($wins, 'amount'));

        return $spin;
    }

    /**
     * The exact return of the dealt strip: every stop combination scored (for the RTP tests).
     */
    public function exactRtp(): float
    {
        $symbols = $this->symbols();
        $strip = array_map(fn (int $n) => $symbols[$n - 1], $this->strip());
        $length = count($strip);
        $total = 0.0;
        for ($a = 0; $a < $length; $a++) {
            for ($b = 0; $b < $length; $b++) {
                for ($c = 0; $c < $length; $c++) {
                    $grid = [];
                    foreach ([$a, $b, $c] as $reel => $stop) {
                        for ($row = 0; $row < self::ROWS; $row++) {
                            $grid[$reel][$row] = $strip[($stop + $row) % $length];
                        }
                    }
                    $total += $this->evaluate($grid)['totalWin'];
                }
            }
        }

        return $total / $length ** 3 * 100;
    }
}
