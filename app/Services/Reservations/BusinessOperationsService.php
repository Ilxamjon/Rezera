<?php

namespace App\Services\Reservations;

use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\ReservationSession;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class BusinessOperationsService
{
    public function __construct(
        private readonly ReservationOperationalStatusResolver $operationalStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function today(Business $business, ?string $resourceId = null): array
    {
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $now = CarbonImmutable::now($timezone);
        $dayStart = $now->startOfDay()->utc();
        $dayEnd = $now->endOfDay()->utc();

        $query = Reservation::query()
            ->where('business_id', $business->id)
            ->whereBetween('start_at', [$dayStart, $dayEnd])
            ->with(['customer:id,name,phone', 'resource.group', 'activeSession'])
            ->orderBy('start_at');

        if ($resourceId !== null) {
            $query->where('resource_id', $resourceId);
        }

        $reservations = $query->get();

        $activeSessions = ReservationSession::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationSessionStatus::Active->value)
            ->with(['reservation.customer', 'resource.group', 'user:id,name'])
            ->when($resourceId !== null, fn ($q) => $q->where('resource_id', $resourceId))
            ->get();

        $upcoming = $reservations->filter(fn (Reservation $r): bool => $r->start_at->isFuture());
        $overdue = $reservations->filter(function (Reservation $r) use ($now, $timezone): bool {
            if ($r->status !== ReservationStatus::Confirmed) {
                return false;
            }

            return CarbonImmutable::instance($r->end_at)->timezone($timezone)->lessThan($now)
                && $r->checked_in_at === null;
        });

        return [
            'date' => $now->toDateString(),
            'timezone' => $timezone,
            'reservations_today' => $reservations->count(),
            'upcoming_count' => $upcoming->count(),
            'active_sessions_count' => $activeSessions->count(),
            'overdue_count' => $overdue->count(),
            'reservations' => $reservations,
            'active_sessions' => $activeSessions,
            'upcoming' => $upcoming->values(),
            'overdue' => $overdue->values(),
        ];
    }

    /**
     * @return Collection<int, ReservationSession>
     */
    public function activeSessions(Business $business, ?string $resourceId = null): Collection
    {
        return ReservationSession::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationSessionStatus::Active->value)
            ->with(['reservation', 'user:id,name,phone', 'resource.group'])
            ->when($resourceId !== null, fn ($q) => $q->where('resource_id', $resourceId))
            ->orderBy('started_at')
            ->get();
    }

    /**
     * @return array{total: int, available: int, occupied: int, maintenance: int, inactive: int, unavailable: int}
     */
    public function resourceStatusSnapshot(Business $business): array
    {
        $resources = Resource::query()
            ->forBusiness($business->id)
            ->get(['id', 'status']);

        $occupiedResourceIds = ReservationSession::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationSessionStatus::Active->value)
            ->pluck('resource_id')
            ->flip();

        $available = 0;
        $occupied = 0;
        $maintenance = 0;
        $inactive = 0;

        foreach ($resources as $resource) {
            if ($resource->status === ResourceStatus::Maintenance) {
                $maintenance++;

                continue;
            }

            if ($resource->status === ResourceStatus::Inactive) {
                $inactive++;

                continue;
            }

            if ($occupiedResourceIds->has($resource->id)) {
                $occupied++;

                continue;
            }

            $available++;
        }

        return [
            'total' => $resources->count(),
            'available' => $available,
            'occupied' => $occupied,
            'maintenance' => $maintenance,
            'inactive' => $inactive,
            'unavailable' => $maintenance + $inactive,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resourceOccupancy(Business $business): array
    {
        $resources = Resource::query()
            ->forBusiness($business->id)
            ->with(['group'])
            ->orderBy('sort_order')
            ->get();

        $activeSessions = ReservationSession::query()
            ->where('business_id', $business->id)
            ->where('status', ReservationSessionStatus::Active->value)
            ->with(['reservation.customer', 'user:id,name'])
            ->get()
            ->keyBy('resource_id');

        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $now = CarbonImmutable::now($timezone);

        return $resources->map(function (Resource $resource) use ($activeSessions, $now, $timezone): array {
            $session = $activeSessions->get($resource->id);
            $state = match (true) {
                $resource->status === ResourceStatus::Maintenance => 'maintenance',
                $resource->status === ResourceStatus::Inactive => 'inactive',
                $session !== null => 'occupied',
                default => 'available',
            };

            return [
                'resource' => $resource,
                'occupancy_state' => $state,
                'session' => $session,
                'current_reservation' => $session?->reservation,
                'customer' => $session?->user,
                'checked_in_at' => $session?->started_at?->toIso8601String(),
                'scheduled_end_at' => $session?->reservation?->end_at?->toIso8601String(),
                'is_overtime' => $session !== null
                    && $session->reservation?->end_at !== null
                    && $now->greaterThan(CarbonImmutable::instance($session->reservation->end_at)->timezone($timezone)),
            ];
        })->all();
    }
}
