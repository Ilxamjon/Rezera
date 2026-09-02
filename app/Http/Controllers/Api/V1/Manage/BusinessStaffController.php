<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\BusinessStaffCapabilitiesResource;
use App\Models\Business;
use App\Services\Businesses\BusinessStaffPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessStaffController extends BaseApiController
{
    public function roles(Business $business, BusinessStaffPermissionService $permissions): JsonResponse
    {
        Gate::authorize('viewMembers', $business);

        return $this->success([
            'roles' => $permissions->roleDefinitions(),
            'suggested_job_titles' => config('business_staff.suggested_job_titles', []),
            'permissions' => $permissions->allPermissions(),
        ]);
    }

    public function me(Business $business, BusinessStaffPermissionService $permissions): JsonResponse
    {
        Gate::authorize('viewManagement', $business);

        $capabilities = $permissions->capabilitiesForUser(request()->user(), $business->id);

        if ($capabilities === null) {
            abort(403);
        }

        return $this->success(new BusinessStaffCapabilitiesResource($capabilities));
    }
}
