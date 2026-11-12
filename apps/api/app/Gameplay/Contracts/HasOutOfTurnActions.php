<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Action;

/**
 * Optional for an engine: actions a seat may take while it isn't its turn (Blot's Quansh at any moment of the
 * bazar). GameActionService accepts them from seats not to act, under the same room lock and version check as
 * any move; the room state lists them as the seat's legal actions; AI players may take them too (ActsOutOfTurn).
 */
interface HasOutOfTurnActions
{
    /**
     * @param  array<string, mixed>  $state
     * @return list<Action>
     */
    public function outOfTurnActions(array $state, int $seat): array;
}
