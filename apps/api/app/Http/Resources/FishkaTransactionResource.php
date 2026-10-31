<?php

namespace App\Http\Resources;

use App\Models\FishkaTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FishkaTransaction
 */
class FishkaTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            /** @var string|null */
            'description' => $this->description,
            /** @var string|null */
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
