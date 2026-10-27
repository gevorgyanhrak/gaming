<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-player, per-game totals. Updated when a game finishes (GameFinisher).
 *
 * @property int $user_id
 * @property int $game_id
 * @property int $played
 * @property int $wins
 * @property int $losses
 * @property int $draws
 * @property int $abandoned
 * @property int $current_streak
 * @property int $best_streak
 * @property Carbon|null $last_played_at
 */
#[Fillable(['user_id', 'game_id'])]
class UserGameStat extends Model
{
    public $incrementing = false;

    /** Composite primary key; queries always filter by both columns. */
    protected $primaryKey = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_played_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
