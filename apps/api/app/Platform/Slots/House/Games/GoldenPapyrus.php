<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * GOLDEN PAPYRUS, a 5x3, 10-line book slot. The papyrus is wild on the lines and the scatter: 3+ anywhere pay and
 * start 10 free games on their own strips (3+ there add 10 more). Each session draws one of the nine symbols as the
 * expanding symbol: it leaves ordinary runs (unless wilds lead the line) and instead pays its line pay on all 10
 * lines when 2+ land anywhere. Port of APEXSLOTS' golden-papyrus engine (MIT, github.com/Z1000k/open-source-slots),
 * maths slotopol "bookofra" (MIT): the 95.111967 % reels (exact, see its test). Rounds have the client's shape:
 * {baseSpin, bonus?: {expandingSymbol, spins, retriggers, totalWin}, totalWin}.
 */
final class GoldenPapyrus implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const LINES = 10;

    public const WILD = 'PAPYRUS';

    public const FREE_SPINS = 10;

    public const SYMBOLS = ['EXPLORER', 'MUMMY', 'ANUBIS', 'SCARAB', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'PAPYRUS'];

    public const BASE = [
        [7, 9, 10, 5, 8, 3, 5, 7, 1, 6, 5, 3, 7, 6, 4, 5, 8, 6, 7, 2, 9, 6, 3, 7, 6, 3, 8, 5, 6, 7, 4, 6, 8, 3, 7, 9, 5, 8, 2, 6, 7, 3, 5, 9, 1, 5, 7, 6, 8, 5],
        [8, 7, 10, 5, 9, 8, 2, 6, 5, 8, 4, 6, 9, 4, 8, 6, 2, 7, 9, 6, 5, 8, 9, 6, 8, 9, 6, 4, 8, 9, 6, 1, 7, 8, 9, 4, 8, 6, 5, 3, 8, 9, 6, 7, 9],
        [8, 5, 10, 9, 6, 2, 5, 7, 2, 9, 7, 1, 8, 9, 3, 7, 5, 3, 6, 5, 1, 8, 9, 2, 8, 7, 5, 9, 4, 7, 8, 9, 2, 6, 5, 9, 4, 8, 7, 3, 9, 5, 4, 8, 7, 9],
        [7, 8, 10, 9, 6, 1, 5, 7, 2, 5, 3, 6, 8, 1, 9, 7, 3, 6, 5, 3, 7, 6, 10, 7, 5, 4, 7, 9, 2, 6, 4, 5, 8, 7, 6, 3, 9, 5, 4, 8, 9, 7, 6, 2, 9, 8, 7, 3, 9, 5],
        [9, 8, 10, 6, 8, 3, 7, 5, 3, 6, 7, 9, 4, 7, 5, 4, 8, 2, 7, 3, 5, 9, 10, 7, 8, 2, 5, 6, 3, 7, 4, 5, 9, 6, 2, 7, 1, 5, 9, 3, 6, 8, 1, 5, 6],
    ];

    public const BONUS = [
        [8, 2, 6, 8, 1, 6, 5, 3, 9, 6, 8, 5, 2, 7, 9, 10, 5, 8, 2, 7, 9, 10, 6, 8, 2, 7, 6, 5, 4, 7, 8, 3, 6, 5, 4, 7, 5],
        [7, 3, 6, 9, 4, 8, 10, 6, 7, 5, 9, 7, 1, 8, 5, 4, 8, 7, 10, 5, 9, 6, 3, 5, 8, 7, 9, 2, 8, 5, 7, 8, 4, 9, 5, 6, 9],
        [5, 1, 7, 9, 1, 6, 8, 7, 3, 8, 6, 4, 7, 5, 1, 9, 5, 6, 2, 7, 5, 8, 6, 9, 8, 5, 10, 9, 6, 8, 2, 9, 7, 3, 6, 8],
        [10, 9, 6, 7, 4, 6, 2, 7, 8, 1, 5, 7, 3, 6, 7, 4, 9, 6, 3, 7, 9, 4, 5, 8, 9, 1, 8, 5, 10, 6, 9, 3, 6, 9, 4, 7, 8],
        [7, 4, 9, 10, 6, 2, 7, 5, 3, 9, 8, 10, 6, 8, 2, 5, 3, 8, 5, 1, 8, 9, 3, 5, 1, 6, 4, 9, 2, 7, 3, 6, 4, 7, 2, 9, 1],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2], [2, 1, 1, 1, 0],
    ];

    /** Per line bet, for a run of 1…5 (the expanding symbol's collect pays this for 1…5 cells, ×10 lines ÷ 10). */
    public const LINE_PAY = [
        'EXPLORER' => [0, 10, 100, 1000, 5000],
        'MUMMY' => [0, 5, 40, 400, 2000],
        'ANUBIS' => [0, 5, 30, 100, 750],
        'SCARAB' => [0, 5, 30, 100, 750],
        'ACE' => [0, 0, 5, 40, 150],
        'KING' => [0, 0, 5, 40, 150],
        'QUEEN' => [0, 0, 5, 25, 100],
        'JACK' => [0, 0, 5, 25, 100],
        'TEN' => [0, 0, 5, 25, 100],
    ];

    /** Total bets for 1…5 papyrus anywhere. */
    public const SCAT_PAY = [0, 0, 2, 20, 200];

    public function id(): string
    {
        return 'golden-papyrus';
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
        $base = self::evaluate(self::draw(self::reels(self::BASE), $random), null);
        $round = ['baseSpin' => $base];
        $total = $base['totalWin'];

        if ($base['freeSpinsWon'] > 0) {
            $es = self::SYMBOLS[$random(9)];
            $bonusReels = self::reels(self::BONUS);
            $spins = [];
            $left = $base['freeSpinsWon'];
            $retriggers = 0;
            $bonusWin = 0.0;
            while ($left > 0) {
                $left--;
                $spin = self::evaluate(self::draw($bonusReels, $random), $es);
                if ($spin['freeSpinsWon'] > 0) {
                    $left += $spin['freeSpinsWon'];
                    $retriggers++;
                }
                $bonusWin += $spin['totalWin'];
                $spins[] = $spin;
            }
            $round['bonus'] = ['expandingSymbol' => $es, 'spins' => $spins, 'retriggers' => $retriggers, 'totalWin' => $bonusWin];
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
     * One line, as slotopol's ScanLined with the expanding symbol's break rule: [symbol, count, pay per line bet].
     *
     * @param  list<string>  $symbols
     * @return array{string, int, int}|null
     */
    public static function lineWin(array $symbols, ?string $es): ?array
    {
        $syml = null;
        $leadingWilds = false;
        $count = self::REELS;
        foreach ($symbols as $x => $symbol) {
            if ($symbol === self::WILD) {
                if ($syml === null) {
                    $leadingWilds = true;
                }
            } elseif ($syml === null) {
                if ($symbol === $es && ! $leadingWilds) {
                    return null;
                }
                $syml = $symbol;
            } elseif ($symbol !== $syml) {
                $count = $x;
                break;
            }
        }
        if ($syml === null || $count < 2) {
            return null;
        }
        $pay = self::LINE_PAY[$syml][$count - 1];

        return $pay > 0 ? [$syml, $count, $pay] : null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array<string, mixed>
     */
    public static function evaluate(array $grid, ?string $es): array
    {
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $win = self::lineWin(array_map(fn (int $row, int $reel) => $grid[$reel][$row], $line, array_keys($line)), $es);
            if ($win === null) {
                continue;
            }
            [$symbol, $count, $pay] = $win;
            $positions = [];
            for ($reel = 0; $reel < $count; $reel++) {
                $positions[] = [$reel, $line[$reel]];
            }
            $wins[] = ['line' => $index, 'symbol' => $symbol, 'count' => $count, 'amount' => $pay / self::LINES, 'positions' => $positions];
        }

        $cells = [];
        $scatters = [];
        foreach ($grid as $reel => $column) {
            foreach ($column as $row => $symbol) {
                if ($es !== null && $symbol === $es) {
                    $cells[] = [$reel, $row];
                }
                if ($symbol === self::WILD) {
                    $scatters[] = [$reel, $row];
                }
            }
        }

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        $total = (float) array_sum(array_column($wins, 'amount'));
        if ($es !== null && count($cells) >= 2) {
            $pay = self::LINE_PAY[$es][count($cells) - 1] ?? 0;
            if ($pay > 0) {
                $spin['expandingWin'] = ['symbol' => $es, 'count' => count($cells), 'amount' => $pay, 'positions' => $cells];
                $total += $pay;
            }
        }
        $free = 0;
        if (count($scatters) >= 3) {
            $amount = self::SCAT_PAY[min(count($scatters), 5) - 1];
            $spin['scatterWin'] = ['count' => count($scatters), 'amount' => $amount, 'positions' => $scatters];
            $total += $amount;
            $free = self::FREE_SPINS;
        }
        $spin['freeSpinsWon'] = $free;
        $spin['totalWin'] = $total;

        return $spin;
    }
}
