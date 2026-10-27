<?php

namespace App\Models;

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seat in a room, taken by a player or a bot.
 *
 * @property int $id
 * @property int $game_room_id
 * @property int $seat
 * @property int|null $user_id
 * @property int|null $bot_profile_id
 * @property BotDifficulty|null $bot_difficulty
 * @property bool $is_active
 * @property ParticipantResult|null $result
 * @property int|null $score
 * @property-read GameRoom $room
 * @property-read User|null $user
 * @property-read BotProfile|null $bot
 */
#[Fillable(['seat', 'user_id', 'bot_profile_id', 'bot_difficulty', 'is_active'])]
class GameParticipant extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seat' => 'integer',
            'bot_difficulty' => BotDifficulty::class,
            'is_active' => 'boolean',
            'result' => ParticipantResult::class,
            'score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GameRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class, 'game_room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<BotProfile, $this>
     */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(BotProfile::class, 'bot_profile_id');
    }

    public function isBot(): bool
    {
        return $this->bot_profile_id !== null;
    }
}
