<?php

namespace App\Gameplay\Actions;

use App\Enums\ParticipantResult;
use App\Gameplay\Contracts\DecidesTimeouts;
use App\Gameplay\Contracts\HandlesDepartures;
use App\Gameplay\Contracts\HandlesTimeouts;
use App\Gameplay\Contracts\ValidatesActions;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\ActionResult;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
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
            $actor = $room->participants->firstWhere('id', $actor->id) ?? $actor;

            if ($clientActionId !== null) {
                $previous = $room->moves()->where('client_action_id', $clientActionId)->first();
                if ($previous !== null) {
                    return new ActionResult($room->state_version, replayed: true);
                }
            }

            $current = $this->guard($room, $expectedVersion);

            // Resigning is allowed at any time, not only on your turn.
            if ($action->type === GameMove::RESIGN) {
                if (! $actor->is_active) {
                    throw new RoomNotActive(__('platform.play.not_in_progress'));
                }

                return $this->othersPlayOn($room, $actor)
                    ? $this->depart($room, $current, $actor, $clientActionId)
                    : $this->end($room, $current, $actor, GameMove::RESIGN, $clientActionId);
            }

            if (! in_array($actor->seat, $current->seatsToAct, true)) {
                throw new IllegalAction(__('platform.play.not_your_turn'));
            }

            $time = TimeControl::for($room);

            // A move after the clock ran out (beyond the lag allowance) loses on time.
            if ($current->clock !== null && $time->left($current->clock, $actor->seat, now()->getTimestampMs()) < -TimeControl::GRACE_MS) {
                return $this->end($room, $current, $actor, GameMove::TIMEOUT, $clientActionId);
            }

            $engine = $this->states->engine($room);
            $legal = $engine instanceof ValidatesActions
                ? $engine->isLegal($current->state, $actor->seat, $action)
                : collect($engine->legalActions($current->state, $actor->seat))->contains(fn (Action $a) => $a->equals($action));

            if (! $legal) {
                throw new IllegalAction(__('platform.play.illegal_move'));
            }

            return $this->play($room, $current, $actor, $action, $clientActionId);
        });
    }

    /**
     * Acts for the seats to act if the turn scheduled at $version timed out: the engine's default
     * move where it has one, otherwise the seat loses.
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
            if ($timedOut === null) {
                return;
            }

            $engine = $this->states->engine($room);
            $default = $engine instanceof HandlesTimeouts ? $engine->timeoutAction($current->state, $timedOut->seat) : null;

            if ($default !== null) {
                $this->play($room, $current, $timedOut, $default, null);
            } else {
                $this->end($room, $current, $timedOut, GameMove::TIMEOUT, null);
            }
        });
    }

    /**
     * The player left the game: they lose, or at a bigger table that goes on they are taken out.
     */
    public function abandon(GameRoom $room, GameParticipant $participant): void
    {
        $this->store->locked($room, function () use ($room, $participant) {
            $room->refresh()->load(['game', 'participants']);
            $leaver = $room->participants->firstWhere('id', $participant->id);

            if (! $room->isActive() || $leaver === null || ! $leaver->is_active) {
                return;
            }

            $current = $this->states->current($room);
            $this->othersPlayOn($room, $leaver)
                ? $this->depart($room, $current, $leaver, null, GameMove::ABANDON)
                : $this->end($room, $current, $leaver, GameMove::ABANDON, null);
        });
    }

    /**
     * Apply a legal action and record it.
     */
    private function play(GameRoom $room, RoomState $current, GameParticipant $actor, Action $action, ?string $clientActionId): ActionResult
    {
        $engine = $this->states->engine($room);
        $time = TimeControl::for($room);
        $nowMs = now()->getTimestampMs();

        $random = new RecordingRandom;
        $transition = $engine->apply($current->state, $actor->seat, $action, $random);

        return $this->record($room, $current, $actor, $transition, [
            'type' => $action->type,
            'payload' => $action->toArray(),
            'rng' => $random->draws() ?: null,
            'client_action_id' => $clientActionId,
        ], function (array $seats) use ($current, $time, $actor, $nowMs) {
            return $current->clock === null ? null : $time->afterMove($current->clock, $actor->seat, ! in_array($actor->seat, $seats, true), $nowMs);
        });
    }

    /**
     * The player leaves a game that goes on without them: they lose, and the engine takes them out.
     */
    private function depart(GameRoom $room, RoomState $current, GameParticipant $leaver, ?string $clientActionId, string $type = GameMove::RESIGN): ActionResult
    {
        $engine = $this->states->engine($room);
        assert($engine instanceof HandlesDepartures);
        $random = new RecordingRandom;
        $transition = $engine->depart($current->state, $leaver->seat, $random);

        return $this->record($room, $current, $leaver, $transition, [
            'type' => $type,
            'payload' => ['type' => $type, 'departed' => true],
            'rng' => $random->draws() ?: null,
            'client_action_id' => $clientActionId,
        ], fn () => $current->clock, function () use ($room, $leaver, $type) {
            $this->finisher->finishParticipant($leaver, $room->game_id, $type === GameMove::ABANDON ? ParticipantResult::Abandoned : ParticipantResult::Loss);
        });
    }

    /**
     * Log a move, finish the game if the engine says it's over, store the new state and notify.
     *
     * @param  array<string, mixed>  $move  game_moves columns
     * @param  callable(list<int>): (array{remaining: list<int>, since: int}|null)  $nextClock
     * @param  (callable(): void)|null  $alsoInTransaction
     */
    private function record(GameRoom $room, RoomState $current, GameParticipant $actor, Transition $transition, array $move, callable $nextClock, ?callable $alsoInTransaction = null): ActionResult
    {
        $engine = $this->states->engine($room);
        $outcome = $engine->outcome($transition->state);
        $version = $current->version + 1;
        $seats = $outcome === null ? $engine->seatsToAct($transition->state) : [];
        $clock = $nextClock($seats);

        DB::transaction(function () use ($room, $actor, $move, $version, $outcome, $clock, $alsoInTransaction) {
            $room->moves()->create([...$move, 'seq' => $version, 'participant_id' => $actor->id]);
            $room->forceFill(['state_version' => $version, 'clock' => $clock])->save();

            if ($alsoInTransaction !== null) {
                $alsoInTransaction();
            }
            if ($outcome !== null) {
                $this->finisher->finish($room, $outcome, 'completed');
            }
        });

        $state = new RoomState($version, $transition->state, $seats, TimeControl::for($room)->deadline($clock, $seats, now()->getTimestampMs()), $clock);
        $this->store->put($room, $state);

        $this->afterChange($room, $state, $transition->events);

        return new ActionResult($version);
    }

    /** Whether the game can go on without $leaver: the engine supports it and another person still plays. */
    private function othersPlayOn(GameRoom $room, GameParticipant $leaver): bool
    {
        return $this->states->engine($room) instanceof HandlesDepartures
            && $room->participants->contains(fn (GameParticipant $p) => $p->id !== $leaver->id && $p->user_id !== null && $p->is_active);
    }

    /**
     * Resignation or timeout: the given seat loses (recorded as abandoned for a missed turn timer),
     * every other seat wins.
     */
    private function end(GameRoom $room, RoomState $current, GameParticipant $loser, string $type, ?string $clientActionId): ActionResult
    {
        $version = $current->version + 1;
        $winners = $room->participants
            ->filter(fn (GameParticipant $p) => $p->seat !== $loser->seat && $p->is_active)
            ->pluck('seat')->values()->all();
        $outcome = new Outcome($winners, $type);
        $engine = $this->states->engine($room);
        if ($type === GameMove::TIMEOUT && $engine instanceof DecidesTimeouts) {
            $outcome = $engine->timeoutOutcome($current->state, $loser->seat);
        }

        // Running out of a game clock (chess) is a plain loss on time; a missed per-turn timer counts as
        // walking away from the game.
        $abandoned = $type === GameMove::ABANDON || ($type === GameMove::TIMEOUT && $current->clock === null);

        DB::transaction(function () use ($room, $loser, $type, $version, $clientActionId, $outcome, $abandoned) {
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
                $abandoned && ! $outcome->isDraw() ? [$loser->seat] : [],
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
