<?php

namespace App\Domain\Reservations\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PayAtVenue = 'pay_at_venue';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Void = 'void';
}
