<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\Admin\AdminDashboardResource;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends AdminBaseController
{
    public function show(Request $request, AdminDashboardService $dashboard): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::BusinessesView);

        return $this->success(new AdminDashboardResource(
            $dashboard->summary(
                $request->string('date_from')->toString() ?: null,
                $request->string('date_to')->toString() ?: null,
            ),
        ));
    }
}
