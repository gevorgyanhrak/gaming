<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\VerificationPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Platform\Auth\VerificationCodes;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    /**
     * Create an account with an email address or a phone number, log in, and send a verification code.
     */
    public function __invoke(RegisterRequest $request, VerificationCodes $codes): JsonResponse
    {
        abort_unless(config('platform.registration.enabled'), 403, __('platform.auth.registration_closed'));

        $channel = $request->channel();
        $destination = $request->string($channel->column())->toString();

        $user = DB::transaction(function () use ($request, $channel, $destination) {
            $user = User::create([
                'username' => $request->validated('username'),
                $channel->column() => $destination,
                'password' => $request->validated('password'),
                'locale' => app()->getLocale(),
            ]);
            $user->profile()->create();

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        try {
            $codes->throttle($destination, VerificationPurpose::Verify);
            $codes->send($user, $channel, VerificationPurpose::Verify, $destination);
        } catch (ThrottleRequestsException) {
            // The account exists either way; the user can request a code again later.
        }

        return UserResource::make($user->load('profile'))->response()->setStatusCode(201);
    }
}
