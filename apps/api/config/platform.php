<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Locales the API can respond in. The request locale is negotiated from the
    | Accept-Language header; anything unsupported falls back to app.fallback_locale.
    | Phase 4 moves this into admin-managed platform settings.
    |
    */

    'supported_locales' => array_values(array_filter(
        explode(',', (string) env('APP_SUPPORTED_LOCALES', 'en')),
    )),

];
