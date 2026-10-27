<?php

namespace App\Games\Chess\Engine;

use App\Gameplay\Contracts\DecidesTimeouts;
use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Chess for two players; white is seat 0. One action = one move ("move" with from, to and, for
 * promotions, promotion). On your turn you may also offer a draw ("offer_draw", then move as usual);
 * the opponent accepts on their turn ("accept_draw") or declines by moving.
 *
 * The game ends by checkmate, or is drawn by stalemate, insufficient material, the third repetition
 * of a position or fifty moves by each side without a pawn move or capture (all applied
 * automatically). Running out of time loses, unless the opponent couldn't checkmate anyway.
 *
 * State: a position (see ChessRules) plus check, last (the last move), moves (SAN history), seen
 * (position counts since the last irreversible move), draw_offer (the seat offering a draw) and result.
 */
final class ChessEngine implements DecidesTimeouts, GameEngine
{
    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        return self::stateFrom(ChessRules::fromFen(ChessRules::START));
    }

    /**
     * A game state starting from a position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>
     */
    public static function stateFrom(array $position): array
    {
        return [
            ...$position,
            'check' => ChessRules::inCheck($position['board'], $position['turn']),
            'last' => null,
            'moves' => [],
            'seen' => [ChessRules::key($position) => 1],
            'draw_offer' => null,
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

        $actions = array_map(fn (array $move) => new Action('move', $move), ChessRules::legalMoves($state));
        $offer = $state['draw_offer'] ?? null;
        if ($offer === 1 - $seat) {
            $actions[] = new Action('accept_draw');
        } elseif ($offer === null) {
            $actions[] = new Action('offer_draw');
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        if ($action->type === 'offer_draw' || $action->type === 'accept_draw') {
            if (! collect($this->legalActions($state, $seat))->contains(fn (Action $a) => $a->equals($action))) {
                throw new IllegalAction('That move is not allowed.');
            }

            return $action->type === 'offer_draw'
                ? new Transition([...$state, 'draw_offer' => $seat], [['type' => 'draw_offered', 'seat' => $seat]])
                : new Transition([...$state, 'draw_offer' => null, 'result' => ['winners' => [], 'reason' => 'agreement']], [['type' => 'draw_agreed']]);
        }

        $move = ['from' => (int) ($action->payload['from'] ?? -1), 'to' => (int) ($action->payload['to'] ?? -1)];
        if (isset($action->payload['promotion'])) {
            $move['promotion'] = (string) $action->payload['promotion'];
        }
        if ($action->type !== 'move' || $seat !== $state['turn'] || ! in_array($move, ChessRules::legalMoves($state), true)) {
            throw new IllegalAction('That move is not allowed.');
        }

        $san = ChessRules::san($state, $move);
        $position = ChessRules::play($state, $move);
        $key = ChessRules::key($position);
        $seen = $position['halfmove'] === 0 ? [] : $state['seen'];
        $seen[$key] = ($seen[$key] ?? 0) + 1;
        $check = ChessRules::inCheck($position['board'], $position['turn']);

        $result = match (true) {
            ChessRules::legalMoves($position) === [] => $check
                ? ['winners' => [$seat], 'reason' => 'checkmate']
                : ['winners' => [], 'reason' => 'stalemate'],
            ChessRules::insufficientMaterial($position['board']) => ['winners' => [], 'reason' => 'insufficient_material'],
            $seen[$key] >= 3 => ['winners' => [], 'reason' => 'repetition'],
            $position['halfmove'] >= 100 => ['winners' => [], 'reason' => 'fifty_moves'],
            default => null,
        };

        $state = [
            ...$state,
            ...$position,
            'check' => $check,
            'last' => $move,
            'moves' => [...$state['moves'], $san],
            'seen' => $seen,
            // Moving declines the opponent's offer; your own offer stands for their reply.
            'draw_offer' => ($state['draw_offer'] ?? null) === $seat ? $seat : null,
            'result' => $result,
        ];

        return new Transition($state, [['type' => 'moved', 'seat' => $seat, ...$move, 'san' => $san]]);
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
        return $state['result'] === null ? null : new Outcome($state['result']['winners'], $state['result']['reason']);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function timeoutOutcome(array $state, int $seat): Outcome
    {
        return ChessRules::canMate($state['board'], 1 - $seat)
            ? new Outcome([1 - $seat], 'timeout')
            : new Outcome([], 'timeout_insufficient_material');
    }
}
