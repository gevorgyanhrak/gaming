<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * THE DYNASTY, a 5x3, 20-line slot. The monarch is wild: he joins runs from the left and doubles a run he helps
 * complete; a run of monarchs pays from 2, other runs from 3 (each line pays the better). In the base game 3+ of
 * either heir anywhere (the heiress checked first) call 25–45 free games (drawn) and pay nothing. In those free
 * games the calling heir is the scatter (3+ pay 5/25/500 total bets and draw more games) and the other heir is
 * crowned: wild and doubling like the monarch. Port of APEXSLOTS' the-dynasty engine (MIT,
 * github.com/Z1000k/open-source-slots), maths slotopol "royaldynasty" (MIT): 94.138004 % (exact, see its test).
 */
final class TheDynasty implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const WILD = 'MONARCH';

    public const HEIRS = ['HEIRESS', 'HEIR'];

    public const WILD_MULT = 2;

    /** Free games drawn for each call (uniform). */
    public const FREE_GAMES_DRAW = [25, 30, 35, 40, 45];

    public const SYMBOLS = ['MONARCH', 'KNIGHT', 'EAGLE', 'WOLF', 'CHALICE', 'SCEPTER', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'NINE', 'HEIRESS', 'HEIR'];

    /** The set nearest the provider's published 94.49 %. */
    public const BASE = [
        [5, 9, 12, 2, 11, 3, 10, 4, 8, 6, 10, 1, 7, 4, 12, 6, 9, 3, 8, 9, 4, 14, 7, 5, 8, 11, 2, 7, 3, 12, 11, 5, 12, 10, 6, 13],
        [5, 13, 10, 3, 12, 2, 8, 9, 6, 12, 3, 8, 9, 2, 12, 4, 9, 5, 7, 1, 10, 11, 4, 8, 7, 4, 11, 6, 14, 5, 12, 3, 7, 6, 10, 11],
        [1, 7, 2, 9, 4, 11, 12, 5, 11, 2, 10, 6, 12, 5, 10, 13, 3, 10, 6, 8, 4, 9, 12, 3, 11, 8, 7, 6, 11, 5, 8, 14, 4, 7, 3, 12, 9],
        [7, 11, 4, 10, 11, 2, 10, 4, 8, 6, 12, 4, 7, 6, 9, 5, 10, 3, 12, 8, 5, 9, 8, 7, 3, 14, 5, 12, 13, 3, 9, 6, 11, 2, 12, 1],
        [6, 7, 10, 5, 12, 4, 11, 7, 3, 9, 6, 7, 5, 11, 4, 13, 9, 5, 8, 2, 12, 8, 1, 10, 3, 9, 6, 10, 3, 12, 8, 4, 14, 2, 12, 11],
    ];

    public const FREE = [
        [7, 5, 10, 12, 4, 10, 6, 9, 11, 7, 5, 12, 3, 14, 6, 7, 5, 12, 11, 4, 13, 2, 8, 9, 3, 11, 6, 8, 9, 1, 10, 2, 8],
        [2, 10, 5, 8, 2, 11, 1, 7, 12, 4, 13, 8, 3, 9, 8, 3, 14, 6, 7, 10, 12, 6, 11, 5, 9, 10, 4, 12, 5, 9, 11, 6, 7],
        [10, 6, 8, 1, 12, 10, 8, 4, 11, 6, 7, 13, 5, 10, 4, 11, 2, 9, 5, 7, 2, 9, 12, 3, 11, 14, 9, 5, 8, 3, 12, 6, 7],
        [5, 9, 7, 10, 6, 8, 1, 12, 3, 9, 5, 8, 14, 9, 6, 13, 4, 10, 12, 3, 10, 7, 5, 11, 12, 2, 11, 6, 7, 4, 11, 2, 8],
        [6, 12, 4, 7, 10, 3, 12, 1, 10, 6, 8, 14, 4, 11, 8, 5, 9, 13, 5, 9, 7, 2, 10, 6, 8, 3, 7, 2, 11, 5, 9, 12, 11],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2], [2, 1, 1, 1, 0],
        [0, 1, 1, 1, 2], [0, 0, 1, 0, 0], [2, 2, 1, 2, 2], [1, 0, 1, 2, 1], [1, 2, 1, 0, 1],
        [0, 1, 0, 1, 0], [2, 1, 2, 1, 2], [1, 1, 0, 1, 1], [1, 1, 2, 1, 1], [0, 1, 1, 1, 0],
    ];

    public const LINE_PAY = [
        'MONARCH' => [0, 10, 200, 1200, 5000],
        'KNIGHT' => [0, 0, 25, 100, 500],
        'EAGLE' => [0, 0, 20, 80, 300],
        'WOLF' => [0, 0, 20, 80, 300],
        'CHALICE' => [0, 0, 15, 60, 200],
        'SCEPTER' => [0, 0, 15, 60, 200],
        'ACE' => [0, 0, 10, 50, 150],
        'KING' => [0, 0, 10, 50, 150],
        'QUEEN' => [0, 0, 5, 25, 100],
        'JACK' => [0, 0, 5, 25, 100],
        'TEN' => [0, 0, 5, 25, 100],
        'NINE' => [0, 0, 5, 25, 100],
        'HEIRESS' => [0, 0, 0, 0, 0],
        'HEIR' => [0, 0, 0, 0, 0],
    ];

    /** The calling heir in a free game: total bets for 1…5 anywhere. */
    public const SCAT_PAY = [0, 0, 5, 25, 500];

    public function id(): string
    {
        return 'the-dynasty';
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

    public static function otherHeir(string $heir): string
    {
        return $heir === 'HEIRESS' ? 'HEIR' : 'HEIRESS';
    }

    public function play(Closure $random): array
    {
        $base = self::evaluate(self::draw(self::reels(self::BASE), $random), null);
        $round = ['baseSpin' => $base];
        $total = $base['totalWin'];

        if (isset($base['scatterHit'])) {
            $caller = $base['scatterHit']['symbol'];
            $draw = self::FREE_GAMES_DRAW[$random(count(self::FREE_GAMES_DRAW))];
            $round['baseSpin']['freeSpinsWon'] = $draw;
            $draws = [$draw];
            $freeReels = self::reels(self::FREE);
            $spins = [];
            $awarded = $draw;
            $retriggers = 0;
            $bonusWin = 0.0;
            while (count($spins) < $awarded) {
                $spin = self::evaluate(self::draw($freeReels, $random), $caller);
                if (isset($spin['scatterHit'])) {
                    $spin['freeSpinsWon'] = self::FREE_GAMES_DRAW[$random(count(self::FREE_GAMES_DRAW))];
                    $draws[] = $spin['freeSpinsWon'];
                    $awarded += $spin['freeSpinsWon'];
                    $retriggers++;
                }
                $bonusWin += $spin['totalWin'];
                $spins[] = $spin;
            }
            $round['bonus'] = ['caller' => $caller, 'spins' => $spins, 'spinsPlayed' => count($spins), 'retriggers' => $retriggers, 'draws' => $draws, 'totalWin' => $bonusWin];
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
     * One line (slotopol ScanLinedReg/Bon): [symbol, count, mult, pay per line bet] or null; `crowned` is the
     * other heir in a free game, wild like the monarch.
     *
     * @param  list<string>  $symbols
     * @return array{string, int, int, int}|null
     */
    public static function lineWin(array $symbols, ?string $crowned = null): ?array
    {
        $syml = null;
        $numw = 0;
        $numl = self::REELS;
        $mult = 1;
        foreach ($symbols as $index => $symbol) {
            $x = $index + 1;
            if ($symbol === self::WILD || $symbol === $crowned) {
                if ($syml === null) {
                    $numw = $x;
                }
                $mult = self::WILD_MULT;
            } elseif ($syml === null) {
                $syml = $symbol;
            } elseif ($symbol !== $syml) {
                $numl = $x - 1;
                break;
            }
        }
        $payw = $numw >= 2 ? self::LINE_PAY[self::WILD][$numw - 1] : 0;
        $payl = $numl >= 3 && $syml !== null ? self::LINE_PAY[$syml][$numl - 1] : 0;
        if ($payl * $mult > $payw) {
            return [(string) $syml, $numl, $mult, $payl * $mult];
        }

        return $payw > 0 ? [self::WILD, $numw, 1, $payw] : null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array<string, mixed>
     */
    public static function evaluate(array $grid, ?string $caller): array
    {
        $crowned = $caller === null ? null : self::otherHeir($caller);
        $lines = count(self::PAYLINES);
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $win = self::lineWin(array_map(fn (int $row, int $reel) => $grid[$reel][$row], $line, array_keys($line)), $crowned);
            if ($win === null) {
                continue;
            }
            [$symbol, $count, $mult, $pay] = $win;
            $positions = [];
            for ($reel = 0; $reel < $count; $reel++) {
                $positions[] = [$reel, $line[$reel]];
            }
            $wins[] = ['line' => $index, 'symbol' => $symbol, 'count' => $count, 'mult' => $mult, 'amount' => $pay / $lines, 'positions' => $positions];
        }

        $cells = fn (string $symbol) => array_merge(...array_map(
            fn (array $column, int $reel) => array_map(fn (int $row) => [$reel, $row], array_keys($column, $symbol, true)),
            $grid,
            array_keys($grid),
        ));
        $hit = null;
        if ($caller === null) {
            foreach (self::HEIRS as $heir) {
                $at = $cells($heir);
                if (count($at) >= 3) {
                    $hit = ['symbol' => $heir, 'count' => count($at), 'positions' => $at, 'amount' => 0];
                    break;
                }
            }
        } else {
            $at = $cells($caller);
            if (count($at) >= 3) {
                $hit = ['symbol' => $caller, 'count' => count($at), 'positions' => $at, 'amount' => self::SCAT_PAY[min(count($at), 5) - 1]];
            }
        }

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        if ($hit !== null) {
            $spin['scatterHit'] = $hit;
        }

        return [...$spin, 'freeSpinsWon' => 0, 'freeGame' => $caller !== null, 'caller' => $caller, 'totalWin' => ($hit['amount'] ?? 0) + (float) array_sum(array_column($wins, 'amount'))];
    }
}
