<?php

namespace App\Http\Controllers\API\V2\Recon;

use App\Core\Traits\ReconTraits\ReconClawbackSendToPayrollTraits;
use App\Core\Traits\ReconTraits\ReconCommissionSendToPayrollTraits;
use App\Core\Traits\ReconTraits\ReconDeductionSendToPayrollTraits;
use App\Core\Traits\ReconTraits\ReconRoutineTraits;
use App\Core\Traits\ReconTraits\ReconSendToPayrollTraits;
use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\FrequencyType;
use App\Models\Locations;
use App\Models\MoveToReconciliation;
use App\Models\Payroll;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionPayFrequency;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionsDeductionLimit;
use App\Models\ReconciliationFinalize;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationFinalizeHistoryLock;
use App\Models\ReconciliationStatusForSkipedUser;
use App\Models\ReconClawbackHistory;
use App\Models\ReconClawbackHistoryLock;
use App\Models\ReconCommissionHistory;
use App\Models\ReconCommissionHistoryLock;
use App\Models\ReconDeductionHistory;
use App\Models\ReconDeductionHistoryLock;
use App\Models\ReconOverrideHistory;
use App\Models\ReconOverrideHistoryLock;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
use Carbon\Carbon;
use Doctrine\DBAL\Query\QueryException;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

// use App\Models\MoveToReconHistory;

class ReconController extends Controller
{
    use ReconClawbackSendToPayrollTraits, ReconCommissionSendToPayrollTraits, ReconDeductionSendToPayrollTraits, ReconRoutineTraits, ReconSendToPayrollTraits;

    public $isPestServer = false;

    public const DATE_FORMAT_VALIDATION = 'date_format:Y-m-d';

