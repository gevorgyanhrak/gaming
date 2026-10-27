<?php

namespace App\Filament\Support;

/**
 * For create/edit pages of models using spatie/laravel-translatable (see TranslationData).
 */
trait HandlesTranslations
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return TranslationData::fill($this->getRecord(), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TranslationData::clean($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslationData::clean($data);
    }
}
