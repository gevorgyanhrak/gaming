<?php

namespace App\Gameplay\Lifecycle;

use App\Gameplay\Actions\GameActionService;
use App\Models\GameRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Starts the next round when the pause scheduled at $version is over (see PausesBetweenRounds).
 */
class ResumeRoundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $roomId, public readonly int $version) {}

    public function handle(GameActionService $actions): void
    {
        $room = GameRoom::query()->find($this->roomId);

        if ($room !== null) {
            $actions->resume($room, $this->version);
        }
    }
}
