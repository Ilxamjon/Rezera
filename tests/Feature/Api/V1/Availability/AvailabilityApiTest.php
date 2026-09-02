<?php

namespace Tests\Feature\Api\V1\Availability;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class AvailabilityApiTest extends PostgresTestCase
{
    use AuthenticatesUsers;
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

    public function test_public_availability_endpoint_returns_available_resources(): void
    {
        $business = $this->approvedBusiness();
        $resource = $this->createActiveResource($business, 'PC #01');

        $response = $this->getJson('/api/v1/businesses/'.$business->id.'/availability?'.http_build_query([
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.date', '2026-09-04')
            ->assertJsonPath('data.resources.0.id', $resource->id)
            ->assertJsonPath('data.resources.0.status', 'available')
            ->assertJsonPath('data.resources.0.price', 30000);
    }

    public function test_public_endpoint_hides_inactive_resources(): void
    {
        $business = $this->approvedBusiness();
        $this->createActiveResource($business, 'PC #01');
        Resource::factory()->create([
            'business_id' => $business->id,
            'name' => 'PC #02',
            'code' => 'PC-002',
            'status' => ResourceStatus::Inactive,
            'hourly_rate_amount' => 30_000,
        ]);

        $response = $this->getJson('/api/v1/businesses/'.$business->id.'/availability?'.http_build_query([
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ]));

        $response->assertOk()->assertJsonCount(1, 'data.resources');
    }

    public function test_resource_specific_endpoint_requires_same_business(): void
    {
        $businessA = $this->approvedBusiness();
        $businessB = $this->approvedBusiness();
        $foreignResource = $this->createActiveResource($businessB, 'PC #99');

        $this->getJson('/api/v1/businesses/'.$businessA->id.'/resources/'.$foreignResource->id.'/availability?'.http_build_query([
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ]))->assertNotFound();
    }

    public function test_management_endpoint_requires_membership(): void
    {
        $business = $this->approvedBusiness();
        $outsider = User::factory()->create();

        $this->getJson('/api/v1/manage/businesses/'.$business->id.'/availability?'.http_build_query([
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ]), $this->authHeaders($outsider))->assertForbidden();
    }

    public function test_management_endpoint_includes_unavailable_reasons(): void
    {
        $business = $this->approvedBusiness();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $this->createActiveResource($business, 'PC #01');
        Resource::factory()->create([
            'business_id' => $business->id,
            'name' => 'PC #03',
            'code' => 'PC-003',
            'status' => ResourceStatus::Maintenance,
            'hourly_rate_amount' => 30_000,
        ]);

        $response = $this->getJson('/api/v1/manage/businesses/'.$business->id.'/availability?'.http_build_query([
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ]), $this->authHeaders($owner));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.resources');

        $reasons = collect($response->json('data.resources'))->pluck('reason', 'name');
        $this->assertSame('maintenance', $reasons['PC #03']);
    }

    public function test_validation_rejects_past_time(): void
    {
        $business = $this->approvedBusiness();

        $this->getJson('/api/v1/businesses/'.$business->id.'/availability?'.http_build_query([
            'date' => '2026-09-04',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['start_time']);
    }

    public function test_foreign_category_is_rejected(): void
    {
        $businessA = $this->approvedBusiness();
        $businessB = $this->approvedBusiness();
        $foreignCategory = ResourceGroup::factory()->create(['business_id' => $businessB->id]);

        $this->getJson('/api/v1/businesses/'.$businessA->id.'/availability?'.http_build_query([
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
            'category_id' => $foreignCategory->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    private function approvedBusiness(): Business
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $schedule = collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => '10:00',
            'closes_at' => '02:00',
        ])->all();

        $this->seedBusinessHours($business, $schedule);

        return $business;
    }

    private function createActiveResource(Business $business, string $name): Resource
    {
        return Resource::factory()->create([
            'business_id' => $business->id,
            'name' => $name,
            'code' => strtoupper(str_replace([' ', '#'], ['-', ''], $name)),
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
        ]);
    }
}
