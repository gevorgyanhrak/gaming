<?php

namespace App\Gameplay\Contracts;

use App\Gameplay\Engine\Transition;
use App\Gameplay\Random\RandomSource;

/**
 * Optional for games with more than two players: a player who leaves is taken out and the game
 * goes on for the others, as long as another person is still playing. Without it (or once only bots
 * would be left), leaving ends the game.
 */
interface HandlesDepartures
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function depart(array $state, int $seat, RandomSource $random): Transition;
}
