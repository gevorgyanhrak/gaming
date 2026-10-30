<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Transition;
use App\Gameplay\Random\RandomSource;

/**
 * Optional for games with a break between rounds in which nobody acts (poker: the result of a hand
 * stays on the table before the next deal). While pauseMs() isn't null and no seat is to act, the
 * platform waits that long, then calls resume(). The step is recorded in the move log and replayed
 * like a move; no turn clock runs and no bot acts during the pause.
 */
interface PausesBetweenRounds
{
    /**
     * How long the current pause lasts, or null when the game isn't paused.
     *
     * @param  array<string, mixed>  $state
     */
    public function pauseMs(array $state): ?int;

    /**
     * End the pause: start the next round.
     *
     * @param  array<string, mixed>  $state
     */
    public function resume(array $state, RandomSource $random): Transition;
}
