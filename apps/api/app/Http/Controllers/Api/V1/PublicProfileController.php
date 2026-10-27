<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;

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
}
