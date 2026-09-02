<?php

namespace App\Models;

use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyReward extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyRewardFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'points_cost',
        'reward_type',
        'value',
        'currency',
        'stock',
        'max_redemptions_per_user',
        'is_active',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'points_cost' => 'integer',
            'reward_type' => LoyaltyRewardType::class,
            'value' => 'integer',
            'stock' => 'integer',
            'max_redemptions_per_user' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->stock !== null && $this->stock <= 0) {
            return false;
        }

        return true;
    }
}
