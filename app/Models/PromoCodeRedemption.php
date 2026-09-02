<?php

namespace App\Models;

use App\Domain\Promotions\Enums\PromoRedemptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeRedemption extends Model
{
    use HasUuids;

    protected $fillable = [
        'promo_code_id',
        'user_id',
        'reservation_id',
        'payment_id',
        'discount_amount',
        'currency',
        'status',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PromoRedemptionStatus::class,
            'discount_amount' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
