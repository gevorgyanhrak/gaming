<?php

namespace App\Platform\Slots;

use RuntimeException;

/** A wallet call the rules refuse; the reason goes back to the provider and into slot_transactions. */
final class SlotRefused extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("Slot wallet call refused: {$reason}");
    }
}
