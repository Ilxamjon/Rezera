<?php

use App\Support\Database\PostgresMigration;
use Illuminate\Database\Migrations\Migration;

/**
 * Partial GiST EXCLUDE prevents overlapping occupying reservations on the same resource.
 * Occupying statuses: pending, confirmed, checked_in.
 * Half-open range [start, end+buffer) allows back-to-back bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        PostgresMigration::addReservationOccupancyExclusion();
    }

    public function down(): void
    {
        PostgresMigration::dropReservationOccupancyExclusion();
    }
};
