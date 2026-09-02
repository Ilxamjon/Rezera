<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceGroup extends Model
{
    /** @use HasFactory<\Database\Factories\ResourceGroupFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'default_hourly_rate_amount',
        'default_currency',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'default_hourly_rate_amount' => 'integer',
        ];
    }

    public function activeResources(): HasMany
    {
        return $this->resources()->whereNull('deleted_at');
    }

    /**
     * @param  Builder<ResourceGroup>  $query
     * @return Builder<ResourceGroup>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}
