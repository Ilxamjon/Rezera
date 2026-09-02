<?php

namespace App\Models;

use App\Domain\Reservations\Enums\ConfirmationMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPolicy extends Model
{
    /** @use HasFactory<\Database\Factories\BookingPolicyFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
        'confirmation_mode',
        'min_duration_minutes',
        'max_duration_minutes',
        'duration_step_minutes',
        'cancellation_deadline_minutes',
        'pending_expiry_minutes',
        'check_in_early_minutes',
        'no_show_grace_minutes',
        'buffer_minutes',
        'min_advance_minutes',
        'max_advance_days',
        'customer_can_cancel',
        'business_can_cancel',
        'allow_same_day_reservations',
        'max_active_reservations_per_customer',
        'max_daily_reservations_per_customer',
        'require_customer_note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confirmation_mode' => ConfirmationMode::class,
            'min_duration_minutes' => 'integer',
            'max_duration_minutes' => 'integer',
            'duration_step_minutes' => 'integer',
            'cancellation_deadline_minutes' => 'integer',
            'pending_expiry_minutes' => 'integer',
            'check_in_early_minutes' => 'integer',
            'no_show_grace_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'min_advance_minutes' => 'integer',
            'max_advance_days' => 'integer',
            'customer_can_cancel' => 'boolean',
            'business_can_cancel' => 'boolean',
            'allow_same_day_reservations' => 'boolean',
            'max_active_reservations_per_customer' => 'integer',
            'max_daily_reservations_per_customer' => 'integer',
            'require_customer_note' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
