<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * The rules of one game. Implementations are pure PHP: no framework, database or Redis access, no
 * clock and no randomness except through RandomSource. State is a JSON-serializable array that only
 * the engine interprets. The shared infrastructure handles everything else (turn order enforcement,
 * timers, resignations, persistence, broadcasting).
 */
interface GameEngine
{
    /**
     * @return array<string, mixed>
     */
    public function initialState(GameSetup $setup, RandomSource $random): array;

    /**
     * Seats that may act now. Empty once the game is over.
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    public function seatsToAct(array $state): array;

    /**
     * Every action the seat may take now. The UI offers exactly these; nothing else is accepted.
     *
     * @param  array<string, mixed>  $state
     * @return list<Action>
     */
    public function legalActions(array $state, int $seat): array;

    /**
     * @param  array<string, mixed>  $state
     *
     * @throws IllegalAction
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition;

    /**
     * What a seat may see (null: a spectator). Must hide other players' secrets (cards, deck order).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function viewFor(array $state, ?int $seat): array;

    /**
     * How the game ended, or null while it is still going.
     *
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome;
}
