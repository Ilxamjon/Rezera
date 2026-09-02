<?php

namespace App\Models;

use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'reservation_id',
        'business_id',
        'resource_id',
        'user_id',
        'started_at',
        'ended_at',
        'start_method',
        'end_method',
        'started_by_user_id',
        'ended_by_user_id',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationSessionStatus::class,
            'start_method' => CheckInMethod::class,
            'end_method' => CheckInMethod::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_user_id');
    }
}
