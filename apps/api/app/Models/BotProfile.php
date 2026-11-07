<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Database\Factories\BotProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A bot opponent. Bots are always shown with a "Bot" badge to players.
 *
 * @property int $id
 * @property string $name
 * @property string|null $avatar_path
 * @property int $level The level shown next to the bot (fixed; bots earn no XP).
 * @property bool $is_enabled
 */
#[Fillable(['name', 'avatar_path', 'level', 'is_enabled'])]
#[ObservedBy(AdminChangeObserver::class)]
class BotProfile extends Model
{
    /** @use HasFactory<BotProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'level' => 'integer',
        ];
    }

    /**
     * @return HasMany<BotGameProfile, $this>
     */
    public function gameProfiles(): HasMany
    {
        return $this->hasMany(BotGameProfile::class);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null ? null : Storage::disk('public')->url($this->avatar_path);
    }
}
