<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Slots\HouseSpinRequest;
use App\Models\SlotGame;
use App\Platform\Slots\House\HouseSpins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * House slots (ADR 0024): open a game (balance and bets) and spin. The server deals every round; the browser sends
 * only the bet and a one-time key. A refusal is 422 with a code (insufficient_fishkas, slot_bet_limits, …).
 */
class HouseSlotController extends Controller
{
    public function show(string $slug, Request $request, HouseSpins $spins): JsonResponse
    {
        $game = SlotGame::query()->with('provider')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => [
            'slug' => $game->slug,
            'name' => $game->name,
            ...$spins->open($request->user(), $game),
        ]]);
    }

    public function spin(string $slug, HouseSpinRequest $request, HouseSpins $spins): JsonResponse
    {
        $game = SlotGame::query()->with('provider')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $spins->spin(
            $request->user(),
            $game,
            $request->integer('bet'),
            $request->string('key')->toString(),
        )]);
    }
}
