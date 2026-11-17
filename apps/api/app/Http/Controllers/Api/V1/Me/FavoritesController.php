<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Favorites\Favorites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The player's favourite games and slots (the ♥ on catalogue cards).
 */
class FavoritesController extends Controller
{
    /** Newest first: `kind` is `game` or `slot`. */
    public function index(Request $request, Favorites $favorites): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            /** @var list<array{kind: 'game'|'slot', slug: string}> */
            'data' => $favorites->of($user),
        ]);
    }

    /** Adds a favourite; adding it again changes nothing. 404 for a game or slot the catalogue doesn't show. */
    public function store(Request $request, string $kind, string $slug, Favorites $favorites): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $favorites->add($user, $kind, $slug);

        return $this->index($request, $favorites);
    }

    /** Removes a favourite; removing one that isn't there changes nothing. */
    public function destroy(Request $request, string $kind, string $slug, Favorites $favorites): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $favorites->remove($user, $kind, $slug);

        return $this->index($request, $favorites);
    }
}
