<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Platform\Enums\PlatformPermission;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Services\Authorization\PlatformAuthorizationService;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class AdminBaseController extends BaseApiController
{
    protected function authorizePlatform(PlatformPermission $permission): void
    {
        $user = request()->user();

        if ($user === null || ! app(PlatformAuthorizationService::class)->can($user, $permission)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
                'code' => 'forbidden',
            ], 403));
        }
    }
}
