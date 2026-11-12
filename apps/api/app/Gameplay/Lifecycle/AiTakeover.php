<?php

namespace App\Gameplay\Lifecycle;

use App\Gameplay\Contracts\PlaysForChips;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\GameParticipant;
use App\Models\GameRoom;

/**
 * AI stands in for people at tables of three seats or more (owner, 2026-11-12), so the others keep
 * playing: while a player is away (missed presence reports) AI plays their seat, and gives it back as
 * soon as they are seen again; the result stays theirs. A player who presses Leave loses, and AI plays
 * their seat to the end (tables played for chips keep their own cash-out instead: see GameActionService).
 * Two-player games never hand a seat to AI: the one who stays wins. Each game's own bot (BotStrategy)
 * makes the moves, at the game's highest level, through the same path as everyone (BotTurnJob).
 */
class AiTakeover
{
    public function __construct(
        private readonly RoomStateStore $store,
        private readonly RoomStates $states,
        private readonly GameScheduler $scheduler,
    ) {}

    /**
     * Whether AI may take $participant's seat: a person at a table of 3+ where another person still
     * plays (and is there). Expects the room's participants loaded.
     */
    public function canStandIn(GameRoom $room, GameParticipant $participant): bool
    {
        return $participant->user_id !== null
            && $room->seats >= 3
            && $room->participants->contains(fn (GameParticipant $p) => $p->id !== $participant->id
                && $p->user_id !== null && $p->is_active && ! $p->ai_controlled);
    }

    /** Whether leaving this table hands the seat to AI (not at tables played for chips). */
    public function takesLeaversSeat(GameRoom $room, GameParticipant $participant): bool
    {
        return ! $this->states->engine($room) instanceof PlaysForChips && $this->canStandIn($room, $participant);
    }

    /**
     * The player is away: AI plays their seat from now on. Returns false when it can't (then they have left).
     */
    public function standIn(GameRoom $room, GameParticipant $participant): bool
    {
        return (bool) $this->store->locked($room, function () use ($room, $participant) {
            $room->refresh()->load(['game', 'participants']);
            $seat = $room->participants->firstWhere('id', $participant->id);
            if (! $room->isActive() || $seat === null || ! $seat->is_active) {
                return true;   // Nothing to do: the game or their part in it is over.
            }
            if ($seat->ai_controlled) {
                return true;
            }
            if (! $this->canStandIn($room, $seat)) {
                return false;
            }
            $seat->forceFill(['ai_controlled' => true])->save();
            $this->playNow($room);

            return true;
        });
    }

    /** The player is back: their seat is theirs again (not after they left). */
    public function handBack(GameRoom $room, GameParticipant $participant): void
    {
        if (! $participant->ai_controlled || ! $participant->is_active) {
            return;
        }
        $this->store->locked($room, function () use ($participant) {
            $participant->refresh();
            if ($participant->ai_controlled && $participant->is_active) {
                $participant->forceFill(['ai_controlled' => false])->save();
            }
        });
    }

    /**
     * Schedule AI's turn now if a seat it plays is to act (call under the room lock, after a change of who
     * plays a seat).
     */
    public function playNow(GameRoom $room): void
    {
        $room->load('participants');
        $current = $this->states->current($room);
        $this->scheduler->afterChange($room, $current);
    }
}
