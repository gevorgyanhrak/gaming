<?php

namespace App\Providers;

use App\Gameplay\GameRegistry;
use App\Models\User;
use App\Platform\Levels\Levels;
use App\Platform\Settings\Settings;
use App\Platform\Sms\LogSmsGateway;
use App\Platform\Sms\SmsGateway;
use Database\Seeders\RoleSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request (or job), so settings are read once and never leak between requests.
        $this->app->scoped(Settings::class);
        // Same for levels: the reward table is read once per request.
        $this->app->scoped(Levels::class);

        // Engines register themselves here (see app/Games/*); one registry for the whole app.
        $this->app->singleton(GameRegistry::class);

        $this->app->singleton(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            default => throw new InvalidArgumentException('Unsupported SMS driver ['.config('services.sms.driver').'].'),
        });
    }

    public function boot(): void
    {
        $proxies = (string) config('platform.trusted_proxies');
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        Password::defaults(function () {
            $rule = Password::min(8)->max(128)->letters()->numbers();

            // The breached-password check calls the Have I Been Pwned range API; skip it in tests.
            return $this->app->runningUnitTests() ? $rule : $rule->uncompromised();
        });

        // Super admins hold every permission. Only permission checks are bypassed: policy rules that
        // are false for everyone (e.g. editing the audit log, deleting games) still apply.
        Gate::before(fn (User $user, string $ability) => in_array($ability, RoleSeeder::permissions(), true) && $user->hasRole('super_admin') ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->getAuthIdentifier() ?? $request->ip();

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('lookups', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(10)->by($byUserOrIp($request)));
        // The player's own account: /me and /me/* (wallet, rooms, stats) together. Every page load reads
        // several of them, and the wallet is polled while live updates are down.
        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(60)->by($byUserOrIp($request)));
        // Board games are bursty (Nardi doubles are four quick steps plus state refreshes).
        RateLimiter::for('game', fn (Request $request) => Limit::perMinute(240)->by($byUserOrIp($request)));
        RateLimiter::for('game-actions', fn (Request $request) => Limit::perMinute(120)->by($byUserOrIp($request)));
        // The game page reports presence every 5 s (12 a minute); room by room, so a few open tabs fit.
        RateLimiter::for('presence', fn (Request $request) => Limit::perMinute(60)->by($byUserOrIp($request)));
        // Emojis and quick messages: a quick burst is fine, a flood is not.
        RateLimiter::for('reactions', fn (Request $request) => [
            Limit::perSecond(3)->by('s:'.$byUserOrIp($request)),
            Limit::perMinute(40)->by('m:'.$byUserOrIp($request)),
        ]);
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by($byUserOrIp($request)));
    }
}
