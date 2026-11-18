<?php

namespace App\Platform\Slots\House\Games;

use App\Platform\Slots\House\CreditedHouseSlot;
use Closure;

/**
 * JUNGLE SLOT, a 5x3, 20-line slot: the treasure chest is wild (it stands in for any symbol and pays on its own);
 * leftmost runs of 3+ on a line pay. No scatter or free games. The art is "Jungle Slot Assets" by danitorres567
 * (OpenGameArt, CC0, see apps/web/public/play/jungle-slot/CREDITS.md); the maths is ours: every reel has the same
 * symbol counts (34 stops), so every line has the same symbol law and the RTP is exactly 95.61387608 % (its test
 * sums all 7^5 symbol combinations). Rounds: {grid, lineWins, totalWin}; amounts in total bets.
 */
final class JungleSlot implements CreditedHouseSlot
{
    public const REELS = 5;

    public const ROWS = 3;

    public const LINES = 20;

    public const WILD = 'WILD';

    public const SYMBOLS = ['WILD', 'PINEAPPLE', 'COCONUT', 'APRICOT', 'BANANA', 'MUSHROOM', 'K'];

    /** Symbol ids (1-based into SYMBOLS); each reel: 1 wild, 3 pineapples, 4 coconuts, 5 apricots, 7 bananas, 6 mushrooms, 8 Ks. */
    public const STRIPS = [
        [2, 6, 5, 7, 7, 6, 5, 6, 4, 4, 5, 7, 7, 2, 5, 4, 7, 5, 7, 7, 3, 3, 7, 6, 5, 4, 4, 3, 1, 6, 2, 3, 6, 5],
        [2, 4, 2, 4, 7, 4, 3, 7, 7, 3, 2, 7, 5, 5, 6, 6, 3, 7, 4, 3, 1, 5, 6, 7, 5, 6, 5, 6, 7, 5, 6, 5, 7, 4],
        [5, 7, 5, 5, 3, 5, 4, 7, 4, 6, 1, 2, 6, 3, 4, 5, 5, 7, 3, 7, 2, 7, 7, 5, 6, 7, 7, 3, 4, 6, 2, 6, 4, 6],
        [5, 4, 2, 6, 6, 5, 7, 6, 2, 3, 3, 7, 6, 5, 4, 7, 5, 3, 5, 7, 4, 5, 2, 4, 7, 5, 6, 6, 7, 1, 7, 3, 7, 4],
        [7, 6, 5, 7, 2, 6, 7, 6, 7, 7, 6, 7, 7, 4, 3, 5, 4, 7, 5, 4, 5, 3, 3, 2, 5, 3, 5, 5, 2, 6, 6, 4, 1, 4],
    ];

    /** Rows (0 top … 2 bottom) per reel for each of the 20 lines, in the order the frame numbers them. */
    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 0, 0, 0, 1], [1, 2, 2, 2, 1], [0, 0, 1, 2, 2], [2, 2, 1, 0, 0], [1, 2, 1, 0, 1],
        [1, 0, 1, 2, 1], [0, 1, 1, 1, 0], [2, 1, 1, 1, 2], [0, 1, 0, 1, 0], [2, 1, 2, 1, 2],
        [1, 1, 0, 1, 1], [1, 1, 2, 1, 1], [0, 0, 2, 0, 0], [2, 2, 0, 2, 2], [0, 2, 2, 2, 0],
    ];

    /** Per line bet, for a run of 1…5. */
    public const LINE_PAY = [
        'WILD' => [0, 0, 50, 250, 2500],
        'PINEAPPLE' => [0, 0, 25, 100, 500],
        'COCONUT' => [0, 0, 20, 75, 300],
        'APRICOT' => [0, 0, 15, 50, 200],
        'BANANA' => [0, 0, 10, 40, 150],
        'MUSHROOM' => [0, 0, 5, 25, 100],
        'K' => [0, 0, 5, 20, 75],
    ];

    public function id(): string
    {
        return 'jungle-slot';
    }

    public function credit(): string
    {
        return 'Art: "Jungle Slot Assets" by danitorres567 (OpenGameArt, CC0). Game and maths by this platform; every round is dealt on our server.';
    }

    /** Every line paying five wilds: 20 × 2500 line bets = 2500 total bets (an upper bound). */
    public function maxWin(): int
    {
        return 2500;
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

        return ['round' => $spin, 'total_win' => $spin['totalWin']];
    }

    /**
     * What one line pays, in line bets: the better of the wilds' own run and the run of the first other symbol
     * (wilds standing in for it), counted from the left.
     *
     * @param  list<string>  $symbols  left to right
     * @return array{symbol: string, count: int, pay: int}
     */
    public static function line(array $symbols): array
    {
        $wilds = 0;
        while ($wilds < count($symbols) && $symbols[$wilds] === self::WILD) {
            $wilds++;
        }
        $best = ['symbol' => self::WILD, 'count' => $wilds, 'pay' => $wilds > 0 ? self::LINE_PAY[self::WILD][$wilds - 1] : 0];
        $first = $symbols[$wilds] ?? null;
        if ($first !== null) {
            $count = 0;
            while ($count < count($symbols) && ($symbols[$count] === $first || $symbols[$count] === self::WILD)) {
                $count++;
            }
            $pay = self::LINE_PAY[$first][$count - 1];
            if ($pay > $best['pay']) {
                $best = ['symbol' => $first, 'count' => $count, 'pay' => $pay];
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array<int, string>>  $grid  [reel][row]
     * @return array{grid: array<int, array<int, string>>, lineWins: list<array<string, mixed>>, totalWin: float}
     */
    public static function evaluate(array $grid): array
    {
        $wins = [];
        foreach (self::PAYLINES as $index => $rows) {
            $symbols = [];
            foreach ($rows as $reel => $row) {
                $symbols[] = $grid[$reel][$row];
            }
            $win = self::line($symbols);
            if ($win['pay'] > 0) {
                $positions = [];
                for ($reel = 0; $reel < $win['count']; $reel++) {
                    $positions[] = [$reel, $rows[$reel]];
                }
                $wins[] = ['line' => $index, 'symbol' => $win['symbol'], 'count' => $win['count'], 'amount' => $win['pay'] / self::LINES, 'positions' => $positions];
            }
        }

        return ['grid' => $grid, 'lineWins' => $wins, 'totalWin' => (float) array_sum(array_column($wins, 'amount'))];
    }
}
