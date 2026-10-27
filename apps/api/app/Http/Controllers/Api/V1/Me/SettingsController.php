<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateSettingsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class SettingsController extends Controller
{
    /**
     * Update language and preferences.
     */
    public function update(UpdateSettingsRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->has('locale')) {
            $user->forceFill(['locale' => $request->validated('locale')])->save();
        }

        if ($request->has('preferences')) {
            $user->profile->preferences = [...($user->profile->preferences ?? []), ...$request->validated('preferences')];
            $user->profile->save();
        }

        return new UserResource($user->load('profile'));
    }
}
