<?php

namespace App\Filament\Resources\BotProfiles\Pages;

use App\Filament\Resources\BotProfiles\BotProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBotProfile extends EditRecord
{
    protected static string $resource = BotProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
