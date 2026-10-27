<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Platform\Auth\Avatars;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    /**
     * Upload a new avatar (JPEG, PNG or WebP). It is cropped to a square and re-encoded.
     */
    public function store(UpdateAvatarRequest $request, Avatars $avatars): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $avatars->replace($user->profile, $request->file('avatar'));

        return new UserResource($user->load('profile'));
    }

    /**
     * Remove the avatar.
     */
    public function destroy(Request $request, Avatars $avatars): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $avatars->remove($user->profile);

        return new UserResource($user->load('profile'));
    }
}
