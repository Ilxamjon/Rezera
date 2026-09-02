<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Platform\UpdatePlatformSettingAction;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Requests\Api\V1\Admin\AdminUpdateSettingRequest;
use App\Http\Resources\Api\V1\Admin\PlatformSettingResource;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;

class SettingController extends AdminBaseController
{
    public function index(): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::SettingsManage);

        return $this->success(PlatformSettingResource::collection(PlatformSetting::query()->orderBy('key')->get()));
    }

    public function update(
        AdminUpdateSettingRequest $request,
        string $key,
        UpdatePlatformSettingAction $action,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::SettingsManage);

        $setting = PlatformSetting::query()->where('key', $key)->firstOrFail();
        $updated = $action->execute($setting, $request->validated('value'), $request->user(), $request);

        return $this->success(new PlatformSettingResource($updated));
    }
}
