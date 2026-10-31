<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's Fishka balance. Changed only through FishkaWallets, under a row lock.
 *
 * @property int $user_id
 * @property int $balance
 * @property int $total_earned
 * @property int $total_spent
 * @property Carbon|null $daily_bonus_on
 * @property Carbon|null $refilled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['user_id', 'balance', 'total_earned', 'total_spent', 'daily_bonus_on', 'refilled_at'])]
class FishkaWallet extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'total_earned' => 'integer',
            'total_spent' => 'integer',
            'daily_bonus_on' => 'date',
            'refilled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
