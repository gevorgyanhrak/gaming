<?php

namespace Tests\Fixtures\TicTacToe;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Test-only engine used to exercise the gameplay infrastructure. The first player is chosen at
 * random, so replays also test recorded randomness.
 */
final class TicTacToeEngine implements GameEngine
{
    private const LINES = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        return ['board' => array_fill(0, 9, null), 'turn' => $random->int(0, 1), 'winner' => null];
    }

    public function seatsToAct(array $state): array
    {
        return $this->outcome($state) === null ? [$state['turn']] : [];
    }

    public function legalActions(array $state, int $seat): array
    {
        if ($this->outcome($state) !== null || $seat !== $state['turn']) {
            return [];
        }

        $actions = [];
        foreach ($state['board'] as $cell => $owner) {
            if ($owner === null) {
                $actions[] = new Action('place', ['cell' => $cell]);
            }
        }

        return $actions;
    }

    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        $cell = $action->payload['cell'] ?? null;

        $free = is_int($cell) && array_key_exists($cell, $state['board']) && $state['board'][$cell] === null;

        if ($action->type !== 'place' || ! $free || $seat !== $state['turn']) {
            throw new IllegalAction('Illegal tic-tac-toe move.');
        }

        $state['board'][$cell] = $seat;
        foreach (self::LINES as [$a, $b, $c]) {
            if ($state['board'][$a] === $seat && $state['board'][$b] === $seat && $state['board'][$c] === $seat) {
                $state['winner'] = $seat;
            }
        }
        $state['turn'] = 1 - $seat;

        return new Transition($state, [['type' => 'placed', 'seat' => $seat, 'cell' => $cell]]);
    }

    public function viewFor(array $state, ?int $seat): array
    {
        return $state;
    }

    public function outcome(array $state): ?Outcome
    {
        if ($state['winner'] !== null) {
            return new Outcome([$state['winner']], 'line');
        }

        return in_array(null, $state['board'], true) ? null : new Outcome([], 'draw');
    }
}
