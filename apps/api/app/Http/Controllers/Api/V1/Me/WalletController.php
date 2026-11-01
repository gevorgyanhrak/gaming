<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Gameplay\Rooms\RoomStakes;
use App\Http\Controllers\Controller;
use App\Http\Resources\FishkaTransactionResource;
use App\Models\User;
use App\Platform\Fishka\FishkaBonusUnavailable;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    /**
     * The signed-in player's Fishkas: balance, totals, the entry fee, whether the "out of Fishkas"
     * bonus can be taken now, and the Fishkas at poker tables right now (`at_table`, not in the balance).
     */
    public function show(Request $request, FishkaWallets $wallets, Settings $settings, RoomStakes $stakes): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->summary($user, $wallets, $settings, $stakes)]);
    }

    /**
     * The Fishka ledger, newest first (paginated). `?room=<id>` keeps one game's entries (its bet or
     * buy-in and what it paid back), e.g. to show a game's result.
     */
    public function transactions(Request $request, FishkaWallets $wallets): AnonymousResourceCollection
    {
        $request->validate(['room' => ['nullable', 'string', 'max:64']]);
        /** @var User $user */
        $user = $request->user();
        $wallets->wallet($user);

        return FishkaTransactionResource::collection(
            $user->fishkaTransactions()
                ->when($request->string('room')->toString(), fn ($q, $room) => $q->where('reference_type', 'game_room')->where('reference_id', $room))
                ->latest('id')->paginate(20),
        );
    }

    /**
     * Take the "out of Fishkas" bonus: only while the balance can't pay a game's entry fee, and not
     * again before the cooldown has passed (422 with code "fishka_bonus_unavailable" otherwise).
     */
    public function refill(Request $request, FishkaWallets $wallets, Settings $settings, RoomStakes $stakes): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // Fishkas sitting at a poker table still count: no free bonus while you have chips in play.
        if ($stakes->atTable($user) > 0) {
            throw new FishkaBonusUnavailable(null);
        }
        $wallets->refill($user);

        return response()->json(['data' => $this->summary($user, $wallets, $settings, $stakes)], 201);
    }

    /**
     * @return array{balance: int, total_earned: int, total_spent: int, entry_fee: int, at_table: int, refill: array{available: bool, available_at: string|null, amount: int}}
     */
    private function summary(User $user, FishkaWallets $wallets, Settings $settings, RoomStakes $stakes): array
    {
        $wallet = $wallets->wallet($user)->refresh();
        $refill = $wallets->refillStatus($user);
        $atTable = $stakes->atTable($user);
        if ($atTable > 0) {
            $refill = ['available' => false, 'available_at' => null, 'amount' => $refill['amount']];
        }

        return [
            'balance' => $wallet->balance,
            'total_earned' => $wallet->total_earned,
            'total_spent' => $wallet->total_spent,
            'entry_fee' => (int) $settings->get('fishka.entry_fee'),
            /**
             * Fishkas at poker tables right now (not in the balance).
             *
             * @var int
             */
            'at_table' => $atTable,
            'refill' => [
                'available' => $refill['available'],
                /** @var string|null */
                'available_at' => $refill['available_at']?->toIso8601String(),
                'amount' => $refill['amount'],
            ],
        ];
    }
}
