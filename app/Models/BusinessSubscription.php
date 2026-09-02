<?php

namespace App\Models;

use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessSubscription extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessSubscriptionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'billing_interval',
        'provider',
        'provider_subscription_id',
        'provider_customer_id',
        'started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancel_at_period_end',
        'ended_at',
        'cancellation_reason',
        'pending_plan_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_interval' => BillingInterval::class,
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @param  Builder<BusinessSubscription>  $query
     * @return Builder<BusinessSubscription>
     */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (SubscriptionStatus $status): string => $status->value,
            SubscriptionStatus::effective(),
        ));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'pending_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'business_subscription_id');
    }

    public function isEffective(): bool
    {
        return $this->status?->isEffective() === true;
    }

    public function isScheduledForCancellation(): bool
    {
        return $this->cancel_at_period_end !== null && $this->cancelled_at === null;
    }
}
