<?php

namespace App\Domain\Payments\Enums;

enum WebhookEventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
}
