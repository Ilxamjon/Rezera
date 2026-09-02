<?php

namespace App\Services\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Jobs\Notifications\DeliverNotificationJob;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class NotificationService
{
    public function __construct(
        private readonly NotificationTemplateRenderer $templates,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * @param  array<string, string>  $placeholders
     * @param  array<string, mixed>  $data
     * @param  list<NotificationChannel>|null  $channels
     */
    public function notifyUser(
        User $user,
        NotificationType $type,
        string $entityKey,
        array $placeholders,
        array $data,
        ?array $channels = null,
    ): ?UserNotification {
        $idempotencyKey = $this->buildIdempotencyKey($type, $user->id, $entityKey);

        $existing = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $content = $this->templates->render($type, $user, $placeholders);
        $payload = array_merge($data, ['type' => $type->value]);

        $notification = DB::transaction(function () use ($user, $type, $idempotencyKey, $content, $payload): UserNotification {
            return UserNotification::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'channel' => NotificationChannel::Database,
                'title' => $content['title'],
                'body' => $content['body'],
                'data' => $payload,
                'status' => 'sent',
                'idempotency_key' => $idempotencyKey,
                'sent_at' => now(),
            ]);
        });

        $this->dispatchExternalDeliveries($user, $notification, $type, $channels);

        Log::info('notification.created', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'type' => $type->value,
        ]);

        return $notification;
    }

    /**
     * @param  array<string, string>  $placeholders
     * @param  array<string, mixed>  $data
     */
    public function notifyBusinessMembers(
        string $businessId,
        NotificationType $type,
        string $entityKey,
        array $placeholders,
        array $data,
        BusinessNotificationRecipientResolver $recipients,
    ): void {
        foreach ($recipients->bookingManagers($businessId) as $user) {
            $this->notifyUser(
                user: $user,
                type: $type,
                entityKey: $entityKey.':business:'.$businessId.':user:'.$user->id,
                placeholders: $placeholders,
                data: $data,
            );
        }
    }

    public function buildIdempotencyKey(NotificationType $type, string $userId, string $entityKey): string
    {
        return hash('sha256', $type->value.'|'.$userId.'|'.$entityKey);
    }

    /**
     * @param  list<NotificationChannel>|null  $channels
     */
    private function dispatchExternalDeliveries(
        User $user,
        UserNotification $notification,
        NotificationType $type,
        ?array $channels,
    ): void {
        $targetChannels = $channels ?? array_merge(
            $type->defaultChannels(),
            [NotificationChannel::Push, NotificationChannel::Email],
        );

        foreach ($targetChannels as $channel) {
            if ($channel === NotificationChannel::Database) {
                continue;
            }

            if (! $this->preferences->isChannelEnabled($user, $type, $channel)) {
                continue;
            }

            DeliverNotificationJob::dispatch($notification->id, $channel->value)
                ->onQueue(config('notifications.queue', 'default'));
        }
    }
}
