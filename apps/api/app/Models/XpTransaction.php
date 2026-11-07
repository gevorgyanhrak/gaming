<?php

namespace App\Models;

use App\Enums\XpSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One entry of a player's XP ledger (ADR 0018). Written only by App\Platform\Levels\Levels.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property XpSource $source
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property int $level_before
 * @property int $level_after
 * @property string $idempotency_key
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'amount', 'source', 'reference_type', 'reference_id', 'level_before', 'level_after', 'idempotency_key'])]
class XpTransaction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => XpSource::class,
            'amount' => 'integer',
            'level_before' => 'integer',
            'level_after' => 'integer',
        ];
    }
}
