<?php

namespace App\Http\Resources;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Game
 */
class GameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            /** @var string|null */
            'icon_url' => $this->publicUrl($this->icon_path),
            /** @var string|null */
            'thumbnail_url' => $this->publicUrl($this->thumbnail_path),
            'min_players' => $this->min_players,
            'max_players' => $this->max_players,
            'is_playable' => $this->is_enabled,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
