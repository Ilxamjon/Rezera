<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessFavorite>
 */
class BusinessFavoriteFactory extends Factory
{
    protected $model = BusinessFavorite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_id' => Business::factory(),
            'created_at' => now(),
        ];
    }
}
