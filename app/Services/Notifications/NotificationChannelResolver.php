<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushNotificationProviderInterface;
use App\Contracts\Notifications\SmsProviderInterface;
use App\Contracts\Notifications\TelegramNotificationProviderInterface;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Services\Notifications\Providers\FcmPushNotificationProvider;
use App\Services\Notifications\Providers\MockPushNotificationProvider;
use App\Services\Notifications\Providers\MockSmsProvider;
use App\Services\Notifications\Providers\MockTelegramProvider;

final class NotificationChannelResolver
{
    public function pushProvider(): PushNotificationProviderInterface
    {
        return match (config('notifications.providers.push.driver', 'mock')) {
            'fcm' => app(FcmPushNotificationProvider::class),
            default => app(MockPushNotificationProvider::class),
        };
    }

    public function smsProvider(): SmsProviderInterface
    {
        return match (config('notifications.providers.sms.driver', 'mock')) {
            'mock' => app(MockSmsProvider::class),
            default => app(MockSmsProvider::class),
        };
    }

    public function telegramProvider(): TelegramNotificationProviderInterface
    {
        return match (config('notifications.providers.telegram.driver', 'mock')) {
            'mock' => app(MockTelegramProvider::class),
            default => app(MockTelegramProvider::class),
        };
    }

    public function providerName(NotificationChannel $channel): ?string
    {
        return match ($channel) {
            NotificationChannel::Push => config('notifications.providers.push.driver'),
            NotificationChannel::Sms => config('notifications.providers.sms.driver'),
            NotificationChannel::Telegram => config('notifications.providers.telegram.driver'),
            NotificationChannel::Email => 'mail',
            default => null,
        };
    }
}
