<?php

namespace App\Http\Resources;

use App\Gameplay\Matchmaking\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Ticket $resource
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->botOffered ? 'bot_offered' : 'queued',
            'game_mode_id' => $this->resource->modeId,
            'joined_at' => now()->setTimestamp($this->resource->joinedAt)->toIso8601String(),
            /** @var string|null */
            'bot_offer_at' => $this->resource->botAt === null ? null : now()->setTimestamp($this->resource->botAt)->toIso8601String(),
        ];
    }
}
