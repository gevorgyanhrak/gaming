<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * The authenticated user's account. The first visit of the day (a session that is still signed in
     * counts as signing in) brings the daily Fishka bonus.
     */
    public function show(Request $request, FishkaWallets $wallets): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $wallets->claimDaily($user);

        return new UserResource($user->load('profile'));
    }
}
