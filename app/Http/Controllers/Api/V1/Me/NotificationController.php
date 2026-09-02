<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Notification\ListNotificationsRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class NotificationController extends BaseApiController
{
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        $query = UserNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return $this->paginatedResource(
            $query->paginate($perPage),
            NotificationResource::class,
        );
    }

    public function unreadCount(ListNotificationsRequest $request): JsonResponse
    {
        $count = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return $this->success(['count' => $count]);
    }

    public function show(UserNotification $notification): JsonResponse
    {
        Gate::authorize('view', $notification);

        return $this->success(new NotificationResource($notification));
    }

    public function markRead(UserNotification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $this->success(new NotificationResource($notification->fresh()));
    }

    public function markAllRead(ListNotificationsRequest $request): JsonResponse
    {
        $updated = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success(['updated' => $updated]);
    }
}
