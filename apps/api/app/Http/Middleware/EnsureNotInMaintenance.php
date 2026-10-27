<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Platform\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 503 while maintenance mode is on. Staff who can access the admin panel pass through,
 * so they can check the platform before reopening it.
 */
class EnsureNotInMaintenance
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->get('access.maintenance_mode')) {
            return $next($request);
        }

        $user = $request->user();
        if ($user instanceof User && $user->canAccessAdmin()) {
            return $next($request);
        }

        return response()->json([
            'message' => $this->settings->get('access.maintenance_message') ?: __('platform.maintenance.default_message'),
            'maintenance' => true,
        ], 503);
    }
}
