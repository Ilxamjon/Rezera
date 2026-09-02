<?php

namespace App\Domain\Businesses\Enums;

enum VerificationRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function toBusinessVerificationStatus(): BusinessVerificationStatus
    {
        return match ($this) {
            self::Pending => BusinessVerificationStatus::Pending,
            self::Approved => BusinessVerificationStatus::Verified,
            self::Rejected => BusinessVerificationStatus::Rejected,
            self::Cancelled => BusinessVerificationStatus::Unverified,
        };
    }
}
