<?php

namespace App\Platform\Payments;

use RuntimeException;

/** Buying is switched off, or no provider is connected (the normal state until the owner connects a bank). */
final class PaymentsUnavailable extends RuntimeException {}
