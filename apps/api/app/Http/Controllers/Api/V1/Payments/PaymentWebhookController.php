<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Platform\Payments\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A payment provider's server-to-server call (ADR 0027). The provider verifies its own signature; an unknown
 * provider is 404, an unverified call 400. Repeats are harmless: an order is credited once.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, Payments $payments): JsonResponse
    {
        $handler = $payments->provider($provider) ?? abort(404);
        $event = $handler->parseWebhook($request) ?? abort(400);
        $order = $payments->apply($handler, $event);

        return response()->json(['ok' => $order !== null]);
    }
}
