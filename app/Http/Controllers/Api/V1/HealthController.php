<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends BaseApiController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => config('app.name'),
            'version' => config('rezera.api.version'),
        ]);
    }
}
