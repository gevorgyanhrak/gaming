<?php

namespace App\Platform\Payments;

final readonly class Checkout
{
    public function __construct(
        public string $redirectUrl,
        public string $reference,
    ) {}
}
