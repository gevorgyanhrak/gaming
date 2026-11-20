<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A ranking row: place, the player (a person, or an AI player marked is_ai with no username), and the value ranked
 * by — points, Fishkas or level, as the board says — and, on a points board, the games played and won.
 *
 * @property array{rank: int, is_ai: bool, username: string|null, name: string, avatar_url: string|null, level: int, frame: string|null, value: int, played?: int|null, wins?: int|null} $resource
 */
class RankingRowResource extends JsonResource
{
    /**
     * @return array{rank: int, is_ai: bool, username: string|null, name: string, avatar_url: string|null, level: int, frame: string|null, value: int, played: int|null, wins: int|null}
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
            /**
             * Finished games behind a points board's score (null on the level / Fishkas boards).
             *
             * @var int|null
             */
            'played' => $this->resource['played'] ?? null,
            /** @var int|null */
            'wins' => $this->resource['wins'] ?? null,
        ];
    }
}
