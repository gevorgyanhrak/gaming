<?php

namespace App\Http\Controllers\Api\V1;

use App\Gameplay\Reactions\ReactionCatalog;
use App\Http\Controllers\Controller;
use App\Platform\Levels\Levels;
use Illuminate\Http\JsonResponse;

class ReactionCatalogController extends Controller
{
    /**
     * What players can send each other at a table: emojis by category (with search words in every UI
     * language), predefined message keys by category (the texts are the client's translations), and the
     * exclusive emojis with the level that unlocks each (ADR 0018).
     */
    public function __invoke(ReactionCatalog $catalog, Levels $levels): JsonResponse
    {
        return response()->json([
            'data' => [
                /** @var list<array{slug: string, emojis: list<array{emoji: string, keywords: string}>}> */
                'emoji' => $catalog->emojiCategories(),
                /** @var list<array{slug: string, keys: list<string>}> */
                'messages' => $catalog->messageCategories(),
                /** @var list<array{emoji: string, level: int}> */
                'exclusive' => array_map(
                    fn (string $emoji, int $level) => ['emoji' => $emoji, 'level' => $level],
                    array_map('strval', array_keys($levels->emojiLevels())),
                    array_values($levels->emojiLevels()),
                ),
            ],
        ])->setPublic()->setMaxAge(3600);
    }
}
