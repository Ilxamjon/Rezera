<?php

namespace App\Services\Availability;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class OccupyingReservationProvider
{
    /**
     * @param  list<string>  $resourceIds
     * @return Collection<string, list<TimeInterval>>
     */
    public function intervalsByResource(
        array $resourceIds,
        TimeInterval $requestedInterval,
    ): Collection {
        if ($resourceIds === []) {
            return collect();
        }

        $reservations = Reservation::query()
            ->select(['id', 'resource_id', 'start_at', 'end_at', 'buffer_minutes_applied', 'status'])
            ->whereIn('resource_id', $resourceIds)
            ->whereIn('status', array_map(
                static fn (ReservationStatus $status): string => $status->value,
                ReservationStatus::occupying(),
            ))
            ->where('start_at', '<', $requestedInterval->toUtcEnd())
            ->where('end_at', '>', $requestedInterval->toUtcStart())
            ->get();

        return $reservations
            ->groupBy('resource_id')
            ->map(static function (Collection $items): array {
                return $items->map(static function (Reservation $reservation): TimeInterval {
                    $start = CarbonImmutable::instance($reservation->start_at)->utc();
                    $end = CarbonImmutable::instance($reservation->end_at)
                        ->utc()
                        ->addMinutes((int) $reservation->buffer_minutes_applied);

                    return new TimeInterval($start, $end);
                })->all();
            });
    }
}
