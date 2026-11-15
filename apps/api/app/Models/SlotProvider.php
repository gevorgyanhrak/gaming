<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A slot game provider (docs/research/casino-slots.md). It can be switched on only when the code has an adapter
 * for it (SlotAdapters) and its wallet secret is configured; its wallet calls are signed with that secret.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property bool $is_enabled
 * @property string|null $notes
 */
#[Fillable(['slug', 'name', 'is_enabled', 'notes'])]
#[ObservedBy(AdminChangeObserver::class)]
class SlotProvider extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /**
     * @return HasMany<SlotGame, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(SlotGame::class);
    }
}
