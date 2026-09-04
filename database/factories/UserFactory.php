<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\Locale;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+99890'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'avatar_url' => null,
            'locale' => Locale::Russian,
            'platform_role' => PlatformRole::User,
            'status' => UserStatus::Active,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'last_login_at' => null,
        ];
    }
}
