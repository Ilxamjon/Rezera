<?php

namespace App\Domain\Businesses\Enums;

enum BusinessStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Approved;
    }
}
