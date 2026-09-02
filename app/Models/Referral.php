<?php

namespace App\Models;

use App\Domain\Referrals\Enums\ReferralStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code_id',
        'status',
        'qualified_at',
        'rewarded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }
}
