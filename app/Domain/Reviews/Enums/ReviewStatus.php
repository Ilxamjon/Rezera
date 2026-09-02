<?php

namespace App\Domain\Reviews\Enums;

enum ReviewStatus: string
{
    case Published = 'published';
    case Hidden = 'hidden';
    case Pending = 'pending';
    case Rejected = 'rejected';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    public function contributesToRating(): bool
    {
        return $this === self::Published;
    }
}
