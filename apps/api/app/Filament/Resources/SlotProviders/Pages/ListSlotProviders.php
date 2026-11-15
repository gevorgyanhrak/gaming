<?php

namespace App\Filament\Resources\SlotProviders\Pages;

use App\Filament\Resources\SlotProviders\SlotProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotProviders extends ListRecords
{
    protected static string $resource = SlotProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
