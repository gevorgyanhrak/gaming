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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $slug
 * @property int|null $game_category_id
 * @property-read GameCategory|null $category
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
 * @property bool $maintenance shown, but not playable: players see maintenance_message
 * @property string|null $maintenance_message
 * @property Carbon|null $archived_at removed from the platform; its history is kept
 */
#[Fillable([
    'slug', 'game_category_id', 'name', 'description', 'rules', 'icon_path', 'thumbnail_path',
    'min_players', 'max_players', 'is_enabled', 'is_visible', 'sort_order',
    'bots_enabled', 'bot_fallback_mode', 'bot_fallback_timeout_seconds',
    'maintenance', 'maintenance_message', 'archived_at',
])]
#[ObservedBy(AdminChangeObserver::class)]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'rules', 'maintenance_message'];

    /**
     * @return BelongsTo<GameCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'game_category_id');
    }

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
            'maintenance' => 'boolean',
            'archived_at' => 'datetime',
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
        $query->where('is_visible', true)->whereNull('archived_at');
    }

    /** Game Management's status: archived, disabled, maintenance or enabled. */
    public function status(): string
    {
        return match (true) {
            $this->archived_at !== null => 'archived',
            ! $this->is_enabled => 'disabled',
            $this->maintenance => 'maintenance',
            default => 'enabled',
        };
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
