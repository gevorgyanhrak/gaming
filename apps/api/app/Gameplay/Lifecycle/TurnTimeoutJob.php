<?php

namespace App\Gameplay\Lifecycle;

use App\Gameplay\Actions\GameActionService;
use App\Models\GameRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Ends the game for the seats to act when the turn deadline passes. The turn clock, not socket
 * presence, decides abandonment: a disconnected player keeps their turn until it runs out.
 */
class TurnTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $roomId, public readonly int $version) {}

    public function handle(GameActionService $actions): void
    {
        $room = GameRoom::query()->find($this->roomId);

        if ($room !== null) {
            $actions->timeOut($room, $this->version);
        }
    }
}
