<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends BaseApiController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $ok = ! in_array(false, array_column($checks, 'ok'), true);
        $status = $ok ? 'ok' : 'degraded';
        $http = $ok ? 200 : 503;

        return response()->json([
            'success' => $ok,
            'message' => null,
            'data' => [
                'status' => $status,
                'service' => config('app.name'),
                'version' => config('rezera.api.version'),
                'checks' => $checks,
            ],
        ], $http);
    }

    /**
     * @return array{ok: bool, latency_ms?: int, error?: string}
     */
    private function checkDatabase(): array
    {
        $started = hrtime(true);

        try {
            DB::select('select 1');

            return [
                'ok' => true,
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'error' => 'database_unavailable',
            ];
        }
    }

    /**
     * @return array{ok: bool, skipped?: bool, latency_ms?: int, error?: string}
     */
    private function checkRedis(): array
    {
        $driver = config('cache.default');
        $queue = config('queue.default');

        if (! in_array('redis', [$driver, $queue], true)) {
            return ['ok' => true, 'skipped' => true];
        }

        $started = hrtime(true);

        try {
            Redis::connection()->ping();

            return [
                'ok' => true,
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'error' => 'redis_unavailable',
            ];
        }
    }
}
