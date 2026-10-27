<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records when an authenticated user was last active, throttled to one write per
 * interval so that ordinary traffic does not turn into one UPDATE per request.
 */
class TouchLastActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $interval = (int) config('platform.last_active_interval_minutes');

        if ($user instanceof User && ($user->last_active_at === null || $user->last_active_at->lt(now()->subMinutes($interval)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
