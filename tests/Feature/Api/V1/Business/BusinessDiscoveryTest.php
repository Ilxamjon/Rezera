<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessHour;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\ResourceGroup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\IsolatesPublicCatalog;

class BusinessDiscoveryTest extends PostgresTestCase
{
    use IsolatesPublicCatalog;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_search_by_business_name_with_q_parameter(): void
    {
        Business::factory()->create([
            'name' => 'Alpha Gaming Club',
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->create([
            'name' => 'Beta Cafe',
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?q=gaming');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Alpha Gaming Club');
    }

    public function test_search_normalizes_whitespace(): void
    {
        Business::factory()->create([
            'name' => 'Alpha Gaming Club',
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?q='.urlencode('  gaming   club  '));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_legacy_search_parameter_still_works(): void
    {
        Business::factory()->create([
            'name' => 'Alpha Gaming Club',
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?search=Alpha');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_long_search_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/businesses?q='.str_repeat('a', 121));

        $response->assertStatus(422);
    }

    public function test_category_slug_filter(): void
    {
        $gaming = BusinessCategory::factory()->create(['slug' => 'gaming-club']);
        $cafe = BusinessCategory::factory()->create(['slug' => 'cafe']);

        $gamingBusiness = Business::factory()->create([
            'category_id' => $gaming->id,
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->create([
            'category_id' => $cafe->id,
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?category=gaming-club');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $gamingBusiness->id);
    }

    public function test_inactive_category_is_excluded_from_category_list(): void
    {
        BusinessCategory::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/business-categories');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_category_list_includes_business_count(): void
    {
        $category = BusinessCategory::factory()->create(['is_active' => true]);

        Business::factory()->count(2)->create([
            'category_id' => $category->id,
            'status' => BusinessStatus::Approved,
            'is_publicly_listed' => true,
        ]);

        Business::factory()->create([
            'category_id' => $category->id,
            'status' => BusinessStatus::Suspended,
        ]);

        $response = $this->getJson('/api/v1/business-categories');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.business_count', 2);
    }

    public function test_city_filter_is_case_insensitive(): void
    {
        Business::factory()->create([
            'city' => 'Tashkent',
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->create([
            'city' => 'Samarkand',
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?city=tashkent');

        $response->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_district_filter(): void
    {
        Business::factory()->create([
            'district' => 'Yunusabad',
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->create([
            'district' => 'Chilanzar',
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?district=Yunusabad');

        $response->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_geo_search_requires_valid_coordinates(): void
    {
        $response = $this->getJson('/api/v1/businesses?latitude=120&longitude=69.28');

        $response->assertStatus(422);
    }

    public function test_geo_search_returns_distance_and_sorts_nearest_by_default(): void
    {
        $near = Business::factory()->create([
            'name' => 'Near Club',
            'latitude' => 41.311,
            'longitude' => 69.280,
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->create([
            'name' => 'Far Club',
            'latitude' => 41.500,
            'longitude' => 69.500,
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?latitude=41.310&longitude=69.279&radius=5');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $near->id)
            ->assertJsonStructure(['data' => ['items' => [['distance_km']]]]);
    }

    public function test_invalid_radius_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/businesses?latitude=41.31&longitude=69.28&radius=500');

        $response->assertStatus(422);
    }

    public function test_suspended_business_is_hidden(): void
    {
        Business::factory()->create([
            'status' => BusinessStatus::Suspended,
        ]);

        $response = $this->getJson('/api/v1/businesses');

        $response->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_private_business_is_hidden(): void
    {
        Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'is_publicly_listed' => false,
        ]);

        $response = $this->getJson('/api/v1/businesses');

        $response->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_soft_deleted_business_is_hidden(): void
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
        ]);

        $business->delete();

        $response = $this->getJson('/api/v1/businesses');

        $response->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_discovery_list_does_not_expose_internal_fields(): void
    {
        Business::factory()->create([
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses');

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.status')
            ->assertJsonMissingPath('data.items.0.rejection_reason')
            ->assertJsonStructure([
                'data' => [
                    'items' => [[
                        'id',
                        'name',
                        'short_description',
                        'cover_image_url',
                        'category',
                        'city',
                        'price_from',
                    ]],
                    'meta' => ['current_page', 'per_page', 'total'],
                ],
            ]);
    }

    public function test_price_filter_uses_starting_resource_price(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'hourly_rate_amount' => 15_000,
            'status' => ResourceStatus::Active,
        ]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'hourly_rate_amount' => 35_000,
            'status' => ResourceStatus::Active,
        ]);

        $expensive = Business::factory()->create(['status' => BusinessStatus::Approved]);
        Resource::factory()->create([
            'business_id' => $expensive->id,
            'hourly_rate_amount' => 80_000,
            'status' => ResourceStatus::Active,
        ]);

        $response = $this->getJson('/api/v1/businesses?min_price=10000&max_price=40000');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $business->id)
            ->assertJsonPath('data.items.0.price_from', 15000);
    }

    public function test_invalid_price_range_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/businesses?min_price=50000&max_price=10000');

        $response->assertStatus(422);
    }

    public function test_sort_by_name(): void
    {
        Business::factory()->create(['name' => 'Zulu Club', 'status' => BusinessStatus::Approved]);
        Business::factory()->create(['name' => 'Alpha Club', 'status' => BusinessStatus::Approved]);

        $response = $this->getJson('/api/v1/businesses?sort=name');

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Alpha Club')
            ->assertJsonPath('data.items.1.name', 'Zulu Club');
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/businesses?sort=unsafe_column');

        $response->assertStatus(422);
    }

    public function test_pagination_defaults_and_maximum(): void
    {
        Business::factory()->count(3)->create(['status' => BusinessStatus::Approved]);

        $this->getJson('/api/v1/businesses')
            ->assertOk()
            ->assertJsonPath('data.meta.per_page', 20);

        $this->getJson('/api/v1/businesses?per_page=100')
            ->assertStatus(422);
    }

    public function test_open_now_filter_uses_business_timezone(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 20:00:00', 'Asia/Tashkent'));

        $weekday = CarbonImmutable::parse('2026-09-02 20:00:00', 'Asia/Tashkent')->dayOfWeekIso;

        $openBusiness = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        BusinessHour::factory()->create([
            'business_id' => $openBusiness->id,
            'weekday' => $weekday,
            'opens_at' => '09:00:00',
            'closes_at' => '23:00:00',
        ]);

        $closedBusiness = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        BusinessHour::factory()->create([
            'business_id' => $closedBusiness->id,
            'weekday' => $weekday,
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);

        $response = $this->getJson('/api/v1/businesses?open_now=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $openBusiness->id)
            ->assertJsonPath('data.items.0.open_now', true);
    }

    public function test_availability_window_excludes_booked_resources(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 10:00:00', 'Asia/Tashkent'));

        $availableBusiness = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $resource = Resource::factory()->create([
            'business_id' => $availableBusiness->id,
            'status' => ResourceStatus::Active,
        ]);

        $bookedBusiness = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $bookedResource = Resource::factory()->create([
            'business_id' => $bookedBusiness->id,
            'status' => ResourceStatus::Active,
        ]);

        Reservation::factory()->create([
            'business_id' => $bookedBusiness->id,
            'resource_id' => $bookedResource->id,
            'start_at' => CarbonImmutable::parse('2026-09-05 19:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-05 21:00:00', 'Asia/Tashkent')->utc(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $response = $this->getJson('/api/v1/businesses?available_date=2026-09-05&available_start_time=19:00&available_end_time=21:00');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $availableBusiness->id);

        $this->assertNotNull($resource->id);
    }

    public function test_resource_type_filter(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'resource_type' => ResourceType::Console,
            'status' => ResourceStatus::Active,
        ]);

        $other = Business::factory()->create(['status' => BusinessStatus::Approved]);
        Resource::factory()->create([
            'business_id' => $other->id,
            'resource_type' => ResourceType::Pc,
            'status' => ResourceStatus::Active,
        ]);

        $response = $this->getJson('/api/v1/businesses?resource_type=console');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $business->id);
    }

    public function test_resource_category_filter(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $group = ResourceGroup::factory()->create([
            'business_id' => $business->id,
            'is_active' => true,
        ]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $group->id,
            'status' => ResourceStatus::Active,
        ]);

        $other = Business::factory()->create(['status' => BusinessStatus::Approved]);
        Resource::factory()->create([
            'business_id' => $other->id,
            'status' => ResourceStatus::Active,
        ]);

        $response = $this->getJson('/api/v1/businesses?resource_category_id='.$group->id);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $business->id);
    }

    public function test_business_detail_includes_discovery_summary_fields(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'hourly_rate_amount' => 25_000,
            'status' => ResourceStatus::Active,
        ]);

        BusinessHour::factory()->closed()->create([
            'business_id' => $business->id,
            'weekday' => CarbonImmutable::now($business->timezone)->dayOfWeekIso,
        ]);

        $response = $this->getJson('/api/v1/businesses/'.$business->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.price_from', 25000)
            ->assertJsonStructure([
                'data' => [
                    'resource_categories',
                    'resources',
                    'open_now',
                    'price_from',
                ],
            ]);
    }

    public function test_discovery_query_avoids_n_plus_one_on_categories(): void
    {
        Business::factory()->count(5)->create(['status' => BusinessStatus::Approved]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/businesses')->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, count($queries));
    }
}
