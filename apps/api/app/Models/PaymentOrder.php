<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A player's order for a chip package (ADR 0027): what was bought as it was then, the provider handling it, and its
 * status. Changed only by App\Platform\Payments\Payments.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $chip_package_id
 * @property int $price_cents
 * @property string $currency
 * @property int $chips
 * @property string $provider
 * @property string|null $provider_reference
 * @property PaymentStatus $status
 * @property string $client_key
 * @property string|null $failure_reason
 * @property int|null $fishka_transaction_id
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 */
#[Fillable(['public_id', 'user_id', 'chip_package_id', 'price_cents', 'currency', 'chips', 'provider', 'provider_reference', 'status', 'client_key', 'failure_reason', 'fishka_transaction_id', 'paid_at'])]
class PaymentOrder extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'price_cents' => 'integer',
            'chips' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ChipPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ChipPackage::class, 'chip_package_id');
    }
}
