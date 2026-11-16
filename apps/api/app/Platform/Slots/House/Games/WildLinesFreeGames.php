<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * The APEXSLOTS 5x3 line slots with a doubling wild and scatter-started free games (MIT,
 * github.com/Z1000k/open-source-slots; maths from slotopol, MIT), ported from their engines' evaluate.ts and
 * round.ts. A wild joins any run from the left and multiplies it by WILD_MULT; a run of wilds pays as the wild's
 * own line (each line pays the better). Scatters pay anywhere and start free games on the bonus strips, where
 * every win is multiplied by FREE_MULT and scatters can add more. Rounds have the client's RoundResult shape:
 * {baseSpin, bonus?: {spins, spinsPlayed, retriggers, totalWin}, totalWin}.
 */
abstract class WildLinesFreeGames implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const WILD_MULT = 2;

    /** Every free game win is multiplied by this. */
    public const FREE_MULT = 1;

    /** @return list<string> slotopol's symbol ids 1…n, in order */
    abstract public static function symbols(): array;

    /** @return list<list<int>> */
    abstract public static function baseStrips(): array;

    /** @return list<list<int>> */
    abstract public static function bonusStrips(): array;

    /** @return list<list<int>> */
    abstract public static function paylines(): array;

    /** @return array<string, list<int>> per line bet, for a run of 1…5 */
    abstract public static function linePay(): array;

    /** @return list<int> total bets for 1…5 scatters */
    abstract public static function scatPay(): array;

    /** @return list<int> free games for 1…5 scatters */
    abstract public static function scatFreeSpins(): array;

    abstract public static function wild(): string;

    abstract public static function scatter(): string;

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
        $symbols = static::symbols();

        return array_map(fn (array $strip) => array_map(fn (int $n) => $symbols[$n - 1], $strip), $strips);
    }

    public function play(Closure $random): array
    {
        $base = static::evaluate(self::draw(static::reels(static::baseStrips()), $random));
        $round = ['baseSpin' => $base];
        $total = $base['totalWin'];

        if ($base['freeSpinsWon'] > 0) {
            $bonusReels = static::reels(static::bonusStrips());
            $spins = [];
            $left = $base['freeSpinsWon'];
            $retriggers = 0;
            $bonusWin = 0.0;
            while ($left > 0) {
                $left--;
                $spin = static::evaluate(self::draw($bonusReels, $random), true);
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
    protected static function draw(array $reels, Closure $random): array
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
     * One line's pay, as slotopol's ScanLined: [symbol, count, mult, pay per line bet], or null.
     *
     * @param  list<string>  $symbols  the line's symbol on each reel
     * @return array{string, int, int, int}|null
     */
    public static function lineWin(array $symbols, bool $freeGame = false): ?array
    {
        $wild = static::wild();
        $pays = static::linePay();
        $syml = null;
        $numw = 0;
        $numl = self::REELS;
        $mw = 1;
        foreach ($symbols as $index => $symbol) {
            $x = $index + 1;
            if (static::isWild($symbol, $freeGame)) {
                if ($syml === null) {
                    $numw = $x;
                }
                $mw = static::WILD_MULT;
            } elseif ($syml === null) {
                $syml = $symbol;
            } elseif ($symbol !== $syml) {
                $numl = $x - 1;
                break;
            }
        }

        $payw = $numw >= 2 ? $pays[$wild][$numw - 1] : 0;
        $payl = $numl >= 2 && $syml !== null ? $pays[$syml][$numl - 1] : 0;
        if ($payl * $mw > $payw) {
            return [(string) $syml, $numl, $mw, $payl * $mw];
        }
        if ($payw > 0) {
            return [$wild, $numw, 1, $payw];
        }

        return null;
    }

    /** Whether a symbol stands in for others on a line (a game may add one in its free games). */
    protected static function isWild(string $symbol, bool $freeGame): bool
    {
        return $symbol === static::wild();
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array<string, mixed>
     */
    public static function evaluate(array $grid, bool $freeGame = false): array
    {
        $mm = $freeGame ? static::FREE_MULT : 1;
        $lines = count(static::paylines());
        $wins = [];
        foreach (static::paylines() as $index => $line) {
            $win = static::lineWin(array_map(fn (int $row, int $reel) => $grid[$reel][$row], $line, array_keys($line)), $freeGame);
            if ($win === null) {
                continue;
            }
            [$symbol, $count, $mult, $pay] = $win;
            $positions = [];
            for ($reel = 0; $reel < $count; $reel++) {
                $positions[] = [$reel, $line[$reel]];
            }
            $wins[] = ['line' => $index, 'symbol' => $symbol, 'count' => $count, 'mult' => $mult, 'amount' => $pay * $mm / $lines, 'positions' => $positions];
        }

        $scatters = [];
        foreach ($grid as $reel => $column) {
            foreach ($column as $row => $symbol) {
                if ($symbol === static::scatter()) {
                    $scatters[] = [$reel, $row];
                }
            }
        }

        $spin = ['grid' => $grid, 'lineWins' => $wins];
        $total = (float) array_sum(array_column($wins, 'amount'));
        $free = 0;
        if (count($scatters) >= 2) {
            $n = min(count($scatters), 5);
            $amount = static::scatPay()[$n - 1] * $mm;
            $spin['scatterWin'] = ['count' => count($scatters), 'amount' => $amount, 'positions' => $scatters];
            $total += $amount;
            $free = static::scatFreeSpins()[$n - 1];
        }
        $spin['freeSpinsWon'] = $free;
        if (static::FREE_MULT !== 1) {
            $spin['multiplier'] = $mm;
        }
        $spin['totalWin'] = $total;

        return $spin;
    }
}
