<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class BusinessMemberTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_owner_can_add_staff_member(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $staffUser = User::factory()->create(['phone' => '+998901112233']);

        $response = $this->postJson('/api/v1/manage/businesses/'.$business->id.'/members', [
            'phone' => '90 111 22 33',
            'member_role' => BusinessMemberRole::Staff->value,
        ], $this->authHeaders($owner));

        $response
            ->assertCreated()
            ->assertJsonPath('data.member_role', BusinessMemberRole::Staff->value)
            ->assertJsonPath('data.user.phone', '+998901112233');

        $this->assertDatabaseHas('business_members', [
            'business_id' => $business->id,
            'user_id' => $staffUser->id,
            'member_role' => BusinessMemberRole::Staff->value,
            'status' => BusinessMemberStatus::Active->value,
        ]);
    }

    public function test_staff_cannot_add_members(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create();
        $target = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $response = $this->postJson('/api/v1/manage/businesses/'.$business->id.'/members', [
            'phone' => $target->phone,
            'member_role' => BusinessMemberRole::Staff->value,
        ], $this->authHeaders($staff));

        $response->assertForbidden();
    }

    public function test_duplicate_membership_is_rejected(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $existing = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $existing->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $response = $this->postJson('/api/v1/manage/businesses/'.$business->id.'/members', [
            'phone' => $existing->phone,
            'member_role' => BusinessMemberRole::Staff->value,
        ], $this->authHeaders($owner));

        $response->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_manager_cannot_add_manager_without_owner_permission(): void
    {
        $business = Business::factory()->create();
        $manager = User::factory()->create();
        $target = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $manager->id,
            'member_role' => BusinessMemberRole::Manager,
        ]);

        $response = $this->postJson('/api/v1/manage/businesses/'.$business->id.'/members', [
            'phone' => $target->phone,
            'member_role' => BusinessMemberRole::Manager->value,
        ], $this->authHeaders($manager));

        $response->assertForbidden();
    }

    public function test_owner_cannot_remove_last_owner(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $ownerMember = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $response = $this->deleteJson(
            '/api/v1/manage/businesses/'.$business->id.'/members/'.$ownerMember->id,
            [],
            $this->authHeaders($owner),
        );

        $response->assertUnprocessable()->assertJsonValidationErrors(['member']);
    }

    public function test_membership_in_one_business_does_not_grant_access_to_another(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $member = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $businessA->id,
            'user_id' => $member->id,
            'member_role' => BusinessMemberRole::Manager,
        ]);

        $this->getJson('/api/v1/manage/businesses/'.$businessB->id.'/members', $this->authHeaders($member))
            ->assertForbidden();
    }
}
