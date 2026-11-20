<?php

namespace App\Gameplay\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone at a table disconnected, came back, or was replaced by AI (ADR 0028): each person at the table refetches
 * the room (the seats are on it). Sent on their private channels, like room updates.
 */
class RoomSeatsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  'disconnected'|'returned'|'replaced'  $change
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $roomId,
        public readonly int $seat,
        public readonly string $change,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'room.seats';
    }

    /** @return array{room: string, seat: int, change: string} */
    public function broadcastWith(): array
    {
        return ['room' => $this->roomId, 'seat' => $this->seat, 'change' => $this->change];
    }
}
