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
        $open = $spins->open($request->user(), $game);

        return response()->json(['data' => [
            'slug' => $game->slug,
            'name' => $game->name,
            /** @var string|null */
            'studio' => $game->studio,
            'reels' => (int) ($game->meta['width'] ?? 5),
            'rows' => (int) ($game->meta['height'] ?? 3),
            'symbols' => (int) ($game->meta['symbols'] ?? 0),
            /**
             * The game's features as the engine flags them (free_games, cascade, wilds, scatters, …).
             *
             * @var list<string>
             */
            'features' => array_values((array) ($game->meta['features'] ?? [])),
            'balance' => $open['balance'],
            /** @var list<int> */
            'bets' => $open['bets'],
            'lines' => $open['lines'],
            /**
             * What a spin costs at bet 1 when the lines are fixed (the game's own price, e.g. 25 for 243 ways).
             *
             * @var int
             */
            'unit' => $open['unit'],
            /**
             * The screen where the player left it (`grid`: reels as columns of symbol numbers) and the game's extras.
             *
             * @var array<string, mixed>
             */
            'state' => $open['state'],
            /** In free games or cascade falls: the next spin costs nothing. */
            'free' => $open['free'],
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
