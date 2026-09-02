<?php

namespace Tests\Feature\Api\V1\Resource;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\Business;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class ResourceCategoryTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_owner_can_create_resource_category(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/resource-categories',
            [
                'name' => 'Gaming PC',
                'description' => 'High-end PCs',
                'icon' => 'pc',
                'color' => '#FF5733',
                'sort_order' => 1,
            ],
            $this->authHeaders($owner),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Gaming PC')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('resource_groups', [
            'business_id' => $business->id,
            'name' => 'Gaming PC',
        ]);
    }

    public function test_unauthorized_user_cannot_create_category(): void
    {
        $business = Business::factory()->create();
        $outsider = User::factory()->create();

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/resource-categories',
            ['name' => 'VIP Room'],
            $this->authHeaders($outsider),
        )->assertForbidden();
    }

    public function test_category_cannot_be_deleted_when_it_has_resources(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);
        $category = ResourceGroup::factory()->create(['business_id' => $business->id]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $category->id,
        ]);

        $this->deleteJson(
            '/api/v1/manage/businesses/'.$business->id.'/resource-categories/'.$category->id,
            [],
            $this->authHeaders($owner),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_same_category_name_is_allowed_in_different_businesses(): void
    {
        $businessA = Business::factory()->create();
        $businessB = Business::factory()->create();
        $ownerA = User::query()->findOrFail($businessA->created_by_user_id);
        $ownerB = User::query()->findOrFail($businessB->created_by_user_id);

        $this->postJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/resource-categories',
            ['name' => 'Gaming PC'],
            $this->authHeaders($ownerA),
        )->assertCreated();

        $this->postJson(
            '/api/v1/manage/businesses/'.$businessB->id.'/resource-categories',
            ['name' => 'Gaming PC'],
            $this->authHeaders($ownerB),
        )->assertCreated();
    }
}
