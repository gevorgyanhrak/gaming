<?php

namespace App\Gameplay\Random;

/**
 * Records every number drawn, so the move log can replay the exact same game.
 */
final class RecordingRandom implements RandomSource
{
    /** @var list<int> */
    private array $draws = [];

    public function __construct(private readonly RandomSource $source = new SecureRandom) {}

    public function int(int $min, int $max): int
    {
        return $this->draws[] = $this->source->int($min, $max);
    }

    /**
     * @return list<int>
     */
    public function draws(): array
    {
        return $this->draws;
    }
}
