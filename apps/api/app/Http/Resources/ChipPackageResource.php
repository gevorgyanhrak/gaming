<?php

namespace App\Http\Resources;

use App\Models\ChipPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChipPackage */
class ChipPackageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            /** @var int Price in the currency's minor unit (cents). */
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'chips' => $this->chips,
            'bonus_chips' => $this->bonus_chips,
            /** @var int */
            'total_chips' => $this->totalChips(),
            'is_recommended' => $this->is_recommended,
        ];
    }
}
