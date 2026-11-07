<?php

namespace App\Http\Resources;

use App\Enums\RoomStatus;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Platform\Levels\Levels;
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
        $levels = app(Levels::class);

        return [
            'id' => $this->public_id,
            'game' => ['slug' => $this->game->slug, 'name' => $this->game->name],
            'mode' => ['id' => $this->mode->id, 'slug' => $this->mode->slug, 'name' => $this->mode->name],
            'type' => $this->type->value,
            'status' => $this->status->value,
            /** @var string|null */
            'invite_code' => $isParticipant && $this->status === RoomStatus::Waiting ? $this->invite_code : null,
            'seats' => $this->seats,
            /** The play group the table belongs to (null for tables from before groups). */
            'group' => $this->group === null ? null : new PlayGroupResource($this->group),
            /** What each player bets (stake games; 0 for poker). */
            'stake' => $this->stake,
            'participants' => $this->participants->map(fn (GameParticipant $p) => [
                'seat' => $p->seat,
                'is_bot' => $p->isBot(),
                /**
                 * The level a bot plays at; null for people.
                 *
                 * @var 'easy'|'medium'|'hard'|null
                 */
                'bot_difficulty' => $p->bot_difficulty?->value,
                'name' => $p->isBot() ? $p->bot?->name : ($p->user?->profile->display_name ?? $p->user?->username),
                /** @var string|null */
                'username' => $p->user?->username,
                /** @var string|null */
                'avatar_url' => $p->isBot() ? $p->bot?->avatarUrl() : $p->user?->profile->avatarUrl(),
                /** @var int The player's level (a bot's is fixed). */
                'level' => ($p->isBot() ? $p->bot?->level : $p->user?->level) ?? 1,
                /** @var string|null The avatar frame a person's level unlocked; bots have none. */
                'frame' => $p->isBot() || $p->user === null ? null : $levels->looks($p->user->level)['frame'],
                /** @var string|null */
                'result' => $p->result?->value,
                /** @var int|null Points the result is worth (Nardi: 2 for a mars, a double win). */
                'score' => $p->score,
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
