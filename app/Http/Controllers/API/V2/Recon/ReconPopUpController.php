<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Http\Controllers\Controller;
use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\MonthlyPayFrequency;
use App\Models\PayrollDeductions;
use App\Models\ReconAdjustment;
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
use App\Models\WeeklyPayFrequency;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReconPopUpController extends Controller
{
    public $isPestServer = false;

    public $isUpfront = false;

    const ERROR_MSG = 'Something went wrong.';

    const DATE_FORMATE = 'date_format:Y-m-d';

    const FINALIZE_VALIDATE = 'required|in:0,1';

    const RECON_USER_ACCOUNT_SUMMARY = 'Recon User Account Summary';

    const M2_UPDATE = 'm2 update';

    const MOVE_TO_RECON = ' | Move To Recon';

    public function __construct()
    {
        /* check server is pest or not */
        $companyProfile = CompanyProfile::first();
        $this->isPestServer = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
        $this->isUpfront = $companyProfile->deduct_any_available_reconciliation_upfront;
    }

    public function reconCommissionPopup(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date', self::DATE_FORMATE],
            'end_date' => ['required', 'date', self::DATE_FORMATE],
            'payout' => ['required', 'integer', 'min:1', 'max:100'],
            'is_finalize' => self::FINALIZE_VALIDATE,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        // Extract start and end dates from the request
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $payout = $request->payout;
        $search = $request->search;
        $userId = $id;
        $isFinalize = $request->is_finalize;
        // return $this->getReconCommissionData($userId, $startDate, $endDate, $search, $payout, $isFinalize);
        [$data, $subTotal, $inReconSubTotal, $commissionDueSubTotal] = $this->getReconCommissionData($userId, $startDate, $endDate, $search, $payout, $isFinalize);

        // Return the response with the transformed data and subtotal
        return response()->json([
            'ApiName' => 'Recon Commission Breakdown Api',
            'status' => true,
            'message' => 'Successfully.',
            'total_data' => count($data),
            'data' => $data,
            'subtotal' => $subTotal,
            'in_recon_subtotal' => $inReconSubTotal,
            'total_commission' => $commissionDueSubTotal,
        ]);
    }

    public function getReconCommissionData1($userId, $startDate, $endDate, $search, $payout, $isFinalize)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $isUpfront = $this->isUpfront;
        $userData = User::select('id', 'redline')->where('id', $userId)->first();
        $subTotal = 0;
        $inReconSubTotal = 0;
        $commissionDueSubTotal = 0;

        $sales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->whereHas('salesProductMasterDetails', function ($q) {
                $q->whereNotNull('milestone_date')->where('is_last_date', 1);
            })
            ->join('sale_master_process', 'sale_master_process.pid', 'sale_masters.pid')
            ->where(function ($subQuery) use ($userId) {
                $subQuery->where('sale_master_process.closer1_id', $userId)
                    ->orWhere('sale_master_process.closer2_id', $userId)
                    ->orWhere('sale_master_process.setter1_id', $userId)
                    ->orWhere('sale_master_process.setter2_id', $userId);
            })->when($search && ! empty($search), function ($q) use ($search) {
                $q->where('sale_masters.pid', 'LIKE', '%'.$search.'%')->orWhere('sale_masters.customer_name', 'LIKE', '%'.$search.'%');
            })->orderBy('sale_masters.pid', 'ASC')->get();

        $sales->transform(function ($result) use ($userData, $payout, $startDate, $endDate, $isFinalize, $isUpfront, &$subTotal, &$inReconSubTotal, &$commissionDueSubTotal) {
            $isEligible = false;
            if (! empty($result->salesProductMasterDetails->milestone_date) && $result->salesProductMasterDetails->milestone_date >= $startDate && $result->salesProductMasterDetails->milestone_date <= $endDate && empty($result->date_cancelled)) {
                $isEligible = true;
            }

            $inReconAmount = 0;
            $inReconPercentage = 0;
            $userCommissionPercentage = 0;
            $userCommissiontype = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $userData->id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $result->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $userCommissionPercentage = $commissionHistory->commission;
                $userCommissiontype = $commissionHistory->commission_type;
            }

            $commission = UserCommission::selectRaw("SUM(amount) as totalCommission, SUM(CASE WHEN settlement_type = 'reconciliation' THEN amount ELSE 0 END) as totalReconAmount")
                ->where(['user_id' => $userData->id, 'pid' => $result->pid, 'is_displayed' => '1'])->first();
            $totalCommission = $commission->totalCommission ?? 0;
            $totalReconAmount = $commission->totalReconAmount ?? 0;

            $totalPaidInRecon = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userData->id, 'is_deducted' => '0', 'is_displayed' => '1'])
                ->when($isFinalize, function ($q) {
                    $q->where('status', 'payroll');
                })->sum('paid_amount');
            $totalPaid = ($totalCommission - $totalReconAmount) + $totalPaidInRecon;

            $inReconPercentage = 0;
            if ($isEligible) {
                $abse = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userData->id, 'is_displayed' => '1'])
                    ->when($isFinalize, function ($q) {
                        $q->where('status', 'payroll');
                    })->sum('paid_amount');
                $remain = $totalReconAmount - $abse;
                $inReconPercentage = ($remain * ($payout / 100));

                $inReconAmount = $remain - $inReconPercentage;
                $inReconSubTotal += $inReconAmount;
                $commissionDueSubTotal += $totalCommission;
            } else {
                $inReconAmount = $totalReconAmount - $totalPaidInRecon;
            }

            $upfrontAmount = 0;
            if ($isUpfront == 1) {

                $upfrontAmount = UserCommission::where(['pid' => $result->pid, 'user_id' => $userData->id, 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->where('amount', '!=', 'reconciliation')->sum('amount') ?? 0;
                $paidUpfront = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userData->id, 'is_deducted' => '1', 'is_displayed' => '1'])->where('type', '!=', 'reconciliation')
                    ->when($isFinalize, function ($q) {
                        $q->where('status', 'payroll');
                    })->sum('paid_amount') ?? 0;
                $totalPaid = $totalPaid + $paidUpfront;
                $upfrontAmount = $upfrontAmount + $paidUpfront;
                if ($inReconPercentage) {
                    // $inReconPercentage = $inReconPercentage;
                    // $subTotal += $inReconPercentage - $upfrontAmount;
                    $m2 = SalesMaster::where(['pid' => $result->pid])->whereNotNull('m2_date')->first();
                    if (! empty($m2->pid)) {
                        $inReconPercentage = $inReconPercentage;
                        $subTotal += $inReconPercentage;
                    } else {
                        $inReconPercentage = $inReconPercentage - $upfrontAmount;
                        $subTotal += $inReconPercentage;
                    }
                    // $inReconPercentage = $inReconPercentage - $upfrontAmount;
                    // $subTotal += $inReconPercentage;

                } else {
                    if (! $isEligible) {
                        $inReconPercentage = $inReconPercentage + (0 - ($upfrontAmount + $totalPaidInRecon));
                        $subTotal += $inReconPercentage;
                    }
                }
            } else {
                $subTotal += $inReconPercentage;
            }

            /* GET RECON ADJUSTMENT DETAILS */
            if ($isFinalize == '0') {
                $reconAdjustment = $this->reconAdjustmentDetails($result->pid, $userData->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'commission', 'commission', $isFinalize);
            } else {
                $reconAdjustment = $this->reconAdjustmentDetailsonfinalize($result->pid, $userData->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'commission', 'commission', $isFinalize);
            }
            $state_name = State::find($result->state_id);

            return [
                'id' => 0,
                'pid' => $result->pid,
                'user_id' => $userData->id,
                'state_id' => ucfirst($result->state_id),
                'customer_name' => ucfirst($result->customer_name),
                'customer_state' => ucfirst($state_name->state_code),
                'rep_redline' => $userData->redline,
                'kw' => $result->kw,
                'net_epc' => $result->net_epc,
                'epc' => $result->epc,
                'adders' => $result->adders,
                'type' => ucfirst($reconCommissionData?->amount_type ?? '-'),
                'amount' => $totalCommission ?? 0,
                'paid' => $totalPaid,
                'in_recon_percentage' => $inReconPercentage,
                'in_recon' => $inReconAmount,
                'position_id' => $reconAdjustment['position_id'],
                'sub_position_id' => $reconAdjustment['sub_position_id'],
                'is_super_admin' => $reconAdjustment['is_super_admin'],
                'is_manager' => $reconAdjustment['is_manager'],
                'adjustment_amount' => $reconAdjustment['adjustment_amount'],
                'adjustment_comment' => $reconAdjustment['adjustment_comment'],
                'adjustment_by' => $reconAdjustment['adjustment_by'],
                'comment_status' => $reconAdjustment['comment_status'],
                'is_move_to_recon' => 0,
                'is_ineligible' => $isEligible ? 0 : 1,
                'is_upfront' => $isUpfront,
                'date_cancelled' => $result->date_cancelled,
                'gross_account_value' => $result->gross_account_value,
                'user_commission' => $userCommissionPercentage,
                'user_commission_type' => $userCommissiontype,
            ];
        });

        return [$sales, $subTotal, $inReconSubTotal, $commissionDueSubTotal];
    }

    public function getReconCommissionData($userId, $startDate, $endDate, $search, $payout, $isFinalize)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $isUpfront = $this->isUpfront;
        $userData = User::select('id', 'redline')->where('id', $userId)->first();
        $subTotal = 0;
        $inReconSubTotal = 0;
        $commissionDueSubTotal = 0;

        $sales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->join('sale_master_process', 'sale_master_process.pid', 'sale_masters.pid')
            ->where(function ($subQuery) use ($userId) {
                $subQuery->where('sale_master_process.closer1_id', $userId)
                    ->orWhere('sale_master_process.closer2_id', $userId)
                    ->orWhere('sale_master_process.setter1_id', $userId)
                    ->orWhere('sale_master_process.setter2_id', $userId);
            })->when($search && ! empty($search), function ($q) use ($search) {
                $q->where('sale_masters.pid', 'LIKE', '%'.$search.'%')->orWhere('sale_masters.customer_name', 'LIKE', '%'.$search.'%');
            })->orderBy('sale_masters.pid', 'ASC')->get();

        $sales->transform(function ($result) use ($userData, $payout, $startDate, $endDate, $isFinalize, $isUpfront, &$subTotal, &$inReconSubTotal, &$commissionDueSubTotal) {
            $isEligible = false;
            $finalDate = SaleProductMaster::select('id', 'pid', 'milestone_date')->where(['pid' => $result->pid, 'is_last_date' => 1])->first();
            if (! empty($finalDate->milestone_date) && $finalDate->milestone_date >= $startDate && $finalDate->milestone_date <= $endDate && empty($result->date_cancelled)) {
                $isEligible = true;
            }

            $inReconAmount = 0;
            $inReconPercentage = 0;
            $userCommissionPercentage = 0;
            $userCommissiontype = null;
            $commissionHistory = UserCommissionHistory::where('user_id', $userData->id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $result->customer_signoff)->orderBy('commission_effective_date', 'DESC')->first();
            if ($commissionHistory) {
                $userCommissionPercentage = $commissionHistory->commission;
                $userCommissiontype = $commissionHistory->commission_type;
            }

            $commission = UserCommission::selectRaw("SUM(amount) as totalCommission, SUM(CASE WHEN settlement_type = 'reconciliation' THEN amount ELSE 0 END) as totalReconAmount")
                ->where(['user_id' => $userData->id, 'pid' => $result->pid, 'is_displayed' => '1'])->first();
            $totalCommission = $commission->totalCommission ?? 0;
            $totalReconAmount = $commission->totalReconAmount ?? 0;

            $totalPaidInRecon = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userData->id, 'is_deducted' => '0', 'is_displayed' => '1'])
                ->when($isFinalize, function ($q) {
                    $q->where('status', 'payroll');
                })->sum('paid_amount');
            $totalPaid = ($totalCommission - $totalReconAmount) + $totalPaidInRecon;

            if ($isUpfront == 1) {

                $upfrontAmount = UserCommission::where(['pid' => $result->pid, 'user_id' => $userData->id, 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->where('amount', '!=', 'reconciliation')->sum('amount') ?? 0;
                $paidUpfront = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userData->id, 'is_deducted' => '1', 'is_displayed' => '1'])->where('type', '!=', 'reconciliation')
                    ->when($isFinalize, function ($q) {
                        $q->where('status', 'payroll');
                    })->sum('paid_amount') ?? 0;
                $upfrontAmount = $upfrontAmount + $paidUpfront;

                if ($isEligible) {
                    $abse = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userData->id, 'is_displayed' => '1'])
                        ->when($isFinalize, function ($q) {
                            $q->where('status', 'payroll');
                        })->sum('paid_amount');

                    $totalPaid = ($upfrontAmount + $totalPaidInRecon);

                    $remain = $totalReconAmount - $abse;
                    $inReconPayout = ($remain * ($payout / 100));
                    // $inReconPercentage = ($inReconPayout - $upfrontAmount);
                    $inReconPercentage = ($inReconPayout);

                    $inReconAmount = $remain - $inReconPercentage;
                    $inReconSubTotal += $inReconAmount;
                    $commissionDueSubTotal += $totalCommission;
                } else {
                    $totalPaid = ($upfrontAmount + $totalPaidInRecon);
                    $inReconPercentage = (0 - $totalPaid);
                    $inReconAmount = $totalReconAmount - $totalPaidInRecon;
                }

                $subTotal += $inReconPercentage;

            } else {

                if ($isEligible) {
                    $abse = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userData->id, 'is_displayed' => '1'])
                        ->when($isFinalize, function ($q) {
                            $q->where('status', 'payroll');
                        })->sum('paid_amount');
                    $remain = $totalReconAmount - $abse;
                    $inReconPercentage = ($remain * ($payout / 100));

                    $inReconAmount = $remain - $inReconPercentage;
                    $inReconSubTotal += $inReconAmount;
                    $commissionDueSubTotal += $totalCommission;
                    $subTotal += $inReconPercentage;
                } else {
                    $inReconAmount = $totalReconAmount - $totalPaidInRecon;
                }

            }

            /* GET RECON ADJUSTMENT DETAILS */
            if ($isFinalize == '0') {
                $reconAdjustment = $this->reconAdjustmentDetails($result->pid, $userData->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'commission', 'commission', $isFinalize);
            } else {
                $reconAdjustment = $this->reconAdjustmentDetailsonfinalize($result->pid, $userData->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'commission', 'commission', $isFinalize);
            }
            $state_name = State::find($result->state_id);

            return [
                'id' => 0,
                'pid' => $result->pid,
                'user_id' => $userData->id,
                'state_id' => ucfirst($result->state_id),
                'customer_name' => ucfirst($result->customer_name),
                'customer_state' => ucfirst($state_name->state_code),
                'rep_redline' => $userData->redline,
                'kw' => $result->kw,
                'net_epc' => $result->net_epc,
                'epc' => $result->epc,
                'adders' => $result->adders,
                'type' => ucfirst($reconCommissionData?->amount_type ?? '-'),
                'amount' => $totalCommission ?? 0,
                'paid' => $totalPaid,
                'in_recon_percentage' => $inReconPercentage,
                'in_recon' => $inReconAmount,
                'position_id' => $reconAdjustment['position_id'],
                'sub_position_id' => $reconAdjustment['sub_position_id'],
                'is_super_admin' => $reconAdjustment['is_super_admin'],
                'is_manager' => $reconAdjustment['is_manager'],
                'adjustment_amount' => $reconAdjustment['adjustment_amount'],
                'adjustment_comment' => $reconAdjustment['adjustment_comment'],
                'adjustment_by' => $reconAdjustment['adjustment_by'],
                'comment_status' => $reconAdjustment['comment_status'],
                'is_move_to_recon' => 0,
                'is_ineligible' => $isEligible ? 0 : 1,
                'is_upfront' => $isUpfront,
                'date_cancelled' => $result->date_cancelled,
                'gross_account_value' => $result->gross_account_value,
                'user_commission' => $userCommissionPercentage,
                'user_commission_type' => $userCommissiontype,
            ];
        });

        return [$sales, $subTotal, $inReconSubTotal, $commissionDueSubTotal];
    }

    public function reconOverridePop(Request $request, $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date', self::DATE_FORMATE],
            'end_date' => ['required', 'date', self::DATE_FORMATE],
            'payout' => ['required', 'integer', 'min:1', 'max:100'],
            'is_finalize' => self::FINALIZE_VALIDATE,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'ApiName' => 'PayRoll Reconciliation Overrides By Employee ID',
                'message' => $validator->errors()->first(),
            ]);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $payoutPercent = $request->payout;
        $search = $request->search;
        $isFinalize = $request->is_finalize;

        [$data, $subTotal, $inReconSubTotal] = $this->getReconOverRidesData($userId, $startDate, $endDate, $search, $payoutPercent, $isFinalize);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Overrides By Employee ID',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'sub_total' => $subTotal,
            'in_recon_subtotal' => $inReconSubTotal,
            'total_override' => $subTotal + $inReconSubTotal,
        ]);
    }

    public function getReconOverRidesData($userId, $startDate, $endDate, $search, $payout, $isFinalize)
    {

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $subTotal = 0;
        $inReconSubTotal = 0;

        $data = [];
        $sales = SalesMaster::selectRaw('m2_date, sale_masters.pid, customer_name, customer_state, user_overrides.sale_user_id, user_overrides.type, user_overrides.overrides_amount, user_overrides.overrides_type, user_overrides.calculated_redline, user_overrides.calculated_redline_type,   
                SUM(user_overrides.amount) as totalOverride, sale_masters.gross_account_value, user_overrides.id')
            ->leftJoin('user_overrides', 'user_overrides.pid', 'sale_masters.pid')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->where(['user_overrides.user_id' => $userId, 'is_displayed' => '1', 'user_overrides.overrides_settlement_type' => 'reconciliation'])
            ->when($search && ! empty($search), function ($q) use ($search) {
                $q->where('sale_masters.pid', 'LIKE', '%'.$search.'%')->orWhere('sale_masters.customer_name', 'LIKE', '%'.$search.'%');
            })->groupBy('user_overrides.sale_user_id', 'user_overrides.type', 'user_overrides.pid')->orderBy('sale_masters.pid', 'ASC')->get();

        foreach ($sales as $result) {
            $userData = User::select('id', 'first_name', 'last_name', 'image')->where('id', $result->sale_user_id)->first();
            $isEligible = false;
            $finalDate = SaleProductMaster::select('id', 'pid', 'milestone_date')->where(['pid' => $result->pid, 'is_last_date' => 1])->first();
            if (! empty($finalDate->milestone_date) && $finalDate->milestone_date >= $startDate && $finalDate->milestone_date <= $endDate && empty($result->date_cancelled)) {
                $isEligible = true;
            }

            $totalOverride = $result->totalOverride ?? 0;
            $totalOverrideAmount = UserOverrides::selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as positive_amount,
                    SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as negative_amount')
                ->where(['pid' => $result->pid, 'user_id' => $userId, 'sale_user_id' => $result->sale_user_id, 'type' => $result->type, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->first();
            $positiveOverride = $totalOverrideAmount->positive_amount ?? 0;
            $negativeOverride = $totalOverrideAmount->negative_amount ?? 0;
            $totalReconOverride = $positiveOverride + $negativeOverride;

            $paidReconAmount = ReconOverrideHistory::selectRaw('SUM(CASE WHEN paid > 0 THEN paid ELSE 0 END) as positive_amount,
                    SUM(CASE WHEN paid < 0 THEN paid ELSE 0 END) as negative_amount')
                ->where(['pid' => $result->pid, 'user_id' => $userId, 'overrider' => $result->sale_user_id, 'type' => $result->type, 'is_displayed' => '1'])
                ->when($isFinalize, function ($q) {
                    $q->where('status', 'payroll');
                })->first();

            $positiveReconOverride = $paidReconAmount->positive_amount ?? 0;
            $negativeReconOverride = $paidReconAmount->negative_amount ?? 0;
            $totalPaidInRecon = $positiveReconOverride + $negativeReconOverride;

            $inReconAmount = 0;
            $inReconPercentage = 0;
            if ($isEligible) {
                $positiveValue = $positiveOverride - $positiveReconOverride;
                $negativeValue = $negativeOverride - $negativeReconOverride;
                $inReconPercentage = ($positiveValue * ($payout / 100)) + $negativeValue;
                $inReconAmount = $totalReconOverride - $totalPaidInRecon - $inReconPercentage;
                $inReconSubTotal += $inReconAmount;
            } else {
                $inReconAmount = $totalReconOverride - $totalPaidInRecon;
            }

            $totalPaid = $totalOverride - $inReconPercentage - $inReconAmount;
            $subTotal += $inReconPercentage;

            /* GET RECON ADJUSTMENT DETAILS */
            // $reconAdjustment = $this->reconAdjustmentDetails($result->pid, $userData->id, $startDate->format("Y-m-d"), $endDate->format("Y-m-d"), "override", $result->type, $isFinalize);
            if ($isFinalize == '0') {
                $reconAdjustment = $this->reconAdjustmentDetails($result->pid, $userId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'override', $result->type, $isFinalize);
            } else {
                $reconAdjustment = $this->reconAdjustmentDetailsonfinalize($result->pid, $userId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'override', $result->type, $isFinalize);
            }

            if ($result->type == 'Stack') {
                $overridesType = $result->calculated_redline_type;
                $overridesAmount = $result->calculated_redline;
            } else {
                $overridesType = $result->overrides_type;
                $overridesAmount = $result->overrides_amount;
            }

            $s3Image = $userData->image ? s3_getTempUrl(config('app.domain_name').'/'.$userData->image) : null;
            $data[] = [
                'id' => $result->id,
                'user_id' => $userId,
                'pid' => $result->pid,
                'customer_name' => $result->customer_name,
                'customer_state' => $result->customer_state,
                'override_over_image' => $s3Image,
                'override_over_first_name' => $userData->first_name,
                'override_over_last_name' => $userData->last_name,
                'type' => $result->type,
                'overrides_type' => $overridesType,
                'overrides_amount' => $overridesAmount,
                'amount' => $totalOverride,
                'paid' => $totalPaid,
                'in_recon_percentage' => $inReconPercentage,
                'in_recon' => $inReconAmount,
                'adjustment_amount' => $reconAdjustment['adjustment_amount'],
                'comment_status' => $reconAdjustment['comment_status'],
                'adjustment_comment' => $reconAdjustment['adjustment_comment'],
                'adjustment_by' => $reconAdjustment['adjustment_by'],
                'is_super_admin' => $reconAdjustment['is_super_admin'],
                'is_manager' => $reconAdjustment['is_manager'],
                'position_id' => $reconAdjustment['position_id'],
                'sub_position_id' => $reconAdjustment['sub_position_id'],
                'is_ineligible' => $isEligible ? 0 : 1,
                'gross_account_value' => $result->gross_account_value,
            ];
        }

        return [$data, $subTotal, $inReconSubTotal];
    }

    public function reconClawbackPopup(Request $request, $id): JsonResponse
    {
        try {
            $apiName = 'Payroll Reconciliation Clawback By employee Id';
            $valid = validator($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'is_finalize' => self::FINALIZE_VALIDATE,
            ]);

            if ($valid->fails()) {
                return response()->json([
                    'ApiName' => $apiName,
                    'status' => false,
                    'message' => $valid->errors()->first(),
                ], 400);
            }

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $search = $request->search;
            $isFinalize = $request->is_finalize;
            $finalizeId = $request->finalize_id;
            $reconClawbackData = $this->getReconClawbackData($id, $startDate, $endDate, $search, $isFinalize, $finalizeId);
            // $response = $reconClawbackData->filter();
            $response = $reconClawbackData;

            return response()->json([
                'ApiName' => $apiName,
                'status' => true,
                'message' => 'Successfully.',
                'data' => $response['response'],
                'subTotal' => $response['sum'],
            ]);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getReconClawbackData($userId, $startDate, $endDate, $search, $isFinalize, $finalizeId)
    {
        try {
            $responseData = [];
            $getAllPids = DB::table('sale_masters')
                ->select('sale_masters.*')
                ->join('sale_master_process', function ($join) use ($userId) {
                    $join->on('sale_master_process.pid', '=', 'sale_masters.pid')
                        ->where(function ($subQuery) use ($userId) {
                            $subQuery->where('sale_master_process.closer1_id', $userId)
                                ->orWhere('sale_master_process.closer2_id', $userId)
                                ->orWhere('sale_master_process.setter1_id', $userId)
                                ->orWhere('sale_master_process.setter2_id', $userId);
                        });
                })
                ->whereBetween('sale_masters.customer_signoff', [$startDate, $endDate])
            // ->whereNotNull("sale_masters.date_cancelled")
                ->get();

            $reconClawbackData = ClawbackSettlement::where('clawback_type', 'reconciliation')
                ->where('user_id', $userId)
                ->where('payroll_id', 0)
                ->where('status', 3)
                    // ->where("recon_status", 1)
                ->where(function ($query) use ($search) {
                    if (! empty($search)) {
                        $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                            ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                    }
                })->get();

            $sum = 0;
            $pids = [];
            if (count($reconClawbackData) > 0) {
                foreach ($reconClawbackData as $y => $response) {
                    $pids[] = $response->pid;
                    $location = Locations::with('State')->where('general_code', $response->s_m_t_customer_state)->first();
                    $stateCode = $location ? $location->state->id : null;
                    $clawbackAmount = $response->clawback_amount ?? 0;

                    if (in_array($response->type, ['commission', 'recon-commission'])) {
                        $paymentType = 'Commission';
                    } else {
                        $paymentType = 'Override';
                    }
                    /* recon clawback history */
                    $reconClawbackHistory = ReconClawbackHistory::where('pid', $response->pid)->where('user_id', $response->user_id)->where('finalize_id', $finalizeId)
                        ->where('type', $response->type == 'overrides' ? 'overrides' : $response->type);

                    if ($isFinalize == 1) {
                        $reconPaidAmount = $reconClawbackHistory->where('status', 'payroll')->sum('paid_amount');
                    } else {
                        $reconPaidAmount = $reconClawbackHistory->sum('paid_amount');
                    }
                    $totalAmount = $clawbackAmount - $reconPaidAmount;
                    if ($totalAmount == 0) {
                        return null;
                    }
                    /* clawback adjustment Data */
                    $reconClawbackAdjustment = $this->reconAdjustmentDetails($response->pid, $response->user_id, $startDate, $endDate, 'clawback', $response->adders_type, '');
                    $moveToReconStatus = $response->is_move_to_recon == 1 ? ' | Move From Payroll' : '';
                    $customerName = isset($response->salesDetail->customer_name) ? $response->salesDetail->customer_name : null;

                    $sum = ($sum + $clawbackAmount);
                    $responseData[] = [
                        'id' => $response->id,
                        'pid' => $response->pid,
                        'user_id' => $response->user_id,
                        'customer_name' => $customerName.$moveToReconStatus,
                        'state_id' => $stateCode,
                        'state' => strtoupper($response->salesDetail->customer_state),
                        'payment_type' => $paymentType,
                        'date' => $response->salesDetail->date_cancelled,
                        'date_paid' => $response->updated_at,
                        'clawback_type' => $response->type,
                        'type' => $response->adders_type,
                        'amount' => -1 * floatval($clawbackAmount),
                        'is_prev_paid' => 0,
                        'in_recon' => -1 * $clawbackAmount,
                        'adjustment_amount' => floatval($reconClawbackAdjustment['adjustment_amount']),
                        'comment_status' => $reconClawbackAdjustment['comment_status'],
                        'adjustment_comment' => $reconClawbackAdjustment['adjustment_comment'],
                        'adjustment_by' => $reconClawbackAdjustment['adjustment_by'],
                        'is_move_to_recon' => $reconClawbackAdjustment['is_move_to_recon'],
                        'is_super_admin' => $reconClawbackAdjustment['is_super_admin'],
                        'is_manager' => $reconClawbackAdjustment['is_manager'],
                        'position_id' => $reconClawbackAdjustment['position_id'],
                        'sub_position_id' => $reconClawbackAdjustment['sub_position_id'],
                    ];

                }

            }

            if (count($getAllPids) > 0) {
                foreach ($getAllPids as $x => $val) {

                    if (! empty($val->date_cancelled) && ! in_array($val->id, $pids)) {

                        $responseData[] = [
                            'id' => $val->id,
                            'pid' => $val->pid,
                            'user_id' => $userId,
                            'customer_name' => $val->customer_name,
                            'state_id' => $val->state_id ?? 0,
                            'state' => strtoupper($val->customer_state),
                            'payment_type' => '',
                            'date' => $val->date_cancelled,
                            'date_paid' => '',
                            'clawback_type' => '',
                            'type' => $val->adders_type ?? '',
                            'amount' => 0,
                            'is_prev_paid' => 0,
                            'in_recon' => 0,
                            'adjustment_amount' => 0,
                            'comment_status' => '',
                            'adjustment_comment' => '',
                            'adjustment_by' => '',
                            'is_move_to_recon' => '',
                            'is_super_admin' => '',
                            'is_manager' => '',
                            'position_id' => '',
                            'sub_position_id' => '',
                        ];

                    }
                }
            }

            return $data = [
                'response' => $responseData,
                'sum' => (0 - $sum),
            ];

        } catch (\Throwable $th) {
            Log::channel('reconLog')->info($th->getMessage());

            return response()->json([
                'ApiName' => 'Get Recon Clawback Data',
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 500);
        }
    }

    public function reconAdjustmentPopup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $search = $request->search;
        $reconAdjustmentData = ReconAdjustment::whereDate('start_date', $request->start_date)
            ->whereDate('end_date', $request->end_date)
            ->where('user_id', $request->user_id)
            ->whereNull('payroll_id')
            ->whereNull('pay_period_from')
            ->whereNull('pay_period_to')
            ->whereNull('payroll_status')
            ->join('sale_masters as s_m_t', function ($join) {
                $join->on('recon_adjustments.pid', DB::raw('s_m_t.pid COLLATE utf8mb4_unicode_ci'));
            })
            ->select(
                'recon_adjustments.*',
                's_m_t.customer_name',
                's_m_t.customer_state'
            )
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                        ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                }
            })->get();

        if ($request->is_finalize == 1) {
            $reconAdjustmentData = ReconAdjustment::whereDate('start_date', $request->start_date)
                ->whereDate('end_date', $request->end_date)
                ->where('user_id', $request->user_id)
                ->whereNull('payroll_id')
                ->whereNull('pay_period_from')
                ->whereNull('pay_period_to')
                ->join('sale_masters as s_m_t', function ($join) {
                    $join->on('recon_adjustments.pid', DB::raw('s_m_t.pid COLLATE utf8mb4_unicode_ci'));
                })
                ->select(
                    'recon_adjustments.*',
                    's_m_t.customer_name',
                    's_m_t.customer_state'
                )
                ->where(function ($query) use ($search) {
                    if (! empty($search)) {
                        $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                            ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                    }
                })->get();
        }
        $finalData = [
            'commission' => [],
            'overrides' => [],
            'commission_total' => 0.00,
            'override_total' => 0.00,
            'clawback_total' => 0.00,
            'deduction_total' => 0.00,
        ];

        $reconAdjustmentData->transform(function ($result) use (&$finalData) {
            $s3_image = $result?->commentUser?->image ? s3_getTempUrl(config('app.domain_name').'/'.$result->commentUser->image) : null;
            $totalAmount = floatval($result->adjustment_amount);
            $data = [
                'pid' => $result->pid,
                'date' => $result->created_at->format('Y-m-d'),
                'comment' => $result->adjustment_comment,
                'type' => $result->adjustment_override_type,
                'amount' => $totalAmount,
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

            return $result;
        });

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $deductionData = PayrollDeductions::where('user_id', $request->user_id)
            ->where('is_move_to_recon', 1)
            ->where('status', 3)
            ->whereBetween('pay_period_from', [$startDate, $endDate])
            ->whereBetween('pay_period_to', [$startDate, $endDate])
            ->where('is_move_to_recon_paid', 0)
            ->get();

        $responseArray = [];
        $deductionData->map(function ($item) use (&$responseArray) {
            $costCenterName = $item->costcenter->name;
            // Group by cost center name
            $responseArray[$costCenterName][] = [
                'deduction_name' => $costCenterName,
                'date' => $item->created_at->format('Y-m-d'),
                'comment' => $item->costcenter->code,
                'adjust_by' => $item->adjust_by,
                'amount' => '-'.$item->total,
                'deduct' => '-'.$item->total,
            ];
        });

        $responseArray = array_map(function ($group) {
            return array_filter($group, function ($item) {
                return $item['amount'] !== '-0.00' && $item['deduct'] !== '-0.00';
            });
        }, $responseArray);

        $responseArray = array_map(function ($group) {
            return array_values($group); // This removes the numeric keys, ensuring a clean array
        }, $responseArray);

        $totalDeductionAmount = array_reduce($responseArray, function ($carry, $group) {
            foreach ($group as $item) {
                // Convert the string amounts to float and add to the carry
                $carry['deduct'] += (float) $item['deduct'];
                $carry['amount'] += (float) $item['amount'];
            }

            return $carry;
        }, ['deduct' => 0, 'amount' => 0]);

        $finalData['deduction_total'] = $totalDeductionAmount['deduct'];
        $finalData['deduction'] = $responseArray;

        return response()->json($finalData);
    }

    public function reportReconAdjustmentPopup(Request $request) // this function is use for the admin recon report not on the start recon reports.
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

        $reconAdjustmentData = ReconAdjustment::where('user_id', $request->user_id)
            ->where('start_date', $request->start_date)
            ->where('end_date', $request->end_date)
            ->where('finalize_id', $request->finalize_count)
            // ->where('payroll_status', 'payroll')
            ->get();

        $finalData = [
            'commission' => [],
            'overrides' => [],
            'clawback' => [],
            'commission_total' => 0.00,
            'override_total' => 0.00,
            'clawback_total' => 0.00,
            'deduction_total' => 0.00,
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

            $data = [
                'pid' => $result->pid,
                'date' => $result->created_at->format('Y-m-d'),
                'comment' => $result->adjustment_comment,
                'type' => $result->adjustment_type,
                'amount' => $totalAmount,
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

            return $result;
        });

        /* deduction data */
        $deductionsResults = ReconDeductionHistory::where('user_id', $request->user_id)
            ->whereDate('start_date', $request->start_date)
            ->whereDate('end_Date', $request->end_date)
            ->where('finalize_id', $request->finalize_count)
            ->get();

        $responseArray = $deductionsResults->groupBy(function ($item) {
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

    public function reconUserCommissionAccountSummary(Request $request, $userId)
    {
        try {
            $validate = Validator::make($request->all(), [
                'pid' => ['required', 'exists:sale_masters,pid'],
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'api_name' => self::RECON_USER_ACCOUNT_SUMMARY,
                    'status' => false,
                    'message' => $validate->errors(),
                ], 400);
            }
            $commissionData = UserCommission::where('pid', $request->pid)->where('user_id', $userId)->where('amount_type', '!=', 'reconciliation')->get()->transform(function ($result) use ($request, $userId) {
                $moveFromPayroll = 0;
                $paidAmount = $result->amount;
                $unpaidAmount = 0;
                $recon = '';
                if (in_array($result->amount_type, ['m1', 'm2', self::M2_UPDATE]) && $result->is_move_to_recon == 1 && $result->status == 3) {
                    $moveFromPayroll = 1;
                    $recon = ($result->status == 3 && $result->is_move_to_recon == 1) ? self::MOVE_TO_RECON : '';
                    $paidAmount = 0;
                }
                $reconData = ReconCommissionHistory::where('pid', $request->pid)->where('user_id', $userId)->where('move_from_payroll', $moveFromPayroll)->where('type', $result->amount_type)->exists();
                // $type = ucfirst($result->amount_type) . ' Payment' . $recon;
                $companyProfile = CompanyProfile::first();
                $type = '';
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($result->amount_type == 'm1') {
                        $type = 'Upfront';
                    } elseif ($result->amount_type == 'm2') {
                        $type = 'Commission';
                    } elseif ($result->amount_type == 'm2 update') {
                        $type = 'Commission Update';
                    }
                } else {
                    $type = ucfirst($result->amount_type).' Payment'.$recon;
                }

                if (! $reconData) {
                    return [
                        'date' => Carbon::parse($result->date)->format('m/d/Y'),
                        'user_id' => $result->user_id,
                        'employee' => $result->userdata->first_name.' '.$result->userdata->last_name,
                        'position_id' => $result->userdata->position_id,
                        'sub_position_id' => $result->userdata->sub_position_id,
                        'is_super_admin' => $result->userdata->is_super_admin,
                        'is_manager' => $result->userdata->is_manager,
                        'type' => $type,
                        'paid' => $paidAmount,
                        'unpaid' => $unpaidAmount,
                        'status' => $result->status,
                        'stop_payroll' => $result->userdata->stop_payroll,
                        'date_paid' => Carbon::parse($result->pay_period_from)->format('m/d/Y').' to '.Carbon::parse($result->pay_period_to)->format('m/d/Y'),
                    ];
                }
            });
            $reconCommissionData = ReconCommissionHistory::where('user_id', $userId)
                ->where('is_ineligible', '0')
                ->where('pid', $request->pid)
                ->where('user_id', $userId)
                ->whereIn('status', ['payroll', 'finalize'])
                ->get();

            $reconPaidResponse = $reconCommissionData->transform(function ($result) {
                $unPaidAmount = 0;
                $paidAmount = 0;

                if ($result->status == 'payroll' && $result->payroll_execute_status == '3') {
                    $paidAmount = $result->paid_amount;
                } elseif ($result->status == 'finalize' || $result->status == 'payroll') {
                    $unPaidAmount = $result->paid_amount;
                }

                $commissionType = ['m1', 'm2', self::M2_UPDATE];
                $reconStatus = ($result->move_from_payroll == 1) ? ' | Move To Recon' : '';
                $reconType = in_array($result->type, $commissionType) ? $result->type.' | ' : '';
                $finalType = $reconType.'Reconciliation'.$reconStatus;

                return [
                    'date' => Carbon::parse($result?->payrollHistory?->created_at)->format('m/d/Y'),
                    'user_id' => $result->user->id,
                    'employee' => $result->user->first_name.' '.$result->user->last_name,
                    'position_id' => $result->user->position_id,
                    'sub_position_id' => $result->user->sub_position_id,
                    'is_super_admin' => $result->user->is_super_admin,
                    'is_manager' => $result->user->is_manager,
                    'type' => $finalType,
                    'paid' => $paidAmount,
                    'unpaid' => $unPaidAmount,
                    'status' => $result->status,
                    'stop_payroll' => $result->user->stop_payroll,
                    'date_paid' => null,
                ];
            });
            $data['total_commission'] = $commissionData->concat($reconPaidResponse);
            $total = array_reduce($data['total_commission']->toArray(), function ($carry, $item) {
                $carry['paid'] += $item['paid'];
                $carry['unpaid'] += $item['unpaid'];

                return $carry;
            }, ['paid' => 0, 'unpaid' => 0]);

            $data['commission_paid_total'] = $total['paid'];
            $data['commission_unpaid_total'] = $total['unpaid'];
            $data['commission_total'] = array_sum($total);

            return response()->json([
                'api_name' => self::RECON_USER_ACCOUNT_SUMMARY,
                'status' => true,
                'data' => $data,
                'message' => 'Success',
            ]);
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info($th->getMessage());

            return response()->json([
                'api_name' => self::RECON_USER_ACCOUNT_SUMMARY,
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 400);
        }
    }

    public function reportReconUserCommissionAccountSummary(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'pid' => ['required', 'exists:sale_masters,pid'],
                'user_id' => ['required', 'exists:users,id'],
                'start_date' => ['required', self::DATE_FORMATE],
                'end_date' => ['required', self::DATE_FORMATE],
            ]);
            if ($validate->fails()) {
                return response()->json([
                    'api_name' => 'Recon Report User Account Summary',
                    'status' => false,
                    'message' => $validate->errors(),
                ], 400);
            }

            $userId = $request->user_id;
            $pid = $request->pid;
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $finalizeCount = $request->finalize_count;
            $data = [];

            $commissions = UserCommission::where('pid', $request->pid)
                ->where('user_id', $userId)
                ->get();

            $totalCommissionData = $commissions->transform(function ($result) use ($userId, $startDate, $endDate, $finalizeCount) {
                $unPaidCommission = 0.00;
                $paidCommission = 0.00;
                $recon = ($result->status == 6) ? self::MOVE_TO_RECON : '';
                $nextPayroll = ($result->status == 4) ? ' | Move To Next Payroll' : '';
                $type = '';
                if ($this->isPestServer) {
                    if ($result->amount_type == 'm1') {
                        $type = 'Upfront';
                    } elseif ($result->amount_type == 'm2') {
                        $type = 'Commission';
                    } elseif ($result->amount_type == self::M2_UPDATE) {
                        $type = 'Commission Update';
                    }
                } else {
                    $type = $result->amount_type.' Payment'.$recon.$nextPayroll;
                }
                if ($result->status != 6) {
                    $paidCommission = isset($result->amount) ? $result->amount : 0;
                } else {
                    $paidCommission = ReconCommissionHistory::where('user_id', $userId)
                        ->where('pid', $result->pid)
                        ->where('start_date', $startDate)
                        ->where('end_date', $endDate)
                        // ->where("type", $result->amount_type)
                        ->where('status', 'payroll')
                        ->where('finalize_count', $finalizeCount)
                        ->sum('paid_amount');

                    $unPaidCommission = (isset($result->amount) ? $result->amount : 0) - $paidCommission;
                }

                return [
                    'date' => Carbon::parse($result->date)->format('m/d/Y'),
                    'user_id' => $userId,
                    'employee' => $result->userdata->first_name.' '.$result->userdata->last_name,
                    'position_id' => $result->userdata->position_id,
                    'sub_position_id' => $result->userdata->sub_position_id,
                    'is_super_admin' => $result->userdata->is_super_admin,
                    'is_manager' => $result->userdata->is_manager,
                    'type' => $type,
                    'paid' => $paidCommission,
                    'unpaid' => $unPaidCommission,
                    'status' => $result->status,
                    'stop_payroll' => $result->userdata->stop_payroll,
                    'date_paid' => Carbon::parse($result->pay_period_from)->format('m/d/Y').' to '.Carbon::parse($result->pay_period_to)->format('m/d/Y'),
                ];
            });

            $reconPaidData = ReconCommissionHistory::where('user_id', $userId)
                ->where('pid', $pid)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->where('status', 'payroll')
                ->where('finalize_count', $finalizeCount)
                ->get();

            $reconPaidResponse = $reconPaidData->transform(function ($result) {
                $unPaidAmount = 0;
                $paidAmount = 0;

                if ($result->reconCommissionHistory->status == 'payroll' && $result->reconCommissionHistory->payroll_execute_status == '3') {
                    $paidAmount = $result->paid_amount;
                } elseif ($result->reconCommissionHistory->status == 'finalize' || $result->reconCommissionHistory->status == 'payroll') {
                    $unPaidAmount = $result->paid_amount;
                }

                $commissionType = ['m1', 'm2', self::M2_UPDATE];
                $reconStatus = ($result->move_from_payroll == 1) ? self::MOVE_TO_RECON : '';
                $reconType = in_array($result->type, $commissionType) ? $result->type.' | ' : '';
                $finalType = $reconType.'Reconciliation'.$reconStatus;

                return [
                    'date' => Carbon::parse($result?->payrollHistory?->created_at)->format('m/d/Y'),
                    'user_id' => $result->user->id,
                    'employee' => $result->user->first_name.' '.$result->user->last_name,
                    'position_id' => $result->user->position_id,
                    'sub_position_id' => $result->user->sub_position_id,
                    'is_super_admin' => $result->user->is_super_admin,
                    'is_manager' => $result->user->is_manager,
                    'type' => $finalType,
                    'paid' => $paidAmount,
                    'unpaid' => $unPaidAmount,
                    'status' => $result->status,
                    'stop_payroll' => $result->user->stop_payroll,
                    'date_paid' => null,
                ];
            });

            $data['total_commission'] = $totalCommissionData->concat($reconPaidResponse);
            $total = array_reduce($data['total_commission']->toArray(), function ($carry, $item) {
                $carry['paid'] += $item['paid'];
                $carry['unpaid'] += $item['unpaid'];

                return $carry;
            }, ['paid' => 0, 'unpaid' => 0]);

            $data['commission_paid_total'] = $total['paid'];
            $data['commission_unpaid_total'] = $total['unpaid'];
            $data['commission_total'] = array_sum($total);

            return response()->json([
                'api_name' => 'Report Recon User Account Summary',
                'status' => true,
                'data' => $data,
                'message' => 'Success',
            ], 200);
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info('!! Error !! '.$th->getMessage().'. At line number '.$th->getLine().'. File name is '.$th->getFile());

            return response()->json([
                'api_name' => 'Report Recon User Account Summary',
                'status' => false,
                'message' => '!! Error !! '.$th->getMessage().'. At line number '.$th->getLine().'. File name is '.$th->getFile(),
            ], 400);
        }
    }

    public function updateReconClawbackDetails(Request $request): JsonResponse
    {
        try {
            $validate = Validator::make($request->all(), [
                'id' => ['required', 'exists:clawback_settlements,id'],
                'user_id' => ['required', 'exists:users,id'],
                'pid' => ['required'],
                'start_date' => ['required', self::DATE_FORMATE],
                'end_date' => ['required', self::DATE_FORMATE],
                'adjust_amount' => ['required'],
                'type' => ['required'], // adjustment_override_type
                'adjustment_type' => ['required'], // adjustment_type
                'comment' => ['required'],
                'is_finalize' => self::FINALIZE_VALIDATE,
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validate->messages(),
                ], 400);
            }

            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $userId = $request->user_id;
            $pid = $request->pid;
            $adjustAmount = $request->adjust_amount;
            $comment = $request->comment;
            $type = $request->type;
            /* if ($type == 'Reconciliation') {
                $type = "recon-commission";
            } */

            $body = [
                'user_id' => $userId,
                'pid' => $pid,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'adjustment_type' => 'clawback',
                'adjustment_override_type' => $type,
                'adjustment_amount' => $adjustAmount,
                'adjustment_comment' => $comment,
                'adjustment_by_user_id' => auth()->user()->id,
            ];

            $reconAdjustmentData = ReconAdjustment::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->whereNull('pay_period_from')
                ->whereNull('pay_period_to')
                ->whereNull('payroll_id')
                ->where('adjustment_override_type', $type)
                ->where('pid', $pid)
                ->where('user_id', $userId);

            if ($reconAdjustmentData->exists()) {
                $reconAdjustmentData->update($body);
            } else {
                ReconAdjustment::create($body);
            }

            return response()->json([
                'ApiName' => 'Edit reconciliation clawback adjustment',
                'status' => true,
                'message' => 'Update Successfully.',
            ]);
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info($th->getMessage());

            return response()->json([
                'ApiName' => 'Edit reconciliation override adjustment',
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 500);
        }
    }

    public function reconAdjustmentEdit(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required',
                'pid' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
                'adjust_amount' => 'required',
                'comment' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $pid = $request->pid;
            if (! $sale = SalesMaster::where('pid', $pid)->first()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sale not found!!',
                ], 400);
            }

            $finalDate = SaleProductMaster::where('pid', $pid)->where('is_last_date', 1)->whereNotNull('milestone_date')->first();
            if (! $finalDate) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user is ineligible for adjustment!!',
                ], 400);
            }

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $m2Date = Carbon::parse($finalDate->milestone_date);
            if (! $m2Date->between($startDate, $endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user is ineligible for adjustment!!',
                ], 400);
            }

            $userId = $request->user_id;
            $adjustmentAmount = $request->adjust_amount;
            $comment = $request->comment;
            $body = [
                'user_id' => $userId,
                'pid' => $pid,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'adjustment_type' => 'commission',
                'adjustment_amount' => $adjustmentAmount,
                'adjustment_comment' => $comment,
                'adjustment_by_user_id' => auth()->user()->id,
                'adjustment_override_type' => 'commission',
            ];

            $adjustmentData = ReconAdjustment::where(['start_Date' => $startDate, 'end_date' => $endDate, 'user_id' => $userId, 'adjustment_type' => 'commission', 'pid' => $pid])
                ->whereNull('payroll_id')->whereNull('pay_period_from')->whereNull('pay_period_to');
            if ($adjustmentData->exists()) {
                $adjustmentData->update($body);
            } else {
                ReconAdjustment::create($body);
            }

            return response()->json([
                'ApiName' => 'Edit commission adjustment',
                'status' => true,
                'message' => 'Update Successfully.',
            ]);
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info($th->getMessage());

            return response()->json([
                'ApiName' => 'Edit reconciliation commission adjustment',
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 500);
        }
    }

    public function overrideAdjustmentEdit(Request $request): JsonResponse
    {
        try {
            $validate = Validator::make($request->all(), [
                'id' => ['required', 'exists:user_overrides,id'],
                'user_id' => ['required', 'exists:users,id'],
                'pid' => ['required'],
                'adjust_amount' => ['required'],
                'comment' => ['required'],
                'start_date' => ['required', self::DATE_FORMATE],
                'end_date' => ['required', self::DATE_FORMATE],
                'type' => 'required',
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validate->messages(),
                ], 400);
            }

            $id = $request->id;
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $userId = $request->user_id;
            $pid = $request->pid;
            $adjustAmount = $request->adjust_amount;
            $comment = $request->comment;
            $type = $request->type;

            if (! $sale = SalesMaster::where('pid', $pid)->first()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sale not found!!',
                ], 400);
            }

            $startDate = Carbon::parse($startDate);
            $endDate = Carbon::parse($endDate);

            $finalDate = SaleProductMaster::where('pid', $pid)->where('is_last_date', 1)->whereNotNull('milestone_date')->first();
            if (! $finalDate) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user is ineligible for adjustment!!',
                ], 400);
            }

            $m2Date = Carbon::parse($finalDate->milestone_date);
            if (! $m2Date->between($startDate, $endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user is ineligible for adjustment!!',
                ], 400);
            }

            $userOverrideData = UserOverrides::find($id);
            $body = [
                'user_id' => $userId,
                'pid' => $pid,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'adjustment_type' => 'override',
                'adjustment_amount' => $adjustAmount,
                'adjustment_comment' => $comment,
                'adjustment_by_user_id' => auth()->user()->id,
                'adjustment_override_type' => $request->type,
                'sale_user_id' => $userOverrideData->sale_user_id,
                'move_from_payroll' => $userOverrideData->is_move_to_recon == 1 ? 1 : 0,
            ];

            $reconAdjustmentData = ReconAdjustment::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->whereNull('pay_period_from')
                ->whereNull('pay_period_to')
                ->whereNull('payroll_id')
                ->where('adjustment_override_type', $type)
                ->where('pid', $pid)
                ->where('user_id', $userId);

            if ($reconAdjustmentData->exists()) {
                $reconAdjustmentData->update($body);
            } else {
                ReconAdjustment::create($body);
            }

            return response()->json([
                'ApiName' => 'Edit reconciliation override adjustment',
                'status' => true,
                'message' => 'Update Successfully.',
            ]);
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info($th->getMessage());

            return response()->json([
                'ApiName' => 'Edit reconciliation override adjustment',
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 500);
        }
    }

    public function getPids($userId, $startDate, $endDate, $type = null, $search = null)
    {
        $getAllPids = DB::table('sale_masters')
            ->select(
                'sale_masters.*',
                'sale_masters.customer_signoff',
                'sale_masters.m2_date',
                'sale_masters.date_cancelled',
                'sale_masters.customer_state',
                'sale_masters.customer_name',
                'sale_masters.state_id'
            )->when($type === 'commission', function ($query) use ($userId) {
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
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('sale_masters.customer_name', 'LIKE', "%{$search}%")
                        ->orWhere('sale_masters.pid', 'LIKE', "%{$search}%");
                }
            })
            ->whereBetween('sale_masters.customer_signoff', [$startDate, $endDate])
            ->whereNull('sale_masters.date_cancelled')
            ->get();

        // Filter the results to get only eligible PIDs based on m2_date
        $eligiblePids = $getAllPids->filter(function ($item) use ($startDate, $endDate) {
            return ! empty($item->m2_date) && $item->m2_date >= $startDate && $item->m2_date <= $endDate && empty($item->date_cancelled);
        })->pluck('pid')->toArray();

        return [
            'allPids' => $getAllPids,
            // $getAllPids->pluck('pid')->toArray(),
            'eligiblePids' => $eligiblePids,
        ];
    }

    public function reconAdjustmentDetails($pid, $userId, $startDate, $endDate, $adjustmentType, $adjustmentOverrideType = null)
    {
        $reconAdjustment = ReconAdjustment::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)
            ->where('user_id', $userId)->where('pid', $pid)
            ->where('adjustment_type', $adjustmentType)
            ->where('adjustment_override_type', $adjustmentOverrideType)
            ->whereNull('payroll_id')
            ->whereNull('pay_period_to')
            ->whereNull('pay_period_from')
            ->first();
        $adjustmentUser = User::find($reconAdjustment?->adjustment_by_user_id);

        return [
            'position_id' => $adjustmentUser?->position_id,
            'sub_position_id' => $adjustmentUser?->sub_position_id,
            'is_super_admin' => $adjustmentUser?->is_super_admin,
            'is_manager' => $adjustmentUser?->is_manager,
            'adjustment_amount' => $reconAdjustment?->adjustment_amount,
            'adjustment_comment' => $reconAdjustment?->adjustment_comment,
            'adjustment_by' => $adjustmentUser?->first_name.' '.$adjustmentUser?->last_name,
            'comment_status' => $reconAdjustment ? 1 : 0,
            'is_move_to_recon' => $reconAdjustment?->move_from_payroll,
        ];
    }

    public function reconAdjustmentDetailsonfinalize($pid, $userId, $startDate, $endDate, $adjustmentType, $adjustmentOverrideType, $isFinalize)
    {

        $reconAdjustment = ReconAdjustment::with('commentUser')
            ->where('pid', $pid)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('adjustment_type', $adjustmentType)
            ->where('adjustment_override_type', $adjustmentOverrideType)
            ->where('user_id', $userId)
            ->whereNull('payroll_id')
            ->whereNull('pay_period_from')
            ->whereNull('pay_period_to')
            ->where('payroll_status', '=', 'finalize')
            ->get();

        // Calculate the sum of adjustment_amount
        $totalAdjustment = $reconAdjustment->sum('adjustment_amount');

        // Assuming there's only one commentUser related to the adjustment, you can get it like this:
        $adjustmentUser = $reconAdjustment->isNotEmpty() ? $reconAdjustment->first()->commentUser : null;

        return [
            'position_id' => $adjustmentUser->position_id ?? null,
            'sub_position_id' => $adjustmentUser->sub_position_id ?? null,
            'is_super_admin' => $adjustmentUser->is_super_admin ?? null,
            'is_manager' => $adjustmentUser->is_manager ?? null,
            'adjustment_amount' => $totalAdjustment,
            'adjustment_comment' => $reconAdjustment->isNotEmpty() ? $reconAdjustment->first()->adjustment_comment : null,
            'adjustment_by' => $adjustmentUser ? $adjustmentUser->first_name.' '.$adjustmentUser->last_name : null,
            'comment_status' => $reconAdjustment->isNotEmpty() ? 1 : 0,
            'is_move_to_recon' => $reconAdjustment->isNotEmpty() ? $reconAdjustment->first()->move_from_payroll : null,
        ];
    }
}
