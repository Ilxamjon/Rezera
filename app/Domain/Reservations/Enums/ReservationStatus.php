<?php

namespace App\Domain\Reservations\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function occupiesResource(): bool
    {
        return in_array($this, self::occupying(), true);
    }

    /**
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [
            self::Pending,
            self::Confirmed,
            self::CheckedIn,
        ];
    }
}
