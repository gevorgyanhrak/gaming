<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * HOUSE OF RA, a 5x3, 10-line slot. Base game: the sun disc is wild (skipped wherever it sits on a line, no line pay
 * of its own). 3+ sun discs anywhere pay 2/20/200 total bets and start 10 free games on their own strips, where the
 * single best way (any row on each reel, a run from reel 1) expands and pays on all 10 lines; the sun disc is not
 * wild there. Port of APEXSLOTS' house-of-ra engine (MIT, github.com/Z1000k/open-source-slots), base maths slotopol
 * "dynastyofra" (MIT), free strips APEXSLOTS' own: 95.182099 % (exact, see its test). Spins carry `wayWin`,
 * `scatterHit` and `freeGame` as the client expects.
 */
final class HouseOfRa implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const LINES = 10;

    public const SUN = 'SUNDISC';

    public const FREE_SPINS = 10;

    public const SYMBOLS = ['PHARAOH', 'FALCON', 'ANKH', 'LOTUS', 'COBRA', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'SUNDISC'];

    /** The original Dynasty of Ra strips. */
    public const BASE = [
        [10, 5, 11, 7, 9, 3, 8, 5, 6, 3, 7, 10, 4, 9, 10, 4, 6, 1, 8, 6, 3, 9, 2, 6, 7, 2, 8, 10, 7, 1, 9, 4, 10, 5, 8],
        [2, 10, 3, 7, 1, 9, 2, 6, 5, 11, 10, 6, 3, 9, 4, 7, 10, 1, 7, 4, 8, 9, 5, 7, 4, 6, 8, 9, 5, 6, 3, 8, 5, 10, 8],
        [9, 5, 7, 1, 9, 3, 10, 2, 8, 6, 3, 11, 7, 10, 4, 6, 9, 4, 6, 5, 8, 2, 7, 5, 8, 3, 9, 10, 1, 7, 6, 5, 8, 4, 10],
        [3, 8, 4, 7, 8, 2, 9, 5, 10, 2, 6, 3, 7, 8, 5, 6, 10, 5, 7, 8, 10, 3, 9, 10, 1, 6, 4, 7, 1, 9, 4, 6, 11, 5, 9],
        [10, 4, 7, 10, 8, 11, 4, 9, 5, 6, 2, 8, 3, 7, 5, 6, 3, 10, 2, 8, 9, 5, 7, 1, 6, 8, 10, 9, 4, 6, 10, 9, 3, 7, 1],
    ];

    /** APEXSLOTS' free strips (slotopol's, stacked two high on reels 3–5). */
    public const FREE = [
        [10, 3, 9, 5, 6, 3, 7, 5, 9, 7, 6, 9, 2, 8, 4, 6, 8, 2, 10, 1, 9, 10, 8, 1, 7, 6, 4, 8, 3, 11, 5, 10, 4, 7],
        [4, 9, 2, 7, 8, 5, 6, 1, 9, 3, 10, 7, 4, 10, 11, 7, 6, 8, 2, 7, 5, 9, 6, 8, 3, 10, 1, 6, 4, 8, 3, 9, 5, 10],
        [6, 6, 11, 5, 7, 7, 9, 9, 1, 1, 10, 10, 9, 9, 3, 3, 7, 7, 4, 4, 8, 8, 2, 2, 6, 6, 5, 10, 10, 1, 1, 6, 6, 11, 8, 8, 4, 4, 10, 10, 7, 7, 8, 8, 3, 3, 6, 6, 10, 10, 5, 9, 9, 3, 3, 8, 8, 9, 9, 4, 4, 7, 7, 2, 2],
        [9, 9, 7, 7, 4, 4, 8, 8, 3, 3, 9, 9, 11, 4, 4, 6, 6, 10, 10, 7, 7, 3, 3, 8, 8, 10, 10, 3, 3, 6, 6, 5, 5, 9, 9, 8, 8, 2, 2, 7, 7, 1, 1, 10, 10, 11, 5, 5, 6, 6, 1, 1, 8, 8, 2, 2, 9, 9, 4, 4, 10, 10, 5, 5, 7, 7, 6, 6],
        [9, 9, 3, 3, 8, 8, 6, 6, 4, 4, 10, 10, 8, 8, 6, 6, 5, 5, 10, 10, 4, 4, 7, 7, 2, 2, 11, 4, 4, 9, 9, 8, 8, 1, 1, 7, 7, 9, 9, 3, 3, 10, 10, 5, 5, 9, 9, 1, 1, 7, 7, 8, 8, 5, 5, 6, 6, 10, 10, 11, 3, 3, 6, 6, 7, 7, 2, 2],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2], [2, 1, 1, 1, 0],
    ];

    public const LINE_PAY = [
        'PHARAOH' => [0, 10, 50, 200, 1000],
        'FALCON' => [0, 5, 25, 100, 500],
        'ANKH' => [0, 5, 20, 50, 250],
        'LOTUS' => [0, 5, 20, 50, 250],
        'COBRA' => [0, 5, 20, 50, 250],
        'ACE' => [0, 0, 10, 30, 125],
        'KING' => [0, 0, 10, 30, 125],
        'QUEEN' => [0, 0, 5, 20, 100],
        'JACK' => [0, 0, 5, 20, 100],
        'TEN' => [0, 0, 5, 20, 100],
    ];

    public const SCAT_PAY = [0, 0, 2, 20, 200];

    public const SCAT_FREESPIN = [0, 0, 10, 10, 10];

    public function id(): string
    {
        return 'house-of-ra';
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
     * A base-game line (slotopol ScanLinedReg): sun discs are skipped; [symbol, count, pay per line bet] or null.
     *
     * @param  list<string>  $symbols
     * @return array{string, int, int}|null
     */
    public static function lineWin(array $symbols): ?array
    {
        $syml = null;
        $count = self::REELS;
        foreach ($symbols as $x => $symbol) {
            if ($symbol === self::SUN) {
                continue;
            }
            if ($syml === null) {
                $syml = $symbol;
            } elseif ($symbol !== $syml) {
                $count = $x;
                break;
            }
        }
        if ($syml === null) {
            return null;
        }
        $pay = self::LINE_PAY[$syml][$count - 1];

        return $pay > 0 ? [$syml, $count, $pay] : null;
    }

    /**
     * The free games' best way: for each symbol, how many reels in a row from reel 1 show it anywhere; the best pay
     * wins (a tie goes to the symbol listed first). Null when no way pays.
     *
     * @param  array<int, array<int, string>>  $grid
     * @return array{symbol: string, count: int, positions: list<array{int, int}>, amount: int}|null
     */
    public static function bestWay(array $grid): ?array
    {
        $best = null;
        foreach (self::LINE_PAY as $symbol => $pays) {
            $count = 0;
            while ($count < self::REELS && in_array($symbol, $grid[$count], true)) {
                $count++;
            }
            if ($count === 0 || $pays[$count - 1] <= 0 || ($best !== null && $pays[$count - 1] <= $best['amount'])) {
                continue;
            }
            $positions = [];
            for ($x = 0; $x < $count; $x++) {
                foreach ($grid[$x] as $y => $s) {
                    if ($s === $symbol) {
                        $positions[] = [$x, $y];
                    }
                }
            }
            $best = ['symbol' => $symbol, 'count' => $count, 'positions' => $positions, 'amount' => $pays[$count - 1]];
        }

        return $best;
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array<string, mixed>
     */
    public static function evaluate(array $grid, bool $freeGame = false): array
    {
        $wins = [];
        $way = null;
        if (! $freeGame) {
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
        } else {
            $way = self::bestWay($grid);
            if ($way !== null) {
                foreach (self::PAYLINES as $index => $line) {
                    $positions = [];
                    for ($reel = 0; $reel < $way['count']; $reel++) {
                        $positions[] = [$reel, $line[$reel]];
                    }
                    $wins[] = ['line' => $index, 'symbol' => $way['symbol'], 'count' => $way['count'], 'amount' => $way['amount'] / self::LINES, 'positions' => $positions];
                }
            }
        }

        $scatters = [];
        foreach ($grid as $reel => $column) {
            foreach ($column as $row => $symbol) {
                if ($symbol === self::SUN) {
                    $scatters[] = [$reel, $row];
                }
            }
        }
        $k = min(count($scatters), 5);
        $free = $k > 0 ? self::SCAT_FREESPIN[$k - 1] : 0;
        $scatPay = $k > 0 ? self::SCAT_PAY[$k - 1] : 0;

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        if ($way !== null) {
            $spin['wayWin'] = $way;
        }
        if ($scatPay > 0 || $free > 0) {
            $spin['scatterHit'] = ['count' => count($scatters), 'positions' => $scatters, 'amount' => $scatPay];
        }

        return [...$spin, 'freeSpinsWon' => $free, 'freeGame' => $freeGame, 'totalWin' => $scatPay + (float) array_sum(array_column($wins, 'amount'))];
    }
}
