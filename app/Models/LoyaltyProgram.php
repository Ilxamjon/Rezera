<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyProgramFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'is_enabled',
        'earn_rate_points',
        'earn_amount',
        'flat_points_per_reservation',
        'minimum_qualifying_amount',
        'max_points_per_transaction',
        'points_expiration_days',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'earn_rate_points' => 'integer',
            'earn_amount' => 'integer',
            'flat_points_per_reservation' => 'integer',
            'minimum_qualifying_amount' => 'integer',
            'max_points_per_transaction' => 'integer',
            'points_expiration_days' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(LoyaltyAccount::class, 'business_id', 'business_id');
    }
}
