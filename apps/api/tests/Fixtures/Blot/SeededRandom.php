<?php

namespace Tests\Fixtures\Blot;

use App\Gameplay\Random\RandomSource;

/** A repeatable random source (xorshift) for simulations in tests. */
final class SeededRandom implements RandomSource
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed !== 0 ? $seed : 0x2545F491;
    }

    public function int(int $min, int $max): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        return $min + $this->state % ($max - $min + 1);
    }
}
