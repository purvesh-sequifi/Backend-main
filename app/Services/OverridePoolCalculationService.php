<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OverridePoolCalculation;
use App\Models\OverridePoolPercentageTier;
use App\Models\OverridePoolQuarterlyAdvance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Override Pool Calculation Service
 *
 * Implements the Grow Marketing override pool compensation logic:
 *  - Part 1: override earned on direct recruits' personal sales
 *  - Part 2: override earned on each direct recruit's deeper downline,
 *            net of that recruit's own pool percentage
 *  - Q4 true-up: annual total minus Q1-Q3 quarterly advances
 *
 * Designed to be extensible: additional rule tables (bonus thresholds,
 * rate overrides, eligibility conditions) can be injected via constructor
 * parameters or separate service classes without modifying core logic.
 */
class OverridePoolCalculationService
{
    /**
     * Dollar amount earned per sale in the downline ($50 by default for Grow Marketing).
     * Configurable to support future client variations.
     */
    private float $poolRate;

    public function __construct(float $poolRate = 50.0)
    {
        $this->poolRate = $poolRate;
    }

    // -------------------------------------------------------------------------
    // Downline Traversal
    // -------------------------------------------------------------------------

    /**
     * Get all descendant user IDs for a given user using iterative BFS.
     * Handles cycles gracefully via a visited set.
     *
     * @return int[] Array of descendant user IDs (excludes the user themselves)
     */
    public function getDownline(int $userId): array
    {
        $visited = [];
        $queue   = [$userId];

        while (!empty($queue)) {
            $current = array_shift($queue);

            $recruits = User::where('recruiter_id', $current)
                ->whereNotIn('id', array_merge([$userId], $visited))
                ->where('dismiss', '0')
                ->pluck('id')
                ->toArray();

            foreach ($recruits as $recruitId) {
                if (!in_array($recruitId, $visited, true)) {
                    $visited[] = $recruitId;
                    $queue[]   = $recruitId;
                }
            }
        }

        return $visited;
    }

