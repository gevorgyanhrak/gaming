<?php

namespace App\Gameplay\Lifecycle;

use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\Bots\HumanPace;
use App\Gameplay\Contracts\ActsOutOfTurn;
use App\Gameplay\Contracts\HasOutOfTurnActions;
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

        $engine = $this->states->engine($room);
        $bot = $this->registry->get($room->game->slug)->bot();
        // AI players due at the same moment take their turns one after another, as people would.
        $queue = 0;
        foreach ($room->participants->sortBy(fn ($p) => random_int(0, 1000)) as $participant) {
            if (! $participant->playedByAi()) {
                continue;
            }
            if (in_array($participant->seat, $state->seatsToAct, true)) {
                BotTurnJob::dispatch($room->id, $state->version, $participant->seat)
                    ->onQueue(config('gameplay.queue'))
                    ->delay(now()->addMilliseconds($this->thinking($room, $state, $participant->seat, $queue++)));
            } elseif ($engine instanceof HasOutOfTurnActions && $bot instanceof ActsOutOfTurn
                && $engine->outOfTurnActions($state->state, $participant->seat) !== []) {
                // AI may also act out of turn (Blot's Quansh), after a moment's thought.
                BotTurnJob::dispatch($room->id, $state->version, $participant->seat, outOfTurn: true)
                    ->onQueue(config('gameplay.queue'))
                    ->delay(now()->addMilliseconds(1500 + HumanPace::thinkingMs(1, $participant->bot?->personality, $queue++)));
            }
        }
    }

    /**
     * How long the AI at $seat waits before it moves (HumanPace): its game's own estimate where the bot gives one
     * (PacesThinking), otherwise from how many moves it can choose from; at its personality's pace, after the AI
     * players before it in $queue, and inside the turn timer.
     */
    private function thinking(GameRoom $room, RoomState $state, int $seat, int $queue): int
    {
        $engine = $this->states->engine($room);
        $legal = $engine->legalActions($state->state, $seat);
        $bot = $this->registry->get($room->game->slug)->bot();
        $base = $bot instanceof PacesThinking
            ? max(0, $bot->thinkingMs($engine->viewFor($state->state, $seat), $legal, new SecureRandom))
            : null;
        $msLeft = $state->deadline === null ? null : max(0, $state->deadline * 1000 - (int) floor(microtime(true) * 1000));

        return HumanPace::thinkingMs(count($legal), $room->participants->firstWhere('seat', $seat)?->bot?->personality, $queue, $base, $msLeft);
    }
}
