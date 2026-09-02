<?php

namespace App\Policies;

use App\Models\Referral;
use App\Models\User;
use App\Services\Authorization\PlatformAuthorizationService;

class ReferralPolicy extends BasePolicy
{
    public function __construct(
        private readonly PlatformAuthorizationService $platformAuthorization,
    ) {}

    public function viewOwn(User $user): bool
    {
        return $user->isActive();
    }

    public function viewAnyAdmin(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function viewAdmin(User $user, Referral $referral): bool
    {
        return $user->isPlatformAdmin();
    }
}
