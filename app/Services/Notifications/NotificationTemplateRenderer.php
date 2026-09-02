<?php

namespace App\Services\Notifications;

use App\Domain\Identity\Enums\Locale;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Models\User;

final class NotificationTemplateRenderer
{
    /**
     * @param  array<string, string>  $placeholders
     * @return array{title: string, body: string}
     */
    public function render(NotificationType $type, User $user, array $placeholders): array
    {
        $locale = $this->resolveLocale($user);

        return [
            'title' => __(
                'notifications.types.'.$type->value.'.title',
                $placeholders,
                $locale,
            ),
            'body' => __(
                'notifications.types.'.$type->value.'.body',
                $placeholders,
                $locale,
            ),
        ];
    }

    private function resolveLocale(User $user): string
    {
        $locale = $user->locale?->value ?? config('notifications.default_locale', 'ru');

        return Locale::isSupported($locale) ? $locale : Locale::default()->value;
    }
}
