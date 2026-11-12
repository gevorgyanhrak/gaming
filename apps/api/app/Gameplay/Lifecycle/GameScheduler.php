<?php

namespace App\Gameplay\Lifecycle;

use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\Contracts\PacesThinking;
use App\Gameplay\GameRegistry;
use App\Gameplay\Random\SecureRandom;
use App\Gameplay\Rooms\RoomState;
use App\Gameplay\Rooms\RoomStates;
use App\Models\GameRoom;

/**
 * After a room changes: schedule the turn timeout and any AI turns (AI players, and people's seats AI
 * plays for them: AiTakeover) for the new version, or the end
 * of a pause between rounds. Every job carries the version it was scheduled for and does nothing if
 * the room has moved on since. Call after the transaction commits.
 */
class GameScheduler
{
    public function __construct(
        private readonly RoomStates $states,
        private readonly GameRegistry $registry,
    ) {}

    public function afterChange(GameRoom $room, RoomState $state): void
    {
        if (! $room->isActive()) {
            return;
        }

        if ($state->seatsToAct === []) {
            $pause = $this->states->pauseMs($room, $state->state, []);
            if ($pause !== null) {
                ResumeRoundJob::dispatch($room->id, $state->version)
                    ->onQueue(config('gameplay.queue'))
                    ->delay(now()->addMilliseconds($pause));
            }

            return;
        }

        if ($state->deadline !== null) {
            TurnTimeoutJob::dispatch($room->id, $state->version)
                ->onQueue(config('gameplay.queue'))
                ->delay(now()->setTimestamp($state->deadline)->addSecond());
        }

        foreach ($room->participants as $participant) {
            if ($participant->playedByAi() && in_array($participant->seat, $state->seatsToAct, true)) {
                BotTurnJob::dispatch($room->id, $state->version, $participant->seat)
                    ->onQueue(config('gameplay.queue'))
                    ->delay(now()->addMilliseconds($this->thinking($room, $state, $participant->seat)));
            }
        }
    }

    /** How long the AI at $seat waits before it moves: as its game's bot says (PacesThinking), or the default. */
    private function thinking(GameRoom $room, RoomState $state, int $seat): int
    {
        $bot = $this->registry->get($room->game->slug)->bot();
        if ($bot instanceof PacesThinking) {
            $engine = $this->states->engine($room);

            return max(0, $bot->thinkingMs($engine->viewFor($state->state, $seat), $engine->legalActions($state->state, $seat), new SecureRandom));
        }
        $delay = config('gameplay.bot_delay_ms');

        return random_int($delay['min'], $delay['max']);
    }
}
