<?php

namespace App\Listeners\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Events\Payments\PaymentCreated;
use App\Events\Payments\PaymentFailed;
use App\Events\Payments\PaymentRefunded;
use App\Events\Payments\PaymentSucceeded;
use App\Models\Payment;
use App\Services\Notifications\NotificationPayloadBuilder;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchPaymentNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly NotificationPayloadBuilder $payloadBuilder,
    ) {}

    public function handlePaymentCreated(PaymentCreated $event): void
    {
        $this->notifyCustomer($event->payment, NotificationType::PaymentCreated, 'payment:'.$event->payment->id.':created');
    }

    public function handlePaymentSucceeded(PaymentSucceeded $event): void
    {
        $this->notifyCustomer(
            $event->payment,
            NotificationType::PaymentSucceeded,
            'payment:'.$event->payment->id.':succeeded',
            [NotificationChannel::Database, NotificationChannel::Push],
        );
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->notifyCustomer(
            $event->payment,
            NotificationType::PaymentFailed,
            'payment:'.$event->payment->id.':failed',
            [NotificationChannel::Database, NotificationChannel::Push],
        );
    }

    public function handlePaymentRefunded(PaymentRefunded $event): void
    {
        $this->notifyCustomer($event->payment, NotificationType::PaymentRefunded, 'payment:'.$event->payment->id.':refunded');
    }

    /**
     * @param  list<NotificationChannel>|null  $channels
     */
    private function notifyCustomer(
        Payment $payment,
        NotificationType $type,
        string $entityKey,
        ?array $channels = null,
    ): void {
        $payment->loadMissing(['user', 'reservation.business', 'reservation.resource']);

        if ($payment->user === null) {
            return;
        }

        $placeholders = $this->payloadBuilder->paymentPlaceholders($payment);
        $data = $this->payloadBuilder->paymentData($payment);

        $this->notifications->notifyUser(
            user: $payment->user,
            type: $type,
            entityKey: $entityKey,
            placeholders: $placeholders,
            data: $data,
            channels: $channels,
        );
    }
}
