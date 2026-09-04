<?php

namespace Database\Factories;

use App\Models\BusinessCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCategory>
 */
class BusinessCategoryFactory extends Factory
{
    protected $model = BusinessCategory::class;

    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => [
                'uz' => ucfirst($slug).' klubi',
                'kaa' => ucfirst($slug).' klubi',
                'ru' => 'Клуб '.ucfirst($slug),
            ],
            'icon' => 'gaming',
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
