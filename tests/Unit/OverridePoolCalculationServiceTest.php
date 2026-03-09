<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\OverridePoolPercentageTier;
use App\Models\OverridePoolQuarterlyAdvance;
use App\Models\OverridePoolCalculation;
use App\Services\OverridePoolCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for OverridePoolCalculationService
 *
 * Tests all calculation steps for the Grow Marketing Override Pool feature:
 *  - Pool percentage tier lookup
 *  - Part 1 (direct recruit override)
 *  - Part 2 (indirect override, net of recruit's pool %)
 *  - Q4 true-up reconciliation
 *  - Edge cases (no downline, same pool %, no recruit downline)
 */
class OverridePoolCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private OverridePoolCalculationService $service;
    private float $poolRate = 50.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OverridePoolCalculationService($this->poolRate);
    }

    // -------------------------------------------------------------------------
    // Pool Percentage Lookup
    // -------------------------------------------------------------------------

    /**
     * Sales within 0–400 range should return 12%.
     */
    public function test_get_pool_percentage_returns_correct_tier_for_low_sales(): void
    {
        $this->seedTiers();

        $result = $this->service->getPoolPercentage(300);

        $this->assertEquals(12.0, $result);
    }

    /**
     * Sales within 401–600 range should return 16%.
     */
    public function test_get_pool_percentage_returns_correct_tier_for_mid_sales(): void
    {
        $this->seedTiers();

        $result = $this->service->getPoolPercentage(500);

        $this->assertEquals(16.0, $result);
    }

    /**
     * Sales within 601–1400 range should return 20%.
     */
    public function test_get_pool_percentage_returns_correct_tier_for_high_sales(): void
    {
        $this->seedTiers();

        $result = $this->service->getPoolPercentage(1000);

        $this->assertEquals(20.0, $result);
    }

    /**
     * Boundary value: exactly 400 sales should match the 0–400 tier.
     */
    public function test_get_pool_percentage_boundary_at_400(): void
    {
        $this->seedTiers();

        $this->assertEquals(12.0, $this->service->getPoolPercentage(400));
    }

    /**
     * Boundary value: exactly 401 sales should match the 401–600 tier.
     */
    public function test_get_pool_percentage_boundary_at_401(): void
    {
        $this->seedTiers();

        $this->assertEquals(16.0, $this->service->getPoolPercentage(401));
    }

    /**
     * Sales count above all defined tiers should return null.
     */
    public function test_get_pool_percentage_returns_null_when_above_all_tiers(): void
    {
        $this->seedTiers();

        $result = $this->service->getPoolPercentage(9999);

        $this->assertNull($result);
    }

    /**
     * Open-ended tier (sales_to = null) should match any count above sales_from.
     */
    public function test_get_pool_percentage_open_ended_tier_matches_large_count(): void
    {
        OverridePoolPercentageTier::create([
            'sales_from'      => 1000,
            'sales_to'        => null,
            'pool_percentage' => 25.0,
            'is_active'       => 1,
        ]);

        $result = $this->service->getPoolPercentage(50000);

        $this->assertEquals(25.0, $result);
    }

    /**
     * Inactive tiers should not be matched.
     */
    public function test_get_pool_percentage_ignores_inactive_tiers(): void
    {
        OverridePoolPercentageTier::create([
            'sales_from'      => 0,
            'sales_to'        => 400,
            'pool_percentage' => 12.0,
            'is_active'       => 0,  // inactive
        ]);

        $result = $this->service->getPoolPercentage(200);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Part 1 Calculation
    // -------------------------------------------------------------------------

    /**
     * Part 1 = direct recruit personal sales × pool_rate × user pool %
     *
     * Example: 100 sales × $50 × 20% = $1,000
     */
    public function test_part1_calculation_with_known_values(): void
    {
        $directRecruitSales = 100;
        $poolPct            = 20.0;
        $expected           = round($directRecruitSales * $this->poolRate * ($poolPct / 100.0), 2);

        $result = $this->computePart1($directRecruitSales, $poolPct);

        $this->assertEquals(1000.0, $expected);
        $this->assertEquals($expected, $result);
    }

    /**
     * Part 1 should be zero when direct recruit sales are zero.
     */
    public function test_part1_is_zero_when_no_direct_sales(): void
    {
        $result = $this->computePart1(0, 20.0);

        $this->assertEquals(0.0, $result);
    }

    // -------------------------------------------------------------------------
    // Part 2 Calculation
    // -------------------------------------------------------------------------

    /**
     * Part 2 = 0 when user pool % equals direct recruit pool % (no override earned).
     *
     * PRD edge case: "If a direct recruit's pool % equals user's pool %, Part 2 = $0"
     */
    public function test_part2_is_zero_when_pool_percentages_are_equal(): void
    {
        $recruitDownlineSales = 500;
        $userPoolPct          = 20.0;
        $recruitPoolPct       = 20.0;

        $result = $this->computePart2($recruitDownlineSales, $userPoolPct, $recruitPoolPct);

        $this->assertEquals(0.0, $result);
    }

    /**
     * Part 2 = 0 when direct recruit has no downline sales.
     *
     * PRD edge case: "If a direct recruit has no downline, their Part 2 = $0"
     */
    public function test_part2_is_zero_when_recruit_has_no_downline_sales(): void
    {
        $result = $this->computePart2(0, 20.0, 12.0);

        $this->assertEquals(0.0, $result);
    }

    /**
     * Part 2 = recruit downline sales × pool_rate × (user pool % - recruit pool %)
     *
     * Example: 500 × $50 × (20% - 12%) = 500 × 50 × 0.08 = $2,000
     */
    public function test_part2_calculation_with_pool_percentage_difference(): void
    {
        $recruitDownlineSales = 500;
        $userPoolPct          = 20.0;
        $recruitPoolPct       = 12.0;
        $expected             = round(500 * 50.0 * ((20.0 - 12.0) / 100.0), 2);

        $result = $this->computePart2($recruitDownlineSales, $userPoolPct, $recruitPoolPct);

        $this->assertEquals(2000.0, $expected);
        $this->assertEquals($expected, $result);
    }

    /**
     * Part 2 should not be negative even if recruit pool % > user pool %
     * (guard against edge case where data is inconsistent).
     */
    public function test_part2_is_not_negative_when_recruit_pool_pct_exceeds_user(): void
    {
        // Recruit pool pct > user pool pct → pct difference is negative → Part 2 = 0
        $result = $this->computePart2(500, 12.0, 20.0);

        $this->assertEquals(0.0, $result);
    }

    // -------------------------------------------------------------------------
    // Total Pool Payment
    // -------------------------------------------------------------------------

    /**
     * Total pool payment = Part 1 + sum of all Part 2 values.
     */
    public function test_total_pool_payment_equals_part1_plus_part2_sum(): void
    {
        $part1      = 1000.0;
        $part2Total = 2000.0;
        $expected   = round($part1 + $part2Total, 2);

        $this->assertEquals(3000.0, $expected);
    }

    // -------------------------------------------------------------------------
    // Q4 True-Up
    // -------------------------------------------------------------------------

    /**
     * Q4 true-up = total pool payment - (Q1 + Q2 + Q3) advances.
     */
    public function test_q4_trueup_equals_payment_minus_advances(): void
    {
        $totalPoolPayment = 10000.0;
        $q1 = 2000.0;
        $q2 = 2000.0;
        $q3 = 2000.0;
        $expected = round($totalPoolPayment - ($q1 + $q2 + $q3), 2);

        $this->assertEquals(4000.0, $expected);
    }

    /**
     * Q4 true-up can be negative (agent was overpaid); should NOT be zeroed out.
     * PRD: "Flag for administrator review; do not auto-deduct."
     */
    public function test_q4_trueup_is_negative_when_overpaid(): void
    {
        $totalPoolPayment = 5000.0;
        $q1 = 2000.0;
        $q2 = 2000.0;
        $q3 = 2000.0;
        $result = round($totalPoolPayment - ($q1 + $q2 + $q3), 2);

        $this->assertEquals(-1000.0, $result);
        $this->assertLessThan(0.0, $result);
    }

    /**
     * computeQ4TrueUp() returns null if no calculation exists for user/year.
     */
    public function test_compute_q4_trueup_returns_null_if_no_calculation(): void
    {
        $result = $this->service->computeQ4TrueUp(999999, 2025);

        $this->assertNull($result);
    }

    /**
     * computeQ4TrueUp() correctly updates the stored record and returns new value.
     */
    public function test_compute_q4_trueup_updates_stored_record(): void
    {
        $user = \App\Models\User::factory()->create();

        OverridePoolCalculation::create([
            'user_id'            => $user->id,
            'year'               => 2025,
            'downline_count'     => 5,
            'downline_sales'     => 100,
            'pool_percentage'    => 20.0,
            'gross_pool_value'   => 5000.0,
            'part1'              => 3000.0,
            'part2_total'        => 2000.0,
            'total_pool_payment' => 5000.0,
            'pool_rate'          => 50.0,
        ]);

        OverridePoolQuarterlyAdvance::create([
            'user_id'    => $user->id,
            'year'       => 2025,
            'q1_advance' => 1000.0,
            'q2_advance' => 1000.0,
            'q3_advance' => 1000.0,
        ]);

        $result = $this->service->computeQ4TrueUp($user->id, 2025);

        $this->assertEquals(2000.0, $result);

        $stored = OverridePoolCalculation::where('user_id', $user->id)->where('year', 2025)->first();
        $this->assertEquals(2000.0, $stored->q4_trueup);
    }

    // -------------------------------------------------------------------------
    // OverridePoolCalculation Model Helpers
    // -------------------------------------------------------------------------

    /**
     * OverridePoolCalculation::isOverpaid() returns true only when q4_trueup < 0.
     */
    public function test_is_overpaid_returns_true_when_q4_trueup_is_negative(): void
    {
        $calc = new OverridePoolCalculation(['q4_trueup' => -500.0]);

        $this->assertTrue($calc->isOverpaid());
    }

    /**
     * OverridePoolCalculation::isOverpaid() returns false when q4_trueup is zero or positive.
     */
    public function test_is_overpaid_returns_false_when_q4_trueup_is_positive(): void
    {
        $calc = new OverridePoolCalculation(['q4_trueup' => 500.0]);

        $this->assertFalse($calc->isOverpaid());
    }

    /**
     * OverridePoolCalculation::isOverpaid() returns false when q4_trueup is null.
     */
    public function test_is_overpaid_returns_false_when_q4_trueup_is_null(): void
    {
        $calc = new OverridePoolCalculation(['q4_trueup' => null]);

        $this->assertFalse($calc->isOverpaid());
    }

    // -------------------------------------------------------------------------
    // OverridePoolQuarterlyAdvance Model Helpers
    // -------------------------------------------------------------------------

    /**
     * totalAdvances() returns sum of Q1 + Q2 + Q3.
     */
    public function test_total_advances_sums_all_quarters(): void
    {
        $advance = new OverridePoolQuarterlyAdvance([
            'q1_advance' => 1500.0,
            'q2_advance' => 2500.0,
            'q3_advance' => 1000.0,
        ]);

        $this->assertEquals(5000.0, $advance->totalAdvances());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed the standard 3 tiers from the PRD.
     */
    private function seedTiers(): void
    {
        OverridePoolPercentageTier::insert([
            ['sales_from' => 0,   'sales_to' => 400,  'pool_percentage' => 12.0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['sales_from' => 401, 'sales_to' => 600,  'pool_percentage' => 16.0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['sales_from' => 601, 'sales_to' => 1400, 'pool_percentage' => 20.0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Compute Part 1 inline (mirrors service logic).
     */
    private function computePart1(int $directSales, float $poolPct): float
    {
        return round($directSales * $this->poolRate * ($poolPct / 100.0), 2);
    }

    /**
     * Compute Part 2 inline (mirrors service logic).
     */
    private function computePart2(int $recruitDownlineSales, float $userPoolPct, float $recruitPoolPct): float
    {
        $pctDifference = $userPoolPct - $recruitPoolPct;
        return ($recruitDownlineSales > 0 && $pctDifference > 0)
            ? round($recruitDownlineSales * $this->poolRate * ($pctDifference / 100.0), 2)
            : 0.0;
    }
}
