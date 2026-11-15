<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One wallet call from a slot provider (bet, win or rollback), accepted ("ok") or refused with an error code.
 * Unique per provider and provider transaction id: a repeated call gets the stored answer and moves nothing.
 *
 * @property int $id
 * @property int $slot_provider_id
 * @property string $provider_tx_id
 * @property int|null $slot_session_id
 * @property int|null $user_id
 * @property string|null $round_id
 * @property string $type
 * @property int $amount
 * @property string $status
 * @property string|null $error
 * @property int|null $balance_after
 * @property string|null $reference_tx_id
 * @property-read SlotProvider $provider
 * @property-read SlotSession|null $session
 * @property-read User|null $user
 */
class SlotTransaction extends Model
{
    public const OK = 'ok';

    public const REFUSED = 'refused';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_after' => 'integer'];
    }

    /**
     * @return BelongsTo<SlotProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(SlotProvider::class, 'slot_provider_id');
    }

    /**
     * @return BelongsTo<SlotSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SlotSession::class, 'slot_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
