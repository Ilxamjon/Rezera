<?php

namespace App\Events\Reservations;

use App\Domain\Reservations\Enums\CheckInMethod;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReservationCheckedIn
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly User $actor,
        public readonly CheckInMethod $method,
    ) {}
}
