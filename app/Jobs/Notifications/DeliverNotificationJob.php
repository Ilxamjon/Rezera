<?php

namespace App\Jobs\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Mail\NotificationEmail;
use App\Models\NotificationDelivery;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class DeliverNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public function __construct(
        public readonly string $notificationId,
        public readonly string $channel,
    ) {
        $this->tries = (int) config('notifications.retry.tries', 3);
        $this->backoff = config('notifications.retry.backoff', [10, 30, 60]);
    }

    public function handle(NotificationChannelResolver $resolver): void
    {
        $notification = UserNotification::query()
            ->with('user')
            ->find($this->notificationId);

        if ($notification === null || $notification->user === null) {
            return;
        }

        $channel = NotificationChannel::from($this->channel);
        $deliveryKey = hash('sha256', $notification->id.'|'.$channel->value);

        $delivery = NotificationDelivery::query()->firstOrCreate(
            ['idempotency_key' => $deliveryKey],
            [
                'notification_id' => $notification->id,
                'channel' => $channel,
                'provider' => $resolver->providerName($channel),
                'status' => NotificationDeliveryStatus::Pending,
            ],
        );

        if ($delivery->status === NotificationDeliveryStatus::Sent
            || $delivery->status === NotificationDeliveryStatus::Delivered) {
            return;
        }

        $delivery->update(['status' => NotificationDeliveryStatus::Processing]);

        $success = false;
        $providerMessageId = null;
        $errorMessage = null;

        try {
            match ($channel) {
                NotificationChannel::Push => (function () use ($resolver, $notification, &$success, &$providerMessageId, &$errorMessage): void {
                    $result = $resolver->pushProvider()->sendToUser(
                        $notification->user,
                        $notification->title,
                        $notification->body,
                        $notification->data ?? [],
                    );
                    $success = $result->success;
                    $providerMessageId = $result->providerMessageId;
                    $errorMessage = $result->errorMessage;
                })(),
                NotificationChannel::Sms => (function () use ($resolver, $notification, &$success, &$providerMessageId, &$errorMessage): void {
                    $result = $resolver->smsProvider()->sendSms(
                        (string) $notification->user->phone,
                        $notification->body,
                    );
                    $success = $result->success;
                    $providerMessageId = $result->providerMessageId;
                    $errorMessage = $result->errorMessage;
                })(),
                NotificationChannel::Email => (function () use ($notification, &$success, &$providerMessageId, &$errorMessage): void {
                    if ($notification->user->email === null) {
                        $success = false;
                        $errorMessage = 'Missing email';

                        return;
                    }

                    Mail::to($notification->user->email)->send(new NotificationEmail($notification));
                    $success = true;
                    $providerMessageId = 'mail_'.uniqid();
                })(),
                NotificationChannel::Telegram => (function () use ($resolver, $notification, &$success, &$providerMessageId, &$errorMessage): void {
                    $result = $resolver->telegramProvider()->sendMessage('', $notification->body, $notification->data ?? []);
                    $success = $result->success;
                    $providerMessageId = $result->providerMessageId;
                    $errorMessage = $result->errorMessage;
                })(),
                default => null,
            };
        } catch (\Throwable $exception) {
            $success = false;
            $errorMessage = $exception->getMessage();
        }

        if ($success) {
            $delivery->update([
                'status' => NotificationDeliveryStatus::Sent,
                'provider_message_id' => $providerMessageId,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            return;
        }

        $delivery->update([
            'status' => NotificationDeliveryStatus::Failed,
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);

        Log::warning('notification.delivery.failed', [
            'notification_id' => $notification->id,
            'channel' => $channel->value,
            'error' => $errorMessage,
        ]);

        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)] ?? 30);
        }
    }
}
