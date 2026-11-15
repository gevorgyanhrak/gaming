<?php

namespace App\Http\Resources;

use App\Models\CasinoBanner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin CasinoBanner
 */
class CasinoBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            /** @var string|null */
            'description' => $this->description ?: null,
            /** @var string|null The button's label; the app's own "Open" when empty. */
            'cta' => $this->cta ?: null,
            /** @var string|null */
            'image_url' => $this->image_path === null ? null : Storage::disk('public')->url($this->image_path),
            /** @var string|null A path in the app ("/games/poker") or an https address. */
            'link' => $this->link,
        ];
    }
}
