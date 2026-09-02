<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class MeTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_me_returns_safe_user_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Gafur',
            'phone' => '+998903333333',
        ]);

        $response = $this->getJson('/api/v1/auth/me', $this->authHeaders($user));

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Gafur')
            ->assertJsonPath('data.phone', '+998903333333')
            ->assertJsonMissing(['password'])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'locale',
                    'platform_role',
                    'capabilities',
                    'business_memberships',
                ],
            ]);
    }
}
