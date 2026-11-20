<?php

namespace App\Filament\Resources\ChipPackages\Pages;

use App\Filament\Resources\ChipPackages\ChipPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChipPackages extends ListRecords
{
    protected static string $resource = ChipPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