    public function __construct()
    {
        /* check server is pest or not */
        $companyProfile = CompanyProfile::first();
        $this->isPestServer = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $data = Positions::withcount('peoples')->with('departmentDetail', 'Commission', 'Upfront', 'deductionname', 'Override', 'deductionlimit', 'OverrideTier', 'reconciliation', 'payFrequency')->where('id', $id)->first();
        $positionCommissionDeductionSetting = PositionCommissionDeductionSetting::where('position_id', $id)->first();
        $positionDeductionLimit = PositionsDeductionLimit::where('position_id', $id)->first();

        $overrides = $this->getOverrides($data);
        $deductionName = $this->getDeductionNames($data);

        $isEnable = $this->calculateIsEnable($data, $id);

        $formattedData = $this->formatData($data, $positionCommissionDeductionSetting, $positionDeductionLimit, $overrides, $deductionName, $isEnable);

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $formattedData], 200);
    }

    protected function getOverrides($data)
    {
        $overrides = [];
        foreach ($data->Override as $override) {
            $overrides[] = [
                'override_id' => $override->override_id,
                'status' => $override->status,
                'settlement_id' => $override->settlement_id,
                'override_ammount' => $override->override_ammount,
                'override_ammount_locked' => $override->override_ammount_locked,
                'type' => $override->type,
                'override_type_locked' => $override->override_type_locked,
                'override_type_id' => isset($override->overridesDetail->id) ? $override->overridesDetail->id : null,
                'overrides_type' => isset($override->overridesDetail->overrides_type) ? $override->overridesDetail->overrides_type : null,
            ];
        }

        return $overrides;
    }

    protected function getDeductionNames($data)
    {
        $deductionName = [];
        foreach ($data->deductionname as $deduction) {
            $deductionName[] = [
                'id' => $deduction->id,
                'deduction_setting_id' => $deduction->deduction_setting_id,
                'position_id' => $deduction->sub_position_id,
                'cost_center_id' => $deduction->cost_center_id,
                'deduction_type' => $deduction->deduction_type,
                'ammount_par_paycheck' => $deduction->ammount_par_paycheck,
                'cost_center_name' => isset($deduction->costcenter->name) ? $deduction->costcenter->name : null,
            ];
        }

        return $deductionName;
    }

    protected function calculateIsEnable($data, $id)
    {
        $isEnable = 1;

        if ($data) {
            $payFrequency = PositionPayFrequency::where('position_id', $id)->first();
            if ($payFrequency && ($data->peoples_count > 0 || Payroll::where('position_id', $id)->exists())) {
                $isEnable = 0;
            }
        }

        return $isEnable;
    }

    protected function formatData($data, $positionCommissionDeductionSetting, $positionDeductionLimit, $overrides, $deductionName, $isEnable)
    {
        return [
            'position_id' => $data->id,
            'parent_position_id' => $data->parent_id,
            'is_manager' => $data->is_manager,
            'is_stack' => $data->is_stack,
            'position_name' => isset($data->position_name) ? $data->position_name : null,
            'commission_parentage' => data_get($data, 'Commission.0.commission_parentage'),
            'commission_parentag_hiring_locked' => data_get($data, 'Commission.0.commission_parentag_hiring_locked'),
            'commission_amount_type' => data_get($data, 'Commission.0.commission_amount_type'),
            'commission_amount_type_locked' => data_get($data, 'Commission.0.commission_amount_type_locked'),
            'commission_parentag_type_hiring_locked' => data_get($data, 'Commission.0.commission_parentag_type_hiring_locked'),
            'commission_structure_type' => data_get($data, 'Commission.0.commission_structure_type'),
            'upfront_ammount' => data_get($data, 'Upfront.0.upfront_ammount'),
            'upfront_ammount_locked' => data_get($data, 'Upfront.0.upfront_ammount_locked', 0),
            'upfront_status' => data_get($data, 'Upfront.0.upfront_status'),
            'calculated_by' => data_get($data, 'Upfront.0.calculated_by'),
            'calculated_locked' => data_get($data, 'Upfront.0.calculated_locked'),
            'upfront_system' => data_get($data, 'Upfront.0.upfront_system'),
            'upfront_system_locked' => data_get($data, 'Upfront.0.upfront_system_locked'),
            'upfront_limit' => data_get($data, 'Upfront.0.upfront_limit'),
            'deduction_status' => isset($positionDeductionLimit->status) ? $positionDeductionLimit->status : 0,
            'reconciliation_status' => isset($data->reconciliation->status) ? $data->reconciliation->status : null,
            'deduction_locked' => isset($positionCommissionDeductionSetting->deducation_locked) ? $positionCommissionDeductionSetting->deducation_locked : null,
            'deduction' => $deductionName,
            'limit_ammount' => isset($data->deductionlimit->limit_ammount) ? $data->deductionlimit->limit_ammount : null,
            'limit_type' => isset($data->deductionlimit->limit_type) ? $data->deductionlimit->limit_type : null,
            'limit' => isset($data->deductionlimit->limit) ? $data->deductionlimit->limit : null,
            'deduction_status' => isset($data->deductionlimit->status) ? $data->deductionlimit->status : null,
            'override' => $overrides, // isset($data->Override) ? $data->Override : null,
            'tier_override_status' => isset($data->OverrideTier->tier_status) ? $data->OverrideTier->tier_status : null,
            'sliding_scale' => isset($data->OverrideTier->sliding_scale) ? $data->OverrideTier->sliding_scale : null,
            'sliding_scale_locked' => isset($data->OverrideTier->sliding_scale_locked) ? $data->OverrideTier->sliding_scale_locked : null,
            'levels' => isset($data->OverrideTier->levels) ? $data->OverrideTier->levels : null,
            'level_locked' => isset($data->OverrideTier->level_locked) ? $data->OverrideTier->level_locked : null,
            'commission_withheld' => isset($data->reconciliation->commission_withheld) ? $data->reconciliation->commission_withheld : null,
            'commission_type' => isset($data->reconciliation->commission_type) ? $data->reconciliation->commission_type : null,
            'maximum_withheld' => isset($data->reconciliation->maximum_withheld) ? $data->reconciliation->maximum_withheld : null,
            'override_settlement' => isset($data->reconciliation->override_settlement) ? $data->reconciliation->override_settlement : null,
            'clawback_settlement' => isset($data->reconciliation->clawback_settlement) ? $data->reconciliation->clawback_settlement : null,
            'stack_settlement' => isset($data->reconciliation->stack_settlement) ? $data->reconciliation->stack_settlement : null,
            'settlement_id' => 1,
            'frequency_type_id' => isset($data->payFrequency->frequency_type_id) ? $data->payFrequency->frequency_type_id : null,
            'frequency_type_name' => isset($data->payFrequency->frequencyType->name) ? $data->payFrequency->frequencyType->name : null,
            'first_months' => isset($data->payFrequency->first_months) ? $data->payFrequency->first_months : null,
            'first_day' => isset($data->payFrequency->first_day) ? $data->payFrequency->first_day : null,
            'day_of_week' => isset($data->payFrequency->day_of_week) ? $data->payFrequency->day_of_week : null,
            'day_of_months' => isset($data->payFrequency->day_of_months) ? $data->payFrequency->day_of_months : null,
            'pay_period' => isset($data->payFrequency->pay_period) ? $data->payFrequency->pay_period : null,
            'monthly_per_days' => isset($data->payFrequency->monthly_per_days) ? $data->payFrequency->monthly_per_days : null,
            'commission_type_locked' => isset($data->reconciliation->commission_type_locked) ? $data->reconciliation->commission_type_locked : null,
            'commission_withheld_locked' => isset($data->reconciliation->commission_withheld_locked) ? $data->reconciliation->commission_withheld_locked : null,
            'is_frequency_enable' => $isEnable,
        ];
    }

    /**
     * Method reconciliationListUserSkipped: This function is use for the user skip recon
     *
     * @param  Request  $request  [explicit description]
     */
    public function reconciliationListUserSkipped(Request $request): JsonResponse
    {
        $checkParamsValidation = $this->validateSkipReconUser($request);
        if ($checkParamsValidation->fails()) {
            return response()->json(['error' => $checkParamsValidation->errors()], 400);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $userIds = $this->getUserIds($request);
        $this->createReconciliationStatusForSkippedUsers($userIds, $startDate, $endDate);

        return response()->json([
            'ApiName' => 'reconciliation user skipped',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    /**
     * Method reconciliationListUserSkippedUndo: This function is used for the skip recon undo process.
     *
     * @param  Request  $request  [explicit description]
     */
    public function reconciliationListUserSkippedUndo(Request $request): JsonResponse
    {
        $user_id = implode(',', $request->user_id);
        $userIds = explode(',', $user_id);

        foreach ($userIds as $userId) {
            User::where('id', $userId)->first();
            ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->delete();
        }

        return response()->json([
            'ApiName' => 'reconciliation user status undo',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    /**
     * Method finalizeReconDraftList: THis function is used for the show recon finalize draft list
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
    public function finalizeReconDraftList(Request $request)
    {
        try {
            $perPage = $request->perpage ?? 10;
            if (! is_numeric($perPage) || $perPage <= 0) {
                $perPage = 10;
            }

            $sortStr = 'start_date ASC';
            $sorting = $request->sort;
            switch ($sorting) {
                case 'commission':
                    $sortStr = "commissions $request->sort_val";
                    break;
                case 'override':
                    $sortStr = "overrides $request->sort_val";
                    break;
                case 'total_due':
                    $sortStr = "total_due $request->sort_val";
                    break;
                case 'clawback':
                    $sortStr = "clawbacks $request->sort_val";
                    break;
                case 'adjustments':
                    $sortStr = "adjustments + deductions $request->sort_val";
                    break;
                case 'payout':
                    $sortStr = "net_amount $request->sort_val";
                    break;
                case 'remaining':
                    $sortStr = "remaining $request->sort_val";
                    break;
                default:
                    $sortStr = 'start_date ASC';
                    break;
            }

            $reconFinalized = ReconciliationFinalize::where(['status' => 'finalize'])->orderByRaw("$sortStr")->paginate($perPage);
            $reconFinalized->getCollection()->transform(function ($item) {

                $data = DB::table('reconciliation_finalize_history')
                    ->select(
                        'start_date',
                        'end_date',
                        'finalize_id',
                        DB::raw('GROUP_CONCAT(DISTINCT l_t.office_name) AS office'),
                        DB::raw('GROUP_CONCAT(DISTINCT p_t.position_name) AS position')
                    )
                    ->leftJoin('locations as l_t', 'office_id', '=', 'l_t.id')
                    ->leftJoin('positions as p_t', 'position_id', '=', 'p_t.id')
                    ->where('status', 'finalize')
                    ->where('finalize_id', $item->id)
                    ->where('start_date', $item->start_date)
                    ->where('end_date', $item->end_date)
                    ->first();

                // $office = ucfirst($item->office_id);
                // $position = ucfirst($item->position_id);
                // if ($item->office_id != 'all') {
                //     $office = Locations::whereIn('id', explode(',', $item->office_id))->pluck('office_name')->implode(', ');
                // }
                // if ($item->position_id != 'all') {
                //     $position = Positions::whereIn('id', explode(',', $item->position_id))->pluck('position_name')->implode(', ');
                // }

                return [
                    'finalize_id' => $item->id,
                    'start_date' => $item->start_date,
                    'end_date' => $item->end_date,
                    'office' => $data->office,
                    'position' => $data->position,
                    'commission' => $item->commissions,
                    'overrides' => $item->overrides,
                    'total_due' => $item->total_due,
                    'clawback' => -1 * ($item->clawbacks),
                    'adjustments' => ($item->adjustments - $item->deductions),
                    'payout' => $item->net_amount,
                    'remaining' => $item->remaining,
                    'payout_per' => $item->payout_percentage,
                    'status' => $item->status,
                    'finalize_count' => 0,
                ];
            });

            return response()->json([
                'ApiName' => 'reconciliation finalize list',
                'status' => true,
                'message' => 'Successfully.',
                'total' => [],
                'data' => $reconFinalized,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'ApiName' => 'reconciliation finalize list',
                'status' => false,
                'message' => '!! Error . !! '.$th->getMessage().' At line number '.$th->getLine(),
                'total' => [],
                'data' => [],
            ], 400);
        }
    }

    /**
     * Method reconSendToPayroll: this function is used for the send to payroll
     *
     * @param  Request  $request  [explicit description]
     * @return void
     */
    public function reconSendToPayroll(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'start_date' => ['required', self::DATE_FORMAT_VALIDATION],
            'end_date' => ['required', self::DATE_FORMAT_VALIDATION],
            'data' => ['required', 'array'],
            'finalize_id' => ['required'],
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $data = $request->data;
        $stopUserPayRoll = 0;
        $finalizeId = $request->finalize_id;

        /* update recon commission history data */
        $commissionFinalizeData = ReconCommissionHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->where('status', 'finalize')
            ->where('finalize_id', $finalizeId)
            ->get();
        if ($commissionFinalizeData->isNotEmpty()) {
            $this->sendToPayrollCommission($commissionFinalizeData, $data);
        }

        /* Retrieve finalized reconciliation data for the given date range */
        $reconCommissionFinalizeHistoryData = ReconciliationFinalizeHistory::where('status', 'finalize')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('finalize_id', $finalizeId)
            ->get();
        $overrideFinalizeData = ReconOverrideHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->where('status', 'finalize')
            ->where('finalize_id', $finalizeId)
            ->get();

        /* update recon override history data */
        if ($overrideFinalizeData->isNotEmpty()) {
            $this->sendToPayrollOverride($overrideFinalizeData, $data);
        }

        /* update recon clawback history data */
        $clawbackFinalizeData = ReconClawbackHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('status', 'finalize')
            ->where('finalize_id', $finalizeId)
            ->get();
        if ($clawbackFinalizeData->isNotEmpty()) {
            $this->sendToPayrollClawback($clawbackFinalizeData, $data);
        }

        /* update deduction clawback history data */
        $deductionFinalizeData = ReconDeductionHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('status', 'finalize')
            ->where('finalize_id', $finalizeId)
            ->get();
        if ($deductionFinalizeData->isNotEmpty()) {
            $this->sendToPayrollDeduction($deductionFinalizeData, $data);
        }

        if ($reconCommissionFinalizeHistoryData->isNotEmpty()) {
            /* Get unique user IDs from the finalized reconciliation data */
            $userIds = $reconCommissionFinalizeHistoryData->pluck('user_id')->unique();

            /* Retrieve all user data with position details in a single query */
            $usersData = User::with('positionDetail')->whereIn('id', $userIds)->get()->keyBy('id');
            /* Iterate over each finalized reconciliation record */
            foreach ($reconCommissionFinalizeHistoryData as $reconCommissionFinalizeHistoryValue) {
                $userId = $reconCommissionFinalizeHistoryValue->user_id;
                /* update recon adjustment */

                /* manage pay frequency and update status in payroll based on frequency */
                $this->manageReconPayrollPayFrequency($usersData, $userId, $data, $reconCommissionFinalizeHistoryValue, $startDate, $endDate);

                if ($reconCommissionFinalizeHistoryValue->payout == 100 || $reconCommissionFinalizeHistoryValue->payout == '100') {
                    /* UserReconciliationWithholding::where(function ($query) use ($reconCommissionFinalizeHistoryValue) {
                        $query->where("closer_id", $reconCommissionFinalizeHistoryValue->user_id)->orWhere("setter_id", $reconCommissionFinalizeHistoryValue->user_id);
                    })
                        ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                        ->where('finalize_status', 0)
                        ->where('status', 'unpaid')
                        ->update(['finalize_status' => 1, "status" => "paid"]); */

                    UserCommission::where('user_id', $reconCommissionFinalizeHistoryValue->user_id)
                        ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                        ->where('settlement_type', 'reconciliation')
                        ->where('amount_type', 'reconciliation')
                        ->update(['recon_status' => 3]);
                }
            }
        }
        /* Manage  stop user payroll based on the condition */
        if ($stopUserPayRoll === 1) {
            $response = response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Can not send to payroll. Payroll has been stopped for this employee.',
            ]);
        } elseif ($stopUserPayRoll > 1) {
            $response = response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Some users Cannot send to payroll. Because Payroll has been stopped for these employee.',
            ]);
        } else {
            $response = response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Successfully.',
            ]);
        }
        /* create recon send to payroll lock data code */
        $this->createReconLockData();

        return $response;
    }

    public function manageReconPayrollPayFrequency($usersData, $userId, $data, $reconCommissionFinalizeHistoryValue, $startDate, $endDate)
    {
        /* Calculate the totals for commission, override, clawback, adjustments, and net pay */
        $userCalculation = ReconciliationFinalizeHistory::where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('status', 'finalize')
            ->where('user_id', $reconCommissionFinalizeHistoryValue->user_id);
        $commission = $userCalculation->sum('paid_commission');
        $overrideDue = $userCalculation->sum('paid_override');
        $clawbackDue = $userCalculation->sum('clawback');
        $totalAdjustments = $userCalculation->sum('adjustments');
        // /* $totalDue = $userCalculation->sum('gross_amount'); */
        $netPay = $commission + $overrideDue + $totalAdjustments - $clawbackDue;

        /* Get the latest sent count for payroll status in reconciliation finalize history */
        $userReconCount = ReconciliationFinalizeHistory::where('status', 'payroll')
            ->orderBy('id', 'desc')
            ->first();

        /* Calculate the new sent count */
        $sendCount = isset($userReconCount->sent_count) ? $userReconCount->sent_count + 1 : 1;

        /* Retrieve the user data from the pre-fetched collection */
        $userData = $usersData->get($userId);
        /* Handle based on pay frequency type */
        switch ($userData?->positionDetail?->payFrequency?->frequency_type_id) {
            case 1:
                $payPeriodFrom = $data['daily']['pay_period_from'];
                $payPeriodTo = $data['daily']['pay_period_to'];
                $this->handleDailyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 2:
                $payPeriodFrom = $data['weekly']['pay_period_from'];
                $payPeriodTo = $data['weekly']['pay_period_to'];
                $this->handleWeeklyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 5:
                $payPeriodFrom = $data['monthly']['pay_period_from'];
                $payPeriodTo = $data['monthly']['pay_period_to'];
                $this->handleMonthlyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::BI_WEEKLY_ID:
                $payPeriodFrom = $data['biweekly']['pay_period_from'];
                $payPeriodTo = $data['biweekly']['pay_period_to'];
                $this->handleBiAndSemiWeeklyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::SEMI_MONTHLY_ID:
                $payPeriodFrom = $data['semimonthly']['pay_period_from'];
                $payPeriodTo = $data['semimonthly']['pay_period_to'];
                $this->handleBiAndSemiWeeklyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo);
                break;
            default:
                $payPeriodFrom = null;
                $payPeriodTo = null;
        }

        /* Getting payroll Data based on the condition */
        $payData = Payroll::where('user_id', $reconCommissionFinalizeHistoryValue->user_id)
            ->where([
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();
        if ($payData) {
            if ($payData->reconciliation > 0) {
                $recon = $payData->reconciliation;
            } else {
                $recon = 0;
            }
            $updateData = [
                'reconciliation' => $netPay + $recon,
            ];

            /* update recon amount in payrolls */
            Payroll::where('id', $payData->id)->update($updateData);

            $userReconComm = ReconciliationFinalizeHistory::where([
                'status' => 'payroll',
                'user_id' => $reconCommissionFinalizeHistoryValue->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();

            /* update payroll id in recon history table after send to payroll recon data */
            if ($userReconComm) {
                ReconciliationFinalizeHistory::where([
                    'status' => 'payroll',
                    'user_id' => $reconCommissionFinalizeHistoryValue->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                ])->update(['payroll_id' => $payData->id]);
            }

            /* get data from user_reconciliation_withholds table */
            /* $commissionAdjustment = UserReconciliationWithholding::where('closer_id', $reconCommissionFinalizeHistoryValue->user_id)
                ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                ->orWhere('setter_id', $reconCommissionFinalizeHistoryValue->user_id)
                ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                ->get(); */

            $commissionAdjustment = UserCommission::where('user_id', $reconCommissionFinalizeHistoryValue->user_id)
                ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                ->where('settlement_type', 'reconciliation')
                ->where('amount_type', 'reconciliation')
                ->get();

            if ($commissionAdjustment->isNotEmpty()) {
                /* UserReconciliationWithholding::where('closer_id', $reconCommissionFinalizeHistoryValue->user_id)
                    ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                    ->orWhere('setter_id', $reconCommissionFinalizeHistoryValue->user_id)
                    ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                    ->update(['adjustment_amount' => 0]); */
            }

            /* Move to recon from payroll */
            MoveToReconciliation::where('user_id', $reconCommissionFinalizeHistoryValue->user_id)
                ->where('pid', $reconCommissionFinalizeHistoryValue->pid)
                ->update(['status' => 1]);

            $totalClawbackDue = ClawbackSettlement::where([
                'user_id' => $reconCommissionFinalizeHistoryValue->user_id,
                'clawback_type' => 'reconciliation',
                'pid' => $reconCommissionFinalizeHistoryValue->pid,
                'status' => '1',
                'payroll_id' => 0,
            ])->first();

            if ($totalClawbackDue) {
                Log::info('clawback insert');
                $totalClawbackDue->update([
                    'payroll_id' => $payData->id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                ]);
            }
        } else {
            $payroll_data = Payroll::create([
                'user_id' => $userData->id,
                'position_id' => $userData->sub_position_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'status' => 1,
                'reconciliation' => $reconCommissionFinalizeHistoryValue->net_amount,
            ]);
            $payRollId = $payroll_data->id;

            $userReconComm = ReconciliationFinalizeHistory::where([
                'status' => 'payroll',
                'user_id' => $reconCommissionFinalizeHistoryValue->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();
            if (isset($userReconComm) && $userReconComm != '') {
                $count = isset($userReconComm->sent_count) ? $userReconComm->sent_count : 0;
                // $sendCount = $count+1;
                $update = ReconciliationFinalizeHistory::where([
                    'status' => 'payroll',
                    'user_id' => $reconCommissionFinalizeHistoryValue->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                ])->update(['payroll_id' => $payRollId, 'sent_count' => $sendCount]);
            }

            // clawback -------------

            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $reconCommissionFinalizeHistoryValue->user_id, 'clawback_type' => 'reconciliation', 'pid' => $reconCommissionFinalizeHistoryValue->pid, 'status' => '1', 'payroll_id' => 0])->first();
            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                ClawbackSettlement::where([
                    'user_id' => $reconCommissionFinalizeHistoryValue->user_id,
                    'clawback_type' => 'reconciliation',
                    'pid' => $reconCommissionFinalizeHistoryValue->pid,
                    'status' => '1',
                    'payroll_id' => 0,
                ])->update([
                    'payroll_id' => $payRollId,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                ]);
                ClawbackSettlement::where([
                    'clawback_settlements.user_id' => $reconCommissionFinalizeHistoryValue->user_id,
                    'clawback_settlements.clawback_type' => 'next payroll',
                    'clawback_settlements.pid' => $reconCommissionFinalizeHistoryValue->pid,
                    'clawback_settlements.status' => 6,
                    'clawback_settlements.is_move_to_recon' => 1,
                ])->join('sale_masters AS s_m_t_c_s', function ($join) use ($startDate, $endDate) {
                    $join->on('s_m_t_c_s.pid', '=', 'clawback_settlements.pid')
                        ->whereBetween('s_m_t_c_s.customer_signoff', [$startDate, $endDate])
                        ->whereBetween('s_m_t_c_s.date_cancelled', [$startDate, $endDate]);
                })
                    ->update([
                        'clawback_settlements.is_move_to_recon' => 0,
                    ]);
            }
        }
    }

    // Function to handle daily frequency type
    public function handleDailyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo)
    {
        $userReconciliationCommission = $reconCommissionFinalizeHistoryValue;
        // Update ReconciliationFinalizeHistory
        ReconciliationFinalize::where('status', 'finalize')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $sendCount,
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        ReconciliationFinalizeHistory::where('status', 'finalize')
            ->where('user_id', $userReconciliationCommission->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $sendCount,
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        // if(isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id!=null)
        if (isset($userReconciliationCommission->id) && $userReconciliationCommission->id != null) {
            Payroll::where(['id' => $userReconciliationCommission->payroll_id])->update(['status' => 7]);
        }
        // Additional logic for Daily frequency type
        // ...

    }

    // Function to handle weekly frequency type
    public function handleWeeklyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo)
    {
        $userReconCommission = $reconCommissionFinalizeHistoryValue;
        // Update ReconciliationFinalizeHistory

        ReconciliationFinalize::where('status', 'finalize')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        ReconciliationFinalizeHistory::where('status', 'finalize')
            ->where('user_id', $userReconCommission->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $sendCount,
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        if ($userReconCommission->id) {
            Payroll::where(['id' => $userReconCommission->payroll_id])
                ->update(['status' => 7]);
        }
    }

    // Function to handle monthly frequency type
    public function handleMonthlyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo)
    {

        ReconciliationFinalize::where('status', 'finalize')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        ReconciliationFinalizeHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionFinalizeHistoryValue->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $sendCount,
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        if ($reconCommissionFinalizeHistoryValue->id) {
            Payroll::where(['id' => $reconCommissionFinalizeHistoryValue->id])
                ->update(['status' => 1]);
        }
    }

    // Function to handle monthly frequency type
    public function handleBiAndSemiWeeklyFrequency($reconCommissionFinalizeHistoryValue, $startDate, $endDate, $sendCount, $payPeriodFrom, $payPeriodTo)
    {
        ReconciliationFinalize::where('status', 'finalize')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        ReconciliationFinalizeHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionFinalizeHistoryValue->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $sendCount,
                'executed_on' => Carbon::now()->format('Y-m-d h:i:s'),
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);

        if ($reconCommissionFinalizeHistoryValue->id) {
            Payroll::where(['id' => $reconCommissionFinalizeHistoryValue->id])
                ->update(['status' => 1]);
        }
    }

    private function getUserIds($request)
    {
        $userIds = $this->extractIds($request->user_id);
        $officeIds = $this->extractIds($request->office_id);
        $positionIds = $this->extractIds($request->position_id);

        if ($request->select_type === 'all') {
            return $this->getAllUserIds($officeIds, $positionIds);
        }

        return $userIds;
    }

    private function extractIds($ids)
    {
        return is_array($ids) ? $ids : explode(',', $ids);
    }

    private function getAllUserIds($officeIds, $positionIds)
    {
        $response = User::whereIn('office_id', $officeIds)
            ->whereIn('sub_position_id', $positionIds)
            ->pluck('id')
            ->toArray();
        if ($officeIds === ['all'] && $positionIds === ['all']) {
            $response = User::orderBy('id', 'desc')->pluck('id')->toArray();
        } elseif ($officeIds === ['all']) {
            $response = User::whereIn('sub_position_id', $positionIds)->pluck('id')->toArray();
        } elseif ($positionIds === ['all']) {
            $response = User::whereIn('office_id', $officeIds)->pluck('id')->toArray();
        }

        return $response;
    }

    private function getSalesPid($userIds, $startDate, $endDate)
    {
        /* $pids = UserReconciliationWithholding::whereIn('closer_id', $userIds)
            ->where('finalize_status', 0)
            ->where('status', 'unpaid')
            ->orWhereIn('setter_id', $userIds)
            ->where('status', 'unpaid')
            ->where('finalize_status', 0)
            ->pluck('pid')
            ->toArray(); */

        $pids = UserCommission::whereIn('user_id', $userIds)
            ->where('settlement_type', 'reconciliation')
            ->where('amount_type', 'reconciliation')
            ->where('recon_status', '!=', '3')
            ->pluck('pid')
            ->toArray();

        return SalesMaster::with('salesMasterProcess')
            ->whereIn('pid', $pids)
            ->whereBetween('m2_date', [$startDate, $endDate])
            ->pluck('pid')
            ->toArray();
    }

    private function getPayrollUserIds($salesPid)
    {
        $findUsers = SalesMaster::with('salesMasterProcess')
            ->whereIn('pid', $salesPid)
            ->get();

        $userIds = [];
        foreach ($findUsers as $findUser) {
            $userIds[] = $findUser->salesMasterProcess->closer1_id;
            $userIds[] = $findUser->salesMasterProcess->setter1_id;
        }

        return array_unique($userIds);
    }

    private function createReconciliationStatusForSkippedUsers($userIds, $startDate, $endDate)
    {
        foreach ($userIds as $userId) {
            $userData = User::find($userId);
            if ($userData && ! $this->userHasSkippedReconciliation($userId, $startDate, $endDate)) {
                ReconciliationStatusForSkipedUser::create([
                    'user_id' => $userId,
                    'office_id' => $userData->office_id,
                    'position_id' => $userData->sub_position_id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'skipped',
                ]);
                ReconciliationFinalizeHistory::where([
                    'user_id' => $userId,
                    'status' => 'finalize',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ])->update([
                    'user_recon_is_skip' => 1,
                ]);
            }
        }
    }

    private function userHasSkippedReconciliation($userId, $startDate, $endDate)
    {
        return ReconciliationStatusForSkipedUser::where('user_id', $userId)
            ->where('status', 'skipped')
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->exists();
    }

    /**
     * Method reconCompanySettingStatus : This function is use for manage recon is enable or not on whole system.
     *
     * @return void
     */
    public function reconCompanySettingStatus(Request $request)
    {
        DB::beginTransaction(); // Start a transaction
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|boolean',
                'type' => 'required|in:reconciliation',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $status = CompanySetting::where('type', $request->type)->first();

            if ($status) {
                // Check if there are any unpaid reconciliations
                // $positionReconciliations = UserReconciliationWithholding::where('status', 'unpaid')->exists();
                $positionReconciliations = UserCommission::where('recon_status', '!=', '3')->where('settlement_type', 'reconciliation')->where('amount_type', 'reconciliation')->exists();
                $payrollReconciliations = Payroll::sum('reconciliation');
                if ($positionReconciliations && $request->status === false && $payrollReconciliations == 0) {
                    return response()->json([
                        'ApiName' => 'Check Recon Status',
                        'status' => false,
                        'message' => 'Reconciliation cannot be disabled due to unpaid reconciliations.',
                        'checkStatus' => $positionReconciliations ? 1 : 0,
                    ], 400);
                }
                // Update the status
                $status->status = $request->status;
                $status->save();
                $page = 'Setting';
                $action = 'Company Setting Status Updated';
                $description = 'Type =>'.$request->type.', '.'Status =>'.$request->status;
                user_activity_log($page, $action, $description);

                if ($request->status === false) {
                    PositionReconciliations::query()->update(['status' => 0]);
                }

                $description = 'Type =>'.$request->type.', '.'Status =>'.$request->status;
                user_activity_log('Setting', 'Company Setting Status Updated', $description);

                DB::commit(); // Commit the transaction

                $response = response()->json([
                    'ApiName' => 'setup-active-inactive',
                    'status' => true,
                    'message' => 'Status Updated Successfully.',
                    'data' => $status,
                ], 200);
            } else {
                DB::rollBack(); // Rollback the transaction if no status found
                $response = response()->json([
                    'ApiName' => 'setup-active-inactive',
                    'status' => false,
                    'message' => 'Company setting not found.',
                    'data' => [],
                ], 404);
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            $response = response()->json([
                'ApiName' => 'setup-active-inactive',
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        } catch (QueryException $e) {
            DB::rollBack();
            $response = response()->json([
                'ApiName' => 'setup-active-inactive',
                'status' => false,
                'message' => 'Database error: '.$e->getMessage(),
                'data' => [],
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            $response = response()->json([
                'ApiName' => 'setup-active-inactive',
                'status' => false,
                'message' => 'An unexpected error occurred: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }

        return $response;
    }

    /**
     * Method reconPositionStatus: check reconciliation user is exits or not.
     *
     * @param  Request  $request  [explicit description]
     */
    public function reconPositionStatus(Request $request): JsonResponse
    {
        try {
            $positionStatus = false; // position is default false
            $checkPositionId = $request->position_id;
            $positionData = Positions::findOrFail($checkPositionId);
            $positionUserId = $positionData?->peoples->pluck('id')->toArray();

            /* $positionReconciliations = UserReconciliationWithholding::where('status', 'unpaid')->where(function ($query) use ($positionUserId) {
                $query->whereIn("closer_id", $positionUserId)
                    ->orWhereIn("setter_id", $positionUserId);
            })->select('closer_id', "setter_id", "id")->exists(); */

            $positionReconciliations = UserCommission::where('recon_status', '!=', '3')
                ->where('settlement_type', 'reconciliation')
                ->where('amount_type', 'reconciliation')
                ->whereIn('user_id', $positionUserId)
                ->select('id', 'user_id')->exists();

            $commissionRecon = UserCommission::wherein('user_id', $positionUserId)->where('status', 6)->where('is_move_to_recon', 1)->exists();

            $overrideRecon = UserOverrides::wherein('user_id', $positionUserId)
                ->orWhere('status', 6)
                ->orWhere('is_move_to_recon', 1)
                ->orWhere('overrides_settlement_type', 'reconciliation')
                ->get()->toArray();

            $clawbackRecon = ClawbackSettlement::wherein('user_id', $positionUserId)
                ->orWhere('status', 6)
                ->orWhere('is_move_to_recon', 1)
                ->orWhere('clawback_type', 'reconciliation')
                ->get()->toArray();

            if ($positionReconciliations || $commissionRecon || $overrideRecon || $clawbackRecon) {
                $positionStatus = true;
            } else {
                $reconPosition = PositionReconciliations::where('position_id', $checkPositionId)->first();
                PositionReconciliations::find($reconPosition->id)->update([
                    'status' => $reconPosition->status == 1 ? 0 : 1,
                ]);
            }

            return response()->json([
                'ApiName' => 'Check Recon Position Status',
                'status' => $positionStatus,
                'message' => 'Successfully.',
                'checkStatus' => $positionReconciliations ? 1 : 0,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                'ApiName' => 'Check Recon Position Status',
                'status' => false,
                'message' => $th->getMessage().' - '.$th->getLine(),
                'checkStatus' => [],
            ], 400);
        }
    }

    /**
     * Method validateFrequencyType: This function is use for the validating frequency time
     *
     * @param  $request  $request [explicit description]
     * @param  $id  $id [explicit description]
     * @param  $position  $position [explicit description]
     * @return void
     */
    public function validateFrequencyType($request, $id, $position)
    {
        $response = null;
        if (! isset($request['frequency_type_id']) || $request->frequency_type_id === '') {
            $response = null;
        }

        $payFrequency = PositionPayFrequency::where('position_id', $id)->first();
        if ($payFrequency && $payFrequency->frequency_type_id !== $request['frequency_type_id']) {
            if ($position->peoples_count > 0) {
                $response = response()->json(['status' => false, 'message' => 'This position is assigned to other users; therefore, Pay Frequency cannot be changed.'], 400);
            }

            $payroll = Payroll::where('position_id', $payFrequency->position_id)->first();
            if ($payroll) {
                $response = response()->json(['status' => false, 'message' => 'Payroll data exists for this position; therefore, Pay Frequency cannot be changed.'], 400);
            }
        }

        return $response;
    }

    /**
     * Method validateCompanyProfile: Check pest company validation.
     *
     * @object $request $request [explicit description]
     *
     * @return object
     */
    public function validateCompanyProfile($request)
    {
        return Validator::make($request->all(), [
            'commission_amount_type' => 'nullable|in:percent',
            'calculated_by' => 'nullable|in:per sale',
            'override_settlement' => 'nullable|in:Initial Service,Reconciliation',
            'stack_settlement' => 'nullable|in:Initial Service,Reconciliation',
            'override.*.type' => 'nullable|in:per sale,percent',
        ], [
            'override.*.type.in' => 'Override Type Per KW is not allowed to select.',
        ]);
    }

    /**
     * Method updatePosition: Update position data.
     *
     * @object $position $position [explicit description]
     * @object $request $request [explicit description]
     *
     * @return void
     */
    public function updatePosition($position, $request)
    {
        $position->position_name = $request->position_name;
        $position->is_stack = $request->is_stack;
        $position->save();
    }

    public function updateCommissions($id, $request)
    {
        $commissions = PositionCommission::where('position_id', $id)->first();
        if ($commissions) {
            $commissions->update([
                'commission_parentage' => $request->commission_parentage ?? null,
                'commission_parentage_hiring_locked' => $request->commission_parentag_hiring_locked,
                'commission_amount_type' => $request->commission_amount_type,
                'commission_amount_type_locked' => $request->commission_amount_type_locked,
                'commission_structure_type' => $request->commission_structure_type,
                'commission_parentag_type_hiring_locked' => $request->commission_parentag_type_hiring_locked,
            ]);
        } else {
            PositionCommission::create([
                'position_id' => $id,
                'commission_parentage' => $request->commission_parentage,
                'commission_parentag_hiring_locked' => $request->commission_parentag_hiring_locked,
                'commission_amount_type' => $request->commission_amount_type,
                'commission_amount_type_locked' => $request->commission_amount_type_locked,
                'commission_structure_type' => $request->commission_structure_type,
                'commission_parentag_type_hiring_locked' => $request->commission_parentag_type_hiring_locked,
            ]);
        }
    }

    public function updateUpfront($request)
    {
        $upfront = PositionCommissionUpfronts::where('position_id', $request->position_id)->first();
        if ($upfront) {
            $upfront->update([
                'status_id' => $request->upfront_status == 1 ? $request->upfront_status : $upfront->status_id,
                'upfront_status' => $request->upfront_status,
                'upfront_ammount' => $request->upfront_ammount,
                'upfront_ammount_locked' => $request->upfront_ammount_locked,
                'calculated_by' => $request->calculated_by,
                'calculated_locked' => $request->calculated_locked,
                'upfront_system' => $request->upfront_system,
                'upfront_system_locked' => $request->upfront_system_locked,
                'upfront_limit' => $request->upfront_limit,
            ]);
        } elseif ($request->upfront_status == 1) {
            PositionCommissionUpfronts::create([
                'position_id' => $request->sub_position_id,
                'status_id' => $request->upfront_status,
                'upfront_ammount' => $request->upfront_ammount,
                'upfront_ammount_locked' => $request->upfront_ammount_locked,
                'calculated_by' => $request->calculated_by,
                'calculated_locked' => $request->calculated_locked,
                'upfront_system' => $request->upfront_system,
                'upfront_system_locked' => $request->upfront_system_locked,
                'upfront_limit' => $request->upfront_limit,
            ]);
        }
    }

    public function updateUserUpfront($id, $request)
    {
        $users = User::where('sub_position_id', $id)->get();
        foreach ($users as $user) {
            $user->update([
                'upfront_pay_amount' => $request->upfront_ammount,
                'upfront_sale_type' => $request->calculated_by,
            ]);
        }
    }

    public function updateDeductions($id, $request)
    {
        PositionCommissionDeduction::where('position_id', $request->position_id)->delete();
        if ($request->deduction) {
            self::updateDeductionSettings($request);

            foreach ($request->deduction as $deduction) {
                if ($deduction['cost_center_id']) {
                    PositionCommissionDeduction::create([
                        'position_id' => $id,
                        'deduction_setting_id' => 1,
                        'deduction_type' => $deduction['deduction_type'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'ammount_par_paycheck' => $deduction['ammount_par_paycheck'],
                    ]);
                }
            }
        }

        self::updateDeductionLimits($id, $request);
    }

    public function updateDeductionSettings($request)
    {
        $setting = PositionCommissionDeductionSetting::where('position_id', $request->position_id)->first();
        if ($setting) {
            $setting->update([
                'status' => $request->deduction_status,
                'deducation_locked' => $request->deduction_locked,
            ]);
        } else {
            PositionCommissionDeductionSetting::create([
                'position_id' => $request->sub_position_id,
                'status' => $request->deduction_status,
                'deducation_locked' => $request->deduction_locked,
            ]);
        }
    }

    public function updateDeductionLimits($id, $request)
    {
        $limit = PositionsDeductionLimit::where('position_id', $id)->first();
        if ($limit) {
            $limit->update([
                'limit_ammount' => $request->limit_ammount,
                'limit' => $request->limit,
                'status' => $request->deduction_status,
                'limit_type' => $request->limit_type,
            ]);
        } else {
            PositionsDeductionLimit::create([
                'position_id' => $id,
                'limit_ammount' => $request->limit_ammount,
                'limit' => $request->limit,
                'status' => $request->deduction_status,
                'limit_type' => $request->limit_type,
            ]);
        }
    }

    public function updatePositionOverrides($id, $request)
    {
        if (! isset($request['settlement_id']) || $request['settlement_id'] === '') {
            return;
        }

        PositionOverride::where('position_id', $id)->delete();
        foreach ($request->override as $override) {
            PositionOverride::create([
                'position_id' => $id,
                'override_id' => $override['override_id'],
                'settlement_id' => $request['settlement_id'],
                'status' => $override['status'],
                'override_ammount' => $override['override_ammount'],
                'override_ammount_locked' => $override['override_ammount_locked'],
                'type' => $override['type'],
                'override_type_locked' => $override['override_type_locked'],
            ]);
        }
    }

    public function updatePositionReconciliation($id, $request)
    {
        $reconciliation = PositionReconciliations::where('position_id', $id)->first();
        if ($reconciliation) {
            $reconciliation->update([
                'commission_withheld' => $request->commission_withheld,
                'commission_type' => $request->commission_type,
                'maximum_withheld' => $request->maximum_withheld,
                'override_settlement' => $request->override_settlement,
                'clawback_settlement' => $request->clawback_settlement,
                'stack_settlement' => $request->stack_settlement,
                'status' => $request->reconciliation_status,
                'commission_withheld_locked' => $request->commission_withheld_locked ?? 0,
                'commission_type_locked' => $request->commission_type_locked ?? 0,
            ]);
        } else {
            PositionReconciliations::create([
                'position_id' => $id,
                'commission_withheld' => $request->commission_withheld,
                'commission_type' => $request->commission_type,
                'maximum_withheld' => $request->maximum_withheld,
                'override_settlement' => $request->override_settlement,
                'clawback_settlement' => $request->clawback_settlement,
                'stack_settlement' => $request->stack_settlement,
                'status' => $request->reconciliation_status,
                'commission_withheld_locked' => $request->commission_withheld_locked ?? 0,
                'commission_type_locked' => $request->commission_type_locked ?? 0,
            ]);
        }
    }

    public function updatePayFrequency($id, $request)
    {
        if (! isset($request['frequency_type_id']) || $request->frequency_type_id === '') {
            return;
        }

        $payFrequency = PositionPayFrequency::where('position_id', $id)->first();
        $payFrequencyData = [
            'frequency_type_id' => $request->frequency_type_id,
            'first_months' => $request->first_months,
            'day_of_week' => $request->day_of_week,
            'first_day' => $request->first_day,
            'day_of_months' => $request->day_of_months,
            'pay_period' => $request->pay_period,
            'monthly_per_days' => $request->monthly_per_days,
            'first_day_pay_of_manths' => $request->first_day_pay_of_manths,
            'second_pay_day_of_month' => $request->second_pay_day_of_month,
            'deadline_to_run_payroll' => $request->deadline_to_run_payroll,
            'first_pay_period_ends_on' => $request->first_pay_period_ends_on,
        ];

        if ($payFrequency) {
            $payFrequency->update($payFrequencyData);
        } else {
            PositionPayFrequency::create(array_merge(['position_id' => $id], $payFrequencyData));
        }

        $position = Positions::where('id', $request->position_id)->first();
        $position->update(['setup_status' => 1]);
    }

    protected function validateSkipReconUser(Request $request)
    {
        return Validator::make(
            $request->all(),
            [
                'start_date' => ['required', self::DATE_FORMAT_VALIDATION],
                'end_date' => ['required', self::DATE_FORMAT_VALIDATION],
            ],
            [
                'start_date.required' => 'The start date is required.',
                'start_date.date_format' => 'The start date must be in the format YYYY-MM-DD.',
                'end_date.required' => 'The end date is required.',
                'end_date.date_format' => 'The end date must be in the format YYYY-MM-DD.',
            ]
        );
    }

    public function finalizeReconciliationList(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'recon_payout' => ['required', 'numeric', 'min:1', 'max:100'],
            'finalize_id' => ['required', 'numeric', 'min:1'],
        ]);

        if ($validate->fails()) {
            return response()->json([
                'ApiName' => 'Finalize Recon Draft List',
                'status' => true,
                'message' => $validate->messages(),
            ], 400);
        }

        $perPage = ! empty($request->perpage) ? $request->perpage : 10;
        $search = $request->input('search', '');
        $sortBy = $request->sort;

        $finalize = ReconciliationFinalize::where(['id' => $request->finalize_id, 'status' => 'finalize'])->first();
        if (! $finalize) {
            return response()->json([
                'ApiName' => 'Finalize Recon Draft List',
                'status' => true,
                'message' => 'Finalized period not found!!',
            ], 400);
        }

        $sortStr = 'users.first_name ASC';
        switch ($sortBy) {
            case 'commission':
                $sortStr = "commission $request->sort_val";
                break;
            case 'override':
                $sortStr = "override $request->sort_val";
                break;
            case 'total_due':
                $sortStr = "commission + override $request->sort_val";
                break;
            case 'clawback':
                $sortStr = "clawback $request->sort_val";
                break;
            case 'adjustments':
                $sortStr = "adjustments + deductions $request->sort_val";
                break;
            case 'payout':
                $sortStr = "commission + override - clawback + adjustments + deductions $request->sort_val";
                break;
            case 'remaining':
                $sortStr = "remaining $request->sort_val";
                break;
            default:
                $sortStr = 'users.first_name ASC';
                break;
        }

        $reconFinalize = ReconciliationFinalizeHistory::select('reconciliation_finalize_history.*', 'users.first_name', 'users.last_name', 'users.image', 'users.is_super_admin', 'users.is_manager', 'users.position_id', 'users.sub_position_id')
            ->join('users', 'reconciliation_finalize_history.user_id', '=', 'users.id')
            ->where(function ($query) use ($search) {
                if (! empty($search)) {
                    $query->where('users.first_name', 'LIKE', "%{$search}%")
                        ->orWhere('users.last_name', 'LIKE', "%{$search}%")
                        ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'LIKE', "%{$search}%");
                }
            })->where('finalize_id', $finalize->id)->orderByRaw("$sortStr")->paginate($perPage);
        $reconFinalize->getCollection()->transform(function ($result) {
            if ($result->image) {
                $imageUrl = s3_getTempUrl(config('app.domain_name').'/'.$result->image);
            } else {
                $imageUrl = null;
            }

            $totalDue = floatval($result->commission) + floatval($result->override) ?? 0;
            $clawback = -1 * floatval($result->clawback ?? 0);
            // $adjustments = $result->adjustments + $result->deductions;
            $adjustments = $result->adjustments - $result->deductions;
            $payout = ($totalDue + $adjustments) - $result->clawback;

            return [
                'finalize_id' => $result->finalize_id,
                'user_id' => $result->user_id,
                'emp_img' => $result->image ? $result->image : 'Employee_profile/default-user.png',
                'emp_name' => $result->first_name.' '.$result->last_name,
                'emp_img_s3' => $imageUrl,
                'commissionWithholding' => $result->commission,
                'overrideDue' => $result->override,
                'total_due' => $totalDue,
                'clawbackDue' => $clawback,
                'totalAdjustments' => $adjustments,
                'total_pay' => $result->percentage_pay_amount,
                'payout' => $payout,
                'remaining' => $result->remaining,
                'is_super_admin' => $result->is_super_admin,
                'is_manager' => $result->is_manager,
                'position_id' => $result->position_id,
                'sub_position_id' => $result->sub_position_id,
            ];
        });

        $finalizeUsers = ReconciliationFinalizeHistory::where('finalize_id', $finalize->id)->pluck('user_id');
        $userPosition = User::with(['positionDetail.payFrequency.frequencyType'])->whereIn('id', $finalizeUsers)->get()->map(function ($user) {
            return [
                'sub_position_id' => $user->sub_position_id,
                'frequency_name' => $user?->positionDetail?->payFrequency?->frequencyType?->name ?? null,
                'frequency_id' => $user?->positionDetail?->payFrequency?->frequencyType?->id ?? null,
            ];
        })->unique()->values();

        return response()->json([
            'ApiName' => 'reconciliation_details',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => 0,
            'data' => $reconFinalize,
            'finalizeUserPayFrequency' => $userPosition,
        ]);
    }

    private function getReconData($userIds, $startDate, $endDate, $reconPayout, $search, $request)
    {
        $finalizeReconDataList = ReconciliationFinalizeHistory::whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('finalize_count', $request->finalize_count)
            ->whereIn('user_id', $userIds)
            ->get();

        return $finalizeReconDataList->transform(function ($result) use ($reconPayout) {
            if ($result->user->image) {
                $imageUrl = s3_getTempUrl(config('app.domain_name').'/'.$result->user->image);
            } else {
                $imageUrl = null;
            }

            $totalDue = floatval($result->commission) + floatval($result->override) ?? 0;
            $totalDueNoCB = floatval($result->commission) + floatval($result->override) ?? 0;
            if ($result->commission < 0.5 && $result->override < 0.5) {
                $totalDueNoCB = 0;
            } else {
                if ($result->commission < 0.5) {
                    $totalDueNoCB = floatval($result->override * ($reconPayout / 100));
                }
                if ($result->override < 0.5) {
                    $totalDueNoCB = floatval($result->commission * ($reconPayout / 100));
                }

                if ($result->commission > 0.5 && $result->override > 0.5) {
                    $totalDueNoCB = floatval($result->commission + $result->override) * ($reconPayout / 100);
                }
            }

            return [
                'user_id' => $result->user_id,
                'emp_img' => $result->user->emp_img ? $result->user->emp_img : 'Employee_profile/default-user.png',
                'emp_name' => $result->user->first_name.' '.$result->user->last_name,
                'emp_img_s3' => $imageUrl,
                'commissionWithholding' => $result->commission,
                'overrideDue' => $result->override,
                'total_due' => $totalDue,
                'clawbackDue' => -1 * floatval($result->clawback ?? 0),
                'totalAdjustments' => $result->adjustments + $result->deductions,
                // 'total_pay' => $totalDueNoCB,
                'total_pay' => $result->percentage_pay_amount,
                'payout' => $result->gross_amount,
                'is_super_admin' => $result->user->is_super_admin,
                'is_manager' => $result->user->is_manager,
                'position_id' => $result->user->position_id,
                'sub_position_id' => $result->user->sub_position_id,
                'frequency_type_id' => @$result->user->positionpayfrequencies->frequency_type_id,
                'frequency_type_name' => @$result->user->positionpayfrequencies->frequencyType->name,
            ];
        })->toArray();
    }

    private function checkIfUserSkipped($userId, $startDate, $endDate)
    {
        return ReconciliationStatusForSkipedUser::where('user_id', $userId)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->where('status', 'skipped')->exists() ? 1 : 0;
    }

    private function createReconLockData()
    {
        /* create finalize lock table data */
        $finalizeReconAmount = ReconciliationFinalizeHistory::where([
            'status' => 'payroll',
        ])
            ->whereNotNull('pay_period_from')
            ->whereNotNull('pay_period_to')
            ->whereNotNull('payroll_id')
            ->get();

        if (count($finalizeReconAmount) > 0) {
            foreach ($finalizeReconAmount->toArray() as $value) {
                ReconciliationFinalizeHistoryLock::updateOrCreate(['id' => $value['id'], 'status' => 'payroll'], $value);
            }
        }

        /* create recon override history data */
        $finalizeReconOverrideAmount = ReconOverrideHistory::where([
            'status' => 'payroll',
        ])->whereNotNull('pay_period_from')
            ->whereNotNull('pay_period_to')
            ->whereNotNull('payroll_id')
            ->get();

        if (count($finalizeReconOverrideAmount) > 0) {
            foreach ($finalizeReconOverrideAmount->toArray() as $value) {
                ReconOverrideHistoryLock::updateOrCreate(['id' => $value['id'], 'status' => 'payroll'], $value);
            }
        }

        /* create recon commission history lock data */

        $finalizeReconDeductionAmount = ReconCommissionHistory::where([
            'status' => 'payroll',
        ])->whereNotNull('pay_period_from')
            ->whereNotNull('pay_period_to')
            ->whereNotNull('payroll_id')
            ->get();

        if (count($finalizeReconDeductionAmount) > 0) {
            foreach ($finalizeReconDeductionAmount->toArray() as $value) {
                ReconCommissionHistoryLock::updateOrCreate(['id' => $value['id'], 'status' => 'payroll'], $value);
            }
        }

        /* create recon deduction history lock data */

        $finalizeReconDeductionAmount = ReconDeductionHistory::where([
            'status' => 'payroll',
        ])->whereNotNull('pay_period_from')
            ->whereNotNull('pay_period_to')
            ->whereNotNull('payroll_id')
            ->get();

        if (count($finalizeReconDeductionAmount) > 0) {
            foreach ($finalizeReconDeductionAmount->toArray() as $value) {
                ReconDeductionHistoryLock::updateOrCreate(['id' => $value['id'], 'status' => 'payroll'], $value);
            }
        }

        /* create recon clawback history lock data */

        $finalizeReconDeductionAmount = ReconClawbackHistory::where([
            'status' => 'payroll',
        ])->whereNotNull('pay_period_from')
            ->whereNotNull('pay_period_to')
            ->whereNotNull('payroll_id')
            ->get();

        if (count($finalizeReconDeductionAmount) > 0) {
            foreach ($finalizeReconDeductionAmount->toArray() as $value) {
                ReconClawbackHistoryLock::updateOrCreate(['id' => $value['id'], 'status' => 'payroll'], $value);
            }
        }
    }
}
