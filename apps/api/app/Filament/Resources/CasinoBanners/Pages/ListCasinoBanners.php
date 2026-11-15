<?php

namespace App\Filament\Resources\CasinoBanners\Pages;

use App\Filament\Resources\CasinoBanners\CasinoBannerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCasinoBanners extends ListRecords
{
    protected static string $resource = CasinoBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
