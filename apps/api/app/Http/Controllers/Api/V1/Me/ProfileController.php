<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Update display name, bio and country.
     */
    public function update(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->profile->fill($request->validated())->save();

        return new UserResource($user->load('profile'));
    }
}
