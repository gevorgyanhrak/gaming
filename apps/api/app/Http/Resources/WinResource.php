<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person's win in the Big Wins feed (GameWins::present()).
 *
 * @property array{id: int, amount: int, won_at: string, celebrate: bool, player: array{username: string, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}} $resource
 */
class WinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $win = $this->resource;

        return [
            /** @var int */
            'id' => $win['id'],
            /** @var int Fishkas won: the player's net for that game, from the ledger. */
            'amount' => $win['amount'],
            /** @var string */
            'won_at' => $win['won_at'],
            /** @var bool At or above the celebration amount (admin setting wins.celebrate_at). */
            'celebrate' => $win['celebrate'],
            /** @var array{username: string, name: string, avatar_url: string|null, level: int} */
            'player' => $win['player'],
            /** @var array{slug: string, name: string} */
            'game' => $win['game'],
        ];
    }
}
