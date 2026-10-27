<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    /**
     * Change the password. This session stays signed in; other sessions are signed out
     * on their next request and API tokens are revoked.
     */
    public function update(UpdatePasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();
        $user->tokens()->delete();

        return response()->noContent();
    }
}
