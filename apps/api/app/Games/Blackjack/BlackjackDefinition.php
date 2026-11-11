<?php

namespace App\Games\Blackjack;

use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Contracts\GameDefinition;
use App\Gameplay\Contracts\GameEngine;
use App\Games\Blackjack\Bot\BlackjackBot;
use App\Games\Blackjack\Engine\BlackjackEngine;

final class BlackjackDefinition implements GameDefinition
{
    public function slug(): string
    {
        return 'blackjack';
    }

    public function engine(): GameEngine
    {
        return new BlackjackEngine;
    }

    public function bot(): BotStrategy
    {
        return new BlackjackBot;
    }

    public function version(): int
    {
        return 1;
    }
}
