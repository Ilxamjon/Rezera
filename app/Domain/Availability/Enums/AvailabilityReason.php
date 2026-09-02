<?php

namespace App\Domain\Availability\Enums;

enum AvailabilityReason: string
{
    case Available = 'available';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Booked = 'booked';
    case OutsideWorkingHours = 'outside_working_hours';
    case BusinessClosed = 'business_closed';
    case Past = 'past';
    case Blocked = 'blocked';
    case AdvanceTooSoon = 'advance_too_soon';
    case AdvanceTooFar = 'advance_too_far';
    case SameDayDisabled = 'same_day_disabled';
    case InvalidDuration = 'invalid_duration';
    case InvalidStartTime = 'invalid_start_time';
}
