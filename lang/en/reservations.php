<?php

return [
    'conflict' => 'This resource is not available for the selected time period.',
    'not_available' => 'The selected time is not available (:reason).',
    'business_not_bookable' => 'This business is not accepting bookings.',
    'resource_not_in_business' => 'The selected resource does not belong to this business.',
    'resource_not_bookable' => 'The selected resource is not available for booking.',
    'resource_missing_price' => 'The selected resource does not have a price configured.',
    'cannot_cancel' => 'This reservation cannot be cancelled.',
    'invalid_status_transition' => 'Cannot change reservation status from :from to :to.',
    'created' => 'Reservation created successfully.',
    'cancelled' => 'Reservation cancelled successfully.',
    'pending_expired' => 'Pending reservation expired due to confirmation timeout.',
    'status_updated' => 'Reservation status updated successfully.',
    'checked_in' => 'Check-in successful.',
    'checked_out' => 'Check-out successful.',
    'date_range_too_large' => 'Date range cannot exceed :days days.',
    'check_in' => [
        'cancelled' => 'This reservation cannot be checked in.',
        'completed' => 'This reservation is already completed.',
        'already_checked_in' => 'This reservation is already checked in.',
        'invalid_status' => 'This reservation is not eligible for check-in.',
        'resource_not_bookable' => 'The resource is not available for check-in.',
        'too_early' => 'Check-in is not available yet.',
        'too_late' => 'The check-in window has expired.',
        'invalid_qr_token' => 'The QR code is invalid or has been revoked.',
        'wrong_resource_qr' => 'This QR code does not match the reserved resource.',
    ],
    'check_out' => [
        'not_checked_in' => 'This reservation has not been checked in.',
        'already_checked_out' => 'This reservation is already checked out.',
    ],
    'qr' => [
        'generated' => 'QR code generated successfully.',
        'revoked' => 'QR code revoked successfully.',
    ],
];
