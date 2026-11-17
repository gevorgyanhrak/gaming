<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Slots\EngineSpinRequest;
use App\Models\SlotGame;
use App\Platform\Slots\Engine\EngineSpins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Engine slots (ADR 0026): open a game (balance, bets, lines, where the player left it) and spin. The engine decides
 * every screen and win; the browser sends only the bet, the lines and a one-time key. A refusal is 422 with a code
 * (insufficient_fishkas, slot_bet_limits, slot_game_off, …).
 */
class EngineSlotController extends Controller
{
    public function show(string $slug, Request $request, EngineSpins $spins): JsonResponse
    {
        $game = SlotGame::query()->with('provider')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => [
            'slug' => $game->slug,
            'name' => $game->name,
            'studio' => $game->studio,
            'reels' => (int) ($game->meta['width'] ?? 5),
            'rows' => (int) ($game->meta['height'] ?? 3),
            'symbols' => (int) ($game->meta['symbols'] ?? 0),
            'features' => (array) ($game->meta['features'] ?? []),
            ...$spins->open($request->user(), $game),
        ]]);
    }

    public function spin(string $slug, EngineSpinRequest $request, EngineSpins $spins): JsonResponse
    {
        $game = SlotGame::query()->with('provider')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $spins->spin(
            $request->user(),
            $game,
            $request->integer('bet'),
            $request->integer('lines'),
            $request->string('key')->toString(),
        )]);
    }
}
