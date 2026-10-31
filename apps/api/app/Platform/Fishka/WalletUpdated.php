<?php

namespace App\Platform\Fishka;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "wallet.updated" on the owner's channel after every change, so every open tab shows the new balance.
 */
class WalletUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly int $userId, public readonly int $balance, public readonly int $change) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'wallet.updated';
    }

    /**
     * @return array{balance: int, change: int}
     */
    public function broadcastWith(): array
    {
        return ['balance' => $this->balance, 'change' => $this->change];
    }
}
