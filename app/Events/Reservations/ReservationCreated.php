<?php

namespace App\Events\Reservations;

use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReservationCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Reservation $reservation,
    ) {}
}