    /**
     * Get only the direct (level-1) recruit IDs for a given user.
     *
     * @return int[]
     */
    public function getDirectRecruits(int $userId): array
    {
        return User::where('recruiter_id', $userId)
            ->where('dismiss', '0')
            ->pluck('id')
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Sales Counting
    // -------------------------------------------------------------------------

    /**
     * Count valid sales attributed to a set of users for a given year.
     *
     * A sale is counted when:
     *  - closer1_id is in the provided user IDs
     *  - date_cancelled IS NULL (not cancelled)
     *  - is_exempted != 1 (not exempted from override calculation)
     *  - YEAR(customer_signoff) matches the given year
     *
     * @param int[] $userIds
     */
    public function getSalesCount(array $userIds, int $year): int
    {
        if (empty($userIds)) {
            return 0;
        }

        return (int) DB::table('sale_masters')
            ->whereIn('closer1_id', $userIds)
            ->whereNull('date_cancelled')
            ->where(function ($q) {
                $q->where('is_exempted', '!=', 1)->orWhereNull('is_exempted');
            })
            ->whereYear('customer_signoff', $year)
            ->count();
    }

    // -------------------------------------------------------------------------
    // Pool Percentage Lookup
    // -------------------------------------------------------------------------

    /**
     * Look up the pool percentage for a given downline sales count from the
     * configured tiers table.
     *
     * Tiers are matched as: sales_from <= salesCount <= sales_to
     * An open-ended tier has sales_to = NULL, matching any count above sales_from.
     *
     * Returns null if no matching tier is found (caller should flag as error).
     */
    public function getPoolPercentage(int $salesCount): ?float
    {
        $tier = OverridePoolPercentageTier::active()
            ->where('sales_from', '<=', $salesCount)
            ->where(function ($q) use ($salesCount) {
                $q->whereNull('sales_to')
                  ->orWhere('sales_to', '>=', $salesCount);
            })
            ->first();

        return $tier ? (float) $tier->pool_percentage : null;
    }

    // -------------------------------------------------------------------------
    // Core Calculation
    // -------------------------------------------------------------------------

    /**
     * Calculate all override pool steps for a single user.
     *
     * @param  int[] $allPoolPercentages  Map of userId → poolPercentage (pre-computed for Part 2)
     * @return array|null Returns null if the user has no downline (ineligible).
     *
     * Returned array structure:
     * [
     *   user_id              => int,
     *   downline_count       => int,
     *   downline_sales       => int,
     *   pool_percentage      => float|null,
     *   gross_pool_value     => float,
     *   part1                => float,
     *   part2_breakdown      => [{user_id, personal_sales, downline_sales, pool_pct, part2}],
     *   part2_total          => float,
     *   total_pool_payment   => float,
     *   pool_rate            => float,
     *   calculation_error    => string|null,
     * ]
     */
    public function calculateForUser(int $userId, int $year, array $allPoolPercentages = []): ?array
    {
        $directRecruits = $this->getDirectRecruits($userId);

        // Users with no downline are excluded
        if (empty($directRecruits)) {
            return null;
        }

        $downline      = $this->getDownline($userId);
        $downlineCount = count($downline);
        $downlineSales = $this->getSalesCount($downline, $year);
        $poolPct       = $allPoolPercentages[$userId] ?? $this->getPoolPercentage($downlineSales);
        $error         = null;

        if ($poolPct === null) {
            $error = "No matching pool percentage tier for downline sales count: {$downlineSales}";
        }

        $grossPoolValue = $downlineSales * $this->poolRate;

        // --- Part 1: override on direct recruits' personal sales ---
        $directRecruitIds   = $directRecruits;
        $directPersonalSales = $this->getSalesCount($directRecruitIds, $year);
        $part1               = $poolPct !== null
            ? round($directPersonalSales * $this->poolRate * ($poolPct / 100.0), 2)
            : 0.0;

        // --- Part 2: override on each direct recruit's deeper downline ---
        $part2Breakdown = [];
        $part2Total     = 0.0;

        foreach ($directRecruitIds as $recruitId) {
            $recruitDownline = $this->getDownline($recruitId);
            $recruitDownlineSales = empty($recruitDownline) ? 0 : $this->getSalesCount($recruitDownline, $year);

            $recruitPoolPct = $allPoolPercentages[$recruitId] ?? null;
            if ($recruitPoolPct === null) {
                $recruitSalesForTier = $this->getSalesCount($this->getDownline($recruitId), $year);
                $recruitPoolPct = $this->getPoolPercentage($recruitSalesForTier) ?? 0.0;
            }

            // Part 2 = 0 if recruit has no downline, or if pool% difference <= 0
            $pctDifference = ($poolPct ?? 0.0) - $recruitPoolPct;
            $part2ForRecruit = ($recruitDownlineSales > 0 && $pctDifference > 0)
                ? round($recruitDownlineSales * $this->poolRate * ($pctDifference / 100.0), 2)
                : 0.0;

            $part2Breakdown[] = [
                'user_id'          => $recruitId,
                'personal_sales'   => $this->getSalesCount([$recruitId], $year),
                'downline_sales'   => $recruitDownlineSales,
                'pool_pct'         => $recruitPoolPct,
                'pct_difference'   => $pctDifference,
                'part2'            => $part2ForRecruit,
            ];

            $part2Total += $part2ForRecruit;
        }

        $part2Total        = round($part2Total, 2);
        $totalPoolPayment  = round($part1 + $part2Total, 2);

        return [
            'user_id'            => $userId,
            'downline_count'     => $downlineCount,
            'downline_sales'     => $downlineSales,
            'pool_percentage'    => $poolPct,
            'gross_pool_value'   => round($grossPoolValue, 2),
            'part1'              => $part1,
            'part2_breakdown'    => $part2Breakdown,
            'part2_total'        => $part2Total,
            'total_pool_payment' => $totalPoolPayment,
            'pool_rate'          => $this->poolRate,
            'calculation_error'  => $error,
        ];
    }

    /**
     * Calculate override pool for ALL eligible users in a given year.
     *
     * Two-pass algorithm:
     *  Pass 1 — Compute each eligible user's pool percentage (required for Part 2 deduction).
     *  Pass 2 — Compute Part 1, Part 2, and total pool payment using pre-computed percentages.
     *
     * Results are upserted into override_pool_calculations.
     * Q4 true-up is recomputed for users who already have advance records.
     *
     * @return array[] Array of calculation result arrays, one per eligible user
     */
    public function calculateAll(int $year): array
    {
        // Fetch all users who have at least one direct recruit
        $eligibleUserIds = User::where('dismiss', '0')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users as recruits')
                    ->whereColumn('recruits.recruiter_id', 'users.id')
                    ->where('recruits.dismiss', '0');
            })
            ->pluck('id')
            ->toArray();

