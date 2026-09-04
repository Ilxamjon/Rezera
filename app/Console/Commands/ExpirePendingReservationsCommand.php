<?php

namespace App\Console\Commands;

use App\Actions\Reservations\ChangeReservationStatusAction;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;

final class ExpirePendingReservationsCommand extends Command
{
    protected $signature = 'reservations:expire-pending {--limit=200}';

    protected $description = 'Expire pending reservations past their TTL';

    public function handle(ChangeReservationStatusAction $changeStatus): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $expired = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get()
            ->each(function (Reservation $reservation) use ($changeStatus, &$expired): void {
                $reservation->refresh();
                $customer = $reservation->customer;

                if ($customer === null) {
                    return;
                }

                $changeStatus->execute(
                    reservation: $reservation,
                    toStatus: ReservationStatus::Expired,
                    actor: $customer,
                    source: ReservationEventSource::SystemJob,
                    reason: __('reservations.pending_expired'),
                );

                $expired++;
            });

        $this->info("Expired {$expired} pending reservations.");

        return self::SUCCESS;
    }
}
