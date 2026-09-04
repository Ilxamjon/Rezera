<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUserAction
{
    public function execute(User $user, ?string $plainTextToken = null): void
    {
        if ($plainTextToken !== null) {
            PersonalAccessToken::findToken($plainTextToken)?->delete();

            return;
        }

        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }
    }
}
