<?php

namespace App\Games\Nardi\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Nardi\Engine\NardiEngine;
use App\Games\Nardi\Engine\NardiRules;

/**
 * Nardi bot. Easy: a random legal step. Medium: plans the whole roll and picks the position with the
 * best evaluation. Hard: like medium, but among the best candidates it also considers the opponent's
 * best reply averaged over all 21 rolls. Each call returns the first step of the chosen plan; the
 * plan is recomputed every step, which gives the same result because evaluation is deterministic.
 */
final class NardiBot implements BotStrategy
{
    private const HARD_CANDIDATES = 6;

    public function __construct(private readonly NardiEngine $engine = new NardiEngine) {}

    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        if ($difficulty === BotDifficulty::Easy || count($legalActions) === 1) {
            return $legalActions[$random->int(0, count($legalActions) - 1)];
        }

        $seat = $view['turn'];
        $plans = $this->plans($view);

        usort($plans, fn (array $a, array $b) => $this->evaluate($b['state'], $seat) <=> $this->evaluate($a['state'], $seat));

        if ($difficulty === BotDifficulty::Hard) {
            $candidates = array_slice($plans, 0, self::HARD_CANDIDATES);
            usort($candidates, fn (array $a, array $b) => $this->lookahead($b['state'], $seat) <=> $this->lookahead($a['state'], $seat));
            $plans = $candidates;
        }

        $first = $plans[0]['first'];

        foreach ($legalActions as $action) {
            if ($action->payload == $first) {
                return $action;
            }
        }

        return $legalActions[0];
    }

    /**
     * Every distinct end position reachable with the remaining dice, with the first step leading there.
     *
     * @param  array<string, mixed>  $state
     * @return list<array{first: array<string, mixed>, state: array<string, mixed>}>
     */
    private function plans(array $state): array
    {
        $plans = [];

        foreach ($this->engine->legalSteps($state) as $first) {
            $this->extend($state, $first, $first, $plans);
        }

        return array_values($plans);
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $step
     * @param  array<string, array{first: array<string, mixed>, state: array<string, mixed>}>  $plans
     * @param  array<string, mixed>  $state
     */
    private function extend(array $state, array $first, array $step, array &$plans): void
    {
        $seat = $state['turn'];
        [$next] = NardiRules::applyStep($state, $seat, $step);
        $next['dice'] = NardiRules::withoutDie($state['dice'], $step['die']);

        $continuations = $next['dice'] === [] || $next['off'][$seat] === NardiRules::CHECKERS ? [] : $this->engine->legalSteps($next);

        if ($continuations === []) {
            $plans[NardiRules::key($next, [])] ??= ['first' => $first, 'state' => $next];

            return;
        }

        foreach ($continuations as $continuation) {
            $this->extend($next, $first, $continuation, $plans);
        }
    }

    /**
     * Position value for $seat (higher is better).
     *
     * @param  array<string, mixed>  $state
     */
    public function evaluate(array $state, int $seat): float
    {
        $opp = 1 - $seat;

        if ($state['off'][$seat] === NardiRules::CHECKERS) {
            return 10_000;
        }

        $score = (NardiRules::pips($state, $opp) - NardiRules::pips($state, $seat)) * 1.0;
        $score += 8 * $state['bar'][$opp] - 8 * $state['bar'][$seat];
        $score += 2 * $state['off'][$seat];

        for ($p = 1; $p <= 24; $p++) {
            $own = NardiRules::own($state, $seat, $p);

            if ($own >= 2) {
                $score += NardiRules::inHome($seat, $p) ? 3 : 1.5;
            } elseif ($own === 1) {
                $score -= $this->exposure($state, $seat, $p);
            }
        }

        return $score;
    }

    /**
     * How threatened a single checker on $p is: opponent checkers within direct-shot range.
     *
     * @param  array<string, mixed>  $state
     */
    private function exposure(array $state, int $seat, int $p): float
    {
        $opp = 1 - $seat;
        $threat = 0.0;

        // The opponent moves the other way: seat 1's checkers come from below, seat 0's from above.
        for ($distance = 1; $distance <= 12; $distance++) {
            $from = $seat === 0 ? $p - $distance : $p + $distance;
            $shooters = $from >= 1 && $from <= 24 ? NardiRules::own($state, $opp, $from) : 0;
            if ($shooters > 0) {
                $threat += $distance <= 6 ? 4.0 : 1.5;
            }
        }

        if ($state['bar'][$opp] > 0 && NardiRules::inHome($seat, $p)) {
            $threat += 3.0;
        }

        return min($threat, 12.0);
    }

    /**
     * Expected value after the opponent's best reply, over all 21 rolls.
     *
     * @param  array<string, mixed>  $state
     */
    private function lookahead(array $state, int $seat): float
    {
        if ($state['off'][$seat] === NardiRules::CHECKERS) {
            return 10_000;
        }

        $opp = 1 - $seat;
        $total = 0.0;

        for ($a = 1; $a <= 6; $a++) {
            for ($b = $a; $b <= 6; $b++) {
                $weight = $a === $b ? 1 : 2;
                $reply = [...$state, 'turn' => $opp, 'dice' => $a === $b ? [$a, $a, $a, $a] : [$a, $b]];
                $plans = $this->engine->legalSteps($reply) === [] ? [['state' => $reply]] : $this->plans($reply);

                $worst = INF;
                foreach ($plans as $plan) {
                    $worst = min($worst, $this->evaluate($plan['state'], $seat));
                }

                $total += $weight * $worst;
            }
        }

        return $total / 36;
    }
}
