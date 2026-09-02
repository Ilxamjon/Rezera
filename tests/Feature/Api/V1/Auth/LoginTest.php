<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class LoginTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_user_can_login_with_normalized_phone_formats(): void
    {
        User::factory()->create([
            'phone' => '+998909876543',
            'password' => Hash::make('secret-pass'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '90 987 65 43',
            'password' => 'secret-pass',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phone', '+998909876543')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_returns_generic_error_for_wrong_password(): void
    {
        User::factory()->create([
            'phone' => '+998901111111',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998901111111',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_login_returns_generic_error_for_unknown_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998907777777',
            'password' => 'password123',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_blocked_user_cannot_login(): void
    {
        User::factory()->create([
            'phone' => '+998902222222',
            'password' => Hash::make('password123'),
            'status' => UserStatus::Blocked,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+998902222222',
            'password' => 'password123',
        ]);

        $response->assertUnauthorized()->assertJsonPath('code', 'invalid_credentials');
    }
}
