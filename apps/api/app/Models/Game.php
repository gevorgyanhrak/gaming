<?php

namespace App\Models;

use App\Enums\BotFallbackMode;
use App\Observers\AdminChangeObserver;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property bool $bots_enabled
 * @property BotFallbackMode $bot_fallback_mode
 * @property int|null $bot_fallback_timeout_seconds
 */
#[Fillable([
    'slug', 'name', 'description', 'rules', 'icon_path', 'thumbnail_path',
    'min_players', 'max_players', 'is_enabled', 'is_visible', 'sort_order',
    'bots_enabled', 'bot_fallback_mode', 'bot_fallback_timeout_seconds',
])]
#[ObservedBy(AdminChangeObserver::class)]
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
            'bots_enabled' => 'boolean',
            'bot_fallback_mode' => BotFallbackMode::class,
            'bot_fallback_timeout_seconds' => 'integer',
        ];
    }

    /**
     * @return HasMany<GameMode, $this>
     */
    public function modes(): HasMany
    {
        return $this->hasMany(GameMode::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<BotGameProfile, $this>
     */
    public function botProfiles(): HasMany
    {
        return $this->hasMany(BotGameProfile::class);
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
