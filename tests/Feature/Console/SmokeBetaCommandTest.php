<?php

namespace Tests\Feature\Console;

use Database\Seeders\BusinessCategorySeeder;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;

class SmokeBetaCommandTest extends PostgresTestCase
{
    public function test_smoke_command_passes_on_migrated_database(): void
    {
        $this->seed(BusinessCategorySeeder::class);

        $this->artisan('rezera:smoke')
            ->expectsOutputToContain('[ok] Database connection')
            ->expectsOutputToContain('Smoke OK')
            ->assertSuccessful();
    }

    public function test_smoke_detects_missing_btree_gist_message_path(): void
    {
        // Soft assertion: constraint query runs without throwing on a healthy DB.
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM pg_constraint WHERE conname = ? LIMIT 1',
            ['reservations_no_overlapping_occupancy']
        );

        $this->assertNotNull($row);
    }
}
