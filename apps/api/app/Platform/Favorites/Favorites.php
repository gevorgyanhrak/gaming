<?php

namespace App\Platform\Favorites;

use App\Models\Favorite;
use App\Models\Game;
use App\Models\SlotGame;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A player's favourite games and slots. Adding and removing are idempotent (a repeated or racing tap leaves one
 * row, the unique keys see to it); only what the catalogue shows can be added.
 */
class Favorites
{
    public const KINDS = ['game', 'slot'];

    /**
     * Newest first, only what the catalogue still shows.
     *
     * @return list<array{kind: string, slug: string}>
     */
    public function of(User $user): array
    {
        return Favorite::query()->where('user_id', $user->id)
            ->with(['game' => fn ($q) => $q->visible(), 'slotGame' => fn ($q) => $q->offered()])
            ->latest('id')->get()
            ->map(fn (Favorite $f) => match (true) {
                $f->game !== null => ['kind' => 'game', 'slug' => $f->game->slug],
                $f->slotGame !== null => ['kind' => 'slot', 'slug' => $f->slotGame->slug],
                default => null,
            })
            ->filter()->values()->all();
    }

    /** @throws ModelNotFoundException when the catalogue has no such game or slot */
    public function add(User $user, string $kind, string $slug): void
    {
        Favorite::query()->insertOrIgnore([
            'user_id' => $user->id,
            ...$this->column($kind, $slug),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function remove(User $user, string $kind, string $slug): void
    {
        try {
            $column = $this->column($kind, $slug, onlyShown: false);
        } catch (ModelNotFoundException) {
            return;
        }
        Favorite::query()->where('user_id', $user->id)->where($column)->delete();
    }

    /** @return array<string, int> */
    private function column(string $kind, string $slug, bool $onlyShown = true): array
    {
        return $kind === 'game'
            ? ['game_id' => Game::query()->when($onlyShown, fn ($q) => $q->visible())->where('slug', $slug)->valueOrFail('id')]
            : ['slot_game_id' => SlotGame::query()->when($onlyShown, fn ($q) => $q->offered())->where('slug', $slug)->valueOrFail('id')];
    }
}
