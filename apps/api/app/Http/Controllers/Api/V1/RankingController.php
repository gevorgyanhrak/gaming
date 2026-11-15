<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RankingRowResource;
use App\Models\Game;
use App\Platform\Rankings\Rankings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Rankings (public, real data): a game's Top 10 by points or Fishkas, and the platform's highest levels / most Fishkas. */
class RankingController extends Controller
{
    /** The game's Top 10; `kind` says what it ranks by ('points' or 'chips'), null when the game has no board. */
    public function game(string $slug, Rankings $rankings): JsonResponse
    {
        $game = Game::query()->visible()->where('slug', $slug)->firstOrFail();
        $board = $rankings->forGame($game);

        return response()->json([
            /** @var 'points'|'chips'|null */
            'kind' => $board['kind'],
            'data' => RankingRowResource::collection($board['rows']),
        ]);
    }

    /** The platform's Top 10 people by level ('level') or by Fishkas ('chips'). */
    public function platform(string $board, Rankings $rankings): AnonymousResourceCollection
    {
        return RankingRowResource::collection(match ($board) {
            'level' => $rankings->highestLevels(),
            'chips' => $rankings->mostChips(),
            default => abort(404),
        });
    }
}
