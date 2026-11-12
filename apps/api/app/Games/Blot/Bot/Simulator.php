<?php

namespace App\Games\Blot\Bot;

use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Plays a sampled deal to its end with the real rules (BlotEngine: following suit, trumping, declarations,
 * blot, the kaput stop, scoring, match-ending deals), every seat playing BlotBot's quick card policy, and
 * scores it for one pair. The searches (CardSearch, BidPlanner, QuanshAdvisor) compare their options on
 * many such deals.
 */
final class Simulator
{
    /** What ending the match is worth against a deal's sai. */
    public const MATCH = 150;

    private readonly BlotEngine $engine;

    public function __construct(private readonly BlotBot $bot = new BlotBot)
    {
        $this->engine = new BlotEngine;
    }

    /**
     * A full table state from a seat view and one sample of everyone's hands. Players who haven't played
     * their first card may still declare what they hold.
     *
     * @param  array<string, mixed>  $view
     * @param  array<int, list<string>>  $hands  what each seat holds now
     * @param  array<int, list<string>>|null  $original  the hands as dealt (for blot), when play has begun
     * @return array<string, mixed>
     */
    public function state(array $view, array $hands, ?array $original = null): array
    {
        $state = [...$view, 'hands' => $hands, 'available' => [[], [], [], []], 'declared_index' => [[], [], [], []], 'pause_ms' => 0, 'deal_ms' => 0];
        unset($state['hand'], $state['hand_sizes'], $state['my_declarations']);
        if ($view['phase'] === 'playing') {
            $trump = $view['contract']['suit'];
            // Announced declarations as the engine keeps them (the view shows only kind, points and cards).
            $state['declared'] = array_map(fn (array $list) => array_map(
                fn (array $d) => BlotCards::declarations($d['cards'], $trump)[0] ?? ['kind' => $d['kind'], 'cards' => $d['cards'], 'points' => $d['points'], 'key' => [$d['points'], 0, 0, 0]],
                $list,
            ), $view['declared']);
            // Who holds blot, from the sampled deal (the view shows it only once its first card is played).
            $state['belote'] = null;
            if ($trump !== null && $original !== null) {
                foreach ($original as $s => $cards) {
                    if (in_array('K'.$trump, $cards, true) && in_array('Q'.$trump, $cards, true)) {
                        $state['belote'] = $s;
                    }
                }
            }
            foreach (range(0, 3) as $s) {
                if (! ($view['played_first'][$s] ?? true)) {
                    $shown = array_map(fn (array $d) => $d['cards'], $view['declared'][$s] ?? []);
                    $state['available'][$s] = array_values(array_filter(
                        BlotCards::declarations($hands[$s], $trump),
                        fn (array $d) => ! in_array($d['cards'], $shown, true),
                    ));
                }
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function act(array $state, int $seat, Action $action, RandomSource $random): array
    {
        return $this->engine->apply($state, $seat, $action, $random)->state;
    }

    /**
     * Plays on to the end of the deal: pending bazar steps pass, then everyone declares and plays.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function finish(array $state, RandomSource $random): array
    {
        while ($state['phase'] === 'bidding' || $state['phase'] === 'answer') {
            $state = $this->act($state, (int) $state['to_act'], new Action('pass'), $random);
        }
        while ($state['phase'] === 'playing') {
            $who = (int) $state['to_act'];
            $cards = [];
            $declare = null;
            foreach ($this->engine->legalActions($state, $who) as $action) {
                if ($action->type === 'declare') {
                    $declare ??= $action;
                } else {
                    $cards[] = (string) $action->payload['card'];
                }
            }
            if ($declare !== null) {
                $state = $this->act($state, $who, $declare, $random);

                continue;
            }
            $card = count($cards) === 1 ? $cards[0] : $this->bot->card(
                ['trump' => $state['contract']['suit'], 'seat' => $who, 'trick' => $state['trick'], 'contract' => $state['contract']],
                $cards,
            );
            $state = $this->act($state, $who, new Action('play', ['card' => $card]), $random);
        }

        return $state;
    }

    /**
     * A finished deal for $team: its score less the other pair's, and MATCH either way for a deal that ends
     * the match (by the rules, or — with $target — by reaching the target).
     *
     * @param  array<string, mixed>  $state
     */
    public function value(array $state, int $team, bool $target = true): float
    {
        $scored = $state['last_hand']['scored'];
        $value = $scored[$team] - $scored[1 - $team];
        $result = $state['result'];
        if ($result !== null && ($target || $result['reason'] !== 'target_reached')) {
            $value += $result['team'] === $team ? self::MATCH : -self::MATCH;
        }

        return $value;
    }
}
