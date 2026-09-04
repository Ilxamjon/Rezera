<?php

namespace App\Services\Discovery;

use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class BusinessOpenNowFilter
{
    /**
     * @param  Builder<Business>  $query
     */
    public function apply(Builder $query, ?CarbonImmutable $at = null): void
    {
        // Bind PHP/app clock so Carbon::setTestNow() is honored (PostgreSQL now() is not).
        $at ??= CarbonImmutable::instance(now());
        $atUtc = $at->utc()->format('Y-m-d H:i:s.uP');

        $query->whereExists(function ($subQuery) use ($atUtc): void {
            $subQuery
                ->selectRaw('1')
                ->from('business_hours as bh')
                ->whereColumn('bh.business_id', 'businesses.id')
                ->where('bh.is_closed', false)
                ->where(function ($hoursQuery) use ($atUtc): void {
                    $hoursQuery
                        ->whereRaw('
                            bh.is_open_24h = true
                            AND bh.weekday = EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz))::int
                        ', [$atUtc])
                        ->orWhereRaw('
                            bh.weekday = EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz))::int
                            AND bh.opens_at IS NOT NULL
                            AND bh.closes_at IS NOT NULL
                            AND (
                                (
                                    bh.closes_at > bh.opens_at
                                    AND timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz)::time >= bh.opens_at
                                    AND timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz)::time < bh.closes_at
                                )
                                OR (
                                    bh.closes_at < bh.opens_at
                                    AND timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz)::time >= bh.opens_at
                                )
                            )
                        ', [$atUtc, $atUtc, $atUtc, $atUtc])
                        ->orWhereRaw('
                            bh.weekday = CASE
                                WHEN EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz))::int = 1 THEN 7
                                ELSE EXTRACT(ISODOW FROM timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz))::int - 1
                            END
                            AND bh.opens_at IS NOT NULL
                            AND bh.closes_at IS NOT NULL
                            AND bh.closes_at < bh.opens_at
                            AND timezone(COALESCE(businesses.timezone, \'UTC\'), ?::timestamptz)::time < bh.closes_at
                        ', [$atUtc, $atUtc, $atUtc]);
                });
        });
    }
}
