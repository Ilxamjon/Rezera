<?php

namespace App\Support\Reservations;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Builds PostgreSQL tstzrange expressions for reservation occupancy.
 * Uses half-open interval [start, end + buffer) so adjacent bookings do not overlap.
 */
final class OccupancyRange
{
    public static function expression(
        CarbonInterface $start,
        CarbonInterface $end,
        int $bufferMinutes = 0,
    ): Expression {
        $startIso = $start->copy()->utc()->format('Y-m-d H:i:sP');
        $endIso = $end->copy()->utc()->format('Y-m-d H:i:sP');

        if ($bufferMinutes > 0) {
            return DB::raw(
                "tstzrange('{$startIso}'::timestamptz, ('{$endIso}'::timestamptz + interval '{$bufferMinutes} minutes'), '[)')"
            );
        }

        return DB::raw("tstzrange('{$startIso}'::timestamptz, '{$endIso}'::timestamptz, '[)')");
    }
}
