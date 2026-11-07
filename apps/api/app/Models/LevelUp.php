<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A level a player reached, with the reward it gave (ADR 0018). Unclaimed ones are pending: the popup
 * shows them and collecting pays their Fishkas, once. Together they are the player's reward history.
 *
 * @property int $id
 * @property int $user_id
 * @property int $level
 * @property int $fishkas
 * @property list<array{kind: string, key: string}>|null $items
 * @property bool $is_milestone
 * @property int|null $fishka_transaction_id
 * @property Carbon|null $claimed_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'level', 'fishkas', 'items', 'is_milestone', 'fishka_transaction_id', 'claimed_at'])]
class LevelUp extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'fishkas' => 'integer',
            'items' => 'array',
            'is_milestone' => 'boolean',
            'claimed_at' => 'datetime',
        ];
    }
}
