<?php

namespace App\Games\Durak\Engine;

use App\Gameplay\Random\RandomSource;

/**
 * The 36-card deck. A card is rank + suit, e.g. "6S", "TH" (ten of hearts), "AD"; suits are S, H,
 * D and C.
 */
final class DurakCards
{
    public const RANKS = ['6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A'];

    public const SUITS = ['S', 'H', 'D', 'C'];

    /**
     * A shuffled deck (Fisher–Yates through the recorded random source).
     *
     * @return list<string>
     */
    public static function shuffled(RandomSource $random): array
    {
        $deck = [];
        foreach (self::SUITS as $suit) {
            foreach (self::RANKS as $rank) {
                $deck[] = $rank.$suit;
            }
        }
        for ($i = count($deck) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$deck[$i], $deck[$j]] = [$deck[$j], $deck[$i]];
        }

        return $deck;
    }

    public static function rank(string $card): int
    {
        return (int) array_search($card[0], self::RANKS, true);
    }

    public static function suit(string $card): string
    {
        return $card[1];
    }

    /** Whether $defense beats $attack. */
    public static function beats(string $defense, string $attack, string $trump): bool
    {
        if (self::suit($defense) === self::suit($attack)) {
            return self::rank($defense) > self::rank($attack);
        }

        return self::suit($defense) === $trump;
    }

    /** For ordering and bots: trumps rank above every other card. */
    public static function strength(string $card, string $trump): int
    {
        return self::rank($card) + (self::suit($card) === $trump ? 9 : 0);
    }
}
