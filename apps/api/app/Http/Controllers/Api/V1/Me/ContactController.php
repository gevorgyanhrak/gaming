<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\VerificationPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Http\Requests\Me\ChangeContactRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Platform\Auth\VerificationCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    /**
     * Start adding or changing the email address or phone number: a code is sent to the new one.
     * The account is not changed until the code is confirmed.
     */
    public function store(ChangeContactRequest $request, VerificationCodes $codes): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $codes->throttle($request->destination(), VerificationPurpose::ChangeContact);
        $codes->send($user, $request->channel(), VerificationPurpose::ChangeContact, $request->destination());

        return response()->json(['message' => __('platform.auth.code_sent')], 202);
    }

    /**
     * Confirm the new email address or phone number; it replaces the previous one and is verified.
     */
    public function verify(VerifyCodeRequest $request, VerificationCodes $codes): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $channel = $request->channel();
        $record = $codes->consume($user, $channel, VerificationPurpose::ChangeContact, $request->string('code')->toString());

        if ($record === null) {
            throw ValidationException::withMessages(['code' => __('platform.verification.invalid')]);
        }

        $taken = User::query()->where($channel->column(), $record->destination)->whereKeyNot($user->id)->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'code' => __('validation.unique', ['attribute' => __("platform.channels.{$channel->value}")]),
            ]);
        }

        $user->forceFill([
            $channel->column() => $record->destination,
            $channel->verifiedAtColumn() => now(),
        ])->save();

        return new UserResource($user->load('profile'));
    }
}
