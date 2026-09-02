<?php

namespace App\Domain\Reservations\Enums;

enum ConfirmationMode: string
{
    case Instant = 'instant';
    case Manual = 'manual';
}
