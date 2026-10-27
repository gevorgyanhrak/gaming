<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * Creates the privacy policy and terms of service pages as unpublished drafts. Their text must be
 * written (and legally reviewed) in the admin panel before publishing; nothing is shown to players
 * until then. Non-destructive.
 */
class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        $draft = ['en' => 'Draft. Replace this text with the reviewed document before publishing.'];

        ContentPage::query()->firstOrCreate(['slug' => ContentPage::PRIVACY], [
            'title' => ['en' => 'Privacy policy', 'ru' => 'Политика конфиденциальности', 'hy' => 'Գաղտնիության քաղաքականություն'],
            'body' => $draft,
            'is_published' => false,
        ]);

        ContentPage::query()->firstOrCreate(['slug' => ContentPage::TERMS], [
            'title' => ['en' => 'Terms of service', 'ru' => 'Условия использования', 'hy' => 'Օգտագործման պայմաններ'],
            'body' => $draft,
            'is_published' => false,
        ]);
    }
}
