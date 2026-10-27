<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Platform\Settings\Settings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Administration panel at /admin (optionally on its own domain, ADMIN_DOMAIN).
 *
 * Uses the "admin" guard: the same accounts as the SPA but a separate login, so signing in here
 * never signs anyone in to the player-facing app. Access requires the "access admin" permission
 * (User::canAccessPanel) and, unless disabled for local development, two-factor authentication.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->domain(config('platform.admin.domain'))
            ->authGuard('admin')
            ->login(Login::class)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: (bool) config('platform.admin.mfa_required'),
            )
            ->brandName(fn () => app(Settings::class)->get('general.platform_name').' Admin')
            ->colors(['primary' => Color::Violet])
            ->defaultThemeMode(ThemeMode::Dark)
            ->navigationGroups([
                NavigationGroup::make('Users'),
                NavigationGroup::make('Games'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('System'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
