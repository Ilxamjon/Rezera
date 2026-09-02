<?php

return [
    'default_locale' => env('APP_LOCALE', 'ru'),

    'channels' => [
        'database' => ['enabled' => true],
        'push' => ['enabled' => env('NOTIFICATION_PUSH_ENABLED', false)],
        'sms' => ['enabled' => env('NOTIFICATION_SMS_ENABLED', false)],
        'email' => ['enabled' => env('NOTIFICATION_EMAIL_ENABLED', true)],
        'telegram' => ['enabled' => env('NOTIFICATION_TELEGRAM_ENABLED', false)],
    ],

    'providers' => [
        'push' => ['driver' => env('NOTIFICATION_PUSH_DRIVER', 'mock')],
        'sms' => ['driver' => env('NOTIFICATION_SMS_DRIVER', 'mock')],
        'telegram' => ['driver' => env('NOTIFICATION_TELEGRAM_DRIVER', 'mock')],
    ],

    'queue' => env('NOTIFICATION_QUEUE', 'default'),

    'retry' => [
        'tries' => (int) env('NOTIFICATION_DELIVERY_TRIES', 3),
        'backoff' => [10, 30, 60],
    ],
];
