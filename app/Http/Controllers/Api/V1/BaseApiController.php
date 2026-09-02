<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseApiController extends Controller
{
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ApiResponse::success($data, $message, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function paginatedResource(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        return $this->success([
            'items' => $resourceClass::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
