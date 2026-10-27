<?php

namespace App\Http\Middleware;

use App\Platform\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies admin-managed settings that configure the framework itself (platform name, session
 * lifetime) before the session starts. Runs per request, never at boot, so artisan commands and
 * deployments work without a database. If settings can't be read, configured defaults remain.
 */
class ApplyRuntimeSettings
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            config([
                'app.name' => $this->settings->get('general.platform_name'),
                'session.lifetime' => $this->settings->get('security.session_lifetime_minutes'),
            ]);
        } catch (Throwable $e) {
            Log::warning('Platform settings unavailable; using configured defaults.', ['error' => $e->getMessage()]);
        }

        return $next($request);
    }
}
