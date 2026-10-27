<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Enums\VerificationPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ContactChannel $channel
 * @property string $destination
 * @property VerificationPurpose $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
#[Fillable(['channel', 'destination', 'purpose', 'code_hash', 'expires_at'])]
class VerificationCode extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * Codes are useless a day after expiry; the scheduler deletes them (model:prune).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDay());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ContactChannel::class,
            'purpose' => VerificationPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
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
