<?php

namespace App\Games\Checkers;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Checkers\Bot\CheckersBot;
use App\Games\Checkers\Engine\CheckersEngine;

final class CheckersDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'checkers';
    }

    public function engine(): GameEngine
    {
        return new CheckersEngine;
    }

    public function bot(): BotStrategy
    {
        return new CheckersBot;
    }

    public function version(): int
    {
        return 1;
    }
}
