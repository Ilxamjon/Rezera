<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    protected $model = BusinessHour::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'weekday' => fake()->numberBetween(1, 7),
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => '09:00:00',
            'closes_at' => '23:00:00',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'is_closed' => true,
            'is_open_24h' => false,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }

    public function open24h(): static
    {
        return $this->state(fn () => [
            'is_closed' => false,
            'is_open_24h' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
