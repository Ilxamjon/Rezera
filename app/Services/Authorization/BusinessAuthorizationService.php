<?php

namespace App\Services\Authorization;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\BusinessMember;
use App\Models\User;

class BusinessAuthorizationService
{
    public function membership(User $user, string $businessId): ?BusinessMember
    {
        return BusinessMember::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->where('status', BusinessMemberStatus::Active)
            ->first();
    }

    public function isMember(User $user, string $businessId): bool
    {
        return $this->membership($user, $businessId) !== null;
    }

    public function hasRole(User $user, string $businessId, BusinessMemberRole $role): bool
    {
        return BusinessMember::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->where('status', BusinessMemberStatus::Active)
            ->where('member_role', $role)
            ->exists();
    }

    public function hasAnyRole(User $user, string $businessId, BusinessMemberRole ...$roles): bool
    {
        return BusinessMember::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->where('status', BusinessMemberStatus::Active)
            ->whereIn('member_role', array_map(static fn (BusinessMemberRole $role) => $role->value, $roles))
            ->exists();
    }

    public function canViewManagement(User $user, string $businessId): bool
    {
        return $user->isPlatformAdmin() || $this->isMember($user, $businessId);
    }

    public function canManageBusiness(User $user, string $businessId): bool
    {
        return $user->isPlatformAdmin() || $this->hasAnyRole(
            $user,
            $businessId,
            BusinessMemberRole::Owner,
            BusinessMemberRole::Manager,
        );
    }

    public function canManageBookings(User $user, string $businessId): bool
    {
        return $this->canOperateBookings($user, $businessId);
    }

    public function canOperateBookings(User $user, string $businessId): bool
    {
        return $user->isPlatformAdmin() || $this->hasAnyRole(
            $user,
            $businessId,
            BusinessMemberRole::Owner,
            BusinessMemberRole::Manager,
            BusinessMemberRole::Staff,
        );
    }

    public function canAdministerBookings(User $user, string $businessId): bool
    {
        return $user->isPlatformAdmin() || $this->hasAnyRole(
            $user,
            $businessId,
            BusinessMemberRole::Owner,
            BusinessMemberRole::Manager,
        );
    }

    public function canViewMembers(User $user, string $businessId): bool
    {
        return $this->canManageBusiness($user, $businessId);
    }

    public function canManageMembers(User $user, string $businessId): bool
    {
        return $this->canManageBusiness($user, $businessId);
    }

    public function canManageWorkingHours(User $user, string $businessId): bool
    {
        return $this->canManageBusiness($user, $businessId);
    }

    public function canViewResources(User $user, string $businessId): bool
    {
        return $this->canViewManagement($user, $businessId);
    }

    public function canManageResources(User $user, string $businessId): bool
    {
        return $this->canManageBusiness($user, $businessId);
    }

    public function canAddManager(User $user, string $businessId): bool
    {
        return $user->isPlatformAdmin() || $this->hasRole($user, $businessId, BusinessMemberRole::Owner);
    }

    public function canChangeMemberRole(User $user, string $businessId, BusinessMemberRole $targetRole): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if ($targetRole === BusinessMemberRole::Manager) {
            return $this->hasRole($user, $businessId, BusinessMemberRole::Owner);
        }

        return $this->canManageMembers($user, $businessId);
    }

    public function canRemoveMember(User $user, string $businessId, BusinessMember $member): bool
    {
        if ($user->isPlatformAdmin()) {
            return $member->member_role !== BusinessMemberRole::Owner;
        }

        if ($member->member_role === BusinessMemberRole::Owner) {
            return $this->hasRole($user, $businessId, BusinessMemberRole::Owner);
        }

        if ($member->member_role === BusinessMemberRole::Manager) {
            return $this->hasRole($user, $businessId, BusinessMemberRole::Owner);
        }

        return $this->canManageMembers($user, $businessId);
    }
}
