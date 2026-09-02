<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class PostgresTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seedSubscriptionPlans = true;

    protected function setUp(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for this test suite.');
        }

        parent::setUp();

        if ($this->seedSubscriptionPlans) {
            $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        }
    }

    protected function assertPostgresExclusionViolation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected PostgreSQL exclusion constraint violation (SQLSTATE 23P01).');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertSame('23P01', $exception->errorInfo[0] ?? null, $exception->getMessage());
        }
    }

    protected function postgresExtensionEnabled(string $extension): bool
    {
        $result = DB::selectOne('SELECT 1 FROM pg_extension WHERE extname = ?', [$extension]);

        return $result !== null;
    }
}
