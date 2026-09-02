<?php

namespace App\Services\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Time\TimeInterval;
use Carbon\CarbonInterface;

final class ReservationConflictService
{
    public function hasConflict(string $resourceId, TimeInterval $interval, ?string $ignoreReservationId = null): bool
    {
        return $this->conflictQuery($resourceId, $interval, $ignoreReservationId)->exists();
    }

    public function conflictQuery(string $resourceId, TimeInterval $interval, ?string $ignoreReservationId = null)
    {
        $query = Reservation::query()
            ->where('resource_id', $resourceId)
            ->whereIn('status', array_map(
                static fn (ReservationStatus $status): string => $status->value,
                ReservationStatus::occupying(),
            ))
            ->where('start_at', '<', $interval->toUtcEnd())
            ->where('end_at', '>', $interval->toUtcStart());

        if ($ignoreReservationId !== null) {
            $query->where('id', '!=', $ignoreReservationId);
        }

        return $query;
    }

    public function hasConflictIncludingBuffer(
        string $resourceId,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        int $bufferMinutes,
        ?string $ignoreReservationId = null,
    ): bool {
        $intervalStart = CarbonInterface::instance($startAt)->utc();
        $intervalEnd = CarbonInterface::instance($endAt)->utc()->addMinutes($bufferMinutes);

        $query = Reservation::query()
            ->where('resource_id', $resourceId)
            ->whereIn('status', array_map(
                static fn (ReservationStatus $status): string => $status->value,
                ReservationStatus::occupying(),
            ))
            ->where('start_at', '<', $intervalEnd)
            ->whereRaw('(end_at + make_interval(mins => buffer_minutes_applied)) > ?', [$intervalStart]);

        if ($ignoreReservationId !== null) {
            $query->where('id', '!=', $ignoreReservationId);
        }

        return $query->exists();
    }
}
