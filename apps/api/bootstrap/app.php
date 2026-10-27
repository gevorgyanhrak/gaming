<?php

use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Exceptions\RoomNotActive;
use App\Gameplay\Exceptions\VersionConflict;
use App\Http\Middleware\ApplyRuntimeSettings;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureContactVerified;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Http\Middleware\SetLocaleFromRequest;
use App\Http\Middleware\TouchLastActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Admin-managed framework settings (session lifetime, platform name), before sessions start.
        $middleware->prepend(ApplyRuntimeSettings::class);
        // Cookie-based (session) authentication for requests coming from the SPA origin.
        $middleware->statefulApi();
        $middleware->api(append: [SetLocaleFromRequest::class]);
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'touch-last-active' => TouchLastActive::class,
            'maintenance' => EnsureNotInMaintenance::class,
            'verified-contact' => EnsureContactVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Gameplay outcomes the client handles: refetch state on 409, show the message on 422.
        $exceptions->render(fn (IllegalAction $e) => response()->json(['message' => $e->getMessage()], 422));
        $exceptions->render(fn (RoomNotActive $e) => response()->json(['message' => $e->getMessage()], 409));
        $exceptions->render(fn (VersionConflict $e) => response()->json([
            'message' => $e->getMessage(),
            'current_version' => $e->currentVersion,
        ], 409));

        // Generic body: the default message names the Eloquent model class that was not found.
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? response()->json(['message' => 'Not found.'], 404)
            : null);
    })->create();
