<?php

namespace Tests\Feature\Api\V1\Resource;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\Resource;
use App\Models\ResourceGroup;
use Tests\PostgresTestCase;

class PublicResourceTest extends PostgresTestCase
{
    public function test_public_endpoint_returns_only_active_resources_for_approved_business(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $category = ResourceGroup::factory()->create(['business_id' => $business->id]);

        $active = Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $category->id,
            'name' => 'PC #01',
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 15_000,
        ]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Inactive,
        ]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Maintenance,
        ]);

        $response = $this->getJson('/api/v1/businesses/'.$business->id.'/resources');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $active->id)
            ->assertJsonPath('data.items.0.price', 15000)
            ->assertJsonPath('data.items.0.price_unit', 'hour')
            ->assertJsonPath('data.items.0.category.name', $category->name)
            ->assertJsonMissingPath('data.items.0.business_id');
    }

    public function test_public_endpoint_is_hidden_for_non_public_business(): void
    {
        $business = Business::factory()->draft()->create();

        $this->getJson('/api/v1/businesses/'.$business->id.'/resources')
            ->assertForbidden();
    }

    public function test_public_endpoint_supports_category_filter(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $gaming = ResourceGroup::factory()->create(['business_id' => $business->id, 'name' => 'Gaming PC']);
        $vip = ResourceGroup::factory()->create(['business_id' => $business->id, 'name' => 'VIP Room']);

        $gamingResource = Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $gaming->id,
            'status' => ResourceStatus::Active,
        ]);

        Resource::factory()->create([
            'business_id' => $business->id,
            'resource_group_id' => $vip->id,
            'status' => ResourceStatus::Active,
        ]);

        $this->getJson('/api/v1/businesses/'.$business->id.'/resources?resource_category_id='.$gaming->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $gamingResource->id);
    }
}
