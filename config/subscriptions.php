<?php

return [
    'default_plan' => env('SUBSCRIPTION_DEFAULT_PLAN', 'free'),
    'trial_enabled' => (bool) env('SUBSCRIPTION_TRIAL_ENABLED', true),
    'grace_period_days' => (int) env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 3),
    'supported_intervals' => ['monthly', 'yearly'],
];
