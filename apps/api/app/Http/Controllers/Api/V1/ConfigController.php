<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * Public platform configuration for the SPA.
     *
     * Only values that are safe to expose belong here. Phase 4 sources these from
     * admin-managed platform settings (allowlisted with is_public).
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                /** @var string */
                'name' => config('app.name'),
                /** @var string */
                'default_locale' => config('app.fallback_locale'),
                /** @var list<string> */
                'supported_locales' => config('platform.supported_locales'),
                'registration' => [
                    /** @var bool */
                    'enabled' => config('platform.registration.enabled'),
                    /** @var list<'email'|'phone'> */
                    'methods' => ['email', 'phone'],
                    /** @var bool */
                    'require_email_verification' => config('platform.registration.require_email_verification'),
                    /** @var bool */
                    'require_phone_verification' => config('platform.registration.require_phone_verification'),
                ],
            ],
        ]);
    }
}
