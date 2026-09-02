<?php

namespace App\Models;

use App\Domain\Resources\Enums\RateUnit;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    /** @use HasFactory<\Database\Factories\ResourceFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'resource_group_id',
        'name',
        'code',
        'description',
        'image_url',
        'resource_type',
        'status',
        'capacity',
        'hourly_rate_amount',
        'currency',
        'rate_unit',
        'min_duration_minutes',
        'max_duration_minutes',
        'duration_step_minutes',
        'buffer_minutes',
        'metadata',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'resource_type' => ResourceType::class,
            'status' => ResourceStatus::class,
            'capacity' => 'integer',
            'hourly_rate_amount' => 'integer',
            'rate_unit' => RateUnit::class,
            'min_duration_minutes' => 'integer',
            'max_duration_minutes' => 'integer',
            'duration_step_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'metadata' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<Resource>  $query
     * @return Builder<Resource>
     */
    public function scopePubliclyBookable(Builder $query): Builder
    {
        return $query
            ->where('status', ResourceStatus::Active)
            ->whereNull('deleted_at');
    }

    /**
     * @param  Builder<Resource>  $query
     * @return Builder<Resource>
     */
    public function scopeForBusiness(Builder $query, string $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ResourceGroup::class, 'resource_group_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
