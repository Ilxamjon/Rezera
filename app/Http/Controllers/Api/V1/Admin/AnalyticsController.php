<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Services\Analytics\PlatformAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends AdminBaseController
{
    public function overview(Request $request, PlatformAnalyticsService $analytics): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::BusinessesView);

        return $this->success($analytics->overview(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        ));
    }
}
