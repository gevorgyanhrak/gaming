<?php

namespace App\Platform\Payments;

use App\Enums\PaymentStatus;

/** What a verified provider call says about one order. */
final readonly class ProviderEvent
{
    public function __construct(
        public string $reference,
        public PaymentStatus $status,
        public ?string $reason = null,
    ) {}
}
