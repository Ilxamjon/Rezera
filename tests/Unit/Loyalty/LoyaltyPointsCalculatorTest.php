<?php

namespace Tests\Unit\Loyalty;

use App\Models\LoyaltyProgram;
use App\Services\Loyalty\LoyaltyPointsCalculator;
use Tests\TestCase;

class LoyaltyPointsCalculatorTest extends TestCase
{
    public function test_floor_rounding_for_spend_based_points(): void
    {
        $calculator = new LoyaltyPointsCalculator;
        $program = new LoyaltyProgram([
            'is_enabled' => true,
            'earn_rate_points' => 1,
            'earn_amount' => 1000,
            'flat_points_per_reservation' => null,
        ]);

        $this->assertSame(2, $calculator->calculate($program, 2500));
        $this->assertSame(100, $calculator->calculate($program, 100_000));
    }

    public function test_flat_points_are_added(): void
    {
        $calculator = new LoyaltyPointsCalculator;
        $program = new LoyaltyProgram([
            'is_enabled' => true,
            'earn_rate_points' => 1,
            'earn_amount' => 1000,
            'flat_points_per_reservation' => 50,
        ]);

        $this->assertSame(150, $calculator->calculate($program, 100_000));
    }

    public function test_max_points_per_transaction_is_enforced(): void
    {
        $calculator = new LoyaltyPointsCalculator;
        $program = new LoyaltyProgram([
            'is_enabled' => true,
            'earn_rate_points' => 1,
            'earn_amount' => 1000,
            'max_points_per_transaction' => 50,
        ]);

        $this->assertSame(50, $calculator->calculate($program, 100_000));
    }
}
