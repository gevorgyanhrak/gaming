<?php

namespace App\Filament\Resources\CasinoBanners\Pages;

use App\Filament\Resources\CasinoBanners\CasinoBannerResource;
use App\Filament\Support\HandlesTranslations;
use Filament\Resources\Pages\EditRecord;

class EditCasinoBanner extends EditRecord
{
    use HandlesTranslations;

    protected static string $resource = CasinoBannerResource::class;
}
