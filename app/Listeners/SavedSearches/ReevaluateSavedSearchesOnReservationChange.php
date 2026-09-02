<?php

namespace App\Listeners\SavedSearches;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Reservations\ReservationStatusChanged;
use App\Jobs\SavedSearches\EvaluateSavedSearchJob;
use App\Models\SavedSearch;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ReevaluateSavedSearchesOnReservationChange implements ShouldQueue
{
    public function handle(ReservationStatusChanged $event): void
    {
        if ($event->toStatus !== ReservationStatus::Cancelled) {
            return;
        }

        $reservation = $event->reservation;

        $query = SavedSearch::query()
            ->where('alert_enabled', true)
            ->where('availability_required', true)
            ->where(function ($builder) use ($reservation): void {
                $builder
                    ->where('business_id', $reservation->business_id)
                    ->orWhere('resource_id', $reservation->resource_id);
            });

        $query->pluck('id')->each(
            fn (string $id) => EvaluateSavedSearchJob::dispatch($id, ignoreFrequency: true),
        );
    }
}
