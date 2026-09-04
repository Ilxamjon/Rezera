<?php

namespace Database\Factories;

use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\Business;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        $code = 'PC-'.fake()->unique()->numberBetween(1, 999);

        return [
            'business_id' => Business::factory(),
            'resource_group_id' => null,
            'name' => $code,
            'code' => $code,
            'description' => fake()->optional()->sentence(),
            'image_url' => null,
            'resource_type' => ResourceType::Pc,
            'status' => ResourceStatus::Active,
            'capacity' => 1,
            'hourly_rate_amount' => 20_000,
            'currency' => 'UZS',
            'rate_unit' => 'hour',
            'metadata' => [
                'cpu' => 'Ryzen 7',
                'gpu' => 'RTX 4070',
                'ram_gb' => 32,
            ],
            'sort_order' => 0,
        ];
    }
}
