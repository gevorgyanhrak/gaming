<?php

namespace App\Gameplay\Rooms;

use App\Models\GameRoom;
use App\Models\PlayGroup;

/**
 * What a table plays for: its group and, for stake games, each player's bet (0 for poker, where the
 * group's minimum bet is the big blind and players bring their Fishkas as chips).
 */
final readonly class TableTerms
{
    public function __construct(public PlayGroup $group, public int $stake) {}

    /** The terms of an existing room (joining a private table). */
    public static function of(GameRoom $room): ?self
    {
        return $room->group === null ? null : new self($room->group, $room->stake);
    }
}
