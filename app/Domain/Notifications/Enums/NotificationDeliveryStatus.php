<?php

namespace App\Domain\Notifications\Enums;

enum NotificationDeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
