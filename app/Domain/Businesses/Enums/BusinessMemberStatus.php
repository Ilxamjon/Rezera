<?php

namespace App\Domain\Businesses\Enums;

enum BusinessMemberStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
