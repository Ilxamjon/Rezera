<?php

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;

class SavedSearchPolicy extends BasePolicy
{
    public function view(User $user, SavedSearch $savedSearch): bool
    {
        return $savedSearch->user_id === $user->id;
    }

    public function update(User $user, SavedSearch $savedSearch): bool
    {
        return $savedSearch->user_id === $user->id && $user->isActive();
    }

    public function delete(User $user, SavedSearch $savedSearch): bool
    {
        return $savedSearch->user_id === $user->id && $user->isActive();
    }
}
