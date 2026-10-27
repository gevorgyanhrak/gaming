<?php

namespace App\Platform\Auth;

use App\Enums\ContactChannel;
use App\Enums\VerificationPurpose;
use App\Models\User;
use App\Models\VerificationCode;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Issues and checks one-time 6-digit codes sent by email or SMS.
 *
 * Codes are stored as keyed hashes, expire, allow a limited number of attempts, and
 * sending is throttled per destination (cooldown + hourly cap) to limit abuse and SMS cost.
 */
class VerificationCodes
{
    /**
     * Enforce the send limits for a destination, independent of whether an account exists,
     * so throttling behaves identically for unknown addresses (no account enumeration).
     *
     * @throws ThrottleRequestsException
     */
    public function throttle(string $destination, VerificationPurpose $purpose): void
    {
        $config = config('platform.verification');
        $cooldownKey = "verification:cooldown:{$purpose->value}:".sha1($destination);
        $hourlyKey = 'verification:hourly:'.sha1($destination);

        foreach ([$cooldownKey => 1, $hourlyKey => $config['max_sends_per_hour']] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                throw new ThrottleRequestsException(
                    __('platform.verification.throttled', ['seconds' => $seconds]),
                    headers: ['Retry-After' => $seconds],
                );
            }
        }

        RateLimiter::hit($cooldownKey, $config['resend_cooldown_seconds']);
        RateLimiter::hit($hourlyKey, 3600);
    }

    /**
     * Create a new code (replacing any outstanding one for the same purpose) and send it.
     * Callers must call throttle() first.
     */
    public function send(User $user, ContactChannel $channel, VerificationPurpose $purpose, string $destination): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($user, $channel, $purpose, $destination, $code) {
            $user->verificationCodes()
                ->where('channel', $channel)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->delete();

            $user->verificationCodes()->create([
                'channel' => $channel,
                'destination' => $destination,
                'purpose' => $purpose,
                'code_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes(config('platform.verification.code_ttl_minutes')),
            ]);
        });

        Notification::route($channel->notificationRoute(), $destination)
            ->notify((new VerificationCodeNotification($code, $purpose))->locale($user->locale ?? app()->getLocale()));
    }

    /**
     * Consume the user's outstanding code if it matches. Every check counts as an attempt.
     *
     * @return VerificationCode|null The consumed code (its destination is what was verified).
     */
    public function consume(User $user, ContactChannel $channel, VerificationPurpose $purpose, string $code): ?VerificationCode
    {
        return DB::transaction(function () use ($user, $channel, $purpose, $code) {
            $record = $user->verificationCodes()
                ->where('channel', $channel)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->attempts >= config('platform.verification.max_attempts')) {
                return null;
            }

            $record->increment('attempts');

            if (! hash_equals($record->code_hash, $this->hash($code))) {
                return null;
            }

            $record->forceFill(['consumed_at' => now()])->save();

            return $record;
        });
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
