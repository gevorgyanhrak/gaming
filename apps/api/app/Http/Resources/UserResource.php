<?php

namespace App\Http\Resources;

use App\Enums\ContactChannel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user's own account. Never use this for other users (see PublicUserResource).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => $this->hasVerified(ContactChannel::Email),
            'phone_verified' => $this->hasVerified(ContactChannel::Phone),
            'has_password' => $this->password !== null,
            'locale' => $this->locale,
            'created_at' => $this->created_at->toIso8601String(),
            /** @var string|null */
            'last_active_at' => $this->last_active_at?->toIso8601String(),
            'can_access_admin' => $this->canAccessAdmin(),
            'profile' => [
                /** @var string|null */
                'display_name' => $this->profile->display_name,
                /** @var string|null */
                'avatar_url' => $this->profile->avatarUrl(),
                /** @var string|null */
                'bio' => $this->profile->bio,
                /** @var string|null */
                'country' => $this->profile->country,
                'preferences' => [
                    'sound' => (bool) ($this->profile->preferences['sound'] ?? true),
                ],
            ],
        ];
    }
}
