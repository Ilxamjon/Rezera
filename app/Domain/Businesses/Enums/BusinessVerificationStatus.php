<?php

namespace App\Domain\Businesses\Enums;

enum BusinessVerificationStatus: string
{
    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function allowsResubmission(): bool
    {
        return in_array($this, [self::Unverified, self::Rejected], true);
    }
}
