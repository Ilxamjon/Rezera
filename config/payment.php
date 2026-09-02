<?php

return [
    'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'UZS'),

    'providers' => [
        'mock' => [
            'enabled' => env('PAYMENT_MOCK_ENABLED', env('APP_ENV') !== 'production'),
            'allow_simulation' => env('PAYMENT_MOCK_ALLOW_SIMULATION', env('APP_ENV') === 'local' || env('APP_ENV') === 'testing'),
            'webhook_secret' => env('PAYMENT_MOCK_WEBHOOK_SECRET', 'mock-webhook-secret'),
        ],
        'payme' => [
            'enabled' => env('PAYMENT_PAYME_ENABLED', false),
            'merchant_id' => env('PAYME_MERCHANT_ID'),
            'secret' => env('PAYME_SECRET'),
        ],
        'click' => [
            'enabled' => env('PAYMENT_CLICK_ENABLED', false),
            'merchant_id' => env('CLICK_MERCHANT_ID'),
            'secret' => env('CLICK_SECRET'),
        ],
        'uzum' => [
            'enabled' => env('PAYMENT_UZUM_ENABLED', false),
            'merchant_id' => env('UZUM_MERCHANT_ID'),
            'secret' => env('UZUM_SECRET'),
        ],
        'stripe' => [
            'enabled' => env('PAYMENT_STRIPE_ENABLED', false),
            'secret_key' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'cash' => [
            'enabled' => env('PAYMENT_CASH_ENABLED', false),
        ],
    ],
];
