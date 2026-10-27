<?php

use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Each player's private channel: room updates (with their own view), matchmaking results.
Broadcast::channel('user.{id}', fn (User $user, int $id) => $user->id === $id && $user->isActive());

// Presence of the players in a room (who is connected). Carries no game state.
Broadcast::channel('room.{room}', function (User $user, string $room) {
    $participant = GameRoom::query()->where('public_id', $room)->first()?->participantFor($user);

    return $participant === null ? false : ['id' => $user->id, 'seat' => $participant->seat];
});
