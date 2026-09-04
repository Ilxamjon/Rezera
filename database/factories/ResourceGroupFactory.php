<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\ResourceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceGroup>
 */
class ResourceGroupFactory extends Factory
{
    protected $model = ResourceGroup::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'icon' => 'gaming',
            'color' => '#3366FF',
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
