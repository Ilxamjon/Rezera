<?php

namespace App\Domain\Reservations\Enums;

enum ReservationOperationalStatus: string
{
    case Scheduled = 'scheduled';
    case EligibleForCheckIn = 'eligible_for_check_in';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
