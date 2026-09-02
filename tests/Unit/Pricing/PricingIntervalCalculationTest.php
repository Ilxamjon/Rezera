<?php

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\Enums\PricingType;
use App\Models\Business;
use App\Models\PricingRule;
use App\Models\Resource;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\PricingEngine;
use Carbon\CarbonImmutable;
use Tests\PostgresTestCase;

class PricingIntervalCalculationTest extends PostgresTestCase
{
    public function test_fixed_pricing_applies_once_for_full_interval(): void
    {
        $business = Business::factory()->create(['timezone' => 'Asia/Tashkent']);
        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'hourly_rate_amount' => 30_000,
        ]);

        PricingRule::factory()->forResource($resource)->create([
            'pricing_type' => PricingType::Fixed,
            'price' => 100_000,
            'day_of_week' => 5,
            'start_time' => '10:00',
            'end_time' => '23:00',
        ]);

        $start = CarbonImmutable::parse('2026-09-04 20:00:00', 'Asia/Tashkent')->utc();
        $end = CarbonImmutable::parse('2026-09-04 22:00:00', 'Asia/Tashkent')->utc();

        $result = app(PricingEngine::class)->calculate(new PricingContext(
            business: $business,
            resource: $resource,
            startAtUtc: $start,
            endAtUtc: $end,
            timezone: 'Asia/Tashkent',
        ));

        $this->assertSame(100_000, $result->subtotal);
    }
}
