<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\HouseSlot;
use Closure;

/**
 * BUMPER CROP, a 5x3, 25-line avalanche slot. The scarecrow is wild (a run of scarecrows pays as his own line, each
 * line paying the better); runs pay from 3. Winning cells are harvested, the columns fall and the reels roll back
 * by as many symbols, and the new board scores again until a fall pays nothing (at most 20 falls). 3+ tractors
 * anywhere pay 5/20/200 total bets and open the barn ladder: five levels of shuffled cells turned from the left
 * (prizes; golden prizes and multipliers climb; the bucket ends it), the prizes paid × the multiplier. Port of
 * APEXSLOTS' bumper-crop engine (MIT, github.com/Z1000k/open-source-slots), maths slotopol "cashfarm" (MIT; the
 * ladder adds a climbing prize as slotopol's own Pay() and RTP tables do): 95.548865 %. Rounds have the client's
 * shape: {falls: [{index, grid, lineWins, scatterWin?, bonus?, cleared, win}], finalGrid, cascades, totalWin}.
 */
final class BumperCrop implements HouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const WILD = 'SCARECROW';

    public const SCATTER = 'TRACTOR';

    public const FALL_LIMIT = 20;

    public const SYMBOLS = ['SCARECROW', 'COW', 'RAM', 'PIG', 'RABBIT', 'ROOSTER', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'NINE', 'TRACTOR'];

    /** slotopol's 95.548865 % set (symbols 67.462352 % + farm 28.086514 %). */
    public const STRIPS = [
        [8, 6, 11, 4, 9, 5, 7, 2, 9, 12, 8, 5, 10, 6, 12, 3, 11, 2, 10, 4, 8, 12, 5, 11, 2, 7, 3, 12, 1, 9, 7, 3, 11, 4, 10, 6, 13],
        [2, 12, 9, 5, 12, 8, 3, 9, 8, 3, 10, 4, 8, 6, 12, 4, 11, 6, 10, 2, 11, 3, 7, 4, 10, 2, 11, 5, 12, 11, 1, 7, 9, 5, 13, 6, 7],
        [9, 3, 12, 2, 7, 5, 10, 2, 11, 8, 6, 12, 1, 10, 6, 9, 12, 4, 13, 3, 11, 6, 7, 2, 8, 4, 11, 5, 12, 3, 9, 10, 11, 4, 7, 8, 5],
        [7, 5, 8, 1, 12, 6, 10, 4, 7, 12, 10, 9, 2, 7, 3, 10, 6, 8, 11, 2, 8, 4, 11, 3, 12, 5, 9, 4, 13, 5, 9, 11, 3, 12, 6, 11, 2],
        [7, 8, 5, 13, 8, 6, 9, 4, 12, 11, 1, 10, 3, 9, 6, 7, 2, 11, 3, 7, 6, 11, 2, 12, 3, 8, 5, 12, 2, 10, 11, 4, 12, 5, 9, 10, 4],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2], [2, 1, 1, 1, 0],
        [0, 1, 1, 1, 2], [1, 2, 1, 0, 1], [1, 0, 1, 2, 1], [0, 1, 0, 1, 0], [2, 1, 2, 1, 2],
        [1, 1, 0, 1, 1], [1, 1, 2, 1, 1], [0, 2, 0, 2, 0], [2, 0, 2, 0, 2], [2, 0, 1, 0, 2],
        [0, 2, 1, 2, 0], [0, 0, 2, 0, 0], [2, 2, 0, 2, 2], [1, 0, 2, 0, 1], [1, 2, 0, 2, 1],
    ];

    public const LINE_PAY = [
        'SCARECROW' => [0, 0, 100, 750, 5000],
        'COW' => [0, 0, 75, 300, 1000],
        'RAM' => [0, 0, 50, 150, 500],
        'PIG' => [0, 0, 50, 150, 500],
        'RABBIT' => [0, 0, 30, 100, 300],
        'ROOSTER' => [0, 0, 30, 100, 300],
        'ACE' => [0, 0, 15, 75, 200],
        'KING' => [0, 0, 15, 75, 200],
        'QUEEN' => [0, 0, 10, 50, 150],
        'JACK' => [0, 0, 10, 50, 150],
        'TEN' => [0, 0, 5, 25, 75],
        'NINE' => [0, 0, 5, 25, 75],
    ];

    public const SCAT_PAY = [0, 0, 5, 20, 200];

    /** Ladder prize values: levels 1–3, level 4, level 5 (total bets). */
    public const REEL_LOW = [2, 2, 3, 4, 5, 6, 8, 10, 14];

    public const REEL_MID = [14, 14, 20, 24];

    public const REEL_TOP = [20, 24, 40, 50];

    public function id(): string
    {
        return 'bumper-crop';
    }

    /** Avalanches and several ladders can stack in one round: the wallet's cap is wide. */
    public function maxWin(): int
    {
        return 100_000;
    }

    /** @return list<list<string>> */
    public static function reels(): array
    {
        return array_map(fn (array $strip) => array_map(fn (int $n) => self::SYMBOLS[$n - 1], $strip), self::STRIPS);
    }

    /**
     * Cyclic strip lookup that also takes negative positions (slotopol ReelAt).
     *
     * @param  list<string>  $reel
     */
    private static function reelAt(array $reel, int $pos): string
    {
        $n = count($reel);

        return $reel[(($pos % $n) + $n) % $n];
    }

    public function play(Closure $random): array
    {
        $reels = self::reels();
        $grid = [];
        $seed = [];
        foreach ($reels as $x => $reel) {
            $pos = $random(count($reel));
            $grid[$x] = [$reel[$pos % count($reel)], $reel[($pos + 1) % count($reel)], $reel[($pos + 2) % count($reel)]];
            $seed[$x] = $pos;
        }

        $falls = [];
        $total = 0.0;
        for ($index = 0; $index < self::FALL_LIMIT; $index++) {
            $scan = self::evaluate($grid);
            if ($index > 0 && $scan['win'] == 0) {
                break;
            }
            $cleared = self::clearedCells($scan);
            $bonus = $scan['bonusTriggered'] ? self::playBonus($random) : null;
            $win = $scan['win'] + ($bonus['totalWin'] ?? 0);
            $total += $win;

            $fall = ['index' => $index, 'grid' => $grid, 'lineWins' => $scan['lineWins']];
            if (isset($scan['scatterWin'])) {
                $fall['scatterWin'] = $scan['scatterWin'];
            }
            if ($bonus !== null) {
                $fall['bonus'] = $bonus;
            }
            $falls[] = [...$fall, 'cleared' => $cleared, 'win' => $win];

            if ($cleared === []) {
                break;
            }
            [$grid, $seed] = self::pushFall($grid, $seed, $reels, $cleared);
        }

        $round = ['falls' => $falls, 'finalGrid' => $grid, 'cascades' => max(0, count($falls) - 1), 'totalWin' => $total];

        return ['round' => $round, 'total_win' => $total];
    }

    /**
     * Clears the cells and lets each column fall, topping up from the strip above (slotopol Cascade5x3.PushFall).
     *
     * @param  array<int, array<int, string>>  $grid
     * @param  array<int, int>  $seed
     * @param  list<list<string>>  $reels
     * @param  list<array{int, int}>  $cleared
     * @return array{array<int, array<int, string>>, array<int, int>}
     */
    public static function pushFall(array $grid, array $seed, array $reels, array $cleared): array
    {
        $hits = [];
        foreach ($cleared as [$x, $y]) {
            $hits[$x][$y] = true;
        }
        for ($x = 0; $x < self::REELS; $x++) {
            $col = $grid[$x];
            $n = 0;
            for ($y = 0; $y < self::ROWS; $y++) {
                if (! isset($hits[$x][$y])) {
                    continue;
                }
                for ($i = 0; $i < $y; $i++) {
                    $col[$y - $i] = $col[$y - $i - 1];
                }
                $n++;
            }
            $seed[$x] -= $n;
            for ($y = 0; $y < $n; $y++) {
                $col[$y] = self::reelAt($reels[$x], $seed[$x] + $y);
            }
            $grid[$x] = $col;
        }

        return [$grid, $seed];
    }

    /**
     * One line (slotopol ScanLined): [symbol, count, pay per line bet] or null. The tractor has no line pay.
     *
     * @param  list<string>  $symbols
     * @return array{string, int, int}|null
     */
    public static function lineWin(array $symbols): ?array
    {
        $wilds = 0;
        $syml = null;
        $count = self::REELS;
        foreach ($symbols as $x => $symbol) {
            if ($symbol === self::WILD) {
                if ($syml === null) {
                    $wilds = $x + 1;
                }
            } elseif ($syml === null) {
                $syml = $symbol;
            } elseif ($symbol !== $syml) {
                $count = $x;
                break;
            }
        }
        $payWild = $wilds >= 3 ? self::LINE_PAY[self::WILD][$wilds - 1] : 0;
        $paySym = $count >= 3 && $syml !== null && $syml !== self::SCATTER ? self::LINE_PAY[$syml][$count - 1] : 0;
        if ($paySym > $payWild) {
            return [(string) $syml, $count, $paySym];
        }

        return $payWild > 0 ? [self::WILD, $wilds, $payWild] : null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @return array{lineWins: list<array<string, mixed>>, scatterWin?: array<string, mixed>, bonusTriggered: bool, win: float}
     */
    public static function evaluate(array $grid): array
    {
        $lines = count(self::PAYLINES);
        $wins = [];
        foreach (self::PAYLINES as $index => $line) {
            $win = self::lineWin(array_map(fn (int $row, int $reel) => $grid[$reel][$row], $line, array_keys($line)));
            if ($win === null) {
                continue;
            }
            [$symbol, $count, $pay] = $win;
            $positions = [];
            for ($x = 0; $x < $count; $x++) {
                $positions[] = [$x, $line[$x]];
            }
            $wins[] = ['line' => $index, 'symbol' => $symbol, 'count' => $count, 'amount' => $pay / $lines, 'positions' => $positions];
        }

        $scatters = [];
        foreach ($grid as $x => $column) {
            foreach ($column as $y => $symbol) {
                if ($symbol === self::SCATTER) {
                    $scatters[] = [$x, $y];
                }
            }
        }
        $scan = ['lineWins' => $wins];
        $win = (float) array_sum(array_column($wins, 'amount'));
        if (count($scatters) >= 3) {
            $amount = self::SCAT_PAY[min(count($scatters), 5) - 1];
            $scan['scatterWin'] = ['count' => count($scatters), 'amount' => $amount, 'positions' => $scatters];
            $win += $amount;
        }

        return [...$scan, 'bonusTriggered' => isset($scan['scatterWin']), 'win' => $win];
    }

    /**
     * Every cell that pays on this scan, each once.
     *
     * @param  array<string, mixed>  $scan
     * @return list<array{int, int}>
     */
    public static function clearedCells(array $scan): array
    {
        $cells = [];
        foreach ([...array_merge(...array_column($scan['lineWins'], 'positions')), ...($scan['scatterWin']['positions'] ?? [])] as [$x, $y]) {
            $cells[$x * self::ROWS + $y] ??= [$x, $y];
        }

        return array_values($cells);
    }

    /**
     * The five levels, unshuffled (kind pay|mult|bucket, value, climbs); `$value` picks a prize from its list (a
     * test passes the list's mean, hence int|float).
     *
     * @param  Closure(list<int>): (int|float)  $value
     * @return list<list<array{kind: string, value: int|float, climbs: bool}>>
     */
    public static function ladder(Closure $value): array
    {
        $prize = fn (int|float $v, bool $climbs) => ['kind' => 'pay', 'value' => $v, 'climbs' => $climbs];
        $mult = ['kind' => 'mult', 'value' => 0, 'climbs' => true];
        $bucket = ['kind' => 'bucket', 'value' => 0, 'climbs' => false];
        $low = fn () => $value(self::REEL_LOW);

        return [
            [$prize($low(), false), $prize($low(), false), $prize($low(), false), $prize($low(), true), $prize($low(), true), $prize($low(), true)],
            [$prize($low(), false), $prize($low(), false), $prize($low(), true), $mult, $bucket],
            [$prize($low(), false), $prize($low(), true), $mult, $bucket],
            [$prize($value(self::REEL_MID), false), $mult, $bucket],
            [$prize($value(self::REEL_TOP), false), $bucket],
        ];
    }

    /**
     * Plays the barn ladder out (slotopol cashfarm farm.go).
     *
     * @param  Closure(int): int  $random
     * @return array<string, mixed>
     */
    public static function playBonus(Closure $random): array
    {
        $ladder = self::ladder(fn (array $values) => $values[$random(count($values))]);
        foreach ($ladder as $i => $cells) {
            for ($k = count($cells) - 1; $k > 0; $k--) {
                $j = $random($k + 1);
                [$cells[$k], $cells[$j]] = [$cells[$j], $cells[$k]];
            }
            $ladder[$i] = $cells;
        }

        $levels = array_map(fn (array $cells) => ['cells' => $cells, 'revealed' => 0], $ladder);
        $pay = 0;
        $multiplier = 1;
        $reached = 0;
        foreach ($levels as $i => $level) {
            $reached++;
            foreach ($level['cells'] as $cell) {
                $levels[$i]['revealed']++;
                if ($cell['kind'] === 'bucket') {
                    break 2;
                }
                if ($cell['kind'] === 'mult') {
                    $multiplier++;
                } else {
                    $pay += $cell['value'];
                }
                if ($cell['climbs']) {
                    break;
                }
            }
        }

        return ['levels' => $levels, 'levelsReached' => $reached, 'pay' => $pay, 'multiplier' => $multiplier, 'totalWin' => $pay * $multiplier];
    }
}
