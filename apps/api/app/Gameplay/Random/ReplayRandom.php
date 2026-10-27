<?php

namespace App\Gameplay\Random;

use LogicException;

/**
 * Replays numbers recorded by RecordingRandom, in order.
 */
final class ReplayRandom implements RandomSource
{
    private int $position = 0;

    /**
     * @param  list<int>  $draws
     */
    public function __construct(private readonly array $draws) {}

    public function int(int $min, int $max): int
    {
        $value = $this->draws[$this->position++] ?? throw new LogicException('Replay needs more random numbers than were recorded.');

        if ($value < $min || $value > $max) {
            throw new LogicException("Recorded random number {$value} is outside [{$min}, {$max}]: the engine changed since this game was played.");
        }

        return $value;
    }
}
