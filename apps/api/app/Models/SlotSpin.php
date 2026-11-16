<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One house slot round (ADR 0024): the bet, the win and the round as dealt, keyed by the player's spin key.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $slot_game_id
 * @property int $slot_session_id
 * @property string $spin_key
 * @property int $bet
 * @property int $win
 * @property array<string, mixed> $result
 * @property-read SlotGame $game
 */
class SlotSpin extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $spin) {
            $spin->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bet' => 'integer', 'win' => 'integer', 'result' => 'array'];
    }

    /**
     * @return BelongsTo<SlotGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(SlotGame::class, 'slot_game_id');
    }
}
