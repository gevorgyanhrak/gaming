<?php

namespace App\Filament\Resources\SlotGames\Pages;

use App\Filament\Resources\SlotGames\SlotGameResource;
use App\Filament\Support\HandlesTranslations;
use Filament\Resources\Pages\CreateRecord;

class CreateSlotGame extends CreateRecord
{
    use HandlesTranslations;

    protected static string $resource = SlotGameResource::class;
}
