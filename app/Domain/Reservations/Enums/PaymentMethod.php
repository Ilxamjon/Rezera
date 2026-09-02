<?php

namespace App\Domain\Reservations\Enums;

enum PaymentMethod: string
{
    case Venue = 'venue';
    case Mock = 'mock';
}
