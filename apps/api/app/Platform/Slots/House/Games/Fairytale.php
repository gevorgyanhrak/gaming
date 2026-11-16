<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * FAIRYTALE, a 5x3, 10-line slot. The enchantress is wild (no multiplier) and the top symbol; a run of her pays as
 * her own line, each line paying the better. 2+ lanterns anywhere pay, 3+ start 10 free games: before each one a
 * symbol is drawn from a 28-entry pool and covers reels 1–2 or 1–3 whole (a lantern sits mid-reel between blanks),
 * only the reels after spin, so every free game pays. Port of APEXSLOTS' fairytale engine (MIT,
 * github.com/Z1000k/open-source-slots), maths slotopol "fairyqueen" (MIT): 95.566989 % (exact, see its test).
 * Spins carry `scatterHit`, `freeGame` and `expand` ({symbol, reels}) as the client expects.
 */
final class Fairytale implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const LINES = 10;

    public const WILD = 'ENCHANTRESS';

    public const SCATTER = 'LANTERN';

    public const BLANK = 'BLANK';

    public const SYMBOLS = ['ENCHANTRESS', 'UNICORN', 'GRIFFIN', 'CASTLE', 'SLIPPER', 'MIRROR', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'NINE', 'LANTERN'];

    /** The set nearest the provider's published 95.09 %. */
    public const BASE = [
        [3, 12, 4, 11, 3, 7, 2, 9, 1, 8, 3, 12, 5, 10, 6, 8, 4, 7, 5, 10, 12, 6, 11, 4, 7, 6, 10, 4, 11, 2, 12, 5, 9, 6, 8, 5, 13, 2, 9],
        [11, 4, 9, 3, 7, 1, 9, 4, 12, 5, 7, 6, 8, 4, 11, 5, 10, 3, 8, 4, 12, 2, 8, 6, 13, 5, 10, 6, 12, 2, 9, 6, 12, 5, 7, 3, 11, 10, 2],
        [11, 6, 8, 4, 13, 5, 12, 4, 11, 5, 12, 4, 7, 6, 8, 2, 9, 3, 10, 4, 7, 1, 12, 5, 9, 2, 11, 6, 10, 3, 9, 2, 8, 7, 6, 10, 3, 12, 5],
        [5, 13, 4, 11, 6, 12, 2, 10, 3, 12, 6, 7, 3, 12, 1, 8, 4, 10, 5, 11, 2, 12, 5, 9, 6, 7, 2, 9, 4, 8, 5, 9, 3, 10, 11, 4, 8, 6, 7],
        [2, 12, 3, 10, 5, 7, 4, 10, 6, 9, 2, 8, 12, 4, 8, 5, 12, 6, 11, 4, 9, 2, 11, 3, 7, 5, 11, 6, 12, 3, 13, 4, 8, 5, 10, 1, 7, 6, 9],
    ];

    /** The free-games strips for reels 3, 4 and 5 (reels 1–2 are always covered). */
    public const FREE = [
        2 => [2, 11, 7, 4, 12, 9, 4, 7, 9, 10, 6, 8, 2, 9, 3, 8, 7, 12, 4, 13, 11, 9, 10, 5, 11, 6, 10, 8, 6, 9, 11, 5, 8, 11, 6, 8, 12, 3, 11, 1, 12, 10, 5, 12, 1, 7, 10, 5, 12, 9, 3, 12, 2, 7, 10],
        3 => [4, 9, 8, 5, 10, 9, 5, 10, 11, 2, 7, 10, 12, 3, 13, 10, 3, 7, 6, 11, 4, 8, 12, 1, 9, 12, 8, 11, 1, 12, 4, 11, 5, 7, 9, 11, 12, 8, 6, 12, 3, 11, 2, 9, 12, 10, 6, 7, 9, 2, 8, 5, 10, 6, 7],
        4 => [3, 7, 2, 11, 9, 5, 11, 6, 12, 11, 3, 12, 10, 7, 2, 9, 3, 11, 10, 13, 4, 9, 5, 12, 7, 10, 12, 1, 8, 12, 9, 5, 7, 1, 8, 4, 9, 6, 11, 10, 9, 12, 5, 10, 7, 6, 8, 2, 12, 8, 6, 11, 8, 4, 10],
    ];

    /** The expanding-symbol pool: one entry drawn before every free game (weights are repeats). */
    public const EXPAND_POOL = [2, 2, 2, 2, 3, 3, 3, 3, 4, 5, 6, 7, 8, 9, 9, 10, 10, 11, 11, 12, 12, 12, 12, 12, 12, 13, 13, 13];

    /** How many reels from the left the drawn symbol covers. */
    public const EXPAND_REELS = [
        'UNICORN' => 2, 'GRIFFIN' => 2, 'CASTLE' => 3, 'SLIPPER' => 3, 'MIRROR' => 3, 'ACE' => 3, 'KING' => 3,
        'QUEEN' => 3, 'JACK' => 3, 'TEN' => 3, 'NINE' => 2, 'LANTERN' => 2,
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2], [2, 1, 1, 1, 0],
    ];

    public const LINE_PAY = [
        'ENCHANTRESS' => [0, 10, 250, 2500, 9000],
        'UNICORN' => [0, 2, 25, 125, 750],
        'GRIFFIN' => [0, 2, 25, 125, 750],
        'CASTLE' => [0, 0, 20, 100, 400],
        'SLIPPER' => [0, 0, 15, 75, 250],
        'MIRROR' => [0, 0, 15, 75, 250],
        'ACE' => [0, 0, 10, 50, 125],
        'KING' => [0, 0, 10, 50, 125],
        'QUEEN' => [0, 0, 5, 25, 100],
        'JACK' => [0, 0, 5, 25, 100],
        'TEN' => [0, 0, 5, 25, 100],
        'NINE' => [0, 2, 5, 25, 100],
        'LANTERN' => [0, 0, 0, 0, 0],
        'BLANK' => [0, 0, 0, 0, 0],
    ];

    public const SCAT_PAY = [0, 2, 5, 75, 750];

    public const SCAT_FREESPIN = [0, 0, 10, 10, 10];

    public function id(): string
    {
        return 'fairytale';
    }

    /** A free games session adds up spin by spin: the wallet's cap is wide. */
    public function maxWin(): int
    {
        return 100_000;
    }

    /**
     * @param  list<int>  $strip
     * @return list<string>
     */
    public static function strip(array $strip): array
    {
        return array_map(fn (int $n) => self::SYMBOLS[$n - 1], $strip);
    }

    /** @return list<string> the column a covering symbol shows */
    public static function coveredReel(string $symbol): array
    {
        return $symbol === self::SCATTER ? [self::BLANK, self::SCATTER, self::BLANK] : [$symbol, $symbol, $symbol];
    }

    public function play(Closure $random): array
    {
        $grid = [];
        foreach (self::BASE as $reel => $strip) {
            $grid[$reel] = self::window(self::strip($strip), $random);
        }
        $base = self::evaluate($grid);
        $round = ['baseSpin' => $base];
        $total = $base['totalWin'];

        if ($base['freeSpinsWon'] > 0) {
            $spins = [];
            $left = $base['freeSpinsWon'];
            $retriggers = 0;
            $bonusWin = 0.0;
            while ($left > 0) {
                $left--;
                $symbol = self::SYMBOLS[self::EXPAND_POOL[$random(count(self::EXPAND_POOL))] - 1];
                $covered = self::EXPAND_REELS[$symbol];
                $free = [];
                for ($reel = 0; $reel < self::REELS; $reel++) {
                    $free[$reel] = $reel < $covered ? self::coveredReel($symbol) : self::window(self::strip(self::FREE[$reel]), $random);
                }
                $spin = self::evaluate($free, ['symbol' => $symbol, 'reels' => $covered]);
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
     * @param  list<string>  $strip
     * @param  Closure(int): int  $random
     * @return list<string>
     */
    private static function window(array $strip, Closure $random): array
    {
        $stop = $random(count($strip));

        return [$strip[$stop], $strip[($stop + 1) % count($strip)], $strip[($stop + 2) % count($strip)]];
    }

    /**
     * One line (slotopol ScanLined; the blank is a symbol that pays nothing): [symbol, count, pay] or null.
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
        $payw = $numw >= 2 ? self::LINE_PAY[self::WILD][$numw - 1] : 0;
        $payl = $numl >= 2 && $syml !== null ? self::LINE_PAY[$syml][$numl - 1] : 0;
        if ($payl > $payw) {
            return [(string) $syml, $numl, $payl];
        }

        return $payw > 0 ? [self::WILD, $numw, $payw] : null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @param  array{symbol: string, reels: int}|null  $expand
     * @return array<string, mixed>
     */
    public static function evaluate(array $grid, ?array $expand = null): array
    {
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $win = self::lineWin(array_map(fn (int $row, int $reel) => $grid[$reel][$row], $line, array_keys($line)));
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

        $scatters = [];
        foreach ($grid as $reel => $column) {
            foreach ($column as $row => $symbol) {
                if ($symbol === self::SCATTER) {
                    $scatters[] = [$reel, $row];
                }
            }
        }
        $k = min(count($scatters), 5);
        $scatPay = $k > 0 ? self::SCAT_PAY[$k - 1] : 0;
        $free = $k > 0 ? self::SCAT_FREESPIN[$k - 1] : 0;

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        if ($scatPay > 0 || $free > 0) {
            $spin['scatterHit'] = ['count' => count($scatters), 'positions' => $scatters, 'amount' => $scatPay];
        }
        $spin = [...$spin, 'freeSpinsWon' => $free, 'freeGame' => $expand !== null];
        if ($expand !== null) {
            $spin['expand'] = $expand;
        }
        $spin['totalWin'] = $scatPay + (float) array_sum(array_column($wins, 'amount'));

        return $spin;
    }
}
