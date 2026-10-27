<?php

namespace App\Enums;

enum RoomStatus: string
{
    /** Waiting for players (private rooms). */
    case Waiting = 'waiting';
    case Active = 'active';
    case Finished = 'finished';
    /** Closed before it started. */
    case Aborted = 'aborted';
}
