<?php

namespace App\Models;

use App\Domain\Loyalty\Enums\LoyaltyAccountStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyAccountFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'user_id',
        'balance',
        'lifetime_earned',
        'lifetime_redeemed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'lifetime_earned' => 'integer',
            'lifetime_redeemed' => 'integer',
            'status' => LoyaltyAccountStatus::class,
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

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function isPlatformAccount(): bool
    {
        return $this->business_id === null;
    }
}
