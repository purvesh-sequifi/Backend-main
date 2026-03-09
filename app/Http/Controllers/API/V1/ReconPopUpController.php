<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Locations;
use App\Models\MonthlyPayFrequency;
use App\Models\PayrollDeductions;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconClawbackHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconDeductionHistory;
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
use Illuminate\Support\Collection;
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

    /**
     *This function is used for the get recon-commission-popup-details.
     *
     * @return \Illuminate\Http\Response
     */
    public function reconCommissionPopup(Request $request, $id)
    {
        $request->merge([
            'payout' => (int) $request->query('payout'),
        ]);

        // Validate the request
        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date', self::DATE_FORMATE],
            'end_date' => ['required', 'date', self::DATE_FORMATE],
            'payout' => ['required', 'integer', 'min:1', 'max:100'],
            'is_finalize' => self::FINALIZE_VALIDATE,
        ]);

        // If validation fails, return an error response
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

        $responseArray = $this->getReconCommissionData($userId, $startDate, $endDate, $search, $payout, $isFinalize);
        $total = array_reduce($responseArray->toArray(), function ($carry, $item) {
            if (! $item['is_ineligible']) {
                $carry['in_recon'] += $item['in_recon'];
                $carry['in_recon_percentage'] += $item['in_recon_percentage'];
            }

            return $carry;
        }, ['in_recon' => 0, 'in_recon_percentage' => 0]);

        /* add one condition if total amount is negative then not apply recon payout is apply 100% payout */
        if (array_sum($total) < 0) {
            $responseArray = $this->getReconCommissionData($userId, $startDate, $endDate, $search, 100, $isFinalize);
            $total = array_reduce($responseArray->toArray(), function ($carry, $item) {
                if (! $item['is_ineligible']) {
                    $carry['in_recon'] += $item['in_recon'];
                    $carry['in_recon_percentage'] += $item['in_recon_percentage'];
                }

                return $carry;
            }, ['in_recon' => 0, 'in_recon_percentage' => 0]);
        }

        // Sort response array by pid
        $response = $responseArray->toArray();
        usort($response, function ($a, $b) {
            return strcmp($a['pid'], $b['pid']);
        });

        // Return the response with the transformed data and subtotal
        return response()->json([
            'ApiName' => 'Recon Commission Breakdown Api',
            'status' => true,
            'message' => 'Successfully.',
            'total_data' => count($response),
            'data' => $response,
            'subtotal' => $total['in_recon_percentage'],
            'in_recon_subtotal' => $total['in_recon'],
        ]);
    }

    /**
     * Fetch reconciliation data based on the given criteria.
     *
     * @param  int  $id
     */
    public function getReconCommissionData($userId, string $startDate, string $endDate, ?string $search, $payout, $isFinalize): Collection
    {
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);
        $pids = $this->getPids($userId, $startDate, $endDate, 'commission', $search);

        $eligiblePids = $pids['eligiblePids'];
        $getAllPids = $pids['allPids'];
        $isUpfront = $this->isUpfront;

        $userData = User::find($userId);

        return $getAllPids->transform(function ($result) use ($userData, $payout, $eligiblePids, $startDate, $endDate, $isFinalize, $isUpfront) {
            $inReconAmount = 0;
            $inReconPercentage = 0;

            $totalCommission = UserCommission::where('pid', $result->pid)->where('user_id', $userData->id)->sum('amount') ?? 0;
            $totalReconAmount = DB::table('user_commission as recon_commission_table')
                ->where(function ($query) {
                    $query->where('settlement_type', 'reconciliation')
                        ->orWhereIn('amount_type', ['m1', 'm2', 'm2 update', 'reconciliation']);
                })
                ->where('user_id', $userData->id)
                ->where('status', 3)
                ->where('pid', $result->pid)
                ->where(function ($query) {
                    $query->where('is_move_to_recon', 0)
                        ->orWhere('is_move_to_recon', 1);
                })
                ->sum('amount') ?? 0;

            $reconCommissionData = DB::table('user_commission as recon_commission_table')
                ->where('settlement_type', 'reconciliation')
                ->where('user_id', $userData->id)
                ->where('status', 3)
                ->where('pid', $result->pid)
                ->where(function ($query) {
                    $query->where('is_move_to_recon', 0)
                        ->orWhere('is_move_to_recon', 1);
                })
                ->first();

            $paidReconAmount = ReconCommissionHistory::where([
                'pid' => $result->pid,
                'user_id' => $userData->id,
                'move_from_payroll' => 0,
            ]);

            if ($isFinalize) {
                $paidReconAmount->where('status', 'payroll');
            }

            $upfrontAmount = 0;
            if ($isUpfront == 1) {
                $upfrontAmount = UserCommission::where('pid', $result->pid)->where('user_id', $userData->id)->where('amount_type', 'm1')->sum('amount') ?? 0;

            }

            if (in_array($result->pid, $eligiblePids)) {
                $inReconAmount = $totalReconAmount - $paidReconAmount->sum('paid_amount') ?? 0;
                $inReconPercentage = $inReconAmount * ($payout / 100);
                $paidPreviously = $totalCommission - $upfrontAmount - $totalReconAmount + $paidReconAmount->sum('paid_amount') ?? 0;
                $inReconAmount = $inReconAmount - $inReconPercentage;

                // $totalCommission = $totalCommission - $upfrontAmount;

            } else {
                if ($isUpfront == 0) {
                    $upfrontAmount = UserCommission::where('pid', $result->pid)->where('user_id', $userData->id)->where('amount_type', 'm1')->sum('amount') ?? 0;
                }
                $paidPreviously = $upfrontAmount;
                $inReconPercentage = (0 - $upfrontAmount);
                $inReconAmount = 0;
            }

            /* if ($totalReconAmount < 0.5) {
                $inReconAmount = $totalReconAmount - $paidReconAmount->sum("paid_amount") ?? 0;
                $inReconPercentage = $inReconAmount * 1;
            } */

            if ($totalReconAmount < 0) {
                $inReconAmount = $totalReconAmount - $paidReconAmount->sum('paid_amount') ?? 0;
                $inReconPercentage = $inReconAmount * 1;
            }

            /* get recon adjustment details */
            $reconAdjustment = $this->reconAdjustmentDetails($result->pid, $userData->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'), 'commission');

            return [
                'id' => $reconCommissionData?->id,
                'pid' => $result->pid,
                'user_id' => $userData->id,
                'state_id' => ucfirst($result->state_id),
                'customer_name' => ucfirst($result->customer_name),
                'customer_state' => ucfirst($result->customer_state),
                'rep_redline' => $userData->redline,
                'kw' => $result->kw,
                'net_epc' => $result->net_epc,
                'epc' => $result->epc,
                'adders' => $result->adders,
                'type' => ucfirst($reconCommissionData?->amount_type ?? '-'),
                'amount' => $totalCommission ?? 0,
                // 'amount' => $totalCommission - $upfrontAmount,
                'paid' => $paidPreviously,
                'in_recon' => $inReconAmount,
                'in_recon_percentage' => $inReconPercentage,
                'position_id' => $reconAdjustment['position_id'],
                'sub_position_id' => $reconAdjustment['sub_position_id'],
                'is_super_admin' => $reconAdjustment['is_super_admin'],
                'is_manager' => $reconAdjustment['is_manager'],
                'adjustment_amount' => $reconAdjustment['adjustment_amount'],
                'adjustment_comment' => $reconAdjustment['adjustment_comment'],
                'adjustment_by' => $reconAdjustment['adjustment_by'],
                'comment_status' => $reconAdjustment['comment_status'],
                'is_move_to_recon' => $reconCommissionData?->is_move_to_recon,
                'is_ineligible' => in_array($result->pid, $eligiblePids) ? 0 : 1,
                'is_upfront' => $isUpfront,
                'date_cancelled' => $result->date_cancelled,
            ];
        });
        /* old code bkp start */

        // $reconData = DB::table('user_reconciliation_withholds as u_r_w_h_t')
        //     ->select(
        //         'u_r_w_h_t.id as withhold_id',
        //         'u_r_w_h_t.pid',
        //         'u_r_w_h_t.withhold_amount as amount',
        //         's_m_t.customer_name',
        //         's_m_t.customer_state',
        //         's_m_t.kw',
        //         's_m_t.net_epc',
        //         's_m_t.epc',
        //         's_m_t.adders',
        //         's_m_t.customer_state as s_m_t_c_s',
        //         's_m_t.customer_name as s_m_t_c_n',
        //         's_m_t.kw as s_m_t_kw',
        //         's_m_t.net_epc as s_m_t_n_epc',
        //         's_m_t.epc as s_m_t_epc',
        //         's_m_t.adders as s_m_t_adders',
        //         's_m_t.m2_date as s_m_t_m2_date',
        //         'recon_adjustments.adjustment_amount as recon_adjustments_c_d',
        //         'recon_adjustments.adjustment_by_user_id as recon_adjustments_comment_by',
        //         'recon_adjustments.adjustment_comment as recon_adjustments_comment',
        //         DB::raw("CASE
        //                 WHEN s_m_p_t.closer1_id = $userId THEN s_m_p_t.closer1_commission
        //                 WHEN s_m_p_t.setter1_id = $userId THEN s_m_p_t.setter1_commission
        //                 WHEN s_m_p_t.closer2_id = $userId THEN s_m_p_t.closer2_commission
        //                 WHEN s_m_p_t.setter2_id = $userId THEN s_m_p_t.setter2_commission
        //                 ELSE NULL
        //             END AS user_commission"),
        //         DB::raw('CASE
        //                 WHEN u_r_w_h_t.closer_id = ' . $userId . ' THEN u_r_w_h_t.closer_id
        //                 WHEN u_r_w_h_t.setter_id = ' . $userId . ' THEN u_r_w_h_t.setter_id
        //                 ELSE NULL
        //             END AS user_id'),
        //     )
        //     ->join('sale_masters as s_m_t', function ($join) use ($startDate, $endDate) {
        //         $join->/* on('s_m_t.pid', '=', 'u_r_w_h_t.pid')
        //         -> */whereBetween('s_m_t.customer_signoff', [$startDate, $endDate])
        //             ->whereNull('s_m_t.date_cancelled');
        //     })
        //     ->join('sale_master_process as s_m_p_t', 's_m_t.pid', '=', 's_m_p_t.pid')
        //     ->leftJoin("recon_adjustments", function ($join) use ($startDate, $endDate, $userId, $isFinalize) {
        //         $join->on("recon_adjustments.pid", "=", 'u_r_w_h_t.pid')
        //             ->where("recon_adjustments.user_id", $userId)
        //             ->where("recon_adjustments.adjustment_type", "commission")
        //             ->whereDate("recon_adjustments.start_date", $startDate)
        //             ->whereDate("recon_adjustments.end_date", $endDate)
        //             ->whereNull("recon_adjustments.payroll_id")
        //             ->whereNull("recon_adjustments.pay_period_to")
        //             ->whereNull("recon_adjustments.pay_period_from");
        //         if ($isFinalize) {
        //             $join->where("recon_adjustments.payroll_status", "finalize");
        //         } else {
        //             $join->whereNull("recon_adjustments.payroll_status");
        //         }
        //         $join->where("adjustment_override_type", "recon-commission");
        //     })
        //     ->where(function ($query) use ($userId) {
        //         $query->where('closer_id', $userId)
        //             ->orWhere('setter_id', $userId)
        //             ->orWhere('s_m_p_t.closer1_id', $userId)
        //             ->orWhere('s_m_p_t.setter1_id', $userId)
        //             ->orWhere('s_m_p_t.closer2_id', $userId)
        //             ->orWhere('s_m_p_t.setter2_id', $userId);
        //     })
        //     ->where('u_r_w_h_t.status', '=', 'unpaid')
        //     ->whereIn('u_r_w_h_t.pid', $eligiblePids)
        //     ->where('u_r_w_h_t.withhold_amount', '!=', 0)
        //     ->whereNotNull('u_r_w_h_t.withhold_amount')
        //     ->where(function ($query) use ($userId) {
        //         $query->where("u_r_w_h_t.closer_id", $userId)
        //             ->orWhere("u_r_w_h_t.setter_id", $userId);
        //     })
        //     ->where(function ($query) use ($search) {
        //         if (!empty($search)) {
        //             $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
        //                 ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
        //         }
        //     })
        //     ->get();

        // /* move to recon commission query */
        // $userData = User::with(['positionDetail.payFrequency.frequencyType'])
        //     ->where('id', $userId)
        //     ->get(['id', 'sub_position_id'])
        //     ->map(function ($user) {
        //         // Collect pay frequencies, ensuring no null values
        //         return $user->positionDetail?->payFrequency?->frequencyType?->name;
        //     });
        // $payFrequencyTypes = $userData->unique();

        // $moveToReconCommissionResults = new Collection();
        // $moveToReconCommissionQuery = DB::table("user_commission as move_to_recon_commission_table")
        //     ->select(
        //         'move_to_recon_commission_table.*',
        //         'sale_master_table.*',
        //         'recon_adjustments.adjustment_amount as recon_adjustments_c_d',
        //         'recon_adjustments.adjustment_by_user_id as recon_adjustments_comment_by',
        //         'recon_adjustments.adjustment_comment as recon_adjustments_comment',
        //         DB::raw("CASE
        //                 WHEN s_m_p_t.closer1_id = $userId THEN s_m_p_t.closer1_commission
        //                 WHEN s_m_p_t.setter1_id = $userId THEN s_m_p_t.setter1_commission
        //                 WHEN s_m_p_t.closer2_id = $userId THEN s_m_p_t.closer2_commission
        //                 WHEN s_m_p_t.setter2_id = $userId THEN s_m_p_t.setter2_commission
        //                 ELSE NULL
        //             END AS user_commission"),
        //     )
        //     ->join('sale_masters as sale_master_table', function ($join) use ($startDate, $endDate) {
        //         $join->/* on('sale_master_table.pid', '=', 'move_to_recon_commission_table.pid')
        //             -> */whereBetween('sale_master_table.customer_signoff', [$startDate, $endDate])->whereNull('sale_master_table.date_cancelled');
        //     })
        //     ->join('sale_master_process as s_m_p_t', 'sale_master_table.pid', '=', 's_m_p_t.pid')
        //     ->leftJoin("recon_adjustments", function ($join) use ($startDate, $endDate, $userId, $isFinalize) {
        //         $join->on("recon_adjustments.pid", "=", 'move_to_recon_commission_table.pid')
        //             ->where("recon_adjustments.user_id", $userId)
        //             ->where("recon_adjustments.adjustment_type", "commission")
        //             ->whereDate("recon_adjustments.start_date", $startDate)
        //             ->whereDate("recon_adjustments.end_date", $endDate)
        //             ->whereNull("recon_adjustments.payroll_id")
        //             ->whereNull("recon_adjustments.pay_period_to")
        //             ->whereNull("recon_adjustments.pay_period_from");
        //         if ($isFinalize) {
        //             $join->where("recon_adjustments.payroll_status", "finalize");
        //         } else {
        //             $join->whereNull("recon_adjustments.payroll_status");
        //         }
        //         $join->where("adjustment_override_type", "commission");
        //     })
        //     ->where("move_to_recon_commission_table.user_id", $userId)
        //     ->where("move_to_recon_commission_table.pid", $eligiblePids)
        //     ->where("move_to_recon_commission_table.is_move_to_recon", 1)
        //     ->where("move_to_recon_commission_table.status", 6);

        // foreach ($payFrequencyTypes as $frequency) {
        //     $results = [];
        //     $query = clone $moveToReconCommissionQuery;

        //     if ($frequency === 'Weekly') {
        //         $query->join('weekly_pay_frequencies as w_p_f', function ($join) {
        //             $join->on('w_p_f.pay_period_from', '=', 'move_to_recon_commission_table.pay_period_from')
        //                 ->on('w_p_f.pay_period_to', '=', 'move_to_recon_commission_table.pay_period_to')
        //                 ->where('w_p_f.closed_status', '=', 1);
        //         });
        //         $results = $query->get();
        //     } elseif ($frequency === 'Monthly') {
        //         $query->join('monthly_pay_frequencies as m_p_f', function ($join) {
        //             $join->on('m_p_f.pay_period_from', '=', 'move_to_recon_commission_table.pay_period_from')
        //                 ->on('m_p_f.pay_period_to', '=', 'move_to_recon_commission_table.pay_period_to')
        //                 ->where('m_p_f.closed_status', '=', 1);
        //         });
        //         $results = $query->get();
        //     }
        //     $moveToReconCommissionResults = $moveToReconCommissionResults->merge($results);
        // }

        // $reconCommissionData = $reconData->transform(function ($result) use ($userId, $startDate, $endDate) {
        //     $userData = User::find($userId);
        //     $commentBy = User::find($result->recon_adjustments_comment_by);
        //     $m2Date = Carbon::parse($result->s_m_t_m2_date);

        //     $response = [
        //         'id' => $result->withhold_id,
        //         'user_id' => $userId,
        //         'pid' => $result->pid,
        //         'state_id' => $result->s_m_t_c_s,
        //         'customer_name' => $result->s_m_t_c_n,
        //         'customer_state' => $result->s_m_t_c_s,
        //         'rep_redline' => $userData->redline,
        //         'kw' => $result->s_m_t_kw,
        //         'net_epc' => $result->s_m_t_n_epc,
        //         'epc' => $result->s_m_t_epc,
        //         'adders' => $result->s_m_t_adders,
        //         'type' => 'Reconciliation',
        //         "withheld_amount" => 0,
        //         "amount" => 0,
        //         "paid" => 0,
        //         'in_recon' => 0,
        //         "in_recon_percentage" => 0,
        //         "position_id" => $commentBy?->position_id,
        //         "sub_position_id" => $commentBy?->sub_position_id,
        //         "is_super_admin" => $commentBy?->is_super_admin,
        //         "is_manager" => $commentBy?->is_manager,
        //         'adjustment_amount' => $result->recon_adjustments_c_d,
        //         "adjustment_comment" => $result->recon_adjustments_comment,
        //         "adjustment_by" => $commentBy ? $commentBy?->first_name . " " . $commentBy?->last_name : null,
        //         'comment_status' => $result->recon_adjustments_c_d != null ? 1 : 0,
        //         "is_move_to_recon" => isset($result->move_to_recon_flag) ? 1 : 0,
        //         'is_ineligible' => ($result->s_m_t_m2_date && $m2Date->between($startDate, $endDate)) ? 0 : 1
        //     ];
        //     return $response;
        // });

        // $moveToReconData = $moveToReconCommissionResults->transform(function ($result) use ($startDate, $endDate) {
        //     $userData = User::find($result->user_id);
        //     $commentBy = User::find($result->recon_adjustments_comment_by);
        //     $m2Date = Carbon::parse($result->m2_date);

        //     return [
        //         "id" => $result->id,
        //         "user_id" => $result->user_id,
        //         "pid" => $result->pid,
        //         "state_id" => $userData->state_id,
        //         "customer_name" => $result->customer_name,
        //         "customer_state" => $result->customer_state,
        //         "rep_redline" => $userData->redline,
        //         "kw" => $result->kw,
        //         "net_epc" => $result->net_epc,
        //         "epc" => $result->epc,
        //         "type" => "Move From Payroll",
        //         "withheld_amount" => 0,
        //         "amount" => 0,
        //         "paid" => 0,
        //         "adders" => $result->adders,
        //         'in_recon' => 0,
        //         "in_recon_percentage" => 0,
        //         "position_id" => $commentBy?->position_id,
        //         "sub_position_id" => $commentBy?->sub_position_id,
        //         "is_super_admin" => $commentBy?->is_super_admin,
        //         "is_manager" => $commentBy?->is_manager,
        //         'adjustment_amount' => $result->recon_adjustments_c_d,
        //         "adjustment_comment" => $result->recon_adjustments_comment,
        //         "adjustment_by" => $commentBy ? $commentBy?->first_name . " " . $commentBy?->last_name : null,
        //         'comment_status' => $result->recon_adjustments_c_d != null ? 1 : 0,
        //         "is_move_to_recon" => $result->is_move_to_recon ? 1 : 0,
        //         'is_ineligible' => ($result->m2_date && $m2Date->between($startDate, $endDate)) ? 0 : 1
        //     ];
        // });

        // $mergedData = $reconCommissionData->merge($moveToReconData)->groupBy('pid')->map(function ($group) {
        //     $firstItem = $group->first();
        //     $adjustedItem = $group->firstWhere('adjustment_amount', '!=', null);
        //     return [
        //         'id' => $firstItem['id'],
        //         'pid' => $firstItem['pid'],
        //         'user_id' => $firstItem['user_id'],
        //         'state_id' => $firstItem['state_id'],
        //         'customer_name' => $firstItem['customer_name'],
        //         'customer_state' => $firstItem['customer_state'],
        //         'rep_redline' => $firstItem['rep_redline'],
        //         'kw' => $firstItem['kw'],
        //         'net_epc' => $firstItem['net_epc'],
        //         'epc' => $firstItem['epc'],
        //         'adders' => $firstItem['adders'],
        //         'type' => $firstItem['type'],
        //         'withheld_amount' => 0,
        //         'amount' => 0,
        //         'paid' => 0,
        //         'in_recon' => 0,
        //         'in_recon_percentage' => 0,
        //         'position_id' => $adjustedItem['position_id'] ?? null,
        //         'sub_position_id' => $adjustedItem['sub_position_id'] ?? null,
        //         'is_super_admin' => $adjustedItem['is_super_admin'] ?? null,
        //         'is_manager' => $adjustedItem['is_manager'] ?? null,
        //         'adjustment_amount' => $adjustedItem['adjustment_amount'] ?? 0,
        //         'adjustment_comment' => $adjustedItem['adjustment_comment'] ?? null,
        //         'adjustment_by' => $adjustedItem['adjustment_by'] ?? null,
        //         'comment_status' => $adjustedItem['comment_status'] ?? null,
        //         'is_move_to_recon' => $adjustedItem['is_move_to_recon'] ?? null,
        //         'is_ineligible' => $firstItem['is_ineligible'] ?? 0
        //     ];
        // });

        // $finalData = [];
        // foreach ($mergedData as $reconCommission) {
        //     $show = 1;
        //     if ($isFinalize) {
        //         $finalized = ReconciliationFinalizeHistory::where(['start_date' => $startDate, 'end_date' => $endDate])->orderBy('id', 'DESC')->first();
        //         $finalizedUser = ReconciliationFinalizeHistory::where(['user_id' => $reconCommission['user_id'], 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_count' => $finalized->finalize_count])->orderBy('id', 'DESC')->first();
        //         if ($finalizedUser) {
        //             $pid = explode(',', $finalizedUser->pid);
        //             if (!in_array($reconCommission['pid'], $pid)) {
        //                 $show = 0;
        //             }
        //         }
        //     }

        //     if (!$show) {
        //         continue;
        //     }
        //     $totalCommission = UserCommission::where("user_id", $reconCommission['user_id'])
        //         ->where("pid", $reconCommission['pid'])
        //         ->whereIn("amount_type", ["m1", "m2", "m2 update"])->sum('amount') ?? 0;

        //     $withHeldAmount = UserReconciliationWithholding::where(function ($query) use ($reconCommission) {
        //         $query->where("closer_id", $reconCommission['user_id'])
        //             ->orWhere("setter_id", $reconCommission['user_id']);
        //     })->where("pid", $reconCommission['pid'])->sum('withhold_amount') ?? 0;
        //     $totalCommissionAmount = ($totalCommission + $withHeldAmount);

        //     $paidCommission = UserCommission::where("user_id", $reconCommission['user_id'])
        //         ->where('status', '!=', '6')
        //         ->where('is_move_to_recon', '0')
        //         ->where("pid", $reconCommission['pid'])
        //         ->whereIn("amount_type", ["m1", "m2", "m2 update"])->sum('amount') ?? 0;

        //     $finalizeAmount = ReconCommissionHistory::where("user_id", $reconCommission['user_id'])->where("pid", $reconCommission['pid'])->where("is_ineligible", '0');
        //     if ($isFinalize) {
        //         $finalizeAmount->where("status", "payroll");
        //     }
        //     $paidAmount = floatval($finalizeAmount->sum("paid_amount") ?? 0);

        //     $reconCommissionAmount = UserCommission::where("user_id", $reconCommission['user_id'])
        //         ->where("pid", $reconCommission['pid'])
        //         ->where("status", '6')
        //         ->where("is_move_to_recon", '1')
        //         ->whereIn("amount_type", ["m1", "m2", "m2 update"])->sum('amount') ?? 0;

        //     $inRecon = $reconCommissionAmount + $withHeldAmount - $paidAmount;
        //     $inReconPer = ($inRecon * ($payout / 100));
        //     $paidPrev = $paidCommission + $paidAmount;
        //     if ($reconCommission['is_ineligible']) {
        //         $inReconPer = 0;
        //     }

        //     if (($totalCommissionAmount - $paidPrev) != 0) {
        //         $finalData[] = [
        //             'id' => $reconCommission['id'],
        //             'pid' => $reconCommission['pid'],
        //             'user_id' => $reconCommission['user_id'],
        //             'state_id' => $reconCommission['state_id'],
        //             'customer_name' => $reconCommission['customer_name'],
        //             'customer_state' => $reconCommission['customer_state'],
        //             'rep_redline' => $reconCommission['rep_redline'],
        //             'kw' => $reconCommission['kw'],
        //             'net_epc' => $reconCommission['net_epc'],
        //             'epc' => $reconCommission['epc'],
        //             'adders' => $reconCommission['adders'],
        //             'type' => $reconCommission['type'],
        //             'withheld_amount' => 0,
        //             'amount' => $totalCommissionAmount,
        //             'paid' => $paidPrev,
        //             'in_recon' => ($inRecon - $inReconPer),
        //             'in_recon_percentage' => $inReconPer,
        //             'position_id' => $reconCommission['position_id'] ?? null,
        //             'sub_position_id' => $reconCommission['sub_position_id'] ?? null,
        //             'is_super_admin' => $reconCommission['is_super_admin'] ?? null,
        //             'is_manager' => $reconCommission['is_manager'] ?? null,
        //             'adjustment_amount' => $reconCommission['adjustment_amount'] ?? 0,
        //             'adjustment_comment' => $reconCommission['adjustment_comment'] ?? null,
        //             'adjustment_by' => $reconCommission['adjustment_by'] ?? null,
        //             'comment_status' => $reconCommission['comment_status'] ?? null,
        //             'is_move_to_recon' => $reconCommission['is_move_to_recon'] ?? null,
        //             'is_ineligible' => $reconCommission['is_ineligible'] ?? 0
        //         ];
        //     }
        // }
        // return collect($finalData);
    }

    public function reconClawbackPopup($request, $id)
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
            $reconClawbackData = $this->getReconClawbackData($id, $startDate, $endDate, $search, $isFinalize);

            $response = $reconClawbackData->filter();

            return response()->json([
                'ApiName' => $apiName,
                'status' => true,
                'message' => 'Successfully.',
                'data' => $response,
                'subTotal' => $response->sum('amount'),
            ]);
        } catch (\Throwable $th) {
            dd($th);
        }
        /* old code is here */
        $reconDataQuery = DB::table('clawback_settlements as c_s_t')
            ->join('sale_masters as s_m_t', function ($join) {
                $join->on('s_m_t.pid', '=', 'c_s_t.pid');
            })
            ->join('users', 'users.id', 'c_s_t.user_id')
            ->where('c_s_t.user_id', $id)
            ->where('payroll_id', 0)
            ->where('status', 1)
            ->where('clawback_type', 'reconciliation')
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                        ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                }
            })
            ->select(
                'c_s_t.id as clawback_settlement_id',
                'c_s_t.user_id as user_id',
                'c_s_t.is_move_to_recon as is_move_to_recon',
                'c_s_t.clawback_amount as clawback_amount',
                'c_s_t.clawback_type as clawback_type',
                'c_s_t.pid as pid',
                'c_s_t.type as type',
                'c_s_t.adders_type as adders_type',
                's_m_t.customer_name as s_m_t_customer_name',
                's_m_t.customer_state as s_m_t_customer_state',
                's_m_t.date_cancelled as date_cancelled',
                'c_s_t.updated_at as updated_at',
                DB::raw('c_s_t.clawback_amount as total_t_r_c_a')
            );

        $moveToReconDataQuery = DB::table('clawback_settlements as c_s_t')
            ->join('sale_masters as s_m_t', function ($join) {
                $join->on('s_m_t.pid', '=', 'c_s_t.pid')
                    ->when(request('search'), function ($q) {
                        $q->where('s_m_t.pid', 'like', '%'.request('search').'%')
                            ->orWhere('s_m_t.customer_name', 'like', '%'.request('search').'%');
                    });
            })
            ->join('users', 'users.id', 'c_s_t.user_id')
            ->where('c_s_t.user_id', $id)
            ->where('c_s_t.status', 6)
            ->where('c_s_t.is_move_to_recon', 1)
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                        ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                }
            })
            ->select(
                'c_s_t.id as clawback_settlement_id',
                'c_s_t.user_id as user_id',
                'c_s_t.is_move_to_recon as is_move_to_recon',
                'c_s_t.clawback_amount as clawback_amount',
                'c_s_t.clawback_type as clawback_type',
                'c_s_t.pid as pid',
                'c_s_t.type as type',
                'c_s_t.adders_type as adders_type',
                's_m_t.customer_name as s_m_t_customer_name',
                's_m_t.customer_state as s_m_t_customer_state',
                's_m_t.date_cancelled as date_cancelled',
                'c_s_t.updated_at as updated_at',
                DB::raw('c_s_t.clawback_amount as total_t_r_c_a')
            );

        $frequency = User::with(['positionDetail.payFrequency.frequencyType'])
            ->where('id', $id)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                // Collect pay frequencies, ensuring no null values
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });

        $query = clone $moveToReconDataQuery;
        $results = new Collection; // Initialize results as a collection

        // Modify the query based on the frequency type
        if ($frequency === 'Weekly') {
            $query->join('weekly_pay_frequencies as w_p_f', function ($join) {
                $join->on('w_p_f.pay_period_from', '=', 'c_s_t.pay_period_from')
                    ->on('w_p_f.pay_period_to', '=', 'c_s_t.pay_period_to')
                    ->where('w_p_f.closed_status', '=', 1);
            });
        } elseif ($frequency === 'Monthly') {
            $query->join('monthly_pay_frequencies as m_p_f', function ($join) {
                $join->on('m_p_f.pay_period_from', '=', 'c_s_t.pay_period_from')
                    ->on('m_p_f.pay_period_to', '=', 'c_s_t.pay_period_to')
                    ->where('m_p_f.closed_status', '=', 1);
            });
        }

        $results = $query;
        $data = $reconDataQuery->union($results)->get();
        $response = $data->transform(function ($response) use ($startDate, $endDate, $isFinalize) {
            $show = 1;
            if ($isFinalize) {
                $finalized = ReconciliationFinalizeHistory::where(['start_date' => $startDate, 'end_date' => $endDate])->orderBy('id', 'DESC')->first();
                $finalizedUser = ReconciliationFinalizeHistory::where(['user_id' => $response->user_id, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_count' => $finalized->finalize_count])->orderBy('id', 'DESC')->first();
                if ($finalizedUser) {
                    $pid = explode(',', $finalizedUser->pid);
                    if (! in_array($response->pid, $pid)) {
                        $show = 0;
                    }
                }
            }

            if (! $show) {
                return null;
            }

            $clawbackAmount = $response->clawback_amount ?? 0;
            $reconPaidAmount = 0;
            if ($isFinalize == 1) {
                $reconAdjustment = ReconAdjustment::where('adjustment_type', 'clawback')
                    ->where('adjustment_override_type', $response->adders_type)
                    ->where('user_id', $response->user_id)
                    ->where('pid', $response->pid)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('payroll_status', 'finalize')
                    ->orderBy('id', 'desc')
                    ->first();
            } else {
                $reconAdjustment = ReconAdjustment::where('adjustment_type', 'clawback')
                    ->where('adjustment_override_type', $response->adders_type)
                    ->where('user_id', $response->user_id)
                    ->where('pid', $response->pid)
                    ->whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->whereNull('payroll_status')
                    ->orderBy('id', 'desc')
                    ->first();
            }

            $location = Locations::with('State')->where('general_code', $response->s_m_t_customer_state)->first();

            $stateCode = $location ? $location->state->id : null;
            $userData = User::find($reconAdjustment?->adjustment_by_user_id);
            $moveToReconStatus = $response->is_move_to_recon == 1 ? ' | Move From Payroll' : '';

            if (in_array($response->type, ['commission', 'recon-commission'])) {
                $paymentType = 'Commission';
            } else {
                $paymentType = 'Override';
            }

            /* recon clawback history */
            $reconClawbackHistory = ReconClawbackHistory::where('pid', $response->pid)->where('user_id', $response->user_id)->where('adders_type', $response->adders_type)
                ->where('type', $response->type == 'overrides' ? 'override' : $response->type);

            if ($isFinalize == 1) {
                $reconPaidAmount = $reconClawbackHistory->where('status', 'payroll')->sum('paid_amount');
            } else {
                $reconPaidAmount = $reconClawbackHistory->sum('paid_amount');
            }

            $totalAmount = $clawbackAmount - $reconPaidAmount;
            if ($totalAmount == 0) {
                return null;
            }

            return [
                'id' => $response->clawback_settlement_id,
                'pid' => $response->pid,
                'user_id' => $response->user_id,
                'customer_name' => $response->s_m_t_customer_name.$moveToReconStatus,
                'state_id' => $stateCode,
                'state' => $response->s_m_t_customer_state,
                'payment_type' => $paymentType,
                'date' => $response->date_cancelled,
                'date_paid' => $response->updated_at,
                'clawback_type' => $response->type,
                'type' => $response->adders_type == 'recon-commission' ? 'Reconciliation' : ucfirst($response->adders_type),
                'amount' => -1 * floatval($clawbackAmount),
                'is_prev_paid' => 0,
                'in_recon' => -1 * $clawbackAmount,
                'adjustment_id' => $reconAdjustment?->id,
                'adjustment_amount' => floatval($reconAdjustment?->adjustment_amount),
                'comment_status' => isset($reconAdjustment) ? 1 : 0,
                'adjustment_comment' => $reconAdjustment?->adjustment_comment,
                'adjustment_by' => $reconAdjustment?->commentUser->first_name.' '.$reconAdjustment?->commentUser->last_name,
                'is_move_to_recon' => $userData?->is_move_to_recon,
                'is_super_admin' => $userData?->is_super_admin,
                'is_manager' => $userData?->is_manager,
                'position_id' => $userData?->position_id,
                'sub_position_id' => $userData?->sub_position_id,
            ];
        });

        $response = $response->filter();

        return response()->json([
            'ApiName' => $apiName,
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
            'subTotal' => $response->sum('amount'),
        ]);
    }

    /**
     * Method reconAdjustmentPopup: getting recon adjustment popup data
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
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
        /* $payFrequency = $this->getUserBasedExecutedPayFrequencyDate(User::find($request->user_id));

        $filteredDeductionData = $deductionData->filter(function ($response) use ($startDate, $endDate, $payFrequency) {
            foreach ($payFrequency as $frequency) {
                if ($response->pay_period_from == $frequency['pay_period_from'] && $response->pay_period_to == $frequency['pay_period_to']) {
                    if (Carbon::parse($startDate)->format("Y-m-d") <= Carbon::parse($response->pay_period_from)->format("Y-m-d") && Carbon::parse($endDate)->format("Y-m-d") >= Carbon::parse($response->pay_period_to)->format("Y-m-d")) {
                        return true;
                    }
                }
            }
            return false;
        }); */

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
            ->where('finalize_count', $request->finalize_count)
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
                'comment' => $result->comment,
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
        $userData = User::with(['positionDetail.payFrequency.frequencyType'])
            ->where('id', $request->user_id)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                // Collect pay frequencies, ensuring no null values
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });
        $payFrequencyTypes = $userData->unique();

        $deductionsResults = ReconDeductionHistory::where('user_id', $request->user_id)
            ->whereDate('start_date', $request->start_date)
            ->whereDate('end_Date', $request->end_date)
            ->where('finalize_count', $request->finalize_count)
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

    /**
     * Method reconUserCommissionAccountSummary : Fetching user commission details in the recon
     *
     * @param  Request  $request  [explicit description]
     * @param  $userId  $userId [explicit description]
     * @return void
     */
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

    /**
     * Method reconOverridePop: getting recon override popup details
     *
     * @object $request $request [explicit description]
     *
     * @param  $userId  $userId [explicit description]
     * @return void
     */
    public function reconOverridePop($request, $userId)
    {
        $request->merge([
            'payout' => (int) $request->query('payout'),
        ]);

        // Validate the request
        $validator = Validator::make($request->all(), [
            'start_date' => ['required', 'date', self::DATE_FORMATE],
            'end_date' => ['required', 'date', self::DATE_FORMATE],
            'payout' => ['required', 'integer', 'min:1', 'max:100'],
            'is_finalize' => self::FINALIZE_VALIDATE,
        ]);

        // If validation fails, return an error response
        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation Commission By Employee ID',
                'status' => false,
                'message' => $validator->errors()->first(),
            ]);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $payoutPercent = $request->payout;
        $search = $request->search;
        $isFinalize = $request->is_finalize;
        $responseArray = $this->getReconOverRidesData($userId, $startDate, $endDate, $search, $payoutPercent, $isFinalize);

        $total = array_reduce($responseArray->toArray(), function ($carry, $item) {
            if (! $item['is_ineligible']) {
                $carry['in_recon'] += $item['in_recon'];
                $carry['in_recon_percentage'] += $item['in_recon_percentage'];
            }

            return $carry;
        }, ['in_recon' => 0, 'in_recon_percentage' => 0]);

        /* add one condition if total amount is negative  then not apply recon payout is apply 100% payout */
        /* if (array_sum($total) < 0) {
            $responseArray = $this->getReconOverRidesData($userId, $startDate, $endDate, $search, 100, $isFinalize);

            $total = array_reduce($responseArray, function ($carry, $item) {
                if(!$item["is_ineligible"]){
                    $carry['in_recon'] += $item['in_recon'];
                    $carry['in_recon_percentage'] += $item['in_recon_percentage'];
                }
                return $carry;
            }, ['in_recon' => 0, 'in_recon_percentage' => 0]);
        } */
        $responseArray = $responseArray->toArray();
        usort($responseArray, function ($a, $b) {
            return strcmp($a['pid'], $b['pid']);
        });

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commission By Employee ID',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $responseArray,
            'sub_total' => $total['in_recon_percentage'],
            'in_recon_subtotal' => $total['in_recon'],
        ]);
    }

    public function getReconOverRidesData($userId, $startDate, $endDate, $search, $payoutPercent, $isFinalize)
    {
        try {
            $startDate = Carbon::parse($startDate);
            $endDate = Carbon::parse($endDate);
            $pids = $this->getPids($userId, $startDate, $endDate, '', $search);
            $eligiblePids = $pids['eligiblePids'];
            $getAllPids = $pids['allPids']/* ->pluck("pid")->toArray() */;

            $userData = User::find($userId);

            return $getAllPids->flatMap(function ($result) use ($userData, $payoutPercent, $eligiblePids, $startDate, $endDate, $isFinalize) {
                $getReconOverrideData = UserOverrides::where('pid', $result->pid)
                    ->where('user_id', $userData->id)
                    ->where('overrides_settlement_type', 'reconciliation')
                    ->get();

                return $getReconOverrideData->map(function ($overrideResult) use ($userData, $result, $eligiblePids, $isFinalize, $payoutPercent, $startDate, $endDate) {
                    $reconAdjustment = $this->reconAdjustmentDetails($result->pid, $userData->id, $startDate, $endDate, 'override', $overrideResult->type);

                    $saleUserDetails = User::find($overrideResult->sale_user_id);
                    $paidOverrideAmount = ReconOverrideHistory::where([
                        'pid' => $overrideResult->pid,
                        'user_id' => $overrideResult->user_id,
                        'is_ineligible' => 0,
                        'type' => $overrideResult->type,
                    ])->where(function ($query) use ($saleUserDetails) {
                        $query->where('overrider', $saleUserDetails->first_name.' '.$saleUserDetails->last_name)
                            ->orWhere('overrider', $saleUserDetails->id);
                    });

                    if ($isFinalize) {
                        $paidOverrideAmount->where('status', 'payroll');
                    }
                    $totalAmount = $overrideResult->amount;
                    $paidPreviously = floatval($paidOverrideAmount->sum('paid'));
                    $isIneligible = in_array($result->pid, $eligiblePids) ? 0 : 1;
                    $inReconAmount = floatval($totalAmount) - floatval($paidPreviously);
                    $inReconPercentageAmount = floatval($inReconAmount) * ($payoutPercent / 100);

                    if ($inReconAmount < 0.5) {
                        /* amount is less than 0.5 or negative then apply 100% payout */
                        $inReconPercentageAmount = floatval($inReconAmount);
                    }

                    if ($isIneligible) {
                        $inReconPercentageAmount = 0;
                    }

                    return [
                        'id' => $overrideResult->id,
                        'user_id' => $userData->id,
                        'pid' => $result->pid,
                        'customer_name' => $result->customer_name,
                        'customer_state' => $result->customer_state,
                        'image' => $saleUserDetails->first_name,
                        'override_over_image' => $saleUserDetails->image,
                        'override_over_first_name' => $saleUserDetails->first_name,
                        'override_over_last_name' => $saleUserDetails->last_name,
                        'type' => $overrideResult->type,
                        'display_type_name' => $overrideResult->is_move_to_recon == 1 ? $overrideResult->type.' | Move From Payroll' : $overrideResult->type,
                        'rep_redline' => $saleUserDetails->redline,
                        'kw' => $result->kw,
                        'overrides_type' => $overrideResult->overrides_type,
                        'overrides_amount' => $overrideResult->overrides_amount,
                        'amount' => $totalAmount,
                        'paid' => $paidPreviously,
                        'in_recon_percentage' => $inReconPercentageAmount,
                        'in_recon' => $inReconAmount - $inReconPercentageAmount,
                        'is_ineligible' => $isIneligible,
                        'adjustment_amount' => $reconAdjustment['adjustment_amount'],
                        'comment_status' => $reconAdjustment['comment_status'],
                        'adjustment_comment' => $reconAdjustment['adjustment_comment'],
                        'adjustment_by' => $reconAdjustment['adjustment_by'],
                        'is_super_admin' => $reconAdjustment['is_super_admin'],
                        'is_manager' => $reconAdjustment['is_manager'],
                        'position_id' => $reconAdjustment['position_id'],
                        'sub_position_id' => $reconAdjustment['sub_position_id'],
                    ];
                });
            });
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info('!! Error !! '.$th->getMessage().'. At line number '.$th->getLine().'. FIle is '.$th->getFile());
            $response = response()->json([
                'ApiName' => 'Recon Override Breakdown Api',
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 500);
        }
        /* recon override popup old code */
        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $reconDataQuery = DB::table('user_overrides AS u_o_t')
            ->select(
                'u_o_t.*',
                'u_o_t.user_id AS u_o_t_user_id',
                'u_o_t.id AS u_o_t_id',
                'u_o_t.pid AS u_o_t_pid',
                'u_o_t.overrides_amount AS u_o_t_overrides_amount',
                's_m_t.customer_name AS s_m_t_customer_name',
                's_m_t.customer_state AS s_m_t_customer_state',
                's_m_t.m2_date AS s_m_t_m2_date',
                'u_o_t.sale_user_id AS s_m_t_sale_user_id',
                'u_o_t.type AS s_m_t_type',
                'users.is_super_admin',
                'users.is_manager',
                'users.position_id',
                'users.sub_position_id',
                'r_a_t.adjustment_amount As r_a_t_o_d',
                'r_a_t.adjustment_comment As r_a_t_comment',
                'r_a_t.adjustment_by_user_id As r_a_t_comment_by'
            )
            ->join('sale_masters AS s_m_t', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t.pid', '=', 'u_o_t.pid')
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t.date_cancelled');
            })
            ->join('users', function ($join) {
                $join->on('u_o_t.user_id', '=', 'users.id');
            })
            ->leftJoin('recon_adjustments AS r_a_t', function ($join) use ($startDate, $endDate, $userId, $isFinalize) {
                $join->on('r_a_t.pid', '=', 'u_o_t.pid')
                    ->on('u_o_t.type', '=', 'r_a_t.adjustment_override_type')
                    ->on('u_o_t.sale_user_id', '=', 'r_a_t.sale_user_id')
                    ->where('r_a_t.user_id', $userId)
                    ->whereNotNull('r_a_t.adjustment_override_type')
                    ->whereDate('r_a_t.start_date', $startDate)
                    ->whereDate('r_a_t.end_date', $endDate)
                    ->whereNull('r_a_t.payroll_id');
                if ($isFinalize) {
                    $join->where('r_a_t.payroll_status', 'finalize');
                } else {
                    $join->whereNull('r_a_t.payroll_status');
                }
                $join->whereNull('r_a_t.pay_period_from')
                    ->whereNull('r_a_t.pay_period_to');
            })
            ->where('u_o_t.overrides_settlement_type', 'reconciliation')
            ->where('u_o_t.user_id', $userId)
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                        ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                }
            });

        $moveToReconDataQuery = DB::table('user_overrides AS u_o_t')
            ->select(
                'u_o_t.*',
                'u_o_t.user_id AS u_o_t_user_id',
                'u_o_t.id AS u_o_t_id',
                'u_o_t.pid AS u_o_t_pid',
                'u_o_t.overrides_amount AS u_o_t_overrides_amount',
                's_m_t.customer_name AS s_m_t_customer_name',
                's_m_t.customer_state AS s_m_t_customer_state',
                's_m_t.m2_date AS s_m_t_m2_date',
                'u_o_t.sale_user_id AS s_m_t_sale_user_id',
                'u_o_t.type AS s_m_t_type',
                'users.is_super_admin',
                'users.is_manager',
                'users.position_id',
                'users.sub_position_id',
                'r_a_t.adjustment_amount As r_a_t_o_d',
                'r_a_t.adjustment_comment As r_a_t_comment',
                'r_a_t.adjustment_by_user_id As r_a_t_comment_by'
            )
            ->join('sale_masters AS s_m_t', function ($join) use ($startDate, $endDate) {
                $join->on('s_m_t.pid', '=', 'u_o_t.pid')
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->whereNull('s_m_t.date_cancelled');
            })
            ->join('users', function ($join) {
                $join->on('u_o_t.user_id', '=', 'users.id');
            })
            ->leftJoin('recon_adjustments AS r_a_t', function ($join) use ($startDate, $endDate, $userId, $isFinalize) {
                $join->on('r_a_t.pid', '=', 'u_o_t.pid')
                    ->on('u_o_t.type', '=', 'r_a_t.adjustment_override_type')
                    ->on('u_o_t.sale_user_id', '=', 'r_a_t.sale_user_id')
                    ->where('r_a_t.user_id', $userId)
                    ->whereNotNull('r_a_t.adjustment_override_type')
                    ->whereDate('r_a_t.start_date', $startDate)
                    ->whereDate('r_a_t.end_date', $endDate)
                    ->whereNull('r_a_t.payroll_id');
                if ($isFinalize) {
                    $join->where('r_a_t.payroll_status', 'finalize');
                } else {
                    $join->whereNull('r_a_t.payroll_status');
                }
                $join->whereNull('r_a_t.pay_period_from')
                    ->whereNull('r_a_t.pay_period_to');
            })
            ->where('u_o_t.user_id', $userId)
            ->where('u_o_t.status', 6)
            ->where('u_o_t.is_move_to_recon', 1)
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                        ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                }
            });
        $frequency = User::with(['positionDetail.payFrequency.frequencyType'])
            ->where('id', $userId)
            ->get(['id', 'sub_position_id'])
            ->map(function ($user) {
                // Collect pay frequencies, ensuring no null values
                return $user->positionDetail?->payFrequency?->frequencyType?->name;
            });

        $query = clone $moveToReconDataQuery;
        $results = new Collection; // Initialize results as a collection

        // Modify the query based on the frequency type
        if ($frequency === 'Weekly') {
            $query->join('weekly_pay_frequencies as w_p_f', function ($join) {
                $join->on('w_p_f.pay_period_from', '=', 'u_o_t.pay_period_from')
                    ->on('w_p_f.pay_period_to', '=', 'u_o_t.pay_period_to')
                    ->where('w_p_f.closed_status', '=', 1);
            });
        } elseif ($frequency === 'Monthly') {
            $query->join('monthly_pay_frequencies as m_p_f', function ($join) {
                $join->on('m_p_f.pay_period_from', '=', 'u_o_t.pay_period_from')
                    ->on('m_p_f.pay_period_to', '=', 'u_o_t.pay_period_to')
                    ->where('m_p_f.closed_status', '=', 1);
            });
        }

        // Execute the query to get results
        $results = $query;

        // Combine with union and get results (query should be executed separately for union)
        $result = $reconDataQuery->union($results)->get();
        $response = [];
        foreach ($result as $value) {
            $show = 1;
            if ($isFinalize) {
                $finalized = ReconciliationFinalizeHistory::where(['start_date' => $startDate, 'end_date' => $endDate])->orderBy('id', 'DESC')->first();
                $finalizedUser = ReconciliationFinalizeHistory::where(['user_id' => $value->u_o_t_user_id, 'start_date' => $startDate, 'end_date' => $endDate, 'finalize_count' => $finalized->finalize_count])->orderBy('id', 'DESC')->first();
                if ($finalizedUser) {
                    $pid = explode(',', $finalizedUser->pid);
                    if (! in_array($value->u_o_t_pid, $pid)) {
                        $show = 0;
                    }
                }
            }

            if (! $show) {
                continue;
            }
            $saleUserDetails = User::find($value->s_m_t_sale_user_id);
            $finalizeData = ReconOverrideHistory::where('pid', $value->u_o_t_pid)
                ->where('is_ineligible', '0')
                ->where('user_id', $userId)
                ->where(function ($query) use ($saleUserDetails) {
                    $query->where('overrider', $saleUserDetails->first_name.' '.$saleUserDetails->last_name)
                        ->orWhere('overrider', $saleUserDetails->id);
                })
                ->where('type', $value->s_m_t_type);
            if ($isFinalize) {
                $finalizeData->where('status', 'payroll');
            }
            $totalAmount = $value->amount;
            $paidPreviously = floatval($finalizeData->sum('paid'));

            $m2Date = Carbon::parse($value->s_m_t_m2_date);
            $is_ineligible = ($value->s_m_t_m2_date && $m2Date->between($startDate, $endDate)) ? 0 : 1;
            $inReconAmount = floatval($totalAmount) - floatval($paidPreviously);
            $inReconPercentageAmount = floatval($inReconAmount) * ($payoutPercent / 100);
            if ($is_ineligible) {
                $inReconPercentageAmount = 0;
            }

            if (($totalAmount - $paidPreviously) != 0) {
                $response[] = [
                    'id' => $value->u_o_t_id,
                    'user_id' => $userId,
                    'pid' => $value->u_o_t_pid,
                    'customer_name' => $value->s_m_t_customer_name,
                    'customer_state' => $value->s_m_t_customer_state,
                    'image' => $saleUserDetails->first_name,
                    'override_over_image' => $saleUserDetails->image,
                    'override_over_first_name' => $saleUserDetails->first_name,
                    'override_over_last_name' => $saleUserDetails->last_name,
                    'type' => $value->s_m_t_type,
                    'display_type_name' => $value->is_move_to_recon == 1 ? $value->s_m_t_type.' | Move From Payroll' : $value->s_m_t_type,
                    'rep_redline' => $saleUserDetails->redline,
                    'kw' => $value->kw,
                    'overrides_type' => $value->overrides_type,
                    'overrides_amount' => $value->u_o_t_overrides_amount,
                    'amount' => $totalAmount,
                    'paid' => $paidPreviously,
                    'in_recon_percentage' => $inReconPercentageAmount,
                    'in_recon' => $inReconAmount - floatval($inReconPercentageAmount),
                    'adjustment_amount' => $value->r_a_t_o_d,
                    'comment_status' => $value->r_a_t_comment ? 1 : 0,
                    'adjustment_comment' => $value->r_a_t_comment,
                    'adjustment_by' => $value->r_a_t_comment_by ? User::find($value->r_a_t_comment_by)?->first_name.' '.User::find($value->r_a_t_comment_by)?->last_name : null,
                    'is_super_admin' => $value->r_a_t_comment_by ? User::find($value->r_a_t_comment_by)?->is_super_admin : null,
                    'is_manager' => $value->r_a_t_comment_by ? User::find($value->r_a_t_comment_by)?->is_manager : null,
                    'position_id' => $value->r_a_t_comment_by ? User::find($value->r_a_t_comment_by)?->position_id : null,
                    'sub_position_id' => $value->r_a_t_comment_by ? User::find($value->r_a_t_comment_by)?->sub_position_id : null,
                    'is_ineligible' => $is_ineligible,
                ];
            }
        }

        return $response;
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

    public function overrideAdjustmentEdit($request): JsonResponse
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
            if (! $sale->m2_date) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user is ineligible for adjustment!!',
                ], 400);
            }

            $m2Date = Carbon::parse($sale->m2_date);
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

    public function reconAdjustmentEdit($request, $adjustmentType)
    {
        try {
            $id = $request->id;
            $userId = $request->user_id;
            $pid = $request->pid;
            $adjustmentAmount = $request->adjust_amount;
            $comment = $request->comment;
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            if (! $sale = SalesMaster::where('pid', $pid)->first()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sale not found!!',
                ], 400);
            }

            $startDate = Carbon::parse($startDate);
            $endDate = Carbon::parse($endDate);
            if (! $sale->m2_date) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user is ineligible for adjustment!!',
                ], 400);
            }

            $m2Date = Carbon::parse($sale->m2_date);
            if (! $m2Date->between($startDate, $endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'This user is ineligible for adjustment!!',
                ], 400);
            }

            /* $commissionAdjustmentType = UserReconciliationWithholding::where("id", $id)
                ->where("pid", $pid)
                ->where(function ($query) use ($userId) {
                    $query->where("closer_id", $userId)
                        ->orWhere("setter_id", $userId);
                })->exists(); */
            $commissionAdjustmentType = UserCommission::where('id', $id)
                ->where('pid', $pid)
                ->where('user_id', $userId)->exists();

            $adjustmentData = ReconAdjustment::where('start_Date', $startDate)
                ->where('end_date', $endDate)
                ->where('user_id', $userId)
                ->where('adjustment_type', $adjustmentType)
                ->where('pid', $pid)
                ->whereNull('payroll_id')
                ->whereNull('pay_period_from')
                ->whereNull('pay_period_to');

            if ($adjustmentType == 'commission') {
                $adjustmentOverrideType = null;
            } else {
                $adjustmentOverrideType = $commissionAdjustmentType ? 'recon-commission' : 'commission';
            }

            $body = [
                'user_id' => $userId,
                'pid' => $pid,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'adjustment_type' => $adjustmentType,
                'adjustment_amount' => $adjustmentAmount,
                'adjustment_comment' => $comment,
                'adjustment_by_user_id' => auth()->user()->id,
                // "adjustment_override_type" => $commissionAdjustmentType ? "recon-commission" : "commission",
                'adjustment_override_type' => $adjustmentOverrideType,
            ];

            if ($adjustmentData->exists()) {
                $adjustmentData->update($body);
            } else {
                ReconAdjustment::create($body);
            }
            $response = response()->json([
                'ApiName' => "Edit {$adjustmentType} adjustment",
                'status' => true,
                'message' => 'Update Successfully.',
            ]);
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info('!! Error !! '.$th->getMessage().'. At line number '.$th->getLine().'. FIle is '.$th->getFile());
            $response = response()->json([
                'ApiName' => 'Edit reconciliation commission adjustment',
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 500);
        }

        return $response;
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

    public function getReconClawbackData($userId, $startDate, $endDate, $search, $isFinalize)
    {
        try {
            $reconClawbackData = ClawbackSettlement::where('clawback_type', 'reconciliation')
                ->where('user_id', $userId)
                ->where('payroll_id', 0)
                ->where('status', 3)
                ->where(function ($query) use ($search) {
                    if (! empty($search)) {
                        $query->where('s_m_t.customer_name', 'LIKE', "%{$search}%")
                            ->orWhere('s_m_t.pid', 'LIKE', "%{$search}%");
                    }
                })->get();
            $data = $reconClawbackData;

            return $response = $data->transform(function ($response) use ($startDate, $endDate, $isFinalize) {
                $location = Locations::with('State')->where('general_code', $response->s_m_t_customer_state)->first();
                $stateCode = $location ? $location->state->id : null;
                $clawbackAmount = $response->clawback_amount ?? 0;

                if (in_array($response->type, ['commission', 'recon-commission'])) {
                    $paymentType = 'Commission';
                } else {
                    $paymentType = 'Override';
                }
                /* recon clawback history */
                $reconClawbackHistory = ReconClawbackHistory::where('pid', $response->pid)->where('user_id', $response->user_id)->where('adders_type', $response->adders_type)
                    ->where('type', $response->type == 'overrides' ? 'override' : $response->type);

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
                $reconClawbackAdjustment = $this->reconAdjustmentDetails($response->pid, $response->user_id, $startDate, $endDate, 'clawback', $response->adders_type);
                $moveToReconStatus = $response->is_move_to_recon == 1 ? ' | Move From Payroll' : '';
                $customerName = isset($response->salesDetail->customer_name) ? $response->salesDetail->customer_name : null;

                return [
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
            });
        } catch (\Throwable $th) {
            Log::channel('reconLog')->info($th->getMessage());

            return response()->json([
                'ApiName' => 'Get Recon Clawback Data',
                'status' => false,
                'message' => self::ERROR_MSG,
            ], 500);
        }
    }
}
