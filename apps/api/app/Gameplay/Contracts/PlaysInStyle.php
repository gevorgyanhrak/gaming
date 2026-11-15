<?php

namespace App\Gameplay\Contracts;

use App\Enums\BotPersonality;

/**
 * A bot whose game leaves room for style (when to bluff, how much to bet) plays in its AI player's personality.
 * Style only: it must not make the play weaker.
 */
interface PlaysInStyle
{
    public function inStyle(BotPersonality $personality): BotStrategy;
}
