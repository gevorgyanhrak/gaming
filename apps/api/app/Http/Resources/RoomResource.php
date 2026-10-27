<?php

namespace App\Http\Resources;

use App\Enums\RoomStatus;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameRoom
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isParticipant = $viewer !== null && $this->participants->contains('user_id', $viewer->id);

        return [
            'id' => $this->public_id,
            'game' => ['slug' => $this->game->slug, 'name' => $this->game->name],
            'mode' => ['slug' => $this->mode->slug, 'name' => $this->mode->name],
            'type' => $this->type->value,
            'status' => $this->status->value,
            /** @var string|null */
            'invite_code' => $isParticipant && $this->status === RoomStatus::Waiting ? $this->invite_code : null,
            'seats' => $this->seats,
            'participants' => $this->participants->map(fn (GameParticipant $p) => [
                'seat' => $p->seat,
                'is_bot' => $p->isBot(),
                'name' => $p->isBot() ? $p->bot?->name : ($p->user?->profile->display_name ?? $p->user?->username),
                /** @var string|null */
                'username' => $p->user?->username,
                /** @var string|null */
                'avatar_url' => $p->isBot() ? $p->bot?->avatarUrl() : $p->user?->profile->avatarUrl(),
                /** @var string|null */
                'result' => $p->result?->value,
            ])->values()->all(),
            /** @var string|null */
            'end_reason' => $this->end_reason,
            'created_at' => $this->created_at->toIso8601String(),
            /** @var string|null */
            'started_at' => $this->started_at?->toIso8601String(),
            /** @var string|null */
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
