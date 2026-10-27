<?php

namespace App\Gameplay\Actions;

use App\Gameplay\Contracts\DecidesTimeouts;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\ActionResult;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Exceptions\RoomNotActive;
use App\Gameplay\Exceptions\VersionConflict;
use App\Gameplay\Lifecycle\GameFinisher;
use App\Gameplay\Lifecycle\GameScheduler;
use App\Gameplay\Lifecycle\TimeControl;
use App\Gameplay\Random\RecordingRandom;
use App\Gameplay\Rooms\RoomState;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\GameMove;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for gameplay: every move, resignation and timeout, from humans and bots.
 *
 * Under the room lock: idempotency (client_action_id) → room active → version check → turn check →
 * legal action → DB transaction (move log + game end) → state store → after commit, timers, bots and
 * notifications. The move log's unique (room, seq) and (room, client_action_id) are the last line of
 * defence against duplicates.
 */
class GameActionService
{
    public function __construct(
        private readonly RoomStateStore $store,
        private readonly RoomStates $states,
        private readonly GameFinisher $finisher,
        private readonly GameScheduler $scheduler,
        private readonly RoomEvents $events,
    ) {}

    /**
     * @throws RoomNotActive|VersionConflict|IllegalAction
     */
    public function submit(GameRoom $room, GameParticipant $actor, Action $action, ?int $expectedVersion = null, ?string $clientActionId = null): ActionResult
    {
        return $this->store->locked($room, function () use ($room, $actor, $action, $expectedVersion, $clientActionId) {
            $room->refresh()->load(['game', 'participants']);

            if ($clientActionId !== null) {
                $previous = $room->moves()->where('client_action_id', $clientActionId)->first();
                if ($previous !== null) {
                    return new ActionResult($room->state_version, replayed: true);
                }
            }

            $current = $this->guard($room, $expectedVersion);

            // Resigning is allowed at any time, not only on your turn.
            if ($action->type === GameMove::RESIGN) {
                return $this->end($room, $current, $actor, GameMove::RESIGN, $clientActionId);
            }

            if (! in_array($actor->seat, $current->seatsToAct, true)) {
                throw new IllegalAction(__('platform.play.not_your_turn'));
            }

            $time = TimeControl::for($room);
            $nowMs = now()->getTimestampMs();

            // A move after the clock ran out (beyond the lag allowance) loses on time.
            if ($current->clock !== null && $time->left($current->clock, $actor->seat, $nowMs) < -TimeControl::GRACE_MS) {
                return $this->end($room, $current, $actor, GameMove::TIMEOUT, $clientActionId);
            }

            $engine = $this->states->engine($room);
            $legal = collect($engine->legalActions($current->state, $actor->seat))->contains(fn (Action $a) => $a->equals($action));

            if (! $legal) {
                throw new IllegalAction(__('platform.play.illegal_move'));
            }

            $random = new RecordingRandom;
            $transition = $engine->apply($current->state, $actor->seat, $action, $random);
            $outcome = $engine->outcome($transition->state);
            $version = $current->version + 1;
            $seats = $outcome === null ? $engine->seatsToAct($transition->state) : [];
            $clock = $current->clock === null ? null : $time->afterMove($current->clock, $actor->seat, ! in_array($actor->seat, $seats, true), $nowMs);

            DB::transaction(function () use ($room, $actor, $action, $random, $version, $clientActionId, $outcome, $clock) {
                $room->moves()->create([
                    'seq' => $version,
                    'participant_id' => $actor->id,
                    'type' => $action->type,
                    'payload' => $action->toArray(),
                    'rng' => $random->draws() ?: null,
                    'client_action_id' => $clientActionId,
                ]);
                $room->forceFill(['state_version' => $version, 'clock' => $clock])->save();

                if ($outcome !== null) {
                    $this->finisher->finish($room, $outcome, 'completed');
                }
            });

            $state = new RoomState($version, $transition->state, $seats, $time->deadline($clock, $seats, $nowMs), $clock);
            $this->store->put($room, $state);

            $this->afterChange($room, $state, $transition->events);

            return new ActionResult($version);
        });
    }

    /**
     * Ends the game for the seats to act if the turn scheduled at $version timed out.
     */
    public function timeOut(GameRoom $room, int $version): void
    {
        $this->store->locked($room, function () use ($room, $version) {
            $room->refresh()->load(['game', 'participants']);

            if (! $room->isActive() || $room->state_version !== $version) {
                return;
            }

            $current = $this->states->current($room);

            if ($current->deadline === null || $current->deadline > now()->getTimestamp()) {
                return;
            }

            $timedOut = $room->participants->firstWhere('seat', $current->seatsToAct[0] ?? -1);
            if ($timedOut !== null) {
                $this->end($room, $current, $timedOut, GameMove::TIMEOUT, null);
            }
        });
    }

    /**
     * Resignation or timeout: the given seat loses, every other seat wins. (For games with more than
     * two players, ending only that player's participation comes with those games.)
     */
    private function end(GameRoom $room, RoomState $current, GameParticipant $loser, string $type, ?string $clientActionId): ActionResult
    {
        $version = $current->version + 1;
        $winners = $room->participants->pluck('seat')->reject(fn (int $seat) => $seat === $loser->seat)->values()->all();
        $outcome = new Outcome($winners, $type);
        $engine = $this->states->engine($room);
        if ($type === GameMove::TIMEOUT && $engine instanceof DecidesTimeouts) {
            $outcome = $engine->timeoutOutcome($current->state, $loser->seat);
        }

        DB::transaction(function () use ($room, $loser, $type, $version, $clientActionId, $outcome) {
            $room->moves()->create([
                'seq' => $version,
                'participant_id' => $loser->id,
                'type' => $type,
                'client_action_id' => $clientActionId,
            ]);
            $room->forceFill(['state_version' => $version])->save();

            $this->finisher->finish(
                $room,
                $outcome,
                $type,
                $type === GameMove::TIMEOUT && ! $outcome->isDraw() ? [$loser->seat] : [],
            );
        });

        $state = new RoomState($version, $current->state, [], null, $current->clock);
        $this->store->put($room, $state);
        $this->afterChange($room, $state, [['type' => $type, 'seat' => $loser->seat]]);

        return new ActionResult($version);
    }

    private function guard(GameRoom $room, ?int $expectedVersion): RoomState
    {
        if (! $room->isActive()) {
            throw new RoomNotActive(__('platform.play.not_in_progress'));
        }

        if ($expectedVersion !== null && $expectedVersion !== $room->state_version) {
            throw new VersionConflict($room->state_version);
        }

        return $this->states->current($room);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function afterChange(GameRoom $room, RoomState $state, array $events): void
    {
        DB::afterCommit(function () use ($room, $state, $events) {
            $this->events->changed($room, $state, $events);
            $this->scheduler->afterChange($room, $state);
        });
    }
}
