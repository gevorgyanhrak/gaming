<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WinResource;
use App\Platform\Wins\GameWins;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Big Wins live: the biggest wins of the last week (public; new ones arrive on the "wins" channel). */
class WinController extends Controller
{
    public function index(GameWins $wins): AnonymousResourceCollection
    {
        return WinResource::collection($wins->biggest());
    }
}
