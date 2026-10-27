<?php

namespace App\Policies;

use App\Models\GameRoom;
use App\Models\User;

/**
 * Rooms are visible to their players only (spectating is not part of the MVP).
 */
class GameRoomPolicy
{
    public function view(User $user, GameRoom $room): bool
    {
        return $room->participants()->where('user_id', $user->id)->exists();
    }
}
