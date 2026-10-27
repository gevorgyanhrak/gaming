<?php

namespace App\Gameplay\Lifecycle;

use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\Rooms\RoomState;
use App\Models\GameRoom;

/**
 * After a room changes: schedule the turn timeout and any bot turns for the new version. Every job
 * carries the version it was scheduled for and does nothing if the room has moved on since.
 * Call after the transaction commits.
 */
class GameScheduler
{
    public function afterChange(GameRoom $room, RoomState $state): void
    {
        if (! $room->isActive() || $state->seatsToAct === []) {
            return;
        }

        if ($state->deadline !== null) {
            TurnTimeoutJob::dispatch($room->id, $state->version)
                ->onQueue(config('gameplay.queue'))
                ->delay(now()->setTimestamp($state->deadline)->addSecond());
        }

        $delay = config('gameplay.bot_delay_ms');
        foreach ($room->participants as $participant) {
            if ($participant->isBot() && in_array($participant->seat, $state->seatsToAct, true)) {
                BotTurnJob::dispatch($room->id, $state->version, $participant->seat)
                    ->onQueue(config('gameplay.queue'))
                    ->delay(now()->addMilliseconds(random_int($delay['min'], $delay['max'])));
            }
        }
    }
}
