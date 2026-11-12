<?php

namespace App\Games\Blot\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\ActsOutOfTurn;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\PacesThinking;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotCards;

/**
 * The blot AI. It decides by looking ahead, never by peeking: it remembers every card played and what
 * plays revealed (CardMemory), deals the cards it can't see in ways that fit all of that and the bazar
 * (DealSampler), plays each possibility out with the real rules (Simulator) and picks what gives its pair
 * the best expected result over the whole deal —
 * - bazar: every trump, Boy, number and kaput priced with the table's scoring (BidPlanner);
 * - Quansh and Sur: the chance the contract fails or is made and what doubling gains (QuanshAdvisor);
 * - each card: every legal card played to the end of the deal on each sample (CardSearch).
 * It declares every combination it holds. Hard (the level AI plays at) samples the most; Medium fewer;
 * Easy plays as the old rule-based bot (ClassicBlotBot). Its quick card rules (card()) are also how every
 * seat plays inside the look-ahead.
 */
final class BlotBot implements ActsOutOfTurn, BotStrategy, PacesThinking
{
    /**
     * @param  bool  $research  the techniques adopted from docs/research/blot-ai (exact endgame, merged
     *                          candidates); off only to measure them in the arena
     */
    public function __construct(private readonly bool $research = true) {}

    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        if ($difficulty === BotDifficulty::Easy) {
            return (new ClassicBlotBot($this))->chooseAction($view, $legalActions, $difficulty, $random);
        }
        $legal = [];
        foreach ($legalActions as $action) {
            $legal[$action->type] = $action;
        }
        [$bids, $sur, $cards] = $difficulty === BotDifficulty::Hard ? [24, 48, 40] : [12, 24, 16];

        if ($view['phase'] === 'bidding') {
            // Quansh when the opponents' contract is judged to fail (QuanshAdvisor), otherwise bid or pass.
            if (isset($legal['contra']) && (new QuanshAdvisor($this))->shouldQuansh($view, QuanshSkill::of($difficulty), $random)) {
                return $legal['contra'];
            }

            return (new BidPlanner($this))->choose($view, $legal, $legalActions, $bids, $random);
        }
        if ($view['phase'] === 'answer') {
            return (new QuanshAdvisor($this))->shouldSur($view, $sur, $random) ? $legal['recontra'] : $legal['pass'];
        }
        // Announce every combination before the first card.
        if (isset($legal['declare'])) {
            return $legal['declare'];
        }
        $plays = array_map(
            fn (Action $a) => (string) $a->payload['card'],
            array_values(array_filter($legalActions, fn (Action $a) => $a->type === 'play')),
        );

        return new Action('play', ['card' => (new CardSearch($this, exact: $this->research, merge: $this->research))->choose($view, $plays, $cards, $random)]);
    }

    /**
     * Quansh out of turn, as a person would the moment a contract looks wrong (QuanshAdvisor); otherwise wait.
     *
     * @param  array<string, mixed>  $view
     */
    public function chooseOutOfTurn(array $view, array $actions, BotDifficulty $difficulty, RandomSource $random): ?Action
    {
        foreach ($actions as $action) {
            if ($action->type === 'contra' && (new QuanshAdvisor($this))->shouldQuansh($view, QuanshSkill::of($difficulty), $random)) {
                return $action;
            }
        }

        return null;
    }

    /**
     * A person's pace: a forced card quickly, a choice among many longer, the bazar longer still, a
     * possible Quansh and a Sur most deliberately; the first card of a deal and a rich trick a little more.
     *
     * @param  array<string, mixed>  $view
     */
    public function thinkingMs(array $view, array $legalActions, RandomSource $random): int
    {
        $types = array_map(fn (Action $a) => $a->type, $legalActions);
        if ($view['phase'] === 'answer') {
            return 1800 + $random->int(0, 1600);
        }
        if ($view['phase'] === 'bidding') {
            return 1200 + $random->int(0, 1000) + (in_array('contra', $types, true) ? 700 + $random->int(0, 900) : 0);
        }
        if (in_array('declare', $types, true)) {
            return 600 + $random->int(0, 500);
        }
        $options = count(array_filter($types, fn (string $t) => $t === 'play'));
        if ($options <= 1) {
            return 450 + $random->int(0, 400);
        }
        $points = 0;
        foreach ($view['trick'] ?? [] as $play) {
            $points += BlotCards::points((string) $play['card'], $view['trump']);
        }

        return 700 + 130 * $options + $random->int(0, 700)
            + (($view['history'] ?? []) === [] && ($view['trick'] ?? []) === [] ? 400 : 0)
            + ($points >= 20 ? 300 : 0);
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
     * The quick card rules: let a partner's winning trick through and load it, win a trick with the cheapest
     * card that does, otherwise throw the least valuable one. How every seat plays in the look-ahead.
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
