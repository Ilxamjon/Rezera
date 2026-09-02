<?php

namespace App\Models;

use App\Domain\Promotions\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    /** @use HasFactory<\Database\Factories\PromoCodeFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'currency',
        'minimum_amount',
        'maximum_discount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_count',
        'per_user_limit',
        'is_active',
        'metadata',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'minimum_amount' => 'integer',
            'maximum_discount' => 'integer',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PromoCode>  $query
     * @return Builder<PromoCode>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function isPlatformWide(): bool
    {
        return $this->business_id === null;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }
}
