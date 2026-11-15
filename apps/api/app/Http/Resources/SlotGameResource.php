<?php

namespace App\Http\Resources;

use App\Models\SlotGame;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin SlotGame
 */
class SlotGameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'provider' => $this->provider->name,
            /** @var string|null */
            'image_url' => $this->image_path === null ? null : Storage::disk('public')->url($this->image_path),
            'min_bet' => $this->min_bet,
            'max_bet' => $this->max_bet,
        ];
    }
}
