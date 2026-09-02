<?php

namespace App\Actions\Favorites;

use App\Events\Favorites\BusinessUnfavorited;
use App\Models\Business;
use App\Models\User;
use App\Services\Favorites\FavoriteBusinessService;

final class RemoveFavoriteBusinessAction
{
    public function __construct(
        private readonly FavoriteBusinessService $favorites,
    ) {}

    public function execute(User $user, Business $business): bool
    {
        $wasFavorite = $this->favorites->isFavorite($user, $business);
        $isFavorite = $this->favorites->unfavorite($user, $business);

        if ($wasFavorite) {
            BusinessUnfavorited::dispatch($user, $business);
        }

        return $isFavorite;
    }
}
