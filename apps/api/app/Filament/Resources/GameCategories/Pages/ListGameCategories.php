<?php

namespace App\Filament\Resources\GameCategories\Pages;

use App\Filament\Resources\GameCategories\GameCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGameCategories extends ListRecords
{
    protected static string $resource = GameCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
