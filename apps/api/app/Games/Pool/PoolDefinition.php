<?php

namespace App\Games\Pool;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Pool\Bot\PoolBot;
use App\Games\Pool\Engine\PoolEngine;

final class PoolDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'pool';
    }

    public function engine(): GameEngine
    {
        return new PoolEngine;
    }

    public function bot(): BotStrategy
    {
        return new PoolBot;
    }

    public function version(): int
    {
        return 1;
    }
}
