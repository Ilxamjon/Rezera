<?php

namespace App\Services\Favorites;

use App\Models\Business;
use App\Models\BusinessFavorite;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FavoriteBusinessService
{
    public function favorite(User $user, Business $business): bool
    {
        $this->assertFavoritable($business);

        try {
            BusinessFavorite::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'business_id' => $business->id,
                ],
                [
                    'created_at' => now(),
                ],
            );
        } catch (QueryException|UniqueConstraintViolationException) {
            // Concurrent duplicate favorite — unique constraint is authoritative.
        }

        return true;
    }

    public function unfavorite(User $user, Business $business): bool
    {
        BusinessFavorite::query()
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->delete();

        return false;
    }

    public function isFavorite(User $user, Business $business): bool
    {
        return BusinessFavorite::query()
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->exists();
    }

    private function assertFavoritable(Business $business): void
    {
        if (! $business->isPubliclyVisible()) {
            throw new NotFoundHttpException(__('favorites.business_not_available'));
        }
    }
}
