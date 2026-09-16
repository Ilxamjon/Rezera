<?php

namespace App\Models;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Identity\Enums\Locale;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Identity\Enums\UserStatus;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'avatar_url',
        'locale',
        'timezone',
        'platform_role',
        'status',
        'phone_verified_at',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'platform_role' => PlatformRole::class,
            'status' => UserStatus::class,
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platform_role?->isAdmin() === true;
    }

    public function isPlatformStaff(): bool
    {
        return $this->platform_role?->isStaff() === true;
    }

    public function isActive(): bool
    {
        return $this->status?->canAuthenticate() === true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isPlatformStaff() && $this->isActive();
    }

    public function businessMemberships(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    public function activeBusinessMemberships(): HasMany
    {
        return $this->businessMemberships()->where('status', BusinessMemberStatus::Active);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_members')
            ->withPivot(['member_role', 'status'])
            ->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'customer_id');
    }

    public function createdBusinesses(): HasMany
    {
        return $this->hasMany(Business::class, 'created_by_user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(BusinessFavorite::class);
    }

    public function favoriteBusinesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_favorites')
            ->withPivot('created_at');
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function canAccessBusiness(string $businessId, ?BusinessMemberRole $minimumRole = null): bool
    {
        $membership = $this->businessMemberships()
            ->where('business_id', $businessId)
            ->where('status', BusinessMemberStatus::Active)
            ->first();

        if ($membership === null) {
            return false;
        }

        if ($minimumRole === null) {
            return true;
        }

        return match ($minimumRole) {
            BusinessMemberRole::Staff => true,
            BusinessMemberRole::Manager => in_array($membership->member_role, [
                BusinessMemberRole::Owner,
                BusinessMemberRole::Manager,
            ], true),
            BusinessMemberRole::Owner => $membership->member_role === BusinessMemberRole::Owner,
        };
    }
}
