<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessMember;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class BusinessManagementTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_owner_can_view_my_businesses_with_role(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->draft()->create(['created_by_user_id' => $owner->id]);

        BusinessMember::query()->where('business_id', $business->id)->delete();
        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'member_role' => BusinessMemberRole::Owner,
        ]);

        $response = $this->getJson('/api/v1/manage/businesses', $this->authHeaders($owner));

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $business->id)
            ->assertJsonPath('data.items.0.my_role', BusinessMemberRole::Owner->value);
    }

    public function test_non_member_cannot_view_management_details(): void
    {
        $business = Business::factory()->create();
        $outsider = User::factory()->create();

        $response = $this->getJson('/api/v1/manage/businesses/'.$business->id, $this->authHeaders($outsider));

        $response->assertForbidden();
    }

    public function test_staff_can_view_management_details_but_cannot_update(): void
    {
        $business = Business::factory()->draft()->create();
        $staff = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $this->getJson('/api/v1/manage/businesses/'.$business->id, $this->authHeaders($staff))
            ->assertOk()
            ->assertJsonPath('data.my_role', BusinessMemberRole::Staff->value);

        $this->patchJson('/api/v1/manage/businesses/'.$business->id, [
            'name' => 'Renamed by staff',
        ], $this->authHeaders($staff))
            ->assertForbidden();
    }

    public function test_owner_and_manager_can_update_business(): void
    {
        $category = BusinessCategory::factory()->create();
        $business = Business::factory()->draft()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $manager = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $manager->id,
            'member_role' => BusinessMemberRole::Manager,
        ]);

        $this->patchJson('/api/v1/manage/businesses/'.$business->id, [
            'name' => 'Owner Updated Name',
            'category_id' => $category->id,
        ], $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.name', 'Owner Updated Name');

        $this->patchJson('/api/v1/manage/businesses/'.$business->id, [
            'description' => 'Manager updated description',
        ], $this->authHeaders($manager))
            ->assertOk()
            ->assertJsonPath('data.description', 'Manager updated description');
    }

    public function test_my_businesses_excludes_unrelated_memberships(): void
    {
        $user = User::factory()->create();
        $memberBusiness = Business::factory()->create(['created_by_user_id' => $user->id]);
        Business::factory()->create();

        $response = $this->getJson('/api/v1/manage/businesses', $this->authHeaders($user));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $memberBusiness->id);
    }
}
