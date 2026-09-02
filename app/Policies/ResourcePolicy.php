<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Resource;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;

class ResourcePolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
    ) {}

    public function viewAny(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canViewResources($user, $business->id);
    }

    public function view(User $user, Resource $resource, Business $business): bool
    {
        return $this->belongsToBusiness($resource, $business)
            && $this->businessAuthorization->canViewResources($user, $business->id);
    }

    public function create(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageResources($user, $business->id);
    }

    public function update(User $user, Resource $resource, Business $business): bool
    {
        return $this->belongsToBusiness($resource, $business)
            && $this->businessAuthorization->canManageResources($user, $business->id);
    }

    public function delete(User $user, Resource $resource, Business $business): bool
    {
        return $this->update($user, $resource, $business);
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

    private function belongsToBusiness(Resource $resource, Business $business): bool
    {
        return $resource->business_id === $business->id;
    }
}
