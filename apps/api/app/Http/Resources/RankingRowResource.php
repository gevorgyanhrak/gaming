<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ranking row: place, the player (a person, or an AI player marked is_ai with no username), and the value ranked
 * by — points, Fishkas or level, as the board says.
 *
 * @property array{rank: int, is_ai: bool, username: string|null, name: string, avatar_url: string|null, level: int, frame: string|null, value: int} $resource
 */
class RankingRowResource extends JsonResource
{
    /**
     * @return array{rank: int, is_ai: bool, username: string|null, name: string, avatar_url: string|null, level: int, frame: string|null, value: int}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'rank' => $this->resource['rank'],
            /** @var bool */
            'is_ai' => $this->resource['is_ai'],
            /** @var string|null */
            'username' => $this->resource['username'],
            /** @var string */
            'name' => $this->resource['name'],
            /** @var string|null */
            'avatar_url' => $this->resource['avatar_url'],
            /** @var int */
            'level' => $this->resource['level'],
            /** @var string|null */
            'frame' => $this->resource['frame'],
            /** @var int */
            'value' => $this->resource['value'],
        ];
    }
}
