<?php

namespace App\Domain\Reservations\Enums;

enum ReservationSessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
