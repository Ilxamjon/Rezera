<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Platform\ChangeBusinessStatusAction;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Requests\Api\V1\Admin\AdminUpdateBusinessStatusRequest;
use App\Http\Requests\Api\V1\Admin\AdminUpdateBusinessVerificationRequest;
use App\Http\Resources\Api\V1\Admin\AdminBusinessResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::BusinessesView);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = Business::query()->with(['category', 'createdBy'])->withCount(['resources', 'reservations', 'favorites']);

        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';
            $query->where('name', 'ilike', $term);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->string('category_id'));
        }

        if ($request->filled('city')) {
            $query->where('city', 'ilike', '%'.$request->string('city').'%');
        }

        $sort = $request->string('sort')->toString() === 'name' ? 'name' : 'created_at';
        $query->orderBy($sort, $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc');

        return $this->paginatedResource($query->paginate($perPage), AdminBusinessResource::class);
    }

    public function show(Business $business): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::BusinessesView);
        $business->load(['category', 'createdBy'])->loadCount(['resources', 'reservations', 'favorites']);

        return $this->success(new AdminBusinessResource($business));
    }

    public function updateStatus(
        AdminUpdateBusinessStatusRequest $request,
        Business $business,
        ChangeBusinessStatusAction $action,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::BusinessesManage);

        $updated = $action->execute(
            business: $business,
            status: BusinessStatus::from($request->validated('status')),
            actor: $request->user(),
            reason: $request->validated('reason'),
            request: $request,
        );

        return $this->success(new AdminBusinessResource($updated->load(['category', 'createdBy'])));
    }

    public function updateVerification(
        AdminUpdateBusinessVerificationRequest $request,
        Business $business,
        ChangeBusinessStatusAction $action,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::BusinessesManage);

        $updated = $action->verify(
            business: $business,
            status: BusinessVerificationStatus::from($request->validated('status')),
            actor: $request->user(),
            note: $request->validated('note'),
            request: $request,
        );

        return $this->success(new AdminBusinessResource($updated->load(['category', 'createdBy'])));
    }
}
