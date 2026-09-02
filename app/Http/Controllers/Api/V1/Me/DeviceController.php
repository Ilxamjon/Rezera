<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Notification\StoreDeviceRequest;
use App\Http\Requests\Api\V1\Notification\UpdateDeviceRequest;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DeviceController extends BaseApiController
{
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $device = UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $data['device_id'],
            ],
            [
                'platform' => $data['platform'],
                'push_token' => $data['push_token'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'locale' => $data['locale'] ?? $user->locale?->value,
                'last_seen_at' => now(),
                'is_active' => true,
            ],
        );

        return $this->created(new DeviceResource($device));
    }

    public function update(UpdateDeviceRequest $request, UserDevice $device): JsonResponse
    {
        Gate::authorize('update', $device);

        $device->update([
            ...$request->validated(),
            'last_seen_at' => now(),
        ]);

        return $this->success(new DeviceResource($device->fresh()));
    }

    public function destroy(UserDevice $device): JsonResponse
    {
        Gate::authorize('delete', $device);

        $device->update([
            'is_active' => false,
            'push_token' => null,
            'last_seen_at' => now(),
        ]);

        return $this->success(new DeviceResource($device->fresh()));
    }
}
