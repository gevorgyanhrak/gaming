<?php

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;

/**
 * @mixin Game
 */
class GameDetailResource extends GameResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            // Paragraphs separated by blank lines.
            'rules' => $this->rules,
            'modes' => GameModeResource::collection($this->whenLoaded('modes')),
        ];
    }
}
