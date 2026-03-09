<?php

namespace App\Http\Controllers\API\Payroll;

use App\Core\Traits\CheckPayrollZeroDataTrait;
use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Events\EvereeOnboardingUserEvent;
use App\Events\UserloginNotification;
use App\Http\Controllers\Controller;
use App\Jobs\EvereeOnboardingUsersJob;
use App\Jobs\executePayrollJob;
use App\Jobs\finalizePayrollJob;
use App\Jobs\finalizeW2PayrollJob;
use App\Jobs\oneTimePaymentJob;
use App\Jobs\PayrollFailedRecordsProcess;
use App\Models\AdditionalPayFrequency;
use App\Models\AdjustementType;
use App\Models\AdvancePaymentSetting;
use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\DailyPayFrequency;
use App\Models\FrequencyType;
use App\Models\LegacyApiNullData;
use App\Models\Locations;
use App\Models\MonthlyPayFrequency;
use App\Models\OneTimePayments;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollCommon;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollDeductions;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertime;
use App\Models\PayrollOvertimeLock;
use App\Models\PayrollShiftHistorie;
use App\Models\PayrollSsetup;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionPayFrequency;
use App\Models\PositionReconciliations;
use App\Models\PositionsDeductionLimit;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\RequestApprovelByPid;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserDeductionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationWithholding;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use App\Models\UserWagesHistory;
use App\Models\WeeklyPayFrequency;
use App\Services\PayrollCalculationService;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayrollSingleController extends Controller
{
    use CheckPayrollZeroDataTrait;
    use EmailNotificationTrait;
    use EvereeTrait;
    use PayFrequencyTrait;
    use PushNotificationTrait;

    public function singlePayrollMarkAsPaid(Request $request)
    {
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'paid' => 'required',
                'id' => 'required',
                'pid' => 'required',
                'select_type' => 'required',
                'pay_period_to' => 'required',
                'pay_period_from' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $paid = $request->paid; // 0, 1
            $recordId = $request->id;
            $payrollIds = $request->pid;
            $adjustmentId = $request->adjustment;
            $selectType = $request->select_type; // commision - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconcillation - 6
            $payPeriodTo = $request->pay_period_to;
            $payPeriodFrom = $request->pay_period_from;
            $clawbackId = 0;
            $next = 0;

            if ($paid) {
                $api_name = 'Mark As Paid';
            } else {
                $api_name = 'Mark As Unpaid';
            }

            if (Payroll::where(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => $api_name,
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            $jsonData = $request->json()->all();
            $recordId = $jsonData['id'];
            if (isset($jsonData['id']['commission'])) {
                $recordId = $jsonData['id']['commission'];
            }
            if (isset($jsonData['id']['overrides'])) {
                $recordId = $jsonData['id']['overrides'];
            }
            if (isset($jsonData['id']['request'])) {
                $recordId = $jsonData['id']['request'];
            }
            if (isset($jsonData['id']['clawback'])) {
                $clawbackId = $jsonData['id']['clawback'];
            }
            if (isset($jsonData['id']['deduction'])) {
                $recordId = $jsonData['id']['deduction'];
            }
            if (isset($jsonData['id']['hourlysalary'])) {
                $recordId = $jsonData['id']['hourlysalary'];
            }
            if (isset($jsonData['id']['overtime'])) {
                $recordId = $jsonData['id']['overtime'];
            }

            // Ensure recordId is an array and remove any nested arrays
            $recordId = is_array($recordId) ? array_filter($recordId, function ($value) {
                return ! is_array($value);
            }) : [$recordId];

            $payrollIds = $jsonData['pid'];

            DB::beginTransaction();
            try {
                switch ($selectType) {
                    case '1':
                        UserCommission::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '2':
                        UserOverrides::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '3':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '4':
                        ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '5':
                        PayrollDeductions::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '7':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        ClawbackSettlement::whereIn('id', $recordId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        break;
                    case '8':
                        PayrollHourlySalary::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '9':
                        PayrollOvertime::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                }

                if ($paid) {
                    $message = 'Mark as Paid Successfully!';
                } else {
                    Payroll::whereIn('id', $payrollIds)->update(['is_mark_paid' => 0]);
                    $message = 'Mark as Unpaid Successfully!';
                }

                DB::commit();

                return response()->json([
                    'ApiName' => $api_name,
                    'status' => true,
                    'message' => $message,
                ]);
            } catch (Exception $err) {
                DB::rollBack();

                return response()->json([
                    'ApiName' => $api_name,
                    'status' => false,
                    'message' => $err->getMessage(),
                ], 400);
            }
        } else {
            $paid = $request->paid; // 0, 1
            $user_id = $request->user_id;
            $recordId = $request->id;
            $payrollIds = $request->pid;
            $adjustmentId = $request->adjustment;
            $selectType = $request->select_type; // commision - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconcillation - 6
            $payPeriodTo = $request->pay_period_to;
            $payPeriodFrom = $request->pay_period_from;
            $clawbackId = 0;
            $next = 0;

            $Validator = Validator::make(
                $request->all(),
                [
                    'paid' => 'required',
                    'user_id' => 'required',
                    'id' => 'required',
                    'pid' => 'required',
                    'select_type' => 'required',
                    'pay_period_to' => 'required',
                    'pay_period_from' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            if ($paid) {
                $api_name = 'Mark As Paid';
            } else {
                $api_name = 'Mark As Unpaid';
            }

            if (Payroll::where(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => $api_name,
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            $jsonData = $request->json()->all();
            $recordId = $jsonData['id'];
            if (isset($jsonData['id']['commission'])) {
                $recordId = $jsonData['id']['commission'];
            }
            if (isset($jsonData['id']['overrides'])) {
                $recordId = $jsonData['id']['overrides'];
            }
            if (isset($jsonData['id']['request'])) {
                $recordId = $jsonData['id']['request'];
            }
            if (isset($jsonData['id']['clawback'])) {
                $clawbackId = $jsonData['id']['clawback'];
            }
            if (isset($jsonData['id']['deduction'])) {
                $recordId = $jsonData['id']['deduction'];
            }
            if (isset($jsonData['id']['hourlysalary'])) {
                $recordId = $jsonData['id']['hourlysalary'];
            }
            if (isset($jsonData['id']['overtime'])) {
                $recordId = $jsonData['id']['overtime'];
            }

            // Ensure recordId is an array and remove any nested arrays
            $recordId = is_array($recordId) ? array_filter($recordId, function ($value) {
                return ! is_array($value);
            }) : [$recordId];

            $payrollIds = $jsonData['pid'];

            DB::beginTransaction();
            try {
                switch ($selectType) {
                    case '1':
                        $pid = UserCommission::whereIn('id', $recordId)->value('pid');
                        UserCommission::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '2':
                        $pid = UserOverrides::whereIn('id', $recordId)->value('pid');
                        UserOverrides::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '3':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '4':
                        ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '5':
                        PayrollDeductions::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_mark_paid' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => '1', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '7':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        ClawbackSettlement::whereIn('id', $recordId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        break;
                    case '8':
                        // $pid = PayrollHourlySalary::whereIn('id', $recordId)->value('id');
                        PayrollHourlySalary::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                    case '9':
                        // $pid = PayrollHourlySalary::whereIn('id', $recordId)->value('id');
                        PayrollOvertime::whereIn('id', $recordId)->update(['is_mark_paid' => $paid]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        }
                        break;
                        // default:

                }
                foreach ($payrollIds as $payrollId) {
                    $this->updateEveree($payrollId);
                }

                if ($paid) {
                    $message = 'Mark as Paid Successfully!';
                } else {
                    Payroll::whereIn('id', $payrollIds)->update(['is_mark_paid' => 0]);
                    $message = 'Mark as Unpaid Successfully!';
                }
                DB::commit();
            } catch (Exception $err) {
                // Handle any exceptions that occur within the transaction
                $message = $err->getMessage();
                DB::rollBack();
            }

            return response()->json([
                'ApiName' => $api_name,
                'status' => true,
                'message' => $message,
            ], 200);
        }
    }

    public function single_one_time_payment_pay_now(Request $request)
    {
        $req_user_id = '';
        $type_id = 12;
        $req_id = '';
        $req_amount = '';
        $req_no = '';
        $req_des = '';
        $amount = 0;

        try {
            $validator = Validator::make($request->all(), [
                'paid' => 'required',
                'id' => 'required',
                'pid' => 'required',
                'select_type' => 'required',
                'pay_period_to' => 'required',
                'pay_period_from' => 'required',
                'pay_frequency' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $paid = $request->paid; // 0, 1
            $recordId = $request->id;
            $payrollIds = $request->pid;
            $adjustmentId = $request->adjustment;
            $selectType = $request->select_type; // commision - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconcillation - 6
            $payPeriodTo = $request->pay_period_to;
            $payPeriodFrom = $request->pay_period_from;
            $clawbackId = 0;
            $next = 0;
            $total_amount = [];

            if ($paid) {
                $api_name = 'Onetime Payment';
            } else {
                $api_name = 'No Onetime Payment';
            }

            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            $CrmSetting = CrmSetting::where('crm_id', 3)->first();

            if (empty($CrmData)) {
                return response()->json([
                    'error' => ['message' => ['You are presently not set up to utilize Sequifi\'s payment services. Therefore, this payment cannot be processed. Please reach out to your system administrator.']],
                ], 400);
            }
            $payrolls = Payroll::whereIn('id', $request->pid)->where(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'status' => '2'])->get();

            if (! empty($payrolls->toArray())) {
                return response()->json([
                    'ApiName' => 'single one time payment.',
                    'status' => false,
                    'data' => $payrolls->toArray(),
                    'message' => 'The payroll has been already finalized. No changes can be made after finalization.',
                ], 400);
            }

            $payrollUsersId = Payroll::whereIn('id', $request->pid)->pluck('user_id');
            if ($payrollUsersId) {
                $usersRecord = User::whereIn('id', $payrollUsersId)->where(function ($query) {
                    $query->whereNull('employee_id')
                        ->orWhere('employee_id', '')
                        ->orWhereNull('everee_workerId')
                        ->orWhere('everee_workerId', '');
                })->get();

                if (! empty($usersRecord->toArray())) {
                    return response()->json([
                        'ApiName' => 'single_one_time_payment',
                        'status' => false,
                        'data' => $usersRecord,
                        'message' => 'Since the user has not completed their self-onboarding process and their information is incomplete, we are unable to process the payment. Please ensure their details are fully updated to proceed with the payment.',
                    ], 400);
                }
            }

            $jsonData = $request->json()->all();
            $recordId = $jsonData['id'];
            if (isset($jsonData['id']['commission'])) {
                $recordId = $jsonData['id']['commission'];
            }
            if (isset($jsonData['id']['overrides'])) {
                $recordId = $jsonData['id']['overrides'];
            }
            if (isset($jsonData['id']['request'])) {
                $recordId = $jsonData['id']['request'];
            }
            if (isset($jsonData['id']['clawback'])) {
                $clawbackId = $jsonData['id']['clawback'];
            }
            if (isset($jsonData['id']['deduction'])) {
                $recordId = $jsonData['id']['deduction'];
            }
            if (isset($jsonData['id']['hourlysalary'])) {
                $recordId = $jsonData['id']['hourlysalary'];
            }
            if (isset($jsonData['id']['overtime'])) {
                $recordId = $jsonData['id']['overtime'];
            }

            // Ensure recordId is an array and remove any nested arrays
            $recordId = is_array($recordId) ? array_filter($recordId, function ($value) {
                return ! is_array($value);
            }) : [$recordId];

            if (! empty($recordId)) {
                // foreach($recordId as $key => $singleRecordId){
                // $singleRecordId = array($recordId);
                if ($request->pid) {
                    $payroll_id = isset($request->pid[0]) ? $request->pid[0] : '';
                    // foreach ($request->pid as $payroll_id) {

                    $payroll = Payroll::where(['id' => $payroll_id, 'is_mark_paid' => 0, 'status' => 1, 'finalize_status' => 0, 'is_onetime_payment' => 0])->first();

                    if ($payroll) {

                        // dd($payroll->net_pay);

                        if ($selectType == '1') {
                            // $singlePaymentDetail = UserCommission::whereIn('id', $recordId)->first();
                            $singlePaymentDetail = UserCommission::whereIn('id', $recordId)->sum('amount');

                            // print_r($singlePaymentDetail);die;
                            if ($singlePaymentDetail) {
                                $amount = $singlePaymentDetail ? $singlePaymentDetail : 0;
                            }
                        } elseif ($selectType == '2') {
                            // $singlePaymentDetail = UserOverrides::whereIn('id', $singleRecordId)->first();
                            $singlePaymentDetail = UserOverrides::whereIn('id', $recordId)->sum('amount');
                            if ($singlePaymentDetail) {
                                $amount = $singlePaymentDetail ? $singlePaymentDetail : 0;
                            }
                        } elseif ($selectType == '3') {
                            // $singlePaymentDetail = PayrollAdjustmentDetail::whereIn('id', $singleRecordId)->where([ 'payroll_type'=> 'commission'])->first();
                            // if($singlePaymentDetail){
                            //     $amount = $singlePaymentDetail ? $singlePaymentDetail->amount : 0;
                            // }else{
                            $singlePaymentDetail = ApprovalsAndRequest::whereIn('id', $recordId)->whereNotIn('adjustment_type_id', [2, 5])->where(['status' => 'Accept'])->first();
                            // }
                            if ($singlePaymentDetail) {
                                $amount = $singlePaymentDetail->amount ? $singlePaymentDetail->amount : 0;
                            }
                        } elseif ($selectType == '4') {
                            $singlePaymentDetail = ApprovalsAndRequest::whereIn('id', $recordId)->where(['status' => 'Accept', 'adjustment_type_id' => 2])->first();
                            if ($singlePaymentDetail) {
                                $amount = $singlePaymentDetail->amount ? $singlePaymentDetail->amount : 0;
                            }
                        } elseif ($selectType == '5') {
                            $singlePaymentDetail = PayrollDeductions::whereIn('id', $recordId)->first();
                            if ($singlePaymentDetail) {
                                $amount = $singlePaymentDetail ? $singlePaymentDetail->total : 0;
                            }
                        } elseif ($selectType == '8') {
                            $singlePaymentDetail = PayrollHourlySalary::whereIn('id', $recordId)->first();
                            if ($singlePaymentDetail) {
                                $amount = $singlePaymentDetail ? $singlePaymentDetail->total : 0;
                            }
                        } elseif ($selectType == '9') {
                            $singlePaymentDetail = PayrollOvertime::whereIn('id', $recordId)->first();
                            if ($singlePaymentDetail) {
                                $amount = $singlePaymentDetail ? $singlePaymentDetail->total : 0;
                            }
                        }

                        $pay_amount = $amount;

                        if ($pay_amount < 1) {
                            return response()->json([
                                'error' => ['messege' => ['amount must be a positive number']],
                            ], 400);
                        }

                        $uid = isset($payroll->user_id) ? $payroll->user_id : '';
                        $user = User::where('id', $uid)->first();
                        $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $user->sub_position_id])->first();
                        if (! $positionPayFrequency) {
                            return response()->json([
                                'error' => ['messege' => ['sorry user doesn\'t have any position pay frequency that\'s why we are unable to process right now.']],
                            ], 400);
                        }

                        $check = OneTimePayments::where('adjustment_type_id', $type_id)->count();

                        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                        $CrmSetting = CrmSetting::where('crm_id', 3)->first();

                        if (! empty($CrmData) && ! empty($CrmSetting)) {
                            if ($type_id == 1) {
                                if (! empty($check)) {
                                    $req_no = 'OTPD'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTPD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } elseif ($type_id == 2) {
                                if (! empty($check)) {
                                    $req_no = 'OTR'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } elseif ($type_id == 3) {
                                if (! empty($check)) {
                                    $req_no = 'OTB'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTB'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } elseif ($type_id == 4) {
                                if (! empty($check)) {
                                    $req_no = 'OTA'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } elseif ($type_id == 6) {
                                if (! empty($check)) {
                                    $req_no = 'OTI'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTI'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } elseif ($type_id == 10) {
                                if (! empty($request->customer_pid)) {
                                    $req_no = 'OTC'.$request->customer_pid;
                                } else {
                                    $req_no = 'OTC'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } elseif ($type_id == 11) {
                                if (! empty($check)) {
                                    $req_no = 'OTOV'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTOV'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } elseif ($type_id == 12) {
                                if (! empty($check)) {
                                    $req_no = 'OTPR'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTPR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            } else {
                                if (! empty($check)) {
                                    $req_no = 'OTO'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                } else {
                                    $req_no = 'OTO'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                }
                            }

                            // $external_id = $user->employee_id . "-" . strtotime('now');

                            $amount = isset($pay_amount) ? $pay_amount : $req_amount;
                            $evereeFields = [
                                'usersdata' => [
                                    'employee_id' => $user->employee_id,
                                    'everee_workerId' => $user->everee_workerId,
                                    'id' => $user->id,
                                    'onboardProcess' => $user->onboardProcess,
                                ],
                                'net_pay' => $amount,
                                'payable_type' => 'one time payment',
                                'payable_label' => 'one time payment',
                            ];

                            // Generate a unique key for external ID creation
                            // $key = uniqid();

                            // if REIMBURSEMENT exits in payroll than REIMBURSEMENT - from netpay
                            // commission
                            if ($selectType == 4) {
                                $R_external_id = 'OTR-'.$user->employee_id.'-'.$payroll->id.'-'.strtotime('now');
                                $reimbursement = $payroll['reimbursement'];
                                $net_pay = $evereeFields['net_pay'];
                                $evereeFields['net_pay'] = $reimbursement;
                                $R_untracked = $this->add_payable($evereeFields, $R_external_id, 'REIMBURSEMENT');  // add  payable in everee
                                if ((isset($R_untracked['success']['status']) && $R_untracked['success']['status'] == true)) {
                                    $external_id = $R_external_id;
                                    $untracked = $R_untracked;
                                } else {
                                    $this->delete_payable($R_external_id, $uid);
                                    $external_id = '';
                                    $untracked = $R_untracked;
                                }
                                $enableEVE = 1;
                            } else {
                                $external_id = 'PRR-'.$user->employee_id.'-'.$payroll->id.'-'.strtotime('now');
                                $untracked = $this->add_payable($evereeFields, $external_id, 'COMMISSION');  // update payable in everee
                                $enableEVE = 1;
                            }

                            // print_r($untracked['success']['status']); die;
                            if ((isset($untracked['success']['status']) && $untracked['success']['status'] == true)) {

                                $payable_request = $this->payable_request($evereeFields);
                                // print_r($payable_request); die;
                                // print_r($payable_request); die;
                                $response = OneTimePayments::create([
                                    'user_id' => $uid,
                                    'req_id' => $req_id ? $req_id : null,
                                    'pay_by' => Auth::user()->id,
                                    'req_no' => $req_no ? $req_no : null,
                                    'everee_external_id' => $external_id,
                                    'everee_payment_req_id' => isset($payable_request['success']['paymentId']) ? $payable_request['success']['paymentId'] : null,
                                    'everee_paymentId' => isset($payable_request['success']['everee_payment_id']) ? $payable_request['success']['everee_payment_id'] : null,
                                    'adjustment_type_id' => $type_id,
                                    'amount' => $amount,
                                    'description' => $request->description ? $request->description : $req_des,
                                    'pay_date' => date('Y-m-d'),
                                    'payment_status' => 3,
                                    'everee_status' => 1,
                                    'everee_json_response' => isset($payable_request) ? json_encode($payable_request) : null,
                                    'everee_webhook_response' => null,
                                    'everee_payment_status' => 0,
                                ]);

                                $attributes = $request->all();
                                if ($request && $response && $payroll_id) {
                                    // \Log::error('$reques: ' . $request);
                                    // \Log::error('$singleRecordId: ' . json_encode($singleRecordId));
                                    // \Log::error('$response: ' . $response);
                                    // \Log::error('$payroll_id: ' . $payroll_id);
                                    // \Log::error('$untracked: ' . json_encode($untracked));
                                    // \Log::error('$payable_request: ' . json_encode($payable_request));
                                    // \Log::error('$CrmData: ' . $CrmData);
                                    // \Log::error('$CrmSetting: ' . $CrmSetting);
                                    // \Log::error('$payroll: ' . $payroll);
                                    // \Log::error('$external_id: ' . $external_id);
                                    if (! empty($CrmData) && ! empty($CrmSetting)) {
                                        foreach ($recordId as $singleRecordId) {
                                            $this->updateSingleOnetimePaymentStatus($request, $singleRecordId, $response, $payroll_id, $untracked, $payable_request, $CrmData, $CrmSetting, $payroll, $external_id);
                                        }
                                    }
                                }

                                $oneTimePaymentId = $response->id;

                                create_paystub_employee([
                                    'user_id' => $payroll->user_id,
                                    'pay_period_from' => $payroll->pay_period_from,
                                    'pay_period_to' => $payroll->pay_period_to,
                                ]);

                                // Merge additional keys into the attributes array
                                $additionalProperties = [
                                    'req_no' => $response->req_no, // Include request number
                                    'everee_paymentId' => $response->everee_paymentId,
                                    'payment_status' => $response->payment_status,
                                    'everee_payment_status' => $response->everee_payment_status,
                                ];

                                $mergedProperties = array_merge($attributes, $additionalProperties);

                                // Log activity
                                activity()
                                    ->causedBy(Auth::user()) // The user who triggered the action
                                    ->performedOn($response) // The OneTimePayments record
                                    ->withProperties(['attributes' => $mergedProperties])
                                    ->event('created')
                                    ->log('One-time payment created'); // Log description

                                // return response()->json([
                                //     'ApiName' => 'one_time_payment',
                                //     'status' => true,
                                //     'message' => 'success!',
                                //     'everee_response' => $payable['success']['everee_response'],
                                //     'data' => $response
                                // ], 200);
                            } else {
                                $untracked['fail']['everee_response']['errorMessage'] = isset($untracked['fail']['everee_response']['errorMessage']) ? $untracked['fail']['everee_response']['errorMessage'] : (isset($untracked['fail']['everee_response']['error']) ? $untracked['fail']['everee_response']['error'] : 'An error occurred during the payment process.');

                                return response()->json([
                                    'status' => false,
                                    'message' => $untracked['fail']['everee_response']['errorMessage'],
                                    'ApiName' => 'one_time_payment',
                                    'response' => $untracked['fail']['everee_response'],
                                ], 400);
                            }
                        }
                    } else {
                        return response()->json([
                            'ApiName' => 'one time payment payroll.',
                            'data' => $payroll_id.' payroll one time payment execution failed.',
                            'status' => false,
                            'message' => 'This payroll already in finalize stage.',
                        ], 400);
                    }
                    // }
                    // return response()->json([
                    //     'ApiName' => 'one_time_payment',
                    //     'status' => true,
                    //     'message' => 'success!',
                    //     'everee_response' => true,
                    //     'data' => ''
                    // ], 200);
                } else {
                    return response()->json([
                        'error' => ['message' => ['Please select payroll.']],
                    ], 400);
                }

                // }
                return response()->json([
                    'ApiName' => 'one_time_payment',
                    'status' => true,
                    'message' => 'success!',
                    'everee_response' => true,
                    'data' => '',
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => $api_name,
                    'status' => false,
                    'message' => 'Please choose at least one option.',
                ], 400);
            }

        } catch (\Exception $e) {
            // Log activity for failed payment creation
            activity()
                ->causedBy(Auth::user())
                ->withProperties(['error' => $e->getMessage()])
                ->log('Failed to create one-time payment');

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'Line' => $e->getLine(),
                'File' => $e->getFile(),
            ], 400);
        }
    }

    public function updateSingleOnetimePaymentStatus($request, $singleRecordId, $response, $payroll_id, $untracked, $payable_request, $CrmData, $CrmSetting, $payroll, $external_id): JsonResponse
    {

        if ($request->type == 'pid') {

            $paid = $request->paid; // 0, 1
            $recordId = $request->id;
            $payrollIds = $request->pid;
            $adjustmentId = $request->adjustment;
            $selectType = $request->select_type; // commision - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconcillation - 6
            $payPeriodTo = $request->pay_period_to;
            $payPeriodFrom = $request->pay_period_from;
            $clawbackId = 0;
            $next = 0;

            if ($paid) {
                $api_name = 'Onetime Payment';
            } else {
                $api_name = 'No Onetime Payment';
            }
            $jsonData = $request->json()->all();
            if (! empty($singleRecordId)) {
                $recordId = is_array($singleRecordId) ? $singleRecordId : [$singleRecordId];
            } else {
                $recordId = [0];
            }
            $payrollIds = $jsonData['pid'];

            DB::beginTransaction();
            try {
                switch ($selectType) {
                    case '1':
                        UserCommission::whereIn('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'is_next_payroll' => $next, 'one_time_payment_id' => $response->id, 'status' => '3']);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '2':
                        UserOverrides::whereIn('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '3':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => 'paid', 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '4':
                        ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => 'paid', 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '5':
                        PayrollDeductions::whereIn('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '7':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        ClawbackSettlement::whereIn('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        break;
                    case '8':
                        PayrollHourlySalary::whereIn('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'is_next_payroll' => $next, 'one_time_payment_id' => $response->id, 'status' => '3']);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '9':
                        PayrollOvertime::whereIn('id', $recordId)->update(['is_onetime_payment' => $paid, 'status' => '3', 'one_time_payment_id' => $response->id]);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'status' => '3', 'one_time_payment_id' => $response->id, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id, 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                }

                if ($paid) {
                    $message = 'Onetime Payment Successfully!';
                } else {
                    Payroll::whereIn('id', $payrollIds)->update(['is_onetime_payment' => 0]);
                    $message = 'Onetime Payment Successfully!';
                }

                DB::commit();

            } catch (Exception $err) {
                DB::rollBack();

                return response()->json([
                    'ApiName' => $api_name,
                    'status' => false,
                    'message' => $err->getMessage(),
                ], 400);
            }
        } else {

            try {

                $paid = $request->paid; // 0, 1
                $user_id = $request->user_id;
                $recordId = $request->id;
                $payrollIds = $request->pid;
                $adjustmentId = $request->adjustment;
                $selectType = $request->select_type; // commision - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconcillation - 6
                $payPeriodTo = $request->pay_period_to;
                $payPeriodFrom = $request->pay_period_from;
                $clawbackId = 0;
                $next = 0;

                if ($paid) {
                    $api_name = 'Onetime Payment';
                } else {
                    $api_name = 'No onetime payment';
                }
                // echo $api_name; die;

                $jsonData = $request->json()->all();

                if (! empty($singleRecordId)) {
                    $recordId = is_array($singleRecordId) ? $singleRecordId : [$singleRecordId];
                } else {
                    $recordId = [0];
                }

                $payrollIds = $jsonData['pid'];

                DB::beginTransaction();
                switch ($selectType) {
                    case '1':
                        UserCommission::where('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }

                        if ($clawbackId) {
                            ClawbackSettlement::where('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '2':
                        UserOverrides::whereIn('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::where('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '3':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::where('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => 'paid', 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::where('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '4':
                        ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => 'paid', 'is_next_payroll' => $next]);
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '5':
                        PayrollDeductions::where('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_onetime_payment' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '0', 'is_next_payroll' => $next]);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id, 'is_next_payroll' => $next]);
                                }
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::where('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '7':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        ClawbackSettlement::where('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        break;
                    case '8':
                        // $pid = PayrollHourlySalary::whereIn('id', $recordId)->value('id');
                        PayrollHourlySalary::where('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::where('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                    case '9':
                        // $pid = PayrollHourlySalary::whereIn('id', $recordId)->value('id');
                        PayrollOvertime::where('id', $recordId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3']);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::where('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_onetime_payment' => $paid, 'status' => '3', 'one_time_payment_id' => $response->id, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::where('id', $clawbackId)->update(['is_onetime_payment' => $paid, 'one_time_payment_id' => $response->id, 'status' => '3', 'is_next_payroll' => $next]);
                        }
                        break;
                        // default:

                }

                foreach ($payrollIds as $payrollId) {
                    $this->updateEveree($payrollId);
                }

                if ($paid) {
                    $message = 'Onetime Payment Successfully!';
                } else {
                    Payroll::whereIn('id', $payrollIds)->update(['is_onetime_payment' => 0]);
                    $message = 'Onetime Payment Successfully!';
                }
                DB::commit();
            } catch (Exception $err) {
                // Handle any exceptions that occur within the transaction
                $message = $err->getMessage();

                return response()->json([
                    'ApiName' => 'singlePayrollMoveToNextPayroll',
                    'status' => false,
                    'message' => $message,
                    'Line' => $err->getLine(),
                ], 400);

                DB::rollBack();
            }
        }

        if ($payroll && $request->pay_period_from && $request->pay_period_to && $request->pay_frequency && $untracked && $CrmData && $response) {
            $this->modelsLockingForSingleOnetimepayment($payroll, $request->pay_period_from, $request->pay_period_to, $request->pay_frequency, $untracked, $CrmData, $response, $singleRecordId);
        }
    }

    public function singlePayrollMoveToNextPayroll(Request $request)
    {
        if ($request->type == 'pid') {
            $next = $request->next; // 0,1
            $recordId = $request->id;
            $payrollId = $request->pid;
            $adjustmentId = $request->adjustment;
            $selectType = $request->select_type; // commision - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconcillation - 6
            $payPeriodTo = $request->pay_period_to;
            $payPeriodFrom = $request->pay_period_from;
            $clawbackId = 0;
            $paid = 0;

            $validator = Validator::make($request->all(), [
                'next' => 'required',
                'id' => 'required',
                'pid' => 'required',
                'select_type' => 'required',
                'pay_period_to' => 'required',
                'pay_period_from' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            if (Payroll::where(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => 'singlePayrollMoveToNextPayroll',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            $jsonData = $request->json()->all();
            $recordId = $jsonData['id'];
            if (isset($jsonData['id']['commission'])) {
                $recordId = $jsonData['id']['commission'];
            }
            if (isset($jsonData['id']['overrides'])) {
                $recordId = $jsonData['id']['overrides'];
            }
            if (isset($jsonData['id']['request'])) {
                $recordId = $jsonData['id']['request'];
            }
            if (isset($jsonData['id']['clawback'])) {
                $clawbackId = $jsonData['id']['clawback'];
            }
            if (isset($jsonData['id']['deduction'])) {
                $recordId = $jsonData['id']['deduction'];
            }
            if (isset($jsonData['id']['hourlysalary'])) {
                $recordId = $jsonData['id']['hourlysalary'];
            }
            if (isset($jsonData['id']['overtime'])) {
                $recordId = $jsonData['id']['overtime'];
            }

            // Ensure recordId is an array and remove any nested arrays
            $recordId = is_array($recordId) ? array_filter($recordId, function ($value) {
                return ! is_array($value);
            }) : [$recordId];

            $payrollIds = $jsonData['pid'];

            if ($next) {
                $api_name = 'Moved To Next Payroll';
            } else {
                $api_name = 'Moved To Previous Payroll';
            }

            DB::beginTransaction();
            try {
                switch ($selectType) {
                    case '1':
                        UserCommission::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'commision', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_next_payroll' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '0']);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '1']);
                                }
                            }
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '2':
                        UserOverrides::whereIn('id', $recordId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'override', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_next_payroll' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '0']);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '1']);
                                }
                            }
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '3':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_next_payroll' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '0']);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '1']);
                                }
                            }
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        $appreject = ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->pluck('id');
                        if ($appreject) {
                            $this->updateReferences($appreject, 'approvalreject', $next);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '4':
                        ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        $appreject = ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->pluck('id');
                        if ($appreject) {
                            $this->updateReferences($appreject, 'approvalreject', $next);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '5':
                        PayrollDeductions::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'deduction', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '7':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_next_payroll' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '0']);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '1']);
                                }
                            }
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($recordId) {
                            ClawbackSettlement::whereIn('id', $recordId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($recordId, 'clawback', $next);
                        }
                        break;
                    case '8':
                        PayrollHourlySalary::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'hourlysalary', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_next_payroll' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '0']);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '1']);
                                }
                            }
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '9':
                        PayrollOvertime::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'overtime', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            foreach ($payrollIds as $payrollId) {
                                $pay = Payroll::find($payrollId);
                                $detail = PayrollAdjustmentDetail::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'is_next_payroll' => '0'])->first();
                                if ($detail) {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '0']);
                                } else {
                                    PayrollAdjustment::where('payroll_id', $pay->id)->where(['user_id' => $pay->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => '1']);
                                }
                            }
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                }
                foreach ($payrollIds as $payrollId) {
                    $this->updateEveree($payrollId);
                }

                if ($next) {
                    $message = 'Moved To Next Payroll Successfully!';
                } else {
                    Payroll::whereIn('id', $payrollIds)->update(['is_next_payroll' => 0]);
                    $message = 'Moved To Current Payroll Successfully!';
                }
                DB::commit();

                return response()->json([
                    'ApiName' => $api_name,
                    'status' => true,
                    'message' => $message,
                ]);
            } catch (Exception $err) {
                // Handle any exceptions that occur within the transaction
                $message =
                DB::rollBack();

                return response()->json([
                    'ApiName' => $api_name,
                    'status' => false,
                    'message' => $err->getMessage(),
                ], 200);
            }
        } else {
            $next = $request->next; // 0,1
            $user_id = $request->user_id;
            $recordId = $request->id;
            $payrollId = $request->pid;
            $adjustmentId = $request->adjustment;
            $selectType = $request->select_type; // commision - 1, override - 2, adjustment - 3, reimbursement - 4, deduction - 5, reconcillation - 6
            $payPeriodTo = $request->pay_period_to;
            $payPeriodFrom = $request->pay_period_from;
            $clawbackId = 0;
            $paid = 0;

            $Validator = Validator::make(
                $request->all(),
                [
                    'next' => 'required',
                    'user_id' => 'required',
                    'id' => 'required',
                    'pid' => 'required',
                    'select_type' => 'required',
                    'pay_period_to' => 'required',
                    'pay_period_from' => 'required',
                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            if (Payroll::where(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => 'singlePayrollMoveToNextPayroll',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            $jsonData = $request->json()->all();
            $recordId = $jsonData['id'];
            if (isset($jsonData['id']['commission'])) {
                $recordId = $jsonData['id']['commission'];
            }
            if (isset($jsonData['id']['overrides'])) {
                $recordId = $jsonData['id']['overrides'];
            }
            if (isset($jsonData['id']['request'])) {
                $recordId = $jsonData['id']['request'];
            }
            if (isset($jsonData['id']['clawback'])) {
                $clawbackId = $jsonData['id']['clawback'];
            }
            if (isset($jsonData['id']['deduction'])) {
                $recordId = $jsonData['id']['deduction'];
            }
            if (isset($jsonData['id']['hourlysalary'])) {
                $recordId = $jsonData['id']['hourlysalary'];
            }
            if (isset($jsonData['id']['overtime'])) {
                $recordId = $jsonData['id']['overtime'];
            }

            // Ensure recordId is an array and remove any nested arrays
            $recordId = is_array($recordId) ? array_filter($recordId, function ($value) {
                return ! is_array($value);
            }) : [$recordId];

            $payrollIds = $jsonData['pid'];

            if ($next) {
                $api_name = 'Moved To Next Payroll';
            } else {
                $api_name = 'Moved To Previous Payroll';
            }
            $ids = [];
            DB::beginTransaction();
            try {
                switch ($selectType) {
                    case '1':
                        $pid = UserCommission::whereIn('id', $recordId)->value('pid');
                        UserCommission::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'commision', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '2':
                        $pid = UserOverrides::whereIn('id', $recordId)->value('pid');
                        UserOverrides::whereIn('id', $recordId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'override', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '3':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        $appreject = ApprovalsAndRequest::whereIn('id', $recordId)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->pluck('id');
                        if ($appreject) {
                            $this->updateReferences($appreject, 'approvalreject', $next);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '4':
                        ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                        $appreject = ApprovalsAndRequest::whereIn('id', $recordId)->where('adjustment_type_id', 2)->pluck('id');
                        if ($appreject) {
                            $this->updateReferences($appreject, 'approvalreject', $next);
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '5':
                        PayrollDeductions::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'deduction', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '7':
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($recordId) {
                            ClawbackSettlement::whereIn('id', $recordId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($recordId, 'clawback', $next);
                        }
                        break;
                    case '8':
                        // $pid = PayrollHourlySalary::whereIn('id', $recordId)->value('pid');
                        PayrollHourlySalary::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'hourlysalary', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                    case '9':
                        // $pid = PayrollHourlySalary::whereIn('id', $recordId)->value('pid');
                        PayrollOvertime::whereIn('id', $recordId)->update(['is_next_payroll' => $next]);
                        $this->updateReferences($recordId, 'overtime', $next);
                        if (! empty($adjustmentId)) {
                            PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $payrolladj = PayrollAdjustmentDetail::whereIn('id', $adjustmentId)->pluck('id');
                            if ($payrolladj) {
                                $this->updateReferences($payrolladj, 'adjustment', $next);
                            }
                        }
                        if ($clawbackId) {
                            ClawbackSettlement::whereIn('id', $clawbackId)->update(['is_mark_paid' => $paid, 'is_next_payroll' => $next]);
                            $this->updateReferences($clawbackId, 'clawback', $next);
                        }
                        break;
                        // default:

                }
                foreach ($payrollIds as $payrollId) {
                    $this->updateEveree($payrollId);
                }

                if ($next) {
                    $message = 'Moved To Next Payroll Successfully!';
                } else {
                    Payroll::whereIn('id', $payrollIds)->update(['is_next_payroll' => 0]);
                    $message = 'Moved To Current Payroll Successfully!';
                }
                DB::commit();
            } catch (Exception $err) {
                // Handle any exceptions that occur within the transaction
                $message = $err->getMessage();
                DB::rollBack();
            }

            return response()->json([
                'ApiName' => $api_name,
                'status' => true,
                'message' => $message,
            ], 200);
        }
    }

    private function updateReferences($check_ref_ids, $type, $next = 1)
    {
        foreach ($check_ref_ids as $check_ref_id) {
            if ($next) {
                $date = date('Y-m-d');
            } else {
                $date = '';
            }
            if ($type == 'clawback') {
                $clawback = ClawbackSettlement::find($check_ref_id);
                if ($clawback && $clawback->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    ClawbackSettlement::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($clawback) {
                    $pay_period = PayrollCommon::where('id', $clawback->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $clawback->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        ClawbackSettlement::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'commision') {
                $commision = UserCommission::find($check_ref_id);
                if ($commision && $commision->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    UserCommission::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($commision) {
                    $pay_period = PayrollCommon::where('id', $commision->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $commision->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        UserCommission::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'override') {
                $override = UserOverrides::find($check_ref_id);
                if ($override && $override->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    UserOverrides::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($override) {
                    $pay_period = PayrollCommon::where('id', $override->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $override->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        UserOverrides::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'adjustment') {
                $adjustment = PayrollAdjustmentDetail::find($check_ref_id);
                if ($adjustment && $adjustment->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    PayrollAdjustmentDetail::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($adjustment) {
                    $pay_period = PayrollCommon::where('id', $adjustment->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $adjustment->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        PayrollAdjustmentDetail::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'approvalreject') {
                $approvalreject = ApprovalsAndRequest::find($check_ref_id);
                if ($approvalreject && $approvalreject->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    ApprovalsAndRequest::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($approvalreject) {
                    $pay_period = PayrollCommon::where('id', $approvalreject->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $approvalreject->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        ApprovalsAndRequest::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }

            } elseif ($type == 'approvalrejectreimbursement') {
                $approvalreject = ApprovalsAndRequest::find($check_ref_id);
                if ($approvalreject && $approvalreject->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    ApprovalsAndRequest::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($approvalreject) {
                    $pay_period = PayrollCommon::where('id', $approvalreject->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $approvalreject->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        ApprovalsAndRequest::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'deduction') {
                $deduction = PayrollDeductions::find($check_ref_id);
                if ($deduction && $deduction->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    PayrollDeductions::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($deduction) {
                    $pay_period = PayrollCommon::where('id', $deduction->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $deduction->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        PayrollDeductions::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'hourlysalary') {
                $hourlysalary = PayrollHourlySalary::find($check_ref_id);
                if ($hourlysalary && $hourlysalary->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    PayrollHourlySalary::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($hourlysalary) {
                    $pay_period = PayrollCommon::where('id', $hourlysalary->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $hourlysalary->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        PayrollHourlySalary::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'overtime') {
                $overtime = PayrollOvertime::find($check_ref_id);
                if ($overtime && $overtime->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    PayrollOvertime::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($overtime) {
                    $pay_period = PayrollCommon::where('id', $overtime->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $overtime->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        PayrollOvertime::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'customField') {
                $customField = CustomField::find($check_ref_id);
                if ($customField && $customField->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    CustomField::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                } elseif ($customField) {
                    $pay_period = PayrollCommon::where('id', $customField->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $customField->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        CustomField::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            } elseif ($type == 'reconciliation') {
                $reconFinalizeData = ReconciliationFinalizeHistory::find($check_ref_id);
                if ($reconFinalizeData && $reconFinalizeData->ref_id == 0) {
                    $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                    $reconFinalizeData->update([
                        'ref_id' => $payrollCommon->id,
                    ]);
                } elseif ($reconFinalizeData) {
                    $pay_period = PayrollCommon::where('id', $reconFinalizeData->ref_id)->first();
                    if ($pay_period) {
                        if ($pay_period->orig_payfrom == '') {
                            PayrollCommon::where('id', $reconFinalizeData->ref_id)->update(['payroll_modified_date' => $date]);
                        }
                    } else {
                        $payrollCommon = PayrollCommon::create(['payroll_modified_date' => $date]);
                        ReconciliationFinalizeHistory::where('id', $check_ref_id)->update(['ref_id' => $payrollCommon->id]);
                    }
                }
            }
        }
    }

    public function singlePayrollAdjustmentdelete(Request $request): JsonResponse
    {
        $recordId = (int) $request->id;
        $payrollId = $request->pid;
        $user_id = $request->user_id;
        $selectType = $request->select_type; // commision - 1, override - 2
        $payPeriodTo = $request->pay_period_to;
        $payPeriodFrom = $request->pay_period_from;

        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required',
                'id' => 'required',
                'pid' => 'required',
                'select_type' => 'required',
                'pay_period_to' => 'required',
                'pay_period_from' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if (Payroll::where(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo, 'status' => '2'])->first()) {
            return response()->json([
                'ApiName' => 'singlePayrollAdjustmentdelete',
                'status' => false,
                'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
            ], 400);
        }

        $jsonData = $request->json()->all();
        $recordId = $jsonData['id'];
        $payrollIds = $jsonData['pid'];

        DB::beginTransaction();
        try {
            PayrollAdjustmentDetail::whereIn('id', $recordId)->delete();
            if ($selectType == 1) {
                PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'commission_type' => 'commission', 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['commission_amount' => 0]);
            } elseif ($selectType == 2) {
                PayrollAdjustment::whereIn('payroll_id', $payrollIds)->where(['user_id' => $user_id, 'overrides_type' => 'overrides', 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->update(['overrides_amount' => 0]);
            }
            foreach ($payrollIds as $payrollId) {
                $this->updateEveree($payrollId);
            }
            DB::commit();
            $message = 'Adjustment Deleted Successfully!';
        } catch (Exception $err) {
            // Handle any exceptions that occur within the transaction
            $message = $err->getMessage();
            DB::rollBack();
        }

        return response()->json([
            'ApiName' => 'Adjustment Deleted',
            'status' => true,
            'message' => $message,
        ], 200);
    }

    private function updateEveree($payrollId)
    {
        $payroll = Payroll::where(['id' => $payrollId])->first();
        if (isset($payroll)) {
            if ($payroll->status == 2) {
                $finalizeData = Payroll::where(['id' => $payrollId])->update(['status' => 1, 'finalize_status' => 0]);
                if ($finalizeData == 1) {
                    $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                    if ($CrmData) {
                        $external_id = $payroll->everee_external_id;
                        $payabledata = $this->get_payable($external_id, $payroll->user_id); // check payable in everee
                        if (! empty($payabledata)) {
                            foreach ($payabledata as $payabledata) {
                                if (isset($payabledata['id']) && ($payabledata['id'] == $external_id)) {
                                    $untracked = $this->delete_payable($external_id, $payroll->user_id); // delete payable in everee
                                    if ($untracked == null || (isset($untracked['errorCode']) && $untracked['errorCode'] == 404)) {
                                        Payroll::where(['id' => $payroll->id])->update(['everee_external_id' => null]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function finalizePayroll(Request $request): JsonResponse
    {
        try {
            $validat = finalizePayrollValidations($request);
            if (! $validat['success']) {
                return response()->json($validat, $validat['code']);
            }

            $endDate = $request->end_date;
            $startDate = $request->start_date;
            $frequencyTypeId = $request->pay_frequency;
            $workerType = isset($request->worker_type) ? $request->worker_type : '1099';

            if (Crms::where(['id' => 3, 'status' => 1])->first()) {
                $token = $this->gettoken($workerType);
                if (isset($token->username) && empty($token->username)) {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'finalize_payroll',
                        'message' => 'Sequipay key is not set up. Please configure the Sequipay key or disable Sequipay on the server!!',
                    ], 400);
                }

                $check = $this->validateTenantApiKey($workerType);
                if (isset($check['error']) && $check['error'] == 'unauthorized') {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'finalize_payroll',
                        'message' => 'Sequipay is not Authorized, Please contact your administrator!!',
                    ], 400);
                }
            }

            $usersIds = User::where('worker_type', $workerType)->whereIn('sub_position_id', function ($query) use ($frequencyTypeId) {
                $query->select('position_id')->from('position_pay_frequencies')->where('frequency_type_id', $frequencyTypeId);
            })->pluck('id');

            if ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
                DailyPayFrequency::updateOrCreate(['pay_period_from' => $startDate, 'pay_period_to' => $endDate], [
                    'pay_period_from' => $startDate,
                    'pay_period_to' => $endDate,
                    'closed_status' => 0,
                    'open_status_from_bank' => 0,
                ]);
            }

            $auth = auth()->user();
            $query = Payroll::whereHas('usersdata')->with('usersdata', 'positionDetail')
                ->where('status', '!=', 2)->where(['is_stop_payroll' => 0, 'is_onetime_payment' => 0])->whereIn('user_id', $usersIds)->whereIn('finalize_status', [0, 3])
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                });
            $payrolls = $query->get();
            if ($workerType == 'w2' || $workerType == 'W2') {
                $nextPayrollQuery = clone $query;
                $userIdArray = $nextPayrollQuery->where('is_next_payroll', '!=', 1)->pluck('user_id')->toArray();
                $attendanceCheck = $this->getUserAttendenceApprovedStatus($userIdArray, $startDate, $endDate);
                if ($attendanceCheck) {
                    return response()->json([
                        'ApiName' => 'finalizePayroll',
                        'status' => false,
                        'unapprove_status' => 1,
                        'message' => 'Warning: Enable to process your request to finalize payroll for this pay period. Because this payroll users attendace not approved.',
                    ]);
                }

                $final = false;
                foreach ($payrolls as $key => $payroll) {
                    if (($key + 1) == count($payrolls)) {
                        $final = true;
                    }
                    finalizeW2PayrollJob::dispatch($payroll, $startDate, $endDate, $auth, $frequencyTypeId, $final);
                }
            } else {
                $final = false;
                foreach ($payrolls as $key => $payroll) {
                    if (($key + 1) == count($payrolls)) {
                        $final = true;
                    }
                    finalizePayrollJob::dispatch($payroll, $startDate, $endDate, $auth, $frequencyTypeId, $final);
                }
            }

            Payroll::where(['status' => 1, 'finalize_status' => 0, 'is_stop_payroll' => 0, 'is_onetime_payment' => 0])->whereIn('user_id', $usersIds)
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->update(['finalize_status' => 1]);

            return response()->json([
                'ApiName' => 'get_payroll_data',
                'status' => true,
                'message' => 'Successfully',
                'data' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'get_payroll_data',
                'message' => $e->getMessage(),
                'Line' => $e->getLine(),
                'File' => $e->getFile(),
            ], 400);
        }
    }

    public function executePayroll(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'execute_payroll',
                    'status' => false,
                    'error' => $validator->errors(),
                ], 400);
            }

            if ($request->pay_frequency == FrequencyType::DAILY_PAY_ID) {
                $validator = Validator::make($request->all(), [
                    'start_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                    'end_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'ApiName' => 'finalize_payroll',
                        'status' => false,
                        'error' => $validator->errors(),
                    ], 400);
                }
            }
            $workerType = isset($request->worker_type) ? $request->worker_type : '1099';
            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $payFrequency = $request->pay_frequency;

            if (Payroll::where(['status' => 2, 'finalize_status' => 3, 'is_onetime_payment' => 0])->first()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'finalize_payroll',
                    'message' => 'Some users failed to sync with SequiPay, so you cannot execute the payroll!!',
                ], 400);
            }

            if ($payroll = Payroll::where(['status' => 3, 'is_onetime_payment' => 0])->first()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'finalize_payroll',
                    'message' => 'Payroll is being processed for the pay period from '.date('m/d/Y', strtotime($payroll->pay_period_from)).' to '.date('m/d/Y', strtotime($payroll->pay_period_to)),
                ], 400);
            }

            if (Crms::where(['id' => 3, 'status' => 1])->first()) {
                $token = $this->gettoken($workerType);
                if (isset($token->username) && empty($token->username)) {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'finalize_payroll',
                        'message' => 'Sequipay key is not set up. Please configure the Sequipay key or disable Sequipay on the server!!',
                    ], 400);
                }

                $check = $this->validateTenantApiKey($workerType);
                if (isset($check['error']) && $check['error'] == 'unauthorized') {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'finalize_payroll',
                        'message' => 'Sequipay is not Authorized, Please contact your administrator!!',
                    ], 400);
                }
            }

            $checkNetxPayroll = Payroll::whereHas('usersdata')->when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })->where(['status' => 2, 'finalize_status' => 2, 'is_next_payroll' => 1, 'is_onetime_payment' => 0, 'worker_type' => $workerType])->first();
            $nextPeriod = $this->payFrequencyById($endDate, $payFrequency, $workerType);
            if ($checkNetxPayroll && ! isset($nextPeriod->pay_period_from)) {
                return response()->json([
                    'ApiName' => 'finalize_payroll',
                    'status' => false,
                    'error' => 'No next pay period available to move user to next pay period.',
                ], 400);
            }
            $advanceSetting = AdvancePaymentSetting::first();
            if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                if (! isset($nextPeriod->pay_period_from)) {
                    return response()->json([
                        'ApiName' => 'finalize_payroll',
                        'status' => false,
                        'error' => 'No next pay period available to move user to next pay period.',
                    ], 400);
                }
            }

            Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'status' => 2, 'finalize_status' => 2, 'is_onetime_payment' => 0])->update(['status' => 3]);
            $newFromDate = $nextPeriod->pay_period_from;
            $newToDate = $nextPeriod->pay_period_to;
            $this->movePayrollData($startDate, $endDate, $newFromDate, $newToDate, $workerType, $payFrequency);

            $query = Payroll::whereHas('usersdata')->with('usersdata')->when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })->where(['status' => 3, 'is_stop_payroll' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0, 'worker_type' => $workerType]);

            $openStatusFromBank = 0;
            $netPayQuery = clone $query;
            $netPayZeroAmount = $netPayQuery->where('net_pay', '>', 0)->count();
            if (Crms::where(['id' => 3, 'status' => 1])->first() && $netPayZeroAmount > 0) {
                $openStatusFromBank = 1;
            }

            $final = false;
            $payrolls = $query->get();
            foreach ($payrolls as $key => $payroll) {
                if (($key + 1) == count($payrolls)) {
                    $final = true;
                }
                executePayrollJob::dispatch($payroll, $startDate, $endDate, $newFromDate, $newToDate, $payFrequency, $final);
            }

            if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
                // CRITICAL: Use model instance save() to trigger observers (not mass update)
                $daily = DailyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                if ($daily) {
                    $daily->closed_status = 1;
                    $daily->open_status_from_bank = $openStatusFromBank;
                    $daily->save();
                }
            } else {
                // CRITICAL: Use model instance save() to trigger observers (not mass update)
                if ($workerType == 'w2' || $workerType == 'W2') {
                    $weekly = WeeklyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($weekly) {
                        $weekly->w2_closed_status = 1;
                        $weekly->w2_open_status_from_bank = $openStatusFromBank;
                        $weekly->save();
                    }
                    
                    $monthly = MonthlyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($monthly) {
                        $monthly->w2_closed_status = 1;
                        $monthly->w2_open_status_from_bank = $openStatusFromBank;
                        $monthly->save();
                    }
                    
                    $additional = AdditionalPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($additional) {
                        $additional->w2_closed_status = 1;
                        $additional->w2_open_status_from_bank = $openStatusFromBank;
                        $additional->save();
                    }
                } else {
                    $weekly = WeeklyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($weekly) {
                        $weekly->closed_status = 1;
                        $weekly->open_status_from_bank = $openStatusFromBank;
                        $weekly->save();
                    }
                    
                    $monthly = MonthlyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($monthly) {
                        $monthly->closed_status = 1;
                        $monthly->open_status_from_bank = $openStatusFromBank;
                        $monthly->save();
                    }
                    
                    $additional = AdditionalPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($additional) {
                        $additional->closed_status = 1;
                        $additional->open_status_from_bank = $openStatusFromBank;
                        $additional->save();
                    }
                }
            }

            return response()->json([
                'ApiName' => 'execute_Payroll',
                'status' => true,
                'message' => 'payroll execution request sent successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'execute_Payroll',
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 400);
        }
    }

    public function movePayrollData($startDate, $endDate, $newFromDate, $newToDate, $workerType, $payFrequency)
    {
        $usersIds = User::whereIn('sub_position_id', function ($query) use ($payFrequency) {
            $query->select('position_id')->from('position_pay_frequencies')->where('frequency_type_id', $payFrequency);
        })->pluck('id');

        $userIdArray = Payroll::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->whereIn('user_id', $usersIds)->where(['worker_type' => $workerType, 'is_onetime_payment' => 0])->pluck('user_id')->toArray();

        $stopPayrolls = Payroll::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->select('id', 'user_id', 'is_stop_payroll')->where(['is_stop_payroll' => 1, 'is_onetime_payment' => 0])->whereIn('user_id', $userIdArray)->get();
        $tablesToUpdate = ['UserCommission', 'UserOverrides', 'ClawbackSettlement',  'ApprovalsAndRequest', 'PayrollAdjustment', 'PayrollAdjustmentDetail', 'PayrollHourlySalary', 'PayrollOvertime', 'CustomField'];
        foreach ($stopPayrolls as $val) {
            $payrollDeduction = PayrollDeductions::where(['payroll_id' => $val->id, 'user_id' => $val->user_id, 'is_stop_payroll' => 1, 'is_onetime_payment' => 0])->get();
            foreach ($payrollDeduction as $deduction) {
                $outstanding = ($deduction->amount - 0);
                PayrollDeductions::where('id', $deduction->id)->update(['total' => 0, 'outstanding' => $outstanding]);
            }

            foreach ($tablesToUpdate as $table) {
                $fullClassName = 'App\Models\\'.$table;
                $modelInstance = new $fullClassName;
                $whereConditions = ['user_id' => $val->user_id, 'is_onetime_payment' => 0];
                $updateData = ['pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate];
                $modelInstance::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->where($whereConditions)->whereIn('user_id', $userIdArray)->update($updateData);

                $modelInstance = new $fullClassName;
                $whereConditions = ['pay_period_from' => $startDate, 'pay_period_to' => $endDate];
                $updateData = ['is_next_payroll' => 0, 'payroll_id' => 0, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate];
                $modelInstance::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->where('is_next_payroll', '>', 0)->where(['is_onetime_payment' => 0])->whereIn('user_id', $userIdArray)->update($updateData);

                $newdata = $modelInstance::where(['pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate])->whereIn('user_id', $userIdArray)->get();
                foreach ($newdata as $value) {
                    $check_ref_id = PayrollCommon::where(['id' => $value->ref_id])->whereNull('orig_payfrom')->count();
                    if ($check_ref_id > 0) {
                        PayrollCommon::where('id', $value->ref_id)->update(['orig_payfrom' => $startDate, 'orig_payto' => $endDate]);
                    }

                    $payRoll = Payroll::where(['user_id' => $value->user_id, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0])->first();
                    if ($payRoll) {
                        $modelInstance::where(['user_id' => $value->user_id, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0])->update(['payroll_id' => $payRoll->id]);
                    } else {
                        $userpositionId = User::select('id', 'sub_position_id')->where('id', $value->user_id)->first();
                        $payRoll = Payroll::create([
                            'user_id' => $value->user_id,
                            'position_id' => isset($value->position_id) ? $value->position_id : $userpositionId->sub_position_id,
                            'pay_period_from' => isset($newFromDate) ? $newFromDate : null,
                            'pay_period_to' => isset($newToDate) ? $newToDate : null,
                            'status' => 1,
                        ]);
                        $modelInstance::where(['user_id' => $value->user_id, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0])->update(['payroll_id' => $payRoll->id]);
                    }
                }
            }

            CustomField::where(['user_id' => $val->user_id, 'payroll_id' => $val->id])->update(['pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate]);
            if (! Payroll::where(['user_id' => $val->user_id, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0])->first()) {
                Payroll::where(['id' => $val->id, 'is_stop_payroll' => 1, 'is_onetime_payment' => 0])->update(['pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate]);
            }
        }

        $payrollDeduction = PayrollDeductions::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->where(['is_next_payroll' => 1, 'is_onetime_payment' => 0])->whereIn('user_id', $userIdArray)->get();
        foreach ($payrollDeduction as $deduction) {
            $outstanding = ($deduction->amount - 0);
            PayrollDeductions::where('id', $deduction->id)->update(['total' => 0, 'outstanding' => $outstanding]);
        }

        foreach ($tablesToUpdate as $table) {
            $fullClassName = 'App\Models\\'.$table;
            $modelInstance = new $fullClassName;
            $updateData = ['is_next_payroll' => 0, 'payroll_id' => 0, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate];
            $modelInstance::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })->where('is_next_payroll', '>', 0)->whereIn('user_id', $userIdArray)->where(['is_onetime_payment' => 0])->update($updateData);

            $newdata = $modelInstance::where(['pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0])->whereIn('user_id', $userIdArray)->get();
            foreach ($newdata as $value) {
                $checkRefId = PayrollCommon::where(['id' => $value->ref_id])->whereNull('orig_payfrom')->count();
                if ($checkRefId > 0) {
                    PayrollCommon::where('id', $value->ref_id)->update(['orig_payfrom' => $startDate, 'orig_payto' => $endDate]);
                }

                if ($payRoll = Payroll::where(['user_id' => $value->user_id, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0, 'worker_type' => $workerType])->first()) {
                    $modelInstance::where(['user_id' => $value->user_id, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0])->update(['payroll_id' => $payRoll->id]);
                } else {
                    $userpositionId = User::select('id', 'sub_position_id')->where('id', $value->user_id)->first();
                    $payRoll = Payroll::create([
                        'user_id' => $value->user_id,
                        'position_id' => isset($value->position_id) ? $value->position_id : $userpositionId->sub_position_id,
                        'pay_period_from' => isset($newFromDate) ? $newFromDate : null,
                        'pay_period_to' => isset($newToDate) ? $newToDate : null,
                        'status' => 1,
                    ]);
                    $modelInstance::where(['user_id' => $value->user_id, 'pay_period_from' => $newFromDate, 'pay_period_to' => $newToDate, 'is_onetime_payment' => 0])->update(['payroll_id' => $payRoll->id]);
                }
            }
        }

        Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'worker_type' => $workerType, 'is_onetime_payment' => 0])->where('is_next_payroll', '>', 0)->whereIn('user_id', $userIdArray)->delete();
    }

    public function create_payment_for_pay_now(Request $request): JsonResponse
    {
        // dd('dfdfd');
        $req_user_id = '';
        $type_id = 12;
        $req_id = '';
        $req_amount = '';
        $req_no = '';
        $req_des = '';

        try {
            $validator = Validator::make($request->all(), [
                'payrollId' => 'required|array',
                'select_type' => 'required|in:this_page,all_pages',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $payrolls = Payroll::whereIn('id', $request->payrollId)->where(['status' => 2])->get();
            if (! empty($payrolls->toArray())) {
                return response()->json([
                    'ApiName' => 'one time payment payroll.',
                    'status' => false,
                    'data' => $payrolls->toArray(),
                    'message' => 'The payroll has been already finalized. No changes can be made after finalization.',
                ], 400);
            }
            $payrollUsersId = Payroll::whereIn('id', $request->payrollId)->pluck('user_id');
            if ($payrollUsersId) {
                $usersRecord = User::whereIn('id', $payrollUsersId)->where(function ($query) {
                    $query->whereNull('employee_id')
                        ->orWhere('employee_id', '')
                        ->orWhereNull('everee_workerId')
                        ->orWhere('everee_workerId', '');
                })->get();
                if (! empty($usersRecord->toArray())) {
                    return response()->json([
                        'ApiName' => 'one_time_payment',
                        'status' => false,
                        'data' => $usersRecord,
                        'message' => 'Since the user has not completed their self-onboarding process and their information is incomplete, we are unable to process the payment. Please ensure their details are fully updated to proceed with the payment.',
                    ], 400);
                }
            }

            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            $CrmSetting = CrmSetting::where('crm_id', 3)->first();

            if (empty($CrmData)) {
                return response()->json([
                    'error' => ['message' => ['You are presently not set up to utilize Sequifi\'s payment services. Therefore, this payment cannot be processed. Please reach out to your system administrator.']],
                ], 400);
            } else {
                if ($request->payrollId) {
                    foreach ($request->payrollId as $payroll_id) {

                        $payroll = Payroll::where(['id' => $payroll_id, 'is_mark_paid' => 0, 'status' => 1, 'finalize_status' => 0, 'is_onetime_payment' => 0])->first();
                        if ($payroll) {

                            // dd($payroll->net_pay);
                            $pay_amount = $payroll->net_pay;

                            $uid = isset($payroll->user_id) ? $payroll->user_id : '';
                            $user = User::where('id', $uid)->first();
                            $positionPayFrequency = PositionPayFrequency::query()->where(['position_id' => $user->sub_position_id])->first();
                            if (! $positionPayFrequency) {
                                return response()->json([
                                    'error' => ['messege' => ['sorry user doesn\'t have any position pay frequency that\'s why we are unable to process right now.']],
                                ], 400);
                            }

                            $check = OneTimePayments::where('adjustment_type_id', $type_id)->count();
                            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                            $CrmSetting = CrmSetting::where('crm_id', 3)->first();

                            if ($user && ($user->employee_id == null || $user->employee_id == '' || $user->everee_workerId == null || $user->everee_workerId == '')) {
                                return response()->json([
                                    'ApiName' => 'one_time_payment',
                                    'status' => false,
                                    'message' => 'Since the user has not completed their self-onboarding process and their information is incomplete, we are unable to process the payment. Please ensure their details are fully updated to proceed with the payment.',
                                ], 400);
                            }

                            if (! empty($CrmData) && ! empty($CrmSetting)) {
                                if ($type_id == 1) {
                                    if (! empty($check)) {
                                        $req_no = 'OTPD'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTPD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } elseif ($type_id == 2) {
                                    if (! empty($check)) {
                                        $req_no = 'OTR'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } elseif ($type_id == 3) {
                                    if (! empty($check)) {
                                        $req_no = 'OTB'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTB'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } elseif ($type_id == 4) {
                                    if (! empty($check)) {
                                        $req_no = 'OTA'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } elseif ($type_id == 6) {
                                    if (! empty($check)) {
                                        $req_no = 'OTI'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTI'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } elseif ($type_id == 10) {
                                    if (! empty($request->customer_pid)) {
                                        $req_no = 'OTC'.$request->customer_pid;
                                    } else {
                                        $req_no = 'OTC'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } elseif ($type_id == 11) {
                                    if (! empty($check)) {
                                        $req_no = 'OTOV'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTOV'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } elseif ($type_id == 12) {
                                    if (! empty($check)) {
                                        $req_no = 'OTPR'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTPR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                } else {
                                    if (! empty($check)) {
                                        $req_no = 'OTO'.str_pad($check + 1, 6, '0', STR_PAD_LEFT);
                                    } else {
                                        $req_no = 'OTO'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                                    }
                                }

                                // $external_id = $user->employee_id . "-" . strtotime('now');

                                $amount = isset($pay_amount) ? $pay_amount : $req_amount;
                                $evereeFields = [
                                    'usersdata' => [
                                        'employee_id' => $user->employee_id,
                                        'everee_workerId' => $user->everee_workerId,
                                        'id' => $user->id,
                                        'onboardProcess' => $user->onboardProcess,
                                    ],
                                    'net_pay' => $amount,
                                    'payable_type' => 'one time payment',
                                    'payable_label' => 'one time payment',
                                ];

                                // if REIMBURSEMENT exits in payroll than REIMBURSEMENT - from netpay
                                // commission

                                if ($payroll->reimbursement > 0) {
                                    $R_external_id = 'OTR-'.$user->employee_id.'-'.$payroll->id.'-'.strtotime('now');
                                    $reimbursement = $payroll['reimbursement'];
                                    $net_pay = $evereeFields['net_pay'];
                                    $evereeFields['net_pay'] = $reimbursement;
                                    $R_untracked = $this->add_payable($evereeFields, $R_external_id, 'REIMBURSEMENT');  // add  payable in everee
                                    if ((isset($R_untracked['success']['status']) && $R_untracked['success']['status'] == true)) {
                                        $C_external_id = 'PRR-'.$user->employee_id.'-'.$payroll->id.'-'.strtotime('now');
                                        $evereeFields['net_pay'] = $net_pay - $reimbursement;
                                        if ($payroll->net_pay > 0) {
                                            $C_untracked = $this->add_payable($evereeFields, $C_external_id, 'COMMISSION');  // Add payable in everee
                                            if ((isset($C_untracked['success']['status']) && $C_untracked['success']['status'] == true)) {
                                                $external_id = $R_external_id.','.$C_external_id;
                                                $untracked = $C_untracked;
                                            } else {
                                                $this->delete_payable($R_external_id, $uid);
                                                $external_id = '';
                                                $untracked = $C_untracked;
                                            }
                                        } else {
                                            $external_id = $R_external_id;
                                            $untracked = $R_untracked;
                                        }
                                    } else {
                                        $external_id = '';
                                        $untracked = $R_untracked;
                                    }
                                    $enableEVE = 1;
                                } else {
                                    $external_id = 'PRR-'.$user->employee_id.'-'.$payroll->id.'-'.strtotime('now');
                                    $untracked = $this->add_payable($evereeFields, $external_id, 'COMMISSION');  // update payable in everee
                                    $enableEVE = 1;
                                }

                                if ((isset($untracked['success']['status']) && $untracked['success']['status'] == true)) {
                                    $payable_request = $this->payable_request($evereeFields);
                                    $response = OneTimePayments::create([
                                        'user_id' => $uid,
                                        'req_id' => $req_id ? $req_id : null,
                                        'pay_by' => Auth::user()->id,
                                        'req_no' => $req_no ? $req_no : null,
                                        'everee_external_id' => $external_id,
                                        'everee_payment_req_id' => isset($payable_request['success']['paymentId']) ? $payable_request['success']['paymentId'] : null,
                                        'everee_paymentId' => isset($payable_request['success']['everee_payment_id']) ? $payable_request['success']['everee_payment_id'] : null,
                                        'adjustment_type_id' => $type_id,
                                        'amount' => $amount,
                                        'description' => $request->description ? $request->description : $req_des,
                                        'pay_date' => date('Y-m-d'),
                                        'payment_status' => 3,
                                        'everee_status' => 1,
                                        'everee_json_response' => isset($payable_request) ? json_encode($payable_request) : null,
                                        'everee_webhook_response' => null,
                                        'everee_payment_status' => 0,
                                    ]);
                                    $attributes = $request->all();
                                    if ($request && $response && $payroll_id) {
                                        if (! empty($CrmData) && ! empty($CrmSetting)) {
                                            $this->updateonetimepaymentstatus($request, $response, $payroll_id, $untracked, $payable_request, $CrmData, $CrmSetting, $payroll, $external_id);
                                        }
                                    }

                                    $oneTimePaymentId = $response->id;

                                    create_paystub_employee([
                                        'user_id' => $payroll->user_id,
                                        'pay_period_from' => $payroll->pay_period_from,
                                        'pay_period_to' => $payroll->pay_period_to,
                                    ]);

                                    // Merge additional keys into the attributes array
                                    $additionalProperties = [
                                        'req_no' => $response->req_no, // Include request number
                                        'everee_paymentId' => $response->everee_paymentId,
                                        'payment_status' => $response->payment_status,
                                        'everee_payment_status' => $response->everee_payment_status,
                                    ];

                                    $mergedProperties = array_merge($attributes, $additionalProperties);

                                    // Log activity
                                    activity()
                                        ->causedBy(Auth::user()) // The user who triggered the action
                                        ->performedOn($response) // The OneTimePayments record
                                        ->withProperties(['attributes' => $mergedProperties])
                                        ->event('created')
                                        ->log('One-time payment created'); // Log description

                                    // return response()->json([
                                    //     'ApiName' => 'one_time_payment',
                                    //     'status' => true,
                                    //     'message' => 'success!',
                                    //     'everee_response' => $payable['success']['everee_response'],
                                    //     'data' => $response
                                    // ], 200);
                                } else {
                                    $untracked['fail']['everee_response']['errorMessage'] = isset($untracked['fail']['everee_response']['errorMessage']) ? $untracked['fail']['everee_response']['errorMessage'] : (isset($untracked['fail']['everee_response']['error']) ? $untracked['fail']['everee_response']['error'] : 'An error occurred during the payment process.');

                                    return response()->json([
                                        'status' => false,
                                        'message' => $untracked['fail']['everee_response']['errorMessage'],
                                        'ApiName' => 'one_time_payment',
                                        'response' => $untracked['fail']['everee_response'],
                                    ], 400);
                                }
                            }
                        } else {
                            return response()->json([
                                'ApiName' => 'one time payment payroll.',
                                'data' => $payroll_id.' payroll one time payment execution failed.',
                                'status' => false,
                                'message' => 'This payroll already in finalize stage.',
                            ], 400);
                        }
                    }

                    return response()->json([
                        'ApiName' => 'one_time_payment',
                        'status' => true,
                        'message' => 'success!',
                        'everee_response' => true,
                        'data' => '',
                    ], 200);
                } else {
                    return response()->json([
                        'error' => ['message' => ['Please select payroll.']],
                    ], 400);
                }
            }
        } catch (\Exception $e) {
            // Log activity for failed payment creation
            activity()
                ->causedBy(Auth::user())
                ->withProperties(['error' => $e->getMessage()])
                ->log('Failed to create one-time payment');

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'Line' => $e->getLine(),
                'File' => $e->getFile(),
            ], 400);
        }
    }

    public function updateonetimepaymentstatus(Request $request, $response, $payroll_id, $untracked, $payable_request, $CrmData, $CrmSetting, $payrollData, $external_id): JsonResponse
    {
        // $data = array();
        // $payrollId = $request->payrollId;
        $select_type = $request->select_type;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        if ($request->type == 'pid') {
            DB::beginTransaction();
            try {
                UserCommission::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['is_next_payroll' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);
                UserOverrides::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['is_next_payroll' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);
                ClawbackSettlement::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['is_next_payroll' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);
                PayrollAdjustmentDetail::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['is_next_payroll' => '0', 'is_mark_paid' => 0, 'is_onetime_payment' => 0, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);

                CustomField::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['is_next_payroll' => '0', 'is_mark_paid' => 0, 'is_onetime_payment' => 0, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_onetime_payment' => '1',  'one_time_payment_id' => $response->id]);

                $adjDetails = PayrollAdjustmentDetail::selectRaw('payroll_id, user_id, SUM(CASE WHEN payroll_type = "commission" THEN amount ELSE 0 END) as total_commission, SUM(CASE WHEN payroll_type = "overrides" THEN amount ELSE 0 END) as total_overrides')
                    ->when($select_type == 'this_page', function ($q) use ($payroll_id) {
                        $q->where('payroll_id', $payroll_id);
                    })->where(['is_next_payroll' => '0', 'is_mark_paid' => 0, 'is_onetime_payment' => 0, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])
                    ->groupBy('payroll_id', 'user_id')->get();
                foreach ($adjDetails as $adjDetail) {
                    $adjustment = PayrollAdjustment::where(['payroll_id' => $adjDetail->payroll_id, 'user_id' => $adjDetail->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_next_payroll' => '0', 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->first();
                    if ($adjustment) {
                        if ($adjustment->commission_amount == $adjDetail->total_commission && $adjustment->overrides_amount == $adjDetail->total_overrides) {
                            $adjustment->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);
                        }
                    }
                }

                $payroll = Payroll::select('id', 'status')->where(['id' => $payroll_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->first();
                if ($payroll->status == 2) {
                    $this->updateEveree($payroll->id);
                }

                $userCommission = UserCommission::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->pluck('user_id');
                $userOverride = UserOverrides::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->pluck('user_id');
                $userClawback = ClawbackSettlement::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->pluck('user_id');
                $adjustmentDetail = PayrollAdjustmentDetail::when($select_type == 'this_page', function ($q) use ($payroll_id) {
                    $q->where('payroll_id', $payroll_id);
                })->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->pluck('user_id');

                $userIds = array_unique(array_merge($userCommission->toArray(), $userOverride->toArray(), $userClawback->toArray(), $adjustmentDetail->toArray()));
                if (count($userIds) != 0) {
                    foreach ($userIds as $userId) {
                        $commission = UserCommission::where(['payroll_id' => $payroll_id, 'user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_onetime_payment' => '0', 'is_next_payroll' => '0', 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->first();
                        $override = UserOverrides::where(['payroll_id' => $payroll_id, 'user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_onetime_payment' => '0', 'is_next_payroll' => '0', 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->first();
                        $clawback = ClawbackSettlement::where(['payroll_id' => $payroll_id, 'user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_onetime_payment' => '0', 'is_next_payroll' => '0', 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->first();
                        $adjustment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll_id, 'user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_onetime_payment' => '0', 'is_mark_paid' => 0, 'is_next_payroll' => '0', 'is_onetime_payment' => 0])->first();
                        $requestAndApproval = ApprovalsAndRequest::where(['payroll_id' => $payroll_id, 'user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_onetime_payment' => '0', 'is_mark_paid' => 0, 'is_next_payroll' => '0', 'is_onetime_payment' => 0])->first();

                        if (! $commission && ! $override && ! $clawback && ! $adjustment && ! $requestAndApproval) {
                            Payroll::where(['id' => $payroll_id, 'user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '1', 'one_time_payment_id' => $response->id]);
                        }
                    }
                }

                DB::commit();
                // return response()->json([
                //     'ApiName' => 'Mark as paid',
                //     'status'  => true,
                //     'message' => 'Mark as paid successfully.'
                // ]);
            } catch (Exception $err) {
                DB::rollBack();
                // return response()->json([
                //     'ApiName' => 'Mark as paid',
                //     'status'  => true,
                //     'message' => $err->getMessage()
                // ], 400);
            }
        } else {
            DB::beginTransaction();
            try {
                $payroll = Payroll::where('id', $payroll_id)->where(['is_mark_paid' => 0, 'is_onetime_payment' => 0])->first();
                if ($payroll) {
                    // Update the record
                    $payroll->is_onetime_payment = 1;
                    $payroll->one_time_payment_id = $response->id;
                    $payroll->save();
                    // $queries = DB::getQueryLog();
                    // dd($queries);
                    // dd($payroll);
                    UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);

                    UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);

                    ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);

                    ApprovalsAndRequest::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => 'Paid', 'one_time_payment_id' => $response->id]);

                    PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);

                    PayrollAdjustment::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);

                    PayrollDeductions::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'status' => '3', 'one_time_payment_id' => $response->id]);

                    CustomField::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->update(['is_onetime_payment' => '1', 'one_time_payment_id' => $response->id]);

                    if ($payroll->status == 2) {
                        $this->updateEveree($payroll->id);
                    }

                    if ($payroll && $request->pay_period_from && $request->pay_period_to && $request->pay_frequency && $untracked && $CrmData && $response) {
                        $this->modelsLockingForOnetimepayment($payroll, $request->pay_period_from, $request->pay_period_to, $request->pay_frequency, $untracked, $CrmData, $response);
                    }
                    $message = 'One time payment successfully.';
                    DB::commit();
                } else {
                    $message = 'payroll not found.';
                }
            } catch (Exception $err) {
                // Handle any exceptions that occur within the transaction
                $message = $err->getMessage();
                DB::rollBack();
            }

            // return response()->json([
            //     'ApiName' => 'One time payment pay now',
            //     'status'  => true,
            //     'message' => $message
            // ]);
        }
    }

    private function modelsLockingForSingleOnetimepayment($data, $start_date, $end_date, $pay_frequency, $untracked, $CrmData, $onetimepaymentData, $singleRecordId)
    {
        // Define amount column mapping for filtering zero amounts
        $modelAmountColumns = [
            PayrollAdjustment::class => 'commission_amount',
            PayrollAdjustmentDetail::class => 'amount',
            UserCommission::class => 'amount',
            UserOverrides::class => 'amount',
            ClawbackSettlement::class => 'clawback_amount',
            PayrollDeductions::class => 'total',
            PayrollHourlySalary::class => 'total',
            PayrollOvertime::class => 'total',
        ];

        $modelToLocak = [
            PayrollAdjustment::class => PayrollAdjustmentLock::class,
            PayrollAdjustmentDetail::class => PayrollAdjustmentDetailLock::class,
            // UserReconciliationCommission::class => UserReconciliationCommissionLock::class, //paid
            // ApprovalsAndRequest::class => ApprovalsAndRequest::class, // paid
            UserCommission::class => UserCommissionLock::class,
            UserOverrides::class => UserOverridesLock::class,
            ClawbackSettlement::class => ClawbackSettlementLock::class,
            PayrollDeductions::class => PayrollDeductionLock::class,
            PayrollHourlySalary::class => PayrollHourlySalaryLock::class,
            PayrollOvertime::class => PayrollOvertimeLock::class,

        ];
        foreach ($modelToLocak as $model => $modelLock) {
            try {
                $amountColumn = $modelAmountColumns[$model];
                
                $addToLock = $model::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })
                    ->where([
                        'id' => $singleRecordId,
                        'user_id' => $data->user_id,
                        // 'payroll_id' => $data->id,
                        'payroll_id' => ($data->id != 0) ? $data->id : 0,
                        'status' => 3,
                        'is_onetime_payment' => 1,
                    ])
                    // Only copy non-zero amount records
                    ->where(function($q) use ($amountColumn) {
                        $q->whereNotNull($amountColumn)->where($amountColumn, '!=', 0);
                    });

                if ($modelLock == UserOverridesLock::class) {
                    \Log::info($addToLock->toSql(), $addToLock->getBindings());
                }

                $addToLock = $addToLock->get();

                $addToLock->each(function ($value) use ($modelLock, $data) {
                    try {
                        $existingRecord = $modelLock::where('id', $value['id'])->first();
                        if (! $existingRecord) {
                            $modelLock::updateOrCreate(
                                ['id' => $value['id'], 'payroll_id' => $data->id],
                                $value->toArray()
                            );
                        }
                    } catch (\Exception $e) {
                        // Handle the error for individual record processing
                        \Log::error("Error updating/creating record for ID {$value['id']} on model {$modelLock}: ".$e->getMessage());
                        // Optionally, rethrow or continue with the loop depending on your needs
                    }
                });
            } catch (\Exception $e) {
                // Handle the error for the entire model processing
                \Log::error("Error processing model {$model}: ".$e->getMessage());
                // Optionally, rethrow the exception or continue with the next model
            }
        }
        $this->createPayrollHistoryForSingleOTP($data, $pay_frequency, $start_date, $end_date, $untracked, $CrmData, $onetimepaymentData);

    }

    private function modelsLockingForOnetimepayment($data, $start_date, $end_date, $pay_frequency, $untracked, $CrmData, $onetimepaymentData)
    {
        // Define amount column mapping for filtering zero amounts
        $modelAmountColumns = [
            PayrollAdjustment::class => 'commission_amount',
            PayrollAdjustmentDetail::class => 'amount',
            UserCommission::class => 'amount',
            UserOverrides::class => 'amount',
            ClawbackSettlement::class => 'clawback_amount',
            PayrollDeductions::class => 'total',
            PayrollHourlySalary::class => 'total',
            PayrollOvertime::class => 'total',
        ];

        $modelToLocak = [
            PayrollAdjustment::class => PayrollAdjustmentLock::class,
            PayrollAdjustmentDetail::class => PayrollAdjustmentDetailLock::class,
            // UserReconciliationCommission::class => UserReconciliationCommissionLock::class, //paid
            // ApprovalsAndRequest::class => ApprovalsAndRequest::class, // paid
            UserCommission::class => UserCommissionLock::class,
            UserOverrides::class => UserOverridesLock::class,
            ClawbackSettlement::class => ClawbackSettlementLock::class,
            PayrollDeductions::class => PayrollDeductionLock::class,
            PayrollHourlySalary::class => PayrollHourlySalaryLock::class,
            PayrollOvertime::class => PayrollOvertimeLock::class,

        ];
        foreach ($modelToLocak as $model => $modelLock) {
            try {
                $amountColumn = $modelAmountColumns[$model];
                
                $addToLock = $model::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })
                    ->where([
                        'user_id' => $data->user_id,
                        'payroll_id' => $data->id,
                        'status' => 3,
                        'is_onetime_payment' => 1,
                    ])
                    // Only copy non-zero amount records
                    ->where(function($q) use ($amountColumn) {
                        $q->whereNotNull($amountColumn)->where($amountColumn, '!=', 0);
                    })
                    ->get();

                $addToLock->each(function ($value) use ($modelLock, $data) {
                    try {
                        $existingRecord = $modelLock::where('id', $value['id'])->first();
                        if (! $existingRecord) {
                            $modelLock::updateOrCreate(
                                ['id' => $value['id'], 'payroll_id' => $data->id],
                                $value->toArray()
                            );
                        }
                    } catch (\Exception $e) {
                        // Handle the error for individual record processing
                        \Log::error("Error updating/creating record for ID {$value['id']} on model {$modelLock}: ".$e->getMessage());
                        // Optionally, rethrow or continue with the loop depending on your needs
                    }
                });
            } catch (\Exception $e) {
                // Handle the error for the entire model processing
                \Log::error("Error processing model {$model}: ".$e->getMessage());
                // Optionally, rethrow the exception or continue with the next model
            }
        }
        $this->createPayrollHistory($data, $pay_frequency, $start_date, $end_date, $untracked, $CrmData, $onetimepaymentData);

    }

    private function createPayrollHistory($data, $pay_frequency, $start_date, $end_date, $untracked, $CrmData, $onetimepaymentData)
    {
        if (! empty($CrmData) && $data->is_mark_paid != 1 && $data->is_onetime_payment != 1 && $data->net_pay > 0 && $data->status != 6 && $data->status != 7) {
            $enableEVE = 1;
            $untracked = $untracked; // update payable in everee
            $pay_type = 'Bank';
        } elseif ($CrmData && (empty($data['usersdata']['everee_workerId']) || $data['usersdata']['everee_workerId'] == '')) {
            $enableEVE = 2;
            $pay_type = 'Bank';
        } else {
            $enableEVE = 0;
            $pay_type = 'Manualy';
        }
        try {
            // Check if the conditions are met to proceed
            if (isset($untracked) || $enableEVE == 0 || $enableEVE == 2) {
                // Prepare the data to be inserted into PayrollHistory
                $createdata = [
                    'payroll_id' => $data->id,
                    'user_id' => $data->user_id,
                    'position_id' => $data->position_id,
                    'everee_status' => $enableEVE,
                    'commission' => $data->commission,
                    'override' => $data->override,
                    'reimbursement' => $data->reimbursement,
                    'clawback' => $data->clawback,
                    'deduction' => $data->deduction,
                    'adjustment' => $data->adjustment,
                    'reconciliation' => $data->reconciliation,
                    'hourly_salary' => $data->hourly_salary,
                    'overtime' => $data->overtime,
                    'net_pay' => $data->net_pay,
                    'pay_period_from' => $data->pay_period_from,
                    'pay_period_to' => $data->pay_period_to,
                    'status' => '3',
                    'custom_payment' => $data->custom_payment,
                    'pay_type' => $pay_type,
                    'pay_frequency_date' => $data->created_at,
                    'everee_external_id' => $data->everee_external_id,
                    'everee_payment_status' => $enableEVE,
                    'everee_paymentId' => isset($untracked['success']['everee_payment_id']) ? $untracked['success']['everee_payment_id'] : null,
                    'everee_payment_requestId' => isset($untracked['success']['paymentId']) ? $untracked['success']['paymentId'] : null,
                    'everee_json_response' => isset($untracked) ? json_encode($untracked) : null,
                    'is_onetime_payment' => 1,
                    'one_time_payment_id' => $onetimepaymentData ? $onetimepaymentData->id : null,
                ];

                // Check if the payroll history already exists
                $check = PayrollHistory::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })->where('user_id', $data->user_id)
                    ->where('payroll_id', $data->id)
                    ->count();

                // If no matching payroll history found, create a new one
                if ($check == 0) {
                    // Create the payroll history record
                    $payrollHistory = PayrollHistory::create($createdata);
                    // If the record was created successfully
                    if ($payrollHistory) {
                        // Check for CRM data and net pay condition for further logic
                        if ($CrmData && $data->net_pay > 0) {
                            $open_status_from_bank = 1;
                        } else {
                            $open_status_from_bank = 0;
                        }
                        $payroll_id = $data->id;
                        $data = json_encode($data);
                        $onetimepaymentData = json_encode($onetimepaymentData);
                        // Dispatch the job to handle the one-time payment logic
                        oneTimePaymentJob::dispatch($data, $start_date, $end_date, $pay_frequency, $open_status_from_bank, $onetimepaymentData);

                        // Log the payroll history for debugging (optional)

                        // If payroll history was created, delete the original payroll with status '2'
                        Payroll::where(['id' => $payroll_id, 'is_onetime_payment' => '1'])->delete();
                    }
                }
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database-related errors, such as query failures
            \Log::error('Database query exception: '.$e->getMessage());

            return response()->json(['error' => 'Database error occurred while processing payroll.'], 500);
        } catch (\Exception $e) {
            // Handle any other generic exceptions
            \Log::error('An error occurred: '.$e->getMessage());

            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }

    }

    private function createPayrollHistoryForSingleOTP($data, $pay_frequency, $start_date, $end_date, $untracked, $CrmData, $onetimepaymentData)
    {
        if (! empty($CrmData) && $data->is_mark_paid != 1 && $data->is_onetime_payment != 1 && $data->net_pay > 0 && $data->status != 6 && $data->status != 7) {
            $enableEVE = 1;
            $untracked = $untracked; // update payable in everee
            $pay_type = 'Bank';
        } elseif ($CrmData && (empty($data['usersdata']['everee_workerId']) || $data['usersdata']['everee_workerId'] == '')) {
            $enableEVE = 2;
            $pay_type = 'Bank';
        } else {
            $enableEVE = 0;
            $pay_type = 'Manualy';
        }
        try {
            // Check if the conditions are met to proceed
            if (isset($untracked) || $enableEVE == 0 || $enableEVE == 2) {
                // Prepare the data to be inserted into PayrollHistory
                $createdata = [
                    'payroll_id' => $data->id,
                    'user_id' => $data->user_id,
                    'position_id' => $data->position_id,
                    'everee_status' => $enableEVE,
                    'commission' => $data->commission,
                    'override' => $data->override,
                    'reimbursement' => $data->reimbursement,
                    'clawback' => $data->clawback,
                    'deduction' => $data->deduction,
                    'adjustment' => $data->adjustment,
                    'reconciliation' => $data->reconciliation,
                    'hourly_salary' => $data->hourly_salary,
                    'overtime' => $data->overtime,
                    'net_pay' => $data->net_pay,
                    'pay_period_from' => $data->pay_period_from,
                    'pay_period_to' => $data->pay_period_to,
                    'status' => '3',
                    'custom_payment' => $data->custom_payment,
                    'pay_type' => $pay_type,
                    'pay_frequency_date' => $data->created_at,
                    'everee_external_id' => $data->everee_external_id,
                    'everee_payment_status' => $enableEVE,
                    'everee_paymentId' => isset($untracked['success']['everee_payment_id']) ? $untracked['success']['everee_payment_id'] : null,
                    'everee_payment_requestId' => isset($untracked['success']['paymentId']) ? $untracked['success']['paymentId'] : null,
                    'everee_json_response' => isset($untracked) ? json_encode($untracked) : null,
                    'is_onetime_payment' => 1,
                    'one_time_payment_id' => $onetimepaymentData ? $onetimepaymentData->id : null,
                ];

                $check = $this->countonetimepaymentstatus($data, $pay_frequency, $start_date, $end_date);

                // If no matching payroll history found, create a new one
                if ($check < 1) {
                    // Create the payroll history record
                    $payrollHistory = PayrollHistory::create($createdata);
                    // If the record was created successfully
                    if ($payrollHistory) {
                        // Check for CRM data and net pay condition for further logic
                        if ($CrmData && $data->net_pay > 0) {
                            $open_status_from_bank = 1;
                        } else {
                            $open_status_from_bank = 0;
                        }

                        // Dispatch the job to handle the one-time payment logic
                        // oneTimePaymentJob::dispatch($data, $start_date, $end_date, $pay_frequency, $open_status_from_bank);

                        // Log the payroll history for debugging (optional)

                        // If payroll history was created, delete the original payroll with status '2'
                        Payroll::where(['id' => $data->id])->delete();
                    }
                }
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database-related errors, such as query failures
            \Log::error('Database query exception: '.$e->getMessage());

            return response()->json(['error' => 'Database error occurred while processing payroll.'], 500);
        } catch (\Exception $e) {
            // Handle any other generic exceptions
            \Log::error('An error occurred: '.$e->getMessage());

            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }

    }

    private function countonetimepaymentstatus($data, $pay_frequency, $start_date, $end_date)
    {
        $pay_period_from = $start_date;
        $pay_period_to = $end_date;
        $commission = UserCommission::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();
        $overrides = UserOverrides::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();
        $clawbackSettlement = ClawbackSettlement::where(['user_id' => $data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'clawback_type' => 'next payroll'])->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();

        $approvalsAndRequest = ApprovalsAndRequest::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', 'Accept')->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();
        $payrollAdjustment = PayrollAdjustment::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();

        $closerReconciliation = UserReconciliationWithholding::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('closer_id', $data->user_id)->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();
        $setterReconciliation = UserReconciliationWithholding::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('setter_id', $data->user_id)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->where('status', '!=', 3)->count();
        // $reconciliationsAdjustement = ReconciliationsAdjustement::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id',$userID)->count();
        $customField = CustomField::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();

        $hourlySalary = PayrollHourlySalary::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->user_id])->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();
        $overtime = PayrollOvertime::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->user_id])->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();

        $payrolldeduction = PayrollDeductions::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->user_id])->where('status', '!=', 3)->where('is_onetime_payment', '!=', 1)->where('payroll_id', $data->id)->count();

        /* recon payroll zero data */
        // $reconFinalizeData = ReconciliationFinalizeHistory::where(['user_id'=> $userID, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->where('payroll_id', $data->id)->count();
        // $check = ($commission+$overrides+$clawbackSettlement+$approvalsAndRequest+$payrollAdjustment+$reconFinalizeData+$closerReconciliation+$setterReconciliation+$customField+$hourlySalary+$overtime);

        $check = ($commission + $overrides + $clawbackSettlement + $approvalsAndRequest + $payrollAdjustment + $closerReconciliation + $setterReconciliation + $customField + $hourlySalary + $overtime + $payrolldeduction);

        return $check;
    }

    public function payrollMarkAsPaid(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payrollId' => 'required|array',
            'select_type' => 'required|in:this_page,all_pages',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
            'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        if (Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($request) {
            $query->where('pay_period_from', $request->pay_period_from)
                ->where('pay_period_to', $request->pay_period_to);
        })
            ->where(['status' => '2'])->first()) {
            return response()->json([
                'ApiName' => 'payrollMarkAsPaid',
                'status' => false,
                'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
            ], 400);
        }

        $data = [];
        $payrollId = $request->payrollId;
        $select_type = $request->select_type;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        if ($request->type == 'pid') {
            DB::beginTransaction();
            try {
                UserCommission::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_next_payroll' => '0', 'is_stop_payroll' => 0])->update(['is_mark_paid' => '1']);
                UserOverrides::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_next_payroll' => '0', 'is_stop_payroll' => 0])->update(['is_mark_paid' => '1']);
                ClawbackSettlement::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_next_payroll' => '0', 'is_stop_payroll' => 0])->update(['is_mark_paid' => '1']);
                PayrollAdjustmentDetail::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_next_payroll' => '0'])->update(['is_mark_paid' => '1']);

                $adjDetails = PayrollAdjustmentDetail::selectRaw('payroll_id, user_id, SUM(CASE WHEN payroll_type = "commission" THEN amount ELSE 0 END) as total_commission, SUM(CASE WHEN payroll_type = "overrides" THEN amount ELSE 0 END) as total_overrides')
                    ->when($select_type == 'this_page', function ($q) {
                        $q->whereIn('pid', request()->input('payrollId'));
                    })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_next_payroll' => '0', 'is_mark_paid' => '1'])
                    ->groupBy('payroll_id', 'user_id')->get();
                foreach ($adjDetails as $adjDetail) {
                    $adjustment = PayrollAdjustment::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['payroll_id' => $adjDetail->payroll_id, 'user_id' => $adjDetail->user_id])->first();
                    if ($adjustment) {
                        if ($adjustment->commission_amount == $adjDetail->total_commission && $adjustment->overrides_amount == $adjDetail->total_overrides) {
                            $adjustment->update(['is_mark_paid' => '1']);
                        }
                    }
                }

                // if ($payroll->status == 2) {
                //     $this->updateEveree($payroll->id);
                // }

                $userCommission = UserCommission::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_stop_payroll' => 0])->pluck('user_id');
                $userOverride = UserOverrides::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_stop_payroll' => 0])->pluck('user_id');
                $userClawback = ClawbackSettlement::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['is_stop_payroll' => 0])->pluck('user_id');
                $adjustmentDetail = PayrollAdjustmentDetail::when($select_type == 'this_page', function ($q) {
                    $q->whereIn('pid', request()->input('payrollId'));
                })
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->pluck('user_id');

                $userIds = array_unique(array_merge($userCommission->toArray(), $userOverride->toArray(), $userClawback->toArray(), $adjustmentDetail->toArray()));

                if (count($userIds) != 0) {
                    foreach ($userIds as $userId) {
                        $commission = UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                        })->where(['is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $override = UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                        })->where(['is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $clawback = ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                        })->where(['is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $adjustment = PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                        })->where(['is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $requestAndApproval = ApprovalsAndRequest::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                        })->where(['is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();

                        if (! $commission && ! $override && ! $clawback && ! $adjustment && ! $requestAndApproval) {
                            Payroll::where(['user_id' => $userId])
                                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                        ->whereColumn('pay_period_from', 'pay_period_to');
                                }, function ($query) use ($request) {
                                    $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                                })
                                ->update(['is_mark_paid' => '1']);
                        }
                    }
                }

                DB::commit();

                return response()->json([
                    'ApiName' => 'Mark as paid',
                    'status' => true,
                    'message' => 'Mark as paid successfully.',
                ]);
            } catch (Exception $err) {
                DB::rollBack();

                return response()->json([
                    'ApiName' => 'Mark as paid',
                    'status' => false,
                    'message' => $err->getMessage(),
                ], 400);
            }
        } else {
            if (count($payrollId) > 0 && $select_type == 'this_page') {
                $data = Payroll::whereIn('id', $payrollId)->where(['is_mark_paid' => 0])->get();
            } elseif (! empty($pay_period_from) && ! empty($pay_period_to) && $select_type == 'all_pages') {
                $data = Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['is_mark_paid' => 0])->get();
            }

            DB::beginTransaction();
            try {
                if (count($data) > 0) {
                    foreach ($data as $payroll) {
                        Payroll::where(['id' => $payroll->id])->update(['is_mark_paid' => '1']);
                        UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        ApprovalsAndRequest::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        PayrollAdjustment::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        PayrollDeductions::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        PayrollHourlySalary::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        PayrollOvertime::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => '1']);

                        ReconciliationFinalizeHistory::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => 1]);

                        ReconCommissionHistory::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => 1]);

                        ReconOverrideHistory::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->update(['is_mark_paid' => 1]);

                        if ($payroll->status == 2) {
                            $this->updateEveree($payroll->id);
                        }
                    }
                }
                $message = 'Mark as paid successfully.';
                DB::commit();
            } catch (Exception $err) {
                // Handle any exceptions that occur within the transaction
                $message = $err->getMessage();
                DB::rollBack();
            }

            return response()->json([
                'ApiName' => 'Mark as paid',
                'status' => true,
                'message' => $message,
            ]);
        }
    }

    // undo-mark-as-paid
    public function payroll_mark_as_unpaid(Request $request): JsonResponse
    {
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'payrollId' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            if (Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where('pay_period_from', $request->pay_period_from)
                    ->where('pay_period_to', $request->pay_period_to);
            })
                ->where(['status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => 'payrollMarkAsPaid',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            DB::beginTransaction();
            try {
                $pay_period_from = $request->pay_period_from;
                $pay_period_to = $request->pay_period_to;

                UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId, 'is_next_payroll' => '0', 'is_stop_payroll' => 0])->update(['is_mark_paid' => '0']);

                UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId, 'is_next_payroll' => '0', 'is_stop_payroll' => 0])->update(['is_mark_paid' => '0']);

                ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId, 'is_next_payroll' => '0', 'is_stop_payroll' => 0])->update(['is_mark_paid' => '0']);

                PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId, 'is_next_payroll' => '0'])->update(['is_mark_paid' => '0']);

                $adjDetails = PayrollAdjustmentDetail::selectRaw('payroll_id, user_id, SUM(CASE WHEN payroll_type = "commission" THEN amount ELSE 0 END) as total_commission, SUM(CASE WHEN payroll_type = "overrides" THEN amount ELSE 0 END) as total_overrides')
                    ->where(['pid' => $request->payrollId, 'is_next_payroll' => '0', 'is_mark_paid' => '0'])
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                    ->groupBy('payroll_id', 'user_id')->get();
                foreach ($adjDetails as $adjDetail) {
                    $adjustment = PayrollAdjustment::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['payroll_id' => $adjDetail->payroll_id, 'user_id' => $adjDetail->user_id])->first();
                    if ($adjustment) {
                        if ($adjustment->commission_amount == $adjDetail->total_commission && $adjustment->overrides_amount == $adjDetail->total_overrides) {
                            $adjustment->update(['is_mark_paid' => '0']);
                        }
                    }
                }

                // if ($payroll->status == 2) {
                //     $this->updateEveree($payroll->id);
                // }

                $userCommission = UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId, 'is_stop_payroll' => 0])->pluck('user_id');

                $userOverride = UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId, 'is_stop_payroll' => 0])->pluck('user_id');

                $userClawback = ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId, 'is_stop_payroll' => 0])->pluck('user_id');

                $adjustmentDetail = PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where(['pid' => $request->payrollId])->pluck('user_id');

                $userIds = array_unique(array_merge($userCommission->toArray(), $userOverride->toArray(), $userClawback->toArray(), $adjustmentDetail->toArray()));
                if (count($userIds) != 0) {
                    Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->whereIn('user_id', $userIds)->update(['is_mark_paid' => '0']);
                }

                DB::commit();

                return response()->json([
                    'ApiName' => 'Mark as Unpaid',
                    'status' => true,
                    'message' => 'Mark as Unpaid successfully.',
                ]);
            } catch (Exception $err) {
                DB::rollBack();

                return response()->json([
                    'ApiName' => 'Mark as Unpaid',
                    'status' => true,
                    'message' => $err->getMessage(),
                ], 400);
            }
        } else {
            $payroll_id = $request->payrollId;
            $message = 'Payroll not paid!! ';
            $payroll_data = Payroll::where('id', $payroll_id)->first();
            $pay_period_from = $payroll_data->pay_period_from;
            $pay_period_to = $payroll_data->pay_period_to;

            if (Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where('pay_period_from', $request->pay_period_from)
                    ->where('pay_period_to', $request->pay_period_to);
            })
                ->where(['status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => 'payrollMarkAsPaid',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            DB::beginTransaction();
            try {
                if (! empty($payroll_data) && $payroll_data->status != 3 && $payroll_data->is_mark_paid == 1) {
                    $payroll_update = Payroll::where(['id' => $payroll_id])->update(['is_mark_paid' => '0']);
                    if ($payroll_update) {
                        $message = 'Payroll undo mark-as-paid Successfully ';
                    }

                    $commision = UserCommission::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $overrides = UserOverrides::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $settlement = ClawbackSettlement::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $approvalRequest = ApprovalsAndRequest::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $payrollAdjustmentDetails = PayrollAdjustmentDetail::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $payrollDeductions = PayrollDeductions::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $payrollHourlySalary = PayrollHourlySalary::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $payrollOvertime = PayrollOvertime::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $recon = ReconciliationFinalizeHistory::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);
                    $recon = ReconCommissionHistory::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);
                    $recon = ReconOverrideHistory::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id])->update(['is_mark_paid' => '0']);

                    $this->updateEveree($payroll_id);
                }
                DB::commit();
            } catch (Exception $err) {
                // Handle any exceptions that occur within the transaction
                $message = $err->getMessage();
                DB::rollBack();
            }

            return response()->json([
                'ApiName' => 'Mark As Unpaid',
                'status' => true,
                'message' => $message,
            ], 200);
        }
    }

    public function payrollMoveToNextPayroll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payrollId' => 'required|array',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $payrollId = $request->payrollId;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            if (Payroll::where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => 'payrollMarkAsPaid',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            $pay_period_from = $request->pay_period_from;
            $pay_period_to = $request->pay_period_to;

            DB::beginTransaction();
            try {
                UserCommission::whereIn('pid', request()->input('payrollId'))->where(['is_mark_paid' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0])->update(['is_next_payroll' => '1']);
                UserOverrides::whereIn('pid', request()->input('payrollId'))->where(['is_mark_paid' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0])->update(['is_next_payroll' => '1']);
                ClawbackSettlement::whereIn('pid', request()->input('payrollId'))->where(['is_mark_paid' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0])->update(['is_next_payroll' => '1']);
                PayrollAdjustmentDetail::whereIn('pid', request()->input('payrollId'))->where(['is_mark_paid' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_next_payroll' => '1']);

                $adjDetails = PayrollAdjustmentDetail::selectRaw('payroll_id, user_id, SUM(CASE WHEN payroll_type = "commission" THEN amount ELSE 0 END) as total_commission, SUM(CASE WHEN payroll_type = "overrides" THEN amount ELSE 0 END) as total_overrides')
                    ->whereIn('pid', request()->input('payrollId'))->where(['is_mark_paid' => '0', 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_next_payroll' => '1'])
                    ->groupBy('payroll_id', 'user_id')->get();
                foreach ($adjDetails as $adjDetail) {
                    $adjustment = PayrollAdjustment::where(['payroll_id' => $adjDetail->payroll_id, 'user_id' => $adjDetail->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
                    if ($adjustment) {
                        if ($adjustment->commission_amount == $adjDetail->total_commission && $adjustment->overrides_amount == $adjDetail->total_overrides) {
                            $adjustment->update(['is_next_payroll' => '1']);
                        }
                    }
                }

                // if ($payroll->status == 2) {
                //     $this->updateEveree($payroll->id);
                // }

                $userCommission = UserCommission::whereIn('pid', request()->input('payrollId'))->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0])->pluck('user_id');
                $userOverride = UserOverrides::whereIn('pid', request()->input('payrollId'))->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0])->pluck('user_id');
                $userClawback = ClawbackSettlement::whereIn('pid', request()->input('payrollId'))->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0])->pluck('user_id');
                $adjustmentDetail = PayrollAdjustmentDetail::whereIn('pid', request()->input('payrollId'))->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->pluck('user_id');

                $userIds = array_unique(array_merge($userCommission->toArray(), $userOverride->toArray(), $userClawback->toArray(), $adjustmentDetail->toArray()));
                $moved_by = auth()->user()->id;
                if (count($userIds) != 0) {
                    foreach ($userIds as $userId) {
                        $commission = UserCommission::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $override = UserOverrides::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $clawback = ClawbackSettlement::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $adjustment = PayrollAdjustmentDetail::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();
                        $requestAndApproval = ApprovalsAndRequest::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->first();

                        $ref_id = 0;
                        if (! $commission && ! $override && ! $clawback && ! $adjustment && ! $requestAndApproval) {
                            $payroll = Payroll::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
                            $value = $payroll->id;

                            $payroll_shift_histrorie_data = PayrollShiftHistorie::create([
                                'payroll_id' => $payroll->id,
                                'moved_by' => $moved_by,
                                'pay_period_from' => $payroll->pay_period_from,
                                'pay_period_to' => $payroll->pay_period_to,
                                'new_pay_period_from' => $start_date,
                                'new_pay_period_to' => $end_date,
                            ]);

                            if ($payroll_shift_histrorie_data) {
                                $payrollId_arr[$value] = 'success';
                                $payrollUpdate = Payroll::where(['id' => $value])->first();
                                if (! empty($payrollUpdate)) {
                                    $check_ref_id = PayrollCommon::where('id', $payrollUpdate->ref_id)->count();
                                    if ($check_ref_id > 0) {
                                        $ref_id = $payrollUpdate->ref_id;
                                        PayrollCommon::where('id', $payrollUpdate->ref_id)->update(['payroll_modified_date' => date('Y-m-d')]);
                                    } else {
                                        $PayrollCommon = PayrollCommon::create(['payroll_modified_date' => date('Y-m-d')]);
                                        $ref_id = $PayrollCommon->id;
                                    }

                                    $payrollUpdate->is_next_payroll = 1;
                                    $payrollUpdate->everee_external_id = null;
                                    $payrollUpdate->ref_id = $ref_id;
                                    $payrollUpdate->save();
                                }
                                $payroll_id = $value;
                            }

                            $UserCommission = UserCommission::where(['payroll_id' => $payroll_id, 'user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
                            foreach ($UserCommission as $userComm) {
                                $updateUserCommission = UserCommission::where('id', $userComm->id)->first();
                                $updateUserCommission->is_next_payroll = 1;
                                $this->updateReferences([$userComm->id], 'commision', 1);
                                $updateUserCommission->save();
                            }

                            $UserOverrides = UserOverrides::where(['payroll_id' => $payroll_id, 'user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
                            foreach ($UserOverrides as $userOver) {
                                $updateUserOverrides = UserOverrides::where('id', $userOver->id)->first();
                                $updateUserOverrides->is_next_payroll = 1;
                                $this->updateReferences([$userOver->id], 'override', 1);
                                $updateUserOverrides->save();
                            }

                            // ClawbackSettlement move to next payroll
                            $ClawbackSettlement = ClawbackSettlement::where(['payroll_id' => $payroll_id, 'user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
                            foreach ($ClawbackSettlement as $clawSettle) {
                                $updateClawbackSettlement = ClawbackSettlement::where('id', $clawSettle->id)->first();
                                $updateClawbackSettlement->is_next_payroll = 1;
                                $this->updateReferences([$clawSettle->id], 'clawback', 1);
                                $updateClawbackSettlement->save();
                            }

                            // PayrollAdjustment move to next payroll
                            $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payroll_id, 'user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
                            if ($PayrollAdjustment) {
                                foreach ($PayrollAdjustment as $payrollAdjust) {
                                    $updatePayrollAdjustment = PayrollAdjustment::where('id', $payrollAdjust->id)->first();
                                    $updatePayrollAdjustment->is_next_payroll = 1;
                                    $this->updateReferences([$payrollAdjust->id], 'adjustment', 1);
                                    $updatePayrollAdjustment->save();
                                }
                            }

                            // PayrollAdjustmentDetails move to next payroll
                            $PayrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll_id, 'user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
                            if ($PayrollAdjustmentDetail) {
                                foreach ($PayrollAdjustmentDetail as $payrollAdjustDetails) {
                                    $updatePayrollAdjustmentDetail = PayrollAdjustmentDetail::where('id', $payrollAdjustDetails->id)->first();
                                    $updatePayrollAdjustmentDetail->is_next_payroll = 1;
                                    $this->updateReferences([$payrollAdjustDetails->id], 'adjustment', 1);
                                    $updatePayrollAdjustmentDetail->save();
                                }
                            }

                            // if ($payroll->status == 2) {
                            //     $this->updateEveree($payroll->id);
                            // }
                        } else {
                            $commission = UserCommission::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '1'])->get();
                            $override = UserOverrides::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '1'])->get();
                            $clawback = ClawbackSettlement::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_stop_payroll' => 0, 'is_mark_paid' => '0', 'is_next_payroll' => '1'])->get();
                            $adjustment = PayrollAdjustmentDetail::where(['user_id' => $userId, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '0', 'is_next_payroll' => '1'])->get();
                            if ($commission) {
                                $this->updateReferences($commission->pluck('id'), 'commision', '1');
                            }

                            if ($override) {
                                $this->updateReferences($override->pluck('id'), 'override', '1');
                            }

                            if ($clawback) {
                                $this->updateReferences($clawback->pluck('id'), 'clawback', '1');
                            }

                            if ($adjustment) {
                                $this->updateReferences($adjustment->pluck('id'), 'adjustment', '1');
                            }
                        }
                    }
                }

                DB::commit();

                return response()->json([
                    'ApiName' => 'Mark as paid',
                    'status' => true,
                    'message' => 'Mark as paid successfully.',
                ]);
            } catch (Exception $err) {
                DB::rollBack();

                return response()->json([
                    'ApiName' => 'Mark as paid',
                    'status' => true,
                    'message' => $err->getMessage(),
                ], 400);
            }
        } else {
            $validator = Validator::make($request->all(), [
                'payrollId' => 'required|array',
                'start_date' => 'required',
                'end_date' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            if (count($payrollId) > 0) {
                $moved_by = auth()->user()->id;
                $payrollId_arr = [];

                foreach ($payrollId as $key => $value) {
                    // $data = ['status' => '4'];
                    $paydata = Payroll::where('id', $value)->first();

                    if (Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['status' => '2'])->first()) {
                        return response()->json([
                            'ApiName' => 'payrollMarkAsPaid',
                            'status' => false,
                            'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                        ], 400);
                    }

                    // storing moved payroll into db for undo moved payroll
                    $payroll_shift_histrorie_data = [
                        'payroll_id' => $paydata->id,
                        'moved_by' => $moved_by,
                        'pay_period_from' => $paydata->pay_period_from,
                        'pay_period_to' => $paydata->pay_period_to,
                        'new_pay_period_from' => $start_date,
                        'new_pay_period_to' => $end_date,
                    ];

                    DB::beginTransaction();
                    $payroll_shift_histrorie_data = PayrollShiftHistorie::create($payroll_shift_histrorie_data);
                    $ref_id = 0;
                    if ($payroll_shift_histrorie_data) {
                        $payrollId_arr[$value] = 'success';
                        $data = [
                            'pay_period_from' => $start_date,
                            'pay_period_to' => $end_date,
                        ];
                        $payrollUpdate = Payroll::where(['id' => $value])->first();
                        if (! empty($payrollUpdate)) {
                            $check_ref_id = PayrollCommon::where('id', $payrollUpdate->ref_id)->count();
                            if ($check_ref_id > 0) {
                                $ref_id = $payrollUpdate->ref_id;
                                PayrollCommon::where('id', $payrollUpdate->ref_id)->update(['payroll_modified_date' => date('Y-m-d')]);
                            } else {
                                $PayrollCommon = PayrollCommon::create(['payroll_modified_date' => date('Y-m-d')]);
                                $ref_id = $PayrollCommon->id;
                            }

                            $payrollUpdate->is_next_payroll = 1;
                            $payrollUpdate->everee_external_id = null;
                            $payrollUpdate->ref_id = $ref_id;
                            $payrollUpdate->save();
                        }
                        $payroll_id = $value; // payroll_id.

                        $UserCommission = UserCommission::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        foreach ($UserCommission as $userComm) {
                            $updateUserCommission = UserCommission::where('id', $userComm->id)->first();
                            $updateUserCommission->is_next_payroll = 1;
                            $updateUserCommission->ref_id = $ref_id;
                            $this->updateReferences([$userComm->id], 'commision', 1);
                            $updateUserCommission->save();
                        }

                        $UserOverrides = UserOverrides::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        foreach ($UserOverrides as $userOver) {
                            $updateUserOverrides = UserOverrides::where('id', $userOver->id)->first();
                            $updateUserOverrides->is_next_payroll = 1;
                            $this->updateReferences([$userOver->id], 'override', 1);
                            $updateUserOverrides->save();
                        }

                        $ApprovalsAndRequest = ApprovalsAndRequest::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->get();
                        foreach ($ApprovalsAndRequest as $appReq) {
                            $updateApprovalsAndRequest = ApprovalsAndRequest::where('id', $appReq->id)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->first();
                            $updateApprovalsAndRequest->is_next_payroll = 1;
                            // $updateApprovalsAndRequest->ref_id = $ref_id;
                            $this->updateReferences([$appReq->id], 'approvalreject', 1);
                            $updateApprovalsAndRequest->save();
                        }

                        $ApprovalsAndRequest = ApprovalsAndRequest::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to, 'adjustment_type_id' => 2])->get();
                        foreach ($ApprovalsAndRequest as $appReq) {
                            $updateApprovalsAndRequest = ApprovalsAndRequest::where('id', $appReq->id)->where('adjustment_type_id', 2)->first();
                            $updateApprovalsAndRequest->is_next_payroll = 1;
                            // $updateApprovalsAndRequest->ref_id = $ref_id;
                            $this->updateReferences([$appReq->id], 'approvalrejectreimbursement', 1);
                            $updateApprovalsAndRequest->save();
                        }

                        // ClawbackSettlement move to next payroll
                        $ClawbackSettlement = ClawbackSettlement::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        foreach ($ClawbackSettlement as $clawSettle) {
                            $updateClawbackSettlement = ClawbackSettlement::where('id', $clawSettle->id)->first();
                            $updateClawbackSettlement->is_next_payroll = 1;
                            $this->updateReferences([$clawSettle->id], 'clawback', 1);
                            $updateClawbackSettlement->save();
                        }

                        // PayrollAdjustment move to next payroll

                        $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        if ($PayrollAdjustment) {
                            foreach ($PayrollAdjustment as $payrollAdjust) {
                                $updatePayrollAdjustment = PayrollAdjustment::where('id', $payrollAdjust->id)->first();
                                $updatePayrollAdjustment->is_next_payroll = 1;
                                $this->updateReferences([$payrollAdjust->id], 'adjustment', 1);
                                $updatePayrollAdjustment->save();
                            }
                        }

                        // PayrollAdjustmentDetails move to next payroll

                        $PayrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        if ($PayrollAdjustmentDetail) {
                            foreach ($PayrollAdjustmentDetail as $payrollAdjustDetails) {
                                $updatePayrollAdjustmentDetail = PayrollAdjustmentDetail::where('id', $payrollAdjustDetails->id)->first();
                                $updatePayrollAdjustmentDetail->is_next_payroll = 1;
                                $this->updateReferences([$payrollAdjustDetails->id], 'adjustment', 1);
                                $updatePayrollAdjustmentDetail->save();
                            }
                        }

                        // PayrollDeductions move to next payroll
                        $payrollDeductions = PayrollDeductions::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        foreach ($payrollDeductions as $userComm) {
                            $updatePayrollDeduction = PayrollDeductions::where('id', $userComm->id)->first();
                            $updatePayrollDeduction->is_next_payroll = 1;
                            $updatePayrollDeduction->ref_id = $ref_id;
                            $this->updateReferences([$userComm->id], 'deduction', 1);
                            $updatePayrollDeduction->save();
                        }

                        // PayrollHourlySalary move to next payroll
                        $payrollHourlySalary = PayrollHourlySalary::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        foreach ($payrollHourlySalary as $payhosal) {
                            $updatepayrollHourlySalary = PayrollHourlySalary::where('id', $payhosal->id)->first();
                            $updatepayrollHourlySalary->is_next_payroll = 1;
                            $updatepayrollHourlySalary->ref_id = $ref_id;
                            $this->updateReferences([$payhosal->id], 'hourlysalary', 1);
                            $updatepayrollHourlySalary->save();
                        }

                        // PayrollOvertime move to next payroll
                        $PayrollOvertime = PayrollOvertime::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        foreach ($PayrollOvertime as $payovertime) {
                            $updatePayrollOvertime = PayrollOvertime::where('id', $payovertime->id)->first();
                            $updatePayrollOvertime->is_next_payroll = 1;
                            $updatePayrollOvertime->ref_id = $ref_id;
                            $this->updateReferences([$payovertime->id], 'overtime', 1);
                            $updatePayrollOvertime->save();
                        }

                        // PayrollCustomField move to next payroll
                        $payrollCustomField = CustomField::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                        foreach ($payrollCustomField as $userComm) {
                            $updatePayrollCustomField = CustomField::where('id', $userComm->id)->first();
                            $updatePayrollCustomField->is_next_payroll = 1;
                            // $updatePayrollCustomField->ref_id = $ref_id;
                            $this->updateReferences([$userComm->id], 'customField', 1);
                            $updatePayrollCustomField->save();
                        }

                        if ($paydata->status == 2) {
                            $this->updateEveree($paydata->id);
                        }

                        DB::commit();
                    } else {
                        $payrollId_arr[$value] = 'false';
                        DB::rollBack();
                    }
                }
            }

            return response()->json([
                'ApiName' => 'Move_To_Next_Payroll',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $payrollId_arr,
            ], 200);
        }
    }

    //  undo MoveToNextPayroll
    public function payroll_undo_next_payroll(Request $request): JsonResponse
    {
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'payroll_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            if (Payroll::where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to, 'status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => 'payrollMarkAsPaid',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            $pay_period_from = $request->pay_period_from;
            $pay_period_to = $request->pay_period_to;

            DB::beginTransaction();
            try {
                $userCommission = UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where('pid', request()->input('payroll_id'))->where(['is_stop_payroll' => 0])->pluck('user_id');

                $userOverride = UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where('pid', request()->input('payroll_id'))->where(['is_stop_payroll' => 0])->pluck('user_id');

                $userClawback = ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where('pid', request()->input('payroll_id'))->where(['is_stop_payroll' => 0])->pluck('user_id');

                $adjustmentDetail = PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where('pay_period_from', $request->pay_period_from)
                        ->where('pay_period_to', $request->pay_period_to);
                })
                    ->where('pid', request()->input('payroll_id'))->pluck('user_id');
                $userIds = array_unique(array_merge($userCommission->toArray(), $userOverride->toArray(), $userClawback->toArray(), $adjustmentDetail->toArray()));

                if (count($userIds) != 0) {
                    foreach ($userIds as $userId) {
                        $payroll = Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['user_id' => $userId, 'is_next_payroll' => '1'])->first();
                        if ($payroll) {
                            $history_data = PayrollShiftHistorie::where(['payroll_id' => $payroll->id, 'is_undo_done' => 1])->orderBy('id', 'DESC')->first();

                            if (! empty($history_data)) {
                                if (isset($payroll->ref_id)) {
                                    PayrollCommon::where('id', $payroll->ref_id)->whereNull('orig_payfrom')->delete();
                                }
                                $payrollUpdate_user_id = $payroll->user_id;
                                $payroll->is_next_payroll = 0;
                                $payroll->ref_id = 0;
                                $payroll->save();
                            }
                        }

                        $commission = UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_stop_payroll' => 0, 'is_next_payroll' => '1'])->get();
                        $override = UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_stop_payroll' => 0, 'is_next_payroll' => '1'])->get();
                        $clawback = ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_stop_payroll' => 0, 'is_next_payroll' => '1'])->get();
                        $adjustment = PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($request) {
                            $query->where('pay_period_from', $request->pay_period_from)
                                ->where('pay_period_to', $request->pay_period_to);
                        })
                            ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_next_payroll' => '1'])->get();
                        if ($commission) {
                            $this->updateReferences($commission->pluck('id'), 'commision', '0');
                            UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereColumn('pay_period_from', 'pay_period_to');
                            }, function ($query) use ($request) {
                                $query->where('pay_period_from', $request->pay_period_from)
                                    ->where('pay_period_to', $request->pay_period_to);
                            })
                                ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_next_payroll' => '1'])->update(['is_mark_paid' => '0', 'is_next_payroll' => '0']);
                        }

                        if ($override) {
                            $this->updateReferences($override->pluck('id'), 'override', '0');
                            UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereColumn('pay_period_from', 'pay_period_to');
                            }, function ($query) use ($request) {
                                $query->where('pay_period_from', $request->pay_period_from)
                                    ->where('pay_period_to', $request->pay_period_to);
                            })
                                ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_mark_paid' => '0', 'is_next_payroll' => '1'])->update(['is_mark_paid' => '0', 'is_next_payroll' => '0']);
                        }

                        if ($clawback) {
                            $this->updateReferences($clawback->pluck('id'), 'clawback', '0');
                            ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereColumn('pay_period_from', 'pay_period_to');
                            }, function ($query) use ($request) {
                                $query->where('pay_period_from', $request->pay_period_from)
                                    ->where('pay_period_to', $request->pay_period_to);
                            })
                                ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_mark_paid' => '0', 'is_next_payroll' => '1'])->update(['is_mark_paid' => '0', 'is_next_payroll' => '0']);
                        }

                        if ($adjustment) {
                            $this->updateReferences($adjustment->pluck('id'), 'adjustment', '0');
                            PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                                    ->whereColumn('pay_period_from', 'pay_period_to');
                            }, function ($query) use ($request) {
                                $query->where('pay_period_from', $request->pay_period_from)
                                    ->where('pay_period_to', $request->pay_period_to);
                            })
                                ->where(['pid' => request()->input('payroll_id'), 'user_id' => $userId, 'is_next_payroll' => '1'])->update(['is_mark_paid' => '0', 'is_next_payroll' => '0']);
                        }
                    }
                }

                DB::commit();

                return response()->json([
                    'ApiName' => 'payroll_undo_next_payroll',
                    'status' => true,
                    'message' => 'Payroll undo next payroll Successfully',
                ]);
            } catch (Exception $e) {
                DB::rollBack();

                return response()->json([
                    'ApiName' => 'payroll_undo_next_payroll',
                    'status' => false,
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                ], 400);
            }
        } else {
            $payroll_id = $request->payroll_id;
            $pay_period_from = $request->pay_period_from; // from
            $pay_period_to = $request->pay_period_to; // from
            $history_data = PayrollShiftHistorie::where(['payroll_id' => $payroll_id, 'is_undo_done' => 1])->orderBy('id', 'DESC')->first();

            if (Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where('pay_period_from', $request->pay_period_from)
                    ->where('pay_period_to', $request->pay_period_to);
            })
                ->where(['status' => '2'])->first()) {
                return response()->json([
                    'ApiName' => 'payrollMarkAsPaid',
                    'status' => false,
                    'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
                ], 400);
            }

            $message = 'Payroll not found!!';
            DB::beginTransaction();
            $data = [];
            $status = false;
            $status_code = 400;
            $moved_by = auth()->user()->id;

            try {
                $message = 'Nothing for undo!!';
                if (! empty($history_data)) {
                    $old_pay_period_from = $pay_period_from; // to
                    $old_pay_period_to = $pay_period_to; // to

                    // $new_pay_period_from = $history_data->new_pay_period_from; // from
                    // $new_pay_period_to = $history_data->new_pay_period_to; // from

                    // $WeeklyPayFrequency = WeeklyPayFrequency::where(['pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to])->first();
                    // if ($WeeklyPayFrequency != null) {
                    //     $closed_status = $WeeklyPayFrequency->closed_status;
                    //     $MonthlyPayFrequency = MonthlyPayFrequency::where(['pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to])->first();
                    //     $closed_status = $MonthlyPayFrequency->closed_status;
                    // }

                    $message = "Previous payroll was closed, can't undo this payroll";
                    // if ($closed_status == 0) {

                    $payrollUpdate = Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where(['id' => $payroll_id])->first();
                    if (isset($payrollUpdate->ref_id)) {
                        PayrollCommon::where('id', $payrollUpdate->ref_id)->whereNull('orig_payfrom')->delete();
                    }
                    $payrollUpdate_user_id = $payrollUpdate->user_id;
                    $payrollUpdate->is_next_payroll = 0;
                    $payrollUpdate->ref_id = 0;
                    $payrollUpdate->save();

                    $UserCommission = UserCommission::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->get();
                    foreach ($UserCommission as $userComm) {
                        $updateUserCommission = UserCommission::where('id', $userComm->id)->first();
                        $updateUserCommission->is_next_payroll = 0;
                        $this->updateReferences([$userComm->id], 'commission', 0);
                        $updateUserCommission->save();
                    }

                    $UserOverrides = UserOverrides::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->get();
                    foreach ($UserOverrides as $userOver) {
                        $updateUserOverrides = UserOverrides::where('id', $userOver->id)->first();
                        $updateUserOverrides->is_next_payroll = 0;
                        $this->updateReferences([$userOver->id], 'override', 0);
                        $updateUserOverrides->save();
                    }

                    $ApprovalsAndRequest = ApprovalsAndRequest::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->get();
                    foreach ($ApprovalsAndRequest as $appRequest) {
                        $updateApprovalsAndRequest = ApprovalsAndRequest::where('id', $appRequest->id)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->first();
                        $updateApprovalsAndRequest->is_next_payroll = 0;
                        $this->updateReferences([$appRequest->id], 'approvalreject', 0);
                        $updateApprovalsAndRequest->save();
                    }

                    $ApprovalsAndRequest = ApprovalsAndRequest::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->where('adjustment_type_id', 2)->get();
                    foreach ($ApprovalsAndRequest as $appRequest) {
                        $updateApprovalsAndRequest = ApprovalsAndRequest::where('id', $appRequest->id)->where('adjustment_type_id', 2)->first();
                        $updateApprovalsAndRequest->is_next_payroll = 0;
                        $this->updateReferences([$appRequest->id], 'approvalrejectreimbursement', 0);
                        $updateApprovalsAndRequest->save();
                    }

                    $ClawbackSettlement = ClawbackSettlement::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->get();
                    foreach ($ClawbackSettlement as $clawSettle) {
                        $updateClawbackSettlement = ClawbackSettlement::where('id', $clawSettle->id)->first();
                        $updateClawbackSettlement->is_next_payroll = 0;
                        $this->updateReferences([$clawSettle->id], 'clawback', 0);
                        $updateClawbackSettlement->save();
                    }

                    // PayrollAdjustment undo to next payroll

                    $PayrollAdjustment = PayrollAdjustment::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->get();
                    foreach ($PayrollAdjustment as $payrollAdjust) {
                        $updatePayrollAdjustment = PayrollAdjustment::where('id', $payrollAdjust->id)->first();
                        $updatePayrollAdjustment->is_next_payroll = 0;
                        $this->updateReferences([$payrollAdjust->id], 'adjustment', 0);
                        $updatePayrollAdjustment->save();
                    }

                    // PayrollAdjustmentDetails undo to next payroll

                    $PayrollAdjustmentDetail = PayrollAdjustmentDetail::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->get();
                    foreach ($PayrollAdjustmentDetail as $payrollAdjustDetail) {
                        $updatePayrollAdjustmentDetail = PayrollAdjustmentDetail::where('id', $payrollAdjustDetail->id)->first();
                        $updatePayrollAdjustmentDetail->is_next_payroll = 0;
                        $this->updateReferences([$payrollAdjustDetail->id], 'adjustment', 0);
                        $updatePayrollAdjustmentDetail->save();
                    }

                    // PayrollDeductions undo to next payroll
                    $payrollDeductions = PayrollDeductions::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where('pay_period_from', $request->pay_period_from)
                            ->where('pay_period_to', $request->pay_period_to);
                    })
                        ->where([
                            'payroll_id' => $payroll_id,
                            'user_id' => $payrollUpdate_user_id,
                        ])->get();
                    foreach ($payrollDeductions as $userComm) {
                        $updatePayrollDeduction = PayrollDeductions::where('id', $userComm->id)->first();
                        $updatePayrollDeduction->is_next_payroll = 0;
                        $this->updateReferences([$userComm->id], 'deduction', 0);
                        $updatePayrollDeduction->save();
                    }
                    // PayrollHourlySalary undo to next payroll
                    $PayrollHourlySalary = PayrollHourlySalary::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate_user_id, 'pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to,
                    ])->get();
                    foreach ($PayrollHourlySalary as $payhosa) {
                        $updatePayrollHourlySalary = PayrollHourlySalary::where('id', $payhosa->id)->first();
                        $updatePayrollHourlySalary->is_next_payroll = 0;
                        $this->updateReferences([$payhosa->id], 'hourlysalary', 0);
                        $updatePayrollHourlySalary->save();
                    }

                    // PayrollOvertime undo to next payroll
                    $PayrollOvertime = PayrollOvertime::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate_user_id, 'pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to,
                    ])->get();
                    foreach ($PayrollOvertime as $payovert) {
                        $updatePayrollOvertime = PayrollOvertime::where('id', $payovert->id)->first();
                        $updatePayrollOvertime->is_next_payroll = 0;
                        $this->updateReferences([$payovert->id], 'overtime', 0);
                        $updatePayrollOvertime->save();
                    }

                    // PayrollCustomField undo to next payroll
                    $payrollCustomField = CustomField::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate_user_id, 'pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to,
                    ])->get();
                    foreach ($payrollCustomField as $userComm) {
                        $updateCustomField = CustomField::where('id', $userComm->id)->first();
                        $updateCustomField->is_next_payroll = 0;
                        $this->updateReferences([$userComm->id], 'customField', 0);
                        $updateCustomField->save();
                    }

                    // PayrollCustomField undo to next payroll
                    $payrollCustomField = CustomField::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate_user_id, 'pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to,
                    ])->get();
                    foreach ($payrollCustomField as $userComm) {
                        $updateCustomField = CustomField::where('id', $userComm->id)->first();
                        $updateCustomField->is_next_payroll = 0;
                        $this->updateReferences([$userComm->id], 'customField', 0);
                        $updateCustomField->save();
                    }

                    $PayrollShiftHistorie_undo = PayrollShiftHistorie::where(['id' => $history_data->id])->update(['is_undo_done' => 0]);

                    $this->updateEveree($payroll_id);

                    if ($payrollUpdate) { // && $PayrollShiftHistorie_delete
                        $status_code = 200;
                        $status = true;
                        $message = 'Payroll undo next payroll Successfully';
                        DB::commit();
                    } else {
                        $message = 'Somthing went wrong!!';
                        DB::rollback();
                    }
                    // }
                    // }
                }

                return response()->json([
                    'ApiName' => 'payroll_undo_next_payroll',
                    'status' => true,
                    'message' => 'Payroll undo next payroll Successfully',
                ], 200);
            } catch (Exception $e) {
                // Handle any exceptions that occur within the transaction
                $message = $e->getMessage();
                DB::rollBack();

                return response()->json([
                    'ApiName' => 'payroll_undo_next_payroll',
                    'status' => false,
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                ], 400);
            }
        }
    }

    public function commissionDetails(Request $request): JsonResponse
    {
        // echo "<pre>"; print_r($request->all()); die;
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $commissionPayrolls = UserCommission::with('userdata.positionDetail', 'saledata', 'payrollcommon')
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                })->where(['pid' => $request->pid])->whereIn('status', [1, 2, 6]);

            $onetimeCommissionPayrolls = UserCommission::with('userdata.positionDetail', 'saledata', 'payrollcommon')
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                })->where(['pid' => $request->pid])->where(['is_onetime_payment' => 1, 'status' => 3]);

            $commissionPayrollsData = $commissionPayrolls->union($onetimeCommissionPayrolls)->get();

            $data = [];
            $subtotal['commission']['ignore'] = [];

            foreach ($commissionPayrollsData as $commissionPayroll) {
                $payroll_status = empty($commissionPayroll->payrollcommon) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($commissionPayroll->is_mark_paid == 1 || $commissionPayroll->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = empty($commissionPayroll->payrollcommon) ? 'current' : (date('m/d/Y', strtotime($commissionPayroll->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($commissionPayroll->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($commissionPayroll->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($commissionPayroll->payrollcommon->payroll_modified_date)) : '';

                $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['pid' => $request->pid, 'user_id' => $commissionPayroll->user_id, 'pid' => $commissionPayroll->pid, 'payroll_type' => 'commission', 'type' => $commissionPayroll->amount_type, 'adjustment_type' => $commissionPayroll->amount_type])->first();
                $type = $commissionPayroll->schema_name;
                if ($commissionPayroll->amount_type == 'm2 update') {
                    $type = 'Commission Update';
                }

                $repRedline = null;
                if ($commissionPayroll->redline_type) {
                    if (in_array($commissionPayroll->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                        $repRedline = $commissionPayroll->redline.' Per Watt';
                    } else {
                        $repRedline = $commissionPayroll->redline.' '.ucwords($commissionPayroll->redline_type);
                    }
                }
                $s3_image = (isset($commissionPayroll->userdata->image) && $commissionPayroll->userdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$commissionPayroll->userdata->image) : null;
                $compRate = 0;
                $companyProfile = CompanyProfile::first();
                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $commissionPayroll->commission_type !== 'per sale') {
                    // $compRate = round((($commissionPayroll->net_epc * 100) - $commissionPayroll->redline), 2);
                    $compRate = number_format($commissionPayroll->comp_rate, 4, '.', '');
                }

                // Inner subquery (as raw SQL)
                $innerQuery = DB::table('sale_product_master')
                    ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                    ->where('pid', $commissionPayroll->pid)
                    ->groupBy('milestone_schema_id');

                // Outer query wrapping the inner one
                $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                    ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                    ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                    ->get();

                $data[$payroll_status][$period][] = [
                    'id' => $commissionPayroll->id,
                    'user_id' => $commissionPayroll->user_id,
                    'pid' => $commissionPayroll->pid,
                    'payroll_id' => $commissionPayroll->payroll_id,
                    'product' => $commissionPayroll?->saledata?->product_code,
                    'first_name' => $commissionPayroll->userdata->first_name,
                    'last_name' => $commissionPayroll->userdata->last_name,
                    'image_s3' => $s3_image,
                    'rep_redline' => $repRedline,
                    'comp_rate' => $compRate,
                    'amount' => isset($commissionPayroll->amount) ? $commissionPayroll->amount * 1 : null,
                    'pay_period_from' => isset($commissionPayroll->pay_period_from) ? $commissionPayroll->pay_period_from : null,
                    'pay_period_to' => isset($commissionPayroll->pay_period_to) ? $commissionPayroll->pay_period_to : null,
                    'amount_type' => $type,
                    'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $commissionPayroll->is_mark_paid,
                    'is_next_payroll' => $commissionPayroll->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'position_name' => @$commissionPayroll->userdata->positionDetail->position_name,
                    'position_id' => @$commissionPayroll->userdata->position_id,
                    'sub_position_id' => @$commissionPayroll->userdata->sub_position_id,
                    'is_super_admin' => @$commissionPayroll->userdata->is_super_admin,
                    'is_manager' => @$commissionPayroll->userdata->is_manager,
                    'is_stop_payroll' => @$commissionPayroll->is_stop_payroll,
                    'gross_account_value' => isset($commissionPayroll->saledata->gross_account_value) ? $commissionPayroll->saledata->gross_account_value : null,
                    'is_onetime_payment' => $commissionPayroll->is_onetime_payment,
                    'commission_amount' => @$commissionPayroll->commission_amount,
                    'commission_type' => @$commissionPayroll->commission_type,
                    'trigger_date' => @$result[0]->milestone_json,
                ];

                // $subtotal['commission'][$payroll_calculate][] = $commissionPayroll->amount * 1;
                // Only include in subtotal if all exclusion flags are 0
                if ($commissionPayroll->is_mark_paid == 0 && $commissionPayroll->is_next_payroll == 0 && $commissionPayroll->is_move_to_recon == 0 && $commissionPayroll->is_onetime_payment == 0) {
                    $subtotal['commission'][$payroll_calculate][] = $commissionPayroll->amount * 1;
                }
            }

            $clawbackSettlements = ClawbackSettlement::with('users.positionDetail', 'salesDetail', 'payrollcommon')
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                })->where('type', '!=', 'overrides')->where(['pid' => $request->pid, 'clawback_type' => 'next payroll'])->whereIn('status', [1, 2, 6])->get();
            foreach ($clawbackSettlements as $clawbackSettlement) {
                $payroll_status = (empty($clawbackSettlement->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($clawbackSettlement->is_mark_paid == 1 || $clawbackSettlement->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($clawbackSettlement->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($clawbackSettlement->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($clawbackSettlement->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($clawbackSettlement->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($clawbackSettlement->payrollcommon->payroll_modified_date)) : '';

                $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['pid' => $request->pid, 'user_id' => $clawbackSettlement->user_id, 'payroll_type' => 'commission', 'type' => 'clawback', 'adjustment_type' => $clawbackSettlement->adders_type])->first();
                $returnSalesDate = isset($clawbackSettlement->salesDetail->return_sales_date) ? $clawbackSettlement->salesDetail->return_sales_date : null;
                $s3_image = (isset($clawbackSettlement->users->image) && $clawbackSettlement->users->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$clawbackSettlement->users->image) : null;

                $repRedline = null;
                if ($clawbackSettlement->redline_type) {
                    if (in_array($clawbackSettlement->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                        $repRedline = $clawbackSettlement->redline.' Per Watt';
                    } else {
                        $repRedline = $clawbackSettlement->redline.' '.ucwords($clawbackSettlement->redline_type);
                    }
                }

                $compRate = 0;
                $companyProfile = CompanyProfile::first();
                if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    $compRate = round((($clawbackSettlement->net_epc * 100) - $clawbackSettlement->redline), 4);
                }
                $innerQuery = DB::table('sale_product_master')
                    ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                    ->where('pid', $clawbackSettlement->pid)
                    ->groupBy('milestone_schema_id');

                // Outer query wrapping the inner one
                $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                    ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                    ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                    ->get();

                $product = $clawbackSettlement?->product_code;
                if (empty($product)) {
                    $product = $clawbackSettlement?->salesDetail?->product ?? null;
                }
                $product = strtolower($product ?? '');

                $data[$payroll_status][$period][] = [
                    'id' => $clawbackSettlement->id,
                    'user_id' => $clawbackSettlement->user_id,
                    'pid' => $clawbackSettlement->pid,
                    'product' => $product,
                    'payroll_id' => $clawbackSettlement->payroll_id,
                    'first_name' => $clawbackSettlement->users->first_name,
                    'last_name' => $clawbackSettlement->users->last_name,
                    'image_s3' => $s3_image,
                    'rep_redline' => $repRedline,
                    // 'comp_rate' => $compRate,
                    'amount' => isset($clawbackSettlement->clawback_cal_amount) ? (0 - $clawbackSettlement->clawback_cal_amount * 1) : null,
                    'date' => isset($clawbackSettlement->salesDetail->date_cancelled) ? $clawbackSettlement->salesDetail->date_cancelled : $returnSalesDate,
                    'pay_period_from' => isset($clawbackSettlement->pay_period_from) ? $clawbackSettlement->pay_period_from : null,
                    'pay_period_to' => isset($clawbackSettlement->pay_period_to) ? $clawbackSettlement->pay_period_to : null,
                    'amount_type' => 'clawback',
                    'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $clawbackSettlement->is_mark_paid,
                    'is_next_payroll' => $clawbackSettlement->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'position_name' => @$clawbackSettlement->users->positionDetail->position_name,
                    'position_id' => @$clawbackSettlement->users->position_id,
                    'sub_position_id' => @$clawbackSettlement->users->sub_position_id,
                    'is_super_admin' => @$clawbackSettlement->users->is_super_admin,
                    'is_manager' => @$clawbackSettlement->users->is_manager,
                    'is_stop_payroll' => @$clawbackSettlement->is_stop_payroll,
                    'is_onetime_payment' => $clawbackSettlement->is_onetime_payment,
                    'commission_amount' => $clawbackSettlement->clawback_cal_amount,
                    'commission_type' => $clawbackSettlement->clawback_cal_type,
                    'trigger_date' => @$result[0]->milestone_json,
                ];
                // $subtotal['commission'][$payroll_calculate][] =  isset($clawbackSettlement->clawback_cal_amount) ? (0 - $clawbackSettlement->clawback_cal_amount * 1) : 0;
                // Only include in subtotal if all exclusion flags are 0
                if ($clawbackSettlement->is_mark_paid == 0 && $clawbackSettlement->is_next_payroll == 0 && $clawbackSettlement->is_move_to_recon == 0 && $clawbackSettlement->is_onetime_payment == 0) {
                    $subtotal['commission'][$payroll_calculate][] = isset($clawbackSettlement->clawback_cal_amount) ? (0 - $clawbackSettlement->clawback_cal_amount * 1) : 0;
                }
            }
            $data['subtotal'] = $subtotal;
            $commonData = SalesMaster::where('pid', $request->pid)->first();
            $data['common_data'] = [
                'location_code' => @$commonData->location_code,
                'kw' => @$commonData->kw,
                'net_epc' => @$commonData->net_epc,
            ];

            // Check if sorting parameters are present in the request
            if (! empty($request->has('sort')) && ! empty($request->has('sort_val')) && ! empty($data)) {
                // Get the column/key to sort by (e.g., pid, amount, product, etc.)
                $sortKey = $request->get('sort');

                // Determine the sort direction: ascending or descending
                $sortDirection = strtolower($request->get('sort_val')) === 'desc' ? SORT_DESC : SORT_ASC;

                // Sorting is applied after data aggregation because the dataset merges records from multiple sources
                // (e.g., commissions + clawbacks), making SQL-based sorting impractical or inconsistent at that stage.
                \applyPayrollCommissionSorting($data, $sortKey, $sortDirection);
            }

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => '0',
                'data' => $data,
            ]);
        } else {
            $data = [];
            $subtotal = [];
            $Validator = Validator::make($request->all(), [
                'id' => 'required',
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = $request->id;
            $payroll_id = $request->id;
            $user_id = $request->user_id;

            $Payroll = Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where([
                    'pay_period_from' => $request->pay_period_from,
                    'pay_period_to' => $request->pay_period_to,
                ]);
            })->where(['id' => $id, 'user_id' => $user_id])->first();

            if (empty($Payroll)) {
                // check is onetimePayment
                $payrollHistory = PayrollHistory::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                    ->where(['payroll_id' => $id, 'user_id' => $user_id, 'is_onetime_payment' => 1])->first();

                $Payroll = $payrollHistory;
            }

            if (! empty($Payroll)) {
                $usercommission = UserCommission::with('saledata', 'payrollcommon', 'userdata.positionDetail')
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where([
                            'pay_period_from' => $request->pay_period_from,
                            'pay_period_to' => $request->pay_period_to,
                        ]);
                    })->whereIn('status', [1, 2, 6])->where(['payroll_id' => $payroll_id, 'user_id' => $Payroll->user_id]);

                $onetimeCommissionPayrolls = UserCommission::with('userdata.positionDetail', 'saledata', 'payrollcommon')
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where(['payroll_id' => $payroll_id, 'user_id' => $Payroll->user_id])->where(['is_onetime_payment' => 1, 'status' => 3]);

                $commissionPayrollsData = $usercommission->union($onetimeCommissionPayrolls)->get();

                foreach ($commissionPayrollsData as $value) {
                    $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                    $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1 || $value->is_move_to_recon == 1) ? 'ignore' : 'count';
                    $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                    $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                    $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                        ->where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->schema_type, 'adjustment_type' => $value->schema_type])->first();

                    $type = $value->schema_name;
                    if ($value->amount_type == 'm2 update') {
                        $type = 'Commission Update';
                    }

                    $repRedline = null;
                    if ($value->redline_type) {
                        if (in_array($value->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                            $repRedline = $value->redline.' Per Watt';
                        } else {
                            $repRedline = $value->redline.' '.ucwords($value->redline_type);
                        }
                    }
                    $compRate = 0;
                    $companyProfile = CompanyProfile::first();
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $value->commission_type !== 'per sale') {

                        $compRate = $value->comp_rate;
                    }

                    $innerQuery = DB::table('sale_product_master')
                        ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                        ->where('pid', $value->pid)
                        ->groupBy('milestone_schema_id');

                    // Outer query wrapping the inner one
                    $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                        ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                        ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                        ->get();

                    $data[$payroll_status][$period][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'payroll_id' => $value->payroll_id,
                        'state_id' => isset($value->saledata->customer_state) ? strtoupper($value->saledata->customer_state) : null,
                        'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                        'customer_state' => isset($value->saledata->customer_state) ? strtoupper($value->saledata->customer_state) : null,
                        'rep_redline' => $repRedline,
                        'comp_rate' => number_format($compRate, 4, '.', ''),
                        'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
                        'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
                        'amount' => isset($value->amount) ? $value->amount * 1 : null,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        'amount_type' => $type,
                        'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                        'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                        'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                        'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                        'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                        'position_name' => @$value->userdata->positionDetail->position_name,
                        'position_id' => $value->position_id,
                        'is_mark_paid' => $value->is_mark_paid,
                        'is_next_payroll' => $value->is_next_payroll,
                        'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                        'is_recon' => $this->checkPositionReconStatus($value->user_id),
                        'is_move_to_recon' => $value->is_move_to_recon,
                        'product' => $value?->saledata?->product_code,
                        'gross_account_value' => isset($value->saledata->gross_account_value) ? $value->saledata->gross_account_value : null,
                        'scheduled_install' => isset($value->saledata->service_schedule) ? $value->saledata->service_schedule : null,
                        'is_stop_payroll' => @$value->is_stop_payroll,
                        'is_recon' => $this->checkPositionReconStatus($value->user_id),
                        'is_move_to_recon' => $value->is_move_to_recon,
                        'is_onetime_payment' => $value->is_onetime_payment,
                        'commission_amount' => $value->commission_amount,
                        'commission_type' => $value->commission_type,
                        'trigger_date' => @$result[0]->milestone_json,
                    ];
                    // $subtotal['commission'][$payroll_calculate][] = $value->amount * 1;
                    // Only include in subtotal if all exclusion flags are 0
                    if ($value->is_mark_paid == 0 && $value->is_next_payroll == 0 && $value->is_move_to_recon == 0 && $value->is_onetime_payment == 0) {
                        $subtotal['commission'][$payroll_calculate][] = $value->amount * 1;
                    }
                }

                $clawbackSettlement = ClawbackSettlement::with('users.positionDetail', 'salesDetail', 'payrollcommon')
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to]);
                    })->where('type', '!=', 'overrides')->where(['payroll_id' => $payroll_id, 'user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll'])->get();

                foreach ($clawbackSettlement as $value) {
                    $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                    $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1 || $value->is_move_to_recon == 1) ? 'ignore' : 'count';
                    $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                    $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                    $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                        ->where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => 'clawback', 'adjustment_type' => $value->schema_type])->first();
                    $returnSalesDate = isset($value->salesDetail->return_sales_date) ? $value->salesDetail->return_sales_date : null;

                    $repRedline = null;
                    if ($value->redline_type) {
                        if (in_array($value->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                            $repRedline = $value->redline.' Per Watt';
                        } else {
                            $repRedline = $value->redline.' '.ucwords($value->redline_type);
                        }
                    }
                    $compRate = 0;
                    $companyProfile = CompanyProfile::first();
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        if ($value->salesDetail->net_epc !== null) {
                            $compRate = round((($value->salesDetail->net_epc * 100) - $value->redline), 4);
                        }
                    }
                    $innerQuery = DB::table('sale_product_master')
                        ->select('milestone_schema_id', DB::raw('MIN(milestone_date) AS milestone_date'))
                        ->where('pid', $value->pid)
                        ->groupBy('milestone_schema_id');

                    // Outer query wrapping the inner one
                    $result = DB::table(DB::raw("({$innerQuery->toSql()}) as schema_dates"))
                        ->mergeBindings($innerQuery) // necessary to pass bindings like $pid
                        ->select(DB::raw("CONCAT('[',GROUP_CONCAT(CONCAT('{\"date\":', IF(milestone_date IS NULL, 'null', CONCAT('\"', milestone_date, '\"')), '}') ORDER BY milestone_schema_id SEPARATOR ','),']') AS milestone_json"))
                        ->get();

                    $product = $value?->product_code;
                    if (empty($product)) {
                        $product = $value?->salesDetail?->product ?? null;
                    }
                    $product = strtolower($product ?? '');

                    $data[$payroll_status][$period][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'payroll_id' => $value->payroll_id,
                        'product' => $product,
                        'state_id' => isset($value->salesDetail->customer_state) ? strtoupper($value->salesDetail->customer_state) : null,
                        'customer_name' => isset($value->salesDetail->customer_name) ? $value->salesDetail->customer_name : null,
                        'customer_state' => isset($value->salesDetail->customer_state) ? $value->salesDetail->customer_state : null,
                        'rep_redline' => $repRedline,
                        // 'comp_rate' => round($compRate, 2),
                        'kw' => isset($value->salesDetail->kw) ? $value->salesDetail->kw : null,
                        'net_epc' => isset($value->salesDetail->net_epc) ? $value->salesDetail->net_epc : null,
                        'amount' => isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : null,
                        'date' => isset($value->salesDetail->date_cancelled) ? $value->salesDetail->date_cancelled : $returnSalesDate,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        // this is clawback adjustment
                        'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                        'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                        'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                        'amount_type' => 'clawback',
                        'adders' => isset($value->adders) ? $value->adders : null,
                        'is_mark_paid' => $value->is_mark_paid,
                        'is_next_payroll' => $value->is_next_payroll,
                        'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                        'position_name' => @$value->users->positionDetail->position_name,
                        'is_stop_payroll' => @$value->is_stop_payroll,
                        'is_recon' => $this->checkPositionReconStatus($value->user_id),
                        'is_move_to_recon' => $value->is_move_to_recon,
                        'is_onetime_payment' => $value->is_onetime_payment,
                        'commission_amount' => $value->clawback_cal_amount,
                        'commission_type' => $value->clawback_cal_type,
                        'trigger_date' => @$result[0]->milestone_json,
                    ];
                    // $subtotal['commission'][$payroll_calculate][] =  isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : 0;
                    // Only include in subtotal if all exclusion flags are 0
                    if ($value->is_mark_paid == 0 && $value->is_next_payroll == 0 && $value->is_move_to_recon == 0 && $value->is_onetime_payment == 0) {
                        $subtotal['commission'][$payroll_calculate][] = isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : 0;
                    }
                }

                $data['subtotal'] = $subtotal;

                // Check if sorting parameters are present in the request
                if (! empty($request->has('sort')) && ! empty($request->has('sort_val')) && ! empty($data)) {
                    // Get the column/key to sort by (e.g., pid, amount, product, etc.)
                    $sortKey = $request->get('sort');

                    // Determine the sort direction: ascending or descending
                    $sortDirection = strtolower($request->get('sort_val')) === 'desc' ? SORT_DESC : SORT_ASC;

                    // Sorting is applied after data aggregation because the dataset merges records from multiple sources
                    // (e.g., commissions + clawbacks), making SQL-based sorting impractical or inconsistent at that stage.
                    \applyPayrollCommissionSorting($data, $sortKey, $sortDirection);
                }

                return response()->json([
                    'ApiName' => 'commission_details',
                    'status' => true,
                    'message' => 'Successfully.',
                    'payroll_status' => $Payroll->status,
                    'is_recon' => $this->checkPositionReconStatus($request->user_id),
                    'data' => $data,
                ]);
            } else {
                return response()->json([
                    'ApiName' => 'commission_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ]);
            }
        }
    }

    public function overrideDetails(Request $request): JsonResponse
    {
        $companyProfile = CompanyProfile::first();
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $overridePayrolls = UserOverrides::with('userInfo', 'userdata', 'salesDetail', 'payrollcommon')
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                ->where(['pid' => $request->pid, 'overrides_settlement_type' => 'during_m2'])
                ->whereIn('status', [1, 2, 6]);

            $onetimeOverridePayrolls = UserOverrides::with('userInfo', 'userdata', 'salesDetail', 'payrollcommon')
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                ->where(['pid' => $request->pid, 'overrides_settlement_type' => 'during_m2'])
                ->where(['is_onetime_payment' => 1, 'status' => 3]);

            $overridePayrollsData = $overridePayrolls->union($onetimeOverridePayrolls)->get();

            $data = [];
            $subtotal['commission']['ignore'] = [];

            foreach ($overridePayrollsData as $overridePayroll) {
                $payroll_status = (empty($overridePayroll->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($overridePayroll->is_mark_paid == 1 || $overridePayroll->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($overridePayroll->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($overridePayroll->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($overridePayroll->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($overridePayroll->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($overridePayroll->payrollcommon->payroll_modified_date)) : '';

                $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where([
                            'pay_period_from' => $request->pay_period_from,
                            'pay_period_to' => $request->pay_period_to,
                        ]);
                    })
                    ->where(['user_id' => $overridePayroll->user_id, 'pid' => $overridePayroll->pid, 'payroll_type' => 'overrides', 'type' => $overridePayroll->type, 'adjustment_type' => $overridePayroll->type, 'sale_user_id' => $overridePayroll->sale_user_id])->first();

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($overridePayroll->type == 'Stack m2 update') {
                        $type = 'Stack commission update';
                    } elseif ($overridePayroll->type == 'm2 update') {
                        $type = 'Commission Update';
                    } else {
                        $type = $overridePayroll->type;
                    }
                } else {
                    $type = $overridePayroll->type;
                }

                $redLineType = $overridePayroll->calculated_redline_type;
                if (in_array($overridePayroll->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                    $redLineType = 'percent';
                }

                $data[$payroll_status][$period][] = [
                    'id' => $overridePayroll->id,
                    'pid' => $overridePayroll->pid,
                    'payroll_id' => $overridePayroll->payroll_id,
                    'product' => $overridePayroll?->salesDetail?->product_code,
                    'first_name' => isset($overridePayroll->userInfo->first_name) ? $overridePayroll->userInfo->first_name : null,
                    'last_name' => isset($overridePayroll->userInfo->last_name) ? $overridePayroll->userInfo->last_name : null,
                    'position_id' => isset($overridePayroll->userInfo->position_id) ? $overridePayroll->userInfo->position_id : null,
                    'sub_position_id' => isset($overridePayroll->userInfo->sub_position_id) ? $overridePayroll->userInfo->sub_position_id : null,
                    'is_super_admin' => isset($overridePayroll->userInfo->is_super_admin) ? $overridePayroll->userInfo->is_super_admin : null,
                    'is_manager' => isset($overridePayroll->userInfo->is_manager) ? $overridePayroll->userInfo->is_manager : null,
                    'image' => isset($overridePayroll->userInfo->image) ? $overridePayroll->userInfo->image : null,
                    'user_first_name' => isset($overridePayroll->userdata->first_name) ? $overridePayroll->userdata->first_name : null,
                    'user_last_name' => isset($overridePayroll->userdata->last_name) ? $overridePayroll->userdata->last_name : null,
                    'user_image' => isset($overridePayroll->userdata->image) ? $overridePayroll->userdata->image : null,
                    'user_position_id' => @$overridePayroll->userdata->position_id,
                    'user_sub_position_id' => @$overridePayroll->userdata->sub_position_id,
                    'user_is_super_admin' => @$overridePayroll->userdata->is_super_admin,
                    'user_is_manager' => @$overridePayroll->userdata->is_manager,
                    'type' => $type,
                    'total_amount' => isset($overridePayroll->amount) ? $overridePayroll->amount * 1 : 0,
                    'override_type' => $overridePayroll->overrides_type,
                    'override_amount' => isset($overridePayroll->overrides_amount) ? $overridePayroll->overrides_amount * 1 : 0,
                    'm2_date' => isset($overridePayroll->salesDetail->m2_date) ? $overridePayroll->salesDetail->m2_date : null,
                    'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : null,
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $overridePayroll->is_mark_paid,
                    'is_next_payroll' => $overridePayroll->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'calculated_redline' => $overridePayroll->calculated_redline,
                    'is_stop_payroll' => @$overridePayroll->is_stop_payroll,
                    'calculated_redline_type' => $redLineType,
                    'is_onetime_payment' => $overridePayroll->is_onetime_payment,
                ];
                $subtotal['override'][$payroll_calculate][] = isset($overridePayroll->amount) ? $overridePayroll->amount * 1 : 0;
            }

            $clawbackSettlements = ClawbackSettlement::with(['payrollcommon', 'salesDetail', 'saleUserInfo.state', 'users'])->where([
                'type' => 'overrides',
                'pid' => $request->pid,
                'clawback_type' => 'next payroll',
            ])
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                ->whereIn('status', [1, 2, 6])->get();

            foreach ($clawbackSettlements as $clawbackSettlement) {
                $payroll_status = (empty($clawbackSettlement->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($clawbackSettlement->is_mark_paid == 1 || $clawbackSettlement->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($clawbackSettlement->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($clawbackSettlement->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($clawbackSettlement->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($clawbackSettlement->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($clawbackSettlement->payrollcommon->payroll_modified_date)) : '';

                $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where([
                            'pay_period_from' => $request->pay_period_from,
                            'pay_period_to' => $request->pay_period_to,
                        ]);
                    })
                    ->where(['user_id' => $clawbackSettlement->user_id, 'pid' => request()->input('pid'), 'payroll_type' => 'overrides', 'type' => 'clawback',  'adjustment_type' => $clawbackSettlement->adders_type, 'sale_user_id' => $clawbackSettlement->sale_user_id])->first();

                $data[$payroll_status][$period][] = [
                    'id' => $clawbackSettlement->id,
                    'pid' => $clawbackSettlement->pid,
                    'payroll_id' => $clawbackSettlement->payroll_id,
                    'product' => $clawbackSettlement?->salesDetail?->product_code,
                    'first_name' => isset($clawbackSettlement->saleUserInfo->first_name) ? $clawbackSettlement->saleUserInfo->first_name : null,
                    'last_name' => isset($clawbackSettlement->saleUserInfo->last_name) ? $clawbackSettlement->saleUserInfo->last_name : null,
                    'position_id' => isset($clawbackSettlement->saleUserInfo->position_id) ? $clawbackSettlement->saleUserInfo->position_id : null,
                    'sub_position_id' => isset($clawbackSettlement->saleUserInfo->sub_position_id) ? $clawbackSettlement->saleUserInfo->sub_position_id : null,
                    'is_super_admin' => isset($clawbackSettlement->saleUserInfo->is_super_admin) ? $clawbackSettlement->saleUserInfo->is_super_admin : null,
                    'is_manager' => isset($clawbackSettlement->saleUserInfo->is_manager) ? $clawbackSettlement->saleUserInfo->is_manager : null,
                    'image' => isset($clawbackSettlement->saleUserInfo->image) ? $clawbackSettlement->saleUserInfo->image : null,
                    'user_first_name' => isset($clawbackSettlement->users->first_name) ? $clawbackSettlement->users->first_name : null,
                    'user_last_name' => isset($clawbackSettlement->users->last_name) ? $clawbackSettlement->users->last_name : null,
                    'user_image' => isset($clawbackSettlement->users->image) ? $clawbackSettlement->users->image : null,
                    'user_position_id' => @$clawbackSettlement->users->position_id,
                    'user_sub_position_id' => @$clawbackSettlement->users->sub_position_id,
                    'user_is_super_admin' => @$clawbackSettlement->users->is_super_admin,
                    'user_is_manager' => @$clawbackSettlement->users->is_manager,
                    'type' => 'clawback',
                    'total_amount' => isset($clawbackSettlement->clawback_amount) ? (0 - $clawbackSettlement->clawback_amount * 1) : null,
                    'override_type' => 'clawback',
                    'override_amount' => null,
                    'm2_date' => isset($clawbackSettlement->salesDetail->m2_date) ? $clawbackSettlement->salesDetail->m2_date : null,
                    'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : null,
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'is_mark_paid' => $clawbackSettlement->is_mark_paid,
                    'is_next_payroll' => $clawbackSettlement->is_next_payroll,
                    'is_stop_payroll' => @$clawbackSettlement->is_stop_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'calculated_redline' => null,
                    'is_onetime_payment' => $clawbackSettlement->is_onetime_payment,
                ];
                $subtotal['override'][$payroll_calculate][] = isset($clawbackSettlement->clawback_amount) ? (0 - $clawbackSettlement->clawback_amount * 1) : 0;
            }

            $data['subtotal'] = $subtotal;
            $commonData = SalesMaster::where('pid', $request->pid)->first();
            $data['common_data'] = [
                'location_code' => @$commonData->location_code,
                'kw' => @$commonData->kw,
                'net_epc' => @$commonData->net_epc,
            ];

            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => '0',
                'data' => $data,
            ]);
        } else {
            $data = [];
            $subtotal = [];
            $Validator = Validator::make($request->all(), [
                'id' => 'required',
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = $request->id;
            $payroll_id = $request->id;
            $user_id = $request->user_id;
            $pay_period_from = $request->pay_period_from;
            $pay_period_to = $request->pay_period_to;

            $Payroll = Payroll::where(['id' => $id, 'user_id' => $user_id])
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                ->first();

            if (empty($Payroll)) {
                // check is onetimePayment
                $payrollHistory = PayrollHistory::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                    ->where(['payroll_id' => $id, 'user_id' => $user_id, 'is_onetime_payment' => 1])->first();

                $Payroll = $payrollHistory;
            }

            if (! empty($Payroll)) {
                $userdata = UserOverrides::with('userInfo', 'salesDetail', 'payrollcommon')->whereIn('status', [1, 2, 6])
                    ->where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $Payroll->user_id,
                        'overrides_settlement_type' => 'during_m2',
                    ])
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where([
                            'pay_period_from' => $request->pay_period_from,
                            'pay_period_to' => $request->pay_period_to,
                        ]);
                    });

                $onetimeOverridePayrolls = UserOverrides::with('userInfo', 'userdata', 'salesDetail', 'payrollcommon')
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where([
                            'pay_period_from' => $request->pay_period_from,
                            'pay_period_to' => $request->pay_period_to,
                        ]);
                    })
                    ->where(['payroll_id' => $payroll_id,
                        'user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2'])
                    ->where(['is_onetime_payment' => 1, 'status' => 3]);

                $overridePayrollsData = $userdata->union($onetimeOverridePayrolls)->get();

                foreach ($overridePayrollsData as $value) {
                    $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                    $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                    $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                    $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                    $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                        ->where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type, 'adjustment_type' => $value->type, 'sale_user_id' => $value->sale_user_id])->first();
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        if ($value->type == 'Stack m2 update') {
                            $type = 'Stack commission update';
                        } elseif ($value->type == 'm2 update') {
                            $type = 'Commission Update';
                        } else {
                            $type = $value->type;
                        }
                    } else {
                        $type = $value->type;
                    }

                    $redLineType = $value->calculated_redline_type;
                    if (in_array($value->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                        $redLineType = 'percent';
                    }

                    $data[$payroll_status][$period][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'payroll_id' => $value->payroll_id,
                        'product' => $value?->salesDetail?->product_code,
                        'first_name' => isset($value->userInfo->first_name) ? $value->userInfo->first_name : null,
                        'last_name' => isset($value->userInfo->last_name) ? $value->userInfo->last_name : null,
                        'position_id' => isset($value->userInfo->position_id) ? $value->userInfo->position_id : null,
                        'sub_position_id' => isset($value->userInfo->sub_position_id) ? $value->userInfo->sub_position_id : null,
                        'is_super_admin' => isset($value->userInfo->is_super_admin) ? $value->userInfo->is_super_admin : null,
                        'is_manager' => isset($value->userInfo->is_manager) ? $value->userInfo->is_manager : null,
                        'image' => isset($value->userInfo->image) ? $value->userInfo->image : null,
                        'type' => $type,
                        'accounts' => 1,
                        'kw_installed' => $value->kw, // this one is used for solar case
                        'total_amount' => isset($value->amount) ? $value->amount * 1 : 0,
                        'override_type' => $value->overrides_type,
                        'override_amount' => isset($value->overrides_amount) ? $value->overrides_amount * 1 : 0,
                        'calculated_redline' => $value->calculated_redline,
                        'state' => isset($value->userInfo->state) ? $value->userInfo->state->state_code : null,
                        'm2_date' => isset($value->salesDetail->m2_date) ? $value->salesDetail->m2_date : null,
                        'customer_name' => isset($value->salesDetail->customer_name) ? $value->salesDetail->customer_name : null,
                        'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                        'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : null,
                        'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                        'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                        'is_mark_paid' => $value->is_mark_paid,
                        'is_next_payroll' => $value->is_next_payroll,
                        'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                        'is_stop_payroll' => @$value->is_stop_payroll,
                        'account_value' => in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) ? $value->kw : null,
                        'calculated_redline_type' => $redLineType,
                        'is_onetime_payment' => $value->is_onetime_payment,
                        'is_move_to_recon' => $value->is_move_to_recon,
                    ];

                    $subtotal['override'][$payroll_calculate][] = isset($value->amount) ? $value->amount * 1 : 0;
                }

                $clawbackSettlements = ClawbackSettlement::with(['payrollcommon', 'salesDetail', 'saleUserInfo.state'])->where([
                    'type' => 'overrides',
                    'payroll_id' => $payroll_id,
                    'user_id' => $Payroll->user_id,
                    'clawback_type' => 'next payroll',
                ])
                    ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                        $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                            ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($request) {
                        $query->where([
                            'pay_period_from' => $request->pay_period_from,
                            'pay_period_to' => $request->pay_period_to,
                        ]);
                    })
                    ->get();

                foreach ($clawbackSettlements as $value) {
                    $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                    $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                    $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                    $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                    $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->whereIn('status', [1, 2, 6])
                        ->where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => 'clawback', 'adjustment_type' => $value->adders_type, 'sale_user_id' => $value->sale_user_id])->first();
                    $data[$payroll_status][$period][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'payroll_id' => $value->payroll_id,
                        'product' => $value?->salesDetail?->product_code,
                        'first_name' => isset($value->saleUserInfo->first_name) ? $value->saleUserInfo->first_name : null,
                        'last_name' => isset($value->saleUserInfo->last_name) ? $value->saleUserInfo->last_name : null,
                        'position_id' => isset($value->saleUserInfo->position_id) ? $value->saleUserInfo->position_id : null,
                        'sub_position_id' => isset($value->saleUserInfo->sub_position_id) ? $value->saleUserInfo->sub_position_id : null,
                        'is_super_admin' => isset($value->saleUserInfo->is_super_admin) ? $value->saleUserInfo->is_super_admin : null,
                        'is_manager' => isset($value->saleUserInfo->is_manager) ? $value->saleUserInfo->is_manager : null,
                        'image' => isset($value->saleUserInfo->image) ? $value->saleUserInfo->image : null,
                        'type' => 'clawback',
                        'accounts' => 1,
                        'kw_installed' => isset($value->salesDetail->kw) ? $value->salesDetail->kw : null,
                        'total_amount' => isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : null,
                        'override_type' => 'clawback', // Not On Table
                        'override_amount' => null,
                        'calculated_redline' => '',
                        'state' => isset($value->saleUserInfo->state) ? $value->saleUserInfo->state->state_code : null,
                        'm2_date' => isset($value->salesDetail->m2_date) ? $value->salesDetail->m2_date : null,
                        'customer_name' => isset($value->salesDetail->customer_name) ? $value->salesDetail->customer_name : null,
                        'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0,
                        'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : null,
                        'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                        'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                        'is_mark_paid' => $value->is_mark_paid,
                        'is_next_payroll' => $value->is_next_payroll,
                        'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                        'is_stop_payroll' => @$value->is_stop_payroll,
                        'is_onetime_payment' => $value->is_onetime_payment,
                    ];
                    $subtotal['override'][$payroll_calculate][] = isset($value->clawback_amount) ? (0 - $value->clawback_amount * 1) : 0;
                }

                $data['subtotal'] = $subtotal;

                return response()->json([
                    'ApiName' => 'override_details',
                    'status' => true,
                    'message' => 'Successfully.',
                    'payroll_status' => $Payroll->status,
                    'data' => $data,
                ]);
            } else {
                return response()->json([
                    'ApiName' => 'override_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ]);
            }
        }
    }

    public function adjustmentDetails(Request $request): JsonResponse
    {
        if ($request->type == 'pid') {
            $validator = Validator::make($request->all(), [
                'pid' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $adjustmentPayrolls = PayrollAdjustmentDetail::with('commented_by', 'payrollcommon', 'userData', 'saledata')->whereIn('status', [1, 2, 6])
                ->where(['pid' => $request->pid])
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                ->get();
            if (count($adjustmentPayrolls) == 0) {
                return response()->json([
                    'ApiName' => 'adjustment_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ]);
            }

            $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
            foreach ($adjustmentPayrolls as $adjustmentPayroll) {
                $payroll = Payroll::where(['id' => $adjustmentPayroll->payroll_id])->first();
                $payroll_status = (empty($adjustmentPayroll->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($adjustmentPayroll->is_mark_paid == 1 || $adjustmentPayroll->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($adjustmentPayroll->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($adjustmentPayroll->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($adjustmentPayroll->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($adjustmentPayroll->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($adjustmentPayroll->payrollcommon->payroll_modified_date)) : '';

                $data[$payroll_status][$period][] = [
                    'id' => $adjustmentPayroll->id,
                    'pid' => $adjustmentPayroll->pid,
                    'payroll_id' => $adjustmentPayroll->payroll_id,
                    'user_details' => $adjustmentPayroll->userData,
                    'first_name' => isset($adjustmentPayroll->commented_by->first_name) ? $adjustmentPayroll->commented_by->first_name : null,
                    'last_name' => isset($adjustmentPayroll->commented_by->first_name) ? $adjustmentPayroll->commented_by->last_name : null,
                    'is_super_admin' => isset($adjustmentPayroll->commented_by->is_super_admin) ? $adjustmentPayroll->commented_by->is_super_admin : null,
                    'is_manager' => isset($adjustmentPayroll->commented_by->is_manager) ? $adjustmentPayroll->commented_by->is_manager : null,
                    'position_id' => isset($adjustmentPayroll->commented_by->position_id) ? $adjustmentPayroll->commented_by->position_id : null,
                    'sub_position_id' => isset($adjustmentPayroll->commented_by->sub_position_id) ? $adjustmentPayroll->commented_by->sub_position_id : null,
                    'image' => $image_s3,
                    'date' => isset($adjustmentPayroll['updated_at']) ? date('Y-m-d', strtotime($adjustmentPayroll['updated_at'])) : null,
                    'amount' => $adjustmentPayroll['amount'] * 1,
                    'payroll_type' => $adjustmentPayroll['payroll_type'],
                    'type' => $adjustmentPayroll['type'],
                    'description' => isset($adjustmentPayroll['comment']) ? $adjustmentPayroll['comment'] : null,
                    'adjustment_type' => 'payroll',
                    'adjustment_id' => $adjustmentPayroll->id,
                    'is_mark_paid' => $adjustmentPayroll->is_mark_paid,
                    'is_next_payroll' => $adjustmentPayroll->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'is_stop_payroll' => @$payroll->is_stop_payroll,
                    'is_from_approval_and_request' => 0,
                    'is_onetime_payment' => $adjustmentPayroll->is_onetime_payment,
                    'customer_name' => isset($adjustmentPayroll->saledata->customer_name) ? $adjustmentPayroll->saledata->customer_name : null,
                ];
                $subtotal['adjustment'][$payroll_calculate][] = isset($adjustmentPayroll['amount']) ? $adjustmentPayroll['amount'] * 1 : 0;
            }

            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => '0',
                'data' => $data,
            ]);
        } else {
            $data = [];
            $subtotal = [];
            $Validator = Validator::make(
                $request->all(),
                [
                    'id' => 'required', // 15
                    'user_id' => 'required',
                    'pay_period_from' => 'required',
                    'pay_period_to' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = $request->id;
            $user_id = $request->user_id;
            $pay_period_from = $request->pay_period_from;
            $pay_period_to = $request->pay_period_to;

            $payroll = Payroll::where(['id' => $id, 'user_id' => $user_id])
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                ->first();

            if (empty($payroll)) {
                // check is onetimePayment
                $payrollHistory = PayrollHistory::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                    ->where(['payroll_id' => $id, 'user_id' => $user_id, 'is_onetime_payment' => 1])->first();

                $payroll = $payrollHistory;
            }

            if (! empty($payroll)) {
                // $dataAdjustment = PayrollAdjustment::with('detail')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();

                // if (!empty($dataAdjustment)) {
                // $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
                $details = PayrollAdjustmentDetail::with('commented_by', 'payrollcommon', 'userData', 'saledata')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();
                $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
                foreach ($details as $value) {

                    $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                    $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1 || $value->is_move_to_recon == 1) ? 'ignore' : 'count';
                    $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                    $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                    $data[$payroll_status][$period][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'payroll_id' => $value->payroll_id,
                        'user_details' => $value->userData,
                        'first_name' => isset($value->commented_by->first_name) ? $value->commented_by->first_name : 'Super',
                        'last_name' => isset($value->commented_by->first_name) ? $value->commented_by->last_name : 'Admin',
                        'is_super_admin' => isset($value->commented_by->is_super_admin) ? $value->commented_by->is_super_admin : null,
                        'is_manager' => isset($value->commented_by->is_manager) ? $value->commented_by->is_manager : null,
                        'position_id' => isset($value->commented_by->position_id) ? $value->commented_by->position_id : null,
                        'sub_position_id' => isset($value->commented_by->sub_position_id) ? $value->commented_by->sub_position_id : null,
                        'image' => $image_s3,
                        'date' => isset($value['updated_at']) ? date('Y-m-d', strtotime($value['updated_at'])) : null,
                        'amount' => $value['amount'] * 1,
                        'payroll_type' => $value['payroll_type'],
                        'type' => $value['type'],
                        'description' => isset($value['comment']) ? $value['comment'] : null,
                        'adjustment_type' => 'payroll',
                        'adjustment_id' => $value->id,
                        'is_mark_paid' => $value->is_mark_paid,
                        'is_next_payroll' => $value->is_next_payroll,
                        'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                        'is_stop_payroll' => @$payroll->is_stop_payroll,
                        'is_from_approval_and_request' => 0,
                        'is_onetime_payment' => $value->is_onetime_payment,
                        'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,

                    ];
                    $subtotal['adjustment'][$payroll_calculate][] = isset($value['amount']) ? $value['amount'] * 1 : 0;
                    unset($value);
                }
                // }

                $dataApprovalAndRequest = ApprovalsAndRequest::with('adjustment', 'approvedBy', 'payrollcommon', 'userData', 'comments')
                    ->where('adjustment_type_id', '!=', 2)
                    ->where(['payroll_id' => $payroll->id])
                    ->where(['user_id' => $payroll->user_id])
                    ->get();

                foreach ($dataApprovalAndRequest as $key => $value) {
                    // $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
                    $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                    $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                    $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                    $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                    $data[$payroll_status][$period][] = [
                        'id' => $value['id'],
                        'pid' => $value->req_no,
                        'payroll_id' => $value->payroll_id,
                        'user_details' => $value->userData,
                        'is_super_admin' => $value->approvedBy->is_super_admin,
                        'is_manager' => $value->approvedBy->is_manager,
                        'position_id' => $value->approvedBy->position_id,
                        'sub_position_id' => $value->approvedBy->sub_position_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'date' => isset($value['created_at']) ? date('Y-m-d', strtotime($value['created_at'])) : null,
                        'amount' => ($value->adjustment_type_id == 5 && ! empty($value['amount'])) ? -$value['amount'] * 1 : $value['amount'] * 1,
                        'payroll_type' => $value->adjustment->name,
                        'type' => 'Adjustment',
                        'description' => isset($value->description) ? $value->description : (isset($value->comments->comment) ? strip_tags($value->comments->comment) : null),
                        'adjustment_type' => 'payroll',
                        'adjustment_id' => null,
                        'is_mark_paid' => $value->is_mark_paid,
                        'is_next_payroll' => $value->is_next_payroll,
                        'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                        'is_stop_payroll' => @$payroll->is_stop_payroll,
                        'is_from_approval_and_request' => 1,
                        'is_onetime_payment' => $value->is_onetime_payment,

                    ];
                    $subtotal['adjustment'][$payroll_calculate][] = ($value->adjustment_type_id == 5 && ! empty($value['amount'])) ? -$value['amount'] * 1 : $value['amount'] * 1;
                    unset($value);
                }
                $data['subtotal'] = $subtotal;

                return response()->json([
                    'ApiName' => 'adjustment_details',
                    'status' => true,
                    'message' => 'Successfully.',
                    'payroll_status' => $payroll->status,
                    'is_recon' => $this->checkPositionReconStatus($request->user_id),
                    'data' => $data,

                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'adjustment_details',
                    'status' => true,
                    'message' => 'No Records.',
                    'data' => [],
                ], 200);
            }
        }
    }

    public function reimbursementDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = Payroll::where(['id' => $id, 'user_id' => $user_id])
            ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where([
                    'pay_period_from' => $request->pay_period_from,
                    'pay_period_to' => $request->pay_period_to,
                ]);
            })
            ->first();

        if (empty($payroll)) {
            // check is onetimePayment
            $payrollHistory = PayrollHistory::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where([
                    'pay_period_from' => $request->pay_period_from,
                    'pay_period_to' => $request->pay_period_to,
                ]);
            })
                ->where(['payroll_id' => $id, 'user_id' => $user_id, 'is_onetime_payment' => 1])->first();

            $payroll = $payrollHistory;
        }

        $payroll_status = '';
        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy', 'costcenter', 'userData')->where('status', 'Accept')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])
                ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                    $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                        ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($request) {
                    $query->where([
                        'pay_period_from' => $request->pay_period_from,
                        'pay_period_to' => $request->pay_period_to,
                    ]);
                })
                ->get();

            foreach ($reimbursement as $key => $value) {

                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';

                // if(isset($value->approvedBy->image) && $value->approvedBy->image!=null){
                //     $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$value->approvedBy->image);
                // }else{
                //     $image_s3 = null;
                // }
                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'pid' => $value->req_no,
                    'payroll_id' => $value->payroll_id,
                    'cost_center' => isset($value->costcenter->name) ? $value->costcenter->name : null,
                    'user_details' => $value->userData,
                    'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                    'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                    'position_id' => isset($value->approvedBy->position_id) ? $value->approvedBy->position_id : null,
                    'sub_position_id' => isset($value->approvedBy->sub_position_id) ? $value->approvedBy->sub_position_id : null,
                    'is_super_admin' => isset($value->approvedBy->is_super_admin) ? $value->approvedBy->is_super_admin : null,
                    'is_manager' => isset($value->approvedBy->is_manager) ? $value->approvedBy->is_manager : null,
                    'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                    // 'image_s3' => $image_s3,
                    'date' => isset($value->cost_date) ? $value->cost_date : null,
                    'amount' => isset($value->amount) ? $value->amount * 1 : 0,
                    'description' => isset($value->description) ? $value->description : null,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'is_stop_payroll' => @$payroll->is_stop_payroll,
                    'is_from_approval_and_request' => 1,
                    'is_onetime_payment' => $value->is_onetime_payment,
                ];
                $subtotal['reimbursement'][$payroll_calculate][] = isset($value->amount) ? $value->amount * 1 : 0;
                unset($value);
            }

            $data['subtotal'] = $subtotal;

            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll_status,
                'is_recon' => $this->checkPositionReconStatus($request->user_id),
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function check_payroll_closed_status($start_date, $end_date, $pay_frequency, $workerType = '1099')
    {
        if ($pay_frequency == 2) {
            $weekly = WeeklyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->when($workerType == 'w2', function ($q) {
                    $q->where('w2_closed_status', 1);
                })
                ->when($workerType == '1099', function ($q) {
                    $q->where('closed_status', 1);
                })->first();
            if ($weekly) {
                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'This payroll is already closed.',
                    'finalize_status' => null,
                    'data' => [],
                    'all_paid' => false,
                    'total_alert_count' => 0,
                ], 200);
            } else {
                return [];
            }
        } elseif ($pay_frequency == FrequencyType::BI_WEEKLY_ID) {
            $biWeekly = AdditionalPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where(['type' => AdditionalPayFrequency::BI_WEEKLY_TYPE])
                ->when($workerType == 'w2', function ($q) {
                    $q->where('w2_closed_status', 1);
                })
                ->when($workerType == '1099', function ($q) {
                    $q->where('closed_status', 1);
                })->first();
            if ($biWeekly) {
                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'This payroll is already closed.',
                    'finalize_status' => null,
                    'data' => [],
                    'all_paid' => false,
                    'total_alert_count' => 0,
                ], 200);
            } else {
                return [];
            }
        } elseif ($pay_frequency == FrequencyType::SEMI_MONTHLY_ID) {
            $semiMonthly = AdditionalPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where(['type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE])
                ->when($workerType == 'w2', function ($q) {
                    $q->where('w2_closed_status', 1);
                })
                ->when($workerType == '1099', function ($q) {
                    $q->where('closed_status', 1);
                })->first();
            if ($semiMonthly) {
                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'This payroll is already closed.',
                    'finalize_status' => null,
                    'data' => [],
                    'all_paid' => false,
                    'total_alert_count' => 0,
                ], 200);
            } else {
                return [];
            }
        } elseif ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
            $semiMonthly = DailyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->when($workerType == 'w2', function ($q) {
                    $q->where('w2_closed_status', 1);
                })
                ->when($workerType == '1099', function ($q) {
                    $q->where('closed_status', 1);
                })->first();
            if ($semiMonthly) {
                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'This payroll is already closed.',
                    'finalize_status' => null,
                    'data' => [],
                    'all_paid' => false,
                    'total_alert_count' => 0,
                ], 200);
            } else {
                return [];
            }
        } else {
            $monthly = MonthlyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->when($workerType == 'w2', function ($q) {
                    $q->where('w2_closed_status', 1);
                })
                ->when($workerType == '1099', function ($q) {
                    $q->where('closed_status', 1);
                })->first();
            if ($monthly) {
                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'This payroll is already closed.',
                    'finalize_status' => null,
                    'data' => [],
                    'all_paid' => false,
                    'total_alert_count' => 0,
                ], 200);
            } else {
                return [];
            }
        }
    }

    public function check_open_status_from_bank($start_date, $end_date, $pay_frequency)
    {
        if ($pay_frequency == 2) {
            $weekly = WeeklyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('closed_status', 1)->where('open_status_from_bank', 1)->first();
            if ($weekly) {
                return true;
            } else {
                return false;
            }
        } elseif ($pay_frequency == FrequencyType::BI_WEEKLY_ID) {
            $biWeekly = AdditionalPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where(['closed_status' => 1, 'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE, 'open_status_from_bank' => 1])->first();
            if ($biWeekly) {
                return true;
            } else {
                return false;
            }
        } elseif ($pay_frequency == FrequencyType::SEMI_MONTHLY_ID) {
            $semiMonthly = AdditionalPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where(['closed_status' => 1, 'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE, 'open_status_from_bank' => 1])->first();
            if ($semiMonthly) {
                return true;
            } else {
                return false;
            }
        } else {
            $monthly = MonthlyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('closed_status', 1)->where('open_status_from_bank', 1)->first();
            if ($monthly) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function update_payroll_data($start_date, $end_date, $pay_frequency)
    {
        if (! empty($start_date) && ! empty($end_date)) {
            // START REMOVE DUPLICATE USERS
            $duplicates = DB::table('payrolls as p1')->select('p1.id')
                ->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                    $query->join('payrolls as p2', function ($join) {
                        $join->on('p1.user_id', 'p2.user_id')
                            ->on('p1.id', '<', 'p2.id');
                    })
                        ->whereBetween('p1.pay_period_from', [$start_date, $end_date])
                        ->whereBetween('p1.pay_period_to', [$start_date, $end_date])
                        ->whereColumn('p1.pay_period_from', 'p1.pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->join('payrolls as p2', function ($join) {
                        $join->on('p1.pay_period_from', 'p2.pay_period_from')
                            ->on('p1.user_id', 'p2.user_id')
                            ->on('p1.id', '<', 'p2.id');
                    })
                        ->where([
                            'p1.pay_period_from' => $start_date,
                            'p1.pay_period_to' => $end_date,
                        ]);
                })
                ->get()->pluck('id');

            //    dump($duplicates);
            // REMOVE DEPLICATE RECORD BY ID
            Payroll::whereIn('id', $duplicates)->delete();
            // END REMOVE DUPLICATE USERS

            $paydata = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
            $paydata = Payroll::when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {

                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })
                ->get();
            if (count($paydata) > 0) {
                foreach ($paydata as $data) {
                    try {
                        DB::beginTransaction();

                        if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                            // UPDATING PAYROLL RECORDS WHERE PAYROLL ID IS NULL OR 0
                            UserCommission::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            UserOverrides::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('overrides_settlement_type', 'during_m2')->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('clawback_type', 'next payroll')->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', '!=', 'Paid')
                                ->update(['payroll_id' => $data->id]);

                            PayrollAdjustment::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            PayrollAdjustmentDetail::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $data->user_id)->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);
                        } else {

                            // UPDATING PAYROLL RECORDS WHERE PAYROLL ID IS NULL OR 0
                            UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            UserOverrides::where(['user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            ClawbackSettlement::where(['user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            ApprovalsAndRequest::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('status', '!=', 'Paid')
                                ->update(['payroll_id' => $data->id]);

                            PayrollAdjustment::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);

                            PayrollAdjustmentDetail::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('status', '!=', 3)
                                ->update(['payroll_id' => $data->id]);
                        }

                        $payroll_hourly_salary = PayrollHourlySalary::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])
                            ->update(['payroll_id' => $data->id]);
                        $payroll_overtimes = PayrollOvertime::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])
                            ->update(['payroll_id' => $data->id]);

                        $check = false;
                        if ($data->is_next_payroll == 1) {
                            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                                $adjustment_total = PayrollAdjustmentDetail::with('costcenter')
                                    ->where(function ($query) use ($data) {
                                        $query->where(['payroll_id' => $data->id, 'is_next_payroll' => 0])
                                            ->where(function ($subQuery) {
                                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                                    ->orWhereHas('costcenter', function ($q) {
                                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                                    });
                                            });
                                    })
                                    ->sum('amount');
                                $usercommissions = UserCommission::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id])->sum('amount');
                                $clawbackSum = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll'])->where('type', '!=', 'overrides')->sum('clawback_amount');
                                $overrides = UserOverrides::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2'])->sum('amount');
                                $reimbursement = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'adjustment_type_id' => '2'])->sum('amount');
                                $adjustmentToAdd = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
                                $adjustmentToNigative = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [5])->sum('amount');
                                $clawbackSumChange = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll'])->where('type', 'overrides')->sum('clawback_amount');
                                $customfieldSum = CustomField::where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id])->sum('value');
                                $hourlySalarySum = PayrollHourlySalary::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_next_payroll' => 0])->sum('total');
                                $overtimeSum = PayrollOvertime::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_next_payroll' => 0])->sum('total');
                            } else {
                                $adjustment_total = PayrollAdjustmentDetail::with('costcenter')
                                    ->where(function ($query) use ($data) {
                                        $query->where(['payroll_id' => $data->id, 'is_next_payroll' => 0])
                                            ->where(function ($subQuery) {
                                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                                    ->orWhereHas('costcenter', function ($q) {
                                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                                    });
                                            });
                                    })
                                    ->sum('amount');
                                $usercommissions = UserCommission::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                                $clawbackSum = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('type', '!=', 'overrides')->sum('clawback_amount');
                                $overrides = UserOverrides::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                                $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'adjustment_type_id' => '2', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                                $adjustmentToAdd = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
                                $adjustmentToNigative = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [5])->sum('amount');
                                $clawbackSumChange = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('type', 'overrides')->sum('clawback_amount');
                                $customfieldSum = CustomField::where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id])->sum('value');
                                $hourlySalarySum = PayrollHourlySalary::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('total');
                                $overtimeSum = PayrollOvertime::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('total');
                            }

                            if ($adjustment_total || $usercommissions || $clawbackSum || $overrides || $reimbursement || $adjustmentToAdd || $adjustmentToNigative || $clawbackSumChange || $customfieldSum || $hourlySalarySum || $overtimeSum) {
                                $check = true;
                                $data->update(['is_next_payroll' => 0]);
                            }
                        } elseif ($data->is_mark_paid == 1) {
                            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                                $adjustment_total = PayrollAdjustmentDetail::with('costcenter')
                                    ->where(function ($query) use ($data) {
                                        $query->where(['payroll_id' => $data->id, 'is_mark_paid' => 0])
                                            ->where(function ($subQuery) {
                                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                                    ->orWhereHas('costcenter', function ($q) {
                                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                                    });
                                            });
                                    })
                                    ->sum('amount');
                                $usercommissions = UserCommission::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->sum('amount');
                                $clawbackSum = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll'])->where('type', '!=', 'overrides')->sum('clawback_amount');
                                $overrides = UserOverrides::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2'])->sum('amount');
                                $reimbursement = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'adjustment_type_id' => '2'])->sum('amount');
                                $adjustmentToAdd = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
                                $adjustmentToNigative = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [5])->sum('amount');
                                $clawbackSumChange = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll'])->where('type', 'overrides')->sum('clawback_amount');
                                $customfieldSum = CustomField::where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->sum('value');
                                $hourlySalarySum = PayrollHourlySalary::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0])->sum('total');
                                $overtimeSum = PayrollOvertime::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0])->sum('total');
                            } else {
                                $adjustment_total = PayrollAdjustmentDetail::with('costcenter')
                                    ->where(function ($query) use ($data) {
                                        $query->where(['payroll_id' => $data->id, 'is_mark_paid' => 0])
                                            ->where(function ($subQuery) {
                                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                                    ->orWhereHas('costcenter', function ($q) {
                                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                                    });
                                            });
                                    })
                                    ->sum('amount');
                                $usercommissions = UserCommission::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                                $clawbackSum = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('type', '!=', 'overrides')->sum('clawback_amount');
                                $overrides = UserOverrides::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                                $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'adjustment_type_id' => '2', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                                $adjustmentToAdd = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
                                $adjustmentToNigative = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [5])->sum('amount');
                                $clawbackSumChange = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('type', 'overrides')->sum('clawback_amount');
                                $customfieldSum = CustomField::where(['payroll_id' => $data->id, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->sum('value');
                                $hourlySalarySum = PayrollHourlySalary::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('total');
                                $overtimeSum = PayrollOvertime::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('total');
                            }

                            if ($adjustment_total || $usercommissions || $clawbackSum || $overrides || $reimbursement || $adjustmentToAdd || $adjustmentToNigative || $clawbackSumChange || $customfieldSum || $hourlySalarySum || $overtimeSum) {
                                $check = true;
                                $data->update(['is_mark_paid' => 0]);
                            }
                        }

                        if (! $check && ($data->is_next_payroll >= 1 || $data->is_mark_paid == 1)) {
                            $updateData = [
                                'commission' => 0,
                                'override' => 0,
                                'reimbursement' => 0,
                                'adjustment' => 0,
                                'hourly_salary' => 0,
                                'overtime' => 0,
                                'reconciliation' => 0,
                                'gross_pay' => 0,
                                'net_pay' => 0,
                            ];
                        } else {
                            $userStop = User::select('id', 'stop_payroll')->where('id', $data->user_id)->first();
                            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                                $adjustment_total = PayrollAdjustmentDetail::with('costcenter')
                                    ->where(function ($query) use ($data) {
                                        $query->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0])
                                            ->where(function ($subQuery) {
                                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                                    ->orWhereHas('costcenter', function ($q) {
                                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                                    });
                                            });
                                    })
                                    ->sum('amount');
                                // changes due to MoveToNextPayroll
                                $usercommissions = UserCommission::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('amount');

                                $clawbackSum = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->where('type', '!=', 'overrides')->sum('clawback_amount');

                                $overrides = UserOverrides::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->sum('amount');

                                $reimbursement = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'adjustment_type_id' => '2', 'is_mark_paid' => 0])->sum('amount');

                                $adjustmentToAdd = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');

                                $adjustmentToNigative = ApprovalsAndRequest::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [5])->sum('amount');

                                $clawbackSumChange = ClawbackSettlement::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_move_to_recon' => 0])->where('type', 'overrides')->sum('clawback_amount');

                                $customfieldSum = CustomField::where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'is_mark_paid' => 0])->sum('value');

                                $hourlySalarySum = PayrollHourlySalary::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->sum('total');

                                $overtimeSum = PayrollOvertime::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->sum('total');

                                /* recon amount total display */
                                $reconAdjustmentSum = ReconciliationFinalizeHistory::where(['user_id' => $data->user_id, 'payroll_id' => $data->id])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('adjustments');
                                $reconDeductionSum = ReconciliationFinalizeHistory::where(['user_id' => $data->user_id, 'payroll_id' => $data->id])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('deductions');
                                $reconClawbackSum = ReconciliationFinalizeHistory::where(['user_id' => $data->user_id, 'payroll_id' => $data->id])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('clawback');
                                $reconCommissionSum = ReconCommissionHistory::where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'is_ineligible' => '0'])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('paid_amount');
                                $reconOverrideSum = ReconOverrideHistory::where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'is_ineligible' => '0'])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->sum('paid');
                                $reconFinalizeSum = $reconCommissionSum + $reconOverrideSum + $reconAdjustmentSum + $reconDeductionSum - $reconClawbackSum;
                            } else {
                                $adjustment_total = PayrollAdjustmentDetail::with('costcenter')
                                    ->where(function ($query) use ($data) {
                                        $query->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0])
                                            ->where(function ($subQuery) {
                                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                                    ->orWhereHas('costcenter', function ($q) {
                                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                                    });
                                            });
                                    })
                                    ->sum('amount');
                                // changes due to MoveToNextPayroll
                                $usercommissions = UserCommission::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 0])->sum('amount');
                                $clawbackSum = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 0])->where('type', '!=', 'overrides')->sum('clawback_amount');
                                $overrides = UserOverrides::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 0])->sum('amount');
                                $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'adjustment_type_id' => '2', 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                                $adjustmentToAdd = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
                                $adjustmentToNigative = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_mark_paid' => 0, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [5])->sum('amount');
                                $clawbackSumChange = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 0])->where('type', 'overrides')->sum('clawback_amount');
                                $customfieldSum = CustomField::where(['payroll_id' => $data->id, 'is_next_payroll' => 0, 'user_id' => $data->user_id, 'is_mark_paid' => 0])->sum('value');
                                $hourlySalarySum = PayrollHourlySalary::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 0])->sum('total');
                                $overtimeSum = PayrollOvertime::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_move_to_recon' => 0])->sum('total');

                                /* recon amount total display */
                                $reconAdjustmentSum = ReconciliationFinalizeHistory::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('payroll_id', $data->id)->sum('adjustments');
                                $reconDeductionSum = ReconciliationFinalizeHistory::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('payroll_id', $data->id)->sum('deductions');
                                $reconClawbackSum = ReconciliationFinalizeHistory::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('payroll_id', $data->id)->sum('clawback');
                                $reconCommissionSum = ReconCommissionHistory::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_ineligible' => '0'])->where('payroll_id', $data->id)->sum('paid_amount');
                                $reconOverrideSum = ReconOverrideHistory::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_ineligible' => '0'])->where('payroll_id', $data->id)->sum('paid');
                                $reconFinalizeSum = $reconCommissionSum + $reconOverrideSum + $reconAdjustmentSum - $reconDeductionSum - $reconClawbackSum;
                            }

                            $clawbackSumChange = (0 - $clawbackSumChange);
                            $overrides = $overrides + $clawbackSumChange;
                            $adjustment = ($adjustmentToAdd - $adjustmentToNigative) + ($adjustment_total);
                            $usercommission = ($usercommissions - $clawbackSum);

                            // Add Custom Field In NetPay
                            $dataCustomField = CustomField::where(['user_id' => $data->user_id, 'payroll_id' => $data->id])->sum('value');
                            $net_pay = $usercommission + $overrides + $adjustment + $reimbursement + $dataCustomField + $hourlySalarySum + $overtimeSum + $reconFinalizeSum;
                            $gross_pay = $usercommission + $overrides + $adjustment + $dataCustomField + $hourlySalarySum + $overtimeSum + $reconFinalizeSum;

                            $updateData = [
                                'commission' => $usercommission,
                                'override' => $overrides,
                                'reimbursement' => $reimbursement,
                                'adjustment' => $adjustment,
                                'custom_payment' => $customfieldSum,
                                'hourly_salary' => $hourlySalarySum,
                                'overtime' => $overtimeSum,
                                'reconciliation' => $reconFinalizeSum,
                                'gross_pay' => $gross_pay,
                                'net_pay' => $net_pay,
                                'is_stop_payroll' => $userStop->stop_payroll ?? 0,
                            ];
                        }

                        Payroll::where(['user_id' => $data->user_id, 'id' => $data->id])->update($updateData);

                        DB::commit();
                    } catch (Exception $e) {
                        DB::rollBack();
                    }
                }
            }
        }
    }

    public function getPayrollData(Request $request)
    {

        $companyProfile = CompanyProfile::first();
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'get_payroll_data',
                'status' => false,
                'error' => $validator->errors(),
            ], 400);
        }

        $data = [];
        $payroll_total = 0;
        $workerType = '1099';
        $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
        $positions = $request->input('position_filter');
        $netPay = $request->input('netpay_filter');
        $commission = $request->input('commission_filter');
        $pay_frequency = $request->input('pay_frequency');
        $usersids = User::whereIn('sub_position_id', function ($query) use ($pay_frequency) {
            $query->select('position_id')
                ->from('position_pay_frequencies')
                ->where('frequency_type_id', $pay_frequency);
        })->pluck('id');
        if (! empty($request->input('perpage'))) {
            $perpage = $request->input('perpage');
        } else {
            $perpage = 10;
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $fullName = $request->input('search');
        $search_full_name = removeMultiSpace($fullName);
        $type = $request->input('type');
        $everee_webhook_message = '';
        $everee_payment_status = 0;
        $emp_total = 0;

        $payrollHistoryCount = PayrollHistory::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
            ->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            })->count();
        if ($payrollHistoryCount == 0) {
            $this->user_salary_create($start_date, $end_date, $workerType);
        }

        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 3)->first();
        if (! isset($request['manually'])) {
            $request['manually'] = 1;
            $info = $this->getPayrollDataForEmployees($request);
            $responseData = json_decode($info->getContent(), true);
            $emp_total = (isset($responseData['data']) && is_array($responseData['data']) && isset($responseData['data']['total'])) ? $responseData['data']['total'] : 0;
        }

        // condition for one time payment

        $oneTimePaymentData = PayrollHistory::with('workertype', 'usersdata.office.State')->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
            $query->whereBetween('pay_period_from', [$start_date, $end_date])
                ->whereBetween('pay_period_to', [$start_date, $end_date])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($start_date, $end_date) {
            $query->where([
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
            ]);
        })
            ->where(['status' => 3, 'is_onetime_payment' => 1])
            ->when($search_full_name && ! empty($search_full_name), function ($q) use ($search_full_name) {
                $q->whereHas('usersdata', function ($query) use ($search_full_name) {
                    $query->where(function ($subQuery) use ($search_full_name) {
                        $subQuery->where('first_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere('last_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                    });
                });
            })
        // ->whereHas('workertype', function($q) use($workerType){
            // $q->where('worker_type', $workerType);
        // })
            ->get();

        if ($oneTimePaymentData) {
            $total_custom = 0;
            foreach ($oneTimePaymentData as $oneTimePaymentDataKey => $oneTimePaymentDataValue) {

                $custom_filed = [];
                $setting = PayrollSsetup::where('worked_type', 'LIKE', '%'.$workerTypeValue.'%')->orderBy('id', 'Asc')->get();
                if ($setting) {
                    foreach ($setting as $value) {
                        $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $oneTimePaymentDataValue->payroll_id])->latest('id')->first();

                        if ($this->check_open_status_from_bank($start_date, $end_date, $pay_frequency)) {
                            $payroll_data = CustomFieldHistory::where(['column_id' => $value['id'], 'payroll_id' => $oneTimePaymentDataValue->payroll_id])->latest('id')->first();
                        }
                        $total_custom += @$payroll_data->value;

                        $custom_filed[] = [
                            'id' => @$value['id'],
                            'field_name' => @$value['field_name'],
                            'comment' => @$payroll_data['comment'],
                            'value' => @$payroll_data->value,
                            'worker_type' => @$value['worked_type'],
                        ];
                    }
                }

                $onetime_payment_result_data[] = [
                    'id' => $oneTimePaymentDataValue->id,
                    'payroll_id' => $oneTimePaymentDataValue->payroll_id,
                    'user_id' => $oneTimePaymentDataValue->user_id,
                    'first_name' => isset($oneTimePaymentDataValue->usersdata) ? $oneTimePaymentDataValue->usersdata->first_name : null,
                    'last_name' => isset($oneTimePaymentDataValue->usersdata) ? $oneTimePaymentDataValue->usersdata->last_name : null,
                    'position_id' => isset($oneTimePaymentDataValue->usersdata) ? $oneTimePaymentDataValue->usersdata->position_id : null,
                    'sub_position_id' => isset($oneTimePaymentDataValue->usersdata) ? $oneTimePaymentDataValue->usersdata->sub_position_id : null,
                    'is_super_admin' => isset($oneTimePaymentDataValue->usersdata) ? $oneTimePaymentDataValue->usersdata->is_super_admin : null,
                    'is_manager' => isset($oneTimePaymentDataValue->usersdata) ? $oneTimePaymentDataValue->usersdata->is_manager : null,
                    'image' => isset($oneTimePaymentDataValue->usersdata) ? $oneTimePaymentDataValue->usersdata->image : null,
                    // 'image_s3' => $s3_image,
                    'position' => isset($oneTimePaymentDataValue->positionDetail) ? $oneTimePaymentDataValue->positionDetail->position_name : null,
                    'commission' => isset($oneTimePaymentDataValue->commission) ? $oneTimePaymentDataValue->commission * 1 : 0,
                    'override' => isset($oneTimePaymentDataValue->override) ? $oneTimePaymentDataValue->override * 1 : 0,
                    'override_value_is_higher' => 0,
                    'adjustment' => isset($oneTimePaymentDataValue->adjustment) ? $oneTimePaymentDataValue->adjustment * 1 : 0,
                    'reimbursement' => isset($oneTimePaymentDataValue->reimbursement) ? $oneTimePaymentDataValue->reimbursement * 1 : 0,
                    'clawback' => isset($data->clawback) ? $oneTimePaymentDataValue->clawback * 1 : 0,
                    'deduction' => isset($oneTimePaymentDataValue->deduction) ? $oneTimePaymentDataValue->deduction * 1 : 0,
                    'reconciliation' => isset($oneTimePaymentDataValue->reconciliation) ? $oneTimePaymentDataValue->reconciliation * 1 : 0,
                    'net_pay' => round($oneTimePaymentDataValue->net_pay, 2),
                    'gross_pay' => 0,
                    'status_id' => $oneTimePaymentDataValue->status,
                    'status' => isset($oneTimePaymentDataValue->payrollstatus) ? $oneTimePaymentDataValue->payrollstatus->status : null,
                    'is_mark_paid' => isset($oneTimePaymentDataValue->is_mark_paid) ? $oneTimePaymentDataValue->is_mark_paid : 0,
                    'is_next_payroll' => isset($oneTimePaymentDataValue->is_next_payroll) ? $oneTimePaymentDataValue->is_next_payroll : 0,
                    'created_at' => $oneTimePaymentDataValue->created_at,
                    'updated_at' => $oneTimePaymentDataValue->updated_at,
                    'custom_filed' => $custom_filed,
                    'commission_yellow_status' => ($oneTimePaymentDataValue->commission_count >= 1 || $oneTimePaymentDataValue->clawback_count >= 1) ? 1 : 0,
                    'override_yellow_status' => ($oneTimePaymentDataValue->override_count >= 1) ? 1 : 0,
                    'approve_request_yellow_status' => ($oneTimePaymentDataValue->approve_request_count >= 1 || $oneTimePaymentDataValue->payroll_adjustment_details_count >= 1) ? 1 : 0,
                    'reimbursement_yellow_status' => ($oneTimePaymentDataValue->reimbursement_count >= 1) ? 1 : 0,
                    'deduction_yellow_status' => 0,
                    'paid_next' => 'comm-'.$oneTimePaymentDataValue->commission_count.' ,over-'.$oneTimePaymentDataValue->override_count.' ,claw-'.$oneTimePaymentDataValue->clawback_count.' , appr-'.$oneTimePaymentDataValue->approve_request_count.' ,reimb-'.$oneTimePaymentDataValue->reimbursement_count,
                    'total_custom' => $total_custom,
                    'everee_response' => $everee_webhook_message,
                    'everee_payment_status' => $everee_payment_status,
                    'hourly_salary' => isset($data->hourly_salary) ? $oneTimePaymentDataValue->hourly_salary : 0,
                    'overtime' => isset($data->overtime) ? $oneTimePaymentDataValue->overtime : 0,
                    'worker_type' => isset($workerType) ? $workerType : null,
                    // 'everee_onboarding_process' => $everee_onboarding_process,
                    'is_onetime_payment' => isset($oneTimePaymentDataValue->is_onetime_payment) ? $oneTimePaymentDataValue->is_onetime_payment : 0,
                    'office_name' => $oneTimePaymentDataValue?->usersdata?->office?->office_name, // Returns null if usersdata or office is null
                    'state_name' => $oneTimePaymentDataValue?->usersdata?->office?->State?->name, // Returns null if usersdata, office, or State is null
                    'state_code' => $oneTimePaymentDataValue?->usersdata?->office?->State?->state_code, // Returns null if usersdata, office, or State is null
                ];
            }
        }

        if ($type == 'pid') {
            // everee code start
            $payrollHistory = PayrollHistory::with('workertype')->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })
                ->where(['pay_type' => 'Bank'])->whereIn('everee_payment_status', [1, 2])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->pluck('payroll_id');

            if (count($payrollHistory) > 0) {
                $commissionPayrolls = UserCommissionLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $overridePayrolls = UserOverridesLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $clawbackPayrolls = ClawbackSettlementLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $adjustmentDetailsPayrolls = PayrollAdjustmentDetailLock::with('saledata')
                    ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $data = [];
                foreach ($commissionPayrolls as $commissionPayroll) {
                    $commissionPayroll['data_type'] = 'commission';
                    $data[$commissionPayroll['pid']][] = $commissionPayroll;
                }
                foreach ($overridePayrolls as $overridePayroll) {
                    $overridePayroll['data_type'] = 'override';
                    $data[$overridePayroll['pid']][] = $overridePayroll;
                }
                foreach ($clawbackPayrolls as $clawbackPayroll) {
                    $clawbackPayroll['data_type'] = 'clawback';
                    $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
                }
                foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                    $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                    $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
                }

                $finalData = [];
                $payrollTotal = 0;
                foreach ($data as $key => $data) {
                    $commission = 0;
                    $override = 0;
                    $adjustment = 0;

                    $commissionNoPaid = 0;
                    $overrideNoPaid = 0;
                    $adjustmentNoPaid = 0;

                    $commissionColor = 0;
                    $overrideColor = 0;
                    $adjustmentColor = 0;
                    $isMarkPaid = 0;
                    $isNextPayroll = 0;
                    $total = 0;
                    $loan_amount = 0;
                    $net_epc = 0;
                    foreach ($data as $inner) {
                        if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                                if (! $commissionColor) {
                                    $commissionColor = 1;
                                }
                            } else {
                                if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                    $commissionNoPaid += (0 - $inner['clawback_amount']);
                                } else {
                                    $commissionNoPaid += $inner['amount'];
                                }
                            }
                            $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                        } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                                if (! $overrideColor) {
                                    $overrideColor = 1;
                                }
                            } else {
                                if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                    $overrideNoPaid += (0 - $inner['clawback_amount']);
                                } else {
                                    $overrideNoPaid += $inner['amount'];
                                }
                            }
                            $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                        } elseif ($inner['data_type'] == 'adjustment') {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                                if (! $adjustmentColor) {
                                    $adjustmentColor = 1;
                                }
                            } else {
                                $adjustmentNoPaid += $inner['amount'];
                            }
                            $adjustment += $inner['amount'];
                        }

                        $total += 1;
                        if ($inner['is_mark_paid'] >= 1) {
                            $isMarkPaid += 1;
                        }
                        if ($inner['is_next_payroll'] >= 1) {
                            $isNextPayroll += 1;
                        }
                    }

                    if ($commission || $override || $adjustment) {
                        $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                        $payrollTotal += $netPayAmount;

                        $finalData[] = [
                            'pid' => $key,
                            'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                            'commission' => round($commissionNoPaid, 2),
                            'override' => round($overrideNoPaid, 2),
                            'adjustment' => round($adjustmentNoPaid, 2),
                            'net_pay' => round($netPayAmount, 2),
                            'gross_pay' => round($netPayAmount, 2),
                            'is_mark_paid' => ($total == $isMarkPaid) ? 1 : 0,
                            'is_next_payroll' => ($total == $isNextPayroll) ? 1 : 0,
                            'commission_yellow_status' => $commissionColor,
                            'override_yellow_status' => $overrideColor,
                            'adjustment_yellow_status' => $adjustmentColor,
                            'deduction_yellow_status' => 0,
                            'status_id' => 3,
                            'everee_response' => '',
                            'loan_amount' => @$data[0]['saledata']['gross_account_value'] ?? null,
                            'net_epc' => @$data[0]['saledata']['net_epc'] ?? null,
                        ];
                    }
                }

                $data = paginate($finalData, $perpage);

                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'Successfully.',
                    'finalize_status' => null,
                    'payment_failed' => 1,
                    'data' => $data,
                    'all_paid' => null,
                    'all_next' => null,
                    'payroll_total' => round($payroll_total, 2),
                    'second_total' => $emp_total,
                    'total_alert_count' => isset($total_alert_count) ? $total_alert_count : 0,
                ]);
            }

            // CHECKING PAYROLL IS CLOSED OR NOT FOR THE GIVEN PAY PERIOD.
            $checkPayrollClosedStatus = $this->check_payroll_closed_status($start_date, $end_date, $pay_frequency, $workerType);
            if (! empty($checkPayrollClosedStatus)) {
                return $checkPayrollClosedStatus;
            }

            // Payroll Zero Data update
            $this->updatePayrollZeroData($start_date, $end_date, $pay_frequency);
            // End Payroll Zero Data update

            // UPDATING PAYROLL RECORDS AMOUNT VALUE IN PAYROLL FOR THE GIVEN PAY PERIODS.
            if (! empty($start_date) && ! empty($end_date)) {
                $this->update_payroll_data($start_date, $end_date, $pay_frequency);
            }

            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                $commissionPayrolls = UserCommission::with('saledata', 'userdata')->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $overridePayrolls = UserOverrides::with('saledata', 'userdata')->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $clawbackPayrolls = ClawbackSettlement::with('saledata', 'userdata')->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $adjustmentDetailsPayrolls = PayrollAdjustmentDetail::with('saledata', 'userdata')->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to')->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();
            } else {
                $commissionPayrolls = UserCommission::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $overridePayrolls = UserOverrides::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $clawbackPayrolls = ClawbackSettlement::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $adjustmentDetailsPayrolls = PayrollAdjustmentDetail::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                    ->whereHas('userdata', function ($q) use ($workerType) {
                        $q->where('worker_type', $workerType);
                    })
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })
                    ->get();
            }

            $data = [];
            foreach ($commissionPayrolls as $commissionPayroll) {
                $commissionPayroll['data_type'] = 'commission';
                $data[$commissionPayroll['pid']][] = $commissionPayroll;
            }
            foreach ($overridePayrolls as $overridePayroll) {
                $overridePayroll['data_type'] = 'override';
                $data[$overridePayroll['pid']][] = $overridePayroll;
            }
            foreach ($clawbackPayrolls as $clawbackPayroll) {
                $clawbackPayroll['data_type'] = 'clawback';
                $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
            }
            foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
            }

            $finalData = [];
            $payrollTotal = 0;
            foreach ($data as $key => $data) {
                $commission = 0;
                $override = 0;
                $adjustment = 0;

                $commissionNoPaid = 0;
                $overrideNoPaid = 0;
                $adjustmentNoPaid = 0;

                $commissionColor = 0;
                $overrideColor = 0;
                $adjustmentColor = 0;
                $payrollIds = [];
                $isMarkPaid = 0;
                $isNextPayroll = 0;
                $total = 0;
                $loan_amount = 0;
                $net_epc = 0;
                foreach ($data as $inner) {
                    if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                            if (! $commissionColor) {
                                $commissionColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                $commissionNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $commissionNoPaid += $inner['amount'];
                            }
                        }
                        $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                            if (! $overrideColor) {
                                $overrideColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                $overrideNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $overrideNoPaid += $inner['amount'];
                            }
                        }
                        $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'adjustment') {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                            if (! $adjustmentColor) {
                                $adjustmentColor = 1;
                            }
                        } else {
                            $adjustmentNoPaid += $inner['amount'];
                        }
                        $adjustment += $inner['amount'];
                    }

                    $payrollIds[] = $inner['payroll_id'];
                    $total += 1;
                    if ($inner['is_mark_paid'] >= 1) {
                        $isMarkPaid += 1;
                    }
                    if ($inner['is_next_payroll'] >= 1) {
                        $isNextPayroll += 1;
                    }
                }

                $status = 1;

                if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                    $payroll = Payroll::whereIn('id', $payrollIds)->where(['status' => '2', 'finalize_status' => '2', 'is_stop_payroll' => 0])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->first();
                } else {
                    $payroll = Payroll::whereIn('id', $payrollIds)->where(['status' => '2', 'finalize_status' => '2', 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->first();
                }

                if ($payroll) {
                    $status = 2;
                }
                if ($commission || $override || $adjustment) {
                    $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                    $payrollTotal += $netPayAmount;

                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                        $loan_amount = @$data[0]['saledata']['gross_account_value'] ?? 0;
                        $net_epc = round(@$data[0]['saledata']['net_epc'] * 100, 4) ?? 0;
                    }

                    $finalData[] = [
                        'pid' => $key,
                        'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                        'commission' => round($commissionNoPaid, 2),
                        'override' => round($overrideNoPaid, 2),
                        'adjustment' => round($adjustmentNoPaid, 2),
                        'net_pay' => round($netPayAmount, 2),
                        'gross_pay' => round($netPayAmount, 2),
                        'is_mark_paid' => ($total == $isMarkPaid) ? 1 : 0,
                        'is_next_payroll' => ($total == $isNextPayroll) ? 1 : 0,
                        'commission_yellow_status' => $commissionColor,
                        'override_yellow_status' => $overrideColor,
                        'adjustment_yellow_status' => $adjustmentColor,
                        'status_id' => $status,
                        'data_type' => $inner['data_type'],
                        'deduction_yellow_status' => 0,
                        'loan_amount' => $loan_amount,
                        'net_epc' => $net_epc,
                    ];
                }
            }

            if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
                $finalData = collect($finalData)->where('net_pay', '<', 0)->values()->toArray();
            }

            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                $allPaidNextData = Payroll::where(['is_stop_payroll' => 0])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')
                    ->selectRaw('SUM(CASE WHEN is_mark_paid = 0 THEN 1 ELSE 0 END) as is_mark_paid, SUM(CASE WHEN is_next_payroll = 0 THEN 1 ELSE 0 END) as is_next_payroll')->first();
                $isPayrollData = Payroll::where(['is_stop_payroll' => 0])->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->first();
            } else {
                $allPaidNextData = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])
                    ->selectRaw('SUM(CASE WHEN is_mark_paid = 0 THEN 1 ELSE 0 END) as is_mark_paid, SUM(CASE WHEN is_next_payroll = 0 THEN 1 ELSE 0 END) as is_next_payroll')->first();
                $isPayrollData = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->first();
            }

            // CHECKING ALL PAYROLLS ARE PAID OR NOT, FOR FINALIZING PROCESS.
            $allPaid = false;
            if ($isPayrollData && $allPaidNextData->is_mark_paid == 0) {
                $allPaid = true;
            }

            // CHECKING ALL PAYROLLS ARE Moved To Next OR NOT, FOR FINALIZING PROCESS.
            $allNext = false;
            if ($isPayrollData && $allPaidNextData->is_next_payroll == 0) {
                $allNext = true;
            }

            // plz dont delete this function
            // $this->deduction_for_all_deduction_enable_users($start_date,$end_date,$pay_frequency);

            $checkFinalizeStatus = payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->where('status', '>', 1)->first() ? 1 : 0;
            $crmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($crmData) {
                EvereeOnboardingUsersJob::Dispatch($start_date, $end_date);
                // $paydata = Payroll::with('usersdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
                // if (count($paydata) > 0) {
                //     event(new EvereeOnboardingUserEvent($paydata, '1'));
                // }
            }

            $totalAlertCount = LegacyApiNullData::select(
                DB::raw('count(`sales_alert`) as sales_alert'),
                DB::raw('count(`missingrep_alert`) as missingrep'),
                DB::raw('count(`closedpayroll_alert`) as closedpayroll'),
                DB::raw('count(`locationredline_alert`) as locationredline'),
                DB::raw('count(`repredline_alert`) as repredline')
            )->where(function ($query) use ($request) {
                $query->whereRaw("`m1_date` >= '".$request->start_date."' AND `m1_date` <= '".$request->end_date."'")
                    ->orWhereRaw("`m2_date` >= '".$request->start_date."' AND `m2_date` <= '".$request->end_date."'");
            })->whereNotNull('data_source_type')->first();

            payroll::where('status', 2)->where('finalize_status', '!=', 2)->update(['finalize_status' => 2]);
            payroll::where(['status' => 1, 'finalize_status' => 2])->update(['finalize_status' => 0]);

            // Check if both 'sort' (column name) and 'sort_val' (sorting direction) are present in the request
            if (! empty($request->has('sort')) && ! empty($request->has('sort_val')) && ! empty($finalData)) {
                // Get the column to sort by from the request
                $sortKey = $request->get('sort');

                // Determine the sorting direction; default to 'asc' if not explicitly 'desc'
                $sortDirection = strtolower($request->get('sort_val')) === 'desc' ? 'desc' : 'asc';

                // Call the custom helper function to apply sorting on the finalData array
                \applyPayrollSorting($finalData, $sortKey, $sortDirection);
            }

            $finalData = paginate($finalData, $perpage);

            return response()->json([
                'ApiName' => 'get_payroll_data',
                'status' => true,
                'message' => 'Successfully.',
                'finalize_status' => isset($checkFinalizeStatus) ? $checkFinalizeStatus : null,
                'payment_failed' => 0,
                'data' => $finalData,
                'all_paid' => $allPaid,
                'all_next' => $allNext,
                'payroll_total' => round($payrollTotal, 2),
                'second_total' => $emp_total,
                'total_alert_count' => isset($totalAlertCount) ? $totalAlertCount : 0,
            ]);
        } else {
            // This condition will work when payroll failed and everee_workerId not null in the user records. Added by anurag under the aneesh guidance.
            if (! empty($CrmData) && ! empty($CrmSetting)) { // && $request['everee_failed_status']!=1
                $payrollHistoryForclosedstatus = PayrollHistory::with(['user'])->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'pay_type' => 'Bank', 'everee_status' => '2'])->whereIn('everee_payment_status', [1, 2])->get();
                if ($payrollHistoryForclosedstatus) {
                    foreach ($payrollHistoryForclosedstatus as $payrollHistoryForclosedstatusValue) {
                        if ($payrollHistoryForclosedstatusValue->user->everee_workerId != null && $payrollHistoryForclosedstatusValue->user->everee_workerId != '') {
                            PayrollFailedRecordsProcess::Dispatch($payrollHistoryForclosedstatusValue->user->id);
                        }
                    }
                }
            }

            // everee code start
            $paymemntFailedStatus = false;
            $payrollHistory = PayrollHistory::with('workertype', 'usersdata.office.State')->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })
                ->whereIn('user_id', $usersids)->where(['pay_type' => 'Bank'])->whereIn('everee_payment_status', [1, 2])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->whereHas('usersdata', function ($q) use ($search_full_name) {
                    $q->where(function ($query) use ($search_full_name) {
                        $query->where('first_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere('last_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                    });
                })->get();

            if (count($payrollHistory) > 0) {
                // Check if all rows have everee_payment_status of 1
                $paymemntFailedStatus = collect($payrollHistory)->every(function ($history) {
                    return $history->everee_payment_status == 1;
                });
                if ($paymemntFailedStatus) {
                    $paymemntFailedStatus = true;
                } else {
                    $paymemntFailedStatus = false;
                }
            } else {
                $paymemntFailedStatus = false;
            }

            $total_custom = 0;
            if (count($payrollHistory) > 0) {
                $payroll_total = $payrollHistory->sum('net_pay');
                $total_custom = 0;
                foreach ($payrollHistory as $payrollHistoryKey => $data) {
                    $everee_payment_status = $data->everee_payment_status;
                    if ($data->everee_payment_status == 3) {
                        $everee_webhook_message = 'Payment Success';

                    } elseif ($data->everee_payment_status == 2 && $data->everee_status == 2 && ($data->everee_webhook_json == null || $data->everee_webhook_json == '')) {
                        // Differentiate between profile completion and self-onboarding completion
                        $user = $data->user ?? null;
                        if (!$user || !$user->onboardProcess) {
                            // Self-onboarding completion - user hasn't completed Everee self-onboarding
                            $everee_webhook_message = 'Payment will be processed once the user has logged in and completed the self-onboarding steps, confirming all required details.';
                        } else {
                            // Default fallback message
                            $everee_webhook_message = 'Payment will be processed once the user profile is fully completed.';
                        }
                    } elseif ($data->everee_payment_status == 2 && $data->everee_webhook_json != null && $data->everee_webhook_json != '') {
                        $everee_webhook_data = json_decode($data->everee_webhook_json, true);
                        if (isset($everee_webhook_data['paymentStatus']) && $everee_webhook_data['paymentStatus'] == 'ERROR') {
                            $everee_webhook_message = $everee_webhook_data['paymentErrorMessage'] ?? null;
                        } else {
                            $everee_webhook_message = $data->everee_webhook_json;
                        }
                    } elseif ($data->everee_payment_status == 1) {
                        $everee_webhook_message = 'Waiting for payment status to be updated.';
                    } elseif ($data->everee_payment_status == 0) {
                        $everee_webhook_message = 'External payment processing is disabled. Payment completed locally';
                    }

                    $custom_filed = [];
                    $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                    if ($setting) {
                        foreach ($setting as $value) {
                            $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $data->payroll_id])->first();

                            if ($this->check_open_status_from_bank($start_date, $end_date, $pay_frequency)) {
                                $payroll_data = CustomFieldHistory::where(['column_id' => $value['id'], 'payroll_id' => $data->payroll_id])->first();
                            }
                            $total_custom += @$payroll_data->value;

                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => @$value['field_name'],
                                'comment' => @$payroll_data['comment'],
                                'value' => @$payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }
                    }

                    $yellow_status = 0;
                    $s3_image = (isset($data->usersdata->image) && $data->usersdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image) : null;

                    // Determine onboarding process status
                    $everee_onboarding_process = 0;
                    $errorMsg = null;

                    if ($CrmData && $workerType == '1099' && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId)) {
                        $everee_onboarding_process = 1;
                    } elseif ($CrmData && ($workerType == 'w2' || $workerType == 'W2') && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId) && $data->usersdata->everee_embed_onboard_profile == 1) {
                        $everee_onboarding_process = 1;
                    }

                    // Always check for errors regardless of everee_workerId status
                    // This ensures we catch users with incomplete information
                    if ($CrmData) {
                        $evereeErrors = checkEvereeErrorStructured($data->user_id);
                        if ($evereeErrors) {
                            $errorMsg = implode(', ', array_values($evereeErrors));
                            $everee_onboarding_process = 0; // Override to 0 if there are errors

                            // Check for unauthorized error and replace with custom message
                            if ($errorMsg && strpos($errorMsg, 'unauthorized; Full authentication is required to access this resource') !== false) {
                                $errorMsg = 'Online Payroll Processing isn\'t available at the moment., Please contact your Customer Success representative for assistance.';
                            }
                        }
                    }

                    // Calculate total_sales and total_gross_amount using service (Path B - Finalized/Failed Payment)
                    // Only run for Pest company type
                    if ($companyProfile && $companyProfile->company_type === 'Pest') {
                        $payrollCalculationService = app(PayrollCalculationService::class);
                        $commissionTotals = $payrollCalculationService->calculateUserCommissionTotalsFromLocked(
                            $data->user_id,
                            $start_date,
                            $end_date,
                            $pay_frequency
                        );

                        $total_sales = $commissionTotals['total_sales'];
                        $total_gross_amount = $commissionTotals['total_gross_amount'];
                    } else {
                        $total_sales = 0;
                        $total_gross_amount = 0;
                    }

                    $result_data[] = [
                        'id' => $data->id,
                        'payroll_id' => $data->payroll_id,
                        'user_id' => $data->user_id,
                        'approvals_and_requests_status' => 0,
                        'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                        'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                        'position_id' => isset($data->usersdata) ? $data->usersdata->position_id : null,
                        'sub_position_id' => isset($data->usersdata) ? $data->usersdata->sub_position_id : null,
                        'is_super_admin' => isset($data->usersdata) ? $data->usersdata->is_super_admin : null,
                        'is_manager' => isset($data->usersdata) ? $data->usersdata->is_manager : null,
                        'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                        'image_s3' => $s3_image,
                        'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                        'commission' => isset($data->commission) ? $data->commission * 1 : 0,
                        'override' => isset($data->override) ? $data->override * 1 : 0,
                        'override_value_is_higher' => 0,
                        'adjustment' => isset($data->adjustment) ? $data->adjustment * 1 : 0,
                        'reimbursement' => isset($data->reimbursement) ? $data->reimbursement * 1 : 0,
                        'clawback' => isset($data->clawback) ? $data->clawback * 1 : 0,
                        'deduction' => isset($data->deduction) ? $data->deduction * 1 : 0,
                        'reconciliation' => isset($data->reconciliation) ? $data->reconciliation * 1 : 0,
                        'net_pay' => round($data->net_pay, 2),
                        'gross_pay' => 0,
                        'status_id' => $data->status,
                        'status' => isset($data->payrollstatus) ? $data->payrollstatus->status : null,
                        'is_mark_paid' => isset($data->is_mark_paid) ? $data->is_mark_paid : 0,
                        'is_next_payroll' => isset($data->is_next_payroll) ? $data->is_next_payroll : 0,
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                        'custom_filed' => $custom_filed,
                        'commission_yellow_status' => ($data->commission_count >= 1 || $data->clawback_count >= 1) ? 1 : 0,
                        'override_yellow_status' => ($data->override_count >= 1) ? 1 : 0,
                        'approve_request_yellow_status' => ($data->approve_request_count >= 1 || $data->payroll_adjustment_details_count >= 1) ? 1 : 0,
                        'reimbursement_yellow_status' => ($data->reimbursement_count >= 1) ? 1 : 0,
                        'deduction_yellow_status' => 0,
                        'paid_next' => 'comm-'.$data->commission_count.' ,over-'.$data->override_count.' ,claw-'.$data->clawback_count.' , appr-'.$data->approve_request_count.' ,reimb-'.$data->reimbursement_count,
                        'total_custom' => $total_custom,
                        'everee_response' => $everee_webhook_message,
                        'everee_payment_status' => $everee_payment_status,
                        'hourly_salary' => isset($data->hourly_salary) ? $data->hourly_salary : 0,
                        'overtime' => isset($data->overtime) ? $data->overtime : 0,
                        'worker_type' => isset($workerType) ? $workerType : null,
                        'everee_onboarding_process' => $everee_onboarding_process,
                        'everee_onboarding_process_errorMsg' => $errorMsg,
                        'is_onetime_payment' => isset($data->is_onetime_payment) ? $data->is_onetime_payment : 0,
                        'office_name' => $data?->usersdata?->office?->office_name, // Returns null if usersdata or office is null
                        'state_name' => $data?->usersdata?->office?->State?->name, // Returns null if usersdata, office, or State is null
                        'state_code' => $data?->usersdata?->office?->State?->state_code, // Returns null if usersdata, office, or State is null
                        'total_sales' => $total_sales,
                        'total_gross_amount' => round($total_gross_amount, 2),
                    ];
                }

                $data = paginate($result_data, $perpage);

                // $data = $payrollHistory;
                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'Successfully.',
                    'finalize_status' => null,
                    'payment_failed' => 1,
                    'payment_proccess' => $paymemntFailedStatus ? 1 : 0,
                    'data' => $data,
                    'all_paid' => null,
                    'all_next' => null,
                    'payroll_total' => round($payroll_total, 2),
                    'second_total' => $emp_total,
                    'total_alert_count' => 0,
                ]);
            }

            // CHECKING PAYROLL IS CLOSED OR NOT FOR THE GIVEN PAY PERIOD.
            $check_payroll_closed_status = $this->check_payroll_closed_status($start_date, $end_date, $pay_frequency, $workerType);
            if (! empty($check_payroll_closed_status)) {
                return $check_payroll_closed_status;
            }

            // Payroll Zero Data update
            $this->updatePayrollZeroData($start_date, $end_date, $pay_frequency);
            // End Payroll Zero Data update

            // UPDATING PAYROLL RECORDS AMOUNT VALUE IN PAYROLL FOR THE GIVEN PAY PERIODS.
            if (! empty($start_date) && ! empty($end_date) && ! empty($pay_frequency)) {
                $this->update_payroll_data($start_date, $end_date, $pay_frequency);
            }
            // plz dont delete this function
            $this->deduction_for_all_deduction_enable_users($start_date, $end_date, $pay_frequency, $workerType, $usersids);

            // CHECKING ALL PAYROLLS ARE PAID OR NOT , FOR FINALIZING PROCESS.
            $all_paid = false;
            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                $data_query = Payroll::with('workertype')->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                });
            } else {
                $data_query = Payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                });
            }
            $count_data = $data_query->whereIn('user_id', $usersids)->count();
            if ($count_data > 0) {
                $all_paid_count = $data_query->where('is_mark_paid', '0')->count();
                if ($all_paid_count == 0) {
                    $all_paid = true;
                }
            }

            // CHECKING ALL PAYROLLS ARE Moved To Next OR NOT, FOR FINALIZING PROCESS.
            $all_next = false;
            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                $data_query = Payroll::with('workertype')->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                });
            } else {
                $data_query = Payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                });
            }
            $count_data = $data_query->whereIn('user_id', $usersids)->count();
            if ($count_data > 0) {
                $all_paid_count = $data_query->where('is_next_payroll', '0')->count();
                if ($all_paid_count == 0) {
                    $all_next = true;
                }
            }

            $users = User::orderBy('id', 'asc');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $users->where(function ($query) use ($search_full_name) {
                    return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere('first_name', 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                });
            }
            $userArray = $users->pluck('id')->toArray();

            $checkFinalizeStatus = 0;
            $data_query = Payroll::with( // 'payrolladjust' , 'PayrollShiftHistorie', 'reconciliationInfo'
                'usersdata.office.State',
                'payrollstatus',
                'approvalRequest',
                'workertype'
            )
                ->whereIn('user_id', $usersids)
                ->whereIn('user_id', $userArray)
                ->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->whereHas('usersdata', function ($q) use ($search_full_name) {
                    $q->where(function ($query) use ($search_full_name) {
                        $query->where('first_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere('last_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                    });
                })
                // ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->where('id', '!=', 0)
                ->with(['userPayrollAdjustmentDetails' => function ($query) {
                    $query->with(['costcenter' => function ($subQuery) {
                        $subQuery->where('status', 1);
                    }])->orWhereNull('cost_center_id'); // Ensures records with NULL cost_center_id are retrieved
                }])
                ->with(['userDeductions' => function ($query) {
                    $query->with(['costcenter' => function ($subQuery) {
                        $subQuery->where('status', 1);
                    }]);
                }])
                ->withCount([
                    'userCommission as commission_count' => function ($query) {
                        $query->whereColumn('user_commission.user_id', 'payrolls.user_id')
                            ->where(function ($q) {
                                $q->where('is_mark_paid', '>=', 1)
                                    ->orWhere('is_next_payroll', '>=', 1)
                                    ->orWhere('is_move_to_recon', '>=', 1)
                                    ->orWhere('is_onetime_payment', '>=', 1);
                            });
                    },
                    'userOverride as override_count' => function ($query) {
                        $query->whereColumn('user_overrides.user_id', 'payrolls.user_id')
                            ->where(function ($q) {
                                $q->where('is_mark_paid', '>=', 1)
                                    ->orWhere('is_next_payroll', '>=', 1)
                                    ->orWhere('is_move_to_recon', '>=', 1)
                                    ->orWhere('is_onetime_payment', '>=', 1);
                            });
                    },
                    'userClawback as commission_clawback_count' => function ($query) {
                        $query->where('type', 'commission')->whereColumn('clawback_settlements.user_id', 'payrolls.user_id')->where(function ($q) {
                            $q->where('is_mark_paid', '>=', '1')->orWhere('is_next_payroll', '>=', 1)->orWhere('is_move_to_recon', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                        });
                    },
                    'userClawback as override_clawback_count' => function ($query) {
                        $query->where('type', 'overrides')->whereColumn('clawback_settlements.user_id', 'payrolls.user_id')->where(function ($q) {
                            $q->where('is_mark_paid', '>=', '1')->orWhere('is_next_payroll', '>=', 1)->orWhere('is_move_to_recon', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                        });
                    },
                    'userApproveRequest as approve_request_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },
                    'userApproveRequestReimbursement as reimbursement_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },
                    'userPayrollAdjustmentDetails as payroll_adjustment_details_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_move_to_recon', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },
                    'userDeductions as deduction_count' => function ($query) {
                        $query->whereColumn('payroll_deductions.user_id', 'payrolls.user_id')
                            ->where(function ($q) {
                                $q->where('is_mark_paid', '>=', 1)
                                    ->orWhere('is_next_payroll', '>=', 1)
                                    ->orWhere('is_move_to_recon', '>=', 1)
                                    ->orWhere('is_onetime_payment', '>=', 1);
                            });
                    },
                    'reconciliationInfo as recon_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },

                ]);

            $payroll_total = payroll::with('workertype')->where(['is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_next_payroll' => 0])
                ->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })->whereIn('user_id', $usersids)->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })->sum('net_pay');

            if ($positions && $positions != '') {
                $data_query->where('position_id', $positions);
            }

            if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
                $data_query->where('net_pay', '<', 0);
            }

            if ($commission && $commission != '') {
                $data_query->where('commission', $commission);
            }

            // Check if sorting parameters are present in the request
            if (! empty($request->has('sort')) && ! empty($request->has('sort_val'))) {

                // Get the column to sort by from the request
                $sortColumn = $request->get('sort');

                // Normalize the sorting direction to either 'asc' or 'desc' (default to 'asc')
                $sortDirection = strtolower($request->get('sort_val')) === 'desc' ? 'desc' : 'asc';

                // Apply sorting based on the selected column
                switch ($sortColumn) {
                    case 'first_name':
                    case 'last_name':
                        // Sort by user table first_name or last_name
                        $data_query->orderBy(
                            User::select($sortColumn)
                                ->whereColumn('id', 'payrolls.user_id')
                                ->limit(1),
                            $sortDirection
                        );
                        break;

                    case 'full_name':
                        // Sort by concatenated full name (first_name + last_name)
                        $data_query->orderBy(
                            DB::table('users')
                                ->selectRaw("CONCAT(first_name, ' ', last_name)")
                                ->whereColumn('id', 'payrolls.user_id')
                                ->limit(1),
                            $sortDirection
                        );
                        break;

                    case 'hourly_salary':
                    case 'overtime':
                    case 'commission':
                    case 'override':
                    case 'adjustment':
                    case 'reimbursement':
                    case 'deduction':
                    case 'net_pay':
                    case 'reconciliation':
                        // Apply direct sorting on the payrolls table columns
                        $data_query->orderBy($sortColumn, $sortDirection);
                        break;

                    default:
                        // Optional: fallback to first_name ascending
                        $data_query->orderBy(
                            User::select('first_name')
                                ->whereColumn('id', 'payrolls.user_id')
                                ->limit(1),
                            'asc'
                        );
                        break;
                }
            } else {
                // Default sort if no sort field is specified
                $data_query->orderBy(
                    User::select('first_name')
                        ->whereColumn('id', 'payrolls.user_id')
                        ->limit(1),
                    'asc'
                );
            }

            if (isset($request->is_reconciliation) && $request->is_reconciliation == 1) {
                $positionArray = PositionReconciliations::where('status', 1)->pluck('position_id')->toArray();
                $data_query->whereIn('position_id', $positionArray);
            }
            // $payroll_data = $data_query->paginate($perpage);
            $payroll_data = $data_query->get();
            // dd($payroll_data->toArray());

            // $uId = $payroll_data->pluck('user_id')->toArray();

            $result_data = [];
            $total_custom = 0;

            if (count($payroll_data) > 0) {
                foreach ($payroll_data as $key => $data) {
                    $custom_filed = [];
                    $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                    if ($setting) {
                        foreach ($setting as $value) {
                            if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
                                $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $data->id])
                                    ->whereBetween('pay_period_from', [$start_date, $end_date])
                                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                                    ->whereColumn('pay_period_from', 'pay_period_to')->first();
                            } else {
                                $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $data->id])->first();
                            }

                            // check payroll is closed and open status from bank
                            if ($this->check_open_status_from_bank($start_date, $end_date, $pay_frequency)) {
                                $payroll_data = CustomFieldHistory::where(['column_id' => $value['id'], 'payroll_id' => $data->id])->first();
                            }
                            $total_custom += (float) @$payroll_data->value;

                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => @$value['field_name'],
                                'comment' => @$payroll_data->comment,
                                'value' => @$payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }
                    }

                    if ($checkFinalizeStatus == 0 && $data->status > 1) {
                        $checkFinalizeStatus = 1;
                    }
                    $yellow_status = 0;
                    if (in_array($data->user_id, $usersids->toArray())) {
                        if ($data->is_mark_paid == 1 || $data->is_next_payroll >= 1) {
                            $yellow_status = 1;
                        } else {
                            if (($data->commission_count + $data->override_count + $data->clawback_count + $data->approve_request_count) > 0) {
                                $yellow_status = 1;
                            } else {
                                $yellow_status = 0;
                            }
                        }
                        $s3_image = (isset($data->usersdata->image) && $data->usersdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image) : null;
                        $netPay = $data->net_pay;
                        $status = (empty($data->approvalRequest)) ? 0 : 1;

                        if ($data->approvalRequest) {
                            $approvals_and_requests_status = 1;
                        } else {
                            $check = ApprovalsAndRequest::where('user_id', $data->user_id)->where('status', 'Approved')->exists();
                            $approvals_and_requests_status = $check ? 1 : 0;
                        }

                        // Determine onboarding process status
                        $everee_onboarding_process = 0;
                        $errorMsg = null;

                        if ($CrmData && $workerType == '1099' && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId)) {
                            $everee_onboarding_process = 1;
                        } elseif ($CrmData && ($workerType == 'w2' || $workerType == 'W2') && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId) && $data->usersdata->everee_embed_onboard_profile == 1) {
                            $everee_onboarding_process = 1;
                        }

                        // Always check for errors regardless of everee_workerId status
                        // This ensures we catch users with incomplete information
                        if ($CrmData) {
                            $evereeErrors = checkEvereeErrorStructured($data->user_id);
                            if ($evereeErrors) {
                                $errorMsg = implode(', ', array_values($evereeErrors));
                                $everee_onboarding_process = 0; // Override to 0 if there are errors

                                // Check for unauthorized error and replace with custom message
                                if ($errorMsg && strpos($errorMsg, 'unauthorized; Full authentication is required to access this resource') !== false) {
                                    $errorMsg = 'Online Payroll Processing isn\'t available at the moment., Please contact your Customer Success representative for assistance.';
                                }
                            }
                        }

                        // Calculate total_sales and total_gross_amount using service (Path C - Standard Payroll View)
                        // Only run for Pest company type
                        if ($companyProfile && $companyProfile->company_type === 'Pest') {
                            $payrollCalculationService = app(PayrollCalculationService::class);
                            $commissionTotals = $payrollCalculationService->calculateUserCommissionTotals(
                                $data->user_id,
                                $start_date,
                                $end_date,
                                $pay_frequency
                            );

                            $total_sales = $commissionTotals['total_sales'];
                            $total_gross_amount = $commissionTotals['total_gross_amount'];
                        } else {
                            $total_sales = 0;
                            $total_gross_amount = 0;
                        }

                        $result_data[] = [
                            'id' => $data->id,
                            'payroll_id' => $data->id,
                            'user_id' => $data->user_id,
                            'approvals_and_requests_status' => $approvals_and_requests_status,
                            'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                            'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                            'position_id' => isset($data->usersdata) ? $data->usersdata->position_id : null,
                            'sub_position_id' => isset($data->usersdata) ? $data->usersdata->sub_position_id : null,
                            'is_super_admin' => isset($data->usersdata) ? $data->usersdata->is_super_admin : null,
                            'is_manager' => isset($data->usersdata) ? $data->usersdata->is_manager : null,
                            'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                            'image_s3' => $s3_image,
                            'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                            'commission' => isset($data->commission) ? $data->commission * 1 : 0,
                            'override' => isset($data->override) ? $data->override * 1 : 0,
                            'override_value_is_higher' => 0,
                            'adjustment' => isset($data->adjustment) ? $data->adjustment * 1 : 0,
                            'reimbursement' => isset($data->reimbursement) ? $data->reimbursement * 1 : 0,
                            'clawback' => isset($data->clawback) ? $data->clawback * 1 : 0,
                            'deduction' => isset($data->deduction) ? $data->deduction * 1 : 0,
                            'reconciliation' => isset($data->reconciliation) ? $data->reconciliation * 1 : 0,
                            'net_pay' => round($data->net_pay, 2),
                            'gross_pay' => round($data->gross_pay, 2),
                            // 'comment' => isset($data->payrolladjust) ? $data->payrolladjust->comment : null,
                            'status_id' => $data->status,
                            'status' => isset($data->payrollstatus) ? $data->payrollstatus->status : null,
                            'is_mark_paid' => isset($data->is_mark_paid) ? $data->is_mark_paid : 0,
                            'is_next_payroll' => isset($data->is_next_payroll) ? $data->is_next_payroll : 0,
                            'created_at' => $data->created_at,
                            'updated_at' => $data->updated_at,
                            'custom_filed' => $custom_filed,
                            // 'PayrollShiftHistorie_count' => isset($data->PayrollShiftHistorie) ? count($data->PayrollShiftHistorie) : '',
                            'commission_yellow_status' => ($data->commission_count >= 1 || $data->commission_clawback_count >= 1) ? 1 : 0,
                            'override_yellow_status' => ($data->override_count >= 1 || $data->override_clawback_count >= 1) ? 1 : 0,
                            'approve_request_yellow_status' => ($data->approve_request_count >= 1 || $data->payroll_adjustment_details_count >= 1) ? 1 : 0,
                            'reimbursement_yellow_status' => ($data->reimbursement_count >= 1) ? 1 : 0,
                            'deduction_yellow_status' => ($data->deduction_count >= 1) ? 1 : 0,
                            'paid_next' => 'comm-'.$data->commission_count.' ,over-'.$data->override_count.' ,claw-'.$data->clawback_count.' , appr-'.$data->approve_request_count.' ,reimb-'.$data->reimbursement_count,
                            'total_custom' => $total_custom,
                            'everee_response' => @$everee_webhook_message,
                            'everee_payment_status' => @$everee_payment_status,
                            'is_stop_payroll' => isset($data->is_stop_payroll) ? $data->is_stop_payroll : 0,
                            'hourly_salary' => isset($data->hourly_salary) ? $data->hourly_salary : 0,
                            'overtime' => isset($data->overtime) ? $data->overtime : 0,
                            'worker_type' => isset($workerType) ? $workerType : null,
                            'everee_onboarding_process' => $everee_onboarding_process,
                            'everee_onboarding_process_errorMsg' => $errorMsg,
                            'is_onetime_payment' => isset($data->is_onetime_payment) ? $data->is_onetime_payment : 0,
                            'office_name' => $data?->usersdata?->office?->office_name, // Returns null if usersdata or office is null
                            'state_name' => $data?->usersdata?->office?->State?->name, // Returns null if usersdata, office, or State is null
                            'state_code' => $data?->usersdata?->office?->State?->state_code, // Returns null if usersdata, office, or State is null
                            'total_sales' => $total_sales,
                            'total_gross_amount' => round($total_gross_amount, 2),
                        ];
                    }
                    unset($yellow_status);
                }
            }

            if (! empty($onetime_payment_result_data)) {
                $result_data = array_merge($result_data, $onetime_payment_result_data);
            }

            $data = paginate($result_data, $perpage);
            // $data = $payroll_data;

            // $saleCount = LegacyApiNullData::whereBetween('m1_date', [$start_date, $end_date])->orWhereBetween('m2_date', [$start_date, $end_date])->whereNotNull('data_source_type')->count();
            // $peopleCount = User::whereIn('id', $uId)->where('tax_information', null)->where('name_of_bank', null)->count();

            if ($CrmData) { // && $request['everee_failed_status']!=1
                EvereeOnboardingUsersJob::Dispatch($start_date, $end_date);
            }

            $total_alert_count_query = LegacyApiNullData::select(
                DB::raw('count(`sales_alert`) as sales_alert'),
                DB::raw('count(`missingrep_alert`) as missingrep'),
                DB::raw('count(`closedpayroll_alert`) as closedpayroll'),
                DB::raw('count(`locationredline_alert`) as locationredline'),
                DB::raw('count(`repredline_alert`) as repredline')
            );
            $total_alert_count_query->where(function ($query) use ($request) {
                return $query->whereRaw("`m1_date` >= '".$request->start_date."' AND `m1_date` <= '".$request->end_date."'")
                    ->orWhereRaw("`m2_date` >= '".$request->start_date."' AND `m2_date` <= '".$request->end_date."'");
            });
            $total_alert_count = $total_alert_count_query->whereNotNull('data_source_type')->first();

            payroll::where('status', 2)->where('finalize_status', '!=', 2)->update(['finalize_status' => 2]);
            payroll::where('status', 1)->where('finalize_status', 2)->update(['finalize_status' => 0]);

            return response()->json([
                'ApiName' => 'get_payroll_data',
                'status' => true,
                'message' => 'Successfully.',
                'finalize_status' => isset($checkFinalizeStatus) ? $checkFinalizeStatus : null,
                'payment_failed' => 0,
                'payment_proccess' => $paymemntFailedStatus ? 1 : 0,
                'data' => $data,
                'all_paid' => $all_paid,
                'all_next' => $all_next,
                'payroll_total' => round($payroll_total, 2),
                'second_total' => $emp_total,
                'total_alert_count' => isset($total_alert_count) ? $total_alert_count : 0,
            ], 200);
        }
    }

    public function updatePaymentRequest(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'request_ids' => 'required',
                'type' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $data = [];
        if (count($request->request_ids) > 0) {
            $paymentRequest = $request->request_ids;
            $status = $request->type;
            $i = 0;

            foreach ($paymentRequest as $key => $value) {
                $appuser = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Approved'])->first();
                $user = User::where(['id' => $appuser->user_id])->first();
                $date = date('Y-m-d');
                // dd($appuser);

                if ($user && $user->stop_payroll == 0 && $status != 'Declined') {
                    $history = UserOrganizationHistory::where('user_id', $user->id)->where('effective_date', '<=', now()->format('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                    if ($history) {
                        $subPosition = $history->sub_position_id;
                    } else {
                        $subPosition = $user->sub_position_id;
                    }

                    $position_pay_frequency = PositionPayFrequency::with('frequencyType')->where('position_id', $subPosition)->first();

                    $frequency_type_id = $position_pay_frequency->frequencyType->id;
                    $pay_period_from = null;
                    $pay_period_to = null;

                    $checkRequestPayPeriod = $this->checkRequestPayPeriodNormal($request, $user->id);
                    $checkRequestPayPeriod = json_decode($checkRequestPayPeriod);
                    if (isset($checkRequestPayPeriod->start_date) && isset($checkRequestPayPeriod->end_date)) {
                        $pay_period_from = $checkRequestPayPeriod->start_date;
                        $pay_period_to = $checkRequestPayPeriod->end_date;
                    } else {
                        $pay_periods = $request->pay_periods;
                        if ($pay_periods) {
                            foreach ($pay_periods as $pay_period) {
                                $pay_period = (array) $pay_period;
                                $pay_period_from = $pay_period['pay_period_from'];
                                $pay_period_to = $pay_period['pay_period_to'];
                                break;
                            }
                        }
                    }

                    if ($pay_period_to == '' || $pay_period_from == '') {
                        // $payFrequency = $this->payFrequencyNew($date, $subPosition);
                        $payFrequency = $this->openPayFrequency($subPosition, $user->id);

                        if (isset($payFrequency) && $payFrequency->closed_status == 1) {
                            $payFrequency->pay_period_from = $payFrequency->next_pay_period_from;
                            $payFrequency->pay_period_to = $payFrequency->next_pay_period_to;

                            $appId = WeeklyPayFrequency::where(['pay_period_from' => $payFrequency->next_pay_period_from, 'pay_period_to' => $payFrequency->next_pay_period_to])->first();
                            $frequencyID = $appId->id + 1;
                            $WeeklyPayFrequency = WeeklyPayFrequency::where(['id' => $frequencyID])->first();
                            $startDateNext = $WeeklyPayFrequency->pay_period_from;
                            $endDateNext = $WeeklyPayFrequency->pay_period_to;

                        } else {
                            $payFrequency->pay_period_from = $payFrequency->pay_period_from;
                            $payFrequency->pay_period_to = $payFrequency->pay_period_to;

                            $startDateNext = $payFrequency->next_pay_period_from;
                            $endDateNext = $payFrequency->next_pay_period_to;
                        }
                        $pay_period_to = $payFrequency->pay_period_to;
                        $pay_period_from = $payFrequency->pay_period_from;
                    }

                    if (isset($request->declined_at) && $request->declined_at != null) {
                        $declined_at = $request->declined_at;
                    } else {
                        $declined_at = null;
                    }

                    $check = payroll::when($frequency_type_id == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {

                        $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                            ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($pay_period_from, $pay_period_to) {
                        $query->where([
                            'pay_period_from' => $pay_period_from,
                            'pay_period_to' => $pay_period_to,
                        ]);
                    })->where(['user_id' => $appuser->user_id, 'status' => 2])->count();

                    // dd($check);
                    if ($check == 0) {
                        $payRoll = PayRoll::when($frequency_type_id == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {

                            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($pay_period_from, $pay_period_to) {
                            $query->where([
                                'pay_period_from' => $pay_period_from,
                                'pay_period_to' => $pay_period_to,
                            ]);
                        })
                            ->where(['user_id' => $appuser->user_id])->first();
                        // dd($payRoll);
                        if ($payRoll) {
                            $payRoll->adjustment += $appuser->amount;
                            $payRoll->status = 1;
                            $payRoll->finalize_status = 0;
                            $payRoll->is_mark_paid = 0;
                            $payRoll->is_next_payroll = 0;
                            $payRoll->save();

                            $update = [
                                'status' => $status,
                                'pay_period_from' => $payRoll->pay_period_from,
                                'pay_period_to' => $payRoll->pay_period_to,
                                'declined_at' => $declined_at,
                                'payroll_id' => $payRoll->id,
                            ];

                        } else {
                            if ($frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                                $payRoll = PayRoll::create([
                                    'user_id' => $user->id,
                                    'adjustment' => $appuser->amount,
                                    'pay_period_from' => $pay_period_to,
                                    'pay_period_to' => $pay_period_to,
                                    'position_id' => $subPosition,
                                    'comment' => $appuser->description,
                                    'status' => 1,
                                    'finalize_status' => 0,
                                    'net_pay' => $appuser->amount,
                                    'gross_pay' => $appuser->amount,
                                    'is_mark_paid' => 0,
                                    'is_next_payroll' => 0,
                                ]);

                                $update = [
                                    'status' => $status,
                                    'pay_period_from' => $pay_period_to,
                                    'pay_period_to' => $pay_period_to,
                                    'declined_at' => $declined_at,
                                    'payroll_id' => $payRoll->id,
                                ];
                            } else {
                                $payRoll = PayRoll::create([
                                    'user_id' => $user->id,
                                    'adjustment' => $appuser->amount,
                                    'pay_period_from' => $pay_period_from,
                                    'pay_period_to' => $pay_period_to,
                                    'position_id' => $subPosition,
                                    'comment' => $appuser->description,
                                    'status' => 1,
                                    'finalize_status' => 0,
                                    'net_pay' => $appuser->amount,
                                    'gross_pay' => $appuser->amount,
                                    'is_mark_paid' => 0,
                                    'is_next_payroll' => 0,
                                ]);

                                $update = [
                                    'status' => $status,
                                    'pay_period_from' => $pay_period_from,
                                    'pay_period_to' => $pay_period_to,
                                    'declined_at' => $declined_at,
                                    'payroll_id' => $payRoll->id,
                                ];
                            }
                        }
                        $paymentRequest = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Approved'])->update($update);
                    } else {
                        $msg = 'Cannot send to payroll. this pay period has been Already Finalize for this employee.';

                        return response()->json([
                            'ApiName' => 'update_payment_request',
                            'status' => false,
                            'message' => $msg,
                        ], 400);
                    }

                    if ($status == 'Declined' || $status == 'declined') {
                        $payroll_id = 0;
                        $pay_period_from = null;
                        $pay_period_to = null;

                    } else {
                        $payroll_id = $payRoll->id;
                    }
                    $update = [
                        'status' => $status,
                        'pay_period_from' => $pay_period_from,
                        'pay_period_to' => $pay_period_to,
                        'declined_at' => $declined_at,
                        'payroll_id' => $payroll_id,
                    ];
                    $paymentRequest = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Approved'])->update($update);

                } elseif ($status == 'Declined') {

                    $declined_at = isset($request->declined_at) ? $request->declined_at : null;
                    $update = [
                        'status' => $status,
                        'declined_at' => $declined_at,
                    ];
                    $paymentRequest = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Approved'])->update($update);
                }

                if ($user && $status != 'Declined' && $user->stop_payroll == 1) {
                    $i++;
                }

            }

            // if (count($request->request_ids) == 1) {
            //     if ($i > 0) {
            //         $msg = 'Cannot send to payroll. Payroll has been stopped for this employee.';
            //         return response()->json([
            //             'ApiName' => 'update_payment_request',
            //             'status' => false,
            //             'message' => $msg,
            //         ], 400);
            //     }

            // }else {
            //     if ($i > 0) {
            //         $msg = 'Some users Cannot send to payroll. Because Payroll has been stopped for these employee.';
            //         return response()->json([
            //             'ApiName' => 'update_payment_request',
            //             'status' => false,
            //             'message' => $msg,
            //         ], 400);
            //     }
            // }
        }

        return response()->json([
            'ApiName' => 'update_payment_request',
            'status' => true,
            'message' => 'Successfully.',
            //
        ], 200);

    }

    public function adminUpdatePaymentRequest(Request $request, $checkRequestPayPeriod): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'request_ids' => 'required',
                'type' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $data = [];
        if (count($request->request_ids) > 0) {
            $paymentRequest = $request->request_ids;
            // $status = $request->type;
            $status = 'Accept';
            $i = 0;

            foreach ($paymentRequest as $key => $value) {
                $appuser = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Accept'])->first();
                $user = User::where(['id' => $appuser->user_id])->first();
                $date = date('Y-m-d');

                if ($user && $user->stop_payroll == 0 && $status != 'Declined') {
                    $history = UserOrganizationHistory::where('user_id', $user->id)->where('effective_date', '<=', now()->format('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                    if ($history) {
                        $subPosition = $history->sub_position_id;
                    } else {
                        $subPosition = $user->sub_position_id;
                    }

                    $position_pay_frequency = PositionPayFrequency::with('frequencyType')->where('position_id', $subPosition)->first();

                    $frequency_type_id = $position_pay_frequency->frequencyType->id;

                    $pay_period_from = null;
                    $pay_period_to = null;

                    $checkRequestPayPeriod = json_decode($checkRequestPayPeriod);
                    if (isset($checkRequestPayPeriod->start_date) && isset($checkRequestPayPeriod->end_date)) {
                        $pay_period_from = $checkRequestPayPeriod->start_date;
                        $pay_period_to = $checkRequestPayPeriod->end_date;
                    } else {
                        $pay_periods = json_decode($request->pay_periods);
                        if ($pay_periods) {
                            foreach ($pay_periods as $pay_period) {
                                $pay_period = (array) $pay_period;
                                $pay_period_from = $pay_period['pay_period_from'];
                                $pay_period_to = $pay_period['pay_period_to'];
                                break;
                            }
                        }
                    }

                    if (isset($request->declined_at) && $request->declined_at != null) {
                        $declined_at = $request->declined_at;
                    } else {
                        $declined_at = null;
                    }

                    $check = Payroll::when($frequency_type_id == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {

                        $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                            ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($pay_period_from, $pay_period_to) {
                        $query->where([
                            'pay_period_from' => $pay_period_from,
                            'pay_period_to' => $pay_period_to,
                        ]);
                    })->where(['user_id' => $appuser->user_id, 'status' => 2])->count();

                    if ($check == 0) {
                        $payRoll = Payroll::when($frequency_type_id == FrequencyType::DAILY_PAY_ID, function ($query) use ($pay_period_from, $pay_period_to) {

                            $query->whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])
                                ->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])
                                ->whereColumn('pay_period_from', 'pay_period_to');
                        }, function ($query) use ($pay_period_from, $pay_period_to) {
                            $query->where([
                                'pay_period_from' => $pay_period_from,
                                'pay_period_to' => $pay_period_to,
                            ]);
                        })
                            ->where(['user_id' => $appuser->user_id])->first();
                        if ($payRoll) {
                            $payRoll->adjustment += $appuser->amount;
                            $payRoll->status = 1;
                            $payRoll->finalize_status = 0;
                            $payRoll->is_mark_paid = 0;
                            $payRoll->is_next_payroll = 0;
                            $payRoll->save();

                            $update = [
                                'status' => $status,
                                'pay_period_from' => $payRoll->pay_period_from,
                                'pay_period_to' => $payRoll->pay_period_to,
                                'declined_at' => $declined_at,
                                'payroll_id' => $payRoll->id,
                            ];

                        } else {
                            if ($frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                                $payRoll = PayRoll::create([
                                    'user_id' => $user->id,
                                    'adjustment' => $appuser->amount,
                                    'pay_period_from' => $pay_period_from,
                                    'pay_period_to' => $pay_period_to,
                                    'position_id' => $subPosition,
                                    'comment' => $appuser->description,
                                    'status' => 1,
                                    'finalize_status' => 0,
                                    'net_pay' => $appuser->amount,
                                    'gross_pay' => $appuser->amount,
                                    'is_mark_paid' => 0,
                                    'is_next_payroll' => 0,
                                ]);

                                $update = [
                                    'status' => $status,
                                    'pay_period_from' => $pay_period_from,
                                    'pay_period_to' => $pay_period_to,
                                    'declined_at' => $declined_at,
                                    'payroll_id' => $payRoll->id,
                                ];
                            } else {
                                $payRoll = PayRoll::create([
                                    'user_id' => $user->id,
                                    'adjustment' => $appuser->amount,
                                    'pay_period_from' => $pay_period_from,
                                    'pay_period_to' => $pay_period_to,
                                    'position_id' => $subPosition,
                                    'comment' => $appuser->description,
                                    'status' => 1,
                                    'finalize_status' => 0,
                                    'net_pay' => $appuser->amount,
                                    'gross_pay' => $appuser->amount,
                                    'is_mark_paid' => 0,
                                    'is_next_payroll' => 0,
                                ]);

                                $update = [
                                    'status' => $status,
                                    'pay_period_from' => $pay_period_from,
                                    'pay_period_to' => $pay_period_to,
                                    'declined_at' => $declined_at,
                                    'payroll_id' => $payRoll->id,
                                ];
                            }
                        }
                        $paymentRequest = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Accept'])->update($update);
                    } else {
                        $msg = 'Cannot send to payroll. this pay period has been Already Finalize for this employee.';

                        return response()->json([
                            'ApiName' => 'update_payment_request',
                            'status' => false,
                            'message' => $msg,
                        ], 400);
                    }
                } elseif ($status == 'Declined') {

                    $declined_at = isset($request->declined_at) ? $request->declined_at : null;
                    $update = [
                        'status' => $status,
                        'declined_at' => $declined_at,
                    ];
                    $paymentRequest = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Accept'])->update($update);
                }

                if ($user && $status != 'Declined' && $user->stop_payroll == 1) {
                    $i++;
                }

            }

            // if (count($request->request_ids) == 1) {
            //     if ($i > 0) {
            //         $msg = 'Cannot send to payroll. Payroll has been stopped for this employee.';
            //         return response()->json([
            //             'ApiName' => 'update_payment_request',
            //             'status' => false,
            //             'message' => $msg,
            //         ], 400);
            //     }

            // }else {
            //     if ($i > 0) {
            //         $msg = 'Some users Cannot send to payroll. Because Payroll has been stopped for these employee.';
            //         return response()->json([
            //             'ApiName' => 'update_payment_request',
            //             'status' => false,
            //             'message' => $msg,
            //         ], 400);
            //     }
            // }
        }

        return response()->json([
            'ApiName' => 'update_payment_request',
            'status' => true,
            'message' => 'Successfully.',
            //
        ], 200);

    }

    public function addPaymentAdminRequest(Request $request, $checkRequestPayPeriod)
    {
        if ($request->adjustment_type_id == 10) {
            $Validator = Validator::make($request->all(), ['document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048', 'customer_pid' => 'required']);
        } else {
            $Validator = Validator::make(
                $request->all(),
                [
                    'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                    // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',
                ]
            );
        }
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        // $request->start_date = null;
        // $request->end_date = null;
        $checkRequestPayPeriod = json_decode($checkRequestPayPeriod);
        if (isset($checkRequestPayPeriod->start_date) && isset($checkRequestPayPeriod->end_date)) {
            $request->merge([
                'start_date' => $checkRequestPayPeriod->start_date,
                'end_date' => $checkRequestPayPeriod->end_date,
            ]);
        } else {
            $pay_periods = json_decode($request->pay_periods);
            if ($pay_periods) {
                foreach ($pay_periods as $pay_period) {
                    $pay_period = (array) $pay_period;
                    $pay_period_to = $pay_period['pay_period_to'];
                    $pay_period_from = $pay_period['pay_period_from'];
                    $request->merge([
                        'start_date' => $pay_period_from,
                        'end_date' => $pay_period_to,
                    ]);
                    break;
                }
            }
        }

        if (Auth::user()->is_super_admin == 1) {
            if (! $request->image == null) {
                $file = $request->file('image');
                if (isset($file) && $file != null && $file != '') {
                    // s3 bucket
                    $img_path = time().$file->getClientOriginalName();
                    $img_path = str_replace(' ', '_', $img_path);
                    $awsPath = config('app.domain_name').'/'.'request-image/'.$img_path;
                    s3_upload($awsPath, file_get_contents($file), false);
                    // s3 bucket end
                }
                $image_path = time().$file->getClientOriginalName();
                $ex = $file->getClientOriginalExtension();
                $destinationPath = 'request-image';
                $image_path = $file->move($destinationPath, $img_path);
            } else {
                $image_path = '';
            }

            $user_id = Auth::user()->id;
            $user_data = User::where('id', $user_id)->first();
            if (! empty($request->user_id)) {
                $userID = $request->user_id;
                $userManager = $user_data = User::where('id', $userID)->first();
                $managerId = $userManager->manager_id;

            } else {
                $userID = $user_id;
            }

            $effectiveDate = date('Y-m-d');
            if ($request->adjustment_type_id == 2) {
                $effectiveDate = $request->cost_date;
            } elseif ($request->adjustment_type_id == 3 || $request->adjustment_type_id == 5 || $request->adjustment_type_id == 6) {
                $effectiveDate = $request->request_date;
            } elseif ($request->adjustment_type_id == 7 || $request->adjustment_type_id == 8) {
                $effectiveDate = $request->end_date;
            } elseif ($request->adjustment_type_id == 9) {
                $effectiveDate = $request->adjustment_date;
            }

            $terminated = checkTerminateFlag($userID, $effectiveDate);
            if ($terminated && $terminated->is_terminate) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status' => false,
                    'message' => $user_data?->first_name.' '.$user_data?->last_name.' have been terminated, therefore a one-time payment can\'t be made.',
                ], 400);
            }

            $dismissed = checkDismissFlag($userID, $effectiveDate);
            if ($dismissed && $dismissed->dismiss) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status' => false,
                    'message' => $user_data?->first_name.' '.$user_data?->last_name.' have been disabled, therefore a one-time payment can\'t be made.',
                ], 400);
            }

            if ($user_data?->disable_login) {
                return response()->json([
                    'ApiName' => 'add-request',
                    'status' => false,
                    'message' => $user_data?->first_name.' '.$user_data?->last_name.' have been suspended, therefore a one-time payment can\'t be made.',
                ], 400);
            }

            // echo $data->name;die;
            $adjustementType = AdjustementType::where('id', $request->adjustment_type_id)->first();

            $approvalsAndRequest = ApprovalsAndRequest::where('adjustment_type_id', $adjustementType->id)->whereNotNull('req_no')->latest('id')->first();
            if ($approvalsAndRequest) {
                $approvalsAndRequest = preg_replace('/[A-Za-z]+/', '', $approvalsAndRequest->req_no);
            }
            if ($adjustementType->id == 1) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTPD'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTPD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 2) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTR'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 3) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTB'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTB'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 4) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'A'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'A'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 5) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTFF'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTFF'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 6) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTI'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTI'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 7) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTL'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTL'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 8) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTPT'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTPT'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 9) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTTA'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTTA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 10) {
                if (! empty($request->customer_pid)) {
                    $req_no = 'OTC'.$request->customer_pid;
                } else {
                    $req_no = 'OTC'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 11) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTOV'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTOV'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } else {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'OTO'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'OTO'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            }
            // echo $req_no;die;
            // return $request;

            if ($adjustementType->id == 7 || $adjustementType->id == 8 || $adjustementType->id == 9) {
                $startDate = $request->start_date;
                $endDate = $request->end_date;

                $insertUpdate = [
                    'user_id' => $userID,
                    'manager_id' => isset($managerId) ? $managerId : $user_data->manager_id,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $user_id,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    'description' => $request->description,
                    'cost_tracking_id' => $request->cost_tracking_id,
                    'emi' => $request->emi,
                    'request_date' => $request->request_date,
                    'cost_date' => $request->cost_date,
                    'amount' => $request->amount,
                    'image' => $image_path,
                    'status' => 'Accept',
                    'start_date' => isset($request->start_date) ? $request->start_date : null,
                    'end_date' => isset($request->end_date) ? $request->end_date : null,
                    'pto_hours_perday' => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                    'adjustment_date' => isset($request->adjustment_date) ? $request->adjustment_date : null,
                    'clock_in' => isset($request->clock_in) ? $request->clock_in : null,
                    'clock_out' => isset($request->clock_out) ? $request->clock_out : null,
                    'lunch_adjustment' => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                    'break_adjustment' => isset($request->break_adjustment) ? $request->break_adjustment : null,
                ];

                if ($adjustementType->id == 9) {
                    $adjustmentDate = $request->adjustment_date;
                    $clock_in = null;
                    $clock_out = null;
                    $userPosition = User::select('sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                    $office_id = $userPosition->office_id;
                    if (isset($request->clock_in) && ! empty($request->clock_in)) {
                        $clock_in = $request->clock_in;
                    }
                    if (isset($request->clock_out) && ! empty($request->clock_out)) {
                        $clock_out = $request->clock_out;
                    }
                    $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $adjustmentDate, $request->lunch_adjustment);
                    $leaveData = ApprovalsAndRequest::where(['user_id' => $userID, 'adjustment_type_id' => 7])->where('start_date', '<=', $adjustmentDate)->where('end_date', '>=', $adjustmentDate)->where('status', 'Approved')->first();
                    if ($leaveData) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because this adjustment date has already been leave request'], 400);
                    } else {
                        $approvalData = ApprovalsAndRequest::where('adjustment_type_id', $adjustementType->id)->where(['user_id' => $userID, 'adjustment_date' => $adjustmentDate])->first();
                        if ($approvalData) {
                            $insertUpdate['req_no'] = $approvalData->req_no;
                            ApprovalsAndRequest::where('id', $approvalData->id)->update($insertUpdate);
                        } else {
                            ApprovalsAndRequest::create($insertUpdate);
                        }
                    }
                } elseif ($adjustementType->id == 7) {
                    $userPosition = User::select('id', 'sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                    $subPositionId = $userPosition->sub_position_id;
                    $spayFrequency = $this->payFrequency($startDate, $subPositionId, $userPosition->id);
                    $epayFrequency = $this->payFrequency($endDate, $subPositionId, $userPosition->id);

                    $office_id = $userPosition->office_id;
                    $schedule_start_date = Carbon::parse($request->start_date);
                    $schedule_end_date = Carbon::parse($request->end_date);
                    $schedule_from = '08:00:00';
                    $schedule_to = '16:00:00';
                    for ($date = $schedule_start_date; $date->lte($schedule_end_date); $date->addDay()) {
                        $clock_in = $date->copy()->setTimeFromTimeString($schedule_from);
                        $clock_out = $date->copy()->setTimeFromTimeString($schedule_to);
                        // dd($schedule_from, $schedule_to, $clock_in, $clock_out);
                        $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $date, null);
                    }

                    if ((! empty($spayFrequency) && $spayFrequency->closed_status == 1) || (! empty($spayFrequency) && $epayFrequency->closed_status == 1)) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the pay period has already been closed'], 400);

                    } else {
                        $approvalData = ApprovalsAndRequest::where(['adjustment_type_id' => $adjustementType->id, 'user_id' => $userID])->where(['start_date' => $startDate, 'end_date' => $endDate])->first();
                        if ($approvalData) {
                            $insertUpdate['req_no'] = $approvalData->req_no;
                            ApprovalsAndRequest::where('id', $approvalData->id)->update($insertUpdate);
                        } else {
                            ApprovalsAndRequest::create($insertUpdate);
                        }
                    }
                } elseif ($adjustementType->id == 8) {
                    $userPosition = User::select('id', 'sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                    $subPositionId = $userPosition->sub_position_id;
                    $spayFrequency = $this->payFrequency($startDate, $subPositionId, $userPosition->id);
                    $epayFrequency = $this->payFrequency($endDate, $subPositionId, $userPosition->id);

                    $office_id = $userPosition->office_id;
                    $schedule_start_date = Carbon::parse($request->start_date);
                    $schedule_end_date = Carbon::parse($request->end_date);
                    $pto_hours_perday = $request->pto_hours_perday;
                    $schedule_from = '08:00:00';
                    $sc_time = Carbon::createFromFormat('H:i:s', $schedule_from);
                    $schedule_to = $sc_time->addHours($pto_hours_perday)->format('H:i:s');
                    // dd($schedule_to);
                    for ($date = $schedule_start_date; $date->lte($schedule_end_date); $date->addDay()) {
                        $clock_in = $date->copy()->setTimeFromTimeString($schedule_from);
                        $clock_out = $date->copy()->setTimeFromTimeString($schedule_to);
                        // dd($schedule_from, $schedule_to, $clock_in, $clock_out);
                        $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $date, null);
                    }
                    if ((! empty($spayFrequency) && $spayFrequency->closed_status == 1) || (! empty($spayFrequency) && $epayFrequency->closed_status == 1)) {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the pay period has already been closed'], 400);

                    } else {
                        $start = Carbon::parse($startDate);
                        $end = Carbon::parse($endDate);
                        $daysCount = $start->diffInDays($end) + 1;
                        $ptoHoursPerday = ($request->pto_hours_perday * $daysCount);
                        $date = date('Y-m-d');
                        $calpto = $this->calculatePTOs($userID);
                        $usedpto = isset($calpto['total_user_ptos']) ? $calpto['total_user_ptos'] : 0;
                        // $userWagesHistory = UserWagesHistory::where('user_id', $userID)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
                        // print_r($userWagesHistory);die();
                        if ($calpto && ! empty($calpto['total_ptos']) && ($usedpto + $ptoHoursPerday) <= $calpto['total_ptos']) {
                            $checkstatus = $this->checkUsedDay($userID, $startDate, $endDate, $insertUpdate['pto_hours_perday']);
                            if (! empty($checkstatus)) {
                                return response()->json(['status' => false, 'message' => $checkstatus[0]], 400);
                            }
                            ApprovalsAndRequest::create($insertUpdate);
                        } else {
                            return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the PTO hour greater than PTO balance'], 400);
                        }
                    }
                }

            } else {

                $data = ApprovalsAndRequest::create([
                    'user_id' => $userID,
                    'manager_id' => isset($managerId) ? $managerId : $user_data->manager_id,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $user_id,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    // 'customer_pid' => $request->customer_pid,
                    'description' => $request->description,
                    'cost_tracking_id' => $request->cost_tracking_id,
                    'emi' => $request->emi,
                    'request_date' => $request->request_date,
                    'cost_date' => $request->cost_date,
                    'amount' => $request->amount,
                    'image' => $image_path,
                    'status' => 'Accept',
                    'start_date' => isset($request->start_date) ? $request->start_date : null,
                    'end_date' => isset($request->end_date) ? $request->end_date : null,
                    'pto_hours_perday' => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                    'adjustment_date' => isset($request->adjustment_date) ? $request->adjustment_date : null,
                    'clock_in' => isset($request->clock_in) ? $request->clock_in : null,
                    'clock_out' => isset($request->clock_out) ? $request->clock_out : null,
                    'lunch_adjustment' => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                    'break_adjustment' => isset($request->break_adjustment) ? $request->break_adjustment : null,
                ]);

            }

            $customerPid = $request->customer_pid;
            if ($customerPid) {
                //  $pid = implode(',',$customerPid);
                $valPid = explode(',', $customerPid);
                foreach ($valPid as $val) {
                    $customerName = SalesMaster::where('pid', $val)->first();
                    RequestApprovelByPid::create([
                        'request_id' => $data->id,
                        'pid' => $val,
                        'customer_name' => isset($customerName->customer_name) ? $customerName->customer_name : null,
                    ]);
                }
            }

            if ($user_data->manager_id) {

                // $data =  Notification::create([
                //     'user_id' => isset($user_data->manager_id)?$user_data->manager_id:1,
                //     'type' => 'request-approval',
                //     'description' => 'A new request is generated by '.$user_data->first_name,
                //     'is_read' => 0,

                // ]);

                $notificationData = [
                    'user_id' => isset($user_data->manager_id) ? $user_data->manager_id : 1,
                    'device_token' => $user_data->device_token,
                    'title' => 'A new request is generated.',
                    'sound' => 'sound',
                    'type' => 'request-approval',
                    'body' => 'A new request is generated by '.$user_data->first_name,
                ];
                $this->sendNotification($notificationData);
            }
            $user = [

                'user_id' => isset($user_data->manager_id) ? $user_data->manager_id : 1,
                'description' => 'A new request is generated by '.$user_data->first_name.' '.$user_data->last_name,
                'type' => 'request-approval',
                'is_read' => 0,
            ];
            $notify = event(new UserloginNotification($user));

            return ['message' => 'Request Completed', 'data' => $data];
        } else {
            return response()->json(['status' => false, 'message' => 'Sorry you dont have right.'], 400);
        }
    }

    public function checkRequestPayPeriod(Request $request)
    {
        $checkRequestPayPeriodData = [];
        $pay_periods = json_decode($request->pay_periods);
        foreach ($pay_periods as $pay_period) {
            $pay_period = (array) $pay_period;
            $start_date = $pay_period['pay_period_from'];
            $end_date = $pay_period['pay_period_to'];
            $pay_frequency = $pay_period['frequency_type_id'];
            break;
        }
        if ($request->user_id && $start_date && $end_date) {

            $checkPayroll = Payroll::where(['user_id' => $request->user_id, 'is_onetime_payment' => 1, 'status' => 3])->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {

                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })->get();
            if (empty($checkPayroll->toArray())) {
                $checkPayroll = PayrollHistory::where(['user_id' => $request->user_id, 'is_onetime_payment' => 1, 'status' => 3])->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {

                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })->get();
            }
            if (empty($checkPayroll->toArray())) {
                $checkRequestPayPeriodData = ['start_date' => null, 'end_date' => null];
            } else {
                $userSubPosition = User::where('id', $request->user_id)->first('sub_position_id');
                $userSubPositionId = isset($userSubPosition->sub_position_id) ? $userSubPosition->sub_position_id : null;
                $payfrequency = $this->nextPayFrequency($start_date, $userSubPositionId);
                if ($payfrequency->next_pay_period_from != null && $payfrequency->next_pay_period_to != null) {
                    $checkRequestPayPeriodData = ['start_date' => $payfrequency->next_pay_period_from, 'end_date' => $payfrequency->next_pay_period_to];
                } else {
                    return response()->json([
                        'ApiName' => 'update_payment_request',
                        'status' => false,
                        'message' => 'No any next pay period found at this frequency.',
                    ], 400);
                }
            }

        }

        return json_encode($checkRequestPayPeriodData);
    }

    public function checkRequestPayPeriodNormal(Request $request, $user_id)
    {
        $checkRequestPayPeriodData = [];
        $pay_periods = $request->pay_periods;
        foreach ($pay_periods as $pay_period) {
            $pay_period = (array) $pay_period;
            $start_date = $pay_period['pay_period_from'];
            $end_date = $pay_period['pay_period_to'];
            $pay_frequency = $pay_period['frequency_type_id'];
            break;
        }
        if ($user_id && $start_date && $end_date) {

            $checkPayroll = Payroll::where(['user_id' => $user_id, 'is_onetime_payment' => 1, 'status' => 3])->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {

                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })->get();
            if (empty($checkPayroll->toArray())) {
                $checkPayroll = PayrollHistory::where(['user_id' => $user_id, 'is_onetime_payment' => 1, 'status' => 3])->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {

                    $query->whereBetween('pay_period_from', [$start_date, $end_date])
                        ->whereBetween('pay_period_to', [$start_date, $end_date])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($start_date, $end_date) {
                    $query->where([
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ]);
                })->get();
            }
            if (empty($checkPayroll->toArray())) {
                $checkRequestPayPeriodData = ['start_date' => null, 'end_date' => null];
            } else {
                $userSubPosition = User::where('id', $user_id)->first('sub_position_id');
                $userSubPositionId = isset($userSubPosition->sub_position_id) ? $userSubPosition->sub_position_id : null;
                $payfrequency = $this->nextPayFrequency($start_date, $userSubPositionId);
                if ($payfrequency->next_pay_period_from != null && $payfrequency->next_pay_period_to != null) {
                    $checkRequestPayPeriodData = ['start_date' => $payfrequency->next_pay_period_from, 'end_date' => $payfrequency->next_pay_period_to];
                } else {
                    return response()->json([
                        'ApiName' => 'update_payment_request',
                        'status' => false,
                        'message' => 'No any next pay period found at this frequency.',
                    ], 400);
                }
            }

        }

        return json_encode($checkRequestPayPeriodData);
    }

    public function adminPaymentRequestAddPayroll(Request $request)
    {
        $checkRequestPayPeriod = $this->checkRequestPayPeriod($request);
        if ($checkRequestPayPeriod) {
            $adminPaymentRequest = $this->addPaymentAdminRequest($request, $checkRequestPayPeriod);
            if (is_array($adminPaymentRequest) && $adminPaymentRequest['message'] == 'Request Completed') {
                $req_id = $adminPaymentRequest['data']->id ? $adminPaymentRequest['data']->id : null;
                $request->merge(['request_ids' => [$req_id]]);

                return $this->adminUpdatePaymentRequest($request, $checkRequestPayPeriod);
            } else {
                return $adminPaymentRequest;
            }
        } else {
            return $checkRequestPayPeriod;
        }
    }

    private function calculatePTOs($user_id = null, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d');
        }
        if ($user_id == null) {
            $user_id = Auth::user()->id;
        }
        // $user = User::find($user_id);
        $total_used_pto_hours = 0;
        $total_pto_hours = 0;
        $date = Carbon::parse($date);
        $user = UserWagesHistory::where('user_id', $user_id)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
        if ($user->unused_pto_expires == 'Monthly' || $user->unused_pto_expires == 'Expires Monthly') {
            $total_pto_hours = $user->pto_hours;
            $start_date = $date->copy()->startOfMonth()->toDateString();
            $end_date = $date->copy()->endOfMonth()->toDateString();
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }

                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Annually' || $user->unused_pto_expires == 'Expires Annually') {
            $start_date = $date->copy()->startOfYear()->toDateString();
            $end_date = $date->copy()->endOfYear()->toDateString();

            $pto_start_date = Carbon::parse($user->created_at)->lt($date->copy()->startOfYear()) ? $date->copy()->startOfYear() : Carbon::parse($user->created_at);
            $monthCount = $pto_start_date->diffInMonths($date);
            $total_pto_hours = $user->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);
            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }
                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Accrues Continuously' || $user->unused_pto_expires == 'Expires Accrues Continuously') {
            $monthCount = Carbon::parse($user->created_at)->diffInMonths($date);
            $total_pto_hours = $user->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                $days = $pto_start_date->diffInDays($pto_end_date) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        }

        return [
            'total_ptos' => (int) $total_pto_hours,
            'total_user_ptos' => (int) $total_used_pto_hours,
            'total_remaining_ptos' => (int) $total_pto_hours - $total_used_pto_hours,
        ];
    }

    private function checkUsedDay($id, $start_date, $end_date, $requestday)
    {
        $start = \Carbon\Carbon::parse($start_date);
        $end = \Carbon\Carbon::parse($end_date);
        $error = [];
        if ($start->isSameDay($end)) {
            $approvalData = ApprovalsAndRequest::where([
                'adjustment_type_id' => 8,
                'user_id' => $id,
            ])->whereDate('start_date', '<=', $start_date)
                ->whereDate('end_date', '>=', $start_date)
                ->where('status', '!=', 'Declined')->get();
            $tpto = 0;
            foreach ($approvalData as $approval) {
                $tpto += $approval->pto_hours_perday;
            }
            if ($approvalData && ($tpto + $requestday) > 8) {
                $error[] = $start->format('m/d/y').' request cannot be created because the PTO hours exceed 8.';
            }
        } else {
            foreach ($start->daysUntil($end) as $date) {
                $approvalData = ApprovalsAndRequest::where([
                    'adjustment_type_id' => 8,
                    'user_id' => $id,
                ])->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date)
                    ->where('status', '!=', 'Declined')
                    ->get();
                $tpto = 0;
                foreach ($approvalData as $approval) {
                    $tpto += $approval->pto_hours_perday;
                }
                if ($approvalData && ($tpto + $requestday) > 8) {
                    $error[] = $date->format('m/d/y').' request cannot be created because the PTO hours exceed 8.';
                }
            }
        }

        return $error;
    }

    private function createOrUpdateUserSchedules($user_id, $office_id, $clock_in, $clock_out, $adjustment_date, $lunch)
    {
        // dd($user_id, $office_id,$clock_in,$clock_out,$adjustment_date,$lunch);
        if (! empty($lunch) && ! is_null($lunch) && $lunch != 'None') {
            $lunch = $lunch.' Mins';
        }
        $userschedule = UserSchedule::where('user_id', $user_id)->first();
        // dd($userschedule);
        $s_date = Carbon::parse($clock_in);
        $dayNumber = $s_date->dayOfWeekIso;
        if ($userschedule) {
            $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $userschedule->id)->where('office_id', $office_id)->wheredate('schedule_from', $adjustment_date)->first();
            if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                $scheduleDetaisData = [
                    'schedule_id' => $userschedule->id,
                    'office_id' => $office_id,
                    'schedule_from' => $clock_in,
                    // 'schedule_to' => $scheduleTo->toDateTimeString(),
                    'schedule_to' => $clock_out,
                    'lunch_duration' => $lunch,
                    'work_days' => $dayNumber,
                    'repeated_batch' => 0,
                    'user_attendance_id' => null,
                ];
                $dataStored = UserScheduleDetail::create($scheduleDetaisData);
            } else {
                // update schedules
                // $checkUserScheduleDetail->where('schedule_id',$userschedule->id)->where('office_id',$office_id)->wheredate('schedule_from',$adjustment_date)->update(['schedule_from' => $clock_in, 'schedule_to' => $clock_out,'lunch_duration' => $lunch, 'work_days' => $dayNumber]);
            }
        } else {
            $create_userschedule = UserSchedule::create(['user_id' => $user_id, 'scheduled_by' => Auth::user()->id]);
            if ($create_userschedule) {
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $create_userschedule->id)
                    ->where('office_id', $office_id)
                    ->wheredate('schedule_from', $adjustment_date)
                    ->first();
                if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                    $scheduleDetaisData = [
                        'schedule_id' => $create_userschedule->id,
                        'office_id' => $office_id,
                        'schedule_from' => $clock_in,
                        'schedule_to' => $clock_out,
                        'lunch_duration' => null,
                        'work_days' => $dayNumber,
                        'repeated_batch' => 0,
                        'user_attendance_id' => null,
                    ];
                    $dataStored = UserScheduleDetail::create($scheduleDetaisData);
                }
            }
        }
    }

    public function createPayrollDataNew()
    {
        $payrolls = Payroll::where('status', '1')->get();
        foreach ($payrolls as $payroll) {
            Payroll::where('status', '6')->where('user_id', $payroll->user_id)->where('pay_period_from', $payroll->pay_period_from)->where('pay_period_to', $payroll->pay_period_to)->update(['status' => 1]);
            $pay = Payroll::where('status', '1')->where('user_id', $payroll->user_id)->where('pay_period_from', $payroll->pay_period_from)->where('pay_period_to', $payroll->pay_period_to)->first();
            if ($pay) {
                Payroll::where('id', '=', $pay->id)->where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_next_payroll' => 0]);
                $tablesToUpdate = ['UserCommission', 'UserOverrides', 'ClawbackSettlement', 'ApprovalsAndRequest', 'PayrollAdjustmentDetail'];
                foreach ($tablesToUpdate as $table) {
                    $fullClassName = 'App\Models\\'.$table;
                    $modelInstance = new $fullClassName;
                    $whereConditions = ['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to];
                    $modelInstance::where($whereConditions)->update(['payroll_id' => $pay->id, 'is_next_payroll' => '0']);
                }
                Payroll::where('id', '!=', $pay->id)->where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->delete();
            }
        }
    }

    public function deduction_for_all_deduction_enable_users($startDate, $endDate, $payFrequencyTypeId, $workerType, $usersIds)
    {
        $users = User::select('id', 'sub_position_id', 'stop_payroll')->whereIn('id', $usersIds)->where('is_super_admin', '!=', '1')
            ->where(DB::raw('DATE_FORMAT(period_of_agreement_start_date, "%Y-%m-%d")'), '<=', $endDate)
            ->where('worker_type', $workerType)->get();

        if ($payFrequencyTypeId == FrequencyType::DAILY_PAY_ID) {
            $payrollData = [];
            $payDatas = Payroll::selectRaw('id, user_id, SUM(commission) as commission, SUM(override) as override, SUM(net_pay) as net_pay, SUM(gross_pay) as gross_pay')
                ->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to')
                ->whereIn('user_id', $usersIds)->groupBy('user_id')->get();
            foreach ($payDatas as $payData) {
                $payrollData[$payData->user_id] = $payData;
            }

            foreach ($users as $user) {
                $subPositionId = $user->sub_position_id;
                if ($subPositionId) {
                    $deductionStatus = PositionCommissionDeductionSetting::where(['position_id' => $subPositionId, 'status' => 1])->first();
                    if ($deductionStatus) {
                        $deductionLimit = PositionsDeductionLimit::where(['position_id' => $subPositionId])->first();
                        $totalDeduction = 0;
                        $deductionLimit = isset($deductionLimit->limit_ammount) ? $deductionLimit->limit_ammount : '0';
                        if (empty($deductionLimit)) {
                            $deductionLimit = 100;
                        }
                        $deductionRecords = [];
                        $userPayrollData = isset($payrollData[$user->id]) ? $payrollData[$user->id] : null;
                        $deductions = PositionCommissionDeduction::where(['position_id' => $subPositionId])->get();
                        foreach ($deductions as $deduction) {
                            $deductionAmount = 0;
                            $payPeriods = getDatesFromToToday($deduction->pay_period_from);
                            foreach ($payPeriods as $payPeriod) {
                                $userDeductionHistory = UserDeductionHistory::select('user_id', 'cost_center_id', 'amount_par_paycheque')->with('costcenter:id,name,status')
                                    ->whereHas('costcenter', function ($q) {
                                        $q->where('status', 1);
                                    })->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->where('effective_date', '<=', $payPeriod)->orderBy('effective_date', 'DESC')->first();

                                if ($userDeductionHistory) {
                                    $totalDeduction += $userDeductionHistory->amount_par_paycheque;
                                    $deductionAmount += $userDeductionHistory->amount_par_paycheque;
                                }
                            }

                            $outstandingDeduction = PayrollDeductions::select('id', 'outstanding', 'cost_center_id')
                                ->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])
                                ->where('pay_period_to', '<', $startDate)->sum('outstanding');
                            $totalDeduction += isset($outstandingDeduction) ? round($outstandingDeduction, 2) : 0;
                            $deductionAmount += isset($outstandingDeduction) ? round($outstandingDeduction, 2) : 0;

                            $deductionRecords[] = [
                                'pay_period_from' => $startDate,
                                'pay_period_to' => $endDate,
                                'user_id' => $user->id,
                                'cost_center_id' => $deduction->cost_center_id,
                                'payroll_id' => isset($userPayrollData->id) ? $userPayrollData->id : 0,
                                'amount' => round($deductionAmount ?? 0, 2),
                                'limit' => round($deductionLimit, 2),
                                'is_stop_payroll' => isset($user->stop_payroll) ? $user->stop_payroll : 0,
                            ];
                        }

                        $userCommission = isset($userPayrollData->commission) ? $userPayrollData->commission : 0;
                        $userOverride = isset($userPayrollData->override) ? $userPayrollData->override : 0;
                        $totalSum = $userCommission + $userOverride;
                        $subTotal = (($totalSum) <= 0) ? 0 : round(($totalSum) * ($deductionLimit / 100), 2);
                        $subTotal = ($totalDeduction < $subTotal) ? $totalDeduction : $subTotal;

                        $finalDeductionTotal = 0;
                        foreach ($deductionRecords as $deductionRecord) {
                            $total = ($totalDeduction > 0) ? round($subTotal * ($deductionRecord['amount'] / $totalDeduction), 2) : 0;
                            $outstanding = $deductionRecord['amount'] - $total;

                            PayrollDeductions::updateOrCreate([
                                'pay_period_from' => $deductionRecord['pay_period_from'],
                                'user_id' => $deductionRecord['user_id'],
                                'cost_center_id' => $deductionRecord['cost_center_id'],
                            ], [
                                'pay_period_to' => $deductionRecord['pay_period_to'],
                                'payroll_id' => $deductionRecord['payroll_id'],
                                'amount' => round($deductionRecord['amount'], 2),
                                'limit' => round($deductionRecord['limit'], 2),
                                'total' => round($total, 2),
                                'outstanding' => round($outstanding, 2),
                                'subtotal' => round($subTotal, 2),
                                'is_stop_payroll' => $deductionRecord['is_stop_payroll'],
                            ]);

                            $finalDeductionTotal += $total;
                        }

                        if ($userPayrollData && $userPayrollData?->id) {
                            $deductionTotal = PayrollDeductions::where(['payroll_id' => $userPayrollData?->id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->sum('total') ?? 0;
                            Payroll::where('id', $userPayrollData?->id)->update(['deduction' => $deductionTotal, 'net_pay' => $userPayrollData->net_pay - $deductionTotal, 'gross_pay' => $userPayrollData->gross_pay - $deductionTotal]);
                        }
                    }
                }
            }
        } else {
            $payrollData = [];
            $payDatas = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->whereIn('user_id', $usersIds)->get();
            foreach ($payDatas as $payData) {
                $payrollData[$payData->user_id] = $payData;
            }

            foreach ($users as $user) {
                $subPositionId = $user->sub_position_id;
                if ($subPositionId) {
                    $deductionStatus = PositionCommissionDeductionSetting::where(['position_id' => $subPositionId, 'status' => 1])->first();
                    if ($deductionStatus) {
                        $deductionLimit = PositionsDeductionLimit::where(['position_id' => $subPositionId])->first();
                        $totalDeduction = 0;
                        $deductionLimit = isset($deductionLimit->limit_ammount) ? $deductionLimit->limit_ammount : '0';
                        if (empty($deductionLimit)) {
                            $deductionLimit = 100;
                        }
                        $deductionRecords = [];
                        $userPayrollData = isset($payrollData[$user->id]) ? $payrollData[$user->id] : null;
                        $deductions = PositionCommissionDeduction::where(['position_id' => $subPositionId])->get();
                        foreach ($deductions as $deduction) {
                            $check = true;
                            if ($payFrequencyTypeId == FrequencyType::WEEKLY_ID) {
                                $weekly = WeeklyPayFrequency::when($workerType == 'w2', function ($q) {
                                    $q->where('w2_closed_status', 0);
                                })->when($workerType == '1099', function ($q) {
                                    $q->where('closed_status', 0);
                                })->where('pay_period_from', '>=', $deduction->pay_period_from)->orderBy('id', 'ASC')->first();
                                if (empty($weekly) || $weekly->pay_period_from != $startDate) {
                                    $check = false;
                                } else {
                                    $weeklyPayFrequency = WeeklyPayFrequency::where('pay_period_from', '>', $deduction->pay_period_from)->get();
                                    foreach ($weeklyPayFrequency as $weekly) {
                                        PayrollDeductions::where([
                                            'pay_period_from' => $weekly->pay_period_from,
                                            'pay_period_to' => $weekly->pay_period_to,
                                            'cost_center_id' => $deduction['cost_center_id'],
                                            'user_id' => $user->id,
                                            'status' => '1',
                                        ])->delete();
                                    }
                                }
                            } elseif ($payFrequencyTypeId == FrequencyType::MONTHLY_ID) {
                                $month = MonthlyPayFrequency::when($workerType == 'w2', function ($q) {
                                    $q->where('w2_closed_status', 0);
                                })->when($workerType == '1099', function ($q) {
                                    $q->where('closed_status', 0);
                                })->where('pay_period_from', '>=', $deduction->pay_period_from)->orderBy('id', 'ASC')->first();
                                if (empty($month) || $month->pay_period_from != $startDate) {
                                    $check = false;
                                } else {
                                    $monthlyPayFrequency = MonthlyPayFrequency::where('pay_period_from', '>', $deduction->pay_period_from)->get();
                                    foreach ($monthlyPayFrequency as $monthly) {
                                        PayrollDeductions::where([
                                            'pay_period_from' => $monthly->pay_period_from,
                                            'pay_period_to' => $monthly->pay_period_to,
                                            'cost_center_id' => $deduction['cost_center_id'],
                                            'user_id' => $user->id,
                                            'status' => '1',
                                        ])->delete();
                                    }
                                }
                            } elseif ($payFrequencyTypeId == FrequencyType::BI_WEEKLY_ID || $payFrequencyTypeId == FrequencyType::SEMI_MONTHLY_ID) {
                                $additionalFrequency = AdditionalPayFrequency::when($workerType == 'w2', function ($q) {
                                    $q->where('w2_closed_status', 0);
                                })->when($workerType == '1099', function ($q) {
                                    $q->where('closed_status', 0);
                                })->when($payFrequencyTypeId == FrequencyType::BI_WEEKLY_ID, function ($q) {
                                    $q->where('type', '1');
                                })->when($payFrequencyTypeId == FrequencyType::SEMI_MONTHLY_ID, function ($q) {
                                    $q->where('type', '2');
                                })->where('pay_period_from', '>=', $deduction->pay_period_from)->orderBy('id', 'ASC')->first();
                                if (empty($additionalFrequency) || $additionalFrequency->pay_period_from != $startDate) {
                                    $check = false;
                                } else {
                                    $additionalFrequency = AdditionalPayFrequency::when($payFrequencyTypeId == FrequencyType::BI_WEEKLY_ID, function ($q) {
                                        $q->where('type', '1');
                                    })->when($payFrequencyTypeId == FrequencyType::SEMI_MONTHLY_ID, function ($q) {
                                        $q->where('type', '2');
                                    })->where('pay_period_from', '>', $deduction->pay_period_from)->get();
                                    foreach ($additionalFrequency as $additional) {
                                        PayrollDeductions::where([
                                            'pay_period_from' => $additional->pay_period_from,
                                            'pay_period_to' => $additional->pay_period_to,
                                            'cost_center_id' => $deduction['cost_center_id'],
                                            'user_id' => $user->id,
                                            'status' => '1',
                                        ])->delete();
                                    }
                                }
                            }

                            if (! $check) {
                                continue;
                            }

                            $userDeductionHistory = UserDeductionHistory::select('user_id', 'cost_center_id', 'amount_par_paycheque')->with('costcenter:id,name,status')
                                ->whereHas('costcenter', function ($q) {
                                    $q->where('status', 1);
                                })->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->where('effective_date', '<=', $endDate)->orderBy('effective_date', 'DESC')->first();

                            $outstandingDeduction = PayrollDeductions::select('id', 'outstanding', 'cost_center_id')
                                ->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->where('pay_period_from', '!=', $startDate)->where('pay_period_to', '!=', $endDate)->orderBy('id', 'DESC')->first();
                            $previousOutstanding = isset($outstandingDeduction->outstanding) ? round($outstandingDeduction->outstanding, 2) : 0;
                            if ($userDeductionHistory) {
                                $totalDeduction += $userDeductionHistory->amount_par_paycheque + (($previousOutstanding > 0) ? $previousOutstanding : 0);
                                $deductionAmount = $userDeductionHistory->amount_par_paycheque + (($previousOutstanding > 0) ? $previousOutstanding : 0);
                            } else {
                                $totalDeduction += (($previousOutstanding > 0) ? $previousOutstanding : 0);
                                $deductionAmount = (($previousOutstanding > 0) ? $previousOutstanding : 0);
                            }

                            $deductionRecords[] = [
                                'pay_period_from' => $startDate,
                                'pay_period_to' => $endDate,
                                'user_id' => $user->id,
                                'cost_center_id' => $deduction->cost_center_id,
                                'payroll_id' => isset($userPayrollData->id) ? $userPayrollData->id : 0,
                                'amount' => round($deductionAmount ?? 0, 2),
                                'limit' => round($deductionLimit, 2),
                                'is_stop_payroll' => isset($user->stop_payroll) ? $user->stop_payroll : 0,
                            ];
                        }

                        $userCommission = isset($userPayrollData->commission) ? $userPayrollData->commission : 0;
                        $userOverride = isset($userPayrollData->override) ? $userPayrollData->override : 0;
                        $totalSum = $userCommission + $userOverride;
                        $subTotal = (($totalSum) <= 0) ? 0 : round(($totalSum) * ($deductionLimit / 100), 2);
                        $subTotal = ($totalDeduction < $subTotal) ? $totalDeduction : $subTotal;

                        $finalDeductionTotal = 0;
                        foreach ($deductionRecords as $deductionRecord) {
                            $total = ($totalDeduction > 0) ? round($subTotal * ($deductionRecord['amount'] / $totalDeduction), 2) : 0;
                            $outstanding = $deductionRecord['amount'] - $total;

                            PayrollDeductions::updateOrCreate([
                                'pay_period_from' => $deductionRecord['pay_period_from'],
                                'pay_period_to' => $deductionRecord['pay_period_to'],
                                'user_id' => $deductionRecord['user_id'],
                                'cost_center_id' => $deductionRecord['cost_center_id'],
                            ], [
                                'payroll_id' => $deductionRecord['payroll_id'],
                                'amount' => round($deductionRecord['amount'], 2),
                                'limit' => round($deductionRecord['limit'], 2),
                                'total' => round($total, 2),
                                'outstanding' => round($outstanding, 2),
                                'subtotal' => round($subTotal, 2),
                                'is_stop_payroll' => $deductionRecord['is_stop_payroll'],
                            ]);

                            $finalDeductionTotal += $total;
                        }

                        if ($userPayrollData && $userPayrollData?->id) {
                            $deductionTotal = PayrollDeductions::where(['payroll_id' => $userPayrollData?->id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->sum('total') ?? 0;
                            Payroll::where('id', $userPayrollData?->id)->update(['deduction' => $deductionTotal, 'net_pay' => $userPayrollData->net_pay - $deductionTotal, 'gross_pay' => $userPayrollData->gross_pay - $deductionTotal]);
                        }
                    }
                }
            }
        }

        // $check = true;
        // if ($pay_frequency == 2) {
        //     $weekly = WeeklyPayFrequency::where(['closed_status'=> 0])->orderBy('id', 'ASC')->first();
        //     if (!empty($weekly)) {
        //         if ($weekly->pay_period_from != $start_date) {
        //             $check = false;
        //         }
        //     }
        // }elseif ($pay_frequency == 5) {
        //     $month = MonthlyPayFrequency::where(['closed_status'=> 0])->orderBy('id', 'ASC')->first();
        //     if (!empty($month)) {
        //         if ($month->pay_period_from != $start_date) {
        //             $check = false;
        //         }
        //     }

        // }elseif ($pay_frequency == 3 || $pay_frequency == 4) {
        //     $additionalFrequency = AdditionalPayFrequency::where(['closed_status'=> 0])->orderBy('id', 'ASC')->first();
        //     if (!empty($additionalFrequency)) {
        //         if ($additionalFrequency->pay_period_from != $start_date) {
        //             $check = false;
        //         }
        //     }
        // }
        // no need to add condiyion for daily pay frequency check

        // get users who's deductions status is ON.
        // if($check == true){

        //     $deduction_enable_users = User::select('id','sub_position_id','stop_payroll','dismiss')->with('positionDeductionLimit','positionpayfrequencies','userDeduction.costcenter','positionCommissionDeduction.costcenter')
        //     ->whereHas('positionDeductionLimit', function($q){
        //         $q->where('positions_duduction_limits.status',1);
        //     })
        //     ->whereHas('positionpayfrequencies', function($qry) use($pay_frequency){
        //         $qry->where('position_pay_frequencies.frequency_type_id','=',$pay_frequency);
        //     })
        //     ->where('is_super_admin','!=','1')
        //     //->where(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d")'), '<=' ,$start_date)
        //     ->where(DB::raw('DATE_FORMAT(period_of_agreement_start_date, "%Y-%m-%d")'), '<=' ,$end_date)
        //     // ->where('stop_payroll',0)
        //     // ->where('dismiss',0)
        //     ->where('worker_type',$workerType)
        //     ->get();

        //     // Log::info($deduction_enable_users);
        //     $enable_users = [];
        //     if (count($deduction_enable_users) > 0 && $start_date && $end_date) {
        //         if($pay_frequency== FrequencyType::DAILY_PAY_ID){
        //             $paydata = Payroll::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->whereIn('user_id',$usersids)->get();
        //         }else{
        //             $paydata = Payroll::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->whereIn('user_id',$usersids)->get();
        //         }
        //         $payroll_data = [];
        //         if(count($paydata) > 0) {
        //             foreach($paydata as $p){
        //                 $payroll_data[$p->user_id] = $p;
        //             }
        //         }

        //         $commission_deduction_amt = 0;
        //         $commission_deduction_percent_amt = 0;
        //         $commission_breakup_arr = [];
        //         $commission_deduction_amt_total = 0;
        //         $dediction_amount = 0;
        //         $position_deduction_limit = 0;
        //         $subtotal = 0;
        //         $prev_outstanding = 0;
        //         // $user_deduction = 0;
        //         foreach($deduction_enable_users as $key => $data){
        //             $positionPayFrequency = PositionPayFrequency::where('position_id', $data->sub_position_id)->first();
        //             $frequencyTypeId = $positionPayFrequency?->frequency_type_id;

        //             $enable_users[] = $data->id;
        //             if (count($data->userDeduction) != count($data->positionCommissionDeduction)) {
        //                 if($pay_frequency== FrequencyType::DAILY_PAY_ID){
        //                     PayrollDeductions::whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->id, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->where('status','!=',3)->delete();
        //                 }
        //                 else{
        //                     PayrollDeductions::where(['user_id' => $data->id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->where('status','!=',3)->delete();
        //                 }
        //             }

        //             $user_deduction = [];
        //             // $user_deduction = (count($data->userDeduction)>0)?$data->userDeduction:$data->positionCommissionDeduction;
        //             $user_deduction = (count($data->userDeduction)>0)?$data->positionCommissionDeduction:$data->positionCommissionDeduction;
        //             if(count($user_deduction)<=0){ continue; }
        //             if(isset($user_deduction[0]->ammount_par_paycheck)){
        //                 $d1 = $user_deduction[0]->ammount_par_paycheck;
        //             }
        //             if(isset($user_deduction[1]->ammount_par_paycheck)){
        //                 $d2 = $user_deduction[1]->ammount_par_paycheck;
        //             }

        //             $limit_type = isset($data->positionDeductionLimit->limit_type)?$data->positionDeductionLimit->limit_type:'';
        //             $limit_amount = isset($data->positionDeductionLimit->limit_ammount)?$data->positionDeductionLimit->limit_ammount:'0';

        //             if (empty($limit_amount)) {
        //                 $limit_amount = 100;
        //             }

        //             if(array_key_exists($data->id,$payroll_data)){
        //                 $payrolldata = $payroll_data[$data->id];

        //                 $subtotal = (($payrolldata->commission + $payrolldata->override)<=0)?0:round(($payrolldata->commission + $payrolldata->override)*($limit_amount/100),2);

        //                 // getting previous payroll id by current payroll_id
        //                 // $previous_id = PayrollHistory::where('user_id',$data->id)->where('payroll_id','<',$payrolldata->id)->orderBy('id','DESC')->pluck('payroll_id')->first();

        //                 $amount_total = 0;
        //                 $deduction_total = 0;
        //                 foreach($user_deduction as $key => $d){
        //                     $check = true;
        //                     if ($frequencyTypeId == 2) {
        //                         $weekly = WeeklyPayFrequency::when($workerType == 'w2', function ($q) {
        //                             $q->where('w2_closed_status', 0);
        //                         })->when($workerType == '1099', function ($q) {
        //                             $q->where('closed_status', 0);
        //                         })->where('pay_period_from', '>=', $d->pay_period_from)->orderBy('id', 'ASC')->first();
        //                         if (empty($weekly) || $weekly->pay_period_from != $start_date) {
        //                             $check = false;
        //                         }
        //                     } else if ($frequencyTypeId == 5) {
        //                         $month = MonthlyPayFrequency::when($workerType == 'w2', function ($q) {
        //                             $q->where('w2_closed_status', 0);
        //                         })->when($workerType == '1099', function ($q) {
        //                             $q->where('closed_status', 0);
        //                         })->where('pay_period_from', '>=', $d->pay_period_from)->orderBy('id', 'ASC')->first();
        //                         if (empty($month) || $month->pay_period_from != $start_date) {
        //                             $check = false;
        //                         }
        //                     } else if ($frequencyTypeId == 3 || $frequencyTypeId == 4) {
        //                         $additionalFrequency = AdditionalPayFrequency::when($workerType == 'w2', function ($q) {
        //                             $q->where('w2_closed_status', 0);
        //                         })->when($workerType == '1099', function ($q) {
        //                             $q->where('closed_status', 0);
        //                         })->where('pay_period_from', '>=', $d->pay_period_from)->orderBy('id', 'ASC')->first();
        //                         if (empty($additionalFrequency) || $additionalFrequency->pay_period_from != $start_date) {
        //                             $check = false;
        //                         }
        //                     }

        //                     if (!$check) {
        //                         continue;
        //                     }

        //                     $userDeductionHistory = UserDeductionHistory::select('user_id','cost_center_id','amount_par_paycheque')
        //                     ->with('costcenter:id,name,status')
        //                     ->whereHas('costcenter', function($q){
        //                         $q->where('status',1);
        //                    })
        //                     ->where(['user_id'=> $data->id, 'cost_center_id'=> $d->cost_center_id])->where('effective_date','<=',$end_date)->orderBy('effective_date','DESC')->first();
        //                     if ($userDeductionHistory) {
        //                         $d->ammount_par_paycheck = $userDeductionHistory->amount_par_paycheque;
        //                     }

        //                     $prev_outstanding = 0;
        //                     // $prev = PayrollDeductions::where('user_id',$data->id)->where('cost_center_id',$d->cost_center_id)->where('payroll_id',$previous_id)->select('outstanding','cost_center_id')->first();
        //                     if($pay_frequency== FrequencyType::DAILY_PAY_ID){
        //                         $prev = PayrollDeductions::select('id','outstanding','cost_center_id')
        //                         ->with('costcenter:id,name,status')
        //                         ->where(function ($query) {
        //                             $query->where('outstanding', '!=', 0)
        //                             ->orWhere(function ($subQuery) {
        //                                 $subQuery->where('outstanding', '=', 0)
        //                                         ->where(function ($q) {
        //                                             $q->whereNull('cost_center_id')
        //                                                 ->orWhereHas('costcenter', function ($q2) {
        //                                                     $q2->where('status', 1);
        //                                                 });
        //                                         });
        //                             });
        //                         })
        //                         ->whereNotBetween('pay_period_from', [$start_date, $end_date])->whereNotBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')
        //                         ->where('user_id',$data->id)->where('cost_center_id',$d->cost_center_id)->orderBy('id','DESC')->first();
        //                     } else {
        //                         $prev = PayrollDeductions::select('id','outstanding','cost_center_id')
        //                         ->with('costcenter:id,name,status')
        //                         ->where(function ($query) {
        //                             $query->where('outstanding', '!=', 0)
        //                             ->orWhere(function ($subQuery) {
        //                                 $subQuery->where('outstanding', '=', 0)
        //                                         ->where(function ($q) {
        //                                             $q->whereNull('cost_center_id')
        //                                                 ->orWhereHas('costcenter', function ($q2) {
        //                                                     $q2->where('status', 1);
        //                                                 });
        //                                         });
        //                             });
        //                         })
        //                         ->where('user_id',$data->id)->where('cost_center_id',$d->cost_center_id)->where('pay_period_from','!=',$start_date)->where('pay_period_to','!=',$end_date)->orderBy('id','DESC')->first();
        //                     }
        //                     $prev_outstanding = (isset($prev->outstanding))?round($prev->outstanding,2):0;

        //                     $amount_total += $d->ammount_par_paycheck + (($prev_outstanding>0)?$prev_outstanding:0);
        //                     $d->ammount_par_paycheck += (($prev_outstanding>0)?$prev_outstanding:0);
        //                 }
        //                 $subtotal = ($amount_total < $subtotal )?$amount_total:$subtotal;
        //                 $deduction_total = 0;
        //                 foreach($user_deduction as $key => $d){

        //                     $total =($amount_total>0)?round($subtotal * ($d->ammount_par_paycheck/$amount_total),2):0;
        //                     $outstanding = $d->ammount_par_paycheck - $total;

        //                     if($pay_frequency== FrequencyType::DAILY_PAY_ID){
        //                         $checkdata = PayrollDeductions::with('costcenter:id,name,status')
        //                         ->where(function ($query) {
        //                             $query->where('outstanding', '!=', 0)
        //                             ->orWhere(function ($subQuery) {
        //                                 $subQuery->where('outstanding', '=', 0)
        //                                         ->where(function ($q) {
        //                                             $q->whereNull('cost_center_id')
        //                                                 ->orWhereHas('costcenter', function ($q2) {
        //                                                     $q2->where('status', 1);
        //                                                 });
        //                                         });
        //                             });
        //                         })
        //                         ->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->id,
        //                         'cost_center_id' => $d->cost_center_id])->first();
        //                         if(!$checkdata){
        //                             PayrollDeductions::create([
        //                                 'payroll_id' => $payrolldata->id,
        //                                 'amount' => round($d->ammount_par_paycheck,2),
        //                                 'limit' => round($limit_amount,2),
        //                                 'total' => round($total,2),
        //                                 'outstanding' => round($outstanding,2),
        //                                 'subtotal' => round($subtotal,2),
        //                                 'is_stop_payroll' => isset($data->stop_payroll) ? $data->stop_payroll : 0,
        //                                 'pay_period_from' => $end_date,
        //                                 'pay_period_to' => $end_date,
        //                                 'user_id' => $data->id,
        //                                 'cost_center_id' => $d->cost_center_id,
        //                             ]);
        //                         } else {
        //                             PayrollDeductions::where([
        //                                 'id' => $checkdata->id,
        //                                 'user_id' => $checkdata->user_id,
        //                                 'cost_center_id' => $d->cost_center_id,
        //                             ])->update([
        //                                 'payroll_id' => $payrolldata->id,
        //                                 'user_id' => $checkdata->user_id,
        //                                 'amount' => round($d->ammount_par_paycheck,2),
        //                                 'limit' => round($limit_amount,2),
        //                                 'total' => round($total,2),
        //                                 'outstanding' => round($outstanding,2),
        //                                 'subtotal' => round($subtotal,2),
        //                                 'is_stop_payroll' => isset($data->stop_payroll) ? $data->stop_payroll : 0,
        //                                 'pay_period_from' => $end_date,
        //                                 'pay_period_to' => $end_date,
        //                                 'cost_center_id' => $d->cost_center_id,
        //                             ]);
        //                         }
        //                     } else{
        //                         PayrollDeductions::updateOrCreate([
        //                             'pay_period_from' => $start_date,
        //                             'pay_period_to' => $end_date,
        //                             'user_id' => $data->id,
        //                             'cost_center_id' => $d->cost_center_id,
        //                         ],[
        //                             'payroll_id' => $payrolldata->id,
        //                             'amount' => round($d->ammount_par_paycheck,2),
        //                             'limit' => round($limit_amount,2),
        //                             'total' => round($total,2),
        //                             'outstanding' => round($outstanding,2),
        //                             'subtotal' => round($subtotal,2),
        //                             'is_stop_payroll' => isset($data->stop_payroll) ? $data->stop_payroll : 0,
        //                             'pay_period_from' => $start_date,
        //                             'pay_period_to' => $end_date
        //                         ]);
        //                     }

        //                     $deduction_total +=$total;
        //                 }

        //                 $deductionTotal = PayrollDeductions::with('costcenter:id,name,status')
        //                 ->where(function ($query) {
        //                     $query->where('outstanding', '!=', 0)
        //                     ->orWhere(function ($subQuery) {
        //                         $subQuery->where('outstanding', '=', 0)
        //                                 ->where(function ($q) {
        //                                     $q->whereNull('cost_center_id')
        //                                         ->orWhereHas('costcenter', function ($q2) {
        //                                             $q2->where('status', 1);
        //                                         });
        //                                 });
        //                     });
        //                 })
        //                 ->when($pay_frequency == FrequencyType::DAILY_PAY_ID , function ($query) use ($start_date, $end_date) {

        //                      $query->whereBetween('pay_period_from', [$start_date, $end_date])
        //                           ->whereBetween('pay_period_to', [$start_date, $end_date])
        //                           ->whereColumn('pay_period_from', 'pay_period_to');
        //                 }, function ($query) use ($start_date, $end_date) {
        //                     $query->where([
        //                         'pay_period_from' => $start_date,
        //                         'pay_period_to' => $end_date
        //                     ]);
        //                 })->where(['payroll_id'=> $payrolldata->id, 'is_mark_paid'=> 0, 'is_next_payroll'=> 0, 'is_move_to_recon'=> 0])->sum('total');
        //                 // Payroll::where('id',$payrolldata->id)->update(['deduction'=>$deduction_total,'net_pay' => $payrolldata->net_pay - $deduction_total,'gross_pay' => $payrolldata->gross_pay - $deduction_total]);
        //                 Payroll::where('id',$payrolldata->id)->update(['deduction'=>$deductionTotal,'net_pay' => $payrolldata->net_pay - $deductionTotal,'gross_pay' => $payrolldata->gross_pay - $deductionTotal]);

        //             }
        //             elseif(!empty($data->id)){

        //                 $amount_total = 0;
        //                 $deduction_total = 0;
        //                 foreach($user_deduction as $key => $d){
        //                     $userDeductionHistory = UserDeductionHistory::with('costcenter')
        //                     ->whereHas('costcenter', function($q){
        //                         $q->where('status',1);
        //                     })
        //                     ->where(['user_id'=> $data->id, 'cost_center_id'=> $d->cost_center_id])->where('effective_date','<=',$end_date)->orderBy('effective_date','DESC')->first();
        //                     if ($userDeductionHistory) {
        //                         $d->ammount_par_paycheck = $userDeductionHistory->amount_par_paycheque;
        //                     }

        //                     $prev_outstanding = 0;
        //                     $prev = PayrollDeductions::select('id','outstanding','cost_center_id')
        //                     ->with('costcenter:id,name,status')
        //                     ->where(function ($query) {
        //                         $query->where('outstanding', '!=', 0)
        //                         ->orWhere(function ($subQuery) {
        //                             $subQuery->where('outstanding', '=', 0)
        //                                     ->where(function ($q) {
        //                                         $q->whereNull('cost_center_id')
        //                                             ->orWhereHas('costcenter', function ($q2) {
        //                                                 $q2->where('status', 1);
        //                                             });
        //                                     });
        //                         });
        //                     })
        //                     ->where('user_id',$data->id)->where('cost_center_id',$d->cost_center_id)
        //                     ->whereNotBetween('pay_period_from', [$start_date, $end_date])->whereNotBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')
        //                     ->orderBy('id','DESC')->first();
        //                     $prev_outstanding = (isset($prev->outstanding))?round($prev->outstanding,2):0;

        //                     $amount_total += $d->ammount_par_paycheck + (($prev_outstanding>0)?$prev_outstanding:0);
        //                     $d->ammount_par_paycheck += (($prev_outstanding>0)?$prev_outstanding:0);
        //                 }
        //                 // $subtotal = ($amount_total < $subtotal )?$amount_total:$subtotal;
        //                 $subtotal = $amount_total;
        //                 $deduction_total = 0;
        //                 foreach($user_deduction as $key => $d){

        //                     $total =($amount_total>0)?round($subtotal * ($d->ammount_par_paycheck/$amount_total),2):0;

        //                     if($pay_frequency== FrequencyType::DAILY_PAY_ID){
        //                         $checkdata = PayrollDeductions::with('costcenter:id,name,status')
        //                         ->where(function ($query) {
        //                             $query->where('outstanding', '!=', 0)
        //                             ->orWhere(function ($subQuery) {
        //                                 $subQuery->where('outstanding', '=', 0)
        //                                         ->where(function ($q) {
        //                                             $q->whereNull('cost_center_id')
        //                                                 ->orWhereHas('costcenter', function ($q2) {
        //                                                     $q2->where('status', 1);
        //                                                 });
        //                                         });
        //                             });
        //                         })
        //                         ->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->id, 'cost_center_id' => $d->cost_center_id])->first();
        //                         if(!$checkdata){
        //                             PayrollDeductions::create([
        //                                 'amount' => round($d->ammount_par_paycheck,2),
        //                                 'limit' => round($limit_amount,2),
        //                                 'total' => 0,
        //                                 'outstanding' => round($d->ammount_par_paycheck,2),
        //                                 'subtotal' => round($subtotal,2),
        //                                 'is_stop_payroll' => isset($data->stop_payroll) ? $data->stop_payroll : 0,
        //                                 'pay_period_from' => $end_date,
        //                                 'pay_period_to' => $end_date,
        //                                 'user_id' => $data->id,
        //                                 'cost_center_id' => $d->cost_center_id,
        //                             ]);
        //                         } else {
        //                             PayrollDeductions::where([
        //                                 'id' => $checkdata->id,
        //                                 'user_id' => $checkdata->user_id,
        //                                 'cost_center_id' => $d->cost_center_id,
        //                             ])->update([
        //                                 'amount' => round($d->ammount_par_paycheck,2),
        //                                 'limit' => round($limit_amount,2),
        //                                 'total' => 0,
        //                                 'outstanding' => round($d->ammount_par_paycheck,2),
        //                                 'subtotal' => round($subtotal,2),
        //                                 'is_stop_payroll' => isset($data->stop_payroll) ? $data->stop_payroll : 0,
        //                                 'pay_period_from' => $end_date,
        //                                 'pay_period_to' => $end_date,
        //                                 'user_id' => $data->id,
        //                                 'cost_center_id' => $d->cost_center_id,
        //                             ]);
        //                         }
        //                     } else {
        //                         PayrollDeductions::updateOrCreate([
        //                             'pay_period_from' => $start_date,
        //                             'pay_period_to' => $end_date,
        //                             'user_id' => $data->id,
        //                             'cost_center_id' => $d->cost_center_id,
        //                         ],[
        //                             //'payroll_id' => null,
        //                             'amount' => round($d->ammount_par_paycheck,2),
        //                             'limit' => round($limit_amount,2),
        //                             // 'total' => round($total,2),
        //                             // 'outstanding' => round($outstanding,2),
        //                             'total' => 0,
        //                             'outstanding' => round($d->ammount_par_paycheck,2),
        //                             'subtotal' => round($subtotal,2),
        //                             'is_stop_payroll' => isset($data->stop_payroll) ? $data->stop_payroll : 0,
        //                         ]);
        //                     }

        //                     $deduction_total +=$total;
        //                 }

        //             }

        //         }
        //     }

        //     $deductionPayPeriod = PayrollDeductions::select("pay_period_from","pay_period_to","cost_center_id")->with('costcenter:id,name,status')
        //     ->where(function ($query) {
        //         $query->where('outstanding', '!=', 0)
        //         ->orWhere(function ($subQuery) {
        //             $subQuery->where('outstanding', '=', 0)
        //                     ->where(function ($q) {
        //                         $q->whereNull('cost_center_id')
        //                             ->orWhereHas('costcenter', function ($q2) {
        //                                 $q2->where('status', 1);
        //                             });
        //                     });
        //         });
        //     })
        //     ->where("pay_period_from", "<", $start_date)->orderBy('pay_period_from', 'DESC')->first();
        //     if ($deductionPayPeriod) {
        //         // $deduction_pending_users = PayrollDeductions::where('user_id',$data->id)->where('cost_center_id',$d->cost_center_id)->where(['pay_period_from'=> $deductionPayPeriod->pay_period_from, 'pay_period_to'=> $deductionPayPeriod->pay_period_to])->orderBy('id','DESC')->get();
        //         $deduction_pending_users = PayrollDeductions::with('userdata','costcenter:id,name,status')
        //         ->where(function ($query) {
        //             $query->where('outstanding', '!=', 0)
        //             ->orWhere(function ($subQuery) {
        //                 $subQuery->where('outstanding', '=', 0)
        //                         ->where(function ($q) {
        //                             $q->whereNull('cost_center_id')
        //                                 ->orWhereHas('costcenter', function ($q2) {
        //                                     $q2->where('status', 1);
        //                                 });
        //                         });
        //             });
        //         })
        //         ->where(['pay_period_from'=> $deductionPayPeriod->pay_period_from, 'pay_period_to'=> $deductionPayPeriod->pay_period_to])->where('outstanding','>',0)->get();
        //         if (count($deduction_pending_users) > 0 && $start_date && $end_date) {
        //             $paydata = Payroll::
        //             when($pay_frequency == FrequencyType::DAILY_PAY_ID , function ($query) use ($start_date, $end_date) {

        //                  $query->whereBetween('pay_period_from', [$start_date, $end_date])
        //                       ->whereBetween('pay_period_to', [$start_date, $end_date])
        //                       ->whereColumn('pay_period_from', 'pay_period_to');
        //             }, function ($query) use ($start_date, $end_date) {
        //                 $query->where([
        //                     'pay_period_from' => $start_date,
        //                     'pay_period_to' => $end_date
        //                 ]);
        //             })->whereIn('user_id',$usersids)->get();
        //             $payroll_data = [];
        //             if(count($paydata) > 0) {
        //                 foreach($paydata as $p){
        //                     $payroll_data[$p->user_id] = $p;
        //                 }
        //             }

        //             $subtotal = 0;
        //             foreach($deduction_pending_users as $key => $data){

        //                 if (!in_array($data->user_id, $enable_users)) {

        //                     $limit_amount = isset($data->limit)?$data->limit:'0';
        //                     if (empty($limit_amount)) {
        //                         $limit_amount = 100;
        //                     }

        //                     if(array_key_exists($data->user_id,$payroll_data)){
        //                         $payrolldata = $payroll_data[$data->user_id];

        //                         $subtotal = (($payrolldata->commission + $payrolldata->override)<=0)?0:round(($payrolldata->commission + $payrolldata->override)*($limit_amount/100),2);

        //                         $prev_outstanding = (isset($data->outstanding))?round($data->outstanding,2):0;
        //                         $amount_total = $prev_outstanding;

        //                         $subtotal = ($amount_total < $subtotal )?$amount_total:$subtotal;

        //                         $total = ($amount_total>0)?round($subtotal,2):0;
        //                         $outstanding = $amount_total - $total;
        //                         if($pay_frequency == FrequencyType::DAILY_PAY_ID){
        //                             $checkdata = PayrollDeductions::with('costcenter:id,name,status')
        //                             ->where(function ($query) {
        //                                 $query->where('outstanding', '!=', 0)
        //                                 ->orWhere(function ($subQuery) {
        //                                     $subQuery->where('outstanding', '=', 0)
        //                                             ->where(function ($q) {
        //                                                 $q->whereNull('cost_center_id')
        //                                                     ->orWhereHas('costcenter', function ($q2) {
        //                                                         $q2->where('status', 1);
        //                                                     });
        //                                             });
        //                                 });
        //                             })
        //                             ->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->user_id, 'cost_center_id' => $data->cost_center_id])->first();
        //                             if(!$checkdata){
        //                                 PayrollDeductions::create([
        //                                     'payroll_id' => $payrolldata->id,
        //                                     'amount' => round($prev_outstanding,2),
        //                                     'limit' => round($limit_amount,2),
        //                                     'total' => round($total,2),
        //                                     'outstanding' => round($outstanding,2),
        //                                     'subtotal' => round($subtotal,2),
        //                                     'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                                     'pay_period_from' => $end_date,
        //                                     'pay_period_to' => $end_date,
        //                                     'user_id' => $data->user_id,
        //                                     'cost_center_id' => $data->cost_center_id,
        //                                 ]);
        //                             } else {
        //                                 PayrollDeductions::where([
        //                                     'id' => $checkdata->id,
        //                                     'user_id' => $data->user_id,
        //                                     'cost_center_id' => $data->cost_center_id,
        //                                 ])->update([
        //                                     'payroll_id' => $payrolldata->id,
        //                                     'amount' => round($prev_outstanding,2),
        //                                     'limit' => round($limit_amount,2),
        //                                     'total' => round($total,2),
        //                                     'outstanding' => round($outstanding,2),
        //                                     'subtotal' => round($subtotal,2),
        //                                     'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                                     'pay_period_from' => $end_date,
        //                                     'pay_period_to' => $end_date,
        //                                     'user_id' => $data->user_id,
        //                                     'cost_center_id' => $data->cost_center_id,
        //                                 ]);
        //                             }
        //                         } else {

        //                             if($pay_frequency == FrequencyType::DAILY_PAY_ID){
        //                                 $checkdata = PayrollDeductions::with('costcenter:id,name,status')
        //                                 ->where(function ($query) {
        //                                     $query->where('outstanding', '!=', 0)
        //                                     ->orWhere(function ($subQuery) {
        //                                         $subQuery->where('outstanding', '=', 0)
        //                                                 ->where(function ($q) {
        //                                                     $q->whereNull('cost_center_id')
        //                                                         ->orWhereHas('costcenter', function ($q2) {
        //                                                             $q2->where('status', 1);
        //                                                         });
        //                                                 });
        //                                     });
        //                                 })
        //                                 ->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->user_id, 'cost_center_id' => $data->cost_center_id])->first();
        //                                 if(!$checkdata){
        //                                     PayrollDeductions::create([
        //                                         'payroll_id' => $payrolldata->id,
        //                                         'amount' => round($prev_outstanding,2),
        //                                         'limit' => round($limit_amount,2),
        //                                         'total' => round($total,2),
        //                                         'outstanding' => round($outstanding,2),
        //                                         'subtotal' => round($subtotal,2),
        //                                         'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                                         'pay_period_from' => $end_date,
        //                                         'pay_period_to' => $end_date,
        //                                         'user_id' => $data->user_id,
        //                                         'cost_center_id' => $data->cost_center_id
        //                                     ]);
        //                                 } else {
        //                                     PayrollDeductions::where([
        //                                         'id' => $checkdata->id,
        //                                         'user_id' => $data->user_id,
        //                                         'cost_center_id' => $data->cost_center_id,
        //                                     ])->update([
        //                                         'payroll_id' => $payrolldata->id,
        //                                         'amount' => round($prev_outstanding,2),
        //                                         'limit' => round($limit_amount,2),
        //                                         'total' => round($total,2),
        //                                         'outstanding' => round($outstanding,2),
        //                                         'subtotal' => round($subtotal,2),
        //                                         'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                                         'pay_period_from' => $end_date,
        //                                         'pay_period_to' => $end_date,
        //                                         'user_id' => $data->user_id,
        //                                         'cost_center_id' => $data->cost_center_id
        //                                     ]);
        //                                 }
        //                             } else {
        //                                 PayrollDeductions::updateOrCreate([
        //                                     'pay_period_from' => $start_date,
        //                                     'pay_period_to' => $end_date,
        //                                     'user_id' => $data->user_id,
        //                                     'cost_center_id' => $data->cost_center_id,
        //                                 ],[
        //                                     'payroll_id' => $payrolldata->id,
        //                                     'amount' => round($prev_outstanding,2),
        //                                     'limit' => round($limit_amount,2),
        //                                     'total' => round($total,2),
        //                                     'outstanding' => round($outstanding,2),
        //                                     'subtotal' => round($subtotal,2),
        //                                     'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                                     'pay_period_from' => $start_date,
        //                                     'pay_period_to' => $end_date
        //                                 ]);
        //                             }
        //                         }

        //                         // $deductionTotal = PayrollDeductions::when($pay_frequency == FrequencyType::DAILY_PAY_ID , function ($query) use ($start_date, $end_date) {
        //                         //     $query->whereBetween('p1.pay_period_from', [$start_date, $end_date])
        //                         //           ->whereBetween('p1.pay_period_to', [$start_date, $end_date])
        //                         //           ->whereColumn('p1.pay_period_from', 'p1.pay_period_to');
        //                         // }, function ($query) use ($start_date, $end_date) {
        //                         //     $query->where([
        //                         //         'p1.pay_period_from' => $start_date,
        //                         //         'p1.pay_period_to' => $end_date
        //                         //     ]);
        //                         // })->where(['payroll_id'=> $payrolldata->id, 'is_mark_paid'=> 0, 'is_next_payroll'=> 0])->whereIn('user_id',$usersids)->sum('total');

        //                         $deductionTotal = PayrollDeductions::with('costcenter:id,name,status')
        //                         ->where(function ($query) {
        //                             $query->where('outstanding', '!=', 0)
        //                             ->orWhere(function ($subQuery) {
        //                                 $subQuery->where('outstanding', '=', 0)
        //                                         ->where(function ($q) {
        //                                             $q->whereNull('cost_center_id')
        //                                                 ->orWhereHas('costcenter', function ($q2) {
        //                                                     $q2->where('status', 1);
        //                                                 });
        //                                         });
        //                             });
        //                         })
        //                         ->when($pay_frequency == FrequencyType::DAILY_PAY_ID , function ($query) use ($start_date, $end_date) {
        //                             $query->whereBetween('pay_period_from', [$start_date, $end_date])
        //                                   ->whereBetween('pay_period_to', [$start_date, $end_date])
        //                                   ->whereColumn('pay_period_from', 'pay_period_to');
        //                         }, function ($query) use ($start_date, $end_date) {
        //                             $query->where([
        //                                 'pay_period_from' => $start_date,
        //                                 'pay_period_to' => $end_date
        //                             ]);
        //                         })->where(['payroll_id'=> $payrolldata->id, 'is_mark_paid'=> 0, 'is_next_payroll'=> 0])->whereIn('user_id',$usersids)->sum('total');

        //                         Payroll::where('id',$payrolldata->id)->update(['deduction'=>$deductionTotal,'net_pay' => $payrolldata->net_pay - $deductionTotal,'gross_pay' => $payrolldata->gross_pay - $deductionTotal]);
        //                     }
        //                     elseif(!empty($data->user_id)){
        //                         $subtotal = 0;
        //                         $outstanding = (isset($data->outstanding))?round($data->outstanding,2):0;
        //                         if($pay_frequency == FrequencyType::DAILY_PAY_ID) {
        //                             $checkdata = PayrollDeductions::with('costcenter:id,name,status')
        //                             ->where(function ($query) {
        //                                 $query->where('outstanding', '!=', 0)
        //                                 ->orWhere(function ($subQuery) {
        //                                     $subQuery->where('outstanding', '=', 0)
        //                                             ->where(function ($q) {
        //                                                 $q->whereNull('cost_center_id')
        //                                                     ->orWhereHas('costcenter', function ($q2) {
        //                                                         $q2->where('status', 1);
        //                                                     });
        //                                             });
        //                                 });
        //                             })
        //                             ->whereBetween('pay_period_from', [$start_date, $end_date])->whereBetween('pay_period_to', [$start_date, $end_date])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $data->user_id, 'cost_center_id' => $data->cost_center_id])->first();
        //                             if(!$checkdata){
        //                                 PayrollDeductions::create([
        //                                     'amount' => round($outstanding,2),
        //                                     'limit' => round($limit_amount,2),
        //                                     'total' => 0,
        //                                     'outstanding' => round($outstanding,2),
        //                                     'subtotal' => round($subtotal,2),
        //                                     'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                                     'pay_period_from' => $start_date,
        //                                     'pay_period_to' => $end_date,
        //                                     'user_id' => $data->user_id,
        //                                     'cost_center_id' => $data->cost_center_id,
        //                                 ]);
        //                             } else {
        //                                 PayrollDeductions::where([
        //                                     'id' => $checkdata->id,
        //                                     'user_id' => $data->user_id,
        //                                     'cost_center_id' => $data->cost_center_id,
        //                                 ])->update([
        //                                     'amount' => round($outstanding,2),
        //                                     'limit' => round($limit_amount,2),
        //                                     'total' => 0,
        //                                     'outstanding' => round($outstanding,2),
        //                                     'subtotal' => round($subtotal,2),
        //                                     'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                                     'pay_period_from' => $start_date,
        //                                     'pay_period_to' => $end_date,
        //                                     'user_id' => $data->user_id,
        //                                     'cost_center_id' => $data->cost_center_id,
        //                                 ]);
        //                             }
        //                         } else {
        //                             PayrollDeductions::updateOrCreate([
        //                                 'pay_period_from' => $start_date,
        //                                 'pay_period_to' => $end_date,
        //                                 'user_id' => $data->user_id,
        //                                 'cost_center_id' => $data->cost_center_id,
        //                             ],[

        //                                 'amount' => round($outstanding,2),
        //                                 'limit' => round($limit_amount,2),
        //                                 'total' => 0,
        //                                 'outstanding' => round($outstanding,2),
        //                                 'subtotal' => round($subtotal,2),
        //                                 'is_stop_payroll' => isset($data->userdata->stop_payroll) ? $data->userdata->stop_payroll : 0,
        //                             ]);
        //                         }
        //                     }

        //                 }

        //             }
        //         }

        //     }

        // }
    }

    public function getSummaryPayroll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'frequency_type_id' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        return $this->getPayrollDataSummary($request, Payroll::class);
    }

    public function getPayrollDataSummary(Request $request, $payrollClass)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $frequencyTypeId = $request->frequency_type_id;
        $commission = 0;
        if ($frequencyTypeId == FrequencyType::WEEKLY_ID) {
            $class = WeeklyPayFrequency::class;
        } elseif ($frequencyTypeId == FrequencyType::MONTHLY_ID) {
            $class = MonthlyPayFrequency::class;
        } elseif ($frequencyTypeId == FrequencyType::BI_WEEKLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
        } elseif ($frequencyTypeId == FrequencyType::SEMI_MONTHLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
        } elseif ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
            $class = DailyPayFrequency::class;
        }
        if (isset($class)) {
            $payFrequency = $class::query();
            if ($frequencyTypeId == FrequencyType::BI_WEEKLY_ID || $frequencyTypeId == FrequencyType::SEMI_MONTHLY_ID) {
                $payFrequency = $payFrequency->where('type', $type)->where('pay_period_from', '<', $startDate)->orderBy('pay_period_from', 'DESC')->first();
            } elseif ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
                $payFrequency = $payFrequency->where('pay_period_from', '<', $startDate)->orderBy('pay_period_from', 'DESC')->first();
            } else {
                $payFrequency = $payFrequency->where('pay_period_from', '<', $startDate)->orderBy('pay_period_from', 'DESC')->first();
            }
            if (! $payFrequency) {
                $newClass = Payroll::class;
                $priviesStartDate = null;
                $priviesEndDate = null;
            } else {
                if ($payFrequency->closed_status == '1') {
                    $newClass = PayrollHistory::class;
                } else {
                    $newClass = Payroll::class;
                }
                $priviesStartDate = $payFrequency->pay_period_from;
                $priviesEndDate = $payFrequency->pay_period_to;
            }
        }
        if ($startDate) {
            // Current Payroll Summary Start
            $payrolldata = $payrollClass::query()->select(
                DB::raw('sum(commission) as commission'),
                DB::raw('sum(override) as override'),
                DB::raw('sum(adjustment) as adjustment'),
                DB::raw('sum(reimbursement) as reimbursement'),
                DB::raw('sum(reconciliation) as reconciliation'),
                DB::raw('sum(deduction) as deduction'),
                DB::raw('sum(net_pay) as net_pay'),
                DB::raw('sum(hourly_salary) as hourly_salary'),
                DB::raw('sum(overtime) as overtime')
            )->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])
                    ->whereBetween('pay_period_to', [$startDate, $endDate])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where('pay_period_from', $startDate)
                    ->where('pay_period_to', $endDate);
            });
            if ($payrollClass == Payroll::class) {
                $payrolldata = $payrolldata->where('is_stop_payroll', '0');
            }
            $payrolldata = $payrolldata->first();
            if ($payrollClass == Payroll::class) {
                $payrollIds = $payrollClass::query()
                    ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('pay_period_from', [$startDate, $endDate])
                            ->whereBetween('pay_period_to', [$startDate, $endDate])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($startDate, $endDate) {
                        $query->where('pay_period_from', $startDate)
                            ->where('pay_period_to', $endDate);
                    })
                    ->where(['is_stop_payroll' => '0', 'is_mark_paid' => 0, 'is_next_payroll' => 0])->pluck('id')->toArray();
                $sumCustomeField = CustomField::whereIn('payroll_id', $payrollIds)->sum('value');
                // Get All Custom Fields
                $payrollCustomeFields = PayrollSsetup::where('status', 1)->orderBy('id', 'Asc')->get();
                $payrollCustomeFields->transform(function ($payrollCustomeFields) use ($payrollIds) {
                    $customeFieldValue = CustomField::where('column_id', $payrollCustomeFields->id)->whereIn('payroll_id', $payrollIds)->sum('value');

                    return [
                        'id' => $payrollCustomeFields->id,
                        'field_name' => $payrollCustomeFields->field_name ?? '',
                        'value' => $customeFieldValue,
                    ];
                });
            } else {
                $payrollIds = $payrollClass::query()
                    ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('pay_period_from', [$startDate, $endDate])
                            ->whereBetween('pay_period_to', [$startDate, $endDate])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($startDate, $endDate) {
                        $query->where('pay_period_from', $startDate)
                            ->where('pay_period_to', $endDate);
                    })
                    ->pluck('payroll_id')->toArray();
                $sumCustomeField = CustomFieldHistory::whereIn('payroll_id', $payrollIds)->where(['is_mark_paid' => '0', 'is_next_payroll' => '0'])->sum('value');
                // Get All Custom Fields
                $payrollCustomeFields = PayrollSsetup::where('status', 1)->orderBy('id', 'Asc')->get();
                $payrollCustomeFields->transform(function ($payrollCustomeFields) use ($payrollIds) {
                    $customeFieldValue = CustomFieldHistory::where(['column_id' => $payrollCustomeFields->id, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->whereIn('payroll_id', $payrollIds)->sum('value');

                    return [
                        'id' => $payrollCustomeFields->id,
                        'field_name' => $payrollCustomeFields->field_name ?? '',
                        'value' => $customeFieldValue,
                    ];
                });
            }
            $commission = $payrolldata->commission;
            $override = $payrolldata->override;
            $adjustment = $payrolldata->adjustment;
            $reimbursement = $payrolldata->reimbursement;
            $reconciliation = $payrolldata->reconciliation;
            $deduction = (0 - $payrolldata->deduction);
            $totalCustomFields = $sumCustomeField;
            $hourly_salary = $payrolldata->hourly_salary;
            $overtime = $payrolldata->overtime;
            $payrollSum = $payrolldata->net_pay;
            // Current Payroll Summary End
            // Current Payroll By Location Start
            if ($payrollClass == Payroll::class) {
                $payrollData = $payrollClass::query()->select('states.name', DB::raw('sum(net_pay) as net_pay'))
                    ->leftJoin('users', 'users.id', 'payrolls.user_id')->leftJoin('states', 'states.id', 'users.state_id')
                    ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('pay_period_from', [$startDate, $endDate])
                            ->whereBetween('pay_period_to', [$startDate, $endDate])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($startDate, $endDate) {
                        $query->where('pay_period_from', $startDate)
                            ->where('pay_period_to', $endDate);
                    })
                    ->where(['payrolls.is_stop_payroll' => '0']);
            } else {
                $payrollData = $payrollClass::query()->select('states.name', DB::raw('sum(net_pay) as net_pay'))
                    ->leftJoin('users', 'users.id', 'payroll_history.user_id')->leftJoin('states', 'states.id', 'users.state_id')
                    ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('pay_period_from', [$startDate, $endDate])
                            ->whereBetween('pay_period_to', [$startDate, $endDate])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($startDate, $endDate) {
                        $query->where('pay_period_from', $startDate)
                            ->where('pay_period_to', $endDate);
                    });
            }
            $payrollData = $payrollData->groupBy('users.state_id')->get();
            $stateName = [];
            $stateTotal = 0;
            foreach ($payrollData as $payroll) {
                $stateTotal += $payroll->net_pay;
                $stateName[] = [
                    'state' => $payroll->name,
                    'total' => round($payroll->net_pay, 3),
                    'locationCustomField' => 0,
                    'state_total' => round($payroll->net_pay, 3),
                ];
            }
            // Current Payroll By Location End
            // Current Payroll By Position Start
            if ($payrollClass == Payroll::class) {
                $payRollByPosition = $payrollClass::query()->with('positionDetail')->select('position_id', DB::raw('SUM(payrolls.net_pay) AS netPayTotal'), 'payrolls.id as payroll_id')
                    ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('pay_period_from', [$startDate, $endDate])
                            ->whereBetween('pay_period_to', [$startDate, $endDate])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($startDate, $endDate) {
                        $query->where('pay_period_from', $startDate)
                            ->where('pay_period_to', $endDate);
                    })
                    ->where(['is_stop_payroll' => '0']);
            } else {
                $payRollByPosition = $payrollClass::query()->with('positionDetail')->select('position_id', DB::raw('SUM(payroll_history.net_pay) AS netPayTotal'), 'payroll_history.id as payroll_id')
                    ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('pay_period_from', [$startDate, $endDate])
                            ->whereBetween('pay_period_to', [$startDate, $endDate])
                            ->whereColumn('pay_period_from', 'pay_period_to');
                    }, function ($query) use ($startDate, $endDate) {
                        $query->where('pay_period_from', $startDate)
                            ->where('pay_period_to', $endDate);
                    });
            }
            $payRollByPosition = $payRollByPosition->groupBy('position_id')->get();
            $payRollByPositionTotal = 0;
            $payRollByPosition = $payRollByPosition->transform(function ($position) use (&$payRollByPositionTotal) {
                $payRollByPositionTotal += $position->netPayTotal;

                return [
                    'position_name' => $position?->positionDetail?->position_name,
                    'total' => round($position->netPayTotal, 3),
                    'positionCustomField' => 0,
                    'position_total' => round($position->netPayTotal, 3),
                    'positionid' => $position?->positionDetail?->id,
                ];
            });
            // Current Payroll By Position End
        }
        $priviesTotalPayroll = 0;
        if ($priviesStartDate) {
            // Previous Payroll Summary Start
            $payrolldata = $newClass::query();
            $payrolldata = $payrolldata->select(DB::raw('SUM(net_pay) as net_pay'))
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($priviesStartDate, $priviesEndDate) {
                    $query->whereBetween('pay_period_from', [$priviesStartDate, $priviesEndDate])
                        ->whereBetween('pay_period_to', [$priviesStartDate, $priviesEndDate])
                        ->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($priviesStartDate, $priviesEndDate) {
                    $query->where('pay_period_from', $priviesStartDate)
                        ->where('pay_period_to', $priviesEndDate);
                });
            if ($newClass == Payroll::class) {
                $payrolldata = $payrolldata->where('is_stop_payroll', '0');
            }
            $payrolldata = $payrolldata->first();
            $priviesTotalPayroll = $payrolldata->net_pay;
            // Previous Payroll Summary End
        }
        $currentPayroll = round($payrollSum, 3);
        $priviesPayroll = round($priviesTotalPayroll, 3);
        if ($currentPayroll && $priviesPayroll) {
            $percentage = (($currentPayroll - $priviesPayroll) / $currentPayroll) * 100;
        } elseif ($currentPayroll) {
            $percentage = 100;
        } elseif ($priviesPayroll) {
            $percentage = -100;
        } else {
            $percentage = 0;
        }
        $payroll['commission'] = round(($commission), 3);
        $payroll['override'] = round($override, 3);
        $payroll['adjustment'] = round($adjustment, 3);
        $payroll['reimbursement'] = round($reimbursement, 3);
        $payroll['reconciliation'] = round($reconciliation, 3);
        $payroll['deduction'] = round($deduction, 3);
        $payroll['hourly_salary'] = round($hourly_salary, 3);
        $payroll['overtime'] = round($overtime, 3);
        $payroll['custom_fields'] = $payrollCustomeFields;
        $payroll['total_custom_fields'] = round($totalCustomFields, 3);
        $payroll['total_payroll_percentage'] = round($percentage, 3);
        $payroll['total_payroll'] = $payrollSum;
        $data['payroll'] = $payroll;
        $data['payroll_by_location'] = $stateName;
        $data['payroll_by_location_total_percentage'] = round($percentage, 3);
        $data['payroll_by_location_total'] = round($stateTotal, 3);
        $data['payroll_by_position'] = $payRollByPosition;
        $data['total_payroll_by_position_percentage'] = round($percentage, 3);
        $data['total_payroll_by_position'] = round($payRollByPositionTotal, 3);

        return response()->json([
            'ApiName' => 'Summary Payroll Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function hourlySalaryDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $payroll_id = $request->payroll_id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = Payroll::where(['id' => $payroll_id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        if (empty($payroll)) {
            $payrollHistory = PayrollHistory::where([
                'payroll_id' => $payroll_id,
                'user_id' => $user_id,
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
                'is_onetime_payment' => 1,
            ])
                ->first();

            $payroll = $payrollHistory;
        }

        if (! empty($payroll)) {
            $userSalary = PayrollHourlySalary::with('payrollcommon')->whereIn('status', [1, 2, 6])
                ->where([
                    'payroll_id' => $payroll_id,
                    'user_id' => $payroll->user_id,
                    'pay_period_from' => $payroll->pay_period_from,
                    'pay_period_to' => $payroll->pay_period_to,
                ])
                ->get();
            $rhour = 0;
            $total = 0;
            $adj = 0;
            $salary = 0;
            $adjustment = 0;
            $totalHours = 0;
            $totalMinutes = 0;

            foreach ($userSalary as $key => $value) {
                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';
                $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->where(['payroll_id' => $value->payroll_id, 'user_id' => $value->user_id, 'payroll_type' => 'hourlysalary', 'type' => 'hourlysalary'])->first();
                $totalTime = Carbon::createFromTime(0, 0);
                $date = isset($value->date) ? $value->date : '';
                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'payroll_id' => $value->payroll_id,
                    'date' => isset($date) ? $date : null,
                    'hourly_rate' => isset($value->hourly_rate) ? $value->hourly_rate * 1 : null,
                    'salary' => isset($value->salary) ? $value->salary * 1 : null,
                    'regular_hour' => isset($value->regular_hours) ? $value->regular_hours : null,
                    'total' => isset($value->total) ? $value->total * 1 : null,
                    'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                    'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                    'position_id' => $value->position_id,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'is_stop_payroll' => @$payroll->is_stop_payroll,
                    'hourlysalary_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'amount_type' => 'hourlysalary',
                ];
                $adjustmentAmount = isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0;
                $salary1 = isset($value->salary) ? $value->salary * 1 : 0;
                $total1 = isset($value->total) ? $value->total * 1 : 0;
                $regular_hours1 = isset($value->regular_hours) ? $value->regular_hours : '00:00';

                $time = Carbon::createFromTimeString($regular_hours1);
                $totalHours += $time->hour;
                $totalMinutes += $time->minute;

                $salary += $salary1;
                $adjustment += $adjustmentAmount;
                $total += $total1;
            }
            $totalHours += intdiv($totalMinutes, 60);
            $totalRemainingMinutes = $totalMinutes % 60;

            // Format the result ensuring it's within a 24-hour range
            $finalHours = $totalHours % 24;
            $result = Carbon::createFromTime($finalHours, $totalRemainingMinutes);
            $subtotal['regular_hours'] = $result->format('H:i');
            $subtotal['salary'] = $salary;
            $subtotal['adjustment'] = $adjustment;
            $subtotal['total'] = $total;

            $data['subtotal'] = $subtotal;

            return response()->json([
                'ApiName' => 'hourly_salary_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll->status,
                'data' => $data,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'hourly_salary_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function overtimeDetails(Request $request): JsonResponse
    {
        $data = [];
        $subtotal = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $payroll_id = $request->payroll_id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = Payroll::where(['id' => $payroll_id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        if (empty($payroll)) {
            $payrollHistory = PayrollHistory::where([
                'payroll_id' => $payroll_id,
                'user_id' => $user_id,
                'pay_period_from' => $pay_period_from,
                'pay_period_to' => $pay_period_to,
                'is_onetime_payment' => 1,
            ])
                ->first();

            $payroll = $payrollHistory;
        }

        if (! empty($payroll)) {
            $userSalary = PayrollOvertime::with('payrollcommon')->whereIn('status', [1, 2, 6])
                ->where([
                    'payroll_id' => $payroll_id,
                    'user_id' => $payroll->user_id,
                    'pay_period_from' => $payroll->pay_period_from,
                    'pay_period_to' => $payroll->pay_period_to,
                ])
                ->get();
            $overtime = 0;
            $adjustmentAmounttotal = 0;
            $total = 0;

            foreach ($userSalary as $key => $value) {
                $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
                $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
                $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)) : '';
                $adjustmentAmount = PayrollAdjustmentDetail::with('commented_by')->where(['payroll_id' => $value->payroll_id, 'user_id' => $value->user_id, 'payroll_type' => 'overtime', 'type' => 'overtime'])->first();
                $date = isset($value->date) ? $value->date : '';

                $data[$payroll_status][$period][] = [
                    'id' => $value->id,
                    'payroll_id' => $value->payroll_id,
                    'date' => isset($date) ? $date : null,
                    'overtime_rate' => isset($value->overtime_rate) ? $value->overtime_rate * 1 : null,
                    'overtime' => isset($value->overtime) ? $value->overtime * 1 : null,
                    'total' => isset($value->total) ? $value->total * 1 : null,
                    'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                    'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                    'position_id' => $value->position_id,
                    'is_mark_paid' => $value->is_mark_paid,
                    'is_next_payroll' => $value->is_next_payroll,
                    'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                    'is_stop_payroll' => @$payroll->is_stop_payroll,
                    'overtime_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name.' '.$adjustmentAmount->commented_by->last_name : 'Super Admin',
                    'adjustment_comment' => isset($adjustmentAmount->comment) ? $adjustmentAmount->comment : null,
                    'adjustment_id' => isset($adjustmentAmount->id) ? $adjustmentAmount->id : null,
                    'amount_type' => 'overtime',
                ];
                $adjustmentAmount1 = isset($adjustmentAmount->amount) ? $adjustmentAmount->amount * 1 : 0;
                $overtime1 = isset($value->overtime) ? $value->overtime * 1 : 0;
                $total1 = isset($value->total) ? $value->total * 1 : 0;
                $overtime += $overtime1;
                $adjustmentAmounttotal += $adjustmentAmount1;
                $total += $total1;
                unset($value);
            }
            $subtotal['overtime'] = floatval(number_format($overtime, 2));
            $subtotal['adjustment'] = $adjustmentAmounttotal;
            $subtotal['total'] = $total;
            $data['subtotal'] = $subtotal;

            return response()->json([
                'ApiName' => 'overtime_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll->status,
                'data' => $data,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'overtime_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function payrollhourlysalaryedit(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
                // 'user_id'    => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        // return $request;
        $data = [];
        $payrollId = $request->payroll_id;
        // $userId = $request->user_id;
        $pid = null;
        $type = $request->type;
        $amount = $request->amount;
        $comment = $request->comment;
        $payroll = Payroll::where('id', $payrollId)->first();

        if (Payroll::where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => '2'])->first()) {
            return response()->json([
                'ApiName' => 'payroll_hourly_salary_edit',
                'status' => false,
                'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
            ], 400);
        }

        $userId = $payroll->user_id;
        $data = [
            'payroll_id' => $payrollId,
            'user_id' => $userId,
            'pid' => $pid,
            'payroll_type' => 'hourlysalary',
            'type' => $type,
            'amount' => $amount,
            'comment' => $comment,
            'comment_by' => Auth::user()->id,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ];
        $dataPayroll = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'type' => $type, 'payroll_type' => 'hourlysalary'])->first();
        if ($dataPayroll) {
            PayrollAdjustmentDetail::where('id', $dataPayroll->id)->update($data);
            Payroll::where('id', $payrollId)->update(['status' => 1, 'finalize_status' => 0]);
        } else {
            PayrollAdjustmentDetail::create($data);
        }

        $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payrollId, 'user_id' => $userId])->first();
        $totalamount = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'payroll_type' => 'hourlysalary'])->sum('amount');
        if ($PayrollAdjustment) {
            $updateAjustment = PayrollAdjustment::where(['payroll_id' => $payrollId, 'user_id' => $userId])->update(['hourlysalary_amount' => $totalamount, 'status' => 1, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to]);
        } else {

            $data1 = [
                'payroll_id' => $payrollId,
                'user_id' => $userId,
                'hourlysalary_amount' => $totalamount,
                'overrides_amount' => 0,
                'adjustments_amount' => 0,
                'reimbursements_amount' => 0,
                'deductions_amount' => 0,
                'reconciliations_amount' => 0,
                'clawbacks_amount' => 0,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
            ];
            $addPayrollAdjustment = PayrollAdjustment::Create($data1);
        }

        return response()->json([
            'ApiName' => 'payroll_hourly_salary_edit',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function payrollovertimeedit(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
                // 'user_id'    => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        // return $request;
        $data = [];
        $payrollId = $request->payroll_id;
        // $userId = $request->user_id;
        $pid = null;
        $type = $request->type;
        $amount = $request->amount;
        $comment = $request->comment;
        $payroll = Payroll::where('id', $payrollId)->first();

        if (Payroll::where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => '2'])->first()) {
            return response()->json([
                'ApiName' => 'payroll_overtime_edit',
                'status' => false,
                'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
            ], 400);
        }

        $userId = $payroll->user_id;
        $data = [
            'payroll_id' => $payrollId,
            'user_id' => $userId,
            'pid' => $pid,
            'payroll_type' => 'overtime',
            'type' => $type,
            'amount' => $amount,
            'comment' => $comment,
            'comment_by' => Auth::user()->id,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ];
        $dataPayroll = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'type' => $type, 'payroll_type' => 'overtime'])->first();
        if ($dataPayroll) {
            PayrollAdjustmentDetail::where('id', $dataPayroll->id)->update($data);
            Payroll::where('id', $payrollId)->update(['status' => 1, 'finalize_status' => 0]);
        } else {
            PayrollAdjustmentDetail::create($data);
        }

        $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payrollId, 'user_id' => $userId])->first();
        $totalamount = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'payroll_type' => 'overtime'])->sum('amount');
        if ($PayrollAdjustment) {
            $updateAjustment = PayrollAdjustment::where(['payroll_id' => $payrollId, 'user_id' => $userId])->update(['overtime_amount' => $totalamount, 'status' => 1, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to]);
        } else {

            $data1 = [
                'payroll_id' => $payrollId,
                'user_id' => $userId,
                'overtime_amount' => $totalamount,
                'overrides_amount' => 0,
                'adjustments_amount' => 0,
                'reimbursements_amount' => 0,
                'deductions_amount' => 0,
                'reconciliations_amount' => 0,
                'clawbacks_amount' => 0,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
            ];
            $addPayrollAdjustment = PayrollAdjustment::Create($data1);
        }

        return response()->json([
            'ApiName' => 'payroll_overtime_edit',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getPayrollDataForContactor(Request $request)
    {
        $data = [];
        $payroll_total = 0;
        // $workerType = isset($request->worker_type) ? $request->worker_type : '1099';
        $workerType = '1099';
        $positions = $request->input('position_filter');
        $netPay = $request->input('netpay_filter');
        $commission = $request->input('commission_filter');
        $pay_frequency = $request->input('pay_frequency');
        $usersids = User::whereIn('sub_position_id', function ($query) use ($pay_frequency) {
            $query->select('position_id')
                ->from('position_pay_frequencies')
                ->where('frequency_type_id', $pay_frequency);
        })->pluck('id');
        if (! empty($request->input('perpage'))) {
            $perpage = $request->input('perpage');
        } else {
            $perpage = 10;
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $fullName = $request->input('search');
        $type = $request->input('type');

        $everee_webhook_message = '';
        $everee_payment_status = '';

        if ($type == 'pid') {
            // everee code start
            $payrollHistory = PayrollHistory::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'pay_type' => 'Bank'])->where('everee_payment_status', 2)
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })->pluck('payroll_id');
            if (count($payrollHistory) > 0) {
                $commissionPayrolls = UserCommissionLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $overridePayrolls = UserOverridesLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $clawbackPayrolls = ClawbackSettlementLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $adjustmentDetailsPayrolls = PayrollAdjustmentDetailLock::with(['saledata', 'costcenter:id,name,status'])
                    ->where(function ($query) use ($start_date, $end_date, $payrollHistory, $fullName) {
                        $query->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                            ->whereIn('payroll_id', $payrollHistory)
                            ->when($fullName && ! empty($fullName), function ($q) {
                                $q->whereHas('saledata', function ($q) {
                                    $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                                });
                            })
                            ->where(function ($subQuery) {
                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                    ->orWhereHas('costcenter', function ($q) {
                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                    });
                            });
                    })
                    ->get();

                $data = [];
                foreach ($commissionPayrolls as $commissionPayroll) {
                    $commissionPayroll['data_type'] = 'commission';
                    $data[$commissionPayroll['pid']][] = $commissionPayroll;
                }
                foreach ($overridePayrolls as $overridePayroll) {
                    $overridePayroll['data_type'] = 'override';
                    $data[$overridePayroll['pid']][] = $overridePayroll;
                }
                foreach ($clawbackPayrolls as $clawbackPayroll) {
                    $clawbackPayroll['data_type'] = 'clawback';
                    $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
                }
                foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                    $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                    $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
                }

                $finalData = [];
                $payrollTotal = 0;
                foreach ($data as $key => $data) {
                    $commission = 0;
                    $override = 0;
                    $adjustment = 0;

                    $commissionNoPaid = 0;
                    $overrideNoPaid = 0;
                    $adjustmentNoPaid = 0;

                    $commissionColor = 0;
                    $overrideColor = 0;
                    $adjustmentColor = 0;
                    $isMarkPaid = 0;
                    $isNextPayroll = 0;
                    $total = 0;
                    foreach ($data as $inner) {
                        if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                                if (! $commissionColor) {
                                    $commissionColor = 1;
                                }
                            } else {
                                if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                    $commissionNoPaid += (0 - $inner['clawback_amount']);
                                } else {
                                    $commissionNoPaid += $inner['amount'];
                                }
                            }
                            $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                        } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                                if (! $overrideColor) {
                                    $overrideColor = 1;
                                }
                            } else {
                                if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                    $overrideNoPaid += (0 - $inner['clawback_amount']);
                                } else {
                                    $overrideNoPaid += $inner['amount'];
                                }
                            }
                            $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                        } elseif ($inner['data_type'] == 'adjustment') {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                                if (! $adjustmentColor) {
                                    $adjustmentColor = 1;
                                }
                            } else {
                                $adjustmentNoPaid += $inner['amount'];
                            }
                            $adjustment += $inner['amount'];
                        }

                        $total += 1;
                        if ($inner['is_mark_paid'] >= 1) {
                            $isMarkPaid += 1;
                        }
                        if ($inner['is_next_payroll'] >= 1) {
                            $isNextPayroll += 1;
                        }
                    }

                    if ($commission || $override || $adjustment) {
                        $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                        $payrollTotal += $netPayAmount;

                        $finalData[] = [
                            'pid' => $key,
                            'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                            'commission' => round($commissionNoPaid, 2),
                            'override' => round($overrideNoPaid, 2),
                            'adjustment' => round($adjustmentNoPaid, 2),
                            'net_pay' => round($netPayAmount, 2),
                            'gross_pay' => round($netPayAmount, 2),
                            'is_mark_paid' => ($total == $isMarkPaid) ? 1 : 0,
                            'is_next_payroll' => ($total == $isNextPayroll) ? 1 : 0,
                            'commission_yellow_status' => $commissionColor,
                            'override_yellow_status' => $overrideColor,
                            'adjustment_yellow_status' => $adjustmentColor,
                            'deduction_yellow_status' => 0,
                            'status_id' => 3,
                            'data_type' => $inner['data_type'],
                            'everee_response' => '',
                        ];
                    }
                }

                $data = paginate($finalData, $perpage);

                return response()->json([
                    'ApiName' => 'get_payroll_data_contactor',
                    'status' => true,
                    'message' => 'Successfully.',
                    'finalize_status' => null,
                    'payment_failed' => 1,
                    'data' => $data,
                    'all_paid' => null,
                    'all_next' => null,
                    'payroll_total' => round($payroll_total, 2),
                    'total_alert_count' => isset($total_alert_count) ? $total_alert_count : 0,
                ]);
            }

            // Payroll Zero Data update
            $updatePayrollZeroData = $this->updatePayrollZeroData($start_date, $end_date, $pay_frequency);
            // End Payroll Zero Data update

            // CHECKING PAYROLL IS CLOSED OR NOT FOR THE GIVEN PAY PERIOD.
            $checkPayrollClosedStatus = $this->check_payroll_closed_status($start_date, $end_date, $pay_frequency, $workerType);
            if (! empty($checkPayrollClosedStatus)) {
                return $checkPayrollClosedStatus;
            }

            // UPDATING PAYROLL RECORDS AMOUNT VALUE IN PAYROLL FOR THE GIVEN PAY PERIODS.
            if (! empty($start_date) && ! empty($end_date)) {
                $this->update_payroll_data($start_date, $end_date, $pay_frequency);
            }

            $commissionPayrolls = UserCommission::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('userdata', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $overridePayrolls = UserOverrides::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('userdata', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $clawbackPayrolls = ClawbackSettlement::with('saledata', 'users')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('users', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $adjustmentDetailsPayrolls = PayrollAdjustmentDetail::with('saledata', 'userData')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('userData', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $data = [];
            foreach ($commissionPayrolls as $commissionPayroll) {
                $commissionPayroll['data_type'] = 'commission';
                $data[$commissionPayroll['pid']][] = $commissionPayroll;
            }
            foreach ($overridePayrolls as $overridePayroll) {
                $overridePayroll['data_type'] = 'override';
                $data[$overridePayroll['pid']][] = $overridePayroll;
            }
            foreach ($clawbackPayrolls as $clawbackPayroll) {
                $clawbackPayroll['data_type'] = 'clawback';
                $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
            }
            foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
            }

            $finalData = [];
            $payrollTotal = 0;
            foreach ($data as $key => $data) {
                $commission = 0;
                $override = 0;
                $adjustment = 0;

                $commissionNoPaid = 0;
                $overrideNoPaid = 0;
                $adjustmentNoPaid = 0;

                $commissionColor = 0;
                $overrideColor = 0;
                $adjustmentColor = 0;
                $payrollIds = [];
                $isMarkPaid = 0;
                $isNextPayroll = 0;
                $total = 0;
                foreach ($data as $inner) {
                    if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $commissionColor) {
                                $commissionColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                $commissionNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $commissionNoPaid += $inner['amount'];
                            }
                        }
                        $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $overrideColor) {
                                $overrideColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                $overrideNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $overrideNoPaid += $inner['amount'];
                            }
                        }
                        $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'adjustment') {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1) {
                            if (! $adjustmentColor) {
                                $adjustmentColor = 1;
                            }
                        } else {
                            $adjustmentNoPaid += $inner['amount'];
                        }
                        $adjustment += $inner['amount'];
                    }

                    $payrollIds[] = $inner['payroll_id'];
                    $total += 1;
                    if ($inner['is_mark_paid'] >= 1) {
                        $isMarkPaid += 1;
                    }
                    if ($inner['is_next_payroll'] >= 1) {
                        $isNextPayroll += 1;
                    }
                }

                $status = 1;
                $payroll = Payroll::whereIn('id', $payrollIds)->where(['status' => '2', 'finalize_status' => '2', 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->first();
                if ($payroll) {
                    $status = 2;
                }
                if ($commission || $override || $adjustment) {
                    $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                    $payrollTotal += $netPayAmount;

                    $finalData[] = [
                        'pid' => $key,
                        'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                        'commission' => round($commissionNoPaid, 2),
                        'override' => round($overrideNoPaid, 2),
                        'adjustment' => round($adjustmentNoPaid, 2),
                        'net_pay' => round($netPayAmount, 2),
                        'gross_pay' => round($netPayAmount, 2),
                        'is_mark_paid' => ($total == $isMarkPaid) ? 1 : 0,
                        'is_next_payroll' => ($total == $isNextPayroll) ? 1 : 0,
                        'commission_yellow_status' => $commissionColor,
                        'override_yellow_status' => $overrideColor,
                        'adjustment_yellow_status' => $adjustmentColor,
                        'status_id' => $status,
                        'data_type' => $inner['data_type'],
                        'deduction_yellow_status' => 0,
                    ];
                }
            }

            if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
                $finalData = collect($finalData)->where('net_pay', '<', 0)->values()->toArray();
            }

            $allPaidNextData = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])
                ->selectRaw('SUM(CASE WHEN is_mark_paid = 0 THEN 1 ELSE 0 END) as is_mark_paid, SUM(CASE WHEN is_next_payroll = 0 THEN 1 ELSE 0 END) as is_next_payroll')->first();
            $isPayrollData = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->first();

            // CHECKING ALL PAYROLLS ARE PAID OR NOT, FOR FINALIZING PROCESS.
            $allPaid = false;
            if ($isPayrollData && $allPaidNextData->is_mark_paid == 0) {
                $allPaid = true;
            }

            // CHECKING ALL PAYROLLS ARE Moved To Next OR NOT, FOR FINALIZING PROCESS.
            $allNext = false;
            if ($isPayrollData && $allPaidNextData->is_next_payroll == 0) {
                $allNext = true;
            }

            // plz dont delete this function
            // $this->deduction_for_all_deduction_enable_users($start_date,$end_date,$pay_frequency, $workerType, $usersids);

            $checkFinalizeStatus = payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->where('status', '>', 1)->first() ? 1 : 0;
            $crmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($crmData) {
                $paydata = Payroll::with('usersdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
                if (count($paydata) > 0) {
                    event(new EvereeOnboardingUserEvent($paydata, '1'));
                }
            }

            $totalAlertCount = LegacyApiNullData::select(
                DB::raw('count(`sales_alert`) as sales_alert'),
                DB::raw('count(`missingrep_alert`) as missingrep'),
                DB::raw('count(`closedpayroll_alert`) as closedpayroll'),
                DB::raw('count(`locationredline_alert`) as locationredline'),
                DB::raw('count(`repredline_alert`) as repredline')
            )->where(function ($query) use ($request) {
                $query->whereRaw("`m1_date` >= '".$request->start_date."' AND `m1_date` <= '".$request->end_date."'")
                    ->orWhereRaw("`m2_date` >= '".$request->start_date."' AND `m2_date` <= '".$request->end_date."'");
            })->whereNotNull('data_source_type')->first();

            payroll::where('status', 2)->where('finalize_status', '!=', 2)->update(['finalize_status' => 2]);
            payroll::where(['status' => 1, 'finalize_status' => 2])->update(['finalize_status' => 0]);

            $finalData = paginate($finalData, $perpage);

            return response()->json([
                'ApiName' => 'get_payroll_data_contactor',
                'status' => true,
                'message' => 'Successfully.',
                'finalize_status' => isset($checkFinalizeStatus) ? $checkFinalizeStatus : null,
                'payment_failed' => 0,
                'data' => $finalData,
                'all_paid' => $allPaid,
                'all_next' => $allNext,
                'payroll_total' => round($payrollTotal, 2),
                'total_alert_count' => isset($totalAlertCount) ? $totalAlertCount : 0,
            ]);
        } else {
            // everee code start
            $payrollHistory = PayrollHistory::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'pay_type' => 'Bank'])->where('everee_payment_status', 2)
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })->get();
            $total_custom = 0;
            if (count($payrollHistory) > 0) {
                $payroll_total = $payrollHistory->sum('net_pay');
                $total_custom = 0;
                foreach ($payrollHistory as $data) {
                    if ($data->everee_payment_status == 3) {
                        $everee_webhook_message = 'Payment Success';
                    } elseif ($data->everee_payment_status == 2 && $data->everee_status == 2 && ($data->everee_webhook_json == null || $data->everee_webhook_json == '')) {
                        $everee_webhook_message = 'Payment will be processed once the user profile is successfully completed.';
                    } elseif ($data->everee_payment_status == 2 && $data->everee_webhook_json != null && $data->everee_webhook_json != '') {
                        $everee_webhook_data = json_decode($data->everee_webhook_json, true);
                        if ($everee_webhook_data['paymentStatus'] == 'ERROR') {
                            $everee_webhook_message = $everee_webhook_data['paymentErrorMessage'];
                        } else {
                            $everee_webhook_message = $data->everee_webhook_json;
                        }
                    } elseif ($data->everee_payment_status == 1) {
                        $everee_webhook_message = 'Waiting for payment status to be updated.';
                    } elseif ($data->everee_payment_status == 0) {
                        $everee_webhook_message = 'External payment processing is disabled. Payment completed locally';
                    }

                    $custom_filed = [];
                    $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                    if ($setting) {
                        foreach ($setting as $value) {
                            $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $data->id])->first();
                            $total_custom += @$payroll_data->value;

                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => @$value['field_name'],
                                'comment' => @$payroll_data['comment'],
                                'value' => @$payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }
                    }

                    $yellow_status = 0;
                    $s3_image = (isset($data->usersdata->image) && $data->usersdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image) : null;
                    $result_data[] = [
                        'id' => $data->id,
                        'payroll_id' => $data->payroll_id,
                        'user_id' => $data->user_id,
                        'approvals_and_requests_status' => 0,
                        'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                        'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                        'position_id' => isset($data->usersdata) ? $data->usersdata->position_id : null,
                        'sub_position_id' => isset($data->usersdata) ? $data->usersdata->sub_position_id : null,
                        'is_super_admin' => isset($data->usersdata) ? $data->usersdata->is_super_admin : null,
                        'is_manager' => isset($data->usersdata) ? $data->usersdata->is_manager : null,
                        'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                        'image_s3' => $s3_image,
                        'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                        'commission' => isset($data->commission) ? $data->commission * 1 : 0,
                        'override' => isset($data->override) ? $data->override * 1 : 0,
                        'override_value_is_higher' => 0,
                        'adjustment' => isset($data->adjustment) ? $data->adjustment * 1 : 0,
                        'reimbursement' => isset($data->reimbursement) ? $data->reimbursement * 1 : 0,
                        'clawback' => isset($data->clawback) ? $data->clawback * 1 : 0,
                        'deduction' => isset($data->deduction) ? $data->deduction * 1 : 0,
                        'reconciliation' => isset($data->reconciliation) ? $data->reconciliation * 1 : 0,
                        'net_pay' => round($data->net_pay, 2),
                        'gross_pay' => 0,
                        'status_id' => $data->status,
                        'status' => isset($data->payrollstatus) ? $data->payrollstatus->status : null,
                        'is_mark_paid' => isset($data->is_mark_paid) ? $data->is_mark_paid : 0,
                        'is_next_payroll' => isset($data->is_next_payroll) ? $data->is_next_payroll : 0,
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                        'custom_filed' => $custom_filed,
                        'commission_yellow_status' => ($data->commission_count >= 1 || $data->clawback_count >= 1) ? 1 : 0,
                        'override_yellow_status' => ($data->override_count >= 1) ? 1 : 0,
                        'approve_request_yellow_status' => ($data->approve_request_count >= 1 || $data->payroll_adjustment_details_count >= 1) ? 1 : 0,
                        'reimbursement_yellow_status' => ($data->reimbursement_count >= 1) ? 1 : 0,
                        'deduction_yellow_status' => 0,
                        'paid_next' => 'comm-'.$data->commission_count.' ,over-'.$data->override_count.' ,claw-'.$data->clawback_count.' , appr-'.$data->approve_request_count.' ,reimb-'.$data->reimbursement_count,
                        'total_custom' => $total_custom,
                        'everee_response' => $everee_webhook_message,
                        'hourly_salary' => isset($data->hourly_salary) ? $data->hourly_salary : 0,
                        'overtime' => isset($data->overtime) ? $data->overtime : 0,
                        'worker_type' => isset($workerType) ? $workerType : null,
                        'is_onetime_payment' => isset($data->is_onetime_payment) ? $data->is_onetime_payment : 0,
                    ];
                }

                $data = paginate($result_data, $perpage);

                return response()->json([
                    'ApiName' => 'get_payroll_data_contactor',
                    'status' => true,
                    'message' => 'Successfully.',
                    'finalize_status' => null,
                    'payment_failed' => 1,
                    'data' => $data,
                    'all_paid' => null,
                    'all_next' => null,
                    'payroll_total' => round($payroll_total, 2),
                    'total_alert_count' => 0,
                ]);
            }

            // Payroll Zero Data update
            $updatePayrollZeroData = $this->updatePayrollZeroData($start_date, $end_date, $pay_frequency);
            // End Payroll Zero Data update

            // CHECKING PAYROLL IS CLOSED OR NOT FOR THE GIVEN PAY PERIOD.
            $check_payroll_closed_status = $this->check_payroll_closed_status($start_date, $end_date, $pay_frequency, $workerType);
            if (! empty($check_payroll_closed_status)) {
                return $check_payroll_closed_status;
            }

            // UPDATING PAYROLL RECORDS AMOUNT VALUE IN PAYROLL FOR THE GIVEN PAY PERIODS.
            if (! empty($start_date) && ! empty($end_date)) {
                $update_payroll = $this->update_payroll_data($start_date, $end_date, $pay_frequency);
            }

            // CHECKING ALL PAYROLLS ARE PAID OR NOT , FOR FINALIZING PROCESS.
            $all_paid = false;
            $data_query = Payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            });
            $count_data = $data_query->count();
            if ($count_data > 0) {
                $all_paid_count = $data_query->where('is_mark_paid', '0')->count();
                if ($all_paid_count == 0) {
                    $all_paid = true;
                }
            }

            // CHECKING ALL PAYROLLS ARE Moved To Next OR NOT, FOR FINALIZING PROCESS.
            $all_next = false;
            $data_query = Payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            });
            $count_data = $data_query->count();
            if ($count_data > 0) {
                $all_paid_count = $data_query->where('is_next_payroll', '0')->count();
                if ($all_paid_count == 0) {
                    $all_next = true;
                }
            }

            $users = User::orderBy('id', 'asc');
            $search_full_name = removeMultiSpace($fullName);
            if ($request->has('search') && ! empty($request->input('search'))) {
                $users->where(function ($query) use ($search_full_name) {
                    return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere('first_name', 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                });
            }
            $userArray = $users->where('worker_type', $workerType)->pluck('id')->toArray();

            // plz dont delete this function
            $this->deduction_for_all_deduction_enable_users($start_date, $end_date, $pay_frequency, $workerType, $usersids);

            $checkFinalizeStatus = 0;
            $data_query = Payroll::with( // 'payrolladjust' , 'PayrollShiftHistorie', 'reconciliationInfo'
                'usersdata',
                'payrollstatus',
                'approvalRequest'
            )
                ->whereIn('user_id', $userArray)
                ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->where('id', '!=', 0)
                ->withCount([
                    'userCommission as commission_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)
                            ->orWhere('is_next_payroll', '>=', 1);
                    },
                    'userOverride as override_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)
                            ->orWhere('is_next_payroll', '>=', 1);
                    },
                    'userClawback as commission_clawback_count' => function ($query) {
                        $query->where('type', 'commission')->where(function ($q) {
                            $q->where('is_mark_paid', '>=', '1')->orWhere('is_next_payroll', '>=', 1);
                        });
                    },
                    'userClawback as override_clawback_count' => function ($query) {
                        $query->where('type', 'overrides')->where(function ($q) {
                            $q->where('is_mark_paid', '>=', '1')->orWhere('is_next_payroll', '>=', 1);
                        });
                    },
                    'userApproveRequest as approve_request_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1);
                    },
                    'userApproveRequestReimbursement as reimbursement_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1);
                    },
                    'userPayrollAdjustmentDetails as payroll_adjustment_details_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1);
                    },
                    'userDeductions as deduction_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1);
                    },

                ]);

            $payroll_total = payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_next_payroll' => 0])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })->sum('net_pay');

            if ($positions && $positions != '') {
                $data_query->where('position_id', $positions);
            }

            if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
                $data_query->where('net_pay', '<', 0);
            }

            if ($commission && $commission != '') {
                $data_query->where('commission', $commission);
            }

            $data_query->orderBy(
                User::select('first_name')
                    ->whereColumn('id', 'payrolls.user_id')
                    ->orderBy('first_name', 'asc')
                    ->limit(1),
                'ASC'
            );

            if (isset($request->is_reconciliation) && $request->is_reconciliation == 1) {
                $positionArray = PositionReconciliations::where('status', 1)->pluck('position_id')->toArray();
                $data_query->whereIn('position_id', $positionArray);
            }
            $payroll_data = $data_query->get();

            // $uId = $payroll_data->pluck('user_id')->toArray();

            $result_data = [];
            $total_custom = 0;
            if (count($payroll_data) > 0) {
                foreach ($payroll_data as $data) {
                    $custom_filed = [];
                    $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                    if ($setting) {
                        foreach ($setting as $value) {
                            $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $data->id])->first();
                            $total_custom += (float) @$payroll_data->value;

                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => @$value['field_name'],
                                'value' => @$payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }
                    }

                    if ($checkFinalizeStatus == 0 && $data->status > 1) {
                        $checkFinalizeStatus = 1;
                    }
                    $yellow_status = 0;
                    if (in_array($data->user_id, $userArray)) {
                        if ($data->is_mark_paid == 1 || $data->is_next_payroll >= 1) {
                            $yellow_status = 1;
                        } else {
                            if (($data->commission_count + $data->override_count + $data->clawback_count + $data->approve_request_count) > 0) {
                                $yellow_status = 1;
                            } else {
                                $yellow_status = 0;
                            }
                        }
                        $s3_image = (isset($data->usersdata->image) && $data->usersdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image) : null;
                        $netPay = $data->net_pay;

                        $result_data[] = [
                            'id' => $data->id,
                            'payroll_id' => $data->id,
                            'user_id' => $data->user_id,
                            'approvals_and_requests_status' => (empty($data->approvalRequest)) ? 0 : 1,
                            'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                            'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                            'position_id' => isset($data->usersdata) ? $data->usersdata->position_id : null,
                            'sub_position_id' => isset($data->usersdata) ? $data->usersdata->sub_position_id : null,
                            'is_super_admin' => isset($data->usersdata) ? $data->usersdata->is_super_admin : null,
                            'is_manager' => isset($data->usersdata) ? $data->usersdata->is_manager : null,
                            'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                            'image_s3' => $s3_image,
                            'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                            'commission' => isset($data->commission) ? $data->commission * 1 : 0,
                            'override' => isset($data->override) ? $data->override * 1 : 0,
                            'override_value_is_higher' => 0,
                            'adjustment' => isset($data->adjustment) ? $data->adjustment * 1 : 0,
                            'reimbursement' => isset($data->reimbursement) ? $data->reimbursement * 1 : 0,
                            'clawback' => isset($data->clawback) ? $data->clawback * 1 : 0,
                            'deduction' => isset($data->deduction) ? $data->deduction * 1 : 0,
                            'reconciliation' => isset($data->reconciliation) ? $data->reconciliation * 1 : 0,
                            'net_pay' => round($data->net_pay, 2),
                            'gross_pay' => round($data->gross_pay, 2),
                            // 'comment' => isset($data->payrolladjust) ? $data->payrolladjust->comment : null,
                            'status_id' => $data->status,
                            'status' => isset($data->payrollstatus) ? $data->payrollstatus->status : null,
                            'is_mark_paid' => isset($data->is_mark_paid) ? $data->is_mark_paid : 0,
                            'is_next_payroll' => isset($data->is_next_payroll) ? $data->is_next_payroll : 0,
                            'created_at' => $data->created_at,
                            'updated_at' => $data->updated_at,
                            'custom_filed' => $custom_filed,
                            // 'PayrollShiftHistorie_count' => isset($data->PayrollShiftHistorie) ? count($data->PayrollShiftHistorie) : '',
                            'commission_yellow_status' => ($data->commission_count >= 1 || $data->commission_clawback_count >= 1) ? 1 : 0,
                            'override_yellow_status' => ($data->override_count >= 1 || $data->override_clawback_count >= 1) ? 1 : 0,
                            'approve_request_yellow_status' => ($data->approve_request_count >= 1 || $data->payroll_adjustment_details_count >= 1) ? 1 : 0,
                            'reimbursement_yellow_status' => ($data->reimbursement_count >= 1) ? 1 : 0,
                            'deduction_yellow_status' => ($data->deduction_count >= 1) ? 1 : 0,
                            'paid_next' => 'comm-'.$data->commission_count.' ,over-'.$data->override_count.' ,claw-'.$data->clawback_count.' , appr-'.$data->approve_request_count.' ,reimb-'.$data->reimbursement_count,
                            'total_custom' => $total_custom,
                            'everee_response' => @$everee_webhook_message,
                            'everee_payment_status' => @$everee_payment_status,
                            'is_stop_payroll' => isset($data->is_stop_payroll) ? $data->is_stop_payroll : 0,
                            'hourly_salary' => isset($data->hourly_salary) ? $data->hourly_salary : 0,
                            'overtime' => isset($data->overtime) ? $data->overtime : 0,
                            'worker_type' => isset($workerType) ? $workerType : null,
                            'is_onetime_payment' => isset($data->is_onetime_payment) ? $data->is_onetime_payment : 0,
                        ];
                    }
                    unset($yellow_status);
                }
            }
            $data = paginate($result_data, $perpage);
            // $saleCount = LegacyApiNullData::whereBetween('m1_date', [$start_date, $end_date])->orWhereBetween('m2_date', [$start_date, $end_date])->whereNotNull('data_source_type')->count();
            // $peopleCount = User::whereIn('id', $uId)->where('tax_information', null)->where('name_of_bank', null)->count();
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) { // && $request['everee_failed_status']!=1
                $paydata = Payroll::with('usersdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
                if (count($paydata) > 0) {
                    event(new EvereeOnboardingUserEvent($paydata, $payroll = '1'));
                }
            }

            $total_alert_count_query = LegacyApiNullData::select(
                DB::raw('count(`sales_alert`) as sales_alert'),
                DB::raw('count(`missingrep_alert`) as missingrep'),
                DB::raw('count(`closedpayroll_alert`) as closedpayroll'),
                DB::raw('count(`locationredline_alert`) as locationredline'),
                DB::raw('count(`repredline_alert`) as repredline')
            );
            $total_alert_count_query->where(function ($query) use ($request) {
                return $query->whereRaw("`m1_date` >= '".$request->start_date."' AND `m1_date` <= '".$request->end_date."'")
                    ->orWhereRaw("`m2_date` >= '".$request->start_date."' AND `m2_date` <= '".$request->end_date."'");
            });
            $total_alert_count = $total_alert_count_query->whereNotNull('data_source_type')->first();

            payroll::where('status', 2)->where('finalize_status', '!=', 2)->update(['finalize_status' => 2]);
            payroll::where('status', 1)->where('finalize_status', 2)->update(['finalize_status' => 0]);

            return response()->json([
                'ApiName' => 'get_payroll_data_contactor',
                'status' => true,
                'message' => 'Successfully.',
                'finalize_status' => isset($checkFinalizeStatus) ? $checkFinalizeStatus : null,
                'payment_failed' => 0,
                'data' => $data,
                'all_paid' => $all_paid,
                'all_next' => $all_next,
                'payroll_total' => round($payroll_total, 2),
                'total_alert_count' => isset($total_alert_count) ? $total_alert_count : 0,
            ], 200);
        }
    }

    public function getPayrollDataForEmployees(Request $request)
    {
        $data = [];
        $payroll_total = 0;
        $workerType = 'w2';
        $positions = $request->input('position_filter');
        $netPay = $request->input('netpay_filter');
        $commission = $request->input('commission_filter');
        $pay_frequency = $request->input('pay_frequency');
        $usersids = User::whereIn('sub_position_id', function ($query) use ($pay_frequency) {
            $query->select('position_id')
                ->from('position_pay_frequencies')
                ->where('frequency_type_id', $pay_frequency);
        })->pluck('id');
        if (! empty($request->input('perpage'))) {
            $perpage = $request->input('perpage');
        } else {
            $perpage = 10;
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $fullName = $request->input('search');
        $search_full_name = removeMultiSpace($fullName);
        $type = $request->input('type');

        $payrollHistoryCount = PayrollHistory::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
            ->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            })->count();
        if ($payrollHistoryCount == 0) {
            $this->user_salary_create($start_date, $end_date, $workerType);
        }

        $emp_total = 0;
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
        if (! isset($request['manually'])) {
            $request['manually'] = 1;
            $info = $this->getPayrollData($request);
            $responseData = json_decode($info->getContent(), true);
            $emp_total = (isset($responseData['data']) && is_array($responseData['data']) && isset($responseData['data']['total'])) ? $responseData['data']['total'] : 0;
        }

        $everee_webhook_message = '';

        if ($type == 'pid') {
            // everee code start
            $payrollHistory = PayrollHistory::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'pay_type' => 'Bank'])->where('everee_payment_status', 2)
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })->pluck('payroll_id');
            if (count($payrollHistory) > 0) {
                $commissionPayrolls = UserCommissionLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $overridePayrolls = UserOverridesLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $clawbackPayrolls = ClawbackSettlementLock::with('saledata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '3')
                    ->whereIn('payroll_id', $payrollHistory)
                    ->when($fullName && ! empty($fullName), function ($q) {
                        $q->whereHas('saledata', function ($q) {
                            $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                        });
                    })->get();

                $adjustmentDetailsPayrolls = PayrollAdjustmentDetailLock::with(['saledata', 'costcenter:id,name,status'])
                    ->where(function ($query) use ($start_date, $end_date, $payrollHistory, $fullName) {
                        $query->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                            ->where('status', '3')
                            ->whereIn('payroll_id', $payrollHistory)
                            ->when($fullName && ! empty($fullName), function ($q) {
                                $q->whereHas('saledata', function ($q) {
                                    $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                                });
                            })
                            ->where(function ($subQuery) {
                                $subQuery->whereNull('cost_center_id')  // Include records where cost_center_id is NULL
                                    ->orWhereHas('costcenter', function ($q) {
                                        $q->where('status', 1); // Apply status = 1 when cost_center_id exists
                                    });
                            });
                    })
                    ->get();

                $data = [];
                foreach ($commissionPayrolls as $commissionPayroll) {
                    $commissionPayroll['data_type'] = 'commission';
                    $data[$commissionPayroll['pid']][] = $commissionPayroll;
                }
                foreach ($overridePayrolls as $overridePayroll) {
                    $overridePayroll['data_type'] = 'override';
                    $data[$overridePayroll['pid']][] = $overridePayroll;
                }
                foreach ($clawbackPayrolls as $clawbackPayroll) {
                    $clawbackPayroll['data_type'] = 'clawback';
                    $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
                }
                foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                    $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                    $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
                }

                $finalData = [];
                $payrollTotal = 0;
                foreach ($data as $key => $data) {
                    $commission = 0;
                    $override = 0;
                    $adjustment = 0;

                    $commissionNoPaid = 0;
                    $overrideNoPaid = 0;
                    $adjustmentNoPaid = 0;

                    $commissionColor = 0;
                    $overrideColor = 0;
                    $adjustmentColor = 0;
                    $isMarkPaid = 0;
                    $isNextPayroll = 0;
                    $total = 0;
                    foreach ($data as $inner) {
                        if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                                if (! $commissionColor) {
                                    $commissionColor = 1;
                                }
                            } else {
                                if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                    $commissionNoPaid += (0 - $inner['clawback_amount']);
                                } else {
                                    $commissionNoPaid += $inner['amount'];
                                }
                            }
                            $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                        } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                                if (! $overrideColor) {
                                    $overrideColor = 1;
                                }
                            } else {
                                if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                    $overrideNoPaid += (0 - $inner['clawback_amount']);
                                } else {
                                    $overrideNoPaid += $inner['amount'];
                                }
                            }
                            $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                        } elseif ($inner['data_type'] == 'adjustment') {
                            if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                                if (! $adjustmentColor) {
                                    $adjustmentColor = 1;
                                }
                            } else {
                                $adjustmentNoPaid += $inner['amount'];
                            }
                            $adjustment += $inner['amount'];
                        }

                        $total += 1;
                        if ($inner['is_mark_paid'] >= 1) {
                            $isMarkPaid += 1;
                        }
                        if ($inner['is_next_payroll'] >= 1) {
                            $isNextPayroll += 1;
                        }
                    }

                    if ($commission || $override || $adjustment) {
                        $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                        $payrollTotal += $netPayAmount;

                        $finalData[] = [
                            'pid' => $key,
                            'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                            'commission' => round($commissionNoPaid, 2),
                            'override' => round($overrideNoPaid, 2),
                            'adjustment' => round($adjustmentNoPaid, 2),
                            'net_pay' => round($netPayAmount, 2),
                            'gross_pay' => round($netPayAmount, 2),
                            'is_mark_paid' => ($total == $isMarkPaid) ? 1 : 0,
                            'is_next_payroll' => ($total == $isNextPayroll) ? 1 : 0,
                            'commission_yellow_status' => $commissionColor,
                            'override_yellow_status' => $overrideColor,
                            'adjustment_yellow_status' => $adjustmentColor,
                            'deduction_yellow_status' => 0,
                            'status_id' => 3,
                            'data_type' => $inner['data_type'],
                            'everee_response' => '',
                        ];
                    }
                }

                $data = paginate($finalData, $perpage);

                return response()->json([
                    'ApiName' => 'get_payroll_data_employee',
                    'status' => true,
                    'message' => 'Successfully.',
                    'finalize_status' => null,
                    'payment_failed' => 1,
                    'data' => $data,
                    'all_paid' => null,
                    'all_next' => null,
                    'payroll_total' => round($payroll_total, 2),
                    'second_total' => $emp_total,
                    'total_alert_count' => isset($total_alert_count) ? $total_alert_count : 0,
                ]);
            }

            // CHECKING PAYROLL IS CLOSED OR NOT FOR THE GIVEN PAY PERIOD.
            $checkPayrollClosedStatus = $this->check_payroll_closed_status($start_date, $end_date, $pay_frequency, $workerType);
            if (! empty($checkPayrollClosedStatus)) {
                return $checkPayrollClosedStatus;
            }

            // Payroll Zero Data update
            $this->updatePayrollZeroData($start_date, $end_date, $pay_frequency);
            // End Payroll Zero Data update

            // UPDATING PAYROLL RECORDS AMOUNT VALUE IN PAYROLL FOR THE GIVEN PAY PERIODS.
            if (! empty($start_date) && ! empty($end_date)) {
                $this->update_payroll_data($start_date, $end_date, $pay_frequency);
            }

            $commissionPayrolls = UserCommission::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('userdata', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $overridePayrolls = UserOverrides::with('saledata', 'userdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('userdata', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $clawbackPayrolls = ClawbackSettlement::with('saledata', 'users')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('users', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $adjustmentDetailsPayrolls = PayrollAdjustmentDetail::with('saledata', 'userData')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', '3')
                ->whereHas('userData', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })
                ->when($fullName && ! empty($fullName), function ($q) {
                    $q->whereHas('saledata', function ($q) {
                        $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                    });
                })->get();

            $data = [];
            foreach ($commissionPayrolls as $commissionPayroll) {
                $commissionPayroll['data_type'] = 'commission';
                $data[$commissionPayroll['pid']][] = $commissionPayroll;
            }
            foreach ($overridePayrolls as $overridePayroll) {
                $overridePayroll['data_type'] = 'override';
                $data[$overridePayroll['pid']][] = $overridePayroll;
            }
            foreach ($clawbackPayrolls as $clawbackPayroll) {
                $clawbackPayroll['data_type'] = 'clawback';
                $data[$clawbackPayroll['pid']][] = $clawbackPayroll;
            }
            foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
                $adjustmentDetailsPayroll['data_type'] = 'adjustment';
                $data[$adjustmentDetailsPayroll['pid']][] = $adjustmentDetailsPayroll;
            }

            $finalData = [];
            $payrollTotal = 0;
            foreach ($data as $key => $data) {
                $commission = 0;
                $override = 0;
                $adjustment = 0;

                $commissionNoPaid = 0;
                $overrideNoPaid = 0;
                $adjustmentNoPaid = 0;

                $commissionColor = 0;
                $overrideColor = 0;
                $adjustmentColor = 0;
                $payrollIds = [];
                $isMarkPaid = 0;
                $isNextPayroll = 0;
                $total = 0;
                foreach ($data as $inner) {
                    if ($inner['data_type'] == 'commission' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                            if (! $commissionColor) {
                                $commissionColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') {
                                $commissionNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $commissionNoPaid += $inner['amount'];
                            }
                        }
                        $commission += ($inner['data_type'] == 'clawback' && $inner['type'] == 'commission') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'override' || ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides')) {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                            if (! $overrideColor) {
                                $overrideColor = 1;
                            }
                        } else {
                            if ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') {
                                $overrideNoPaid += (0 - $inner['clawback_amount']);
                            } else {
                                $overrideNoPaid += $inner['amount'];
                            }
                        }
                        $override += ($inner['data_type'] == 'clawback' && $inner['type'] == 'overrides') ? $inner['clawback_amount'] : $inner['amount'];
                    } elseif ($inner['data_type'] == 'adjustment') {
                        if ($inner['is_mark_paid'] >= 1 || $inner['is_next_payroll'] >= 1 || $inner['is_onetime_payment'] >= 1 || $inner['is_move_to_recon'] >= 1) {
                            if (! $adjustmentColor) {
                                $adjustmentColor = 1;
                            }
                        } else {
                            $adjustmentNoPaid += $inner['amount'];
                        }
                        $adjustment += $inner['amount'];
                    }

                    $payrollIds[] = $inner['payroll_id'];
                    $total += 1;
                    if ($inner['is_mark_paid'] >= 1) {
                        $isMarkPaid += 1;
                    }
                    if ($inner['is_next_payroll'] >= 1) {
                        $isNextPayroll += 1;
                    }
                }

                $status = 1;
                $payroll = Payroll::whereIn('id', $payrollIds)->where(['status' => '2', 'finalize_status' => '2', 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->first();
                if ($payroll) {
                    $status = 2;
                }
                if ($commission || $override || $adjustment) {
                    $netPayAmount = $commissionNoPaid + $overrideNoPaid + $adjustmentNoPaid;
                    $payrollTotal += $netPayAmount;

                    $finalData[] = [
                        'pid' => $key,
                        'customer_name' => @$data[0]['saledata']['customer_name'] ?? null,
                        'commission' => round($commissionNoPaid, 2),
                        'override' => round($overrideNoPaid, 2),
                        'adjustment' => round($adjustmentNoPaid, 2),
                        'net_pay' => round($netPayAmount, 2),
                        'gross_pay' => round($netPayAmount, 2),
                        'is_mark_paid' => ($total == $isMarkPaid) ? 1 : 0,
                        'is_next_payroll' => ($total == $isNextPayroll) ? 1 : 0,
                        'commission_yellow_status' => $commissionColor,
                        'override_yellow_status' => $overrideColor,
                        'adjustment_yellow_status' => $adjustmentColor,
                        'status_id' => $status,
                        'data_type' => $inner['data_type'],
                        'deduction_yellow_status' => 0,
                    ];
                }
            }

            if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
                $finalData = collect($finalData)->where('net_pay', '<', 0)->values()->toArray();
            }

            $allPaidNextData = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])
                ->selectRaw('SUM(CASE WHEN is_mark_paid = 0 THEN 1 ELSE 0 END) as is_mark_paid, SUM(CASE WHEN is_next_payroll = 0 THEN 1 ELSE 0 END) as is_next_payroll')->first();
            $isPayrollData = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->first();

            // CHECKING ALL PAYROLLS ARE PAID OR NOT, FOR FINALIZING PROCESS.
            $allPaid = false;
            if ($isPayrollData && $allPaidNextData->is_mark_paid == 0) {
                $allPaid = true;
            }

            // CHECKING ALL PAYROLLS ARE Moved To Next OR NOT, FOR FINALIZING PROCESS.
            $allNext = false;
            if ($isPayrollData && $allPaidNextData->is_next_payroll == 0) {
                $allNext = true;
            }

            // plz dont delete this function
            // $this->deduction_for_all_deduction_enable_users($start_date,$end_date,$pay_frequency,$workerType, $usersids);

            $checkFinalizeStatus = payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->where('status', '>', 1)->first() ? 1 : 0;
            // $crmData = Crms::where('id', 3)->where('status', 1)->first();
            // if ($crmData) {
            //     $paydata = Payroll::with('usersdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
            //     if (count($paydata) > 0) {
            //         event(new EvereeOnboardingUserEvent($paydata, '1'));
            //     }
            // }

            $totalAlertCount = LegacyApiNullData::select(
                DB::raw('count(`sales_alert`) as sales_alert'),
                DB::raw('count(`missingrep_alert`) as missingrep'),
                DB::raw('count(`closedpayroll_alert`) as closedpayroll'),
                DB::raw('count(`locationredline_alert`) as locationredline'),
                DB::raw('count(`repredline_alert`) as repredline')
            )->where(function ($query) use ($request) {
                $query->whereRaw("`m1_date` >= '".$request->start_date."' AND `m1_date` <= '".$request->end_date."'")
                    ->orWhereRaw("`m2_date` >= '".$request->start_date."' AND `m2_date` <= '".$request->end_date."'");
            })->whereNotNull('data_source_type')->first();

            payroll::where('status', 2)->where('finalize_status', '!=', 2)->update(['finalize_status' => 2]);
            payroll::where(['status' => 1, 'finalize_status' => 2])->update(['finalize_status' => 0]);

            $finalData = paginate($finalData, $perpage);

            return response()->json([
                'ApiName' => 'get_payroll_data_employee',
                'status' => true,
                'message' => 'Successfully.',
                'finalize_status' => isset($checkFinalizeStatus) ? $checkFinalizeStatus : null,
                'payment_failed' => 0,
                'data' => $finalData,
                'all_paid' => $allPaid,
                'all_next' => $allNext,
                'payroll_total' => round($payrollTotal, 2),
                'second_total' => $emp_total,
                'total_alert_count' => isset($totalAlertCount) ? $totalAlertCount : 0,
            ]);
        } else {
            // everee code start
            $payrollHistory = PayrollHistory::with('workertype', 'usersdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'pay_type' => 'Bank'])->whereIn('everee_payment_status', [1, 2])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })->whereHas('usersdata', function ($q) use ($search_full_name) {
                    $q->where(function ($query) use ($search_full_name) {
                        $query->where('first_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere('last_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                    });
                })->get();

            $paymemntFailedStatus = false;
            if (count($payrollHistory) > 0) {
                // Check if all rows have everee_payment_status of 1
                $paymemntFailedStatus = collect($payrollHistory)->every(function ($history) {
                    return $history->everee_payment_status == 1;
                });
                if ($paymemntFailedStatus) {
                    $paymemntFailedStatus = true;
                } else {
                    $paymemntFailedStatus = false;
                }
            } else {
                $paymemntFailedStatus = false;
            }

            $total_custom = 0;
            if (count($payrollHistory) > 0) {
                $payroll_total = $payrollHistory->sum('net_pay');
                $total_custom = 0;
                foreach ($payrollHistory as $data) {
                    if ($data->everee_payment_status == 3) {
                        $everee_webhook_message = 'Payment Success';
                    } elseif ($data->everee_payment_status == 2 && $data->everee_status == 2 && ($data->everee_webhook_json == null || $data->everee_webhook_json == '')) {
                        // Differentiate between profile completion and self-onboarding completion
                        $user = $data->user ?? null;
                        if (!$user || !$user->onboardProcess) {
                            // Self-onboarding completion - user hasn't completed Everee self-onboarding
                            $everee_webhook_message = 'Payment will be processed once the user has logged in and completed the self-onboarding steps, confirming all required details.';
                        } else {
                            // Default fallback message
                            $everee_webhook_message = 'Payment will be processed once the user profile is fully completed.';
                        }
                    } elseif ($data->everee_payment_status == 2 && $data->everee_webhook_json != null && $data->everee_webhook_json != '') {
                        $everee_webhook_data = json_decode($data->everee_webhook_json, true);
                        if ($everee_webhook_data['paymentStatus'] == 'ERROR') {
                            $everee_webhook_message = $everee_webhook_data['paymentErrorMessage'];
                        } else {
                            $everee_webhook_message = $data->everee_webhook_json;
                        }
                    } elseif ($data->everee_payment_status == 1) {
                        $everee_webhook_message = 'Waiting for payment status to be updated.';
                    } elseif ($data->everee_payment_status == 0) {
                        $everee_webhook_message = 'External payment processing is disabled. Payment completed locally';
                    }

                    $custom_filed = [];
                    $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                    if ($setting) {
                        foreach ($setting as $value) {
                            $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $data->id])->first();
                            $total_custom += @$payroll_data->value;

                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => @$value['field_name'],
                                'comment' => @$payroll_data['comment'],
                                'value' => @$payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }
                    }

                    $yellow_status = 0;
                    $s3_image = (isset($data->usersdata->image) && $data->usersdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image) : null;

                    if ($CrmData && $workerType == '1099' && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId)) {
                        $everee_onboarding_process = 1;
                    } elseif (($CrmData && $workerType == 'w2' || $workerType == 'W2') && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId) && $data->usersdata->everee_embed_onboard_profile == 1) {
                        $everee_onboarding_process = 1;
                    } else {
                        $everee_onboarding_process = 0;
                    }

                    $result_data[] = [
                        'id' => $data->id,
                        'payroll_id' => $data->payroll_id,
                        'user_id' => $data->user_id,
                        'approvals_and_requests_status' => 0,
                        'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                        'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                        'position_id' => isset($data->usersdata) ? $data->usersdata->position_id : null,
                        'sub_position_id' => isset($data->usersdata) ? $data->usersdata->sub_position_id : null,
                        'is_super_admin' => isset($data->usersdata) ? $data->usersdata->is_super_admin : null,
                        'is_manager' => isset($data->usersdata) ? $data->usersdata->is_manager : null,
                        'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                        'image_s3' => $s3_image,
                        'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                        'commission' => isset($data->commission) ? $data->commission * 1 : 0,
                        'override' => isset($data->override) ? $data->override * 1 : 0,
                        'override_value_is_higher' => 0,
                        'adjustment' => isset($data->adjustment) ? $data->adjustment * 1 : 0,
                        'reimbursement' => isset($data->reimbursement) ? $data->reimbursement * 1 : 0,
                        'clawback' => isset($data->clawback) ? $data->clawback * 1 : 0,
                        'deduction' => isset($data->deduction) ? $data->deduction * 1 : 0,
                        'reconciliation' => isset($data->reconciliation) ? $data->reconciliation * 1 : 0,
                        'net_pay' => round($data->net_pay, 2),
                        'gross_pay' => 0,
                        'status_id' => $data->status,
                        'status' => isset($data->payrollstatus) ? $data->payrollstatus->status : null,
                        'is_mark_paid' => isset($data->is_mark_paid) ? $data->is_mark_paid : 0,
                        'is_next_payroll' => isset($data->is_next_payroll) ? $data->is_next_payroll : 0,
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                        'custom_filed' => $custom_filed,
                        'commission_yellow_status' => ($data->commission_count >= 1 || $data->clawback_count >= 1) ? 1 : 0,
                        'override_yellow_status' => ($data->override_count >= 1) ? 1 : 0,
                        'approve_request_yellow_status' => ($data->approve_request_count >= 1 || $data->payroll_adjustment_details_count >= 1) ? 1 : 0,
                        'reimbursement_yellow_status' => ($data->reimbursement_count >= 1) ? 1 : 0,
                        'deduction_yellow_status' => 0,
                        'paid_next' => 'comm-'.$data->commission_count.' ,over-'.$data->override_count.' ,claw-'.$data->clawback_count.' , appr-'.$data->approve_request_count.' ,reimb-'.$data->reimbursement_count,
                        'total_custom' => $total_custom,
                        'everee_response' => $everee_webhook_message,
                        'hourly_salary' => isset($data->hourly_salary) ? $data->hourly_salary : 0,
                        'overtime' => isset($data->overtime) ? $data->overtime : 0,
                        'worker_type' => isset($workerType) ? $workerType : null,
                        'everee_onboarding_process' => $everee_onboarding_process,
                        'everee_payment_status' => $data->everee_payment_status,
                        'is_onetime_payment' => isset($data->is_onetime_payment) ? $data->is_onetime_payment : 0,

                    ];
                }

                $data = paginate($result_data, $perpage);

                return response()->json([
                    'ApiName' => 'get_payroll_data_employee',
                    'status' => true,
                    'message' => 'Successfully.',
                    'finalize_status' => null,
                    'payment_failed' => 1,
                    'payment_proccess' => $paymemntFailedStatus ? 1 : 0,
                    'data' => $data,
                    'all_paid' => null,
                    'all_next' => null,
                    'payroll_total' => round($payroll_total, 2),
                    'second_total' => $emp_total,
                    'total_alert_count' => 0,
                ]);
            }

            // CHECKING PAYROLL IS CLOSED OR NOT FOR THE GIVEN PAY PERIOD.
            $check_payroll_closed_status = $this->check_payroll_closed_status($start_date, $end_date, $pay_frequency, $workerType);
            if (! empty($check_payroll_closed_status)) {
                return $check_payroll_closed_status;
            }

            // Payroll Zero Data update
            $this->updatePayrollZeroData($start_date, $end_date, $pay_frequency);
            // End Payroll Zero Data update

            // UPDATING PAYROLL RECORDS AMOUNT VALUE IN PAYROLL FOR THE GIVEN PAY PERIODS.
            if (! empty($start_date) && ! empty($end_date)) {
                $this->update_payroll_data($start_date, $end_date, $pay_frequency);
            }
            // plz dont delete this function
            $this->deduction_for_all_deduction_enable_users($start_date, $end_date, $pay_frequency, $workerType, $usersids);

            // CHECKING ALL PAYROLLS ARE PAID OR NOT , FOR FINALIZING PROCESS.
            $all_paid = false;
            $data_query = Payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            });
            $count_data = $data_query->count();
            if ($count_data > 0) {
                $all_paid_count = $data_query->where('is_mark_paid', '0')->count();
                if ($all_paid_count == 0) {
                    $all_paid = true;
                }
            }

            // CHECKING ALL PAYROLLS ARE Moved To Next OR NOT, FOR FINALIZING PROCESS.
            $all_next = false;
            $data_query = Payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            });
            $count_data = $data_query->count();
            if ($count_data > 0) {
                $all_paid_count = $data_query->where('is_next_payroll', '0')->count();
                if ($all_paid_count == 0) {
                    $all_next = true;
                }
            }

            $users = User::orderBy('id', 'asc');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $users->where(function ($query) use ($search_full_name) {
                    return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere('first_name', 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search_full_name.'%')
                        ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                });
            }
            $userArray = $users->where('worker_type', $workerType)->pluck('id')->toArray();

            $checkFinalizeStatus = 0;
            $data_query = Payroll::with( // 'payrolladjust' , 'PayrollShiftHistorie', 'reconciliationInfo'
                'usersdata',
                'payrollstatus',
                'approvalRequest'
            )
                ->whereIn('user_id', $userArray)
                ->whereHas('usersdata', function ($q) use ($search_full_name) {
                    $q->where(function ($query) use ($search_full_name) {
                        $query->where('first_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere('last_name', 'LIKE', "%{$search_full_name}%")
                            ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search_full_name}%");
                    });
                })
                ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->where('id', '!=', 0)
                ->withCount([
                    'userCommission as commission_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)
                            ->orWhere('is_next_payroll', '>=', 1)
                            ->orWhere('is_onetime_payment', '>=', 1)
                            ->orWhere('is_move_to_recon', '>=', 1);
                    },
                    'userOverride as override_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)
                            ->orWhere('is_next_payroll', '>=', 1)
                            ->orWhere('is_onetime_payment', '>=', 1)
                            ->orWhere('is_move_to_recon', '>=', 1);
                    },
                    'userClawback as commission_clawback_count' => function ($query) {
                        $query->where('type', 'commission')->where(function ($q) {
                            $q->where('is_mark_paid', '>=', '1')->orWhere('is_next_payroll', '>=', 1)->orWhere('is_move_to_recon', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                        });
                    },
                    'userClawback as override_clawback_count' => function ($query) {
                        $query->where('type', 'overrides')->where(function ($q) {
                            $q->where('is_mark_paid', '>=', '1')->orWhere('is_next_payroll', '>=', 1)->orWhere('is_move_to_recon', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                        });
                    },
                    'userApproveRequest as approve_request_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },
                    'userApproveRequestReimbursement as reimbursement_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },
                    'userPayrollAdjustmentDetails as payroll_adjustment_details_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_move_to_recon', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },
                    'userDeductions as deduction_count' => function ($query) {
                        $query->where('is_mark_paid', '>=', 1)->orWhere('is_next_payroll', '>=', 1)->orWhere('is_move_to_recon', '>=', 1)->orWhere('is_onetime_payment', '>=', 1);
                    },

                ]);

            $payroll_total = payroll::with('workertype')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0, 'is_mark_paid' => 0, 'is_next_payroll' => 0])
                ->whereHas('workertype', function ($q) use ($workerType) {
                    $q->where('worker_type', $workerType);
                })->sum('net_pay');

            if ($positions && $positions != '') {
                $data_query->where('position_id', $positions);
            }

            if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
                $data_query->where('net_pay', '<', 0);
            }

            if ($commission && $commission != '') {
                $data_query->where('commission', $commission);
            }

            $data_query->orderBy(
                User::select('first_name')
                    ->whereColumn('id', 'payrolls.user_id')
                    ->orderBy('first_name', 'asc')
                    ->limit(1),
                'ASC'
            );

            if (isset($request->is_reconciliation) && $request->is_reconciliation == 1) {
                $positionArray = PositionReconciliations::where('status', 1)->pluck('position_id')->toArray();
                $data_query->whereIn('position_id', $positionArray);
            }
            $payroll_data = $data_query->get();

            // $uId = $payroll_data->pluck('user_id')->toArray();

            $result_data = [];
            $total_custom = 0;
            if (count($payroll_data) > 0) {
                foreach ($payroll_data as $data) {
                    $custom_filed = [];
                    $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
                    if ($setting) {
                        foreach ($setting as $value) {
                            $payroll_data = CustomField::where(['column_id' => $value['id'], 'payroll_id' => $data->id])->first();
                            $total_custom += (float) @$payroll_data->value;

                            $custom_filed[] = [
                                'id' => @$value['id'],
                                'field_name' => @$value['field_name'],
                                'value' => @$payroll_data->value,
                                'worker_type' => @$value['worked_type'],
                            ];
                        }
                    }

                    if ($checkFinalizeStatus == 0 && $data->status > 1) {
                        $checkFinalizeStatus = 1;
                    }
                    $yellow_status = 0;
                    if (in_array($data->user_id, $userArray)) {
                        if ($data->is_mark_paid == 1 || $data->is_next_payroll >= 1) {
                            $yellow_status = 1;
                        } else {
                            if (($data->commission_count + $data->override_count + $data->clawback_count + $data->approve_request_count) > 0) {
                                $yellow_status = 1;
                            } else {
                                $yellow_status = 0;
                            }
                        }
                        $s3_image = (isset($data->usersdata->image) && $data->usersdata->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image) : null;
                        $netPay = $data->net_pay;

                        if ($CrmData && $workerType == '1099' && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId)) {
                            $everee_onboarding_process = 1;
                        } elseif ($CrmData && ($workerType == 'w2' || $workerType == 'W2') && isset($data->usersdata) && ! empty($data->usersdata->everee_workerId) && $data->usersdata->everee_embed_onboard_profile == 1) {
                            $everee_onboarding_process = 1;
                        } else {
                            $everee_onboarding_process = 0;
                        }

                        // //$CrmData = Crms::where('id', 3)->where('status', 1)->first();
                        // if($CrmData && $workerType=='1099' && isset($data->usersdata) && !empty($data->usersdata->everee_workerId)) {
                        //     $everee_payment_status = 1;
                        // }
                        // elseif($CrmData &&  ($workerType=='w2' || $workerType=='W2') && isset($data->usersdata) && !empty($data->usersdata->everee_workerId) && $data->usersdata->everee_embed_onboard_profile == 1) {
                        //     $everee_payment_status = 1;
                        // }
                        // else{
                        //     $everee_payment_status = 0;
                        // }

                        $result_data[] = [
                            'id' => $data->id,
                            'payroll_id' => $data->id,
                            'user_id' => $data->user_id,
                            'approvals_and_requests_status' => (empty($data->approvalRequest)) ? 0 : 1,
                            'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                            'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                            'position_id' => isset($data->usersdata) ? $data->usersdata->position_id : null,
                            'sub_position_id' => isset($data->usersdata) ? $data->usersdata->sub_position_id : null,
                            'is_super_admin' => isset($data->usersdata) ? $data->usersdata->is_super_admin : null,
                            'is_manager' => isset($data->usersdata) ? $data->usersdata->is_manager : null,
                            'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                            'image_s3' => $s3_image,
                            'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                            'commission' => isset($data->commission) ? $data->commission * 1 : 0,
                            'override' => isset($data->override) ? $data->override * 1 : 0,
                            'override_value_is_higher' => 0,
                            'adjustment' => isset($data->adjustment) ? $data->adjustment * 1 : 0,
                            'reimbursement' => isset($data->reimbursement) ? $data->reimbursement * 1 : 0,
                            'clawback' => isset($data->clawback) ? $data->clawback * 1 : 0,
                            'deduction' => isset($data->deduction) ? $data->deduction * 1 : 0,
                            'reconciliation' => isset($data->reconciliation) ? $data->reconciliation * 1 : 0,
                            'net_pay' => round($data->net_pay, 2),
                            'gross_pay' => round($data->gross_pay, 2),
                            // 'comment' => isset($data->payrolladjust) ? $data->payrolladjust->comment : null,
                            'status_id' => $data->status,
                            'status' => isset($data->payrollstatus) ? $data->payrollstatus->status : null,
                            'is_mark_paid' => isset($data->is_mark_paid) ? $data->is_mark_paid : 0,
                            'is_next_payroll' => isset($data->is_next_payroll) ? $data->is_next_payroll : 0,
                            'created_at' => $data->created_at,
                            'updated_at' => $data->updated_at,
                            'custom_filed' => $custom_filed,
                            // 'PayrollShiftHistorie_count' => isset($data->PayrollShiftHistorie) ? count($data->PayrollShiftHistorie) : '',
                            'commission_yellow_status' => ($data->commission_count >= 1 || $data->commission_clawback_count >= 1) ? 1 : 0,
                            'override_yellow_status' => ($data->override_count >= 1 || $data->override_clawback_count >= 1) ? 1 : 0,
                            'approve_request_yellow_status' => ($data->approve_request_count >= 1 || $data->payroll_adjustment_details_count >= 1) ? 1 : 0,
                            'reimbursement_yellow_status' => ($data->reimbursement_count >= 1) ? 1 : 0,
                            'deduction_yellow_status' => ($data->deduction_count >= 1) ? 1 : 0,
                            'paid_next' => 'comm-'.$data->commission_count.' ,over-'.$data->override_count.' ,claw-'.$data->clawback_count.' , appr-'.$data->approve_request_count.' ,reimb-'.$data->reimbursement_count,
                            'total_custom' => $total_custom,
                            'everee_response' => @$everee_webhook_message,
                            'everee_payment_status' => 0,
                            'is_stop_payroll' => isset($data->is_stop_payroll) ? $data->is_stop_payroll : 0,
                            'hourly_salary' => isset($data->hourly_salary) ? $data->hourly_salary : 0,
                            'overtime' => isset($data->overtime) ? $data->overtime : 0,
                            'worker_type' => isset($workerType) ? $workerType : null,
                            'everee_onboarding_process' => $everee_onboarding_process,
                            'is_onetime_payment' => isset($data->is_onetime_payment) ? $data->is_onetime_payment : 0,
                        ];
                    }
                    unset($yellow_status);
                }
            }

            // Check if both 'sort' (column name) and 'sort_val' (sorting direction) are present in the request
            if (! empty($request->has('sort')) && ! empty($request->has('sort_val')) && ! empty($result_data)) {
                // Get the column to sort by from the request
                $sortKey = $request->get('sort');

                // Determine the sorting direction; default to 'asc' if not explicitly 'desc'
                $sortDirection = strtolower($request->get('sort_val')) === 'desc' ? 'desc' : 'asc';

                // Define the list of allowed keys that can be sorted for payroll_data_employees
                $allowedSortKeys = [
                    'first_name', 'last_name', 'position', 'commission', 'override', 'adjustment',
                    'reimbursement', 'deduction', 'reconciliation', 'net_pay', 'gross_pay',
                    'hourly_salary', 'overtime', 'clawback',
                ];

                // If the requested sort key is in the allowed list, apply sorting
                if (in_array($sortKey, $allowedSortKeys)) {
                    // Use Laravel's Collection to sort the result_data array by the given key
                    $result_data = collect($result_data)->sortBy(function ($item) use ($sortKey) {
                        // Sort by the value of the specified key, fallback to null if not present
                        return $item[$sortKey] ?? null;
                    }, SORT_REGULAR, strtolower($sortDirection) === 'desc')->values()->toArray();
                }
            }

            $data = paginate($result_data, $perpage);
            // $saleCount = LegacyApiNullData::whereBetween('m1_date', [$start_date, $end_date])->orWhereBetween('m2_date', [$start_date, $end_date])->whereNotNull('data_source_type')->count();
            // $peopleCount = User::whereIn('id', $uId)->where('tax_information', null)->where('name_of_bank', null)->count();
            // $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            // if ($CrmData) //&& $request['everee_failed_status']!=1
            // {
            //     $paydata = Payroll::with('usersdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
            //     if (count($paydata) > 0) {
            //         event(new EvereeOnboardingUserEvent($paydata, $payroll = '1'));
            //     }
            // }

            $total_alert_count_query = LegacyApiNullData::select(
                DB::raw('count(`sales_alert`) as sales_alert'),
                DB::raw('count(`missingrep_alert`) as missingrep'),
                DB::raw('count(`closedpayroll_alert`) as closedpayroll'),
                DB::raw('count(`locationredline_alert`) as locationredline'),
                DB::raw('count(`repredline_alert`) as repredline')
            );
            $total_alert_count_query->where(function ($query) use ($request) {
                return $query->whereRaw("`m1_date` >= '".$request->start_date."' AND `m1_date` <= '".$request->end_date."'")
                    ->orWhereRaw("`m2_date` >= '".$request->start_date."' AND `m2_date` <= '".$request->end_date."'");
            });
            $total_alert_count = $total_alert_count_query->whereNotNull('data_source_type')->first();

            payroll::where('status', 2)->where('finalize_status', '!=', 2)->update(['finalize_status' => 2]);
            payroll::where('status', 1)->where('finalize_status', 2)->update(['finalize_status' => 0]);

            return response()->json([
                'ApiName' => 'get_payroll_data_employee',
                'status' => true,
                'message' => 'Successfully.',
                'finalize_status' => isset($checkFinalizeStatus) ? $checkFinalizeStatus : null,
                'payment_failed' => 0,
                'payment_proccess' => $paymemntFailedStatus ? 1 : 0,
                'data' => $data,
                'all_paid' => $all_paid,
                'all_next' => $all_next,
                'payroll_total' => round($payroll_total, 2),
                'second_total' => $emp_total,
                'total_alert_count' => isset($total_alert_count) ? $total_alert_count : 0,
            ], 200);
        }
    }

    public function getPayrollWorkers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'worker_type' => 'required',
            'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'get_payroll_worker_data',
                'status' => false,
                'error' => $validator->errors(),
            ], 400);
        }

        $data = [];
        $payroll_total = 0;
        $workerType = ! empty($request->input('worker_type')) ? $request->input('worker_type') : '1099';
        $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
        $positions = $request->input('position_filter');
        $netPay = $request->input('netpay_filter');
        $commission = $request->input('commission_filter');
        $pay_frequency = $request->input('pay_frequency');
        $query = User::whereIn('sub_position_id', function ($query) use ($pay_frequency) {
            $query->select('position_id')
                ->from('position_pay_frequencies')
                ->where('frequency_type_id', $pay_frequency);
        });
        if ($workerType) {
            $query->where('worker_type', $workerType);
        }
        $usersids = $query->pluck('id');
        if (! empty($request->input('perpage'))) {
            $perpage = $request->input('perpage');
        } else {
            $perpage = 10;
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $fullName = $request->input('search');
        $type = $request->input('type');
        $everee_webhook_message = '';
        $everee_payment_status = 0;
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 3)->first();

        $checkFinalizeStatus = 0;
        $data_query = Payroll::with( // 'payrolladjust' , 'PayrollShiftHistorie', 'reconciliationInfo'
            'usersdata', 'workertype'
        )
            ->whereIn('user_id', $usersids)
            // ->whereIn('finalize_status', [0,1])
            ->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })
            ->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            })
            // ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
            ->where('id', '!=', 0)
            ->where(function ($query) {
                $query->orWhere('is_mark_paid', 1)
                    ->orWhere('is_next_payroll', 1)
                    ->orWhere('is_onetime_payment', 1)
                    ->orWhere('status', 3)
                    ->orWhere('reconciliation', '>', 0);
            });

        // if ($positions && $positions != '') {
        //     $data_query->where('position_id', $positions);
        // }

        // if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
        //     $data_query->where('net_pay', '<', 0);
        // }

        // if ($commission && $commission != '') {
        //     $data_query->where('commission', $commission);
        // }

        $data_query->orderBy(
            User::select('first_name')
                ->whereColumn('id', 'payrolls.user_id')
                ->orderBy('first_name', 'asc')
                ->limit(1),
            'ASC'
        );
        $useridsfrompayroll = $data_query->pluck('user_id');

        $payrollhistorydata_query = PayrollHistory::with( // 'payrolladjust' , 'PayrollShiftHistorie', 'reconciliationInfo'
            'usersdata', 'workertype'
        )
            ->whereIn('user_id', $usersids)
            // ->whereIn('finalize_status', [0,1])
            ->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($start_date, $end_date) {
                $query->where([
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
            })
            ->whereHas('workertype', function ($q) use ($workerType) {
                $q->where('worker_type', $workerType);
            })
            // ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
            ->where('id', '!=', 0)
            ->where(function ($query) {
                $query->orWhere('is_onetime_payment', 1)
                    ->orWhere('status', 3)
                    ->orWhere('reconciliation', '>', 0);
            });

        $payrollhistorydata_query->orderBy(
            User::select('first_name')
                ->whereColumn('id', 'payroll_history.user_id')
                ->orderBy('first_name', 'asc')
                ->limit(1),
            'ASC'
        );
        $payrollhistoryuseridsfrompayroll = $payrollhistorydata_query->pluck('user_id');
        // dd($useridsfrompayroll);

        if ($useridsfrompayroll) {
            $remainingUsers = $usersids->diff($useridsfrompayroll);
            $remainingUsers = $remainingUsers->diff($payrollhistoryuseridsfrompayroll);
        } else {
            $remainingUsers = $usersids;
        }

        // dd($remainingUsers);
        $user_data = User::whereIn('id', $remainingUsers)->get();

        // if (isset($request->is_reconciliation) && $request->is_reconciliation == 1) {
        //     $positionArray = PositionReconciliations::where('status', 1)->pluck('position_id')->toArray();
        //     $data_query->whereIn('position_id', $positionArray);
        // }
        // $payroll_data = $data_query->paginate($perpage);
        // $payroll_data = $data_query->get();

        // $uId = $payroll_data->pluck('user_id')->toArray();

        // dd($uId);

        $result_data = [];
        $total_custom = 0;

        $customcolumndatafromsetting = PayrollSsetup::where('worked_type', 'LIKE', '%'.$workerTypeValue.'%')->orderBy('id', 'Asc')->get();
        // dd($customcolumndatafromsetting);
        // $customcolumndatafromsetting = array_column($customcolumndatafromsetting,'field_name');
        // dd($customcolumndatafromsetting);

        if (count($user_data) > 0) {
            foreach ($user_data as $key => $data) {
                $customFieldArray = [];
                if ($customcolumndatafromsetting) {
                    foreach ($customcolumndatafromsetting as $customcolumndatafromsettingkey => $customcolumndatafromsettingval) {
                        $customFieldValue = CustomField::where(['user_id' => $data->id, 'column_id' => $customcolumndatafromsettingval->id])
                            ->when($pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($start_date, $end_date) {
                                $query->whereBetween('pay_period_from', [$start_date, $end_date])
                                    ->whereBetween('pay_period_to', [$start_date, $end_date])
                                    ->whereColumn('pay_period_from', 'pay_period_to');
                            }, function ($query) use ($start_date, $end_date) {
                                $query->where([
                                    'pay_period_from' => $start_date,
                                    'pay_period_to' => $end_date,
                                ]);
                            })->latest('id')->first(['value']);
                        $customFieldArray[] = ['custom_field_id' => $customcolumndatafromsettingval->id, 'custom_field_value' => $customFieldValue ? $customFieldValue->value : ''];
                    }
                }

                $s3_image = (isset($data->image) && $data->image != null) ? s3_getTempUrl(config('app.domain_name').'/'.$data->image) : null;
                if ($CrmData && $workerType == '1099' && isset($data) && ! empty($data->everee_workerId)) {
                    $everee_onboarding_process = 1;
                } elseif ($CrmData && ($workerType == 'w2' || $workerType == 'W2') && isset($data) && ! empty($data->everee_workerId) && $data->everee_embed_onboard_profile == 1) {
                    $everee_onboarding_process = 1;
                } else {
                    $everee_onboarding_process = 0;
                }

                $result_data[] = [
                    'id' => $data->id,
                    'user_id' => $data->id,
                    'first_name' => isset($data->first_name) ? $data->first_name : null,
                    'last_name' => isset($data->last_name) ? $data->last_name : null,
                    'user_email' => isset($data->email) ? $data->email : null,
                    'is_super_admin' => isset($data->is_super_admin) ? $data->is_super_admin : null,
                    'is_manager' => isset($data->is_manager) ? $data->is_manager : null,
                    'image' => isset($data->image) ? $data->image : null,
                    'image_s3' => $s3_image,
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'custom_fields' => $customFieldArray,
                ];
                // }
                // unset($yellow_status);
            }
        }

        return response()->json([
            'ApiName' => 'get_payroll_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result_data,
        ], 200);
    }

    // public function hourlySalaryDetails(Request $request)
    // {
    //     $data = array();
    //     $subtotal = [];
    //     $Validator = Validator::make(
    //         $request->all(),
    //         [
    //             'payroll_id' => 'required', // 15
    //             'user_id' => 'required',
    //             'pay_period_from' => 'required',
    //             'pay_period_to' => 'required',
    //         ]
    //     );
    //     if ($Validator->fails()) {
    //         return response()->json(['error' => $Validator->errors()], 400);
    //     }

    //     $payroll_id = $request->payroll_id;
    //     $user_id = $request->user_id;
    //     $pay_period_from = $request->pay_period_from;
    //     $pay_period_to = $request->pay_period_to;

    //     $payroll = Payroll::where(['id'=> $payroll_id, 'user_id'=> $user_id, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->first();

    //     if (!empty($payroll)) {
    //         $userSalary = PayrollHourlySalary::with('payrollcommon')->whereIn('status', [1, 2, 6])
    //             ->where([
    //                 'payroll_id' => $payroll_id,
    //                 'user_id' =>  $payroll->user_id,
    //                 'pay_period_from' =>  $payroll->pay_period_from,
    //                 'pay_period_to' =>  $payroll->pay_period_to
    //             ])
    //             ->get();

    //         foreach ($userSalary as $key => $value) {
    //             $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
    //             $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
    //             $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)) . ' - ' . date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
    //             $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date)?date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)):'';

    //             $date = isset($value->date) ? $value->date : '';

    //             $data[$payroll_status][$period][] = [
    //                 'id' => $value->id,
    //                 'payroll_id' => $value->payroll_id,
    //                 'date' => isset($date) ? $date : null,
    //                 'hourly_rate' => isset($value->hourly_rate) ? $value->hourly_rate*1 : null,
    //                 'salary' => isset($value->salary) ? $value->salary*1 : null,
    //                 'regular_hour' => isset($value->regular_hours) ? $value->regular_hours : null,
    //                 'total' => isset($value->total) ? $value->total*1 : null,
    //                 'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
    //                 'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
    //                 'position_id' => $value->position_id,
    //                 'is_mark_paid' => $value->is_mark_paid,
    //                 'is_next_payroll' => $value->is_next_payroll,
    //                 'is_move_to_recon' => $value->is_move_to_recon,
    //                 'payroll_modified_date' => ($period=='current')?'':$payroll_modified_date,
    //                 'is_stop_payroll' => @$payroll->is_stop_payroll,
    //                 'hourlysalary_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
    //                 'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name .' '  .  $adjustmentAmount->commented_by->last_name  : 'Super Admin',
    //                 'adjustment_comment' =>isset($adjustmentAmount->comment) ? $adjustmentAmount->comment:null,
    //                 'adjustment_id' =>isset($adjustmentAmount->id) ? $adjustmentAmount->id:null,
    //                 'is_recon' => $this->checkPositionReconStatus($request->user_id),
    //                 'amount_type' => 'hourlysalary',
    //             ];

    //             $subtotal['salary'][$payroll_calculate][] = $value->total*1;
    //             unset($value);
    //         }

    //         $data['subtotal'] = $subtotal;

    //         return response()->json([
    //             'ApiName' => 'hourly_salary_details',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'payroll_status' => $payroll->status,
    //             'is_recon' => $this->checkPositionReconStatus($request->user_id),
    //             'data' => $data,
    //         ], 200);
    //     } else {

    //         return response()->json([
    //             'ApiName' => 'hourly_salary_details',
    //             'status' => true,
    //             'message' => 'No Records.',
    //             'data' => [],
    //         ], 200);
    //     }

    // }

    // public function overtimeDetails(Request $request)
    // {
    //     $data = array();
    //     $subtotal = [];
    //     $Validator = Validator::make(
    //         $request->all(),
    //         [
    //             'payroll_id' => 'required', // 15
    //             'user_id' => 'required',
    //             'pay_period_from' => 'required',
    //             'pay_period_to' => 'required',
    //         ]
    //     );
    //     if ($Validator->fails()) {
    //         return response()->json(['error' => $Validator->errors()], 400);
    //     }

    //     $payroll_id = $request->payroll_id;
    //     $user_id = $request->user_id;
    //     $pay_period_from = $request->pay_period_from;
    //     $pay_period_to = $request->pay_period_to;

    //     $payroll = Payroll::where(['id'=> $payroll_id, 'user_id'=> $user_id, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->first();

    //     if (!empty($payroll)) {
    //         $userSalary = PayrollOvertime::with('payrollcommon')->whereIn('status', [1, 2, 6])
    //             ->where([
    //                 'payroll_id' => $payroll_id,
    //                 'user_id' =>  $payroll->user_id,
    //                 'pay_period_from' =>  $payroll->pay_period_from,
    //                 'pay_period_to' =>  $payroll->pay_period_to
    //             ])
    //             ->get();

    //         foreach ($userSalary as $key => $value) {
    //             $payroll_status = (empty($value->payrollcommon)) ? 'current_payroll' : 'next_payroll';
    //             $payroll_calculate = ($value->is_mark_paid == 1 || $value->is_next_payroll == 1) ? 'ignore' : 'count';
    //             $period = (empty($value->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($value->payrollcommon->orig_payfrom)) . ' - ' . date('m/d/Y', strtotime($value->payrollcommon->orig_payto)));
    //             $payroll_modified_date = isset($value->payrollcommon->payroll_modified_date)?date('m/d/Y', strtotime($value->payrollcommon->payroll_modified_date)):'';

    //             $date = isset($value->date) ? $value->date : '';

    //             $data[$payroll_status][$period][] = [
    //                 'id' => $value->id,
    //                 'payroll_id' => $value->payroll_id,
    //                 'date' => isset($date) ? $date : null,
    //                 'overtime_rate' => isset($value->overtime_rate) ? $value->overtime_rate*1 : null,
    //                 'overtime' => isset($value->overtime) ? $value->overtime*1 : null,
    //                 'total' => isset($value->total) ? $value->total*1 : null,
    //                 'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
    //                 'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
    //                 'position_id' => $value->position_id,
    //                 'is_mark_paid' => $value->is_mark_paid,
    //                 'is_next_payroll' => $value->is_next_payroll,
    //                 'is_move_to_recon' => $value->is_move_to_recon,
    //                 'payroll_modified_date' => ($period=='current')?'':$payroll_modified_date,
    //                 'is_stop_payroll' => @$payroll->is_stop_payroll,
    //                 'overtime_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
    //                 'adjustment_by' => isset($adjustmentAmount->commented_by->first_name) ? $adjustmentAmount->commented_by->first_name .' '  .  $adjustmentAmount->commented_by->last_name  : 'Super Admin',
    //                 'adjustment_comment' =>isset($adjustmentAmount->comment) ? $adjustmentAmount->comment:null,
    //                 'adjustment_id' =>isset($adjustmentAmount->id) ? $adjustmentAmount->id:null,
    //                 'is_recon' => $this->checkPositionReconStatus($request->user_id),
    //                 'amount_type' => 'overtime'
    //             ];

    //             $subtotal['overtime'][$payroll_calculate][] = $value->total*1;
    //             unset($value);
    //         }

    //         $data['subtotal'] = $subtotal;

    //         return response()->json([
    //             'ApiName' => 'overtime_details',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'payroll_status' => $payroll->status,
    //             'is_recon' => $this->checkPositionReconStatus($request->user_id),
    //             'data' => $data,
    //         ], 200);
    //     } else {

    //         return response()->json([
    //             'ApiName' => 'overtime_details',
    //             'status' => true,
    //             'message' => 'No Records.',
    //             'data' => [],
    //         ], 200);
    //     }

    // }

    public function checkPositionReconStatus($userId)
    {
        $userPosition = User::find($userId);
        $reconPositionData = PositionReconciliations::where('position_id', $userPosition->sub_position_id)->first();
        $reconStatus = false;
        if ($reconPositionData) {
            $reconStatus = $reconPositionData->status === 1 ? 1 : 0;
        }

        return $reconStatus;
    }

    public function sendAttendanceData($getPayrollData, $start_date, $end_date)
    {

        $myArray = [];
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();

        if (count($getPayrollData) > 0) {
            foreach ($getPayrollData as $k1 => $val) {

                $actualNetPay = $val['net_pay'];

                if ($CrmData && $val['is_mark_paid'] != 1 && $val['net_pay'] > 0 && $val['status'] != 6 && $val['status'] != 7) {
                    $user_attendance = UserAttendance::where('user_id', $val->user_id)
                        ->whereBetween('date', [$start_date, $end_date])->get();
                    // dd(count($user_attendance));
                    $untracked = [];
                    $external_id = '';
                    $enableEVE = 0;
                    if (count($user_attendance) > 0) {
                        // $data = $this->sendAttendanceData($user_attendance);

                        foreach ($user_attendance as $data) {
                            $attendance_details_obj = UserAttendanceDetail::where('user_attendance_id', $data->id)->get()->toArray();
                            // dd($attendance_details_obj);
                            $types = array_column($attendance_details_obj, 'type');
                            $dates = array_column($attendance_details_obj, 'attendance_date');
                            // dd($types, $dates);
                            $payload = [];
                            $findUser = User::find($data->user_id);
                            $payload['user_id'] = $data->user_id;
                            $payload['clockIn'] = $dates[array_search('clock in', $types)];
                            $payload['clockOut'] = $dates[array_search('clock out', $types)];
                            $payload['lunch'] = $dates[array_search('lunch', $types)];
                            $payload['lunchEnd'] = $dates[array_search('end lunch', $types)];
                            $payload['break'] = $dates[array_search('break', $types)];
                            $payload['breakEnd'] = $dates[array_search('end break', $types)];
                            $payload['workerId'] = ! empty($findUser->everee_workerId) ? $findUser->everee_workerId : null;
                            $payload['externalWorkerId'] = ! empty($findUser->employee_id) ? $findUser->employee_id : null;
                            // dd($payload);
                            $untracked = $this->send_timesheet_data($payload);

                            // if(empty($data->everee_status) || is_null($data->everee_status)){
                            //     $data->everee_status = $getResponse;
                            //     $data->save();
                            // }
                        }
                        $external_id = '';
                        $enableEVE = 1;
                    }
                } else {
                    $external_id = '';
                    $enableEVE = 0;
                }

                $checkPayroll = Payroll::where('id', $val->id)->first();
                if ($checkPayroll->net_pay != $actualNetPay) {
                    $errorMessage = 'netpay amount being sent to external processor is '.$val->net_pay.' and netpay in payroll is now '.$checkPayroll->net_pay;
                    $finalize_status = Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_message' => $errorMessage]);
                    if ($enableEVE == 1) {
                        $myArray[$val->user_id] = $errorMessage;
                    }
                } else {
                    if ((isset($untracked['success']['status']) && $untracked['success']['status'] == true) || $enableEVE == 0) {
                        $status = Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => $external_id, 'everee_message' => null]);
                        $errorMessage = '';
                        if ($enableEVE == 1) {
                            $myArray[$val->user_id] = $errorMessage;
                        }
                    } elseif ((isset($untracked['fail']['status']) && $untracked['fail']['status'] == false) || $enableEVE == 0) {
                        $errorMessage = isset($untracked['fail']['everee_response']['errorMessage']) ? $untracked['fail']['everee_response']['errorMessage'] : '';
                        $finalize_status = Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_message' => $errorMessage]);
                        if ($enableEVE == 1) {
                            $myArray[$val->user_id] = $errorMessage;
                        }
                    } else {
                        $errorMessage = 'something went wrong!';
                        $finalize_status = Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_message' => $errorMessage]);
                        $myArray[$val->user_id] = $errorMessage;
                    }
                }

                // $status = Payroll::where('id',$val->id)->update(['status'=>2,'finalize_status'=> 2,'everee_message' => null]);
            }
        }
    }

    private function getUserAttendenceApprovedStatus($userIdArray, $startDate, $endDate)
    {
        $userSchedulesData = UserSchedule::join('users', 'user_schedules.user_id', 'users.id')
            ->join('user_schedule_details', 'user_schedule_details.schedule_id', 'user_schedules.id')
            ->whereBetween('user_schedule_details.schedule_from', [$startDate, $endDate])
            ->whereIn('user_schedules.user_id', $userIdArray)
            ->where('user_schedule_details.attendance_status', 0)->count();
        if ($userSchedulesData > 0) {
            return 1;
        } else {
            return 0;
        }
    }

    private function evereeExecutePayroll($data)
    {

        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
        $myArray = [];

        $data->transform(function ($data, $key) use ($CrmData, &$myArray) {
            $user_id = $data->user_id;
            $actualNetPay = $data['net_pay'];
            if (! empty($CrmData) && $data['is_mark_paid'] != 1 && $data['net_pay'] > 0 && $data['status'] != 6 && $data['status'] != 7) { // $ data['is_next_payroll'] !=1 &&
                $workerId = isset($data['usersdata']['everee_workerId']) ? $data['usersdata']['everee_workerId'] : null;
                $date = date('Y-m-d');

                $userWagesHistory = UserWagesHistory::where(['user_id' => $data->user_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
                $unitRate = isset($userWagesHistory->pay_type) ? $userWagesHistory->pay_type : null;
                $pay_rate = isset($userWagesHistory->pay_rate) ? $userWagesHistory->pay_rate : '0';

                $office_id = isset($data['usersdata']['office_id']) ? $data['usersdata']['office_id'] : null;
                $location_data = Locations::where('id', $office_id)->first();
                $workLocationId = isset($location_data->everee_location_id) ? $location_data->everee_location_id : '';

                if (! empty($workerId)) {

                    if ($unitRate == 'Salary' && $data['hourly_salary'] > 0) {

                        $external_id = 'S-'.$data['usersdata']['employee_id'].'-'.$data->id;
                        $hourly_salary = $data['hourly_salary'];
                        $earningType = 'REGULAR_SALARY';

                        $requestData = [
                            'workerId' => $workerId,
                            // 'externalWorkerId' => $data['externalWorkerId'],
                            'type' => $earningType,
                            'grossAmount' => [
                                'amount' => $hourly_salary,
                                'currency' => 'USD',
                            ],
                            'unitRate' => [
                                'amount' => $pay_rate,
                                'currency' => 'USD',
                            ],
                            'unitCount' => '40.0',
                            'referenceDate' => $date,
                            // 'workLocationId' => 3005,
                            'workLocationId' => $workLocationId,
                            'externalId' => $external_id,
                        ];

                        $S_untracked = $this->create_gross_earning_data($requestData, $user_id);

                    }

                    if ($data['reimbursement'] > 0) {

                        $external_id = 'R-'.$data['usersdata']['employee_id'].'-'.$data->id;
                        $reimbursement = $data['reimbursement'];
                        $earningType = 'REIMBURSEMENT';

                        $requestData = [
                            'workerId' => $workerId,
                            // 'externalWorkerId' => $data['externalWorkerId'],
                            'type' => $earningType,
                            'grossAmount' => [
                                'amount' => $reimbursement,
                                'currency' => 'USD',
                            ],
                            'referenceDate' => $date,
                            // 'workLocationId' => 3005,
                            'workLocationId' => $workLocationId,
                            'externalId' => $external_id,
                        ];

                        $R_untracked = $this->create_gross_earning_data($requestData, $user_id);

                    }

                    if ($data['net_pay'] > 0) {

                        $reimbursement = ($data['reimbursement'] > 0) ? $data['reimbursement'] : 0;
                        $hourlySalary = ($data['hourly_salary'] > 0) ? $data['hourly_salary'] : 0;

                        $net_pay = ($data['net_pay'] - $reimbursement - $hourlySalary);
                        $external_id = 'C-'.$data['usersdata']['employee_id'].'-'.$data->id;
                        $earningType = 'COMMISSION';

                        if ($net_pay > 0) {

                            $requestData = [
                                'workerId' => $workerId,
                                // 'externalWorkerId' => $data['externalWorkerId'],
                                'type' => $earningType,
                                'grossAmount' => [
                                    'amount' => $net_pay,
                                    'currency' => 'USD',
                                ],
                                'referenceDate' => $date,
                                // 'workLocationId' => 3005,
                                'workLocationId' => $workLocationId,
                                'externalId' => $external_id,
                            ];

                            $C_untracked = $this->create_gross_earning_data($requestData, $user_id);
                        }

                    }

                }

                $external_id = '';
                $enableEVE = 1;
            } else {
                // dd('check');
                $external_id = '';
                $enableEVE = 0;
            }

        });

        // $external_id = "ASHU234555";
        // $earningType = "REGULAR_SALARY";
        // $earning = $this->create_gross_earning_data($data,$external_id,$earningType);
    }
    // public function sendAttendanceData($d){
    //     // dd($d);
    //     foreach($d as $data){
    //         $attendance_details_obj = UserAttendanceDetail::where('user_attendance_id',$data->id)->get()->toArray();
    //         //dd($attendance_details_obj);
    //         $types = array_column($attendance_details_obj, 'type');
    //         $dates = array_column($attendance_details_obj, 'attendance_date');
    //         //dd($types, $dates);
    //         $payload = [];
    //         $findUser = User::find($data->user_id);
    //         $payload['user_id'] = $data->user_id;
    //         $payload['clockIn'] = $dates[array_search('clock in', $types)];
    //         $payload['clockOut'] = $dates[array_search('clock out', $types)];
    //         $payload['lunch'] = $dates[array_search('lunch', $types)];
    //         $payload['lunchEnd'] = $dates[array_search('end lunch', $types)];
    //         $payload['break'] = $dates[array_search('break', $types)];
    //         $payload['breakEnd'] = $dates[array_search('end break', $types)];
    //         $payload['workerId'] = !empty($findUser->everee_workerId) ? $findUser->everee_workerId :  null;
    //         $payload['externalWorkerId'] = !empty($findUser->employee_id) ? $findUser->employee_id : null;
    //         //dd($payload);
    //         $getResponse = $this->send_timesheet_data($payload);
    //         if(empty($data->everee_status) || is_null($data->everee_status)){
    //             $data->everee_status = $getResponse;
    //             $data->save();
    //         }
    //     }
    // }

    public function undoPayrollAApprovalRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
            'payroll_id' => 'required',
            'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 400);
        }
        $checkPayroll = Payroll::where([
            'pay_period_from' => $request->pay_period_from,
            'pay_period_to' => $request->pay_period_to,
            'status' => 2,
        ])->whereIn('finalize_status', [1, 2])->count();
        if ($checkPayroll > 0) {
            return response()->json([
                'ApiName' => 'undo_payroll_approval_request',
                'status' => false,
                'message' => 'You cannot undo this request because payroll has been finalized or is in the process of being finalized.',
            ], 400);
        }

        $checkReq = ApprovalsAndRequest::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
            $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($request) {
            $query->where('pay_period_from', $request->pay_period_from)
                ->where('pay_period_to', $request->pay_period_to);
        })->where(['id' => $request->request_id, 'payroll_id' => $request->payroll_id, 'status' => 'Accept'])->first();
        if (! $checkReq) {
            return response()->json([
                'ApiName' => 'undo_payroll_approval_request',
                'status' => false,
                'message' => 'request not found ',
            ], 400);
        }

        if ($checkReq->adjustment_type_id == 4 && $checkReq->req_no == null) {
            $adwance_setting = AdvancePaymentSetting::find(1);
            if ($adwance_setting->adwance_setting == 'automatic') {
                return response()->json([
                    'ApiName' => 'undo_payroll_approval_request',
                    'status' => false,
                    'message' => "You cannot undo the advance negative drawback request while the Advance Settings is 'Automatically deduct from the next available payroll'",
                ], 400);
            } else {
                $parent_id = ApprovalsAndRequest::where([
                    'id' => $request->request_id,
                    'pay_period_from' => $request->pay_period_from,
                    'pay_period_to' => $request->pay_period_to,
                    'payroll_id' => $request->payroll_id,
                ])->first();
                ApprovalsAndRequest::where('id', $parent_id->parent_id)->update([
                    'pay_period_from' => null,
                    'pay_period_to' => null,
                    'payroll_id' => 0,
                    'ref_id' => 0,
                    'is_next_payroll' => 0,
                    'is_mark_paid' => 0,
                    'status' => 'Approved',
                ]);
                ApprovalsAndRequest::where([
                    'id' => $request->request_id,
                    'pay_period_from' => $request->pay_period_from,
                    'pay_period_to' => $request->pay_period_to,
                    'payroll_id' => $request->payroll_id,
                ])->delete();
            }
        } else {
            ApprovalsAndRequest::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where('pay_period_from', $request->pay_period_from)
                    ->where('pay_period_to', $request->pay_period_to);
            })->where([
                'id' => $request->request_id,
                'payroll_id' => $request->payroll_id,
            ])->update([
                'pay_period_from' => null,
                'pay_period_to' => null,
                'payroll_id' => 0,
                'ref_id' => 0,
                'is_next_payroll' => 0,
                'is_mark_paid' => 0,
                'status' => 'Approved',
            ]);
        }

        return response()->json([
            'ApiName' => 'undo_payroll_approval_request',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function user_salary_create($start_date, $end_date, $workerType = null)
    {
        $workerType = isset($workerType) ? $workerType : '1099';
        $userWages = UserWagesHistory::with('user:id,pay_type,worker_type,sub_position_id,stop_payroll,dismiss,period_of_agreement_start_date')->where('effective_date', '<=', $start_date)->orderBy('effective_date', 'desc')->get()->unique('user_id');

        if (count($userWages) > 0) {
            foreach ($userWages as $key => $userWage) {
                if ($userWage->pay_type == 'Salary' && $end_date >= $userWage->user->period_of_agreement_start_date) {
                    $date = date('Y-m-d');
                    $subPositionId = $userWage->user->sub_position_id;
                    $stop_payroll = $userWage->user->stop_payroll;
                    $payFrequency = $this->payFrequencyNew($start_date, $subPositionId, $userWage->user->id);
                    if (strtolower($workerType) == 'w2') {
                        $close_status = $payFrequency->w2_closed_status;
                    } else {
                        $close_status = $payFrequency->closed_status;
                    }
                    if ($payFrequency && $close_status == 0) {
                        if ($payFrequency->pay_period_from == $start_date && $payFrequency->pay_period_to == $end_date) {
                            $payRate = isset($userWage->pay_rate) ? $userWage->pay_rate : 0;
                            $payrollHourlySalary = PayrollHourlySalary::where(['user_id' => $userWage->user->id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->where('status', '!=', 3)->first();
                            if ($payrollHourlySalary) {
                                $payrollHourlySalary->salary = $payRate;
                                $payrollHourlySalary->total = $payRate;
                                $payrollHourlySalary->save();
                            } else {
                                if ($payRate != 0) {
                                    $dataArray = [
                                        'user_id' => $userWage->user->id,
                                        'position_id' => $subPositionId,
                                        'date' => $date,
                                        'salary' => $payRate,
                                        'total' => $payRate,
                                        'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                                        'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                                        'is_stop_payroll' => $stop_payroll,
                                        'status' => 1,
                                    ];
                                    PayrollHourlySalary::create($dataArray);
                                    $payRoll = PayRoll::where(['user_id' => $userWage->user->id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->whereIn('status', [1, 2])->first();
                                    if ($payRoll) {
                                        $payRoll->hourly_salary = $payRate;
                                        $payRoll->save();
                                    } else {
                                        PayRoll::create([
                                            'user_id' => $userWage->user->id,
                                            'position_id' => $subPositionId,
                                            'hourly_salary' => $payRate,
                                            'pay_period_from' => $payFrequency->pay_period_from,
                                            'pay_period_to' => $payFrequency->pay_period_to,
                                            'status' => 1,
                                            'is_stop_payroll' => $userWage->user->stop_payroll ?? 0,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
