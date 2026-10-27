<?php

namespace App\Filament\Resources\BotProfiles\Pages;

use App\Filament\Resources\BotProfiles\BotProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBotProfiles extends ListRecords
{
    protected static string $resource = BotProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
