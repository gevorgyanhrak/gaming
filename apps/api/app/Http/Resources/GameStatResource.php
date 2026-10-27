<?php

namespace App\Http\Resources;

use App\Models\UserGameStat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserGameStat
 */
class GameStatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'game' => ['slug' => $this->game->slug, 'name' => $this->game->name],
            'played' => $this->played,
            'wins' => $this->wins,
            'losses' => $this->losses,
            'draws' => $this->draws,
            'abandoned' => $this->abandoned,
            'current_streak' => $this->current_streak,
            'best_streak' => $this->best_streak,
            /** @var string|null */
            'last_played_at' => $this->last_played_at?->toIso8601String(),
        ];
    }
}
