<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class PublicBusinessTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_public_listing_shows_only_approved_businesses(): void
    {
        $category = BusinessCategory::factory()->create();

        $visible = Business::factory()->create([
            'name' => 'Visible Arena',
            'category_id' => $category->id,
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->draft()->create([
            'name' => 'Hidden Draft Club',
            'category_id' => $category->id,
        ]);

        $response = $this->getJson('/api/v1/businesses');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $visible->id)
            ->assertJsonMissingPath('data.items.0.status');
    }

    public function test_public_listing_supports_category_filter(): void
    {
        $gaming = BusinessCategory::factory()->create(['slug' => 'gaming']);
        $cafe = BusinessCategory::factory()->create(['slug' => 'cafe']);

        $gamingBusiness = Business::factory()->create([
            'category_id' => $gaming->id,
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->create([
            'category_id' => $cafe->id,
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?category_id='.$gaming->id);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $gamingBusiness->id);
    }

    public function test_public_listing_supports_search_and_pagination(): void
    {
        Business::factory()->create([
            'name' => 'Alpha Gaming',
            'status' => BusinessStatus::Approved,
        ]);

        Business::factory()->create([
            'name' => 'Beta Cafe',
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses?search=Alpha&per_page=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.meta.per_page', 1)
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_public_can_view_approved_business_details(): void
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
        ]);

        $response = $this->getJson('/api/v1/businesses/'.$business->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $business->id)
            ->assertJsonMissingPath('data.rejection_reason')
            ->assertJsonMissingPath('data.my_role');
    }

    public function test_public_cannot_view_draft_business_details(): void
    {
        $business = Business::factory()->draft()->create();

        $response = $this->getJson('/api/v1/businesses/'.$business->id);

        $response->assertForbidden();
    }

    public function test_business_member_can_view_draft_business_details(): void
    {
        $business = Business::factory()->draft()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->getJson('/api/v1/businesses/'.$business->id, $this->authHeaders($owner));

        $response->assertOk()->assertJsonPath('data.id', $business->id);
    }
}
