<?php

namespace App\Filament\Resources\ChipPackages\Pages;

use App\Filament\Resources\ChipPackages\ChipPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChipPackage extends EditRecord
{
    protected static string $resource = ChipPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
