<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Action;

/**
 * Optional for engines whose actions can't all be listed, such as bets of any size. legalActions()
 * then describes them (e.g. {type: "raise", min, max}) and isLegal() decides about a concrete one
 * (e.g. {type: "raise", amount: 120}).
 */
interface ValidatesActions
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function isLegal(array $state, int $seat, Action $action): bool;
}
