<?php

namespace App\Models;

use App\Domain\Pricing\Enums\PricingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingRule extends Model
{
    /** @use HasFactory<\Database\Factories\PricingRuleFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'resource_id',
        'resource_group_id',
        'name',
        'description',
        'pricing_type',
        'price',
        'currency',
        'day_of_week',
        'start_time',
        'end_time',
        'specific_date',
        'starts_at',
        'ends_at',
        'priority',
        'is_active',
        'metadata',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'pricing_type' => PricingType::class,
            'price' => 'integer',
            'day_of_week' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'specific_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PricingRule>  $query
     * @return Builder<PricingRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function resourceGroup(): BelongsTo
    {
        return $this->belongsTo(ResourceGroup::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
