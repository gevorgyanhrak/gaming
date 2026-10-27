<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Platform\Auth\LoginIdentifier;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:128'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Attempt to log in with an email or phone number and password.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $identifier = LoginIdentifier::parse($this->string('login')->toString());

        if ($identifier === null) {
            throw ValidationException::withMessages(['login' => __('platform.auth.invalid_login')]);
        }

        if (! Auth::guard('web')->attempt($identifier->credentials($this->string('password')->toString()), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->isActive()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['login' => __('platform.auth.inactive')]);
        }

        return $user;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ])->status(429);
    }

    private function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->string('login')->trim()->toString())).'|'.$this->ip();
    }
}
