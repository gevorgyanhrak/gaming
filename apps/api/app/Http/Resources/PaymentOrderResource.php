<?php

namespace App\Http\Resources;

use App\Models\PaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentOrder */
class PaymentOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            /** @var 'pending'|'processing'|'paid'|'failed'|'cancelled'|'expired'|'refunded' */
            'status' => $this->status->value,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'chips' => $this->chips,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
