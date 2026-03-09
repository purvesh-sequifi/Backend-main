<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Traits\PayPeriodCalculationTrait;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Unit tests for PayPeriodCalculationTrait
 * 
 * Tests all date calculation methods for different pay frequency types
 * including edge cases like month boundaries, leap years, and year transitions.
 */
class PayPeriodCalculationTraitTest extends TestCase
{
    use PayPeriodCalculationTrait;

    /**
     * Test weekly pay period calculation (7 days)
     */
    public function test_calculate_next_weekly_period(): void
    {
        $lastPeriod = (object) [
            'pay_period_to' => '2026-01-07'
        ];

        $result = $this->calculateNextWeeklyPeriod($lastPeriod);

        $this->assertEquals('2026-01-08', $result['pay_period_from']);
        $this->assertEquals('2026-01-14', $result['pay_period_to']);
    }

    /**
     * Test weekly calculation across month boundary
     */
    public function test_weekly_period_crosses_month_boundary(): void
    {
        $lastPeriod = (object) [
            'pay_period_to' => '2026-01-31'
        ];

        $result = $this->calculateNextWeeklyPeriod($lastPeriod);

        $this->assertEquals('2026-02-01', $result['pay_period_from']);
        $this->assertEquals('2026-02-07', $result['pay_period_to']);
    }

    /**
     * Test weekly calculation across year boundary
     */
    public function test_weekly_period_crosses_year_boundary(): void
    {
        $lastPeriod = (object) [
            'pay_period_to' => '2025-12-31'
        ];

        $result = $this->calculateNextWeeklyPeriod($lastPeriod);

        $this->assertEquals('2026-01-01', $result['pay_period_from']);
        $this->assertEquals('2026-01-07', $result['pay_period_to']);
    }

    /**
     * Test monthly pay period calculation
     */
    public function test_calculate_next_monthly_period(): void
    {
        $lastPeriod = (object) [
            'pay_period_to' => '2026-01-31'
        ];

        $result = $this->calculateNextMonthlyPeriod($lastPeriod);

        $this->assertEquals('2026-02-01', $result['pay_period_from']);
        $this->assertEquals('2026-02-28', $result['pay_period_to']);
    }

    /**
     * Test monthly calculation with leap year
     */
    public function test_monthly_period_handles_leap_year(): void
    {
        $lastPeriod = (object) [
            'pay_period_to' => '2024-01-31' // 2024 is a leap year
        ];

        $result = $this->calculateNextMonthlyPeriod($lastPeriod);

        $this->assertEquals('2024-02-01', $result['pay_period_from']);
        $this->assertEquals('2024-02-29', $result['pay_period_to']); // Leap year has 29 days
    }

    /**
     * Test monthly calculation across year boundary
     */
    public function test_monthly_period_crosses_year_boundary(): void
    {
        $lastPeriod = (object) [
            'pay_period_to' => '2025-12-31'
        ];

        $result = $this->calculateNextMonthlyPeriod($lastPeriod);

        $this->assertEquals('2026-01-01', $result['pay_period_from']);
        $this->assertEquals('2026-01-31', $result['pay_period_to']);
    }

    /**
     * Test bi-weekly calculation (replicates 13-day duration)
     */
    public function test_calculate_next_additional_period_biweekly(): void
    {
        $lastPeriod = (object) [
            'pay_period_from' => '2026-01-01',
            'pay_period_to' => '2026-01-14' // 13 days duration
        ];

        $result = $this->calculateNextAdditionalPeriod($lastPeriod);

        $this->assertEquals('2026-01-15', $result['pay_period_from']);
        $this->assertEquals('2026-01-28', $result['pay_period_to']); // Same 13-day duration
    }

    /**
     * Test bi-weekly calculation with 14-day duration
     */
    public function test_biweekly_with_14_day_duration(): void
    {
        $lastPeriod = (object) [
            'pay_period_from' => '2026-01-01',
            'pay_period_to' => '2026-01-15' // 14 days duration
        ];

        $result = $this->calculateNextAdditionalPeriod($lastPeriod);

        $this->assertEquals('2026-01-16', $result['pay_period_from']);
        $this->assertEquals('2026-01-30', $result['pay_period_to']); // Same 14-day duration
    }

    /**
     * Test bi-weekly across month boundary
     */
    public function test_biweekly_crosses_month_boundary(): void
    {
        $lastPeriod = (object) [
            'pay_period_from' => '2026-01-18',
            'pay_period_to' => '2026-01-31' // 13 days
        ];

        $result = $this->calculateNextAdditionalPeriod($lastPeriod);

        $this->assertEquals('2026-02-01', $result['pay_period_from']);
        $this->assertEquals('2026-02-14', $result['pay_period_to']);
    }

    /**
     * Test that all calculation methods return correct array structure
     */
    public function test_all_methods_return_correct_structure(): void
    {
        $lastPeriod = (object) [
            'pay_period_from' => '2026-01-01',
            'pay_period_to' => '2026-01-07'
        ];

        $methods = [
            'calculateNextWeeklyPeriod',
            'calculateNextMonthlyPeriod',
            'calculateNextAdditionalPeriod'
        ];

        foreach ($methods as $method) {
            $result = $this->$method($lastPeriod);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('pay_period_from', $result);
            $this->assertArrayHasKey('pay_period_to', $result);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result['pay_period_from']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result['pay_period_to']);
        }
    }

    /**
     * Test that next period always starts the day after last period ends
     */
    public function test_next_period_starts_day_after_last_ends(): void
    {
        $lastPeriod = (object) [
            'pay_period_from' => '2026-01-01',
            'pay_period_to' => '2026-01-07'
        ];

        $weekly = $this->calculateNextWeeklyPeriod($lastPeriod);
        $monthly = $this->calculateNextMonthlyPeriod($lastPeriod);
        $additional = $this->calculateNextAdditionalPeriod($lastPeriod);

        $expectedNextDay = Carbon::parse($lastPeriod->pay_period_to)->addDay()->format('Y-m-d');

        $this->assertEquals($expectedNextDay, $weekly['pay_period_from']);
        $this->assertEquals($expectedNextDay, $monthly['pay_period_from']);
        $this->assertEquals($expectedNextDay, $additional['pay_period_from']);
    }
}
