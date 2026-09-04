<?php

namespace Tests;

use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class PostgresTestCase extends TestCase
{
    use LazilyRefreshDatabase;

    protected bool $seedSubscriptionPlans = true;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL is required for this test suite.');
        }

        $this->recoverAbortedPostgresTransactionIfNeeded();

        if ($this->seedSubscriptionPlans) {
            $this->seed(SubscriptionPlanSeeder::class);
        }
    }

    protected function tearDown(): void
    {
        if (config('database.default') === 'pgsql') {
            $this->recoverAbortedPostgresTransactionIfNeeded();
        }

        parent::tearDown();
    }

    private function recoverAbortedPostgresTransactionIfNeeded(): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        $connection = DB::connection();

        try {
            $connection->selectOne('SELECT 1');
        } catch (\Throwable) {
            DB::purge(config('database.default'));

            return;
        }

        while ($connection->transactionLevel() > 0) {
            try {
                $connection->rollBack();
            } catch (\Throwable) {
                DB::purge(config('database.default'));

                break;
            }
        }
    }

    protected function assertPostgresExclusionViolation(callable $callback): void
    {
        try {
            DB::transaction(function () use ($callback): void {
                $callback();
                $this->fail('Expected PostgreSQL exclusion constraint violation (SQLSTATE 23P01).');
            });
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->errorInfo[0] ?? null, $exception->getMessage());
        }
    }

    protected function postgresExtensionEnabled(string $extension): bool
    {
        $result = DB::selectOne('SELECT 1 FROM pg_extension WHERE extname = ?', [$extension]);

        return $result !== null;
    }
}
