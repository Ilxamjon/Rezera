<?php

namespace App\Domain\Businesses\Enums;

enum BusinessMemberInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
