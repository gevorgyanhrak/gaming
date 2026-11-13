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
     * One deal of the unseen cards that respects everything known. Each card (most constrained first) goes
     * to a seat that may hold it, weighted by the room left, but only where the cards still to come can all
     * be placed afterwards — Hall's condition over the three other seats: for every group of seats, the cards
     * only that group may hold must fit in its room. So a deal never fails and the constraints are never
     * dropped (research next candidate 3); only an impossible view (a broken rule) falls back to capacity.
     *
     * @param  array<string, mixed>  $view
     * @return array<int, list<string>>
     */
    public function deal(CardMemory $memory, array $view, RandomSource $random): array
    {
        $seat = $memory->seat;
        $sizes = $view['hand_sizes'];
        $hands = [];
        $left = [];
        foreach (range(0, 3) as $s) {
            $hands[$s] = $s === $seat ? $memory->hand : $memory->known[$s];
            $left[$s] = $s === $seat ? 0 : max(0, (int) $sizes[$s] - count($memory->known[$s]));
        }
        $others = array_values(array_diff([0, 1, 2, 3], [$seat]));

        // Which seats may hold each card, as a bit mask over $others; and how many cards share each mask.
        $cards = $this->shuffle($memory->unseen(), $random);
        $masks = [];
        $count = array_fill(0, 8, 0);
        foreach ($cards as $card) {
            $mask = 0;
            foreach ($others as $bit => $s) {
                $mask |= $memory->mayHold($s, $card) ? 1 << $bit : 0;
            }
            $masks[$card] = $mask;
            $count[$mask]++;
        }
        if (! $this->feasible($count, $left, $others)) {
            $masks = array_fill_keys($cards, 7);   // impossible view: capacity only
            $count = array_fill(0, 8, 0);
            $count[7] = count($cards);
        }
        usort($cards, fn (string $a, string $b) => substr_count(decbin($masks[$a]), '1') <=> substr_count(decbin($masks[$b]), '1'));

        foreach ($cards as $card) {
            $count[$masks[$card]]--;
            $open = [];
            foreach ($others as $bit => $s) {
                if (($masks[$card] >> $bit & 1) === 1 && $left[$s] > 0) {
                    $left[$s]--;
                    if ($this->feasible($count, $left, $others)) {
                        $open[] = $s;
                    }
                    $left[$s]++;
                }
            }
            if ($open === []) {
                $open = array_values(array_filter($others, fn (int $s) => $left[$s] > 0));   // can't happen
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

        return $hands;
    }

    /**
     * Hall's condition: for every group of the other seats, the cards that only those seats may hold fit in
     * the room they have left.
     *
     * @param  array<int, int>  $count  cards still to place, by mask of the seats that may hold them
     * @param  array<int, int>  $left
     * @param  list<int>  $others
     */
    private function feasible(array $count, array $left, array $others): bool
    {
        for ($group = 1; $group < 8; $group++) {
            $room = 0;
            foreach ($others as $bit => $s) {
                $room += ($group >> $bit & 1) === 1 ? $left[$s] : 0;
            }
            $need = 0;
            for ($mask = 0; $mask < 8; $mask++) {
                if (($mask & ~$group) === 0) {
                    $need += $count[$mask];
                }
            }
            if ($need > $room) {
                return false;
            }
        }

        return true;
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
