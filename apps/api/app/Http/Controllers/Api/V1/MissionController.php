<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\Missions\Missions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Daily missions and weekly challenges (Platform/Missions): progress from real games, rewards claimed once. */
class MissionController extends Controller
{
    /** Today's missions and this week's challenges, with progress and what was claimed. */
    public function index(Request $request, Missions $missions): JsonResponse
    {
        return response()->json([
            /** @var list<array{key: string, period: 'day'|'week', kind: 'play'|'win'|'variety', target: int, progress: int, reward: int, claimed: bool, ends_at: string}> */
            'data' => $missions->for($request->user()),
        ]);
    }

    /** Collects a finished mission's Fishkas (once); 422 when it isn't finished. Answers with the missions again. */
    public function claim(Request $request, string $key, Missions $missions): JsonResponse
    {
        $missions->claim($request->user(), $key);

        return response()->json([
            /** @var list<array{key: string, period: 'day'|'week', kind: 'play'|'win'|'variety', target: int, progress: int, reward: int, claimed: bool, ends_at: string}> */
            'data' => $missions->for($request->user()),
        ]);
    }
}
