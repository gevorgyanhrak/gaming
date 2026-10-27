<?php

namespace App\Gameplay\Random;

/** Cryptographically secure randomness (CSPRNG), used for real games. */
final class SecureRandom implements RandomSource
{
    public function int(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
