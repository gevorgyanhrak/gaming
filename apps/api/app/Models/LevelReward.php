<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * What reaching a level gives (ADR 0018): Fishkas, items from App\Platform\Levels\LevelItems, and whether
 * it's a milestone (a bigger celebration). Edited in the admin panel; applies to future level-ups.
 *
 * @property int $level
 * @property int $fishkas
 * @property list<array{kind: string, key: string}>|null $items
 * @property bool $is_milestone
 */
#[Fillable(['level', 'fishkas', 'items', 'is_milestone'])]
#[ObservedBy(AdminChangeObserver::class)]
class LevelReward extends Model
{
    protected $primaryKey = 'level';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'fishkas' => 'integer',
            'items' => 'array',
            'is_milestone' => 'boolean',
        ];
    }
}
