<?php

namespace App\Services\Reviews;

use App\Domain\Reviews\Enums\ReviewReportStatus;
use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Business;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;

final class ReviewQueryBuilder
{
    /**
     * @return Builder<Review>
     */
    public function forBusiness(Business $business, bool $publicOnly = true): Builder
    {
        $query = Review::query()
            ->where('business_id', $business->id)
            ->with(['user:id,name,avatar_url', 'businessResponder:id,name']);

        if ($publicOnly) {
            $query->published();
        }

        return $query;
    }

    /**
     * @return Builder<Review>
     */
    public function forUser(string $userId): Builder
    {
        return Review::query()
            ->where('user_id', $userId)
            ->with(['business:id,name,cover_image_url,city'])
            ->orderByDesc('created_at');
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function applyFilters(
        Builder $query,
        ?int $rating = null,
        ?string $status = null,
        ?string $search = null,
        ?string $resourceId = null,
        ?bool $hasResponse = null,
        ?bool $reported = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): void {
        if ($rating !== null) {
            $query->where('rating', $rating);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($resourceId !== null) {
            $query->where('resource_id', $resourceId);
        }

        if ($hasResponse === true) {
            $query->whereNotNull('business_response');
        } elseif ($hasResponse === false) {
            $query->whereNull('business_response');
        }

        if ($reported === true) {
            $query->whereHas('reports', fn (Builder $builder) => $builder->where('status', ReviewReportStatus::Open));
        } elseif ($reported === false) {
            $query->whereDoesntHave('reports', fn (Builder $builder) => $builder->where('status', ReviewReportStatus::Open));
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($search !== null && $search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('title', 'ilike', $term)
                    ->orWhere('body', 'ilike', $term)
                    ->orWhereHas('reservation', fn (Builder $reservation) => $reservation->where('reservation_number', 'ilike', $term))
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'ilike', $term));
            });
        }
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function applySort(Builder $query, ?string $sort = null): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'highest' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'lowest' => $query->orderBy('rating')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function applyModerationQueue(Builder $query, ?string $queue = null): void
    {
        match ($queue) {
            'pending' => $query->where('status', ReviewStatus::Pending)->orderByDesc('created_at'),
            'reported' => $query
                ->whereHas('reports', fn (Builder $builder) => $builder->where('status', ReviewReportStatus::Open))
                ->orderByDesc('created_at'),
            'low_rating' => $query->where('rating', '<=', 2)->orderByDesc('created_at'),
            'recent' => $query->where('created_at', '>=', now()->subDays(7))->orderByDesc('created_at'),
            default => null,
        };
    }

    /**
     * @return Builder<Review>
     */
    public function adminQuery(): Builder
    {
        return Review::query()
            ->with(['business:id,name', 'user:id,name'])
            ->withCount(['reports as open_reports_count' => fn (Builder $builder) => $builder->where('status', ReviewReportStatus::Open)])
            ->orderByDesc('created_at');
    }

    public function assertBelongsToBusiness(Review $review, Business $business): void
    {
        if ($review->business_id !== $business->id) {
            abort(404);
        }
    }
}
