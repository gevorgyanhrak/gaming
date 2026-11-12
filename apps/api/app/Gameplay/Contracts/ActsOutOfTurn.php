<?php

namespace App\Gameplay\Contracts;

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;

/**
 * Optional for a game's bot: whether to take one of the engine's out-of-turn actions (HasOutOfTurnActions)
 * now, or null to let the moment pass.
 */
interface ActsOutOfTurn
{
    /**
     * @param  array<string, mixed>  $view
     * @param  list<Action>  $actions
     */
    public function chooseOutOfTurn(array $view, array $actions, BotDifficulty $difficulty, RandomSource $random): ?Action;
}
