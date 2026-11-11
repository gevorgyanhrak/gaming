<?php

namespace App\Games\Blot;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Engine\BlotEngine;

final class BlotDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'blot';
    }

    public function engine(): GameEngine
    {
        return new BlotEngine;
    }

    public function bot(): BotStrategy
    {
        return new BlotBot;
    }

    /** 2: Armenian Bazar Blot rules, scored in sai (docs/games/blot-rules.md). */
    public function version(): int
    {
        return 2;
    }
}
