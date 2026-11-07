<?php

namespace App\Platform\Levels;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "level.updated" on the player's channel after XP is added, so open tabs move the progress bar and,
 * after a level-up, open the popup.
 */
class LevelUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $userId,
        public readonly int $level,
        public readonly int $xp,
        public readonly int $gained,
        public readonly int $pending,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'level.updated';
    }

    /**
     * @return array{level: int, xp: int, gained: int, pending: int}
     */
    public function broadcastWith(): array
    {
        return ['level' => $this->level, 'xp' => $this->xp, 'gained' => $this->gained, 'pending' => $this->pending];
    }
}
