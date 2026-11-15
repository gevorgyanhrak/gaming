<?php

namespace App\Filament\Resources\CasinoBanners\Pages;

use App\Filament\Resources\CasinoBanners\CasinoBannerResource;
use App\Filament\Support\HandlesTranslations;
use App\Models\CasinoBanner;
use Filament\Resources\Pages\CreateRecord;

class CreateCasinoBanner extends CreateRecord
{
    use HandlesTranslations {
        mutateFormDataBeforeCreate as cleanTranslations;
    }

    protected static string $resource = CasinoBannerResource::class;

    /**
     * A new banner goes last on the page until an administrator moves it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$this->cleanTranslations($data), 'sort_order' => (int) CasinoBanner::query()->max('sort_order') + 10];
    }
}
