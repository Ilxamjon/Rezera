<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Platform\ChangeUserStatusAction;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Requests\Api\V1\Admin\AdminUpdateUserStatusRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::UsersView);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = User::query()->withCount(['reservations', 'businessMemberships']);

        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'ilike', $term)
                    ->orWhere('phone', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('role')) {
            $query->where('platform_role', $request->string('role'));
        }

        $sort = $request->string('sort')->toString() === 'name' ? 'name' : 'created_at';
        $query->orderBy($sort, $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc');

        return $this->paginatedResource($query->paginate($perPage), AdminUserResource::class);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::UsersView);
        $user->loadCount(['reservations', 'businessMemberships']);

        return $this->success(new AdminUserResource($user));
    }

    public function updateStatus(
        AdminUpdateUserStatusRequest $request,
        User $user,
        ChangeUserStatusAction $action,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::UsersManage);

        $updated = $action->execute(
            user: $user,
            status: UserStatus::from($request->validated('status')),
            actor: $request->user(),
            reason: $request->validated('reason'),
            request: $request,
        );

        return $this->success(new AdminUserResource($updated));
    }
}
