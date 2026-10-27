<?php

namespace App\Games\Durak;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Durak\Bot\DurakBot;
use App\Games\Durak\Engine\DurakEngine;

final class DurakDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'durak';
    }

    public function engine(): GameEngine
    {
        return new DurakEngine;
    }

    public function bot(): BotStrategy
    {
        return new DurakBot;
    }

    public function version(): int
    {
        return 1;
    }
}
