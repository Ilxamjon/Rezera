<?php

namespace App\Models;

use App\Domain\Loyalty\Enums\LoyaltyRedemptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyRedemption extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyRedemptionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'user_id',
        'loyalty_reward_id',
        'loyalty_account_id',
        'points_spent',
        'status',
        'redemption_code',
        'redeemed_at',
        'used_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'points_spent' => 'integer',
            'status' => LoyaltyRedemptionStatus::class,
            'redeemed_at' => 'datetime',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }
}
