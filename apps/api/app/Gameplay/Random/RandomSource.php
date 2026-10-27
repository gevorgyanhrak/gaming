<?php

namespace App\Gameplay\Random;

/**
 * The only source of randomness engines may use (dice, shuffles, bot choices).
 */
interface RandomSource
{
    /** A uniformly random integer in [$min, $max]. */
    public function int(int $min, int $max): int;
}
