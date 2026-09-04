<?php

namespace Tests\Feature\Api\V1\Auth;

use Tests\PostgresTestCase;

class AuthThrottleTest extends PostgresTestCase
{
    public function test_login_is_rate_limited_by_auth_limiter(): void
    {
        config(['rezera.rate_limits.auth' => 2]);

        $payload = [
            'phone' => '+998901112233',
            'password' => 'wrong-password',
            'device_name' => 'test',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }
}
