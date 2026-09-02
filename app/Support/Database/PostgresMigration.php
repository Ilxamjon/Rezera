<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Helpers for PostgreSQL-specific migrations and constraints.
 *
 * Laravel Schema Builder cannot express:
 * - btree_gist extension
 * - tstzrange columns
 * - GiST EXCLUDE constraints
 * - partial unique indexes with complex predicates
 *
 * Future migrations should use these helpers or raw DB::statement().
 */
final class PostgresMigration
{
    public static function enableBtreeGist(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
    }

    public static function disableBtreeGist(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS btree_gist');
    }

    /**
     * Add the partial exclusion constraint for reservation occupancy.
     * Must be called after reservations table exists.
     */
    public static function addReservationOccupancyExclusion(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE reservations
            ADD CONSTRAINT reservations_no_overlapping_occupancy
            EXCLUDE USING gist (
                resource_id WITH =,
                occupancy_range WITH &&
            )
            WHERE (status IN ('pending', 'confirmed', 'checked_in'))
        SQL);
    }

    public static function dropReservationOccupancyExclusion(): void
    {
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlapping_occupancy');
    }
}
