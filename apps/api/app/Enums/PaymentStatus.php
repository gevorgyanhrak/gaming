<?php

namespace App\Enums;

/** A payment order's state (ADR 0027). Paid, failed, cancelled, expired and refunded are final. */
enum PaymentStatus: string
{
    case Pending = 'pending';       // created; the player hasn't paid yet
    case Processing = 'processing'; // the provider is working on it
    case Paid = 'paid';             // confirmed by the provider; Fishkas credited
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function isFinal(): bool
    {
        return ! in_array($this, [self::Pending, self::Processing], true);
    }
}
