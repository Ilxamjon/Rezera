<?php

use App\Support\Database\PostgresMigration;
use Illuminate\Database\Migrations\Migration;

/**
 * btree_gist is required for GiST exclusion constraints on reservations.occupancy_range.
 */
return new class extends Migration
{
    public function up(): void
    {
        PostgresMigration::enableBtreeGist();
    }

    public function down(): void
    {
        PostgresMigration::disableBtreeGist();
    }
};
