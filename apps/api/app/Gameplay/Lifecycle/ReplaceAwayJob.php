<?php

namespace App\Gameplay\Lifecycle;

use App\Models\GameParticipant;
use App\Models\GameRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The end of a disconnected player's return window (ADR 0028): AI takes their seat unless they came back. Runs on
 * the game queue at the window's end; nothing happens if they returned or the game is over.
 */
class ReplaceAwayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $roomId, public readonly int $participantId)
    {
        $this->onQueue((string) config('gameplay.queue', 'game'));
    }

    public function handle(RoomPresence $presence): void
    {
        $room = GameRoom::query()->find($this->roomId);
        $participant = GameParticipant::query()->find($this->participantId);
        if ($room === null || $participant === null || $participant->disconnected_at === null || $participant->replaced_at !== null) {
            return;
        }
        // A worker running a moment early waits for the window's real end (server time decides, ADR 0028).
        if (! $presence->windowOver($participant)) {
            $this->release(1);

            return;
        }
        $presence->expire($room, $participant);
    }
}
