<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * SAMURAI, a 5x3, 20-line slot. The samurai is wild (no multiplier) and the top symbol: a run of samurai pays as
 * his own line, each line paying the better; runs pay from 3. 3+ gongs anywhere pay 2/20/200 total bets and start
 * 10 free games on their own strips (3+ there add 10 more), where every reel that shows a samurai is wild top to
 * bottom. Port of APEXSLOTS' samurai engine (MIT, github.com/Z1000k/open-source-slots), maths slotopol "katana"
 * (MIT): 95.388785 % (exact, see its test). Spins carry `scatterHit`, `freeGame` and `wildReels` as the client
 * expects.
 */
final class Samurai implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const WILD = 'SAMURAI';

    public const SCATTER = 'GONG';

    public const SYMBOLS = ['SAMURAI', 'DRAGON', 'TIGER', 'ONI', 'HELMET', 'FAN', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'GONG'];

    /** The set nearest the provider's published 94.95 % (slotopol's label 95.385457). */
    public const BASE = [
        [11, 3, 6, 1, 5, 11, 3, 4, 6, 7, 9, 11, 2, 6, 5, 10, 8, 9, 10, 7, 11, 4, 10, 9, 5, 12, 8, 2, 4, 3, 10, 7, 8, 2],
        [9, 7, 2, 5, 11, 7, 3, 9, 5, 2, 9, 10, 6, 3, 2, 9, 12, 10, 11, 1, 5, 11, 4, 8, 10, 4, 8, 7, 11, 4, 6, 8, 10, 6, 3],
        [9, 12, 6, 7, 4, 6, 3, 8, 9, 3, 1, 10, 4, 2, 9, 3, 2, 8, 5, 10, 6, 11, 9, 7, 11, 5, 4, 11, 7, 5, 10, 8, 11, 2, 10],
        [11, 9, 4, 7, 5, 2, 3, 6, 4, 11, 8, 6, 11, 7, 8, 4, 12, 6, 7, 9, 3, 11, 5, 10, 9, 8, 2, 10, 3, 1, 10, 9, 2, 10, 5],
        [4, 3, 10, 2, 6, 8, 7, 2, 9, 11, 8, 4, 2, 6, 5, 11, 10, 8, 5, 3, 7, 9, 11, 5, 7, 1, 9, 11, 10, 4, 12, 3, 10, 6],
    ];

    public const FREE = [
        [7, 3, 1, 4, 5, 6, 3, 7, 5, 4, 7, 11, 4, 8, 10, 3, 9, 12, 8, 10, 6, 9, 11, 12, 5, 2, 10, 9, 2, 11, 6, 8, 2],
        [11, 2, 6, 9, 8, 7, 4, 10, 6, 4, 1, 8, 6, 3, 5, 9, 2, 5, 10, 7, 3, 8, 10, 9, 11, 7, 5, 4, 12, 11, 3, 2, 12],
        [12, 11, 5, 7, 1, 4, 5, 7, 6, 4, 8, 10, 6, 12, 9, 4, 11, 2, 8, 10, 3, 5, 10, 3, 11, 2, 9, 8, 7, 3, 9, 2, 6],
        [11, 7, 2, 6, 7, 1, 2, 4, 9, 10, 8, 9, 12, 5, 4, 8, 7, 11, 8, 3, 4, 11, 5, 10, 3, 9, 6, 3, 2, 6, 12, 5, 10],
        [11, 12, 5, 6, 9, 8, 5, 10, 3, 9, 12, 2, 4, 3, 2, 7, 6, 4, 9, 10, 4, 1, 2, 7, 8, 11, 3, 6, 7, 11, 10, 5, 8],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [0, 0, 1, 2, 2], [2, 2, 1, 0, 0], [1, 0, 0, 0, 1], [1, 2, 2, 2, 1], [0, 1, 1, 1, 0],
        [2, 1, 1, 1, 2], [1, 0, 1, 2, 1], [1, 2, 1, 0, 1], [0, 2, 0, 2, 0], [2, 0, 2, 0, 2],
        [0, 1, 0, 1, 0], [2, 1, 2, 1, 2], [0, 2, 2, 2, 2], [2, 0, 0, 0, 0], [1, 1, 0, 1, 1],
    ];

    public const LINE_PAY = [
        'SAMURAI' => [0, 0, 100, 500, 1000],
        'DRAGON' => [0, 0, 80, 250, 800],
        'TIGER' => [0, 0, 40, 200, 600],
        'ONI' => [0, 0, 40, 200, 600],
        'HELMET' => [0, 0, 30, 150, 500],
        'FAN' => [0, 0, 30, 150, 500],
        'ACE' => [0, 0, 10, 50, 200],
        'KING' => [0, 0, 10, 30, 150],
        'QUEEN' => [0, 0, 10, 30, 150],
        'JACK' => [0, 0, 10, 20, 100],
        'TEN' => [0, 0, 10, 20, 100],
        'GONG' => [0, 0, 0, 0, 0],
    ];

    public const SCAT_PAY = [0, 0, 2, 20, 200];

    public const SCAT_FREESPIN = [0, 0, 10, 10, 10];

    public function id(): string
    {
        return 'samurai';
    }

    /** A free games session adds up spin by spin: the wallet's cap is wide. */
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
            $freeReels = self::reels(self::FREE);
            $spins = [];
            $left = $base['freeSpinsWon'];
            $retriggers = 0;
            $bonusWin = 0.0;
            while ($left > 0) {
                $left--;
                $spin = self::evaluate(self::draw($freeReels, $random), true);
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
     * One line, as slotopol's ScanLined: [symbol, count, pay per line bet] or null. A cell reading WILD is wild
     * (a samurai, or any cell of a wild reel).
     *
     * @param  list<string>  $symbols
     * @return array{string, int, int}|null
     */
    public static function lineWin(array $symbols): ?array
    {
        $syml = null;
        $numw = 0;
        $numl = self::REELS;
        foreach ($symbols as $index => $symbol) {
            $x = $index + 1;
            if ($symbol === self::WILD) {
                if ($syml === null) {
                    $numw = $x;
                }
            } elseif ($syml === null) {
                $syml = $symbol;
            } elseif ($symbol !== $syml) {
                $numl = $x - 1;
                break;
            }
        }
        $payw = $numw >= 3 ? self::LINE_PAY[self::WILD][$numw - 1] : 0;
        $payl = $numl >= 3 && $syml !== null ? self::LINE_PAY[$syml][$numl - 1] : 0;
        if ($payl > $payw) {
            return [(string) $syml, $numl, $payl];
        }

        return $payw > 0 ? [self::WILD, $numw, $payw] : null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array<string, mixed>
     */
    public static function evaluate(array $grid, bool $freeGame = false): array
    {
        $wildReels = [];
        if ($freeGame) {
            foreach ($grid as $reel => $column) {
                if (in_array(self::WILD, $column, true)) {
                    $wildReels[] = $reel;
                }
            }
        }

        $lines = count(self::PAYLINES);
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $cells = array_map(fn (int $row, int $reel) => in_array($reel, $wildReels, true) ? self::WILD : $grid[$reel][$row], $line, array_keys($line));
            $win = self::lineWin($cells);
            if ($win === null) {
                continue;
            }
            [$symbol, $count, $pay] = $win;
            $positions = [];
            for ($reel = 0; $reel < $count; $reel++) {
                $positions[] = [$reel, $line[$reel]];
            }
            $wins[] = ['line' => $index, 'symbol' => $symbol, 'count' => $count, 'amount' => $pay / $lines, 'positions' => $positions];
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
        if (count($scatters) >= 3) {
            $k = min(count($scatters), 5);
            $spin['scatterHit'] = ['count' => count($scatters), 'positions' => $scatters, 'amount' => self::SCAT_PAY[$k - 1]];
            $total += self::SCAT_PAY[$k - 1];
            $free = self::SCAT_FREESPIN[$k - 1];
        }

        return [...$spin, 'freeSpinsWon' => $free, 'freeGame' => $freeGame, 'wildReels' => $wildReels, 'totalWin' => $total];
    }
}
