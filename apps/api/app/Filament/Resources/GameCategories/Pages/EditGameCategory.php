<?php

namespace App\Filament\Resources\GameCategories\Pages;

use App\Filament\Resources\GameCategories\GameCategoryResource;
use App\Filament\Support\HandlesTranslations;
use Filament\Resources\Pages\EditRecord;

class EditGameCategory extends EditRecord
{
    use HandlesTranslations;

    protected static string $resource = GameCategoryResource::class;
}
