<?php

return [

    'supported_locales' => array_filter(array_map(
        'trim',
        explode(',', env('REZERA_SUPPORTED_LOCALES', 'uz,kaa,ru'))
    )),

    'default_locale' => env('REZERA_DEFAULT_LOCALE', 'ru'),

    'default_business_timezone' => env('REZERA_DEFAULT_BUSINESS_TIMEZONE', 'Asia/Tashkent'),

    'default_currency' => env('REZERA_DEFAULT_CURRENCY', 'UZS'),

    'api' => [
        'version' => 'v1',
        'prefix' => 'api/v1',
    ],

    'rate_limits' => [
        'api' => (int) env('API_RATE_LIMIT', 60),
        'auth' => (int) env('API_AUTH_RATE_LIMIT', 10),
        'booking' => (int) env('API_BOOKING_RATE_LIMIT', 20),
    ],

    'check_in' => [
        'early_minutes_default' => (int) env('REZERA_CHECK_IN_EARLY_MINUTES', 15),
        'late_minutes_default' => (int) env('REZERA_CHECK_IN_LATE_MINUTES', 20),
    ],

    'referral' => [
        'referrer_reward_points' => (int) env('REZERA_REFERRAL_REFERRER_POINTS', 100),
        'referred_reward_points' => (int) env('REZERA_REFERRAL_REFERRED_POINTS', 50),
    ],

    'booking' => [
        'max_advance_days' => 14,
        'occupying_statuses' => ['pending', 'confirmed', 'checked_in'],
    ],

    'availability' => [
        'max_duration_minutes' => (int) env('REZERA_AVAILABILITY_MAX_DURATION_MINUTES', 1440),
    ],

    'discovery' => [
        'default_radius_km' => (float) env('REZERA_DISCOVERY_DEFAULT_RADIUS_KM', 10),
        'max_radius_km' => (float) env('REZERA_DISCOVERY_MAX_RADIUS_KM', 100),
        'default_per_page' => 20,
        'max_per_page' => 50,
        'popularity_days' => (int) env('REZERA_DISCOVERY_POPULARITY_DAYS', 30),
        'search_provider' => env('REZERA_SEARCH_PROVIDER', 'database'),
    ],

    'saved_searches' => [
        'max_per_user' => (int) env('REZERA_SAVED_SEARCHES_MAX_PER_USER', 25),
        'lookahead_days' => (int) env('REZERA_SAVED_SEARCHES_LOOKAHEAD_DAYS', 14),
        'batch_size' => (int) env('REZERA_SAVED_SEARCHES_BATCH_SIZE', 50),
        'max_businesses_per_scan' => (int) env('REZERA_SAVED_SEARCHES_MAX_BUSINESSES_PER_SCAN', 20),
        'alert_retention_days' => (int) env('REZERA_SAVED_SEARCH_ALERT_RETENTION_DAYS', 90),
        'scheduler_minutes' => (int) env('REZERA_SAVED_SEARCHES_SCHEDULER_MINUTES', 5),
    ],

];
