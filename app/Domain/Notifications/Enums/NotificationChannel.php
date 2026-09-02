<?php

namespace App\Domain\Notifications\Enums;

enum NotificationChannel: string
{
    case Database = 'database';
    case Push = 'push';
    case Sms = 'sms';
    case Email = 'email';
    case Telegram = 'telegram';

    /**
     * @return list<self>
     */
    public static function external(): array
    {
        return [
            self::Push,
            self::Sms,
            self::Email,
            self::Telegram,
        ];
    }

    public function isExternal(): bool
    {
        return $this !== self::Database;
    }
}
