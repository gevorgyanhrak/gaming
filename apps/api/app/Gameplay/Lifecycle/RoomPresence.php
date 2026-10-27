<?php

namespace App\Gameplay\Lifecycle;

use App\Enums\RoomStatus;
use App\Gameplay\Actions\GameActionService;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Platform\Settings\Settings;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Whether players are still at their game. The game page reports every few seconds; a player who
 * hasn't been seen for the admin-set `matchmaking.abandon_after_seconds` (0 = never) has left, and
 * loses (or, at a bigger table, is taken out while the others play on).
 */
class RoomPresence
{
    public function __construct(
        private readonly GameActionService $actions,
        private readonly Settings $settings,
    ) {}

    public function seen(GameRoom $room, GameParticipant $participant): void
    {
        $this->store()->put($this->key($room, $participant), now()->getTimestamp(), 3600);
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

    /**
     * End the game for players who left. Run every few seconds by the scheduler.
     */
    public function sweep(): void
    {
        $after = (int) $this->settings->get('matchmaking.abandon_after_seconds');
        if ($after <= 0) {
            return;
        }
        $cutoff = now()->getTimestamp() - $after;

        $rooms = GameRoom::query()->where('status', RoomStatus::Active)->with(['game', 'participants'])->get();
        foreach ($rooms as $room) {
            foreach ($room->participants as $participant) {
                if ($participant->user_id === null || ! $participant->is_active) {
                    continue;
                }
                $last = $this->store()->get($this->key($room, $participant));
                if (! is_numeric($last)) {
                    // Unknown (e.g. the store was flushed): count from now rather than punish.
                    $this->seen($room, $participant);
                } elseif ((int) $last < $cutoff) {
                    $this->actions->abandon($room, $participant);
                }
            }
        }
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
