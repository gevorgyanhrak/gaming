<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of a room's append-only move log. seq 0 is the game start.
 *
 * @property int $id
 * @property int $game_room_id
 * @property int $seq
 * @property int|null $participant_id
 * @property string $type
 * @property array<string, mixed>|null $payload
 * @property list<int>|null $rng
 * @property string|null $client_action_id
 * @property Carbon $created_at
 */
#[Fillable(['seq', 'participant_id', 'type', 'payload', 'rng', 'client_action_id'])]
class GameMove extends Model
{
    public const UPDATED_AT = null;

    public const START = 'start';

    public const RESIGN = 'resign';

    public const TIMEOUT = 'timeout';

    /** The player left the game page and didn't come back in time. */
    public const ABANDON = 'abandon';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'payload' => 'array',
            'rng' => 'array',
        ];
    }

    /**
     * @return BelongsTo<GameParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(GameParticipant::class);
    }
}
