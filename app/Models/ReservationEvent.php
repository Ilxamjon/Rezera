<?php

namespace App\Models;

use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationEvent extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationEventFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'reservation_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'source',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ReservationStatus::class,
            'to_status' => ReservationStatus::class,
            'source' => ReservationEventSource::class,
            'created_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
