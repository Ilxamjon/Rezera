<?php

namespace App\Domain\Businesses\Enums;

enum BusinessMemberRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';

    public function canManageBusinessSettings(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canManageBookings(): bool
    {
        return true;
    }
}
