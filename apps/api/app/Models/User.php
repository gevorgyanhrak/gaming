<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $username
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $password
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property UserStatus $status
 * @property string|null $locale
 * @property Carbon|null $last_active_at
 * @property Carbon $created_at
 * @property-read UserProfile $profile
 */
#[Fillable(['username', 'email', 'phone', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Mirrors the column default, so a freshly created model is consistent before reloading.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Emails are stored lower-cased so lookups and uniqueness are exact.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    /**
     * @return HasOne<UserProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class)->withDefault();
    }

    /**
     * @return HasMany<VerificationCode, $this>
     */
    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function contact(ContactChannel $channel): ?string
    {
        return $this->getAttribute($channel->column());
    }

    public function hasVerified(ContactChannel $channel): bool
    {
        return $this->getAttribute($channel->verifiedAtColumn()) !== null;
    }

    public function canAccessAdmin(): bool
    {
        return $this->can('access admin');
    }

    /**
     * Destination for the "sms" notification channel.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }
}
