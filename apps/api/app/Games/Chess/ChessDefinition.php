<?php

namespace App\Games\Chess;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Chess\Bot\ChessBot;
use App\Games\Chess\Engine\ChessEngine;

final class ChessDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'chess';
    }

    public function engine(): GameEngine
    {
        return new ChessEngine;
    }

    public function bot(): BotStrategy
    {
        return new ChessBot;
    }

    public function version(): int
    {
        return 1;
    }
}
