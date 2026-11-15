<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Slots\SlotWalletRequest;
use App\Models\SlotProvider;
use App\Platform\Slots\SlotAdapters;
use App\Platform\Slots\SlotWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The wallet endpoint a slot provider's server calls (never a browser). Signed: X-Slot-Timestamp (unix seconds,
 * within 5 minutes) and X-Slot-Signature = hex HMAC-SHA256 of "{timestamp}.{raw body}" with the provider's secret.
 * Answers 200 with {status, error, balance, tx_id}; a bad or stale signature is 401 and touches nothing.
 */
class SlotWalletController extends Controller
{
    private const MAX_SKEW = 300;

    public function __invoke(string $provider, Request $request, SlotAdapters $adapters, SlotWallet $wallet): JsonResponse
    {
        $model = SlotProvider::query()->where('slug', $provider)->first();
        $secret = $adapters->for($provider)?->secret();
        $timestamp = (string) $request->header('X-Slot-Timestamp', '');
        $signature = (string) $request->header('X-Slot-Signature', '');

        $valid = $model !== null && $secret !== null && $secret !== ''
            && ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= self::MAX_SKEW
            && hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret), $signature);
        if (! $valid) {
            return response()->json(['status' => 'refused', 'error' => 'bad_signature'], 401);
        }

        /** @var array{action: string, session: string, tx_id?: string|null, round_id?: string|null, amount?: int|null, reference_tx_id?: string|null} $call */
        $call = app(SlotWalletRequest::class)->validated();

        return response()->json($wallet->handle($model, $call));
    }
}
