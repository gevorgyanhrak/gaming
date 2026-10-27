<?php

namespace App\Games\Checkers\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Russian draughts for two players; white (seat 0) moves first. One action = one step ("move" with
 * from and to); during a capture series the same player keeps acting until the series ends.
 *
 * Draws: the same position with the same player to move for the third time, or fifteen moves by
 * each player with kings only and no capture.
 *
 * State: board (see CheckersRules), turn, capturing (the square of the piece in a capture series, or
 * null), captured (pieces jumped in that series), last (the last step), quiet (consecutive king moves
 * without capture), seen (position counts since the last irreversible move), result.
 */
final class CheckersEngine implements GameEngine
{
    /** 15 moves by each player. */
    public const QUIET_LIMIT = 30;

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $board = CheckersRules::startingBoard();

        return [
            'board' => $board,
            'turn' => 0,
            'capturing' => null,
            'captured' => [],
            'last' => null,
            'quiet' => 0,
            'seen' => [CheckersRules::key($board, 0) => 1],
            'result' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function seatsToAct(array $state): array
    {
        return $state['result'] === null ? [$state['turn']] : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function legalActions(array $state, int $seat): array
    {
        if ($state['result'] !== null || $seat !== $state['turn']) {
            return [];
        }

        return array_map(fn (array $step) => new Action('move', $step), $this->legalSteps($state));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        $step = ['from' => (int) ($action->payload['from'] ?? -1), 'to' => (int) ($action->payload['to'] ?? -1)];
        if ($action->type !== 'move' || $seat !== $state['turn'] || ! in_array($step, $this->legalSteps($state), true)) {
            throw new IllegalAction('That move is not allowed.');
        }

        $wasKing = CheckersRules::isKing($state['board'][$step['from']]);
        [$board, $captured, $jumped, $continues] = CheckersRules::applyStep($state['board'], $state['captured'], $step);
        $event = ['type' => 'moved', 'seat' => $seat, ...$step, 'captured' => $jumped];
        if (! $wasKing && CheckersRules::isKing($board[$step['to']])) {
            $event['crowned'] = true;
        }

        $state['board'] = $board;
        $state['captured'] = $captured;
        $state['capturing'] = $continues ? $step['to'] : null;
        $state['last'] = $step;

        if (! $continues) {
            $state = $this->endTurn($state, quiet: $wasKing && $jumped === null);
        }

        return new Transition($state, [$event]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        unset($state['seen']);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        if ($state['result'] === null) {
            return null;
        }

        return new Outcome($state['result']['winners'], $state['result']['reason']);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array{from: int, to: int}>
     */
    private function legalSteps(array $state): array
    {
        return $state['capturing'] === null
            ? CheckersRules::steps($state['board'], $state['turn'])
            : CheckersRules::captureSteps($state['board'], $state['captured'], $state['capturing']);
    }

    /**
     * Hand the move to the other player and check for the end of the game.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function endTurn(array $state, bool $quiet): array
    {
        $state['turn'] = 1 - $state['turn'];
        $state['quiet'] = $quiet ? $state['quiet'] + 1 : 0;
        if (! $quiet) {
            // Man moves and captures can't be undone, so earlier positions can't come back.
            $state['seen'] = [];
        }
        $key = CheckersRules::key($state['board'], $state['turn']);
        $state['seen'][$key] = ($state['seen'][$key] ?? 0) + 1;

        $state['result'] = match (true) {
            CheckersRules::steps($state['board'], $state['turn']) === [] => [
                'winners' => [1 - $state['turn']],
                'reason' => CheckersRules::count($state['board'], $state['turn']) === 0 ? 'no_pieces' : 'no_moves',
            ],
            $state['seen'][$key] >= 3 => ['winners' => [], 'reason' => 'repetition'],
            $state['quiet'] >= self::QUIET_LIMIT => ['winners' => [], 'reason' => 'kings_only'],
            default => null,
        };

        return $state;
    }
}
