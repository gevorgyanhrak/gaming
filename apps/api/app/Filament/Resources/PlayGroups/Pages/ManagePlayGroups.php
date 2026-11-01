<?php

namespace App\Filament\Resources\PlayGroups\Pages;

use App\Filament\Resources\PlayGroups\PlayGroupResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePlayGroups extends ManageRecords
{
    protected static string $resource = PlayGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
