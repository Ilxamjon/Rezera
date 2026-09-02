<?php

return [
    'defaults' => [
        'confirmation_mode' => 'instant',
        'min_duration_minutes' => 30,
        'max_duration_minutes' => 480,
        'duration_step_minutes' => 30,
        'min_advance_minutes' => 0,
        'max_advance_days' => 30,
        'cancellation_deadline_minutes' => 60,
        'pending_expiry_minutes' => 30,
        'check_in_early_minutes' => 15,
        'no_show_grace_minutes' => 15,
        'buffer_minutes' => 0,
        'customer_can_cancel' => true,
        'business_can_cancel' => true,
        'allow_same_day_reservations' => true,
        'max_active_reservations_per_customer' => null,
        'max_daily_reservations_per_customer' => null,
        'require_customer_note' => false,
    ],
];
