<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Filament\Support\HandlesTranslations;
use Filament\Resources\Pages\CreateRecord;

class CreateContentPage extends CreateRecord
{
    use HandlesTranslations;

    protected static string $resource = ContentPageResource::class;
}
