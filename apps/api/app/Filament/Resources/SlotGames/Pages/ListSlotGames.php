<?php

namespace App\Filament\Resources\SlotGames\Pages;

use App\Filament\Resources\SlotGames\SlotGameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotGames extends ListRecords
{
    protected static string $resource = SlotGameResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
