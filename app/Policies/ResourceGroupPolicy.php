<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;

class ResourceGroupPolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
    ) {}

    public function viewAny(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canViewResources($user, $business->id);
    }

    public function view(User $user, ResourceGroup $category, Business $business): bool
    {
        return $this->belongsToBusiness($category, $business)
            && $this->businessAuthorization->canViewResources($user, $business->id);
    }

    public function create(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageResources($user, $business->id);
    }

    public function update(User $user, ResourceGroup $category, Business $business): bool
    {
        return $this->belongsToBusiness($category, $business)
            && $this->businessAuthorization->canManageResources($user, $business->id);
    }

    public function delete(User $user, ResourceGroup $category, Business $business): bool
    {
        return $this->update($user, $category, $business);
    }

    private function belongsToBusiness(ResourceGroup $category, Business $business): bool
    {
        return $category->business_id === $business->id;
    }
}
