<?php

namespace App\Services\Calendar;

use App\Domain\Calendar\Enums\CalendarBlockType;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Reservation;
use App\Models\Resource;
use App\Support\Time\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CalendarScheduleBuilder
{
    /**
     * @param  list<TimeInterval>  $openIntervals
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array<string, mixed>>
     */
    public function buildForResourceDay(
        Resource $resource,
        TimeInterval $viewWindow,
        array $openIntervals,
        Collection $reservations,
        CarbonImmutable $now,
        bool $includeAvailableGaps = true,
    ): array {
        if ($resource->status === ResourceStatus::Maintenance) {
            return [$this->formatBlock(
                type: CalendarBlockType::Maintenance,
                interval: $viewWindow,
                reservation: null,
                timezone: $viewWindow->start->timezone->getName(),
            )];
        }

        if ($resource->status === ResourceStatus::Inactive) {
            return [$this->formatBlock(
                type: CalendarBlockType::Inactive,
                interval: $viewWindow,
                reservation: null,
                timezone: $viewWindow->start->timezone->getName(),
            )];
        }

        if ($openIntervals === []) {
            return [$this->formatBlock(
                type: CalendarBlockType::Closed,
                interval: $viewWindow,
                reservation: null,
                timezone: $viewWindow->start->timezone->getName(),
            )];
        }

        $blocks = [];
        $timezone = $viewWindow->start->timezone->getName();

        foreach ($openIntervals as $openInterval) {
            $clippedOpen = $this->clipInterval($openInterval, $viewWindow);
            if ($clippedOpen === null) {
                continue;
            }

            $cursor = $clippedOpen->start;
            $reservationIntervals = $this->reservationIntervals($reservations, $clippedOpen);

            foreach ($reservationIntervals as $item) {
                if ($includeAvailableGaps && $cursor->lessThan($item['interval']->start)) {
                    $blocks[] = $this->formatBlock(
                        type: CalendarBlockType::Available,
                        interval: new TimeInterval($cursor, $item['interval']->start),
                        reservation: null,
                        timezone: $timezone,
                    );
                }

                $blocks[] = $this->formatBlock(
                    type: $item['type'],
                    interval: $item['interval'],
                    reservation: $item['reservation'],
                    timezone: $timezone,
                );

                if ($item['interval']->end->greaterThan($cursor)) {
                    $cursor = $item['interval']->end;
                }
            }

            if ($includeAvailableGaps && $cursor->lessThan($clippedOpen->end)) {
                $blocks[] = $this->formatBlock(
                    type: CalendarBlockType::Available,
                    interval: new TimeInterval($cursor, $clippedOpen->end),
                    reservation: null,
                    timezone: $timezone,
                );
            }
        }

        return $this->mergeAdjacentBlocks($blocks);
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return list<array{interval: TimeInterval, reservation: Reservation, type: CalendarBlockType}>
     */
    private function reservationIntervals(Collection $reservations, TimeInterval $window): array
    {
        $items = [];

        foreach ($reservations as $reservation) {
            $interval = $this->reservationInterval($reservation);
            $clipped = $this->clipInterval($interval, $window);

            if ($clipped === null) {
                continue;
            }

            $items[] = [
                'interval' => $clipped,
                'reservation' => $reservation,
                'type' => $this->blockTypeFor($reservation),
            ];
        }

        usort($items, static fn (array $a, array $b): int => $a['interval']->start <=> $b['interval']->start);

        return $items;
    }

    private function reservationInterval(Reservation $reservation): TimeInterval
    {
        $start = CarbonImmutable::instance($reservation->start_at);
        $end = CarbonImmutable::instance($reservation->end_at)
            ->addMinutes((int) $reservation->buffer_minutes_applied);

        return new TimeInterval($start, $end);
    }

    private function blockTypeFor(Reservation $reservation): CalendarBlockType
    {
        return match ($reservation->status) {
            ReservationStatus::Cancelled, ReservationStatus::Rejected => CalendarBlockType::Cancelled,
            ReservationStatus::Expired => CalendarBlockType::Other,
            ReservationStatus::Completed => CalendarBlockType::Completed,
            ReservationStatus::NoShow => CalendarBlockType::NoShow,
            ReservationStatus::CheckedIn => CalendarBlockType::Active,
            ReservationStatus::Confirmed, ReservationStatus::Pending => $this->isActiveReservation($reservation)
                ? CalendarBlockType::Active
                : CalendarBlockType::Booked,
        };
    }

    private function isActiveReservation(Reservation $reservation): bool
    {
        return $reservation->checked_in_at !== null
            || $reservation->activeSession?->status === ReservationSessionStatus::Active;
    }

    private function clipInterval(TimeInterval $interval, TimeInterval $window): ?TimeInterval
    {
        $start = $interval->start->greaterThan($window->start) ? $interval->start : $window->start;
        $end = $interval->end->lessThan($window->end) ? $interval->end : $window->end;

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        return new TimeInterval($start, $end);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBlock(
        CalendarBlockType $type,
        TimeInterval $interval,
        ?Reservation $reservation,
        string $timezone,
    ): array {
        $startLocal = $interval->start->timezone($timezone);
        $endLocal = $interval->end->timezone($timezone);

        $block = [
            'type' => $type->value,
            'label' => strtoupper(str_replace('_', ' ', $type->value)),
            'start_at' => $interval->start->toIso8601String(),
            'end_at' => $interval->end->toIso8601String(),
            'start_time' => $startLocal->format('H:i'),
            'end_time' => $endLocal->format('H:i'),
            'duration_minutes' => (int) $startLocal->diffInMinutes($endLocal),
        ];

        if ($reservation !== null) {
            $block['reservation'] = [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'status' => $reservation->status->value,
                'customer_name' => $reservation->customer_name_snapshot,
                'customer_phone' => $reservation->customer_phone_snapshot,
                'duration_minutes' => $reservation->duration_minutes,
                'checked_in_at' => $reservation->checked_in_at?->toIso8601String(),
            ];
        }

        return $block;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function mergeAdjacentBlocks(array $blocks): array
    {
        if ($blocks === []) {
            return [];
        }

        $merged = [];
        $current = $blocks[0];

        foreach (array_slice($blocks, 1) as $block) {
            if (
                $block['type'] === $current['type']
                && ($block['reservation']['id'] ?? null) === ($current['reservation']['id'] ?? null)
                && $block['start_at'] === $current['end_at']
            ) {
                $current['end_at'] = $block['end_at'];
                $current['end_time'] = $block['end_time'];
                $current['duration_minutes'] += $block['duration_minutes'];

                continue;
            }

            $merged[] = $current;
            $current = $block;
        }

        $merged[] = $current;

        return $merged;
    }
}
