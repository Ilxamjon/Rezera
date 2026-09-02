<?php

namespace App\Domain\Resources\Enums;

enum ResourceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    public function isBookable(): bool
    {
        return $this === self::Active;
    }
}
