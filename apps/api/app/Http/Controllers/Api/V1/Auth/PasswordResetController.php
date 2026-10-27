<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\VerificationPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Platform\Auth\VerificationCodes;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Send a password reset code to an email address or phone number.
     *
     * The response is identical whether or not an account exists.
     */
    public function forgot(ForgotPasswordRequest $request, VerificationCodes $codes): JsonResponse
    {
        $identifier = $request->identifier();

        $codes->throttle($identifier->value, VerificationPurpose::PasswordReset);

        $user = $identifier->findUser();

        if ($user?->isActive()) {
            $codes->send($user, $identifier->channel, VerificationPurpose::PasswordReset, $identifier->value);
        }

        return response()->json(['message' => __('platform.auth.reset_sent')], 202);
    }

    /**
     * Set a new password using the code that was sent.
     *
     * Other sessions are signed out on their next request (their stored password hash no
     * longer matches) and API tokens are revoked.
     */
    public function reset(ResetPasswordRequest $request, VerificationCodes $codes): JsonResponse
    {
        $identifier = $request->identifier();
        $user = $identifier->findUser();
        $record = $user?->isActive()
            ? $codes->consume($user, $identifier->channel, VerificationPurpose::PasswordReset, $request->string('code')->toString())
            : null;

        if ($user === null || $record === null) {
            throw ValidationException::withMessages(['code' => __('platform.verification.invalid')]);
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
            // Receiving the code proves ownership of the contact.
            $identifier->channel->verifiedAtColumn() => $user->getAttribute($identifier->channel->verifiedAtColumn()) ?? now(),
        ])->save();

        $user->tokens()->delete();

        event(new PasswordReset($user));

        return response()->json(['message' => __('platform.auth.reset_done')]);
    }
}
