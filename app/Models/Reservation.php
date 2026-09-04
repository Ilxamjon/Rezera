<?php

namespace App\Models;

use App\Domain\Reservations\Enums\CancelledByActorType;
use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Domain\Reservations\Enums\PaymentMethod;
use App\Domain\Reservations\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Support\Reservations\OccupancyRange;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'business_id',
        'resource_id',
        'reservation_number',
        'start_at',
        'end_at',
        'duration_minutes',
        'occupancy_range',
        'buffer_minutes_applied',
        'status',
        'hourly_rate_amount',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'promo_code_id',
        'promo_code_snapshot',
        'discount_type',
        'discount_value_snapshot',
        'pricing_snapshot',
        'confirmation_mode',
        'payment_status',
        'payment_method',
        'notes',
        'cancellation_reason',
        'rejection_reason',
        'cancelled_by_user_id',
        'cancelled_by_actor_type',
        'checked_in_at',
        'checked_out_at',
        'checked_in_by_user_id',
        'checked_out_by_user_id',
        'check_in_method',
        'check_out_method',
        'completed_at',
        'confirmed_at',
        'cancelled_at',
        'no_show_at',
        'expires_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => ReservationStatus::class,
            'confirmation_mode' => ConfirmationMode::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'cancelled_by_actor_type' => CancelledByActorType::class,
            'duration_minutes' => 'integer',
            'buffer_minutes_applied' => 'integer',
            'hourly_rate_amount' => 'integer',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'discount_value_snapshot' => 'integer',
            'pricing_snapshot' => 'array',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'check_in_method' => CheckInMethod::class,
            'check_out_method' => CheckInMethod::class,
            'completed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'no_show_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReservationEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function promoRedemption(): HasOne
    {
        return $this->hasOne(PromoCodeRedemption::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ReservationSession::class);
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(ReservationSession::class)->where('status', 'active');
    }

    public function latestSession(): HasOne
    {
        return $this->hasOne(ReservationSession::class)->latest('created_at');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by_user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation): void {
            if ($reservation->occupancy_range === null && $reservation->start_at && $reservation->end_at) {
                $reservation->occupancy_range = OccupancyRange::expression(
                    $reservation->start_at,
                    $reservation->end_at,
                    (int) ($reservation->buffer_minutes_applied ?? 0),
                );
            }
        });
    }
}
