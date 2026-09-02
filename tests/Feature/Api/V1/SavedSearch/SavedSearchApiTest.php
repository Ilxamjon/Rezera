<?php

namespace Tests\Feature\Api\V1\SavedSearch;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\SavedSearches\Enums\SavedSearchDateMode;
use App\Models\Business;
use App\Models\Resource;
use App\Models\SavedSearch;
use App\Models\SavedSearchAlert;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\SavedSearches\SavedSearchAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\BuildsWorkingHoursSchedule;
use Tests\Support\SeedsBusinessHours;

class SavedSearchApiTest extends PostgresTestCase
{
    use AuthenticatesUsers;
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

    public function test_saved_search_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/me/saved-searches')->assertUnauthorized();
        $this->postJson('/api/v1/me/saved-searches', [])->assertUnauthorized();
    }

    public function test_user_can_create_list_view_update_and_delete_saved_search(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $create = $this->postJson('/api/v1/me/saved-searches', [
            'name' => 'Saturday VIP',
            'business_id' => $business->id,
            'date_mode' => SavedSearchDateMode::SpecificDate->value,
            'specific_date' => '2026-09-05',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'availability_required' => true,
            'alert_enabled' => true,
            'alert_channel' => 'in_app',
        ], $this->authHeaders($user))->assertCreated();

        $id = $create->json('data.id');

        $this->getJson('/api/v1/me/saved-searches', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Saturday VIP');

        $this->getJson('/api/v1/me/saved-searches/'.$id, $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.alert.enabled', true);

        $this->patchJson('/api/v1/me/saved-searches/'.$id, [
            'name' => 'Updated Search',
            'alert_enabled' => false,
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Search')
            ->assertJsonPath('data.alert.enabled', false);

        $this->deleteJson('/api/v1/me/saved-searches/'.$id, [], $this->authHeaders($user))
            ->assertOk();

        $this->assertDatabaseMissing('user_saved_searches', ['id' => $id]);
    }

    public function test_user_cannot_access_another_users_saved_search(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $savedSearch = SavedSearch::factory()->create(['user_id' => $owner->id]);

        $this->getJson('/api/v1/me/saved-searches/'.$savedSearch->id, $this->authHeaders($other))
            ->assertForbidden();

        $this->patchJson('/api/v1/me/saved-searches/'.$savedSearch->id, [
            'name' => 'Hacked',
        ], $this->authHeaders($other))->assertForbidden();

        $this->deleteJson('/api/v1/me/saved-searches/'.$savedSearch->id, [], $this->authHeaders($other))
            ->assertForbidden();
    }

    public function test_cross_business_resource_is_rejected(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $otherBusiness = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $resource = Resource::factory()->create(['business_id' => $otherBusiness->id]);

        $this->postJson('/api/v1/me/saved-searches', [
            'name' => 'Invalid',
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'date_mode' => SavedSearchDateMode::NextAvailable->value,
            'start_time' => '18:00',
            'end_time' => '20:00',
            'availability_required' => true,
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_alert_requires_availability_flag(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/me/saved-searches', [
            'name' => 'Invalid alert',
            'date_mode' => SavedSearchDateMode::NextAvailable->value,
            'alert_enabled' => true,
            'availability_required' => false,
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_user_limit_is_enforced(): void
    {
        Config::set('rezera.saved_searches.max_per_user', 1);
        $user = User::factory()->create();
        SavedSearch::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/v1/me/saved-searches', [
            'name' => 'Second',
            'date_mode' => SavedSearchDateMode::NextAvailable->value,
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_available_slot_generates_single_alert_and_deduplicates(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);
        $this->seedBusinessHours($business, $this->defaultWorkingHoursPayload());
        $resource = Resource::factory()->create(['business_id' => $business->id, 'name' => 'VIP PC']);

        $savedSearch = SavedSearch::factory()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'date_mode' => SavedSearchDateMode::SpecificDate,
            'specific_date' => '2026-09-05',
            'start_time' => '20:00',
            'end_time' => '22:00',
            'availability_required' => true,
            'alert_enabled' => true,
        ]);

        $service = app(SavedSearchAlertService::class);

        $this->assertSame(1, $service->evaluate($savedSearch->fresh(), true));
        $this->assertSame(0, $service->evaluate($savedSearch->fresh(), true));

        $this->assertDatabaseCount('saved_search_alerts', 1);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::AvailabilityAlert->value,
        ]);
    }

    public function test_booked_slot_does_not_generate_alert(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);
        $this->seedBusinessHours($business, $this->defaultWorkingHoursPayload());
        $resource = Resource::factory()->create(['business_id' => $business->id]);

        \App\Models\Reservation::factory()->create([
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'status' => ReservationStatus::Confirmed,
            'start_at' => CarbonImmutable::parse('2026-09-05 20:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-05 22:00:00', 'Asia/Tashkent')->utc(),
        ]);

        $savedSearch = SavedSearch::factory()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'date_mode' => SavedSearchDateMode::SpecificDate,
            'specific_date' => '2026-09-05',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ]);

        $this->assertSame(0, app(SavedSearchAlertService::class)->evaluate($savedSearch, true));
        $this->assertDatabaseCount('saved_search_alerts', 0);
    }

    public function test_duplicate_alert_hash_is_blocked_by_unique_constraint(): void
    {
        $user = User::factory()->create();
        $savedSearch = SavedSearch::factory()->create(['user_id' => $user->id]);
        $business = Business::factory()->create();

        SavedSearchAlert::query()->create([
            'saved_search_id' => $savedSearch->id,
            'user_id' => $user->id,
            'business_id' => $business->id,
            'resource_id' => null,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
            'match_hash' => 'abc123',
            'status' => 'sent',
            'notified_at' => now(),
            'created_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SavedSearchAlert::query()->create([
            'saved_search_id' => $savedSearch->id,
            'user_id' => $user->id,
            'business_id' => $business->id,
            'resource_id' => null,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
            'match_hash' => 'abc123',
            'status' => 'sent',
            'notified_at' => now(),
            'created_at' => now(),
        ]);
    }
}
