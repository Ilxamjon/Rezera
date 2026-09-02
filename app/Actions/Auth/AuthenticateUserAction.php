<?php

namespace App\Actions\Auth;

use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticateUserAction
{
    /**
     * @return array{user: User, token: string}
     */
    public function execute(string $phone, string $password, string $deviceName = 'mobile'): array
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($user->status !== UserStatus::Active) {
            throw new InvalidCredentialsException;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user->fresh(),
            'token' => $token,
        ];
    }
}
