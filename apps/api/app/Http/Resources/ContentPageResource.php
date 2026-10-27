<?php

namespace App\Http\Resources;

use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContentPage
 */
class ContentPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            // Paragraphs separated by blank lines.
            'body' => $this->body,
            /** @var string */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
