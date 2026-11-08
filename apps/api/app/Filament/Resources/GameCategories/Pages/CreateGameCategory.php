<?php

namespace App\Filament\Resources\GameCategories\Pages;

use App\Filament\Resources\GameCategories\GameCategoryResource;
use App\Filament\Support\HandlesTranslations;
use Filament\Resources\Pages\CreateRecord;

class CreateGameCategory extends CreateRecord
{
    use HandlesTranslations;

    protected static string $resource = GameCategoryResource::class;
}
