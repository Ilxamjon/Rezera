<?php

namespace App\Domain\Reservations\Enums;

enum ReservationEventSource: string
{
    case ApiCustomer = 'api_customer';
    case ApiOwner = 'api_owner';
    case SystemJob = 'system_job';
    case Admin = 'admin';
}
