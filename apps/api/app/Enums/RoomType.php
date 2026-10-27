<?php

namespace App\Enums;

enum RoomType: string
{
    /** Created by matchmaking for players who were paired automatically. */
    case Matchmaking = 'matchmaking';

    /** Created by a player; others join with an invite code. */
    case Private = 'private';

    /** A player against one or more bots. */
    case Bot = 'bot';
}
