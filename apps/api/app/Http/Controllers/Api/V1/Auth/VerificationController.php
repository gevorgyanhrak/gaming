<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\VerificationPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendVerificationRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Platform\Auth\VerificationCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    /**
     * Send a verification code to the account's email address or phone number.
     */
    public function send(SendVerificationRequest $request, VerificationCodes $codes): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $channel = $request->channel();
        $destination = $user->contact($channel);
        $channelName = __("platform.channels.{$channel->value}");

        if ($destination === null) {
            throw ValidationException::withMessages(['channel' => __('platform.auth.no_contact', ['channel' => $channelName])]);
        }

        if ($user->hasVerified($channel)) {
            throw ValidationException::withMessages(['channel' => __('platform.auth.already_verified', ['channel' => $channelName])]);
        }

        $codes->throttle($destination, VerificationPurpose::Verify);
        $codes->send($user, $channel, VerificationPurpose::Verify, $destination);

        return response()->json(['message' => __('platform.auth.code_sent')], 202);
    }

    /**
     * Confirm the email address or phone number with the code that was sent to it.
     */
    public function verify(VerifyCodeRequest $request, VerificationCodes $codes): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $channel = $request->channel();
        $record = $codes->consume($user, $channel, VerificationPurpose::Verify, $request->string('code')->toString());

        // A code only verifies the contact it was sent to, in case the contact changed since.
        if ($record === null || $record->destination !== $user->contact($channel)) {
            throw ValidationException::withMessages(['code' => __('platform.verification.invalid')]);
        }

        $user->forceFill([$channel->verifiedAtColumn() => now()])->save();

        return new UserResource($user->load('profile'));
    }
}
