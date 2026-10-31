<?php

namespace App\Platform\Fishka;

use RuntimeException;

/**
 * A charge the wallet can't cover. Rendered as 422 with code "insufficient_fishkas" so the client can
 * offer the "out of Fishkas" bonus.
 */
class InsufficientFishkas extends RuntimeException
{
    public function __construct(public readonly int $balance, public readonly int $needed)
    {
        parent::__construct(__('platform.fishka.insufficient', ['needed' => $needed]));
    }
}
