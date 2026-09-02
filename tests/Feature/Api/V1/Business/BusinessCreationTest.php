<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Models\BusinessCategory;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class BusinessCreationTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_authenticated_user_can_create_business(): void
    {
        $user = User::factory()->create();
        $category = BusinessCategory::factory()->create();

        $response = $this->postJson('/api/v1/businesses', [
            'category_id' => $category->id,
            'name' => 'Cyber Arena',
            'description' => 'Premium gaming venue',
            'city' => 'Tashkent',
            'address_line' => 'Amir Temur 1',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
        ], $this->authHeaders($user));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Cyber Arena')
            ->assertJsonPath('data.status', BusinessStatus::Draft->value);

        $businessId = $response->json('data.id');

        $this->assertDatabaseHas('businesses', [
            'id' => $businessId,
            'name' => 'Cyber Arena',
            'status' => BusinessStatus::Draft->value,
            'created_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('business_members', [
            'business_id' => $businessId,
            'user_id' => $user->id,
            'member_role' => BusinessMemberRole::Owner->value,
            'status' => BusinessMemberStatus::Active->value,
        ]);

        $this->assertDatabaseHas('booking_policies', [
            'business_id' => $businessId,
        ]);
    }

    public function test_unauthenticated_user_cannot_create_business(): void
    {
        $category = BusinessCategory::factory()->create();

        $response = $this->postJson('/api/v1/businesses', [
            'category_id' => $category->id,
            'name' => 'Unauthorized Club',
        ]);

        $response->assertUnauthorized();
    }

    public function test_invalid_business_data_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/businesses', [
            'category_id' => 'not-a-uuid',
            'name' => '',
        ], $this->authHeaders($user));

        $response->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'name']);
    }
}
