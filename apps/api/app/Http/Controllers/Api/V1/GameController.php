<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameDetailResource;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    /**
     * List the games shown in the catalog.
     *
     * The catalog is a small, admin-curated set, so it is returned unpaginated.
     */
    public function index(): AnonymousResourceCollection
    {
        return GameResource::collection(Game::query()->visible()->ordered()->get());
    }

    /**
     * Show a game with its rules.
     */
    public function show(string $slug): GameDetailResource
    {
        return new GameDetailResource(
            Game::query()->visible()->where('slug', $slug)->firstOrFail(),
        );
    }
}
