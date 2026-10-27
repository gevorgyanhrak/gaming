<?php

namespace App\Gameplay\Contracts;

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;

/**
 * Chooses a bot's move. Receives only what the bot's seat may see (GameEngine::viewFor), never the
 * full state, so bots can't use hidden information. The chosen action is validated like a human's.
 */
interface BotStrategy
{
    /**
     * @param  array<string, mixed>  $view
     * @param  list<Action>  $legalActions  Never empty.
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action;
}
