<?php

namespace App\Gameplay\Rooms;

use App\Models\GameRoom;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Live room state and per-room locks, in the "game" store (Redis with noeviction in production).
 * The store is a cache of the move log: anything missing is rebuilt by RoomStates.
 */
class RoomStateStore
{
    private const LOCK_SECONDS = 10;

    private const WAIT_SECONDS = 5;

    public function get(GameRoom $room): ?RoomState
    {
        $data = $this->store()->get($this->key($room));

        return is_array($data) ? RoomState::fromArray($data) : null;
    }

    public function put(GameRoom $room, RoomState $state): void
    {
        $this->store()->forever($this->key($room), $state->toArray());
    }

    public function forget(GameRoom $room): void
    {
        $this->store()->forget($this->key($room));
    }

    /**
     * Run $callback while holding the room's lock, so changes to one room happen one at a time.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function locked(GameRoom $room, Closure $callback): mixed
    {
        $store = $this->store()->getStore();

        if (! $store instanceof LockProvider) {
            throw new LogicException('The game state store must support locks (use Redis).');
        }

        return $store->lock("room-lock:{$room->id}", self::LOCK_SECONDS)->block(self::WAIT_SECONDS, $callback);
    }

    private function key(GameRoom $room): string
    {
        return "room-state:{$room->id}";
    }

    private function store(): Repository
    {
        return Cache::store(config('gameplay.state_store'));
    }
}
