<?php

namespace App\Gameplay\Engine;

/**
 * What an engine needs to start a game.
 */
final readonly class GameSetup
{
    /**
     * @param  int  $seats  Number of players; seats are numbered 0..seats-1.
     * @param  array<string, mixed>  $options  The game mode's options (time control, variant, ...).
     */
    public function __construct(
        public int $seats,
        public array $options = [],
    ) {}
}
