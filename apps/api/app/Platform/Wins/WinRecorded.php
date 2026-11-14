<?php

namespace App\Platform\Wins;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "win.recorded" on the public "wins" channel: a person just won Fishkas in a game (GameWins). Every open home
 * page adds it to Big Wins live; a win marked `celebrate` is celebrated on every open page.
 */
class WinRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $win  GameWins::present()
     */
    public function __construct(public readonly array $win) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('wins')];
    }

    public function broadcastAs(): string
    {
        return 'win.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->win;
    }
}
