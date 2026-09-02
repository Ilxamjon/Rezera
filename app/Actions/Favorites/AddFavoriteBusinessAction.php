<?php

namespace App\Actions\Favorites;

use App\Events\Favorites\BusinessFavorited;
use App\Models\Business;
use App\Models\User;
use App\Services\Favorites\FavoriteBusinessService;

final class AddFavoriteBusinessAction
{
    public function __construct(
        private readonly FavoriteBusinessService $favorites,
    ) {}

    public function execute(User $user, Business $business): bool
    {
        $wasFavorite = $this->favorites->isFavorite($user, $business);
        $isFavorite = $this->favorites->favorite($user, $business);

        if (! $wasFavorite && $isFavorite) {
            BusinessFavorited::dispatch($user, $business);
        }

        return $isFavorite;
    }
}
