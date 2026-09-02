<?php

namespace App\Policies;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;

class BusinessPolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
    ) {}

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function viewPublic(?User $user, Business $business): bool
    {
        if ($business->isPubliclyVisible()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $this->businessAuthorization->canViewManagement($user, $business->id);
    }

    public function viewManagement(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canViewManagement($user, $business->id);
    }

    public function update(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function viewMembers(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canViewMembers($user, $business->id);
    }

    public function manageMembers(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageMembers($user, $business->id);
    }

    public function manageWorkingHours(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageWorkingHours($user, $business->id);
    }

    public function addMember(User $user, Business $business, BusinessMemberRole $role): bool
    {
        if ($role === BusinessMemberRole::Manager) {
            return $this->businessAuthorization->canAddManager($user, $business->id);
        }

        return $this->businessAuthorization->canManageMembers($user, $business->id);
    }

    public function updateMember(User $user, Business $business, BusinessMember $member, BusinessMemberRole $newRole): bool
    {
        if (! $this->businessAuthorization->canChangeMemberRole($user, $business->id, $newRole)) {
            return false;
        }

        if ($member->member_role === BusinessMemberRole::Owner) {
            return false;
        }

        return $this->businessAuthorization->canRemoveMember($user, $business->id, $member)
            || $this->businessAuthorization->canManageMembers($user, $business->id);
    }

    public function removeMember(User $user, Business $business, BusinessMember $member): bool
    {
        return $this->businessAuthorization->canRemoveMember($user, $business->id, $member);
    }

    public function manageSubscription(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function manageVerification(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function manageReservationSettings(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }
}
