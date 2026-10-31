<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Platform\Fishka\FishkaWallets;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    /**
     * Log in with an email address or phone number and a password.
     */
    public function store(LoginRequest $request, FishkaWallets $wallets): UserResource
    {
        $user = $request->authenticate();
        $request->session()->regenerate();
        // The first sign-in of the day brings the daily Fishka bonus.
        $wallets->claimDaily($user);

        return new UserResource($user->load('profile'));
    }

    /**
     * Log out of the current session.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
