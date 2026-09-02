<?php

namespace App\Models;

use App\Domain\Loyalty\Enums\LoyaltyTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyTransactionFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'loyalty_account_id',
        'business_id',
        'user_id',
        'type',
        'points',
        'balance_after',
        'source_type',
        'source_id',
        'description',
        'metadata',
        'expires_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoyaltyTransactionType::class,
            'points' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
