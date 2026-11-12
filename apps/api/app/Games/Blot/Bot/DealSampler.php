<?php

namespace App\Games\Blot\Bot;

use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Ways the hidden cards could lie, as far as a seat can tell: every card it hasn't seen goes to a seat that
 * may hold it (CardMemory: voids, the trump limit, shown cards), each hand gets as many as it has, and
 * deals that fit the bazar are kept more often (rejection sampling: a bidder's hand is usually worth their
 * bid; someone who passed seldom held a hand that could comfortably bid).
 */
final class DealSampler
{
    private const FIT_TRIES = 12;

    private const DEAL_TRIES = 30;

    public function __construct(private readonly BlotBot $bot = new BlotBot) {}

    /**
     * @param  array<string, mixed>  $view
     * @return array<int, list<string>> current hands of all four seats
     */
    public function sample(CardMemory $memory, array $view, RandomSource $random, bool $readPasses = true): array
    {
        $best = null;
        $bestFit = -1.0;
        for ($try = 0; $try < self::FIT_TRIES; $try++) {
            $hands = $this->deal($memory, $view, $random);
            $fit = $this->likelihood($this->original($hands, $view), $view, $readPasses);
            if ($random->int(1, 1000) <= (int) round($fit * 1000)) {
                return $hands;
            }
            if ($fit > $bestFit) {
                [$best, $bestFit] = [$hands, $fit];
            }
        }

        return $best ?? $this->deal($memory, $view, $random);
    }

    /**
     * One deal of the unseen cards that respects what is known (constraints first; capacity only if they
     * can't all be met, which happens only if someone broke the rules of play — they can't here).
     *
     * @param  array<string, mixed>  $view
     * @return array<int, list<string>>
     */
    public function deal(CardMemory $memory, array $view, RandomSource $random): array
    {
        $seat = $memory->seat;
        $sizes = $view['hand_sizes'];
        $base = [];
        $room = [];
        foreach (range(0, 3) as $s) {
            $base[$s] = $s === $seat ? $memory->hand : $memory->known[$s];
            $room[$s] = $s === $seat ? 0 : max(0, (int) $sizes[$s] - count($memory->known[$s]));
        }
        $unseen = $memory->unseen();

        for ($attempt = 0; $attempt <= self::DEAL_TRIES; $attempt++) {
            $strict = $attempt < self::DEAL_TRIES;
            $cards = $this->shuffle($unseen, $random);
            // Most constrained cards first.
            $options = [];
            foreach ($cards as $card) {
                $options[$card] = array_values(array_filter([0, 1, 2, 3], fn (int $s) => $s !== $seat && (! $strict || $memory->mayHold($s, $card))));
            }
            usort($cards, fn (string $a, string $b) => count($options[$a]) <=> count($options[$b]));

            $hands = $base;
            $left = $room;
            $ok = true;
            foreach ($cards as $card) {
                $open = array_values(array_filter($options[$card], fn (int $s) => $left[$s] > 0));
                if ($open === []) {
                    $ok = false;
                    break;
                }
                // Weighted by the room left, so hands fill evenly.
                $pick = $random->int(1, array_sum(array_map(fn (int $s) => $left[$s], $open)));
                foreach ($open as $s) {
                    $pick -= $left[$s];
                    if ($pick <= 0) {
                        $hands[$s][] = $card;
                        $left[$s]--;
                        break;
                    }
                }
            }
            if ($ok) {
                return $hands;
            }
        }

        return $base;   // unreachable in practice: the last attempt ignores the constraints
    }

    /**
     * The hands as dealt: what each seat holds now plus what it has played this deal.
     *
     * @param  array<int, list<string>>  $hands
     * @param  array<string, mixed>  $view
     * @return array<int, list<string>>
     */
    public function original(array $hands, array $view): array
    {
        $tricks = $view['history'] ?? [];
        $tricks[] = ['cards' => $view['trick'] ?? []];
        foreach ($tricks as $trick) {
            foreach ($trick['cards'] as $play) {
                $hands[(int) $play['seat']][] = (string) $play['card'];
            }
        }

        return $hands;
    }

    /**
     * How well dealt hands fit the bazar. Never zero: people over- and underbid.
     *
     * @param  array<int, list<string>>  $hands  as dealt
     * @param  array<string, mixed>  $view
     */
    public function likelihood(array $hands, array $view, bool $readPasses): float
    {
        $seat = (int) $view['seat'];
        $weight = 1.0;
        $lowest = BlotEngine::MIN_BID;
        foreach ($view['bids'] ?? [] as $bid) {
            $who = (int) $bid['seat'];
            $isBid = $bid['type'] === 'bid' || $bid['type'] === 'kaput';
            $value = $bid['type'] === 'kaput' ? BlotEngine::KAPUT : (int) ($bid['value'] ?? 0);
            if ($who !== $seat && $isBid) {
                $suit = $bid['suit'] === 'N' ? null : (string) $bid['suit'];
                $weight *= max(0.05, $this->sigmoid(($this->bot->strength($hands[$who], $suit) - ($value * 10 - 20)) / 15));
            } elseif ($who !== $seat && $bid['type'] === 'pass' && $readPasses) {
                $best = max(array_map(fn (string $s) => $this->bot->strength($hands[$who], $s === 'N' ? null : $s), BlotEngine::BID_SUITS));
                $weight *= max(0.1, 1 - 0.85 * $this->sigmoid(($best - ($lowest * 10 + 20)) / 15));
            } elseif ($who !== $seat && $bid['type'] === 'contra' && ($view['contract']['suit'] ?? null) !== null) {
                // A Quansh is said holding defence in their trump.
                $trump = (string) $view['contract']['suit'];
                $top = count(array_intersect(['J'.$trump, '9'.$trump, 'A'.$trump, 'T'.$trump], $hands[$who]));
                $weight *= [0.15, 0.4, 0.8, 1.0, 1.0][$top];
            }
            if ($isBid) {
                $lowest = $value + 1;
            }
        }

        return $weight;
    }

    private function sigmoid(float $x): float
    {
        return 1 / (1 + exp(-$x));
    }

    /**
     * @param  list<string>  $cards
     * @return list<string>
     */
    private function shuffle(array $cards, RandomSource $random): array
    {
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }

        return $cards;
    }
}
