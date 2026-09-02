<?php

namespace Tests\Feature\Services\Availability;

use App\Domain\Availability\Enums\AvailabilityReason;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Services\Availability\AvailabilityEngine;
use App\Services\Availability\AvailabilityQuery;
use Carbon\CarbonImmutable;
use Tests\PostgresTestCase;
use Tests\Support\BuildsWorkingHoursSchedule;
use Tests\Support\SeedsBusinessHours;

class AvailabilityEngineTest extends PostgresTestCase
{
    use BuildsWorkingHoursSchedule;
    use SeedsBusinessHours;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 12:00:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_active_resource_is_available_during_working_hours(): void
    {
        $business = $this->createBusinessWithHours();
        $resource = $this->createResource($business, 'PC #01');

        $result = $this->engine()->calculate($this->query($business, '20:00', '22:00'));

        $this->assertNull($result->requestLevelReason);
        $this->assertSame(AvailabilityReason::Available, $result->resources[0]->reason);
    }

    public function test_inactive_and_maintenance_resources_are_unavailable(): void
    {
        $business = $this->createBusinessWithHours();
        $inactive = $this->createResource($business, 'PC #02', ResourceStatus::Inactive);
        $maintenance = $this->createResource($business, 'PC #03', ResourceStatus::Maintenance);

        $result = $this->engine()->calculate($this->query($business, '20:00', '22:00', managementView: true));

        $reasons = collect($result->resources)->keyBy(fn ($item) => $item->resource->id);

        $this->assertSame(AvailabilityReason::Inactive, $reasons[$inactive->id]->reason);
        $this->assertSame(AvailabilityReason::Maintenance, $reasons[$maintenance->id]->reason);
    }

    public function test_closed_day_marks_request_outside_working_hours(): void
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $schedule = $this->defaultWorkingHoursPayload();
        $schedule[4] = ['weekday' => 5, 'is_closed' => true, 'is_open_24h' => false, 'opens_at' => null, 'closes_at' => null];
        $this->seedBusinessHours($business, $schedule);
        $this->createResource($business, 'PC #01');

        $result = $this->engine()->calculate($this->query($business, '20:00', '22:00'));

