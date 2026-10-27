<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Platform\Settings\Settings;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Public platform configuration for the SPA.
     *
     * Only settings marked public in the SettingsCatalog may appear here.
     */
    public function __invoke(Settings $settings): JsonResponse
    {
        return response()->json([
            'data' => [
                /** @var string */
                'name' => $settings->get('general.platform_name'),
                /** @var string|null */
                'logo_url' => $settings->fileUrl('general.logo'),
                /** @var string|null */
                'favicon_url' => $settings->fileUrl('general.favicon'),
                /** @var string */
                'default_locale' => $settings->get('general.default_locale'),
                /** @var list<string> */
                'supported_locales' => $settings->get('general.supported_locales'),
                'maintenance' => [
                    /** @var bool */
                    'enabled' => $settings->get('access.maintenance_mode'),
                    /** @var string|null */
                    'message' => $settings->get('access.maintenance_message'),
                ],
                'registration' => [
                    /** @var bool */
                    'enabled' => $settings->get('access.registration_enabled'),
                    /** @var list<'email'|'phone'> */
                    'methods' => ['email', 'phone'],
                    /** @var bool */
                    'require_email_verification' => $settings->get('access.require_email_verification'),
                    /** @var bool */
                    'require_phone_verification' => $settings->get('access.require_phone_verification'),
                    /** @var bool */
                    'requires_terms' => ContentPage::query()->published()->where('slug', ContentPage::TERMS)->exists(),
                ],
                /** @var list<array{slug: string, title: string}> */
                'pages' => ContentPage::query()->published()->orderBy('id')->get()
                    ->map(fn (ContentPage $page) => ['slug' => $page->slug, 'title' => $page->title])
                    ->all(),
            ],
        ]);
    }
}
