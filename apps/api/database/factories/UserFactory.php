<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->regexify('[a-z][a-z0-9_]{5,14}'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_verified_at' => now(),
            'phone_verified_at' => null,
            'password' => static::$password ??= Hash::make('password1'),
            'status' => UserStatus::Active,
            'locale' => 'en',
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            // Query instead of reading $user->profile, which would cache the unsaved default model.
            if (! $user->profile()->exists()) {
                $user->setRelation('profile', UserProfile::factory()->for($user)->create());
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null, 'phone_verified_at' => null]);
    }

    /** Registered with a phone number only. */
    public function withPhone(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'email_verified_at' => null,
            'phone' => '+3749'.fake()->unique()->numerify('#######'),
            'phone_verified_at' => now(),
        ]);
    }

    public function banned(): static
    {
        return $this->state(['status' => UserStatus::Banned]);
    }

    public function deactivated(): static
    {
        return $this->state(['status' => UserStatus::Deactivated]);
    }
}
