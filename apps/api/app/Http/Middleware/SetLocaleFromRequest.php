<?php

namespace App\Http\Middleware;

use App\Platform\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = $this->settings->get('general.supported_locales');

        $locale = $request->hasHeader('Accept-Language')
            ? $request->getPreferredLanguage($supported)
            : null;

        app()->setLocale($locale ?? $this->settings->get('general.default_locale'));

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
