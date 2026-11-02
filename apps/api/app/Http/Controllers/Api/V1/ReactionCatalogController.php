<?php

namespace App\Http\Controllers\Api\V1;

use App\Gameplay\Reactions\ReactionCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReactionCatalogController extends Controller
{
    /**
     * What players can send each other at a table: emojis by category (with search words in every UI
     * language) and predefined message keys by category (the texts are the client's translations).
     */
    public function __invoke(ReactionCatalog $catalog): JsonResponse
    {
        return response()->json([
            'data' => [
                /** @var list<array{slug: string, emojis: list<array{emoji: string, keywords: string}>}> */
                'emoji' => $catalog->emojiCategories(),
                /** @var list<array{slug: string, keys: list<string>}> */
                'messages' => $catalog->messageCategories(),
            ],
        ])->setPublic()->setMaxAge(3600);
    }
}
