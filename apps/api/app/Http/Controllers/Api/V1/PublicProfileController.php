<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlayerCardResource;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use App\Platform\Players\PlayerCard;

class PublicProfileController extends Controller
{
    /**
     * A player's public profile. Banned and deactivated accounts are not shown.
     */
    public function show(string $username): PublicUserResource
    {
        return new PublicUserResource(
            User::query()
                ->where('username', $username)
                ->where('status', UserStatus::Active)
                ->with(['profile', 'gameStats.game'])
                ->firstOrFail(),
        );
    }

    /**
     * A player's card for the popup at a table (PlayerCard). Banned and deactivated accounts are not shown.
     */
    public function card(string $username, PlayerCard $cards): PlayerCardResource
    {
        $user = User::query()->where('username', $username)->where('status', UserStatus::Active)->with('profile')->firstOrFail();

        return new PlayerCardResource($cards->for($user));
    }
}
