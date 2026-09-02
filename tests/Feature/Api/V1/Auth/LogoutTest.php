<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class LogoutTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_authenticated_user_can_logout_current_token(): void
    {
        $user = User::factory()->create();
        $token = $this->issueToken($user, 'device-a');
        $user->createToken('device-b');

        $this->assertSame(2, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());

        $response = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->assertUnauthorized();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }
}
