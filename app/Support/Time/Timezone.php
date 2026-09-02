<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Timezone conventions for Rezera.
 *
 * - Application and database instants: UTC
 * - Business working hours: local civil time in business timezone
 */
final class Timezone
{
    public static function defaultBusinessTimezone(): string
    {
        return config('rezera.default_business_timezone', 'Asia/Tashkent');
    }

    public static function assertValid(string $timezone): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException("Invalid IANA timezone: {$timezone}");
        }
    }

    public static function toUtc(CarbonImmutable $localInstant, string $businessTimezone): CarbonImmutable
    {
        self::assertValid($businessTimezone);

        return $localInstant->timezone($businessTimezone)->utc();
    }

    public static function toBusinessLocal(CarbonImmutable $utcInstant, string $businessTimezone): CarbonImmutable
    {
        self::assertValid($businessTimezone);

        return $utcInstant->utc()->timezone($businessTimezone);
    }
}
