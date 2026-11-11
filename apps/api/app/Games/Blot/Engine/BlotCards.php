<?php

namespace App\Games\Blot\Engine;

use App\Gameplay\Random\RandomSource;

/**
 * The 32-card blot deck (seven to ace), card points, trick taking and declarations. Cards are rank + suit,
 * e.g. "JS" (jack of spades) or "TH" (ten of hearts).
 */
final class BlotCards
{
    public const SUITS = ['S', 'H', 'D', 'C'];

    /** Rank order in a sequence (terz, fifty, hundred). */
    private const SEQUENCE = '789TJQKA';

    /** Trick-taking order, lowest first. */
    private const TRUMP_ORDER = '78QKTA9J';

    private const PLAIN_ORDER = '789JQKTA';

    private const TRUMP_POINTS = ['J' => 20, '9' => 14, 'A' => 11, 'T' => 10, 'K' => 4, 'Q' => 3];

    private const PLAIN_POINTS = ['A' => 11, 'T' => 10, 'K' => 4, 'Q' => 3, 'J' => 2];

    /** Four of a kind: jacks 200, nines 150, aces, tens, kings and queens 100 (sevens and eights nothing). */
    private const CARRE = ['J' => 200, '9' => 150, 'A' => 100, 'T' => 100, 'K' => 100, 'Q' => 100];

    /**
     * @return list<string>
     */
    public static function shuffled(RandomSource $random): array
    {
        $cards = [];
        foreach (self::SUITS as $suit) {
            foreach (str_split(self::SEQUENCE) as $rank) {
                $cards[] = $rank.$suit;
            }
        }
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }

        return $cards;
    }

    public static function suit(string $card): string
    {
        return $card[1];
    }

    public static function points(string $card, string $trump): int
    {
        $table = $card[1] === $trump ? self::TRUMP_POINTS : self::PLAIN_POINTS;

        return $table[$card[0]] ?? 0;
    }

    /**
     * How strongly a card plays in a trick: trumps beat everything, the led suit beats the rest, other cards
     * can't win (-1).
     */
    public static function strength(string $card, string $led, string $trump): int
    {
        return match ($card[1]) {
            $trump => 100 + strpos(self::TRUMP_ORDER, $card[0]),
            $led => strpos(self::PLAIN_ORDER, $card[0]),
            default => -1,
        };
    }

    /**
     * The position in $cards (in play order) of the card winning the trick so far.
     *
     * @param  list<string>  $cards
     */
    public static function winning(array $cards, string $trump): int
    {
        $led = $cards[0][1];
        $best = 0;
        foreach ($cards as $i => $card) {
            if (self::strength($card, $led, $trump) > self::strength($cards[$best], $led, $trump)) {
                $best = $i;
            }
        }

        return $best;
    }

    /**
     * A hand's declarations: sequences of three or more in a suit (20, four 50, five or more 100) and four
     * of a kind. Each has the points and a rank to compare them by (points, four of a kind over a
     * sequence, then the top card).
     *
     * @param  list<string>  $hand
     * @return list<array{kind: string, cards: list<string>, points: int, rank: int}>
     */
    public static function declarations(array $hand): array
    {
        $found = [];
        foreach (self::SUITS as $suit) {
            $ranks = [];
            foreach ($hand as $card) {
                if ($card[1] === $suit) {
                    $ranks[] = strpos(self::SEQUENCE, $card[0]);
                }
            }
            sort($ranks);
            $run = [];
            foreach ([...$ranks, 99] as $rank) {
                if ($run !== [] && $rank !== end($run) + 1) {
                    if (count($run) >= 3) {
                        $points = match (count($run)) {
                            3 => 20,
                            4 => 50,
                            default => 100,
                        };
                        $cards = array_map(fn (int $r) => self::SEQUENCE[$r].$suit, $run);
                        $found[] = ['kind' => match ($points) {
                            20 => 'terz',
                            50 => 'fifty',
                            default => 'hundred',
                        }, 'cards' => $cards, 'points' => $points, 'rank' => $points * 100 + end($run)];
                    }
                    $run = [];
                }
                $run[] = $rank;
            }
        }

        foreach (self::CARRE as $rank => $points) {
            $cards = array_values(array_filter($hand, fn (string $card) => $card[0] === (string) $rank));
            if (count($cards) === 4) {
                $found[] = ['kind' => 'carre', 'cards' => $cards, 'points' => $points, 'rank' => $points * 100 + 50 + strpos(self::SEQUENCE, (string) $rank)];
            }
        }

        return $found;
    }
}
