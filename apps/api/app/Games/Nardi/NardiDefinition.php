<?php

namespace App\Games\Nardi;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Nardi\Bot\NardiBot;
use App\Games\Nardi\Engine\NardiEngine;

final class NardiDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'nardi';
    }

    public function engine(): GameEngine
    {
        return new NardiEngine;
    }

    public function bot(): BotStrategy
    {
        return new NardiBot;
    }

    public function version(): int
    {
        return 1;
    }
}
