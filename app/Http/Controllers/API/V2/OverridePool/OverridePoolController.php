<?php

namespace App\Http\Controllers\API\V2\OverridePool;

use App\Http\Controllers\Controller;
use App\Models\OverridePoolCalculation;
use App\Models\OverridePoolPercentageTier;
use App\Models\OverridePoolQuarterlyAdvance;
use App\Services\OverridePoolCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OverridePoolController extends Controller
{
    public function __construct(private readonly OverridePoolCalculationService $calculationService)
    {
    }

    // -------------------------------------------------------------------------
    // Calculation
    // -------------------------------------------------------------------------

    /**
     * Run the override pool calculation for all eligible users in a given year.
     *
     * GET /api/v2/override-pool/calculate?year=2025
     *
     * Query params:
     *   year (required) - The calculation year
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate(['year' => 'required|integer|min:2000|max:2100']);
        $year = (int) $request->input('year');

        $results = $this->calculationService->calculateAll($year);

        // Attach user name data for display
        $userIds = array_column($results, 'user_id');
        $users   = \App\Models\User::whereIn('id', $userIds)
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->keyBy('id');

        $data = array_map(function ($result) use ($users, $year) {
            $user = $users[$result['user_id']] ?? null;
            return array_merge($result, [
                'user_name'     => $user ? ($user->first_name . ' ' . $user->last_name) : null,
                'is_overpaid'   => $result['q4_trueup'] !== null && $result['q4_trueup'] < 0,
            ]);
        }, $results);

        return response()->json([
            'ApiName' => 'override-pool/calculate',
            'status'  => true,
            'message' => 'Override pool calculated successfully for year ' . $year,
            'year'    => $year,
            'count'   => count($data),
            'data'    => $data,
        ], 200);
    }

    /**
     * Get the stored calculation breakdown for a single user.
     *
     * GET /api/v2/override-pool/user/{userId}?year=2025
     */
    public function userDetail(int $userId, Request $request): JsonResponse
    {
        $request->validate(['year' => 'required|integer|min:2000|max:2100']);
        $year = (int) $request->input('year');

        $calculation = OverridePoolCalculation::with('user:id,first_name,last_name')
            ->where('user_id', $userId)
            ->where('year', $year)
            ->first();

        if (!$calculation) {
            return response()->json([
                'ApiName' => 'override-pool/user-detail',
                'status'  => false,
                'message' => 'No calculation found for this user and year. Run /calculate first.',
            ], 404);
        }

        $advance = OverridePoolQuarterlyAdvance::where('user_id', $userId)
            ->where('year', $year)
            ->first();

        return response()->json([
            'ApiName' => 'override-pool/user-detail',
            'status'  => true,
            'message' => 'Calculation detail retrieved',
            'data'    => [
                'user_id'            => $calculation->user_id,
                'user_name'          => $calculation->user
                    ? ($calculation->user->first_name . ' ' . $calculation->user->last_name)
                    : null,
                'year'               => $calculation->year,
                'step1_downline_count'    => $calculation->downline_count,
                'step2_downline_sales'    => $calculation->downline_sales,
                'step3_pool_percentage'   => $calculation->pool_percentage,
                'step4_gross_pool_value'  => $calculation->gross_pool_value,
                'step5_part1'             => $calculation->part1,
                'step6_part2_breakdown'   => $calculation->part2_breakdown,
                'step7_total_pool_payment' => $calculation->total_pool_payment,
                'pool_rate'          => $calculation->pool_rate,
                'q1_advance'         => $advance?->q1_advance ?? 0,
                'q2_advance'         => $advance?->q2_advance ?? 0,
                'q3_advance'         => $advance?->q3_advance ?? 0,
                'total_advances'     => $advance ? $advance->totalAdvances() : 0,
                'q4_trueup'          => $calculation->q4_trueup,
                'is_overpaid'        => $calculation->isOverpaid(),
                'calculation_error'  => $calculation->calculation_error,
                'last_calculated_at' => $calculation->updated_at,
            ],
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Quarterly Advances
    // -------------------------------------------------------------------------

    /**
     * Save quarterly advance amounts for a user/year.
     * Recalculates Q4 true-up after saving.
     *
     * POST /api/v2/override-pool/advances
     * Body: { user_id, year, q1_advance, q2_advance, q3_advance }
     */
    public function saveAdvances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'    => 'required|integer|exists:users,id',
            'year'       => 'required|integer|min:2000|max:2100',
            'q1_advance' => 'required|numeric|min:0',
            'q2_advance' => 'required|numeric|min:0',
            'q3_advance' => 'required|numeric|min:0',
        ]);

        $advance = DB::transaction(function () use ($validated) {
            return OverridePoolQuarterlyAdvance::updateOrCreate(
                ['user_id' => $validated['user_id'], 'year' => $validated['year']],
                [
                    'q1_advance' => $validated['q1_advance'],
                    'q2_advance' => $validated['q2_advance'],
                    'q3_advance' => $validated['q3_advance'],
                ]
            );
        });

        // Recompute Q4 true-up now that advances are saved
        $q4Trueup = $this->calculationService->computeQ4TrueUp(
            (int) $validated['user_id'],
            (int) $validated['year']
        );

        return response()->json([
            'ApiName'       => 'override-pool/save-advances',
            'status'        => true,
            'message'       => 'Advances saved successfully',
            'data'          => [
                'user_id'        => $advance->user_id,
                'year'           => $advance->year,
                'q1_advance'     => $advance->q1_advance,
                'q2_advance'     => $advance->q2_advance,
                'q3_advance'     => $advance->q3_advance,
                'total_advances' => $advance->totalAdvances(),
                'q4_trueup'      => $q4Trueup,
                'is_overpaid'    => $q4Trueup !== null && $q4Trueup < 0,
            ],
        ], 200);
    }

    /**
     * Get all quarterly advance records for a given year, joined with totals.
     *
     * GET /api/v2/override-pool/advances?year=2025
     */
    public function getAdvances(Request $request): JsonResponse
    {
        $request->validate(['year' => 'required|integer|min:2000|max:2100']);
        $year = (int) $request->input('year');

        $advances = OverridePoolQuarterlyAdvance::with('user:id,first_name,last_name')
            ->where('year', $year)
            ->get()
            ->map(function ($advance) use ($year) {
                $calculation = OverridePoolCalculation::where('user_id', $advance->user_id)
                    ->where('year', $year)
                    ->select('total_pool_payment', 'q4_trueup')
                    ->first();

                return [
                    'user_id'            => $advance->user_id,
                    'user_name'          => $advance->user
                        ? ($advance->user->first_name . ' ' . $advance->user->last_name)
                        : null,
                    'year'               => $advance->year,
                    'q1_advance'         => $advance->q1_advance,
                    'q2_advance'         => $advance->q2_advance,
                    'q3_advance'         => $advance->q3_advance,
                    'total_advances'     => $advance->totalAdvances(),
                    'total_pool_payment' => $calculation?->total_pool_payment,
                    'q4_trueup'          => $calculation?->q4_trueup,
                    'is_overpaid'        => $calculation?->q4_trueup !== null
                        && $calculation->q4_trueup < 0,
                ];
            });

        return response()->json([
            'ApiName' => 'override-pool/get-advances',
            'status'  => true,
            'message' => 'Advances retrieved successfully',
            'year'    => $year,
            'count'   => $advances->count(),
            'data'    => $advances,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Dashboard Widgets
    // -------------------------------------------------------------------------

    /**
     * Return aggregated dashboard widget data for a given year.
     *
     * GET /api/v2/override-pool/dashboard?year=2025
     *
     * Returns:
     *   eligible_agents_count  - number of agents with a pool calculation
     *   overpaid_agents_count  - number of agents where q4_trueup < 0
     *   total_pool_amount      - sum of total_pool_payment across all eligible agents
     *   total_advances         - sum of all Q1+Q2+Q3 advance amounts for the year
     *   q4_trueup_total        - total_pool_amount minus total_advances
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate(['year' => 'required|integer|min:2000|max:2100']);
        $year = (int) $request->input('year');

        $calcs = OverridePoolCalculation::where('year', $year)
            ->selectRaw('COUNT(*) as eligible_agents_count')
            ->selectRaw('SUM(CASE WHEN q4_trueup < 0 THEN 1 ELSE 0 END) as overpaid_agents_count')
            ->selectRaw('COALESCE(SUM(total_pool_payment), 0) as total_pool_amount')
            ->first();

        $totalAdvances = OverridePoolQuarterlyAdvance::where('year', $year)
            ->selectRaw('COALESCE(SUM(q1_advance + q2_advance + q3_advance), 0) as total_advances')
            ->value('total_advances') ?? 0;

        $totalPoolAmount = (float) $calcs->total_pool_amount;
        $q4TrueupTotal   = $totalPoolAmount - (float) $totalAdvances;

        return response()->json([
            'ApiName' => 'override-pool/dashboard',
            'status'  => true,
            'year'    => $year,
            'data'    => [
                'eligible_agents_count' => (int) $calcs->eligible_agents_count,
                'overpaid_agents_count' => (int) $calcs->overpaid_agents_count,
                'total_pool_amount'     => round($totalPoolAmount, 2),
                'total_advances'        => round((float) $totalAdvances, 2),
                'q4_trueup_total'       => round($q4TrueupTotal, 2),
            ],
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Pool Percentage Tiers (Configurable Rules)
    // -------------------------------------------------------------------------

    /**
     * Get all pool percentage tier configurations.
     *
     * GET /api/v2/override-pool/tiers
     */
    public function getTiers(): JsonResponse
    {
        $tiers = OverridePoolPercentageTier::orderBy('sales_from')->get();

        return response()->json([
            'ApiName' => 'override-pool/get-tiers',
            'status'  => true,
            'message' => 'Pool percentage tiers retrieved',
            'count'   => $tiers->count(),
            'data'    => $tiers,
        ], 200);
    }

    /**
     * Replace pool percentage tier configuration.
     * Accepts an array of tier objects; replaces all existing tiers in a transaction.
     *
     * POST /api/v2/override-pool/tiers
     * Body: { tiers: [{sales_from, sales_to, pool_percentage}] }
     *
     * Note: sales_to may be null to indicate an open-ended upper range.
     */
    public function saveTiers(Request $request): JsonResponse
    {
        $request->validate([
            'tiers'                    => 'required|array|min:1',
            'tiers.*.sales_from'       => 'required|integer|min:0',
            'tiers.*.sales_to'         => 'nullable|integer|gt:tiers.*.sales_from',
            'tiers.*.pool_percentage'  => 'required|numeric|min:0|max:100',
        ]);

        $tiers = DB::transaction(function () use ($request) {
            // Deactivate all existing tiers, then insert new ones
            OverridePoolPercentageTier::query()->update(['is_active' => 0]);

            $now  = now();
            $rows = array_map(fn ($t) => [
                'sales_from'      => $t['sales_from'],
                'sales_to'        => $t['sales_to'] ?? null,
                'pool_percentage' => $t['pool_percentage'],
                'is_active'       => 1,
                'created_at'      => $now,
                'updated_at'      => $now,
            ], $request->input('tiers'));

            OverridePoolPercentageTier::insert($rows);

            return OverridePoolPercentageTier::active()->get();
        });

        return response()->json([
            'ApiName' => 'override-pool/save-tiers',
            'status'  => true,
            'message' => 'Pool percentage tiers saved successfully',
            'count'   => $tiers->count(),
            'data'    => $tiers,
        ], 200);
    }
}
