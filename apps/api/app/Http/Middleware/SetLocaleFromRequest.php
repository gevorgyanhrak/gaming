<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('platform.supported_locales');

        $locale = $request->hasHeader('Accept-Language')
            ? $request->getPreferredLanguage($supported)
            : null;

        app()->setLocale($locale ?? config('app.fallback_locale'));

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
