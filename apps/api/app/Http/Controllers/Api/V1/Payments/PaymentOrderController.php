<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CreatePaymentOrderRequest;
use App\Http\Resources\PaymentOrderResource;
use App\Models\ChipPackage;
use App\Models\PaymentOrder;
use App\Platform\Payments\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentOrderController extends Controller
{
    /**
     * Starts buying a chip package: an order and where to pay it (the provider's page). 422 `payments_unavailable`
     * while no provider is connected. Fishkas arrive only when the provider confirms the payment.
     */
    public function store(CreatePaymentOrderRequest $request, Payments $payments): JsonResponse
    {
        $package = ChipPackage::query()->where('slug', $request->string('package'))->firstOrFail();
        // PaymentsUnavailable renders as 422 `payments_unavailable` (bootstrap/app.php).
        $result = $payments->order($request->user(), $package, (string) $request->string('key'));
        $order = $result['order'];

        return response()->json([
            'data' => new PaymentOrderResource($order),
            /** @var string|null */
            'redirect_url' => $result['redirect_url'],
        ], 201); // the same answer for a repeat with the same key (idempotent)
    }

    /** One of your orders (e.g. after coming back from the provider's page). */
    public function show(Request $request, string $id): PaymentOrderResource
    {
        $order = PaymentOrder::query()->where('public_id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        return new PaymentOrderResource($order);
    }
}
