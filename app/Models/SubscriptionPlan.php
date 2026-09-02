<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPlanFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'description',
        'translations',
        'is_active',
        'is_public',
        'sort_order',
        'monthly_price',
        'yearly_price',
        'currency',
        'trial_days',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'monthly_price' => 'integer',
            'yearly_price' => 'integer',
            'trial_days' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(BusinessSubscription::class, 'plan_id');
    }

    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->translations[$locale]['name'] ?? $this->name;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->translations[$locale]['description'] ?? $this->description;
    }

    public function priceForInterval(string $interval): int
    {
        return $interval === 'yearly' ? (int) $this->yearly_price : (int) $this->monthly_price;
    }

    public function isFree(): bool
    {
        return $this->monthly_price === 0 && $this->yearly_price === 0;
    }
}
