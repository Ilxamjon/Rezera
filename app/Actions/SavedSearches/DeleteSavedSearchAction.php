<?php

namespace App\Actions\SavedSearches;

use App\Models\SavedSearch;
use App\Models\User;

final class DeleteSavedSearchAction
{
    public function execute(User $user, SavedSearch $savedSearch): void
    {
        if ($savedSearch->user_id !== $user->id) {
            abort(404);
        }

        $savedSearch->delete();
    }
}
