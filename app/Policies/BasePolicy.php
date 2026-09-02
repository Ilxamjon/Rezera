<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Base policy for business-scoped authorization helpers.
 */
abstract class BasePolicy
{
    use HandlesAuthorization;

    protected function isPlatformAdmin(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    protected function hasBusinessMembership(User $user, string $businessId): bool
    {
        return app(BusinessAuthorizationService::class)->isMember($user, $businessId);
    }

    protected function canViewManagement(User $user, Business $business): bool
    {
        return app(BusinessAuthorizationService::class)->canViewManagement($user, $business->id);
    }
}
