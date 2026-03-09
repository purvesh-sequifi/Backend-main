<?php

namespace App\Http\Controllers\API\V2\ReconViewReports;

use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\Locations;
use App\Models\PayrollDeductions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationAdjustmentDetails;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconDeductionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOverrides;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ViewReportController extends Controller
{
    protected $isSuperAdmin;

    protected $userData;

    protected const API_NAME = 'api name';

    public function __construct()
    {
        $this->middleware('auth:api');
        // Middleware closure to defer the execution
        $this->middleware(function ($request, $next) {
            $this->isSuperAdmin = Auth::user()->is_super_admin == 1 ? true : false;
            $this->userData = Auth::user();

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $validate = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'year' => [
                'required',
                'digits:4',
                'integer',
                'between:2000,'.Carbon::now()->year,
            ],
        ]);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $requestData = $request->all();
        $topHeader = $this->topHeaderBar($request);
        $reconGraph = $this->reconGraph($request);
        $commissionCard = $this->cardDetails($request);

        return response()->json([
            'api_name' => 'view-report-api',
            'data' => [
                'top_header' => $topHeader,
                'recon_graph' => $reconGraph,
                'commission_card' => $commissionCard,
                'user_data' => $this->userData,
            ],
            'status' => true,
        ]);
    }

    public function topHeaderBar($request)
    {
        $userId = $request->user_id;
        $year = $request->year;

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');
        if ($userId) {
            // $totalWithHeldAmount = ReconCommissionHistory::where("user_id", $userId)->whereIn("pid", $pids)->where(['payroll_execute_status'=>0])->whereNotIn("status", ["finalize"])->sum('paid_amount');
            // if($totalWithHeldAmount === 0){
            //     $unPaidAmounts = UserCommission::whereIn("pid", $pids)->where("user_id", $userId)->where(['settlement_type'=>'reconciliation','status'=>3,'recon_status'=>1,'is_move_to_recon'=>0])->sum('amount');
            //     $pidstatusbias = UserCommission::whereIn("pid", $pids)->where("user_id", $userId)->where(['settlement_type'=>'reconciliation','status'=>3,'recon_status'=>2])->pluck('pid');
            //     $pidstatusbiass = UserCommission::whereIn("pid", $pids)->where("user_id", $userId)->where(['settlement_type'=>'reconciliation','status'=>3,'recon_status'=>2])->sum('amount');
            //     $reconunPaidAmount = ReconCommissionHistory::where("user_id", $userId)->whereIn("pid", $pidstatusbias)->whereNotIn("status", ["finalize"])->sum('paid_amount');
            //     $totalWithHeldAmount = $unPaidAmounts + ($pidstatusbiass - $reconunPaidAmount);
            // }else{
            //     $reconunPaidAmount = ReconCommissionHistory::where("user_id", $userId)->whereIn("pid", $pids)->where(['payroll_execute_status'=>0])->whereNotIn("status", ["finalize"])->sum('paid_amount');
            //     $unPaidAmountrecon = UserCommission::whereIn("pid", $pids)->where("user_id", $userId)->where(['settlement_type'=>'reconciliation','status'=>3,'recon_status'=>1,'is_move_to_recon'=>0])->sum('amount');
            //     $totalWithHeldAmount = $reconunPaidAmount + $unPaidAmountrecon;
            // }

            $totalEarnAmount = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->sum('amount');
            $totalduringm2 = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
            $paidrcon = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid_amount');
            $totalPaidCommissionAmount = $totalduringm2 + $paidrcon;
            $totalWithHeldAmount = $totalEarnAmount - $totalPaidCommissionAmount;

            $totalAmountforpid = UserOverrides::whereIn('pid', $pids)->where('user_id', $userId)->sum('amount');
            $totalAmountrecuterpid = UserOverrides::whereNotIn('pid', $pids)->where('user_id', $userId)->sum('amount');
            $totalAmount = $totalAmountforpid + $totalAmountrecuterpid;
            $PaidAmount = ReconOverrideHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', '=', 3)->sum('paid');
            $PaidAmountforrecon = ReconOverrideHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->sum('paid');
            $totalOverrideAmount = $totalAmount - $PaidAmountforrecon;

            $totalClawbackAmount = ClawbackSettlement::where('user_id', $userId)->where('recon_status', 1)->where('status', 3)->sum('clawback_amount');
            $totalAdjustmentAmount = ReconAdjustment::where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('payroll_status', ['finalize'])->where('payroll_execute_status', '!=', 3)->sum('adjustment_amount');
            // $totalDeduction = ReconciliationFinalizeHistory::where("user_id", $userId)->whereNotIn("status", ["finalize"])->where("payroll_execute_status",'!=',3)->sum("deductions") ?? 0;
            $totalDeduction = ReconDeductionHistory::where('user_id', $userId)->where('payroll_executed_status', '!=', 3)->sum('total');

            $totalAdjustment = $totalAdjustmentAmount - $totalDeduction;
            // if($totalDeduction === 0){
            //     $unpaidAdjustmentAmount = ReconAdjustment::whereNotIn("payroll_status", ["finalize"])->where("user_id", $userId)->where("payroll_execute_status", "!=", 3)->whereIn("pid", $pids)->sum("adjustment_amount") ?? 0 ;
            //     $totalAdjustment = ReconciliationFinalizeHistory::whereIn("pid", $pids)->where("user_id", $userId)->whereNotIn("status", ["finalize"])->where("payroll_execute_status", "!=", 3)->sum("deductions") ?? 0;
            //     $totalAdjustment = $unpaidAdjustmentAmount + $totalAdjustment;
            // }
        }

        return [
            'commission_withheld' => $totalWithHeldAmount,
            'override_withheld' => $totalOverrideAmount,
            'clawback' => -1 * $totalClawbackAmount,
            'adjustment' => $totalAdjustment,
            'outstanding_recon_values' => $totalWithHeldAmount + $totalOverrideAmount - $totalClawbackAmount + $totalAdjustment,
        ];
    }

    public function moveToReconCommissionData()
    {
        $userCommission = DB::table('user_commission as u_c_t');
        if ($this->userData?->positionDetail?->payFrequency?->frequencyType?->name == 'Weekly') {
            $userCommission->join('weekly_pay_frequencies as w_p_f_t', function ($join) {
                $join->on('u_c_t.pay_period_from', 'w_p_f_t.pay_period_from')
                    ->on('u_c_t.pay_period_to', 'w_p_f_t.pay_period_to')
                    ->where('w_p_f_t.closed_status', 1);
            });
        } elseif ($this->userData?->positionDetail?->payFrequency?->frequencyType?->name == 'Monthly') {
            $userCommission->join('monthly_pay_frequencies as w_p_f_t', function ($join) {
                $join->on('u_c_t.pay_period_from', 'w_p_f_t.pay_period_from')
                    ->on('u_c_t.pay_period_to', 'w_p_f_t.pay_period_to')
                    ->where('w_p_f_t.closed_status', 1);
            });
        }
        if (! $this->isSuperAdmin) {
            $userCommission->where('user_id', $this->userData->id);
        }

        return $userCommission->where('status', 6)->where('is_move_to_recon', 1)->sum('amount');
    }

    public function moveToReconOverrideData()
    {
        $userCommission = DB::table('clawback_settlements as u_o_r_t');
        if ($this->userData?->positionDetail?->payFrequency?->frequencyType?->name == 'Weekly') {
            $userCommission->join('weekly_pay_frequencies as w_p_f_t', function ($join) {
                $join->on('u_o_r_t.pay_period_from', 'w_p_f_t.pay_period_from')
                    ->on('u_o_r_t.pay_period_to', 'w_p_f_t.pay_period_to')
                    ->where('w_p_f_t.closed_status', 1);
            });
        } elseif ($this->userData?->positionDetail?->payFrequency?->frequencyType?->name == 'Monthly') {
            $userCommission->join('monthly_pay_frequencies as w_p_f_t', function ($join) {
                $join->on('u_o_r_t.pay_period_from', 'w_p_f_t.pay_period_from')
                    ->on('u_o_r_t.pay_period_to', 'w_p_f_t.pay_period_to')
                    ->where('w_p_f_t.closed_status', 1);
            });
        }
        if (! $this->isSuperAdmin) {
            $userCommission->where('user_id', $this->userData->id);
        }

        return $userCommission->where('status', 6)->where('is_move_to_recon', 1)->sum('clawback_amount');
    }

    public function moveToReconClawbackData()
    {
        $userCommission = DB::table('user_overrides as u_o_r_t');
        if ($this->userData->positionDetail->payFrequency?->frequencyType->name == 'Weekly') {
            $userCommission->join('weekly_pay_frequencies as w_p_f_t', function ($join) {
                $join->on('u_o_r_t.pay_period_from', 'w_p_f_t.pay_period_from')
                    ->on('u_o_r_t.pay_period_to', 'w_p_f_t.pay_period_to')
                    ->where('w_p_f_t.closed_status', 1);
            });
        } elseif ($this->userData?->positionDetail?->payFrequency?->frequencyType?->name == 'Monthly') {
            $userCommission->join('monthly_pay_frequencies as w_p_f_t', function ($join) {
                $join->on('u_o_r_t.pay_period_from', 'w_p_f_t.pay_period_from')
                    ->on('u_o_r_t.pay_period_to', 'w_p_f_t.pay_period_to')
                    ->where('w_p_f_t.closed_status', 1);
            });
        }
        if (! $this->isSuperAdmin) {
            $userCommission->where('user_id', $this->userData->id);
        }

        return $userCommission->where('status', 6)->where('is_move_to_recon', 1)->sum('amount');
    }

    public function reconGraph($request)
    {

        $data = $this->topHeaderBar($request);
        $userId = $request->user_id;
        $year = $request->year;

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        $result['account']['total_account'] = count($pids);
        $result['account']['commission'] = $data['commission_withheld'];
        $result['account']['overrides'] = $data['override_withheld'];
        $result['account']['adjustments'] = $data['adjustment'];
        $result['account']['clawback'] = $data['clawback'];

        return $result;
    }

    public function cardDetails($request)
    {
        $userId = $request->user_id;
        $year = $request->year;

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        $finalizeHistoryData = ReconciliationFinalizeHistory::query();
        if (! $this->isSuperAdmin) {
            $finalizeHistoryData->where('user_id', $this->userData->id);
        }

        $userCommissionData = UserCommission::whereIn('pid', $finalizeHistoryData->pluck('pid')->toArray());
        if (! $this->isSuperAdmin) {
            $userCommissionData->where('user_id', $this->userData->id);
        }
        /* commission card data */
        $totalEarnAmount = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->sum('amount');
        $totalduringm2 = UserCommission::where('user_id', $userId)->whereIn('pid', $pids)->where(['settlement_type' => 'during_m2'])->sum('amount');
        $paidrcon = ReconCommissionHistory::where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid_amount');
        $totalPaidCommissionAmount = $totalduringm2 + $paidrcon;

        /* clawback amount */
        $totalClawbackAmount = ReconClawbackHistory::where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->sum('paid_amount') ?? 0;

        $response['commissions'] = [
            'user_id' => $this->userData->id,
            'total_earned' => $totalEarnAmount,
            'total_paid' => $totalPaidCommissionAmount,
            'total_due' => $totalEarnAmount - $totalPaidCommissionAmount,
            'clawback_amount' => (-1 * $totalClawbackAmount),
            'clawback_account' => $finalizeHistoryData->where('clawback', '!=', null)->count(),
        ];

        /* recon override calculate */
        $totalAmountforpid = UserOverrides::whereIn('pid', $pids)->where('user_id', $userId)->sum('amount');
        $totalAmountrecuterpid = UserOverrides::whereNotIn('pid', $pids)->where('user_id', $userId)->sum('amount');
        $totalReconOverrideAmount = $totalAmountforpid + $totalAmountrecuterpid;

        $totalPaidOverrideAmount = ReconOverrideHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', '=', 3)->sum('paid');

        $PaidAmountforrecon = ReconOverrideHistory::whereIn('pid', $pids)->where('user_id', $userId)->whereNotIn('status', ['finalize'])->sum('paid');
        $totalUnpaidOverrideAmount = $totalReconOverrideAmount - $PaidAmountforrecon;
        // $totalUnpaidOverrideAmount = ReconOverrideHistory::where("user_id", $userId)->whereIn("pid", $pids)->whereNotIn("status", ["finalize"])->where("payroll_execute_status", "!=", 3)->sum("paid") ?? 0;
        $officeOverrideAmount = ReconOverrideHistory::where('type', 'Office')->where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', 3)->sum('paid') ?? 0;
        $indirectOverrideAmount = ReconOverrideHistory::where('type', 'Indirect')->where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', 3)->sum('paid') ?? 0;
        $directOverrideAmount = ReconOverrideHistory::where('type', 'Direct')->where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', 3)->sum('paid') ?? 0;
        $stackOverrideAmount = ReconOverrideHistory::where('type', 'Stack')->where('user_id', $userId)->whereIn('pid', $pids)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', 3)->sum('paid') ?? 0;

        $response['overrides'] = [
            'user_id' => $this->userData->id,
            'total_earned' => $totalReconOverrideAmount,
            'office' => $officeOverrideAmount,
            'direct' => $directOverrideAmount,
            'indirect' => $indirectOverrideAmount,
            'stack' => $stackOverrideAmount,
            'total_paid' => $totalPaidOverrideAmount,
            'total_due' => $totalUnpaidOverrideAmount,
        ];

        /* adjustment and deduction */
        $data = [];
        $data['adjustment'] = [];
        $data['deduction'] = [];
        $adjustmantDetails = ReconAdjustment::where('user_id', $userId)->whereIn('pid', $pids)->get();
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
        }

        $deduction = ReconDeductionHistory::where('user_id', $userId)->get();

        $unpaidAdjustmentAmount = ReconAdjustment::whereNotIn('payroll_status', ['finalize'])->where('user_id', $userId)->where('payroll_execute_status', '!=', 3)->whereIn('pid', $pids)->sum('adjustment_amount') ?? 0;
        $unpaidDeduction = ReconDeductionHistory::where('user_id', $userId)->where('payroll_executed_status', '!=', 3)->sum('total');
        // $totalDeduction = ReconciliationFinalizeHistory::whereIn("pid", $pids)->where("user_id", $userId)->whereNotIn("status", ["finalize"])->where("payroll_execute_status", "!=", 3)->sum("deductions") ?? 0;
        $totalDue = $unpaidAdjustmentAmount - $unpaidDeduction;

        $deductionAmount = 0;
        if (count($deduction) > 0) {
            foreach ($deduction as $key => $val) {

                $deductionAmount += $val->total;

                $costCenterName = $val->costcenter->name;

                if (! isset($data['deduction'][$costCenterName])) {
                    $data['deduction'][$costCenterName] = 0;
                }

                $data['deduction'][$costCenterName] += (-1 * $val->total);
            }

            $data['deduction']['total_deduction'] = round((-1 * $deductionAmount), 2);
        }
        // $adjustmentDetails = ReconciliationAdjustmentDetails::whereIn("pid", $finalizeHistoryData->pluck("pid")->toArray());
        // $deduction = PayrollDeductions::with('costcenter');
        // if (!$this->isSuperAdmin) {
        //     $adjustmentDetails->where("user_id", $this->userData->id);
        //     $deduction->where("user_id", $this->userData->id);
        // }
        // $data = [];
        // $data['adjustment'] = [];
        // $data['deduction'] = [];
        // if (count($adjustmentDetails->get()) > 0) {
        //     $subTotalAdj = 0;
        //     foreach ($adjustmentDetails->get() as $key => $val) {
        //         $adjType = $val->adjustment_type;

        //         if (!isset($data['adjustment'][$adjType])) {
        //             $data['adjustment'][$adjType] = 0;
        //         }

        //         $data['adjustment'][$adjType] += $val->amount;
        //         $subTotalAdj += $val->amount;
        //     }

        //     $data['adjustment']['total_adjustment'] = $subTotalAdj;
        // }

        // $deductionAmount = 0;
        // $deductionOutstanding = 0;
        // if (count($deduction->get()) > 0) {

        //     $deductionAmount = $deduction->sum('total');
        //     $deductionOutstanding = $deduction->sum('outstanding');
        //     foreach ($deduction as $key => $val) {

        //         $costCenterName = $val->costcenter->name;

        //         if (!isset($data['deduction'][$costCenterName])) {
        //             $data['deduction'][$costCenterName] = 0;
        //         }

        //         $data['deduction'][$costCenterName] += $val->total;
        //     }

        //     $data['deduction']['total_deduction'] = round($deductionAmount, 2);
        // }

        $response['adjustment_deduction'] = $data;
        $response['adjustment_deduction']['total_due'] = round($totalDue, 2);

        return $response;
    }

    public function clawbackReportList()
    {
        $userId = isset($request->user_id) ? $request->user_id : $this->userData->id;
        $year = isset($request->year) ? $request->year : date('Y');

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        $result = ReconClawbackHistory::with('user', 'salesDetail')->whereIn('pid', $pids)
            ->where('user_id', $userId)
            ->whereNotIn('status', ['finalize'])
            ->get();

        // Step 2: Process and transform data
        if (empty($result)) {
            $data = $result->map(function ($item) {
                // Fetch redline data
                $rep_redline = $this->calculateRepRedline($item->user_id, $item->salesDetail->customer_state);

                // Fetch state ID
                $state_code = Locations::with('State')
                    ->where('general_code', '=', $item->salesDetail->customer_state)
                    ->value('state_id');

                $paidAmount = 0;
                $unpaidAmount = 0;
                if ($item->payroll_execute_status == 3) {
                    $paidAmount = $item->paid_amount;
                } else {
                    $unpaidAmount = $item->paid_amount;
                }

                return [
                    'id' => $item->user_id,
                    'pid' => $item->pid,
                    'customer_name' => $item->salesDetail->customer_name,
                    'first_name' => $item->user->first_name,
                    'last_name' => $item->user->last_name,
                    'state_id' => $state_code,
                    'state' => $item->salesDetail->customer_state,
                    'rep_redline' => $rep_redline,
                    'kw' => $item->salesDetail->kw,
                    'net_epc' => $item->salesDetail->net_epc,
                    'adders' => null, // Assume no data for adders, update if necessary
                    'date' => $item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d') : null,
                    'total_clawback' => (-1) * $item->clawback_amount ?? 0.00, // Set based on your logic
                    'previous_paid' => (-1) * $paidAmount, // Set based on your logic
                    'due_amount' => (-1) * $unpaidAmount, // Set based on your logic
                    'type' => 'Clawback',
                ];
            });
        } else {

            $result = ClawbackSettlement::with('user', 'salesDetail')->whereIn('pid', $pids)
                ->where('user_id', $userId)->get();

            $data = $result->map(function ($item) {
                // Fetch redline data
                $rep_redline = $this->calculateRepRedline($item->user_id, $item->salesDetail->customer_state);

                // Fetch state ID
                $state_code = Locations::with('State')
                    ->where('general_code', '=', $item->salesDetail->customer_state)
                    ->value('state_id');

                $paidAmount = 0;
                $unpaidAmount = 0;
                if ($item->status == 3 && $item->recon_status == 3) {
                    $paidAmount = $item->clawback_amount;
                } else {
                    $unpaidAmount = $item->clawback_amount;
                }
                $userCommissionPercentage = 0;
                $userCommissiontype = null;

                $commissionHistory = UserCommissionHistory::where('user_id', $item->user_id)->where('self_gen_user', 0)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $userCommissionPercentage = $commissionHistory->commission;
                    $userCommissiontype = $commissionHistory->commission_type;
                }

                $state_name = State::find($item->salesDetail->state_id);

                return [
                    'id' => $item->user_id,
                    'pid' => $item->pid,
                    'customer_name' => $item->salesDetail->customer_name,
                    'first_name' => $item->user->first_name,
                    'last_name' => $item->user->last_name,
                    'state_id' => $state_code,
                    'state' => $state_name->state_code,
                    'rep_redline' => $rep_redline,
                    'kw' => $item->salesDetail->kw,
                    'net_epc' => $item->salesDetail->net_epc,
                    'adders' => null, // Assume no data for adders, update if necessary
                    'date' => $item->created_at ? Carbon::parse($item->created_at)->format('Y-m-d') : null,
                    'total_clawback' => (-1) * $item->clawback_amount ?? 0.00, // Set based on your logic
                    'previous_paid' => (-1) * $paidAmount, // Set based on your logic
                    'due_amount' => (-1) * $unpaidAmount, // Set based on your logic
                    'type' => 'Clawback',
                    'user_commission' => $userCommissionPercentage,
                    'gross_account_value' => $item->salesDetail->gross_account_value,
                    'user_commission_type' => $userCommissiontype,
                ];
            });
        }

        $total = array_reduce($data->toArray(), function ($carry, $item) {
            $carry['total_amount'] += $item['total_clawback'];
            $carry['total_paid_previously'] += $item['previous_paid'];
            $carry['remaining_in_recon'] += $item['due_amount'];

            return $carry;
        }, ['total_amount' => 0, 'total_paid_previously' => 0, 'remaining_in_recon' => 0]);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Clawback By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'total' => $total,

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

    public function commissionReportList_old()
    {
        $userId = $this->userData->id;
        $year = isset($request->year) ? $request->year : date('Y');

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        $salesData = ReconCommissionHistory::with('salesDetail')->whereIn('pid', $pids)
            ->where('user_id', $userId)
            ->whereNotIn('status', ['finalize'])
            ->get();
        if (empty($salesData)) {
            $data = $salesData->transform(function ($result) use ($userId) {
                $paidAmount = 0;
                $unpaidAmount = 0;
                if ($result->payroll_execute_status == 3) {
                    $paidAmount = $result->paid_amount;
                } else {
                    $unpaidAmount = $result->paid_amount;
                }

                return [
                    'pid' => $result->pid,
                    'customer' => $result->salesDetail->customer_name,
                    'state' => strtoupper($result->salesDetail->customer_state),
                    'rep_redline' => User::find($userId)->redline,
                    'kw' => $result->salesDetail->kw,
                    'net_epc' => $result->salesDetail->net_epc,
                    'total_commission' => $result->total_amount,
                    'previous_paid' => $paidAmount,
                    'payout_recon' => $result->payout,
                    'remaining_recon' => $unpaidAmount,
                    'm2_date' => $result->salesDetail->m2_date,
                    'customer_signoff' => $result->salesDetail->customer_signoff,
                ];
            });
        } else {
            $salesData = UserCommission::with('salesDetail')->whereIn('pid', $pids)->where('user_id', $userId)->get();
            $data = $salesData->transform(function ($result) use ($userId) {
                $paidAmount = 0;
                $unpaidAmount = 0;

                // if($result->recon_status == 3){
                //     $paidAmount = $result->paid_amount;
                // }else{
                //     $unpaidAmount = $result->amount;
                // }

                // if(($result->recon_status == 3 && $result->status == 3)){
                //     $paidAmount = $result->amount;
                // }
                //   if($result->settlement_type == 'during_m2'){
                //     if(($result->recon_status == 1 && $result->status == 3)){
                //         $paidAmount = $result->amount;
                //     }else{
                //         $unpaidAmount = $result->amount;
                //     }
                //  }
                $typedata = ReconCommissionHistory::where('user_id', $userId)->where('pid', $result->pid)->first();
                $typecomm = @$typedata->type;

                if ($result->settlement_type == 'reconciliation') {
                    $reconunPaidAmount = ReconCommissionHistory::where('user_id', $userId)->where('pid', $result->pid)->whereNotIn('status', ['finalize'])->sum('paid_amount');
                    $unpaidAmount = $result->amount - $reconunPaidAmount;
                }

                if ($result->settlement_type == 'during_m2' && $result->status == 3 && $result->recon_status == 1) {
                    // $reconunPaidAmount = ReconCommissionHistory::where("user_id", $userId)->where("pid", $result->pid)->whereNotIn("status", ["finalize"])->sum('paid_amount');
                    $paidAmount = $result->amount;
                }
                if ($result->settlement_type == 'reconciliation' && $result->status == 3 && $result->recon_status == 3) {
                    // $reconunPaidAmount = ReconCommissionHistory::where("user_id", $userId)->where("pid", $result->pid)->whereNotIn("status", ["finalize"])->sum('paid_amount');
                    $paidAmount += $result->amount;
                }

                if ($result->settlement_type == 'reconciliation' && $result->status == 3 && $result->recon_status == 2) {
                    $reconPaidAmount = ReconCommissionHistory::where('user_id', $userId)->where('pid', $result->pid)->where(['payroll_execute_status' => 3])->whereNotIn('status', ['finalize'])->sum('paid_amount');
                    $paidAmount += $reconPaidAmount;
                }

                $userCommissionPercentage = 0;
                $userCommissiontype = null;
                $commissionHistory = UserCommissionHistory::where('user_id', $result->user_id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $result->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $userCommissionPercentage = $commissionHistory->commission;
                    $userCommissiontype = $commissionHistory->commission_type;
                }

                $state_name = State::find($result->salesDetail->state_id);

                return [
                    'pid' => $result->pid,
                    'customer' => $result->salesDetail->customer_name,
                    'state' => strtoupper($state_name->state_code),
                    'rep_redline' => User::find($userId)->redline,
                    'kw' => $result->salesDetail->kw,
                    'net_epc' => $result->salesDetail->net_epc,
                    'total_commission' => $result->amount,
                    'previous_paid' => $paidAmount,
                    'payout_recon' => $result->payout,
                    'remaining_recon' => $unpaidAmount,
                    'm2_date' => $result->salesDetail->m2_date,
                    'customer_signoff' => $result->salesDetail->customer_signoff,
                    'user_commission' => $userCommissionPercentage,
                    'gross_account_value' => $result->salesDetail->gross_account_value,
                    'user_commission_type' => $userCommissiontype,
                    // 'type' => ($typecomm == 'M1' || $typecomm == 'm1') ? 'Upfront' : $typecomm,
                    'type' => ($typecomm == 'M1' || $typecomm == 'm1') ? 'Upfront' : 'Commission',
                ];
            });

        }
        $groupedData = $data->groupBy('pid')->map(function ($group) {
            return [
                'pid' => $group->first()['pid'],
                'total_commission' => $group->sum('total_commission'),
                'remaining_recon' => $group->sum('remaining_recon'),
                'previous_paid' => $group->sum('previous_paid'),
                'customer' => $group->first()['customer'],
                'state' => $group->first()['state'],
                'user_commission' => $group->first()['user_commission'],
                'user_commission_type' => $group->first()['user_commission_type'],
                'gross_account_value' => $group->first()['gross_account_value'],
                'm2_date' => $group->first()['m2_date'],
                'customer_signoff' => $group->first()['customer_signoff'],
                'type' => $group->first()['type'],
            ];
        });

        // Convert the grouped data to a plain array (removes the keys)
        $groupedDataArray = $groupedData->values()->toArray();

        // Now calculate the overall totals across all groups
        $total = array_reduce($groupedDataArray, function ($carry, $item) {
            $carry['total_amount'] += $item['total_commission'];
            $carry['total_paid_previously'] += $item['previous_paid'];
            $carry['remaining_recon'] += $item['remaining_recon'];

            return $carry;
        }, ['total_amount' => 0, 'total_paid_previously' => 0, 'remaining_recon' => 0]);

        return [
            'ApiName' => 'view-commission-reports',
            'status' => true,
            'data' => $groupedDataArray,
            'total' => $total,
        ];
    }

    public function commissionReportList()
    {
        $userId = $this->userData->id;
        $year = isset($request->year) ? $request->year : date('Y');

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        if (! empty($pids)) {
            foreach ($pids as $key => $pid) {
                $result = ReconCommissionHistory::with('salesDetail')->where('pid', $pid)->where('user_id', $userId)->first();
                if ($result) {
                    $customerName = $result->salesDetail->customer_name;
                    $customerState = $result->salesDetail->customer_state;
                    $kw = $result->salesDetail->kw;
                    $netEpc = $result->salesDetail->net_epc;
                    $customer_signoff = $result->salesDetail->customer_signoff;
                    $gross_account_value = $result->salesDetail->gross_account_value;
                } else {
                    $result = SalesMaster::where('pid', $pid)->first();
                    $customerName = $result->customer_name;
                    $customerState = $result->customer_state;
                    $kw = $result->kw;
                    $netEpc = $result->net_epc;
                    $customer_signoff = $result->customer_signoff;
                    $gross_account_value = $result->gross_account_value;
                }

                $finalDate = SaleProductMaster::select('id', 'pid', 'milestone_date')->whereNotNull('milestone_date')->where(['pid' => $result->pid, 'is_last_date' => 1])->first();
                $isIneligible = 1;
                $m2_date = null;
                if ($finalDate) {
                    $isIneligible = 0;
                    $m2_date = $finalDate->milestone_date;
                }
                $typecomm = @$result->type;
                $userCommissionPercentage = 0;
                $userCommissiontype = null;
                $commissionHistory = UserCommissionHistory::where('user_id', $result->user_id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $result->salesDetail->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $userCommissionPercentage = $commissionHistory->commission;
                    $userCommissiontype = $commissionHistory->commission_type;
                }

                $totalEarnAmount = UserCommission::where('user_id', $userId)->where('pid', $result->pid)->sum('amount');
                $totalduringm2 = UserCommission::where('user_id', $userId)->where('pid', $result->pid)->where(['settlement_type' => 'during_m2'])->sum('amount');
                $paidrcon = ReconCommissionHistory::where('user_id', $userId)->where('pid', $result->pid)->whereNotIn('status', ['finalize'])->sum('paid_amount');
                $totalPaidCommissionAmount = $totalduringm2 + $paidrcon;

                $data[] = [
                    'pid' => $result->pid,
                    'customer' => $customerName,
                    'state' => strtoupper($customerState),
                    'rep_redline' => User::find($userId)->redline,
                    'kw' => $kw,
                    'net_epc' => $netEpc,
                    'total_commission' => $totalEarnAmount,
                    'previous_paid' => $totalPaidCommissionAmount,
                    'remaining_recon' => $totalEarnAmount - $totalPaidCommissionAmount,
                    'payout_recon' => $result->payout,
                    'm2_date' => $m2_date,
                    'customer_signoff' => $customer_signoff,
                    'gross_account_value' => $gross_account_value,
                    'user_commission' => $userCommissionPercentage,
                    'user_commission_type' => $userCommissiontype,
                    'type' => 'Commission',
                    'is_ineligible' => $isIneligible,
                ];

            }
        }

        // $groupedData = $data->groupBy('pid')->map(function ($group) {
        //     return [
        //         'pid' => $group->first()['pid'],
        //         'total_commission' => $group->sum('total_commission'),
        //         'remaining_recon' => $group->sum('remaining_recon'),
        //         'previous_paid' => $group->sum('previous_paid'),
        //         'customer' => $group->first()['customer'],
        //         'state' => $group->first()['state'],
        //         'user_commission' => $group->first()['user_commission'],
        //         'user_commission_type' => $group->first()['user_commission_type'],
        //         'gross_account_value' => $group->first()['gross_account_value'],
        //         'm2_date' => $group->first()['m2_date'],
        //         'customer_signoff' => $group->first()['customer_signoff'],
        //         'type' => $group->first()['type'],
        //     ];
        // });

        // $groupedDataArray = $groupedData->values()->toArray();
        $groupedDataArray = $data;

        // Now calculate the overall totals across all groups
        $total = array_reduce($groupedDataArray, function ($carry, $item) {
            $carry['total_amount'] += $item['total_commission'];
            $carry['total_paid_previously'] += $item['previous_paid'];
            $carry['remaining_recon'] += $item['remaining_recon'];

            return $carry;
        }, ['total_amount' => 0, 'total_paid_previously' => 0, 'remaining_recon' => 0]);

        return [
            'ApiName' => 'view-commission-reports',
            'status' => true,
            'data' => $groupedDataArray,
            'total' => $total,
        ];
    }

    public function overridesReportList(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $userId = $request->user_id;
        $year = isset($request->year) ? $request->year : date('Y');

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        /* $finalizeHistoryData = ReconciliationFinalizeHistory::where("user_id", $userId);
        $userOverRideData = UserOverrides::whereIn("pid", $finalizeHistoryData->pluck("pid")->toArray())
            ->where("user_id", $userId)->get(); */

        $userOverRideData = ReconOverrideHistory::whereIn('pid', $pids)
            ->where('user_id', $userId)
            ->whereNotIn('status', ['finalize'])
            ->get();

        if (! empty($userOverRideData)) {
            $overrideList = $userOverRideData->transform(function ($result) {
                $paidAmount = 0;
                $unpaidAmount = 0;
                if ($result->payroll_execute_status == 3) {
                    $paidAmount = $result->paid;
                } else {
                    $unpaidAmount = $result->paid;
                }
                // $payout = ReconciliationFinalizeHistory::where('pid', $result->pid)->where('user_id', $userId)->first();

                return [
                    'pid' => $result->pid,
                    'customer' => $result->salesDetail->customer_name,
                    'image' => isset($result->userpayrolloverride->first_name) ? $result->userpayrolloverride->first_name : null,
                    'override_over_image' => isset($result->userpayrolloverride->image) ? $result->userpayrolloverride->image : null,
                    'override_over_first_name' => isset($result->userpayrolloverride->first_name) ? $result->userpayrolloverride->first_name : null,
                    'override_over_last_name' => isset($result->userpayrolloverride->last_name) ? $result->userpayrolloverride->last_name : null,
                    'type' => $result->type,
                    'kw_installed' => $result->kw,
                    'override' => $result->override_amount.' '.$result->overrides_type,
                    'total_override' => $result->amount,
                    'payout' => $payout?->payout ?? 0,
                    'paid_total' => $result->total_amount,
                    'paid_previously' => $paidAmount,
                    // "remaining_in_recon" => $result->total_amount - $paidAmount,
                    'remaining_in_recon' => $unpaidAmount,

                ];
            });
        } else {
            $userOverRideData = UserOverrides::with('saledata')
                ->where('user_id', $userId)
                ->get();
            $overrideList = $userOverRideData->transform(function ($result) use ($userId) {
                $paidAmount = 0;
                $unpaidAmount = 0;
                // if($result->recon_status == 3){
                //     $paidAmount = $result->paid;
                // }else{
                //     $unpaidAmount = $result->paid;
                // }

                if ($result->recon_status == 3) {
                    $paidAmount = $result->amount;
                }
                if ($result->recon_status == 2) {
                    $paidAmounts = ReconOverrideHistory::where('pid', $result->pid)->where('user_id', $userId)->where('type', $result->type)->whereNotIn('status', ['finalize'])->where('payroll_execute_status', '=', 3)->sum('paid');
                    $paidAmount = $result->amount - $paidAmounts;
                }
                if ($result->recon_status == 1) {

                    $unpaidAmount = $result->amount;
                }
                if ($result->recon_status == 2) {
                    $unpaidAmounts = ReconOverrideHistory::where('pid', $result->pid)->where('user_id', $userId)->where('type', $result->type)->whereNotIn('status', ['finalize'])->sum('paid');
                    $unpaidAmount = $result->amount - $unpaidAmounts;
                }
                // $payout = ReconciliationFinalizeHistory::where('pid', $result->pid)->where('user_id', $userId)->first();

                return [
                    'pid' => $result->pid,
                    'customer' => $result->salesDetail->customer_name,
                    'image' => isset($result->userpayrolloverride->first_name) ? $result->userpayrolloverride->first_name : null,
                    'override_over_image' => isset($result->userpayrolloverride->image) ? $result->userpayrolloverride->image : null,
                    'override_over_first_name' => isset($result->userpayrolloverride->first_name) ? $result->userpayrolloverride->first_name : null,
                    'override_over_last_name' => isset($result->userpayrolloverride->last_name) ? $result->userpayrolloverride->last_name : null,
                    'type' => $result->type,
                    'kw_installed' => $result->kw,
                    'override' => $result->override_amount.' '.$result->overrides_type,
                    'total_override' => $result->amount,
                    'payout' => $payout?->payout ?? 0,
                    'paid_total' => $result->amount,
                    'paid_previously' => $paidAmount,
                    // "remaining_in_recon" => $result->total_amount - $paidAmount,
                    'remaining_in_recon' => $unpaidAmount,
                    'gross_account_value' => $result->saledata->gross_account_value,

                ];
            });
        }
        // return $overrideList;
        $total = array_reduce($overrideList->toArray(), function ($carry, $item) {
            $carry['total_earned'] += $item['paid_total'];  // Sum of all paid totals
            $carry['total_due'] += $item['remaining_in_recon'];  // Sum of all remaining amounts

            return $carry;
        }, ['total_earned' => 0, 'total_due' => 0]);

        // Now calculate total_paid_previously
        $total['total_paid_previously'] = $total['total_earned'] - $total['total_due'];

        // dd($total);
        $total['total_amount'] = array_sum($total);

        return response()->json([
            self::API_NAME => 'view-overrides-reports',
            'status' => true,
            // "data" => $response,
            'total' => $total,
            'override_list' => $overrideList,
        ]);
    }

    public function reconAdjustmentReportList(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required',
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

        $userId = $request->user_id;
        $year = isset($request->year) ? $request->year : date('Y');

        $pid = $this->getUserPids($year, $userId);
        $pids = collect($pid)->pluck('pid');

        $reconAdjustmentData = ReconAdjustment::whereIn('pid', $pids)
            ->where('user_id', $userId)
            // ->whereNotIn("payroll_status", ["finalize"])
            ->get();
        $reconAdjustmenttotal = ReconAdjustment::whereIn('pid', $pids)
            ->where('user_id', $userId)
            ->whereNotIn('payroll_status', ['finalize'])
            ->where(['payroll_execute_status' => 3])
            ->sum('adjustment_amount');
        $reconAdjustmentdue = ReconAdjustment::whereIn('pid', $pids)
            ->where('user_id', $userId)
        // ->whereNotIn("payroll_status", ["finalize"])
            ->where(['payroll_execute_status' => 0])
            ->sum('adjustment_amount');

        $finalData = [
            'commission' => [],
            'overrides' => [],
            'clawback' => [],
            'commission_total' => 0.00,
            'override_total' => 0.00,
            'clawback_total' => 0.00,
            'deduction_total' => 0.00,
            'adjustment_amount_total' => ($reconAdjustmenttotal) ? $reconAdjustmenttotal : 0.00,
            'adjustment_amount_due' => ($reconAdjustmentdue) ? $reconAdjustmentdue : 0.00,
            'paid_status' => '',
            'subTotalAdjustment' => 0,
        ];

        $reconAdjustmentData->transform(function ($result) use (&$finalData) {
            if ($result->payroll_status == 'payroll' && $result->payroll_execute_status == 3) {
                $paid_status = 1;
            } else {
                $paid_status = 0;
            }
            $s3_image = $result?->commentUser?->image ? s3_getTempUrl(config('app.domain_name').'/'.$result->user->image) : null;
            $data = [
                'pid' => $result->pid,
                'date' => $result->created_at->format('Y-m-d'),
                'comment' => $result->adjustment_comment,
                'type' => $result->adjustment_type,
                'amount' => $result->adjustment_amount,
                'adjust_by' => $result?->commentUser?->first_name.' '.$result?->commentUser?->last_name,
                'is_manager' => $result?->commentUser?->is_manager,
                'is_super_admin' => $result?->commentUser?->is_super_admin,
                'position_id' => $result?->commentUser?->position_id,
                'sub_position_id' => $result?->commentUser?->sub_position_id,
                'image_url' => $s3_image,
                'paid_status' => $paid_status,
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

            return $result;
        });

        $finalData['subTotalAdjustment'] = ($finalData['commission_total'] + $finalData['override_total'] + $finalData['clawback_total']);
        /* deduction data */
        // $deduction = PayrollDeductions::with('costcenter')->where([
        //     'user_id' => $request->user_id,
        //     "is_move_to_recon" => 1,
        //     "status" => 6,
        // ])->get();
        $deductionData = PayrollDeductions::with('costcenter')->where('user_id', $request->user_id)
            ->join('weekly_pay_frequencies as w_p_f', function ($query) {
                $query->on('w_p_f.pay_period_from', '=', 'payroll_deductions.pay_period_from')
                    ->on('w_p_f.pay_period_to', '=', 'payroll_deductions.pay_period_to')
                    ->where('w_p_f.closed_status', '=', 1);
            })
            ->where('is_move_to_recon', 1)
            ->where('status', 3)
            ->where('is_move_to_recon_paid', 1)
            ->get();

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
                ];
            })->all();
        })->all();

        $finalData['deduction_total'] = -1 * $deductionTotal;
        $finalData['deduction'] = $responseArray;

        return response()->json($finalData);
    }

    public function reconductionreports(Request $request): JsonResponse
    {

        $validate = Validator::make($request->all(), [
            'user_id' => ['required', function ($attribute, $value, $fail) {
                if ($value != 'all' && ! User::where('id', $value)->exists()) {
                    $fail('This user is not exists in our system.');
                }
            }],
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validate->errors(),
            ], 400);
        }

        $userId = $request->user_id;

        //    $reconFinalize = ReconciliationFinalizeHistory::where("user_id", $userId)
        //         ->where("finalize_id", $finalizeId)
        //         // ->whereDate("start_date", $startDate)
        //         // ->whereDate("end_date", $endDate)
        //         ->whereIn('status', ["payroll", "clawback"])
        //         ->first();
        $reconFinalize = ReconDeductionHistory::where('user_id', $userId)
            ->first();

        if ($reconFinalize && $reconFinalize->status == 'payroll') {
            $paid_status = 1;
        } else {
            $paid_status = 0;
        }

        $deduction = ReconDeductionHistory::with('costcenter')
            ->where('user_id', $userId)
       // ->where("finalize_id", $finalizeId)
        // ->whereDate("start_date", $startDate)
        // ->whereDate("end_date", $endDate)
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
                'ApiName' => 'outstanding report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => $total,
                'data' => $data,
                'paid_status' => $paid_status,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'outstanding report past reconciliation',
                'status' => true,
                'message' => 'Successfully.',
                'total' => [],
                'data' => [],
            ], 200);
        }
    }

    private function getUserPids($year, $userId)
    {
        if ($userId != 'all') {
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
}
