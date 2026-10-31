<?php

namespace App\Models;

use App\Enums\FishkaTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of a user's append-only Fishka ledger.
 *
 * @property int $id
 * @property int $user_id
 * @property FishkaTransactionType $type
 * @property int $amount
 * @property int $balance_before
 * @property int $balance_after
 * @property string|null $description
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property int|null $actor_id
 * @property string|null $reason
 * @property string|null $idempotency_key
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'type', 'amount', 'balance_before', 'balance_after', 'description', 'reference_type', 'reference_id', 'actor_id', 'reason', 'idempotency_key'])]
class FishkaTransaction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FishkaTransactionType::class,
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
