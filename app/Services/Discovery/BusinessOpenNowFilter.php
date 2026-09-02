<?php

namespace App\Services\Discovery;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class BusinessOpenNowFilter
{
    /**
     * @param  Builder<\App\Models\Business>  $query
     */
    public function apply(Builder $query): void
    {
        $query->whereExists(function ($subQuery): void {
            $subQuery
                ->selectRaw('1')
                ->from('business_hours as bh')
                ->whereColumn('bh.business_id', 'businesses.id')
                ->where('bh.is_closed', false)
                ->whereRaw("
                    (
                        bh.is_open_24h = true
                        AND bh.weekday = EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, 'UTC'), now()))::int
                    )
                    OR (
                        bh.weekday = EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, 'UTC'), now()))::int
                        AND bh.opens_at IS NOT NULL
                        AND bh.closes_at IS NOT NULL
                        AND (
                            (
                                bh.closes_at > bh.opens_at
                                AND timezone(COALESCE(businesses.timezone, 'UTC'), now())::time >= bh.opens_at
                                AND timezone(COALESCE(businesses.timezone, 'UTC'), now())::time < bh.closes_at
                            )
                            OR (
                                bh.closes_at < bh.opens_at
                                AND timezone(COALESCE(businesses.timezone, 'UTC'), now())::time >= bh.opens_at
                            )
                        )
                    )
                    OR (
                        bh.weekday = CASE
                            WHEN EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, 'UTC'), now()))::int = 1 THEN 7
                            ELSE EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, 'UTC'), now()))::int - 1
                        END
                        AND bh.opens_at IS NOT NULL
                        AND bh.closes_at IS NOT NULL
                        AND bh.closes_at < bh.opens_at
                        AND timezone(COALESCE(businesses.timezone, 'UTC'), now())::time < bh.closes_at
                    )
                ");
        });
    }
}
