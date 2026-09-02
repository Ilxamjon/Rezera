<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PlatformAuthorizationService;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function __construct(
        private readonly PlatformAuthorizationService $platformAuthorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->platformAuthorization->isStaff($user)) {
            return ApiResponse::error(
                message: __('auth.unauthorized'),
                status: Response::HTTP_FORBIDDEN,
                code: 'forbidden',
            );
        }

        return $next($request);
    }
}
