<?php

namespace App\Providers;

use App\Models\User;
use App\Platform\Sms\LogSmsGateway;
use App\Platform\Sms\SmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
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
        $this->app->singleton(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            default => throw new InvalidArgumentException('Unsupported SMS driver ['.config('services.sms.driver').'].'),
        });
    }

    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->max(128)->letters()->numbers();

            // The breached-password check calls the Have I Been Pwned range API; skip it in tests.
            return $this->app->runningUnitTests() ? $rule : $rule->uncompromised();
        });

        // Super admins pass every authorization check.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->getAuthIdentifier() ?? $request->ip();

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('lookups', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(10)->by($byUserOrIp($request)));
        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(30)->by($byUserOrIp($request)));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by($byUserOrIp($request)));
    }
}
