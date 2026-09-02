<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\ResourceManagementResource;
use App\Models\Business;
use App\Models\Resource;
use App\Services\Reservations\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResourceQrController extends BaseApiController
{
    public function show(Business $business, Resource $resource, QrTokenService $qrTokens): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $resource);
        Gate::authorize('view', [$resource, $business]);

        $token = $qrTokens->activeToken($resource);

        return $this->success([
            'resource_id' => $resource->id,
            'has_active_token' => $token !== null,
            'token_preview' => $token ? substr($token->token, 0, 8).'...' : null,
            'last_used_at' => $token?->last_used_at?->toIso8601String(),
            'created_at' => $token?->created_at?->toIso8601String(),
        ]);
    }

    public function store(
        Request $request,
        Business $business,
        Resource $resource,
        QrTokenService $qrTokens,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $resource);
        Gate::authorize('update', [$resource, $business]);

        $token = $qrTokens->generate($resource, $request->user()->id);

        return $this->created([
            'resource_id' => $resource->id,
            'token' => $token->token,
            'qr_payload' => 'rezera://check-in/'.$token->token,
        ], __('reservations.qr.generated'));
    }

    public function revoke(
        Business $business,
        Resource $resource,
        QrTokenService $qrTokens,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $resource);
        Gate::authorize('update', [$resource, $business]);

        $qrTokens->revoke($resource);

        return $this->success(null, __('reservations.qr.revoked'));
    }

    private function ensureBelongsToBusiness(Business $business, Resource $resource): void
    {
        if ($resource->business_id !== $business->id) {
            abort(404);
        }
    }
}
