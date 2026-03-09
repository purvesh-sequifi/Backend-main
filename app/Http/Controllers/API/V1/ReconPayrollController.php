<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\MonthlyPayFrequency;
use App\Models\MoveToReconciliation;
use App\Models\Payroll;
use App\Models\PayrollDeductions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationAdjustmentDetails;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationStatusForSkipedUser;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
use App\Models\WeeklyPayFrequency;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReconPayrollController extends Controller
{
    public $isPestServer = false;

    public $isUpfront = false;

    public function __construct()
    {
        /* check server is pest or not */
        $companyProfile = CompanyProfile::first();
        $this->isPestServer = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $this->isUpfront = $companyProfile->deduct_any_available_reconciliation_upfront;
    }

    /**
     * Method startReconList : This function getting list based on recon payroll params
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
    public function startReconList(Request $request)
    {
        Log::channel('reconLog')->info('start-recon');
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $officeIds = $request->office_id;
        $positionIds = $request->position_id;
        $perPage = ! empty($request->perpage) ? $request->perpage : 10;
        $page = ! empty($request->page) ? $request->page : 1;
        $search = $request->input('search', '');
        $reconPayout = $request->recon_payout;
        $sortBy = $request->sort;
        $sortOrder = $request->sort_val;

        $officeIds = $this->normalizeIds($officeIds);
        $positionIds = $this->normalizeIds($positionIds);

        $userIds = $this->getUserIds($officeIds, $positionIds, $search);
        if (empty($userIds)) {
            return response()->json([
                'ApiName' => 'reconciliation_details',
                'status' => true,
                'message' => 'Successfully.',
                'finalize_status' => 0,
                'data' => [],
            ], 200);
        }
        // $pids = $this->getReconciliationPids($userIds);
        // $salePids = $this->getSalePids($pids, $startDate, $endDate);
        /* get recon data */
        $reconWithHoldResponseData = $this->getReconData($userIds, $startDate, $endDate, $reconPayout);
        $reconWithHoldResponseArray = $reconWithHoldResponseData['data'];
        // Sorting logic
        switch ($sortBy) {
            case 'commission':
                $sortBy = 'commissionWithholding';
                break;
            case 'override':
                $sortBy = 'overrideDue';
                break;
            case 'clawback':
                $sortBy = 'clawbackDue';
                break;
            case 'adjustments':
                $sortBy = 'totalAdjustments';
                break;

            default:
                $sortBy = 'emp_name';
                $sortOrder = 'asc';
                break;
        }

        if ($sortBy && $sortOrder) {
            usort($reconWithHoldResponseArray, function ($a, $b) use ($sortBy, $sortOrder) {
                if ($sortOrder == 'asc') {
                    return $a[$sortBy] <=> $b[$sortBy];
                } else {
                    return $b[$sortBy] <=> $a[$sortBy];
                }
            });
        }

        $data = paginate($reconWithHoldResponseArray, $perPage, $page);

        return response()->json([
            'ApiName' => 'reconciliation_details',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => 0,
            'data' => $data,
            'user_id' => $reconWithHoldResponseData['userIds'],
            // "user_id" => []
        ]);
    }

    public function getReconData($userIds, $startDate, $endDate, $reconPayout, $isFinalize = false)
    {
        try {
            $response = [];
            $finalUserIds = [];
            $parseStartDate = Carbon::parse($startDate);
            $parseEndDate = Carbon::parse($endDate);
            $isUpfront = $this->isUpfront;
            foreach ($userIds as $userId) {

                $userData = User::find($userId);
                $userSkipStatus = ReconciliationStatusForSkipedUser::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)->where('user_id', $userId)->exists();

                /* getting pids for override list */
                $getAllPids = $this->getPids($userId, $startDate, $endDate);
                $eligiblePids = $getAllPids->filter(function ($item) use ($parseStartDate, $parseEndDate) {
                    return ! empty($item->m2_date) && $item->m2_date >= $parseStartDate && $item->m2_date <= $parseEndDate && empty($item->date_cancelled);
                })->pluck('pid')->toArray();

                $allPids = $getAllPids->pluck('pid')->toArray();

                $getUserPids = $this->getUserPids($userId, $startDate, $endDate);

                $check = 0;
                if (count($getUserPids) > 0) {
                    $check += 1;
                }

                /* commission code */
                $totalWithHoldCount = UserCommission::where('user_id', $userId)
                    ->where(function ($query) {
                        $query->where('settlement_type', 'reconciliation')
                            ->orWhereIn('amount_type', ['m1', 'm2', 'm2 update', 'reconciliation']);
                    })
                    ->where('status', 3)
                    ->whereIn('pid', $allPids)
                    ->count('id') ?? 0;

                if ($totalWithHoldCount > 0) {
                    $check += 1;
                }

                $totalWithHoldAmount = UserCommission::where('user_id', $userId)
                    ->where(function ($query) {
                        $query->where('settlement_type', 'reconciliation')
                            ->orWhereIn('amount_type', ['m1', 'm2', 'm2 update', 'reconciliation']);
                    })
                    ->where('status', 3)
                    ->whereIn('pid', $eligiblePids)
                    ->sum('amount') ?? 0;

                $paidReconAmount = ReconCommissionHistory::where([
                    'user_id' => $userData->id,
                    'move_from_payroll' => 0,
                ])->whereIn('pid', $eligiblePids);

                if ($isFinalize) {
                    $paidReconAmount->where('status', 'payroll');
                }

                $totalPaidReconAmount = $paidReconAmount->sum('paid_amount') ?? 0;
                $totalWithHoldAmount = $totalWithHoldAmount - $totalPaidReconAmount;

                /* override code */
                $totalOverrideCount = UserOverrides::where('user_id', $userId)
                    ->whereIn('pid', $eligiblePids)
                    ->where('overrides_settlement_type', 'reconciliation')
                    ->count('id') ?? 0;

                if ($totalOverrideCount > 0) {
                    $check += 1;
                }

                $totalOverrideAmount = UserOverrides::where('user_id', $userId)
                    ->whereIn('pid', $eligiblePids)
                    ->where('overrides_settlement_type', 'reconciliation')
                    ->sum('amount') ?? 0;

                $paidOverrideAmount = ReconOverrideHistory::where([
                    'user_id' => $userId,
                    'move_from_payroll' => 0,
                ])->whereIn('pid', $eligiblePids);

                if ($isFinalize) {
                    $paidOverrideAmount->where('status', 'payroll');
                }

                $totalPaidOverride = $paidOverrideAmount->sum('paid');
                $totalOverrideAmount = $totalOverrideAmount - $totalPaidOverride;

                /* clawback code */
                $totalClawbackCount = ClawbackSettlement::where('clawback_type', 'reconciliation')
                    ->where('user_id', $userId)
                    ->where('payroll_id', 0)
                    ->where('status', 3)
                    ->count('id') ?? 0;

                if ($totalClawbackCount > 0) {
                    $check += 1;
                }

                $totalClawbackAmount = ClawbackSettlement::where('clawback_type', 'reconciliation')
                    ->where('user_id', $userId)
                    ->where('payroll_id', 0)
                    ->where('status', 3)
                    ->sum('clawback_amount') ?? 0;

                $reconClawbackHistory = ReconClawbackHistory::where('user_id', $userId)->whereIn('pid', $eligiblePids);

                if ($isFinalize) {
                    $reconPaidAmount = $reconClawbackHistory->where('status', 'payroll')->sum('paid_amount');
                } else {
                    $reconPaidAmount = $reconClawbackHistory->sum('paid_amount');
                }

                $totalClawbackAmount = $totalClawbackAmount - $reconPaidAmount;

                /* adjustment and deduction code */
                $totalAdjustment = ReconAdjustment::whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $userId)
                    // ->whereIn("pid", $eligiblePids)
                    ->whereNull('payroll_id')
                    ->whereNull('pay_period_from')
                    ->whereNull('pay_period_to')
                    ->whereNull('payroll_status')
                    ->sum('adjustment_amount');

                $deductionData = PayrollDeductions::where('user_id', $userId)
                    ->where('is_move_to_recon', 1)
                    ->where('status', 3)
                    ->whereBetween('pay_period_from', [$startDate, $endDate])
                    ->whereBetween('pay_period_to', [$startDate, $endDate])
                    ->sum('total');

                $imageUrl = null;
                if ($userData->image) {
                    $imageUrl = s3_getTempUrl(config('app.domain_name').'/'.$userData->image);
                }

                $finalClawbackAmount = 0;
                $totalDueAmount = ($totalWithHoldAmount + $totalOverrideAmount);
                if ($totalDueAmount > 0) {
                    $totalPercentageAmount = ($totalWithHoldAmount + $totalOverrideAmount) * ($reconPayout / 100);
                } else {
                    $totalPercentageAmount = ($totalWithHoldAmount + $totalOverrideAmount);
                }

                $payout = $totalPercentageAmount + $finalClawbackAmount + $totalAdjustment + $totalClawbackAmount;

                /* $check = 0;
                if($totalWithHoldAmount != 0 || $totalOverrideAmount != 0 || $totalClawbackAmount != 0){
                    $check = 1;
                } */

                if ($check > 0) {

                    if (! $userSkipStatus) {
                        $finalUserIds[] = $userId;
                    }

                    $response[] = [
                        'user_id' => $userData->id,
                        'pids' => $eligiblePids,
                        'emp_img' => $userData->emp_img ? $userData->emp_img : 'Employee_profile/default-user.png',
                        'emp_name' => ucfirst($userData->first_name).' '.ucfirst($userData->last_name),
                        'emp_img_s3' => $imageUrl,
                        'commissionWithholding' => $totalWithHoldAmount,
                        'overrideDue' => $totalOverrideAmount,
                        'total_due' => $totalDueAmount,
                        'total_pay' => $totalPercentageAmount,
                        'clawbackDue' => (-1 * $totalClawbackAmount),
                        'payout' => $payout,
                        'percentage' => 10,
                        'totalAdjustments' => $totalAdjustment - $deductionData,
                        'adjustments' => $totalAdjustment,
                        'deductions' => $deductionData,
                        'user_skip' => $userSkipStatus ? 1 : 0,
                        'is_super_admin' => $userData->is_super_admin,
                        'is_manager' => $userData->is_manager,
                        'position_id' => $userData->position_id,
                        'sub_position_id' => $userData->sub_position_id,
                        'frequency_type_id' => $userData?->positionDetail?->payFrequency?->frequencyType?->id,
                        'frequency_type_name' => $userData?->positionDetail?->payFrequency?->frequencyType?->name,
                        'is_upfront' => $isUpfront,
                    ];
                }
            }

            // return $response;
            return [
                'data' => $response,
                'userIds' => $finalUserIds,
            ];
        } catch (\Exception $e) {
            dd([
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ]);
        }
    }

    public function getReconData1($userIds, $startDate, $endDate, $reconPayout, $isFinalize = false)
    {
        try {
            $response = [];
            $parseStartDate = Carbon::parse($startDate);
            $parseEndDate = Carbon::parse($endDate);
            $isUpfront = $this->isUpfront;
            foreach ($userIds as $userId) {
                $userData = User::find($userId);
                $userSkipStatus = ReconciliationStatusForSkipedUser::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)->where('user_id', $userId)->exists();

                /* getting pids for override list */
                $getAllPids = $this->getPids($userId, $startDate, $endDate);
                $eligiblePids = $getAllPids->filter(function ($item) use ($parseStartDate, $parseEndDate) {
                    return ! empty($item->m2_date) && $item->m2_date >= $parseStartDate && $item->m2_date <= $parseEndDate && empty($item->date_cancelled);
                })->pluck('pid')->toArray();

                /* commission code */
                $reconCommissionData = (new ReconPopUpController)->getReconCommissionData($userId, $startDate, $endDate, '', $reconPayout, $isFinalize)->toArray();
                $totalCommissionData = array_reduce($reconCommissionData, function ($carry, $item) {
                    $carry['in_recon'] += $item['in_recon'];
                    $carry['in_recon_percentage'] += $item['in_recon_percentage'];

                    return $carry;
                }, ['in_recon' => 0, 'in_recon_percentage' => 0]);
                $totalWithHoldAmount = array_sum($totalCommissionData);

                /* override code */
                $reconOverrideData = (new ReconPopUpController)->getReconOverRidesData($userId, $startDate, $endDate, '', $reconPayout, $isFinalize)->toArray();
                $totalOverrideData = array_reduce($reconOverrideData, function ($carry, $item) {
                    $carry['in_recon'] += $item['in_recon'];
                    $carry['in_recon_percentage'] += $item['in_recon_percentage'];

                    return $carry;
                }, ['in_recon' => 0, 'in_recon_percentage' => 0]);
                $totalOverrideAmount = array_sum($totalOverrideData);

                /* clawback code */
                $reconClawbackData = (new ReconPopUpController)->getReconClawbackData($userId, $startDate, $endDate, '', $isFinalize)->toArray();
                $totalClawbackData = array_reduce($reconClawbackData, function ($carry, $item) {
                    $carry['in_recon'] += $item['in_recon'];

                    return $carry;
                }, ['in_recon' => 0]);
                $totalClawbackAmount = array_sum($totalClawbackData);

                /* adjustment and deduction code */
                $totalAdjustment = ReconAdjustment::whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $userId)
                    // ->whereIn("pid", $eligiblePids)
                    ->whereNull('payroll_id')
                    ->whereNull('pay_period_from')
                    ->whereNull('pay_period_to')
                    ->whereNull('payroll_status')
                    ->sum('adjustment_amount');

                $deductionData = PayrollDeductions::where('user_id', $userId)
                    ->where('is_move_to_recon', 1)
                    ->where('status', 3)
                    ->whereBetween('pay_period_from', [$startDate, $endDate])
                    ->whereBetween('pay_period_to', [$startDate, $endDate])
                    ->sum('total');

                $imageUrl = null;
                if ($userData->image) {
                    $imageUrl = s3_getTempUrl(config('app.domain_name').'/'.$userData->image);
                }

                $finalClawbackAmount = 0;
                $totalDueAmount = ($totalWithHoldAmount + $totalOverrideAmount);
                $totalPercentageAmount = ($totalCommissionData['in_recon_percentage'] + $totalOverrideData['in_recon_percentage']);
                $payout = $totalPercentageAmount + $finalClawbackAmount + $totalAdjustment + $totalClawbackAmount;
                // if($payout != 0){
                $response[] = [
                    'user_id' => $userData->id,
                    'pids' => $eligiblePids,
                    'emp_img' => $userData->emp_img ? $userData->emp_img : 'Employee_profile/default-user.png',
                    'emp_name' => ucfirst($userData->first_name).' '.ucfirst($userData->last_name),
                    'emp_img_s3' => $imageUrl,
                    'commissionWithholding' => $totalWithHoldAmount,
                    'overrideDue' => $totalOverrideAmount,
                    'total_due' => $totalDueAmount,
                    'total_pay' => $totalPercentageAmount,
                    'clawbackDue' => $totalClawbackAmount,
                    'payout' => $payout,
                    'percentage' => 10,
                    'totalAdjustments' => $totalAdjustment - $deductionData,
                    'adjustments' => $totalAdjustment,
                    'deductions' => $deductionData,
                    'user_skip' => $userSkipStatus ? 1 : 0,
                    'is_super_admin' => $userData->is_super_admin,
                    'is_manager' => $userData->is_manager,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'frequency_type_id' => $userData?->positionDetail?->payFrequency?->frequencyType?->id,
                    'frequency_type_name' => $userData?->positionDetail?->payFrequency?->frequencyType?->name,
                    'is_upfront' => $isUpfront,
                ];
                // }
            }

            return $response;
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getReconDataOld($userIds, $startDate, $endDate, $reconPayout)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $userData = User::with(['positionDetail.payFrequency.frequencyType'])
            ->whereIn('id', $userIds)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                // Collect pay frequencies, ensuring no null values
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });
        $payFrequencyTypes = $userData->unique();

        $withholdsSubQuery = DB::table('user_reconciliation_withholds AS u_r_w_h_t')
            ->select(
                DB::raw('CASE WHEN u_r_w_h_t.closer_id IN ('.implode(',', $userIds).') THEN u_r_w_h_t.closer_id WHEN u_r_w_h_t.setter_id IN ('.implode(',', $userIds).') THEN u_r_w_h_t.setter_id ELSE NULL END AS user_id'),
                DB::raw('SUM(CASE WHEN u_r_w_h_t.status = "clawback" THEN 0 ELSE u_r_w_h_t.withhold_amount END) AS total_withhold_amount'),
                DB::raw('SUM(CASE WHEN u_r_w_h_t.status = "clawback" THEN u_r_w_h_t.withhold_amount ELSE 0 END) AS total_clawback_amount'),
                DB::raw('MAX(users.image) AS emp_img'),
                DB::raw('MAX(users.first_name) AS first_name'),
                DB::raw('MAX(users.last_name) AS last_name'),
                'u_r_w_h_t.pid AS pid',
                DB::raw('GROUP_CONCAT(DISTINCT u_r_w_h_t.pid) AS withhold_pids'),
                DB::raw('CONCAT("[", GROUP_CONCAT(
                    CONCAT("{",
                        "\"pid\":\"", u_r_w_h_t.pid, "\",",
                        "\"withhold_amount\":\"", CASE WHEN u_r_w_h_t.status = "clawback" THEN 0 ELSE u_r_w_h_t.withhold_amount END, "\",",
                        "\"clawback_amount\":\"", CASE WHEN u_r_w_h_t.status = "clawback" THEN u_r_w_h_t.withhold_amount ELSE 0 END, "\",",
                        "\"adjustment_amount\":\"", COALESCE((SELECT SUM(r_a_t.adjustment_amount)
                                                    FROM recon_adjustments AS r_a_t
                                                    WHERE (r_a_t.user_id = u_r_w_h_t.closer_id 
                                                           OR r_a_t.user_id = u_r_w_h_t.setter_id)
                                                    AND DATE(r_a_t.start_date) = "'.$startDate->format('Y-m-d').'"
                                                    AND DATE(r_a_t.end_date) = "'.$endDate->format('Y-m-d').'"
                                                    AND r_a_t.adjustment_type = "commission"
                                                    AND r_a_t.payroll_id IS NULL
                                                    AND r_a_t.pay_period_from IS NULL
                                                    AND r_a_t.pay_period_to IS NULL
                                                    AND r_a_t.payroll_status IS NULL
                                                    AND r_a_t.pid = u_r_w_h_t.pid), 0), "\",",
                        "\"m2_date\":\"", s_m_t.m2_date, "\""
                    "}")
                ), "]") AS withholds')
            )
            ->join('sale_masters AS s_m_t', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t.pid', '=', 'u_r_w_h_t.pid')
                    ->whereBetween('s_m_t.customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t.date_cancelled');
            })
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'u_r_w_h_t.closer_id')
                    ->orOn('users.id', '=', 'u_r_w_h_t.setter_id');
            })
            ->where(function ($query) use ($userIds) {
                $query->whereIn('u_r_w_h_t.closer_id', $userIds)
                    ->orWhereIn('u_r_w_h_t.setter_id', $userIds);
            })
            ->where('u_r_w_h_t.status', '=', 'unpaid')
            ->groupBy(DB::raw('
                CASE
                    WHEN u_r_w_h_t.closer_id IN ('.implode(',', $userIds).') THEN u_r_w_h_t.closer_id
                    WHEN u_r_w_h_t.setter_id IN ('.implode(',', $userIds).') THEN u_r_w_h_t.setter_id
                    ELSE NULL
                END
            '));

        /* Move to recon commission data */
        $moveToReconCommissionQuery = DB::table('user_commission as user_commission_move_to_recon')
            ->select(
                'user_commission_move_to_recon.*',
                DB::raw('GROUP_CONCAT(DISTINCT user_commission_move_to_recon.pid) AS commission_pids'),
                DB::raw('SUM(user_commission_move_to_recon.amount) AS move_to_recon_commission_amount'),
                'user_commission_move_to_recon.user_id AS user_commission_move_to_recon_user_id',
                'user_commission_move_to_recon.id AS user_commission_move_to_recon_id',
                'user_commission_move_to_recon.pid AS user_commission_move_to_recon_pid',
                'user_commission_move_to_recon.amount AS user_commission_move_to_recon_amount',
                's_m_t.customer_name AS s_m_t_customer_name',
                's_m_t.customer_state AS s_m_t_customer_state',
                's_m_t.m2_date AS s_m_t_m2_date',
                'users.is_super_admin',
                'users.is_manager',
                'users.position_id',
                'users.sub_position_id',
                DB::raw('CONCAT("[", GROUP_CONCAT(
                    CONCAT("{",
                        "\"pid\":\"", user_commission_move_to_recon.pid, "\",",
                        "\"commission_amount\":\"", user_commission_move_to_recon.amount, "\",",
                        "\"adjustment_amount\":\"", COALESCE((SELECT SUM(r_a_t.adjustment_amount)
                                                    FROM recon_adjustments AS r_a_t
                                                    WHERE r_a_t.user_id = user_commission_move_to_recon.user_id
                                                    AND r_a_t.adjustment_type = "commission"
                                                    AND r_a_t.adjustment_override_type = "commission"
                                                    AND DATE(r_a_t.start_date) = "'.$startDate->format('Y-m-d').'"
                                                    AND DATE(r_a_t.end_date) = "'.$endDate->format('Y-m-d').'"
                                                    AND r_a_t.payroll_id IS NULL
                                                    AND r_a_t.pay_period_from IS NULL
                                                    AND r_a_t.pay_period_to IS NULL
                                                    AND r_a_t.payroll_status IS NULL
                                                    AND r_a_t.pid = user_commission_move_to_recon.pid), 0), "\",",
                        "\"m2_date\":\"", s_m_t.m2_date, "\""
                    "}")
                ), "]") AS commissions')
            )
            ->join('sale_masters as s_m_t', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t.pid', '=', 'user_commission_move_to_recon.pid')
                    ->whereBetween('s_m_t.customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t.date_cancelled');
            })
            ->join('users', 'users.id', 'user_commission_move_to_recon.user_id')
            ->where('user_commission_move_to_recon.status', 6)
            ->where('user_commission_move_to_recon.is_move_to_recon', 1)
            ->where('user_commission_move_to_recon.is_displayed', '1')
            ->whereIn('user_commission_move_to_recon.user_id', $userIds)
            ->groupBy('user_commission_move_to_recon.user_id');

        $moveToReconCommissionResults = new Collection;
        foreach ($payFrequencyTypes as $frequency) {
            $results = [];
            $query = clone $moveToReconCommissionQuery;

            if ($frequency === 'Weekly') {
                $query->join('weekly_pay_frequencies as w_p_f', function ($join) {
                    $join->on('w_p_f.pay_period_from', '=', 'user_commission_move_to_recon.pay_period_from')
                        ->on('w_p_f.pay_period_to', '=', 'user_commission_move_to_recon.pay_period_to')
                        ->where('w_p_f.closed_status', '=', 1);
                });
                $results = $query->get();
            } elseif ($frequency === 'Monthly') {
                $query->join('monthly_pay_frequencies as m_p_f', function ($join) {
                    $join->on('m_p_f.pay_period_from', '=', 'user_commission_move_to_recon.pay_period_from')
                        ->on('m_p_f.pay_period_to', '=', 'user_commission_move_to_recon.pay_period_to')
                        ->where('m_p_f.closed_status', '=', 1);
                });
                $results = $query->get();
            }
            $moveToReconCommissionResults = $moveToReconCommissionResults->merge($results);
        }

        // /* SubQuery for user_overrides */
        $overridesSubQuery = DB::table('user_overrides as u_o_t')
            ->select(
                'u_o_t.user_id',
                'u_o_t.pid',
                DB::raw('SUM(u_o_t.amount) AS amount'),
                DB::raw('GROUP_CONCAT(DISTINCT u_o_t.pid) AS override_pids'),
                DB::raw('CONCAT("[", GROUP_CONCAT(
                    CONCAT("{",
                        "\"pid\":\"", u_o_t.pid, "\",",
                        "\"override_amount\":\"", u_o_t.amount, "\",",
                        "\"adjustment_amount\":\"", COALESCE((SELECT SUM(r_a_t.adjustment_amount)
                                                    FROM recon_adjustments AS r_a_t
                                                    WHERE r_a_t.user_id = u_o_t.user_id
                                                    AND r_a_t.adjustment_type = "override"
                                                    AND r_a_t.adjustment_override_type = u_o_t.type
                                                    AND DATE(r_a_t.start_date) = "'.$startDate->format('Y-m-d').'"
                                                    AND DATE(r_a_t.end_date) = "'.$endDate->format('Y-m-d').'"
                                                    AND r_a_t.payroll_id IS NULL
                                                    AND r_a_t.pay_period_from IS NULL
                                                    AND r_a_t.pay_period_to IS NULL
                                                    AND r_a_t.payroll_status IS NULL
                                                    AND r_a_t.pid = u_o_t.pid), 0), "\",",
                        "\"m2_date\":\"", s_m_t_o_r.m2_date, "\""
                    "}")
                ), "]") AS overrides')
            )
            ->join('sale_masters AS s_m_t_o_r', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t_o_r.pid', '=', 'u_o_t.pid')
                    ->whereBetween('s_m_t_o_r.customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t_o_r.date_cancelled');
            })
            ->join('users', 'users.id', '=', 'u_o_t.user_id')
            ->whereIn('u_o_t.user_id', $userIds)
            ->where('u_o_t.overrides_settlement_type', 'reconciliation')
            ->where('u_o_t.status', 1)
            ->where('u_o_t.is_displayed', '1')
            ->groupBy('u_o_t.user_id');

        $moveToReconOverRideQuery = DB::table('user_overrides as u_o_t_m_t_r')
            ->select(
                'u_o_t_m_t_r.*',
                DB::raw('GROUP_CONCAT(DISTINCT u_o_t_m_t_r.pid) AS override_pids'),
                DB::raw('SUM(u_o_t_m_t_r.amount) AS m_t_r_t_amount'),
                'u_o_t_m_t_r.user_id AS u_o_t_m_t_r_user_id',
                'u_o_t_m_t_r.id AS u_o_t_m_t_r_id',
                'u_o_t_m_t_r.pid AS u_o_t_m_t_r_pid',
                'u_o_t_m_t_r.overrides_amount AS u_o_t_m_t_r_overrides_amount',
                's_m_t.customer_name AS s_m_t_customer_name',
                's_m_t.customer_state AS s_m_t_customer_state',
                's_m_t.m2_date AS s_m_t_m2_date',
                'u_o_t_m_t_r.sale_user_id AS s_m_t_sale_user_id',
                'u_o_t_m_t_r.type AS s_m_t_type',
                'users.is_super_admin',
                'users.is_manager',
                'users.position_id',
                'users.sub_position_id',
                DB::raw('CONCAT("[", GROUP_CONCAT(
                    CONCAT("{",
                        "\"pid\":\"", u_o_t_m_t_r.pid, "\",",
                        "\"override_amount\":\"", u_o_t_m_t_r.amount, "\",",
                        "\"adjustment_amount\":\"", COALESCE((SELECT SUM(r_a_t.adjustment_amount)
                                                    FROM recon_adjustments AS r_a_t
                                                    WHERE r_a_t.user_id = u_o_t_m_t_r.user_id
                                                    AND r_a_t.adjustment_type = "override"
                                                    AND r_a_t.adjustment_override_type = u_o_t_m_t_r.type
                                                    AND DATE(r_a_t.start_date) = "'.$startDate->format('Y-m-d').'"
                                                    AND DATE(r_a_t.end_date) = "'.$endDate->format('Y-m-d').'"
                                                    AND r_a_t.payroll_id IS NULL
                                                    AND r_a_t.pay_period_from IS NULL
                                                    AND r_a_t.pay_period_to IS NULL
                                                    AND r_a_t.payroll_status IS NULL
                                                    AND r_a_t.pid = u_o_t_m_t_r.pid), 0), "\",",
                        "\"m2_date\":\"", s_m_t.m2_date, "\""
                    "}")
                ), "]") AS overrides')
            )
            ->join('sale_masters as s_m_t', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t.pid', '=', 'u_o_t_m_t_r.pid')
                    ->whereBetween('s_m_t.customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t.date_cancelled');
            })
            ->join('users', 'users.id', 'u_o_t_m_t_r.user_id')
            ->where('u_o_t_m_t_r.status', 6)
            ->where('u_o_t_m_t_r.is_move_to_recon', 1)
            ->whereIn('u_o_t_m_t_r.user_id', $userIds)
            ->where('u_o_t_m_t_r.is_displayed', '1')
            ->groupBy('u_o_t_m_t_r.user_id');

        $moveToReconOverridesResults = new Collection;
        foreach ($payFrequencyTypes as $frequency) {
            $results = [];
            $query = clone $moveToReconOverRideQuery;

            if ($frequency === 'Weekly') {
                $query->join('weekly_pay_frequencies as w_p_f', function ($join) {
                    $join->on('w_p_f.pay_period_from', '=', 'u_o_t_m_t_r.pay_period_from')
                        ->on('w_p_f.pay_period_to', '=', 'u_o_t_m_t_r.pay_period_to')
                        ->where('w_p_f.closed_status', '=', 1);
                });
                $results = $query->get();
            } elseif ($frequency === 'Monthly') {
                $query->join('monthly_pay_frequencies as m_p_f', function ($join) {
                    $join->on('m_p_f.pay_period_from', '=', 'u_o_t_m_t_r.pay_period_from')
                        ->on('m_p_f.pay_period_to', '=', 'u_o_t_m_t_r.pay_period_to')
                        ->where('m_p_f.closed_status', '=', 1);
                });
                $results = $query->get();
            }
            $moveToReconOverridesResults = $moveToReconOverridesResults->merge($results);
        }

        // /* subQuery of clawback-settlement */
        $clawbackSubQuery = DB::table('clawback_settlements AS c_s_t')
            ->select(
                'c_s_t.pid',
                'c_s_t.user_id AS c_s_t_user_id',
                DB::raw('SUM(c_s_t.clawback_amount) AS total_clawback_amount'),
                DB::raw('(
                        SELECT SUM(r_a_t.adjustment_amount)
                        FROM recon_adjustments AS r_a_t
                        WHERE r_a_t.user_id = c_s_t.user_id
                        AND r_a_t.adjustment_type = "clawback"
                        AND r_a_t.start_date = "'.$startDate.'"
                        AND r_a_t.end_date = "'.$endDate.'"
                        AND r_a_t.payroll_id IS NULL
                        AND r_a_t.pay_period_from IS NULL
                        AND r_a_t.pay_period_to IS NULL
                        AND r_a_t.payroll_status IS NULL
                    ) AS recon_adjustments_clawback_amount')
            )
            ->join('sale_masters AS s_m_t_c_s', function ($join) {
                $join->on('s_m_t_c_s.pid', '=', 'c_s_t.pid');
            })
            ->where('c_s_t.clawback_type', 'reconciliation')
            ->whereIn('c_s_t.user_id', $userIds)
            ->where('c_s_t.payroll_id', 0)
            ->where('c_s_t.status', '!=', 3)
            ->where('c_s_t.is_displayed', '1')
            ->groupBy('c_s_t.user_id');

        $moveToReconClawbackQuery = DB::table('clawback_settlements AS c_s_t_m_t_r')
            ->select(
                'c_s_t_m_t_r.user_id AS c_s_t_m_t_r_user_id',
                DB::raw('GROUP_CONCAT(DISTINCT c_s_t_m_t_r.pid) AS c_s_t_m_t_r_pid'),
                DB::raw('SUM(c_s_t_m_t_r.clawback_amount) AS total_move_to_recon_clawback_amount'),
                DB::raw('(
                        SELECT SUM(r_a_t.adjustment_amount)
                        FROM recon_adjustments AS r_a_t
                        WHERE r_a_t.user_id = c_s_t_m_t_r.user_id
                        AND r_a_t.adjustment_type = "clawback"
                        AND r_a_t.start_date = "'.$startDate.'"
                        AND r_a_t.end_date = "'.$endDate.'"
                        AND r_a_t.payroll_id IS NULL
                        AND r_a_t.pay_period_from IS NULL
                        AND r_a_t.pay_period_to IS NULL
                        AND r_a_t.payroll_status IS NULL
                    ) AS recon_adjustments_move_to_recon_clawback_amount')
            )
            ->join('sale_masters AS s_m_t', function ($query) {
                $query->on('s_m_t.pid', '=', 'c_s_t_m_t_r.pid');
            })
            ->where('c_s_t_m_t_r.status', 6)
            ->where('c_s_t_m_t_r.is_move_to_recon', 1)
            ->whereIn('c_s_t_m_t_r.user_id', $userIds)
            ->where('c_s_t_m_t_r.is_displayed', '1')
            ->groupBy('c_s_t_m_t_r.user_id');

        $moveToReconClawbackResults = new Collection;
        foreach ($payFrequencyTypes as $frequency) {
            $results = [];
            $query = clone $moveToReconClawbackQuery;

            if ($frequency === 'Weekly') {
                $query->join('weekly_pay_frequencies as w_p_f_move_to_recon_clawback', function ($join) {
                    $join->on('w_p_f_move_to_recon_clawback.pay_period_from', '=', 'c_s_t_m_t_r.pay_period_from')
                        ->on('w_p_f_move_to_recon_clawback.pay_period_to', '=', 'c_s_t_m_t_r.pay_period_to')
                        ->where('w_p_f_move_to_recon_clawback.closed_status', '=', 1);
                });
                $results = $query->get();
            } elseif ($frequency === 'Monthly') {
                $query->join('monthly_pay_frequencies as m_p_f_move_to_recon_clawback', function ($join) {
                    $join->on('m_p_f_move_to_recon_clawback.pay_period_from', '=', 'c_s_t_m_t_r.pay_period_from')
                        ->on('m_p_f_move_to_recon_clawback.pay_period_to', '=', 'c_s_t_m_t_r.pay_period_to')
                        ->where('m_p_f_move_to_recon_clawback.closed_status', '=', 1);
                });
                $results = $query->get();
            }
            $moveToReconClawbackResults = $moveToReconClawbackResults->merge($results);
        }

        $deductionQuery = DB::table('payroll_deductions As p_d_t')
            ->join('users', 'users.id', '=', 'p_d_t.user_id')
            ->select(
                'p_d_t.id AS p_d_t_id',
                'p_d_t.user_id AS p_d_t_user_id',
                DB::raw('SUM(p_d_t.total) AS total_p_d_t_amount')
            )
            ->where('p_d_t.status', 6)
            ->where('p_d_t.is_move_to_recon', 1)
            ->where('p_d_t.is_move_to_recon_paid', 0)
            ->whereBetween('p_d_t.pay_period_from', [$startDate, $endDate])
            ->whereBetween('p_d_t.pay_period_to', [$startDate, $endDate])
            ->whereIn('p_d_t.user_id', $userIds)
            ->groupBy('p_d_t.user_id');

        $deductionsResults = new Collection;
        foreach ($payFrequencyTypes as $frequency) {
            $results = [];
            $query = clone $deductionQuery;

            if ($frequency === 'Weekly') {
                $query->join('weekly_pay_frequencies as w_p_f_move_to_recon_payroll_deduction', function ($join) {
                    $join->on('w_p_f_move_to_recon_payroll_deduction.pay_period_from', '=', 'p_d_t.pay_period_from')
                        ->on('w_p_f_move_to_recon_payroll_deduction.pay_period_to', '=', 'p_d_t.pay_period_to')
                        ->where('w_p_f_move_to_recon_payroll_deduction.closed_status', '=', 1);
                });
                $results = $query->get();
            } elseif ($frequency === 'Monthly') {
                $query->join('monthly_pay_frequencies as m_p_f_move_to_recon_payroll_deduction', function ($join) {
                    $join->on('m_p_f_move_to_recon_payroll_deduction.pay_period_from', '=', 'p_d_t.pay_period_from')
                        ->on('m_p_f_move_to_recon_payroll_deduction.pay_period_to', '=', 'p_d_t.pay_period_to')
                        ->where('m_p_f_move_to_recon_payroll_deduction.closed_status', '=', 1);
                });
                $results = $query->get();
            }
            $deductionsResults = $deductionsResults->merge($results);
        }

        $finalData = [];
        // Execute each sub-query and merge results into the final data array
        $withholdsResults = $withholdsSubQuery->get();
        foreach ($withholdsResults as $result) {
            $userId = $result->user_id;
            $finalData[$userId]['allPids']['recon-commission'] = [];
            $finalData[$userId]['total_withhold_amount'] = 0;
            $finalData[$userId]['total_clawback_amount'] = 0;
            $finalData[$userId]['recon_adjustment_commission_amount'] = 0;
            if (isset($finalData[$userId]['is_ineligible']) && $finalData[$userId]['is_ineligible'] != 1) {
                //
            } else {
                $finalData[$userId]['is_ineligible'] = 0;
            }

            $withholds = json_decode($result->withholds, true);
            if ($withholds) {
                foreach ($withholds as $withhold) {
                    $m2Date = Carbon::parse($withhold['m2_date']);
                    $with = ReconCommissionHistory::where(['user_id' => $userId, 'move_from_payroll' => 0, 'is_ineligible' => '0', 'is_displayed' => '1'])->where('pid', $withhold['pid'])->sum('paid_amount');
                    $r = $withhold['withhold_amount'];
                    $go = 0;
                    if ($with != $r) {
                        $finalData[$userId]['allPids']['recon-commission'][] = $withhold['pid'];
                        $go = 1;
                    }
                    if ($withhold['clawback_amount'] && ! ReconClawbackHistory::where(['user_id' => $userId, 'pid' => $withhold['pid'], 'adders_type' => 'recon-commission', 'is_displayed' => '1'])->exists()) {
                        $finalData[$userId]['allPids']['recon-commission'][] = $withhold['pid'];
                        $go = 1;
                    }
                    if ($go) {
                        if ($m2Date->between($startDate, $endDate)) {
                            $finalData[$userId]['total_withhold_amount'] += $withhold['withhold_amount'];
                            $finalData[$userId]['total_clawback_amount'] += $withhold['clawback_amount'];
                            $finalData[$userId]['recon_adjustment_commission_amount'] += $withhold['adjustment_amount'];
                        } else {
                            $finalData[$userId]['is_ineligible'] = 1;
                        }
                    }
                }
            }
            $finalData[$userId]['emp_img'] = $result->emp_img;
            $finalData[$userId]['first_name'] = $result->first_name;
            $finalData[$userId]['last_name'] = $result->last_name;
        }

        // moveToReconCommissionResults
        foreach ($moveToReconCommissionResults as $result) {
            $userId = $result->user_id;
            $finalData[$userId]['allPids']['move-top-recon-commission'] = [];
            $finalData[$userId]['moveToReconCommission'] = 0;
            $finalData[$userId]['recon_adjustments_move_to_recon_commission_amount'] = 0;
            if (isset($finalData[$userId]['is_ineligible']) && $finalData[$userId]['is_ineligible'] != 1) {
                //
            } else {
                $finalData[$userId]['is_ineligible'] = 0;
            }

            $commissions = json_decode($result->commissions, true);
            if ($commissions) {
                foreach ($commissions as $commission) {
                    $m2Date = Carbon::parse($commission['m2_date']);
                    if ($m2Date->between($startDate, $endDate)) {
                        $finalData[$userId]['allPids']['move-top-recon-commission'][] = $commission['pid'];
                        $finalData[$userId]['moveToReconCommission'] += $commission['commission_amount'];
                        $finalData[$userId]['recon_adjustments_move_to_recon_commission_amount'] += $commission['adjustment_amount'];
                    } else {
                        $finalData[$userId]['is_ineligible'] = 1;
                    }
                }
            }
        }

        $overridesResults = $overridesSubQuery->get();
        foreach ($overridesResults as $result) {
            $userId = $result->user_id;
            $finalData[$userId]['allPids']['recon-override'] = [];
            $finalData[$userId]['overrides'] = 0;
            $finalData[$userId]['recon_adjustments_override_amount'] = 0;
            if (isset($finalData[$userId]['is_ineligible']) && $finalData[$userId]['is_ineligible'] != 1) {
                //
            } else {
                $finalData[$userId]['is_ineligible'] = 0;
            }

            $overrides = json_decode($result->overrides, true);
            if ($overrides) {
                foreach ($overrides as $override) {
                    $m2Date = Carbon::parse($override['m2_date']);
                    if ($m2Date->between($startDate, $endDate)) {
                        $finalData[$userId]['allPids']['recon-override'][] = $override['pid'];
                        $finalData[$userId]['overrides'] += $override['override_amount'];
                        $finalData[$userId]['recon_adjustments_override_amount'] += $override['adjustment_amount'];
                    } else {
                        $finalData[$userId]['is_ineligible'] = 1;
                    }
                }
            }
        }

        foreach ($moveToReconOverridesResults as $result) {
            $userId = $result->user_id;
            $finalData[$userId]['allPids']['move-top-recon-override'] = [];
            $finalData[$userId]['moveToReconOverrides'] = 0;
            $finalData[$userId]['recon_adjustments_move_to_recon_override_amount'] = 0;
            if (isset($finalData[$userId]['is_ineligible']) && $finalData[$userId]['is_ineligible'] != 1) {
                //
            } else {
                $finalData[$userId]['is_ineligible'] = 0;
            }

            $overrides = json_decode($result->overrides, true);
            if ($overrides) {
                foreach ($overrides as $override) {
                    $m2Date = Carbon::parse($override['m2_date']);
                    if ($m2Date->between($startDate, $endDate)) {
                        $finalData[$userId]['allPids']['moveToReconOverrides'][] = $override['pid'];
                        $finalData[$userId]['moveToReconOverrides'] += $override['override_amount'];
                        $finalData[$userId]['recon_adjustments_move_to_recon_override_amount'] += $override['adjustment_amount'];
                    } else {
                        $finalData[$userId]['is_ineligible'] = 1;
                    }
                }
            }
        }

        $clawbackResults = $clawbackSubQuery->get();
        foreach ($clawbackResults as $result) {
            $userId = $result->c_s_t_user_id;
            $finalData[$userId]['allPids']['recon-clawback'][] = $result->pid;
            $finalData[$userId]['clawback'] = $result->total_clawback_amount;
            $finalData[$userId]['recon_adjustments_clawback_amount'] = $result->recon_adjustments_clawback_amount;
        }

        $moveToReconClawbackResults = $moveToReconClawbackQuery->get();
        foreach ($moveToReconClawbackResults as $result) {
            $userId = $result->c_s_t_m_t_r_user_id;
            $finalData[$userId]['allPids']['move-to-recon-clawback'][] = $result->c_s_t_m_t_r_pid;
            $finalData[$userId]['move_to_recon_clawback'] = $result->total_move_to_recon_clawback_amount;
            $finalData[$userId]['recon_adjustments_move_to_recon_clawback_amount'] = $result->recon_adjustments_move_to_recon_clawback_amount;
        }

        $deductionsResults = $deductionQuery->get();
        foreach ($deductionsResults as $result) {
            $userId = $result->p_d_t_user_id;
            $finalData[$userId]['deductions'] = $result->total_p_d_t_amount;
        }

        $response = [];
        foreach ($finalData as $userId => $value) {
            $totalReconCommissionFinalizeAmount = 0;
            $finalizeCommission = 0;
            $moveToReconFinalizeCommission = 0;
            $finalizeOverride = 0;
            $finalizeMoveToReconOverride = 0;
            $finalizeMoveToReconClawback = 0;
            $finalizeClawback = 0;

            $allPids = [];
            if (isset($value['allPids']['recon-commission'])) {
                $pids = $value['allPids']['recon-commission'];
                // foreach ($value['allPids']["recon-commission"] as $pidString) {
                //     $pids = array_merge($pids, explode(',', $pidString));
                // }
                $allPids = array_merge($allPids, $pids);
                $finalizeCommission = ReconCommissionHistory::where(['user_id' => $userId, 'move_from_payroll' => 0, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('pid', $pids)->sum('paid_amount');
            }
            if (isset($value['allPids']['move-top-recon-commission'])) {
                $pids = $value['allPids']['move-top-recon-commission'];
                // $pids = [];
                // foreach ($value['allPids']["move-top-recon-commission"] as $pidString) {
                //     $pids = array_merge($pids, explode(',', $pidString));
                // }
                $allPids = array_merge($allPids, $pids);
                $moveToReconFinalizeCommission = ReconCommissionHistory::where(['user_id' => $userId, 'move_from_payroll' => 1, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('pid', $pids)->sum('paid_amount');
            }
            if (isset($value['allPids']['recon-override'])) {
                $pids = $value['allPids']['recon-override'];
                // $pids = [];
                // foreach ($value['allPids']["recon-override"] as $pidString) {
                //     $pids = array_merge($pids, explode(',', $pidString));
                // }
                $allPids = array_merge($allPids, $pids);
                $finalizeOverride = ReconOverrideHistory::where(['user_id' => $userId, 'move_from_payroll' => 0, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('pid', $pids)->sum('paid');
            }
            if (isset($value['allPids']['move-top-recon-override'])) {
                $pids = $value['allPids']['move-top-recon-override'];
                // $pids = [];
                // foreach ($value['allPids']["move-top-recon-override"] as $pidString) {
                //     $pids = array_merge($pids, explode(',', $pidString));
                // }
                $allPids = array_merge($allPids, $pids);
                $finalizeMoveToReconOverride = ReconOverrideHistory::where(['user_id' => $userId, 'move_from_payroll' => 1, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('pid', $pids)->sum('paid');
            }
            if (isset($value['allPids']['recon-clawback'])) {
                $pids = $value['allPids']['recon-clawback'];
                // $pids = [];
                // foreach ($value['allPids']["recon-clawback"] as $pidString) {
                //     $pids = array_merge($pids, explode(',', $pidString));
                // }
                $allPids = array_merge($allPids, $pids);
                $finalizeClawback = ReconClawbackHistory::where(['user_id' => $userId, 'move_from_payroll' => '0', 'is_displayed' => '1'])->whereIn('pid', $pids)->sum('paid_amount');
            }
            if (isset($value['allPids']['move-to-recon-clawback'])) {
                $pids = $value['allPids']['move-to-recon-clawback'];
                // $pids = [];
                // foreach ($value['allPids']["move-to-recon-clawback"] as $pidString) {
                //     $pids = array_merge($pids, explode(',', $pidString));
                // }
                $allPids = array_merge($allPids, $pids);
                $finalizeMoveToReconClawback = ReconClawbackHistory::where(['user_id' => $userId, 'move_from_payroll' => 1, 'is_displayed' => '1'])->whereIn('pid', $pids)->sum('paid_amount');
            }
            $uniquePids = array_unique($allPids);

            $userData = User::find($userId);
            $userSkip = $this->checkIfUserSkipped($userData->id, $startDate, $endDate);

            $imageUrl = null;
            if ($userData->image) {
                $imageUrl = s3_getTempUrl(config('app.domain_name').'/'.$userData->image);
            }

            /* commission amount calculation */
            $totalMoveToReconCommissionAmount = isset($value['moveToReconCommission']) ? $value['moveToReconCommission'] : 0;
            $totalReconCommissionFinalizeAmount = $moveToReconFinalizeCommission + $finalizeCommission;
            $totalWithHoldAmount = (isset($value['total_withhold_amount']) ? round($value['total_withhold_amount'], 2) : 0) + $totalMoveToReconCommissionAmount - round($totalReconCommissionFinalizeAmount, 2);
            $reconCommissionAdjustment = isset($value['recon_adjustment_commission_amount']) ? $value['recon_adjustment_commission_amount'] : 0;
            $moveToReconCommissionAdjustment = isset($value['recon_adjustments_move_to_recon_commission_amount']) ? $value['recon_adjustments_move_to_recon_commission_amount'] : 0;
            $totalCommissionAdjustment = $reconCommissionAdjustment + $moveToReconCommissionAdjustment;

            /* override amount calculation */
            $totalMoveToReconOverrideAmount = (isset($value['moveToReconOverrides']) ? $value['moveToReconOverrides'] : 0);
            $totalReconOverrideFinalizeAmount = $finalizeOverride + $finalizeMoveToReconOverride;
            $totalOverrideAmount = (isset($value['overrides']) ? $value['overrides'] : 0) + $totalMoveToReconOverrideAmount - round($totalReconOverrideFinalizeAmount, 2);
            $reconOverrideAdjustment = isset($value['recon_adjustments_override_amount']) ? $value['recon_adjustments_override_amount'] : 0;
            $reconOverrideMoveToReconAdjustment = isset($value['recon_adjustments_move_to_recon_override_amount']) ? $value['recon_adjustments_move_to_recon_override_amount'] : 0;
            $totalReconOverrideAdjustment = $reconOverrideAdjustment + $reconOverrideMoveToReconAdjustment;

            /* clawback amount calculation */
            $totalReconClawbackAmount = (isset($value['clawback']) ? $value['clawback'] : 0);
            $totalMoveToReconClawbackAmount = (isset($value['move_to_recon_clawback']) ? $value['move_to_recon_clawback'] : 0);
            $totalFinalizeClawbackAmount = $finalizeClawback + $finalizeMoveToReconClawback;
            $totalReconAmount = $totalReconClawbackAmount + $totalMoveToReconClawbackAmount;
            $finalClawbackAmount = -1 * ($totalReconAmount - $totalFinalizeClawbackAmount);
            /* deduction amount calculation */
            $totalDeductionAmount = isset($value['deductions']) ? -1 * $value['deductions'] : 0;

            /* clawback adjustment */
            $totalDueAmount = floatval($totalWithHoldAmount) + floatval($totalOverrideAmount);
            $clawbackAdjustment = isset($value['recon_adjustments_clawback_amount']) ? $value['recon_adjustments_clawback_amount'] : 0;
            $moveToReconClawbackAdjustment = isset($value['recon_adjustments_move_to_recon_clawback_amount']) ? $value['recon_adjustments_move_to_recon_clawback_amount'] : 0;
            $totalClawbackAdjustment = $clawbackAdjustment + $moveToReconClawbackAdjustment;

            $adjustments = ($totalCommissionAdjustment + $totalReconOverrideAdjustment + $totalClawbackAdjustment) ?? 0;
            $totalAdjustment = ($totalCommissionAdjustment + $totalReconOverrideAdjustment + $totalClawbackAdjustment + $totalDeductionAmount) ?? 0;
            if ($totalOverrideAmount < 0.5 && $totalWithHoldAmount < 0.5) {
                $totalDueAmount = 0;
                $payout = $totalWithHoldAmount + $totalOverrideAmount + $finalClawbackAmount + $totalAdjustment;
            } else {
                if ($totalWithHoldAmount < 0.5) {
                    $totalDueAmount = ($totalOverrideAmount) * ($reconPayout / 100);
                    $payout = $totalDueAmount + $totalAdjustment + $finalClawbackAmount + $totalWithHoldAmount;
                }
                if ($totalOverrideAmount < 0.5) {
                    $totalDueAmount = ($totalWithHoldAmount) * ($reconPayout / 100);
                    $payout = $totalDueAmount + $totalAdjustment + $finalClawbackAmount + $totalOverrideAmount;
                }

                if ($totalOverrideAmount > 0.5 && $totalWithHoldAmount > 0.5) {
                    $totalDueAmount = ($totalWithHoldAmount + $totalOverrideAmount) * ($reconPayout / 100);
                    $payout = $totalDueAmount + $finalClawbackAmount + $totalAdjustment;
                }
            }
            $is_ineligible = isset($value['is_ineligible']) ? $value['is_ineligible'] : 0;

            if ($totalDueAmount != 0 || $payout != 0 || $finalClawbackAmount != 0 || $is_ineligible) {
                $response[] = [
                    'user_id' => $userId,
                    'pids' => $uniquePids,
                    'emp_img' => $userData->emp_img ? $userData->emp_img : 'Employee_profile/default-user.png',
                    'emp_name' => $userData->first_name.' '.$userData->last_name,
                    'emp_img_s3' => $imageUrl,
                    'commissionWithholding' => $totalWithHoldAmount,
                    'overrideDue' => $totalOverrideAmount,
                    'total_due' => ($totalWithHoldAmount + $totalOverrideAmount),
                    'clawbackDue' => $finalClawbackAmount,
                    'payout' => $payout,
                    'percentage' => $reconPayout,
                    'totalAdjustments' => $totalAdjustment,
                    'adjustments' => $adjustments,
                    'deductions' => $totalDeductionAmount,
                    'total_pay' => $totalDueAmount,
                    'user_skip' => $userSkip ?? 0,
                    'is_super_admin' => $userData->is_super_admin,
                    'is_manager' => $userData->is_manager,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'frequency_type_id' => $userData->positionpayfrequencies->frequency_type_id,
                    'frequency_type_name' => $userData->positionpayfrequencies->frequencyType->name,
                ];
            }
        }

        return [
            'data' => $response,
            'userIds' => array_keys($finalData),
        ];
    }

    /* get move to recon data function */
    private function getMoveToReconData($startDate, $endDate, $userId, $search = '')
    {
        // Retrieve UserCommission entries with status 6 and is_move_to_recon 1
        $userCommissions = UserCommission::with(['userdata', 'saledata' => function ($query) use ($search) {
            if (! empty($search)) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('pid', 'LIKE', '%'.$search.'%')
                        ->orWhere('customer_name', 'LIKE', '%'.$search.'%');
                });
            }
        }, 'payrollAdjustmentAmount' => function ($query) use ($userId) {
            $query->where('user_id', $userId)
                /* ->where('pid', $pid) */;
        }])->where('status', 6)
            ->where('is_move_to_recon', 1)
            ->where('user_id', $userId)
            ->get();
        // Filter UserCommission entries that fall within the m2_date range
        $filteredByM2Date = $userCommissions->filter(function ($commission) use ($startDate, $endDate) {
            if ($commission->saledata && $commission->saledata->m2_date) {
                $m2Date = $commission->saledata->m2_date;

                return Carbon::parse($m2Date) >= Carbon::parse($startDate) && Carbon::parse($m2Date) <= Carbon::parse($endDate);
            }

            return false;
        });

        $filteredCommissions = collect();

        foreach ($filteredByM2Date as $commission) {
            $payFrequency = $this->getUserBasedExecutedPayFrequencyDate($commission->userdata);

            // Filter commissions for the user based on pay frequency date ranges
            $filtered = $filteredByM2Date->filter(function ($commission) use ($payFrequency) {
                foreach ($payFrequency as $frequency) {
                    if ($commission->pay_period_from >= $frequency['pay_period_from'] && $commission->pay_period_to <= $frequency['pay_period_to']) {
                        return true;
                    }
                }

                return false;
            });

            $filteredCommissions = $filteredCommissions->merge($filtered)->unique('id');
        }

        return $filteredCommissions;
    }

    public function getOverrideMoveToReconData($startDate, $endDate, $userId)
    {
        $userCommissions = UserOverrides::with(['userdata', 'salesDetail'])
            ->where('status', 6)
            ->where('is_move_to_recon', 1)
            ->where('user_id', $userId)
            ->get();
        // Filter UserCommission entries that fall within the m2_date range
        $filteredByM2Date = $userCommissions->filter(function ($commission) use ($startDate, $endDate) {
            if ($commission->salesDetail && $commission->salesDetail->m2_date) {
                $m2Date = $commission->salesDetail->m2_date;

                return $m2Date >= Carbon::parse($startDate) && $m2Date <= Carbon::parse($endDate);
            }

            return false;
        });
        $filteredCommissions = collect();

        foreach ($filteredByM2Date as $commission) {
            $payFrequency = $this->getUserBasedExecutedPayFrequencyDate($commission->userdata);

            // Filter commissions for the user based on pay frequency date ranges
            $filtered = $filteredByM2Date->filter(function ($commission) use ($payFrequency) {
                foreach ($payFrequency as $frequency) {
                    if ($commission->pay_period_from >= $frequency['pay_period_from'] && $commission->pay_period_to <= $frequency['pay_period_to']) {
                        return true;
                    }
                }

                return false;
            });

            $filteredCommissions = $filteredCommissions->merge($filtered)->unique('id');
        }

        return $filteredCommissions;
    }

    /**
     * Method runPayrollReconciliationPopUp:  This function is used for payroll-recon popup
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
    public function runPayrollReconciliationPopUp(Request $request, $id)
    {
        $valid = validator($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        if ($valid->fails()) {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation Commission By employee Id',
                'status' => false,
                'message' => $valid->errors()->first(),
            ]);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;
        $withHoldData = UserReconciliationWithholding::whereHas('salesDetail', function ($query) use ($startDate, $endDate, $search) {
            $query->whereBetween('m2_date', [$startDate, $endDate]);
            // Apply search parameters if they exist
            if (! empty($search)) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('pid', 'LIKE', '%'.$search.'%')
                        ->orWhere('customer_name', 'LIKE', '%'.$search.'%');
                });
            }
        })->where(function ($qry) use ($id) {
            $qry->where('closer_id', $id)->orWhere('setter_id', $id);
        })
            ->where('status', '!=', 'paid')
            ->where('finalize_status', 0)
            ->where('withhold_amount', '!=', 0)
            ->get();
        $moveToReconCommissionData = $this->getMoveToReconData($startDate, $endDate, $id, $search);

        $combinedData = collect($withHoldData)->merge($moveToReconCommissionData) /* ->unique('id') */;
        $groupedData = $combinedData->groupBy('user_id');

        $array = [];
        $subtotal = 0;
        $array = [];
        foreach ($combinedData as $data) {
            $reconType = 'move_to_recon';
            $userId = $data->user_id;
            if (! $userId) {
                $userId = $data->closer_id ?? $data->setter_id;
                $reconType = 'reconciliation';
            }
            $redline = $data->redline;
            if (! $redline) {
                $redline = $data->closer ? $data->closer->redline : $data->setter->redline;
            }
            $location = Locations::with('State')
                ->where('general_code', $data->salesDetail->customer_state ?? $data->saledata->customer_state)
                ->first();

            $payout = ReconciliationFinalizeHistory::where('pid', $data->pid)
                ->where('user_id', $userId)
                ->where('status', 'payroll');

            $adjustmentComment = ReconciliationAdjustmentDetails::where('user_id', $id)
                ->where('pid', $data->pid)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->where('adjustment_type', 'commission')
                ->first();

            $adjustmentDetails = ReconciliationAdjustmentDetails::where('user_id', $id)
                ->where('pid', $data->pid)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->where('adjustment_type', 'commission')
                ->sum('amount');
            if ($reconType == 'move_to_recon') {
                $adjustmentDetails = $data?->payrollAdjustmentAmount?->amount ?? 0.00;
            }
            $array[] = [
                'id' => $data->id,
                'user_id' => $userId,
                'pid' => $data->pid,
                'state_id' => $location?->state?->state_code,
                'customer_name' => $data->salesDetail ? $data->salesDetail->customer_name : $data->saledata->customer_name,
                'customer_state' => $data->salesDetail ? $data->salesDetail->customer_state : $data->saledata->customer_state,
                'rep_redline' => $redline,
                'kw' => $data->salesDetail ? $data->salesDetail->kw : $data->saledata->kw,
                'net_epc' => $data->salesDetail ? $data->salesDetail->net_epc : $data->saledata->net_epc,
                'epc' => $data->salesDetail ? $data->salesDetail->epc : $data->saledata->epc,
                'adders' => $data->salesDetail ? $data->salesDetail->adders : $data->saledata->adders,
                'type' => $data->amount_type ? $data->amount_type : 'Withheld',
                'amount' => $data->withhold_amount ?? $data?->amount,
                'finalize_payout' => 0,
                'adjustment_by' => $adjustmentComment?->commentUser?->first_name.' '.$adjustmentComment?->commentUser?->last_name,
                'adjustment_comment' => $adjustmentComment?->comment,
                'adjustment_amount' => $adjustmentDetails,
                'comment_status' => isset($adjustmentComment) ? 1 : 0,
                'is_super_admin' => $adjustmentComment?->commentUser?->is_super_admin,
                'is_manager' => $adjustmentComment?->commentUser?->is_manager,
                'sub_position_id' => $adjustmentComment?->commentUser?->sub_position_id,
                'position_id' => $adjustmentComment?->commentUser?->position_id,
                'paid' => $payout->sum('paid_commission'),
                'in_recon' => ($data->withhold_amount ?? $data->amount) - floatval($payout->sum('paid_commission')),
            ];
            $subtotal += $data->withhold_amount ?? $data->amount;
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commission By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $array,
            'subtotal' => $subtotal,
        ], 200);
        $array = [];

        foreach ($groupedData as $value) {
            foreach ($value as $data) {
                $redline = $data->closer ?? $data->setter;

                $payout = ReconciliationFinalizeHistory::where('pid', $data->pid)
                    ->where('user_id', $id)
                    ->where('status', 'payroll')
                    ->first();

                $adjustmentComment = ReconciliationAdjustmentDetails::where('user_id', $id)
                    ->where('pid', $data->pid)
                    ->where('start_date', $startDate)
                    ->where('end_date', $endDate)
                    ->where('adjustment_type', 'commission')
                    ->first();

                $paidAmount = $payout->paid_commission ?? 0;
                $payOut = $payout->payout ?? 0;
                $totalPaid = @($data->withhold_amount * $payOut / 100) ?? 0;

                $location = Locations::with('State')->where('general_code', $data->salesDetail->customer_state ?? $data->saledata->customer_state)->first();
                $adjustmentDetails = ReconciliationAdjustmentDetails::where('user_id', $id)
                    ->where('pid', $data->pid)
                    ->where('start_date', $startDate)
                    ->where('end_date', $endDate)
                    ->where('adjustment_type', 'commission')
                    ->sum('amount');
                $payrollToRecon = MoveToReconciliation::where('user_id', $id)->where('pid', $data->pid);
                $payrollToRecon->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('m2_date', [$startDate, $endDate]);
                });
                $payrollToReconCommission = @$payrollToRecon->sum('commission') ?: 0;
                $totalMoveCommission = @($data->withhold_amount + $payrollToReconCommission) ?? 0;
                $recon = $totalMoveCommission - $paidAmount;
                $subtotal += @$recon ?? 0;
                // if ($totalMoveCommission > 0) {
                $array[] = [
                    'id' => $data->id,
                    'user_id' => @$redline->id,
                    'pid' => $data->pid,
                    'state_id' => $location?->state?->state_code,
                    'customer_name' => $data->salesDetail ? $data->salesDetail->customer_name : $data->saledata->customer_name,
                    'customer_state' => $data->salesDetail ? $data->salesDetail->customer_state : $data->saledata->customer_state,
                    'rep_redline' => $redline ? $redline->redline : $data->redline,
                    'kw' => $data->salesDetail ? $data->salesDetail->kw : $data->saledata->kw,
                    'net_epc' => $data->salesDetail ? $data->salesDetail->net_epc : $data->saledata->net_epc,
                    'epc' => $data->salesDetail ? $data->salesDetail->epc : $data->saledata->epc,
                    'adders' => $data->salesDetail ? $data->salesDetail->adders : $data->saledata->adders,
                    'type' => 'Withheld', // $data->type,
                    'amount' => $totalMoveCommission,
                    'paid' => $paidAmount,
                    'in_recon' => $recon,
                    'finalize_payout' => 0,
                    'adjustment_amount' => $adjustmentDetails,
                    'comment_status' => isset($adjustmentComment) ? 1 : 0,
                    'is_super_admin' => $adjustmentComment?->commentUser?->is_super_admin,
                    'is_manager' => $adjustmentComment?->commentUser?->is_manager,
                    'sub_position_id' => $adjustmentComment?->commentUser?->sub_position_id,
                    'position_id' => $adjustmentComment?->commentUser?->position_id,
                    'adjustment_by' => $adjustmentComment?->commentUser?->first_name.' '.$adjustmentComment?->commentUser?->last_name,
                    'adjustment_comment' => $adjustmentComment?->comment,
                ];
                // }
            }
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commission By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $array,
            'subtotal' => $subtotal,
        ], 200);
        $myArray = [];
        if (isset($data) && $data != '[]') {
            foreach ($data as $data) {
                $commission = $data->commission;
                $payout = $data->payout;
                $override = $data->override;
                $totalCommission = ($commission * $payout) / 100;
                $totalOverride = ($override * $payout) / 100;
                $clawback = $data->clawback;
                $adjustments = $data->adjustments;
                $total = $data->net_amount;

                $myArray[] = [
                    'added_to_payroll_on' => Carbon::parse($data->updated_at)->format('m-d-Y h:s:a'),
                    'startDate_endDate' => Carbon::parse($data->start_date)->format('m/d/Y').' to '.Carbon::parse($data->end_date)->format('m/d/Y'),
                    'commission' => $totalCommission,
                    'override' => $totalOverride,
                    'clawback' => $clawback,
                    'adjustment' => $adjustments,
                    'total' => $total,
                    'payout' => $payout,
                ];
            }
        }

        return response()->json([
            'ApiName' => 'payroll in reconciliation popup  api ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $myArray,
        ], 200);
    }

    private function normalizeIds($ids)
    {
        return $ids === 'all' ? [] : explode(',', implode(',', $ids));
    }

    private function getUserIds($officeIds, $positionIds, $search)
    {
        $userQuery = User::query();

        if (! in_array('all', $officeIds) && ! in_array('all', $positionIds)) {
            $userQuery->whereIn('office_id', $officeIds)
                ->whereIn('sub_position_id', $positionIds);
        } elseif (in_array('all', $officeIds) && ! in_array('all', $positionIds)) {
            $userQuery->whereIn('sub_position_id', $positionIds);
        } elseif (! in_array('all', $officeIds) && in_array('all', $positionIds)) {
            $userQuery->whereIn('office_id', $officeIds);
        }

        if (! empty($search)) {
            $userQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
            });
        }

        return $userQuery->pluck('id')->toArray();
    }

    private function getPids($userId, $startDate, $endDate, $type = null)
    {
        return DB::table('sale_masters')
            ->select('sale_masters.pid', 'sale_masters.customer_signoff', 'sale_masters.m2_date', 'sale_masters.date_cancelled')
            ->when($type === 'commission', function ($query) use ($userId) {
                return $query->join('sale_master_process', function ($join) use ($userId) {
                    $join->on('sale_master_process.pid', '=', 'sale_masters.pid')
                        ->where(function ($subQuery) use ($userId) {
                            $subQuery->where('sale_master_process.closer1_id', $userId)
                                ->orWhere('sale_master_process.closer2_id', $userId)
                                ->orWhere('sale_master_process.setter1_id', $userId)
                                ->orWhere('sale_master_process.setter2_id', $userId);
                        });
                });
            })
            ->whereBetween('sale_masters.customer_signoff', [$startDate, $endDate])
            ->get();
    }

    private function getUserPids($userId, $startDate, $endDate, $type = 'commission')
    {
        return DB::table('sale_masters')
            ->select('sale_masters.pid', 'sale_masters.customer_signoff', 'sale_masters.m2_date', 'sale_masters.date_cancelled')
            ->when($type == 'commission', function ($query) use ($userId) {
                return $query->join('sale_master_process', function ($join) use ($userId) {
                    $join->on('sale_master_process.pid', '=', 'sale_masters.pid')
                        ->where(function ($subQuery) use ($userId) {
                            $subQuery->where('sale_master_process.closer1_id', $userId)
                                ->orWhere('sale_master_process.closer2_id', $userId)
                                ->orWhere('sale_master_process.setter1_id', $userId)
                                ->orWhere('sale_master_process.setter2_id', $userId);
                        });
                });
            })
            ->whereBetween('sale_masters.customer_signoff', [$startDate, $endDate])
            ->get();
    }

    private function checkIfUserSkipped($userId, $startDate, $endDate)
    {
        return ReconciliationStatusForSkipedUser::where('user_id', $userId)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('status', 'skipped')->exists() ? 1 : 0;
    }

    public function paginate($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        // /* $currentPage = Paginator::resolveCurrentPage('page'); */
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function moveToReconciliations1(Request $request): JsonResponse
    {
        $data = [];
        $payrollId = $request->payrollId;
        $period_from = $request->period_from;
        $period_to = $request->period_to;
        $validator = Validator::make(
            $request->all(),
            [
                'period_from' => 'required',
                'period_to' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        if (count($payrollId) > 0) {
            $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
            $data->transform(function ($data) use ($period_from, $period_to) {
                $payroll = $data;
                $user_commission = UserCommission::where('status', 1)->where('payroll_id', $payroll->id)->get();
                if ($user_commission) {
                    $user_commission->transform(function ($user_commission_value) use ($payroll, $period_from, $period_to) {
                        $check_closer_setter = SalesMaster::with('sales_master_process')->where('pid', $user_commission_value->pid)->first();
                        if ($user_commission_value->user_id == $check_closer_setter->sales_master_process->closer1_id || $user_commission_value->user_id == $check_closer_setter->sales_master_process->closer2_id) {
                            UserReconciliationWithholding::updateOrCreate([
                                'pid' => $user_commission_value->pid,
                                'payroll_id' => $payroll->id,
                            ], [
                                'pid' => $user_commission_value->pid,
                                'payroll_id' => $payroll->id,
                                'closer_id' => $user_commission_value->user_id,
                                'withhold_amount' => $user_commission_value->amount,
                                'payroll_to_recon_status' => 1,
                                'status' => 'unpaid',
                                'pay_period_from' => $period_from,
                                'pay_period_to' => $period_to,
                            ]);
                        } elseif ($user_commission_value->user_id == $check_closer_setter->sales_master_process->setter1_id || $user_commission_value->user_id == $check_closer_setter->sales_master_process->setter2_id) {
                            UserReconciliationWithholding::updateOrCreate([
                                'pid' => $user_commission_value->pid,
                                'payroll_id' => $payroll->id,
                            ], [
                                'pid' => $user_commission_value->pid,
                                'payroll_id' => $payroll->id,
                                'setter_id' => $user_commission_value->user_id,
                                'withhold_amount' => $user_commission_value->amount,
                                'payroll_to_recon_status' => 1,
                                'status' => 'unpaid',
                                'pay_period_from' => $period_from,
                                'pay_period_to' => $period_to,
                            ]);
                        }
                        UserCommission::where('id', $user_commission_value->id)->where('user_id', $user_commission_value->user_id)->update(['status' => 6]); // 6 for Reconciliation Adjustments
                    });
                    UserOverrides::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]);
                    Payroll::where(['id' => $data->id])->update(['status' => 6]);
                }
            });
        }

        return response()->json([
            'ApiName' => 'Mark_As_Paid',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function getUserBasedExecutedPayFrequencyDate($userData)
    {
        $getUserFrequencyType = $userData->positionpayfrequencies->frequencyType->toArray();
        switch ($getUserFrequencyType['id']) {
            case '2':
                $payFrequencyDate = WeeklyPayFrequency::select('pay_period_from', 'pay_period_to')->where('closed_status', 1)->get();
                break;
            case '3':
                $payFrequencyDate = AdditionalPayFrequency::select('pay_period_from', 'pay_period_to')
                    ->where([
                        'closed_status' => 1,
                        'type' => 1,
                    ])
                    ->get();
                break;
            case '4':
                $payFrequencyDate = AdditionalPayFrequency::select('pay_period_from', 'pay_period_to')
                    ->where([
                        'closed_status' => 1,
                        'type' => 2,
                    ])
                    ->get();
                break;
            case '5':
                $payFrequencyDate = MonthlyPayFrequency::select('pay_period_from', 'pay_period_to')->where('closed_status', 1)->get();
                break;

            default:
                $payFrequencyDate = [];
                break;
        }

        return $payFrequencyDate->toArray();
    }

    public function getWithholdData($userId, $eligiblePids, $startDate, $endDate, $isFinalize)
    {
        $reconCommissionAmount = DB::table('user_commission as recon_commission_table')
            ->where('settlement_type', 'reconciliation')
            ->where('amount_type', 'reconciliation')
            ->where('user_id', $userId)
            ->where('status', 3)
            ->whereIn('pid', $eligiblePids)
            ->where('is_move_to_recon', 0)
            ->sum('amount');

        $moveToReconCommissionAmount = 0;
        $paidMoveToReconCommissionAmount = 0;

        if ($isFinalize) {
            $paidReconAmount = ReconCommissionHistory::where(['user_id' => $userId, 'move_from_payroll' => 0, 'is_ineligible' => '0', 'is_displayed' => '1'])->where('status', 'payroll')->whereIn('pid', $eligiblePids)->sum('paid_amount');
        } else {
            $paidReconAmount = ReconCommissionHistory::where(['user_id' => $userId, 'move_from_payroll' => 0, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('status', ['payroll', 'finalize'])->whereIn('pid', $eligiblePids)->sum('paid_amount');
        }
        $totalAmount = $reconCommissionAmount + $moveToReconCommissionAmount;
        $totalPaidAmount = $paidReconAmount + $paidMoveToReconCommissionAmount;

        return [
            'totalAmount' => $totalAmount,
            'paidAmount' => $totalPaidAmount,
        ];
    }

    public function getOverrideReconData($userId, $eligiblePids, $startDate, $endDate, $isFinalize)
    {
        $reconOverrideAmount = DB::table('user_overrides as recon_override_table')
            ->where('overrides_settlement_type', 'reconciliation')
            ->where('user_id', $userId)
            ->whereIn('pid', $eligiblePids)
            ->where('status', 3)
            ->sum('amount');

        $moveToReconOverrideAmount = 0;
        $paidMoveToReconOverrideAmount = 0;

        if ($isFinalize) {
            $paidReconAmount = ReconOverrideHistory::where(['user_id' => $userId, 'move_from_payroll' => 0, 'is_ineligible' => '0', 'is_displayed' => '1'])->where('status', 'payroll')->whereIn('pid', $eligiblePids)->sum('paid');
        } else {
            $paidReconAmount = ReconOverrideHistory::where(['user_id' => $userId, 'move_from_payroll' => 0, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('status', ['payroll', 'finalize'])->whereIn('pid', $eligiblePids)->sum('paid');
        }
        $totalAmount = $reconOverrideAmount + $moveToReconOverrideAmount;
        $totalPaidAmount = $paidReconAmount + $paidMoveToReconOverrideAmount;

        return [
            'totalAmount' => $totalAmount,
            'paidAmount' => $totalPaidAmount,
        ];
    }
}
