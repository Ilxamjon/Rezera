<?php

namespace Tests\Feature\Api\V1\Business;

use App\Models\BusinessCategory;
use Tests\PostgresTestCase;

class BusinessCategoryTest extends PostgresTestCase
{
    public function test_public_user_can_retrieve_active_categories(): void
    {
        $active = BusinessCategory::factory()->create([
            'slug' => 'gaming-club',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        BusinessCategory::factory()->create([
            'slug' => 'inactive-type',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $response = $this->getJson('/api/v1/business-categories');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'localized_name', 'icon', 'sort_order']]]);
    }

    public function test_localized_name_respects_accept_language_header(): void
    {
        BusinessCategory::factory()->create([
            'name' => [
                'uz' => 'O\'yin klubi',
                'kaa' => 'Oyin klubi',
                'ru' => 'Игровой клуб',
            ],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/business-categories', [
            'Accept-Language' => 'uz',
        ]);

        $response->assertJsonPath('data.0.localized_name', 'O\'yin klubi');
    }
}
