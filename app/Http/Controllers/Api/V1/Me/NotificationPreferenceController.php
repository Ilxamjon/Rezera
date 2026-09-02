<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Notification\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;

class NotificationPreferenceController extends BaseApiController
{
    public function index(NotificationPreferenceService $preferences): JsonResponse
    {
        $items = $preferences->listForUser(request()->user());

        return $this->success([
            'items' => NotificationPreferenceResource::collection($items)->resolve(),
        ]);
    }

    public function update(
        UpdateNotificationPreferencesRequest $request,
        NotificationPreferenceService $preferences,
    ): JsonResponse {
        $preferences->updateForUser(
            $request->user(),
            $request->validated('preferences'),
        );

        $items = $preferences->listForUser($request->user());

        return $this->success([
            'items' => NotificationPreferenceResource::collection($items)->resolve(),
        ]);
    }
}
