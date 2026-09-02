<?php

namespace App\Services\Authorization;

use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Models\User;

final class PlatformAuthorizationService
{
    public function isStaff(User $user): bool
    {
        return $user->platform_role?->isStaff() === true;
    }

    public function can(User $user, PlatformPermission $permission): bool
    {
        if (! $this->isStaff($user)) {
            return false;
        }

        $role = $user->platform_role;

        if ($role === null) {
            return false;
        }

        if (in_array($role, [PlatformRole::SuperAdmin, PlatformRole::PlatformAdmin], true)) {
            return true;
        }

        return in_array($permission, $this->permissionsForRole($role), true);
    }

    /**
     * @return list<PlatformPermission>
     */
    private function permissionsForRole(PlatformRole $role): array
    {
        return match ($role) {
            PlatformRole::Admin => [
                PlatformPermission::UsersView,
                PlatformPermission::UsersManage,
                PlatformPermission::BusinessesView,
                PlatformPermission::BusinessesManage,
                PlatformPermission::ReservationsView,
                PlatformPermission::ReservationsManage,
                PlatformPermission::PaymentsView,
                PlatformPermission::CategoriesManage,
                PlatformPermission::AuditLogsView,
                PlatformPermission::NotificationsView,
                PlatformPermission::ReviewsView,
                PlatformPermission::ReviewsManage,
                PlatformPermission::PromotionsView,
                PlatformPermission::PromotionsManage,
                PlatformPermission::PricingView,
                PlatformPermission::PricingManage,
                PlatformPermission::SubscriptionsView,
                PlatformPermission::SubscriptionsManage,
            ],
            PlatformRole::Support => [
                PlatformPermission::UsersView,
                PlatformPermission::BusinessesView,
                PlatformPermission::ReservationsView,
                PlatformPermission::PaymentsView,
                PlatformPermission::NotificationsView,
                PlatformPermission::ReviewsView,
                PlatformPermission::PromotionsView,
                PlatformPermission::PricingView,
                PlatformPermission::SubscriptionsView,
            ],
            default => [],
        };
    }
}
