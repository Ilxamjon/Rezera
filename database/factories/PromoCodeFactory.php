<?php

namespace Database\Factories;

use App\Domain\Promotions\Enums\DiscountType;
use App\Models\Business;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PROMO##??')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'currency' => null,
            'minimum_amount' => null,
            'maximum_discount' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'per_user_limit' => null,
            'is_active' => true,
            'metadata' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function percentage(int $value = 10): static
    {
        return $this->state(fn (): array => [
            'discount_type' => DiscountType::Percentage,
            'discount_value' => $value,
            'currency' => null,
        ]);
    }

    public function fixed(int $value = 50_000): static
    {
        return $this->state(fn (): array => [
            'discount_type' => DiscountType::Fixed,
            'discount_value' => $value,
            'currency' => 'UZS',
        ]);
    }

    public function platform(): static
    {
        return $this->state(fn (): array => [
            'business_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
