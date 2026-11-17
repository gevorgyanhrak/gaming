<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's favourite game or slot (exactly one of the two).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $game_id
 * @property int|null $slot_game_id
 */
#[Fillable(['user_id', 'game_id', 'slot_game_id'])]
class Favorite extends Model
{
    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<SlotGame, $this> */
    public function slotGame(): BelongsTo
    {
        return $this->belongsTo(SlotGame::class);
    }
}
