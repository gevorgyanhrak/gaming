<?php

namespace Tests\Fixtures\TicTacToe;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;

final class TicTacToeDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'tictactoe';
    }

    public function engine(): GameEngine
    {
        return new TicTacToeEngine;
    }

    public function bot(): BotStrategy
    {
        return new TicTacToeBot;
    }

    public function version(): int
    {
        return 1;
    }
}
