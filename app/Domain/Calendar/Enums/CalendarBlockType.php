<?php

namespace App\Domain\Calendar\Enums;

enum CalendarBlockType: string
{
    case Closed = 'closed';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';
    case Available = 'available';
    case Booked = 'booked';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Other = 'other';
}
