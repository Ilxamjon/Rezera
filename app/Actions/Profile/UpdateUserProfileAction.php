<?php

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Support\Arr;

final class UpdateUserProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): User
    {
        if (array_key_exists('preferred_language', $data)) {
            $data['locale'] = $data['preferred_language'];
            unset($data['preferred_language']);
        }

        $data = Arr::only($data, ['name', 'locale', 'email', 'timezone', 'avatar_url']);

        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        $user->fill($data);
        $user->save();

        return $user->fresh();
    }
}
