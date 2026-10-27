<?php

namespace App\Gameplay\Actions;

use App\Gameplay\Broadcasting\RoomUpdated;
use App\Gameplay\Rooms\RoomPresenter;
use App\Gameplay\Rooms\RoomState;
use App\Models\GameRoom;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells every human player in a room about a change, each with their own view.
 * A broadcasting failure never undoes an accepted move: clients resync by version.
 */
class RoomEvents
{
    public function __construct(private readonly RoomPresenter $presenter) {}

    /**
     * @param  list<array<string, mixed>>  $events
     */
    public function changed(GameRoom $room, RoomState $state, array $events = []): void
    {
        foreach ($room->participants as $participant) {
            if ($participant->user_id === null) {
                continue;
            }

            try {
                event(new RoomUpdated($participant->user_id, [
                    ...$this->presenter->stateFor($room, $state, $participant),
                    'events' => $events,
                ]));
            } catch (Throwable $e) {
                Log::warning('Room update broadcast failed.', ['room' => $room->public_id, 'error' => $e->getMessage()]);
            }
        }
    }
}
