<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;

class LoyaltyPolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
    ) {}

    public function viewProgram(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function updateProgram(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function viewCustomerLoyalty(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function adjustPoints(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }
}
