<?php

namespace App\Domain\Identity\Enums;

enum PlatformRole: string
{
    case User = 'user';
    case PlatformAdmin = 'platform_admin';
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Support = 'support';

    public function isStaff(): bool
    {
        return in_array($this, self::staffRoles(), true);
    }

    public function isAdmin(): bool
    {
        return in_array($this, [self::PlatformAdmin, self::SuperAdmin, self::Admin], true);
    }

    public function isElevated(): bool
    {
        return in_array($this, [self::PlatformAdmin, self::SuperAdmin], true);
    }

    /**
     * @return list<self>
     */
    public static function staffRoles(): array
    {
        return [
            self::PlatformAdmin,
            self::SuperAdmin,
            self::Admin,
            self::Support,
        ];
    }
}
