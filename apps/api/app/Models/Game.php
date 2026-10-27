<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property string $rules
 * @property string|null $icon_path
 * @property string|null $thumbnail_path
 * @property int $min_players
 * @property int $max_players
 * @property bool $is_enabled
 * @property bool $is_visible
 * @property int $sort_order
 */
#[Fillable([
    'slug', 'name', 'description', 'rules', 'icon_path', 'thumbnail_path',
    'min_players', 'max_players', 'is_enabled', 'is_visible', 'sort_order',
])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'rules'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_players' => 'integer',
            'max_players' => 'integer',
            'is_enabled' => 'boolean',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
