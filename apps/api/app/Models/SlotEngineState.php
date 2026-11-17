<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A player's state in one engine slot (ADR 0026): what the engine needs for the next spin, and the round the free
 * games and cascade falls in progress belong to.
 *
 * @property int $id
 * @property int $user_id
 * @property int $slot_game_id
 * @property array<string, mixed> $state
 * @property string|null $round_key
 * @property int $round_bet
 */
#[Fillable(['user_id', 'slot_game_id', 'state', 'round_key', 'round_bet'])]
class SlotEngineState extends Model
{
    protected function casts(): array
    {
        return ['state' => 'array', 'round_bet' => 'integer'];
    }
}
