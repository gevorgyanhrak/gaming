<?php

namespace App\Platform\Fishka;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The "out of Fishkas" bonus isn't available now: the balance is still enough to play, or the last
 * one was too recent. Rendered as 422 with code "fishka_bonus_unavailable".
 */
class FishkaBonusUnavailable extends RuntimeException
{
    public function __construct(public readonly ?Carbon $availableAt)
    {
        parent::__construct($availableAt === null
            ? __('platform.fishka.bonus_not_needed')
            : __('platform.fishka.bonus_cooldown', ['time' => $availableAt->toIso8601String()]));
    }
}
