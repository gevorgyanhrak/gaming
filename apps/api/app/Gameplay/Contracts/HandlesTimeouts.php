<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Action;

/**
 * Optional for engines where running out of time means a default move rather than losing (poker:
 * check or fold). The move is recorded and replayed like any other.
 */
interface HandlesTimeouts
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): ?Action;
}
