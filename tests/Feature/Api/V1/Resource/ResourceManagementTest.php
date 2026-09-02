<?php

namespace Tests\Feature\Api\V1\Resource;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class ResourceManagementTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_owner_can_create_resource(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $category = ResourceGroup::factory()->create(['business_id' => $business->id]);

        $response = $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources',
            [
                'resource_category_id' => $category->id,
                'name' => 'PC #01',
                'code' => 'PC-001',
                'resource_type' => ResourceType::Pc->value,
                'price' => 15_000,
                'price_unit' => 'hour',
                'metadata' => ['gpu' => 'RTX 4060'],
            ],
            $this->authHeaders($owner),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'PC #01')
            ->assertJsonPath('data.price', 15000)
            ->assertJsonPath('data.metadata.gpu', 'RTX 4060');

        $this->assertDatabaseHas('resources', [
            'business_id' => $business->id,
            'code' => 'PC-001',
            'hourly_rate_amount' => 15000,
        ]);
    }

    public function test_manager_can_create_resource(): void
    {
        $business = Business::factory()->create();
        $manager = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $manager->id,
            'member_role' => BusinessMemberRole::Manager,
        ]);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources',
            [
                'name' => 'PS5 #01',
                'code' => 'PS5-001',
                'resource_type' => ResourceType::Console->value,
                'price' => 30_000,
            ],
            $this->authHeaders($manager),
        )->assertCreated();
    }

    public function test_staff_cannot_create_resource(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources',
            [
                'name' => 'PC #02',
                'code' => 'PC-002',
                'price' => 10_000,
            ],
            $this->authHeaders($staff),
        )->assertForbidden();
    }

    public function test_user_from_another_business_cannot_access_resources(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $ownerA = User::query()->findOrFail($businessA->created_by_user_id);

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessB->id.'/resources',
            $this->authHeaders($ownerA),
        )->assertForbidden();
    }

    public function test_cross_tenant_resource_id_returns_not_found_via_scoped_binding(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $ownerA = User::query()->findOrFail($businessA->created_by_user_id);
        $foreignResource = Resource::factory()->create(['business_id' => $businessB->id]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/resources/'.$foreignResource->id,
            $this->authHeaders($ownerA),
        )->assertNotFound();
    }

    public function test_resource_cannot_reference_another_business_category(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $ownerA = User::query()->findOrFail($businessA->created_by_user_id);
        $foreignCategory = ResourceGroup::factory()->create(['business_id' => $businessB->id]);

        $this->postJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/resources',
            [
                'resource_category_id' => $foreignCategory->id,
                'name' => 'PC #01',
                'code' => 'PC-001',
                'price' => 15_000,
            ],
            $this->authHeaders($ownerA),
        )->assertUnprocessable()->assertJsonValidationErrors(['resource_category_id']);
    }

    public function test_duplicate_code_within_same_business_is_rejected(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        Resource::factory()->create([
            'business_id' => $business->id,
            'code' => 'PC-001',
        ]);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources',
            [
                'name' => 'Another PC',
                'code' => 'PC-001',
                'price' => 12_000,
            ],
            $this->authHeaders($owner),
        )->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_same_code_is_allowed_in_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $ownerA = User::query()->findOrFail($businessA->created_by_user_id);
        $ownerB = User::query()->findOrFail($businessB->created_by_user_id);

        $this->postJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/resources',
            ['name' => 'PC #01', 'code' => 'PC-001', 'price' => 10_000],
            $this->authHeaders($ownerA),
        )->assertCreated();

        $this->postJson(
            '/api/v1/manage/businesses/'.$businessB->id.'/resources',
            ['name' => 'PC #01', 'code' => 'PC-001', 'price' => 12_000],
            $this->authHeaders($ownerB),
        )->assertCreated();
    }

    public function test_resource_can_be_updated_deactivated_and_marked_maintenance(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
        ]);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources/'.$resource->id,
            ['name' => 'PC #01 Pro'],
            $this->authHeaders($owner),
        )->assertOk()->assertJsonPath('data.name', 'PC #01 Pro');

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources/'.$resource->id,
            ['status' => ResourceStatus::Inactive->value],
            $this->authHeaders($owner),
        )->assertOk()->assertJsonPath('data.status', ResourceStatus::Inactive->value);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources/'.$resource->id,
            ['status' => ResourceStatus::Maintenance->value, 'price' => 20_000],
            $this->authHeaders($owner),
        )->assertOk()->assertJsonPath('data.status', ResourceStatus::Maintenance->value);
    }

    public function test_resource_soft_delete_works(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $resource = Resource::factory()->create(['business_id' => $business->id]);

        $this->deleteJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources/'.$resource->id,
            [],
            $this->authHeaders($owner),
        )->assertOk();

        $this->assertSoftDeleted('resources', ['id' => $resource->id]);
    }

    public function test_management_list_supports_filters(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $category = ResourceGroup::factory()->create(['business_id' => $business->id]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $category->id,
            'status' => ResourceStatus::Active,
            'code' => 'PC-100',
        ]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Maintenance,
            'code' => 'PC-200',
        ]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources?status=maintenance',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.code', 'PC-200');
    }
}
