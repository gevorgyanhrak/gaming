<?php

namespace App\Gameplay\Lifecycle;

use App\Enums\RoomStatus;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Broadcasting\RoomSeatsChanged;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Platform\Settings\Settings;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Whether players are still at their game (ADR 0028). The game page reports every 2 s and says when it closes; a
 * player not seen for `presence.offline_after_seconds`, or whose page closed, is disconnected: the others see a
 * countdown and play on, and the player has `presence.return_seconds` to come back. Back in time: nothing changed.
 * Not back (ReplaceAwayJob, exactly at the end of the window), or gone to another game: AI takes the seat for the
 * rest of the game (GameActionService::replaceAway). Every decision is made under the room lock, by server time.
 */
class RoomPresence
{
    public function __construct(
        private readonly GameActionService $actions,
        private readonly RoomStateStore $rooms,
        private readonly Settings $settings,
    ) {}

    /** The player's page reported: they are here. A disconnected player who is back in time keeps their seat. */
    public function seen(GameRoom $room, GameParticipant $participant): void
    {
        $this->store()->put($this->key($room, $participant), now()->getTimestamp(), 3600);
        if ($participant->disconnected_at !== null) {
            $this->comeBack($room, $participant);
        }
        $this->leaveOtherWindows($room, $participant);
    }

    /** Everyone starts as present when a game begins (they are on their way to the page). */
    public function started(GameRoom $room): void
    {
        foreach ($room->participants as $participant) {
            if ($participant->user_id !== null) {
                $this->seen($room, $participant);
            }
        }
    }

    /** The player's page closed or went quiet: the return window opens (once). */
    public function away(GameRoom $room, GameParticipant $participant): void
    {
        $opened = $this->rooms->locked($room, function () use ($room, $participant) {
            $room->refresh()->load('participants');
            $seat = $room->participants->firstWhere('id', $participant->id);
            if (! $room->isActive() || $seat === null || $seat->user_id === null || ! $seat->is_active
                || $seat->ai_controlled || $seat->disconnected_at !== null) {
                return null;
            }
            $seat->forceFill(['disconnected_at' => now()])->save();

            return $seat;
        });
        if ($opened instanceof GameParticipant) {
            ReplaceAwayJob::dispatch($room->id, $opened->id)->delay(now()->addSeconds($this->window()));
            $this->tell($room, $opened, 'disconnected');
        }
    }

    /** The window's end came (ReplaceAwayJob): AI takes the seat unless they came back. */
    public function expire(GameRoom $room, GameParticipant $participant): bool
    {
        $replaced = $this->actions->replaceAway($room, $participant, fn (GameParticipant $seat) => $this->windowOver($seat));
        if ($replaced) {
            $this->tell($room, $participant, 'replaced');
        }

        return $replaced;
    }

    /** Mark players whose pages went quiet as disconnected. Run every second by the scheduler. */
    public function sweep(): void
    {
        $cutoff = now()->getTimestamp() - (int) $this->settings->get('presence.offline_after_seconds');

        $rooms = GameRoom::query()->where('status', RoomStatus::Active)->with('participants')->get();
        foreach ($rooms as $room) {
            foreach ($room->participants as $participant) {
                if ($participant->user_id === null || ! $participant->is_active || $participant->ai_controlled || $participant->disconnected_at !== null) {
                    continue;
                }
                $last = $this->store()->get($this->key($room, $participant));
                if (! is_numeric($last)) {
                    // Unknown (e.g. the store was flushed): count from now rather than punish.
                    $this->store()->put($this->key($room, $participant), now()->getTimestamp(), 3600);
                } elseif ((int) $last < $cutoff) {
                    $this->away($room, $participant);
                }
            }
        }
    }

    /** Whether the return window is over for this seat (server time; the boundary itself counts as over). */
    public function windowOver(GameParticipant $seat): bool
    {
        return $seat->disconnected_at !== null
            && now()->getTimestampMs() >= Carbon::parse($seat->disconnected_at)->getTimestampMs() + $this->window() * 1000;
    }

    private function comeBack(GameRoom $room, GameParticipant $participant): void
    {
        $back = $this->rooms->locked($room, function () use ($room, $participant) {
            $seat = $room->participants()->whereKey($participant->id)->first();
            if ($seat === null || $seat->disconnected_at === null || $seat->replaced_at !== null || $this->windowOver($seat)) {
                return false;
            }
            $seat->forceFill(['disconnected_at' => null])->save();

            return true;
        });
        if ($back) {
            $participant->disconnected_at = null;
            $this->tell($room, $participant, 'returned');
        } else {
            // Too late (the job may not have run yet): the window is over, AI takes the seat now.
            $this->expire($room, $participant);
        }
    }

    /** Playing elsewhere ends any return window the player still had at another table. */
    private function leaveOtherWindows(GameRoom $room, GameParticipant $participant): void
    {
        $others = GameParticipant::query()->with('room')
            ->where('user_id', $participant->user_id)->where('game_room_id', '!=', $room->id)
            ->whereNotNull('disconnected_at')->whereNull('replaced_at')->where('is_active', true)->get();
        foreach ($others as $other) {
            if ($this->actions->replaceAway($other->room, $other, fn () => true)) {
                $this->tell($other->room, $other, 'replaced');
            }
        }
    }

    /** Everyone at the table refetches the seats. */
    private function tell(GameRoom $room, GameParticipant $seat, string $change): void
    {
        foreach ($room->participants()->whereNotNull('user_id')->pluck('user_id') as $userId) {
            RoomSeatsChanged::dispatch((int) $userId, $room->public_id, $seat->seat, $change);
        }
    }

    private function window(): int
    {
        return (int) $this->settings->get('presence.return_seconds');
    }

    private function key(GameRoom $room, GameParticipant $participant): string
    {
        return "presence:{$room->id}:{$participant->id}";
    }

    private function store(): Repository
    {
        return Cache::store(config('gameplay.state_store'));
    }
}
