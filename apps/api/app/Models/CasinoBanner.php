<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A promotion banner on the casino home, managed by administrators. Shown while visible and inside its optional
 * date window. Its link is a path in the app ("/games/poker") or an https address.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string|null $cta
 * @property string|null $image_path
 * @property string|null $link
 * @property bool $is_visible
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $sort_order
 */
#[Fillable(['title', 'description', 'cta', 'image_path', 'link', 'is_visible', 'starts_at', 'ends_at', 'sort_order'])]
#[ObservedBy(AdminChangeObserver::class)]
class CasinoBanner extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'description', 'cta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The banners players see now, in the admin's order.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeShowing(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_visible', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
