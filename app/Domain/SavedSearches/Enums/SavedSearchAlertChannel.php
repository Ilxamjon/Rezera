<?php

namespace App\Domain\SavedSearches\Enums;

use App\Domain\Notifications\Enums\NotificationChannel;

enum SavedSearchAlertChannel: string
{
    case InApp = 'in_app';
    case Push = 'push';

    public function toNotificationChannel(): NotificationChannel
    {
        return match ($this) {
            self::InApp => NotificationChannel::Database,
            self::Push => NotificationChannel::Push,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $channel): string => $channel->value, self::cases());
    }
}
