<?php

namespace App\Gameplay\Matchmaking;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A matchmaking moment on time (AI fills a table, a table starts), on the game queue: the 5-second sweep is
 * too coarse for a 7 / 10 second countdown. Doing it twice is harmless (Matchmaker::step is idempotent).
 */
class MatchmakingTick implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly string $pool) {}

    public function handle(Matchmaker $matchmaker): void
    {
        $matchmaker->step($this->pool);
    }
}
