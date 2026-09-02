<?php

namespace App\Services\Reviews;

use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative rating summary and business aggregate synchronization.
 */
final class RatingSummaryService
{
    /**
     * @param  Builder<Business>  $query
     */
    public function applyAggregates(Builder $query): void
    {
        $query->addSelect([
            'rating_average' => DB::raw('businesses.rating_average'),
            'rating_count' => DB::raw('businesses.rating_count'),
        ]);
    }

    /**
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function summaryForBusiness(Business $business): array
    {
        $rows = DB::table('reviews')
            ->select('rating', DB::raw('COUNT(*) as total'))
            ->where('business_id', $business->id)
            ->where('status', ReviewStatus::Published->value)
            ->whereNull('deleted_at')
            ->groupBy('rating')
            ->get();

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $count = 0;
        $sum = 0;

        foreach ($rows as $row) {
            $rating = (int) $row->rating;
            $total = (int) $row->total;
            $distribution[$rating] = $total;
            $count += $total;
            $sum += $rating * $total;
        }

        return [
            'average' => $count > 0 ? round($sum / $count, 2) : null,
            'count' => $count,
            'distribution' => $distribution,
        ];
    }

    public function syncBusinessCache(Business $business): void
    {
        $summary = $this->calculateFromDatabase($business->id);

        Business::query()
            ->whereKey($business->id)
            ->update([
                'rating_average' => $summary['average'],
                'rating_count' => $summary['count'],
            ]);
    }

    public function recalculate(?string $businessId = null): int
    {
        $query = Business::query();

        if ($businessId !== null) {
            $query->whereKey($businessId);
        }

        $count = 0;

        $query->select('id')->chunkById(100, function ($businesses) use (&$count): void {
            foreach ($businesses as $business) {
                $this->syncBusinessCache($business);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @return array{average: float|null, count: int}
     */
    private function calculateFromDatabase(string $businessId): array
    {
        $row = DB::table('reviews')
            ->selectRaw('ROUND(AVG(rating)::numeric, 2) as average')
            ->selectRaw('COUNT(*) as total')
            ->where('business_id', $businessId)
            ->where('status', ReviewStatus::Published->value)
            ->whereNull('deleted_at')
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'average' => $total > 0 ? (float) $row->average : null,
            'count' => $total,
        ];
    }
}
