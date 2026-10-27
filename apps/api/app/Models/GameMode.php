<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Database\Factories\GameModeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $game_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $min_players
 * @property int $max_players
 * @property array<string, mixed>|null $config
 * @property bool $is_enabled
 * @property bool $matchmaking_enabled
 * @property bool $private_rooms_enabled
 * @property bool $bots_allowed
 * @property int $sort_order
 */
#[Fillable([
    'slug', 'name', 'description', 'min_players', 'max_players', 'config', 'is_enabled',
    'matchmaking_enabled', 'private_rooms_enabled', 'bots_allowed', 'sort_order',
])]
#[ObservedBy(AdminChangeObserver::class)]
class GameMode extends Model
{
    /** @use HasFactory<GameModeFactory> */
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'min_players' => 'integer',
            'max_players' => 'integer',
            'is_enabled' => 'boolean',
            'matchmaking_enabled' => 'boolean',
            'private_rooms_enabled' => 'boolean',
            'bots_allowed' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('is_enabled', true)->orderBy('sort_order')->orderBy('id');
    }
}
