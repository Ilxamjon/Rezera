<?php

return [
    'public_requires_verification' => (bool) env('BUSINESS_PUBLIC_REQUIRES_VERIFICATION', true),
    'public_requires_onboarding_complete' => (bool) env('BUSINESS_PUBLIC_REQUIRES_ONBOARDING_COMPLETE', true),
    'reservation_requires_verification' => (bool) env('BUSINESS_RESERVATION_REQUIRES_VERIFICATION', true),
    'reservation_requires_onboarding_complete' => (bool) env('BUSINESS_RESERVATION_REQUIRES_ONBOARDING_COMPLETE', true),
];
