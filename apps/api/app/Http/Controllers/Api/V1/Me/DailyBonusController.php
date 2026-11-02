<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyBonusController extends Controller
{
    /**
     * Today's daily bonus: the streak's day (1–5), what each day gives, and whether today's was taken.
     */
    public function show(Request $request, FishkaWallets $wallets): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = $wallets->dailyStatus($user);

        return response()->json([
            'data' => [
                /** @var int Today's day of the streak, 1–5. */
                'day' => $status['day'],
                /** @var bool Whether today's bonus was already collected. */
                'claimed' => $status['claimed'],
                /** @var int What today's bonus gives. */
                'amount' => $status['amount'],
                /** @var int What tomorrow's gives if the streak goes on. */
                'tomorrow' => $status['tomorrow'],
                /** @var list<array{day: int, amount: int}> */
                'days' => $status['days'],
            ],
        ]);
    }

    /**
     * Collect today's daily bonus: 201 with `collected` (the Fishkas added) and the new status; a
     * repeat the same day collects nothing (200, `collected` 0).
     */
    public function store(Request $request, FishkaWallets $wallets): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $entry = $wallets->claimDaily($user);
        // Only a bonus this request added counts (a repeat may find today's existing ledger entry).
        if ($entry !== null && ! $entry->wasRecentlyCreated) {
            $entry = null;
        }
        $status = $wallets->dailyStatus($user);
        $data = [
            /** @var int */
            'day' => $status['day'],
            /** @var bool */
            'claimed' => $status['claimed'],
            /** @var int */
            'amount' => $status['amount'],
            /** @var int */
            'tomorrow' => $status['tomorrow'],
            /** @var list<array{day: int, amount: int}> */
            'days' => $status['days'],
            /** @var int The Fishkas this request added (0 for a repeat). */
            'collected' => $entry === null ? 0 : $entry->amount,
        ];

        if ($entry === null) {
            return response()->json(['data' => $data]);
        }

        return response()->json(['data' => $data], 201);
    }
}
