<?php

return [
    'description' => 'Payment for reservation :number',
    'provider_disabled' => 'Payment provider :provider is disabled.',
    'provider_not_implemented' => 'Payment provider :provider is not implemented yet.',
    'provider_create_failed' => 'Payment provider could not initialize the payment.',
    'not_owner' => 'You can only pay for your own reservations.',
    'reservation_not_payable' => 'This reservation is not eligible for payment.',
    'reservation_already_paid' => 'This reservation has already been paid.',
    'zero_amount' => 'This reservation does not require payment.',
    'already_active' => 'An active payment already exists for this reservation.',
    'invalid_state' => 'Payment is in an invalid state for this operation.',
    'invalid_status_transition' => 'Cannot change payment status from :from to :to.',
    'invalid_webhook_signature' => 'Invalid webhook signature.',
    'simulation_disabled' => 'Payment simulation is disabled.',
    'payment_not_found_for_webhook' => 'Payment could not be matched for webhook event.',
    'created' => 'Payment created successfully.',
    'auto_confirm_after_payment' => 'Reservation confirmed after successful payment.',
    'marked_paid_at_venue' => 'Marked as paid at venue.',
    'date_range_too_large' => 'Date range cannot exceed :days days.',
];
