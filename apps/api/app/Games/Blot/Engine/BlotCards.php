<?php

namespace App\Games\Blot\Engine;

use App\Gameplay\Random\RandomSource;

/**
 * The 32-card blot deck (seven to ace), card points, trick taking and declarations, per
 * docs/games/blot-rules.md. Cards are rank + suit, e.g. "JS" (jack of spades), "TH" (ten of hearts). A
 * no-trump game passes null as the trump.
 */
final class BlotCards
{
    public const SUITS = ['S', 'H', 'D', 'C'];

    /** Rank order in a sequence (terz, 50, 100). */
    private const SEQUENCE = '789TJQKA';

    /** Trick-taking order, lowest first. */
    private const TRUMP_ORDER = '78QKTA9J';

    private const PLAIN_ORDER = '789JQKTA';

    private const TRUMP_POINTS = ['J' => 20, '9' => 14, 'A' => 11, 'T' => 10, 'K' => 4, 'Q' => 3];

    private const PLAIN_POINTS = ['A' => 11, 'T' => 10, 'K' => 4, 'Q' => 3, 'J' => 2];

    private const NO_TRUMP_POINTS = ['A' => 19, 'T' => 10, 'K' => 4, 'Q' => 3, 'J' => 2];

    /** Four of a kind in a trump game, and its order when two compare (J 9 A 10 K Q). */
    private const CARRE_TRUMP = ['J' => 200, '9' => 140, 'A' => 110, 'T' => 100, 'K' => 100, 'Q' => 100];

    private const CARRE_TRUMP_ORDER = 'QKTA9J';

    /** Four of a kind in no-trump (A 10 K Q J). */
    private const CARRE_NO_TRUMP = ['A' => 190, 'T' => 100, 'K' => 100, 'Q' => 100, 'J' => 100];

    private const CARRE_NO_TRUMP_ORDER = 'JQKTA';

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

    public static function points(string $card, ?string $trump): int
    {
        $table = match (true) {
            $trump === null => self::NO_TRUMP_POINTS,
            $card[1] === $trump => self::TRUMP_POINTS,
            default => self::PLAIN_POINTS,
        };

        return $table[$card[0]] ?? 0;
    }

    /**
     * How strongly a card plays in a trick: trumps beat everything, the led suit beats the rest, other cards
     * can't win (-1).
     */
    public static function strength(string $card, string $led, ?string $trump): int
    {
        return match (true) {
            $trump !== null && $card[1] === $trump => 100 + (int) strpos(self::TRUMP_ORDER, $card[0]),
            $card[1] === $led => (int) strpos(self::PLAIN_ORDER, $card[0]),
            default => -1,
        };
    }

    /**
     * The position in $cards (in play order) of the card winning the trick so far.
     *
     * @param  list<string>  $cards
     */
    public static function winning(array $cards, ?string $trump): int
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
     * Card points to sai (÷ 10; more than half rounds up: 15 → 1, 16 → 2).
     */
    public static function sai(int $points): int
    {
        return intdiv($points, 10) + ($points % 10 > 5 ? 1 : 0);
    }

    /**
     * The declarations a hand holds: each maximal sequence of three or more in a suit (terz 20, four 50,
     * five or more 100) and each four of a kind worth something in this game. `key` orders them for the
     * comparison between the pairs (points, four of a kind over a sequence, top card, a sequence in trumps).
     *
     * @param  list<string>  $hand
     * @return list<array{kind: string, cards: list<string>, points: int, key: list<int>}>
     */
    public static function declarations(array $hand, ?string $trump): array
    {
        $found = [];
        foreach (self::SUITS as $suit) {
            $ranks = [];
            foreach ($hand as $card) {
                if ($card[1] === $suit) {
                    $ranks[] = (int) strpos(self::SEQUENCE, $card[0]);
                }
            }
            sort($ranks);
            $run = [];
            foreach ([...$ranks, 99] as $rank) {
                if ($run !== [] && $rank !== end($run) + 1) {
                    if (count($run) >= 3) {
                        [$kind, $points] = match (count($run)) {
                            3 => ['terz', 20],
                            4 => ['fifty', 50],
                            default => ['hundred', 100],
                        };
                        $found[] = [
                            'kind' => $kind,
                            'cards' => array_map(fn (int $r) => self::SEQUENCE[$r].$suit, $run),
                            'points' => $points,
                            'key' => [$points, 0, (int) end($run), $suit === $trump ? 1 : 0],
                        ];
                    }
                    $run = [];
                }
                $run[] = $rank;
            }
        }

        [$values, $order] = $trump === null ? [self::CARRE_NO_TRUMP, self::CARRE_NO_TRUMP_ORDER] : [self::CARRE_TRUMP, self::CARRE_TRUMP_ORDER];
        foreach ($values as $rank => $points) {
            $rank = (string) $rank;
            $cards = array_values(array_filter($hand, fn (string $card) => $card[0] === $rank));
            if (count($cards) === 4) {
                $found[] = ['kind' => 'carre', 'cards' => $cards, 'points' => $points, 'key' => [$points, 1, (int) strpos($order, $rank), 0]];
            }
        }

        return $found;
    }
}
