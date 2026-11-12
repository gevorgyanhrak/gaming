<?php

namespace App\Games\Blot\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Bids on the strength of its best trump suit or no-trump (with a share for its partner), says Quansh when
 * QuanshAdvisor judges the opponents' contract to fail (by skill: Easy = Beginner, Medium = Advanced,
 * Hard = Expert), declares every combination it holds, and plays the trick: lets its partner's
 * winning trick through and loads it with points, takes a trick it can win with its cheapest winning card,
 * otherwise throws its least valuable card. Easy plays a random card and rarely bids; hard also leads
 * trumps as the bidder, bids a little more boldly and recontras a contra it can beat.
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
        if ($view['phase'] === 'answer') {
            $contract = $view['contract'];
            $strong = $contract['suit'] !== null && $this->strength($view['hand'], $contract['suit']) >= $contract['value'] * 10 + 30;

            return $difficulty === BotDifficulty::Hard && $strong ? $legal['recontra'] : $legal['pass'];
        }
        // Announce every combination before the first card.
        if (isset($legal['declare'])) {
            return $legal['declare'];
        }
        $plays = array_values(array_filter($legalActions, fn (Action $a) => $a->type === 'play'));
        if ($difficulty === BotDifficulty::Easy) {
            return $plays[$random->int(0, count($plays) - 1)];
        }

        $cards = array_map(fn (Action $a) => (string) $a->payload['card'], $plays);

        return new Action('play', ['card' => $this->card($view, $cards, $difficulty)]);
    }

    /**
     * Points a hand is worth with $trump as trumps, plus what the partner is likely to bring.
     *
     * @param  list<string>  $hand
     */
    public function strength(array $hand, ?string $trump): int
    {
        if ($trump === null) {
            // No-trump: aces and tens win tricks; a little less help from the partner.
            $points = 0;
            foreach ($hand as $card) {
                $points += BlotCards::points($card, null) + ($card[0] === 'A' ? 12 : 0);
            }

            return $points + 20;
        }
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
        foreach (BlotCards::declarations($hand, $trump) as $declaration) {
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
        foreach (BlotEngine::BID_SUITS as $suit) {
            $value = $this->strength($view['hand'], $suit === 'N' ? null : $suit);
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

        // Quansh when the opponents' contract is judged to fail, by this bot's skill (QuanshAdvisor).
        if (isset($legal['contra']) && (new QuanshAdvisor($this))->shouldQuansh($view, QuanshSkill::of($difficulty), $random)) {
            return $legal['contra'];
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
     * The card to play (also used by QuanshAdvisor to play sampled deals out).
     *
     * @param  array<string, mixed>  $view  At least trump, seat, trick and contract.
     * @param  list<string>  $cards  Playable cards.
     */
    public function card(array $view, array $cards, BotDifficulty $difficulty = BotDifficulty::Hard): string
    {
        $trump = $view['trump'];
        $seat = (int) $view['seat'];
        $trick = $view['trick'];
        $value = fn (string $c) => BlotCards::points($c, $trump) + ($trump !== null && $c[1] === $trump ? 15 : 0);
        $cheapest = fn (array $options) => array_reduce($options, fn (?string $a, string $c) => $a === null || $value($c) < $value($a) ? $c : $a);
        $dearest = fn (array $options) => array_reduce($options, fn (?string $a, string $c) => $a === null || $value($c) > $value($a) ? $c : $a);

        if ($trick === []) {
            $bidder = $view['contract']['team'] === $seat % 2;
            if ($difficulty === BotDifficulty::Hard && $bidder && $trump !== null && in_array('J'.$trump, $cards, true)) {
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
            $plain = array_values(array_filter($cards, fn (string $c) => $trump === null || $c[1] !== $trump));

            return $dearest($plain ?: $cards);
        }
        $winners = array_values(array_filter($cards, fn (string $c) => BlotCards::strength($c, $led, $trump) > $top));
        if ($winners !== []) {
            $tricky = array_sum(array_map(fn (string $c) => BlotCards::points($c, $trump), $played));

            // Take it cheaply; don't spend a big trump on an empty trick early on.
            return $tricky > 0 || $last ? $cheapest($winners) : ($cheapest(array_filter($winners, fn (string $c) => $trump === null || $c[1] !== $trump)) ?? $cheapest($winners));
        }

        return $cheapest($cards);
    }
}
