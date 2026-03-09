<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\Positions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalize;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationStatusForSkipedUser;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconDeductionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReconFinalizeController extends Controller
{
    public $isUpfront = false;

    public function __construct()
    {
        /* check server is pest or not */
        $companyProfile = CompanyProfile::first();
        $this->isUpfront = $companyProfile->deduct_any_available_reconciliation_upfront;
    }

    private const FINALIZE_API_DRAFT_NAME = 'Create Reconciliation Finalize';

    private const FINALIZE_POSITION_ERROR_MSG = 'A reconciliation period has already been finalized for the specified locations and positions. Please process that finalized period before proceeding.';

    public function reconciliationFinalizeDraft(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $validate = Validator::make($request->all(), [
                'start_date' => 'required',
                'end_date' => 'required',
                'recon_payout' => 'required|numeric|between:0,100',
                'office_id' => [
                    'required',
                    function ($_, $value, $fail) { // NO SONAR
                        if (! in_array('all', $value)) {
                            foreach ($value as $locationId) {
                                if (! Locations::where('id', $locationId)->exists()) {
                                    $fail('The selected office does not exist in our system.');
                                }
                            }
                        }
                    },
                ],
                'position_id' => [
                    'required',
                    function ($_, $value, $fail) { // NO SONAR
                        if (! in_array('all', $value)) {
                            foreach ($value as $positionId) {
                                if (! Positions::where('id', $positionId)->exists()) {
                                    $fail('The selected position does not exist in our system.');
                                }
                            }
                        }
                    },
                ],
            ]);

            if ($validate->fails()) {
                return response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => true,
                    'message' => $validate->messages(),
                ], 400);
            }

            $startDate = $request->start_date;
            $endDate = $request->end_date;

            $officeId = app(ReconPayrollController::class)->normalizeIds($request->office_id);
            $positionId = app(ReconPayrollController::class)->normalizeIds($request->position_id);
            $userIds = app(ReconPayrollController::class)->getUserIds($officeId, $positionId, '');
            $finalUserId = app(ReconPayrollController::class)->getUserId($userIds, $startDate, $endDate);
            if (count($finalUserId) == 0) {
                return response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => false,
                    'message' => 'There is no data pending for finalization at this time!!',
                ], 400);
            }

            $skippedUser = ReconciliationStatusForSkipedUser::whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->pluck('user_id')->toArray();
            $withoutSkipped = array_values(array_diff($finalUserId, $skippedUser));
            if (count($withoutSkipped) == 0) {
                return response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => false,
                    'message' => 'All users have been skipped, and there are no users left to finalize!!',
                ], 400);
            }

            $check = ReconciliationFinalizeHistory::where(['status' => 'finalize'])->whereIn('user_id', $withoutSkipped)->first();
            if ($check) {
                return response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => false,
                    'message' => 'Some users have already been finalized. Please process the finalized period before proceeding with the new finalization!!',
                ], 400);
            }

            $reconPayout = $request->recon_payout;
            $finalize = ReconciliationFinalize::create([
                'office_id' => implode(',', $request->office_id),
                'position_id' => implode(',', $request->position_id),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payout_percentage' => $request->recon_payout,
                'is_upfront' => $this->isUpfront,
            ]);
            $response = $this->processReconciliation($withoutSkipped, $startDate, $endDate, $reconPayout, $finalize);
            if ($response['success']) {
                ReconciliationStatusForSkipedUser::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)->delete();

                DB::commit();

                return response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => true,
                    'message' => 'Recon has been finalized successfully!!',
                ]);
            }

            DB::rollBack();

            return response()->json([
                'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                'status' => false,
                'message' => $response['message'],
            ], 400);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                'status' => false,
                'message' => 'Error !! '.$th->getMessage().' .At line number '.$th->getLine(),
            ], 500);
        }
    }

    public function filterData($data)
    {
        $filteredData = [];

        // Iterate over each row in the 'data' array
        foreach ($data['data'] as $row) {
            // Check if all specified values are zero
            if ($row['commissionWithholding'] == 0 &&
                $row['overrideDue'] == 0 &&
                $row['total_due'] == 0 &&
                $row['clawbackDue'] == 0 &&
                $row['totalAdjustments'] == 0) {
                continue;  // Skip this row if all values are 0
            }

            // Otherwise, add the row to the filtered result
            $filteredData[] = $row;
        }

        return $filteredData;
    }

    private function processReconciliation($userIds, $startDate, $endDate, $reconPayout, $finalize)
    {
        try {
            $finalData = (new ReconPayrollController)->getReconData($userIds, $startDate, $endDate, $reconPayout);
            $finalData = $this->filterData($finalData);
            $finalData = $finalData;
            $payout = 0;
            foreach ($finalData as $data) {
                if (! $payout && $data['payout'] != 0) {
                    $payout = 1;
                    break;
                }
            }

            if (! $payout) {
                return ['success' => false, 'message' => 'No data to reconcile!!'];
            }

            $finalizeCommission = 0;
            $finalizeOverride = 0;
            $finalizeTotalDue = 0;
            $finalizeClawBack = 0;
            $finalizeAdjustment = 0;
            $finalizeDeduction = 0;
            $finalizeRemaining = 0;
            foreach ($finalData as $final) {
                $uniquePid = implode(',', $final['pids']);
                $userId = $final['user_id'];
                $userData = User::find($userId);
                $finalizeData = ReconciliationFinalizeHistory::where(['pid' => $uniquePid, 'user_id' => $userId, 'status' => 'finalize'])->get();

                $finalizeCommission += $final['commissionWithholding'] ?? 0;
                $finalizeOverride += $final['overrideDue'] ?? 0;
                $finalizeTotalDue += $final['total_due'] ?? 0;
                $finalizeClawBack += (-1 * $final['clawbackDue']) ?? 0;
                $finalizeAdjustment += $final['adjustments'] ?? 0;
                $finalizeDeduction += $final['deductions'] ?? 0;
                $finalizeRemaining += $final['remaining'] ?? 0;

                $totalCommission = $final['commissionWithholding'] ?? 0;
                $totalOverride = $final['overrideDue'] ?? 0;
                $totalAdjustment = $final['adjustments'] ?? 0;
                $totalDeductionAmount = $final['deductions'] ?? 0;
                $totalClawBack = (-1 * $final['clawbackDue']) ?? 0;
                $totalRemaining = $final['remaining'] ?? 0;
                $percentagePayAmount = 0;
                // $paidCommission = floatval($totalCommission) * ($reconPayout / 100);
                // $paidOverride = floatval($totalOverride) * ($reconPayout / 100);
                $paidCommission = $totalCommission;
                $paidOverride = $totalOverride;

                $percentagePayAmount = $paidCommission + $paidOverride;
                $netAmount = $paidCommission + $paidOverride;
                $grossAmount = $netAmount + $totalAdjustment - $totalDeductionAmount - $totalClawBack;

                $body = [
                    'finalize_id' => $finalize->id,
                    'user_id' => $userId,
                    'pid' => $uniquePid,
                    'office_id' => $userData->office_id,
                    'position_id' => $userData->sub_position_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'adjustments' => $totalAdjustment,
                    'deductions' => $totalDeductionAmount,
                    'clawback' => $totalClawBack,
                    'payout' => $reconPayout,
                    'commission' => $totalCommission,
                    'override' => $totalOverride,
                    'remaining' => $totalRemaining,
                    'status' => 'finalize',
                    'paid_commission' => $paidCommission,
                    'paid_override' => $paidOverride,
                    'type' => 'payroll_reconciliation',
                    'net_amount' => $netAmount,
                    'gross_amount' => $grossAmount,
                    'percentage_pay_amount' => $percentagePayAmount,
                    'is_upfront' => $this->isUpfront,
                ];

                if (! $finalizeData->isEmpty()) {
                    ReconciliationFinalizeHistory::where(['pid' => $uniquePid, 'user_id' => $userId, 'status' => 'finalize', 'is_displayed' => '1'])->update($body);
                } else {
                    ReconciliationFinalizeHistory::create($body);
                }

                /* recon adjustment update */
                ReconAdjustment::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)
                    ->where('user_id', $userData->id)->whereNull('payroll_status')
                    ->update([
                        'payroll_status' => 'finalize',
                        'finalize_id' => $finalize->id,
                    ]);

                // if ($totalCommission != 0) {
                $this->manageCommissionReconHistory($userId, $startDate, $endDate, $reconPayout, $finalize);
                // }
                // if ($totalOverride != 0) {
                $this->manageOverrideReconHistory($userId, $startDate, $endDate, $reconPayout, $finalize);
                // }
                // $this->manageClawbackReconHistory($userId, $startDate, $endDate, $reconPayout, $totalCommission, $finalize);
                $this->manageClawbackReconHistory($userId, $startDate, $endDate, $reconPayout, $finalize);
                $this->manageDeductionReconHistory($userId, $startDate, $endDate, $totalCommission, $finalize);
            }
            $netPay = $finalizeTotalDue + $finalizeAdjustment - $finalizeClawBack - $finalizeDeduction;
            $finalize->update([
                'commissions' => $finalizeCommission,
                'overrides' => $finalizeOverride,
                'total_due' => $finalizeTotalDue,
                'clawbacks' => $finalizeClawBack,
                'adjustments' => $finalizeAdjustment,
                'deductions' => $finalizeDeduction,
                'remaining' => $finalizeRemaining,
                'net_amount' => $netPay,
            ]);

            return ['success' => true, 'message' => 'Success!'];
        } catch (\Throwable $th) {
            return ['success' => false, 'message' => $th->getMessage().' '.$th->getLine()];
        }
    }

    private function manageOverrideReconHistory($userId, $startDate, $endDate, $payout, $finalize)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $sales = SalesMaster::selectRaw('m2_date, sale_masters.pid, customer_name, customer_state, user_overrides.sale_user_id, user_overrides.type, user_overrides.overrides_amount, user_overrides.overrides_type,    
                SUM(user_overrides.amount) as totalOverride, sale_masters.gross_account_value, user_overrides.id, user_overrides.kw, user_overrides.during')
            ->leftJoin('user_overrides', 'user_overrides.pid', 'sale_masters.pid')
            ->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')
            ->where(['user_overrides.user_id' => $userId, 'is_displayed' => '1'])
            ->groupBy('user_overrides.sale_user_id', 'user_overrides.type', 'user_overrides.pid')->orderBy('sale_masters.pid', 'ASC')->get();

        foreach ($sales as $result) {
            $isEligible = false;
            $finalDate = SaleProductMaster::select('id', 'pid', 'milestone_date')->where(['pid' => $result->pid, 'is_last_date' => 1])->first();
            if (! empty($finalDate->milestone_date) && $finalDate->milestone_date >= $startDate && $finalDate->milestone_date <= $endDate && empty($result->date_cancelled)) {
                $isEligible = true;
            }

            $totalOverrideAmount = UserOverrides::selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as positive_amount,
                    SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as negative_amount')
                ->where(['pid' => $result->pid, 'user_id' => $userId, 'sale_user_id' => $result->sale_user_id, 'type' => $result->type, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->first();
            $positiveOverride = $totalOverrideAmount->positive_amount ?? 0;
            $negativeOverride = $totalOverrideAmount->negative_amount ?? 0;

            $paidReconAmount = ReconOverrideHistory::selectRaw('SUM(CASE WHEN paid > 0 THEN paid ELSE 0 END) as positive_amount,
                    SUM(CASE WHEN paid < 0 THEN paid ELSE 0 END) as negative_amount')
                ->where(['pid' => $result->pid, 'user_id' => $userId, 'overrider' => $result->sale_user_id, 'type' => $result->type, 'is_displayed' => '1'])->first();
            $positiveReconOverride = $paidReconAmount->positive_amount ?? 0;
            $negativeReconOverride = $paidReconAmount->negative_amount ?? 0;

            $inReconPercentage = 0;
            $positiveValue = $positiveOverride - $positiveReconOverride;
            $negativeValue = $negativeOverride - $negativeReconOverride;
            if ($isEligible) {
                $inReconPercentage = ($positiveValue * ($payout / 100)) + $negativeValue;
            }
            $totalAmount = $positiveValue + $negativeValue;

            ReconOverrideHistory::create([
                'finalize_id' => $finalize->id,
                'override_id' => $result->id,
                'user_id' => $userId,
                'pid' => $result->pid,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'customer_name' => $result->customer_name,
                'overrider' => $result->sale_user_id,
                'type' => $result->type,
                'kw' => $result->kw,
                'override_amount' => $result->amount,
                'total_amount' => $totalAmount,
                'paid' => $inReconPercentage,
                'percentage' => $payout,
                'status' => 'finalize',
                'during' => $result->during,
                'is_ineligible' => $isEligible ? 0 : 1,
            ]);

            if ($isEligible) {
                $overrides = UserOverrides::where(['pid' => $result->pid, 'user_id' => $userId, 'sale_user_id' => $result->sale_user_id, 'type' => $result->type, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['1', '2'])->get();
                foreach ($overrides as $override) {
                    $amount = UserOverrides::where(['pid' => $result->pid, 'user_id' => $userId, 'sale_user_id' => $override->sale_user_id, 'type' => $override->type, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->sum('amount') ?? 0;
                    $reconPaidAmount = ReconOverrideHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'overrider' => $override->sale_user_id, 'type' => $override->type, 'is_displayed' => '1'])->sum('paid') ?? 0;
                    if ($amount == $reconPaidAmount) {
                        $override->recon_status = 3;
                    } else {
                        $override->recon_status = 2;
                    }
                    $override->save();
                }
            }
        }
    }

    private function manageCommissionReconHistory($userId, $startDate, $endDate, $payout, $finalize)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);
        $isUpfront = $this->isUpfront;

        // Get internal worker sales
        $internalSales = SalesMaster::select('sale_masters.*')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->join('sale_master_process', 'sale_master_process.pid', 'sale_masters.pid')
            ->where(function ($subQuery) use ($userId) {
                $subQuery->where('sale_master_process.closer1_id', $userId)
                    ->orWhere('sale_master_process.closer2_id', $userId)
                    ->orWhere('sale_master_process.setter1_id', $userId)
                    ->orWhere('sale_master_process.setter2_id', $userId);
            });

        // Get external worker sales
        $externalSales = SalesMaster::select('sale_masters.*')
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->join('external_sale_worker', 'external_sale_worker.pid', 'sale_masters.pid')
            ->where('external_sale_worker.user_id', $userId);

        // Combine both internal and external sales
        $sales = $internalSales->union($externalSales)
            ->orderBy('pid', 'ASC')
            ->get();

        foreach ($sales as $result) {
            $isEligible = false;
            $finalDate = SaleProductMaster::select('id', 'pid', 'milestone_date')->where(['pid' => $result->pid, 'is_last_date' => 1])->first();
            if (! empty($finalDate->milestone_date) && $finalDate->milestone_date >= $startDate && $finalDate->milestone_date <= $endDate && empty($result->date_cancelled)) {
                $isEligible = true;
            }

            $commissions = UserCommission::selectRaw('sum(amount) as amount, amount_type, pid')
                ->where(['user_id' => $userId, 'pid' => $result->pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])
                ->whereIn('recon_status', ['1', '2'])->groupBy('amount_type')->get();
            foreach ($commissions as $commission) {
                // $reconPaidAmount = ReconCommissionHistory::where(['user_id' => $userId, 'pid' => $result->pid, 'is_deducted' => '0', 'type' => $commission->amount_type, 'is_displayed' => '1'])->sum("paid_amount");
                $reconPaidAmount = ReconCommissionHistory::where(['user_id' => $userId, 'pid' => $result->pid, 'is_displayed' => '1'])->sum('paid_amount');
                $remaining = $commission->amount - $reconPaidAmount;
                if ($remaining) {
                    $totalAmount = $remaining;
                    if ($remaining < 0) {
                        $paidAmount = $remaining;
                    } else {
                        $paidAmount = ($remaining * ($payout / 100));
                    }

                    if (! $isEligible) {
                        $paidAmount = (0 - $reconPaidAmount);
                    }

                    ReconCommissionHistory::create([
                        'pid' => $commission->pid,
                        'user_id' => $userId,
                        'finalize_id' => $finalize->id,
                        'status' => 'finalize',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'total_amount' => $totalAmount,
                        'paid_amount' => $paidAmount,
                        'payout' => $payout,
                        'type' => $commission->amount_type,
                        'is_ineligible' => $isEligible ? 0 : 1,
                    ]);
                }
            }

            if ($isUpfront) {

                $upfrontmone = UserCommission::where(['pid' => $result->pid, 'user_id' => $userId, 'amount_type' => 'm1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->sum('amount') ?? 0;
                $upfrontmtwo = UserCommission::where(['pid' => $result->pid, 'user_id' => $userId, 'amount_type' => 'm2', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->sum('amount') ?? 0;
                $paidUpfrontmone = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'type' => 'm1', 'is_deducted' => '1', 'is_displayed' => '1'])->sum('paid_amount') ?? 0;
                $paidUpfrontmtwo = ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId, 'type' => 'm2', 'is_deducted' => '1', 'is_displayed' => '1'])->sum('paid_amount') ?? 0;
                $finalmone = $upfrontmone + $paidUpfrontmone;
                $finalmtwo = $upfrontmtwo + $paidUpfrontmtwo;
                $sum = $finalmone + $finalmtwo;

                if ($finalmone || $finalmtwo) {
                    if ($isEligible) {
                        ReconCommissionHistory::create([
                            'pid' => $result->pid,
                            'user_id' => $userId,
                            'finalize_id' => $finalize->id,
                            'status' => 'finalize',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'total_amount' => $upfrontmone,
                            'paid_amount' => 0,
                            'payout' => $payout,
                            'type' => 'm1',
                            // 'is_ineligible' => $isEligible ? 0 : 1,
                            'is_deducted' => 1,
                        ]);
                        ReconCommissionHistory::create([
                            'pid' => $result->pid,
                            'user_id' => $userId,
                            'finalize_id' => $finalize->id,
                            'status' => 'finalize',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'total_amount' => $upfrontmtwo,
                            'paid_amount' => 0,
                            'payout' => $payout,
                            'type' => 'm2',
                            // 'is_ineligible' => $isEligible ? 0 : 1,
                            'is_deducted' => 1,
                        ]);

                    } else {

                        ReconCommissionHistory::create([
                            'pid' => $result->pid,
                            'user_id' => $userId,
                            'finalize_id' => $finalize->id,
                            'status' => 'finalize',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'total_amount' => $upfrontmone,
                            'paid_amount' => (0 - $finalmone),
                            'payout' => $payout,
                            'type' => 'm1',
                            // 'is_ineligible' => $isEligible ? 0 : 1,
                            'is_deducted' => 1,
                        ]);
                        ReconCommissionHistory::create([
                            'pid' => $result->pid,
                            'user_id' => $userId,
                            'finalize_id' => $finalize->id,
                            'status' => 'finalize',
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'total_amount' => $upfrontmtwo,
                            'paid_amount' => (0 - $finalmtwo),
                            'payout' => $payout,
                            'type' => 'm2',
                            // 'is_ineligible' => $isEligible ? 0 : 1,
                            'is_deducted' => 1,
                        ]);
                    }

                }
                // $reconpay =  ReconCommissionHistory::where(['pid' => $result->pid, 'user_id' => $userId])->first();

                //        UserCommission::where(["pid" => $result->pid, "user_id" => $userId, "amount_type" => "m1", "settlement_type" => "during_m2", "is_displayed" => "1"])->update(["amount"=>0]);
                //        UserCommission::where(["pid" => $result->pid, "user_id" => $userId, "amount_type" => "m2", "settlement_type" => "during_m2", "is_displayed" => "1"])->update(["amount"=>0]);
                //        $reconsum =  UserCommission::where(["pid" => $result->pid, "user_id" => $userId, "amount_type" => "reconciliation", "settlement_type" => "reconciliation", "is_displayed" => "1"])->sum("amount");
                //        UserCommission::where(["pid" => $result->pid, "user_id" => $userId, "amount_type" => "reconciliation", "settlement_type" => "reconciliation", "is_displayed" => "1"])->update(["amount"=>($reconsum+$sum)]);
            }

            if ($isEligible) {
                $commissions = UserCommission::where(['user_id' => $userId, 'pid' => $result->pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['1', '2'])->get();
                foreach ($commissions as $commission) {
                    $amount = UserCommission::where(['user_id' => $userId, 'pid' => $result->pid, 'amount_type' => $commission->amount_type, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->sum('amount');
                    $reconPaidAmount = ReconCommissionHistory::where(['user_id' => $userId, 'pid' => $result->pid, 'type' => $commission->amount_type, 'is_displayed' => '1'])->sum('paid_amount');
                    if ($amount == $reconPaidAmount) {
                        $commission->recon_status = 3;
                    } else {
                        $commission->recon_status = 2;
                    }
                    $commission->save();
                }
            }
        }
    }

    public function manageClawbackReconHistory($userId, $startDate, $endDate, $reconPayout, $finalize)
    {
        /* for recon clawback data */
        $reconClawbackData = ClawbackSettlement::where('clawback_type', 'reconciliation')
            ->where('payroll_id', 0)
            ->whereNull('pay_period_from')
            ->whereNull('pay_period_to')
            // ->where("status", 1)
            ->where('status', 3)
            ->where('recon_status', 1)
            ->where('user_id', $userId)
            ->join('sale_masters AS s_m_t_o_r', function ($join) {
                $join->on('s_m_t_o_r.pid', '=', 'clawback_settlements.pid');
            })->get();

        foreach ($reconClawbackData as $clawbackFinalizeData) {
            if ($clawbackFinalizeData->type == 'overrides' || $clawbackFinalizeData->type == 'recon-override') {
                $clawbackHistoryData = DB::table('recon_clawback_histories')->where('user_id', $clawbackFinalizeData->user_id)
                    ->where('pid', $clawbackFinalizeData->pid)
                    ->where('sale_user_id', $clawbackFinalizeData->sale_user_id)
                    ->where('adders_type', $clawbackFinalizeData->adders_type)
                    ->where('during', $clawbackFinalizeData->during)
                    ->where('type', $clawbackFinalizeData->type)
                    ->where('move_from_payroll', 0)
                    ->where('status', 'finalize');

                $body = [
                    'pid' => $clawbackFinalizeData->pid,
                    'user_id' => $clawbackFinalizeData->user_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'finalize',
                    'type' => $clawbackFinalizeData->type,
                    'move_from_payroll' => 0,
                    'finalize_id' => $finalize->id ?? 0,
                    // 'finalize_count' => $finalizeSendCount,
                    'total_amount' => $clawbackFinalizeData->clawback_amount,
                    'paid_amount' => $clawbackFinalizeData->clawback_amount,
                    'payout' => $reconPayout,
                    'sale_user_id' => $clawbackFinalizeData->sale_user_id,
                    'adders_type' => $clawbackFinalizeData->adders_type,
                    'during' => $clawbackFinalizeData->during,
                ];

                if ($clawbackHistoryData->exists()) {
                    $clawbackHistoryData->update($body);
                } else {
                    ReconClawbackHistory::create($body);
                }

                ClawbackSettlement::where('id', $clawbackFinalizeData->id)->update(['recon_status' => '3']);

            } else {
                $clawbackHistoryData = DB::table('recon_clawback_histories')->where('user_id', $clawbackFinalizeData->user_id)
                    ->where('pid', $clawbackFinalizeData->pid)
                    ->where('adders_type', $clawbackFinalizeData->adders_type)
                    ->where('during', $clawbackFinalizeData->during)
                    ->where('type', $clawbackFinalizeData->type)
                    ->where('move_from_payroll', 0)
                    ->where('status', 'finalize');

                $body = [
                    'pid' => $clawbackFinalizeData->pid,
                    'user_id' => $clawbackFinalizeData->user_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'finalize',
                    'type' => $clawbackFinalizeData->type,
                    'move_from_payroll' => 0,
                    'finalize_id' => $finalize->id ?? 0,
                    // 'finalize_count' => $finalizeSendCount,
                    'total_amount' => $clawbackFinalizeData->clawback_amount,
                    'paid_amount' => $clawbackFinalizeData->clawback_amount,
                    'payout' => $reconPayout,
                    'sale_user_id' => $clawbackFinalizeData->sale_user_id,
                    'adders_type' => $clawbackFinalizeData->adders_type,
                    'during' => $clawbackFinalizeData->during,
                ];

                if ($clawbackHistoryData->exists()) {
                    $clawbackHistoryData->update($body);
                } else {
                    ReconClawbackHistory::create($body);
                }

                ClawbackSettlement::where('id', $clawbackFinalizeData->id)->update(['recon_status' => '3']);

            }
        }

        /* move to recon clawback */
        $userData = User::with(['positionDetail.payFrequency.frequencyType'])
            ->where('id', $userId)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });
        $payFrequencyTypes = $userData->unique();
        $moveToReconClawbackSettlement = DB::table('clawback_settlements as clawback_settlement_move_to_recon')
            ->join('sale_masters AS s_m_t_o_r', function ($join) {
                $join->on('s_m_t_o_r.pid', '=', 'clawback_settlement_move_to_recon.pid');
            })
            ->where('clawback_settlement_move_to_recon.user_id', $userId)
            ->where('clawback_settlement_move_to_recon.status', 6)
            ->where('clawback_settlement_move_to_recon.is_move_to_recon', 1);

        $moveToClawbackSettlementResults = new Collection;
        foreach ($payFrequencyTypes as $frequency) {
            $results = [];
            $query = clone $moveToReconClawbackSettlement;

            if ($frequency === 'Weekly') {
                $query->join('weekly_pay_frequencies as w_p_f', function ($join) {
                    $join->on('w_p_f.pay_period_from', '=', 'clawback_settlement_move_to_recon.pay_period_from')
                        ->on('w_p_f.pay_period_to', '=', 'clawback_settlement_move_to_recon.pay_period_to')
                        ->where('w_p_f.closed_status', '=', 1);
                });
                $results = $query->get();
            } elseif ($frequency === 'Monthly') {
                $query->join('monthly_pay_frequencies as m_p_f', function ($join) {
                    $join->on('m_p_f.pay_period_from', '=', 'clawback_settlement_move_to_recon.pay_period_from')
                        ->on('m_p_f.pay_period_to', '=', 'clawback_settlement_move_to_recon.pay_period_to')
                        ->where('m_p_f.closed_status', '=', 1);
                });
                $results = $query->get();
            }
            $moveToClawbackSettlementResults = $moveToClawbackSettlementResults->merge($results);
        }

        foreach ($moveToClawbackSettlementResults as $clawbackSettlementFinalizeData) {
            if ($clawbackSettlementFinalizeData->type == 'overrides' || $clawbackSettlementFinalizeData->type == 'recon-override') {
                $clawbackHistoryData = DB::table('recon_clawback_histories')->where('user_id', $clawbackSettlementFinalizeData->user_id)
                    ->where('pid', $clawbackSettlementFinalizeData->pid)
                    ->where('sale_user_id', $clawbackSettlementFinalizeData->sale_user_id)
                    ->where('adders_type', $clawbackSettlementFinalizeData->adders_type)
                    ->where('during', $clawbackSettlementFinalizeData->during)
                    ->where('type', $clawbackSettlementFinalizeData->type)
                    ->where('move_from_payroll', 1)
                    ->where('status', 'finalize');

                $body = [
                    'pid' => $clawbackSettlementFinalizeData->pid,
                    'user_id' => $clawbackSettlementFinalizeData->user_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'finalize',
                    'type' => $clawbackSettlementFinalizeData->type,
                    'move_from_payroll' => 1,
                    // 'finalize_count' => $finalizeSendCount,
                    'total_amount' => $clawbackSettlementFinalizeData->clawback_amount,
                    'paid_amount' => $clawbackSettlementFinalizeData->clawback_amount,
                    'payout' => $reconPayout,
                    'sale_user_id' => $clawbackSettlementFinalizeData->sale_user_id,
                    'adders_type' => $clawbackSettlementFinalizeData->adders_type,
                    'during' => $clawbackSettlementFinalizeData->during,
                ];

                if ($clawbackHistoryData->exists()) {
                    $clawbackHistoryData->update($body);
                } else {
                    ReconClawbackHistory::create($body);
                }
            } else {
                $clawbackHistoryData = DB::table('recon_clawback_histories')->where('user_id', $clawbackSettlementFinalizeData->user_id)
                    ->where('pid', $clawbackSettlementFinalizeData->pid)
                    ->where('adders_type', $clawbackSettlementFinalizeData->adders_type)
                    ->where('during', $clawbackSettlementFinalizeData->during)
                    ->where('type', $clawbackSettlementFinalizeData->type)
                    ->where('move_from_payroll', 1)
                    ->where('status', 'finalize');

                $body = [
                    'pid' => $clawbackSettlementFinalizeData->pid,
                    'user_id' => $clawbackSettlementFinalizeData->user_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'finalize',
                    'type' => $clawbackSettlementFinalizeData->type,
                    'move_from_payroll' => 1,
                    // 'finalize_count' => $finalizeSendCount,
                    'total_amount' => $clawbackSettlementFinalizeData->clawback_amount,
                    'paid_amount' => $clawbackSettlementFinalizeData->clawback_amount,
                    'payout' => $reconPayout,
                    'sale_user_id' => $clawbackSettlementFinalizeData->sale_user_id,
                    'adders_type' => $clawbackSettlementFinalizeData->adders_type,
                    'during' => $clawbackSettlementFinalizeData->during,
                ];

                if ($clawbackHistoryData->exists()) {
                    $clawbackHistoryData->update($body);
                } else {
                    ReconClawbackHistory::create($body);
                }
            }
        }
    }

    public function manageDeductionReconHistory($userId, $startDate, $endDate, $totalCommission, $finalize)
    {
        $userData = User::with(['positionDetail.payFrequency.frequencyType'])
            ->where('id', $userId)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                // Collect pay frequencies, ensuring no null values
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });
        $payFrequencyTypes = $userData->unique();
        $deductionQuery = DB::table('payroll_deductions As p_d_t')
            ->join('users', 'users.id', '=', 'p_d_t.user_id')
            ->where('p_d_t.status', 3)
            ->where('p_d_t.is_move_to_recon', 1)
            ->where('p_d_t.is_move_to_recon_paid', 0)
            ->where('p_d_t.user_id', $userId)
            ->whereBetween('p_d_t.pay_period_from', [$startDate, $endDate])
            ->whereBetween('p_d_t.pay_period_to', [$startDate, $endDate]);

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

        foreach ($deductionsResults as $key => $value) {
            $reconDeductionHistoryData = DB::table('recon_deduction_histories')->where('user_id', $value->user_id)
                ->where('cost_center_id', $value->cost_center_id)
                ->where('status', 'finalize');

            $body = [
                'user_id' => $value->user_id,
                'cost_center_id' => $value->cost_center_id,
                'amount' => $value->amount,
                'limit' => $value->limit,
                'total' => $value->total,
                'outstanding' => $value->outstanding,
                'subtotal' => $value->subtotal,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'finalize',
                'finalize_id' => $finalize->id ?? 0,
                // "finalize_count" => $finalizeSendCount,
            ];
            if ($reconDeductionHistoryData->exists()) {
                $reconDeductionHistoryData->update($body);
            } else {
                ReconDeductionHistory::create($body);
            }
        }
    }
}
