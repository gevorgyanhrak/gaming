<?php

namespace App\Gameplay\Bots;

use App\Enums\BotDifficulty;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Exceptions\RoomNotActive;
use App\Gameplay\Exceptions\VersionConflict;
use App\Gameplay\GameRegistry;
use App\Gameplay\Random\SecureRandom;
use App\Gameplay\Rooms\RoomStates;
use App\Models\GameRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Plays one move for a bot seat, through the same pipeline and validation as a human move.
 */
class BotTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly int $roomId,
        public readonly int $version,
        public readonly int $seat,
    ) {}

    public function handle(RoomStates $states, GameRegistry $registry, GameActionService $actions): void
    {
        $room = GameRoom::query()->with(['game', 'participants'])->find($this->roomId);
        $bot = $room?->participants->firstWhere('seat', $this->seat);

        if ($room === null || $bot === null || ! $bot->isBot() || ! $room->isActive() || $room->state_version !== $this->version) {
            return;
        }

        $state = $states->current($room);
        $engine = $states->engine($room);
        $legal = $engine->legalActions($state->state, $this->seat);

        if ($legal === []) {
            return;
        }

        $action = $registry->get($room->game->slug)->bot()->chooseAction(
            $engine->viewFor($state->state, $this->seat),
            $legal,
            $bot->bot_difficulty ?? BotDifficulty::Medium,
            new SecureRandom,
        );

        try {
            $actions->submit($room, $bot, $action, expectedVersion: $this->version);
        } catch (VersionConflict|RoomNotActive) {
            // The room moved on (timeout, resignation) while the bot was thinking.
        }
    }
}
