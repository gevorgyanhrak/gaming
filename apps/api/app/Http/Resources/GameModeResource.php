<?php

namespace App\Http\Resources;

use App\Models\GameMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameMode
 */
class GameModeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            /** @var string|null */
            'description' => $this->description,
            'min_players' => $this->min_players,
            'max_players' => $this->max_players,
        ];
    }
}
