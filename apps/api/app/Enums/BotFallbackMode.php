<?php

namespace App\Enums;

/**
 * What matchmaking does when no human opponent is found within the timeout.
 */
enum BotFallbackMode: string
{
    /** Start a game against a bot automatically. */
    case Auto = 'auto';

    /** Offer the player a bot game; they may keep waiting. */
    case Offer = 'offer';

    /** Never fall back to a bot. */
    case Off = 'off';
}
