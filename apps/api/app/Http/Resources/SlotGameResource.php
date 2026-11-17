<?php

namespace App\Http\Resources;

use App\Models\SlotGame;
use App\Platform\Slots\Engine\EngineSlots;
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
        $engine = $this->provider->slug === EngineSlots::PROVIDER;
        $playPath = $house ? "/play/{$this->provider_game_id}/" : null;

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'provider' => $this->provider->name,
            /**
             * Who made the game (engine slots: its original maker, e.g. "Novomatic"); null when not known.
             *
             * @var string|null
             */
            'studio' => $this->studio,
            /**
             * How the web app plays it: house (its own client page), engine (our slot screen), external (launch).
             *
             * @var 'house'|'engine'|'external'
             */
            'kind' => $house ? 'house' : ($engine ? 'engine' : 'external'),
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
        ];
    }
}
