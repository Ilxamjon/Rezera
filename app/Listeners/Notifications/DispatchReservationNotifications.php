<?php

namespace App\Listeners\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Reservations\Enums\CancelledByActorType;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Reservations\ReservationCreated;
use App\Events\Reservations\ReservationStatusChanged;
use App\Services\Notifications\BusinessNotificationRecipientResolver;
use App\Services\Notifications\NotificationPayloadBuilder;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchReservationNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly NotificationPayloadBuilder $payloadBuilder,
        private readonly BusinessNotificationRecipientResolver $recipients,
    ) {}

    public function handleReservationCreated(ReservationCreated $event): void
    {
        $reservation = $event->reservation->loadMissing(['business', 'resource', 'customer']);
        $placeholders = $this->payloadBuilder->reservationPlaceholders($reservation);
        $data = $this->payloadBuilder->reservationData($reservation);

        if ($reservation->customer !== null) {
            $this->notifications->notifyUser(
                user: $reservation->customer,
                type: NotificationType::ReservationCreated,
                entityKey: 'reservation:'.$reservation->id,
                placeholders: $placeholders,
                data: $data,
            );
        }

        $this->notifications->notifyBusinessMembers(
            businessId: $reservation->business_id,
            type: NotificationType::BusinessBookingReceived,
            entityKey: 'reservation:'.$reservation->id,
            placeholders: $placeholders,
            data: $data,
            recipients: $this->recipients,
        );
    }

    public function handleReservationStatusChanged(ReservationStatusChanged $event): void
    {
        $reservation = $event->reservation->loadMissing(['business', 'resource', 'customer']);
        $placeholders = $this->payloadBuilder->reservationPlaceholders($reservation);
        $data = $this->payloadBuilder->reservationData($reservation);
        $entityKey = 'reservation:'.$reservation->id.':status:'.$event->toStatus->value;

        $type = match ($event->toStatus) {
            ReservationStatus::Confirmed => NotificationType::ReservationConfirmed,
            ReservationStatus::Cancelled => NotificationType::ReservationCancelled,
            ReservationStatus::Completed => NotificationType::ReservationCompleted,
            ReservationStatus::NoShow => NotificationType::ReservationNoShow,
            default => null,
        };

        if ($type === null || $reservation->customer === null) {
            return;
        }

        $channels = match ($type) {
            NotificationType::ReservationConfirmed,
            NotificationType::ReservationCancelled => [NotificationChannel::Database, NotificationChannel::Push],
            default => null,
        };

        $this->notifications->notifyUser(
            user: $reservation->customer,
            type: $type,
            entityKey: $entityKey,
            placeholders: $placeholders,
            data: $data,
            channels: $channels,
        );

        if ($type === NotificationType::ReservationCancelled
            && $reservation->cancelled_by_actor_type === CancelledByActorType::Customer) {
            $this->notifications->notifyBusinessMembers(
                businessId: $reservation->business_id,
                type: NotificationType::BusinessBookingCancelled,
                entityKey: $entityKey,
                placeholders: $placeholders,
                data: $data,
                recipients: $this->recipients,
            );
        }
    }
}
