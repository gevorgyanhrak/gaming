<?php

namespace App\Enums;

/**
 * Which set of play groups a game uses: "chips" (poker: players bring their Fishkas as chips; the
 * group's minimum bet is the big blind) or "stake" (every other game: each player stakes the table's
 * bet and the winners share the pot).
 */
enum PlayGroupKind: string
{
    case Chips = 'chips';
    case Stake = 'stake';
}
