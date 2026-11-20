<?php

namespace App\Platform\Payments;

use App\Models\PaymentOrder;
use Illuminate\Http\Request;

/**
 * A bank or payment provider (ADR 0027). None ships: the owner adds one per contract (its keys in the environment,
 * never in settings), registers it under the `payment-providers` container tag and selects it in admin settings.
 */
interface PaymentProvider
{
    /** Its name in `payments.provider` and in the webhook URL. */
    public function id(): string;

    /** Starts paying for the order with the provider: where to send the player, and the provider's reference. */
    public function checkout(PaymentOrder $order): Checkout;

    /**
     * Reads a call from the provider. Must verify its signature; null when it isn't genuine or isn't about a payment.
     */
    public function parseWebhook(Request $request): ?ProviderEvent;
}
