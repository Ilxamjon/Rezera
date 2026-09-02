<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;

class LoyaltyRewardPolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
    ) {}

    public function viewAny(?User $user, Business $business): bool
    {
        return true;
    }

    public function redeem(User $user, LoyaltyReward $reward, Business $business): bool
    {
        return $user->isActive()
            && $reward->business_id === $business->id;
    }

    public function viewAnyManage(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function create(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function view(User $user, LoyaltyReward $reward, Business $business): bool
    {
        return $reward->business_id === $business->id
            && $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function update(User $user, LoyaltyReward $reward, Business $business): bool
    {
        return $this->view($user, $reward, $business);
    }

    public function delete(User $user, LoyaltyReward $reward, Business $business): bool
    {
        return $this->view($user, $reward, $business);
    }
}
