<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_success_payload(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ok',
                    'service' => 'Rezera',
                    'version' => 'v1',
                ],
            ]);
    }
}
