<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A slot game opened for one player. The provider gets a one-off token (we keep only its hash) and names it in
 * every wallet call; the wallet accepts calls only for an open, unexpired session.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $slot_game_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $last_played_at
 * @property-read User $user
 * @property-read SlotGame $game
 */
class SlotSession extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            $session->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_played_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SlotGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(SlotGame::class, 'slot_game_id');
    }
}
