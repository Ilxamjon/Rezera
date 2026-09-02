<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class RegistrationTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_user_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ilham',
            'phone' => '90 123 45 67',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'locale' => 'uz',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.phone', '+998901234567')
            ->assertJsonPath('data.user.locale', 'uz')
            ->assertJsonPath('data.user.platform_role', 'user')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user' => ['id', 'name']]]);

        $this->assertDatabaseHas('users', [
            'phone' => '+998901234567',
            'locale' => 'uz',
        ]);

        $user = User::query()->where('phone', '+998901234567')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+998901234567',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_invalid_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '123',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+998901234567']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'phone' => '998901234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_rejects_unsupported_locale(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+998901112233',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'locale' => 'en',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['locale']);
    }
}
