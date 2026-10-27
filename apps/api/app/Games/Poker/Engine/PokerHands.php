<?php

namespace App\Games\Poker\Engine;

use App\Gameplay\Random\RandomSource;

/**
 * The 52-card deck and hand ranking. A card is rank + suit, e.g. "AS", "TH", "2C".
 */
final class PokerHands
{
    public const RANKS = '23456789TJQKA';

    public const SUITS = ['S', 'H', 'D', 'C'];

    /** Hand categories, weakest first. */
    public const CATEGORIES = ['high_card', 'pair', 'two_pair', 'three_of_a_kind', 'straight', 'flush', 'full_house', 'four_of_a_kind', 'straight_flush'];

    /**
     * @return list<string>
     */
    public static function deck(): array
    {
        $deck = [];
        foreach (self::SUITS as $suit) {
            foreach (str_split(self::RANKS) as $rank) {
                $deck[] = $rank.$suit;
            }
        }

        return $deck;
    }

    /**
     * @param  list<string>  $cards
     * @return list<string>
     */
    public static function shuffle(array $cards, RandomSource $random): array
    {
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }

        return $cards;
    }

    /**
     * The best five-card hand out of five to seven cards: its score (higher wins) and category.
     *
     * @param  list<string>  $cards
     * @return array{0: int, 1: string}
     */
    public static function best(array $cards): array
    {
        $best = -1;
        foreach (self::fives(count($cards)) as $indexes) {
            $best = max($best, self::score(array_map(fn (int $i) => $cards[$i], $indexes)));
        }

        return [$best, self::CATEGORIES[intdiv($best, 13 ** 5)]];
    }

    /**
     * Every way to pick five of $n positions (21 for seven cards), computed once per size.
     *
     * @return list<list<int>>
     */
    private static function fives(int $n): array
    {
        static $cache = [5 => [[0, 1, 2, 3, 4]]];

        if (! isset($cache[$n])) {
            $cache[$n] = [];
            for ($skipA = 0; $skipA < $n; $skipA++) {
                for ($skipB = $skipA; $skipB < $n; $skipB++) {
                    $kept = array_values(array_diff(range(0, $n - 1), [$skipA, $skipB]));
                    if (count($kept) === 5) {
                        $cache[$n][implode(',', $kept)] = $kept;
                    }
                }
            }
            $cache[$n] = array_values($cache[$n]);
        }

        return $cache[$n];
    }

    /**
     * Score of exactly five cards: category * 13^5 plus the deciding ranks.
     *
     * @param  list<string>  $cards
     */
    public static function score(array $cards): int
    {
        $ranks = [];
        $suits = [];
        foreach ($cards as $card) {
            $ranks[] = strpos(self::RANKS, $card[0]);
            $suits[$card[1]] = true;
        }
        rsort($ranks);

        $counts = array_count_values($ranks);
        // Groups by size, then by rank: [[rank, count], ...]
        $groups = [];
        foreach ($counts as $rank => $count) {
            $groups[] = [$rank, $count];
        }
        usort($groups, fn (array $x, array $y) => [$y[1], $y[0]] <=> [$x[1], $x[0]]);
        $order = array_column($groups, 0);

        $flush = count($suits) === 1;
        $straightHigh = null;
        if (count($counts) === 5) {
            if ($ranks[0] - $ranks[4] === 4) {
                $straightHigh = $ranks[0];
            } elseif ($ranks === [12, 3, 2, 1, 0]) {
                $straightHigh = 3;    // the wheel, A-2-3-4-5
            }
        }

        $shape = array_column($groups, 1);
        [$category, $kickers] = match (true) {
            $straightHigh !== null && $flush => [8, [$straightHigh]],
            $shape === [4, 1] => [7, $order],
            $shape === [3, 2] => [6, $order],
            $flush => [5, $ranks],
            $straightHigh !== null => [4, [$straightHigh]],
            $shape === [3, 1, 1] => [3, $order],
            $shape === [2, 2, 1] => [2, $order],
            $shape === [2, 1, 1, 1] => [1, $order],
            default => [0, $ranks],
        };

        $score = $category;
        for ($i = 0; $i < 5; $i++) {
            $score = $score * 13 + ($kickers[$i] ?? 0);
        }

        return $score;
    }
}
