<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SlotGameResource;
use App\Models\SlotGame;
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

    /** Opens the game: the address to show it at, for this player's new session. */
    public function launch(string $slug, Request $request, SlotSessions $sessions): JsonResponse
    {
        $game = SlotGame::query()->with('provider')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $sessions->open($request->user(), $game, app()->getLocale())]);
    }
}
