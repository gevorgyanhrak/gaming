<?php

namespace App\Models;

use App\Enums\PlayGroupKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A play group (level): the Fishka balance needed to sit down and the bets its tables play for.
 * Managed in the admin panel; read through PlayGroups.
 *
 * @property int $id
 * @property PlayGroupKind $kind
 * @property string $slug
 * @property int $position
 * @property int $min_balance Needed to enter; never charged.
 * @property int $min_bet
 * @property int|null $max_bet
 * @property bool $is_enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['kind', 'slug', 'position', 'min_balance', 'min_bet', 'max_bet', 'is_enabled'])]
class PlayGroup extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PlayGroupKind::class,
            'position' => 'integer',
            'min_balance' => 'integer',
            'min_bet' => 'integer',
            'max_bet' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
