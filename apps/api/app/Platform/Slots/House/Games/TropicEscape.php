<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * TROPIC ESCAPE, a 5x3, 9-line slot. The banana is wild: it joins any run from the left and doubles it, and a run
 * of bananas pays as the top symbol (each line pays the better of the two). Suitcases are the scatter: 2+ anywhere
 * pay, 3+ start 45 free games on the bonus strips, and 3+ there add 45 more. Port of APEXSLOTS' tropic-escape engine
 * (MIT, github.com/Z1000k/open-source-slots), maths slotopol "bananasgobahamas" (MIT): the original 95.364527 %
 * reels. Rounds have the client's RoundResult shape: {baseSpin, bonus?: {spins, spinsPlayed, retriggers,
 * totalWin}, totalWin}.
 */
final class TropicEscape implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const LINES = 9;

    public const WILD = 'BANANA';

    public const SCATTER = 'SUITCASE';

    public const WILD_MULT = 2;

    public const SYMBOLS = ['BANANA', 'STRAWBERRY', 'MELON', 'PINEAPPLE', 'MANGO', 'COCONUT', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'NINE', 'SUITCASE'];

    /** The original Bananas Go Bahamas strips (slotopol symbol ids). */
    public const BASE = [
        [6, 11, 1, 9, 12, 8, 11, 7, 2, 11, 6, 8, 9, 4, 7, 9, 13, 10, 9, 3, 7, 5, 11, 8, 10, 11, 5, 10, 4, 7],
        [5, 8, 1, 7, 8, 11, 2, 9, 10, 12, 6, 11, 7, 3, 10, 9, 2, 8, 10, 5, 8, 11, 13, 12, 4, 10, 5, 8, 6, 3, 9, 12, 4, 8, 10, 6, 8, 10, 5, 12, 4, 9, 7],
        [11, 9, 1, 11, 4, 8, 9, 2, 8, 6, 10, 4, 7, 12, 2, 10, 11, 9, 12, 13, 9, 11, 5, 11, 3, 10, 9, 4, 7, 5, 10, 11, 2, 7, 6, 12, 7, 3, 11, 10, 6, 7, 3],
        [8, 11, 1, 9, 3, 7, 10, 4, 9, 2, 7, 6, 9, 5, 12, 10, 6, 12, 8, 5, 11, 13, 8, 6, 10, 2, 7, 6, 12, 5, 8, 11, 1, 9, 3, 6, 12, 4, 10, 5, 12, 10, 3],
        [8, 7, 1, 8, 4, 9, 5, 12, 10, 6, 11, 3, 7, 8, 2, 9, 5, 10, 11, 2, 12, 13, 5, 10, 4, 12, 6, 7, 2, 10, 8, 2, 12, 10, 3, 8, 10, 4, 12, 5, 8, 12, 2],
    ];

    /** The free games strips. */
    public const BONUS = [
        [6, 11, 1, 9, 12, 5, 11, 8, 2, 7, 6, 10, 12, 4, 7, 9, 13, 10, 9, 3, 7, 5, 10, 9, 2, 8, 11, 10, 3, 11, 6, 8, 4, 11, 9],
        [5, 8, 1, 7, 8, 10, 2, 9, 4, 11, 5, 8, 1, 7, 8, 11, 10, 12, 4, 10, 7, 3, 11, 6, 10, 4, 6, 11, 13, 12, 5, 9, 6, 11, 9],
        [11, 9, 1, 11, 4, 7, 8, 2, 6, 11, 9, 1, 11, 4, 7, 12, 10, 9, 8, 2, 12, 10, 5, 12, 3, 6, 7, 12, 13, 9, 10, 5, 3, 7, 5, 10],
        [8, 11, 1, 9, 3, 7, 10, 4, 7, 2, 8, 6, 10, 4, 5, 11, 10, 8, 6, 12, 11, 5, 7, 9, 10, 2, 5, 11, 13, 8, 6, 9, 3, 12, 7, 4],
        [8, 7, 1, 8, 4, 9, 12, 13, 5, 10, 11, 3, 7, 8, 2, 12, 13, 5, 10, 6, 7, 9, 6, 7, 4, 9, 2, 12, 13, 5, 10, 4, 11, 6, 3, 11],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2],
    ];

    /** Per line bet, for a run of 1…5. */
    public const LINE_PAY = [
        'BANANA' => [0, 10, 250, 2500, 9000],
        'STRAWBERRY' => [0, 2, 30, 120, 800],
        'MELON' => [0, 2, 30, 120, 800],
        'PINEAPPLE' => [0, 0, 20, 100, 400],
        'MANGO' => [0, 0, 20, 70, 250],
        'COCONUT' => [0, 0, 20, 70, 250],
        'ACE' => [0, 0, 10, 50, 120],
        'KING' => [0, 0, 10, 50, 120],
        'QUEEN' => [0, 0, 4, 30, 100],
        'JACK' => [0, 0, 4, 30, 100],
        'TEN' => [0, 0, 4, 30, 100],
        'NINE' => [0, 2, 4, 30, 100],
        'SUITCASE' => [0, 0, 0, 0, 0],
    ];

    /** Total bets and free games for 1…5 suitcases anywhere. */
    public const SCAT_PAY = [0, 2, 4, 20, 500];

    public const SCAT_FREESPIN = [0, 0, 45, 45, 45];

    public function id(): string
    {
        return 'tropic-escape';
    }

    /** One spin pays at most 9120 / 9 bets, but a free games session adds up spin by spin: the wallet's cap is wide. */
    public function maxWin(): int
    {
        return 100_000;
    }

    /**
     * @param  list<list<int>>  $strips
     * @return list<list<string>>
     */
    public static function reels(array $strips): array
    {
        return array_map(fn (array $strip) => array_map(fn (int $n) => self::SYMBOLS[$n - 1], $strip), $strips);
    }

    public function play(Closure $random): array
    {
        $base = self::evaluate(self::draw(self::reels(self::BASE), $random));
        $round = ['baseSpin' => $base];
        $total = $base['totalWin'];

        if ($base['freeSpinsWon'] > 0) {
            $bonusReels = self::reels(self::BONUS);
            $spins = [];
            $left = $base['freeSpinsWon'];
            $retriggers = 0;
            $bonusWin = 0.0;
            while ($left > 0) {
                $left--;
                $spin = self::evaluate(self::draw($bonusReels, $random));
                if ($spin['freeSpinsWon'] > 0) {
                    $left += $spin['freeSpinsWon'];
                    $retriggers++;
                }
                $bonusWin += $spin['totalWin'];
                $spins[] = $spin;
            }
            $round['bonus'] = ['spins' => $spins, 'spinsPlayed' => count($spins), 'retriggers' => $retriggers, 'totalWin' => $bonusWin];
            $total += $bonusWin;
        }
        $round['totalWin'] = $total;

        return ['round' => $round, 'total_win' => $total];
    }

    /**
     * @param  list<list<string>>  $reels
     * @param  Closure(int): int  $random
     * @return list<list<string>>
     */
    private static function draw(array $reels, Closure $random): array
    {
        $grid = [];
        foreach ($reels as $reel => $strip) {
            $stop = $random(count($strip));
            for ($row = 0; $row < self::ROWS; $row++) {
                $grid[$reel][$row] = $strip[($stop + $row) % count($strip)];
            }
        }

        return $grid;
    }

    /**
     * The pay of one line, as slotopol's ScanLined: [symbol, count, mult, pay per line bet], or null.
     *
     * @param  list<string>  $symbols  the line's symbol on each reel
     * @return array{string, int, int, int}|null
     */
    public static function lineWin(array $symbols): ?array
    {
        $syml = null;
        $numw = 0;
        $numl = self::REELS;
        $mw = 1;
        foreach ($symbols as $index => $symbol) {
            $x = $index + 1;
            if ($symbol === self::WILD) {
                if ($syml === null) {
                    $numw = $x;
                }
                $mw = self::WILD_MULT;
            } elseif ($syml === null) {
                $syml = $symbol;
            } elseif ($symbol !== $syml) {
                $numl = $x - 1;
                break;
            }
        }

        $payw = $numw >= 2 ? self::LINE_PAY[self::WILD][$numw - 1] : 0;
        $payl = $numl >= 2 && $syml !== null ? self::LINE_PAY[$syml][$numl - 1] : 0;
        if ($payl * $mw > $payw) {
            return [(string) $syml, $numl, $mw, $payl * $mw];
        }
        if ($payw > 0) {
            return [self::WILD, $numw, 1, $payw];
        }

        return null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array{grid: array<int, array<int, string>>, lineWins: list<array<string, mixed>>, scatterWin?: array<string, mixed>, freeSpinsWon: int, totalWin: float}
     */
    public static function evaluate(array $grid): array
    {
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $win = self::lineWin(array_map(fn (int $row, int $reel) => $grid[$reel][$row], $line, array_keys($line)));
            if ($win === null) {
                continue;
            }
            [$symbol, $count, $mult, $pay] = $win;
            $positions = [];
            for ($reel = 0; $reel < $count; $reel++) {
                $positions[] = [$reel, $line[$reel]];
            }
            $wins[] = ['line' => $index, 'symbol' => $symbol, 'count' => $count, 'mult' => $mult, 'amount' => $pay / self::LINES, 'positions' => $positions];
        }

        $scatters = [];
        foreach ($grid as $reel => $column) {
            foreach ($column as $row => $symbol) {
                if ($symbol === self::SCATTER) {
                    $scatters[] = [$reel, $row];
                }
            }
        }

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        $total = (float) array_sum(array_column($wins, 'amount'));
        $free = 0;
        if (count($scatters) >= 2) {
            $n = min(count($scatters), 5);
            $spin['scatterWin'] = ['count' => count($scatters), 'amount' => self::SCAT_PAY[$n - 1], 'positions' => $scatters];
            $total += self::SCAT_PAY[$n - 1];
            $free = self::SCAT_FREESPIN[$n - 1];
        }
        $spin['freeSpinsWon'] = $free;
        $spin['totalWin'] = $total;

        return $spin;
    }
}