        $this->assertSame(AvailabilityReason::BusinessClosed, $result->requestLevelReason);
    }

    public function test_request_outside_working_hours_is_unavailable(): void
    {
        $business = $this->createBusinessWithHours(opens: '10:00', closes: '02:00', overnight: true);
        $this->createResource($business, 'PC #01');

        $result = $this->engine()->calculate($this->query($business, '09:00', '11:00'));

        $this->assertSame(AvailabilityReason::OutsideWorkingHours, $result->requestLevelReason);
    }

    public function test_overnight_request_inside_hours_is_available(): void
    {
        $business = $this->createBusinessWithHours(opens: '18:00', closes: '02:00', overnight: true);
        $this->createResource($business, 'PC #01');

        $result = $this->engine()->calculate($this->query($business, '23:00', '01:00'));

        $this->assertNull($result->requestLevelReason);
        $this->assertSame(AvailabilityReason::Available, $result->resources[0]->reason);
    }

    public function test_overnight_request_beyond_closing_is_unavailable(): void
    {
        $business = $this->createBusinessWithHours(opens: '18:00', closes: '02:00', overnight: true);
        $this->createResource($business, 'PC #01');

        $result = $this->engine()->calculate($this->query($business, '01:00', '03:00'));

        $this->assertSame(AvailabilityReason::OutsideWorkingHours, $result->requestLevelReason);
    }

    public function test_existing_reservation_blocks_overlap(): void
    {
        $business = $this->createBusinessWithHours();
        $resource = $this->createResource($business, 'PC #02');

        Reservation::factory()->forResource($resource)->create([
            'start_at' => CarbonImmutable::parse('2026-09-04 20:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-04 21:00:00', 'Asia/Tashkent')->utc(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $result = $this->engine()->calculate($this->query($business, '20:00', '22:00'));

        $this->assertSame(AvailabilityReason::Booked, $result->resources[0]->reason);
    }

    public function test_non_overlapping_reservation_remains_available(): void
    {
        $business = $this->createBusinessWithHours();
        $resource = $this->createResource($business, 'PC #02');

        Reservation::factory()->forResource($resource)->create([
            'start_at' => CarbonImmutable::parse('2026-09-04 18:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-04 20:00:00', 'Asia/Tashkent')->utc(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $available = $this->engine()->calculate($this->query($business, '20:00', '22:00'));
        $before = $this->engine()->calculate($this->query($business, '17:00', '18:00'));

        $this->assertSame(AvailabilityReason::Available, $available->resources[0]->reason);
        $this->assertSame(AvailabilityReason::Available, $before->resources[0]->reason);
    }

    public function test_category_filter_limits_resources(): void
    {
        $business = $this->createBusinessWithHours();
        $gaming = ResourceGroup::factory()->create(['business_id' => $business->id, 'name' => 'Gaming PC']);
        $vip = ResourceGroup::factory()->create(['business_id' => $business->id, 'name' => 'VIP']);

        $this->createResource($business, 'PC #01', group: $gaming);
        $this->createResource($business, 'VIP #01', group: $vip);

        $result = $this->engine()->calculate($this->query($business, '20:00', '22:00', categoryId: $gaming->id));

        $this->assertCount(1, $result->resources);
        $this->assertSame('PC #01', $result->resources[0]->resource->name);
    }

    public function test_game_club_scenario(): void
    {
        $business = $this->createBusinessWithHours(opens: '10:00', closes: '02:00', overnight: true);

        $pc01 = $this->createResource($business, 'PC #01');
        $pc02 = $this->createResource($business, 'PC #02');
        $pc03 = $this->createResource($business, 'PC #03', ResourceStatus::Maintenance);
        $ps5 = $this->createResource($business, 'PS5 #01', type: ResourceType::Console);
        $vip = $this->createResource($business, 'VIP #01', type: ResourceType::Room);

        Reservation::factory()->forResource($pc02)->create([
            'start_at' => CarbonImmutable::parse('2026-09-04 20:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-04 21:00:00', 'Asia/Tashkent')->utc(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $result = $this->engine()->calculate($this->query($business, '20:00', '22:00', managementView: true));
        $reasons = collect($result->resources)->mapWithKeys(fn ($item) => [$item->resource->name => $item->reason]);

        $this->assertSame(AvailabilityReason::Available, $reasons['PC #01']);
        $this->assertSame(AvailabilityReason::Booked, $reasons['PC #02']);
        $this->assertSame(AvailabilityReason::Maintenance, $reasons['PC #03']);
        $this->assertSame(AvailabilityReason::Available, $reasons['PS5 #01']);
        $this->assertSame(AvailabilityReason::Available, $reasons['VIP #01']);
    }

    public function test_advance_too_soon_marks_request_unavailable(): void
    {
        $business = $this->createBusinessWithHours();
        $this->createResource($business, 'PC #01');

        $business->bookingPolicy->update([
            'min_advance_minutes' => 180,
            'duration_step_minutes' => 60,
        ]);

        $result = $this->engine()->calculate($this->query($business, '13:00', '14:00'));

        $this->assertSame(AvailabilityReason::AdvanceTooSoon, $result->requestLevelReason);
    }

    public function test_invalid_duration_marks_resource_unavailable(): void
    {
        $business = $this->createBusinessWithHours();
        $resource = $this->createResource($business, 'PC #01');

        $business->bookingPolicy->update([
            'min_duration_minutes' => 120,
            'duration_step_minutes' => 60,
        ]);

        $result = $this->engine()->calculate(new AvailabilityQuery(
            business: $business,
            date: CarbonImmutable::parse('2026-09-04', 'Asia/Tashkent'),
            startTime: '20:00',
            endTime: '21:00',
            resourceId: $resource->id,
            managementView: true,
            includeUnavailable: true,
        ));

        $this->assertSame(AvailabilityReason::InvalidDuration, $result->resources[0]->reason);
    }

    private function engine(): AvailabilityEngine
    {
        return app(AvailabilityEngine::class);
    }

    private function query(
        Business $business,
        string $start,
        string $end,
        ?string $categoryId = null,
        bool $managementView = false,
    ): AvailabilityQuery {
        return new AvailabilityQuery(
            business: $business,
            date: CarbonImmutable::parse('2026-09-04', 'Asia/Tashkent'),
            startTime: $start,
            endTime: $end,
            resourceCategoryId: $categoryId,
            managementView: $managementView,
            includeUnavailable: $managementView,
        );
    }

    private function createBusinessWithHours(
        string $opens = '10:00',
        string $closes = '02:00',
        bool $overnight = true,
    ): Business {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $schedule = collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => $opens,
            'closes_at' => $closes,
        ])->all();

        $this->seedBusinessHours($business, $schedule);

        return $business;
    }

    private function createResource(
        Business $business,
        string $name,
        ResourceStatus $status = ResourceStatus::Active,
        ?ResourceGroup $group = null,
        ResourceType $type = ResourceType::Pc,
    ): Resource {
        return Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $group?->id,
            'name' => $name,
            'code' => strtoupper(str_replace([' ', '#'], ['-', ''], $name)),
            'status' => $status,
            'resource_type' => $type,
            'hourly_rate_amount' => 30_000,
        ]);
    }
}
