<?php

namespace App\Services\Discovery;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use Illuminate\Database\Eloquent\Builder;

final class BusinessDiscoveryAvailabilityFilter
{
    /**
     * @param  Builder<\App\Models\Business>  $query
     */
    public function applyAvailableNow(Builder $query): void
    {
        $occupyingStatuses = $this->occupyingStatusList();
        $statusPlaceholders = $this->placeholders($occupyingStatuses);

        $query->whereRaw("
            EXISTS (
                SELECT 1
                FROM resources r
                WHERE r.business_id = businesses.id
                AND r.status = ?
                AND r.deleted_at IS NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM reservations res
                    WHERE res.resource_id = r.id
                    AND res.status IN ({$statusPlaceholders})
                    AND res.start_at <= ?
                    AND res.end_at > ?
                )
            )
        ", array_merge(
            [ResourceStatus::Active->value],
            $occupyingStatuses,
            [now()->toDateTimeString(), now()->toDateTimeString()],
        ));
    }

    /**
     * @param  Builder<\App\Models\Business>  $query
     */
    public function applyAvailabilityWindow(
        Builder $query,
        string $availableDate,
        string $startTime,
        string $endTime,
        ?string $resourceCategoryId = null,
        ?string $resourceType = null,
    ): void {
        $occupyingStatuses = $this->occupyingStatusList();
        $statusPlaceholders = $this->placeholders($occupyingStatuses);

        $resourceCategorySql = $resourceCategoryId !== null ? 'AND r.resource_group_id = ?' : '';
        $resourceTypeSql = $resourceType !== null ? 'AND r.resource_type = ?' : '';

        $bindings = [ResourceStatus::Active->value];

        if ($resourceCategoryId !== null) {
            $bindings[] = $resourceCategoryId;
        }

        if ($resourceType !== null) {
            $bindings[] = $resourceType;
        }

        $bindings = array_merge(
            $bindings,
            $occupyingStatuses,
            [
                $availableDate,
                $endTime,
                $availableDate,
                $startTime,
                $availableDate,
                $endTime,
                $availableDate,
                $endTime,
                $availableDate,
                $startTime,
            ],
        );

        $query->whereRaw("
            EXISTS (
                SELECT 1
                FROM resources r
                WHERE r.business_id = businesses.id
                AND r.status = ?
                AND r.deleted_at IS NULL
                {$resourceCategorySql}
                {$resourceTypeSql}
                AND NOT EXISTS (
                    SELECT 1
                    FROM reservations res
                    WHERE res.resource_id = r.id
                    AND res.status IN ({$statusPlaceholders})
                    AND res.start_at < (
                        CASE
                            WHEN ((? || ' ' || ?)::time <= (? || ' ' || ?)::time)
                            THEN (((? || ' ' || ?)::timestamp + interval '1 day') AT TIME ZONE COALESCE(businesses.timezone, 'UTC'))
                            ELSE ((? || ' ' || ?)::timestamp AT TIME ZONE COALESCE(businesses.timezone, 'UTC'))
                        END
                    )
                    AND res.end_at > ((? || ' ' || ?)::timestamp AT TIME ZONE COALESCE(businesses.timezone, 'UTC'))
                )
            )
        ", $bindings);
    }

    /**
     * @param  list<string>  $values
     */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /**
     * @return list<string>
     */
    private function occupyingStatusList(): array
    {
        return array_map(
            static fn (ReservationStatus $status): string => $status->value,
            ReservationStatus::occupying(),
        );
    }
}
