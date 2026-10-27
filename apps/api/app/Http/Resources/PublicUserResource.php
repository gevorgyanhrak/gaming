<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What anyone may see about a player. Contains no contact details.
 *
 * @mixin User
 */
class PublicUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'username' => $this->username,
            /** @var string|null */
            'display_name' => $this->profile->display_name,
            /** @var string|null */
            'avatar_url' => $this->profile->avatarUrl(),
            /** @var string|null */
            'bio' => $this->profile->bio,
            /** @var string|null */
            'country' => $this->profile->country,
            'created_at' => $this->created_at->toIso8601String(),
            /** @var string|null */
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'stats' => GameStatResource::collection($this->whenLoaded('gameStats')),
        ];
    }
}
