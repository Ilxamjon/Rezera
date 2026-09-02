<?php

namespace App\Listeners\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use App\Events\Reservations\ReservationCheckedIn;
use App\Events\Reservations\ReservationCheckedOut;
use App\Services\Notifications\BusinessNotificationRecipientResolver;
use App\Services\Notifications\NotificationPayloadBuilder;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchCheckInNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly NotificationPayloadBuilder $payloadBuilder,
        private readonly BusinessNotificationRecipientResolver $recipients,
    ) {}

    public function handleCheckedIn(ReservationCheckedIn $event): void
    {
        $reservation = $event->reservation->loadMissing(['business', 'resource', 'customer']);
        $placeholders = $this->payloadBuilder->reservationPlaceholders($reservation);
        $data = $this->payloadBuilder->reservationData($reservation);

        if ($reservation->customer !== null) {
            $this->notifications->notifyUser(
                user: $reservation->customer,
                type: NotificationType::ReservationCheckedIn,
                entityKey: 'reservation:'.$reservation->id.':checked_in',
                placeholders: $placeholders,
                data: $data,
            );
        }

        $this->notifications->notifyBusinessMembers(
            businessId: $reservation->business_id,
            type: NotificationType::BusinessCustomerCheckedIn,
            entityKey: 'reservation:'.$reservation->id.':checked_in',
            placeholders: $placeholders,
            data: $data,
            recipients: $this->recipients,
        );
    }

    public function handleCheckedOut(ReservationCheckedOut $event): void
    {
        $reservation = $event->reservation->loadMissing(['business', 'resource', 'customer']);
        $placeholders = $this->payloadBuilder->reservationPlaceholders($reservation);
        $data = $this->payloadBuilder->reservationData($reservation);

        if ($reservation->customer !== null) {
            $this->notifications->notifyUser(
                user: $reservation->customer,
                type: NotificationType::ReservationCheckedOut,
                entityKey: 'reservation:'.$reservation->id.':checked_out',
                placeholders: $placeholders,
                data: $data,
            );
        }

        $this->notifications->notifyBusinessMembers(
            businessId: $reservation->business_id,
            type: NotificationType::BusinessCustomerCheckedOut,
            entityKey: 'reservation:'.$reservation->id.':checked_out',
            placeholders: $placeholders,
            data: $data,
            recipients: $this->recipients,
        );
    }
}
