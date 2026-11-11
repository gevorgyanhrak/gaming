<?php

namespace App\Games\Blackjack\Engine;

use App\Gameplay\Random\RandomSource;

/**
 * Cards are rank + suit, e.g. "TH" (ten of hearts) or "AS", as in the other card games.
 */
final class BlackjackCards
{
    private const RANKS = '23456789TJQKA';

    private const SUITS = 'SHDC';

    /**
     * A shuffled shoe of $decks standard decks.
     *
     * @return list<string>
     */
    public static function shoe(int $decks, RandomSource $random): array
    {
        $cards = [];
        for ($d = 0; $d < $decks; $d++) {
            foreach (str_split(self::SUITS) as $suit) {
                foreach (str_split(self::RANKS) as $rank) {
                    $cards[] = $rank.$suit;
                }
            }
        }
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }

        return $cards;
    }

    /** Ace 11 (counted as 1 by total() when needed), faces 10. */
    public static function value(string $card): int
    {
        return match ($card[0]) {
            'A' => 11,
            'T', 'J', 'Q', 'K' => 10,
            default => (int) $card[0],
        };
    }

    /**
     * The best total and whether it is soft (an ace still counted as 11).
     *
     * @param  list<string>  $cards
     * @return array{0: int, 1: bool}
     */
    public static function total(array $cards): array
    {
        $total = 0;
        $aces = 0;
        foreach ($cards as $card) {
            $total += self::value($card);
            $aces += $card[0] === 'A' ? 1 : 0;
        }
        while ($total > 21 && $aces > 0) {
            $total -= 10;
            $aces--;
        }

        return [$total, $aces > 0];
    }

    /**
     * Two cards worth 21.
     *
     * @param  list<string>  $cards
     */
    public static function isBlackjack(array $cards): bool
    {
        return count($cards) === 2 && self::total($cards)[0] === 21;
    }
}
