<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait IsolatesPublicCatalog
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->purgePublicCatalogFixtures();
    }

    /**
     * Ensure discovery/category tests start from an empty public catalog.
     *
     * LazilyRefreshDatabase relies on per-test transactions; when a prior test
     * aborts a PostgreSQL transaction, rows can leak into subsequent tests.
     */
    protected function purgePublicCatalogFixtures(): void
    {
        DB::statement('TRUNCATE TABLE businesses RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE business_categories RESTART IDENTITY CASCADE');
    }
}
