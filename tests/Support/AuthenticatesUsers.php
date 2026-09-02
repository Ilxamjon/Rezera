<?php

namespace Tests\Support;

use App\Models\User;

trait AuthenticatesUsers
{
    protected function issueToken(User $user, string $deviceName = 'test-device'): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }

    protected function authHeaders(User $user, string $deviceName = 'test-device'): array
    {
        return [
            'Authorization' => 'Bearer '.$this->issueToken($user, $deviceName),
            'Accept' => 'application/json',
        ];
    }
}
