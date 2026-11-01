<?php

namespace App\Gameplay\Lifecycle;

use App\Gameplay\Actions\GameActionService;
use App\Models\GameRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
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

    /** A room that stays busy past the lock wait is tried again a second later (other errors fail at once). */
    public int $tries = 5;

    public int $maxExceptions = 1;

    public function __construct(public readonly int $roomId, public readonly int $version) {}

    public function handle(GameActionService $actions): void
    {
        $room = GameRoom::query()->find($this->roomId);

        if ($room === null) {
            return;
        }

        try {
            $actions->timeOut($room, $this->version);
        } catch (LockTimeoutException) {
            $this->release(1);
        }
    }
}
