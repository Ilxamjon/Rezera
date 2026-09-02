<?php

namespace App\Domain\Businesses;

final class BusinessOnboardingStep
{
    public const BUSINESS_PROFILE = 'business_profile';
    public const LOCATION = 'location';
    public const CONTACT_INFORMATION = 'contact_information';
    public const WORKING_HOURS = 'working_hours';
    public const RESOURCE_SETUP = 'resource_setup';
    public const PRICING_SETUP = 'pricing_setup';
    public const RESERVATION_SETTINGS = 'reservation_settings';

    /**
     * @return list<string>
     */
    public static function requiredSteps(): array
    {
        return [
            self::BUSINESS_PROFILE,
            self::LOCATION,
            self::CONTACT_INFORMATION,
            self::WORKING_HOURS,
            self::RESOURCE_SETUP,
            self::PRICING_SETUP,
            self::RESERVATION_SETTINGS,
        ];
    }
}
