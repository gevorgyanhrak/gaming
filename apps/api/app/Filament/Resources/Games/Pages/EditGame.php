<?php

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Support\HandlesTranslations;
use Filament\Resources\Pages\EditRecord;

class EditGame extends EditRecord
{
    use HandlesTranslations;

    protected static string $resource = GameResource::class;
}
