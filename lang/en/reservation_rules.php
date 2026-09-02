<?php

return [
    'updated' => 'Reservation settings updated successfully.',
    'reset' => 'Reservation settings reset to defaults.',
    'duration_too_short' => 'Reservation duration must be at least :minimum minutes.',
    'duration_too_long' => 'Reservation duration cannot exceed :maximum minutes.',
    'invalid_step' => 'Reservation duration must be in :step minute increments.',
    'too_soon' => 'Reservations must be made at least :minutes minutes in advance.',
    'too_far' => 'Reservations cannot be made more than :days days in advance.',
    'same_day_disabled' => 'Same-day reservations are not allowed for this business.',
    'in_past' => 'Reservations cannot be made in the past.',
    'customer_limit_reached' => 'You have reached the maximum number of reservations allowed.',
    'customer_cancellation_not_allowed' => 'Customer cancellation is not allowed for this business.',
    'cancellation_deadline_passed' => 'The cancellation deadline for this reservation has passed.',
    'business_cancellation_not_allowed' => 'Business cancellation is not allowed by current settings.',
    'note_required' => 'A note is required when booking with this business.',
    'invalid_start_time' => 'The selected start time does not align with allowed booking increments.',
    'settings_min_exceeds_max' => 'Minimum duration cannot exceed maximum duration.',
    'settings_step_incompatible' => 'Duration step must divide minimum and maximum duration evenly.',
];
