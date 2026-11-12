<?php

namespace App\Enums;

/**
 * What matchmaking does when not enough people are found within the timeout.
 */
enum BotFallbackMode: string
{
    /** AI fills the empty seats of a table once people have waited long enough. */
    case Auto = 'auto';

    /** Former "offer a bot game"; since 2026-11-12 it works as Auto (there is no offer any more). */
    case Offer = 'offer';

    /** Never fill seats with AI: keep waiting for people. */
    case Off = 'off';
}
