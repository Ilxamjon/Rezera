<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Resources\Api\V1\Admin\NotificationDeliveryResource;
use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationDeliveryController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::NotificationsView);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = NotificationDelivery::query()->with('notification')->orderByDesc('created_at');

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->paginatedResource($query->paginate($perPage), NotificationDeliveryResource::class);
    }
}
