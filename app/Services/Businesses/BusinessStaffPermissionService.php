<?php

namespace App\Services\Businesses;

use App\Data\Businesses\BusinessStaffCapabilities;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\BusinessMember;
use App\Models\User;

final class BusinessStaffPermissionService
{
    /**
     * @return list<string>
     */
    public function allPermissions(): array
    {
        return config('business_staff.permissions', []);
    }

    /**
     * @return array<string, bool>
     */
    public function permissionsForRole(BusinessMemberRole $role): array
    {
        $matrix = config('business_staff.role_permissions', []);
        $rolePermissions = $matrix[$role->value] ?? [];

        $resolved = [];
        foreach ($this->allPermissions() as $permission) {
            $resolved[$permission] = (bool) ($rolePermissions[$permission] ?? false);
        }

        return $resolved;
    }

    public function capabilitiesForUser(User $user, string $businessId): ?BusinessStaffCapabilities
    {
        if ($user->isPlatformAdmin()) {
            return new BusinessStaffCapabilities(
                businessId: $businessId,
                role: BusinessMemberRole::Owner,
                jobTitle: null,
                permissions: array_fill_keys($this->allPermissions(), true),
                isPlatformAdmin: true,
            );
        }

        $membership = BusinessMember::query()
            ->where('business_id', $businessId)
            ->where('user_id', $user->id)
            ->where('status', BusinessMemberStatus::Active)
            ->first();

        if ($membership === null) {
            return null;
        }

        return new BusinessStaffCapabilities(
            businessId: $businessId,
            role: $membership->member_role,
            jobTitle: $membership->job_title,
            permissions: $this->permissionsForRole($membership->member_role),
            isPlatformAdmin: false,
        );
    }

    public function userCan(User $user, string $businessId, string $permission): bool
    {
        $capabilities = $this->capabilitiesForUser($user, $businessId);

        return $capabilities?->can($permission) ?? false;
    }

    /**
     * @return list<array{role: string, label: string, permissions: array<string, bool>}>
     */
    public function roleDefinitions(): array
    {
        $labels = config('business_staff.role_labels', []);

        return collect(BusinessMemberRole::cases())
            ->map(fn (BusinessMemberRole $role): array => [
                'role' => $role->value,
                'label' => $labels[$role->value] ?? $role->value,
                'permissions' => $this->permissionsForRole($role),
            ])
            ->values()
            ->all();
    }
}
