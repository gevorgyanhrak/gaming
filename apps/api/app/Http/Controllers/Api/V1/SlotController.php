<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlotGameResource;
use App\Models\SlotGame;
use App\Models\SlotSpin;
use App\Platform\Slots\SlotAdapters;
use App\Platform\Slots\SlotSessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Casino slots: the games players can open (on, with an enabled provider that has an adapter and its secret —
 * none until the owner connects a provider) and opening one.
 */
class SlotController extends Controller
{
    public function index(SlotAdapters $adapters): AnonymousResourceCollection
    {
        $ready = $adapters->readyProviders();
        $games = SlotGame::query()->offered()->with('provider')
            ->whereHas('provider', fn ($q) => $q->whereIn('slug', $ready))
            ->get();

        return SlotGameResource::collection($games);
    }

    /** The slots this player spun last (newest first, each once; only ones still offered). */
    public function recent(Request $request): AnonymousResourceCollection
    {
        $ids = SlotSpin::query()->where('user_id', $request->user()->id)
            ->selectRaw('slot_game_id, MAX(id) as last_id')->groupBy('slot_game_id')
            ->orderByDesc('last_id')->limit(12)->pluck('slot_game_id')->all();
        $games = SlotGame::query()->offered()->with('provider')->whereIn('id', $ids)->get()
            ->sortBy(fn (SlotGame $g) => array_search($g->id, $ids, true))->values();

        return SlotGameResource::collection($games);
    }

    /** Opens the game: the address to show it at, for this player's new session. */
    public function launch(string $slug, Request $request, SlotSessions $sessions): JsonResponse
    {
        $game = SlotGame::query()->with('provider')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $sessions->open($request->user(), $game, app()->getLocale())]);
    }
}
