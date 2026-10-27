<?php

namespace App\Models;

use App\Enums\BotDifficulty;
use App\Observers\AdminChangeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bot_profile_id
 * @property int $game_id
 * @property BotDifficulty $difficulty
 * @property bool $is_enabled
 */
#[Fillable(['game_id', 'difficulty', 'is_enabled'])]
#[ObservedBy(AdminChangeObserver::class)]
class BotGameProfile extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => BotDifficulty::class,
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BotProfile, $this>
     */
    public function botProfile(): BelongsTo
    {
        return $this->belongsTo(BotProfile::class);
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
