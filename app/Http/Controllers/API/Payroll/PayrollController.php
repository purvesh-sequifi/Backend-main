<?php

namespace App\Http\Controllers\API\Payroll;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Events\EvereeOnboardingUserEvent;
use App\Exports\ExportReconPayrollList;
use App\Exports\ExportReconPayrollListEmployee;
use App\Exports\PaymentHistoryExport;
use App\Http\Controllers\Controller;
use App\Jobs\executePayrollJob;
use App\Jobs\finalizePayrollJob;
use App\Models\AdditionalPayFrequency;
use App\Models\AdjustementType;
use App\Models\AdvancePaymentSetting;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\DeductionAlert;
use App\Models\FrequencyType;
use App\Models\GetPayrollData;
use App\Models\LegacyApiNullData;
use App\Models\Locations;
use App\Models\MonthlyPayFrequency;
use App\Models\Notification;
use App\Models\OneTimePayments;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollAlerts;
use App\Models\PayrollDeductions;
use App\Models\PayrollHistory;
use App\Models\PayrollShiftHistorie;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationsAdjustement;
use App\Models\ReconciliationStatusForSkipedUser;
use App\Models\ReconOverrideHistory;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationCommissionLock;
use App\Models\UserReconciliationCommissionWithholding;
use App\Models\UserReconciliationWithholding;
use App\Models\WeeklyPayFrequency;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Barryvdh\DomPDF\PDF;
use DateTime;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    use EmailNotificationTrait;
    use EvereeTrait;
    use PayFrequencyTrait;
    use PushNotificationTrait;

    public function __construct(Request $request)
    {
        // $user = auth('api')->user();
    }

    public function close_payroll(Request $request): JsonResponse
    {
        $validation = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
        ]);

        if ($validation->fails()) {
            return response()->json([
                'ApiName' => 'close_payroll',
                'status' => false,
                'error' => $validation->errors(),
            ], 400);
        }

        if (Payroll::where(['status' => 2, 'finalize_status' => 3])->first()) {
            return response()->json([
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'message' => 'Some users failed to sync with SequiPay, so you cannot execute the payroll!!',
            ], 400);
        }

        if ($payroll = Payroll::where(['status' => 3])->first()) {
            return response()->json([
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'message' => 'Payroll is being processed for the pay period from '.date('m/d/Y', strtotime($payroll->pay_period_from)).' to '.date('m/d/Y', strtotime($payroll->pay_period_to)),
            ], 400);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $payFrequency = $request->pay_frequency;
        $workerType = $request->worker_type ? $request->worker_type : '1099';

        $usersIds = User::where('worker_type', $workerType)->whereIn('sub_position_id', function ($query) use ($payFrequency) {
            $query->select('position_id')->from('position_pay_frequencies')->where('frequency_type_id', $payFrequency);
        })->pluck('id');

        $data = Payroll::with('usersdata')->where(function ($q) {
            $q->where('is_mark_paid', '1')->orWhere('is_next_payroll', '1');
        })->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'worker_type' => $workerType])->whereIn('user_id', $usersIds)->get();

        $payrollPaidCheck = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'worker_type' => $workerType])
            ->whereNotIn('id', function ($query) use ($startDate, $endDate, $workerType) {
                $query->select('id')->from('payrolls')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'worker_type' => $workerType, 'is_mark_paid' => 1]);
            })->whereIn('user_id', $usersIds)->count();
        if ($payrollPaidCheck > 0) {
            $payrollMoveToNextCheck = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'worker_type' => $workerType])
                ->whereNotIn('id', function ($query) use ($startDate, $endDate, $workerType) {
                    $query->select('id')->from('payrolls')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'worker_type' => $workerType, 'is_next_payroll' => 1]);
                })->whereIn('user_id', $usersIds)->count();
            if ($payrollMoveToNextCheck > 0) {
                return response()->json([
                    'ApiName' => 'close_payroll',
                    'status' => false,
                    'error' => 'The payroll cannot be close because there are pending records that need to be processed.',
                ], 400);
            }
        }

        $checkNextPayroll = Payroll::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->whereIn('user_id', $usersIds)->where(['is_next_payroll' => 1, 'worker_type' => $workerType])->first();
        $nextPeriod = $this->payFrequencyById($endDate, $payFrequency, $workerType);
        if ($checkNextPayroll && ! isset($nextPeriod->second_pay_period_from)) {
            return response()->json([
                'ApiName' => 'finalize_payroll',
                'status' => false,
                'error' => 'No next pay period available to move user to next pay period.',
            ], 400);
        }

        if (count($data) == 0) {
            if (date('Y-m-d', strtotime($endDate)) < date('Y-m-d', strtotime(now()))) {
                // CRITICAL: Use model instance save() to trigger observers (not mass update)
                if ($workerType == 'w2' || $workerType == 'W2') {
                    $weekly = WeeklyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($weekly) {
                        $weekly->w2_closed_status = 1;
                        $weekly->w2_open_status_from_bank = 0;
                        $weekly->save();
                    }
                    
                    $monthly = MonthlyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($monthly) {
                        $monthly->w2_closed_status = 1;
                        $monthly->w2_open_status_from_bank = 0;
                        $monthly->save();
                    }
                    
                    $additional = AdditionalPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($additional) {
                        $additional->w2_closed_status = 1;
                        $additional->w2_open_status_from_bank = 0;
                        $additional->save();
                    }
                } else {
                    $weekly = WeeklyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($weekly) {
                        $weekly->closed_status = 1;
                        $weekly->open_status_from_bank = 0;
                        $weekly->save();
                    }
                    
                    $monthly = MonthlyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($monthly) {
                        $monthly->closed_status = 1;
                        $monthly->open_status_from_bank = 0;
                        $monthly->save();
                    }
                    
                    $additional = AdditionalPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                    if ($additional) {
                        $additional->closed_status = 1;
                        $additional->open_status_from_bank = 0;
                        $additional->save();
                    }
                }

                return response()->json([
                    'ApiName' => 'close_payroll',
                    'status' => true,
                    'message' => 'Successfully.',
                ]);
            } else {
                return response()->json([
                    'ApiName' => 'close_payroll',
                    'status' => false,
                    'message' => 'You can not close the future or current payroll.',
                ], 400);
            }
        }

        try {
            DB::beginTransaction();
            $newFromDate = $nextPeriod->second_pay_period_from;
            $newToDate = $nextPeriod->second_pay_period_to;
            $payrollSingleController = new PayrollSingleController;
            $payrollSingleController->movePayrollData($startDate, $endDate, $newFromDate, $newToDate, $workerType, $payFrequency);
            $data = Payroll::with('usersdata')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '1', 'worker_type' => $workerType])->whereIn('user_id', $usersIds)->get();
            $data->transform(function ($data) use ($startDate, $endDate, $payFrequency) {
                if ($data->is_mark_paid == '1') {
                    $create = [
                        'payroll_id' => $data->id,
                        'user_id' => $data->user_id,
                        'position_id' => $data->position_id,
                        'everee_status' => 0,
                        'commission' => $data->commission,
                        'override' => $data->override,
                        'reimbursement' => $data->reimbursement,
                        'clawback' => $data->clawback,
                        'deduction' => $data->deduction,
                        'adjustment' => $data->adjustment,
                        'reconciliation' => $data->reconciliation,
                        'net_pay' => $data->net_pay,
                        'pay_period_from' => $data->pay_period_from,
                        'pay_period_to' => $data->pay_period_to,
                        'status' => '3',
                        'pay_type' => 'Manualy',
                        'pay_frequency_date' => $data->created_at,
                        'everee_payment_status' => 0,
                    ];
                    $insert = PayrollHistory::create($create);
                    UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->update(['status' => 3]);
                    UserOverrides::where(['user_id' => $data->user_id, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->update(['status' => 3]);
                    ClawbackSettlement::where(['user_id' => $data->user_id, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->update(['status' => 3]);
                    UserReconciliationCommission::where('user_id', $data->user_id)->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'payroll_id' => $data->id])->update(['status' => 'paid']);
                    ApprovalsAndRequest::where('status', 'Accept')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->update(['status' => 'Paid']);
                    PayrollAdjustment::where('user_id', $data->user_id)->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'payroll_id' => $data->id])->update(['status' => 3]);
                    PayrollAdjustmentDetail::where('user_id', $data->user_id)->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'payroll_id' => $data->id])->update(['status' => 3]);

                    Payroll::where(['id' => $data->id, 'is_mark_paid' => '1'])->delete();
                    $requestAndApprovals = ApprovalsAndRequest::where('status', 'Paid')->where('payroll_id', $data->id)->get();
                    foreach ($requestAndApprovals as $requestAndApproval) {
                        $childRequest = ApprovalsAndRequest::where('parent_id', $requestAndApproval->parent_id)->where('status', 'Paid')->sum('amount');
                        $parentRequest = ApprovalsAndRequest::where('id', $requestAndApproval->parent_id)->where('status', 'Accept')->sum('amount');
                        if ($childRequest == $parentRequest) {
                            ApprovalsAndRequest::where('id', $requestAndApproval->parent_id)->update(['status' => 'Paid']);
                        }
                    }

                    $payrollAdjustmentData = PayrollAdjustment::where([
                        'user_id' => $data->user_id,
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                        'payroll_id' => $data->id,
                        'status' => 3,
                    ])->get();
                    foreach ($payrollAdjustmentData->toArray() as $value) {
                        PayrollAdjustmentLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }

                    $payrollAdjustmentDetailData = PayrollAdjustmentDetail::where([
                        'user_id' => $data->user_id,
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                        'payroll_id' => $data->id,
                        'status' => 3,
                    ])->get();
                    foreach ($payrollAdjustmentDetailData->toArray() as $value) {
                        PayrollAdjustmentDetailLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }

                    $userReconciliationCommissionData = UserReconciliationCommission::where([
                        'user_id' => $data->user_id,
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                        'payroll_id' => $data->id,
                        'status' => 'paid',
                    ])->get();
                    foreach ($userReconciliationCommissionData->toArray() as $value) {
                        UserReconciliationCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }

                    $userCommissionData = UserCommission::where([
                        'user_id' => $data->user_id,
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                        'payroll_id' => $data->id,
                        'status' => 3,
                    ])->get();
                    foreach ($userCommissionData->toArray() as $value) {
                        UserCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }

                    $userOverridesData = UserOverrides::where([
                        'user_id' => $data->user_id,
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                        'payroll_id' => $data->id,
                        'status' => 3,
                    ])->get();
                    foreach ($userOverridesData->toArray() as $value) {
                        UserOverridesLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }

                    $clawBackSettlementData = ClawbackSettlement::where([
                        'user_id' => $data->user_id,
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                        'payroll_id' => $data->id,
                        'status' => 3,
                    ])->get();
                    foreach ($clawBackSettlementData->toArray() as $value) {
                        ClawbackSettlementLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }

                    $approvalsAndRequestData = ApprovalsAndRequest::where([
                        'user_id' => $data->user_id,
                        'status' => 'Paid',
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                        'payroll_id' => $data->id,
                    ])->get();
                    foreach ($approvalsAndRequestData->toArray() as $value) {
                        ApprovalsAndRequestLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }

                    $customFieldRecords = \App\Models\CustomField::where([
                        'user_id' => $data->user_id,
                        'payroll_id' => $data->id,
                        'is_next_payroll' => 0,
                    ])->get();
                    foreach ($customFieldRecords->toArray() as $value) {
                        $customFieldHistory = \App\Models\CustomFieldHistory::where(['payroll_id' => $value['payroll_id'], 'user_id' => $value['user_id'], 'column_id' => $value['column_id']])->first();
                        if (! $customFieldHistory) {
                            $customFieldHistory = new \App\Models\CustomFieldHistory;
                        }
                        $customFieldHistory->user_id = $value['user_id'];
                        $customFieldHistory->payroll_id = $value['payroll_id'];
                        $customFieldHistory->column_id = $value['column_id'];
                        $customFieldHistory->value = $value['value'];
                        $customFieldHistory->comment = $value['comment'];
                        $customFieldHistory->approved_by = $value['approved_by'];
                        $customFieldHistory->is_mark_paid = $value['is_mark_paid'];
                        $customFieldHistory->is_next_payroll = $value['is_next_payroll'];
                        $customFieldHistory->pay_period_from = $value['pay_period_from'];
                        $customFieldHistory->pay_period_to = $value['pay_period_to'];
                        if ($customFieldHistory->save()) {
                            $customField = \App\Models\CustomField::find($value['id']);
                            $customField->delete();
                        }
                    }

                    $startDateNext = null;
                    $endDateNext = null;
                    $advanceRequestStatus = 'Approved';
                    $advanceSetting = AdvancePaymentSetting::first();
                    if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                        $payFrequency = $this->payFrequencyNew($startDate, $data->usersdata->sub_position_id, $data->usersdata->id);
                        $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                        $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                        $advanceRequestStatus = 'Accept';
                    }
                    $adjustmentTotal = 0;
                    $addApprovalsAndRequestIds = [];
                    if ($data->is_mark_paid == 0 && $data->is_next_payroll == 0) {
                        $approvalAndRequestData = ApprovalsAndRequest::where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $data->user_id, 'status' => 'Paid', 'adjustment_type_id' => 4, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
                        foreach ($approvalAndRequestData as $approvalAndRequest) {
                            $addApprovalsAndRequest = ApprovalsAndRequest::create([
                                'payroll_id' => $insert->payroll_id,
                                'parent_id' => $approvalAndRequest->id,
                                'user_id' => $approvalAndRequest->user_id,
                                'manager_id' => $approvalAndRequest->manager_id,
                                'approved_by' => $approvalAndRequest->approved_by,
                                'adjustment_type_id' => $approvalAndRequest->adjustment_type_id,
                                'state_id' => $approvalAndRequest->state_id,
                                'dispute_type' => $approvalAndRequest->dispute_type,
                                'customer_pid' => $approvalAndRequest->customer_pid,
                                'cost_tracking_id' => $approvalAndRequest->cost_tracking_id,
                                'cost_date' => $approvalAndRequest->cost_date,
                                'request_date' => $approvalAndRequest->request_date,
                                'amount' => (0 - $approvalAndRequest->amount),
                                'status' => $advanceRequestStatus,
                                'pay_period_from' => isset($startDateNext) ? $startDateNext : null,
                                'pay_period_to' => isset($endDateNext) ? $endDateNext : null,
                            ]);
                            $addApprovalsAndRequestIds[] = $addApprovalsAndRequest->id;
                            $adjustmentTotal += $approvalAndRequest->amount;
                        }
                        if ($adjustmentTotal > 0 && $advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                            $payroll_id = updateExistingPayroll($data->user_id, $startDateNext, $endDateNext, $adjustmentTotal, 'adjustment', $data->position_id, 0);
                            ApprovalsAndRequest::whereIn('id', $addApprovalsAndRequestIds)->update(['payroll_id' => $payroll_id]);
                        }
                    }

                    create_paystub_employee([
                        'user_id' => $data->user_id,
                        'pay_period_from' => $startDate,
                        'pay_period_to' => $endDate,
                    ]);
                }
            });
            // CRITICAL: Use model instance save() to trigger observers (not mass update)
            if ($workerType == 'w2' || $workerType == 'W2') {
                $weekly = WeeklyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                if ($weekly) {
                    $weekly->w2_closed_status = 1;
                    $weekly->w2_open_status_from_bank = 0;
                    $weekly->save();
                }
                
                $monthly = MonthlyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                if ($monthly) {
                    $monthly->w2_closed_status = 1;
                    $monthly->w2_open_status_from_bank = 0;
                    $monthly->save();
                }
                
                $additional = AdditionalPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                if ($additional) {
                    $additional->w2_closed_status = 1;
                    $additional->w2_open_status_from_bank = 0;
                    $additional->save();
                }
            } else {
                $weekly = WeeklyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                if ($weekly) {
                    $weekly->closed_status = 1;
                    $weekly->open_status_from_bank = 0;
                    $weekly->save();
                }
                
                $monthly = MonthlyPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                if ($monthly) {
                    $monthly->closed_status = 1;
                    $monthly->open_status_from_bank = 0;
                    $monthly->save();
                }
                
                $additional = AdditionalPayFrequency::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
                if ($additional) {
                    $additional->closed_status = 1;
                    $additional->open_status_from_bank = 0;
                    $additional->save();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'close_payroll',
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 400);
        }

        return response()->json([
            'ApiName' => 'close_payroll',
            'status' => true,
            'message' => 'Successfully.',
        ]);
    }

    public function getPayrollData(Request $request)
    {
        $data = [];
        $payroll_total = 0;
        $positions = $request->input('position_filter');
        $netPay = $request->input('netpay_filter');
        $commission = $request->input('commission_filter');
        $pay_frequency = $request->input('pay_frequency');
        if (! empty($request->input('perpage'))) {
            $perpage = $request->input('perpage');
        } else {
            $perpage = 10;
        }
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $all_paid = false;
        $data_query = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date]);
        if ($pay_frequency == 2) {
            $weekly = WeeklyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('closed_status', 1)->first();
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
            }
        } elseif ($pay_frequency == FrequencyType::BI_WEEKLY_ID) {
            $biWeekly = AdditionalPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE])->where('closed_status', 1)->first();
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
            }
        } elseif ($pay_frequency == FrequencyType::SEMI_MONTHLY_ID) {
            $semiWeekly = AdditionalPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE])->where('closed_status', 1)->first();
            if ($semiWeekly) {
                return response()->json([
                    'ApiName' => 'get_payroll_data',
                    'status' => true,
                    'message' => 'This payroll is already closed.',
                    'finalize_status' => null,
                    'data' => [],
                    'all_paid' => false,
                    'total_alert_count' => 0,
                ], 200);
            }
        } else {
            $monthly = MonthlyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('closed_status', 1)->first();
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
            }
        }

        $count_data = $data_query->count();
        if ($count_data > 0) {
            $all_paid_count = $data_query->where('is_mark_paid', '0')->count();
            if ($all_paid_count == 0) {
                $all_paid = true;
            }
        }
        $fullName = $request->input('search');
        $search_full_name = removeMultiSpace($fullName);
        // $users = User::with('payroll')->where('is_super_admin','!=','1')->orderBy('id', 'asc');
        $users = User::orderBy('id', 'asc');

        if ($request->has('search') && ! empty($request->input('search'))) {
            $users->where(function ($query) use ($search_full_name) {
                return $query->where(DB::raw("concat(first_name, ' ', last_name)"), 'LIKE', '%'.$search_full_name.'%')
                    ->orWhere('first_name', 'LIKE', '%'.$search_full_name.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search_full_name.'%');
            });
        }

        $userArray = $users->pluck('id')->toArray();
        if ($start_date && $end_date) {
            $paydata = Payroll::with('usersdata', 'payrollstatus', 'userDeduction', 'reconciliationInfo')
                ->with(['positionCommissionDeduction' => function ($q) {
                    $q->join('cost_centers', 'cost_centers.id', '=', 'position_commission_deductions.cost_center_id');
                }])->whereIn('user_id', $userArray)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date]);

            if ($positions && $positions != '') {
                $paydata->where('position_id', $positions);
            }

            if ($netPay && $netPay != '' && $netPay == 'negative_amount') {
                $paydata->where('net_pay', '<', 0);
            }

            if ($commission && $commission != '') {
                $paydata->where('commission', $commission);
            }

            $paydata = $paydata->get();
            if (count($paydata) > 0) {
                foreach ($paydata as $key => $data) {
                    try {
                        DB::beginTransaction();
                        if ($data->is_mark_paid == 0 && $data->is_next_payroll == 0) {
                            $usercommissions = UserCommission::where(['payroll_id' => 0, 'user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])
                                ->update(['payroll_id' => $data->id]);
                            $overrides = UserOverrides::where(['payroll_id' => 0, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])
                                ->update(['payroll_id' => $data->id]);
                            $clawbackSum = ClawbackSettlement::where(['payroll_id' => 0, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])
                                ->update(['payroll_id' => $data->id]);
                            $reimbursement = ApprovalsAndRequest::where(['payroll_id' => 0, 'user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])
                                ->update(['payroll_id' => $data->id]);
                        }
                        // $aadjustment = PayrollAdjustment::where('payroll_id', $data->id)->sum(\DB::raw('commission_amount + overrides_amount + adjustments_amount + reimbursements_amount +  deductions_amount + clawbacks_amount + reconciliations_amount'));
                        $comm_over_dedu_aadjustment = PayrollAdjustment::where('payroll_id', $data->id)->sum(\DB::raw('commission_amount + overrides_amount + deductions_amount'));
                        $reim_claw_recon_aadjustment = PayrollAdjustment::where('payroll_id', $data->id)->sum(\DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));
                        // changes due to MoveToNextPayroll
                        $usercommissions = UserCommission::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => $data->is_next_payroll, 'user_id' => $data->user_id, 'is_mark_paid' => $data->is_mark_paid, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                        $clawbackSum = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => $data->is_next_payroll, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'is_mark_paid' => $data->is_mark_paid, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('type', '!=', 'overrides')->sum('clawback_amount');
                        $overrides = UserOverrides::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => $data->is_next_payroll, 'user_id' => $data->user_id, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => $data->is_mark_paid, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                        $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => $data->is_next_payroll, 'user_id' => $data->user_id, 'adjustment_type_id' => '2', 'is_mark_paid' => $data->is_mark_paid, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->sum('amount');
                        $adjustmentToAdd = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => $data->is_next_payroll, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_mark_paid' => $data->is_mark_paid, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
                        $adjustmentToNigative = ApprovalsAndRequest::where('status', 'Accept')->where(['payroll_id' => $data->id, 'is_next_payroll' => $data->is_next_payroll, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'is_mark_paid' => $data->is_mark_paid, 'user_id' => $data->user_id])->whereIn('adjustment_type_id', [5])->sum('amount');

                        $clawbackSumChange = ClawbackSettlement::whereIn('status', [1, 6])->where(['payroll_id' => $data->id, 'is_next_payroll' => $data->is_next_payroll, 'user_id' => $data->user_id, 'clawback_type' => 'next payroll', 'is_mark_paid' => $data->is_mark_paid, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->where('type', 'overrides')->sum('clawback_amount');
                        $clawbackSumChange = (0 - $clawbackSumChange);
                        $overrides = round($overrides + $clawbackSumChange, 2, PHP_ROUND_HALF_EVEN);
                        $adjustment = round(($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment), 2, PHP_ROUND_HALF_EVEN);
                        $usercommission = round($usercommissions - $clawbackSum, 2, PHP_ROUND_HALF_EVEN);

                        $net_pay = round($usercommission + $overrides + $adjustment + $reimbursement + $data->reconciliation, 2, PHP_ROUND_HALF_EVEN);
                        $updateData = [
                            'commission' => $usercommission,
                            'override' => $overrides,
                            'reimbursement' => $reimbursement,
                            'adjustment' => $adjustment, // + $comm_over_dedu_aadjustment,
                            'net_pay' => $net_pay,  // + $comm_over_dedu_aadjustment
                        ];
                        // $payroll = Payroll::where(['user_id'=>$data->user_id,'id'=>$data->id])->where('is_mark_paid',0)->where('status',1)->update($updateData);
                        $payroll = Payroll::where(['user_id' => $data->user_id, 'id' => $data->id])->update($updateData);
                        DB::commit();
                    } catch (Exception $e) {
                        DB::rollBack();

                        return $e->getMessage();
                    }
                }
            }
        }

        // plz dont delete this function
        // $this->deduction_for_all_deduction_enable_users($start_date,$end_date,$pay_frequency);

        // echo $start_date.'---'.$end_date; die;
        if ($start_date && $end_date) {
            // $data = Payroll::with('usersdata', 'positionDetail', 'payrollstatus', 'payrolladjust')->whereIn('user_id', $userArray)->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->where('id','!=',0)->paginate(config('app.paginate', 15));
            $data_query = Payroll::with('usersdata', 'positionDetail', 'payrollstatus', 'payrolladjust', 'PayrollShiftHistorie', 'approvalRequest', 'reconciliationInfo')->whereIn('user_id', $userArray)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->where('id', '!=', 0);
            $payroll_total = payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_stop_payroll' => 0])->sum('net_pay');
            $checkFinalize = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '<', 2)->count();
            $table = 'payrolls';

            // ///// ---------- Everee Paymant faild then get data direct from payroll_history
            // return $request['everee_failed_status'];
            // if($request['everee_failed_status']==1)
            // {
            //     $data_query = PayrollHistory::with('usersdata', 'positionDetail', 'payrollstatus')->whereIn('user_id', $userArray)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('id', '!=', 0)->where('everee_payment_status',2);

            //     $checkFinalize = PayrollHistory::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->where('status','<',2)->count();
            //     $table = 'payroll_history';
            // }

            if ($checkFinalize == 0) {
                $checkFinalizeStatus = 1;
            } else {
                $checkFinalizeStatus = 0;
            }

        } else {
            // $data = Payroll::with('usersdata', 'positionDetail', 'payrollstatus')->whereIn('user_id', $userArray)->where('id','!=',0)->paginate(config('app.paginate', 15));
            $data_query = Payroll::with('usersdata', 'positionDetail', 'payrollstatus', 'PayrollShiftHistorie', 'approvalRequest', 'reconciliationInfo')->whereIn('user_id', $userArray)->where('id', '!=', 0);
            $table = 'payrolls';

            // ///// ---------- Everee Paymant faild then get data direct from payroll_history

            // if($request['everee_failed_status']==1)
            // {
            //     $data_query = PayrollHistory::with('usersdata', 'positionDetail', 'payrollstatus')->whereIn('user_id', $userArray)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('id', '!=', 0)->where('everee_payment_status',2);
            //     $table = 'payroll_history';
            // }
            // $data_query = Payroll::with('usersdata', 'positionDetail', 'payrollstatus' , 'PayrollShiftHistorie','approvalRequest','reconciliationInfo')->whereIn('user_id', $userArray)->where('id','!=',0);
        }

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
                ->whereColumn('id', $table.'.user_id')
                ->orderBy('first_name', 'asc')
                ->limit(1),
            'ASC'
        );

        // && $request['everee_failed_status']!=1
        if (isset($request->is_reconciliation) && $request->is_reconciliation == 1) {
            $positionArray = PositionReconciliations::where('status', 1)->pluck('position_id')->toArray();
            $data_query->whereIn('position_id', $positionArray);
        }
        // return $data_query->get();
        $data = $data_query->paginate($perpage);
        // echo "<pre>";   print_r($data->toArray()); die;

        $uId = $data->pluck('user_id')->toArray();

        // return count($data);

        if (count($data) > 0) {

            $data->transform(function ($data) use ($userArray) {
                if (in_array($data->user_id, $userArray)) {

                    // $approvalsAndRequestCheck = ApprovalsAndRequest::where(['user_id' => $data->user_id])->whereIn('status', ['Approved'])->first();
                    // if($approvalsAndRequestCheck){
                    //     $approvalsAndRequestStatus =1;
                    // }else{
                    //     $approvalsAndRequestStatus =0;
                    // }

                    if (isset($data->usersdata->image) && $data->usersdata->image != null) {
                        $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image);
                    } else {
                        $s3_image = null;
                    }

                    return [
                        'payroll_id' => $data->id,
                        'id' => $data->user_id,
                        'approvals_and_requests_status' => (empty($data->approvalRequest)) ? 0 : 1,  // $approvalsAndRequestStatus,
                        'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                        'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                        'position_id' => isset($data->usersdata) ? $data->usersdata->position_id : null,
                        'sub_position_id' => isset($data->usersdata) ? $data->usersdata->sub_position_id : null,
                        'is_super_admin' => isset($data->usersdata) ? $data->usersdata->is_super_admin : null,
                        'is_manager' => isset($data->usersdata) ? $data->usersdata->is_manager : null,
                        'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                        'image_s3' => $s3_image,
                        'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                        'commission' => $data->commission,
                        'override' => $data->override,
                        'override_value_is_higher' => 0,
                        'adjustment' => $data->adjustment,
                        'reimbursement' => $data->reimbursement,
                        'clawback' => $data->clawback,
                        'deduction' => $data->deduction,
                        'reconciliation' => isset($data->reconciliation) ? $data->reconciliation : 0,
                        'net_pay' => round($data->net_pay, 2),
                        'comment' => isset($data->payrolladjust) ? $data->payrolladjust->comment : null,
                        'status_id' => $data->status,
                        'status' => isset($data->payrollstatus) ? $data->payrollstatus->status : null,
                        'is_mark_paid' => isset($data->is_mark_paid) ? $data->is_mark_paid : 0,
                        'is_next_payroll' => isset($data->is_next_payroll) ? $data->is_next_payroll : 0,
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                        'PayrollShiftHistorie_count' => isset($data->PayrollShiftHistorie) ? count($data->PayrollShiftHistorie) : '',
                    ];
                }
            });
        }

        $saleCount = LegacyApiNullData::whereBetween('m1_date', [$start_date, $end_date])->orWhereBetween('m2_date', [$start_date, $end_date])->whereNotNull('data_source_type')->count();
        $peopleCount = User::whereIn('id', $uId)->where('tax_information', null)->where('name_of_bank', null)->count();
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();

        // return $data;

        // echo $request['everee_failed_status']; die;
        if ($CrmData) {// && $request['everee_failed_status']!=1
            $paydata = Payroll::with('usersdata')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
            if (count($paydata) > 0) {
                $add = event(new EvereeOnboardingUserEvent($paydata, $payroll = '1'));
            }
            // return $add;
        }
        // echo "<pre>"; print_r($data->toArray()); die;

        $total_alert_count_query = LegacyApiNullData::select(
            \DB::raw('count(`sales_alert`) as sales_alert'),
            \DB::raw('count(`missingrep_alert`) as missingrep'),
            \DB::raw('count(`closedpayroll_alert`) as closedpayroll'),
            \DB::raw('count(`locationredline_alert`) as locationredline'),
            \DB::raw('count(`repredline_alert`) as repredline')
        );
        $total_alert_count_query->where(function ($query) use ($request) {
            return $query->whereRaw("`m1_date` >= '".$request->start_date."' AND `m1_date` <= '".$request->end_date."'");
        });
        $total_alert_count_query->orWhere(function ($query) use ($request) {
            return $query->orWhereRaw("`m2_date` >= '".$request->start_date."' AND `m2_date` <= '".$request->end_date."'");
        });

        $total_alert_count = $total_alert_count_query->whereNotNull('data_source_type')->first();

        payroll::where('status', 2)->where('finalize_status', '!=', 2)->update(['finalize_status' => 2]);
        payroll::where('status', 1)->where('finalize_status', 2)->update(['finalize_status' => 0]);

        return response()->json([
            'ApiName' => 'get_payroll_data',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => isset($checkFinalizeStatus) ? $checkFinalizeStatus : null,
            'data' => $data,
            'all_paid' => $all_paid,
            'payroll_total' => round($payroll_total, 2),
            // 'sale_count'=>isset($saleCount) ? $saleCount:0,
            // 'people_count'=>isset($peopleCount) ? $peopleCount:0,
            'total_alert_count' => isset($total_alert_count) ? $total_alert_count : 0,
        ], 200);

    }

    public function runPayrollReconciliationPopUp(Request $request): JsonResponse
    {
        $datas = ReconciliationFinalizeHistory::where('payroll_id', $request->payroll_id)->where('user_id', $request->user_id)->get();
        $myArray = [];
        if (isset($datas) && $datas != '[]') {

            foreach ($datas as $data) {
                $commission = $data->commission;
                $payout = $data->payout;
                $override = $data->override;
                $totalCommission = ($commission * $payout) / 100;
                $totalOverride = ($override * $payout) / 100;
                $clawback = $data->clawback;
                $adjustments = $data->adjustments;
                $total = $data->net_amount;

                $myArray[] = [
                    'added_to_payroll_on' => Carbon::parse($data->updated_at)->format('m-d-Y'),
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
            'ApiName' => 'payroll in reconcitation popup  api ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $myArray,
        ], 200);

    }

    public function exportReconciliationPayrollHistoriesList(Request $request)
    {
        $data = [];

        $all_paid = true;
        $file_name = 'reportReconpayrollList_'.date('Y_m_d_H_i_s').'.csv';

        $executedOn = $request->input('executed_on');

        return Excel::download(new ExportReconPayrollList($executedOn), $file_name);

    }

    public function getPayrollDetailsById($id)
    {
        $result = [];
        $data = Payroll::with('usersdata', 'positionDetail')->where('status', 1)->where('id', $id)->first();
        // return $data;
        if ($data) {
            // changes due to MoveToNextPayroll
            $overrides = UserOverrides::where(['user_id' => $data->user_id])->whereIn('status', ['1', '4'])->sum('amount');
            $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['user_id' => $data->user_id, 'adjustment_type_id' => '2'])->sum('amount');
            $adjustment = ApprovalsAndRequest::where('status', 'Accept')->where('user_id', $data->user_id)->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->sum('amount');
            $reconciliation = UserReconciliationWithholding::where('status', 'unpaid')->where('closer_id', $data->user_id)->orWhere('setter_id', $data->user_id)->sum('withhold_amount');
            $net_pay = (($data->commission + $overrides + $adjustment + $reimbursement + $reconciliation + $data->clawback) - ($data->deduction));
            $result = [
                'id' => $data->id,
                'user_id' => $data->user_id,
                'first_name' => isset($data->usersdata) ? $data->usersdata->first_name : null,
                'last_name' => isset($data->usersdata) ? $data->usersdata->last_name : null,
                'image' => isset($data->usersdata) ? $data->usersdata->image : null,
                'position' => isset($data->positionDetail) ? $data->positionDetail->position_name : null,
                'commission' => $data->commission,
                'override' => $overrides,
                'override_value_is_higher' => 0,
                'adjustment' => $adjustment,
                'reimbursement' => $reimbursement,
                'clawback' => $data->clawback,
                'deduction' => $data->deduction,
                'reconciliation' => $reconciliation,
                'net_pay' => round($net_pay, 2),
                'comment' => $data->comment,
                'status' => $data->status,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ];

        }

        return response()->json([
            'ApiName' => 'payroll_details_by_id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ], 200);

    }

    public function updatePayroll(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        // return $request;
        $data = [];

        $id = $request->payroll_id;
        $payroll = Payroll::where('id', $id)->first();
        if ($request->status == 'skipped') {
            $status = 5;
        }
        if ($request->status == 'next_payroll') {
            $status = 4;

        }
        if ($request->status == 'resume') {
            $status = 1;

        }
        if ($request->status == 'update') {
            $status = 1;

        }
        $data = [
            'commission' => $request->commission,
            'override' => $request->override,
            'reimbursement' => $request->reimbursement,
            'deduction' => $request->deduction,
            'clawback' => $request->clawback,
            'adjustment' => $request->adjustment,
            'reconciliation' => $request->reconciliation,
            'comment' => $request->comment,
            'status' => isset($status) ? $status : $payroll->status,
        ];

        $payroll = Payroll::where('id', $id)->update($data);

        return response()->json([
            'ApiName' => 'update_payroll',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    // public function overrideDetails($id)
    // {
    //     $data = array();
    //     $Payroll = Payroll::where('id', $id)->first();
    //     // $userdata = UserOverrides::where('status',$Payroll->status)->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();

    //     if($Payroll->status==3){
    //         $userdata = UserOverrides::where('status',$Payroll->status)->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();
    //     }else{
    //         $userdata = UserOverrides::where('status','<','3')->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();
    //     }
    //     // $userdata = UserOverrides::where('status',$Payroll->status)->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();

    //     $sub_total = 0;
    //     if($Payroll){
    //         if (count($userdata) > 0) {

    //             foreach ($userdata as $key => $value) {

    //                 $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id'=> $id, 'user_id'=> $Payroll->user_id, 'pid'=> $value->pid, 'payroll_type' =>'overrides', 'type'=> $value->type])->first();

    //                 $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
    //                 $sale = SalesMaster::where(['pid' => $value->pid])->first();
    //                 $sub_total = ($sub_total + $value->amount);
    //                 $data['data'][] = [
    //                     'id' => $value->sale_user_id,
    //                     'pid' => $value->pid,
    //                     'first_name' => isset($user->first_name) ? $user->first_name : null,
    //                     'last_name' => isset($user->last_name) ? $user->last_name : null,
    //                     'image' => isset($user->image) ? $user->image : null,
    //                     'type' => isset($value->type) ? $value->type : null,
    //                     'accounts' => 1,
    //                     'kw_installed' => $value->kw,
    //                     'total_amount' => $value->amount,
    //                     'override_type' => $value->overrides_type,
    //                     'override_amount' => $value->overrides_amount,
    //                     'calculated_redline' => $value->calculated_redline,
    //                     'state' => isset($user->state) ? $user->state->state_code : null,
    //                     'm2_date' => isset($sale->m2_date) ? $sale->m2_date : null,
    //                     'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
    //                     'override_adjustment'=>isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,

    //                 ];
    //             }
    //             $data['sub_total'] = $sub_total;
    //         }

    //         return response()->json([
    //             'ApiName' => 'override_details',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'payroll_status' => $Payroll->status,
    //             'data' => $data,
    //         ], 200);

    //     }else{
    //         return response()->json([
    //             'ApiName' => 'override_details',
    //             'status' => true,
    //             'message' => 'No Records.',
    //             'data' => [],
    //         ], 400);
    //     }
    // }

    // overrideDetails for post request
    public function overrideDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $payroll_id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $sub_total = 0;

        if (! empty($Payroll)) {
            $PayrollCheck = Payroll::where(['id' => $id, 'user_id' => $user_id])->first();
            // $PayrollCheck = Payroll::where(['id' => $id, 'user_id' => $user_id, 'is_mark_paid'=> 1])->first();
            // if ($PayrollCheck) {
            //     $isMarkAsPaid = 1;
            // }else{
            //     $isMarkAsPaid = 0;
            // }
            $isMarkAsPaid = isset($PayrollCheck->isMarkAsPaid) ? $PayrollCheck->isMarkAsPaid : 0;

            $isNextPayroll = isset($PayrollCheck->is_next_payroll) ? $PayrollCheck->is_next_payroll : 0;

            if ($Payroll->status == 3) {
                $userdata = UserOverrides::where('status', $Payroll->status)
                    ->where('is_mark_paid', $isMarkAsPaid)
                    ->where('is_next_payroll', $isNextPayroll)
                    ->where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $Payroll->user_id,
                        'overrides_settlement_type' => 'during_m2',
                        'pay_period_from' => $Payroll->pay_period_from,
                        'pay_period_to' => $Payroll->pay_period_to,
                    ])
                    ->get();
            } else {
                // $userdata = UserOverrides::where('status','<','3')
                $userdata = UserOverrides::whereIn('status', [1, 2, 6])
                    ->where('is_next_payroll', $isNextPayroll)
                    ->where('is_mark_paid', $isMarkAsPaid)
                    ->where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $Payroll->user_id,
                        'overrides_settlement_type' => 'during_m2',
                        'pay_period_from' => $Payroll->pay_period_from,
                        'pay_period_to' => $Payroll->pay_period_to,
                    ])
                    ->get();
            }
            if (count($userdata) > 0) {

                foreach ($userdata as $key => $value) {

                    $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();

                    $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                    $sale = SalesMaster::where(['pid' => $value->pid])->first();
                    $sub_total = ($sub_total + $value->amount);
                    $data['data'][] = [
                        'id' => $value->sale_user_id,
                        'pid' => $value->pid,
                        'first_name' => isset($user->first_name) ? $user->first_name : null,
                        'last_name' => isset($user->last_name) ? $user->last_name : null,
                        'position_id' => isset($user->position_id) ? $user->position_id : null,
                        'sub_position_id' => isset($user->sub_position_id) ? $user->sub_position_id : null,
                        'is_super_admin' => isset($user->is_super_admin) ? $user->is_super_admin : null,
                        'is_manager' => isset($user->is_manager) ? $user->is_manager : null,
                        'image' => isset($user->image) ? $user->image : null,
                        'type' => isset($value->type) ? $value->type : null,
                        'accounts' => 1,
                        'kw_installed' => $value->kw,
                        'total_amount' => $value->amount,
                        'override_type' => $value->overrides_type,
                        'override_amount' => $value->overrides_amount,
                        'calculated_redline' => $value->calculated_redline,
                        'state' => isset($user->state) ? $user->state->state_code : null,
                        'm2_date' => isset($sale->m2_date) ? $sale->m2_date : null,
                        'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
                        'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    ];
                }
                $data['sub_total'] = $sub_total;
            }

            $clawbackSettlements = ClawbackSettlement::with(['salesDetail', 'saleUserInfo.state', 'adjustment' => function ($q) use ($id, $Payroll) {
                $q->where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'payroll_type' => 'overrides', 'type' => 'clawback']);
            }])->where([
                'is_mark_paid' => $isMarkAsPaid,
                'type' => 'overrides',
                'payroll_id' => $payroll_id,
                'user_id' => $Payroll->user_id,
                'clawback_type' => 'next payroll',
                'pay_period_from' => $Payroll->pay_period_from,
                'pay_period_to' => $Payroll->pay_period_to,
            ])->get();
            if (count($clawbackSettlements) > 0) {
                foreach ($clawbackSettlements as $clawbackSettlement) {
                    //                    $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id'=> $id, 'user_id'=> $Payroll->user_id, 'pid'=> $clawbackSettlement->pid, 'payroll_type' =>'overrides', 'type'=> 'clawback'])->first();
                    $data['data'][] = [
                        'id' => $clawbackSettlement->sale_user_id,
                        'pid' => $clawbackSettlement->pid,
                        'first_name' => isset($clawbackSettlement->saleUserInfo->first_name) ? $clawbackSettlement->saleUserInfo->first_name : null,
                        'last_name' => isset($clawbackSettlement->saleUserInfo->last_name) ? $clawbackSettlement->saleUserInfo->last_name : null,
                        'position_id' => isset($clawbackSettlement->saleUserInfo->position_id) ? $clawbackSettlement->saleUserInfo->position_id : null,
                        'sub_position_id' => isset($clawbackSettlement->saleUserInfo->sub_position_id) ? $clawbackSettlement->saleUserInfo->sub_position_id : null,
                        'is_super_admin' => isset($clawbackSettlement->saleUserInfo->is_super_admin) ? $clawbackSettlement->saleUserInfo->is_super_admin : null,
                        'is_manager' => isset($clawbackSettlement->saleUserInfo->is_manager) ? $clawbackSettlement->saleUserInfo->is_manager : null,
                        'image' => isset($clawbackSettlement->saleUserInfo->image) ? $clawbackSettlement->saleUserInfo->image : null,
                        'type' => 'clawback',
                        'accounts' => 1,
                        'kw_installed' => isset($clawbackSettlement->salesDetail->kw) ? $clawbackSettlement->salesDetail->kw : null,
                        'total_amount' => isset($clawbackSettlement->clawback_amount) ? (0 - $clawbackSettlement->clawback_amount) : null,
                        'override_type' => 'clawback', // Not On Table
                        'override_amount' => null,
                        'calculated_redline' => '',
                        'state' => isset($clawbackSettlement->saleUserInfo->state) ? $clawbackSettlement->saleUserInfo->state->state_code : null,
                        'm2_date' => isset($clawbackSettlement->salesDetail->m2_date) ? $clawbackSettlement->salesDetail->m2_date : null,
                        'customer_name' => isset($clawbackSettlement->salesDetail->customer_name) ? $clawbackSettlement->salesDetail->customer_name : null,
                        'override_adjustment' => isset($clawbackSettlement->adjustment->amount) ? $clawbackSettlement->adjustment->amount : 0,
                        //                        'override_adjustment'=>isset($clawbackSettlement->adjustment->amount) ? $clawbackSettlement->adjustment->amount : 0
                    ];
                    $sub_total = $sub_total - $clawbackSettlement->clawback_amount;
                }
                $data['sub_total'] = $sub_total;
            }

            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    // public function adjustmentDetails($id): JsonResponse{
    //     $data = array();
    //     $payroll = Payroll::where(['id' => $id])->first();
    //     $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6])->get();
    //     $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();

    //     if (count($adjustment) > 0) {
    //         foreach ($adjustment as $key => $value) {
    //             $data[] = [
    //                 'id' => $value->user_id,
    //                 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
    //                 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
    //                 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
    //                 // 'date' => isset($value->cost_date) ? $value->cost_date : null,
    //                 'date' => isset($value->updated_at) ? date('Y-m-d',strtotime($value->updated_at)): null,
    //                 'amount' => isset($value->amount) ? $value->amount : null,
    //                 'type' => isset($value->adjustment) ? $value->adjustment->name : null,
    //                 'description' => isset($value->description) ? $value->description : null,
    //             ];
    //         }
    //     }

    //     if (count($adjustmentNegative) > 0) {
    //         foreach ($adjustmentNegative as $key => $value) {
    //             $data[] = [
    //                 'id' => $value->user_id,
    //                 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
    //                 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
    //                 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
    //                 // 'date' => isset($value->cost_date) ? $value->cost_date : null,
    //                 'date' => isset($value->updated_at) ? date('Y-m-d',strtotime($value->updated_at)): null,
    //                 'amount' => isset($value->amount) ? (0 - $value->amount) : null,
    //                 'type' => isset($value->adjustment) ? $value->adjustment->name : null,
    //                 'description' => isset($value->description) ? $value->description : null,
    //             ];
    //         }
    //     }
    //     // code  start by nikhil

    //     $dataAdjustment = PayrollAdjustment::with('detail')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();

    //     $totalAmount = DB::table('payroll_adjustments')->where(['payroll_id' => $payroll->id])->where('user_id', $payroll->user_id)
    //     ->sum(\DB::raw('commission_amount + overrides_amount + adjustments_amount + reimbursements_amount + deductions_amount + reconciliations_amount + clawbacks_amount'));

    //     if (count( $dataAdjustment) > 0) {

    //         foreach ( $dataAdjustment as $key => $val) {

    //             if($val->commission_amount>0 || $val->commission_amount<0){
    //                 $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
    //                 $data[] = [
    //                     'id' => $val->user_id,
    //                     'first_name' => 'Super',
    //                     'last_name' => 'Admin',
    //                     'image' => null,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' => $val->commission_amount,
    //                     'type' => 'Commission',
    //                     'comment' => isset($comment['comment']) ? $comment['comment'] : null,
    //                 ];

    //             }
    //             if($val->overrides_amount>0 || $val->overrides_amount<0){
    //                 $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
    //                 $data[] = [
    //                     'id' => $val->user_id,
    //                     'first_name' => 'Super',
    //                     'last_name' => 'Admin',
    //                     'image' => null,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' =>  $val->overrides_amount,
    //                     'type' => 'Overrides',
    //                     'comment' => isset($comment['comment']) ? $comment['comment'] : null,
    //                 ];

    //             }
    //             if($val->adjustments_amount>0 || $val->adjustments_amount<0){
    //                 $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
    //                 $data[] = [
    //                     'id' => $val->user_id,
    //                     'first_name' => 'Super',
    //                     'last_name' => 'Admin',
    //                     'image' => null,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' => $val->adjustments_amount,
    //                     'type' => 'Adjustments',
    //                     'comment' => isset($comment['comment']) ? $comment['comment'] : null,
    //                 ];

    //             }
    //             if($val->reimbursements_amount>0 || $val->reimbursements_amount<0){
    //                 $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
    //                 $data[] = [
    //                     'id' => $val->user_id,
    //                     'first_name' => 'Super',
    //                     'last_name' => 'Admin',
    //                     'image' => null,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' => $val->reimbursements_amount,
    //                     'type' => 'Reimbursements',
    //                     'comment' => isset($comment['comment']) ? $comment['comment'] : null,
    //                 ];

    //             }
    //             if($val->deductions_amount>0 || $val->deductions_amount<0){
    //                 $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
    //                 $data[] = [
    //                     'id' => $val->user_id,
    //                     'first_name' => 'Super',
    //                     'last_name' => 'Admin',
    //                     'image' => null,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' => $val->deductions_amount,
    //                     'type' => 'Deductions',
    //                     'comment' => isset($comment['comment']) ? $comment['comment'] : null,
    //                 ];

    //             }
    //             if($val->reconciliations_amount>0 || $val->reconciliations_amount<0){
    //                 $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
    //                 $data[] = [
    //                     'id' => $val->user_id,
    //                     'first_name' => 'Super',
    //                     'last_name' => 'Admin',
    //                     'image' => null,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' => $val->reconciliations_amount,
    //                     'type' => 'Reconciliations',
    //                     'comment' => isset($comment['comment']) ? $comment['comment'] : null,
    //                 ];

    //             }
    //             if($val->clawbacks_amount>0 ||$val->clawbacks_amount<0){
    //                 $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
    //                 $data[] = [
    //                     'id' => $val->user_id,
    //                     'first_name' => 'Super',
    //                     'last_name' => 'Admin',
    //                     'image' => null,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' =>$val->clawbacks_amount,
    //                     'type' => 'Clawback',
    //                     'comment' => isset($comment['comment']) ? $comment['comment'] : null,
    //                 ];

    //             }

    //         }
    //     }

    //     // code end by nikhil

    //     return response()->json([
    //         'ApiName' => 'adjustment_details',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'payroll_status' => $payroll->status,
    //         'data' => $data,

    //     ], 200);

    // }

    // adjustmentDetails for post method
    public function adjustmentDetails(Request $request): JsonResponse
    {
        $data = [];
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

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        if (! empty($payroll)) {
            //     if($payroll->status ==3){
            //         $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6])->get();
            //         $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();
            //     }else{
            //         $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6])->get();
            //         $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();

            //     }

            // if (count($adjustment) > 0) {
            //     foreach ($adjustment as $key => $value) {
            //         if(isset($value->approvedBy->image) && $value->approvedBy->image!=null){
            //             $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$value->approvedBy->image);
            //         }else{
            //             $image_s3 = null;
            //         }
            //         $data[] = [
            //             'id' => $value->user_id,
            //             'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
            //             'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
            //             'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
            //             'image_s3' => $image_s3,
            //             // 'date' => isset($value->cost_date) ? $value->cost_date : null,
            //             'date' => isset($value->updated_at) ? date('Y-m-d',strtotime($value->updated_at)): null,
            //             'amount' => isset($value->amount) ? $value->amount : null,
            //             'type' => isset($value->adjustment) ? $value->adjustment->name : null,
            //             'description' => isset($value->description) ? $value->description : null,
            //             'adjustment_type' => 'approvals_request',
            //         ];
            //     }
            // }

            // if (count($adjustmentNegative) > 0) {
            //     foreach ($adjustmentNegative as $key => $value) {
            //         if(isset($value->approvedBy->image) && $value->approvedBy->image!=null){
            //             $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$value->approvedBy->image);
            //         }else{
            //             $image_s3 = null;
            //         }
            //         $data[] = [
            //             'id' => $value->user_id,
            //             'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
            //             'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
            //             'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
            //             'image_s3' => $image_s3,
            //             // 'date' => isset($value->cost_date) ? $value->cost_date : null,
            //             'date' => isset($value->updated_at) ? date('Y-m-d',strtotime($value->updated_at)): null,
            //             'amount' => isset($value->amount) ? (0 - $value->amount) : null,
            //             'type' => isset($value->adjustment) ? $value->adjustment->name : null,
            //             'description' => isset($value->description) ? $value->description : null,
            //             'adjustment_type' => 'approvals_request',
            //         ];
            //     }
            // }
            // code  start by nikhil

            $dataAdjustment = PayrollAdjustment::with('detail')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();

            $totalAmount = DB::table('payroll_adjustments')->where(['payroll_id' => $payroll->id])->where('user_id', $payroll->user_id)
                ->sum(\DB::raw('commission_amount + overrides_amount + adjustments_amount + reimbursements_amount + deductions_amount + reconciliations_amount + clawbacks_amount'));

            if (count($dataAdjustment) > 0) {

                foreach ($dataAdjustment as $key => $val) {
                    $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
                    $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();
                    foreach ($comment as $c) {

                        // Commented by
                        $commentBy = $c->commented_by;

                        $data[] = [
                            'id' => $c['id'],
                            'first_name' => $commentBy?->first_name,
                            'last_name' => $commentBy?->last_name,
                            'image' => null,
                            'image_s3' => $image_s3,
                            'date' => isset($c['created_at']) ? date('Y-m-d', strtotime($c['created_at'])) : null,
                            'amount' => $c['amount'],
                            'payroll_type' => $c['payroll_type'],
                            'type' => $c['type'],
                            'description' => isset($c['comment']) ? $c['comment'] : null,
                            'adjustment_type' => 'payroll',
                            'is_onetime_payment' => $c->is_onetime_payment,
                        ];
                    }

                    // if($val->commission_amount>0 || $val->commission_amount<0){
                    //     $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
                    //     $data[] = [
                    //         'id' => $val->user_id,
                    //         'first_name' => 'Super',
                    //         'last_name' => 'Admin',
                    //         'image' => null,
                    //         'image_s3' => $image_s3,
                    //         'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
                    //         'amount' => $val->commission_amount,
                    //         'type' => 'Commission',
                    //         'description' => isset($comment['comment']) ? $comment['comment'] : null,
                    //         'adjustment_type' => 'payroll',
                    //     ];

                    // }
                    // if($val->overrides_amount>0 || $val->overrides_amount<0){
                    //     $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
                    //     $data[] = [
                    //         'id' => $val->user_id,
                    //         'first_name' => 'Super',
                    //         'last_name' => 'Admin',
                    //         'image' => null,
                    //         'image_s3' => $image_s3,
                    //         'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
                    //         'amount' =>  $val->overrides_amount,
                    //         'type' => 'Overrides',
                    //         'description' => isset($comment['comment']) ? $comment['comment'] : null,
                    //         'adjustment_type' => 'payroll'
                    //     ];

                    // }
                    // if($val->adjustments_amount>0 || $val->adjustments_amount<0){
                    //     $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
                    //     $data[] = [
                    //         'id' => $val->user_id,
                    //         'first_name' => 'Super',
                    //         'last_name' => 'Admin',
                    //         'image' => null,
                    //         'image_s3' => $image_s3,
                    //         'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
                    //         'amount' => $val->adjustments_amount,
                    //         'type' => 'Adjustments',
                    //         'description' => isset($comment['comment']) ? $comment['comment'] : null,
                    //         'adjustment_type' => 'payroll'
                    //     ];

                    // }
                    // if($val->reimbursements_amount>0 || $val->reimbursements_amount<0){
                    //     $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
                    //     $data[] = [
                    //         'id' => $val->user_id,
                    //         'first_name' => 'Super',
                    //         'last_name' => 'Admin',
                    //         'image' => null,
                    //         'image_s3' => $image_s3,
                    //         'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
                    //         'amount' => $val->reimbursements_amount,
                    //         'type' => 'Reimbursements',
                    //         'description' => isset($comment['comment']) ? $comment['comment'] : null,
                    //         'adjustment_type' => 'payroll'
                    //     ];

                    // }
                    // if($val->deductions_amount>0 || $val->deductions_amount<0){
                    //     $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
                    //     $data[] = [
                    //         'id' => $val->user_id,
                    //         'first_name' => 'Super',
                    //         'last_name' => 'Admin',
                    //         'image' => null,
                    //         'image_s3' => $image_s3,
                    //         'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
                    //         'amount' => $val->deductions_amount,
                    //         'type' => 'Deductions',
                    //         'description' => isset($comment['comment']) ? $comment['comment'] : null,
                    //         'adjustment_type' => 'payroll'
                    //     ];

                    // }
                    // if($val->reconciliations_amount>0 || $val->reconciliations_amount<0){
                    //     $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
                    //     $data[] = [
                    //         'id' => $val->user_id,
                    //         'first_name' => 'Super',
                    //         'last_name' => 'Admin',
                    //         'image' => null,
                    //         'image_s3' => $image_s3,
                    //         'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
                    //         'amount' => $val->reconciliations_amount,
                    //         'type' => 'Reconciliations',
                    //         'description' => isset($comment['comment']) ? $comment['comment'] : null,
                    //         'adjustment_type' => 'payroll'
                    //     ];

                    // }
                    // if($val->clawbacks_amount>0 ||$val->clawbacks_amount<0){
                    //     $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
                    //     $data[] = [
                    //         'id' => $val->user_id,
                    //         'first_name' => 'Super',
                    //         'last_name' => 'Admin',
                    //         'image' => null,
                    //         'image_s3' => $image_s3,
                    //         'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
                    //         'amount' =>$val->clawbacks_amount,
                    //         'type' => 'Clawback',
                    //         'description' => isset($comment['comment']) ? $comment['comment'] : null,
                    //         'adjustment_type' => 'payroll'
                    //     ];

                    // }

                }
            }

            $dataApprovalAndRequest = ApprovalsAndRequest::with('adjustment')
                ->where('adjustment_type_id', '!=', 2)
                ->where(['payroll_id' => $payroll->id])
                ->where(['user_id' => $payroll->user_id])
                ->get();
            if (count($dataApprovalAndRequest) > 0) {

                foreach ($dataApprovalAndRequest as $key => $val) {
                    $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.'Employee_profile/default-user.png');
                    // $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();
                    // foreach($comment as $c){
                    $data[] = [
                        'id' => $val['id'],
                        'first_name' => 'Super',
                        'last_name' => 'Admin',
                        'image' => null,
                        'image_s3' => $image_s3,
                        'date' => isset($val['created_at']) ? date('Y-m-d', strtotime($val['created_at'])) : null,
                        'amount' => ($val->adjustment_type_id == 5) ? -$val['amount'] : $val['amount'],
                        'payroll_type' => $val->adjustment->name,
                        'type' => 'Adjustment',
                        'description' => null,
                        'adjustment_type' => 'payroll',
                        'is_onetime_payment' => $val->is_onetime_payment,
                    ];
                    // }
                }
            }
            // code end by nikhil

            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll->status,
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

    // public function reimbursementDetails($id)
    // {
    //     $data = array();
    //     $payroll = Payroll::where(['id' => $id])->first();
    //     $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy')->where('status', 'Accept')->where(['user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from'=> $payroll->pay_period_from, 'pay_period_to'=> $payroll->pay_period_to])->get();
    //     //return $reimbursement;
    //     if (count($reimbursement) > 0) {
    //         foreach ($reimbursement as $key => $value) {
    //             $data[] = [
    //                 'id' => $value->user_id,
    //                 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
    //                 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
    //                 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
    //                 'date' => isset($value->cost_date) ? $value->cost_date : null,
    //                 'amount' => isset($value->amount) ? $value->amount : null,
    //                 'description' => isset($value->description) ? $value->description : null,
    //             ];
    //         }
    //     }

    //     return response()->json([
    //         'ApiName' => 'reimbursement_details',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'payroll_status' => $payroll->status,
    //         'data' => $data,
    //     ], 200);

    // }

    // public function reimbursementDetails($id)
    // {
    //     $data = array();
    //     $payroll = Payroll::where(['id' => $id])->first();
    //     $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy')->where('status', 'Accept')->where(['user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from'=> $payroll->pay_period_from, 'pay_period_to'=> $payroll->pay_period_to])->get();
    //     //return $reimbursement;
    //     if (count($reimbursement) > 0) {
    //         foreach ($reimbursement as $key => $value) {
    //             $data[] = [
    //                 'id' => $value->user_id,
    //                 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
    //                 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
    //                 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
    //                 'date' => isset($value->cost_date) ? $value->cost_date : null,
    //                 'amount' => isset($value->amount) ? $value->amount : null,
    //                 'description' => isset($value->description) ? $value->description : null,
    //             ];
    //         }
    //     }

    //     return response()->json([
    //         'ApiName' => 'reimbursement_details',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'payroll_status' => $payroll->status,
    //         'data' => $data,
    //     ], 200);

    // }

    // reimbursementDetails for post request
    public function reimbursementDetails(Request $request)
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy')->where('status', 'Accept')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
            // $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy')->where('status', 'Accept')->where(['payroll_id'=>$payroll->id,'user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from'=> $payroll->pay_period_from, 'pay_period_to'=> $payroll->pay_period_to])->get();
            // return $reimbursement;
            if (count($reimbursement) > 0) {
                foreach ($reimbursement as $key => $value) {
                    if (isset($value->approvedBy->image) && $value->approvedBy->image != null) {
                        $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$value->approvedBy->image);
                    } else {
                        $image_s3 = null;
                    }
                    if (isset($value->approvedBy->image) && $value->approvedBy->image != null) {
                        $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$value->approvedBy->image);
                    } else {
                        $image_s3 = null;
                    }
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'position_id' => isset($value->approvedBy->position_id) ? $value->approvedBy->position_id : null,
                        'sub_position_id' => isset($value->approvedBy->sub_position_id) ? $value->approvedBy->sub_position_id : null,
                        'is_super_admin' => isset($value->approvedBy->is_super_admin) ? $value->approvedBy->is_super_admin : null,
                        'is_manager' => isset($value->approvedBy->is_manager) ? $value->approvedBy->is_manager : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'image_s3' => $image_s3,
                        'image_s3' => $image_s3,
                        'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_onetime_payment' => $value->is_onetime_payment,
                    ];
                }
            }

            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll_status,
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

    public function paymentRequest(Request $request)
    {
        $data = [];
        $requestType = $request->type;
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        // $paymentRequest = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('adjustment_type_id','!=','4')->where('status', 'Approved')->orderBy('id', 'asc');
        // $paymentRequest = ApprovalsAndRequest::whereNotNull('req_no')->orderBy('id', 'desc');
        $paymentRequest = ApprovalsAndRequest::whereNotNull('req_no')->whereNotIn('adjustment_type_id', [7, 8, 9])->orderBy('id', 'desc');

        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $filter = $request->input('filter');
            $paymentRequest->whereHas('adjustment', function ($query) use ($filter) {
                return $query->where('name', 'like', '%'.$filter.'%');
            });
        }

        if ($request->has('user_id') && ! empty($request->input('user_id'))) {
            $user_id = $request->input('user_id');
            $paymentRequest->whereHas('user', function ($query) use ($user_id) {
                return $query->where('id', $user_id);
            });
        }
        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $paymentRequest->whereHas('user', function ($query) use ($search) {
                return $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhere('req_no', 'like', '%'.$search.'%');
            });
        }
        if ($requestType == 'PaymentRequest') {
            $paymentRequest = $paymentRequest->with('user.positionpayfrequencies.frequencyType', 'approvedBy', 'adjustment')->where('adjustment_type_id', '!=', '4')->where('status', 'Approved')->orderBy('id', 'asc');
        }

        if ($requestType == 'AdvancePaymentRequest') {
            $paymentRequest = $paymentRequest->with('user.positionpayfrequencies.frequencyType', 'approvedBy', 'adjustment')->where('adjustment_type_id', '4')->where('status', 'Approved')->orderBy('id', 'asc');
        }

        if ($requestType == 'Both' || $requestType == 'both') {
            $paymentRequest = $paymentRequest->with('user.positionpayfrequencies.frequencyType', 'approvedBy', 'adjustment')->where('status', 'Approved')->orderBy('id', 'asc');
        }

        // $paymentRequest->with('user', 'approvedBy', 'adjustment')->where('adjustment_type_id','!=','4')->where('status', 'Approved');
        if ($request->has('sort') && $request->input('sort') != '') {
            $paymentRequest = $paymentRequest->get();
        } else {
            $paymentRequest = $paymentRequest->PAGINATE($perpage);
        }

        if (count($paymentRequest) > 0) {
            $paymentRequest->transform(function ($value) {
                if (isset($value->user->image) && $value->user->image != null) {
                    $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$value->user->image);
                } else {
                    $image_s3 = null;
                }

                if ($value->adjustment_type_id == 5) {
                    $value->amount = (0 - $value->amount);
                }
                // $userData = User::where('id',$value->user_id)->first();

                return [
                    'id' => $value->id,
                    'req_no' => $value->req_no,
                    'user_id' => $value->user_id,
                    'first_name' => isset($value->user->first_name) ? $value->user->first_name : null,
                    'last_name' => isset($value->user->last_name) ? $value->user->last_name : null,
                    'position_id' => isset($value->user->position_id) ? $value->user->position_id : null,
                    'sub_position_id' => isset($value->user->sub_position_id) ? $value->user->sub_position_id : null,
                    'frequency_type_id' => $value->user && $value->user->positionpayfrequencies ? $value->user->positionpayfrequencies->frequency_type_id : null,
                    'frequency_type_name' => $value->user && $value->user->positionpayfrequencies && $value->user->positionpayfrequencies->frequencyType ? $value->user->positionpayfrequencies->frequencyType->name : null,
                    'is_super_admin' => isset($value->user->is_super_admin) ? $value->user->is_super_admin : null,
                    'is_manager' => isset($value->user->is_manager) ? $value->user->is_manager : null,
                    'image' => isset($value->user->image) ? $value->user->image : null,
                    'image_s3' => $image_s3,
                    'approved_by' => isset($value->approvedBy) ? $value->approvedBy->first_name : null,
                    'request_on' => isset($value->created_at) ? $value->created_at->format('Y-m-d') : null,
                    'amount' => isset($value->amount) ? $value->amount : null,
                    'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                    'description' => isset($value->description) ? $value->description : null,
                    'adjustment_type_id' => isset($value->adjustment_type_id) ? $value->adjustment_type_id : null,
                    'is_stop_payroll' => isset($value->user->stop_payroll) ? $value->user->stop_payroll : 0,
                    'is_onetime_payment' => $value->is_onetime_payment,
                    'worker_type' => isset($value->user->worker_type) ? $value->user->worker_type : null,
                ];
            });
            // foreach ($paymentRequest as $key => $value) {
            //     $data[] = [
            //         'id' => $value->id,
            //         'user_id' => $value->user_id,
            //         'first_name' => isset($value->user->first_name) ? $value->user->first_name : null,
            //         'last_name' => isset($value->user->last_name) ? $value->user->last_name : null,
            //         'image' => isset($value->user->image) ? $value->user->image : null,
            //         'approved_by' => isset($value->approvedBy) ? $value->approvedBy->first_name : null,
            //         'request_on' => isset($value->created_at) ? $value->created_at->format('Y-m-d') : null,
            //         'amount' => isset($value->amount) ? $value->amount : null,
            //         'type' => isset($value->adjustment) ? $value->adjustment->name : null,
            //         'description' => isset($value->description) ? $value->description : null,
            //     ];
            // }

            if ($request->has('sort') && $request->input('sort') == 'requested_on') {
                $val = $request->input('sort_val');
                $paymentRequest = json_decode($paymentRequest);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($paymentRequest, 'request_on'), SORT_DESC, $paymentRequest);
                } else {
                    array_multisort(array_column($paymentRequest, 'request_on'), SORT_ASC, $paymentRequest);
                }
            }

            if ($request->has('sort') && $request->input('sort') == 'amount') {
                $val = $request->input('sort_val');
                $paymentRequest = json_decode($paymentRequest);
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($paymentRequest, 'amount'), SORT_DESC, $paymentRequest);
                } else {
                    array_multisort(array_column($paymentRequest, 'amount'), SORT_ASC, $paymentRequest);
                }
            }
            if ($request->has('sort') && $request->input('sort') != '') {
                $paymentRequest = $this->paginates($paymentRequest, $perpage);
            }
        }

        return response()->json([
            'ApiName' => 'payment_request',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $paymentRequest,
        ], 200);

    }

    public function advanceNegativePaymentRequest(Request $request)
    {
        try {
            $data = [];
            if (! empty($request->perpage)) {
                $perpage = $request->perpage;
            } else {
                $perpage = 10;
            }
            $sortColumnName = 'created_at';
            $sortType = isset($request->sort_val) ? $request->sort_val : 'asc';
            if (isset($request->sort) && $request->sort == 'amount') {
                $sortColumnName = 'total_amount';
            } elseif (isset($request->sort) && $request->sort == 'age') {
                $sortColumnName = 'daysDifference';
            }
            if (! empty($request->search)) {
                $search_text = $request->search;
            } else {
                $search_text = '';
            }
            if (! in_array($sortType, ['asc', 'desc'])) {
                return response()->json([
                    'ApiName' => 'advance negative payment requests',
                    'status' => false,
                    'message' => 'Parameter is wrong',
                    'data' => [],
                ], 400);
            }

            $data = User::with('positionpayfrequencies.frequencyType')->whereHas('ApprovalsAndRequests', function ($query) {
                $query->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved');
            })->where(function ($query) use ($search_text) {
                $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                    ->orWhereHas('ApprovalsAndRequests.ChildApprovalsAndRequests', function ($query) use ($search_text) {
                        $query->where('req_no', 'LIKE', '%'.$search_text.'%');
                    });
            })->with('ApprovalsAndRequests.approvedBy:id,first_name,last_name,image,manager_id,position_id,sub_position_id,is_super_admin')
                ->with('ApprovalsAndRequests', function ($query) {
                    $query->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved')->select('id', 'parent_id', 'user_id', 'amount', 'txn_id', 'created_at', 'approved_by');
                })->select('first_name', 'last_name', 'image', 'id', 'manager_id', 'position_id', 'sub_position_id', 'is_super_admin', 'worker_type');

            if ($request->user_id) {
                $data = $data->where('id', $request->user_id)->get();
            } else {
                $data = $data->get();
            }

            $data->transform(function ($value) {
                if (isset($value->image) && $value->image != null) {
                    $value->image = s3_getTempUrl(config('app.domain_name').'/'.$value->image);
                } else {
                    $value->image = null;
                }

                $value->ApprovalsAndRequests->transform(function ($req) {
                    $chiieldRequestAmmount = ApprovalsAndRequest::where('parent_id', $req->id)->sum('amount');
                    $reqAmmount = $req->amount - $chiieldRequestAmmount;
                    $req->amount = $reqAmmount;
                    $req->approvedBy->image;
                    if ($req->txn_id == null) {
                        $req_data = ApprovalsAndRequest::where('id', $req->parent_id)->WhereNull('txn_id')->first();
                        $req->req_no = $req_data->req_no;
                    } else {
                        $req->req_no = $req->txn_id;
                    }
                    if (isset($req->approvedBy->image) && $req->approvedBy->image != null) {
                        $image = s3_getTempUrl(config('app.domain_name').'/'.$req->approvedBy->image);
                    } else {
                        $image = null;
                    }
                    $req->approvedBy->s3Image = $image;

                    return $req;
                });

                $start_date = date('Y-m-d');
                // $payFrequency = $this->payFrequencyNew($start_date, $value->sub_position_id);
                $payFrequency = $this->openPayFrequency($value->sub_position_id, $value->id);

                $startDateNext = isset($payFrequency) ? $payFrequency->pay_period_from : null;
                $endDateNext = isset($payFrequency) ? $payFrequency->pay_period_to : null;
                $payroll = Payroll::where(['pay_period_from' => $startDateNext, 'pay_period_to' => $endDateNext, 'user_id' => $value->id])->first();
                if ($payroll) {
                    $value->current_payroll = $payroll->net_pay;
                } else {
                    $value->current_payroll = 0;
                }
                $value->total_request = count($value->ApprovalsAndRequests);
                $value->total_amount = $value->ApprovalsAndRequests->sum('amount');
                $date = Carbon::parse($value->ApprovalsAndRequests->min('created_at'));
                $value->frequency_type_id = isset($value->positionpayfrequencies->frequency_type_id) ? $value->positionpayfrequencies->frequency_type_id : null;
                $value->frequency_type_name = isset($value->positionpayfrequencies->frequencyType->name) ? $value->positionpayfrequencies->frequencyType->name : null;
                $currentDate = Carbon::now();
                $value->daysDifference = $date->diffInDays($currentDate).' days';

                return $value;
            });

            // Sort data dynamically based on custom key
            if ($sortType == 'asc') {
                $sortedData = $data->sortBy($sortColumnName);
            } else {
                $sortedData = $data->sortByDesc($sortColumnName);
            }

            // Ensure you reset the keys after sorting if necessary
            $data = $sortedData->values();
            $data = $this->paginates($data->toArray(), $perpage);

            if ($data) {
                return response()->json([
                    'ApiName' => 'advance negative payment requests',
                    'status' => true,
                    'message' => 'Successfully.',
                    'data' => $data,
                ]);
            } else {
                return response()->json([
                    'ApiName' => 'advance negative payment requests',
                    'status' => false,
                    'message' => 'data not found.',
                    'data' => [],
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'advance negative payment requests',
                'status' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => [],
            ], 400);
        }
    }

    public function advanceRepay(Request $request): JsonResponse
    {
        try {

            $Validator = Validator::make($request->all(),
                [
                    'repay_type' => 'required',
                    'req_id' => 'required',
                    'pay_period_from' => 'required|date',
                    'pay_period_to' => 'required|date|after:pay_period_from',
                ]);
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            } else {
                $adwance_requests = [];
                $checkPayroll = 0;
                if (isset($request->pay_period_from, $request->pay_period_to) && $request->pay_period_from != '' && $request->pay_period_to != '') {
                    $startDateNext = $request->pay_period_from;
                    $endDateNext = $request->pay_period_to;
                } else {
                    $startDateNext = null;
                    $endDateNext = null;
                }
                $checkPayroll = Payroll::where(['pay_period_from' => $startDateNext, 'pay_period_to' => $endDateNext])->whereIn('finalize_status', [1, 2])->count();
                if ($checkPayroll > 0) {
                    return response()->json([
                        'ApiName' => 'advance repayment',
                        'status' => false,
                        'message' => 'Pay period is finalized. Please select another pay period. ',
                    ], 400);
                }
                if ($request->repay_type == 'repay_all') {
                    $adwance_requests = ApprovalsAndRequest::with('user:id,sub_position_id,position_id')->where('user_id', $request->req_id)->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved')->get();
                    $adwance_requests->transform(function ($result) use ($startDateNext, $endDateNext) {
                        // $start_date = date('Y-m-d');
                        // $payFrequency = $this->payFrequencyNew($start_date, $result->user->sub_position_id);
                        // $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                        // $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                        // $payroll_id = updateExistingPayroll($result->user_id, $startDateNext, $endDateNext, $result->amount, 'adjustment', $result->user->position_id, 0);
                        ApprovalsAndRequest::where('id', $result->id)->update([
                            'payroll_id' => null,
                            'pay_period_from' => null,
                            'pay_period_to' => null,
                            'ref_id' => 0,
                            'is_next_payroll' => 0,
                            'is_mark_paid' => 0,
                            'status' => 'Accept', ]);

                        $chiieldRequestAmmount = ApprovalsAndRequest::where('parent_id', $result->id)->sum('amount');
                        $reqAmmount = $result->amount - $chiieldRequestAmmount;
                        $result->amount = $reqAmmount;
                        if ($reqAmmount < 0) {
                            $newData = $result->toArray();
                            $newData['amount'] = $reqAmmount;
                            $newData['parent_id'] = $result->id;
                            $newData['status'] = 'Accept';
                            unset($newData['user']);
                            unset($newData['id']);
                            $partially_request = ApprovalsAndRequest::create($newData);
                            $start_date = date('Y-m-d');
                            // $payFrequency = $this->payFrequencyNew($start_date, $result->user->sub_position_id);
                            $payFrequency = $this->openPayFrequency($result->user->sub_position_id, $result->user->id);
                            if ($startDateNext == null && $endDateNext == null) {
                                $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                                $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                            }
                            $payroll_id = updateExistingPayroll($result->user_id, $startDateNext, $endDateNext, (($reqAmmount) * (-1)), 'adjustment', $result->user->position_id, 0);
                            ApprovalsAndRequest::where('id', $partially_request->id)->update([
                                'payroll_id' => $payroll_id,
                                'pay_period_from' => $startDateNext,
                                'pay_period_to' => $endDateNext,
                                'ref_id' => 0,
                                'is_next_payroll' => 0,
                                'is_mark_paid' => 0,
                                'status' => 'Accept']);
                        }
                    });

                    return response()->json([
                        'ApiName' => 'advance repayment',
                        'status' => true,
                        'message' => 'Success.',
                        'data' => $adwance_requests,
                    ], 200);
                } elseif ($request->repay_type == 'repay') {
                    if (! empty($request->amount) && $request->amount != 0) {
                        $adwance_requests = ApprovalsAndRequest::with('user:id,sub_position_id,position_id')->where('id', $request->req_id)->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved')->first();
                        if ($adwance_requests) {
                            $newData = $adwance_requests->toArray();
                            $newData['amount'] = $request->amount < 0 ? $request->amount : '-'.$request->amount;
                            $newData['parent_id'] = $adwance_requests->id;
                            $newData['status'] = 'Accept';
                            unset($newData['user']);
                            unset($newData['id']);
                            $partially_request = ApprovalsAndRequest::create($newData);
                            $start_date = date('Y-m-d');
                            // $payFrequency = $this->payFrequencyNew($start_date, $adwance_requests->user->sub_position_id);
                            $payFrequency = $this->openPayFrequency($adwance_requests->user->sub_position_id, $adwance_requests->user->id);
                            if ($startDateNext == null && $endDateNext == null) {
                                $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                                $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                            }
                            $payroll_id = updateExistingPayroll($adwance_requests->user_id, $startDateNext, $endDateNext, $request->amount, 'adjustment', $adwance_requests->user->position_id, 0);
                            ApprovalsAndRequest::where('id', $partially_request->id)->update([
                                'payroll_id' => $payroll_id,
                                'pay_period_from' => $startDateNext,
                                'pay_period_to' => $endDateNext,
                                'ref_id' => 0,
                                'is_next_payroll' => 0,
                                'is_mark_paid' => 0,
                                'status' => 'Accept']);
                        }
                        $chiieldRequestAmmount = ApprovalsAndRequest::where('parent_id', $adwance_requests->id)->sum('amount');
                        if ($adwance_requests->amount - $chiieldRequestAmmount == 0) {
                            ApprovalsAndRequest::where('id', $adwance_requests->id)->update(['status' => 'Accept']);
                        }
                    } else {
                        return response()->json([
                            'ApiName' => 'advance repayment',
                            'status' => false,
                            'message' => 'The Amount field is required.',
                            'data' => $adwance_requests,
                        ], 400);
                    }

                    return response()->json([
                        'ApiName' => 'advance repayment',
                        'status' => true,
                        'message' => 'Success.',
                        'data' => $adwance_requests,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'advance repayment',
                        'status' => false,
                        'message' => 'Invalid Repay Type',
                        'data' => $adwance_requests,
                    ], 400);
                }

            }

        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'advance negative payment requests',
                'status' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => [],
            ], 400);
        }

    }

    public function paginates($items, $perPage = null, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function paymentRequestPayNow(Request $request): JsonResponse
    {

        $data = ApprovalsAndRequest::where('id', $request->request_id)->first();
        if ($data) {
            $data->status = 'Paid';
            $data->save();
            $date = date('Y-m-d');
            $user = User::where('id', $data->user_id)->first();
            $payFrequency = $this->payFrequency($date, $user->sub_position_id, $user->user_id);
            if ($data->adjustment_type_id == 4) {
                $create = [
                    'user_id' => $data->user_id,
                    'manager_id' => $data->manager_id,
                    'approved_by' => $data->approved_by,
                    'adjustment_type_id' => $data->adjustment_type_id,
                    'state_id' => $data->state_id,
                    'dispute_type' => $data->dispute_type,
                    'customer_pid' => $data->customer_pid,
                    'cost_tracking_id' => $data->cost_tracking_id,
                    'cost_date' => $data->cost_date,
                    'request_date' => $data->request_date,
                    'amount' => (0 - $data->amount),
                    'status' => 'Accept',
                    'pay_period_from' => isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null,
                    'pay_period_to' => isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null,
                ];
                $add = ApprovalsAndRequest::create($create);

                $payRoll = PayRoll::where(['user_id' => $data->user_id, 'pay_period_from' => $payFrequency->next_pay_period_from, 'pay_period_to' => $payFrequency->next_pay_period_to])->first();
                if (empty($payRoll)) {
                    PayRoll::create(
                        [
                            'user_id' => $user->id,
                            'position_id' => $user->position_id,
                            'adjustment' => (0 - $data->amount),
                            'pay_period_from' => isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null,
                            'pay_period_to' => isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null,
                            'status' => 1,
                        ]
                    );
                }
            }

            return response()->json([
                'ApiName' => 'payment_request',
                'status' => true,
                'message' => 'Successfully.',

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'payment_request',
                'status' => false,
                'message' => 'Bad Request.',

            ], 400);
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

                if ($user && $user->stop_payroll == 0) {
                    $history = UserOrganizationHistory::where('user_id', $user->id)->where('effective_date', '<=', now()->format('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                    if ($history) {
                        $subPosition = $history->sub_position_id;
                    } else {
                        $subPosition = $user->sub_position_id;
                    }
                    $payFrequency = $this->payFrequencyNew($date, $subPosition, $user->id);
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
                    if (isset($request->declined_at) && $request->declined_at != null) {
                        $declined_at = $request->declined_at;
                    } else {
                        $declined_at = null;
                    }

                    $check = payroll::where(['user_id' => $appuser->user_id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'status' => 2])->count();
                    if ($check == 0) {

                        $payRoll = PayRoll::where(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'user_id' => $appuser->user_id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first();
                        // $payRoll = PayRoll::where(['user_id'=> $appuser->user_id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first();
                        if (empty($payRoll)) {
                            $payRoll = PayRoll::create(
                                [
                                    'user_id' => $user->id,
                                    'position_id' => $user->position_id,
                                    'adjustment' => $appuser->amount,
                                    'pay_period_from' => isset($payFrequency->pay_period_from) ? $payFrequency->pay_period_from : null,
                                    'pay_period_to' => isset($payFrequency->pay_period_to) ? $payFrequency->pay_period_to : null,
                                    'status' => 1,
                                ]
                            );
                        } else {
                            $payRoll->adjustment += $appuser->amount;
                            $payRoll->status = 1;
                            $payRoll->finalize_status = 0;
                            $payRoll->save();
                        }
                    } else {
                        $msg = 'Cannot send to payroll. this pay period has been Already Finalize for this employee.';

                        return response()->json([
                            'ApiName' => 'update_payment_request',
                            'status' => false,
                            'message' => $msg,
                        ], 400);
                    }

                    $update = [
                        'status' => $status,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'declined_at' => $declined_at,
                        'payroll_id' => $payRoll->id,
                    ];
                    $paymentRequest = ApprovalsAndRequest::where(['id' => $value, 'status' => 'Approved'])->update($update);

                    if ($appuser->adjustment_type_id == 4) {
                        // $create = [
                        //     'user_id' => $appuser->user_id,
                        //     'manager_id' => $appuser->manager_id,
                        //     'approved_by' => $appuser->approved_by,
                        //     'adjustment_type_id' => $appuser->adjustment_type_id,
                        //     'state_id' => $appuser->state_id,
                        //     'dispute_type' => $appuser->dispute_type,
                        //     'customer_pid' => $appuser->customer_pid,
                        //     'cost_tracking_id' => $appuser->cost_tracking_id,
                        //     'cost_date' => $appuser->cost_date,
                        //     'request_date' => $appuser->request_date,
                        //     'amount'  => (0 - $appuser->amount),
                        //     'status' => $status,
                        //     'pay_period_from' => isset($startDateNext)? $startDateNext : null,
                        //     'pay_period_to' => isset($endDateNext)? $endDateNext : null,
                        // ];
                        // $add = ApprovalsAndRequest::create($create);
                        // $payRoll = PayRoll::where(['user_id'=> $appuser->user_id, 'pay_period_from' => $startDateNext, 'pay_period_to' => $endDateNext])->first();
                        // if (empty($payRoll)) {
                        //     PayRoll::create(
                        //         [
                        //             'user_id'     => $user->id,
                        //             'position_id' => $user->position_id,
                        //             'adjustment'  => (0 - $appuser->amount),
                        //             'pay_period_from' => isset($startDateNext)? $startDateNext : null,
                        //             'pay_period_to' => isset($endDateNext)? $endDateNext : null,
                        //             'status'      => 1,
                        //         ]
                        //     );
                        // }
                    }

                }

                if ($user && $user->stop_payroll == 1) {
                    $i++;
                }

            }

            if (count($request->request_ids) == 1) {
                if ($i > 0) {
                    $msg = 'Cannot send to payroll. Payroll has been stopped for this employee.';

                    return response()->json([
                        'ApiName' => 'update_payment_request',
                        'status' => false,
                        'message' => $msg,
                    ], 400);
                }

            } else {
                if ($i > 0) {
                    $msg = 'Some users Cannot send to payroll. Because Payroll has been stopped for these employee.';

                    return response()->json([
                        'ApiName' => 'update_payment_request',
                        'status' => false,
                        'message' => $msg,
                    ], 400);
                }
            }
        }

        return response()->json([
            'ApiName' => 'update_payment_request',
            'status' => true,
            'message' => 'Successfully.',
            //
        ], 200);

    }

    // public function commissionDetails($id)
    // {
    //     $data = array();
    //     $Payroll = Payroll::where('id', $id)->first();
    //     //dd($Payroll);
    //     if($Payroll){

    //         // $usercommission = UserCommission::with('userdata', 'saledata')->where('status',$Payroll->status)->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();

    //         if($Payroll->status==3){
    //             $usercommission = UserCommission::with('userdata', 'saledata')->where('status',$Payroll->status)->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();
    //         }else{
    //             $usercommission = UserCommission::with('userdata', 'saledata')->where('status','<','3')->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();
    //         }

    //         $clawbackSettlement = ClawbackSettlement::with('users', 'salesDetail')->where(['user_id' =>  $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
    //         //return $clawbackSettlement;
    //         $subtotal = 0;
    //         if (count($usercommission) > 0) {
    //             foreach ($usercommission as $key => $value) {
    //                 $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id'=> $id, 'user_id'=> $Payroll->user_id, 'pid'=> $value->pid, 'payroll_type' =>'commission', 'type'=> $value->amount_type])->first();

    //                 if($value->amount_type =='m1'){
    //                     $date = isset($value->saledata->m1_date)?$value->saledata->m1_date:'';
    //                 }else{
    //                     $date = isset($value->saledata->m2_date)?$value->saledata->m2_date:'';
    //                 }
    //                 $data['data'][] = [
    //                     'id' => $value->id,
    //                     'pid' => $value->pid,
    //                     'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
    //                     'customer_state' => isset($value->saledata->customer_state) ? $value->saledata->customer_state : null,
    //                     //'rep_redline' => isset($value->userdata->redline) ? $value->userdata->redline : null,
    //                     'rep_redline' => isset($value->redline) ? $value->redline : null,
    //                     'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
    //                     'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
    //                     'amount' => isset($value->amount) ? $value->amount : null,
    //                     // 'date' => isset($value->date) ? $value->date : null,
    //                     'date' => isset($date) ? $date : null,
    //                     'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
    //                     'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
    //                     'amount_type' => isset($value->amount_type) ? $value->amount_type : null,
    //                     'adders' => isset($value->adders) ? $value->adders : null,
    //                     'commission_adjustment'=> isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,

    //                 ];
    //                 $subtotal = ($subtotal + $value->amount);
    //             }
    //             $data['subtotal'] = $subtotal;
    //         }

    //         if (count($clawbackSettlement) > 0) {
    //             foreach ($clawbackSettlement as $key1 => $val) {
    //                 $data['data'][] = [
    //                     'id' => $val->id,
    //                     'pid' => $val->pid,
    //                     'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
    //                     'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
    //                     'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
    //                     'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
    //                     'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
    //                     'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
    //                     'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
    //                     'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
    //                     'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
    //                     'amount_type' => 'clawback',
    //                     'adders' => isset($val->adders) ? $val->adders : null,
    //                 ];
    //                 $subtotal = ($subtotal - $val->clawback_amount);
    //             }
    //             $data['subtotal'] = $subtotal;
    //         }
    //             return response()->json([
    //                 'ApiName' => 'commission_details',
    //                 'status' => true,
    //                 'message' => 'Successfully.',
    //                 'payroll_status' => $Payroll->status,
    //                 'data' => $data,
    //             ], 200);
    //     }else{

    //         return response()->json([
    //             'ApiName' => 'commission_details',
    //             'status' => true,
    //             'message' => 'No Records.',
    //             'data' => [],
    //         ], 400);

    //     }

    // }

    // commissionDetails for post request
    public function commissionDetails(Request $request)
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $payroll_id = $request->id; // payroll_id
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        if (! empty($Payroll)) {
            $PayrollCheck = Payroll::where(['id' => $id, 'user_id' => $user_id])->first();
            $isMarkAsPaid = (isset($PayrollCheck->is_mark_paid) && $PayrollCheck->is_mark_paid == 1) ? 1 : 0;
            // $isNextPayroll = (isset($PayrollCheck->is_next_payroll) && $PayrollCheck->is_next_payroll==1)? 1 : 0;
            $isNextPayroll = isset($PayrollCheck->is_next_payroll) ? $PayrollCheck->is_next_payroll : 0;

            // $usercommission = UserCommission::with('userdata', 'saledata')->where('status',$Payroll->status)->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();

            if ($Payroll->status == 3) {
                $usercommission = UserCommission::with('userdata', 'saledata')
                    ->where('status', $Payroll->status)
                    ->where('is_mark_paid', $isMarkAsPaid)
                    ->where('is_next_payroll', $isNextPayroll)
                    ->where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $Payroll->user_id,
                        'pay_period_from' => $Payroll->pay_period_from,
                        'pay_period_to' => $Payroll->pay_period_to,
                    ])
                    ->get();
            } else {
                //    $usercommission = UserCommission::where('status','<','3')
                $usercommission = UserCommission::whereIn('status', [1, 2, 6])
                    ->where('is_mark_paid', $isMarkAsPaid)
                    ->where('is_next_payroll', $isNextPayroll)
                    ->where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $Payroll->user_id,
                        'pay_period_from' => $Payroll->pay_period_from,
                        'pay_period_to' => $Payroll->pay_period_to,
                    ])
                    ->get();
            }

            $clawbackSettlement = ClawbackSettlement::with('users', 'salesDetail')
                ->where('is_mark_paid', $isMarkAsPaid)
                ->where('type', '!=', 'overrides')
                ->where([
                    'payroll_id' => $payroll_id,
                    'user_id' => $Payroll->user_id,
                    'clawback_type' => 'next payroll',
                    'pay_period_from' => $Payroll->pay_period_from,
                    'pay_period_to' => $Payroll->pay_period_to,
                ])
                ->get();
            // return $clawbackSettlement;
            $subtotal = 0;
            if (count($usercommission) > 0) {
                foreach ($usercommission as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type])->first();

                    if ($value->amount_type == 'm1') {
                        $date = isset($value->saledata->m1_date) ? $value->saledata->m1_date : '';
                    } else {
                        $date = isset($value->saledata->m2_date) ? $value->saledata->m2_date : '';
                    }

                    $location_data = Locations::with('State')->where('general_code', '=', $value->saledata->customer_state)->first();
                    if ($location_data) {
                        $state_code = $location_data->state->state_code;
                    } else {
                        $state_code = null;
                    }
                    $data['data'][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'state_id' => $state_code,
                        'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                        'customer_state' => isset($value->saledata->customer_state) ? $value->saledata->customer_state : null,
                        // 'rep_redline' => isset($value->userdata->redline) ? $value->userdata->redline : null,
                        'rep_redline' => isset($value->redline) ? $value->redline : null,
                        'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
                        'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        // 'date' => isset($value->date) ? $value->date : null,
                        'date' => isset($date) ? $date : null,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        'amount_type' => isset($value->amount_type) ? $value->amount_type : null,
                        'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                        'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'position_id' => $value->position_id,
                        'is_onetime_payment' => $value->is_onetime_payment,

                    ];
                    $subtotal = ($subtotal + $value->amount);
                }
                $data['subtotal'] = $subtotal;
            }

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $key1 => $val) {

                    $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $val->pid, 'payroll_type' => 'commission', 'type' => 'clawback'])->first(); // $val->type

                    $location_data = Locations::with('State')->where('general_code', '=', $val->salesDetail->customer_state)->first();
                    if ($location_data) {
                        $state_code = $location_data->state->state_code;
                    } else {
                        $state_code = null;
                    }
                    $returnSalesDate = isset($val->salesDetail->return_sales_date) ? $val->salesDetail->return_sales_date : null;
                    $data['data'][] = [
                        'id' => $val->id,
                        'pid' => $val->pid,
                        'state_id' => $state_code,
                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                        'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
                        'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                        'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                        'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : $returnSalesDate,
                        'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                        'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                        // this is clawback adjustment
                        'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'amount_type' => 'clawback',
                        'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                        'is_onetime_payment' => $val->is_onetime_payment,
                    ];
                    $subtotal = ($subtotal - $val->clawback_amount);
                }
                $data['subtotal'] = $subtotal;
            }

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);

        }
    }

    public function updateUserCommission(Request $request)
    {
        $data = [];
        // return $request->commission;
        if (count($request->commission) > 0) {
            foreach ($request->commission as $key => $value) {
                $update = ['amount' => $value['amount']];
                $paymentRequest = UserCommission::where('id', $value['id'])->update($update);

            }
        }

        return response()->json([
            'ApiName' => 'update_user_commission',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function reconciliationDetails_old(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;

        // $checkFinalize = UserReconciliationWithholding::where(['status'=>'finalize'])->whereBetween('created_at', [$startDate, $endDate])->first();
        $checkFinalize = UserReconciliationCommission::where('status', 'finalize')->where(['period_from' => $startDate, 'period_to' => $endDate])->first();

        if ($checkFinalize) {
            $checkFinalizeStatus = 1;
            // $reconciliation = UserReconciliationWithholding::where('status', 'finalize')->whereBetween('created_at', [$startDate, $endDate])->get();
            $reconciliation = UserReconciliationCommission::where('status', 'finalize')->where(['period_from' => $startDate, 'period_to' => $endDate])->get();
        } else {
            $checkFinalizeStatus = 0;
            // $reconciliation = UserReconciliationWithholding::where('status', 'unpaid')->whereBetween('created_at', [$startDate, $endDate])->get();
            $reconciliation = UserReconciliationCommission::where('status', 'Pending')->where(['period_from' => $startDate, 'period_to' => $endDate])->get();
        }

        $users = [];
        if (count($reconciliation) > 0) {
            foreach ($reconciliation as $key => $value) {
                $users[] = $value->user_id;
            }
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $data = User::where('first_name', 'LIKE', '%'.$search.'%')->orWhere('last_name', 'LIKE', '%'.$search.'%')->get();
            foreach ($data as $datas) {
                $userids[] = $datas->id;
            }
            // return $userids;
            $users = array_intersect($userids, $users);
        }
        // return $users;
        if (count($users) > 0) {
            $subtotal = 0;
            foreach ($users as $key1 => $userid) {
                $userdata = User::where('id', $userid)->first();

                // $commissionWithholding = UserReconciliationWithholding::where('closer_id', $userid)->orWhere('setter_id', $userid)
                // ->whereBetween('created_at', [$startDate, $endDate])
                // ->sum('withhold_amount');
                $commission = UserReconciliationCommission::where('user_id', $userid)->where(['period_from' => $startDate, 'period_to' => $endDate])->first();
                $commissionWithholding = $commission->amount;

                $totalOverRideDue = UserOverrides::where('user_id', $userid)
                    ->where('overrides_settlement_type', 'reconciliation')
                    ->where('status', '1')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('overrides_amount');

                $totalClawbackDue = ClawbackSettlement::where('user_id', $userid)
                    ->where('clawback_type', 'reconciliation')
                    ->where('status', '1')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('clawback_amount');

                $reconciliationsAdjustment = ReconciliationsAdjustement::where('user_id', $userid)->first();
                $commissionDue = isset($reconciliationsAdjustment->commission_due) ? $reconciliationsAdjustment->commission_due : 0;
                $overridesDue = isset($reconciliationsAdjustment->overrides_due) ? $reconciliationsAdjustment->overrides_due : 0;
                $deductionsDue = isset($reconciliationsAdjustment->deductions_due) ? $reconciliationsAdjustment->deductions_due : 0;

                $totalAdjustments = $commissionDue + $overridesDue + $deductionsDue;

                $total_due = ($commissionWithholding + $totalOverRideDue + ($totalClawbackDue) + ($totalAdjustments));
                $myArray[] = [
                    'id' => $commission->id,
                    'user_id' => $userid,
                    'emp_img' => $userdata->image,
                    'emp_name' => $userdata->first_name.' '.$userdata->last_name,
                    'commissionWithholding' => $commissionWithholding,
                    'overrideDue' => $totalOverRideDue,
                    'clawbackDue' => $totalClawbackDue,
                    'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                    'total_due' => $total_due,
                ];
            }
        }

        $data = $this->paginate($myArray);

        // return view('paginate', compact('data'));

        return response()->json([
            'ApiName' => 'reconciliation_details',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => $checkFinalizeStatus,
            'data' => $data,
        ], 200);

    }

    public function ReconciliationList(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }

        $checkFinalize = UserReconciliationCommission::where('status', 'finalize')->where(['period_from' => $startDate, 'period_to' => $endDate])->first();

        if ($checkFinalize) {
            $checkFinalizeStatus = 1;

            $reconciliation = UserReconciliationCommission::where('status', 'finalize')->where(['period_from' => $startDate, 'period_to' => $endDate]);
        } else {
            $checkFinalizeStatus = 0;

            $reconciliation = UserReconciliationCommission::where('status', 'pending')->where(['period_from' => $startDate, 'period_to' => $endDate]);
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $userids = User::where('first_name', 'LIKE', '%'.$search.'%')->orWhere('last_name', 'LIKE', '%'.$search.'%')->pluck('id')->toArray();

            $reconciliation->where(function ($query) use ($userids) {
                return $query->whereIn('user_id', $userids);
            });
        }
        $result = $reconciliation->get();
        // return $result;
        if (count($result) > 0) {
            foreach ($result as $key1 => $val) {
                $userdata = User::where('id', $val->user_id)->first();

                $commissionWithholding = $val->amount;
                $totalOverRideDue = '';
                $totalClawbackDue = '';

                // $totalOverRideDue = UserOverrides::where('sale_user_id', $val->user_id)
                $totalOverRideDue = UserOverrides::where('user_id', $val->user_id)
                    ->where('overrides_settlement_type', 'reconciliation')
                    ->where('status', '1')
                    ->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])
                    ->sum('amount');

                $totalClawbackDue = ClawbackSettlement::where('user_id', $val->user_id)
                    ->where('clawback_type', 'reconciliation')
                    ->where('status', '1')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('clawback_amount');

                if (! empty($totalOverRideDue)) {

                    $update = UserReconciliationCommission::where('id', $val->id)->update(['overrides' => $totalOverRideDue]);
                } else {

                    $totalOverRideDue = 0;
                }
                if (! empty($totalClawbackDue)) {

                    $update = UserReconciliationCommission::where('id', $val->id)->update(['clawbacks' => $totalClawbackDue]);
                } else {
                    $totalClawbackDue = 0;
                }

                $reconciliationsAdjustment = ReconciliationsAdjustement::where('reconciliation_id', $val->id)->first();
                $commissionDue = isset($reconciliationsAdjustment->commission_due) ? $reconciliationsAdjustment->commission_due : 0;
                $overridesDue = isset($reconciliationsAdjustment->overrides_due) ? $reconciliationsAdjustment->overrides_due : 0;
                $clawbackDue = isset($reconciliationsAdjustment->clawback_due) ? $reconciliationsAdjustment->clawback_due : 0;

                $totalAdjustments = $commissionDue + $overridesDue + $clawbackDue;

                $total_due = ($commissionWithholding + $totalOverRideDue + ($totalClawbackDue) + ($totalAdjustments));

                if ($val->status == 'pending') {

                    $updateData = UserReconciliationCommission::where('id', $val->id)->update(['total_due' => $total_due]);
                }
                $reconciliationData = UserReconciliationCommission::where('id', $val->id)->first();
                if (isset($userdata->image) && $userdata->image != null) {
                    $image_s3 = s3_getTempUrl(config('app.domain_name').'/'.$userdata->image);
                } else {
                    $image_s3 = null;
                }
                $myArray[] = [
                    'id' => $reconciliationData->id,
                    'user_id' => $reconciliationData->user_id,
                    'emp_img' => $userdata->image,
                    'emp_img_s3' => $image_s3,
                    'emp_name' => $userdata->first_name.' '.$userdata->last_name,
                    'commissionWithholding' => $reconciliationData->amount,
                    'overrideDue' => $reconciliationData->overrides,
                    'clawbackDue' => $reconciliationData->clawbacks,
                    'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                    'total_due' => $reconciliationData->total_due,
                ];
            }
        }
        // code for sorting result by employee name ASC
        $emp_name = array_column($myArray, 'emp_name');
        array_multisort($emp_name, SORT_ASC, $myArray);

        $data = $this->paginates($myArray, $perpage);

        return response()->json([
            'ApiName' => 'reconciliation_details',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => $checkFinalizeStatus,
            'data' => $data,
        ], 200);

    }

    public function ReconciliationListPayRoll(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $recon_payout = $request->recon_payout;
        $request->position_id;
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        if ($position_id == 'all' && $office_id != 'all') {
            $userId = User::whereIn('office_id', $officeId);

        } elseif ($office_id == 'all' && $position_id != 'all') {
            $userId = User::whereIn('sub_position_id', $positionId);
        } elseif ($office_id == 'all' && $position_id == 'all') {
            $userId = User::orderBy('id', 'desc');
        } else {
            $userId = User::whereIn('office_id', $officeId)->whereIn('sub_position_id', $positionId);
        }
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            if ($request->has('search') && ! empty($request->input('search'))) {

                $userId->where(function ($query) use ($request) {
                    return $query->where('first_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
            }
        }

        $userIds = $userId->pluck('id');
        $position_id = $userId->pluck('sub_position_id')->toArray();

        $pid = UserReconciliationWithholding::whereIn('closer_id', $userIds)->where('finalize_status', 0)->where('status', 'unpaid')->orWhereIn('setter_id', $userIds)->where('status', 'unpaid')->where('finalize_status', 0)->pluck('pid');
        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid')->toArray();
        $userId = [];
        // return $salePid;
        $arrayPid = implode(',', $salePid);
        $userDatas = UserReconciliationWithholding::whereIn('pid', $salePid)
            ->where('finalize_status', 0)
            ->whereIn('closer_id', $userIds)
            ->orWhereIn('setter_id', $userIds)
            ->where('finalize_status', 0)
            ->whereIn('pid', $salePid)->get();
        foreach ($userDatas as $userData) {
            $uid[] = isset($userData->closer_id) ? $userData->closer_id : $userData->setter_id;
            $userId = array_unique($uid);
        }
        // return $userId;
        // foreach($userId as $userId)
        // {
        $closerSetter = UserReconciliationWithholding::where('finalize_status', 0)
            ->whereIn('pid', $salePid)
            ->whereIn('closer_id', $userId)
            ->groupBY('closer_id')
            ->orWhereIn('setter_id', $userId)
            ->where('finalize_status', 0)
            ->whereIn('pid', $salePid)
            ->groupBy('setter_id')
            ->get();

        foreach ($closerSetter as $closerSetters) {
            $userId = isset($closerSetters->closer_id) ? $closerSetters->closer_id : $closerSetters->setter_id;
            $userInfo = User::where('id', $userId)->first();

            $withholdAmount = UserReconciliationWithholding::where('finalize_status', 0)
                ->whereIn('pid', $salePid)
                ->where('closer_id', $userId)
                ->orWhere('setter_id', $userId)
                ->where('finalize_status', 0)
                ->whereIn('pid', $salePid)
                ->sum('withhold_amount');

            $commissionWithholding = $withholdAmount;
            $totalOverRideDue = 0;
            $totalClawbackDue = 0;

            // $totalOverRideDue = UserOverrides::where('sale_user_id', $val->user_id)
            //   $totalOverRideDue = UserOverrides::where('user_id', $userId)
            //         ->where('overrides_settlement_type','reconciliation')
            //         ->where('status', '1')
            //         ->whereIn('pid',$salePid)
            //         //->whereBetween('created_at', [$startDate,$endDate])
            //         ->sum('amount');

            $totalOverRideDue = UserOverrides::where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
            $totalOverRideDue->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $salePid) {
                return $query->where('pid', $salePid)
                    ->whereBetween('m2_date', [$startDate, $endDate]);
            });
            $totalOverRideDue = $totalOverRideDue->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->sum('amount');

            $totalClawbackDue = ClawbackSettlement::where('user_id', $userId)->where('payroll_id', 0);
            $totalClawbackDue->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $salePid) {
                return $query->where('pid', $salePid)
                    ->whereBetween('date_cancelled', [$startDate, $endDate]);
            });
            $totalClawbackDue->where('clawback_type', 'reconciliation');
            $totalClawbackDue->where('status', '1');
            $totalClawbackDue->whereIn('pid', $salePid);
            $totalClawbackDue = $totalClawbackDue->with('salesDetail')->sum('clawback_amount');

            // $reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type','reconciliations')->where('user_id', $userId)->where('pid',$salePids)->whereBetween('created_at', [$startDate,$endDate])->first();
            $reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('payroll_status', null)->where('user_id', $userId)->whereIn('pid', $salePid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate);
            $reconCommission = $reconciliationsAdjustment->sum('commission_due');
            $reconoverRide = $reconciliationsAdjustment->sum('overrides_due');
            $reimbursement = $reconciliationsAdjustment->sum('reimbursement');
            $deduction = $reconciliationsAdjustment->sum('deduction');
            $adjustment = $reconciliationsAdjustment->sum('adjustment');
            $reimbursement = $reconciliationsAdjustment->sum('reimbursement');

            $commissionDue = isset($reconCommission) ? $reconCommission : 0;
            $overridesDue = isset($reconoverRide) ? $reconoverRide : 0;
            // $clawbackDue = isset($reconClawback)?$reconClawback:0;
            $reimbursement = isset($reimbursement) ? $reimbursement : 0;
            $deduction = isset($deduction) ? $deduction : 0;
            $adjustment = isset($adjustment) ? $adjustment : 0;
            $reconciliation = isset($reconciliation) ? $reconciliation : 0;

            $totalAdjustments = $commissionDue + $overridesDue + $reimbursement + $deduction + $adjustment + $reconciliation;
            // $total_due = ($commissionWithholding + $totalOverRideDue + ($totalClawbackDue) + ($totalAdjustments));
            $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'skipped')->first();
            if (isset($recUser) && $recUser != '') {
                $userSkip = 1;
            } else {
                $userSkip = 0;
            }

            $payrollPidCommissionGet = ReconciliationFinalizeHistory::whereIn('pid', $salePid)->where('user_id', $userId)->where('status', 'payroll');
            // $payrollPidCommission = $payrollPidCommissionGet->sum('commission');
            $payrollPidCommission = $payrollPidCommissionGet->sum('paid_commission');
            // $payrollPidOverride = $payrollPidCommissionGet->sum('override');
            $payrollPidOverride = $payrollPidCommissionGet->sum('paid_override');
            $payrollPidAdjustment = $payrollPidCommissionGet->sum('adjustments');
            $payrollPidClawback = $payrollPidCommissionGet->sum('clawback');
            $getCommissionPersontage = $payrollPidCommissionGet->first();

            if ($payrollPidCommission > 0 && $getCommissionPersontage->payout != null) {
                $persontage = $getCommissionPersontage->payout;
                $paidCommission = $payrollPidCommission;
            } else {
                $paidCommission = 0;
            }

            if ($payrollPidOverride > 0 && $getCommissionPersontage->payout != null) {
                $persontage = $getCommissionPersontage->payout;
                $paidOverride = $payrollPidOverride;
            } else {
                $paidOverride = 0;
            }
            if ($payrollPidAdjustment > 0) {
                $pidAdjustment = $payrollPidAdjustment;
            } else {
                $pidAdjustment = 0;
            }

            if ($payrollPidClawback > 0) {
                $pidClawback = $payrollPidClawback;
            } else {
                $pidClawback = 0;
            }

            $payrollPid = $paidCommission + $paidOverride;

            $addjustment = $totalAdjustments;

            $total_due = ($commissionWithholding + $totalOverRideDue);

            if ($total_due > 0 || $total_due < 0) {
                $totalDues = $total_due - $payrollPid;
                $pay = ($totalDues * $recon_payout) / 100;
            } else {
                $pay = 0;
            }
            if (isset($userInfo->image) && $userInfo->image != null) {
                $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$userInfo->image);
            } else {
                $s3_image = null;
            }
            $myArray[] = [
                'id' => $closerSetters->id,
                'user_id' => $userId,
                'pid' => $arrayPid,
                'emp_img' => isset($userInfo->image) ? $userInfo->image : null,
                'emp_img_s3' => $s3_image,
                'emp_name' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                'commissionWithholding' => $commissionWithholding - $paidCommission,
                'overrideDue' => isset($totalOverRideDue) ? $totalOverRideDue - $paidOverride : 0,
                'total_due' => $total_due - $payrollPid,
                'pay' => $recon_payout,
                'total_pay' => $pay,
                'clawbackDue' => isset($totalClawbackDue) ? $totalClawbackDue : 0,
                'totalAdjustments' => isset($addjustment) ? $addjustment : 0,
                'payout' => ($pay + $addjustment - $totalClawbackDue),
                'already_paid' => $payrollPid + $pidAdjustment,
                'user_skip' => $userSkip,
            ];
        }
        // ------------S payroll record ---------------
        // $getPayrollToreconUid = UserReconciliationWithholding::where('finalize_status',0)->where('status','unpaid')->where('payroll_to_recon_status',1)->whereBetween('created_at',[$startDate,$endDate])->get();
        // $uids=[];
        // $payrollUserId=[];
        // foreach($getPayrollToreconUid as $getPayrollTorecon)
        //   {
        //       $uids[] = isset($getPayrollTorecon->closer_id)?$getPayrollTorecon->closer_id:$getPayrollTorecon->setter_id;
        //   }
        // $payrollUserId = array_unique($uids);
        // $payrollCloserSetters = UserReconciliationWithholding::where('finalize_status',0)
        // ->where('payroll_to_recon_status',1)
        // ->whereIn('closer_id',$payrollUserId)
        // ->groupBY('closer_id')
        // ->orWhereIn('setter_id',$payrollUserId)
        // ->where('finalize_status',0)
        // ->where('payroll_to_recon_status',1)
        // ->groupBy('setter_id')
        // ->get();

        // foreach($payrollCloserSetters as $payrollCloserSetter)
        // {
        //     $userId = isset($payrollCloserSetter->closer_id)?$payrollCloserSetter->closer_id:$payrollCloserSetter->setter_id;
        //     $userInfo = User::where('id', $userId)->first();
        //     $withholdAmount = UserReconciliationWithholding::where('finalize_status',0)
        //                     //->whereIn('pid',$salePid)
        //                     ->where('closer_id',$userId)
        //                     ->where('payroll_to_recon_status',1)
        //                     ->orWhere('setter_id',$userId)
        //                     ->where('finalize_status',0)
        //                     ->where('payroll_to_recon_status',1)
        //                     //->whereIn('pid',$salePid)
        //                     ->sum('withhold_amount');

        //     $commissionWithholding = $withholdAmount;
        //     $totalOverRideDue = 0;
        //     $totalClawbackDue = 0;

        //     //$totalOverRideDue = UserOverrides::where('sale_user_id', $val->user_id)
        //     // $totalOverRideDue = UserOverrides::where('user_id', $userId)
        //     //     ->where('overrides_settlement_type','reconciliation')
        //     //     ->where('status', '1')
        //     //     ->whereIn('pid',$salePid)
        //     //     //->whereBetween('created_at', [$startDate,$endDate])
        //     //     ->sum('amount');
        //     $totalOverRideDue = UserOverrides::where(['user_id'=>$userId, 'overrides_settlement_type'=>'reconciliation','status'=>1]);
        //     $totalOverRideDue->whereHas('salesDetail',function($query) use ($startDate, $endDate) {
        //         return $query->whereBetween('m2_date' , [$startDate,$endDate]);
        //         });
        //     $totalOverRideDue = $totalOverRideDue->with('salesDetail','userpayrolloverride')->where(['user_id'=>$userId, 'overrides_settlement_type'=>'reconciliation','status'=>1])->sum('amount');

        //         $totalClawbackDue = ClawbackSettlement::where('user_id', $userId);
        //         $totalClawbackDue->whereHas('salesDetail',function($query) use ($startDate, $endDate) {
        //             return $query->whereBetween('date_cancelled' , [$startDate,$endDate])
        //                     ->where('status',3);
        //             });

        //             $totalClawbackDue->where('clawback_type','reconciliation');
        //             $totalClawbackDue->where('status', '1');
        //             $totalClawbackDue->whereIn('pid',$salePid);
        //             //->whereBetween('created_at', [$startDate,$endDate])
        //             $totalClawbackDue = $totalClawbackDue->with('salesDetail')->sum('clawback_amount');

        //         //$reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type','reconciliations')->where('user_id', $userId)->where('pid',$salePids)->whereBetween('created_at', [$startDate,$endDate])->first();
        //         $reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type','reconciliations')->where('user_id', $userId)->where('payroll_move_status','from_payroll');
        //         //->whereIn('pid',$salePid);
        //         $reconCommission =  $reconciliationsAdjustment->sum('commission_due');
        //         $reconoverRide =  $reconciliationsAdjustment->sum('overrides_due');
        //         $reimbursement =  $reconciliationsAdjustment->sum('reimbursement');
        //         $deduction =  $reconciliationsAdjustment->sum('deduction');
        //         $adjustment =  $reconciliationsAdjustment->sum('adjustment');
        //         $reimbursement =  $reconciliationsAdjustment->sum('reimbursement');

        //         $commissionDue = isset($reconCommission)?$reconCommission:0;
        //         $overridesDue  = isset($reconoverRide)?$reconoverRide:0;
        //         //$clawbackDue = isset($reconClawback)?$reconClawback:0;
        //         $reimbursement  = isset($reimbursement)?$reimbursement:0;
        //         $deduction  = isset($deduction)?$deduction:0;
        //         $adjustment  = isset($adjustment)?$adjustment:0;
        //         $reconciliation  = isset($reconciliation)?$reconciliation:0;

        //       $totalAdjustments = $commissionDue+$overridesDue+$reimbursement+$deduction+$adjustment+$reconciliation;

        //         $reconciliationsAdjustmentPayRoll = ReconciliationsAdjustement::where('adjustment_type','reconciliations')->where('user_id', $userId)->where('payroll_move_status','from_payroll')->whereBetween('created_at', [$startDate,$endDate])->first();
        //         $commissionDuePayRoll = isset($reconciliationsAdjustmentPayRoll->commission_due)?$reconciliationsAdjustmentPayRoll->commission_due:0;
        //         $overridesDuePayRoll  = isset($reconciliationsAdjustmentPayRoll->overrides_due)?$reconciliationsAdjustmentPayRoll->overrides_due:0;
        //         $reimbursementPayRoll = isset($reconciliationsAdjustmentPayRoll->reimbursement)?$reconciliationsAdjustmentPayRoll->reimbursement:0;
        //         $deductionPayRoll = isset($reconciliationsAdjustmentPayRoll->deduction)?$reconciliationsAdjustmentPayRoll->deduction:0;
        //         $adjustmentPayRoll = isset($reconciliationsAdjustmentPayRoll->adjustment)?$reconciliationsAdjustmentPayRoll->adjustment:0;
        //         $reconciliationPayRoll = isset($reconciliationsAdjustmentPayRoll->reconciliation)?$reconciliationsAdjustmentPayRoll->reconciliation:0;

        //         $totalAdjustmentsPayRoll = $commissionDuePayRoll+$overridesDuePayRoll+$reimbursementPayRoll+$reconciliationPayRoll+$adjustmentPayRoll-$deductionPayRoll;

        //         //$totalAdjustments=$totalAdjustments+$totalAdjustmentsPayRoll;
        //         $totalAdjustments=$totalAdjustmentsPayRoll;
        //         //$total_due = ($commissionWithholding + $totalOverRideDue + ($totalClawbackDue) + ($totalAdjustments));

        //     $recUser = ReconciliationStatusForSkipedUser::where('user_id',$userId)->where('start_date',$startDate)->where('end_date',$endDate)->where('status','skipped')->first();
        //     if(isset($recUser) && $recUser!="")
        //     {
        //         $userSkip = 1;
        //     }else{
        //         $userSkip = 0;
        //     }

        //     $payrollPidCommissionGet = ReconciliationFinalizeHistory::where('user_id',$userId)->where('status','payroll');
        //     $payrollPidCommission = $payrollPidCommissionGet->sum('commission');
        //     $payrollPidOverride = $payrollPidCommissionGet->sum('override');
        //     $payrollPidAdjustment =$payrollPidCommissionGet->sum('adjustments');
        //     $payrollPidClawback = $payrollPidCommissionGet->sum('clawback');
        //     $getCommissionPersontage = $payrollPidCommissionGet->first();

        //     if($payrollPidCommission > 0 && $getCommissionPersontage->payout!=null)
        //     {
        //         $persontage = $getCommissionPersontage->payout;
        //         $paidCommission = ($payrollPidCommission*$persontage)/100;
        //     }else{
        //         $paidCommission = 0;
        //     }

        //     if($payrollPidOverride > 0 && $getCommissionPersontage->payout!=null)
        //     {
        //         $persontage = $getCommissionPersontage->payout;
        //         $paidOverride = ($payrollPidOverride*$persontage)/100;
        //     }else{
        //         $paidOverride = 0;
        //     }
        //     $payrollPid = $paidCommission+$paidOverride;
        //     $addAdjustment = UserReconciliationWithholding::where('closer_id',$userId)
        //     ->where('finalize_status',0)
        //     ->where('status','unpaid')
        //     ->orWhere('setter_id',$userId)
        //     ->where('finalize_status',0)
        //     ->whereIn('pid',$salePid)
        //     ->where('status','unpaid')
        //     ->sum('adjustment_amount');
        //     $addjustment  = $totalAdjustments;

        //     $total_due = ($commissionWithholding + $totalOverRideDue);

        //         if($total_due>0 || $total_due<0)
        //         {
        //             $totalDues = $total_due-$payrollPid;
        //             $pay  =  ($totalDues*$recon_payout)/100;
        //         }else{
        //             $pay  =  0;
        //         }

        //         if(isset($userInfo->image) && $userInfo->image!=null){
        //             $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$userInfo->image);
        //         }else{
        //             $s3_image = null;
        //         }
        //         $myArray[] = array(
        //             'id' => $payrollCloserSetter->id,
        //             'user_id' =>  $userId,
        //             'pid' =>  $salePid,
        //             'emp_img' => isset($userInfo->image)?$userInfo->image:null,
        //             'emp_img_s3' => $s3_image,
        //             'emp_name' => isset($userInfo->first_name)?$userInfo->first_name . ' ' . $userInfo->last_name:null,
        //             'commissionWithholding' => $commissionWithholding,
        //             //'overrideDue' => isset($overridesDue)?$overridesDue-$payrollPidOverride:0,
        //             'overrideDue' => 0,
        //             'total_due' => $commissionWithholding,
        //             'pay' => $recon_payout,
        //             'total_pay' => $pay,
        //             'total_pay' => 0,
        //             //'clawbackDue' => isset($totalClawbackDue)?$totalClawbackDue-$payrollPidClawback:0,
        //             'clawbackDue' => 0,
        //             'totalAdjustments' => isset($addjustment)?$addjustment-$payrollPidAdjustment:0,
        //             'payout' => ($addjustment-$totalClawbackDue),
        //             'already_paid' => $payrollPid,
        //             'user_skip' =>$userSkip
        //         );
        // }

        // ------------ E payroll record ---------------
        // }
        // code for sorting result by employee name ASC
        $emp_name = array_column($myArray, 'emp_name');
        array_multisort($emp_name, SORT_ASC, $myArray);
        // $data = $this->paginates($myArray, $perpage);
        $data = $myArray;

        if ($request->has('sort') && $request->input('sort') == 'commission') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'override') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'overrideDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'overrideDue'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'clawback') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'clawbackDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'clawbackDue'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'adjustments') {
            $val = $request->input('sort_val');
            //  $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'total_due') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_due'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_due'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'pay') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {

                array_multisort(array_column($data, 'pay'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'pay'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'payout') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'payout'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'payout'), SORT_ASC, $data);
            }
        }
        $data = $this->paginates($data, $perpage);

        return response()->json([
            'ApiName' => 'reconciliation_details',
            'status' => true,
            'message' => 'Successfully.',
            // 'finalize_status' =>$checkFinalizeStatus,
            'finalize_status' => 0,
            'data' => $data,
        ], 200);

    }

    public function ReconciliationListUserSkipped(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'start_date' => 'required',
                'end_date' => 'required',

            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        // return $search = $request->search;
        $user_id = implode(',', $request->user_id);
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $userIds = explode(',', $user_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);
        $selectType = $request->select_type;
        if ($selectType == 'all') {
            if ($position_id == 'all' && $office_id != 'all') {
                $userId = User::whereIn('office_id', $officeId);

            } elseif ($office_id == 'all' && $position_id != 'all') {
                $userId = User::whereIn('sub_position_id', $positionId);
            } elseif ($office_id == 'all' && $position_id == 'all') {
                $userId = User::orderBy('id', 'desc');
            } else {
                $userId = User::whereIn('office_id', $officeId)->whereIn('sub_position_id', $positionId);
            }

            $userIds = $userId->pluck('id');
            $pid = UserReconciliationWithholding::whereIn('closer_id', $userIds)->where('finalize_status', 0)->where('status', 'unpaid')->orWhereIn('setter_id', $userIds)->where('status', 'unpaid')->where('finalize_status', 0)->pluck('pid');
            $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
            $userId = [];

            foreach ($salePid as $salePids) {
                $userData = UserReconciliationWithholding::where('pid', $salePids)
                    ->where('finalize_status', 0)
                    ->whereIn('closer_id', $userIds)
                    ->orWhereIn('setter_id', $userIds)
                    ->where('finalize_status', 0)
                    ->where('pid', $salePids)->first();
                $userId = isset($userData->closer_id) ? $userData->closer_id : $userData->setter_id;

                $userData = User::where('id', $userId)->first();
                $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->first();
                if (! isset($recUser) && $recUser == '') {
                    ReconciliationStatusForSkipedUser::create([
                        'user_id' => $userId,
                        'office_id' => $userData->office_id,
                        'position_id' => $userData->sub_position_id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'skipped',
                    ]);
                }
            }
        } else {
            foreach ($userIds as $userId) {
                $userData = User::where('id', $userId)->first();
                $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->first();
                if (! isset($recUser) && $recUser == '') {
                    ReconciliationStatusForSkipedUser::create([
                        'user_id' => $userId,
                        'office_id' => $userData->office_id,
                        'position_id' => $userData->sub_position_id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'skipped',
                    ]);
                }
            }
        }

        return response()->json([
            'ApiName' => 'reconciliation user skipped',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function ReconciliationListUserSkippedUndo(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        // return $search = $request->search;
        $user_id = implode(',', $request->user_id);
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $userIds = explode(',', $user_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);
        foreach ($userIds as $userId) {
            $userData = User::where('id', $userId)->first();
            $recUser = ReconciliationStatusForSkipedUser::where('user_id', $userId)->where('status', 'skipped')->delete();
        }

        return response()->json([
            'ApiName' => 'reconciliation user status undo',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function ReconciliationListEdit(Request $request): JsonResponse
    {
        $id = $request->id;
        $pid = $request->pid;
        $userId = $request->user_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        if ($startDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Edit reconciliation commission adjustment',
                'status' => false,
                'message' => 'Please select start date and end date.',
            ], 400);
        }
        $adjustmentAmount = isset($request->adjust_amount) ? $request->adjust_amount : 0;
        $payout = UserReconciliationWithholding::where('closer_id', $userId)->where('id', $id)->where('pid', $pid)->orWhere('setter_id', $userId)->where('id', $id)->where('pid', $pid)->first();
        $payout->adjustment_amount = $adjustmentAmount;
        $payout->comment = $request->comment;
        $payout->save();

        // $finalizeHistory = ReconciliationFinalizeHistory::where('user_id',$userId)->where('pid',$pid)->first();
        // $finalizeHistory->adjustments =  $adjustmentAmount;
        // $finalizeHistory->save();

        $data = ReconciliationsAdjustement::where('user_id', $userId)->where('pid', $pid)->where('adjustment_type', 'reconciliations')->where('payroll_move_status', null)->where('type', 'commission')->first();
        if (isset($data) && $data != '') {
            $commiValu = isset($data->commission_due) ? $data->commission_due : 0;
            $data->user_id = $userId;
            $data->pid = $pid;
            $data->reconciliation_id = $payout->id;
            $data->comment = $request->comment;
            $data->adjustment_type = 'reconciliations';
            $data->commission_due = $adjustmentAmount;
            $data->start_date = $startDate;
            $data->end_date = $endDate;
            $data->save();
        } else {
            $data = ReconciliationsAdjustement::create(['user_id' => $userId, 'reconciliation_id' => $payout->id, 'pid' => $pid, 'comment' => $request->comment, 'adjustment_type' => 'reconciliations', 'commission_due' => $adjustmentAmount, 'start_date' => $startDate, 'end_date' => $endDate, 'type' => 'overrides', 'type' => 'commission']);
        }
        // $payout = UserReconciliationWithholding::where('closer_id',$userId)->where('id',$id)->where('pid',$pid)->orWhere('setter_id',$userId)->where('id',$id)->where('pid',$pid)->first();
        // $payout->adjustment_amount =  $adjustmentAmount;
        // $payout->save();

        return response()->json([
            'ApiName' => 'Edit reconciliation commission adjustment',
            'status' => true,
            'message' => 'Update Successfully.',
        ], 200);
        // ReconciliationsAdjustement

    }

    public function ReconciliationOverridesListEdit(Request $request): JsonResponse
    {
        $id = $request->id;
        $pid = $request->pid;
        $userId = $request->user_id;
        $type = $request->type;

        if ($request->start_date == '' && $request->end_date == '') {
            return response()->json([
                'ApiName' => 'Edit reconciliation override adjustment',
                'status' => false,
                'message' => 'Please select sart date and end date.',
            ], 400);
        }
        $adjustmentAmount = $request->adjust_amount;
        $payout = UserOverrides::where('user_id', $userId)->where('id', $id)->where('pid', $pid)->where('overrides_settlement_type', 'reconciliation')->first();
        if ($payout) {
            $payout->adjustment_amount = $adjustmentAmount;
            $payout->comment = $request->comment;
            $payout->save();
        }
        $data = ReconciliationsAdjustement::where('user_id', $userId)->where('pid', $pid)->where('payroll_move_status', null)->where('type', 'overrides')->first();
        if (isset($data) && $data != '') {
            $data->user_id = $userId;
            $data->pid = $pid;
            $data->comment = $request->comment;
            $data->adjustment_type = 'reconciliations';
            $data->overrides_due = $adjustmentAmount;
            $data->start_date = $request->start_date;
            $data->end_date = $request->end_date;
            $data->save();
        } else {
            $data = ReconciliationsAdjustement::create(['user_id' => $userId, 'pid' => $pid, 'comment' => $request->comment, 'adjustment_type' => 'reconciliations', 'overrides_due' => $adjustmentAmount, 'start_date' => $request->start_date, 'end_date' => $request->end_date, 'type' => 'overrides']);
        }
        // $payout = UserReconciliationWithholding::where('closer_id',$userId)->where('id',$id)->where('pid',$pid)->orWhere('setter_id',$userId)->where('id',$id)->where('pid',$pid)->first();
        // $payout->adjustment_amount =  $adjustmentAmount;
        // $payout->save();

        return response()->json([
            'ApiName' => 'Edit reconciliation override adjustment',
            'status' => true,
            'message' => 'Update Successfully.',
        ], 200);
    }

    public function payrollReconciliationHistory(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $reconciliation = UserReconciliationCommission::where('status', 'payroll')->where(['period_from' => $startDate, 'period_to' => $endDate]);
        $result = $reconciliation->get();
        // return $result;
        if (count($result) > 0) {
            foreach ($result as $key1 => $val) {
                $userdata = User::where('id', $val->user_id)->first();

                $reconciliationsAdjustment = ReconciliationsAdjustement::where('reconciliation_id', $val->id)->first();
                $commissionDue = isset($reconciliationsAdjustment->commission_due) ? $reconciliationsAdjustment->commission_due : 0;
                $overridesDue = isset($reconciliationsAdjustment->overrides_due) ? $reconciliationsAdjustment->overrides_due : 0;
                $clawbackDue = isset($reconciliationsAdjustment->clawback_due) ? $reconciliationsAdjustment->clawback_due : 0;

                $totalAdjustments = $commissionDue + $overridesDue + $clawbackDue;

                $myArray[] = [
                    'id' => $val->id,
                    'user_id' => $val->user_id,
                    'emp_img' => $userdata->image,
                    'emp_name' => $userdata->first_name.' '.$userdata->last_name,
                    'commissionWithholding' => $val->amount,
                    'overrideDue' => $val->overrides,
                    'clawbackDue' => $val->clawbacks,
                    'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                    'total_due' => $val->total_due,
                    'pay_period_from' => $val->pay_period_from,
                    'pay_period_to' => $val->pay_period_to,
                ];
            }
        }

        // code for sorting result by employee name  ASC
        $emp_name = array_column($myArray, 'emp_name');
        array_multisort($emp_name, SORT_ASC, $myArray);

        $data = $this->paginate($myArray);

        return response()->json([
            'ApiName' => 'payrollReconciliationHistory',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => 1,
            'data' => $data,
        ], 200);

    }

    public function reconciliationFinalizeDraft(Request $request)
    {
        $data = [];
        $commission = 0;
        $overrides = 0;
        $clawback = 0;
        $adjusment = 0;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $reconPayout = $request->recon_payout;
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }

        if ($position_id == 'all' && $office_id != 'all') {
            $userId = User::whereIn('office_id', $officeId);

        } elseif ($office_id == 'all' && $position_id != 'all') {
            $userId = User::whereIn('sub_position_id', $positionId);
        } elseif ($office_id == 'all' && $position_id == 'all') {
            $userId = User::orderBy('id', 'desc');
        } else {
            $userId = User::whereIn('office_id', $officeId)->whereIn('sub_position_id', $positionId);
        }

        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            if ($request->has('search') && ! empty($request->input('search'))) {

                $userId->where(function ($query) use ($request) {
                    return $query->where('first_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
            }
        }

        $userIds = $userId->pluck('id');

        $pid = UserReconciliationWithholding::whereIn('closer_id', $userIds)->where('status', 'unpaid')->where('finalize_status', 0)->orWhereIn('setter_id', $userIds)->where('status', 'unpaid')->where('finalize_status', 0)->pluck('pid');
        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
        $userId = [];
        foreach ($salePid as $salePids) {
            $userData = UserReconciliationWithholding::where('pid', $salePids)->where('finalize_status', 0)->where('status', 'unpaid')->first();
            $closerSetter = UserReconciliationWithholding::where('pid', $salePids)->where('finalize_status', 0)->where('status', 'unpaid')->get();
            foreach ($closerSetter as $closerSetters) {
                $userId = isset($closerSetters->closer_id) ? $closerSetters->closer_id : $closerSetters->setter_id;
                $userdata = User::where('id', $userId)->first();
                $commissionWithholding = $closerSetters->withhold_amount;
                $totalOverRideDue = 0;
                $totalClawbackDue = 0;

                $totalOverRideDue = UserOverrides::where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
                $totalOverRideDue->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('m2_date', [$startDate, $endDate]);
                });
                $totalOverRideDue = $totalOverRideDue->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->sum('amount');

                $clawPid = $closerSetters->pid;
                $totalClawbackDue = ClawbackSettlement::where(['user_id' => $userId, 'clawback_type' => 'reconciliation', 'status' => '1', 'payroll_id' => 0]);
                $totalClawbackDue->whereHas('salesDetail', function ($query) use ($startDate, $endDate, $clawPid) {
                    return $query->where('pid', $clawPid)
                        ->whereBetween('date_cancelled', [$startDate, $endDate]);
                });
                $totalClawbackDue->where('pid', $salePids);
                $totalClawbackDue->where('status', '1');
                $totalClawbackDue = $totalClawbackDue->sum('clawback_amount');

                $reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('payroll_status', null)->where('user_id', $userId)->whereIn('pid', $salePid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate);
                $commissionDue = $reconciliationsAdjustment->sum('commission_due');
                $overridesDue = $reconciliationsAdjustment->sum('overrides_due');
                $reimbursementDue = $reconciliationsAdjustment->sum('reimbursement');
                $deductionDue = $reconciliationsAdjustment->sum('deduction');
                $adjustmentDue = $reconciliationsAdjustment->sum('adjustment');
                $reconciliationDue = $reconciliationsAdjustment->sum('reimbursement');

                $totalAdjustments = $commissionDue + $overridesDue + $reimbursementDue + $adjustmentDue + $reconciliationDue - $deductionDue;

                $total_due = ($commissionWithholding + $totalOverRideDue + ($totalAdjustments) - ($totalClawbackDue));

                $commission = isset($commissionWithholding) ? $commissionWithholding : 0;
                $clawback = isset($totalClawbackDue) ? $totalClawbackDue : 0;
                $overrides = isset($totalOverRideDue) ? $totalOverRideDue : 0;
                $totalAdjustments = $totalAdjustments;

                $grossAmount = round($commission + $overrides, 2, PHP_ROUND_HALF_EVEN);
                $netAmount = round($grossAmount * $reconPayout / 100, 2, PHP_ROUND_HALF_EVEN);

                $getCommission = ReconciliationFinalizeHistory::where('pid', $salePids)->where('user_id', $userId)->where('status', 'finalize')->first();
                // $adjustAmount = $totalAdjustments;
                $netAmounts = round($netAmount + $totalAdjustments - $totalClawbackDue, 2, PHP_ROUND_HALF_EVEN);
                // --------------  payRollInAddValue ------------------

                $payrollPidCommissionGet = ReconciliationFinalizeHistory::where('pid', $salePids)->where('user_id', $userId)->where('status', 'payroll');

                $payrollPidCommission = $payrollPidCommissionGet->sum('paid_commission');
                $payrollPidOverride = $payrollPidCommissionGet->sum('paid_override');
                $payrollPidAdjustment = $payrollPidCommissionGet->sum('adjustments');
                $payrollPidClawback = $payrollPidCommissionGet->sum('clawback');
                $net = $payrollPidCommissionGet->sum('net_amount');
                $getCommissionPersontage = $payrollPidCommissionGet->first();
                if ($payrollPidCommission > 0 && $getCommissionPersontage->payout != null) {
                    $persontage = $getCommissionPersontage->payout;
                    // $paidCommission = ($payrollPidCommission*$persontage)/100;
                    $paidCommission = $payrollPidCommission;
                } else {
                    $paidCommission = 0;
                }
                if ($payrollPidOverride > 0 && $getCommissionPersontage->payout != null) {
                    $persontage = $getCommissionPersontage->payout;
                    // $paidOverride = ($payrollPidOverride*$persontage)/100;
                    $paidOverride = $payrollPidOverride;
                } else {
                    $paidOverride = 0;
                }
                $payrollPid = $payrollPidCommission + $payrollPidOverride + $payrollPidAdjustment - $payrollPidClawback;

                if ($payrollPidAdjustment > 0) {
                    $paidAdjustAmount = $payrollPidAdjustment;
                } else {
                    $paidAdjustAmount = 0;
                }
                if ($payrollPidClawback > 0) {
                    $clawback = $payrollPidClawback;
                } else {
                    $clawback = 0;
                }
                // $dd = $net-$payrollPidAdjustment-$payrollPidClawback;
                $finalizeCommission = $commission - $paidCommission;

                $finalizeOverRides = $overrides - $paidOverride;
                $finalizeGrossAmount = $finalizeCommission + $finalizeOverRides + $totalAdjustments - $totalClawbackDue;
                $netAmounts = ($finalizeCommission + $finalizeOverRides) * $reconPayout / 100;
                $paidCommission = ($finalizeCommission) * $reconPayout / 100;
                $paidOverride = ($finalizeOverRides) * $reconPayout / 100;
                $totalNetAmounts = $netAmounts + $totalAdjustments - $totalClawbackDue;

                // --------------  payRollInAddValue ------------------

                if ($getCommission && $getCommission != '') {

                    $overRideWithPids = UserOverrides::where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
                    $overRideWithPids->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('m2_date', [$startDate, $endDate]);
                    });
                    $overRideWithPids = $overRideWithPids->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->get();

                    if (isset($overRideWithPids) && $overRideWithPids != '[]') {
                        foreach ($overRideWithPids as $overRideWithPid) {
                            $firstName = isset($overRideWithPid->userpayrolloverride->first_name) ? $overRideWithPid->userpayrolloverride->first_name : null;
                            $lastName = isset($overRideWithPid->userpayrolloverride->last_name) ? $overRideWithPid->userpayrolloverride->last_name : null;
                            $history = ReconOverrideHistory::where('user_id', $userId)->where('pid', $overRideWithPid->pid)->where('type', $overRideWithPid->type)->where('status', 'finalize')->first();
                            if (isset($history) && $history != '') {
                                $historys = ReconOverrideHistory::where('user_id', $userId)->where('pid', $salePids)->where('type', $overRideWithPid->type)->where('status', 'payroll')->first();
                                $paiAmount = isset($historys->paid) ? $historys->paid : 0;
                                $customerName = SalesMaster::where('pid', $salePids)->first();
                                $history->start_date = $startDate;
                                $history->end_date = $endDate;
                                $history->customer_name = $customerName->customer_name;
                                $history->overrider = $firstName.' '.$lastName;
                                $history->type = $overRideWithPid->type;
                                $history->kw = $overRideWithPid->kw;
                                $history->override_amount = $overRideWithPid->overrides_amount;
                                $history->total_amount = $overRideWithPid->amount - $paiAmount;
                                $history->paid = (($overRideWithPid->amount - $paiAmount) * $reconPayout) / 100;
                                $history->save();

                            } else {

                                $customerName = SalesMaster::where('pid', $salePids)->first();
                                $historys = ReconOverrideHistory::where('user_id', $userId)->where('pid', $salePids)->where('type', $overRideWithPid->type)->where('status', 'payroll')->first();
                                $paiAmount = isset($historys->paid) ? $historys->paid : 0;
                                $overHistory = ReconOverrideHistory::create([
                                    'user_id' => $userId,
                                    'pid' => $overRideWithPid->pid,
                                    'start_date' => $startDate,
                                    'end_date' => $endDate,
                                    'customer_name' => $customerName->customer_name,
                                    'overrider' => $firstName.' '.$lastName,
                                    'type' => $overRideWithPid->type,
                                    'kw' => $overRideWithPid->kw,
                                    'override_amount' => $overRideWithPid->overrides_amount,
                                    'total_amount' => $overRideWithPid->amount - $paiAmount,
                                    'paid' => (($overRideWithPid->amount - $paiAmount) * $reconPayout) / 100,
                                    'percentage' => $reconPayout,
                                    'status' => 'finalize',
                                ]);
                            }
                        }
                    }

                    $getCommission->user_id = $userId;
                    $getCommission->pid = $salePids;
                    $getCommission->office_id = $userdata->office_id;
                    $getCommission->position_id = $userdata->sub_position_id;
                    $getCommission->start_date = $startDate;
                    $getCommission->end_date = $endDate;
                    $getCommission->commission = $finalizeCommission;
                    $getCommission->override = $finalizeOverRides;
                    $getCommission->paid_commission = $paidCommission;
                    $getCommission->paid_override = $paidOverride;
                    $getCommission->clawback = $totalClawbackDue;
                    $getCommission->adjustments = $totalAdjustments;
                    $getCommission->gross_amount = $finalizeGrossAmount;
                    $getCommission->payout = $reconPayout;
                    $getCommission->net_amount = $totalNetAmounts;
                    $getCommission->type = 'payroll_reconciliation';
                    $getCommission->status = 'finalize';
                    $getCommission->save();
                } else {

                    $overRideWithPids = UserOverrides::where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
                    $overRideWithPids->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('m2_date', [$startDate, $endDate]);
                    });
                    $overRideWithPids = $overRideWithPids->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $userId, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->get();
                    if (isset($overRideWithPids) && $overRideWithPids != '[]') {
                        foreach ($overRideWithPids as $overRideWithPid) {
                            $firstName = isset($overRideWithPid->userpayrolloverride->first_name) ? $overRideWithPid->userpayrolloverride->first_name : null;
                            $lastName = isset($overRideWithPid->userpayrolloverride->last_name) ? $overRideWithPid->userpayrolloverride->last_name : null;

                            $customerName = SalesMaster::where('pid', $salePids)->first();
                            $historys = ReconOverrideHistory::where('user_id', $userId)->where('pid', $overRideWithPid->pid)->where('type', $overRideWithPid->type)->where('status', 'payroll')->first();
                            $paiAmount = isset($historys->paid) ? $historys->paid : 0;
                            $overHistory = ReconOverrideHistory::create([
                                'user_id' => $userId,
                                'pid' => $overRideWithPid->pid,
                                'start_date' => $startDate,
                                'end_date' => $endDate,
                                'customer_name' => $customerName->customer_name,
                                'overrider' => $firstName.' '.$lastName,
                                'type' => $overRideWithPid->type,
                                'kw' => $overRideWithPid->kw,
                                'override_amount' => $overRideWithPid->overrides_amount,
                                'total_amount' => $overRideWithPid->amount - $paiAmount,
                                'paid' => (($overRideWithPid->amount - $paiAmount) * $reconPayout) / 100,
                                'percentage' => $reconPayout,
                                'status' => 'finalize',
                            ]);
                        }
                    }

                    $data = ReconciliationFinalizeHistory::create([
                        'user_id' => $userId,
                        'pid' => $salePids,
                        'office_id' => $userdata->office_id,
                        'position_id' => $userdata->sub_position_id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'commission' => $finalizeCommission,
                        'override' => $finalizeOverRides,
                        'paid_commission' => $paidCommission,
                        'paid_override' => $paidOverride,
                        'clawback' => $totalClawbackDue,
                        'adjustments' => $totalAdjustments,
                        'gross_amount' => $finalizeGrossAmount,
                        'payout' => $reconPayout,
                        'net_amount' => $totalNetAmounts,
                        'type' => 'payroll_reconciliation',
                        'status' => 'finalize',

                    ]);

                }

            }

        }

        // ----------------- S Payroll to recon Data ------------------------

        $getPayrollToreconUid = UserReconciliationWithholding::where('finalize_status', 0)->where('status', 'unpaid')->where('payroll_to_recon_status', 1)->whereBetween('created_at', [$startDate, $endDate])->get();
        $uids = [];
        $payrollUserId = [];
        foreach ($getPayrollToreconUid as $getPayrollTorecon) {
            $uids[] = isset($getPayrollTorecon->closer_id) ? $getPayrollTorecon->closer_id : $getPayrollTorecon->setter_id;

        }
        $payrollUserId = array_unique($uids);
        $payrollCloserSetter = UserReconciliationWithholding::where('finalize_status', 0)
            ->where('payroll_to_recon_status', 1)
            ->whereIn('closer_id', $payrollUserId)
            ->groupBY('closer_id')
            ->orWhereIn('setter_id', $payrollUserId)
            ->where('finalize_status', 0)
            ->where('payroll_to_recon_status', 1)
            ->groupBy('setter_id')
            ->get();

        // foreach($payrollCloserSetter as $payrollCloserSetter)
        // {
        //     $userId = isset($payrollCloserSetter->closer_id)?$payrollCloserSetter->closer_id:$payrollCloserSetter->setter_id;

        //     //$userId = isset($userData->closer_id)?$userData->closer_id:$userData->setter_id;
        //     $userdata = User::where('id', $userId)->first();

        //     $commissionWithholding = $payrollCloserSetter->withhold_amount;
        //     $totalOverRideDue = 0;
        //     $totalClawbackDue = 0;

        //     //$totalOverRideDue = UserOverrides::where('sale_user_id', $val->user_id)
        //     // $totalOverRideDue = UserOverrides::where('user_id', $userId)
        //     // ->where('pid',$salePids)
        //     // ->where('overrides_settlement_type','reconciliation')
        //     // ->where('status', '1')
        //     // ->sum('amount');

        //     // $totalClawbackDue = ClawbackSettlement::where('user_id', $userId)
        //     //     ->where('clawback_type','reconciliation')
        //     //     ->where('pid',$salePids)
        //     //     ->where('status', '1')
        //     //     ->whereBetween('created_at', [$startDate,$endDate])
        //     //     ->sum('clawback_amount');

        //     $reconciliationsAdjustment = ReconciliationsAdjustement::where('adjustment_type','reconciliations')->where('user_id', $userId)->where('payroll_move_status','from_payroll')->whereBetween('created_at', [$startDate,$endDate])->first();
        //     $commissionDue = isset($reconciliationsAdjustment->commission_due)?$reconciliationsAdjustment->commission_due:0;
        //     $overridesDue  = isset($reconciliationsAdjustment->overrides_due)?$reconciliationsAdjustment->overrides_due:0;
        //     $reimbursement = isset($reconciliationsAdjustment->reimbursement)?$reconciliationsAdjustment->reimbursement:0;
        //     $deduction = isset($reconciliationsAdjustment->deduction)?$reconciliationsAdjustment->deduction:0;
        //     $adjustment = isset($reconciliationsAdjustment->adjustment)?$reconciliationsAdjustment->adjustment:0;
        //     $reconciliation = isset($reconciliationsAdjustment->reconciliation)?$reconciliationsAdjustment->reconciliation:0;

        //     $totalAdjustments = $commissionDue+$overridesDue+$reimbursement+$reconciliation+$adjustment-$deduction;
        //     $total_due = ($commissionWithholding +($totalAdjustments));
        //     $commission= isset($commissionWithholding)?$commissionWithholding:0;
        //     $clawback=isset($clawbackDue)?$clawbackDue:0;
        //     $overrides=isset($overridesDue)?$overridesDue:0;
        //     $totalAdjustments=$totalAdjustments;

        //     $payrollPid = ReconciliationFinalizeHistory::where('user_id',$userId)->where('status','payroll')->sum('net_amount');
        //     if($payrollPid>0)
        //     {
        //     $payrollPid = $payrollPid;
        //     }else{
        //     $payrollPid = 0;
        //     }
        //     // $grossAmount = $commission;
        //     // $netAmount = $grossAmount*$reconPayout/100;

        //     $addAdjustment = UserReconciliationWithholding::where('closer_id',$userId)->where('payroll_to_recon_status',1)
        //     ->where('finalize_status',0)
        //     ->where('status','unpaid')
        //     ->orWhere('setter_id',$userId)
        //     ->where('finalize_status',0)
        //     ->where('payroll_to_recon_status',1)
        //     ->where('status','unpaid')
        //     ->sum('adjustment_amount');
        //     $getCommission = ReconciliationFinalizeHistory::where('user_id',$userId)->where('status','finalize')->first();

        //     $adjustAmount = $totalAdjustments;
        //     $netAmounts = $adjustAmount;

        //     // --------------  payRollInAddValue ------------------

        //     $payrollPidCommissionGet = ReconciliationFinalizeHistory::where('user_id',$userId)->where('status','payroll');

        //     $payrollPidCommission = $payrollPidCommissionGet->sum('commission');
        //     $payrollPidOverride = $payrollPidCommissionGet->sum('override');
        //     $payrollPidAdjustment =$payrollPidCommissionGet->sum('adjustments');
        //     $payrollPidClawback = $payrollPidCommissionGet->sum('clawback');
        //     $net = $payrollPidCommissionGet->sum('net_amount');
        //     $getCommissionPersontage = $payrollPidCommissionGet->first();
        //     if($payrollPidCommission > 0 && $getCommissionPersontage->payout!=null)
        //     {
        //         $persontage = $getCommissionPersontage->payout;
        //         $paidCommission = ($payrollPidCommission*$persontage)/100;
        //     }else{
        //         $paidCommission = 0;
        //     }
        //     if($payrollPidOverride > 0 && $getCommissionPersontage->payout!=null)
        //     {
        //         $persontage = $getCommissionPersontage->payout;
        //         $paidOverride = ($payrollPidOverride*$persontage)/100;
        //     }else{
        //         $paidOverride = 0;
        //     }
        //     $payrollPid = $paidCommission+$paidOverride+$payrollPidAdjustment-$payrollPidClawback;

        //     if($payrollPidAdjustment)
        //     {
        //         $adjustAmount = 0;
        //     }else{
        //         $adjustAmount=$totalAdjustments;
        //     }
        //     if($payrollPidAdjustment)
        //     {
        //         $clawback = 0;
        //     }else{
        //         $clawback= $clawback;
        //     }
        //     $dd = $net-$payrollPidAdjustment+$payrollPidClawback;
        //     $finalizeCommission = $commission-$dd;

        //     $finalizeOverRides = $overrides-$paidOverride;
        //     $finalizeGrossAmount = $finalizeCommission+$finalizeOverRides+$adjustAmount-$clawback;
        //     $netAmounts = ($finalizeCommission+$finalizeOverRides)*$reconPayout/100;
        //     $paidCommission  = ($finalizeCommission)*$reconPayout/100;
        //     $paidOverride = ($finalizeOverRides)*$reconPayout/100;
        //     $totalNetAmounts = $netAmounts+$adjustAmount-$clawback;

        //     // --------------  payRollInAddValue ------------------
        //     if($getCommission && $getCommission!='')
        //     {
        //        $getCommission->user_id = $userId;
        //         //$getCommission->pid = $salePids;
        //         $getCommission->office_id = $userdata->office_id;
        //         $getCommission->position_id = $userdata->sub_position_id;
        //         $getCommission->start_date = $startDate;
        //         $getCommission->end_date = $endDate;
        //         $getCommission->commission = $finalizeCommission;
        //         $getCommission->override = $finalizeOverRides;
        //         $getCommission->paid_commission = $paidCommission;
        //         $getCommission->paid_override = $paidOverride;
        //         $getCommission->clawback = $totalClawbackDue;
        //         $getCommission->adjustments = $adjustAmount;
        //         $getCommission->gross_amount = $finalizeGrossAmount;
        //         $getCommission->payout = $reconPayout;
        //         $getCommission->net_amount = $totalNetAmounts;
        //         $getCommission->type = 'payroll_reconciliation';
        //         $getCommission->status = 'finalize';
        //         $getCommission->save();
        //     }else{
        //         $data = ReconciliationFinalizeHistory::create([
        //             'user_id'=>$userId,
        //             //'pid'=>$salePids,
        //             'office_id'=>$userdata->office_id,
        //             'position_id'=>$userdata->sub_position_id,
        //             'start_date'=>$startDate,
        //             'end_date'=>$endDate,
        //             'commission'=>$finalizeCommission,
        //             'override'=>$finalizeOverRides,
        //             'paid_commission'=>$paidCommission,
        //             'paid_override'=>$paidOverride,
        //             'clawback'=>$totalClawbackDue,
        //             'adjustments'=>$adjustAmount,
        //             'gross_amount'=>$finalizeGrossAmount,
        //             'payout'=>$reconPayout,
        //             'net_amount'=>$totalNetAmounts,
        //             'type'=>'payroll_reconciliation',
        //             'status' =>'finalize'

        //         ]);
        //     }

        // }
        // ------------------ E payroll to recon Data ------------------------

        // $datas = ReconciliationFinalizeHistory::orderBy('id','asc')->groupBy('start_date')->where('status','finalize')->get();
        // code for sorting result by employee name ASC

        return response()->json([
            'ApiName' => 'create reconciliation finalize',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $datas
        ], 200);

    }

    public function reconciliationFinalizeDraftList(Request $request)
    {
        $page = $request->perpage;
        if (isset($page) && $page != null) {
            $pages = $page;
        } else {
            $pages = 10;
        }
        $data = ReconciliationFinalizeHistory::orderBy('id', 'asc')->groupBy('start_date')->where('status', 'finalize')->get();
        $totalCommision = 0;
        $totalOverride = 0;
        $totalClawback = 0;
        $totalAdjustments = 0;
        $grossAmount = 0;
        $payout = 0;

        $data->transform(function ($data) {

            $positionId = ReconciliationFinalizeHistory::orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'finalize')->pluck('position_id');
            $officeId = ReconciliationFinalizeHistory::orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'finalize')->pluck('office_id');
            $uniqueArray = collect($positionId)->unique()->values()->all();
            if ($uniqueArray[0] == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($uniqueArray as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }
            $officeIdArray = collect($officeId)->unique()->values()->all();
            if ($officeIdArray[0] == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeIdArray as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $val = ReconciliationFinalizeHistory::orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'finalize');
            $sumComm = $val->sum('commission');
            $sumOver = $val->sum('override');
            $sumClaw = $val->sum('clawback');
            $sumAdju = $val->sum('adjustments');
            $sumGross = $val->sum('gross_amount');
            $sumPayout = $val->sum('net_amount');

            $totalPay = $sumComm + $sumOver;
            if ($totalPay > 0) {
                $pays = round(($totalPay * $data->payout) / 100, 2, PHP_ROUND_HALF_EVEN);
            } else {
                $pays = 0;
            }

            return [
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'office' => $office,
                'position' => $position,
                'commission' => $sumComm,
                'overrides' => $sumOver,
                'total_due' => $totalPay,
                'pays' => $pays,
                'clawback' => $sumClaw,
                'adjustments' => $sumAdju,
                'payout' => $pays + $sumAdju - $sumClaw,
                'payout_per' => $data->payout,
                // 'net_amount' => $sumPayout,
                'status' => $data->status,
            ];

        });

        $dataCalculate = ReconciliationFinalizeHistory::orderBy('id', 'asc')->groupBy('start_date')->where('status', 'finalize')->get();
        foreach ($dataCalculate as $dataCalculates) {
            $vals = ReconciliationFinalizeHistory::orderBy('id', 'asc')->where('start_date', $dataCalculates->start_date)->where('end_date', $dataCalculates->end_date)->where('status', 'finalize');
            $sumComm = $vals->sum('commission');
            $sumOver = $vals->sum('override');
            $sumClaw = $vals->sum('clawback');
            $sumAdju = $vals->sum('adjustments');
            $sumGross = $vals->sum('gross_amount');
            $sumPayout = $vals->sum('net_amount');

            $totalCommision += $sumComm;
            $totalOverride += $sumOver;
            $totalClawback += $sumClaw;
            $totalAdjustments += $sumAdju;
            $grossAmount += $sumGross;
            $payout += $sumPayout;
        }

        $totalPay = $totalCommision + $totalOverride;
        if ($totalPay > 0) {
            $pays = round(($totalPay * $dataCalculates->payout) / 100, 2, PHP_ROUND_HALF_EVEN);
        } else {
            $pays = 0;
        }
        $total = [
            'totalCommision' => $totalCommision,
            'override' => $totalOverride,
            'total_due' => $totalPay,
            'pay' => $pays,
            'clawback' => $totalClawback,
            'adjustments' => $totalAdjustments,
            'gross_amount' => $grossAmount,
            'payouts' => $payout,
            'nextRecon' => $grossAmount - $payout,
        ];

        if ($request->has('sort') && $request->input('sort') == 'commission') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'commission'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'commission'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'overrides') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'overrides'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'overrides'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'clawback') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'clawback'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'clawback'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'adjustments') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'adjustments'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'adjustments'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'total_due') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_due'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_due'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'pay') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'pay'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'pay'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'payout') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'payout'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'payout'), SORT_ASC, $data);
            }
        }
        if ($request->input('sort') == '') {
            $data = json_decode($data);
            array_multisort(array_column($data, 'total_due'), SORT_ASC, $data);
        }
        $data = $this->paginates($data, $pages);

        return response()->json([
            'ApiName' => 'reconciliation finalize list',
            'status' => true,
            'message' => 'Successfully.',
            'total' => $total,
            'data' => $data,
        ], 200);

    }

    public function reconciliationPayrollHistoriesList(Request $request)
    {
        $page = $request->perpage;
        if (isset($page) && $page != null) {
            $pages = $page;
        } else {
            $pages = 10;
        }

        $executedOn = $request->input('executed_on');
        if ($request->has('executed_on') && $executedOn != '') {
            $data = ReconciliationFinalizeHistory::whereYear('executed_on', $executedOn)->orderBy('id', 'asc')->groupBy('sent_count')->where('status', 'payroll')->paginate($pages);
        } else {
            $data = ReconciliationFinalizeHistory::orderBy('id', 'asc')->groupBy('sent_count')->where('status', 'payroll')->paginate($pages);
        }

        $totalCommision = 0;
        $totalOverride = 0;
        $totalClawback = 0;
        $totalAdjustments = 0;
        $grossAmount = 0;
        $payout = 0;
        $data->transform(function ($data) use ($executedOn) {
            $total = [];
            $positionId = ReconciliationFinalizeHistory::whereYear('executed_on', $executedOn)->where('sent_count', $data->sent_count)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')->pluck('position_id');
            $officeId = ReconciliationFinalizeHistory::whereYear('executed_on', $executedOn)->where('sent_count', $data->sent_count)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')->pluck('office_id');
            $uniqueArray = collect($positionId)->unique()->values()->all();
            if ($uniqueArray[0] == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($uniqueArray as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }
            $officeIdArray = collect($officeId)->unique()->values()->all();
            if ($officeIdArray[0] == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeIdArray as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $val = ReconciliationFinalizeHistory::whereYear('executed_on', $executedOn)->where('sent_count', $data->sent_count)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll');
            $sumComm = $val->sum('paid_commission');
            $sumOver = $val->sum('paid_override');
            $sumClaw = $val->sum('clawback');
            $sumAdju = $val->sum('adjustments');
            $sumGross = $val->sum('gross_amount');
            $sumPayout = $val->sum('net_amount');

            return [
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'executed_on' => $data->executed_on,
                'office' => $office,
                'position' => $position,
                'commission' => $sumComm,
                'overrides' => $sumOver,
                'clawback' => $sumClaw,
                'adjustments' => $sumAdju,
                'gross_amount' => $sumPayout,
                'payout' => $data->payout,
                'net_amount' => $sumPayout,
                'status' => $data->status,
                'sent_id' => $data->sent_count,
            ];

        });

        $dataCalculate = ReconciliationFinalizeHistory::whereYear('executed_on', $executedOn)->orderBy('id', 'asc')->groupBy('sent_count')->where('status', 'payroll')->get();

        foreach ($dataCalculate as $dataCalculates) {
            $vals = ReconciliationFinalizeHistory::whereYear('executed_on', $executedOn)->orderBy('id', 'asc')->where('start_date', $dataCalculates->start_date)->where('end_date', $dataCalculates->end_date)->where('sent_count', $dataCalculates->sent_count)->where('status', 'payroll');
            $sumComm = $vals->sum('paid_commission');
            $sumOver = $vals->sum('paid_override');
            $sumClaw = $vals->sum('clawback');
            $sumAdju = $vals->sum('adjustments');
            $sumGross = $vals->sum('gross_amount');
            $sumPayout = $vals->sum('net_amount');

            $totalCommision += $sumComm;
            $totalOverride += $sumOver;
            $totalClawback += $sumClaw;
            $totalAdjustments += $sumAdju;
            $grossAmount += $sumGross;
            $payout += $sumPayout;
        }

        $total = [
            'totalCommision' => $totalCommision,
            'override' => $totalOverride,
            'clawback' => $totalClawback,
            'adjustments' => $totalAdjustments,
            'gross_amount' => $payout,
            'payout' => $payout,
            'year' => isset($executedOn) ? $executedOn : date('Y'),
            'nextRecon' => $grossAmount - $payout,
        ];

        return response()->json([
            'ApiName' => 'reconciliation payroll list',
            'status' => true,
            'message' => 'Successfully.',
            'total' => $total,
            'data' => $data,
        ], 200);

    }

    public function finalizeReconciliationList(Request $request)
    {
        $page = $request->perpage;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        if ($startDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'reconciliation finalize',
                'status' => false,
                'message' => 'Please select Start date and end date.',

            ], 400);
        }
        if (isset($page) && $page != null) {
            $pages = $page;
        } else {
            $pages = 10;
        }
        $data = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'finalize')->groupBy('user_id');
        if ($request->has('search')) {
            $data->whereHas(
                'user', function ($query) use ($request) {
                    $query->where('first_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
        }
        $data = $data->with('user')->get();

        $data->transform(function ($data) use ($request, $startDate, $endDate) {

            $officeId = explode(',', $data->office_id);
            if ($data->position_id == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($positionid as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }

            if ($data->office_id == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeId as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $userCalculation = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'finalize')->where('user_id', $data->user_id);

            $commission = $userCalculation->sum('commission');
            $overDue = $userCalculation->sum('override');

            $totalOverRideDue = UserOverrides::where(['user_id' => $data->user_id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
            $totalOverRideDue->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('m2_date', [$startDate, $endDate]);
            });
            $totalOverRideDue = $totalOverRideDue->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $data->user_id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->sum('amount');

            $overrideDue = $totalOverRideDue;
            // $overrideDue =  $userCalculation->sum('override');

            $clawbackAmount = ClawbackSettlement::with('user', 'salesDetail')->where(['user_id' => $data->user_id, 'status' => 1])->where('clawback_type', 'reconciliation')->whereDate('created_at', '>=', $request->start_date)
                ->whereDate('created_at', '<=', $request->end_date)->sum('clawback_amount');

            $clawbackDue = isset($clawbackAmount) ? $clawbackAmount : 0;
            $adju = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $data->user_id)->whereDate('created_at', '>=', $request->start_date)
                ->whereDate('created_at', '<=', $request->end_date);
            $totalAdjuCommission = $adju->sum('commission_due');
            $totalAdjuOverrides = $adju->sum('overrides_due');
            $totalAdjuReimbursement = $adju->sum('reimbursement');
            $totalAdjuDeduction = $adju->sum('deduction');
            $totalAdjuAdjustment = $adju->sum('adjustment');
            $totalAdjureconciliation = $adju->sum('reconciliation');
            $totalAdjuClawback = $adju->sum('clawback_due');

            $totalAdjustments = $totalAdjuCommission + $totalAdjuOverrides + $totalAdjuReimbursement + $totalAdjuDeduction + $totalAdjuAdjustment + $totalAdjureconciliation + $totalAdjuClawback;

            $totalDue = $userCalculation->sum('gross_amount');
            $netPay = $userCalculation->sum('net_amount');
            if (isset($data->user->image) && $data->user->image != null) {
                $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$data->user->image);
            } else {
                $s3_image = null;
            }

            return $myArray[] = [
                // 'id' => $reconciliationData->id,
                'user_id' => $data->user_id,
                'pid' => $data->pid,
                'emp_img' => isset($data->user->image) ? $data->user->image : null,
                'emp_img_s3' => $s3_image,
                'emp_name' => isset($data->user->first_name) ? $data->user->first_name.' '.$data->user->last_name : null,
                'commissionWithholding' => isset($commission) ? $commission : 0,
                'overrideDue' => isset($overDue) ? $overDue : 0,
                'total_due' => $commission + $overDue,
                'pay' => $data->payout,
                'total_pay' => (($commission + $overDue) * $data->payout) / 100,
                'clawbackDue' => isset($clawbackDue) ? $clawbackDue : 0,
                'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                'payout' => $netPay,
                // 'net_pay' =>$netPay,
                'status' => $data->status,
            ];

        });

        if ($request->has('sort') && $request->input('sort') == 'commission') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'override') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'overrideDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'overrideDue'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'clawback') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'clawbackDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'clawbackDue'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'adjustments') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'total_due') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_due'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_due'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'payout') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'payout'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'payout'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'net_pay') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'net_pay'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'net_pay'), SORT_ASC, $data);
            }
        }

        if ($request->input('sort') == '') {

            $data = json_decode($data);
            array_multisort(array_column($data, 'pid'), SORT_DESC, $data);

        }
        $data = $this->paginate($data, $pages);

        return response()->json([
            'ApiName' => 'reconciliation finalize',
            'status' => true,
            'message' => 'Successfully.',

            'data' => $data,
        ], 200);
    }

    public function payrollReconciliationList(Request $request)
    {
        $page = $request->perpage;
        if (isset($page) && $page != null) {
            $pages = $page;
        } else {
            $pages = 10;
        }
        $data = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->groupBy('user_id');
        if ($request->has('search')) {
            $data->whereHas(
                'user', function ($query) use ($request) {
                    $query->where('first_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
        }

        $data = $data->with('user')->orderBy('id', 'desc')->get();

        $data->transform(function ($data) use ($request) {

            $officeId = explode(',', $data->office_id);
            if ($data->position_id == 'all') {
                $position = 'All Position';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($positionid as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }

            if ($data->office_id == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeId as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $userCalculation = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->where('user_id', $data->user_id);
            $commission = $userCalculation->sum('paid_commission');
            $overrideDue = $userCalculation->sum('paid_override');
            $clawbackDue = $userCalculation->sum('clawback');
            $totalAdjustments = $userCalculation->sum('adjustments');
            $totalDue = $userCalculation->sum('gross_amount');
            $netPay = $commission + $overrideDue + $totalAdjustments - $clawbackDue;

            if (isset($data->user->image) && $data->user->image != null) {
                $s3_emp_img = s3_getTempUrl(config('app.domain_name').'/'.$data->user->image);
            } else {
                $s3_emp_img = null;
            }

            return $myArray[] = [
                // 'id' => $reconciliationData->id,
                'user_id' => $data->user_id,
                'emp_img' => isset($data->user->image) ? $data->user->image : null,
                'emp_img_s3' => $s3_emp_img,
                'emp_name' => isset($data->user->first_name) ? $data->user->first_name.' '.$data->user->last_name : null,
                'commissionWithholding' => isset($commission) ? $commission : 0,
                'overrideDue' => isset($overrideDue) ? $overrideDue : 0,
                'total_due' => $commission + $overrideDue,
                'pay' => $data->payout,
                'total_pay' => ($commission + $overrideDue),
                'clawbackDue' => isset($clawbackDue) ? $clawbackDue : 0,
                'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                'payout' => $netPay,
                // 'net_pay' =>$netPay,
                'status' => $data->status,
            ];
        });

        $locationPositions = ReconciliationFinalizeHistory::with('user')->where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->groupBy('user_id')->get();
        $office = '';
        $position = '';
        $payout = 0;
        $payoutPer = 0;
        foreach ($locationPositions as $locationPosition) {
            $userCalculation = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->where('user_id', $locationPosition->user_id);
            $netPay = $userCalculation->sum('net_amount');
            $payoutPer = $locationPosition->payout;
            $userPer = $userCalculation->orderBy('id', 'desc')->first();
            $payoutPer = $userPer->payout;
            $payout += $netPay;
            $officeId = explode(',', $locationPosition->office_id);
            if ($locationPosition->position_id == 'all') {
                $position = 'All Position';
            } else {
                $positionid = explode(',', $locationPosition->position_id);
                foreach ($positionid as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                // $position= implode(',',$val);
                $position = $val;
            }

            if ($locationPosition->office_id == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $locationPosition->office_id);
                foreach ($officeId as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                // $office= implode(',',$vals);
                $office = $vals;
            }
        }

        $originalArray = $position;
        $collection = collect($originalArray);
        $positions = $collection->unique()->values()->all();

        $officeArray = $office;
        $OfficeCollection = collect($officeArray);
        $offices = $OfficeCollection->unique()->values()->all();

        if ($request->has('sort') && $request->input('sort') == 'commission') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'commissionWithholding'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'override') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'overrideDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'overrideDue'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'clawback') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'clawbackDue'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'clawbackDue'), SORT_ASC, $data);
            }
        }

        if ($request->has('sort') && $request->input('sort') == 'adjustments') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'totalAdjustments'), SORT_ASC, $data);

            }
        }

        if ($request->has('sort') && $request->input('sort') == 'total_due') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'total_due'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'total_due'), SORT_ASC, $data);

            }
        }
        if ($request->has('sort') && $request->input('sort') == 'payout') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'payout'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'payout'), SORT_ASC, $data);
            }
        }
        if ($request->has('sort') && $request->input('sort') == 'net_pay') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'net_pay'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'net_pay'), SORT_ASC, $data);
            }
        }
        if ($request->input('sort') == '') {
            $data = json_decode($data);
            array_multisort(array_column($data, 'emp_name'), SORT_DESC, $data);
        }

        $data = paginate($data, $pages);

        return response()->json([
            'ApiName' => 'reconciliation finalize',
            'status' => true,
            'message' => 'Successfully.',
            'office' => $offices,
            'position' => $positions,
            'total_payout' => $payout,
            'payout_per' => $payoutPer,
            'data' => $data,
        ], 200);

    }

    public function exportPayrollReconciliationList(Request $request)
    {
        $data = [];

        $all_paid = true;
        $file_name = 'reportReconpayrollListUser_'.date('Y_m_d_H_i_s').'.csv';
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $search = $request->search;

        return Excel::download(new ExportReconPayrollListEmployee($startDate, $endDate, $search), $file_name);
    }

    public function sendTopayrollList(Request $request)
    {
        $data = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->groupBy('user_id');
        if ($request->has('search')) {
            $data->whereHas(
                'user', function ($query) use ($request) {
                    $query->where('first_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
        }
        $data = $data->with('user')->get();

        $data->transform(function ($data) use ($request) {

            $officeId = explode(',', $data->office_id);
            if ($data->position_id == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($positionid as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }

            if ($data->office_id == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeId as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $userCalculation = ReconciliationFinalizeHistory::where('start_date', $request->start_date)->where('end_date', $request->end_date)->where('status', 'payroll')->where('user_id', $data->user_id);

            $commission = $userCalculation->sum('paid_commission');
            $overrideDue = $userCalculation->sum('paid_override');
            $clawbackDue = $userCalculation->sum('clawback');
            $totalAdjustments = $userCalculation->sum('adjustments');
            $totalDue = $commission + $overrideDue + $clawbackDue + $totalAdjustments;
            $netPay = $userCalculation->sum('net_amount');

            return $myArray[] = [
                'user_id' => $data->user_id,
                'emp_img' => isset($data->user->image) ? $data->user->image : null,
                'emp_name' => isset($data->user->first_name) ? $data->user->first_name.' '.$data->user->last_name : null,
                'commissionWithholding' => isset($commission) ? $commission : 0,
                'overrideDue' => isset($overrideDue) ? $overrideDue : 0,
                'clawbackDue' => isset($clawbackDue) ? $clawbackDue : 0,
                'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                'total_due' => $totalDue,
                'payout' => $data->payout,
                'net_pay' => $netPay,
            ];

        });

        return response()->json([
            'ApiName' => 'Reconciliation Payroll List',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function reconciliationByUser(Request $request)
    {

        $userData = ReconciliationFinalizeHistory::where('user_id', $request->user_id)->groupBy('start_date')->get();
        $userData->transform(function ($userData) {
            $data = ReconciliationFinalizeHistory::where('user_id', $userData->user_id)
                ->where('start_date', $userData->start_date)
                ->where('end_date', $userData->end_date)
                ->get();
            $commision = 0;
            $override = 0;
            $clawback = 0;
            $adjustments = 0;
            $grossAmount = 0;
            $netPay = 0;
            $payout = 0;
            foreach ($data as $datas) {
                $commision += $datas->commission;
                $override += $datas->override;
                $clawback += $datas->clawback;
                $adjustments += $datas->adjustments;
                $grossAmount += $datas->gross_amount;
                $netPay += $datas->net_amount;
                $payout = $datas->payout;
            }

            return $val = [
                'start_date' => $userData->start_date,
                'end_date' => $userData->end_date,
                'commission' => $commision,
                'overrides' => $override,
                'clawback' => $clawback,
                'adjustments' => $adjustments,
                'totalDou' => $grossAmount,
                'netPayment' => $netPay,
                'payout' => $payout,
                // 'next_recon' => $grossAmount-$netPay,
            ];
        });
        $totalCommision = $userData->sum('commission');
        $totalOverride = $userData->sum('overrides');
        $totalClawback = $userData->sum('clawback');
        $totalAdjustments = $userData->sum('adjustments');
        $grossAmount = $userData->sum('totalDou');
        $payout = $userData->sum('netPayment');
        $total = [
            'totalCommision' => $totalCommision,
            'override' => $totalOverride,
            'clawback' => $totalClawback,
            'adjustments' => $totalAdjustments,
            'gross_amount' => $grossAmount,
            'payout' => $payout,
            'nextRecon' => $grossAmount - $payout,
        ];

        return response()->json([
            'ApiName' => 'Reconciliation By User Id',
            'status' => true,
            'message' => 'Successfully.',
            'total' => $total,
            'data' => $userData,
        ], 200);

    }

    public function checkUserClosePayroll(Request $request)
    {
        $user_id = implode(',', $request->user_id);
        $userId = explode(',', $user_id);
        $data = User::whereIn('id', $userId)->where('stop_payroll', 1)->get();
        if (count($data) > 0) {
            $data->transform(function ($data) {
                return $myArray[] = [
                    'user_id' => $data->id,
                    'emp_img' => $data->image,
                    'emp_name' => $data->first_name.' '.$data->last_name,
                ];
            });

            return response()->json([
                'ApiName' => 'Check stop payroll for user api',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Check stop payroll for user api',
                'status' => false,
                'message' => 'Data is not find.',
            ], 400);
        }

    }

    public function sendToPayrollRecon(Request $request): JsonResponse
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $data = $request->data;
        $currentDate = Carbon::now();
        $date = $currentDate->format('Y-m-d');
        $UserReconciliationCommissions = ReconciliationFinalizeHistory::where('status', 'finalize')->where('start_date', $startDate)->where('end_date', $endDate)->get();
        $stopUserPayRoll = 0;
        if (count($UserReconciliationCommissions) > 0) {
            $subtotal = 0;
            $overrides = ReconOverrideHistory::where('status', 'finalize')->where('start_date', $startDate)->where('end_date', $endDate)->get();
            if (isset($overrides) && $overrides != '[]') {
                foreach ($overrides as $override) {
                    $overrides = ReconOverrideHistory::where('id', $override->id)->update(['status' => 'payroll']);
                }
            }
            foreach ($UserReconciliationCommissions as $key => $UserReconciliationCommission) {

                $userdata = User::with('positionDetail')->where('id', $UserReconciliationCommission->user_id)->first();
                if ($userdata->stop_payroll == 0) {

                    if ($userdata->positionDetail->payFrequency->frequency_type_id == 1) {
                        // $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->get();
                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'executed_on' => $date, 'pay_period_from' => $data['daily']['pay_period_from'], 'pay_period_to' => $data['daily']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->payroll_id])->update(['status' => 7]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['daily']['pay_period_from'], 'pay_period_to' => $data['daily']['pay_period_to']])->first();
                        if ($paydata) {

                            $updateData = [
                                'reconciliation' => $UserReconciliationCommission->net_amount,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['daily']['pay_period_from'],
                                'pay_period_to' => $data['daily']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['daily']['pay_period_from'],
                                'pay_period_to' => $data['daily']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->net_amount,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['daily']['pay_period_from'],
                                'pay_period_to' => $data['daily']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['daily']['pay_period_from'],
                                    'pay_period_to' => $data['daily']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId]);
                            }
                        }

                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 2) {
                        $userReconCount = ReconciliationFinalizeHistory::where('status', 'payroll')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)->orderBy('id', 'desc')->first();
                        $count = isset($userReconCount->sent_count) ? $userReconCount->sent_count : 0;
                        $sendCount = $count + 1;
                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'sent_count' => $sendCount, 'executed_on' => $date, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->payroll_id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->payroll_id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']])->first();
                        if ($paydata) {
                            if ($paydata->reconciliation > 0) {
                                $recon = $paydata->reconciliation;
                            } else {
                                $recon = 0;
                            }
                            $updateData = [
                                'reconciliation' => $UserReconciliationCommission->net_amount + $recon,
                            ];

                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['weekly']['pay_period_from'],
                                'pay_period_to' => $data['weekly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {

                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                            // overRides ----------------------------------
                            $userReconOver = UserOverrides::where([
                                'overrides_settlement_type' => 'reconciliation',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pid' => $UserReconciliationCommission->pid,
                            ])->first();
                            if (isset($userReconOver) && $userReconOver != '') {
                                $update = UserOverrides::where([
                                    'overrides_settlement_type' => 'reconciliation',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pid' => $UserReconciliationCommission->pid,
                                ])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $UserReconciliationCommission->pay_period_from,
                                    'pay_period_to' => $UserReconciliationCommission->pay_period_to]);
                            }
                            //  Adjustment -------------
                            $adjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->get();
                            if (isset($adjustment) && $adjustment != '') {
                                ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->update(['payroll_status' => 'payroll']);
                            }
                            // clawback -------------

                            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->first();
                            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                                ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                            }

                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['weekly']['pay_period_from'],
                                'pay_period_to' => $data['weekly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->net_amount,
                            ]);
                            $payRollId = $payroll_data->id;

                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['weekly']['pay_period_from'],
                                'pay_period_to' => $data['weekly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $count = isset($userReconcomm->sent_count) ? $userReconcomm->sent_count : 0;
                                $sendCount = $count + 1;
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId, 'sent_count' => $sendCount]);
                            }
                            // overRides ----------------------------------
                            $userReconOver = UserOverrides::where([
                                'overrides_settlement_type' => 'reconciliation',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pid' => $UserReconciliationCommission->pid,
                            ])->first();
                            if (isset($userReconOver) && $userReconOver != '') {
                                $update = UserOverrides::where([
                                    'overrides_settlement_type' => 'reconciliation',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pid' => $UserReconciliationCommission->pid,
                                ])->update(['payroll_id' => $payRollId, 'pay_period_from' => $UserReconciliationCommission->pay_period_from,
                                    'pay_period_to' => $UserReconciliationCommission->pay_period_to]);
                            }
                            //  Adjustment -------------
                            $adjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->get();
                            if (isset($adjustment) && $adjustment != '') {
                                ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->update(['payroll_status' => 'payroll']);
                            }
                            // clawback -------------

                            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->first();
                            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                                ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                            }
                        }
                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 3) {

                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'executed_on' => $date, 'pay_period_from' => $data['biweekly']['pay_period_from'], 'pay_period_to' => $data['biweekly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['biweekly']['pay_period_from'], 'pay_period_to' => $data['biweekly']['pay_period_to']])->first();
                        if ($paydata) {

                            $updateData = [
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['biweekly']['pay_period_from'],
                                'pay_period_to' => $data['biweekly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['biweekly']['pay_period_from'],
                                    'pay_period_to' => $data['biweekly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['biweekly']['pay_period_from'],
                                'pay_period_to' => $data['biweekly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['biweekly']['pay_period_from'],
                                'pay_period_to' => $data['biweekly']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['weekly']['pay_period_from'],
                                    'pay_period_to' => $data['weekly']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId]);
                            }
                        }

                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 4) {

                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'executed_on' => $date, 'pay_period_from' => $data['semimonthly']['pay_period_from'], 'pay_period_to' => $data['semimonthly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['semimonthly']['pay_period_from'], 'pay_period_to' => $data['semimonthly']['pay_period_to']])->first();
                        if ($paydata) {

                            $updateData = [
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                'pay_period_to' => $data['semimonthly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                    'pay_period_to' => $data['semimonthly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                'pay_period_to' => $data['semimonthly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                'pay_period_to' => $data['semimonthly']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['semimonthly']['pay_period_from'],
                                    'pay_period_to' => $data['semimonthly']['pay_period_to'],
                                ])->update(['payroll_id' => $payRollId]);
                            }

                        }

                    } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 5) {

                        $update = ReconciliationFinalizeHistory::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)->where('start_date', $startDate)->where('end_date', $endDate)
                            ->update(['status' => 'payroll', 'executed_on' => $date, 'pay_period_from' => $data['monthly']['pay_period_from'], 'pay_period_to' => $data['monthly']['pay_period_to']]);
                        if (isset($UserReconciliationCommission->id) && $UserReconciliationCommission->id != null) {
                            Payroll::where(['id' => $UserReconciliationCommission->id])->update(['status' => 1]);
                        }
                        $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['monthly']['pay_period_from'], 'pay_period_to' => $data['monthly']['pay_period_to']])->first();
                        if ($paydata) {
                            if ($paydata->reconciliation > 0) {
                                $recon = $paydata->reconciliation;
                            } else {
                                $recon = 0;
                            }
                            $updateData = [
                                // 'reconciliation' => $UserReconciliationCommission->total_due,
                                'reconciliation' => $UserReconciliationCommission->net_amount + $recon,
                            ];
                            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                            ])->first();
                            if (isset($userReconcomm) && $userReconcomm != '') {
                                $update = ReconciliationFinalizeHistory::where([
                                    'status' => 'payroll',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pay_period_from' => $data['monthly']['pay_period_from'],
                                    'pay_period_to' => $data['monthly']['pay_period_to'],
                                ])->update(['payroll_id' => $paydata->id]);
                            }
                            // overRides ----------------------------------
                            $userReconOver = UserOverrides::where([
                                'overrides_settlement_type' => 'reconciliation',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pid' => $UserReconciliationCommission->pid,
                            ])->first();
                            if (isset($userReconOver) && $userReconOver != '') {
                                $update = UserOverrides::where([
                                    'overrides_settlement_type' => 'reconciliation',
                                    'user_id' => $UserReconciliationCommission->user_id,
                                    'pid' => $UserReconciliationCommission->pid,
                                ])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['monthly']['pay_period_from'],
                                    'pay_period_to' => $data['monthly']['pay_period_to']]);
                            }
                            //  Adjustment -------------
                            $adjustment = ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->get();
                            if (isset($adjustment) && $adjustment != '') {
                                ReconciliationsAdjustement::where('adjustment_type', 'reconciliations')->where('user_id', $UserReconciliationCommission->user_id)->where('pid', $UserReconciliationCommission->pid)->where('start_date', '>=', $startDate)->where('end_date', '<=', $endDate)->update(['payroll_status' => 'payroll']);
                            }
                            // clawback -------------

                            $totalClawbackDue = ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->first();
                            if (isset($totalClawbackDue) && $totalClawbackDue != '') {
                                ClawbackSettlement::where(['user_id' => $UserReconciliationCommission->user_id, 'clawback_type' => 'reconciliation', 'pid' => $UserReconciliationCommission->pid, 'status' => '1', 'payroll_id' => 0])->update(['payroll_id' => $paydata->id, 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);
                            }
                        } else {
                            $payroll_data = Payroll::create([
                                'user_id' => $userdata->id,
                                'position_id' => $userdata->position_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                                'status' => 1,
                                'reconciliation' => $UserReconciliationCommission->total_due,
                            ]);
                            $userReconcomm = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                            ])->first();
                            $payRollId = $payroll_data->id;
                            $update = ReconciliationFinalizeHistory::where([
                                'status' => 'payroll',
                                'user_id' => $UserReconciliationCommission->user_id,
                                'pay_period_from' => $data['monthly']['pay_period_from'],
                                'pay_period_to' => $data['monthly']['pay_period_to'],
                            ])->update(['payroll_id' => $payRollId]);
                        }
                    }
                } else {
                    $stopUserPayRoll += 1;
                }
                if ($UserReconciliationCommission->payout == 100) {
                    $updateStatus = UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('finalize_status', 0)
                        ->where('status', 'unpaid')
                        ->orWhere('setter_id', $UserReconciliationCommission->user_id)
                        ->where('finalize_status', 0)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('status', 'unpaid')
                        ->update(['finalize_status' => 1]);
                }

                if ($UserReconciliationCommission->commission == 0 && $UserReconciliationCommission->commission == 0) {
                    $updateStatus = UserReconciliationWithholding::where('closer_id', $UserReconciliationCommission->user_id)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('finalize_status', 0)
                        ->where('status', 'unpaid')
                        ->orWhere('setter_id', $UserReconciliationCommission->user_id)
                        ->where('finalize_status', 0)
                        ->where('pid', $UserReconciliationCommission->pid)
                        ->where('status', 'unpaid')
                        ->update(['finalize_status' => 1]);
                }
            }
        }

        if ($stopUserPayRoll == 1) {
            return response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Can not send to payroll. Payroll has been stopped for this employee.',
            ], 200);
        } elseif ($stopUserPayRoll > 1) {
            return response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Some users Cannot send to payroll. Because Payroll has been stopped for these employee.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'add_reconciliation_to_payroll',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        }
    }

    public function deleteReconAdjustement(Request $request): JsonResponse
    {
        $adjustmentId = $request->adjustment_id;
        $userId = $request->user_id;
        $type = $request->type;
        // $adjustment_details_id = $request->adjustment_details_id;
        try {

            $payrollAdjustmentDetail = ReconciliationsAdjustement::where('id', $adjustmentId)->where('user_id', $userId);
            $data = $payrollAdjustmentDetail->first();

            if ($data == '') {
                return response()->json([
                    'ApiName' => 'delete adjustement',
                    'status' => false,
                    'message' => 'Bad request',
                ], 400);
            }
            if ($type == 'overrides') {
                $data->overrides_due = 0;
                $data->save();
                $over = UserOverrides::where('pid', $data->pid)->where('user_id', $userId)->first();
                if (isset($over)) {
                    $over->adjustment_amount = 0;
                    $over->save();
                }
            }

            if ($type == 'commission') {
                $data->commission_due = 0;
                $data->save();
                $adj = UserReconciliationWithholding::where('pid', $data->pid)->where('closer_id', $userId)->orWhere('setter_id', $userId)->where('pid', $data->pid)->first();
                $adj->adjustment_amount = 0;
                $adj->save();
            }

            if ($type == 'clawback') {
                $data->clawback_due = 0;
                $data->save();
            }

            if ($type == 'reimbursement') {
                $data->reimbursement = 0;
                $data->save();
            }

            if ($type == 'deduction') {
                $data->deduction = 0;
                $data->save();
            }

            if ($type == 'adjustment') {
                $data->adjustment = 0;
                $data->save();
            }

            if ($type == 'reconciliation') {
                $data->reconciliation = 0;
                $data->save();
            }

            $message = 'Deleted Successfully.';

        } catch (Exception $e) {

            $message = $e->getMessage();
        }

        // $payrollId = $request->payroll_id;
        // $userId   = $request->user_id;

        // $payrollAdjustmentDetail = PayrollAdjustmentDetail::where('payroll_id',$payrollId)->where('user_id',$userId)->delete();
        // $adjustmentDetail = PayrollAdjustment::where('payroll_id',$payrollId)->where('user_id',$userId)->delete();

        return response()->json([
            'ApiName' => 'delete adjustement',
            'status' => true,
            'message' => $message,
        ], 200);
    }

    // public function paginate($items, $perPage = 10, $page = null, $options = [])
    // {
    //     $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
    //     $items = $items instanceof Collection ? $items : Collection::make($items);
    //     return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    // }
    public function paginate($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function updateUserReconciliation(Request $request): JsonResponse
    {

        $id = $request->id;
        $data = [
            'commission_due' => $request->commission_due,
            'override_due' => $request->override_due,
            'deduction_due' => $request->deduction_due,
            'comments' => $request->comments,

        ];

        // $payroll = UserReconciliationWithholding::where('id', $id)->update($data);

        return response()->json([
            'ApiName' => 'update_user_reconciliation',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function finalizeReconciliation(Request $request): JsonResponse
    {
        $data = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if ($endDate > date('Y-m-d')) {
            return response()->json([
                'ApiName' => 'finalize_reconciliation',
                'status' => false,
                'message' => 'Can not finalize reconciliation for future date',
            ], 400);
        }

        $reconciliation = UserReconciliationCommission::where('status', 'pending')->where(['period_from' => $startDate, 'period_to' => $endDate])->get();

        if (count($reconciliation) > 0) {
            foreach ($reconciliation as $key => $val) {
                $update = UserReconciliationCommission::where('id', $val->id)->update(['status' => 'finalize']);

                $totalOverRideDue = UserOverrides::where('user_id', $val->user_id)
                    ->where('overrides_settlement_type', 'reconciliation')
                    ->where('status', '1')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->update(['status' => '2']);

                $totalClawbackDue = ClawbackSettlement::where('user_id', $val->user_id)
                    ->where('clawback_type', 'reconciliation')
                    ->where('status', '1')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->update(['status' => '2']);

            }
        }

        return response()->json([
            'ApiName' => 'finalize_reconciliation',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function finalizeReconciliationWithholding(Request $request): JsonResponse
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $request->position_id;
        $office_id = implode(',', $request->office_id);
        $position_id = implode(',', $request->position_id);
        $officeId = explode(',', $office_id);
        $positionId = explode(',', $position_id);

        if ($endDate > date('Y-m-d')) {
            return response()->json([
                'ApiName' => 'finalize_reconciliation',
                'status' => false,
                'message' => 'Can not finalize reconciliation for future date',
            ], 400);
        }

        $reconciliation = UserReconciliationCommissionWithholding::where('status', 'pending')->whereBetween('created_at', [$startDate, $endDate])->get();
        if (count($reconciliation) > 0) {
            foreach ($reconciliation as $key => $val) {
                $update = UserReconciliationCommissionWithholding::where('id', $val->id)->update(['status' => 'finalize']);

                $totalOverRideDue = UserOverrides::where('user_id', $val->user_id)
                    ->where('overrides_settlement_type', 'reconciliation')
                    ->where('status', '1')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->update(['status' => '2']);

                $totalClawbackDue = ClawbackSettlement::where('user_id', $val->user_id)
                    ->where('clawback_type', 'reconciliation')
                    ->where('status', '1')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->update(['status' => '2']);
            }
        }

        return response()->json([
            'ApiName' => 'finalize_reconciliation',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function addReconciliationToPayroll(Request $request): JsonResponse
    {
        // $data = array();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $data = $request->data;
        // dd($data['weekly']['pay_period_from']);

        $UserReconciliationCommissions = UserReconciliationCommission::where('status', 'finalize')->get();
        if (count($UserReconciliationCommissions) > 0) {
            $subtotal = 0;
            foreach ($UserReconciliationCommissions as $key => $UserReconciliationCommission) {

                $userdata = User::with('positionDetail')->where('id', $UserReconciliationCommission->user_id)->first();

                if ($userdata->positionDetail->payFrequency->frequency_type_id == 1) {

                    $update = UserReconciliationCommission::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)
                        ->update(['status' => 'payroll', 'pay_period_from' => $data['daily']['pay_period_from'], 'pay_period_to' => $data['daily']['pay_period_to']]);

                    $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['daily']['pay_period_from'], 'pay_period_to' => $data['daily']['pay_period_to']])->first();
                    if ($paydata) {

                        $updateData = [
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ];
                        $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                    } else {
                        $payroll_data = Payroll::create([
                            'user_id' => $userdata->id,
                            'position_id' => $userdata->position_id,
                            'pay_period_from' => $data['daily']['pay_period_from'],
                            'pay_period_to' => $data['daily']['pay_period_to'],
                            'status' => 1,
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ]);
                        $userReconcomm = UserReconciliationCommission::where([
                            'status' => 'payroll',
                            'user_id' => $UserReconciliationCommission->user_id,
                            'pay_period_from' => $data['daily']['pay_period_from'],
                            'pay_period_to' => $data['daily']['pay_period_to'],
                        ])->first();
                        $update = $userReconcomm->update(['payroll_id' => $payroll_data->id]);
                    }

                } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 2) {

                    $update = UserReconciliationCommission::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)
                        ->update(['status' => 'payroll', 'pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']]);

                    $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['weekly']['pay_period_from'], 'pay_period_to' => $data['weekly']['pay_period_to']])->first();
                    if ($paydata) {

                        $updateData = [
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ];
                        $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                    } else {
                        $payroll_data = Payroll::create([
                            'user_id' => $userdata->id,
                            'position_id' => $userdata->position_id,
                            'pay_period_from' => $data['weekly']['pay_period_from'],
                            'pay_period_to' => $data['weekly']['pay_period_to'],
                            'status' => 1,
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ]);
                        $userReconcomm = UserReconciliationCommission::where([
                            'status' => 'payroll',
                            'user_id' => $UserReconciliationCommission->user_id,
                            'pay_period_from' => $data['weekly']['pay_period_from'],
                            'pay_period_to' => $data['weekly']['pay_period_to'],
                        ])->first();
                        $update = $userReconcomm->update(['payroll_id' => $payroll_data->id]);
                    }

                } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {

                    $update = UserReconciliationCommission::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)
                        ->update(['status' => 'payroll', 'pay_period_from' => $data['biweekly']['pay_period_from'], 'pay_period_to' => $data['biweekly']['pay_period_to']]);

                    $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['biweekly']['pay_period_from'], 'pay_period_to' => $data['biweekly']['pay_period_to']])->first();
                    if ($paydata) {

                        $updateData = [
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ];
                        $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                    } else {
                        $payroll_data = Payroll::create([
                            'user_id' => $userdata->id,
                            'position_id' => $userdata->position_id,
                            'pay_period_from' => $data['biweekly']['pay_period_from'],
                            'pay_period_to' => $data['biweekly']['pay_period_to'],
                            'status' => 1,
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ]);
                        $userReconcomm = UserReconciliationCommission::where([
                            'status' => 'payroll',
                            'user_id' => $UserReconciliationCommission->user_id,
                            'pay_period_from' => $data['biweekly']['pay_period_from'],
                            'pay_period_to' => $data['biweekly']['pay_period_to'],
                        ])->first();
                        $update = $userReconcomm->update(['payroll_id' => $payroll_data->id]);
                    }

                } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {

                    $update = UserReconciliationCommission::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)
                        ->update(['status' => 'payroll', 'pay_period_from' => $data['semimonthly']['pay_period_from'], 'pay_period_to' => $data['semimonthly']['pay_period_to']]);

                    $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['semimonthly']['pay_period_from'], 'pay_period_to' => $data['semimonthly']['pay_period_to']])->first();
                    if ($paydata) {

                        $updateData = [
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ];
                        $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                    } else {
                        $payroll_data = Payroll::create([
                            'user_id' => $userdata->id,
                            'position_id' => $userdata->position_id,
                            'pay_period_from' => $data['semimonthly']['pay_period_from'],
                            'pay_period_to' => $data['semimonthly']['pay_period_to'],
                            'status' => 1,
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ]);
                        $userReconcomm = UserReconciliationCommission::where([
                            'status' => 'payroll',
                            'user_id' => $UserReconciliationCommission->user_id,
                            'pay_period_from' => $data['semimonthly']['pay_period_from'],
                            'pay_period_to' => $data['semimonthly']['pay_period_to'],
                        ])->first();
                        $update = $userReconcomm->update(['payroll_id' => $payroll_data->id]);
                    }

                } elseif ($userdata->positionDetail->payFrequency->frequency_type_id == 5) {

                    $update = UserReconciliationCommission::where('status', 'finalize')->where('user_id', $UserReconciliationCommission->user_id)
                        ->update(['status' => 'payroll', 'pay_period_from' => $data['monthly']['pay_period_from'], 'pay_period_to' => $data['monthly']['pay_period_to']]);

                    $paydata = Payroll::where('user_id', $UserReconciliationCommission->user_id)->where(['pay_period_from' => $data['monthly']['pay_period_from'], 'pay_period_to' => $data['monthly']['pay_period_to']])->first();
                    if ($paydata) {

                        $updateData = [
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ];
                        $payroll = Payroll::where('id', $paydata->id)->update($updateData);
                    } else {
                        $payroll_data = Payroll::create([
                            'user_id' => $userdata->id,
                            'position_id' => $userdata->position_id,
                            'pay_period_from' => $data['monthly']['pay_period_from'],
                            'pay_period_to' => $data['monthly']['pay_period_to'],
                            'status' => 1,
                            'reconciliation' => $UserReconciliationCommission->total_due,
                        ]);
                        $userReconcomm = UserReconciliationCommission::where([
                            'status' => 'payroll',
                            'user_id' => $UserReconciliationCommission->user_id,
                            'pay_period_from' => $data['monthly']['pay_period_from'],
                            'pay_period_to' => $data['monthly']['pay_period_to'],
                        ])->first();
                        $update = $userReconcomm->update(['payroll_id' => $payroll_data->id]);
                    }
                }
            }
        }

        return response()->json([
            'ApiName' => 'add_reconciliation_to_payroll',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function getFinalizeReconciliation(Request $request): JsonResponse
    {

        $id = $request->id;
        if ($id) {
            $result = UserReconciliationWithholding::where('status', 'finalize')->where('id', $id)->first();
        } else {
            $result = UserReconciliationWithholding::where('status', 'finalize')->orderBy('id', 'asc')->get();
        }

        return response()->json([
            'ApiName' => 'get_finalize_reconciliation',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ], 200);
    }

    public function createOneTimePayment(Request $request): JsonResponse
    {
        $user = User::where('id', $request->user_id)->first();
        $date = date('Y-m-d');
        $payFrequency = $this->payFrequency($date, $user->sub_position_id, $user->id);

        if (! $request->image == null) {
            $file = $request->file('image');
            $image_path = time().$file->getClientOriginalName();
            $ex = $file->getClientOriginalExtension();
            $destinationPath = 'request-image';
            $image_path = $file->move($destinationPath, time().$file->getClientOriginalName());
            // $image_path =  "request-image/".time() . $file->getClientOriginalName();
            // \Storage::disk("s3")->put($image_path,file_get_contents($file));
        } else {
            $image_path = '';
        }

        $user_id = Auth::user()->id;
        $user_data = User::where('id', $user_id)->first();
        if (! empty($request->user_id)) {

            $userID = $request->user_id;
        } else {
            $userID = $user_id;
        }
        // echo $data->name;die;
        $adjustementType = AdjustementType::where('id', $request->adjustment_type_id)->first();

        $approvalsAndRequest = ApprovalsAndRequest::where('adjustment_type_id', $adjustementType->id)->latest('id')->count();

        if ($adjustementType->id == 1) {

            if (! empty($approvalsAndRequest)) {
                $req_no = 'PD'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'PD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }

        } elseif ($adjustementType->id == 2) {

            if (! empty($approvalsAndRequest)) {
                $req_no = 'R'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'R'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }

        } elseif ($adjustementType->id == 3) {

            if (! empty($approvalsAndRequest)) {
                $req_no = 'B'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'B'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        } elseif ($adjustementType->id == 4) {

            if (! empty($approvalsAndRequest)) {
                $req_no = 'A'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'A'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        } elseif ($adjustementType->id == 5) {

            if (! empty($approvalsAndRequest)) {
                $req_no = 'FF'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'FF'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        } elseif ($adjustementType->id == 6) {

            if (! empty($approvalsAndRequest)) {
                $req_no = 'I'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'I'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        } else {
            if (! empty($approvalsAndRequest)) {
                $req_no = 'O'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'O'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        }
        $data = [];
        $data['user_id'] = $request->user_id;
        $data['manager_id'] = $user->manager_id;
        $data['req_no'] = $req_no;
        $data['approved_by'] = $user_id;
        $data['adjustment_type_id'] = '4';
        $data['amount'] = $request->amount;
        $data['cost_date'] = date('Y-m-d,H:i:s');
        $data['description'] = $request->description;
        $data['pay_period_from'] = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
        $data['pay_period_to'] = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
        $data['image'] = $image_path;
        $data['description'] = $request->description;

        $data['status'] = 'Accept';
        $create = ApprovalsAndRequest::create($data);

        $txnId = ApprovalsAndRequest::where('id', $create->id)->update(['txn_id' => 'TXN000'.$create->id]);

        $data = Notification::create([
            'user_id' => $user->id,
            'type' => 'Create OneTime Payment',
            'description' => 'Create OneTime Payment by '.auth()->user()->first_name,
            'is_read' => 0,
        ]);
        $notificationData = [
            'user_id' => $user->id,
            'device_token' => $user->device_token,
            'title' => 'Create OneTime Payment.',
            'sound' => 'sound',
            'type' => 'Create OneTime Payment',
            'body' => 'Create OneTime Payment by '.auth()->user()->first_name,
        ];
        $this->sendNotification($notificationData);

        return response()->json([
            'ApiName' => 'create_onetime_payment_api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function finalizePayroll(Request $request)
    {
        try {
            $data = [];
            $start_date = $request->start_date;
            $end_date = $request->end_date;

            $checkOtherFinalizedPayroll = Payroll::where('pay_period_from', '!=', $start_date)
                ->where('pay_period_to', '!=', $end_date)
                ->where('status', 2)
                ->count();

            if ($checkOtherFinalizedPayroll > 0) {
                $dates = Payroll::select('pay_period_from', 'pay_period_to')
                    ->where('pay_period_from', '!=', $start_date)
                    ->where('pay_period_to', '!=', $end_date)
                    ->where('status', 2)
                    ->first();

                return response()->json([
                    'ApiName' => 'finalizePayroll',
                    'status' => false,
                    'message' => 'Warning: Another payroll finalized for the period '.date('m/d/Y', strtotime($dates['pay_period_from'])).' to '.date('m/d/Y', strtotime($dates['pay_period_to'])),
                ], 400);
            }

            $search = $request->search;

            DB::beginTransaction();
            $check_negative = Payroll::where('status', 1)->where('is_mark_paid', '!=', 1)->where('net_pay', '<', '0')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->count();
            if ($check_negative > 0) {
                return response()->json([
                    'ApiName' => 'finalizePayroll',
                    'status' => false,
                    'message' => 'Error - No negative net pay should be in selected pay period.',
                ], 400);
            }

            $users = User::orderBy('id', 'asc');
            if ($request->has('search') && ! empty($request->input('search'))) {
                $users->where(function ($query) use ($request) {
                    return $query->where('first_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->input('search').'%');
                });
            }
            // $userdata = $users->get();
            $userArray = $users->pluck('id')->toArray();
            Payroll::where('status', 2)
                ->where('finalize_status', 0)
                ->whereIn('user_id', $userArray)
                ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->update(['finalize_status' => 2]);

            $query = Payroll::with('usersdata', 'positionDetail')
                ->where('status', '!=', 2)
            // ->where('net_pay','>',0)
                ->where(function ($q) {
                    $q->where('finalize_status', '=', 0)
                        ->orWhere('finalize_status', '=', 3);
                })
                ->whereIn('user_id', $userArray);

            if ($start_date && $end_date) {
                $query->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date]);
            }

            // $data = $query->get();

            $chunkCount = 50;
            $totalIIndex = ceil($query->count() / $chunkCount);
            $query->chunk($chunkCount, function ($data, $currentIndex) use ($totalIIndex, $start_date, $end_date) {
                finalizePayrollJob::Dispatch($data, $currentIndex, $totalIIndex, $start_date, $end_date);
            });

            Payroll::where('status', 1)
                ->where('finalize_status', 0)
            // ->where('net_pay','>',0)
                ->whereIn('user_id', $userArray)
                ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
                ->update(['finalize_status' => 1]);

            DB::commit();

            // if ($start_date && $end_date) {
            //     $data = Payroll::with('usersdata', 'positionDetail')->where('status', 1)->whereIn('user_id', $userArray)->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->get();

            // } else {
            //     $data = Payroll::with('usersdata', 'positionDetail')->where('status', 1)->whereIn('user_id', $userArray)->get();
            // }
            // if (count($data) > 0) {

            //     $CrmData = Crms::where('id',3)->where('status',1)->first();
            //     $CrmSetting = CrmSetting::where('crm_id',3)->first();
            //     $data->transform(function ($data) use($start_date,$end_date,$CrmData,$CrmSetting){
            //         //if ($data->net_pay > -1) {
            //             if(!empty($CrmData) && !empty($CrmSetting))
            //             {
            // if($data['is_mark_paid'] != 1 && $data['net_pay'] != 0)
            // {
            //                      $external_id = $data['usersdata']['employee_id']."-".$data->id;
            //                      $untracked = $this->add_payable($data,$external_id);  //update payable in everee
            // }
            //                 $enableEVE = 1;
            //             }
            //             else
            //             {
            //                 $enableEVE = 0;
            //             }
            //             if((isset($untracked['success']['status']) && $untracked['success']['status'] == true) ||  $enableEVE == 0 )
            //             {
            //                 $status = Payroll::where('id',$data->id)->update(['status'=>2]);
            //             }

            //                 //---------------  Genrete pdf -----------------------
            //                 $newData['CompanyProfile'] = CompanyProfile::first();
            //                 $userId = $data->user_id;
            //                 $user = User::where('id',$userId)->first();

            //                 $newData['id'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->where('id','!=',0)->value('id');

            //                 $newData['pay_stub']['pay_date'] = date('Y-m-d',strtotime(Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->where('id','!=',0)->value('updated_at')));
            //                 $newData['pay_stub']['net_pay'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->where('id','!=',0)->sum('net_pay');

            //                 $newData['pay_stub']['pay_period_from'] =  $start_date;
            //                 $newData['pay_stub']['pay_period_to'] =  $end_date;

            //                 $newData['pay_stub']['period_sale_count'] = UserCommission::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
            //                 $newData['pay_stub']['ytd_sale_count'] = UserCommission::where(['user_id'=>$userId,'status'=>'2'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];

            //                 $user = User::with('positionDetailTeam')->where('id',$userId)->select('first_name','middle_name','last_name','employee_id','social_sequrity_no','name_of_bank','routing_no','account_no','type_of_account','home_address','zip_code','email','work_email','position_id')->first();
            //                 $newData['employee'] = $user;
            //                 $newData['employee']['is_reconciliation'] = PositionReconciliations::where('position_id',$user->position_id)->value('status');

            //                 $newData['earnings']['commission']['period_total'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->sum('commission');
            //                 $newData['earnings']['commission']['ytd_total'] = Payroll::where(['user_id'=>$userId,'status'=>'2'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->sum('commission');
            //                 // dd($newData['earnings']['commission']['period_total']); die();
            //                 $newData['earnings']['overrides']['period_total'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->sum('override');
            //                 $newData['earnings']['overrides']['ytd_total'] = Payroll::where(['user_id'=>$userId,'status'=>'2'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->sum('override');

            //                 $newData['earnings']['reconciliation']['period_total'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'finalize'])->sum('reimbursement');
            //                 $newData['earnings']['reconciliation']['ytd_total'] = Payroll::where(['user_id'=>$userId,'status'=>'finalize'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->sum('reimbursement');

            //                 $newData['deduction']['standard_deduction']['period_total'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->where('id','!=',0)->sum('deduction');
            //                 $newData['deduction']['standard_deduction']['ytd_total'] = Payroll::where(['user_id'=>$userId,'status'=>'2'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->where('id','!=',0)->sum('deduction');

            //                 $newData['miscellaneous']['adjustment']['period_total'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->where('id','!=',0)->sum('adjustment');
            //                 $newData['miscellaneous']['adjustment']['ytd_total'] = Payroll::where(['user_id'=>$userId,'status'=>'2'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->where('id','!=',0)->sum('adjustment');

            //                 $newData['miscellaneous']['reimbursement']['period_total'] = Payroll::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'2'])->where('id','!=',0)->sum('reimbursement');
            //                 $newData['miscellaneous']['reimbursement']['ytd_total'] = Payroll::where(['user_id'=>$userId,'status'=>'2'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->where('id','!=',0)->sum('reimbursement');
            //                 DB::commit();
            //                 // You can pass data to the view here if needed

            // $pdfPath = "/template/".$user->first_name.'-'.$user->last_name."_pay_stub.pdf";
            // $pdf = \PDF::loadView('mail.downloadPayStub',[
            //     'user' => $user,
            //     'email' => $user->email,
            //     'start_date' => $user->startDate,
            //     'end_date' => $user->endDate,
            //     'path' => $pdfPath,
            //     'data' => $newData,
            // ]);
            // $viewPdf = file_put_contents("template/".$user->first_name.'-'.$user->last_name."_pay_stub.pdf", $pdf->output());
            // $pdfPath = "/template/".$user->first_name.'-'.$user->last_name."_pay_stub.pdf";

            //             // ---------------  Close PDF ----------------------

            //         // UserCommission::where(['status'=>1,'user_id' =>  $data->user_id, 'pay_period_from' =>  $data->pay_period_from,'pay_period_to' =>  $data->pay_period_to])->update(['status'=>2]);

            //         // UserOverrides::where(['status'=>1,'user_id' => $data->user_id, 'pay_period_from' =>  $data->pay_period_from,'pay_period_to' =>  $data->pay_period_to])->update(['status'=>2]);
            //         //}

            //         // $status = UserCommission::where(['user_id' => $data->user_id, 'status' => '1'])->update(['status'=>2]);

            //     });

            // }
            // $SuccessCount = Payroll::where('status', 2)->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->count();
            // if(count($dcount) == $SuccessCount )
            // {
            $msg = 'Successfully.';

            // }
            // else
            // {
            //     $msg = "Some payroll are not finalized due to users are not Everee!";
            // }
            return response()->json([
                'ApiName' => 'get_payroll_data',
                'status' => true,
                'message' => $msg,
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'get_payroll_data',
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 400);
        }

    }

    public function finalizeStatusPayroll(Request $request): JsonResponse
    {
        try {
            $workerType = isset($request->worker_type) ? $request->worker_type : '1099';
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'finalize_payroll',
                    'status' => false,
                    'error' => $validator->errors(),
                ], 400);
            }

            $startDate = $request->start_date;
            $endDate = $request->end_date;
            $frequencyTypeId = $request->pay_frequency;
            $usersIds = User::where('worker_type', $workerType)->whereIn('sub_position_id', function ($query) use ($frequencyTypeId) {
                $query->select('position_id')->from('position_pay_frequencies')->where('frequency_type_id', $frequencyTypeId);
            })->pluck('id');

            $executing = Payroll::where(['status' => 3, 'is_stop_payroll' => 0])->whereIn('user_id', $usersIds)
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->first();
            if ($executing) {
                return response()->json([
                    'ApiName' => 'finalize_status_Payroll',
                    'status' => true,
                    'message' => 'Executing.',
                    'finalize_status' => 3,
                ]);
            }

            $executed = PayrollHistory::whereIn('user_id', $usersIds)
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->where(['is_onetime_payment' => 0])->first();
            if ($executed) {
                return response()->json([
                    'ApiName' => 'finalize_status_Payroll',
                    'status' => true,
                    'message' => 'Executed.',
                    'finalize_status' => 4,
                ]);
            }

            $finalizing = Payroll::where(['finalize_status' => 1, 'is_stop_payroll' => 0])->whereIn('user_id', $usersIds)
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->first();
            if ($finalizing) {
                return response()->json([
                    'ApiName' => 'finalize_status_Payroll',
                    'status' => true,
                    'message' => 'Finalizing.',
                    'finalize_status' => 2,
                ]);
            }

            $failed = Payroll::where(['finalize_status' => 3, 'is_stop_payroll' => 0])->whereIn('user_id', $usersIds)
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->first();
            if ($failed) {
                return response()->json([
                    'ApiName' => 'finalize_status_Payroll',
                    'status' => false,
                    'message' => 'Payroll processing has failed. Please re-finalize and try again.',
                    'finalize_status' => 0,
                ], 400);
            }

            $nothing = Payroll::where(['finalize_status' => 0, 'is_stop_payroll' => 0])->whereIn('user_id', $usersIds)
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->first();
            if ($nothing) {
                return response()->json([
                    'ApiName' => 'finalize_status_Payroll',
                    'status' => true,
                    'message' => 'Payroll.',
                    'finalize_status' => 0,
                ]);
            }

            $finalized = Payroll::where(['finalize_status' => 2, 'is_stop_payroll' => 0])->whereIn('user_id', $usersIds)
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->first();
            if ($finalized) {
                return response()->json([
                    'ApiName' => 'finalize_status_Payroll',
                    'status' => true,
                    'message' => 'Finalized successfully.',
                    'finalize_status' => 1,
                ]);
            }

            return response()->json([
                'ApiName' => 'finalize_status_Payroll',
                'status' => true,
                'message' => 'No data found!!',
                'finalize_status' => 0,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'finalize_status_Payroll',
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 400);
        }

        // try {
        //     $workerType = isset($request->worker_type) ? $request->worker_type : '1099';

        //     $validator = Validator::make($request->all(), [
        //         'start_date' => 'required|date_format:Y-m-d',
        //         'end_date' => 'required|date_format:Y-m-d',
        //         'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID
        //     ]);

        //     if ($validator->fails()) {
        //         return response()->json([
        //             'ApiName' => 'finalize_payroll',
        //             'status' => false,
        //             'error' => $validator->errors()
        //         ], 400);
        //     }

        //     $start_date = $request->start_date;
        //     $end_date = $request->end_date;
        //     // $search = $request->search;
        //     $executing      = Payroll::with('workertype')->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
        //         $query->whereBetween('pay_period_from',[$request->start_date,$request->end_date])
        //         ->whereBetween('pay_period_to',[$request->start_date,$request->end_date])
        //         ->whereColumn('pay_period_from','pay_period_to');
        //     }, function ($query) use ($request) {
        //         $query->where([
        //             'pay_period_from' => $request->start_date,
        //             'pay_period_to' => $request->end_date
        //         ]);
        //     })
        //     ->where('finalize_status', 5)->where(['is_stop_payroll'=> 0])->whereHas('workertype', function($q) use($workerType){
        //         $q->where('worker_type', $workerType);
        //     })->count();
        //     $finalized      = Payroll::with('workertype')->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
        //         $query->whereBetween('pay_period_from',[$request->start_date,$request->end_date])
        //         ->whereBetween('pay_period_to',[$request->start_date,$request->end_date])
        //         ->whereColumn('pay_period_from','pay_period_to');
        //     }, function ($query) use ($request) {
        //         $query->where([
        //             'pay_period_from' => $request->start_date,
        //             'pay_period_to' => $request->end_date
        //         ]);
        //     })
        //     ->where('finalize_status', 2)->where(['is_stop_payroll'=> 0])->whereHas('workertype', function($q) use($workerType){
        //         $q->where('worker_type', $workerType);
        //     })->count();
        //     $finalizing     = Payroll::with('workertype')->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
        //         $query->whereBetween('pay_period_from',[$request->start_date,$request->end_date])
        //         ->whereBetween('pay_period_to',[$request->start_date,$request->end_date])
        //         ->whereColumn('pay_period_from','pay_period_to');
        //     }, function ($query) use ($request) {
        //         $query->where([
        //             'pay_period_from' => $request->start_date,
        //             'pay_period_to' => $request->end_date
        //         ]);
        //     })
        //     ->where('finalize_status', 1)->where(['is_stop_payroll'=> 0])->whereHas('workertype', function($q) use($workerType){
        //         $q->where('worker_type', $workerType);
        //     })->count();
        //     $fressRecord    = Payroll::with('workertype')->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
        //         $query->whereBetween('pay_period_from',[$request->start_date,$request->end_date])
        //         ->whereBetween('pay_period_to',[$request->start_date,$request->end_date])
        //         ->whereColumn('pay_period_from','pay_period_to');
        //     }, function ($query) use ($request) {
        //         $query->where([
        //             'pay_period_from' => $request->start_date,
        //             'pay_period_to' => $request->end_date
        //         ]);
        //     })
        //     ->where('finalize_status', 0)->where(['is_stop_payroll'=> 0])->whereHas('workertype', function($q) use($workerType){
        //         $q->where('worker_type', $workerType);
        //     })->count();
        //     $failed         = Payroll::with('workertype')->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
        //         $query->whereBetween('pay_period_from',[$request->start_date,$request->end_date])
        //         ->whereBetween('pay_period_to',[$request->start_date,$request->end_date])
        //         ->whereColumn('pay_period_from','pay_period_to');
        //     }, function ($query) use ($request) {
        //         $query->where([
        //             'pay_period_from' => $request->start_date,
        //             'pay_period_to' => $request->end_date
        //         ]);
        //     })
        //     ->where('finalize_status', 3)->where(['is_stop_payroll'=> 0])->whereHas('workertype', function($q) use($workerType){
        //         $q->where('worker_type', $workerType);
        //     })->count();
        //     $message = '';
        //     $finalize_status = 0;
        //     $responnse_code =  200;
        //     $responnse_status = true;
        //     if($executing > 0){
        //         $message = "Executing";
        //         $finalize_status = 5;  // finalizing
        //     }else if ($finalizing > 0) {
        //         $message = "Finalizing";
        //         $finalize_status = 2;  // finalizing
        //     }else if (($finalized > 0 && $fressRecord == 0) || $failed>0 ){
        //         $message = $failed > 0 ? "The net amount finalized for the specified users doesn't align with the current updated net amount. As a result, payroll processing for these users cannot be completed. Please re-finalize the payroll after verifying the net amount." : "Finalized successfully.";
        //         $finalize_status = $failed > 0 ? 0 : 1;
        //         $responnse_code = $failed > 0 ? 400 : 200;
        //         $responnse_status = $failed > 0 ? false : true;
        //         // $finalize_status = 1;   // Finalized successfully
        //     }else if ($fressRecord > 0) {
        //         $message = "Some new records found in payroll.";
        //         $finalize_status = 0;   // finalize now
        //     }

        //     return response()->json([
        //         'ApiName' => 'finalize_status_Payroll',
        //         'status' => $responnse_status,
        //         'message' => $message,
        //         'finalize_status' => $finalize_status,
        //     ], $responnse_code);

        // }catch(\Exception $e){
        //     DB::rollBack();
        //     return response()->json([
        //         'ApiName' => 'finalize_status_Payroll',
        //         'status' => false,
        //         'message' => $e->getMessage(),
        //         'line'=>$e->getLine()
        //     ], 400);
        // }
    }

    public function executePayroll(Request $request)
    {
        try {
            $data = [];
            $start_date = $request->start_date;
            $end_date = $request->end_date;
            $search = $request->search;
            $pay_frequency = $request->pay_frequency;
            $chunkCount = 50;

            // ---------------- Jobs Queues code by deep ---------------------
            $query = Payroll::with('usersdata', 'positionDetail')
                ->where('status', 2)
            // ->where('net_pay','>',0)
                ->where('finalize_status', 2)
                ->whereIn('user_id', function ($query) use ($request) {
                    $query->select('id')
                        ->from('users')
                        ->orderBy('id', 'asc');

                    // ----------------- search condition to the user query
                    if ($request->has('search') && ! empty($request->input('search'))) {
                        $query->where(function ($subQuery) use ($request) {
                            $subQuery->where('first_name', 'LIKE', '%'.$request->input('search').'%')
                                ->orWhere('last_name', 'LIKE', '%'.$request->input('search').'%');
                        });
                    }
                });
            // ------------------- date condition to the payroll query
            if ($start_date && $end_date) {
                $query->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date]);
            }

            // updating the finalize_status to 5 so that the executing button will be disabled untill executee is not complete
            // Payroll::where('status', 2)
            // ->where('finalize_status',2)
            // ->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])
            // ->update(['finalize_status'=>5]);

            // CRITICAL: Use model instance save() to trigger observers (not mass update)
            $weekly = WeeklyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->first();
            if ($weekly) {
                $weekly->closed_status = 1;
                $weekly->save();
            }
            
            $monthly = MonthlyPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->first();
            if ($monthly) {
                $monthly->closed_status = 1;
                $monthly->save();
            }
            
            $totalIIndex = ceil($query->count() / $chunkCount);
            $query->chunk($chunkCount, function ($data, $currentIndex) use ($totalIIndex, $start_date, $end_date, $pay_frequency) {
                executePayrollJob::Dispatch($data, $currentIndex, $totalIIndex, $start_date, $end_date, $pay_frequency);
            });

            return response()->json([
                'ApiName' => 'execute_Payroll',
                'status' => true,
                'message' => 'payroll execution request sent successfully',
                // 'data' => $createdata,
            ], 200);

            // ---------------- End Jobs Queues code by deep ---------------------

            // -------------comment code by deep -------------------

            //     $users = User::orderBy('id', 'asc');
            //     // if ($request->has('search') && !empty($request->input('search'))) {
            //     //     $users->where(function ($query) use ($request) {
            //     //         return $query->where('first_name', 'LIKE', '%' . $request->input('search') . '%')
            //     //             ->orWhere('last_name', 'LIKE', '%' . $request->input('search') . '%');
            //     //     });
            //     // }
            //     //$userdata = $users->get();
            //     $userArray = $users->pluck('id')->toArray();

            // if ($start_date && $end_date) {
            //     $data = Payroll::with('usersdata', 'positionDetail')->where('status', 2)->whereIn('user_id', $userArray)->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->get();

            // } else {
            //     $data = Payroll::with('usersdata', 'positionDetail')->where('status', 2)->whereIn('user_id', $userArray)->get();
            // }
            // // return $data;

            // $untracked=[];
            // $enableEVE = 0;
            // $count = 0;
            // if (count($data) > 0) {
            //     $CrmData = Crms::where('id',3)->where('status',1)->first();
            //     $CrmSetting = CrmSetting::where('crm_id',3)->first();

            //     DB::beginTransaction();
            //     $data->transform(function ($data) use($start_date,$end_date,$CrmData,$CrmSetting,$count){
            //         $external_id = $data['usersdata']['employee_id']."-".$data->id;
            //         if(!empty($CrmData) && !empty($CrmSetting))
            //         {
            //             $enableEVE = 1;
            //             $untracked = $this->payable_request($data,$external_id); //update payable in everee
            //         }
            //         else
            //         {
            //             $enableEVE = 0;
            //         }
            //         $payroll = Payroll::where(['id' => $data->id,'status'=>'2'])->first();
            //         if((isset($untracked['success']['status']) && $untracked['success']['status'] == true) ||  $enableEVE == 0 )
            //         {
            //             $count = ($count+1);
            //             $createdata = [
            //                 'payroll_id'=> $payroll->id,
            //                 'user_id' => $payroll->user_id,
            //                 'position_id' => $payroll->position_id,
            //                 'commission' => $payroll->commission,
            //                 'override' => $payroll->override,
            //                 'reimbursement' => $payroll->reimbursement,
            //                 'clawback' => $payroll->clawback,
            //                 'deduction' => $payroll->deduction,
            //                 'adjustment' => $payroll->adjustment,
            //                 'reconciliation' => $payroll->reconciliation,
            //                 'net_pay' => $payroll->net_pay,
            //                 'pay_period_from' => $payroll->pay_period_from,
            //                 'pay_period_to' => $payroll->pay_period_to,
            //                 'status' => '3',
            //                 'pay_type' => 'Bank',
            //                 'pay_frequency_date'=>$payroll->created_at,
            //                 'everee_external_id'=>$external_id,
            //                 'everee_payment_requestId'=>isset($untracked['success']['paymentId'])?$untracked['success']['paymentId']:null
            //             ];
            //             $insert = PayrollHistory::create($createdata);
            //             $status = UserCommission::where(['user_id' => $data->user_id,'pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['status'=>3]);
            //             UserOverrides::where(['user_id' => $data->user_id,'pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['status'=>3]);
            //             ClawbackSettlement::where(['user_id' => $data->user_id,'pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['status'=>3]);

            //             $payrollDelete = Payroll::where(['id' => $data->id,'status'=>'2'])->delete();
            //             $weekly = WeeklyPayFrequency::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['closed_status'=>1]);
            //             $monthly = MonthlyPayFrequency::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['closed_status'=>1]);

            //             $userReconcilationCommission = UserReconciliationCommission::where('user_id',$data->user_id)->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date,'payroll_id'=>$payroll->id])->update(['status'=>'paid']);
            //             $approvelAndRequest = ApprovalsAndRequest::where('status','Accept')->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['status'=>'Paid']);
            //             // Added By DeepaK
            //             $approvelAndRequestData = ApprovalsAndRequest::where('amount','>',0)->whereNotNull('req_no')->where(['user_id'=> $data->user_id, 'status'=>'Paid', 'adjustment_type_id'=> 4, 'pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->get();
            //             $payFrequency = $this->payFrequency($start_date, $payroll->position_id);
            //             $startDateNext = $payFrequency->next_pay_period_from;
            //             $endDateNext = $payFrequency->next_pay_period_to;
            //             $adjustmentTotal = 0;
            //             foreach ($approvelAndRequestData as $key => $appuser) {
            //                 $add = ApprovalsAndRequest::create([
            //                     'user_id' => $appuser->user_id,
            //                     'manager_id' => $appuser->manager_id,
            //                     'approved_by' => $appuser->approved_by,
            //                     'adjustment_type_id' => $appuser->adjustment_type_id,
            //                     'state_id' => $appuser->state_id,
            //                     'dispute_type' => $appuser->dispute_type,
            //                     'customer_pid' => $appuser->customer_pid,
            //                     'cost_tracking_id' => $appuser->cost_tracking_id,
            //                     'cost_date' => $appuser->cost_date,
            //                     'request_date' => $appuser->request_date,
            //                     'amount'  => (0 - $appuser->amount),
            //                     'status' => 'Accept',
            //                     'pay_period_from' => isset($startDateNext)? $startDateNext : null,
            //                     'pay_period_to' => isset($endDateNext)? $endDateNext : null,
            //                 ]);
            //                 $adjustmentTotal += $appuser->amount;
            //             }

            //             $payRoll = PayRoll::where(['user_id'=> $payroll->user_id, 'pay_period_from' => $startDateNext, 'pay_period_to' => $endDateNext])->first();
            //             if (empty($payRoll)) {
            //                 PayRoll::create([
            //                     'user_id'     => $payroll->user_id,
            //                     'position_id' => $payroll->position_id,
            //                     'adjustment'  => (0 - $adjustmentTotal),
            //                     'pay_period_from' => isset($startDateNext)? $startDateNext : null,
            //                     'pay_period_to' => isset($endDateNext)? $endDateNext : null,
            //                     'status'      => 1,
            //                 ]);
            //             }else{
            //                 PayRoll::where(['user_id'=> $payroll->user_id, 'pay_period_from' => $startDateNext, 'pay_period_to' => $endDateNext])
            //                 ->update([
            //                     'adjustment'  => ((0 - $adjustmentTotal) + $payRoll->adjustment),
            //                 ]);
            //             }

            //             $userDevice = User::where('id', $data->user_id)->first();
            //             $note =  Notification::create([
            //                 'user_id' => $payroll->user_id,
            //                 'type' => 'Execute PayRoll',
            //                 'description' => 'Execute PayRoll Data',
            //                 'is_read' => 0,
            //             ]);
            //             DB::commit();
            //             $notificationData = array(
            //                 'user_id'      => $userDevice->id,
            //                 'device_token' => $userDevice->device_token,
            //                 'title'        => 'Execute PayRoll Data.',
            //                 'sound'        => 'sound',
            //                 'type'         => 'Execute PayRoll',
            //                 'body'         => 'Updated Execute PayRoll Data',
            //             );
            //             $this->sendNotification($notificationData);
            //         }

            //     });

            // }

            // if(count($data) > 0)
            // {
            //     $pdata = Payroll::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->get();
            //     if(count($pdata) > 0)
            //     {
            //         $weekly = WeeklyPayFrequency::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['closed_status'=>0]);
            //         $monthly = MonthlyPayFrequency::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['closed_status'=>0]);
            //     }
            //     return response()->json([
            //         'ApiName' => 'execute_Payroll',
            //         'status' => true,
            //         'message' => 'Successfully.',
            //         //'data' => $createdata,
            //     ], 200);
            // }

            // -------------end comment by deep --------------------

            // else{
            //     $weekly = WeeklyPayFrequency::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['closed_status'=>0]);
            //     $monthly = MonthlyPayFrequency::where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date])->update(['closed_status'=>0]);

            //     return response()->json([
            //         'ApiName' => 'execute_Payroll',
            //         'status' => true,
            //         'message' => 'Some payroll not executed because they are not added in everee.',
            //     ], 200);
            // }

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'execute_Payroll',
                'status' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 400);
        }

    }

    public function getFinalizePayroll(Request $request): JsonResponse
    {

        $payrollId = $request->id;
        if ($payrollId) {
            $result = Payroll::where('status', 2)->where('id', $payrollId)->first();
        } else {
            $result = Payroll::where('status', 2)->orderBy('id', 'Asc')->get();
        }

        return response()->json([
            'ApiName' => 'get_finalize_payroll',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ], 200);

    }

    public function getSummaryPayroll_old(Request $request)
    {
        $start_date = isset($request->start_date) ? $request->start_date : '';
        $end_date = isset($request->end_date) ? $request->end_date : '';
        $managerPosition = auth()->user()->position;
        $managerId = auth()->user()->id;
        if ($start_date != '' && $end_date != '') {
            $user = User::pluck('id');
            $manager = User::where('position_id', 1)->pluck('id');
            $manager = User::where('position_id', 1)->pluck('id');
            $closer1m1 = SaleMasterProcess::whereIn('closer1_id', $user)->sum('closer1_m1');
            $closer2m1 = SaleMasterProcess::whereIn('closer2_id', $user)->sum('closer2_m1');
            $setter1m1 = SaleMasterProcess::whereIn('setter1_id', $user)->sum('setter1_m1');
            $setter2m1 = SaleMasterProcess::whereIn('setter2_id', $user)->sum('setter2_m1');
            $m1 = $closer1m1 + $closer2m1 + $setter1m1 + $setter2m1;

            $closer1m2 = SaleMasterProcess::whereIn('closer1_id', $user)->sum('closer1_m2');
            $closer2m2 = SaleMasterProcess::whereIn('closer2_id', $user)->sum('closer2_m2');
            $setter1m2 = SaleMasterProcess::whereIn('setter1_id', $user)->sum('setter1_m2');
            $setter2m2 = SaleMasterProcess::whereIn('setter2_id', $user)->sum('setter2_m2');
            $m2 = $closer1m2 + $closer2m2 + $setter1m2 + $setter2m2;

            $closer = $closer1m1 + $closer2m1 + $closer1m2 + $closer2m2;
            $setter = $setter1m1 + $setter2m1 + $setter1m2 + $setter2m2;

            $override = UserOverrides::whereIn('sale_user_id', $user)->where('status', 1)->sum('amount');
            $adjustment = ApprovalsAndRequest::where('status', 'Accept')->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->sum('amount');
            $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['adjustment_type_id' => '2'])->sum('amount');
            $deduction = DeductionAlert::whereIn('user_id', $user)->sum('amount');

            // return $payRollByPosition = Positions::withSum('PayRollByPosition:commission')->get();
            $payRollByPosition = DB::table('positions')
                ->select('positions.id', 'payrolls.position_id', 'positions.position_name', DB::raw('SUM(payrolls.commission) AS commissionTotal'), DB::raw('SUM(payrolls.override) AS overrideTotal'), DB::raw('SUM(payrolls.reimbursement) AS reimbursementTotal'), DB::raw('SUM(payrolls.clawback) AS clawbackTotal'), DB::raw('SUM(payrolls.deduction) AS deductionTotal'))
                ->LeftJoin('payrolls', 'payrolls.position_id', '=', 'positions.id')
                ->groupBy('positions.id')
                ->get();

            $payRollByPositionTotal = 0;
            for ($i = 0; $i < count($payRollByPosition); $i++) {
                $payRollByPositionTotal += $payRollByPosition[$i]->commissionTotal + $payRollByPosition[$i]->overrideTotal + $payRollByPosition[$i]->reimbursementTotal + $payRollByPosition[$i]->clawbackTotal + $payRollByPosition[$i]->deductionTotal;
            }

            $payRollByPosition->transform(function ($payRollByPosition) {
                return [
                    'position_name' => $payRollByPosition->position_name,
                    'total' => $payRollByPosition->commissionTotal + $payRollByPosition->overrideTotal + $payRollByPosition->reimbursementTotal + $payRollByPosition->clawbackTotal + $payRollByPosition->deductionTotal,
                ];
            });

            $payRollByLocation = State::with('user')->get();
            // return $payRollByLocation;
            $stateName = [];
            $payRollByStateTotal = 0;
            $stateTotal = 0;
            foreach ($payRollByLocation as $key => $payRollByLocations) {
                $payRollByLocation = [];
                foreach ($payRollByLocations->user as $users) {
                    $payRollByLocation = DB::table('payrolls')
                        ->select('user_id', DB::raw('SUM(payrolls.commission) AS commissionTotal'), DB::raw('SUM(payrolls.override) AS overrideTotal'), DB::raw('SUM(payrolls.reimbursement) AS reimbursementTotal'), DB::raw('SUM(payrolls.clawback) AS clawbackTotal'), DB::raw('SUM(payrolls.deduction) AS deductionTotal'))
                        ->where('user_id', $users->id)
                        ->first();
                    // dd($payRollByLocation);
                    $payRollByStateTotal += $payRollByLocation->commissionTotal + $payRollByLocation->overrideTotal + $payRollByLocation->reimbursementTotal + $payRollByLocation->clawbackTotal - $payRollByLocation->deductionTotal;
                    $stateTotal += $payRollByStateTotal;
                }
                if ($payRollByStateTotal > 0) {
                    $stateName[$key]['state'] = $payRollByLocations->name;
                    $stateName[$key]['total'] = $payRollByStateTotal;
                }
                $payRollByStateTotal = 0;
            }

            // return $payRollByPartner = SalesMaster::where('install_partner','!=',null)->groupBy('install_partner')->get();

            $payRollByPartner = DB::table('sale_masters')
                ->select('sale_masters.pid', 'sale_masters.install_partner', DB::raw('SUM(sale_master_process.closer1_m1) AS closer1m1'), DB::raw('SUM(sale_master_process.closer2_m1) AS closer2m1'), DB::raw('SUM(sale_master_process.closer1_m2) AS closer1m2'), DB::raw('SUM(sale_master_process.closer2_m2) AS closer2m2'), DB::raw('SUM(sale_master_process.setter1_m1) AS setter1m1'), DB::raw('SUM(sale_master_process.setter2_m1) AS setter2m1'), DB::raw('SUM(sale_master_process.setter1_m2) AS setter1m2'), DB::raw('SUM(sale_master_process.setter2_m2) AS setter2m2'))
                ->LeftJoin('sale_master_process', 'sale_master_process.pid', '=', 'sale_masters.pid')
                ->where('install_partner', '!=', null)
                ->groupBy('sale_masters.install_partner')
                ->get();

            $payRollByPartnerTotal = 0;
            for ($i = 0; $i < count($payRollByPartner); $i++) {
                $payRollByPartnerTotal += $payRollByPartner[$i]->closer1m1 + $payRollByPartner[$i]->closer2m1 + $payRollByPartner[$i]->closer1m2 + $payRollByPartner[$i]->closer2m2 + $payRollByPartner[$i]->setter1m1 + $payRollByPartner[$i]->setter2m1 + $payRollByPartner[$i]->setter1m2 + $payRollByPartner[$i]->setter2m2;

            }

            $payRollByPartner->transform(function ($payRollByPartner) {
                return [
                    'position_name' => $payRollByPartner->install_partner,
                    'total' => $payRollByPartner->closer1m1 + $payRollByPartner->closer2m1 + $payRollByPartner->closer1m2 + $payRollByPartner->closer2m2 + $payRollByPartner->setter1m1 + $payRollByPartner->setter2m1 + $payRollByPartner->setter1m2 + $payRollByPartner->setter2m2,
                ];
            });

        } else {
            $user = User::pluck('id');
            $manager = User::where('position_id', 1)->pluck('id');
            $manager = User::where('position_id', 1)->pluck('id');
            $closer1m1 = SaleMasterProcess::whereIn('closer1_id', $user)->sum('closer1_m1');
            $closer2m1 = SaleMasterProcess::whereIn('closer2_id', $user)->sum('closer2_m1');
            $setter1m1 = SaleMasterProcess::whereIn('setter1_id', $user)->sum('setter1_m1');
            $setter2m1 = SaleMasterProcess::whereIn('setter2_id', $user)->sum('setter2_m1');
            $m1 = $closer1m1 + $closer2m1 + $setter1m1 + $setter2m1;

            $closer1m2 = SaleMasterProcess::whereIn('closer1_id', $user)->sum('closer1_m2');
            $closer2m2 = SaleMasterProcess::whereIn('closer2_id', $user)->sum('closer2_m2');
            $setter1m2 = SaleMasterProcess::whereIn('setter1_id', $user)->sum('setter1_m2');
            $setter2m2 = SaleMasterProcess::whereIn('setter2_id', $user)->sum('setter2_m2');
            $m2 = $closer1m2 + $closer2m2 + $setter1m2 + $setter2m2;

            $closer = $closer1m1 + $closer2m1 + $closer1m2 + $closer2m2;
            $setter = $setter1m1 + $setter2m1 + $setter1m2 + $setter2m2;

            $override = UserOverrides::whereIn('sale_user_id', $user)->where('status', 0)->sum('amount');
            $adjustment = ApprovalsAndRequest::where('status', 'Accept')->whereIn('adjustment_type_id', [1, 3, 4, 5, 6, 13])->sum('amount');
            $reimbursement = ApprovalsAndRequest::where('status', 'Accept')->where(['adjustment_type_id' => '2'])->sum('amount');
            $deduction = DeductionAlert::whereIn('user_id', $user)->sum('amount');

            // return $payRollByPosition = Positions::withSum('PayRollByPosition:commission')->get();
            $payRollByPosition = DB::table('positions')
                ->select('positions.id', 'payrolls.position_id', 'positions.position_name', DB::raw('SUM(payrolls.commission) AS commissionTotal'), DB::raw('SUM(payrolls.override) AS overrideTotal'), DB::raw('SUM(payrolls.reimbursement) AS reimbursementTotal'), DB::raw('SUM(payrolls.clawback) AS clawbackTotal'), DB::raw('SUM(payrolls.deduction) AS deductionTotal'))
                ->LeftJoin('payrolls', 'payrolls.position_id', '=', 'positions.id')
                ->groupBy('positions.id')
                ->get();

            $payRollByPositionTotal = 0;
            for ($i = 0; $i < count($payRollByPosition); $i++) {
                $payRollByPositionTotal += $payRollByPosition[$i]->commissionTotal + $payRollByPosition[$i]->overrideTotal + $payRollByPosition[$i]->reimbursementTotal + $payRollByPosition[$i]->clawbackTotal + $payRollByPosition[$i]->deductionTotal;
            }

            $payRollByPosition->transform(function ($payRollByPosition) {
                return [
                    'position_name' => $payRollByPosition->position_name,
                    'total' => $payRollByPosition->commissionTotal + $payRollByPosition->overrideTotal + $payRollByPosition->reimbursementTotal + $payRollByPosition->clawbackTotal + $payRollByPosition->deductionTotal,
                ];
            });

            $payRollByLocation = State::with('user')->get();
            // return $payRollByLocation;
            $stateName = [];
            $payRollByStateTotal = 0;
            $stateTotal = 0;
            foreach ($payRollByLocation as $key => $payRollByLocations) {
                $payRollByLocation = [];
                foreach ($payRollByLocations->user as $users) {
                    $payRollByLocation = DB::table('payrolls')
                        ->select('user_id', DB::raw('SUM(payrolls.commission) AS commissionTotal'), DB::raw('SUM(payrolls.override) AS overrideTotal'), DB::raw('SUM(payrolls.reimbursement) AS reimbursementTotal'), DB::raw('SUM(payrolls.clawback) AS clawbackTotal'), DB::raw('SUM(payrolls.deduction) AS deductionTotal'))
                        ->where('user_id', $users->id)
                        ->first();
                    // dd($payRollByLocation);
                    $payRollByStateTotal += $payRollByLocation->commissionTotal + $payRollByLocation->overrideTotal + $payRollByLocation->reimbursementTotal + $payRollByLocation->clawbackTotal + $payRollByLocation->deductionTotal;
                    $stateTotal += $payRollByStateTotal;
                }
                if ($payRollByStateTotal > 0) {
                    $stateName[$key]['state'] = $payRollByLocations->name;
                    $stateName[$key]['total'] = $payRollByStateTotal;
                }
                $payRollByStateTotal = 0;
            }

            // return $payRollByPartner = SalesMaster::where('install_partner','!=',null)->groupBy('install_partner')->get();

            $payRollByPartner = DB::table('sale_masters')
                ->select('sale_masters.pid', 'sale_masters.install_partner', DB::raw('SUM(sale_master_process.closer1_m1) AS closer1m1'), DB::raw('SUM(sale_master_process.closer2_m1) AS closer2m1'), DB::raw('SUM(sale_master_process.closer1_m2) AS closer1m2'), DB::raw('SUM(sale_master_process.closer2_m2) AS closer2m2'), DB::raw('SUM(sale_master_process.setter1_m1) AS setter1m1'), DB::raw('SUM(sale_master_process.setter2_m1) AS setter2m1'), DB::raw('SUM(sale_master_process.setter1_m2) AS setter1m2'), DB::raw('SUM(sale_master_process.setter2_m2) AS setter2m2'))
                ->LeftJoin('sale_master_process', 'sale_master_process.pid', '=', 'sale_masters.pid')
                ->where('install_partner', '!=', null)
                ->groupBy('sale_masters.install_partner')
                ->get();

            $payRollByPartnerTotal = 0;
            for ($i = 0; $i < count($payRollByPartner); $i++) {
                $payRollByPartnerTotal += $payRollByPartner[$i]->closer1m1 + $payRollByPartner[$i]->closer2m1 + $payRollByPartner[$i]->closer1m2 + $payRollByPartner[$i]->closer2m2 + $payRollByPartner[$i]->setter1m1 + $payRollByPartner[$i]->setter2m1 + $payRollByPartner[$i]->setter1m2 + $payRollByPartner[$i]->setter2m2;

            }

            $payRollByPartner->transform(function ($payRollByPartner) {
                return [
                    'position_name' => $payRollByPartner->install_partner,
                    'total' => $payRollByPartner->closer1m1 + $payRollByPartner->closer2m1 + $payRollByPartner->closer1m2 + $payRollByPartner->closer2m2 + $payRollByPartner->setter1m1 + $payRollByPartner->setter2m1 + $payRollByPartner->setter1m2 + $payRollByPartner->setter2m2,
                ];
            });
        }

        $data = [];
        $payroll = [];
        $payroll['m1'] = round($m1, 3);
        $payroll['m2'] = round($m2, 3);
        $payroll['override'] = round($override, 3);
        $payroll['adjustment'] = round($adjustment, 3);
        $payroll['reimbursement'] = round($reimbursement, 3);
        $payroll['deduction'] = round($deduction, 3);
        $payroll['total_payroll'] = round($m1 + $m2 + $override + $adjustment + $reimbursement + $deduction, 3);

        $earning = [];

        $earning['override'] = $override;
        $earning['adjustment'] = $adjustment;
        $earning['reimbursement'] = $reimbursement;
        $earning['deduction'] = $deduction;
        $earning['total_payroll'] = $m1 + $m2 + $override + $adjustment + $reimbursement + $deduction;

        $data['payroll'] = $payroll;
        $data['earning_summary'] = $payRollByPartner;
        $data['earning_summary_total_payroll'] = $payRollByPartnerTotal;
        $data['payroll_by_location'] = $stateName;
        $data['payroll_by_location_total'] = $stateTotal;
        $data['payroll_by_position'] = $payRollByPosition;
        $data['total_payroll_by_position'] = $payRollByPositionTotal;

        return response()->json([
            'ApiName' => 'Summary Payroll Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getSummaryPayroll(Request $request)
    {
        $startDate = isset($request->start_date) ? $request->start_date : '';
        $endDate = isset($request->end_date) ? $request->end_date : '';

        $m1 = 0;
        $m2 = 0;

        $fdate = $request->start_date;
        $tdate = $request->end_date;
        $datetime1 = new DateTime($fdate);
        $datetime2 = new DateTime($tdate);
        $interval = $datetime1->diff($datetime2);
        $days = $interval->format('%a');
        if ($days < 7) {
            $sdays = $interval->format('%a') + 1;
            $edays = $interval->format('%a');
        } else {
            $sdays = $interval->format('%a') + 2;
            $edays = $interval->format('%a') + 1;
        }

        $priviesStartDate = Carbon::parse($fdate)->subDays($sdays);
        $priviesStartDate = date('Y-m-d', strtotime($priviesStartDate));
        $priviesEndDate = Carbon::parse($priviesStartDate)->addDays($edays);
        $priviesEndDate = date('Y-m-d', strtotime($priviesEndDate));

        if ($startDate != '' && $endDate != '') {
            // Current Payroll Summary Start
            $payrolldata =
            Payroll::select(
                DB::raw('sum(commission) as commission'),
                DB::raw('sum(override) as override'),
                DB::raw('sum(adjustment) as adjustment'),
                DB::raw('sum(reimbursement) as reimbursement'),
                DB::raw('sum(reconciliation) as reconciliation'),
                DB::raw('sum(deduction) as deduction'),
                DB::raw('sum(net_pay) as net_pay')
            )
                ->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();

            $m1 = $payrolldata->commission;
            $override = $payrolldata->override;
            $adjustment = $payrolldata->adjustment;
            $reimbursement = $payrolldata->reimbursement;
            $reconciliation = $payrolldata->reconciliation;
            $deduction = $payrolldata->deduction;
            $totalPayroll = $payrolldata->net_pay;
            $payroll_sum = $m1 + $m2 + $override + $adjustment + $reimbursement + $reconciliation + $deduction;
            // Current Payroll Summary Stop

            // Current Payroll By Position Start
            $payRollByPosition =
            DB::table('positions')
                ->select(
                    'positions.id',
                    'payrolls.pay_period_from',
                    'payrolls.pay_period_to',
                    'payrolls.position_id',
                    'positions.position_name',
                    DB::raw('SUM(payrolls.net_pay) AS netPayTotal')
                )
                ->LeftJoin('payrolls', 'payrolls.position_id', '=', 'positions.id')
                ->where(['payrolls.pay_period_from' => $startDate, 'payrolls.pay_period_to' => $endDate])
                ->groupBy('positions.id')
                ->get();

            $payRollByPositionTotal = 0;
            for ($i = 0; $i < count($payRollByPosition); $i++) {
                $payRollByPositionTotal += $payRollByPosition[$i]->netPayTotal;

            }
            $payRollByPosition->transform(function ($payRollByPosition) {
                return [
                    'position_name' => $payRollByPosition->position_name,
                    'total' => $payRollByPosition->netPayTotal,
                ];
            });
            // Current Payroll By Position Stop

            // Current Payroll By Location Start
            $payRollByLocation = State::with('user')->get();
            $stateName = [];
            $payRollByStateTotal = 0;
            $stateTotal = 0;
            foreach ($payRollByLocation as $key => $payRollByLocations) {
                $payRollByLocation = [];
                foreach ($payRollByLocations->user as $users) {
                    $payRollByLocation = DB::table('payrolls')
                        ->select('user_id', DB::raw('SUM(payrolls.net_pay) AS netPayTotal'))
                        ->where('user_id', $users->id)
                        ->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])
                        ->first();
                    $payRollByStateTotal += $payRollByLocation->netPayTotal;
                }
                $stateTotal += $payRollByStateTotal;

                if ($payRollByStateTotal != 0) {
                    $stateName[] = [
                        'state' => $payRollByLocations->name,
                        'total' => $payRollByStateTotal,
                    ];
                }
                $payRollByStateTotal = 0;

            }
            // Current Payroll By Location Stop
        }
        if ($priviesStartDate != '' && $priviesEndDate != '') {
            $priviesStartDate = date('Y-m-d', strtotime($priviesStartDate));
            $priviesEndDate = date('Y-m-d', strtotime($priviesEndDate));

            // Previous Payroll Summary Start
            $payrollolddata =
            Payroll::select(
                DB::raw('sum(commission) as commission'),
                DB::raw('sum(override) as override'),
                DB::raw('sum(adjustment) as adjustment'),
                DB::raw('sum(reimbursement) as reimbursement'),
                DB::raw('sum(reconciliation) as reconciliation'),
                DB::raw('sum(deduction) as deduction'),
                DB::raw('sum(net_pay) as net_pay')
            )
                ->where(['pay_period_from' => $priviesStartDate, 'pay_period_to' => $priviesEndDate])->first();

            $priviesCommission = $payrollolddata->commission;
            $priviesOverride = $payrollolddata->override;
            $priviesAdjustment = $payrollolddata->adjustment;
            $priviesReimbursement = $payrollolddata->reimbursement;
            $priviesDeduction = $payrollolddata->deduction;
            $priviesTotalPayroll = $priviesCommission + $priviesOverride + $priviesAdjustment + $priviesReimbursement + $priviesDeduction;
            // Previous Payroll Summary Stop

            // Previous Payroll By Position Start
            $priviesPayRollByPosition =
            DB::table('positions')
                ->select(
                    'positions.id',
                    'payrolls.pay_period_from',
                    'payrolls.pay_period_to',
                    'payrolls.position_id',
                    'positions.position_name',
                    DB::raw('SUM(payrolls.commission) AS commissionTotal'),
                    DB::raw('SUM(payrolls.override) AS overrideTotal'),
                    DB::raw('SUM(payrolls.reimbursement) AS reimbursementTotal'),
                    DB::raw('SUM(payrolls.clawback) AS clawbackTotal'),
                    DB::raw('SUM(payrolls.deduction) AS deductionTotal'))
                ->LeftJoin('payrolls', 'payrolls.position_id', '=', 'positions.id')
                ->where(['payrolls.pay_period_from' => $priviesStartDate, 'payrolls.pay_period_to' => $priviesEndDate])
                ->groupBy('positions.id')
                ->get();

            $priviesPayRollByPositionTotal = 0;

            for ($i = 0; $i < count($priviesPayRollByPosition); $i++) {
                $priviesPayRollByPositionTotal += $priviesPayRollByPosition[$i]->commissionTotal + $priviesPayRollByPosition[$i]->overrideTotal + $priviesPayRollByPosition[$i]->reimbursementTotal + $priviesPayRollByPosition[$i]->clawbackTotal + $priviesPayRollByPosition[$i]->deductionTotal;
            }

            $priviesPayRollByPosition->transform(function ($priviesPayRollByPosition) {
                return [
                    'position_name' => $priviesPayRollByPosition->position_name,
                    'total' => $priviesPayRollByPosition->commissionTotal + $priviesPayRollByPosition->overrideTotal + $priviesPayRollByPosition->reimbursementTotal + $priviesPayRollByPosition->clawbackTotal + $priviesPayRollByPosition->deductionTotal,
                ];
            });
            // Previous Payroll By Position Stop

            // Previous Payroll By Location Start
            $priviesPayRollByLocation = State::with('user')->get();
            $priviesStateName = [];
            $priviesPayRollByStateTotal = 0;
            $priviesStateTotal = 0;
            foreach ($priviesPayRollByLocation as $key => $priviesPayRollByLocations) {
                $priviesPayRollByLocation = [];
                foreach ($priviesPayRollByLocations->user as $users) {
                    $priviesPayRollByLocation = DB::table('payrolls')
                        ->select(
                            'user_id',
                            DB::raw('SUM(payrolls.commission) AS commissionTotal'),
                            DB::raw('SUM(payrolls.override) AS overrideTotal'),
                            DB::raw('SUM(payrolls.reimbursement) AS reimbursementTotal'),
                            DB::raw('SUM(payrolls.clawback) AS clawbackTotal'),
                            DB::raw('SUM(payrolls.deduction) AS deductionTotal'))
                        ->where('user_id', $users->id)
                    // ->whereBetween('created_at', [$start_date, $end_date])
                        ->where(['pay_period_from' => $priviesStartDate, 'pay_period_to' => $priviesEndDate])
                        ->first();
                    // dd($payRollByLocation);
                    $priviesPayRollByStateTotal += $priviesPayRollByLocation->commissionTotal + $priviesPayRollByLocation->overrideTotal + $priviesPayRollByLocation->reimbursementTotal + $priviesPayRollByLocation->clawbackTotal + $priviesPayRollByLocation->deductionTotal;

                }
                $priviesStateTotal += $priviesPayRollByStateTotal;
                if ($priviesPayRollByStateTotal > 0) {
                    $priviesStateName[] = [
                        'state' => $priviesPayRollByLocations->name,
                        'total' => $priviesPayRollByStateTotal,
                    ];
                }
            }
            // Previous Payroll By Position Stop
        }

        $currentPayroll = round($payroll_sum, 3);
        $priviesPayroll = round($priviesTotalPayroll, 3);

        if ($priviesPayroll > 0) {
            $diff = ($currentPayroll / $priviesPayroll) * 100;
        } else {
            $diff = 0;
        }

        $more_less = $diff > 100 ? 'More' : 'Less';

        if ($currentPayroll > 0 && $priviesPayroll > 0) {
            $percentage = (($currentPayroll - $priviesPayroll) / $currentPayroll) * 100;
        } elseif ($currentPayroll > 0 && $priviesPayroll <= 0) {
            $percentage = (($currentPayroll - $priviesPayroll) / $currentPayroll) * 100;
        } elseif ($currentPayroll <= 0 && $priviesPayroll > 0) {
            $percentage = -100;
        } else {
            $percentage = 0;
        }

        if ($priviesStateTotal > 0) {
            $stateTotalPercentage = ($stateTotal / $priviesStateTotal) * 100;
        } else {
            $stateTotalPercentage = 0;
        }

        $stateTotalPercentages = $stateTotalPercentage > 100 ? 'More' : 'Less';

        if ($stateTotal > 0 && $priviesStateTotal > 0) {
            $percentageState = ($stateTotal - $priviesStateTotal) * 100 / $stateTotal;
        } elseif ($stateTotal <= 0 && $priviesStateTotal > 0) {
            $percentageState = -100;
        } elseif ($stateTotal > 0 && $priviesStateTotal <= 0) {
            $percentageState = ($stateTotal - $priviesStateTotal) * 100 / $stateTotal;
        } else {
            $percentageState = 0;
        }

        if ($priviesPayRollByPositionTotal > 0) {
            $positionTotalPercentage = ($payRollByPositionTotal / $priviesPayRollByPositionTotal) * 100;
        } else {
            $positionTotalPercentage = 0;
        }

        $positionTotalPercentages = $stateTotalPercentage > 100 ? 'More' : 'Less';

        if ($payRollByPositionTotal > 0 && $priviesPayRollByPositionTotal > 0) {
            $percentagePosition = (($payRollByPositionTotal - $priviesPayRollByPositionTotal) / $payRollByPositionTotal) * 100;
        } elseif ($payRollByPositionTotal > 0 && $priviesPayRollByPositionTotal <= 0) {
            $percentagePosition = (($payRollByPositionTotal - $priviesPayRollByPositionTotal) / $payRollByPositionTotal) * 100;
        } elseif ($payRollByPositionTotal <= 0 && $priviesPayRollByPositionTotal > 0) {
            $percentagePosition = -100;
        } else {
            $percentagePosition = 0;
        }

        //        $payroll['m1'] = round($m1,3);
        //        $payroll['m2'] = round($m2,3);
        $payroll['commission'] = round(($m1 + $m2), 3);
        $payroll['override'] = round($override, 3);
        $payroll['adjustment'] = round($adjustment, 3);
        $payroll['reimbursement'] = round($reimbursement, 3);
        $payroll['reconciliation'] = round($reconciliation, 3);
        $payroll['deduction'] = round($deduction, 3);
        $payroll['total_payroll_percentage'] = round($percentage);
        $payroll['total_payroll'] = round($payroll_sum, 3);
        $data['payroll'] = $payroll;
        $data['payroll_by_location'] = $stateName;
        $data['payroll_by_location_total_percentage'] = round($percentageState);
        $data['payroll_by_location_total'] = $stateTotal;
        $data['payroll_by_position'] = $payRollByPosition;
        $data['total_payroll_by_position_percentage'] = round($percentagePosition);
        $data['total_payroll_by_position'] = $payRollByPositionTotal;

        return response()->json([
            'ApiName' => 'Summary Payroll Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getOnetimePaymentHistory(Request $request, ApprovalsAndRequest $ApprovalsAndRequest): JsonResponse
    {

        $id = $request->id;
        $paymentHistory = [];
        $search = $request->search;
        // $status =$request->status;
        $filter = $request->filter;
        $ApprovalsAndRequest = $ApprovalsAndRequest->newQuery();

        if ($request->has('status')) {
            $filterDataStatusWise = $request->input('status');

            if ($filterDataStatusWise == 'all_status') {

            } elseif ($filterDataStatusWise == 'success') {
                $ApprovalsAndRequest->where('status', 'success');
            } elseif ($filterDataStatusWise == 'pending') {
                $ApprovalsAndRequest->where('status', 'pending');
            } elseif ($filterDataStatusWise == 'failed') {
                $ApprovalsAndRequest->where('status', 'failed');
            }
        }

        if ($request->has('search')) {
            $ApprovalsAndRequest->whereHas(
                'userData', function ($query) use ($request) {
                    $query->where('first_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$request->search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->search.'%']);
                });
        }

        if ($request->has('filter')) {
            $filterDataDateWise = $request->input('filter');
            if ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()->endOfWeek()));
                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_month') {

                $startOfMonth = Carbon::now()->subDays(0)->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));

                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));

                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d');

                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $ApprovalsAndRequest->whereBetween('cost_date', [$startDate, $endDate]);

            }
        }

        //  Start Anil kumar varma Code -------------------
        $paymentHistory = $ApprovalsAndRequest->with('userData')->where('adjustment_type_id', $id)
            ->select('id', 'user_id', 'cost_date', 'description', 'amount', 'txn_id', 'status')->orderBy('id', 'desc')->get();
        foreach ($paymentHistory as $key => $payment) {
            if (isset($payment->userData->image) && $payment->userData->image != null) {
                $paymentHistory[$key]['userData']['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$payment->userData->image);
            } else {
                $paymentHistory[$key]['userData']['image_s3'] = null;
            }
        }
        $paymentRequest = json_decode($paymentHistory);
        $paymentRequest = $this->paginates($paymentRequest, config('app.paginate', 15));

        // End Anil kumar varma Code -------------------

        return response()->json([
            'ApiName' => 'get OneTime Payment History',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $paymentHistory,

        ], 200);

    }

    public function exportPaymentHistory(Request $request): JsonResponse
    {
        $file_name = date('m-d-Y').'_one-time-payment-history.xlsx';
        Excel::store(new PaymentHistoryExport($request), 'exports/payroll/one-time/'.$file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $url = getStoragePath('exports/payroll/one-time/'.$file_name);

        // $url = getExportBaseUrl().'storage/exports/payroll/one-time/' . $file_name;
        return response()->json(['url' => $url]);
    }

    public function onetimePaymentTotal(Request $request): JsonResponse
    {

        $id = $request->id;
        // $data = ApprovalsAndRequest::where('adjustment_type_id', $id)->where('status','success')->sum('amount');
        $data = OneTimePayments::where('payment_status', '3')->sum('amount');

        return response()->json([
            'ApiName' => 'Onetime Payment Total',
            'status' => true,
            'message' => 'Onetime Payment Total Successfully',
            'total_amount' => (float) (round($data, 6)),
        ], 200);
    }

    public function payrollMarkAsPaid_080923(Request $request)
    {
        $data = [];
        $payrollId = $request->payrollId;
        $select_type = $request->select_type;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        // return $payrollId;
        if (count($payrollId) > 0 && $select_type == 'this_page') {
            $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
        } elseif (! empty($pay_period_from) && ! empty($pay_period_to) && $select_type == 'all_pages') {
            $data = Payroll::with('usersdata', 'positionDetail')->where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->get();
        }

        if (count($data) > 0) {
            $data->transform(function ($data) {

                $payroll = Payroll::where(['id' => $data->id])->first();
                $createdata = [
                    'payroll_id' => $payroll->id,
                    'user_id' => $payroll->user_id,
                    'position_id' => $payroll->position_id,
                    'commission' => $payroll->commission,
                    'override' => $payroll->override,
                    'reimbursement' => $payroll->reimbursement,
                    'clawback' => $payroll->clawback,
                    'deduction' => $payroll->deduction,
                    'adjustment' => $payroll->adjustment,
                    'reconciliation' => $payroll->reconciliation,
                    'net_pay' => $payroll->net_pay,
                    'pay_period_from' => $payroll->pay_period_from,
                    'pay_period_to' => $payroll->pay_period_to,
                    'status' => '3',
                    'pay_type' => 'Manualy',
                    'pay_frequency_date' => $payroll->created_at,
                ];

                $userCommissionStatus = [
                    'status' => '3',
                ];
                $userReconcilationCommission = UserReconciliationCommission::where('user_id', $data->user_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'payroll_id' => $payroll->id])->update(['status' => 'paid']);

                $UserCommission = UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update($userCommissionStatus);
                $UserOverrides = UserOverrides::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update($userCommissionStatus);
                $ClawbackSettlement = ClawbackSettlement::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update($userCommissionStatus);

                $insert = PayrollHistory::create($createdata);
                $payrollDelete = Payroll::where(['id' => $data->id])->delete();

            });
        }

        // return $data;
        return response()->json([
            'ApiName' => 'Mark_As_Paid',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    // undo-mark-as-paid
    public function payroll_mark_as_unpaid_080923(Request $request): JsonResponse
    {

        $payroll_id = $request->payrollId;
        $PayrollHistory_data = PayrollHistory::where('payroll_id', $payroll_id)->first();
        $message = 'Payroll not found!!';
        $Payroll_data = [];
        DB::beginTransaction();
        try {
            if ($PayrollHistory_data) {
                $userCommissionStatus = [
                    'status' => '1',
                ];
                $userReconcilationCommission = UserReconciliationCommission::where('user_id', $PayrollHistory_data->user_id)->where(['pay_period_from' => $PayrollHistory_data->pay_period_from, 'pay_period_to' => $PayrollHistory_data->pay_period_to, 'payroll_id' => $PayrollHistory_data->payroll_id])->update(['status' => 'pending']);

                $UserCommission = UserCommission::where(['user_id' => $PayrollHistory_data->user_id, 'pay_period_from' => $PayrollHistory_data->pay_period_from, 'pay_period_to' => $PayrollHistory_data->pay_period_to])->update($userCommissionStatus);
                $UserOverrides = UserOverrides::where(['user_id' => $PayrollHistory_data->user_id, 'pay_period_from' => $PayrollHistory_data->pay_period_from, 'pay_period_to' => $PayrollHistory_data->pay_period_to])->update($userCommissionStatus);
                $ClawbackSettlement = ClawbackSettlement::where(['user_id' => $PayrollHistory_data->user_id, 'pay_period_from' => $PayrollHistory_data->pay_period_from, 'pay_period_to' => $PayrollHistory_data->pay_period_to])->update($userCommissionStatus);

                $createdata = [
                    // 'id'=> 409,
                    'id' => $PayrollHistory_data->payroll_id,
                    'user_id' => $PayrollHistory_data->user_id,
                    'position_id' => $PayrollHistory_data->position_id,
                    'commission' => $PayrollHistory_data->commission,
                    'override' => $PayrollHistory_data->override,
                    'reimbursement' => $PayrollHistory_data->reimbursement,
                    'clawback' => $PayrollHistory_data->clawback,
                    'deduction' => $PayrollHistory_data->deduction,
                    'adjustment' => $PayrollHistory_data->adjustment,
                    'reconciliation' => $PayrollHistory_data->reconciliation,
                    'net_pay' => $PayrollHistory_data->net_pay,
                    'pay_period_from' => $PayrollHistory_data->pay_period_from,
                    'pay_period_to' => $PayrollHistory_data->pay_period_to,
                    'status' => '1',
                    'created_at' => $PayrollHistory_data->created_at,
                ];

                $Payroll_data = Payroll::create($createdata);
                if ($Payroll_data) {
                    $PayrollHistory_data = PayrollHistory::where('payroll_id', $payroll_id)->delete();
                    $message = 'Payroll undo mark-as-paid Successfully ';
                    DB::commit();
                }
            }
        } catch (\Exception $err) {
            // Handle any exceptions that occur within the transaction
            $message = $err->errorInfo[2];
            DB::rollBack();
        }

        return response()->json([
            'ApiName' => 'Mark As Unpaid',
            'status' => true,
            'message' => $message,
            'data' => $Payroll_data,
        ], 200);
    }

    public function payrollMarkAsPaid(Request $request): JsonResponse
    {

        $data = [];
        $payrollId = $request->payrollId;
        $select_type = $request->select_type;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        if (count($payrollId) > 0 && $select_type == 'this_page') {
            $data = Payroll::whereIn('id', $payrollId)->where(['is_mark_paid' => 0])->get();
        } elseif (! empty($pay_period_from) && ! empty($pay_period_to) && $select_type == 'all_pages') {
            $data = Payroll::where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => 0])->get();
        }

        DB::beginTransaction();
        try {
            if (count($data) > 0) {
                foreach ($data as $payroll) {

                    // $dataPayroll = Payroll::where(['user_id'=> $payroll->user_id, 'pay_period_from'=>$pay_period_from, 'pay_period_to'=>$pay_period_to])->get();
                    // if (count($dataPayroll) > 1) {
                    //     Payroll::where(['user_id'=> $payroll->user_id, 'is_mark_paid'=> 0, 'pay_period_from'=>$pay_period_from, 'pay_period_to'=>$pay_period_to])->delete();
                    // }

                    $userCommissionStatus = [
                        'is_mark_paid' => '1',
                    ];
                    if ($payroll->status != 3) {
                        $payroll_update = Payroll::where(['id' => $payroll->id])->update($userCommissionStatus);
                        // $CrmData = Crms::where('id',3)->where('status',1)->first();
                        // $CrmSetting = CrmSetting::where('crm_id',3)->first();
                        // $data1 = Payroll::with('usersdata')->where(['user_id'=>$payroll->user_id])->first();
                        // if(!empty($CrmData) && !empty($CrmSetting))
                        // {
                        //     $external_id = $data1['usersdata']['employee_id']."-".$data1->id;
                        //     $untracked =  $this->add_payable($data1,$external_id);  //update payable in everee
                        //     $enableEVE = 1;
                        // }
                        // else
                        // {
                        //     $enableEVE = 0;
                        // }

                    }
                    // if((isset($untracked['success']['status']) && $untracked['success']['status'] == true) ||  $enableEVE == 0 )
                    // {

                    $commision = UserCommission::where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '1']);
                    $overrides = UserOverrides::where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '1']);
                    $settlement = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '1']);
                    $approvalRequest = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'user_id' => $payroll->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '1']);
                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '1']);
                    $payrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '1']);

                    // }
                    if ($payroll->status == 2) {
                        $finalizeData = Payroll::where(['id' => $payroll->id])->update(['status' => 1, 'finalize_status' => 0]);

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

                    // $commision = UserCommission::where(['payroll_id'=>$payrollId,'user_id' => $payroll->user_id,'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to])->update(['is_mark_paid' => '1']);
                    // $overrides = UserOverrides::where(['payroll_id'=>$payrollId,'user_id' => $payroll->user_id,'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to])->update(['is_mark_paid' => '1']);
                    // $settlement = ClawbackSettlement::where(['payroll_id'=>$payrollId,'user_id' => $payroll->user_id,'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to])->update(['is_mark_paid' => '1']);
                    // $approvalRequest = ApprovalsAndRequest::where(['payroll_id'=>$payrollId,'status'=>'Accept','user_id' => $payroll->user_id,'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to])->update(['is_mark_paid' => '1']);
                    // $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id'=>$payroll->id,'user_id' => $payroll->user_id,'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to])->update(['is_mark_paid' => '1']);
                    // $payrollAdjustment = PayrollAdjustment::where(['payroll_id'=>$payroll->id,'user_id' => $payroll->user_id,'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to])->update(['is_mark_paid' => '1']);

                }
            }
            DB::commit();
            $message = 'Successfully.';

        } catch (\Exception $err) {
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

    // undo-mark-as-paid
    public function payroll_mark_as_unpaid(Request $request): JsonResponse
    {

        $payroll_id = $request->payrollId;
        $message = 'Payroll not paid!! ';
        $payroll_data = Payroll::where('id', $payroll_id)->first();
        $pay_period_from = $payroll_data->pay_period_from;
        $pay_period_to = $payroll_data->pay_period_to;
        DB::beginTransaction();
        try {
            if (! empty($payroll_data) && $payroll_data !== null && $payroll_data->status != 3 && $payroll_data->is_mark_paid == 1) {
                $userCommissionStatus = [
                    'is_mark_paid' => '0',
                ];

                $payroll_update = Payroll::where(['id' => $payroll_id])->update($userCommissionStatus);
                if ($payroll_update) {
                    $message = 'Payroll undo mark-as-paid Successfully ';
                }

                // $payrollData = Payroll::where(['user_id'=> $payroll_data->user_id, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->count();
                // if ($payrollData > 1) {
                //     Payroll::where(['id'=> $payroll_id, 'user_id'=> $payroll_data->user_id, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->delete();
                // }

                $commision = UserCommission::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '0']);
                $overrides = UserOverrides::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '0']);
                $settlement = ClawbackSettlement::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '0']);
                $approvalRequest = ApprovalsAndRequest::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '0']);
                $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '0']);
                $payrollAdjustmentDetails = PayrollAdjustmentDetail::where(['payroll_id' => $payroll_id, 'user_id' => $payroll_data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->update(['is_mark_paid' => '0']);
            }
            DB::commit();
        } catch (\Exception $err) {
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

    public function moveToReconciliations(Request $request)
    {
        $data = [];
        $payrollId = $request->payrollId;
        $period_from = $request->period_from;
        $period_to = $request->period_to;

        $Validator = Validator::make(
            $request->all(),
            [
                'period_from' => 'required',
                'period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        //

        if (count($payrollId) > 0) {
            $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
            $data->transform(function ($data) use ($period_from, $period_to) {

                $payroll = Payroll::where(['id' => $data->id])->first();

                $userReconciliationCommission = UserReconciliationCommission::where('user_id', $payroll->user_id)->where(['period_from' => $period_from, 'period_to' => $period_to])->first();

                if ($userReconciliationCommission) {
                    if ($userReconciliationCommission->status == 'pending') {
                        $reconciliationsAdjustement = ReconciliationsAdjustement::where('user_id', $payroll->user_id)->where(['start_date' => $period_from, 'end_date' => $period_to])->first();
                        if ($reconciliationsAdjustement) {
                            // echo"reconciliations";die;
                            $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                            $reconciliationsAdjustement->commission_due = $payroll->net_pay;
                            $reconciliationsAdjustement->start_date = $period_from;
                            $reconciliationsAdjustement->end_date = $period_to;
                            $reconciliationsAdjustement->save();
                        } else {
                            $create = [
                                'reconciliation_id' => $userReconciliationCommission->id,
                                'user_id' => $payroll->user_id,
                                'adjustment_type' => 'reconciliations',
                                'commission_due' => $payroll->net_pay,
                                'start_date' => $period_from,
                                'end_date' => $period_to,
                                'comment' => 'pending',
                            ];
                            $insert = ReconciliationsAdjustement::create($create);
                        }
                    } elseif ($userReconciliationCommission->status == 'payroll' || $userReconciliationCommission->status == 'finalize') {

                        $createdata = [
                            'user_id' => $payroll->user_id,
                            'amount' => 0,
                            'period_from' => $period_from,
                            'period_to' => $period_to,
                            'status' => 'pending',
                            'payroll_id' => $payroll->id,
                        ];
                        $insert = UserReconciliationCommission::create($createdata);

                        $create = [
                            'reconciliation_id' => $userReconciliationCommission->id,
                            'user_id' => $payroll->user_id,
                            'adjustment_type' => 'reconciliations',
                            'reconciliation_id' => $insert->id,
                            'commission_due' => $payroll->net_pay,
                            'start_date' => $period_from,
                            'end_date' => $period_to,
                            'comment' => 'pending',
                        ];
                        $insert = ReconciliationsAdjustement::create($create);
                    }
                } else {

                    $createdata = [
                        'user_id' => $payroll->user_id,
                        'amount' => 0,
                        'period_from' => $period_from,
                        'period_to' => $period_to,
                        'status' => 'pending',
                        'payroll_id' => $payroll->id,
                    ];
                    $insert = UserReconciliationCommission::create($createdata);

                    $create = [
                        'reconciliation_id' => $insert->id,
                        'user_id' => $payroll->user_id,
                        'adjustment_type' => 'reconciliations',
                        'reconciliation_id' => $insert->id,
                        'commission_due' => $payroll->net_pay,
                        'start_date' => $period_from,
                        'end_date' => $period_to,
                        'comment' => 'pending',
                    ];
                    $insert = ReconciliationsAdjustement::create($create);
                }

                $usercommission = UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                $overrides = UserOverrides::where(['user_id' => $data->user_id, 'overrides_settlement_type' => 'reconciliation', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                // $payrollDelete = Payroll::where(['id' => $data->id])->delete();
                $payrollUpdate = Payroll::where(['id' => $data->id])->update(['status' => 6]);

            });
        }

        // return $data;
        return response()->json([
            'ApiName' => 'Mark_As_Paid',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function moveRunPayrollToReconciliations(Request $request)
    {
        $data = [];
        $payrollId = $request->payrollId;
        $date = date('Y-m-d');

        if (count($payrollId) > 0) {
            $data = Payroll::with('usersdata', 'positionDetail')->whereIn('id', $payrollId)->get();
            $data->transform(function ($data) use ($date) {
                $payroll = Payroll::where(['id' => $data->id])->first();
                if (isset($data->id) && $data->id != null) {
                    Payroll::where(['id' => $data->id])->update(['status' => 6]);
                }

                $userCommissinData = UserCommission::where('payroll_id', $payroll->id)->where('user_id', $payroll->user_id)->where('pay_period_from', $payroll->pay_period_from)->where('pay_period_to', $payroll->pay_period_to)->first();
                // $userReconciliationCommission = UserReconciliationWithholding::where('closer_id', $data->user_id)->where('payroll_id',$data->id)->where('pid',$userCommissinData->pid)->orWhere('setter_id', $data->user_id)->where('payroll_id',$data->id)->where('pid',$userCommissinData->pid)->first();
                $userReconciliationCommission = UserReconciliationWithholding::where('closer_id', $data->user_id)->where('pid', $userCommissinData->pid)->orWhere('setter_id', $data->user_id)->where('pid', $userCommissinData->pid)->first();

                if ($userReconciliationCommission) {
                    if ($userReconciliationCommission->finalize_status == '0') {

                        $reconciliationsAdjustement = ReconciliationsAdjustement::where('user_id', $payroll->user_id)->where('pid', $userReconciliationCommission->pid)->first();

                        // $usercommission = UserCommission::where([
                        //     'payroll_id'=>$payroll->id,
                        //     'user_id' =>  $payroll->user_id,
                        //     ])
                        // ->first();

                        if ($reconciliationsAdjustement) {

                            // echo"reconciliations";die;
                            $reconciliationsAdjustement->adjustment_type = 'reconciliations';
                            $reconciliationsAdjustement->start_date = $date;
                            $reconciliationsAdjustement->end_date = $date;
                            $reconciliationsAdjustement->commission_due = $payroll->commission;
                            $reconciliationsAdjustement->overrides_due = $payroll->override;
                            // $reconciliationsAdjustement->clawback_due = $payroll->clawback;
                            $reconciliationsAdjustement->reimbursement = $payroll->reimbursement;
                            $reconciliationsAdjustement->deduction = $payroll->deduction;
                            $reconciliationsAdjustement->adjustment = $payroll->adjustment;
                            $reconciliationsAdjustement->reconciliation = $payroll->reconciliation;
                            $reconciliationsAdjustement->payroll_move_status = 'from_payroll';
                            $reconciliationsAdjustement->save();
                        } else {

                            $create = [
                                'pid' => $userReconciliationCommission->pid,
                                'user_id' => $payroll->user_id,
                                'start_date' => $date,
                                'end_date' => $date,
                                'adjustment_type' => 'reconciliations',
                                'commission_due' => $payroll->commission,
                                'overrides_due' => $payroll->override,
                                // 'clawback_due' => $payroll->clawback,
                                'reimbursement' => $payroll->reimbursement,
                                'deduction' => $payroll->deduction,
                                'adjustment' => $payroll->adjustment,
                                'reconciliation' => $payroll->reconciliation,
                                'payroll_move_status' => 'from_payroll',
                                'comment' => 'pending',
                            ];
                            $insert = ReconciliationsAdjustement::create($create);
                        }

                    } elseif ($userReconciliationCommission->finalize_status == '1') {

                        $user = User::where('id', $payroll->user_id)->first();
                        // return $payroll->user_id;

                        if ($user->position_id == 3) {
                            $createdata = [
                                'setter_id' => $payroll->user_id,
                                'payroll_id' => $payroll->id,
                                'pid' => isset($$userCommissinData->pid) ? $$userCommissinData->pid : null,
                                'withhold_amount' => 0,
                                'status' => 'unpaid',
                                'payroll_to_recon_status' => '1',

                            ];
                        } elseif ($user->position_id == 2) {
                            $createdata = [
                                'closer_id' => $payroll->user_id,
                                'payroll_id' => $payroll->id,
                                'pid' => isset($$userCommissinData->pid) ? $$userCommissinData->pid : null,
                                'withhold_amount' => 0,
                                'status' => 'unpaid',
                                'payroll_to_recon_status' => '1',

                            ];
                        }
                        $insert = UserReconciliationWithholding::create($createdata);

                        $create = [
                            'user_id' => $payroll->user_id,
                            'adjustment_type' => 'reconciliations',
                            'start_date' => $date,
                            'end_date' => $date,
                            'pid' => $insert->pid,
                            'reconciliation_id' => $insert->id,
                            'commission_due' => $payroll->net_pay,
                            'comment' => 'pending',
                            'payroll_move_status' => 'from_payroll',
                        ];
                        $insert = ReconciliationsAdjustement::create($create);
                    }
                } else {

                    $user = User::where('id', $payroll->user_id)->first();
                    // return $payroll->user_id;

                    if ($user->position_id == 3) {
                        $createdata = [
                            'setter_id' => $payroll->user_id,
                            'payroll_id' => $payroll->id,
                            'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                            'withhold_amount' => 0,
                            'status' => 'unpaid',
                            'payroll_to_recon_status' => '1',

                        ];
                    } elseif ($user->position_id == 2) {
                        $createdata = [
                            'closer_id' => $payroll->user_id,
                            'payroll_id' => $payroll->id,
                            'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                            'withhold_amount' => 0,
                            'status' => 'unpaid',
                            'payroll_to_recon_status' => '1',

                        ];
                    }

                    $insert = UserReconciliationWithholding::create($createdata);

                    $create = [
                        'reconciliation_id' => $insert->id,
                        'user_id' => $payroll->user_id,
                        'adjustment_type' => 'reconciliations',
                        'pid' => isset($userCommissinData->pid) ? $userCommissinData->pid : null,
                        'start_date' => $date,
                        'end_date' => $date,
                        'commission_due' => $payroll->commission,
                        'overrides_due' => $payroll->override,
                        // 'clawback_due' => $payroll->clawback,
                        'reimbursement' => $payroll->reimbursement,
                        'deduction' => $payroll->deduction,
                        'adjustment' => $payroll->adjustment,
                        'reconciliation' => $payroll->reconciliation,
                        'payroll_move_status' => 'from_payroll',
                        'comment' => 'pending',
                    ];
                    $insert = ReconciliationsAdjustement::create($create);
                }

                $usercommission = UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                $overrides = UserOverrides::where(['user_id' => $data->user_id, 'overrides_settlement_type' => 'reconciliation', 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->update(['status' => 6]); // 6 for Reconciliation Adjustments
                // $payrollDelete = Payroll::where(['id' => $data->id])->delete();
                if ($payroll->status == 2) {
                    $finalizeData = Payroll::where(['id' => $payroll->id])->update(['status' => 1, 'finalize_status' => 0]);
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
                $payrollUpdate = Payroll::where(['id' => $data->id])->update(['status' => 6]);

            });
        }

        // return $data;
        return response()->json([
            'ApiName' => 'Mark_As_Paid',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function payrollMoveToNextPayroll(Request $request)
    {
        $data = [];
        $payrollId = $request->payrollId;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        // return $payrollId;
        if (count($payrollId) > 0) {
            $moved_by = auth()->user()->id;
            $payrollId_arr = [];

            foreach ($payrollId as $key => $value) {
                // $data = ['status' => '4'];
                $paydata = Payroll::where('id', $value)->first();

                if ($paydata->status == 2) {
                    $finalizeData = Payroll::where(['id' => $paydata->id])->update(['status' => 1, 'finalize_status' => 0]);
                    if ($finalizeData == 1) {
                        $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                        if ($CrmData) {
                            $external_id = $paydata->everee_external_id;
                            $payabledata = $this->get_payable($external_id, $paydata->user_id); // check payable in everee
                            if (! empty($payabledata)) {
                                foreach ($payabledata as $payabledata) {
                                    if (isset($payabledata['id']) && ($payabledata['id'] == $external_id)) {
                                        $untracked = $this->delete_payable($external_id, $paydata->user_id); // delete payable in everee
                                        if ($untracked == null || (isset($untracked['errorCode']) && $untracked['errorCode'] == 404)) {
                                            Payroll::where(['id' => $paydata->id])->update(['everee_external_id' => null]);
                                        }
                                    }
                                }
                            }
                        }
                    }
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

                if ($payroll_shift_histrorie_data) {
                    $payrollId_arr[$value] = 'success';
                    $data = [
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                    ];
                    $payrollUpdate = Payroll::where(['id' => $value])->first();
                    if (! empty($payrollUpdate)) {
                        $payrollUpdate->pay_period_from = $start_date;
                        $payrollUpdate->pay_period_to = $end_date;
                        $payrollUpdate->is_next_payroll = $payrollUpdate->is_next_payroll + 1;
                        $payrollUpdate->everee_external_id = null;
                        $payrollUpdate->save();
                    }
                    $payroll_id = $value; // payroll_id.

                    $UserCommission = UserCommission::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                    foreach ($UserCommission as $userComm) {
                        $updateUserCommission = UserCommission::where('id', $userComm->id)->first();
                        $updateUserCommission->pay_period_from = $start_date;
                        $updateUserCommission->pay_period_to = $end_date;
                        $updateUserCommission->is_next_payroll = $updateUserCommission->is_next_payroll + 1;
                        $updateUserCommission->save();
                    }

                    $UserOverrides = UserOverrides::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                    foreach ($UserOverrides as $userOver) {
                        $updateUserOverrides = UserOverrides::where('id', $userOver->id)->first();
                        $updateUserOverrides->pay_period_from = $start_date;
                        $updateUserOverrides->pay_period_to = $end_date;
                        $updateUserOverrides->is_next_payroll = $updateUserOverrides->is_next_payroll + 1;
                        $updateUserOverrides->save();
                    }

                    $ApprovalsAndRequest = ApprovalsAndRequest::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                    foreach ($ApprovalsAndRequest as $appReq) {
                        $updateApprovalsAndRequest = ApprovalsAndRequest::where('id', $appReq->id)->first();
                        $updateApprovalsAndRequest->pay_period_from = $start_date;
                        $updateApprovalsAndRequest->pay_period_to = $end_date;
                        $updateApprovalsAndRequest->is_next_payroll = $updateApprovalsAndRequest->is_next_payroll + 1;
                        $updateApprovalsAndRequest->save();
                    }

                    // ClawbackSettlement move to next payroll
                    $ClawbackSettlement = ClawbackSettlement::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                    foreach ($ClawbackSettlement as $clawSettle) {
                        $updateClawbackSettlement = ClawbackSettlement::where('id', $clawSettle->id)->first();
                        $updateClawbackSettlement->pay_period_from = $start_date;
                        $updateClawbackSettlement->pay_period_to = $end_date;
                        $updateClawbackSettlement->is_next_payroll = $updateClawbackSettlement->is_next_payroll + 1;
                        $updateClawbackSettlement->save();
                    }

                    // PayrollAdjustment move to next payroll

                    $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                    if ($PayrollAdjustment) {
                        foreach ($PayrollAdjustment as $payrollAdjust) {
                            $updatePayrollAdjustment = PayrollAdjustment::where('id', $payrollAdjust->id)->first();
                            $updatePayrollAdjustment->pay_period_from = $start_date;
                            $updatePayrollAdjustment->pay_period_to = $end_date;
                            $updatePayrollAdjustment->is_next_payroll = $updatePayrollAdjustment->is_next_payroll + 1;
                            $updatePayrollAdjustment->save();
                        }
                    }

                    // PayrollAdjustmentDetails move to next payroll

                    $PayrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll_id, 'user_id' => $paydata->user_id, 'pay_period_from' => $paydata->pay_period_from, 'pay_period_to' => $paydata->pay_period_to])->get();
                    if ($PayrollAdjustmentDetail) {
                        foreach ($PayrollAdjustmentDetail as $payrollAdjustDetails) {
                            $updatePayrollAdjustmentDetail = PayrollAdjustmentDetail::where('id', $payrollAdjustDetails->id)->first();
                            $updatePayrollAdjustmentDetail->pay_period_from = $start_date;
                            $updatePayrollAdjustmentDetail->pay_period_to = $end_date;
                            $updatePayrollAdjustmentDetail->is_next_payroll = $updatePayrollAdjustmentDetail->is_next_payroll + 1;
                            $updatePayrollAdjustmentDetail->save();
                        }
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

    //  undo MoveToNextPayroll
    public function payroll_undo_next_payroll(Request $request): JsonResponse
    {
        $payroll_id = $request->payroll_id;
        $pay_period_from = $request->pay_period_from; // from
        $pay_period_to = $request->pay_period_to; // from
        $history_data = PayrollShiftHistorie::where(['payroll_id' => $payroll_id, 'is_undo_done' => 1])->orderBy('id', 'DESC')->first();

        // $Payroll_data = Payroll::with('PayrollShiftHistorie')->where(['id' => $payroll_id, 'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to,])->first();
        // $Payroll_data = Payroll::where(['id' => $payroll_id, 'pay_period_from'=> $pay_period_from,'pay_period_to'=>$pay_period_to,])->first();

        $message = 'Payroll not found!!';
        DB::beginTransaction();
        $data = [];
        $status = false;
        $status_code = 400;
        $moved_by = auth()->user()->id;

        try {
            $message = 'Nothing for undo!!';
            if (! empty($history_data)) {
                $old_pay_period_from = $history_data->pay_period_from; // to
                $old_pay_period_to = $history_data->pay_period_to; // to

                $new_pay_period_from = $history_data->new_pay_period_from; // from
                $new_pay_period_to = $history_data->new_pay_period_to; // from

                // $PayrollShiftHistorie = $Payroll_data->PayrollShiftHistorie;
                // if(count($PayrollShiftHistorie) > 0){
                //     $old_payroll_data = $PayrollShiftHistorie[count($PayrollShiftHistorie) - 1];
                //     $pay_period_from = $old_payroll_data->pay_period_from;
                //     $pay_period_to = $old_payroll_data->pay_period_to;

                $WeeklyPayFrequency = WeeklyPayFrequency::where(['pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to])->first();
                if ($WeeklyPayFrequency != null) {
                    $closed_status = $WeeklyPayFrequency->closed_status;
                } elseif ($MonthlyPayFrequency = MonthlyPayFrequency::where(['pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to])->first()) {
                    $closed_status = $MonthlyPayFrequency->closed_status;
                } elseif ($BiWeeklyPayFrequency = AdditionalPayFrequency::where(['pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to, 'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE])->first()) {
                    $closed_status = $BiWeeklyPayFrequency->closed_status;
                } elseif ($SemiMonthlyPayFrequency = AdditionalPayFrequency::where(['pay_period_from' => $old_pay_period_from, 'pay_period_to' => $old_pay_period_to, 'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE])->first()) {
                    $closed_status = $SemiMonthlyPayFrequency->closed_status;
                }

                if (! isset($closed_status)) {
                    return response()->json([
                        'ApiName' => 'Payroll undo next payroll',
                        'message' => 'Actual Pay Period Not Found!',
                        'status' => $status,
                    ], $status_code);
                }

                $message = "Previous payroll was closed, can't undo this payroll";
                if ($closed_status == 0) {

                    $payrollUpdate = Payroll::where([
                        'id' => $payroll_id, 'pay_period_from' => $new_pay_period_from, 'pay_period_to' => $new_pay_period_to])->first();
                    if (! empty($payrollUpdate)) {
                        $payrollUpdate->pay_period_from = $old_pay_period_from;
                        $payrollUpdate->pay_period_to = $old_pay_period_to;
                        $payrollUpdate->is_next_payroll = ($payrollUpdate->is_next_payroll > 0) ? $payrollUpdate->is_next_payroll - 1 : 0;
                        $payrollUpdate->save();
                    }

                    $UserCommission = UserCommission::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate->user_id, 'pay_period_from' => $new_pay_period_from, 'pay_period_to' => $new_pay_period_to])->get();
                    foreach ($UserCommission as $userComm) {
                        $updateUserCommission = UserCommission::where('id', $userComm->id)->first();
                        $updateUserCommission->pay_period_from = $old_pay_period_from;
                        $updateUserCommission->pay_period_to = $old_pay_period_to;
                        $updateUserCommission->is_next_payroll = ($updateUserCommission->is_next_payroll > 0) ? $updateUserCommission->is_next_payroll - 1 : 0;
                        $updateUserCommission->save();
                    }

                    $UserOverrides = UserOverrides::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate->user_id, 'pay_period_from' => $new_pay_period_from, 'pay_period_to' => $new_pay_period_to])->get();
                    foreach ($UserOverrides as $userOver) {
                        $updateUserOverrides = UserOverrides::where('id', $userOver->id)->first();
                        $updateUserOverrides->pay_period_from = $old_pay_period_from;
                        $updateUserOverrides->pay_period_to = $old_pay_period_to;
                        $updateUserOverrides->is_next_payroll = ($updateUserOverrides->is_next_payroll > 0) ? $updateUserOverrides->is_next_payroll - 1 : 0;
                        $updateUserOverrides->save();
                    }

                    $ApprovalsAndRequest = ApprovalsAndRequest::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate->user_id, 'pay_period_from' => $new_pay_period_from, 'pay_period_to' => $new_pay_period_to])->get();
                    foreach ($ApprovalsAndRequest as $appRequest) {
                        $updateApprovalsAndRequest = ApprovalsAndRequest::where('id', $appRequest->id)->first();
                        $updateApprovalsAndRequest->pay_period_from = $old_pay_period_from;
                        $updateApprovalsAndRequest->pay_period_to = $old_pay_period_to;
                        $updateApprovalsAndRequest->is_next_payroll = ($updateApprovalsAndRequest->is_next_payroll > 0) ? $updateApprovalsAndRequest->is_next_payroll - 1 : 0;
                        $updateApprovalsAndRequest->save();
                    }

                    $ClawbackSettlement = ClawbackSettlement::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate->user_id, 'pay_period_from' => $new_pay_period_from, 'pay_period_to' => $new_pay_period_to])->get();
                    foreach ($ClawbackSettlement as $clawSettle) {
                        $updateClawbackSettlement = ClawbackSettlement::where('id', $clawSettle->id)->first();
                        $updateClawbackSettlement->pay_period_from = $old_pay_period_from;
                        $updateClawbackSettlement->pay_period_to = $old_pay_period_to;
                        $updateClawbackSettlement->is_next_payroll = ($updateClawbackSettlement->is_next_payroll > 0) ? $updateClawbackSettlement->is_next_payroll - 1 : 0;
                        $updateClawbackSettlement->save();
                    }

                    // PayrollAdjustment undo to next payroll

                    $PayrollAdjustment = PayrollAdjustment::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate->user_id, 'pay_period_from' => $new_pay_period_from, 'pay_period_to' => $new_pay_period_to])->get();
                    foreach ($PayrollAdjustment as $payrollAdjust) {
                        $updatePayrollAdjustment = PayrollAdjustment::where('id', $payrollAdjust->id)->first();
                        $updatePayrollAdjustment->pay_period_from = $old_pay_period_from;
                        $updatePayrollAdjustment->pay_period_to = $old_pay_period_to;
                        $updatePayrollAdjustment->is_next_payroll = ($updatePayrollAdjustment->is_next_payroll > 0) ? $updatePayrollAdjustment->is_next_payroll - 1 : 0;
                        $updatePayrollAdjustment->save();
                    }

                    // PayrollAdjustmentDetails undo to next payroll

                    $PayrollAdjustmentDetail = PayrollAdjustmentDetail::where([
                        'payroll_id' => $payroll_id,
                        'user_id' => $payrollUpdate->user_id, 'pay_period_from' => $new_pay_period_from, 'pay_period_to' => $new_pay_period_to])->get();
                    foreach ($PayrollAdjustmentDetail as $payrollAdjustDetail) {
                        $updatePayrollAdjustmentDetail = PayrollAdjustmentDetail::where('id', $payrollAdjustDetail->id)->first();
                        $updatePayrollAdjustmentDetail->pay_period_from = $old_pay_period_from;
                        $updatePayrollAdjustmentDetail->pay_period_to = $old_pay_period_to;
                        $updatePayrollAdjustmentDetail->is_next_payroll = ($updatePayrollAdjustmentDetail->is_next_payroll > 0) ? $updatePayrollAdjustmentDetail->is_next_payroll - 1 : 0;
                        $updatePayrollAdjustmentDetail->save();
                    }

                    $PayrollShiftHistorie_undo = PayrollShiftHistorie::where(['id' => $history_data->id])->update(['is_undo_done' => 0]);

                    if ($payrollUpdate) { // && $PayrollShiftHistorie_delete
                        $status_code = 200;
                        $status = true;
                        $message = 'Payroll undo next payroll Successfully';
                        DB::commit();
                    } else {
                        $message = 'Somthing went wrong!!';
                        DB::rollback();
                    }
                }
                // }
            }

        } catch (\Exception $err) {
            // Handle any exceptions that occur within the transaction
            $message = $err->getMessage();
            DB::rollBack();
        }

        return response()->json([
            'ApiName' => 'Payroll undo next payroll',
            'message' => $message,
            // 'data' => $data,
            'status' => $status,
        ], $status_code);
    }

    public function payrollAdjustment(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        // return $request;
        $data = [];
        $payrollId = $request->payroll_id;

        $payroll = Payroll::where('id', $payrollId)->first();

        $commission_amount = isset($request->commission_amount) ? $request->commission_amount : 0;
        $overrides_amount = isset($request->overrides_amount) ? $request->overrides_amount : 0;
        $adjustments_amount = isset($request->adjustments_amount) ? $request->adjustments_amount : 0;
        $reimbursements_amount = isset($request->reimbursements_amount) ? $request->reimbursements_amount : 0;
        $deductions_amount = isset($request->deductions_amount) ? $request->deductions_amount : 0;
        $reconciliations_amount = isset($request->reconciliations_amount) ? $request->reconciliations_amount : 0;
        $clawbacks_amount = isset($request->clawbacks_amount) ? $request->clawbacks_amount : 0;

        $data = [
            'payroll_id' => $payroll->id,
            'user_id' => $payroll->user_id,
            'commission_amount' => isset($request->commission_amount) ? $request->commission_amount : 0,
            'overrides_amount' => isset($request->overrides_amount) ? $request->overrides_amount : 0,
            'adjustments_amount' => isset($request->adjustments_amount) ? $request->adjustments_amount : 0,
            'reimbursements_amount' => isset($request->reimbursements_amount) ? $request->reimbursements_amount : 0,
            'deductions_amount' => isset($request->deductions_amount) ? $request->deductions_amount : 0,
            'reconciliations_amount' => isset($request->reconciliations_amount) ? $request->reconciliations_amount : 0,
            'clawbacks_amount' => isset($request->clawbacks_amount) ? $request->clawbacks_amount : 0,
            'comment' => $request->comment,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ];

        $payrollAdjustment = PayrollAdjustment::where('payroll_id', $payrollId)->first();

        if (empty($payrollAdjustment->payroll_id)) {
            $payrollAdjustment = PayrollAdjustment::Create($data);

        } else {
            $data = [
                'commission_amount' => ($payrollAdjustment->commission_amount + ($commission_amount)),
                'overrides_amount' => ($payrollAdjustment->overrides_amount + ($overrides_amount)),
                'adjustments_amount' => ($payrollAdjustment->adjustments_amount + ($adjustments_amount)),
                'reimbursements_amount' => ($payrollAdjustment->reimbursements_amount + ($reimbursements_amount)),
                'deductions_amount' => ($payrollAdjustment->deductions_amount + ($deductions_amount)),
                'reconciliations_amount' => ($payrollAdjustment->reconciliations_amount + ($reconciliations_amount)),
                'clawbacks_amount' => ($payrollAdjustment->clawbacks_amount + ($clawbacks_amount)),
                'comment' => $request->comment,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
            ];
            $payrollAdjustment = PayrollAdjustment::where('id', $payrollAdjustment->id)->update($data);
        }

        return response()->json([
            'ApiName' => 'Payroll Adjustment',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function reconciliationsAdjustment(Request $request)
    {
        //
        $Validator = Validator::make(
            $request->all(),
            [
                'reconciliation_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        // return $request;
        $data = [];
        $userId = $request->user_id;
        $reconciliationId = $request->reconciliation_id;

        $commission_withheld = isset($request->commission_withheld) ? $request->commission_withheld : 0;
        $overrides_due = isset($request->overrides_due) ? $request->overrides_due : 0;
        $clawback_due = isset($request->clawback_due) ? $request->clawback_due : 0;

        $data = [
            'user_id' => $userId,
            'reconciliation_id' => $reconciliationId,
            'commission_due' => $request->commission_withheld,
            'overrides_due' => $request->overrides_due,
            'clawback_due' => $request->clawback,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'comment' => $request->comment,
        ];

        $reconciliationsAdjustment = ReconciliationsAdjustement::where('reconciliation_id', $reconciliationId)->first();

        if (empty($reconciliationsAdjustment)) {
            $reconciliationsAdjustment = ReconciliationsAdjustement::Create($data);

        } else {
            $data = [
                'commission_due' => ($reconciliationsAdjustment->commission_due + ($commission_withheld)),
                'overrides_due' => ($reconciliationsAdjustment->overrides_due + ($overrides_due)),
                'clawback_due' => ($reconciliationsAdjustment->clawback_due + ($clawback_due)),
                'comment' => $request->comment,
            ];
            $reconciliationsAdjustment = ReconciliationsAdjustement::where('reconciliation_id', $reconciliationId)->update($data);
        }

        $datas = ReconciliationsAdjustement::where('reconciliation_id', $reconciliationId)->first();

        return response()->json([
            'ApiName' => 'Reconciliations Adjustment',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $datas,
        ], 200);

    }

    public function getReconciliationsAdjustment(): JsonResponse
    {
        $reconciliationsAdjustment = ReconciliationsAdjustement::with('reconciliationDetail')->get();

        return response()->json([
            'ApiName' => 'Reconciliations Adjustment List',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $reconciliationsAdjustment,
        ], 200);

    }

    public function addAlertToPayroll(Request $request): JsonResponse
    {
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;
        $payrollAlerts = PayrollAlerts::where('id', $request->id)->first();
        $userdata = User::with('positionDetail')->where('id', $payrollAlerts->user_id)->first();
        $paydata = Payroll::where('user_id', $payrollAlerts->user_id)->where(['pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to])->first();
        // dd($paydata);
        if ($paydata) {

            $updateData = [
                'commission' => $paydata->commission + $payrollAlerts->commission,
            ];
            $payroll = Payroll::where('id', $paydata->id)->update($updateData);
            PayrollAlerts::where('id', $request->id)->update(['status' => 2]);
        } else {
            Payroll::create([
                'user_id' => $userdata->id,
                'position_id' => $userdata->position_id,
                'commission' => $payrollAlerts->commission,
                'net_pay' => $payrollAlerts->commission,
                'pay_period_from' => $request->pay_period_from,
                'pay_period_to' => $request->pay_period_to,
                'override' => 0,
                'reimbursement' => 0,
                'clawback' => 0,
                'deduction' => 0,
                'adjustment' => 0,
                'reconciliation' => 0,
                'status' => 1,
            ]);
            PayrollAlerts::where('id', $request->id)->update(['status' => 2]);
        }

        return response()->json([
            'ApiName' => 'addAlertToPayroll',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function payrollReconOverridebyEmployeeId(Request $request, $id)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date',

            ], 400);
        }
        $data = UserOverrides::where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
        $data->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
            return $query->whereBetween('m2_date', [$startDate, $endDate]);
        });
        $data = $data->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->get();
        $data->transform(function ($data) use ($id) {
            $payout = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'payroll')->first();
            // $overPaid = ReconOverrideHistory::where('pid',$data->pid)->where('user_id',$data->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->where('status','finalize')->where('type',$data->type)->first();
            $overPaid = ReconOverrideHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'finalize')->where('type', $data->type)->first();
            if (isset($payout->payout) && $payout->payout != 0) {
                $pay = $payout->payout;
            } else {
                $pay = 0;
            }

            if (isset($data->overrides_amount) && $data->overrides_amount != 0) {
                $totalPaid = ($data->amount * $pay) / 100;

            } else {
                $totalPaid = 0;
            }

            if (isset($overPaid) && $overPaid != '') {

                return [
                    'id' => $data->id,
                    'user_id' => $data->user_id,
                    'pid' => $data->pid,
                    'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                    'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                    'image' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                    'override_over_image' => isset($data->userpayrolloverride->image) ? $data->userpayrolloverride->image : null,
                    'override_over_first_name' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                    'override_over_last_name' => isset($data->userpayrolloverride->last_name) ? $data->userpayrolloverride->last_name : null,
                    'type' => $data->type,
                    'rep_redline' => $data->userpayrolloverride->redline,
                    'kw' => $data->kw,
                    'overrides_type' => $data->overrides_type,
                    'overrides_amount' => $overPaid->override_amount,
                    // 'ss'=>'salf',
                    'amount' => $overPaid->total_amount,
                    'paid' => 0,
                    'in_recon' => ($overPaid->total_amount),
                    'adjustment_amount' => $data->adjustment_amount,
                ];
            } else {
                // $overPaidDone = ReconOverrideHistory::where('pid',$data->pid)->where('user_id',$data->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->where('type',$data->type)->where('status','payroll');
                $overPaidDone = ReconOverrideHistory::where('pid', $data->pid)->where('user_id', $data->user_id)->where('type', $data->type)->where('status', 'payroll');
                $overSendAmount = $overPaidDone->sum('paid');
                $overPaidDone = $overPaidDone->first();
                $totalOverPay = isset($overPaidDone->paid) ? $overPaidDone->paid : 0;
                $reconhist = $data->amount - $totalOverPay;
                if (isset($overPaidDone) && $overPaidDone != '') {
                    return [
                        'id' => $data->id,
                        'user_id' => $data->user_id,
                        'pid' => $data->pid,
                        'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                        'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                        'image' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_image' => isset($data->userpayrolloverride->image) ? $data->userpayrolloverride->image : null,
                        'override_over_first_name' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_last_name' => isset($data->userpayrolloverride->last_name) ? $data->userpayrolloverride->last_name : null,
                        'type' => $data->type,
                        'rep_redline' => $data->userpayrolloverride->redline,
                        'kw' => $data->kw,
                        'overrides_type' => $data->overrides_type,
                        'overrides_amount' => $data->overrides_amount,
                        'amount' => $data->total_amount - $totalOverPay,
                        // 'ss'=>$totalOverPay,
                        'paid' => isset($data->amount) ? $data->amount - $totalOverPay : 0 .'('.$pay.'%)',
                        'in_recon' => ($reconhist),
                        'adjustment_amount' => $data->adjustment_amount,
                    ];
                } else {
                    return [
                        'id' => $data->id,
                        'user_id' => $data->user_id,
                        'pid' => $data->pid,
                        'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                        'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                        'image' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_image' => isset($data->userpayrolloverride->image) ? $data->userpayrolloverride->image : null,
                        'override_over_first_name' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                        'override_over_last_name' => isset($data->userpayrolloverride->last_name) ? $data->userpayrolloverride->last_name : null,
                        'type' => $data->type,
                        'rep_redline' => $data->userpayrolloverride->redline,
                        'kw' => $data->kw,
                        'overrides_type' => $data->overrides_type,
                        'overrides_amount' => $data->overrides_amount,
                        'amount' => $data->amount,
                        // 'ss'=>$totalOverPay,
                        'paid' => isset($data->amount) ? $data->amount : 0 .'('.$pay.'%)',
                        'in_recon' => ($reconhist),
                        'adjustment_amount' => $data->adjustment_amount,
                    ];
                }
            }

        });

        $commissionTotal = UserOverrides::where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
        $commissionTotal->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
            return $query->whereBetween('m2_date', [$startDate, $endDate]);
        });
        $commissionTotal = $commissionTotal->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->get();
        $subtotal = 0;
        if (isset($commissionTotal) && $commissionTotal != '[]') {
            foreach ($commissionTotal as $datas) {

                $payout = ReconciliationFinalizeHistory::where('pid', $datas->pid)->where('user_id', $datas->user_id)->where('status', 'payroll')->first();
                // $payout = ReconciliationFinalizeHistory::where('pid',$data->pid)->where('user_id',$data->sale_user_id)->first();
                $overPaid = ReconOverrideHistory::where('pid', $datas->pid)->where('user_id', $datas->user_id)->where('status', 'finalize')->where('type', $datas->type)->first();
                // if(isset($payout->payout) && $payout->payout!=0)
                // {
                //     $pay = $payout->payout;
                // }else{
                //     $pay = 0;
                // }

                // if(isset($datas->amount) && $datas->amount!=0)
                // {
                //     $totalPaid = $datas->amount*$pay/100;

                // }else{
                //     $totalPaid = 0;
                // }
                //  $subtotal += $datas->amount-$totalPaid;

                if (isset($overPaid) && $overPaid != '') {

                    $subtotal += $datas->amount;
                } else {

                    // $overPaidDone = ReconOverrideHistory::where('pid',$data->pid)->where('user_id',$data->user_id)->where('start_date',$startDate)->where('end_date',$endDate)->where('type',$data->type)->where('status','payroll');
                    $overPaidDone = ReconOverrideHistory::where('pid', $datas->pid)->where('user_id', $datas->user_id)->where('type', $datas->type)->where('status', 'payroll');
                    $overSendAmount = $overPaidDone->sum('paid');
                    $overPaidDone = $overPaidDone->first();
                    $totalOverPay = isset($overPaidDone->paid) ? $overPaidDone->paid : 0;

                    $reconhist = $datas->amount - $totalOverPay;
                    if (isset($overPaidDone) && $overPaidDone != '') {
                        $subtotal += $datas->amount - $totalOverPay;
                    } else {
                        $subtotal += $datas->amount;
                    }

                }
            }

            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
                'sub_total' => $subtotal,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data,
                'sub_total' => $subtotal,
            ], 200);
        }

    }

    public function reportReconOverridebyEmployeeId(Request $request, $id)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($startDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date',

            ], 400);
        }
        $data = UserOverrides::where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
        $data->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
            return $query->whereBetween('m2_date', [$startDate, $endDate]);
        });
        $data = $data->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->get();

        // $data = UserOverrides::with('salesDetail','userpayrolloverride')->where(['user_id'=>$id, 'overrides_settlement_type'=>'reconciliation'])->get();
        $data->transform(function ($data) use ($startDate, $endDate) {
            // $payout = ReconciliationFinalizeHistory::where('pid',$data->pid)->where('user_id',$data->user_id)->first();

            $payout = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $data->user_id)->where('status', 'payroll')->first();
            $overPaid = ReconOverrideHistory::where('pid', $data->pid)->where('user_id', $data->user_id)->where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'payroll')->where('type', $data->type);

            $totalPaidPay = $overPaid->sum('paid');
            if (isset($payout->payout) && $payout->payout != 0) {
                $pay = $payout->payout;
            } else {
                $pay = 100;
            }

            if (isset($data->amount) && $data->amount != 0) {
                $totalPaid = $data->amount * $pay / 100;

            } else {
                $totalPaid = 0;
            }

            return [

                'id' => $data->id,
                'user_id' => $data->user_id,
                'pid' => $data->pid,
                'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
                'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
                'image' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                'override_over_image' => isset($data->userpayrolloverride->image) ? $data->userpayrolloverride->image : null,
                'override_over_first_name' => isset($data->userpayrolloverride->first_name) ? $data->userpayrolloverride->first_name : null,
                'override_over_last_name' => isset($data->userpayrolloverride->last_name) ? $data->userpayrolloverride->last_name : null,
                'type' => $data->type,
                'rep_redline' => $data->userpayrolloverride->redline,
                'kw' => $data->kw,
                'overrides_type' => $data->overrides_type,
                'amount' => $data->amount,
                'overrides_amount' => $data->overrides_amount,
                'paid' => isset($totalPaidPay) ? $totalPaidPay : 0,
                'in_recon' => $data->amount - $totalPaidPay,
                'adjustment_amount' => $data->adjustment_amount,
            ];
        });

        $commissionTotal = UserOverrides::where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1]);
        $commissionTotal->whereHas('salesDetail', function ($query) use ($startDate, $endDate) {
            return $query->whereBetween('m2_date', [$startDate, $endDate]);
        });
        $commissionTotal = $commissionTotal->with('salesDetail', 'userpayrolloverride')->where(['user_id' => $id, 'overrides_settlement_type' => 'reconciliation', 'status' => 1])->get();

        // $commissionTotal = UserOverrides::with('salesDetail','userpayrolloverride')->where(['user_id'=>$id, 'overrides_settlement_type'=>'reconciliation'])->get();
        $subtotal = 0;
        if (isset($commissionTotal) && $commissionTotal != '[]') {
            foreach ($commissionTotal as $datas) {
                // $payout = ReconciliationFinalizeHistory::where('pid',$datas->pid)->where('user_id',$datas->user_id)->first();
                // if(isset($payout->payout) && $payout->payout!=0)
                // {
                //     $pay = $payout->payout;
                // }else{
                //     $pay = 100;
                // }
                $overPaid = ReconOverrideHistory::where('pid', $datas->pid)->where('user_id', $datas->user_id)->where('status', 'payroll')->where('type', $datas->type);
                $totalPaidPay = $overPaid->sum('paid');
                if (isset($datas->amount) && $datas->amount != 0) {
                    $totalPaid = $datas->amount;

                } else {
                    $totalPaid = 0;
                }
                $subtotal += $totalPaidPay;
            }

            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
                'sub_total' => $subtotal,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation OverRides By employee Id',
                'status' => true,
                'message' => 'Successfully',
                'data' => $data,
                'sub_total' => $subtotal,
            ], 200);
        }

    }

    public function payrollReconCommissionbyEmployeeId(Request $request, $id)
    {
        // $pid = ReconciliationFinalizeHistory::where('user_id',$id)->pluck('pid');
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $pid = UserReconciliationWithholding::where('closer_id', $id)->where('finalize_status', 0)->where('status', 'unpaid')->orWhere('setter_id', $id)->where('status', 'unpaid')->where('finalize_status', 0)->pluck('pid');
        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
        if ($startDate == '' && $endDate == '') {
            $data = UserReconciliationWithholding::with('salesDetail')
                ->where('closer_id', $id)
                ->where('finalize_status', 0)
                ->orWhere('setter_id', $id)
                ->where('finalize_status', 0)
                ->get();
        } else {
            $data = UserReconciliationWithholding::where('finalize_status', 0)
                ->whereIn('pid', $salePid)
                ->where('closer_id', $id)
                ->orWhere('setter_id', $id)
                ->where('finalize_status', 0)
                ->whereIn('pid', $salePid)
                ->get();
        }

        $data->transform(function ($data) use ($id) {
            $userId = ($data->closer_id != null) ? $data->closer_id : $data->setter_id;
            $redline = User::where('id', $userId)->select('redline', 'redline_type')->first();
            $payout = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'payroll')->first();
            $finalizePer = ReconciliationFinalizeHistory::where('pid', $data->pid)->where('user_id', $id)->where('status', 'finalize')->first();
            if (isset($payout->payout) && $payout->payout != '') {
                $payOut = $payout->payout;
            } else {
                $payOut = 0;
            }

            if ($data->withhold_amount > 0) {
                $totalPaid = $data->withhold_amount * $payOut / 100;
            } else {
                $totalPaid = 0;
            }
            $location = Locations::with('State')->where('general_code', $data->salesDetail->customer_state)->first();
            if ($location) {
                $state_code = $location->state->state_code;
            } else {
                $state_code = null;
            }

            $paidAmount = ReconciliationFinalizeHistory::where('user_id', $id)->where('pid', $data->pid)->where('status', 'payroll')->sum('paid_commission');
            $paidAdjustmant = ReconciliationsAdjustement::where('user_id', $id)->where('pid', $data->pid)->where('payroll_status', '!=', 'payroll')->sum('adjustment');
            if ($paidAmount) {
                $recon = $data->withhold_amount - $paidAmount;
            } else {
                $recon = $data->withhold_amount;
            }
            if (isset($paidAdjustmant) && $paidAdjustmant != '') {
                $paidAdjustmant = $paidAdjustmant;
            } else {
                $paidAdjustmant = 0;
            }

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
                'amount' => $data->withhold_amount,
                'paid' => $paidAmount,
                'in_recon' => $recon,
                'finalize_payout' => 0,
                'adjustment_amount' => isset($data->adjustment_amount) ? $data->adjustment_amount - $paidAdjustmant : 0,
            ];
        });

        $commissionTotal = UserReconciliationWithholding::with('salesDetail')
            ->where('closer_id', $id)
            ->whereIn('pid', $salePid)
            ->where('finalize_status', 0)
            ->orWhere('setter_id', $id)
            ->whereIn('pid', $salePid)
            ->where('finalize_status', 0)
            ->get();
        $subtotal = 0;

        foreach ($commissionTotal as $datas) {
            $userId = ($datas->closer_id != null) ? $datas->closer_id : $datas->setter_id;
            $redline = User::where('id', $userId)->select('redline', 'redline_type')->first();
            $payout = ReconciliationFinalizeHistory::where('pid', $datas->pid)->where('user_id', $userId)->first();
            if (isset($payout->payout) && $payout->payout != '') {
                $payOut = $payout->payout;
            } else {
                $payOut = 100;
            }
            if ($datas->withhold_amount > 0) {
                $totalPaid = $datas->withhold_amount * $payOut / 100;
            } else {
                $totalPaid = 0;
            }

            $paidAmount = ReconciliationFinalizeHistory::where('user_id', $id)->where('pid', $datas->pid)->where('status', 'payroll')->sum('paid_commission');
            if ($paidAmount) {
                $recon = $datas->withhold_amount - $paidAmount;
            } else {
                $recon = $datas->withhold_amount;
            }

            $subtotal += $recon;
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
            'subtotal' => $subtotal,
        ], 200);

    }

    // public function reportReconCommissionbyEmployeeId(Request $request, $id)
    // {
    //     //$pid = ReconciliationFinalizeHistory::where('user_id',$id)->pluck('pid');
    //     $startDate = $request->start_date;
    //     $endDate = $request->end_date;
    //     $pid = UserReconciliationWithholding::where('closer_id',$id)->where('finalize_status',0)->where('status','unpaid')->orWhere('setter_id',$id)->where('status','unpaid')->where('finalize_status',0)->pluck('pid');
    //     $salePid = SalesMaster::whereIn('pid',$pid)->whereBetween('m2_date',[$startDate,$endDate])->pluck('pid')->toArray();
    //    if($startDate=="" && $endDate=="")
    //    {
    //         $data = UserReconciliationWithholding::with('salesDetail')
    //         ->where('closer_id',$id)
    //         ->where('finalize_status',0)
    //         ->orWhere('setter_id',$id)
    //         ->where('finalize_status',0)
    //         ->get();
    //    }else{

    //      $data = UserReconciliationWithholding::where('finalize_status',0)
    //         ->whereIn('pid',$salePid)
    //         ->where('closer_id',$id)
    //         ->orWhere('setter_id',$id)
    //         ->where('finalize_status',0)
    //         ->whereIn('pid',$salePid)
    //         ->get();
    //    }

    //      $data->transform(function ($data) use($id) {
    //          $userId = ($data->closer_id != NULL)?$data->closer_id:$data->setter_id;
    //         $redline = User::where('id',$userId)->select('redline','redline_type')->first();
    //        // $payout = ReconciliationFinalizeHistory::where('pid',$data->pid)->where('user_id',$userId)->where('status','payroll')->first();
    //         $payout = ReconciliationFinalizeHistory::where('user_id',$userId)->where('status','payroll')->first();
    //         //$finalizePer = ReconciliationFinalizeHistory::where('pid',$data->pid)->where('user_id',$userId)->where('status','finalize')->first();
    //         if(isset($payout->payout) && $payout->payout!="")
    //         {
    //            $payOut = $payout->payout;
    //         }else{
    //             $payOut = 0;
    //         }

    //         if($data->withhold_amount>0)
    //         {
    //             $totalPaid = $data->withhold_amount*$payOut/100;
    //         }else{
    //             $totalPaid = 0;
    //         }

    //         $location = Locations::with('State')->where('general_code', $data->salesDetail->customer_state)->first();
    //         if($location){
    //             $state_code = $location->state->state_code;
    //         }else{
    //             $state_code = null;
    //         }

    //         $paidAmount = ReconciliationFinalizeHistory::where('user_id',$id)->where('pid', $data->pid)->where('status','payroll')->sum('paid_commission');
    //         if($paidAmount)
    //         {
    //             $recon = $data->withhold_amount-$paidAmount;
    //         }else{
    //             $recon = $data->withhold_amount;
    //         }

    //         return [
    //             'id' => $data->id,
    //             'user_id' => $userId,
    //             'pid' => $data->pid,
    //             'state_id' => $state_code,
    //             'customer_name' => isset($data->salesDetail->customer_name) ? $data->salesDetail->customer_name : null,
    //             'customer_state' => isset($data->salesDetail->customer_state) ? $data->salesDetail->customer_state : null,
    //             'rep_redline' => isset($redline->redline)?$redline->redline:null,
    //             'kw' => isset($redline->redline_type)?$redline->redline_type:null,
    //             'net_epc' => $data->salesDetail->net_epc,
    //             'epc' => $data->salesDetail->epc,
    //             'adders' => $data->salesDetail->adders,
    //             'type' => 'Withheld',//$data->type,
    //             'amount' => $data->withhold_amount,
    //             'paid' => $paidAmount,
    //             'in_recon' => $recon,
    //             'finalize_payout' => 0,
    //             'adjustment_amount' => isset($data->adjustment_amount)?$data->adjustment_amount:0,
    //         ];
    //     });

    //     $pid = UserReconciliationWithholding::where('closer_id',$id)->where('finalize_status',0)->where('status','unpaid')->orWhere('setter_id',$id)->where('status','unpaid')->where('finalize_status',0)->pluck('pid');
    //     $salePid = SalesMaster::whereIn('pid',$pid)->whereBetween('m2_date',[$startDate,$endDate])->pluck('pid')->toArray();
    //    if($startDate=="" && $endDate=="")
    //    {
    //         $commissionTotal = UserReconciliationWithholding::with('salesDetail')
    //         ->where('closer_id',$id)
    //         ->where('finalize_status',0)
    //         ->orWhere('setter_id',$id)
    //         ->where('finalize_status',0)
    //         ->get();
    //    }else{

    //      $commissionTotal = UserReconciliationWithholding::where('finalize_status',0)
    //         ->whereIn('pid',$salePid)
    //         ->where('closer_id',$id)
    //         ->orWhere('setter_id',$id)
    //         ->where('finalize_status',0)
    //         ->whereIn('pid',$salePid)
    //         ->get();
    //    }
    //     $subtotal=0;

    //     foreach($commissionTotal as $datas)
    //     {
    //         $userId = ($datas->closer_id != NULL)?$datas->closer_id:$datas->setter_id;
    //         $redline = User::where('id',$userId)->select('redline','redline_type')->first();
    //         $payout = ReconciliationFinalizeHistory::where('pid',$datas->pid)->where('user_id',$userId)->where('status','payroll')->first();
    //         if(isset($payout->payout) && $payout->payout!="")
    //         {
    //             $payOut = $payout->payout;
    //         }else{
    //             $payOut = 0;
    //         }
    //         if($datas->withhold_amount>0)
    //         {
    //             $totalPaid = $datas->withhold_amount*$payOut/100;
    //         }else{
    //             $totalPaid = 0;
    //         }
    //         $paidAmount = ReconciliationFinalizeHistory::where('user_id',$id)->where('pid', $datas->pid)->where('status','payroll')->sum('paid_commission');
    //         if($paidAmount)
    //         {
    //             $recon = $paidAmount;
    //         }else{
    //             $recon = $datas->withhold_amount;
    //         }
    //         $subtotal += $paidAmount;
    //     }
    //     return response()->json([
    //         'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'data' => $data,
    //         'subtotal'=>$subtotal
    //     ], 200);

    // }
    public function reportReconCommissionbyEmployeeId(Request $request, $id)
    {
        // $pid = ReconciliationFinalizeHistory::where('user_id',$id)->pluck('pid');
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $sentId = $request->sent_id;
        $pid = UserReconciliationWithholding::where('closer_id', $id)->where('status', 'unpaid')->orWhere('setter_id', $id)->where('status', 'unpaid')->pluck('pid');
        $salePid = SalesMaster::whereIn('pid', $pid)->whereBetween('m2_date', [$startDate, $endDate])->pluck('pid');
        //    if($startDate=="" && $endDate=="")
        //    {
        //         $data = UserReconciliationWithholding::with('salesDetail')
        //         ->where('closer_id',$id)
        //         ->orWhere('setter_id',$id)
        //         ->get();
        //    }else{
        //    $data = UserReconciliationWithholding::whereIn('pid',$salePid)
        //         ->where('closer_id',$id)
        //         ->orWhere('setter_id',$id)
        //         ->whereIn('pid',$salePid)
        //         ->get();
        //    }
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

    public function payrollReconClawbackbyEmployeeId($id): JsonResponse
    {
        $response = [];
        $data = ReconciliationsAdjustement::with('user')->where('user_id', $id)->get();
        if (count($data) > 0) {

            foreach ($data as $key => $val) {

                if ($val->commission_due > 0 || $val->commission_due < 0) {
                    $response[] = [
                        'id' => $val->user_id,
                        'pid' => '',
                        'state' => '',
                        'rep_redline' => '',
                        'kw' => '',
                        'net_epc' => '',
                        'first_name' => $val->user->first_name,
                        'last_name' => $val->user->last_name,
                        'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                        'amount' => $val->commission_due,
                        'type' => 'Commission Due',
                        'description' => isset($val->comment) ? $val->comment : null,
                    ];

                }
                if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                    $response[] = [
                        'id' => $val->user_id,
                        'pid' => '',
                        'state' => '',
                        'rep_redline' => '',
                        'kw' => '',
                        'net_epc' => '',
                        'first_name' => $val->user->first_name,
                        'last_name' => $val->user->last_name,
                        'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                        'amount' => $val->overrides_due,
                        'type' => 'Overrides Due',
                        'description' => isset($val->comment) ? $val->comment : null,

                    ];

                }
                if ($val->clawback_due > 0 || $val->clawback_due < 0) {
                    $response[] = [
                        'id' => $val->user_id,
                        'pid' => '',
                        'state' => '',
                        'rep_redline' => '',
                        'kw' => '',
                        'net_epc' => '',
                        'first_name' => $val->user->first_name,
                        'last_name' => $val->user->last_name,
                        'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                        'amount' => $val->clawback_due,
                        'type' => 'clawback Due',
                        'description' => isset($val->comment) ? $val->comment : null,

                    ];

                }

            }
        }

        unset($data);
        $data = ClawbackSettlement::with('user', 'salesDetail')->where(['user_id' => $id, 'status' => 1])->get();

        foreach ($data as $d) {
            $clawback_amount = isset($d->clawback_amount) ? $d->clawback_amount : 0;
            $totalAdjustments = $clawback_amount;

            if ($d->user->redline_amount_type == 'Fixed') {
                $rep_redline = $d->user->redline;
            } else {
                $sale_state_redline = SalesMaster::join('states', 'states.state_code', '=', 'sale_masters.customer_state')
                    ->join('locations', function ($join) use ($d) {
                        $join->on('states.id', '=', 'locations.id')
                            ->where('locations.general_code', '=', $d->salesDetail->customer_state);
                    })->value('redline_standard');
                $user_redline = $d->user->redline;
                $user_office_redline = Locations::where(['id' => $d->user->office_id, 'general_code' => $d->salesDetail->customer_state])->value('redline_standard');
                $rep_redline = $sale_state_redline + ($user_redline - $user_office_redline);

            }
            $location = Locations::with('State')->where('general_code', '=', $d->salesDetail->customer_state)->first();
            if ($location) {
                $state_code = $location->state->state_code;
            } else {
                $state_code = null;
            }

            $response[] = [
                'id' => $d->user_id,
                'pid' => $d->salesDetail->pid,
                'first_name' => $d->user->first_name,
                'last_name' => $d->user->last_name,
                'state_id' => $state_code,
                'state' => $d->salesDetail->customer_state,
                'rep_redline' => $rep_redline,
                'kw' => $d->salesDetail->kw,
                'net_epc' => $d->salesDetail->net_epc,
                'date' => isset($d->created_at) ? date('Y-m-d', strtotime($d->created_at)) : null,
                'amount' => isset($totalAdjustments) ? $totalAdjustments : null,
                'type' => 'Clawback',
                // 'description'=> 'clawback amount = '. $clawback_amount
            ];
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,

        ], 200);

    }

    public function payrollReconClawbackListbyEmployeeId(Request $request, $id): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Payroll Reconciliation Clawback By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',

            ], 400);
        }
        $pid = $request->pid;
        $data = ClawbackSettlement::where(['user_id' => $id, 'status' => 1])->where('clawback_type', 'reconciliation');
        $data = $data->with('user', 'salesDetail')->get();
        $response = [];

        foreach ($data as $d) {
            $clawback_amount = isset($d->clawback_amount) ? $d->clawback_amount : 0;
            $totalClawback = $clawback_amount;
            $paiClawback = ReconciliationFinalizeHistory::where('user_id', $id)->where('pid', $d->pid)->where('status', 'payroll')->first();
            if (isset($paiClawback) && $paiClawback != '') {
                $paidClawback = $paiClawback->clawback;
            } else {
                $paidClawback = 0;
            }
            if ($d->user->redline_amount_type == 'Fixed') {
                $rep_redline = $d->user->redline;
            } else {
                $sale_state_redline = SalesMaster::join('states', 'states.state_code', '=', 'sale_masters.customer_state')
                    ->join('locations', function ($join) use ($d) {
                        $join->on('states.id', '=', 'locations.id')
                            ->where('locations.general_code', '=', $d->salesDetail->customer_state);
                    })->value('redline_standard');
                $user_redline = $d->user->redline;
                $user_office_redline = Locations::where(['id' => $d->user->office_id, 'general_code' => $d->salesDetail->customer_state])->value('redline_standard');
                $rep_redline = $sale_state_redline + ($user_redline - $user_office_redline);

            }
            $location = Locations::with('State')->where('general_code', '=', $d->salesDetail->customer_state)->first();
            if ($location) {
                $state_code = $location->state->id;
            } else {
                $state_code = null;
            }

            $response[] = [
                'id' => $d->user_id,
                'pid' => $d->salesDetail->pid,
                'customer_name' => $d->salesDetail->customer_name,
                'first_name' => $d->user->first_name,
                'last_name' => $d->user->last_name,
                'state_id' => $state_code,
                'state' => $d->salesDetail->customer_state,
                'rep_redline' => $rep_redline,
                'kw' => $d->salesDetail->kw,
                'net_epc' => $d->salesDetail->net_epc,
                'adders' => $d->salesDetail->adders,
                'date' => isset($d->created_at) ? date('Y-m-d', strtotime($d->created_at)) : null,
                'amount' => isset($totalClawback) ? $totalClawback - $paidClawback : null,
                'type' => 'Clawback',
                // 'description'=> 'clawback amount = '. $clawback_amount
            ];
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,

        ], 200);

    }

    public function reportReconClawbackListbyEmployeeId(Request $request, $id): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Reports Reconciliation Clawback By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',

            ], 400);
        }
        $pid = $request->pid;
        $response = [];
        $data = ClawbackSettlement::with('user', 'salesDetail')->where(['user_id' => $id, 'status' => 1])->where('clawback_type', 'reconciliation')->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)->get();
        foreach ($data as $d) {
            $clawback_amount = isset($d->clawback_amount) ? $d->clawback_amount : 0;
            $totalClawback = $clawback_amount;

            if ($d->user->redline_amount_type == 'Fixed') {
                $rep_redline = $d->user->redline;
            } else {
                $sale_state_redline = SalesMaster::join('states', 'states.state_code', '=', 'sale_masters.customer_state')
                    ->join('locations', function ($join) use ($d) {
                        $join->on('states.id', '=', 'locations.id')
                            ->where('locations.general_code', '=', $d->salesDetail->customer_state);
                    })->value('redline_standard');
                $user_redline = $d->user->redline;
                $user_office_redline = Locations::where(['id' => $d->user->office_id, 'general_code' => $d->salesDetail->customer_state])->value('redline_standard');
                $rep_redline = $sale_state_redline + ($user_redline - $user_office_redline);

            }
            $location = Locations::with('State')->where('general_code', '=', $d->salesDetail->customer_state)->first();
            if ($location) {
                $state_code = $location->state->id;
            } else {
                $state_code = null;
            }

            $response[] = [
                'id' => $d->user_id,
                'pid' => $d->salesDetail->pid,
                'customer_name' => $d->salesDetail->customer_name,
                'first_name' => $d->user->first_name,
                'last_name' => $d->user->last_name,
                'state_id' => $state_code,
                'state' => $d->salesDetail->customer_state,
                'rep_redline' => $rep_redline,
                'kw' => $d->salesDetail->kw,
                'net_epc' => $d->salesDetail->net_epc,
                'adders' => $d->salesDetail->adders,
                'date' => isset($d->created_at) ? date('Y-m-d', strtotime($d->created_at)) : null,
                'amount' => isset($totalClawback) ? $totalClawback : null,
                'type' => 'Clawback',
                // 'description'=> 'clawback amount = '. $clawback_amount
            ];
        }

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Clawback By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,

        ], 200);

    }

    // public function payrollReconAdjustementbyEmployeeId($id)
    // {
    //     $response = [];
    //    $data = ReconciliationsAdjustement::with('user')->where('user_id',$id)->get();
    //     if (count( $data) > 0) {

    //         foreach ( $data as $key => $val) {

    //             if($val->commission_due>0 || $val->commission_due<0 ){
    //                $response[] = [
    //                     'id' => $val->user_id,
    //                     'pid' => '',
    //                     'state'=>'',
    //                     'rep_redline'=>'',
    //                     'kw' =>'',
    //                     'net_epc' => '',
    //                     'first_name' => $val->user->first_name,
    //                     'last_name' => $val->user->last_name,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' => $val->commission_due,
    //                     'type' => 'Commission Due',
    //                     'description' => isset($val->comment) ? $val->comment : null,
    //                 ];

    //             }
    //             if($val->overrides_due>0 ||$val->overrides_due<0){
    //                $response[] = [
    //                     'id' => $val->user_id,
    //                     'pid' => '',
    //                     'state'=>'',
    //                     'rep_redline'=>'',
    //                     'kw' =>'',
    //                     'net_epc' => '',
    //                     'first_name' => $val->user->first_name,
    //                     'last_name' => $val->user->last_name,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' =>  $val->overrides_due,
    //                     'type' => 'Overrides Due',
    //                     'description' => isset($val->comment) ? $val->comment : null,

    //                 ];

    //             }
    //             if($val->clawback_due>0 || $val->clawback_due<0){
    //                $response[] = [
    //                     'id' => $val->user_id,
    //                     'pid' => '',
    //                     'state'=>'',
    //                     'rep_redline'=>'',
    //                     'kw' =>'',
    //                     'net_epc' => '',
    //                     'first_name' => $val->user->first_name,
    //                     'last_name' => $val->user->last_name,
    //                     'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                     'amount' => $val->clawback_due,
    //                     'type' => 'clawback Due',
    //                     'description' => isset($val->comment) ? $val->comment : null,

    //                 ];

    //             }

    //         }
    //     }
    //     unset($data);
    //     return response()->json([
    //         'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'data' => $response,

    //     ], 200);

    // }

    public function payrollReconAdjustementbyEmployeeId(Request $request, $id): JsonResponse
    {
        $response = [];
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',

            ], 400);
        }

        $data = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('adjustment_type', 'reconciliations')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('payroll_status', '!=', 'payroll')->get();

        if (count($data) > 0) {

            foreach ($data as $key => $val) {

                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;
                $sales = SalesMaster::where('pid', $pid)->first();
                $userInfo = Auth()->user();
                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due/Moved from payroll',
                            'input_type' => 'commission',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Overrides Due/Moved from payroll',
                            'input_type' => 'overrides',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reimbursement,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Reimbursement Due/Moved from payroll',
                            'input_type' => 'reimbursement',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->deduction > 0 || $val->deduction < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->deduction,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Deduction Due/Moved from payroll',
                            'input_type' => 'deduction',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Adjustment Due/Moved from payroll',
                            'input_type' => 'adjustment',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Reconciliation',
                            'input_type' => 'reconciliation/Moved from payroll',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                } else {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due',
                            'input_type' => 'commission',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Overrides Due',
                            'input_type' => 'overrides',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }

                    if ($val->clawback_due > 0 || $val->clawback_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->clawback_due,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'clawback Due',
                            'input_type' => 'clawback',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }

                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reimbursement,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Reimbursement Due',
                            'input_type' => 'reimbursement',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->deduction > 0 || $val->deduction < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->deduction,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Deduction Due',
                            'input_type' => 'deduction',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Adjustment Due',
                            'input_type' => 'adjustment',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Reconciliation',
                            'input_type' => 'reconciliation',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                }

            }
        }
        unset($data);

        return response()->json([
            'ApiName' => 'PayRoll Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,

        ], 200);

    }

    public function reportReconAdjustementbyEmployeeId(Request $request, $id): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        if ($endDate == '' && $endDate == '') {
            return response()->json([
                'ApiName' => 'Report Reconciliation Commision By employee Id',
                'status' => false,
                'message' => 'Please select start date and end date.',
            ], 400);
        }

        $response = [];
        $data = ReconciliationsAdjustement::with('user', 'reconciliationInfo')->where('user_id', $id)->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)->where('adjustment_type', 'reconciliations')->get();
        if (count($data) > 0) {

            foreach ($data as $key => $val) {

                $pid = isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null;

                $sales = SalesMaster::where('pid', $pid)->first();

                $userInfo = Auth()->user();

                if ($val->payroll_move_status == 'from_payroll') {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            // 'rep_redline'=>'',
                            // 'kw' =>'',
                            // 'net_epc' => '',
                            // 'first_name' => isset($val->user->first_name)?$val->user->first_name:null,
                            // 'last_name' => isset($val->user->last_name)?$val->user->last_name:null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due/Moved from payroll',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Overrides Due/Moved from payroll',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->reimbursement > 0 || $val->reimbursement < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reimbursement,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Reimbursement Due/Moved from payroll',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->deduction > 0 || $val->deduction < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->deduction,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Deduction Due/Moved from payroll',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                    if ($val->adjustment > 0 || $val->adjustment < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->adjustment,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Adjustment Due/Moved from payroll',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];

                    }
                    if ($val->reconciliation > 0 || $val->reconciliation < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => '-',
                            'customer' => '-',
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->reconciliation,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Overrides Due',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                } else {
                    if ($val->commission_due > 0 || $val->commission_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => isset($val->commission_due) ? $val->commission_due : 0,
                            'adjustment_by' => isset($userInfo->first_name) ? $userInfo->first_name.' '.$userInfo->last_name : null,
                            'user_image' => isset($userInfo->image) ? $userInfo->image : null,
                            'type' => 'Commission Due',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                    if ($val->overrides_due > 0 || $val->overrides_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->overrides_due,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'Overrides Due',
                            'description' => isset($val->comment) ? $val->comment : null,
                        ];
                    }
                    if ($val->clawback_due > 0 || $val->clawback_due < 0) {
                        $response[] = [
                            'id' => $val->id,
                            'user_id' => $val->user_id,
                            'pid' => isset($val->reconciliationInfo->pid) ? $val->reconciliationInfo->pid : null,
                            'customer' => isset($sales->customer_name) ? $sales->customer_name : null,
                            'date' => isset($val->created_at) ? date('Y-m-d', strtotime($val->created_at)) : null,
                            'amount' => $val->clawback_due,
                            'adjustment_by' => isset($val->user->first_name) ? $val->user->first_name.' '.$val->user->last_name : null,
                            'user_image' => isset($val->user->image) ? $val->user->image : null,
                            'type' => 'clawback Due',
                            'description' => isset($val->comment) ? $val->comment : null,

                        ];

                    }
                }
            }
        }
        unset($data);

        return response()->json([
            'ApiName' => 'Report Reconciliation Commision By employee Id',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,

        ], 200);

    }

    public function AdvancepaymentRequest(Request $request)
    {
        $data = [];
        $paymentRequest = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('adjustment_type_id', '4')->where('status', 'Approved')->orderBy('id', 'asc');
        // return $paymentRequest;
        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $paymentRequest->whereHas('user', function ($query) use ($search) {
                return $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
            });
        }
        $paymentRequest = $paymentRequest->PAGINATE(10);
        if (count($paymentRequest) > 0) {
            // foreach ($paymentRequest as $key => $value) {
            //     $data[] = [
            //         'id' => $value->id,
            //         'first_name' => isset($value->user->first_name) ? $value->user->first_name : null,
            //         'last_name' => isset($value->user->last_name) ? $value->user->last_name : null,
            //         'image' => isset($value->user->image) ? $value->user->image : null,
            //         'approved_by' => isset($value->approvedBy) ? $value->approvedBy->first_name : null,
            //         'request_on' => isset($value->created_at) ? $value->created_at->format('Y-m-d') : null,
            //         'amount' => isset($value->amount) ? $value->amount : null,
            //         'type' => isset($value->adjustment) ? $value->adjustment->name : null,
            //         'description' => isset($value->description) ? $value->description : null,
            //     ];
            // }

            $paymentRequest->transform(function ($value) {
                return [
                    'id' => $value->id,
                    'first_name' => isset($value->user->first_name) ? $value->user->first_name : null,
                    'last_name' => isset($value->user->last_name) ? $value->user->last_name : null,
                    'image' => isset($value->user->image) ? $value->user->image : null,
                    'approved_by' => isset($value->approvedBy) ? $value->approvedBy->first_name : null,
                    'request_on' => isset($value->created_at) ? $value->created_at->format('Y-m-d') : null,
                    'amount' => isset($value->amount) ? $value->amount : null,
                    'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                    'description' => isset($value->description) ? $value->description : null,
                ];
            });
        }

        return response()->json([
            'ApiName' => 'payment_request',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
            'data' => $paymentRequest,
        ], 200);

    }

    public function deduction_for_all_deduction_enable_users($start_date, $end_date, $pay_frequency)
    {

        // get users who's deductions status is ON.
        $deduction_enable_users = User::select('id', 'sub_position_id', 'stop_payroll')->with('positionDeductionLimit', 'positionpayfrequencies', 'userDeduction', 'positionCommissionDeduction')
            ->whereHas('positionDeductionLimit', function ($q) {
                $q->where('positions_duduction_limits.status', 1);
            })
            ->whereHas('positionpayfrequencies', function ($qry) use ($pay_frequency) {
                $qry->where('position_pay_frequencies.frequency_type_id', '=', $pay_frequency);
            })
            ->where('is_super_admin', '!=', '1')
            ->where(DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d")'), '<=', $start_date)
            ->where('stop_payroll', 0)
            ->get();

        // Log::info($deduction_enable_users);

        if ($start_date && $end_date) {
            $paydata = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
            $payroll_data = [];
            if (count($paydata) > 0) {
                foreach ($paydata as $p) {
                    $payroll_data[$p->user_id] = $p;
                }
            }

            $payhistorydata = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->select('user_id')->get();
            $payroll_history_data = [];
            if (count($payhistorydata) > 0) {
                foreach ($payhistorydata as $p) {
                    $payroll_history_data[$p->user_id] = $p;
                }
            }

            $commission_deduction_amt = 0;
            $commission_deduction_percent_amt = 0;
            $commission_breakup_arr = [];
            $commission_deduction_amt_total = 0;
            $dediction_amount = 0;
            $position_deduction_limit = 0;
            $subtotal = 0;
            $prev_outstanding = 0;
            // $user_deduction = 0;
            foreach ($deduction_enable_users as $key => $data) {
                $user_deduction = [];
                $user_deduction = (count($data->userDeduction) > 0) ? $data->userDeduction : $data->positionCommissionDeduction;
                if (count($user_deduction) <= 0) {
                    continue;
                }
                if (isset($user_deduction[0]->ammount_par_paycheck)) {
                    $d1 = $user_deduction[0]->ammount_par_paycheck;
                }
                if (isset($user_deduction[1]->ammount_par_paycheck)) {
                    $d2 = $user_deduction[1]->ammount_par_paycheck;
                }

                $limit_type = isset($data->positionDeductionLimit->limit_type) ? $data->positionDeductionLimit->limit_type : '';
                $limit_amount = isset($data->positionDeductionLimit->limit_ammount) ? $data->positionDeductionLimit->limit_ammount : '0';

                if (array_key_exists($data->id, $payroll_data)) {
                    $payrolldata = $payroll_data[$data->id];

                    $subtotal = (($payrolldata->commission + $payrolldata->overrides) <= 0) ? 0 : round(($payrolldata->commission + $payrolldata->overrides) * ($limit_amount / 100), 2, PHP_ROUND_HALF_EVEN);

                    // getting previous payroll id by current payroll_id
                    $previous_id = Payroll::where('user_id', $data->id)->where('id', '<', $payrolldata->id)->orderBy('id', 'DESC')->pluck('id')->first();

                    $amount_total = 0;
                    $deduction_total = 0;
                    foreach ($user_deduction as $key => $d) {
                        $prev_outstanding = 0;
                        $prev = PayrollDeductions::where('user_id', $data->id)->where('cost_center_id', $d->cost_center_id)->where('payroll_id', $previous_id)->select('outstanding', 'cost_center_id')->first();
                        $prev_outstanding = (isset($prev->outstanding)) ? round($prev->outstanding, 2) : 0;
                        // Log::info('$prev_outstanding if '.$prev_outstanding);
                        $amount_total += $d->ammount_par_paycheck + (($prev_outstanding > 0) ? $prev_outstanding : 0);
                        $d->ammount_par_paycheck += (($prev_outstanding > 0) ? $prev_outstanding : 0);
                    }
                    $subtotal = ($amount_total < $subtotal) ? $amount_total : $subtotal;
                    $deduction_total = 0;
                    foreach ($user_deduction as $key => $d) {
                        $total = ($amount_total > 0) ? round($subtotal * ($d->ammount_par_paycheck / $amount_total), 2, PHP_ROUND_HALF_EVEN) : 0;
                        $outstanding = round($d->ammount_par_paycheck - $total, 2, PHP_ROUND_HALF_EVEN);

                        PayrollDeductions::updateOrCreate([
                            'payroll_id' => $payrolldata->id,
                            'user_id' => $data->id,
                            'cost_center_id' => $d->cost_center_id,
                        ], [
                            'amount' => round($d->ammount_par_paycheck, 2, PHP_ROUND_HALF_EVEN),
                            'limit' => round($limit_amount, 2, PHP_ROUND_HALF_EVEN),
                            'total' => round($total, 2, PHP_ROUND_HALF_EVEN),
                            'outstanding' => round($outstanding, 2, PHP_ROUND_HALF_EVEN),
                            'subtotal' => round($subtotal, 2, PHP_ROUND_HALF_EVEN),
                        ]);

                        $deduction_total += $total;
                    }

                    Payroll::where('id', $payrolldata->id)->update(['deduction' => $deduction_total, 'net_pay' => $payrolldata->net_pay - $deduction_total]);

                } elseif (! array_key_exists($data->id, $payroll_history_data)) {
                    $subtotal = 0; // ((0 + 0)<=0)?0:round((0 + 0)*($limit_amount/100),2);

                    // getting previous payroll id by current payroll_id
                    $previous_id = Payroll::where('user_id', $data->id)->orderBy('id', 'DESC')->pluck('id')->first();

                    $amount_total = 0;

                    $original = [];
                    foreach ($user_deduction as $key => $d) {
                        $original[$key] = $d;
                        $prev_outstanding = 0;
                        $prev = PayrollDeductions::where('user_id', $data->id)->where('cost_center_id', $d->cost_center_id)->where('payroll_id', $previous_id)->select('outstanding', 'user_id', 'payroll_id')->first();
                        $prev_outstanding = (isset($prev->outstanding)) ? round($prev->outstanding, 2) : 0;
                        $amount_total += $d->ammount_par_paycheck + (($prev_outstanding > 0) ? $prev_outstanding : 0);
                        $d->ammount_par_paycheck += (($prev_outstanding > 0) ? $prev_outstanding : 0);
                    }
                    $subtotal = ($amount_total < $subtotal) ? $amount_total : $subtotal;

                    DB::beginTransaction();
                    $payroll_id = Payroll::insertGetId([
                        'user_id' => $data->id,
                        'position_id' => $data->sub_position_id,
                        'commission' => 0,
                        'override' => 0,
                        'reimbursement' => 0,
                        'clawback' => 0,
                        'deduction' => 0,
                        'adjustment' => 0,
                        'reconciliation' => 0,
                        'net_pay' => 0,
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                        'status' => 1,
                    ]);
                    $deduction_total = 0;
                    foreach ($user_deduction as $key => $d) {
                        $total = ($amount_total > 0) ? round($subtotal * ($d->ammount_par_paycheck / $amount_total), 2, PHP_ROUND_HALF_EVEN) : 0;
                        $outstanding = round($d->ammount_par_paycheck - $total, 2, PHP_ROUND_HALF_EVEN);

                        PayrollDeductions::updateOrCreate([
                            'payroll_id' => $payroll_id,
                            'user_id' => $data->id,
                            'cost_center_id' => $d->cost_center_id,
                        ], [
                            'amount' => round($d->ammount_par_paycheck, 2, PHP_ROUND_HALF_EVEN),
                            'limit' => round($limit_amount, 2, PHP_ROUND_HALF_EVEN),
                            'total' => round($total, 2, PHP_ROUND_HALF_EVEN),
                            'outstanding' => round($outstanding, 2, PHP_ROUND_HALF_EVEN),
                            'subtotal' => round($subtotal, 2, PHP_ROUND_HALF_EVEN),
                        ]);
                        $deduction_total += $total;
                    }
                    Payroll::where('id', $payroll_id)->update([
                        'deduction' => $deduction_total,
                        'net_pay' => -$deduction_total,
                    ]);

                    if ($deduction_total > 0) {
                        DB::commit();
                    } else {
                        DB::rollBack();
                    }
                    if (isset($user_deduction[0]->ammount_par_paycheck)) {
                        $user_deduction[0]->ammount_par_paycheck = $d1;
                    }
                    if (isset($user_deduction[1]->ammount_par_paycheck)) {
                        $user_deduction[1]->ammount_par_paycheck = $d2;
                    }
                }
            }
        }
    }
}
