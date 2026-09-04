<?php

namespace Database\Factories;

use App\Domain\Notifications\Enums\DevicePlatform;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => fake()->uuid(),
            'platform' => DevicePlatform::Android,
            'push_token' => 'push_'.fake()->sha1(),
            'app_version' => '1.0.0',
            'locale' => 'ru',
            'last_seen_at' => now(),
            'is_active' => true,
        ];
    }
}
