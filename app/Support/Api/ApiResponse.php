<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => self::transformData($data),
        ];

        return response()->json($payload, $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null,
        ?string $code = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        if ($code !== null) {
            $payload['code'] = $code;
        }

        return response()->json($payload, $status);
    }

    private static function transformData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        if ($data instanceof LengthAwarePaginator) {
            return [
                'items' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                ],
            ];
        }

        return $data;
    }
}
