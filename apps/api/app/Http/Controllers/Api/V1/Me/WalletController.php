<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\FishkaTransactionResource;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    /**
     * The signed-in player's Fishkas: balance, totals, the entry fee, and whether the "out of
     * Fishkas" bonus can be taken now.
     */
    public function show(Request $request, FishkaWallets $wallets, Settings $settings): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->summary($user, $wallets, $settings)]);
    }

    /**
     * The Fishka ledger, newest first (paginated).
     */
    public function transactions(Request $request, FishkaWallets $wallets): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $wallets->wallet($user);

        return FishkaTransactionResource::collection(
            $user->fishkaTransactions()->latest('id')->paginate(20),
        );
    }

    /**
     * Take the "out of Fishkas" bonus: only while the balance can't pay a game's entry fee, and not
     * again before the cooldown has passed (422 with code "fishka_bonus_unavailable" otherwise).
     */
    public function refill(Request $request, FishkaWallets $wallets, Settings $settings): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $wallets->refill($user);

        return response()->json(['data' => $this->summary($user, $wallets, $settings)], 201);
    }

    /**
     * @return array{balance: int, total_earned: int, total_spent: int, entry_fee: int, refill: array{available: bool, available_at: string|null, amount: int}}
     */
    private function summary(User $user, FishkaWallets $wallets, Settings $settings): array
    {
        $wallet = $wallets->wallet($user)->refresh();
        $refill = $wallets->refillStatus($user);

        return [
            'balance' => $wallet->balance,
            'total_earned' => $wallet->total_earned,
            'total_spent' => $wallet->total_spent,
            'entry_fee' => (int) $settings->get('fishka.entry_fee'),
            'refill' => [
                'available' => $refill['available'],
                /** @var string|null */
                'available_at' => $refill['available_at']?->toIso8601String(),
                'amount' => $refill['amount'],
            ],
        ];
    }
}
