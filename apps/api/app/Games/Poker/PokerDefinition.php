<?php

namespace App\Games\Poker;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Poker\Bot\PokerBot;
use App\Games\Poker\Engine\PokerEngine;

final class PokerDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'poker';
    }

    public function engine(): GameEngine
    {
        return new PokerEngine;
    }

    public function bot(): BotStrategy
    {
        return new PokerBot;
    }

    public function version(): int
    {
        return 1;
    }
}
