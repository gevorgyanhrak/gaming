<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class OnlineController extends Controller
{
    /**
     * Players active in the last 5 minutes (from last activity, cached briefly).
     */
    public function __invoke(): JsonResponse
    {
        $count = Cache::remember('stats:online', 30, fn () => User::query()->where('last_active_at', '>=', now()->subMinutes(5))->count());

        return response()->json(['data' => ['online' => $count]]);
    }
}
