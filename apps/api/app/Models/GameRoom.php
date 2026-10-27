<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $game_id
 * @property int $game_mode_id
 * @property RoomType $type
 * @property RoomStatus $status
 * @property string|null $invite_code
 * @property int|null $created_by
 * @property int $seats
 * @property bool $has_bot
 * @property array<string, mixed>|null $options
 * @property array{remaining: list<int>, since: int}|null $clock
 * @property int $state_version
 * @property string|null $end_reason
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property-read Game $game
 * @property-read GameMode $mode
 * @property-read Collection<int, GameParticipant> $participants
 */
#[Fillable(['game_id', 'game_mode_id', 'type', 'status', 'invite_code', 'created_by', 'seats', 'has_bot', 'options'])]
class GameRoom extends Model
{
    protected static function booted(): void
    {
        static::creating(function (GameRoom $room) {
            $room->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'status' => RoomStatus::class,
            'seats' => 'integer',
            'has_bot' => 'boolean',
            'options' => 'array',
            'clock' => 'array',
            'state_version' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<GameMode, $this>
     */
    public function mode(): BelongsTo
    {
        return $this->belongsTo(GameMode::class, 'game_mode_id');
    }

    /**
     * @return HasMany<GameParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(GameParticipant::class)->orderBy('seat');
    }

    /**
     * @return HasMany<GameMove, $this>
     */
    public function moves(): HasMany
    {
        return $this->hasMany(GameMove::class)->orderBy('seq');
    }

    public function isActive(): bool
    {
        return $this->status === RoomStatus::Active;
    }

    public function participantFor(User $user): ?GameParticipant
    {
        return $this->participants->firstWhere('user_id', $user->id);
    }

    public function turnSeconds(): int
    {
        return (int) ($this->options['turn_seconds'] ?? config('gameplay.default_turn_seconds'));
    }
}
