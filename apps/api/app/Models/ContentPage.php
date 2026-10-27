<?php

namespace App\Models;

use App\Observers\AdminChangeObserver;
use Database\Factories\ContentPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * Admin-editable page such as the privacy policy or terms of service.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property bool $is_published
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'title', 'body', 'is_published'])]
#[ObservedBy(AdminChangeObserver::class)]
class ContentPage extends Model
{
    /** @use HasFactory<ContentPageFactory> */
    use HasFactory, HasTranslations;

    public const TERMS = 'terms';

    public const PRIVACY = 'privacy';

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
