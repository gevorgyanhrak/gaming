<?php

namespace App\Filament\Resources\LevelRewards\Pages;

use App\Filament\Resources\LevelRewards\LevelRewardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLevelRewards extends ManageRecords
{
    protected static string $resource = LevelRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a level'),
        ];
    }
}
