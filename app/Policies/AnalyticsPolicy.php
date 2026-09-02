<?php

namespace App\Policies;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;

class AnalyticsPolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
    ) {}

    public function viewOperational(User $user, Business $business): bool
    {
        return $user->isPlatformAdmin()
            || $this->businessAuthorization->canOperateBookings($user, $business->id);
    }

    public function viewFinancial(User $user, Business $business): bool
    {
        return $user->isPlatformAdmin()
            || $this->businessAuthorization->hasAnyRole(
                $user,
                $business->id,
                BusinessMemberRole::Owner,
                BusinessMemberRole::Manager,
            );
    }
}
