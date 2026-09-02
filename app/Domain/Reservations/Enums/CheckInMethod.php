<?php

namespace App\Domain\Reservations\Enums;

enum CheckInMethod: string
{
    case Qr = 'qr';
    case Staff = 'staff';
    case Customer = 'customer';
    case System = 'system';
}
