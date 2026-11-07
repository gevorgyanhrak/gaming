<?php

namespace App\Games\Nardi\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Nardi for two players, Short (backgammon rules, NardiRules) or Long (LongNardiRules), chosen by the
 * mode's `variant` option; a state without `variant` is Short. One action = one checker step ("move"
 * with from, to, die); a turn lasts until the dice are used up or no step is possible. Dice are rolled
 * by the engine (through RandomSource) when a turn starts, including the opening roll.
 *
 * State: variant, points (24 signed counts, see NardiRules), bar [s0, s1], off [s0, s1], turn, dice
 * (dice left to play this turn), rolled (this turn's roll), winner; Long also head_moves (checkers that
 * left the head this turn) and turns (turns each player has had). The view adds pips [s0, s1].
 */
final class NardiEngine implements GameEngine
{
    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        // Opening roll: each player throws one die; the higher one starts and plays both.
        do {
            $first = $random->int(1, 6);
            $second = $random->int(1, 6);
        } while ($first === $second);

        $long = ($setup->options['variant'] ?? 'short') === 'long';

        return [
            ...($long ? ['variant' => 'long', 'head_moves' => 0, 'turns' => [0, 0]] : []),
            'points' => $long ? LongNardiRules::startingPoints() : NardiRules::startingPoints(),
            'bar' => [0, 0],
            'off' => [0, 0],
            'turn' => $first > $second ? 0 : 1,
            'dice' => [$first, $second],
            'rolled' => [$first, $second],
            'winner' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function seatsToAct(array $state): array
    {
        return $state['winner'] === null ? [$state['turn']] : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function legalActions(array $state, int $seat): array
    {
        if ($state['winner'] !== null || $seat !== $state['turn']) {
            return [];
        }

        return array_map(fn (array $step) => new Action('move', $step), $this->legalSteps($state));
    }

    /**
     * Steps that begin a way of playing as many dice as possible (all of them if possible; with two
     * different dice of which only one can be played, the higher one when it can be).
     *
     * @param  array<string, mixed>  $state
     * @return list<array{from: int|string, to: int|string, die: int}>
     */
    public function legalSteps(array $state): array
    {
        $rules = self::rules($state);
        $seat = $state['turn'];
        $dice = $state['dice'];
        $memo = [];
        $best = 0;
        $byDepth = [];

        foreach ($rules::singleSteps($state, $seat, $dice) as $step) {
            [$next] = $rules::applyStep($state, $seat, $step);
            $depth = 1 + $rules::maxPlayable($next, $seat, $rules::withoutDie($dice, $step['die']), $memo);
            $byDepth[$depth][] = $step;
            $best = max($best, $depth);
        }

        $steps = $byDepth[$best] ?? [];

        if ($best === 1 && count($dice) === 2 && $dice[0] !== $dice[1]) {
            $higher = array_values(array_filter($steps, fn (array $s) => $s['die'] === max($dice)));
            if ($higher !== []) {
                $steps = $higher;
            }
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        $step = $this->match($state, $seat, $action) ?? throw new IllegalAction('Illegal nardi move.');

        $rules = self::rules($state);
        [$state, $hit] = $rules::applyStep($state, $seat, $step);
        $state['dice'] = $rules::withoutDie($state['dice'], $step['die']);
        $events = [['type' => 'moved', 'seat' => $seat, ...$step, 'hit' => $hit]];

        if ($state['off'][$seat] === NardiRules::CHECKERS) {
            $state['winner'] = $seat;
            $state['dice'] = [];

            return new Transition($state, $events);
        }

        if ($state['dice'] === [] || $this->legalSteps($state) === []) {
            [$state, $more] = $this->nextTurn($state, $random);
            $events = [...$events, ...$more];
        }

        return new Transition($state, $events);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        // Nardi has no hidden information; the pip counts are for the players' panels.
        $rules = self::rules($state);

        return [...$state, 'pips' => [$rules::pips($state, 0), $rules::pips($state, 1)]];
    }

    /**
     * The rules of the state's variant.
     *
     * @param  array<string, mixed>  $state
     * @return class-string<NardiRules>
     */
    public static function rules(array $state): string
    {
        return ($state['variant'] ?? 'short') === 'long' ? LongNardiRules::class : NardiRules::class;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        if ($state['winner'] === null) {
            return null;
        }

        $winner = $state['winner'];
        $mars = $state['off'][1 - $winner] === 0;

        return new Outcome([$winner], $mars ? 'mars' : 'bear_off', [$winner => $mars ? 2 : 1]);
    }

    /**
     * Pass the dice: roll for the other player; if they can't move at all, they pass too.
     *
     * @param  array<string, mixed>  $state
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function nextTurn(array $state, RandomSource $random): array
    {
        $events = [];

        for ($attempt = 0; $attempt < 8; $attempt++) {
            if (isset($state['turns'])) {
                // Long nardi: the turn just played counts (the head rule's first-turn doubles).
                $state['turns'][$state['turn']]++;
                $state['head_moves'] = 0;
            }
            $state['turn'] = 1 - $state['turn'];
            $a = $random->int(1, 6);
            $b = $random->int(1, 6);
            $state['rolled'] = [$a, $b];
            $state['dice'] = $a === $b ? [$a, $a, $a, $a] : [$a, $b];
            $events[] = ['type' => 'rolled', 'seat' => $state['turn'], 'dice' => [$a, $b]];

            if ($this->legalSteps($state) !== []) {
                break;
            }

            $events[] = ['type' => 'no_moves', 'seat' => $state['turn']];
        }

        return [$state, $events];
    }

    /**
     * The legal step the action refers to (in canonical form), or null.
     *
     * @param  array<string, mixed>  $state
     * @return array{from: int|string, to: int|string, die: int}|null
     */
    private function match(array $state, int $seat, Action $action): ?array
    {
        if ($action->type !== 'move' || $seat !== $state['turn'] || $state['winner'] !== null) {
            return null;
        }

        foreach ($this->legalSteps($state) as $step) {
            if ((string) $step['from'] === (string) ($action->payload['from'] ?? '')
                && (string) $step['to'] === (string) ($action->payload['to'] ?? '')
                && $step['die'] === (int) ($action->payload['die'] ?? 0)) {
                return $step;
            }
        }

        return null;
    }
}
