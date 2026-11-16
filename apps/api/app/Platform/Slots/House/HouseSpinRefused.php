<?php

namespace App\Platform\Slots\House;

use RuntimeException;

/** A spin the server would not deal: the reason (an error code) and the balance as it stands. */
final class HouseSpinRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?int $balance = null)
    {
        parent::__construct($reason);
    }
}
