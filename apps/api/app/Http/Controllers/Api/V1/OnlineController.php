<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Platform\Presence\OnlinePlayers;
use Illuminate\Http\JsonResponse;

class OnlineController extends Controller
{
    /**
     * People connected right now (the counter at the top of the app): unique signed-in users on the "online"
     * presence channel. Null when Reverb can't be asked.
     */
    public function __invoke(OnlinePlayers $online): JsonResponse
    {
        return response()->json(['data' => [
            /** @var int|null */
            'online' => $online->count(),
        ]]);
    }
}
