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
            /** @var array{slug: string, name: string, sort_order: int}|null The catalog shelf this game is on. */
            'category' => $this->category === null ? null : [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'sort_order' => $this->category->sort_order,
            ],
            'description' => $this->description,
            /** @var string|null */
            'icon_url' => $this->publicUrl($this->icon_path),
            /** @var string|null */
            'thumbnail_url' => $this->publicUrl($this->thumbnail_path),
            'min_players' => $this->min_players,
            'max_players' => $this->max_players,
            'is_playable' => $this->is_enabled,
            /** In maintenance (Game Management): shown, but no new games; the message says why. */
            'maintenance' => $this->maintenance,
            /** @var string|null */
            'maintenance_message' => $this->maintenance ? ($this->maintenance_message ?: __('platform.play.maintenance')) : null,
            /** Whether its page shows how many play it now (admin setting per game; off for Poker and Blackjack). */
            'show_online' => $this->show_online,
            /** @var int|null People (never AI) at its active tables now; null where the count isn't shown. */
            'playing_now' => $this->show_online ? (Game::playingNow()[$this->id] ?? 0) : null,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }
}
