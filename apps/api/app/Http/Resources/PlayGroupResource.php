<?php

namespace App\Http\Resources;

use App\Models\PlayGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlayGroup
 */
class PlayGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            /**
             * "chips": poker (the minimum bet is the big blind; players bring their Fishkas as chips);
             * "stake": each player bets within min_bet–max_bet.
             *
             * @var 'chips'|'stake'
             */
            'kind' => $this->kind->value,
            'position' => $this->position,
            /** Needed to enter; never charged. */
            'min_balance' => $this->min_balance,
            'min_bet' => $this->min_bet,
            /** @var int|null */
            'max_bet' => $this->max_bet,
        ];
    }
}
