<?php

namespace App\Http\Resources;

use App\Enums\RoomStatus;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Platform\Levels\Levels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            /**
             * What this game brought you once your part in it is over: Fishkas (net: bet or fee and winnings)
             * and XP. Null while you still play, and for others.
             *
             * @var array{fishkas: int, xp: int}|null
             */
            'my_rewards' => $this->rewardsFor($viewer?->id),
            'seats' => $this->seats,
            /** The play group the table belongs to (null for tables from before groups). */
            'group' => $this->group === null ? null : new PlayGroupResource($this->group),
            /** What each player bets (stake games; 0 for poker). */
            'stake' => $this->stake,
            /** @var list<array{seat: int, is_bot: bool, name: string|null, username: string|null, avatar_url: string|null, level: int, frame: string|null, result: string|null, score: int|null}> */
            'participants' => $this->participants->map(fn (GameParticipant $p) => [
                'seat' => $p->seat,
                'is_bot' => $p->isBot(),
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

    /**
     * @return array{fishkas: int, xp: int}|null
     */
    private function rewardsFor(?int $userId): ?array
    {
        $seat = $userId === null ? null : $this->participants->firstWhere('user_id', $userId);
        if ($seat === null || ($seat->is_active && $this->status === RoomStatus::Active)) {
            return null;
        }

        return [
            'fishkas' => (int) DB::table('fishka_transactions')->where('user_id', $userId)
                ->where('reference_type', 'game_room')->where('reference_id', $this->public_id)->sum('amount'),
            'xp' => (int) DB::table('xp_transactions')->where('user_id', $userId)
                ->where('reference_type', 'game_room')->where('reference_id', $this->public_id)->sum('amount'),
        ];
    }
}
