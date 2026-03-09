<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\Locations;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollDeductions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationsAdjustement;
use App\Models\ReconClawbackHistory;
use App\Models\ReconClawbackHistoryLock;
use App\Models\ReconCommissionHistory;
use App\Models\ReconCommissionHistoryLock;
use App\Models\ReconDeductionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\ReconOverrideHistoryLock;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationWithholding;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReconStandardReportController extends Controller
{
    protected $isSuperAdmin;

    protected const API_NAME = 'api name';

    public function __construct()
    {
        $this->middleware('auth:api');
        // Middleware closure to defer the execution
        $this->middleware(function ($request, $next) {
            $this->isSuperAdmin = Auth::user()->is_super_admin == 1 ? true : false;

            return $next($request);
        });
    }

    public function standardReportPastReconciliation(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'year' => [
                    'required',
                    'digits:4',
                    'integer',
                    'between:2000,'.Carbon::now()->year,
                ],
                'user_id' => ['required', function ($attribute, $value, $fail) {
                    if ($value != 'all') {
                        if (! User::where('id', $value)->exists()) {
                            $fail('This user is not exists in our system.');
                        }
                    }
                }],
            ]);
            if ($validation->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validation->errors(),
                ], 400);
            }
            $userId = $request->user_id;
            $yearFilter = $request->year;
            if ($userId != 'all') {
                $user = User::find($userId);
                $recon = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->orderBy('id', 'asc')->groupBy(['finalize_id', 'user_id', 'start_date', 'end_date'])->whereIn('status', ['payroll', 'clawback'])->where('user_id', $userId);
            } else {
                $user = User::find(auth()->user()->id);
                $recon = ReconciliationFinalizeHistory::whereYear('created_at', $yearFilter)->orderBy('id', 'asc')->groupBy(['finalize_id', 'user_id', 'start_date', 'end_date'])->whereIn('status', ['payroll', 'clawback']);
            }
            $recon = $recon->get();

            $response = $recon->transform(function ($result) {

                $totalCommission = ReconCommissionHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_id' => $result->finalize_id])->whereNotIn('status', ['finalize'])->sum('paid_amount') ?? 0;
                $totalOverride = ReconOverrideHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_id' => $result->finalize_id])->whereNotIn('status', ['finalize'])->sum('paid') ?? 0;
                // $totalClawback = ReconClawbackHistory::where(['user_id'=> $result->user_id, 'start_date'=> $result->start_date, 'end_date'=> $result->end_date, 'finalize_id'=> $result->finalize_id])->whereNotIn("status", ["finalize"])->sum("paid_amount") ?? 0;
                $totalClawback = ReconClawbackHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_id' => $result->finalize_id])->sum('paid_amount') ?? 0;
                $totalAdjustment = ReconAdjustment::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_id' => $result->finalize_id])->whereNotIn('payroll_status', ['finalize'])->sum('adjustment_amount') ?? 0;
                $totalDeduction = ReconciliationFinalizeHistory::where(['user_id' => $result->user_id, 'start_date' => $result->start_date, 'end_date' => $result->end_date, 'finalize_id' => $result->finalize_id])->whereNotIn('status', ['finalize'])->sum('deductions') ?? 0;

                $total_payout = ($totalCommission + $totalOverride + $totalAdjustment - $totalDeduction - $totalClawback);

                return [
                    'user_id' => $result->user_id,
                    'start_date' => $result->start_date,
                    'end_date' => $result->end_date,
                    'executed_on' => $result->executed_on,
                    'commission' => $totalCommission,
                    'overrides' => $totalOverride,
                    'clawback' => (-1 * $totalClawback),
                    'adjustments' => $totalAdjustment - $totalDeduction,
                    'total_due' => $totalCommission + $totalOverride,
                    'payout_percent' => $result->payout,
                    'payout' => $totalCommission + $totalOverride,
                    // 'total_payout' => $result->gross_amount,
                    'total_payout' => $total_payout,
                    'status' => $result->status,
                    'sent_id' => $result->sent_count,
                    'finalize_count' => $result->finalize_id,
                    'dismiss' => isUserDismisedOn($result->user_id, date('Y-m-d')) ? 1 : 0,
                    'terminate' => isUserTerminatedOn($result->user_id, date('Y-m-d')) ? 1 : 0,
                    'contract_ended' => isUserContractEnded($result->user_id) ? 1 : 0,
                ];
            });

            $total = array_reduce($response->toArray(), function ($carry, $item) {
                $carry['totalCommision'] += $item['commission'];
                $carry['override'] += $item['overrides'];
                $carry['clawback'] += $item['clawback'];
                $carry['adjustments'] += $item['adjustments'];
                $carry['payout'] += $item['total_payout'];
                $carry['total_due'] += $item['total_due'];
                $carry['total_payout'] += $item['total_payout'];

                return $carry;
            }, ['totalCommision' => 0, 'override' => 0, 'clawback' => 0, 'adjustments' => 0, 'payout' => 0, 'total_due' => 0, 'total_payout' => 0]);

            return response()->json([
                'ApiName' => 'standard report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => $total,
                'data' => $response,
            ], 200);

        } catch (\Throwable $th) {
            Log::channel('reconLog')->debug($th);

            return response()->json([
                'api_name' => 'past reconciliation standard reports',
                'status' => false,
                'message' => 'Something went wrong',
                'data' => [],
            ], 400);
        }
    }

    /**
     * Method commissionBreakdownGraph : this function use for commission breakdown graph from standard report in recon.
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
    public function commissionBreakdownGraph(Request $request)
    {
        $checkYearFilter = $this->yearFilterValidation($request);
        if ($checkYearFilter->getStatusCode() === 400) {
            return $checkYearFilter;
        }
        $userId = $request->user_id;
        $pid = $this->getUserPids($request->year, $request->user_id);

        $pids = collect($pid)->pluck('pid');

        $countArr = $this->calculateMonthlyEarnings($request->year, $pids, $userId);
        $totalAccount = count($pids);
        $totalOverrides = $this->getTotalOverrides($pids, $userId);

        $totalCommission = $this->getCommissionSum($userId, $pids);

        $totalM1Paid = $this->calculateTotalM1Paid($userId, $pids);
        $totalM2Paid = $this->calculateTotalM2Paid($userId, $pids);
        $totalDueCommission = $totalCommission - ($totalM1Paid + $totalM2Paid);

        $clawbackSettlement = ClawbackSettlementLock::whereIn('pid', $pids);
        $clawBackData = $clawbackSettlement->count();
        $clawBackDataAmount = $clawbackSettlement->sum('clawback_amount');

        $payrollDeductionQuery = PayrollDeductionLock::query();
        if ($userId != 'all') {
            $payrollDeductionQuery->whereIn('user_id', [$userId]);
        }
        $deductions = $payrollDeductionQuery->sum('amount');

        $userOverRideQuery = UserOverridesLock::whereIn('pid', $pids);
        if ($userId != 'all') {
            $userOverRideQuery->whereIn('user_id', [$userId]);
        }
        $userOverrideLock = $userOverRideQuery->get();
        $totalEarnedOverrides = $userOverrideLock->where('is_mark_paid', 1)->sum('amount');
        $totalDueOverrides = $userOverrideLock->where('is_mark_paid', 0)->sum('amount');

        $data = $this->prepareData($countArr, $totalAccount, $totalCommission, $totalOverrides, $totalM1Paid, $totalM2Paid, $clawBackData, $clawBackDataAmount, $deductions, $totalDueCommission, $totalEarnedOverrides, $totalDueOverrides);

        return response()->json([
            'ApiName' => 'Reconciliation Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    private function getUserPids($year, $userId)
    {
        if ( /* !$this->isSuperAdmin && */ $userId != 'all') {
            return SalesMaster::join('sale_master_process', 'sale_masters.pid', '=', 'sale_master_process.pid')
                ->select('sale_master_process.*')
                ->whereYear('sale_masters.customer_signoff', $year)
                // ->whereYear('sale_masters.m2_date', $year)
                ->where(function ($query) use ($userId) {
                    $query->where('sale_master_process.closer1_id', $userId)
                        ->orWhere('sale_master_process.closer2_id', $userId)
                        ->orWhere('sale_master_process.setter1_id', $userId)
                        ->orWhere('sale_master_process.setter2_id', $userId);
                })
                ->select(
                    'sale_master_process.pid',
                    DB::raw('CASE
                                WHEN sale_master_process.closer1_id = '.$userId.' THEN sale_master_process.closer1_commission
                                WHEN sale_master_process.closer2_id = '.$userId.' THEN sale_master_process.closer2_commission
                                WHEN sale_master_process.setter1_id = '.$userId.' THEN sale_master_process.setter1_commission
                                WHEN sale_master_process.setter2_id = '.$userId.' THEN sale_master_process.setter2_commission
                            END AS user_commission'),
                    DB::raw('
                CASE
                    WHEN sale_master_process.closer1_id ='.$userId.' THEN sale_master_process.closer1_id
                    WHEN sale_master_process.setter1_id ='.$userId.' THEN sale_master_process.setter1_id
                    WHEN sale_master_process.closer2_id ='.$userId.' THEN sale_master_process.closer2_id
                    WHEN sale_master_process.setter2_id ='.$userId.' THEN sale_master_process.setter2_id
                    ELSE NULL
                END AS user_id')
                )
                ->get();
        } else {
            return SalesMaster::join('sale_master_process', 'sale_masters.pid', '=', 'sale_master_process.pid')
                ->select('sale_master_process.*')
                ->whereYear('sale_masters.customer_signoff', $year)
                // ->whereYear('sale_masters.m2_date', $year)
                ->select(
                    'sale_master_process.pid',
                )
                ->get();
        }
    }

    private function calculateMonthlyEarnings($year, $pid, $userId)
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $countArr = [];

        for ($i = 1; $i <= 12; $i++) {
            $startMonth = Carbon::now()->setYear($year)->month($i)->day(1)->format('Y-m-d');
            $endMonth = Carbon::now()->setYear($year)->month($i)->endOfMonth()->format('Y-m-d');

            $m1AmountTotal = $this->getTotalAmount($pid, $startMonth, $endMonth, 'getMone', ['m1'], $userId);
            $m2AmountTotal = $this->getTotalAmount($pid, $startMonth, $endMonth, 'getMtwo', ['m2'], $userId);

            /* $m1AmountTotal = $this->getTotalAmount($pid, $startMonth, $endMonth, 'getMone', ['closer1_m1', 'closer2_m1', 'setter1_m1', 'setter2_m1']);
            $m2AmountTotal = $this->getTotalAmount($pid, $startMonth, $endMonth, 'getMtwo', ['closer1_m2', 'closer2_m2', 'setter1_m2', 'setter2_m2']); */

            $countArr[$months[$i - 1]]['total_earnings'] = round($m1AmountTotal['totalAmount'] + $m2AmountTotal['totalAmount'], 5);
            $countArr[$months[$i - 1]]['m1'] = round($m1AmountTotal['totalAmount'], 5);
            $countArr[$months[$i - 1]]['m2'] = round($m2AmountTotal['totalAmount'], 5);
            /* // $countArr[$months[$i - 1]]['total_paid_earnings'] = round($m1AmountTotal["paidAmount"] + $m2AmountTotal["paidAmount"], 5);
            $countArr[$months[$i - 1]]['paid_m1'] = round($m1AmountTotal["paidAmount"], 5);
            $countArr[$months[$i - 1]]['paid_m2'] = round($m2AmountTotal["paidAmount"], 5);
            // $countArr[$months[$i - 1]]['total_unpaid_earnings'] = round($m1AmountTotal["unpaidAmount"] + $m2AmountTotal["unpaidAmount"], 5);
            $countArr[$months[$i - 1]]['unpaid_m1'] = round($m1AmountTotal["unpaidAmount"], 5);
            $countArr[$months[$i - 1]]['unpaid_m2'] = round($m2AmountTotal["unpaidAmount"], 5); */
        }

        return $countArr;
    }

    private function getTotalAmount($pid, $startMonth, $endMonth, $relation, $fields, $userId)
    {
        $amountTotal = 0;
        $paidAmount = 0;
        $unpaidAmount = 0;

        $allPids = SalesMaster::select('pid', 'id', 'customer_signoff')
            ->whereIn('pid', $pid)
            ->whereBetween('customer_signoff', [$startMonth, $endMonth])
            ->with($relation)
            ->get();
        foreach ($allPids as $pidValue) {
            foreach ($fields as $field) {
                if ($userId != 'all') {
                    $amountTotal += UserCommission::where('pid', $pidValue->pid)->where('user_id', $userId)->where('amount_type', $field)->sum('amount');
                    $paidAmount += UserCommission::where('pid', $pidValue->pid)->where('user_id', $userId)->where('status', 3)->where('amount_type', $field)->sum('amount');
                    $unpaidAmount = UserCommission::where('pid', $pidValue->pid)->where('user_id', $userId)->where('status', 1)->where('amount_type', $field)->sum('amount');
                } else {
                    $amountTotal += UserCommission::where('pid', $pidValue->pid)->where('amount_type', $field)->sum('amount');
                    $paidAmount += UserCommission::where('pid', $pidValue->pid)->where('status', 3)->where('amount_type', $field)->sum('amount');
                    $unpaidAmount += UserCommission::where('pid', $pidValue->pid)->where('status', 1)->where('amount_type', $field)->sum('amount');
                }
            }
        }

        return [
            'totalAmount' => $amountTotal,
            'paidAmount' => $paidAmount,
            'unpaidAmount' => $unpaidAmount,
        ];
    }

    private function getTotalOverrides($pid, $userId)
    {
        $query = UserOverridesLock::whereIn('pid', $pid);
        if ($userId != 'all') {
            return $query->where('user_id', $userId)->sum('amount');
        }

        return $query->sum('amount');
    }

    private function getCommissionSum($userId, $pids)
    {
        $reconQuery = ReconCommissionHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize']);

        if ($userId != 'all') {
            $totalAmount = $reconQuery->where('user_id', $userId)->sum('paid_amount');
        } else {
            $totalAmount = $reconQuery->sum('paid_amount');
        }

        return $totalAmount;
    }

    private function calculateTotalM1Paid($userId, $pids)
    {
        if ($userId != 'all') {
            $query = SaleMasterProcess::selectRaw('
                sum(CASE WHEN closer1_id = ? THEN closer1_m1 ELSE 0 END) as closer1_m1,
                sum(CASE WHEN closer2_id = ? THEN closer2_m1 ELSE 0 END) as closer2_m1,
                sum(CASE WHEN setter1_id = ? THEN setter1_m1 ELSE 0 END) as setter1_m1,
                sum(CASE WHEN setter2_id = ? THEN setter2_m1 ELSE 0 END) as setter2_m1
            ', [$userId, $userId, $userId, $userId])->whereIn('pid', $pids);

            $query = $query->where(function ($query) use ($userId) {
                $query->where('closer1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('setter2_id', $userId);
            });
        } else {
            $query = SaleMasterProcess::selectRaw('
                sum(closer1_m1) as closer1_m1,
                sum(closer2_m1) as closer2_m1,
                sum(setter1_m1) as setter1_m1,
                sum(setter2_m1) as setter2_m1
            ')->whereIn('pid', $pids);
        }
        $m1Paid = $query->get();

        return round($m1Paid->sum('closer1_m1') + $m1Paid->sum('closer2_m1') + $m1Paid->sum('setter1_m1') + $m1Paid->sum('setter2_m1'), 5);
    }

    private function calculateTotalM2Paid($userId, $pids)
    {
        if ($userId != 'all') {
            $query = SaleMasterProcess::selectRaw('
                sum(CASE WHEN closer1_id = ? THEN closer1_m2 ELSE 0 END) as closer1_m2,
                sum(CASE WHEN closer2_id = ? THEN closer2_m2 ELSE 0 END) as closer2_m2,
                sum(CASE WHEN setter1_id = ? THEN setter1_m2 ELSE 0 END) as setter1_m2,
                sum(CASE WHEN setter2_id = ? THEN setter2_m2 ELSE 0 END) as setter2_m2
            ', [$userId, $userId, $userId, $userId])->whereIn('pid', $pids);

            $query = $query->where(function ($query) use ($userId) {
                $query->where('closer1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('setter2_id', $userId);
            });
        } else {
            $query = SaleMasterProcess::selectRaw('
                sum(closer1_m2) as closer1_m2,
                sum(closer2_m2) as closer2_m2,
                sum(setter1_m2) as setter1_m2,
                sum(setter2_m2) as setter2_m2
            ')->whereIn('pid', $pids);
        }
        $m2Paid = $query->get();

        return round($m2Paid->sum('closer1_m2') + $m2Paid->sum('closer2_m2') + $m2Paid->sum('setter1_m2') + $m2Paid->sum('setter2_m2'), 5);
    }

    private function prepareData($countArr, $totalAccount, $totalCommission, $totalOverrides, $totalM1Paid, $totalM2Paid, $clawBackData, $clawBackDataAmount, $deductions, $totalDueCommission, $totalEarnedOverrides, $totalDueOverrides)
    {
        return [
            'graph' => $countArr,
            'earning_break_down' => [
                'total_account' => $totalAccount,
                'commission' => round($totalCommission ?? 0, 2),
                'overrides' => round($totalOverrides ?? 0, 2),
                'other_item' => 0, // Adjust if you have additional items to include
            ],
            'commission' => [
                'total_account' => $totalAccount,
                'commission_earnings' => round($totalCommission ?? 0, 2),
                'm1_paid' => round($totalM1Paid ?? 0, 2),
                'm2_paid' => round($totalM2Paid ?? 0, 2),
                'advances' => 0, // Adjust if you have additional items to include
                'clawback_account' => "$clawBackData ($clawBackDataAmount)",
                'total_due' => round($totalDueCommission ?? 0, 2),
            ],
            'overrides' => [
                'total_earnings' => 0, // Adjust if you have additional items to include
                'direct' => 0, // Adjust if you have additional items to include
                'indirect' => 0, // Adjust if you have additional items to include
                'office' => 0, // Adjust if you have additional items to include
                'total_due' => round($totalDueOverrides ?? 0, 2),
            ],
            'other_item' => [
                'reimbursements' => 0, // Adjust if you have additional items to include
                'incentives' => 0, // Adjust if you have additional items to include
                'miscellaneous' => 0, // Adjust if you have additional items to include
                'travel' => 0, // Adjust if you have additional items to include
                'rent' => 0, // Adjust if you have additional items to include
                'bonus' => 0, // Adjust if you have additional items to include
                'total_due' => 0, // Adjust if you have additional items to include
            ],
            'deductions' => [
                'rent' => 0, // Adjust if you have additional items to include
                'sign_on_bonus' => 0, // Adjust if you have additional items to include
                'travel' => 0, // Adjust if you have additional items to include
                'phone_bill' => 0, // Adjust if you have additional items to include
                'total_due' => round($deductions ?? 0, 2),
            ],
            'payout_summary' => [
                'commission' => [
                    'total_value' => round($totalCommission ?? 0, 2),
                    'paid' => round($totalM1Paid + $totalM2Paid ?? 0, 2),
                    'held_back' => 0, // Adjust if you have additional items to include
                    'due_amount' => round($totalDueCommission ?? 0, 2),
                ],
                'overrides' => [
                    'total_value' => round($totalOverrides ?? 0, 2),
                    'paid' => round($totalEarnedOverrides ?? 0, 2),
                    'held_back' => 0, // Adjust if you have additional items to include
                    'due_amount' => round($totalDueOverrides ?? 0, 2),
                ],
                'other_item' => [
                    'total_value' => 0, // Adjust if you have additional items to include
                    'paid' => 0, // Adjust if you have additional items to include
                    'held_back' => 0, // Adjust if you have additional items to include
                    'due_amount' => 0, // Adjust if you have additional items to include
                ],
                'deduction' => [
                    'total_value' => round($deductions ?? 0, 2),
                    'paid' => 0, // Adjust if you have additional items to include
                    'held_back' => 0, // Adjust if you have additional items to include
                    'due_amount' => round($deductions ?? 0, 2),
                ],
                'total_due' => round($totalDueCommission + $totalDueOverrides + $deductions ?? 0, 2),
            ],
        ];
    }

    public function outstandingReconValues_old(Request $request)
    {
        $checkYearFilter = $this->yearFilterValidation($request);
        if ($checkYearFilter->getStatusCode() === 400) {
            return $checkYearFilter;
        }
        $userId = $request->user_id;
        $pid = $this->getUserPids($request->year, $request->user_id);

        $pids = collect($pid)->pluck('pid');

        $totalCommission = $this->getCommissionSum($userId, $pids);

        /* getting recon commission */
        $commissionData = $this->getTotalCommissionValue($userId, $totalCommission, $pids);
        $overrideData = $this->getTotalOverrideValue($userId, $pids);
        $clawbackData = $this->getTotalClawbackValue($userId, $pids);
        $adjustmentData = $this->getTotalAdjustment($userId, $pids);

        $result[] = $commissionData;
        $result[] = $overrideData;
        $result[] = $clawbackData;
        $result[] = $adjustmentData;
        $result[] = [
            'type' => 'Total',
            'paid_amount' => floatval($commissionData['paid_amount']) + floatval($overrideData['paid_amount']) + floatval($clawbackData['paid_amount']) + floatval($adjustmentData['paid_amount']),
            'in_recon_amount' => floatval($commissionData['in_recon_amount']) + floatval($overrideData['in_recon_amount']) + floatval($clawbackData['in_recon_amount']) + floatval($adjustmentData['in_recon_amount']),
            'total_amount' => floatval($commissionData['total_amount']) + floatval($overrideData['total_amount']) + floatval($clawbackData['total_amount']) + floatval($adjustmentData['total_amount']),
        ];

        return response()->json([
            self::API_NAME => 'outstandingReconValues',
            'status' => true,
            'data' => $result,
        ], 200);
    }

    public function outstandingReconValues(Request $request)
    {
        $checkYearFilter = $this->yearFilterValidation($request);
        if ($checkYearFilter->getStatusCode() === 400) {
            return $checkYearFilter;
        }

        $year = $request->year;
        $userId = $request->user_id;

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        if ($userId == 'all') {

            // $totalCommission = ReconCommissionHistory::whereYear('created_at', $year)->sum("total_amount") ?? 0;
            // $totalCommissionpaid = ReconCommissionHistory::whereYear('created_at', $year)->sum("paid_amount") ?? 0;

            $totalCommission = UserCommission::whereIn('pid', $pids)->sum('amount');
            $totalduringm2 = UserCommission::whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
            $paidrcon = ReconCommissionHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid_amount');
            $totalCommissionpaid = $totalduringm2 + $paidrcon;

            $totalOverridesum = ReconOverrideHistory::whereYear('created_at', $year)->sum('total_amount') ?? 0;
            $totalOverridepaid = ReconOverrideHistory::whereYear('created_at', $year)->sum('paid') ?? 0;
            // $totalClawback = ReconClawbackHistory::whereYear('created_at', $year)->sum("paid_amount") ?? 0;
            $totalClawback = ReconClawbackHistory::selectRaw('SUM(CASE WHEN payroll_execute_status > 0 THEN paid_amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN payroll_execute_status = 0 THEN paid_amount ELSE 0 END) as unpaid_amount')->whereYear('created_at', $year)->first();
            // $totalAdjustment = ReconAdjustment::whereYear('created_at', $year)->sum("adjustment_amount") ?? 0;
            $totalAdjustment = ReconAdjustment::selectRaw('SUM(CASE WHEN payroll_execute_status > 0 THEN adjustment_amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN payroll_execute_status = 0 THEN adjustment_amount ELSE 0 END) as unpaid_amount')->whereYear('created_at', $year)->first();
            // $totalDeduction = ReconDeductionHistory::whereYear('created_at', $year)->sum("total") ?? 0;
            $totalDeduction = ReconDeductionHistory::selectRaw('SUM(CASE WHEN payroll_executed_status > 0 THEN total ELSE 0 END) as paid_amount,
            SUM(CASE WHEN payroll_executed_status = 0 THEN total ELSE 0 END) as unpaid_amount')->whereYear('created_at', $year)->first();
        } else {
            // $totalCommission = ReconCommissionHistory::whereYear('created_at', $year)->where(['user_id'=> $userId])->sum("total_amount") ?? 0;
            // $totalCommissionpaid = ReconCommissionHistory::whereYear('created_at', $year)->where(['user_id'=> $userId])->sum("paid_amount") ?? 0;

            $totalCommission = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->sum('amount');
            $totalduringm2 = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
            $paidrcon = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid_amount');
            $totalCommissionpaid = $totalduringm2 + $paidrcon;

            $totalOverridesum = ReconOverrideHistory::whereYear('created_at', $year)->where(['user_id' => $userId])->sum('total_amount') ?? 0;
            $totalOverridepaid = ReconOverrideHistory::whereYear('created_at', $year)->where(['user_id' => $userId])->sum('paid') ?? 0;
            // $totalClawback = ReconClawbackHistory::whereYear('created_at', $year)->where(['user_id'=> $userId])->sum("paid_amount") ?? 0;
            $totalClawback = ReconClawbackHistory::selectRaw('SUM(CASE WHEN payroll_execute_status > 0 THEN paid_amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN payroll_execute_status = 0 THEN paid_amount ELSE 0 END) as unpaid_amount')->whereYear('created_at', $year)->where(['user_id' => $userId])->first();
            // $totalAdjustment = ReconAdjustment::whereYear('created_at', $year)->where(['user_id'=> $userId])->sum("adjustment_amount") ?? 0;
            $totalAdjustment = ReconAdjustment::selectRaw('SUM(CASE WHEN payroll_execute_status > 0 THEN adjustment_amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN payroll_execute_status = 0 THEN adjustment_amount ELSE 0 END) as unpaid_amount')->whereYear('created_at', $year)->where(['user_id' => $userId])->first();
            // $totalDeduction = ReconDeductionHistory::whereYear('created_at', $year)->where(['user_id'=> $userId])->sum("total") ?? 0;
            $totalDeduction = ReconDeductionHistory::selectRaw('SUM(CASE WHEN payroll_executed_status > 0 THEN total ELSE 0 END) as paid_amount,
            SUM(CASE WHEN payroll_executed_status = 0 THEN total ELSE 0 END) as unpaid_amount')->whereYear('created_at', $year)->where(['user_id' => $userId])->first();
        }

        $adjustmentPaid = ($totalAdjustment->paid_amount - $totalDeduction->paid_amount);
        $adjustmentUnPaid = ($totalAdjustment->unpaid_amount - $totalDeduction->unpaid_amount);

        /* getting recon commission */
        $commissionData = [
            'type' => 'Commission',
            'paid_amount' => floatval($totalCommissionpaid),
            'in_recon_amount' => floatval($totalCommission - $totalCommissionpaid),
            'total_amount' => floatval($totalCommission),
        ];
        $overrideData = [
            'type' => 'Overrides',
            'paid_amount' => floatval($totalOverridepaid),
            'in_recon_amount' => floatval($totalOverridesum - $totalOverridepaid),
            'total_amount' => floatval($totalOverridesum),
        ];
        $clawbackData = [
            'type' => 'Clawback',
            'paid_amount' => -1 * $totalClawback->paid_amount,
            'in_recon_amount' => -1 * $totalClawback->unpaid_amount,
            'total_amount' => -1 * ($totalClawback->paid_amount + $totalClawback->unpaid_amount),
        ];
        $adjustmentData = [
            'type' => 'Adjustments',
            'paid_amount' => floatval($adjustmentPaid),
            'in_recon_amount' => floatval($adjustmentUnPaid),
            'total_amount' => floatval($adjustmentPaid + $adjustmentUnPaid),
        ];

        $result[] = $commissionData;
        $result[] = $overrideData;
        $result[] = $clawbackData;
        $result[] = $adjustmentData;
        $result[] = [
            'type' => 'Total',
            'paid_amount' => floatval($commissionData['paid_amount']) + floatval($overrideData['paid_amount']) + floatval($clawbackData['paid_amount']) + floatval($adjustmentData['paid_amount']),
            'in_recon_amount' => floatval($commissionData['in_recon_amount']) + floatval($overrideData['in_recon_amount']) + floatval($clawbackData['in_recon_amount']) + floatval($adjustmentData['in_recon_amount']),
            'total_amount' => floatval($commissionData['total_amount']) + floatval($overrideData['total_amount']) + floatval($clawbackData['total_amount']) + floatval($adjustmentData['total_amount']),
        ];

        return response()->json([
            self::API_NAME => 'outstandingReconValues',
            'status' => true,
            'data' => $result,
        ], 200);
    }

    public function outstandingReconGraph(Request $request)
    {
        $checkYearFilter = $this->yearFilterValidation($request);
        if ($checkYearFilter->getStatusCode() === 400) {
            return $checkYearFilter;
        }

        $year = $request->year;
        $userId = $request->user_id;

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        if ($userId == 'all') {
            $totalCommission = UserCommission::whereIn('pid', $pids)->sum('amount');
            $totalduringm2 = UserCommission::whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
            $paidrcon = ReconCommissionHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid_amount');
            $totalCommissionpaid = $totalduringm2 + $paidrcon;
            $totalOverridesum = ReconOverrideHistory::whereYear('created_at', $year)->sum('total_amount') ?? 0;
            $totalOverridepaid = ReconOverrideHistory::whereYear('created_at', $year)->sum('paid') ?? 0;
            $totalClawback = ReconClawbackHistory::whereYear('created_at', $year)->where(['payroll_execute_status' => 0])->sum('paid_amount') ?? 0;
            $totalAdjustment = ReconAdjustment::whereYear('created_at', $year)->where(['payroll_execute_status' => 0])->sum('adjustment_amount') ?? 0;
            $totalDeduction = ReconDeductionHistory::whereYear('created_at', $year)->where(['payroll_executed_status' => 0])->sum('total') ?? 0;
        } else {
            $totalCommission = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->sum('amount');
            $totalduringm2 = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
            $paidrcon = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid_amount');
            $totalCommissionpaid = $totalduringm2 + $paidrcon;

            $totalOverridesum = ReconOverrideHistory::whereYear('created_at', $year)->where(['user_id' => $userId])->sum('total_amount') ?? 0;
            $totalOverridepaid = ReconOverrideHistory::whereYear('created_at', $year)->where(['user_id' => $userId])->sum('paid') ?? 0;
            $totalClawback = ReconClawbackHistory::whereYear('created_at', $year)->where(['user_id' => $userId, 'payroll_execute_status' => 0])->sum('paid_amount') ?? 0;
            $totalAdjustment = ReconAdjustment::whereYear('created_at', $year)->where(['user_id' => $userId, 'payroll_execute_status' => 0])->sum('adjustment_amount') ?? 0;
            $totalDeduction = ReconDeductionHistory::whereYear('created_at', $year)->where(['user_id' => $userId, 'payroll_executed_status' => 0])->sum('total') ?? 0;
        }

        $totalCommissions = $totalCommission - $totalCommissionpaid;
        $totalOverrides = $totalOverridesum - $totalOverridepaid;
        $adjustments = $totalAdjustment - $totalDeduction;

        $totalInReconAmount = $totalCommissions + $totalOverrides + $adjustments - $totalClawback;

        if ($totalInReconAmount != 0) {
            $commissionPercentage = floatval($totalCommissions ?? 0);
            $overridePercentage = floatval($totalOverrides ?? 0);
            // $clawbackPercentage = -1 * floatval($totalClawback ?? 0);
            $clawbackPercentage = floatval($totalClawback ?? 0);
            $adjustmentPercentage = floatval($adjustments ?? 0);
        } else {
            // Handle the case where $totalInReconAmount is zero
            $commissionPercentage = 0;
            $overridePercentage = 0;
            $clawbackPercentage = 0;
            $adjustmentPercentage = 0;
        }
        $result[] = [
            'commission' => ($commissionPercentage > 0) ? $commissionPercentage : (-1 * $commissionPercentage),
            'overrides' => ($overridePercentage > 0) ? $overridePercentage : (-1 * $overridePercentage),
            'clawback' => $clawbackPercentage,
            'adjustments' => ($adjustmentPercentage > 0) ? $adjustmentPercentage : (-1 * $adjustmentPercentage),
            'total_recon_amount' => floatval($totalInReconAmount),
        ];

        return response()->json([
            self::API_NAME => 'outstandingReconValues',
            'status' => true,
            'data' => $result,
        ], 200);
    }

    public function getCommissionReportList(Request $request, $id)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Reports Reconciliation Clawback By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',

            ], 400);
        }
        $sentId = $request->sent_id;
        $pid = UserReconciliationWithholding::where('closer_id', $id)->where('status', 'unpaid')->orWhere('setter_id', $id)->where('status', 'unpaid')->pluck('pid');
        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
        $data = ReconciliationFinalizeHistory::where('user_id', $id)->where('status', 'payroll')->where('sent_count', $sentId)->get();
        $data->transform(function ($data) use ($id, $sentId) {
            $userId = isset($data->user_id) ? $data->user_id : 0;
            $redline = User::where('id', $userId)->select('id', 'redline', 'redline_type')->first();
            $payout = ReconciliationFinalizeHistory::where('id', $data->id)->where('user_id', $id)->where('status', 'payroll')->where('sent_count', $sentId)->first();
            $val = UserReconciliationWithholding::with('salesDetail')
                ->where('pid', $data->pid)
                ->where('closer_id', $id)
                ->orWhere('setter_id', $id)
                ->where('pid', $data->pid)
                ->first();
            if (isset($payout->payout) && $payout->payout != '') {
                $payOut = $payout->payout;

                if ($data->withhold_amount > 0) {
                    $totalPaid = $data->withhold_amount * $payOut / 100;
                } else {
                    $totalPaid = 0;
                }
                $location = Locations::with('State')->where('general_code', $val->salesDetail->customer_state)->first();
                if ($location) {
                    $state_code = $location->state->state_code;
                } else {
                    $state_code = null;
                }

                $paidAmount = ReconciliationFinalizeHistory::where('user_id', $id)->where('pid', $data->pid)->where('status', 'payroll')->where('sent_count', $sentId)->sum('paid_commission');
                $paidAdjustmant = ReconciliationsAdjustement::where('user_id', $id)->where('pid', $data->pid)->where('payroll_status', 'payroll')->sum('adjustment');

                $recon = $paidAmount;

                if (isset($paidAdjustmant) && $paidAdjustmant != '') {
                    $paidAdjustmant = $paidAdjustmant;
                } else {
                    $paidAdjustmant = 0;
                }

                // return $recon;
                return [
                    'id' => $data->id,
                    'user_id' => $userId,
                    'pid' => $data->pid,
                    'state_id' => $state_code,
                    'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                    'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                    'rep_redline' => isset($redline->redline) ? $redline->redline : null,
                    'kw' => isset($redline->redline_type) ? $redline->redline_type : null,
                    'net_epc' => $data->salesDetail->net_epc,
                    'epc' => $data->salesDetail->epc,
                    'adders' => $data->salesDetail->adders,
                    'type' => 'Withheld', // $data->type,
                    'amount' => $data->commission,
                    'paid' => $paidAmount,
                    'in_recon' => $data->commission - $paidAmount,
                    'finalize_payout' => 0,
                    'adjustment_amount' => isset($data->adjustment_amount) ? $data->adjustment_amount - $paidAdjustmant : 0,
                ];
            }
        });

        $commissionTotal = ReconciliationFinalizeHistory::where('user_id', $id)->where('status', 'payroll')->where('sent_count', $sentId)->get();

        $subtotal = 0;

        foreach ($commissionTotal as $datas) {
            $userId = $id;
            $paidAmount = ReconciliationFinalizeHistory::where('user_id', $id)->where('pid', $datas->pid)->where('status', 'payroll')->where('sent_count', $sentId)->sum('paid_commission');

            $subtotal += $paidAmount;
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'subtotal' => $subtotal,
        ], 200);
    }

    protected function yearFilterValidation($request)
    {
        $validation = Validator::make($request->all(), [
            'year' => [
                'required',
                'digits:4',
                'integer',
                'between:2000,'.Carbon::now()->year,
            ],
            'user_id' => ['required', function ($attribute, $value, $fail) {
                if ($value != 'all') {
                    if (! User::where('id', $value)->exists()) {
                        $fail('This user is not exists in our system.');
                    }
                }
            }],
        ]);
        if ($validation->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validation->errors(),
            ], 400);
        }

        return response()->json([
            'status' => true,
            'errors' => [],
        ], 200);
    }

    private function getTotalOverrideValue($userId, $pids)
    {

        $reconOverrideQuery = ReconOverrideHistory::whereIn('pid', $pids);
        if ($userId != 'all') {

            $totalAmountforpid = UserOverrides::whereIn('pid', $pids)->where('user_id', $userId)->sum('amount');
            $totalAmountrecuterpid = UserOverrides::whereNotIn('pid', $pids)->where('user_id', $userId)->sum('amount');
            $totalAmount = $totalAmountforpid + $totalAmountrecuterpid;
            $PaidAmount = ReconOverrideHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', '=', 3)->sum('paid');
            $PaidAmountforrecon = ReconOverrideHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->sum('paid');
            $unPaidAmount = $totalAmount - $PaidAmountforrecon;

        } else {

            $totalAmountforpid = UserOverrides::whereIn('pid', $pids)->sum('amount');
            $totalAmountrecuterpid = UserOverrides::whereNotIn('pid', $pids)->sum('amount');
            $totalAmount = $totalAmountforpid + $totalAmountrecuterpid;
            $PaidAmount = ReconOverrideHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', '=', 3)->sum('paid');
            $PaidAmountforrecon = ReconOverrideHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid');
            $unPaidAmount = $totalAmount - $PaidAmountforrecon;

        }

        return [
            'type' => 'Overrides',
            'paid_amount' => $PaidAmount,
            'in_recon_amount' => $unPaidAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function getTotalCommissionValue($userId, $totalCommission, $pids)
    {
        $reconValue = ReconCommissionHistory::query();

        if ($userId != 'all') {
            $totalCommission = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->sum('amount');
            $totalduringm2 = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
            $paidrcon = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->where(['payroll_execute_status' => 3])->whereNotIn('status', ['finalize'])->sum('paid_amount');
            $paid = $totalduringm2 + $paidrcon;
            $unPaidAmount = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->where(['payroll_execute_status' => 0])->whereNotIn('status', ['finalize'])->sum('paid_amount');
            if ($unPaidAmount === 0) {
                $unPaidAmounts = UserCommission::whereIn('pid', $pids)->where('user_id', $userId)->where(['settlement_type' => 'reconciliation', 'status' => 3, 'recon_status' => 1, 'is_move_to_recon' => 0])->sum('amount');
                $pidstatusbias = UserCommission::whereIn('pid', $pids)->where('user_id', $userId)->where(['settlement_type' => 'reconciliation', 'status' => 3, 'recon_status' => 2])->pluck('pid');
                $pidstatusbiass = UserCommission::whereIn('pid', $pids)->where('user_id', $userId)->where(['settlement_type' => 'reconciliation', 'status' => 3, 'recon_status' => 2])->sum('amount');
                $reconunPaidAmount = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pidstatusbias)->whereNotIn('status', ['finalize'])->sum('paid_amount');
                $unPaidAmount = $unPaidAmounts + ($pidstatusbiass - $reconunPaidAmount);

            } else {
                $reconunPaidAmount = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->where(['payroll_execute_status' => 0])->whereNotIn('status', ['finalize'])->sum('paid_amount');
                $unPaidAmountrecon = UserCommission::whereIn('pid', $pids)->where('user_id', $userId)->where(['settlement_type' => 'reconciliation', 'status' => 3, 'recon_status' => 1, 'is_move_to_recon' => 0])->sum('amount');
                $unPaidAmount = $reconunPaidAmount + $unPaidAmountrecon;
            }

        } else {
            $totalCommission = UserCommission::whereIn('pid', $pids)->sum('amount');
            $totalduringm2 = UserCommission::whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
            $paidrcon = ReconCommissionHistory::whereIn('pid', $pids)->where(['payroll_execute_status' => 3])->whereNotIn('status', ['finalize'])->sum('paid_amount');
            $paid = $totalduringm2 + $paidrcon;
            $unPaidAmount = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->where(['payroll_execute_status' => 0])->whereNotIn('status', ['finalize'])->sum('paid_amount');
            if ($unPaidAmount === 0) {
                $unPaidAmount = UserCommission::whereIn('pid', $pids)->where(['settlement_type' => 'reconciliation', 'status' => 3, 'recon_status' => 1, 'is_move_to_recon' => 0])->sum('amount');
            } else {
                $reconunPaidAmount = ReconCommissionHistory::whereIn('pid', $pids)->where(['payroll_execute_status' => 0])->whereNotIn('status', ['finalize'])->sum('paid_amount');
                $unPaidAmountrecon = UserCommission::whereIn('pid', $pids)->where(['settlement_type' => 'reconciliation', 'status' => 3, 'recon_status' => 1, 'is_move_to_recon' => 0])->sum('amount');
                $unPaidAmount = $reconunPaidAmount + $unPaidAmountrecon;
            }
        }

        return [
            'type' => 'Commission',
            'paid_amount' => $paid,
            'in_recon_amount' => $unPaidAmount,
            'total_amount' => $totalCommission,
        ];
    }

    private function getTotalClawbackValue($userId, $pids)
    {
        if ($userId != 'all') {
            // $totalAmount = ReconClawbackHistory::whereIn("pid", $pids)->whereNotIn("status", ["finalize"])->where("user_id", $userId)->sum("paid_amount");
            // $paidAmount = ReconClawbackHistory::whereIn("pid", $pids)->where("user_id", $userId)->whereNotIn("status", ["finalize"])->where("payroll_execute_status", 3)->sum("paid_amount");
            // $inReconAmount = ReconClawbackHistory::whereIn("pid", $pids)->where("user_id", $userId)->whereNotIn("status", ["finalize"])->where("payroll_execute_status", "!=", 3)->sum("paid_amount");
            // if(empty($totalAmount) && empty($paidAmount)){
            $totalAmount = ClawbackSettlement::where('user_id', $userId)->sum('clawback_amount');
            $paidAmount = ClawbackSettlement::where('user_id', $userId)->where('recon_status', 3)->where('status', 6)->sum('clawback_amount');
            $inReconAmount = ClawbackSettlement::where('user_id', $userId)->where('recon_status', 1)->where('status', 3)->sum('clawback_amount');

            // }
        } else {
            $totalAmount = ClawbackSettlement::sum('clawback_amount');
            $paidAmount = ClawbackSettlement::where('recon_status', 3)->where('status', 6)->sum('clawback_amount');
            $inReconAmount = ClawbackSettlement::where('recon_status', 1)->where('status', 3)->sum('clawback_amount');
        }

        return [
            'type' => 'Clawback',
            'paid_amount' => -1 * $paidAmount,
            'in_recon_amount' => -1 * $inReconAmount,
            'total_amount' => -1 * $totalAmount,
        ];
    }

    private function getTotalAdjustment($userId, $pids)
    {
        if ($userId != 'all') {
            $totalAdjustmentAmount = ReconAdjustment::whereNotIn('payroll_status', ['finalize'])->where('user_id', $userId)->whereIn('pid', $pids)->sum('adjustment_amount') ?? 0;
            $paidAdjustmentAmount = ReconAdjustment::whereNotIn('payroll_status', ['finalize'])->where('user_id', $userId)->where('payroll_execute_status', 3)->whereIn('pid', $pids)->sum('adjustment_amount') ?? 0;
            $unpaidAdjustmentAmount = ReconAdjustment::whereNotIn('payroll_status', ['finalize'])->where('user_id', $userId)->where('payroll_execute_status', '!=', 3)->whereIn('pid', $pids)->sum('adjustment_amount') ?? 0;

            /* deduction */
            $totalDeductionAmount = ReconciliationFinalizeHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->sum('deductions') ?? 0;
            $paidDeduction = ReconciliationFinalizeHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', 3)->sum('deductions') ?? 0;
            // $paidDeduction = PayrollDeductions::where("user_id", $userId)->where("is_move_to_recon",1)->where("is_move_to_recon_paid", 1)->sum("total") ?? 0;
            $unpaidDeduction = ReconciliationFinalizeHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', '!=', 3)->sum('deductions') ?? 0;
        } else {
            $totalAdjustmentAmount = ReconAdjustment::whereNotIn('payroll_status', ['finalize'])->whereIn('pid', $pids)->sum('adjustment_amount') ?? 0;
            $paidAdjustmentAmount = ReconAdjustment::whereNotIn('payroll_status', ['finalize'])->where('payroll_execute_status', 3)->whereIn('pid', $pids)->sum('adjustment_amount') ?? 0;
            $unpaidAdjustmentAmount = ReconAdjustment::whereNotIn('payroll_status', ['finalize'])->where('payroll_execute_status', '!=', 3)->whereIn('pid', $pids)->sum('adjustment_amount') ?? 0;

            /* deduction */
            $totalDeductionAmount = ReconciliationFinalizeHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('deductions') ?? 0;
            $paidDeduction = ReconciliationFinalizeHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', 3)->sum('deductions') ?? 0;
            $unpaidDeduction = ReconciliationFinalizeHistory::whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', '!=', 3)->sum('deductions') ?? 0;
        }
        $totalAmount = $totalAdjustmentAmount - $totalDeductionAmount;
        $paidAmount = $paidAdjustmentAmount - $paidDeduction;
        $totalInReconAmount = $unpaidAdjustmentAmount - $unpaidDeduction;

        return [
            'type' => 'Adjustments',
            'paid_amount' => $paidAmount,
            'in_recon_amount' => $totalInReconAmount,
            'total_amount' => floatval($totalAmount),
        ];
    }

    private function getTotalCommissionValueNew($userId, $totalCommission = 0)
    {
        $withHeldQuery = UserReconciliationWithholding::query();

        $withHeldQuery = $withHeldQuery->whereIn('status', ['unpaid', 'paid']);
        if ($userId != 'all') {
            $withHeldQuery->where('closer_id', $userId)
                ->orWhere('setter_id', $userId);
            $withHeldData = $withHeldQuery->get();
            $totalReconAmount = $withHeldData->sum('withhold_amount');
        } else {
            $withHeldData = $withHeldQuery->get();
            $totalReconAmount = $withHeldData->sum('withhold_amount');
        }

        $totalReconAmount = $withHeldQuery->sum('withhold_amount');
        /* paid amount from recon commission table for recon */
        $paidReconAmount = ReconCommissionHistoryLock::where('move_from_payroll', 0)->where('status', 'payroll')->sum('paid_amount');

        /* move to recon amount */
        $moveToReconDataQuery = UserCommission::where('status', 6)
            ->where('is_move_to_recon', 1)
            ->where(function ($query) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('weekly_pay_frequencies as w_p_f')
                        ->whereColumn('w_p_f.pay_period_from', 'user_commission.pay_period_from')
                        ->whereColumn('w_p_f.pay_period_to', 'user_commission.pay_period_to')
                        ->where('w_p_f.closed_status', '=', 1)
                        ->whereRaw('DATEDIFF(user_commission.pay_period_to, user_commission.pay_period_from) <= 7');
                })
                    ->orWhereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('monthly_pay_frequencies as m_p_f')
                            ->whereColumn('m_p_f.pay_period_from', 'user_commission.pay_period_from')
                            ->whereColumn('m_p_f.pay_period_to', 'user_commission.pay_period_to')
                            ->where('m_p_f.closed_status', '=', 1)
                            ->whereRaw('DATEDIFF(user_commission.pay_period_to, user_commission.pay_period_from) > 7');
                    });
            });

        $totalMoveToReconAmount = $moveToReconDataQuery->sum('user_commission.amount');
        /* paid amount for move to recon */
        $paidMoveToReconAmount = ReconCommissionHistoryLock::where('move_from_payroll', 1)->sum('paid_amount');
        $totalReconAmount = $totalReconAmount + $totalMoveToReconAmount;
        $totalPaidAmount = $paidReconAmount + $paidMoveToReconAmount;
        $totalUnpaidAmount = $totalReconAmount - $totalPaidAmount;

        return [
            'type' => 'Commission',
            'paid_amount' => $totalPaidAmount,
            'in_recon_amount' => $totalUnpaidAmount,
            'total_amount' => $totalReconAmount,
        ];
    }

    private function getTotalOverrideValueNew($userId, $pids)
    {
        $reconOverrideQuery = UserOverrides::whereIn('pid', $pids)->where('overrides_settlement_type', 'reconciliation');

        if ($userId != 'all') {
            $reconOverrideQuery->where('user_id', $userId);
        }
        $totalReconAmount = $reconOverrideQuery->sum('amount');
        $paidReconAmount = ReconOverrideHistoryLock::where('move_from_payroll', 0)->where('status', 'payroll');
        if ($userId != 'all') {
            $paidReconAmount->where('user_id', $userId)->whereIn('pid', $pids);
        }
        $reconUnpaidAmount = $totalReconAmount - $paidReconAmount->sum('paid');

        /* move to recon amount */
        $moveToReconDataQuery = UserOverrides::whereIn('pid', $pids)->where('status', 6)
            ->where('is_move_to_recon', 1)
            ->where(function ($query) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('weekly_pay_frequencies as w_p_f')
                        ->whereColumn('w_p_f.pay_period_from', 'user_overrides.pay_period_from')
                        ->whereColumn('w_p_f.pay_period_to', 'user_overrides.pay_period_to')
                        ->where('w_p_f.closed_status', '=', 1)
                        ->whereRaw('DATEDIFF(user_overrides.pay_period_to, user_overrides.pay_period_from) <= 7');
                })
                    ->orWhereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('monthly_pay_frequencies as m_p_f')
                            ->whereColumn('m_p_f.pay_period_from', 'user_overrides.pay_period_from')
                            ->whereColumn('m_p_f.pay_period_to', 'user_overrides.pay_period_to')
                            ->where('m_p_f.closed_status', '=', 1)
                            ->whereRaw('DATEDIFF(user_overrides.pay_period_to, user_overrides.pay_period_from) > 7');
                    });
            });

        if ($userId != 'all') {
            $moveToReconDataQuery->where('user_id', $userId)->whereIn('pid', $pids);
        }

        $totalMoveToReconAmount = $moveToReconDataQuery->sum('user_overrides.amount');
        $paidMoveToReconAmount = ReconOverrideHistoryLock::where('move_from_payroll', 1)->where('status', 'payroll');
        if ($userId != 'all') {
            $paidMoveToReconAmount->where('user_id', $userId)->whereIn('pid', $pids);
        }
        $unpaidMoveToReconAmount = $totalMoveToReconAmount - $paidMoveToReconAmount->sum('paid');

        $totalAmount = $totalReconAmount + $totalMoveToReconAmount;
        $paidAmount = $paidReconAmount->sum('paid') + $paidMoveToReconAmount->sum('paid');
        $unpaidAmount = $reconUnpaidAmount + $unpaidMoveToReconAmount;

        return [
            'type' => 'Overrides',
            'paid_amount' => $paidAmount,
            'in_recon_amount' => $unpaidAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function getTotalClawbackValueNew($userId, $pids)
    {
        $reconClawbackQuery = ClawbackSettlement::where('clawback_type', 'reconciliation');

        if ($userId != 'all') {
            $reconClawbackQuery->where('user_id', $userId)->whereIn('pid', $pids);
        }

        $totalReconClawbackAmount = -1 * $reconClawbackQuery->sum('clawback_amount');
        $paidReconClawbackAmount = -1 * ReconClawbackHistoryLock::where('move_from_payroll', '0')->where('status', 'payroll')->sum('total_amount');
        $unpaidReconClawbackAmount = $totalReconClawbackAmount - $paidReconClawbackAmount;

        /* move to recon amount */
        /* move to recon amount */
        $moveToReconDataQuery = ClawbackSettlement::whereIn('pid', $pids)->where('status', 6)
            ->where('is_move_to_recon', 1)
            ->where(function ($query) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('weekly_pay_frequencies as w_p_f')
                        ->whereColumn('w_p_f.pay_period_from', 'clawback_settlements.pay_period_from')
                        ->whereColumn('w_p_f.pay_period_to', 'clawback_settlements.pay_period_to')
                        ->where('w_p_f.closed_status', '=', 1)
                        ->whereRaw('DATEDIFF(clawback_settlements.pay_period_to, clawback_settlements.pay_period_from) <= 7');
                })
                    ->orWhereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                            ->from('monthly_pay_frequencies as m_p_f')
                            ->whereColumn('m_p_f.pay_period_from', 'clawback_settlements.pay_period_from')
                            ->whereColumn('m_p_f.pay_period_to', 'clawback_settlements.pay_period_to')
                            ->where('m_p_f.closed_status', '=', 1)
                            ->whereRaw('DATEDIFF(clawback_settlements.pay_period_to, clawback_settlements.pay_period_from) > 7');
                    });
            });
        if ($userId != 'all') {
            $moveToReconDataQuery->where('user_id', $userId);
        }

        $paidMoveToReconClawbackAmount = ReconClawbackHistoryLock::where('move_from_payroll', '1')->where('status', 'payroll');
        if ($userId != 'all') {
            $paidMoveToReconClawbackAmount->where('user_id', $userId)->whereIn('pid', $pids);
        }

        $totalMoveToReconAmount = -1 * $moveToReconDataQuery->sum('clawback_amount');
        $paidMoveToReconAmount = -1 * $paidMoveToReconClawbackAmount->sum('total_amount');
        $unpaidMoveToReconAmount = -1 * $totalMoveToReconAmount - $paidMoveToReconAmount;

        $totalClawbackAmount = $totalReconClawbackAmount + $totalMoveToReconAmount;
        $totalPaidClawbackAmount = $paidReconClawbackAmount + $paidMoveToReconAmount;
        $totalUnPaidClawbackAmount = $unpaidReconClawbackAmount + $unpaidMoveToReconAmount;

        return [
            'type' => 'Clawback',
            'paid_amount' => floatval($totalPaidClawbackAmount),
            'in_recon_amount' => floatval($totalUnPaidClawbackAmount),
            'total_amount' => floatval($totalClawbackAmount),
        ];
    }

    private function getTotalAdjustmentNew($userId, $pids)
    {
        $totalReconAdjustmentQuery = ReconAdjustment::query();

        if ($userId != 'all') {
            $totalReconAdjustmentQuery->where('user_id', $userId)->whereIn('pid', $pids);
        }
        $totalAdjustmentAmount = $totalReconAdjustmentQuery->sum('adjustment_amount');
        $paidAdjustmentAmount = ReconAdjustment::where('payroll_status', 'payroll')->sum('adjustment_amount');
        $unpaidAdjustmentAmount = $totalAdjustmentAmount - $paidAdjustmentAmount;

        return [
            'type' => 'Adjustments',
            'paid_amount' => $paidAdjustmentAmount,
            'in_recon_amount' => $unpaidAdjustmentAmount,
            'total_amount' => $totalAdjustmentAmount,
        ];
    }
}
