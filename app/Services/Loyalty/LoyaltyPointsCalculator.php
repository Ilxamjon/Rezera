<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyProgram;

final class LoyaltyPointsCalculator
{
    /**
     * Calculate integer loyalty points from a qualifying reservation amount.
     *
     * Rounding policy: floor division (truncate toward zero) on spend-based points.
     * Example: 2,500 UZS at 1 point per 1,000 UZS = 2 points.
     */
    public function calculate(LoyaltyProgram $program, int $qualifyingAmount): int
    {
        if (! $program->is_enabled) {
            return 0;
        }

        if ($program->minimum_qualifying_amount !== null
            && $qualifyingAmount < $program->minimum_qualifying_amount) {
            return 0;
        }

        $spendPoints = 0;

        if ($program->earn_amount > 0 && $program->earn_rate_points > 0) {
            $spendPoints = intdiv($qualifyingAmount, $program->earn_amount) * $program->earn_rate_points;
        }

        $flatPoints = $program->flat_points_per_reservation ?? 0;
        $points = $spendPoints + $flatPoints;

        if ($program->max_points_per_transaction !== null) {
            $points = min($points, $program->max_points_per_transaction);
        }

        return max(0, $points);
    }

    /**
     * Calculate proportional reversal points for a partial refund.
     */
    public function proportionalReversal(int $earnedPoints, int $originalAmount, int $refundedAmount): int
    {
        if ($earnedPoints <= 0 || $originalAmount <= 0 || $refundedAmount <= 0) {
            return 0;
        }

        if ($refundedAmount >= $originalAmount) {
            return $earnedPoints;
        }

        return (int) floor($earnedPoints * ($refundedAmount / $originalAmount));
    }
}
