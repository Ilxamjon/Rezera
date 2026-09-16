<?php

return [
    'public_requires_verification' => (bool) env('BUSINESS_PUBLIC_REQUIRES_VERIFICATION', true),
    'public_requires_onboarding_complete' => (bool) env('BUSINESS_PUBLIC_REQUIRES_ONBOARDING_COMPLETE', true),
    'reservation_requires_verification' => (bool) env('BUSINESS_RESERVATION_REQUIRES_VERIFICATION', true),
    'reservation_requires_onboarding_complete' => (bool) env('BUSINESS_RESERVATION_REQUIRES_ONBOARDING_COMPLETE', true),
    // After owner finishes wizard + submits verification, auto-approve & list publicly.
    // Keep false in strict production if platform review is mandatory.
    'auto_publish_on_verification_submit' => (bool) env(
        'BUSINESS_AUTO_PUBLISH_ON_VERIFICATION_SUBMIT',
        env('APP_ENV', 'production') !== 'production',
    ),
];