        if (empty($eligibleUserIds)) {
            return [];
        }

        // --- Pass 1: Compute pool percentages for all eligible users ---
        $allPoolPercentages = [];
        foreach ($eligibleUserIds as $userId) {
            $downline      = $this->getDownline($userId);
            $downlineSales = empty($downline) ? 0 : $this->getSalesCount($downline, $year);
            $poolPct       = $this->getPoolPercentage($downlineSales);
            $allPoolPercentages[$userId] = $poolPct ?? 0.0;
        }

        // --- Pass 2: Compute full payouts ---
        $results = [];
        foreach ($eligibleUserIds as $userId) {
            $result = $this->calculateForUser($userId, $year, $allPoolPercentages);

            if ($result === null) {
                continue; // No downline — skip
            }

            // Fetch existing advance for Q4 true-up
            $advance = OverridePoolQuarterlyAdvance::where('user_id', $userId)
                ->where('year', $year)
                ->first();

            $q4Trueup = null;
            if ($advance) {
                $q4Trueup = round(
                    $result['total_pool_payment'] - $advance->totalAdvances(),
                    2
                );
            }

            // Upsert into override_pool_calculations
            OverridePoolCalculation::updateOrCreate(
                ['user_id' => $userId, 'year' => $year],
                [
                    'downline_count'     => $result['downline_count'],
                    'downline_sales'     => $result['downline_sales'],
                    'pool_percentage'    => $result['pool_percentage'],
                    'gross_pool_value'   => $result['gross_pool_value'],
                    'part1'              => $result['part1'],
                    'part2_total'        => $result['part2_total'],
                    'total_pool_payment' => $result['total_pool_payment'],
                    'pool_rate'          => $result['pool_rate'],
                    'part2_breakdown'    => $result['part2_breakdown'],
                    'q4_trueup'          => $q4Trueup,
                    'calculation_error'  => $result['calculation_error'],
                ]
            );

            $result['q4_trueup'] = $q4Trueup;
            $results[]           = $result;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Q4 True-Up
    // -------------------------------------------------------------------------

    /**
     * Recompute the Q4 true-up for a specific user after their advances are saved.
     * Updates the override_pool_calculations record and returns the new value.
     *
     * Returns null if no calculation record exists yet for the user/year.
     */
    public function computeQ4TrueUp(int $userId, int $year): ?float
    {
        $calculation = OverridePoolCalculation::where('user_id', $userId)
            ->where('year', $year)
            ->first();

        if ($calculation === null) {
            return null;
        }

        $advance = OverridePoolQuarterlyAdvance::where('user_id', $userId)
            ->where('year', $year)
            ->first();

        $totalAdvances = $advance ? $advance->totalAdvances() : 0.0;
        $q4Trueup      = round($calculation->total_pool_payment - $totalAdvances, 2);

        $calculation->update(['q4_trueup' => $q4Trueup]);

        return $q4Trueup;
    }
}
