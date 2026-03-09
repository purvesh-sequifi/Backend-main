<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\Positions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationStatusForSkipedUser;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconDeductionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
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

    public function reconciliationFinalizeDraft(Request $request)
    {
        DB::beginTransaction();
        try {
            $validate = Validator::make($request->all(), [
                'start_date' => 'required',
                'end_date' => 'required',
                'user_id' => 'required',
                'recon_payout' => 'required|numeric|between:0,100',
                'office_id' => [
                    'required',
                    function ($attribute, $value, $fail) { // NOSONAR
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
                    function ($attribute, $value, $fail) { // NOSONAR
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

            $userIds = $request->user_id;
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $officeId = $this->prepareIdArray($request->office_id);
            $positionId = $this->prepareIdArray($request->position_id);
            $skipUserIds = $request->skip_user_id;
            /* check if both are get "all" value in the body */
            if (! $positionId && ! $officeId) {
                $officeId = 'all';
                $positionId = 'all';
            }

            /* check finalize position and location condition */
            $checkPositionLocation = $this->checkLocationAndPositionFinalize($officeId, $positionId, $userIds);
            if (json_decode($checkPositionLocation->getContent(), true)['status'] === 'error') {
                return response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => false,
                    'message' => json_decode($checkPositionLocation->getContent(), true)['message'],
                ], 400);
            }
            $reconPayout = $request->recon_payout;
            $finalizeUserId = $this->checkSkipUserIds($startDate, $endDate, $userIds);
            $res = $this->processReconciliation($finalizeUserId, $startDate, $endDate, $reconPayout);

            /* reset skip user data from this pay-period */
            if ($skipUserIds) {
                ReconciliationStatusForSkipedUser::whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->whereIn('user_id', $skipUserIds)
                    ->delete();
            }
            if ($res['success']) {
                DB::commit();
                $response = response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => true,
                    'message' => 'Recon has been finalized successfully!!',
                ]);
            } else {
                DB::rollBack();
                $response = response()->json([
                    'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                    'status' => false,
                    'message' => $res['message'],
                ], 400);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = response()->json([
                'ApiName' => self::FINALIZE_API_DRAFT_NAME,
                'status' => false,
                'message' => 'Error !! '.$th->getMessage().' .At line number '.$th->getLine(),
            ], 500);
        }

        return $response;
    }

    private function checkLocationAndPositionFinalize($locationId, $positionId, $userId)
    {
        $response = response()->json([
            'status' => 'success',
            'message' => 'Proceeding with finalization.',
        ]);

        if (empty($locationId) && empty($positionId)) {
            $checkFinalizePosition = ReconciliationFinalizeHistory::where('status', 'finalize')->exists();
            if ($checkFinalizePosition) {
                $response = response()->json([
                    'status' => 'error',
                    'message' => self::FINALIZE_POSITION_ERROR_MSG,
                ]);
            }
        } elseif (empty($locationId) && ! empty($positionId)) {
            $checkFinalizePosition = ReconciliationFinalizeHistory::where('status', 'finalize')
                ->whereIn('position_id', $positionId)
                ->exists();
            if ($checkFinalizePosition) {
                $response = response()->json([
                    'status' => 'error',
                    'message' => self::FINALIZE_POSITION_ERROR_MSG,
                ]);
            }
        } elseif (! empty($locationId) && empty($positionId)) {
            $checkFinalizePosition = ReconciliationFinalizeHistory::where('status', 'finalize')
                ->whereIn('office_id', $locationId)
                ->exists();
            if ($checkFinalizePosition) {
                $response = response()->json([
                    'status' => 'error',
                    'message' => self::FINALIZE_POSITION_ERROR_MSG,
                ]);
            }
        } else {
            $checkFinalizePosition = ReconciliationFinalizeHistory::where('status', 'finalize')->whereIn('user_id', $userId)->exists();
            if ($checkFinalizePosition) {
                $response = response()->json([
                    'status' => 'error',
                    'message' => self::FINALIZE_POSITION_ERROR_MSG,
                ]);
            }
        }

        return $response;
    }

    private function prepareIdArray($idString)
    {
        return in_array('all', $idString) ? [] : $idString;
    }

    private function processReconciliation($userIds, $startDate, $endDate, $reconPayout)
    {
        try {
            $finalData = (new ReconPayrollController)->getReconData($userIds, $startDate, $endDate, $reconPayout);
            $finalData = $finalData['data'];
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

            /* finalize count */
            $finalizeCount = ReconciliationFinalizeHistory::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)->orderBy('id', 'Desc')->first();
            if ($finalizeCount) {
                $finalizeSendCount = floatval($finalizeCount->finalize_count) + 1;
            } else {
                $finalizeSendCount = 1;
            }

            foreach ($finalData as $final) {
                $uniquePids = implode(',', $final['pids']);
                $userId = $final['user_id'];
                $userData = User::find($userId);
                $finalizeData = ReconciliationFinalizeHistory::where(['pid' => $uniquePids, 'user_id' => $userId, 'status' => 'finalize'])->get();

                $totalCommission = $final['commissionWithholding'] ?? 0;
                $totalOverride = $final['overrideDue'] ?? 0;
                $totalAdjustment = $final['adjustments'] ?? 0;
                $totalDeductionAmount = $final['deductions'] ?? 0;
                $totalClawback = (-1 * $final['clawbackDue']) ?? 0;
                $percentagePayAmount = 0;
                if ($totalCommission < 0.5) {
                    $paidCommission = floatval($totalCommission);
                } else {
                    $paidCommission = floatval($totalCommission) * ($reconPayout / 100);
                    $percentagePayAmount = $paidCommission;
                }

                if ($totalOverride < 0.5) {
                    $paidOverride = floatval($totalOverride);
                } else {
                    $paidOverride = floatval($totalOverride) * ($reconPayout / 100);
                    $percentagePayAmount = $paidOverride;
                }

                if ($totalOverride > 0.5 && $totalCommission > 0.5) {
                    $percentagePayAmount = $paidCommission + $paidOverride;
                }

                $netAmount = $paidCommission + $paidOverride;
                $grossAmount = $netAmount - $totalClawback + $totalAdjustment + $totalDeductionAmount;
                $body = [
                    'user_id' => $userId,
                    'pid' => $uniquePids,
                    'office_id' => $userData->office_id,
                    'position_id' => $userData->sub_position_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'adjustments' => $totalAdjustment,
                    'deductions' => $totalDeductionAmount,
                    'clawback' => $totalClawback,
                    'payout' => $reconPayout,
                    'commission' => $totalCommission,
                    'override' => $totalOverride,
                    'status' => 'finalize',
                    'paid_commission' => $paidCommission, // paid commission means move to payroll
                    'paid_override' => $paidOverride, // Initialize these fields
                    'type' => 'payroll_reconciliation',
                    'finalize_count' => $finalizeSendCount,
                    'net_amount' => $netAmount,
                    'gross_amount' => $grossAmount,
                    'percentage_pay_amount' => $percentagePayAmount,
                ];

                if (! $finalizeData->isEmpty()) {
                    ReconciliationFinalizeHistory::where(['pid' => $uniquePids, 'user_id' => $userId, 'status' => 'finalize', 'is_displayed' => '1'])->update($body);
                } else {
                    ReconciliationFinalizeHistory::create($body);
                }

                /* recon adjustment update */
                ReconAdjustment::whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $userData->id)
                    ->whereNull('payroll_status')
                    ->update([
                        'payroll_status' => 'finalize',
                        'finalize_count' => $finalizeSendCount,
                    ]);

                /* Manage user over-ride recon data after finalize */
                if ($totalCommission != 0) {
                    $this->manageCommissionReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount, $totalCommission);
                }
                if ($totalOverride != 0) {
                    $this->manageOverrideReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount, $totalOverride);
                }
                $this->manageClawbackReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount, $totalCommission);
                $this->manageDeductionReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount, $totalCommission);
            }

            return ['success' => true, 'message' => 'Success!'];
        } catch (\Throwable $th) {
            return ['success' => false, 'message' => $th->getMessage().' '.$th->getLine()];
        }
    }

    private function manageOverrideReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount, $totalOverride)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        if ($totalOverride < 1) {
            $reconPayout = 100;
        }

        $overrideData = UserOverrides::where([
            'user_id' => $userId,
            'overrides_settlement_type' => 'reconciliation',
            // "status" => 1,
            'status' => 3,
            'is_displayed' => '1',
        ])->join('sale_masters AS s_m_t_o_r', function ($join) use ($startDate, $endDate) {
            $join->on('s_m_t_o_r.pid', '=', 'user_overrides.pid')
                ->whereBetween('s_m_t_o_r.customer_signoff', [$startDate, $endDate])
                ->whereNull('s_m_t_o_r.date_cancelled');
        })->get();

        foreach ($overrideData as $overrideFinalizeData) {
            $userOverride = User::find($overrideFinalizeData->sale_user_id);
            $salesData = SalesMaster::where('pid', $overrideFinalizeData->pid)->first();

            $overrideHistoryData = DB::table('recon_override_history')->where('user_id', $overrideFinalizeData->user_id)
                ->where('pid', $overrideFinalizeData->pid)
                ->where('type', $overrideFinalizeData->type)
                ->where('overrider', $overrideFinalizeData->sale_user_id)
                ->where('move_from_payroll', 0)
                ->where('status', 'finalize');

            $paidAmount = ReconOverrideHistory::where('user_id', $overrideFinalizeData->user_id)
                ->where('pid', $overrideFinalizeData->pid)
                ->where('type', $overrideFinalizeData->type)
                ->where('overrider', $overrideFinalizeData->sale_user_id)
                ->where('status', 'payroll')
                ->where('move_from_payroll', 0)
                ->sum('paid');

            $paiAmount = $paidAmount ?? 0;
            $is_ineligible = 1;
            $m2Date = Carbon::parse($overrideFinalizeData->m2_date);
            $totalPaidAmount = $overrideFinalizeData->amount - $paiAmount;
            if ($m2Date->between($startDate, $endDate)) {
                $is_ineligible = 0;
            }

            if ($totalPaidAmount != 0) {
                $body = [
                    'override_id' => $overrideFinalizeData->id,
                    'user_id' => $overrideFinalizeData->user_id,
                    'pid' => $overrideFinalizeData->pid,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'customer_name' => $salesData->customer_name,
                    'overrider' => $userOverride->id, // sale user_id
                    'type' => $overrideFinalizeData->type,
                    'kw' => $overrideFinalizeData->kw,
                    'override_amount' => $overrideFinalizeData->amount,
                    'total_amount' => $totalPaidAmount,
                    'paid' => ($totalPaidAmount) * ($reconPayout / 100),
                    'percentage' => $reconPayout,
                    'status' => 'finalize',
                    'finalize_count' => $finalizeSendCount,
                    'move_from_payroll' => 0,
                    'during' => $overrideFinalizeData->during,
                    'is_ineligible' => $is_ineligible,
                ];
                if ($overrideHistoryData->exists()) {
                    $overrideHistoryData->update($body);
                } else {
                    ReconOverrideHistory::create($body);
                }
            }
        }

        /* move to recon override query */
        $userData = User::with(['positionDetail.payFrequency.frequencyType'])
            ->where('id', $userId)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                // Collect pay frequencies, ensuring no null values
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });
        $payFrequencyTypes = $userData->unique();
        $moveToReconOverrideData = DB::table('user_overrides as u_o_t_m_t_r')
            ->join('sale_masters AS s_m_t_o_r', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t_o_r.pid', '=', 'u_o_t_m_t_r.pid')
                    ->whereBetween('s_m_t_o_r.customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t_o_r.date_cancelled');
            })
            ->where('u_o_t_m_t_r.user_id', $userId)
            ->where('u_o_t_m_t_r.status', 6)
            ->where('u_o_t_m_t_r.is_displayed', '1')
            ->where('u_o_t_m_t_r.is_move_to_recon', 1);

        $moveToReconOverridesResults = new Collection;
        foreach ($payFrequencyTypes as $frequency) {
            $results = [];
            $query = clone $moveToReconOverrideData;

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

        foreach ($moveToReconOverridesResults as $overrideFinalizeData) {
            $userOverride = User::find($overrideFinalizeData->sale_user_id);
            $salesData = SalesMaster::where('pid', $overrideFinalizeData->pid)->first();

            $overrideHistoryData = DB::table('recon_override_history')->where('user_id', $overrideFinalizeData->user_id)
                ->where('pid', $overrideFinalizeData->pid)
                ->where('type', $overrideFinalizeData->type)
                ->where('overrider', $overrideFinalizeData->sale_user_id)
                ->where('move_from_payroll', 1)
                ->where('status', 'finalize');

            $paidAmount = ReconOverrideHistory::where('user_id', $overrideFinalizeData->user_id)
                ->where('pid', $overrideFinalizeData->pid)
                ->where('type', $overrideFinalizeData->type)
                ->where('overrider', $overrideFinalizeData->sale_user_id)
                ->where('status', 'payroll')
                ->where('move_from_payroll', 1)
                ->sum('paid');

            $paiAmount = 0;
            $is_ineligible = 1;
            $m2Date = Carbon::parse($overrideFinalizeData->m2_date);
            $totalPaidAmount = $overrideFinalizeData->amount - $paiAmount;
            if ($m2Date->between($startDate, $endDate)) {
                $is_ineligible = 0;
                $paiAmount = $paidAmount ?? 0;
            }
            if ($totalPaidAmount != 0) {
                $body = [
                    'override_id' => $overrideFinalizeData->id,
                    'pid' => $overrideFinalizeData->pid,
                    'user_id' => $overrideFinalizeData->user_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'finalize',
                    'customer_name' => $salesData->customer_name,
                    'overrider' => $userOverride->id, // sale user_id
                    'type' => $overrideFinalizeData->type,
                    'during' => $overrideFinalizeData->during,
                    'move_from_payroll' => 1,
                    'kw' => $overrideFinalizeData->kw,
                    'override_amount' => $overrideFinalizeData->amount,
                    'total_amount' => $totalPaidAmount,
                    'paid' => ($totalPaidAmount) * ($reconPayout / 100),
                    'percentage' => $reconPayout,
                    'finalize_count' => $finalizeSendCount,
                    'is_ineligible' => $is_ineligible,
                ];
                if ($overrideHistoryData->exists()) {
                    $overrideHistoryData->update($body);
                } else {
                    ReconOverrideHistory::create($body);
                }
            }
        }
    }

    private function manageCommissionReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount, $totalCommission)
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $userData = User::with(['positionDetail.payFrequency.frequencyType'])
            ->where('id', $userId)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                // Collect pay frequencies, ensuring no null values
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });
        $payFrequencyTypes = $userData->unique();

        if ($totalCommission < 1) {
            $reconPayout = 100;
        }

        /*  $withHeldData = UserReconciliationWithholding::where(function ($query) use ($userId) {
             $query->where('closer_id', $userId)
                 ->orWhere("setter_id", $userId);
         })->join("sale_masters AS s_m_t_o_r", function ($join) use ($startDate, $endDate) {
             $join->on("s_m_t_o_r.pid", "=", "user_reconciliation_withholds.pid")->whereBetween("s_m_t_o_r.customer_signoff", [$startDate, $endDate])->whereNull("date_cancelled");
         })->where("user_reconciliation_withholds.status", "unpaid")->get(); */

        $withHeldData = UserCommission::where('user_id', $userId)
            ->where(['amount_type' => 'reconciliation', 'settlement_type' => 'reconciliation'])
            ->join('sale_masters AS s_m_t_o_r', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t_o_r.pid', '=', 'user_commission.pid')->whereBetween('s_m_t_o_r.customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled');
            })->where('user_commission.status', 3)->get();

        foreach ($withHeldData as $value) {
            // $userData = $value->closer_id ? $value->closer : $value->setter;
            $getFinalizeData = DB::table('recon_commission_histories')
                ->where(['user_id' => $value->user_id, 'pid' => $value->pid, 'status' => 'finalize', 'type' => 'recon-commission', 'move_from_payroll' => '0']);

            $paidAmount = ReconCommissionHistory::where('user_id', $value->user_id)
                ->where('pid', $value->pid)
                ->where('type', 'recon-commission')
                ->where('status', 'payroll')
                ->where('move_from_payroll', '0')
                ->where('is_displayed', '1')
                ->sum('paid_amount');

            $totalAmount = 0;
            $is_ineligible = 1;
            $m2Date = Carbon::parse($value->m2_date);
            if ($m2Date->between($startDate, $endDate)) {
                $is_ineligible = 0;
                // $totalAmount = $value->withhold_amount - $paidAmount;
                $totalAmount = $value->amount - $paidAmount;
            }
            // if ($totalAmount != 0) {
            $body = [
                'pid' => $value->pid,
                'user_id' => $value->user_id,
                'status' => 'finalize',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'finalize_count' => $finalizeSendCount,
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount * ($reconPayout / 100),
                'payout' => $reconPayout,
                'type' => 'recon-commission',
                'move_from_payroll' => '0',
                'is_ineligible' => $is_ineligible,
            ];

            if ($getFinalizeData->exists()) {
                $getFinalizeData->update($body);
            } else {
                ReconCommissionHistory::create($body);
            }
            // }
        }

        /* move to recon commission data */
        $moveToReconCommissionQuery = DB::table('user_commission as user_commission_move_to_recon')
            ->join('sale_masters as s_m_t', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t.pid', '=', 'user_commission_move_to_recon.pid')
                    ->whereBetween('s_m_t.customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t.date_cancelled');
            })
            ->join('users', 'users.id', 'user_commission_move_to_recon.user_id')
            ->where('user_commission_move_to_recon.status', 6)
            ->where('user_commission_move_to_recon.is_move_to_recon', 1)
            ->where('user_commission_move_to_recon.is_displayed', '1')
            ->where('user_commission_move_to_recon.user_id', $userId);

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

        foreach ($moveToReconCommissionResults as $result) {
            $userData = User::find($result->user_id);
            $moveToReconGetFinalizeData = DB::table('recon_commission_histories')
                ->where(['user_id' => $userData->id, 'pid' => $result->pid, 'type' => $result->amount_type, 'status' => 'finalize', 'move_from_payroll' => 1]);

            $moveToReconPaidAmount = ReconCommissionHistory::where('user_id', $userData->id)
                ->where('pid', $result->pid)
                ->where('type', $result->amount_type)
                ->where('move_from_payroll', '1')
                ->where('status', 'payroll')
                ->sum('paid_amount');

            $totalAmount = $result->amount - $moveToReconPaidAmount;

            $is_ineligible = 1;
            $m2Date = Carbon::parse($result->m2_date);
            if ($m2Date->between($startDate, $endDate)) {
                $is_ineligible = 0;
            }
            if ($totalAmount != 0) {
                $body = [
                    'pid' => $result->pid,
                    'user_id' => $userData->id,
                    'status' => 'finalize',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'finalize_count' => $finalizeSendCount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $totalAmount * ($reconPayout / 100),
                    'payout' => $reconPayout,
                    'type' => $result->amount_type,
                    'move_from_payroll' => '1',
                    'is_ineligible' => $is_ineligible,
                ];

                if ($moveToReconGetFinalizeData->exists()) {
                    $moveToReconGetFinalizeData->update($body);
                } else {
                    ReconCommissionHistory::create($body);
                }
            }
        }
    }

    public function manageClawbackReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount)
    {
        /* for recon clawback data */
        $reconClawbackData = ClawbackSettlement::where('clawback_type', 'reconciliation')
            ->where('payroll_id', 0)
            ->whereNull('pay_period_from')
            ->whereNull('pay_period_to')
            ->where('status', 1)
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
                    'finalize_count' => $finalizeSendCount,
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
                    'finalize_count' => $finalizeSendCount,
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
                    'finalize_count' => $finalizeSendCount,
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
                    'finalize_count' => $finalizeSendCount,
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

    public function manageDeductionReconHistory($userId, $startDate, $endDate, $reconPayout, $finalizeSendCount)
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
            ->where('p_d_t.status', 6)
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
                'finalize_count' => $finalizeSendCount,
            ];
            if ($reconDeductionHistoryData->exists()) {
                $reconDeductionHistoryData->update($body);
            } else {
                ReconDeductionHistory::create($body);
            }
        }
    }

    public function checkSkipUserIds($startDate, $endDate, $userIds)
    {
        $getSkipUserData = ReconciliationStatusForSkipedUser::where('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->pluck('user_id')->unique();

        return $userIds = array_diff($userIds, $getSkipUserData->toArray());
    }
}
