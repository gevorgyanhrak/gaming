<?php

namespace App\Games\Ludo;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Ludo\Bot\LudoBot;
use App\Games\Ludo\Engine\LudoEngine;

final class LudoDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'ludo';
    }

    public function engine(): GameEngine
    {
        return new LudoEngine;
    }

    public function bot(): BotStrategy
    {
        return new LudoBot;
    }

    public function version(): int
    {
        return 1;
    }
}
