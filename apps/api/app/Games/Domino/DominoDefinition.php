<?php

namespace App\Games\Domino;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Domino\Bot\DominoBot;
use App\Games\Domino\Engine\DominoEngine;

final class DominoDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'domino';
    }

    public function engine(): GameEngine
    {
        return new DominoEngine;
    }

    public function bot(): BotStrategy
    {
        return new DominoBot;
    }

    public function version(): int
    {
        return 1;
    }
}
