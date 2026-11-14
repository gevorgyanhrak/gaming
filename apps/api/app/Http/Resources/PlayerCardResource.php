<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The player popup (PlayerCard::for()).
 *
 * @property array<string, mixed> $resource
 */
class PlayerCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        return [
            /** @var string */
            'username' => $c['username'],
            /** @var string */
            'name' => $c['name'],
            /** @var string|null */
            'avatar_url' => $c['avatar_url'],
            /** @var int */
            'level' => $c['level'],
            /** @var int XP at which the current level began. */
            'level_xp' => $c['level_xp'],
            /** @var int */
            'xp' => $c['xp'],
            /** @var int|null XP the next level needs (null at the top level). */
            'next_level_xp' => $c['next_level_xp'],
            /** @var int Fishkas (play money). */
            'balance' => $c['balance'],
            /** @var int */
            'played' => $c['played'],
            /** @var int */
            'wins' => $c['wins'],
            /** @var int */
            'losses' => $c['losses'],
            /** @var int|null Wins out of games played, in percent. */
            'win_rate' => $c['win_rate'],
            /** @var array{amount: int, game: array{slug: string, name: string}|null}|null */
            'biggest_win' => $c['biggest_win'],
            /** @var array{slug: string, name: string}|null The game played most. */
            'favourite_game' => $c['favourite_game'],
            /** @var int Wins in a row (best current streak across games). */
            'streak' => $c['streak'],
            /** @var bool Seen in the last few minutes. */
            'online' => $c['online'],
            /** @var array{slug: string, name: string}|null The game they're at now. */
            'playing' => $c['playing'],
            /** @var list<array{key: string, game: array{slug: string, name: string}|null, period: string|null, value: int|null, earned_at: string}> The latest achievements. */
            'achievements' => $c['achievements'],
        ];
    }
}
