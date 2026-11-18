<?php

namespace App\Http\Resources;

use App\Models\SlotGame;
use App\Platform\Slots\House\CreditedHouseSlot;
use App\Platform\Slots\House\HouseSlots;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin SlotGame
 */
class SlotGameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $house = $this->provider->slug === HouseSlots::PROVIDER;
        $playPath = $house ? "/play/{$this->provider_game_id}/" : null;

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'provider' => $this->provider->name,
            /** @var string|null */
            'image_url' => $this->image_path !== null
                ? Storage::disk('public')->url($this->image_path)
                : ($house ? "{$playPath}tile.webp" : null),
            'min_bet' => $this->min_bet,
            'max_bet' => $this->max_bet,
            /**
             * Where the web app shows a house slot (ADR 0024); null for an outside provider (opened by launch).
             *
             * @var string|null
             */
            'play_path' => $playPath,
            /**
             * Where this game's art and maths come from, when the game says so itself (else the provider's credit).
             *
             * @var string|null
             */
            'credit' => $house && ($engine = app(HouseSlots::class)->for($this->provider_game_id)) instanceof CreditedHouseSlot ? $engine->credit() : null,
        ];
    }
}
