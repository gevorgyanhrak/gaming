<?php

namespace App\Filament\Resources\GameCategories\Pages;

use App\Filament\Resources\GameCategories\GameCategoryResource;
use App\Filament\Support\HandlesTranslations;
use App\Models\GameCategory;
use Filament\Resources\Pages\CreateRecord;

class CreateGameCategory extends CreateRecord
{
    use HandlesTranslations {
        mutateFormDataBeforeCreate as cleanTranslations;
    }

    protected static string $resource = GameCategoryResource::class;

    /**
     * A new category goes last on the page until an administrator moves it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$this->cleanTranslations($data), 'sort_order' => (int) GameCategory::query()->max('sort_order') + 10];
    }
}
