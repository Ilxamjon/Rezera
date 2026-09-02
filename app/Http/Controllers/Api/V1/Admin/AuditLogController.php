<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Resources\Api\V1\Admin\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::AuditLogsView);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = AuditLog::query()->with('actor')->orderByDesc('created_at');

        if ($request->filled('actor_user_id')) {
            $query->where('actor_user_id', $request->string('actor_user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->string('entity_id'));
        }

        return $this->paginatedResource($query->paginate($perPage), AuditLogResource::class);
    }
}
