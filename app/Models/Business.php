<?php

namespace App\Models;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Services\Businesses\BusinessVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id',
        'created_by_user_id',
        'status',
        'is_publicly_listed',
        'verification_status',
        'verification_note',
        'name',
        'description',
        'translations',
        'phone',
        'email',
        'country_code',
        'region',
        'city',
        'district',
        'address_line',
        'latitude',
        'longitude',
        'timezone',
        'cover_image_url',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'status_changed_at',
        'status_change_reason',
        'onboarding_status',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'verification_status' => BusinessVerificationStatus::class,
            'onboarding_status' => OnboardingStatus::class,
            'is_publicly_listed' => 'boolean',
            'translations' => 'array',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        $query = $query
            ->where('status', BusinessStatus::Approved)
            ->where('is_publicly_listed', true)
            ->whereNull('deleted_at');

        if (config('business_onboarding.public_requires_verification', true)) {
            $query->where('verification_status', BusinessVerificationStatus::Verified);
        }

        if (config('business_onboarding.public_requires_onboarding_complete', true)) {
            $query->where('onboarding_status', OnboardingStatus::Completed);
        }

        return $query;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BusinessMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active');
    }

    public function memberInvitations(): HasMany
    {
        return $this->hasMany(BusinessMemberInvitation::class);
    }

    public function bookingPolicy(): HasOne
    {
        return $this->hasOne(BookingPolicy::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    public function resourceGroups(): HasMany
    {
        return $this->hasMany(ResourceGroup::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(BusinessFavorite::class);
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_favorites')
            ->withPivot('created_at');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    public function effectiveSubscription(): HasOne
    {
        return $this->hasOne(BusinessSubscription::class)
            ->whereIn('status', ['trialing', 'active', 'past_due', 'paused'])
            ->latestOfMany();
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(BusinessVerification::class);
    }

    public function latestVerification(): HasOne
    {
        return $this->hasOne(BusinessVerification::class)->latestOfMany('submitted_at');
    }

    public function isPubliclyVisible(): bool
    {
        return app(BusinessVisibilityService::class)->isPubliclyDiscoverable($this);
    }
}
