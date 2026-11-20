<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A chip package players can buy once a payment provider is connected (ADR 0027). Admin-managed.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $price_cents
 * @property string $currency
 * @property int $chips
 * @property int $bonus_chips
 * @property bool $is_recommended
 * @property bool $is_enabled
 * @property int $sort_order
 */
#[ObservedBy(AdminChangeObserver::class)]
#[Fillable(['slug', 'name', 'price_cents', 'currency', 'chips', 'bonus_chips', 'is_recommended', 'is_enabled', 'sort_order'])]
class ChipPackage extends Model
{
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'chips' => 'integer',
            'bonus_chips' => 'integer',
            'is_recommended' => 'boolean',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeOnSale(Builder $query): void
    {
        $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('price_cents');
    }

    /** Everything the package gives. */
    public function totalChips(): int
    {
        return $this->chips + $this->bonus_chips;
    }
}
