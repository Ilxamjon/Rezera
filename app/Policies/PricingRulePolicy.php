<?php

namespace App\Policies;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Models\Business;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use App\Services\Authorization\PlatformAuthorizationService;

class PricingRulePolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
        private readonly PlatformAuthorizationService $platformAuthorization,
    ) {}

    public function preview(?User $user, Business $business): bool
    {
        return $user !== null && $user->isActive();
    }

    public function viewAny(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function view(User $user, PricingRule $rule, Business $business): bool
    {
        return $this->belongsToBusiness($rule, $business)
            && $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function create(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function update(User $user, PricingRule $rule, Business $business): bool
    {
        return $this->belongsToBusiness($rule, $business)
            && $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function delete(User $user, PricingRule $rule, Business $business): bool
    {
        return $this->update($user, $rule, $business);
    }

    public function viewAnyAdmin(User $user): bool
    {
        return $this->platformAuthorization->can($user, PlatformPermission::PricingView);
    }

    public function viewAdmin(User $user): bool
    {
        return $this->platformAuthorization->can($user, PlatformPermission::PricingView);
    }

    private function belongsToBusiness(PricingRule $rule, Business $business): bool
    {
        return $rule->business_id === $business->id;
    }
}
