<?php

namespace App\Gameplay\Bots;

use App\Enums\BotDifficulty;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Contracts\ActsOutOfTurn;
use App\Gameplay\Contracts\HasOutOfTurnActions;
use App\Gameplay\Exceptions\RoomNotActive;
use App\Gameplay\Exceptions\VersionConflict;
use App\Gameplay\GameRegistry;
use App\Gameplay\Random\SecureRandom;
use App\Gameplay\Rooms\RoomStates;
use App\Models\GameMove;
use App\Models\GameRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Plays one move for a seat AI plays (an AI player, or a person's seat AI holds for them: AiTakeover),
 * through the same pipeline and validation as a human move, with the game's own bot at the game's highest
 * level. Moves for a person's seat are marked as the server's, so they never count as theirs (XP).
 */
class BotTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** A room that stays busy past the lock wait is tried again a second later (other errors fail at once). */
    public int $tries = 5;

    public int $maxExceptions = 1;

    public function __construct(
        public readonly int $roomId,
        public readonly int $version,
        public readonly int $seat,
        /** Not the seat's turn: only an action the engine allows out of turn, if the bot wants it. */
        public readonly bool $outOfTurn = false,
    ) {}

    public function handle(RoomStates $states, GameRegistry $registry, GameActionService $actions, AiPlayers $ai): void
    {
        $room = GameRoom::query()->with(['game', 'participants'])->find($this->roomId);
        $bot = $room?->participants->firstWhere('seat', $this->seat);

        if ($room === null || $bot === null || ! $bot->playedByAi() || ! $room->isActive() || $room->state_version !== $this->version) {
            return;
        }

        $state = $states->current($room);
        $engine = $states->engine($room);
        $strategy = $registry->get($room->game->slug)->bot();
        $difficulty = $bot->bot_difficulty ?? $ai->difficulty($room->game) ?? BotDifficulty::Hard;

        if ($this->outOfTurn) {
            $offered = $engine instanceof HasOutOfTurnActions ? $engine->outOfTurnActions($state->state, $this->seat) : [];
            $action = $offered !== [] && $strategy instanceof ActsOutOfTurn
                ? $strategy->chooseOutOfTurn($engine->viewFor($state->state, $this->seat), $offered, $difficulty, new SecureRandom)
                : null;
            if ($action === null) {
                return;
            }
        } else {
            $legal = $engine->legalActions($state->state, $this->seat);
            if ($legal === []) {
                return;
            }
            $action = $strategy->chooseAction($engine->viewFor($state->state, $this->seat), $legal, $difficulty, new SecureRandom);
        }

        try {
            $actions->submit($room, $bot, $action, expectedVersion: $this->version, clientActionId: $bot->isBot() ? null : GameMove::AUTO_PREFIX."ai{$this->version}");
        } catch (VersionConflict|RoomNotActive) {
            // The room moved on (timeout, resignation) while the bot was thinking.
        } catch (LockTimeoutException) {
            $this->release(1);
        }
    }
}
