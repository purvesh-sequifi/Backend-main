<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Http\Controllers\Controller;
use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\ExternalSaleWorker;
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
use App\Models\SaleMasterProcess;
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

        /* get recon data */
        $reconWithHoldResponseData = $this->getReconData($userIds, $startDate, $endDate, $reconPayout);
        $reconWithHoldResponseArray = $reconWithHoldResponseData['data'];

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
        ]);
    }

    public function getReconData($userIds, $startDate, $endDate, $reconPayout, $isFinalize = false)
    {
        try {
            // $userIds = [133];
            $response = [];
            $finalUserIds = [];
            $isUpfront = $this->isUpfront;
            foreach ($userIds as $userId) {
                $userData = User::select('id', 'image', 'first_name', 'last_name', 'is_super_admin', 'is_manager', 'position_id', 'sub_position_id')->find($userId);

                // added empty userData check because on testing servers there is garbage user_id's and it is giving error if data not found for any user id
                if (empty($userData)) {
                    continue;
                }

                $userSkipStatus = ReconciliationStatusForSkipedUser::whereDate('start_date', $startDate)->whereDate('end_date', $endDate)->where('user_id', $userId)->exists();

                $eligiblePid = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
                    ->whereNull('date_cancelled')
                    ->whereHas('salesProductMasterDetails', function ($q) use ($startDate, $endDate) {
                        $q->whereNotNull('milestone_date')->where('is_last_date', 1)->whereBetween('milestone_date', [$startDate, $endDate]);
                    })
                    ->pluck('pid')->toArray();

                $allPid = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->pluck('pid')->toArray();

                /* COMMISSION */
                $totalAmounts = UserCommission::selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as positive_amount,
                      SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as negative_amount')
                    ->where(['user_id' => $userId, 'status' => '3', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])
                    ->whereIn('pid', $eligiblePid)->first();

                $positiveCommission = $totalAmounts->positive_amount ?? 0;
                $negativeCommission = $totalAmounts->negative_amount ?? 0;

                $totalPaidReconAmount = ReconCommissionHistory::selectRaw('SUM(CASE WHEN paid_amount > 0 THEN paid_amount ELSE 0 END) as positive_amount,
                        SUM(CASE WHEN paid_amount < 0 THEN paid_amount ELSE 0 END) as negative_amount')
                    ->where(['user_id' => $userData->id, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('pid', $eligiblePid)
                    ->when($isFinalize, function ($q) {
                        $q->where('status', 'payroll');
                    })->first();

                $positivePaidCommission = $totalPaidReconAmount->positive_amount ?? 0;
                $negativePaidCommission = $totalPaidReconAmount->negative_amount ?? 0;
                $positiveDueCommission = $positiveCommission - $positivePaidCommission;
                $negativeDueCommission = $negativeCommission - $negativePaidCommission;
                $commissionPayout = $positiveDueCommission * ($reconPayout / 100);

                $remainingCommission = $positiveDueCommission - $commissionPayout;

                if ($isUpfront) {
                    $inEligiblePid = array_diff($allPid, $eligiblePid);
                    $upfrontAmount = UserCommission::where(['user_id' => $userData->id, 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->whereIn('pid', $inEligiblePid)->sum('amount') ?? 0;
                    //  $upfrontReconAmount = ReconCommissionHistory::where(['user_id' => $userData->id,  'status' => 'payroll', 'is_displayed' => '1'])->where('type','!=','reconciliation')->whereIn("pid", $allPid)->sum("paid_amount");
                    $upfrontReconAmount = ReconCommissionHistory::where(['user_id' => $userData->id,  'status' => 'payroll', 'is_displayed' => '1'])->whereIn('pid', $inEligiblePid)->sum('paid_amount');
                    $totalupfront = $upfrontAmount + $upfrontReconAmount;
                    $negativeDueCommission = $negativeDueCommission - ($upfrontAmount + $upfrontReconAmount);
                    if ($negativeDueCommission > 0) {
                        $negativeDueCommission = $negativeDueCommission * ($reconPayout / 100);
                    }
                }

                $totalWithHoldAmount = $commissionPayout + $negativeDueCommission;

                /* OVERRIDE */
                $totalOverrideAmount = UserOverrides::selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as positive_amount,
                        SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as negative_amount')
                    ->where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])
                    ->whereIn('pid', $eligiblePid)->first();
                $positiveOverride = $totalOverrideAmount->positive_amount ?? 0;
                $negativeOverride = $totalOverrideAmount->negative_amount ?? 0;

                $totalPaidOverride = ReconOverrideHistory::selectRaw('SUM(CASE WHEN paid > 0 THEN paid ELSE 0 END) as positive_amount,
                        SUM(CASE WHEN paid < 0 THEN paid ELSE 0 END) as negative_amount')
                    ->where(['user_id' => $userId, 'is_ineligible' => '0', 'is_displayed' => '1'])->whereIn('pid', $eligiblePid)
                    ->when($isFinalize, function ($q) {
                        $q->where('status', 'payroll');
                    })->first();
                $positivePaidOverride = $totalPaidOverride->positive_amount ?? 0;
                $negativePaidOverride = $totalPaidOverride->negative_amount ?? 0;

                $positiveDueOverride = $positiveOverride - $positivePaidOverride;
                $negativeDueOverride = $negativeOverride - $negativePaidOverride;

                $overridePayout = $positiveDueOverride * ($reconPayout / 100);
                $remainingOverride = $positiveDueOverride - $overridePayout;
                $remaining = ($remainingCommission + $remainingOverride + $negativeDueCommission);
                $totalOverrideAmount = $overridePayout + $negativeDueOverride;

                /* CLAW BACK */
                $totalClawBackAmount = ClawbackSettlement::where(['user_id' => $userId, 'clawback_type' => 'reconciliation'])
                    ->where('recon_status', 1)
                    ->where(function ($q) {
                        $q->where('is_displayed', '1')->orWhere(function ($q2) {
                            $q2->where(['is_displayed' => '0', 'clawback_status' => '1']);
                        });
                    })->sum('clawback_amount') ?? 0;

                $reconPaidAmount = ReconClawbackHistory::where(['user_id' => $userId, 'is_displayed' => '1'])
                    ->when($isFinalize, function ($q) {
                        $q->where('status', 'payroll');
                    })->sum('paid_amount') ?? 0;
                // $totalClawBackAmount = $totalClawBackAmount - $reconPaidAmount;
                $totalClawBackAmount = $totalClawBackAmount;

                /* ADJUSTMENT */
                $totalAdjustment = ReconAdjustment::whereDate('start_date', $startDate)
                    ->whereDate('end_date', $endDate)
                    ->where('user_id', $userId)
                    ->whereNull('payroll_id')
                    ->whereNull('pay_period_from')
                    ->whereNull('pay_period_to')
                    ->whereNull('payroll_status')
                    ->sum('adjustment_amount');

                /* DEDUCTION */
                $deductionData = PayrollDeductions::where(['user_id' => $userId, 'is_move_to_recon' => 1, 'status' => 3])
                    ->where(['is_move_to_recon_paid' => 0])
                    ->whereBetween('pay_period_from', [$startDate, $endDate])
                    ->whereBetween('pay_period_to', [$startDate, $endDate])->sum('total');

                // $recondeductionhistories = ReconDeductionHistory::where(["user_id" => $userId])
                //     ->when($isFinalize, function ($q) {
                //         $q->where("status", "payroll");
                //     })->sum("total") ?? 0;

                // $deductionData = $deductionData - $recondeductionhistories;

                $imageUrl = null;
                if ($userData->image) {
                    $imageUrl = s3_getTempUrl(config('app.domain_name').'/'.$userData->image);
                }

                $totalDueAmount = ($totalWithHoldAmount + $totalOverrideAmount);
                // $payout = $totalDueAmount + $totalAdjustment - $totalClawBackAmount;
                $payout = $totalDueAmount + $totalAdjustment - $deductionData - $totalClawBackAmount;
                if (! $userSkipStatus) {
                    $finalUserIds[] = $userId;
                }

                $response[] = [
                    'user_id' => $userData->id,
                    'pids' => $eligiblePid,
                    'emp_img' => $userData->image ? $userData->image : 'Employee_profile/default-user.png',
                    'emp_name' => ucfirst($userData->first_name).' '.ucfirst($userData->last_name),
                    'emp_img_s3' => $imageUrl,
                    'commissionWithholding' => $totalWithHoldAmount,
                    'overrideDue' => $totalOverrideAmount,
                    'total_due' => $totalDueAmount,
                    'remaining' => $remaining,
                    'clawbackDue' => (-1 * $totalClawBackAmount),
                    'payout' => $payout,
                    'percentage' => $reconPayout,
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

    public function normalizeIds($ids)
    {
        return $ids === 'all' ? [] : explode(',', implode(',', $ids));
    }

    public function getUserIds($officeIds, $positionIds, $search)
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

    public function getUserId($userId, $startDate, $endDate)
    {
        $pid = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->pluck('pid');

        $salesUserIds = SaleMasterProcess::whereIn('pid', $pid)
            ->select(['closer1_id', 'closer2_id', 'setter1_id', 'setter2_id'])
            ->get()
            ->map(function ($item) {
                return collect($item->toArray())->filter(function ($value) {
                    return ! is_null($value);
                })->values();
            })
            ->toArray();
        $saleUserIds = [];
        if (count($salesUserIds) > 0) {
            foreach ($salesUserIds as $data) {
                foreach ($data as $val) {
                    $saleUserIds[] = $val;
                }
            }
        }

        $salesExternalUserIds = ExternalSaleWorker::whereIn('pid', $pid)
            ->select(['user_id'])
            ->get()
            ->map(function ($item) {
                return collect($item->toArray())->filter(function ($value) {
                    return ! is_null($value);
                })->values();
            })
            ->toArray();

        if (count($salesExternalUserIds) > 0) {
            foreach ($salesExternalUserIds as $data) {
                foreach ($data as $val) {
                    $salesExternalUserIds[] = $val;
                }
            }
        }
        $salesUserIds = array_merge($salesUserIds, $salesExternalUserIds);

        $commission = UserCommission::where(['is_displayed' => '1'])->whereIn('user_id', $userId)->whereIn('pid', $pid)->pluck('user_id')->toArray();
        $override = UserOverrides::where(['is_displayed' => '1'])->whereIn('user_id', $userId)->whereIn('pid', $pid)->pluck('user_id')->toArray();
        $clawBack = ClawbackSettlement::whereIn('user_id', $userId)->where(function ($q) {
            $q->where('is_displayed', '1')->orWhere(function ($q2) {
                $q2->where(['is_displayed' => '0', 'clawback_status' => '1']);
            });
        })->pluck('user_id')->toArray();

        return array_values(array_unique(array_merge($commission, $override, $clawBack, $saleUserIds)));
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
