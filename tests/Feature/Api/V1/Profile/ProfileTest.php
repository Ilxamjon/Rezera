<?php

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class ProfileTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_user_can_view_profile(): void
    {
        $user = User::factory()->create(['name' => 'Profile User']);

        $this->getJson('/api/v1/profile', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.name', 'Profile User');
    }

    public function test_user_can_update_name_and_locale(): void
    {
        $user = User::factory()->create(['locale' => 'ru']);

        $response = $this->patchJson('/api/v1/profile', [
            'name' => 'Updated Name',
            'locale' => 'kaa',
        ], $this->authHeaders($user));

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.locale', 'kaa');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'locale' => 'kaa',
        ]);
    }
}
