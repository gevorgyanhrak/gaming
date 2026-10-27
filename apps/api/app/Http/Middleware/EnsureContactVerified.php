<?php

namespace App\Http\Middleware;

use App\Enums\ContactChannel;
use App\Models\User;
use App\Platform\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gameplay requires a verified contact when the platform settings say so: every contact the
 * account has must be verified for each channel that requires verification.
 */
class EnsureContactVerified
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            foreach ([[ContactChannel::Email, 'access.require_email_verification'], [ContactChannel::Phone, 'access.require_phone_verification']] as [$channel, $setting]) {
                if ($this->settings->get($setting) && $user->contact($channel) !== null && ! $user->hasVerified($channel)) {
                    return response()->json([
                        'message' => __('platform.play.verification_required', ['channel' => __("platform.channels.{$channel->value}")]),
                        'reason' => 'verification_required',
                        'channel' => $channel->value,
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
