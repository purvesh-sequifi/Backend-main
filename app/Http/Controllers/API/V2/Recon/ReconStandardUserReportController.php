<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Http\Controllers\API\V1\ReconStandardReportController;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollDeductions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationAdjustmentDetails;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconDeductionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReconStandardUserReportController extends ReconStandardReportController
{
    public $isUpfront = false;

    public function __construct()
    {
        $companyProfile = CompanyProfile::first();
        $this->isUpfront = $companyProfile->deduct_any_available_reconciliation_upfront;
    }

    private function userReportValidation($request)
    {
        return Validator::make($request->all(), [
            'user_id' => ['required', function ($attribute, $value, $fail) { // NOSONAR
                if ($value != 'all' && ! User::where('id', $value)->exists()) {
                    $fail('This user is not exists in our system.');
                }
            }],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'finalize_count' => 'required',
        ]);
    }

    public function topHeaderReportData(Request $request): JsonResponse
    {
        $validate = $this->userReportValidation($request);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $finalizeHistoryData = ReconciliationFinalizeHistory::whereDate('start_date', $request->start_date)
            ->whereDate('end_date', $request->end_date)
            ->where('user_id', $request->user_id)
            ->where('finalize_id', $request->finalize_count)
            ->whereIn('status', ['payroll', 'clawback'])
            ->get();

        $userOverRidetotalamount = ReconOverrideHistory::whereDate('start_date', $request->start_date)
            ->whereDate('end_date', $request->end_date)
        // ->where('status', 'payroll')
            ->where('finalize_id', $request->finalize_count)
            ->where('user_id', $request->user_id)
            ->sum('total_amount');
        $userOverRidepaid = ReconOverrideHistory::whereDate('start_date', $request->start_date)
            ->whereDate('end_date', $request->end_date)
         // ->where('status', 'payroll')
            ->where('finalize_id', $request->finalize_count)
            ->where('user_id', $request->user_id)
            ->sum('paid');
        $amount = floatval($userOverRidetotalamount - $userOverRidepaid);

        // $totalCommission1 = ReconCommissionHistory::where(['user_id'=> $request->user_id, 'start_date'=> $request->start_date, 'end_date'=> $request->end_date, 'finalize_id'=> $request->finalize_count])->sum("total_amount") ?? 0;
        $totalCommissionpaid = ReconCommissionHistory::where(['user_id' => $request->user_id, 'start_date' => $request->start_date, 'end_date' => $request->end_date, 'finalize_id' => $request->finalize_count])->sum('paid_amount') ?? 0;
        // $totalOverridesum = ReconOverrideHistory::where(['user_id'=> $request->user_id, 'start_date'=> $request->start_date, 'end_date'=> $request->end_date, 'finalize_id'=> $request->finalize_count])->sum("total_amount") ?? 0;
        $totalOverridepaid = ReconOverrideHistory::where(['user_id' => $request->user_id, 'start_date' => $request->start_date, 'end_date' => $request->end_date, 'finalize_id' => $request->finalize_count])->sum('paid') ?? 0;
        $totalClawback = ReconClawbackHistory::where(['user_id' => $request->user_id, 'start_date' => $request->start_date, 'end_date' => $request->end_date, 'finalize_id' => $request->finalize_count])->sum('paid_amount') ?? 0;
        $totalAdjustment = ReconAdjustment::where(['user_id' => $request->user_id, 'start_date' => $request->start_date, 'end_date' => $request->end_date, 'finalize_id' => $request->finalize_count])->sum('adjustment_amount') ?? 0;
        $totalDeduction = ReconDeductionHistory::where(['user_id' => $request->user_id, 'start_date' => $request->start_date, 'end_date' => $request->end_date, 'finalize_id' => $request->finalize_count])->sum('total') ?? 0;
        // $totalOverride = $totalOverridesum - $totalOverridepaid;
        // $totalCommission = ($totalCommission - $totalCommissionpaid);

        $totalCommission = $totalCommissionpaid;
        $totalOverride = $totalOverridepaid;

        $response = [
            'totalCommissionRecon' => ($totalCommission + $totalOverride),
            'payout' => $finalizeHistoryData->sum('payout'),
            'clawback' => -1 * $totalClawback,
            'adjustments' => $totalAdjustment - $totalDeduction,
            'net_pay' => ($totalCommission + $totalOverride + $totalAdjustment - $totalDeduction - $totalClawback),
            'user_name' => $finalizeHistoryData[0]->user ?? '',
        ];

        return response()->json([
            self::API_NAME => 'report-top-header-data',
            'status' => true,
            'data' => $response,
        ]);
    }

    public function reconBreakDownReconGraph(Request $request): JsonResponse
    {
        $validate = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'finalize_count' => 'required',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeCount = isset($request->finalize_count) ? $request->finalize_count : null;

        $totalAccountQuery = ReconciliationFinalizeHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            // ->where("status", 'payroll')
            ->whereIn('status', ['payroll', 'clawback'])
            ->where('finalize_id', $finalizeCount)
            ->where('user_id', $userId);

        $userOverRidetotalamount = ReconOverrideHistory::whereDate('start_date', $request->start_date)
            ->whereDate('end_date', $request->end_date)
        // ->where('status', 'payroll')
            ->where('finalize_id', $request->finalize_count)
            ->where('user_id', $request->user_id)
            ->sum('total_amount');
        $userOverRidepaid = ReconOverrideHistory::whereDate('start_date', $request->start_date)
            ->whereDate('end_date', $request->end_date)
         // ->where('status', 'payroll')
            ->where('finalize_id', $request->finalize_count)
            ->where('user_id', $request->user_id)
            ->sum('paid');
        //    $totalOverrides = floatval($userOverRidetotalamount - $userOverRidepaid);
        $totalOverrides = floatval($userOverRidepaid);

        $totalCommissionsum = ReconCommissionHistory::where(['user_id' => $userId, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_id' => $finalizeCount])->sum('total_amount') ?? 0;
        $totalCommissionpaid = ReconCommissionHistory::where(['user_id' => $userId, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_id' => $finalizeCount])->sum('paid_amount') ?? 0;
        $totalCommission = $totalCommissionsum - $totalCommissionpaid;
        // $totalCommission = $totalCommissionpaid;
        $totalOverride = ReconOverrideHistory::where(['user_id' => $userId, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_id' => $finalizeCount])->sum('total_amount') ?? 0;
        $totalOverrideDue = ReconOverrideHistory::where(['user_id' => $userId, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_id' => $finalizeCount])->sum('paid') ?? 0;
        $totalOverrides = $totalOverride - $totalOverrideDue;
        $totalClawback = ReconClawbackHistory::where(['user_id' => $userId, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_id' => $finalizeCount])->sum('paid_amount') ?? 0;
        $totalAdjustment = ReconAdjustment::where(['user_id' => $userId, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_id' => $finalizeCount])->sum('adjustment_amount') ?? 0;
        $totalDeduction = ReconDeductionHistory::where(['user_id' => $userId, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_id' => $finalizeCount])->sum('total') ?? 0;

        $adjustmentTotal = ($totalAdjustment - $totalDeduction);
        $result['account']['total_account'] = $totalAccountQuery->count();
        $result['account']['commission'] = floatval($totalCommission);
        $result['account']['overrides'] = floatval($totalOverrides);
        $result['account']['adjustments'] = floatval($adjustmentTotal);
        $result['account']['clawback'] = floatval(-1 * $totalClawback);
        $result['account']['graphClawback'] = floatval(-1 * $totalClawback);

        return response()->json([
            'api_name' => 'reconBreakDownReconGraph',
            'status' => true,
            'data' => $result,
        ]);
    }

    public function getCommissionOverridesCardDetails(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'finalize_count' => 'required',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $response = [];

        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->finalize_count;

        $finalizeHistoryData = ReconciliationFinalizeHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->whereIn('status', ['payroll', 'clawback'])
            ->where('finalize_id', $finalizeId)
            ->where('user_id', $userId);

        if ($finalizeHistoryData->count() > 0) {
            $total_paid = ReconCommissionHistory::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->where('finalize_id', $finalizeId)
                ->where('user_id', $userId)
                 // ->where("payroll_execute_status", 3)
                ->sum('total_amount');

            $total_unpaid = ReconCommissionHistory::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->where('finalize_id', $finalizeId)
                ->where('user_id', $userId)
                ->where('payroll_execute_status', '!=', 3)
                ->sum('paid_amount');

            $userClawbackData = ReconClawbackHistory::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->where('finalize_id', $finalizeId)
                ->where('user_id', $userId);

            $total_earned = $finalizeHistoryData->sum('commission');
            $payout = $finalizeHistoryData->sum('payout');
            $clawback_amount = $userClawbackData->sum('paid_amount');

            $response['commissions'] = [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_earned' => floatval($total_paid),
                'total_paid' => floatval($total_earned),
                'total_due' => floatval($total_paid - $total_earned),
                'net_payout' => $payout,
                'payout' => $payout,
                'clawback_amount' => (-1 * $clawback_amount),
                'clawback_account' => $finalizeHistoryData->where('clawback', '!=', 0)->count(),
                'finalize_count' => $finalizeId,
            ];
        }

        $userOverRideData = ReconOverrideHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            // ->where('status', 'payroll')
            ->where('finalize_id', $finalizeId)
            ->where('user_id', $userId)
            ->get();

        if (count($userOverRideData) > 0) {
            $finalizeHistoryData = ReconciliationFinalizeHistory::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->whereIn('status', ['payroll', 'clawback'])
                ->where('finalize_id', $finalizeId)
                ->where('user_id', $userId);

            $response['overrides'] = [
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_earned' => floatval($userOverRideData->sum('total_amount')),
                'office' => $userOverRideData->where('type', 'Office')->sum('total_amount'),
                'direct' => $userOverRideData->where('type', 'Direct')->sum('total_amount'),
                'indirect' => $userOverRideData->where('type', 'Indirect')->sum('total_amount'),
                'stack' => $userOverRideData->where('type', 'Stack')->sum('total_amount'),
                'total_paid' => $userOverRideData->sum('paid'),
                'total_due' => floatval($userOverRideData->sum('total_amount')) - $userOverRideData->sum('paid'),
                'net_payout' => $userOverRideData->sum('paid'),
                'payout' => $finalizeHistoryData->sum('payout'),
                'finalize_count' => $finalizeId,
            ];
        } else {
            $response['overrides'] = [];
        }

        $adjustmantDetails = ReconAdjustment::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            // ->where('payroll_status', 'payroll')
            ->where('finalize_id', $finalizeId)
            ->where('user_id', $userId)
            ->get();

        $deduction = ReconDeductionHistory::where('user_id', $userId)
            ->where('finalize_id', $finalizeId)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->get();
        // return $deduction;

        $data = [];
        $data['adjustment'] = [];
        $data['deduction'] = [];
        $subtalamount = 0;
        if (count($adjustmantDetails) > 0) {
            $subTotalAdj = 0;
            foreach ($adjustmantDetails as $key => $val) {
                $adjType = $val->adjustment_type;

                if (! isset($data['adjustment'][$adjType])) {
                    // $response['adjustment'] = ['data' => []];
                    $data['adjustment'][$adjType] = 0;
                }

                $data['adjustment'][$adjType] += $val->adjustment_amount;
                $subTotalAdj += $val->adjustment_amount;
            }

            $data['adjustment']['total_adjustment'] = $subTotalAdj;
            $subtalamount = $subTotalAdj;
        }
        // return $subtalamount;

        $deductionAmount = 0;
        $deductionOutstanding = 0;
        if (count($deduction) > 0) {

            $deductionAmount = (-1 * $deduction->sum('total'));
            $deductionOutstanding = $deduction->sum('outstanding');
            foreach ($deduction as $key => $val) {

                $costCenterName = $val->costcenter->name;

                if (! isset($data['deduction'][$costCenterName])) {
                    $data['deduction'][$costCenterName] = 0;
                }

                $data['deduction'][$costCenterName] += (-1 * $val->total);
            }

            $data['deduction']['total_deduction'] = round($deductionAmount, 2);
        }

        $response['adjustment_deduction'] = $data;
        $response['adjustment_deduction']['total_due'] = $subtalamount + (round($deductionAmount, 2));
        // $response['adjustment_deduction']['total_due'] = 0;
        $response['adjustment_deduction']['finalize_count'] = $finalizeId;

        return response()->json([
            self::API_NAME => 'report-top-header-data',
            'status' => true,
            'data' => $response,
        ]);
    }

    public function getCommissionReconReport_old(Request $request)
    {
        $apiName = 'Employee Commission Report';

        $validate = $this->userReportValidation($request);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }
        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeCount = $request->finalize_count;

        $pidsData = DB::table('sale_masters as s_m_t')
            ->join('sale_master_process as s_m_p_t', function ($join) use ($userId) {
                $join->on('s_m_p_t.pid', '=', 's_m_t.pid')
                    ->where(function ($query) use ($userId) {
                        $query->where('s_m_p_t.closer1_id', $userId)
                            ->orWhere('s_m_p_t.closer2_id', $userId)
                            ->orWhere('s_m_p_t.setter1_id', $userId)
                            ->orWhere('s_m_p_t.setter2_id', $userId);
                    });
            })
            ->whereBetween('s_m_t.customer_signoff', [$startDate, $endDate])
            ->orWhereBetween('s_m_t.m2_date', [$startDate, $endDate])
            ->pluck('s_m_t.pid')->toArray();

        $reconFinalize = ReconciliationFinalizeHistory::select('pid')->where([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'payroll',
            'finalize_count' => $finalizeCount,
        ])->first();

        if ($reconFinalize) {
            $pids = explode(',', $reconFinalize->pid);
        } else {
            $pids = [];
        }

        $pidArrs = array_unique(array_merge($pidsData, $pids));
        // return $pidArrs;
        $salesData = DB::table('sale_masters as s_m_t')
            ->join('sale_master_process as s_m_p_t', function ($join) use ($userId) {
                $join->on('s_m_p_t.pid', '=', 's_m_t.pid')
                    ->where(function ($query) use ($userId) {
                        $query->where('s_m_p_t.closer1_id', $userId)
                            ->orWhere('s_m_p_t.closer2_id', $userId)
                            ->orWhere('s_m_p_t.setter1_id', $userId)
                            ->orWhere('s_m_p_t.setter2_id', $userId);
                    });
            })
            ->select(
                DB::raw('
                    CASE
                        WHEN s_m_p_t.closer1_id = '.$userId.' THEN s_m_p_t.closer1_commission
                        WHEN s_m_p_t.closer2_id = '.$userId.' THEN s_m_p_t.closer2_commission
                        WHEN s_m_p_t.setter1_id = '.$userId.' THEN s_m_p_t.setter1_commission
                        WHEN s_m_p_t.setter2_id = '.$userId.' THEN s_m_p_t.setter2_commission
                        ELSE 0
                    END AS user_commission
                '),
                's_m_t.pid as s_m_t_pid',
                's_m_p_t.closer1_id as closer1_id',
                's_m_p_t.closer2_id as closer2_id',
                's_m_p_t.setter1_id as setter1_id',
                's_m_p_t.setter2_id as setter2_id',
                's_m_t.m2_date as s_m_t_m2_date',
                's_m_t.m1_date as s_m_t_m1_date',
                's_m_t.customer_signoff as s_m_t_customer_signoff',
                's_m_t.customer_name as s_m_t_customer_name',
                's_m_t.customer_state as s_m_t_customer_state',
                's_m_t.kw as s_m_t_kw',
                's_m_t.net_epc as s_m_t_net_epc',
            )
            // ->whereBetween('s_m_t.customer_signoff', [$startDate, $endDate])
            // ->orWhereBetween('s_m_t.m2_date', [$startDate, $endDate])
            ->whereIn('s_m_t.pid', $pidArrs)
            ->get();

        $data = $salesData->transform(function ($result) use ($userId, $startDate, $endDate, $finalizeCount) {
            $newStartDate = Carbon::parse($startDate);
            $newEndDate = Carbon::parse($endDate);
            $m2Date = Carbon::parse($result->s_m_t_m2_date);

            $customerSignOffDate = Carbon::parse($result->s_m_t_customer_signoff);

            // $eligibleStatus = true;
            // if (!$m2Date->between($newStartDate, $newEndDate) || !$customerSignOffDate->between($newStartDate, $newEndDate)) {
            //     $eligibleStatus = false;
            // }

            $eligibleStatus = 0;
            if ($m2Date->between($newStartDate, $newEndDate)) {
                $eligibleStatus += 1;
            }
            if ($customerSignOffDate->between($newStartDate, $newEndDate)) {
                $eligibleStatus += 1;
            }

            $reconPaidCommissions = ReconCommissionHistory::where([
                'pid' => $result->s_m_t_pid,
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'payroll',
                'finalize_count' => $finalizeCount,
            ])->get();

            $checkPid = $reconPaidCommissions->pluck('pid')->toArray();

            $reconFinalizeCommissions = ReconciliationFinalizeHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'payroll',
                'finalize_count' => $finalizeCount,
            ])->get();

            $inReconAmount = $reconPaidCommissions->sum('total_amount') - $reconPaidCommissions->sum('paid_amount');
            $reconPercentage = $reconFinalizeCommissions->sum('payout');
            $totalCommission = $reconPaidCommissions->sum('total_amount');
            $PaidCommission = $reconPaidCommissions->sum('paid_amount');

            if (in_array($result->s_m_t_pid, $checkPid)) {
                $eligibleStatus = 2;
            } else {
                $eligibleStatus = 0;
            }

            return [
                'pid' => $result->s_m_t_pid,
                'customer' => $result->s_m_t_customer_name,
                'state' => strtoupper($result->s_m_t_customer_state),
                'rep_redline' => User::find($userId)->redline,
                'kw' => $result->s_m_t_kw,
                'net_epc' => $result->s_m_t_net_epc,
                'total_commission' => $totalCommission,
                'previous_paid' => $PaidCommission,
                'inReconPercentage' => ($eligibleStatus == 2) ? $reconPercentage : 0,
                'payout_percentage' => $reconPercentage,
                'payout_this_recon' => $PaidCommission,
                'eligible_status' => ($eligibleStatus == 2) ? 1 : 0,
                'payout_recon' => $reconPaidCommissions,
                'remaining_recon' => ($totalCommission - $PaidCommission),
                'm2_date' => $result->s_m_t_m2_date,
                'customer_signoff' => $result->s_m_t_customer_signoff,
            ];

        });

        $total = array_reduce($data->toArray(), function ($carry, $item) {
            $carry['paid_total'] += $item['previous_paid'];
            $carry['remaining_in_recon'] += $item['remaining_recon'];
            $carry['payout'] = $item['payout_percentage'];
            $carry['payout_amount'] += $item['payout_this_recon'];
            $carry['total_amount'] += $item['total_commission'];

            return $carry;
        }, ['paid_total' => 0, 'remaining_in_recon' => 0, 'payout' => 0, 'payout_amount' => 0, 'total_amount' => 0]);

        // $total['total_amount'] = array_sum($total);

        return [
            'ApiName' => $apiName,
            'status' => true,
            'data' => $data,
            'total' => $total ?? 0,
        ];
    }

    public function getCommissionReconReport_14_02_2025(Request $request)
    {
        $apiName = 'Employee Commission Report';

        $validate = $this->userReportValidation($request);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->finalize_count;

        $reconPaidCommissions = ReconCommissionHistory::with('salesDetail')->where([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'finalize_id' => $finalizeId,
        ])->get();

        $data = $reconPaidCommissions->transform(function ($result) use ($userId, $startDate, $endDate, $finalizeId) {
            $userCommissionPercentage = 0;
            $userCommissiontype = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $userId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', @$result->salesDetail->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $userCommissionPercentage = $commissionHistory->commission;
                $userCommissiontype = $commissionHistory->commission_type;
            }

            $reconFinalizeCommissions = ReconciliationFinalizeHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'finalize_id' => $finalizeId,
            ])->get();

            // $finalizeCountPrevious = ($finalizeId > 0) ? ($finalizeId - 1) : 0;
            $paidPrevious = ReconCommissionHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'pid' => $result->pid,
                'type' => $result->type,
            ])
                ->where('finalize_id', '<', $finalizeId)
            // ->where("finalize_count", $finalizeCountPrevious)
            // ->where("is_ineligible", 0)
                ->sum('paid_amount');

            // if($result->paid_amount < 0){
            //     $paidPrevious = -($result->paid_amount);
            // }else{
            //     if($result->paid_amount > 0 && $result->finalize_id == 1){
            //             $paidPrevious = 0;
            //         }else{
            //             $paidPrevious = $result->paid_amount;
            //         }
            // }

            $totalUserCommission = UserCommission::where('pid', $result->pid)->where('user_id', $userId)->sum('amount');
            $withHeldCommission = UserReconciliationWithholding::where(function ($query) use ($userId) {
                $query->where('closer_id', $userId)
                    ->orWhere('setter_id', $userId);
            })->where('pid', $result->pid)
                ->sum('withhold_amount');
            $commission = $totalUserCommission + $withHeldCommission;
            $totalsum = 0;
            if ($result->type == 'reconciliation') {
                $totalfirst = ReconCommissionHistory::where('pid', $result->pid)->where('user_id', $userId)->where('type', 'reconciliation')->first();
                // $totalsum = $totalfirst->total_amount;
                // if($result->is_ineligible == 0 && $result->is_deducted == 0){
                //     //dd("rwerew");
                //    $totalfirstnew = ReconCommissionHistory::where("pid", $result->pid)->where("user_id", $userId)->where('type','m1')->first();
                //    $totalsum = $totalsum + $totalfirstnew->total_amount;
                // }
            } else {
                $totalfirst = ReconCommissionHistory::where('pid', $result->pid)->where('user_id', $userId)->where('type', 'm1')->first();
            }

            $reconPercentage = $reconFinalizeCommissions->sum('payout');
            $totalCommission = $result->total_amount;
            $PaidCommission = $result->paid_amount;
            $eligibleStatus = 2;
            $state_name = State::find($result->salesDetail->state_id);
            if ($PaidCommission < 0) {
                $PaidCommissionnew = $totalCommission + $PaidCommission;
            } else {
                $PaidCommissionnew = $totalCommission - $PaidCommission;
            }

            return [
                'pid' => $result->pid,
                'customer' => $result->salesDetail->customer_name,
                'state' => strtoupper($state_name->state_code),
                'rep_redline' => User::find($userId)->redline,
                'kw' => $result->salesDetail->kw,
                'net_epc' => $result->salesDetail->net_epc,
                // 'total_commission' => $totalfirst->total_amount,
                'total_commission' => ($result->total_amount + $paidPrevious),
                'previous_paid' => $paidPrevious,
                'inReconPercentage' => ($eligibleStatus == 2) ? $reconPercentage : 0,
                'payout_percentage' => $reconPercentage,
                'payout_this_recon' => $PaidCommission,
                // 'eligible_status' => ($eligibleStatus == 2) ? 1 : 0,
                'is_ineligible' => isset($result->is_ineligible) ? $result->is_ineligible : 0, // 0 = Eligible, 1 = Ineligible
                'payout_recon' => $result,
                'remaining_recon' => ($PaidCommissionnew),
                'm2_date' => $result->salesDetail->m2_date,
                'customer_signoff' => $result->salesDetail->customer_signoff,
                'paid_status' => ($result->payroll_execute_status == 3) ? 1 : 0,
                'gross_account_value' => isset($result->salesDetail->gross_account_value) ? $result->salesDetail->gross_account_value : 0,
                'user_commission' => $userCommissionPercentage,
                'user_commission_type' => $userCommissiontype,
                'type' => ($result->type == 'M1' || $result->type == 'm1') ? 'Upfront' : $result->type,
            ];

        });

        $total = array_reduce($data->toArray(), function ($carry, $item) {
            $carry['paid_total'] += $item['previous_paid'];
            $carry['remaining_recon'] += $item['remaining_recon'];
            $carry['payout'] = $item['payout_percentage'];
            $carry['payout_amount'] += $item['payout_this_recon'];
            $carry['total_amount'] += $item['total_commission'];
            $carry['total_paid_previously'] += $item['previous_paid'];

            return $carry;
        }, ['paid_total' => 0, 'remaining_recon' => 0, 'payout' => 0, 'payout_amount' => 0, 'total_amount' => 0, 'total_paid_previously' => 0]);

        // $total['total_amount'] = array_sum($total);

        return [
            'ApiName' => $apiName,
            'status' => true,
            'data' => $data,
            'total' => $total ?? 0,
        ];
    }

    public function getCommissionReconReport(Request $request)
    {
        $apiName = 'Employee Commission Report';
        $isUpfront = $this->isUpfront;
        $validate = $this->userReportValidation($request);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->finalize_count;

        $reconPaidCommissions = ReconCommissionHistory::with('salesDetail')->where([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'finalize_id' => $finalizeId,
        ])->groupBy('pid')->get();

        $data = $reconPaidCommissions->transform(function ($result) use ($userId, $startDate, $endDate, $finalizeId, $isUpfront) {
            $userCommissionPercentage = 0;
            $userCommissiontype = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $userId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', @$result->salesDetail->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $userCommissionPercentage = $commissionHistory->commission;
                $userCommissiontype = $commissionHistory->commission_type;
            }

            $reconFinalizeCommissions = ReconciliationFinalizeHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'finalize_id' => $finalizeId,
            ])->get();

            $totalCommission = ReconCommissionHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'finalize_id' => $finalizeId,
                'pid' => $result->pid,
            ])->sum('total_amount');

            $PaidCommission = ReconCommissionHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'finalize_id' => $finalizeId,
                'pid' => $result->pid,
            ])->sum('paid_amount');

            // $finalizeCountPrevious = ($finalizeId > 0) ? ($finalizeId - 1) : 0;
            $paidPrevious = ReconCommissionHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'pid' => $result->pid,
            ])
                ->where('finalize_id', '<', $finalizeId)
            // ->where("finalize_count", $finalizeCountPrevious)
            // ->where("is_ineligible", 0)
                ->sum('paid_amount');

            // if($result->paid_amount < 0){
            //     $paidPrevious = -($result->paid_amount);
            // }else{
            //     if($result->paid_amount > 0 && $result->finalize_id == 1){
            //             $paidPrevious = 0;
            //         }else{
            //             $paidPrevious = $result->paid_amount;
            //         }
            // }

            $paidPreviousAmount = UserCommission::where([
                'user_id' => $userId,
                'pid' => $result->pid,
            ])
                ->where('settlement_type', '!=', 'reconciliation')
                ->sum('amount');

            $totalUserCommission = UserCommission::where('pid', $result->pid)->where('user_id', $userId)->sum('amount');
            $withHeldCommission = UserReconciliationWithholding::where(function ($query) use ($userId) {
                $query->where('closer_id', $userId)
                    ->orWhere('setter_id', $userId);
            })->where('pid', $result->pid)
                ->sum('withhold_amount');
            $commission = $totalUserCommission + $withHeldCommission;
            $totalsum = 0;
            if ($result->type == 'reconciliation') {
                $totalfirst = ReconCommissionHistory::where('pid', $result->pid)->where('user_id', $userId)->where('type', 'reconciliation')->first();
                // $totalsum = $totalfirst->total_amount;
                // if($result->is_ineligible == 0 && $result->is_deducted == 0){
                //     //dd("rwerew");
                //    $totalfirstnew = ReconCommissionHistory::where("pid", $result->pid)->where("user_id", $userId)->where('type','m1')->first();
                //    $totalsum = $totalsum + $totalfirstnew->total_amount;
                // }
            } else {
                $totalfirst = ReconCommissionHistory::where('pid', $result->pid)->where('user_id', $userId)->where('type', 'm1')->first();
            }

            $reconPercentage = $reconFinalizeCommissions->sum('payout');

            $eligibleStatus = 2;
            $state_name = State::find($result->salesDetail->state_id);
            if ($PaidCommission < 0) {
                $PaidCommissionnew = $totalCommission + $PaidCommission;
            } else {
                $PaidCommissionnew = $totalCommission - $PaidCommission;
            }

            return [
                'pid' => $result->pid,
                'customer' => $result->salesDetail->customer_name,
                'state' => strtoupper($state_name->state_code),
                'rep_redline' => User::find($userId)->redline,
                'kw' => $result->salesDetail->kw,
                'net_epc' => $result->salesDetail->net_epc,
                // 'total_commission' => $totalfirst->total_amount,
                'total_commission' => ($totalCommission + $paidPrevious),
                'previous_paid' => ($paidPreviousAmount + $paidPrevious),
                'inReconPercentage' => ($eligibleStatus == 2) ? $reconPercentage : 0,
                'payout_percentage' => $reconPercentage,
                'payout_this_recon' => $PaidCommission,
                // 'eligible_status' => ($eligibleStatus == 2) ? 1 : 0,
                'is_ineligible' => isset($result->is_ineligible) ? $result->is_ineligible : 0, // 0 = Eligible, 1 = Ineligible
                'payout_recon' => $result,
                'remaining_recon' => ($PaidCommissionnew),
                'm2_date' => $result->salesDetail->m2_date,
                'customer_signoff' => $result->salesDetail->customer_signoff,
                'paid_status' => ($result->payroll_execute_status == 3) ? 1 : 0,
                'gross_account_value' => isset($result->salesDetail->gross_account_value) ? $result->salesDetail->gross_account_value : 0,
                'user_commission' => $userCommissionPercentage,
                'user_commission_type' => $userCommissiontype,
                // 'type'=>($result->type == "M1" ||  $result->type == "m1") ? 'Upfront' : $result->type,
                'type' => 'Commission',
                'is_upfront' => isset($reconFinalizeCommissions[0]['is_upfront']) ? $reconFinalizeCommissions[0]['is_upfront'] : $isUpfront,
            ];

        });

        $total = array_reduce($data->toArray(), function ($carry, $item) {
            $carry['paid_total'] += $item['previous_paid'];
            $carry['remaining_recon'] += $item['remaining_recon'];
            $carry['payout'] = $item['payout_percentage'];
            $carry['payout_amount'] += $item['payout_this_recon'];
            $carry['total_amount'] += $item['total_commission'];
            $carry['total_paid_previously'] += $item['previous_paid'];

            return $carry;
        }, ['paid_total' => 0, 'remaining_recon' => 0, 'payout' => 0, 'payout_amount' => 0, 'total_amount' => 0, 'total_paid_previously' => 0]);

        // $total['total_amount'] = array_sum($total);

        return [
            'ApiName' => $apiName,
            'status' => true,
            'data' => $data,
            'total' => $total ?? 0,
        ];
    }

    public function getClawbackReportList(Request $request)
    {
        $validate = $this->userReportValidation($request);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->finalize_count;

        $reconFinalize = ReconciliationFinalizeHistory::where([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'finalize_id' => $finalizeId,
        ])
            ->whereIn('status', ['payroll', 'clawback'])
            ->first();

        if ($reconFinalize) {
            $pids = explode(',', $reconFinalize->pid);
            $paid_status = ($reconFinalize->payroll_execute_status == 3) ? 1 : 0;
        } else {
            $pids = [];
            $paid_status = 0;
        }

        $salesData = DB::table('recon_clawback_histories as c_s')
            ->join('sale_masters as s_m_t', 'c_s.pid', 's_m_t.pid')
            ->join('users as u', 'u.id', '=', 'c_s.user_id')
            ->select(
                'c_s.*',
                's_m_t.customer_name',
                's_m_t.date_cancelled',
                'u.first_name',
                'u.last_name',
                's_m_t.customer_state',
                's_m_t.gross_account_value',
                's_m_t.kw',
                's_m_t.net_epc',
                's_m_t.state_id'
            )
            ->where('c_s.user_id', $userId)
            ->where('c_s.start_date', $startDate)
            ->where('c_s.end_date', $endDate)
            ->where('c_s.finalize_id', $finalizeId)
            // ->whereIn('c_s.pid', $pids)
            ->get();

        // Step 2: Process and transform data
        $data = $salesData->map(function ($item) use ($userId, $startDate, $endDate, $finalizeId) {
            // Fetch redline data
            $rep_redline = $this->calculateRepRedline($item->user_id, $item->customer_state);

            $finalizeCountPrevious = ($finalizeId > 0) ? ($finalizeId - 1) : 0;
            $paidPrevious = ReconClawbackHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'pid' => $item->pid,
            ])
                ->where('finalize_id', '<', $finalizeId)
            // ->where("finalize_count", $finalizeCountPrevious)
                ->sum('paid_amount');

            // Fetch state ID
            $state_code = Locations::with('State')
                ->where('general_code', '=', $item->customer_state)
                ->value('state_id');

            if (in_array($item->type, ['commission', 'recon-commission'])) {
                $paymentType = 'Commission';
            } else {
                $paymentType = 'Override';
            }
            $userCommissionPercentage = 0;
            $userCommissiontype = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $item->user_id)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $userCommissionPercentage = $commissionHistory->commission;
                $userCommissiontype = $commissionHistory->commission_type;
            }
            $moveToReconStatus = $item->move_from_payroll == 1 ? ' | Move From Payroll' : '';
            $customerName = $item->customer_name.$moveToReconStatus;
            $state_name = State::find($item->state_id);

            return [
                'id' => $item->id,
                'pid' => $item->pid,
                'user_id' => $item->user_id,
                'customer_name' => $customerName,
                'state_id' => $state_code,
                'state' => $state_name->state_code,
                'payment_type' => $paymentType,
                'date' => $item->date_cancelled,
                'date_paid' => $item->updated_at,
                'clawback_type' => $item->type,
                'type' => $item->adders_type == 'recon-commission' ? 'Reconciliation' : (in_array(strtolower($item->adders_type), ['m1']) ? 'Upfront' : ucfirst($item->adders_type)),
                'total_clawback' => -1 * floatval($item->paid_amount),
                'previous_paid' => 0,
                'due_amount' => -1 * floatval($item->paid_amount),
                'paid_status' => ($item->payroll_execute_status == 3) ? 1 : 0,
                'gross_account_value' => isset($item->gross_account_value) ? $item->gross_account_value : 0,
                'user_commission' => $userCommissionPercentage,
                'user_commission_type' => $userCommissiontype,
            ];

        });

        $total = array_reduce($data->toArray(), function ($carry, $item) {
            $carry['paid_total'] += $item['previous_paid'];
            $carry['total_amount'] += $item['total_clawback'];
            $carry['remaining_in_recon'] += $item['due_amount'];
            $carry['total_paid_previously'] += $item['previous_paid'];

            return $carry;
        }, ['paid_total' => 0, 'total_amount' => 0, 'remaining_in_recon' => 0, 'total_paid_previously' => 0]);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Clawback By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'total' => $total,
            'paid_status' => $paid_status,

        ], 200);

    }

    protected function calculateRepRedline($userId, $customerState)
    {
        // Fetch user data including redline information
        $user = User::with('office')->find($userId);

        // Default rep_redline to a neutral or zero value
        $rep_redline = 0;

        if ($user) {
            // Determine user's redline amount type and calculate accordingly
            if ($user->redline_amount_type == 'Fixed') {
                $rep_redline = $user->redline;
            } else {
                // Fetch standard redline for the state from SalesMaster or a related model
                $sale_state_redline = SalesMaster::join('states', 'states.state_code', '=', 'sale_masters.customer_state')
                    ->where('sale_masters.customer_state', '=', $customerState)
                    ->value('redline');

                // Fetch user's office redline
                $user_office_redline = $user->location ? $user->location->redline_standard : 0;

                // Adjust the rep_redline
                $rep_redline = $sale_state_redline + ($user->redline - $user_office_redline);
            }
        }

        return $rep_redline;
    }

    public function getOverridesCardDetails(Request $request)
    {
        $validate = $this->userReportValidation($request);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }
        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->finalize_count;

        $userOverRideData = ReconOverrideHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('user_id', $userId)
            ->where('finalize_id', $finalizeId)
            ->get();

        $overrideList = $userOverRideData->transform(function ($result) use ($startDate, $endDate, $userId, $finalizeId) {

            $overrideData = UserOverrides::where('user_id', $userId)->where('type', $result->type)->where('pid', $result->pid)->first();
            $totalAmount = UserOverrides::where('user_id', $userId)->where('type', $result->type)->where('pid', $result->pid)->sum('amount');
            $payout = ReconciliationFinalizeHistory::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)->where('user_id', $userId)->where('finalize_id', $finalizeId)->whereIn('status', ['payroll', 'clawback'])->first();

            $finalizeCountPrevious = ($finalizeId > 0) ? ($finalizeId - 1) : 0;
            $paidPrevious = ReconOverrideHistory::where([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'pid' => $result->pid,
            ])
                ->where('finalize_id', '<', $finalizeId)
            // ->where("finalize_count", $finalizeCountPrevious)
                ->where('is_ineligible', 0)
                ->sum('paid');

            return [
                'pid' => $result->pid,
                'customer' => isset($result->salesDetail->customer_name) ? $result->salesDetail->customer_name : null,
                'image' => isset($result->userpayrolloverride->first_name) ? $result->userpayrolloverride->first_name : null,
                'override_over_image' => isset($result->overrideOverData->image) ? $result->overrideOverData->image : null,
                'override_over_first_name' => isset($result->overrideOverData->first_name) ? $result->overrideOverData->first_name : null,
                'override_over_last_name' => isset($result->overrideOverData->last_name) ? $result->overrideOverData->last_name : null,
                'type' => $result->type,
                'kw_installed' => $result->kw,
                'override' => $overrideData->overrides_amount.' '.$overrideData->overrides_type,
                'total_override' => $result->total_amount,
                'payout' => $payout->payout,
                'paid_total' => $result->total_amount,
                'payout_this_recon' => $result->paid,
                'paid_previously' => $paidPrevious,
                'remaining_in_recon' => floatval($result->total_amount - $result->paid),
                'is_ineligible' => isset($result->is_ineligible) ? $result->is_ineligible : 0, // 0 = Eligible, 1 = Ineligible
                'paid_status' => ($result->payroll_execute_status == 3) ? 1 : 0,
                'gross_account_value' => isset($result->salesDetail->gross_account_value) ? $result->salesDetail->gross_account_value : 0,

            ];
        });

        $total = array_reduce($overrideList->toArray(), function ($carry, $item) {
            $carry['total_earned'] += $item['paid_total'];
            $carry['total_paid'] += $item['payout_this_recon'];
            $carry['total_due'] += $item['remaining_in_recon'];
            $carry['payout'] = $item['payout'];
            $carry['payout_amount'] += $item['payout_this_recon'];
            $carry['total_paid_previously'] += $item['paid_previously'];
            $carry['is_ineligible'] = $item['is_ineligible'];

            return $carry;
        }, ['total_earned' => 0, 'total_paid' => 0, 'total_due' => 0, 'payout' => 0, 'payout_amount' => 0, 'total_paid_previously' => 0]);

        return response()->json([
            self::API_NAME => 'report-top-header-data',
            'status' => true,
            'data' => $total,
            'override_list' => $overrideList,
        ]);
    }

    public function standardReportDeductionList_old(Request $request): JsonResponse
    {
        // $validate = $this->userReportValidation($request);
        $validate = Validator::make($request->all(), [
            'user_id' => ['required', function ($attribute, $value, $fail) {
                if ($value != 'all' && ! User::where('id', $value)->exists()) {
                    $fail('This user is not exists in our system.');
                }
            }],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }
        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $finalizeDeductAmount = ReconciliationFinalizeHistory::where('user_id', $userId)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate);
        $adjustmantDetails = ReconciliationAdjustmentDetails::whereIn('pid', $finalizeDeductAmount->pluck('pid')->toArray())->where('user_id', $userId)->get();
        $deduction = PayrollDeductions::with('costcenter')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->where('user_id', $userId)->orderBy('id', 'asc')->get();

        if (count($deduction) > 0) {
            $amount = $deduction->sum('amount');
            $deductionAmount = $deduction->sum('total');
            $outstanding = $deduction->sum('outstanding');

            $total = [
                'amount' => $amount,
                'deduction' => $deductionAmount,
                'outstanding' => $outstanding,
            ];

            $data = [];
            foreach ($deduction as $val) {

                $costCenterName = $val->costcenter->name;
                if (! isset($data[$costCenterName])) {
                    $data[$costCenterName] = ['data' => []];
                    $data[$costCenterName]['total']['amount'] = 0;
                    $data[$costCenterName]['total']['deduction'] = 0;
                    $data[$costCenterName]['total']['outstanding'] = 0;
                }

                $data[$costCenterName]['total']['amount'] += $val->amount;
                $data[$costCenterName]['total']['deduction'] += $val->total;
                $data[$costCenterName]['total']['outstanding'] += $val->outstanding;

                $data[$val->costcenter->name]['data'][] = [

                    'cost_head_name' => $val->costcenter->name,
                    'cost_head_code' => $val->costcenter->code,
                    'date' => isset($val->created_at) ? date('m/d/Y', strtotime($val->created_at)) : null,
                    'amount' => $val->amount,
                    'deduction' => $val->total,
                    'outstanding' => $val->outstanding,
                ];
            }

            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => $total,
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => [],
                'data' => [],
            ], 200);
        }

        $user = Auth()->user();
        $userId = Auth()->user()->id;

        $page = $request->perpage;
        if (isset($page) && $page != null) {
            $pages = $page;
        } else {
            $pages = 10;
        }

        $userId = $request->user_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $deduction = PayrollDeductionLock::with('costcenter')->whereBetween(DB::raw('DATE(created_at)'), [$start_date, $end_date])->where('user_id', $userId)->orderBy('id', 'asc');
        // if(Auth()->user()->is_super_admin != '1' && Auth()->user()->id != 1){
        //     $deduction->where('user_id', $userId);
        // }
        $deduction = $deduction->get();

        if (count($deduction) > 0) {

            $amount = $deduction->sum('amount');
            $deductionAmount = $deduction->sum('total');
            $outstanding = $deduction->sum('outstanding');

            $total = [
                'amount' => $amount,
                'deduction' => $deductionAmount,
                'outstanding' => $outstanding,
            ];

            $data = [];
            foreach ($deduction as $key => $val) {

                $costCenterName = $val->costcenter->name;

                if (! isset($data[$costCenterName])) {
                    $data[$costCenterName] = ['data' => []];
                    $data[$costCenterName]['total']['amount'] = 0;
                    $data[$costCenterName]['total']['deduction'] = 0;
                    $data[$costCenterName]['total']['outstanding'] = 0;
                }

                $data[$costCenterName]['total']['amount'] += $val->amount;
                $data[$costCenterName]['total']['deduction'] += $val->total;
                $data[$costCenterName]['total']['outstanding'] += $val->outstanding;

                $data[$val->costcenter->name]['data'][] = [

                    'cost_head_name' => $val->costcenter->name,
                    'cost_head_code' => $val->costcenter->code,
                    'date' => isset($val->created_at) ? date('m/d/Y', strtotime($val->created_at)) : null,
                    'amount' => $val->amount,
                    'deduction' => $val->total,
                    'outstanding' => $val->outstanding,
                ];
            }

            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => $total,
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => [],
                'data' => [],
            ], 200);
        }

    }

    public function standardReportDeductionList(Request $request): JsonResponse
    {
        // $validate = $this->userReportValidation($request);
        $validate = Validator::make($request->all(), [
            'user_id' => ['required', function ($attribute, $value, $fail) {
                if ($value != 'all' && ! User::where('id', $value)->exists()) {
                    $fail('This user is not exists in our system.');
                }
            }],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $finalizeId = $request->finalize_count;

        $reconFinalize = ReconciliationFinalizeHistory::where('user_id', $userId)
            ->where('finalize_id', $finalizeId)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->whereIn('status', ['payroll', 'clawback'])
            ->first();

        if ($reconFinalize && $reconFinalize->payroll_execute_status == 3) {
            $paid_status = 1;
        } else {
            $paid_status = 0;
        }

        $deduction = ReconDeductionHistory::with('costcenter')
            ->where('user_id', $userId)
            ->where('finalize_id', $finalizeId)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->orderBy('id', 'asc')
            ->get();

        if (count($deduction) > 0) {
            $amount = $deduction->sum('total');
            $deductionAmount = $deduction->sum('total');
            // $outstanding = $deduction->sum('outstanding');
            $outstanding = 0;

            $total = [
                'amount' => (-1 * $amount),
                'deduction' => (-1 * $deductionAmount),
                'outstanding' => $outstanding,
            ];

            $data = [];
            foreach ($deduction as $val) {

                $costCenterName = $val->costcenter->name;
                if (! isset($data[$costCenterName])) {
                    $data[$costCenterName] = ['data' => []];
                    $data[$costCenterName]['total']['amount'] = 0;
                    $data[$costCenterName]['total']['deduction'] = 0;
                    $data[$costCenterName]['total']['outstanding'] = 0;
                }

                $data[$costCenterName]['total']['amount'] += (-1 * $val->total);
                $data[$costCenterName]['total']['deduction'] += (-1 * $val->total);
                // $data[$costCenterName]['total']['outstanding'] += $val->outstanding;

                $data[$val->costcenter->name]['data'][] = [

                    'cost_head_name' => $val->costcenter->name,
                    'cost_head_code' => $val->costcenter->code,
                    'date' => isset($val->created_at) ? date('m/d/Y', strtotime($val->created_at)) : null,
                    'amount' => (-1 * $val->total),
                    'deduction' => (-1 * $val->total),
                    // 'outstanding' => $val->outstanding,
                    'outstanding' => 0,
                ];
            }

            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => $total,
                'data' => $data,
                'paid_status' => $paid_status,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => [],
                'data' => [],
            ], 200);
        }

    }

    public function adjustmentReconReport(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
                'finalize_count' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $reconFinalize = ReconciliationFinalizeHistory::where([
            'user_id' => $request->user_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'finalize_id' => $request->finalize_count,
        ])
            ->whereIn('status', ['payroll', 'clawback'])
            ->first();

        if ($reconFinalize && $reconFinalize->payroll_execute_status == 3) {
            $paid_status = 1;
        } else {
            $paid_status = 0;
        }

        $reconAdjustmentData = ReconAdjustment::where([
            'user_id' => $request->user_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'finalize_id' => $request->finalize_count,
            // 'payroll_status' => 'payroll',
        ])
            ->get();

        $finalData = [
            'commission' => [],
            'overrides' => [],
            'clawback' => [],
            'commission_total' => 0.00,
            'override_total' => 0.00,
            'clawback_total' => 0.00,
            'deduction_total' => 0.00,
            'a_amount_total' => 0.00,
            'a_amount_due' => 0.00,
            'paid_status' => $paid_status,
        ];
        $reconAdjustmentData->transform(function ($result) use (&$finalData) {

            $s3_image = $result?->commentUser?->image ? s3_getTempUrl(config('app.domain_name').'/'.$result->user->image) : null;
            $totalAmount = 0;
            if ($result->adjustment_type === 'commission') {
                $totalAmount = floatval($result->adjustment_amount);
            } elseif ($result->adjustment_type === 'override') {
                $totalAmount = floatval($result->adjustment_amount);
            } elseif ($result->adjustment_type === 'clawback') {
                $totalAmount = floatval($result->adjustment_amount);
            }
            if ($result->payroll_execute_status == 3) {
                $adjust_amount_total = floatval($result->adjustment_amount);
            } else {
                $adjust_amount_total = 0;
            }
            if ($result->payroll_execute_status == 0) {
                $adjust_amount_due = floatval($result->adjustment_amount);
            } else {
                $adjust_amount_due = 0;
            }
            if ($result->payroll_execute_status == 3) {
                $paid_status_new = 1;
            } else {
                $paid_status_new = 0;
            }

            $data = [
                'pid' => $result->pid,
                'date' => $result->created_at->format('Y-m-d'),
                'comment' => $result->adjustment_comment,
                'type' => $result->adjustment_type,
                'amount' => $totalAmount,
                'paid_status' => $paid_status_new,
                'amount_adjusted' => $adjust_amount_total,
                'amount_due' => $adjust_amount_due,
                'adjust_by' => $result?->commentUser?->first_name.' '.$result?->commentUser?->last_name,
                'is_manager' => $result?->commentUser?->is_manager,
                'is_super_admin' => $result?->commentUser?->is_super_admin,
                'position_id' => $result?->commentUser?->position_id,
                'sub_position_id' => $result?->commentUser?->sub_position_id,
                'image_url' => $s3_image,
            ];

            if ($result->adjustment_type === 'commission') {
                $finalData['commission_total'] += floatval($result->adjustment_amount);
                $finalData['commission'][] = $data;
            } elseif ($result->adjustment_type === 'override') {
                $finalData['override_total'] += floatval($result->adjustment_amount);
                $finalData['overrides'][] = $data;
            } elseif ($result->adjustment_type === 'clawback') {
                $finalData['clawback_total'] += floatval($result->adjustment_amount);
                $finalData['clawback'][] = $data;
            }

            if ($result->payroll_execute_status == 3) {
                $finalData['a_amount_total'] += floatval($result->adjustment_amount);
            }

            if ($result->payroll_execute_status == 0) {
                $finalData['a_amount_due'] += floatval($result->adjustment_amount);
            }

            return $result;
        });
        $finalData['subTotalAdjustment'] = $finalData['commission_total'] + $finalData['override_total'] + $finalData['clawback_total'];
        $finalData['adjustment_amount_total'] = $finalData['a_amount_total'];
        $finalData['adjustment_amount_due'] = $finalData['a_amount_due'];

        /* deduction data */

        /* $deductionData = PayrollDeductions::where("user_id", $request->user_id)
            ->where("is_move_to_recon", 1)
            ->where("status", 6)
            ->where("is_move_to_recon_paid", 0)
            ->get(); */

        $deductionData = ReconDeductionHistory::where('user_id', $request->user_id)
            ->where('finalize_id', $request->finalize_count)
            ->whereDate('start_date', $request->start_date)
            ->whereDate('end_date', $request->end_date)
            ->orderBy('id', 'asc')
            ->get();

        // return $deductionData;
        $responseArray = $deductionData->groupBy(function ($item) {
            return $item->costcenter->name;
        })->map(function ($items, $deductionName) use (&$deductionTotal) {
            return $items->map(function ($item) use (&$deductionTotal) {
                $deductionTotal += $item->total;

                return [
                    'deduction_name' => $item->costcenter->name,
                    'date' => $item->created_at->format('Y-m-d'),
                    'comment' => $item->comment,
                    'adjust_by' => $item->adjust_by,
                    'amount' => '-'.$item->total,
                    'deduct' => '-'.$item->total,
                    'outstanding' => '-'.$item->outstanding,
                ];
            })->all();
        })->all();
        $finalData['deduction_total'] = -1 * $deductionTotal;
        $finalData['deduction'] = $responseArray;

        return response()->json($finalData);
    }
}
