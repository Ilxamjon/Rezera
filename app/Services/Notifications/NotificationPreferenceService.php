<?php

namespace App\Services\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;

final class NotificationPreferenceService
{
    /**
     * @return list<array{notification_type: string, channel: string, enabled: bool}>
     */
    public function listForUser(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (NotificationPreference $preference): string => $preference->notification_type->value.'|'.$preference->channel->value);

        $items = [];

        foreach (NotificationType::cases() as $type) {
            if (! $type->isUserConfigurable()) {
                continue;
            }

            foreach (NotificationChannel::cases() as $channel) {
                $key = $type->value.'|'.$channel->value;
                $items[] = [
                    'notification_type' => $type->value,
                    'channel' => $channel->value,
                    'enabled' => isset($stored[$key])
                        ? $stored[$key]->is_enabled
                        : $this->defaultEnabled($user, $type, $channel),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  list<array{notification_type: string, channel: string, enabled: bool}>  $preferences
     */
    public function updateForUser(User $user, array $preferences): void
    {
        foreach ($preferences as $preference) {
            $type = NotificationType::from($preference['notification_type']);
            $channel = NotificationChannel::from($preference['channel']);

            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $type,
                    'channel' => $channel,
                ],
                ['is_enabled' => (bool) $preference['enabled']],
            );
        }
    }

    public function isChannelEnabled(User $user, NotificationType $type, NotificationChannel $channel): bool
    {
        if ($channel === NotificationChannel::Database) {
            return true;
        }

        if (! config('notifications.channels.'.$channel->value.'.enabled', false)) {
            return false;
        }

        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('notification_type', $type)
            ->where('channel', $channel)
            ->first();

        if ($stored !== null) {
            return $stored->is_enabled;
        }

        return $this->defaultEnabled($user, $type, $channel);
    }

    public function defaultEnabled(User $user, NotificationType $type, NotificationChannel $channel): bool
    {
        unset($type);

        return match ($channel) {
            NotificationChannel::Database => true,
            NotificationChannel::Push => $user->devices()->where('is_active', true)->whereNotNull('push_token')->exists(),
            NotificationChannel::Email => $user->email !== null && $user->email_verified_at !== null,
            NotificationChannel::Sms => false,
            NotificationChannel::Telegram => false,
        };
    }
}
