<?php

namespace App\Policies;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Models\Business;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use App\Services\Authorization\PlatformAuthorizationService;

class PromoCodePolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
        private readonly PlatformAuthorizationService $platformAuthorization,
    ) {}

    public function validate(?User $user, Business $business): bool
    {
        return $user !== null && $user->isActive();
    }

    public function viewAny(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function view(User $user, PromoCode $promo, Business $business): bool
    {
        return $this->belongsToBusiness($promo, $business)
            && $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function create(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function update(User $user, PromoCode $promo, Business $business): bool
    {
        return $this->belongsToBusiness($promo, $business)
            && $this->businessAuthorization->canManageBusiness($user, $business->id);
    }

    public function delete(User $user, PromoCode $promo, Business $business): bool
    {
        return $this->update($user, $promo, $business);
    }

    public function viewAnyAdmin(User $user): bool
    {
        return $this->platformAuthorization->can($user, PlatformPermission::PromotionsView);
    }

    public function viewAdmin(User $user): bool
    {
        return $this->platformAuthorization->can($user, PlatformPermission::PromotionsView);
    }

    public function createAdmin(User $user): bool
    {
        return $this->platformAuthorization->can($user, PlatformPermission::PromotionsManage);
    }

    public function updateAdmin(User $user): bool
    {
        return $this->platformAuthorization->can($user, PlatformPermission::PromotionsManage);
    }

    public function deleteAdmin(User $user): bool
    {
        return $this->platformAuthorization->can($user, PlatformPermission::PromotionsManage);
    }

    private function belongsToBusiness(PromoCode $promo, Business $business): bool
    {
        return $promo->business_id === $business->id;
    }
}
