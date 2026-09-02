<?php

namespace App\Domain\Reviews\Enums;

enum ReviewReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
