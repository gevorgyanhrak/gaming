<?php

namespace App\Games\Blot\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Bids on the strength of its best trump suit (with a share for its partner), doubles holding the top
 * trumps, and plays the trick: lets its partner's winning trick through and loads it with points, takes a
 * trick it can win with its cheapest winning card, otherwise throws its least valuable card. Easy plays a
 * random card and rarely bids; hard also leads trumps as the bidder and bids a little more boldly.
 */
final class BlotBot implements BotStrategy
{
    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        $legal = [];
        foreach ($legalActions as $action) {
            $legal[$action->type] = $action;
        }

        if ($view['phase'] === 'bidding') {
            return $this->bid($view, $legal, $difficulty, $random);
        }
        if ($difficulty === BotDifficulty::Easy) {
            return $legalActions[$random->int(0, count($legalActions) - 1)];
        }

        $cards = array_map(fn (Action $a) => (string) $a->payload['card'], $legalActions);

        return new Action('play', ['card' => $this->card($view, $cards, $difficulty)]);
    }

    /**
     * Points a hand is worth with $trump as trumps, plus what the partner is likely to bring.
     *
     * @param  list<string>  $hand
     */
    public function strength(array $hand, string $trump): int
    {
        $points = 0;
        $trumps = 0;
        foreach ($hand as $card) {
            if ($card[1] === $trump) {
                $trumps++;
                $points += BlotCards::points($card, $trump);
            } elseif ($card[0] === 'A') {
                $points += 11;
            }
        }
        foreach (BlotCards::declarations($hand) as $declaration) {
            $points += $declaration['points'];
        }
        if (in_array('K'.$trump, $hand, true) && in_array('Q'.$trump, $hand, true)) {
            $points += 20;
        }

        // Length in trumps wins tricks; the partner's half of the deck is worth about 30.
        return $points + $trumps * 6 + 30;
    }

    /**
     * @param  array<string, mixed>  $view
     * @param  array<string, Action>  $legal
     */
    private function bid(array $view, array $legal, BotDifficulty $difficulty, RandomSource $random): Action
    {
        $best = null;
        foreach (BlotCards::SUITS as $suit) {
            $value = $this->strength($view['hand'], $suit);
            if ($best === null || $value > $best[1]) {
                $best = [$suit, $value];
            }
        }
        [$suit, $value] = $best;
        $value += match ($difficulty) {
            BotDifficulty::Easy => -25,
            BotDifficulty::Medium => 0,
            BotDifficulty::Hard => 8,
        };
        $contract = $view['contract'];
        $seat = (int) $view['seat'];

        if (isset($legal['double']) && $contract !== null && $difficulty !== BotDifficulty::Easy) {
            $trump = $contract['suit'];
            $top = count(array_intersect(['J'.$trump, '9'.$trump, 'A'.$trump], $view['hand']));
            if ($top >= 3 || ($top >= 2 && $contract['value'] >= 12)) {
                return $legal['double'];
            }
        }

        // Don't outbid a partner without a clearly better hand.
        $partnerHolds = $contract !== null && $contract['team'] === $seat % 2;
        $wanted = intdiv($value, 10) - ($partnerHolds ? 3 : 0);
        $bid = $legal['bid'] ?? null;
        if ($bid !== null && $wanted >= (int) $bid->payload['min'] && $random->int(1, 100) > ($difficulty === BotDifficulty::Easy ? 70 : 5)) {
            return new Action('bid', ['suit' => $suit, 'value' => min((int) $bid->payload['min'], BlotEngine::MAX_BID)]);
        }

        return $legal['pass'];
    }

    /**
     * @param  array<string, mixed>  $view
     * @param  list<string>  $cards  Playable cards.
     */
    private function card(array $view, array $cards, BotDifficulty $difficulty): string
    {
        $trump = (string) $view['trump'];
        $seat = (int) $view['seat'];
        $trick = $view['trick'];
        $value = fn (string $c) => BlotCards::points($c, $trump) + ($c[1] === $trump ? 15 : 0);
        $cheapest = fn (array $options) => array_reduce($options, fn (?string $a, string $c) => $a === null || $value($c) < $value($a) ? $c : $a);
        $dearest = fn (array $options) => array_reduce($options, fn (?string $a, string $c) => $a === null || $value($c) > $value($a) ? $c : $a);

        if ($trick === []) {
            $bidder = $view['contract']['team'] === $seat % 2;
            if ($difficulty === BotDifficulty::Hard && $bidder && in_array('J'.$trump, $cards, true)) {
                return 'J'.$trump;
            }
            // Lead a side ace when there is one, otherwise something cheap.
            foreach ($cards as $card) {
                if ($card[0] === 'A' && $card[1] !== $trump) {
                    return $card;
                }
            }

            return $cheapest($cards);
        }

        $played = array_column($trick, 'card');
        $led = $played[0][1];
        $winning = $trick[BlotCards::winning($played, $trump)];
        $top = BlotCards::strength($winning['card'], $led, $trump);
        $partnerWins = $seat % 2 === $winning['seat'] % 2;
        $last = count($trick) === 3;

        if ($partnerWins && ($last || $top >= 100 || $winning['card'][0] === 'A')) {
            // Give the partner points, but not a trump.
            $plain = array_values(array_filter($cards, fn (string $c) => $c[1] !== $trump));

            return $dearest($plain ?: $cards);
        }
        $winners = array_values(array_filter($cards, fn (string $c) => BlotCards::strength($c, $led, $trump) > $top));
        if ($winners !== []) {
            $tricky = array_sum(array_map(fn (string $c) => BlotCards::points($c, $trump), $played));

            // Take it cheaply; don't spend a big trump on an empty trick early on.
            return $tricky > 0 || $last ? $cheapest($winners) : ($cheapest(array_filter($winners, fn (string $c) => $c[1] !== $trump)) ?? $cheapest($winners));
        }

        return $cheapest($cards);
    }
}
