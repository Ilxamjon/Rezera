<?php

namespace Tests\Feature\Api\V1\Pricing;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Pricing\Enums\PricingType;
use App\Domain\Promotions\Enums\DiscountType;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class PricingEngineTest extends PostgresTestCase
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

    public function test_base_resource_price_without_rules(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/pricing/preview',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.subtotal', 60_000)
            ->assertJsonPath('data.total', 60_000);
    }

    public function test_time_based_pricing_splits_interval_at_boundary(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PricingRule::factory()->forResource($resource)->create([
            'name' => 'Day rate',
            'pricing_type' => PricingType::Hourly,
            'price' => 30_000,
            'day_of_week' => 5,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'priority' => 10,
        ]);

        PricingRule::factory()->forResource($resource)->create([
            'name' => 'Evening rate',
            'pricing_type' => PricingType::Hourly,
            'price' => 50_000,
            'day_of_week' => 5,
            'start_time' => '17:00',
            'end_time' => '23:00',
            'priority' => 10,
        ]);

        $response = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/pricing/preview',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '16:00',
                'end_time' => '19:00',
            ],
            $this->authHeaders($customer),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.subtotal', 130_000)
            ->assertJsonPath('data.segments.0.amount', 30_000)
            ->assertJsonPath('data.segments.1.amount', 100_000);
    }

    public function test_date_specific_override_beats_weekday_rule(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PricingRule::factory()->forResource($resource)->create([
            'name' => 'Saturday default',
            'pricing_type' => PricingType::Hourly,
            'price' => 60_000,
            'day_of_week' => 6,
            'start_time' => '10:00',
            'end_time' => '23:00',
        ]);

        PricingRule::factory()->forResource($resource)->create([
            'name' => 'Special Saturday',
            'pricing_type' => PricingType::Hourly,
            'price' => 80_000,
            'specific_date' => '2026-09-06',
            'start_time' => '10:00',
            'end_time' => '23:00',
            'priority' => 100,
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/pricing/preview',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-06',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.subtotal', 160_000);
    }

    public function test_higher_priority_rule_wins(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PricingRule::factory()->forResource($resource)->create([
            'name' => 'Low',
            'pricing_type' => PricingType::Hourly,
            'price' => 30_000,
            'day_of_week' => 5,
            'start_time' => '18:00',
            'end_time' => '23:00',
            'priority' => 10,
        ]);

        PricingRule::factory()->forResource($resource)->create([
            'name' => 'High',
            'pricing_type' => PricingType::Hourly,
            'price' => 70_000,
            'day_of_week' => 5,
            'start_time' => '18:00',
            'end_time' => '20:00',
            'priority' => 100,
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/pricing/preview',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '18:00',
                'end_time' => '20:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.subtotal', 140_000);
    }

    public function test_reservation_and_preview_match(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PricingRule::factory()->forResource($resource)->create([
            'pricing_type' => PricingType::Hourly,
            'price' => 50_000,
            'day_of_week' => 5,
            'start_time' => '17:00',
            'end_time' => '23:00',
        ]);

        $preview = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/pricing/preview',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )->json('data');

        $reservation = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        );

        $reservation
            ->assertCreated()
            ->assertJsonPath('data.subtotal_amount', $preview['subtotal'])
            ->assertJsonPath('data.total_amount', $preview['total']);
    }

    public function test_pricing_snapshot_preserved_after_rule_change(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $rule = PricingRule::factory()->forResource($resource)->create([
            'pricing_type' => PricingType::Hourly,
            'price' => 30_000,
            'day_of_week' => 5,
            'start_time' => '10:00',
            'end_time' => '23:00',
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )->assertCreated()->assertJsonPath('data.subtotal_amount', 60_000);

        $rule->update(['price' => 99_999]);

        $this->getJson(
            '/api/v1/me/reservations',
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.items.0.subtotal_amount', 60_000);
    }

    public function test_promo_integrates_after_advanced_pricing(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PricingRule::factory()->forResource($resource)->create([
            'pricing_type' => PricingType::Hourly,
            'price' => 50_000,
            'day_of_week' => 5,
            'start_time' => '10:00',
            'end_time' => '23:00',
        ]);

        PromoCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'TENOFF',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/pricing/preview',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
                'promo_code' => 'TENOFF',
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.subtotal', 100_000)
            ->assertJsonPath('data.discount', 10_000)
            ->assertJsonPath('data.total', 90_000);
    }

    public function test_owner_can_manage_pricing_rules(): void
    {
        [$business, $resource, $owner] = $this->bookableBusinessWithOwner();

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/pricing-rules',
            [
                'name' => 'Evening',
                'pricing_type' => PricingType::Hourly->value,
                'price' => 50_000,
                'currency' => 'UZS',
                'resource_id' => $resource->id,
                'day_of_week' => 5,
                'start_time' => '18:00',
                'end_time' => '23:00',
                'priority' => 50,
            ],
            $this->authHeaders($owner),
        )->assertCreated();

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/pricing-rules',
            $this->authHeaders($owner),
        )->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_cannot_access_pricing_rule_from_another_business(): void
    {
        [$businessA, , $ownerA] = $this->bookableBusinessWithOwner();
        [$businessB, $resourceB] = $this->bookableBusiness();

        $rule = PricingRule::factory()->forResource($resourceB)->create();

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/pricing-rules/'.$rule->id,
            $this->authHeaders($ownerA),
        )->assertNotFound();
    }

    public function test_client_cannot_manipulate_preview_totals(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/pricing/preview',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
                'subtotal' => 1,
                'total' => 1,
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.subtotal', 60_000);
    }

    /**
     * @return array{0: Business, 1: Resource}
     */
    private function bookableBusiness(array $hours = ['opens_at' => '10:00', 'closes_at' => '02:00']): array
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $schedule = collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => $hours['opens_at'],
            'closes_at' => $hours['closes_at'],
        ])->all();

        $this->seedBusinessHours($business, $schedule);

        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
            'code' => 'PC-'.Str::upper(Str::random(4)),
        ]);

        return [$business, $resource];
    }

    /**
     * @return array{0: Business, 1: Resource, 2: User}
     */
    private function bookableBusinessWithOwner(): array
    {
        [$business, $resource] = $this->bookableBusiness();
        $owner = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'member_role' => BusinessMemberRole::Owner,
        ]);

        return [$business, $resource, $owner];
    }
}
