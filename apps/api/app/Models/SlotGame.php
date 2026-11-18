<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A provider's slot game as an administrator listed it: the provider's id for it, bet limits in Fishkas, and the
 * biggest win the wallet will pay for one round (max_multiplier × the round's bets).
 *
 * @property int $id
 * @property int $slot_provider_id
 * @property string $provider_game_id
 * @property string $slug
 * @property string $name
 * @property string|null $image_path
 * @property int $min_bet
 * @property int $max_bet
 * @property int $max_multiplier
 * @property bool $is_enabled
 * @property int $sort_order
 * @property-read SlotProvider $provider
 */
#[Fillable(['slot_provider_id', 'provider_game_id', 'slug', 'name', 'image_path', 'min_bet', 'max_bet', 'max_multiplier', 'is_enabled', 'sort_order'])]
#[ObservedBy(AdminChangeObserver::class)]
class SlotGame extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_bet' => 'integer',
            'max_bet' => 'integer',
            'max_multiplier' => 'integer',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SlotProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(SlotProvider::class, 'slot_provider_id');
    }

    /**
     * Games switched on whose provider is on (playability also needs the provider's adapter: SlotAdapters).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('is_enabled', true)
            ->whereHas('provider', fn (Builder $q) => $q->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
