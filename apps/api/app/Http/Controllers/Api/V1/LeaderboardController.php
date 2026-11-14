<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaderboardRowResource;
use App\Models\Game;
use App\Platform\Wins\Leaderboards;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Leaderboards (public): who won the most Fishkas today, this week or this month — overall or in one game. */
class LeaderboardController extends Controller
{
    public function index(Request $request, Leaderboards $boards): AnonymousResourceCollection
    {
        $data = $request->validate([
            'period' => ['sometimes', Rule::in(Leaderboards::PERIODS)],
            'game' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);
        $game = isset($data['game']) ? Game::query()->visible()->where('slug', $data['game'])->firstOrFail() : null;

        return LeaderboardRowResource::collection($boards->top($data['period'] ?? 'week', $game));
    }
}
