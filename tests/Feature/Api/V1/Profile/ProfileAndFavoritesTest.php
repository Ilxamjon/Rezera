<?php

namespace Tests\Feature\Api\V1\Profile;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessFavorite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class ProfileAndFavoritesTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_me_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/profile')->assertUnauthorized();
    }

    public function test_user_can_view_me_profile_without_admin_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
            'timezone' => 'Asia/Tashkent',
        ]);

        $this->getJson('/api/v1/me/profile', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'Profile User')
            ->assertJsonPath('data.preferred_language', $user->locale?->value)
            ->assertJsonPath('data.timezone', 'Asia/Tashkent')
            ->assertJsonMissingPath('data.platform_role')
            ->assertJsonMissingPath('data.status');
    }

    public function test_user_can_update_profile_fields(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $this->patchJson('/api/v1/me/profile', [
            'name' => 'Updated Name',
            'preferred_language' => 'uz',
            'timezone' => 'Asia/Samarkand',
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.preferred_language', 'uz')
            ->assertJsonPath('data.timezone', 'Asia/Samarkand');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'locale' => 'uz',
            'timezone' => 'Asia/Samarkand',
        ]);
    }

    public function test_profile_update_rejects_invalid_language_and_timezone(): void
    {
        $user = User::factory()->create();

        $this->patchJson('/api/v1/me/profile', [
            'preferred_language' => 'en',
        ], $this->authHeaders($user))->assertStatus(422);

        $this->patchJson('/api/v1/me/profile', [
            'timezone' => 'Invalid/Zone',
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_profile_update_clears_email_verification_on_email_change(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->patchJson('/api/v1/me/profile', [
            'email' => 'new@example.com',
        ], $this->authHeaders($user))->assertOk();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_cannot_update_protected_fields(): void
    {
        $user = User::factory()->create(['name' => 'Original']);

        $this->patchJson('/api/v1/me/profile', [
            'phone' => '+998909999999',
            'status' => 'blocked',
            'platform_role' => 'platform_admin',
        ], $this->authHeaders($user))->assertOk();

        $user->refresh();
        $this->assertSame('Original', $user->name);
        $this->assertNotSame('+998909999999', $user->phone);
        $this->assertNotSame('blocked', $user->status?->value);
    }

    public function test_legacy_profile_route_remains_available(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/profile', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'preferred_language']]);
    }

    public function test_user_can_favorite_public_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $this->postJson('/api/v1/me/favorites/businesses/'.$business->id, [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.business_id', $business->id)
            ->assertJsonPath('data.is_favorite', true);

        $this->assertDatabaseHas('business_favorites', [
            'user_id' => $user->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_favorite_is_idempotent(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/me/favorites/businesses/'.$business->id, [], $headers)->assertOk();
        $this->postJson('/api/v1/me/favorites/businesses/'.$business->id, [], $headers)->assertOk();

        $this->assertSame(1, BusinessFavorite::query()->where('user_id', $user->id)->count());
    }

    public function test_user_can_unfavorite_business(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);

        $this->deleteJson('/api/v1/me/favorites/businesses/'.$business->id, [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);

        $this->assertDatabaseMissing('business_favorites', [
            'user_id' => $user->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_unfavorite_is_idempotent(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $this->deleteJson('/api/v1/me/favorites/businesses/'.$business->id, [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_hidden_business_cannot_be_favorited(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'is_publicly_listed' => false,
        ]);

        $this->postJson('/api/v1/me/favorites/businesses/'.$business->id, [], $this->authHeaders($user))
            ->assertNotFound();
    }

    public function test_favorite_list_returns_public_businesses_only(): void
    {
        $user = User::factory()->create();
        $visible = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $hidden = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'is_publicly_listed' => false,
        ]);

        foreach ([$visible, $hidden] as $business) {
            BusinessFavorite::query()->create([
                'user_id' => $user->id,
                'business_id' => $business->id,
                'created_at' => now(),
            ]);
        }

        $this->getJson('/api/v1/me/favorites/businesses', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $visible->id)
            ->assertJsonPath('data.items.0.is_favorite', true);
    }

    public function test_authenticated_discovery_includes_is_favorite_without_n_plus_one(): void
    {
        $user = User::factory()->create();
        $favorited = Business::factory()->create(['status' => BusinessStatus::Approved, 'name' => 'Fav Club']);
        $other = Business::factory()->create(['status' => BusinessStatus::Approved, 'name' => 'Other Club']);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $favorited->id,
            'created_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/businesses', $this->authHeaders($user));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        $items = collect($response->json('data.items'));
        $this->assertTrue($items->firstWhere('id', $favorited->id)['is_favorite']);
        $this->assertFalse($items->firstWhere('id', $other->id)['is_favorite']);
        $this->assertLessThanOrEqual(8, count($queries));
    }

    public function test_guest_discovery_omits_is_favorite(): void
    {
        Business::factory()->create(['status' => BusinessStatus::Approved]);

        $this->getJson('/api/v1/businesses')
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.is_favorite');
    }

    public function test_business_detail_includes_is_favorite_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/businesses/'.$business->id, $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_favorites_discovery_filter_requires_authentication(): void
    {
        $this->getJson('/api/v1/businesses?favorites=true')->assertUnauthorized();
    }

    public function test_favorites_discovery_filter_returns_only_user_favorites(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $mine = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $theirs = Business::factory()->create(['status' => BusinessStatus::Approved]);
        Business::factory()->create(['status' => BusinessStatus::Approved]);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $mine->id,
            'created_at' => now(),
        ]);

        BusinessFavorite::query()->create([
            'user_id' => $otherUser->id,
            'business_id' => $theirs->id,
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/businesses?favorites=true', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $mine->id);
    }

    public function test_favorite_endpoints_require_authentication(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $this->getJson('/api/v1/me/favorites/businesses')->assertUnauthorized();
        $this->postJson('/api/v1/me/favorites/businesses/'.$business->id)->assertUnauthorized();
        $this->deleteJson('/api/v1/me/favorites/businesses/'.$business->id)->assertUnauthorized();
    }

    public function test_deleted_business_cannot_be_favorited(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $business->delete();

        $this->postJson('/api/v1/me/favorites/businesses/'.$business->id, [], $this->authHeaders($user))
            ->assertNotFound();
    }

    public function test_unfavorite_does_not_affect_reservations_or_reviews(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $reservation = \App\Models\Reservation::factory()->create([
            'customer_id' => $user->id,
            'business_id' => $business->id,
        ]);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);

        $this->deleteJson('/api/v1/me/favorites/businesses/'.$business->id, [], $this->authHeaders($user))
            ->assertOk();

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseMissing('business_favorites', [
            'user_id' => $user->id,
            'business_id' => $business->id,
        ]);
    }

    public function test_favorite_list_user_isolation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        BusinessFavorite::query()->create([
            'user_id' => $userA->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/me/favorites/businesses', $this->authHeaders($userB))
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_duplicate_favorite_unique_constraint_prevents_second_row(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);
    }

    public function test_favorite_list_includes_rating_summary(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/me/favorites/businesses', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $business->id)
            ->assertJsonStructure(['data' => ['items' => [['rating' => ['average', 'count']]]]]);
    }
}
