<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Filament\Support\HandlesTranslations;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentPage extends EditRecord
{
    use HandlesTranslations;

    protected static string $resource = ContentPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
