<?php

namespace App\Services\Favorites;

use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\Favorites\FavoriteBusinessListService;
use App\Services\Reviews\ReviewRatingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FavoriteBusinessListService
{
    public function __construct(
        private readonly ReviewRatingService $reviewRatingService,
    ) {}

    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $query = Business::query()
            ->publiclyVisible()
            ->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('is_active', true))
            ->with(['category'])
            ->withCount([
                'resources as active_resources_count' => fn (Builder $resourceQuery): Builder => $resourceQuery
                    ->where('status', ResourceStatus::Active)
                    ->whereNull('deleted_at'),
            ])
            ->addSelect([
                'price_from' => DB::table('resources')
                    ->selectRaw('MIN(hourly_rate_amount)')
                    ->whereColumn('resources.business_id', 'businesses.id')
                    ->where('status', ResourceStatus::Active->value)
                    ->whereNull('deleted_at'),
            ])
            ->join('business_favorites', function ($join) use ($user): void {
                $join->on('business_favorites.business_id', '=', 'businesses.id')
                    ->where('business_favorites.user_id', '=', $user->id);
            })
            ->select('businesses.*')
            ->orderByDesc('business_favorites.created_at');

        $this->reviewRatingService->applyAggregates($query);

        $paginator = $query->paginate($perPage);

        return $paginator->through(function (Business $business): Business {
            $business->setAttribute('is_favorite', true);

            return $business;
        });
    }
}
