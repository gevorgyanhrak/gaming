<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Outcome;

/**
 * Optional for engines whose rules say what running out of time means (in chess it's a draw when
 * the opponent couldn't checkmate anyway). Without it, the seat that timed out loses.
 */
interface DecidesTimeouts
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function timeoutOutcome(array $state, int $seat): Outcome;
}
