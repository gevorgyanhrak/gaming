<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Fishka\FreeGifts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The free gift every few hours and the daily lucky wheel (FreeGifts).
 */
class FreeGiftsController extends Controller
{
    public function show(Request $request, FreeGifts $gifts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->status($gifts, $user)]);
    }

    /** Collect the gift: `collected` is what was added (0 if it was already taken). */
    public function claim(Request $request, FreeGifts $gifts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $collected = $gifts->claimGift($user);

        $status = $this->status($gifts, $user);

        return response()->json(['data' => [
            /** @var int */
            'collected' => $collected,
            'gift' => $status['gift'],
            'wheel' => $status['wheel'],
        ]]);
    }

    /** Spin today's wheel: where it stops (`index` into `wheel.prizes`) and the Fishkas won. */
    public function spin(Request $request, FreeGifts $gifts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $prize = $gifts->spin($user);

        $status = $this->status($gifts, $user);

        return response()->json(['data' => [
            /** @var int */
            'index' => $prize['index'],
            /** @var int */
            'amount' => $prize['amount'],
            'gift' => $status['gift'],
            'wheel' => $status['wheel'],
        ]]);
    }

    /**
     * @return array{gift: array{amount: int, available: bool, next_at: string}, wheel: array{available: bool, next_at: string, prizes: list<int>}}
     */
    private function status(FreeGifts $gifts, User $user): array
    {
        $gift = $gifts->gift($user);
        $wheel = $gifts->wheel($user);

        return [
            'gift' => [
                'amount' => $gift['amount'],
                'available' => $gift['available'],
                'next_at' => now()->setTimestamp($gift['next_at'])->toIso8601String(),
            ],
            'wheel' => [
                'available' => $wheel['available'],
                'next_at' => now()->setTimestamp($wheel['next_at'])->toIso8601String(),
                /** @var list<int> The wheel's slices, in order. */
                'prizes' => $wheel['prizes'],
            ],
        ];
    }
}
