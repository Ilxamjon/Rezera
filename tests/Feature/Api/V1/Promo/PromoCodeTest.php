<?php

namespace Tests\Feature\Api\V1\Promo;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Promotions\Enums\DiscountType;
use App\Domain\Promotions\Enums\PromoRedemptionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\PromoCode;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class PromoCodeTest extends PostgresTestCase
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

    public function test_business_owner_can_create_promo_code(): void
    {
        [$business, , $owner] = $this->bookableBusinessWithOwner();

        $response = $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/promo-codes',
            [
                'code' => 'game10',
                'name' => '10% off',
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 10,
            ],
            $this->authHeaders($owner),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.code', 'GAME10')
            ->assertJsonPath('data.discount_type', 'percentage');

        $this->assertDatabaseHas('promo_codes', [
            'business_id' => $business->id,
            'code' => 'GAME10',
            'usage_count' => 0,
        ]);
    }

    public function test_staff_cannot_create_promo_code(): void
    {
        [$business, , , $staff] = $this->bookableBusinessWithOwner(includeStaff: true);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/promo-codes',
            [
                'code' => 'STAFF10',
                'name' => 'Staff promo',
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 10,
            ],
            $this->authHeaders($staff),
        )->assertForbidden();
    }

    public function test_cannot_access_promo_from_another_business(): void
    {
        [$businessA, , $ownerA] = $this->bookableBusinessWithOwner();
        [$businessB] = $this->bookableBusinessWithOwner();

        $promo = PromoCode::factory()->create([
            'business_id' => $businessB->id,
            'code' => 'OTHER10',
        ]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/promo-codes/'.$promo->id,
            $this->authHeaders($ownerA),
        )->assertNotFound();
    }

    public function test_validate_promo_returns_authoritative_pricing(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PromoCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'GAME10',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
        ]);

        $response = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/promo-codes/validate',
            [
                'code' => 'game10',
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.code', 'GAME10')
            ->assertJsonPath('data.subtotal', 60_000)
            ->assertJsonPath('data.discount_amount', 6_000)
            ->assertJsonPath('data.total', 54_000);
    }

    public function test_invalid_promo_returns_reason(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/promo-codes/validate',
            [
                'code' => 'MISSING',
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'invalid_code');
    }

    public function test_minimum_amount_validation(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PromoCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'BIGONLY',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'minimum_amount' => 100_000,
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/promo-codes/validate',
            [
                'code' => 'BIGONLY',
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'minimum_amount_not_reached');
    }

    public function test_reservation_applies_promo_and_stores_snapshot(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PromoCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'SAVE10',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
        ]);

        $response = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
                'promo_code' => 'save10',
            ],
            $this->authHeaders($customer),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.subtotal_amount', 60_000)
            ->assertJsonPath('data.discount_amount', 6_000)
            ->assertJsonPath('data.total_amount', 54_000)
            ->assertJsonPath('data.promo.code', 'SAVE10');

        $this->assertDatabaseHas('reservations', [
            'customer_id' => $customer->id,
            'subtotal_amount' => 60_000,
            'discount_amount' => 6_000,
            'total_amount' => 54_000,
            'promo_code_snapshot' => 'SAVE10',
        ]);

        $this->assertDatabaseHas('promo_code_redemptions', [
            'user_id' => $customer->id,
            'discount_amount' => 6_000,
            'status' => PromoRedemptionStatus::Redeemed->value,
        ]);

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'SAVE10',
            'usage_count' => 1,
        ]);
    }

    public function test_client_cannot_manipulate_discount_or_total(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PromoCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'FIXED5K',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 5_000,
            'currency' => 'UZS',
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
                'promo_code' => 'FIXED5K',
                'discount_amount' => 50_000,
                'total_amount' => 1,
            ],
            $this->authHeaders($customer),
        )
            ->assertCreated()
            ->assertJsonPath('data.discount_amount', 5_000)
            ->assertJsonPath('data.total_amount', 55_000);
    }

    public function test_per_user_limit_prevents_second_use(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PromoCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'ONCE',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 5_000,
            'currency' => 'UZS',
            'per_user_limit' => 1,
        ]);

        $headers = $this->authHeaders($customer);
        $payload = [
            'resource_id' => $resource->id,
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
            'promo_code' => 'ONCE',
        ];

        $this->postJson('/api/v1/businesses/'.$business->id.'/reservations', $payload, $headers)
            ->assertCreated();

        $this->postJson('/api/v1/businesses/'.$business->id.'/reservations', [
            ...$payload,
            'start_time' => '22:00',
            'end_time' => '23:00',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['promo_code']);
    }

    public function test_percentage_discount_respects_maximum_cap(): void
    {
        $validator = app(\App\Services\Promotions\PromoCodeValidator::class);
        $promo = PromoCode::factory()->make([
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 20,
            'maximum_discount' => 10_000,
        ]);

        $this->assertSame(10_000, $validator->calculateDiscountAmount($promo, 200_000));
    }

    public function test_fixed_discount_cannot_exceed_subtotal(): void
    {
        $validator = app(\App\Services\Promotions\PromoCodeValidator::class);
        $promo = PromoCode::factory()->make([
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 100_000,
        ]);

        $this->assertSame(60_000, $validator->calculateDiscountAmount($promo, 60_000));
    }

    public function test_usage_limit_prevents_second_redemption(): void
    {
        [$business, $resource] = $this->bookableBusiness();

        PromoCode::factory()->create([
            'business_id' => $business->id,
            'code' => 'LAST1',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 1_000,
            'currency' => 'UZS',
            'usage_limit' => 1,
        ]);

        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
                'promo_code' => 'LAST1',
            ],
            $this->authHeaders($customerA),
        )->assertCreated();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '22:00',
                'end_time' => '23:00',
                'promo_code' => 'LAST1',
            ],
            $this->authHeaders($customerB),
        )->assertUnprocessable();

        $this->assertDatabaseCount('promo_code_redemptions', 1);
        $this->assertDatabaseHas('promo_codes', [
            'code' => 'LAST1',
            'usage_count' => 1,
        ]);
    }

    public function test_platform_admin_can_create_platform_promo(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

        $this->postJson(
            '/api/v1/admin/promo-codes',
            [
                'code' => 'PLATFORM',
                'name' => 'Platform wide',
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 5,
            ],
            $this->authHeaders($admin),
        )
            ->assertCreated()
            ->assertJsonPath('data.business_id', null);
    }

    public function test_support_cannot_create_admin_promo(): void
    {
        $support = User::factory()->create(['platform_role' => PlatformRole::Support]);

        $this->postJson(
            '/api/v1/admin/promo-codes',
            [
                'code' => 'NOPERMS',
                'name' => 'No perms',
                'discount_type' => DiscountType::Percentage->value,
                'discount_value' => 5,
            ],
            $this->authHeaders($support),
        )->assertForbidden();
    }

    public function test_platform_promo_works_for_any_business(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        PromoCode::factory()->platform()->create([
            'code' => 'ALL5',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 5,
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/promo-codes/validate',
            [
                'code' => 'ALL5',
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.discount_amount', 3_000);
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
     * @return array{0: Business, 1: Resource, 2: User, 3?: User}
     */
    private function bookableBusinessWithOwner(bool $includeStaff = false): array
    {
        [$business, $resource] = $this->bookableBusiness();
        $owner = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'member_role' => BusinessMemberRole::Owner,
        ]);

        if (! $includeStaff) {
            return [$business, $resource, $owner];
        }

        $staff = User::factory()->create();
        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        return [$business, $resource, $owner, $staff];
    }
}
