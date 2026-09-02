<?php

namespace App\Listeners\Loyalty;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Payments\PaymentRefunded;
use App\Events\Reservations\ReservationStatusChanged;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Referrals\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class ProcessLoyaltyOnReservationCompleted implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly ReferralService $referralService,
    ) {}

    public function handle(ReservationStatusChanged $event): void
    {
        if ($event->toStatus !== ReservationStatus::Completed) {
            return;
        }

        $this->loyaltyService->earnFromReservation($event->reservation);
        $this->referralService->qualifyFromReservation($event->reservation);
    }
}
