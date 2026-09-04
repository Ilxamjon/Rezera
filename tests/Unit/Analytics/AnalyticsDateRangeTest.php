<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Enums\AnalyticsDatePreset;
use App\Models\Business;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsMetrics;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AnalyticsDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_today_range_uses_business_timezone(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 01:30:00', 'Asia/Tashkent'));

        $business = new Business(['timezone' => 'Asia/Tashkent']);
        $range = AnalyticsDateRange::preset($business, AnalyticsDatePreset::Today);

        $this->assertSame('2026-09-05', $range->fromLocal->toDateString());
        $this->assertSame('2026-09-05', $range->toLocal->toDateString());
    }

    public function test_previous_period_has_same_length(): void
    {
        $business = new Business(['timezone' => 'Asia/Tashkent']);
        $range = AnalyticsDateRange::fromExplicit($business, '2026-09-01', '2026-09-07');
        $previous = $range->previousPeriod();

        $this->assertSame(7, (int) ($previous->fromLocal->diffInDays($previous->toLocal->startOfDay()) + 1));
    }

    public function test_compare_percent_handles_zero_previous(): void
    {
        $result = AnalyticsMetrics::compare(100, 0);

        $this->assertSame(100, $result['change_absolute']);
        $this->assertNull($result['change_percent']);
    }
}
