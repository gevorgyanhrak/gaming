<?php

namespace App\Games\Rummy;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Rummy\Bot\RummyBot;
use App\Games\Rummy\Engine\RummyEngine;

final class RummyDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'rummy';
    }

    public function engine(): GameEngine
    {
        return new RummyEngine;
    }

    public function bot(): BotStrategy
    {
        return new RummyBot;
    }

    public function version(): int
    {
        return 1;
    }
}
