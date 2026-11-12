<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;

/**
 * Optional for a game's bot (BotStrategy): how long a person would think before this decision, so AI
 * players don't answer at machine speed — a forced move quickly, a hard choice longer. Without it the
 * platform waits `gameplay.bot_delay_ms`.
 */
interface PacesThinking
{
    /**
     * @param  array<string, mixed>  $view  the seat's view
     * @param  list<Action>  $legalActions
     */
    public function thinkingMs(array $view, array $legalActions, RandomSource $random): int;
}
