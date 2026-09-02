<?php

namespace App\Domain\Reservations\Enums;

enum CancelledByActorType: string
{
    case Customer = 'customer';
    case Owner = 'owner';
    case Staff = 'staff';
    case Admin = 'admin';
    case System = 'system';
}
