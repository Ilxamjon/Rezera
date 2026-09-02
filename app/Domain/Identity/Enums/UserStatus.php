<?php

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
