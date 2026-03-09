<?php

namespace App\Jobs;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Http\Controllers\API\ManagerReport\ManagerReportsControllerV1;
use App\Models\AdvancePaymentSetting;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\FrequencyType;
use App\Models\Notification;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollDeductions;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertime;
use App\Models\PayrollOvertimeLock;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationFinalizeHistoryLock;
use App\Models\ReconClawbackHistory;
use App\Models\ReconClawbackHistoryLock;
use App\Models\ReconCommissionHistory;
use App\Models\ReconCommissionHistoryLock;
use App\Models\ReconDeductionHistory;
use App\Models\ReconDeductionHistoryLock;
use App\Models\ReconOverrideHistory;
use App\Models\ReconOverrideHistoryLock;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\TempPayrollFinalizeExecuteDetail;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationCommissionLock;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class executePayrollJob implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, EvereeTrait, InteractsWithQueue, PayFrequencyTrait, PushNotificationTrait, Queueable, SerializesModels;

    public $data;

    public $startDate;

    public $endDate;

    public $newStartDate;

    public $newEndDate;

    public $payFrequency;

    public $final;

    public $timeout = 1800; // 30 minutes

    public $tries = 3;

    public function __construct($data, $startDate, $endDate, $newFromDate, $newToDate, $payFrequency, $final)
    {
        // $this->onQueue('execute');
        $this->onQueue('payroll');
        $this->data = $data;
        $this->final = $final;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->newStartDate = $newFromDate;
        $this->newEndDate = $newToDate;
        $this->payFrequency = $payFrequency;
    }

    public function handle(): void
    {
        $data = $this->data;
        $startDate = $this->startDate;
        $endDate = $this->endDate;
        $payFrequency = $this->payFrequency;
        $crmData = Crms::where('id', 3)->where('status', 1)->first();
        $workerType = isset($data?->usersdata?->worker_type) ? $data?->usersdata?->worker_type : null;
        $workerTypeId = isset($data?->usersdata?->everee_workerId) ? $data?->usersdata?->everee_workerId : null;
        if ($crmData && $data->is_mark_paid != 1 && $data->is_onetime_payment != 1 && $data->net_pay > 0) {
            if ($workerTypeId) {
                $enableEVE = 1;
                $untracked = $this->payable_request($data);
                if (! isset($untracked['success']['paymentId']) && strtolower($workerType) != 'w2') {
                    $enableEVE = 2;
                }
            } else {
                $enableEVE = 2;
            }
            $payType = 'Bank';
        } else {
            $enableEVE = 0;
            $payType = 'Manualy';
        }

        $check = PayrollHistory::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->where(['user_id' => $data->user_id, 'is_onetime_payment' => 0])->count();
        if (! $check) {
            PayrollHistory::create([
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
                'pay_type' => $payType,
                'pay_frequency_date' => $data->created_at,
                'everee_external_id' => $data->everee_external_id,
                'everee_payment_status' => $enableEVE,
                'everee_paymentId' => isset($untracked['success']['everee_payment_id']) ? $untracked['success']['everee_payment_id'] : null,
                'everee_payment_requestId' => isset($untracked['success']['paymentId']) ? $untracked['success']['paymentId'] : null,
                'everee_json_response' => isset($untracked) ? json_encode($untracked) : null,
                'worker_type' => $data->worker_type,
            ]);

            if (isset($workerType) && strtolower($workerType) == 'w2') {
                $successStatus = 'SUCCESS';
            } else {
                $successStatus = isset($untracked['success']['everee_payment_id']) ? 'SUCCESS' : 'ERROR';
            }
            TempPayrollFinalizeExecuteDetail::updateOrCreate(['payroll_id' => $data->id], [
                'user_id' => $data->user_id,
                'payroll_id' => $data->id,
                'net_amount' => $data->net_pay,
                'pay_period_from' => $data->pay_period_from,
                'pay_period_to' => $data->pay_period_to,
                'message' => isset($untracked['fail']['everee_response']['errorMessage']) ? $untracked['fail']['everee_response']['errorMessage'] : null,
                'status' => $successStatus,
                'type' => 'Execute',
            ]);
        }
        UserCommission::where(['user_id' => $data->user_id, 'payroll_id' => 0, 'status' => 3, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->update(['payroll_id' => $data->id]);
        $modelsToUpdate = [
            UserCommission::class => ['status' => '3'],
            UserOverrides::class => ['status' => '3'],
            ClawbackSettlement::class => ['status' => '3'],
            PayrollAdjustment::class => ['status' => '3'],
            PayrollAdjustmentDetail::class => ['status' => '3'],
            PayrollDeductions::class => ['status' => '3'],
            PayrollHourlySalary::class => ['status' => '3'],
            PayrollOvertime::class => ['status' => '3'],
        ];

        foreach ($modelsToUpdate as $model => $condition) {
            $model::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })->where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'is_onetime_payment' => 0])->update($condition);
        }

        $modelsToUpdate1 = [
            UserReconciliationCommission::class => ['status' => 'paid'],
        ];

        foreach ($modelsToUpdate1 as $model1 => $condition1) {
            $model1::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })->where(['user_id' => $data->user_id, 'payroll_id' => $data->id])->update($condition1);
        }

        ApprovalsAndRequest::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->where(['status' => 'Accept', 'payroll_id' => $data->id, 'is_onetime_payment' => 0])->update(['status' => 'Paid']);

        $requests = ApprovalsAndRequest::where(['status' => 'Paid', 'payroll_id' => $data->id, 'is_onetime_payment' => 0])->get();
        $requests->each(function ($request) {
            $childReqAmount = ApprovalsAndRequest::where(['parent_id' => $request->parent_id, 'status' => 'Paid', 'is_onetime_payment' => 0])->sum('amount');
            $parentReqAmount = ApprovalsAndRequest::where(['id' => $request->parent_id, 'status' => 'Accept', 'is_onetime_payment' => 0])->sum('amount');
            if ($childReqAmount == $parentReqAmount) {
                ApprovalsAndRequest::where('id', $request->parent_id)->update(['status' => 'Paid']);
            }
        });

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

        $modelToLocks = [
            PayrollAdjustment::class => PayrollAdjustmentLock::class,
            PayrollAdjustmentDetail::class => PayrollAdjustmentDetailLock::class,
            UserCommission::class => UserCommissionLock::class,
            UserOverrides::class => UserOverridesLock::class,
            ClawbackSettlement::class => ClawbackSettlementLock::class,
            PayrollDeductions::class => PayrollDeductionLock::class,
            PayrollHourlySalary::class => PayrollHourlySalaryLock::class,
            PayrollOvertime::class => PayrollOvertimeLock::class,
        ];

        foreach ($modelToLocks as $model => $modelToLock) {
            $amountColumn = $modelAmountColumns[$model];
            
            $addToLock = $model::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })
            ->where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'status' => 3, 'is_onetime_payment' => 0])
            // Only copy non-zero amount records
            ->where(function($q) use ($amountColumn) {
                $q->whereNotNull($amountColumn)->where($amountColumn, '!=', 0);
            })
            ->get();
            
            $addToLock->each(function ($value) use ($modelToLock, $data) {
                $modelToLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value->toArray());
            });
        }

        $userReconciliationCommissionData = UserReconciliationCommission::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })
        ->where(['status' => 'paid', 'user_id' => $data->user_id, 'payroll_id' => $data->id])
        // Only copy non-zero amounts
        ->where(function($q) {
            $q->whereNotNull('net_amount')->where('net_amount', '!=', 0);
        })
        ->get();
        
        foreach ($userReconciliationCommissionData->toArray() as $value) {
            UserReconciliationCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
        }

        $approvalsAndRequestData = ApprovalsAndRequest::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })
        ->where(['user_id' => $data->user_id, 'status' => 'Paid', 'payroll_id' => $data->id, 'is_onetime_payment' => 0])
        // Only copy non-zero amounts
        ->where(function($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })
        ->get();
        
        foreach ($approvalsAndRequestData->toArray() as $value) {
            ApprovalsAndRequestLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
        }

        $customFieldRecords = CustomField::where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->get();
        $customFieldRecords->each(function ($value) {
            CustomFieldHistory::updateOrCreate(
                ['payroll_id' => $value['payroll_id'], 'user_id' => $value['user_id'], 'column_id' => $value['column_id']],
                $value->only(['user_id', 'payroll_id', 'column_id', 'value', 'comment', 'approved_by', 'is_mark_paid', 'is_next_payroll', 'pay_period_from', 'pay_period_to'])
            );
            CustomField::find($value['id'])->delete();
        });

        $advanceSetting = AdvancePaymentSetting::first();
        if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
            $startDateNext = $this->newStartDate;
            $endDateNext = $this->newEndDate;
            $advanceRequestStatus = 'Accept';
        } else {
            $startDateNext = null;
            $endDateNext = null;
            $advanceRequestStatus = 'Approved';
        }

        $adjustmentTotal = 0;
        $addApprovalsAndRequestIds = [];
        $approvalAndRequests = ApprovalsAndRequest::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($startDate, $endDate) {
            $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
        })->where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'status' => 'Paid', 'adjustment_type_id' => 4, 'is_onetime_payment' => 0])->get();
        foreach ($approvalAndRequests as $approvalAndRequest) {
            $description = null;
            if (! empty($approvalAndRequest->req_no)) {
                $description = 'Advance payment request Id: '.$approvalAndRequest->req_no.' Date of request: '.date('m/d/Y');
            }
            $addApprovalsAndRequest = ApprovalsAndRequest::create([
                'user_id' => $approvalAndRequest->user_id,
                'parent_id' => $approvalAndRequest->id,
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
                'description' => $description,
                'pay_period_from' => isset($startDateNext) ? $startDateNext : NULL,
                'pay_period_to' => isset($endDateNext) ? $endDateNext : NULL,
                'user_worker_type' => $approvalAndRequest->user_worker_type,
                'pay_frequency' => $approvalAndRequest->pay_frequency
            ]);
            $addApprovalsAndRequestIds[] = $addApprovalsAndRequest->id;
            $adjustmentTotal += $approvalAndRequest->amount;
        }
        if ($adjustmentTotal > 0 && $advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
            $payrollId = updateExistingPayroll($data->user_id, $startDateNext, $endDateNext, $adjustmentTotal, 'adjustment', $data->position_id, 0);
            ApprovalsAndRequest::whereIn('id', $addApprovalsAndRequestIds)->update(['payroll_id' => $payrollId]);
        }

        /* Recon table update according to payroll */
        $moveToReconCommissionData = UserCommission::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 3,
            'is_move_to_recon' => 1,
            'is_onetime_payment' => 0,
            'settlement_type' => 'during_m2',
        ])
        // Only move non-zero amounts to recon
        ->where(function($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })
        ->get();

        if ($moveToReconCommissionData) {
            foreach ($moveToReconCommissionData as $value) {
                UserCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value->toArray());
                $value->payroll_id = 0;
                $value->pay_period_from = null;
                $value->pay_period_to = null;
                $value->settlement_type = 'reconciliation';
                $value->save();
            }
        }

        $moveToReconOverridesData = UserOverrides::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 3,
            'is_move_to_recon' => 1,
            'is_onetime_payment' => 0,
            'overrides_settlement_type' => 'during_m2',
        ])
        // Only move non-zero amounts to recon
        ->where(function($q) {
            $q->whereNotNull('amount')->where('amount', '!=', 0);
        })
        ->get();

        if ($moveToReconOverridesData) {
            foreach ($moveToReconOverridesData as $value) {
                UserOverridesLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value->toArray());

                $value->payroll_id = 0;
                $value->pay_period_from = null;
                $value->pay_period_to = null;
                $value->overrides_settlement_type = 'reconciliation';
                $value->save();
            }
        }

        $moveToReconClawbackData = ClawbackSettlement::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 3,
            'is_move_to_recon' => 1,
            'is_onetime_payment' => 0,
            'clawback_type' => 'next payroll',
        ])
        // Only move non-zero clawback amounts to recon
        ->where(function($q) {
            $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
        })
        ->get();

        if ($moveToReconClawbackData) {
            foreach ($moveToReconClawbackData as $value) {
                ClawbackSettlementLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value->toArray());

                $value->payroll_id = 0;
                $value->pay_period_from = null;
                $value->pay_period_to = null;
                $value->clawback_type = 'reconciliation';
                $value->save();
            }
        }

        $moveToReconDeductionData = PayrollDeductions::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 3,
            'is_move_to_recon' => 1,
            'is_onetime_payment' => 0,
        ])
        // Only move non-zero deduction amounts to recon
        ->where(function($q) {
            $q->whereNotNull('total')->where('total', '!=', 0);
        })
        ->get();

        if (count($moveToReconDeductionData) > 0) {
            foreach ($moveToReconDeductionData->toArray() as $value) {
                PayrollDeductionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
            }
        }

        /* recon finalize history update */
        ReconciliationFinalizeHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 'payroll',
            'is_onetime_payment' => 0,
        ])->update([
            'payroll_execute_status' => 3,
        ]);

        $finalizeReconAmount = ReconciliationFinalizeHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'payroll_execute_status' => '3',
            'is_onetime_payment' => 0,
        ])->get();

        if (count($finalizeReconAmount) > 0) {
            foreach ($finalizeReconAmount->toArray() as $value) {
                ReconciliationFinalizeHistoryLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
            }
        }

        /* recon commission update */
        ReconCommissionHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 'payroll',
        ])->update([
            'payroll_execute_status' => 3,
        ]);

        $finalizeReconCommissionAmount = ReconCommissionHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'payroll_execute_status' => '3',
        ])->get();

        if (count($finalizeReconCommissionAmount) > 0) {
            foreach ($finalizeReconCommissionAmount->toArray() as $value) {
                ReconCommissionHistoryLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
            }
        }

        /* recon Overrides update */
        ReconOverrideHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 'payroll',
        ])->update([
            'payroll_execute_status' => 3,
        ]);

        $finalizeReconOverrideAmount = ReconOverrideHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'payroll_execute_status' => '3',
        ])->get();

        if (count($finalizeReconOverrideAmount) > 0) {
            foreach ($finalizeReconOverrideAmount->toArray() as $value) {
                ReconOverrideHistoryLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
            }
        }
        /* Recon Adjustment update */
        ReconAdjustment::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
        ])->update([
            'payroll_execute_status' => 3,
        ]);

        /* recon deduction update */
        ReconDeductionHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 'payroll',
        ])->update([
            'payroll_executed_status' => '3',
        ]);

        $finalizeReconDeductionAmount = ReconDeductionHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'payroll_executed_status' => '3',
        ])->get();

        if (count($finalizeReconDeductionAmount) > 0) {
            foreach ($finalizeReconDeductionAmount->toArray() as $value) {
                ReconDeductionHistoryLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
            }
        }

        /* recon clawback update */
        ReconClawbackHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'status' => 'payroll',
        ])->update([
            'payroll_execute_status' => 3,
        ]);

        $finalizeReconClawbackAmount = ReconClawbackHistory::where([
            'user_id' => $data->user_id,
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate,
            'payroll_id' => $data->id,
            'payroll_execute_status' => '3',
        ])->get();

        if (count($finalizeReconClawbackAmount) > 0) {
            foreach ($finalizeReconClawbackAmount->toArray() as $value) {
                ReconClawbackHistoryLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
            }
        }

        Payroll::where('id', $data->id)->delete();
        create_paystub_employee([
            'user_id' => $data->user_id,
            'pay_period_from' => $data->pay_period_from,
            'pay_period_to' => $data->pay_period_to,
        ]);
        Notification::create([
            'user_id' => $data->user_id,
            'type' => 'Execute PayRoll',
            'description' => 'Execute PayRoll Data',
            'is_read' => 0,
        ]);

        $notificationData = [
            'user_id' => $data->usersdata->user_id,
            'device_token' => $data->usersdata->device_token,
            'title' => 'Execute PayRoll Data.',
            'sound' => 'sound',
            'type' => 'Execute PayRoll',
            'body' => 'Updated Execute PayRoll Data',
        ];
        $this->sendNotification($notificationData);

        if ($this->final && $workerType != 'w2' && $workerType != 'W2') {
            $this->generatePdfAndSendMail($startDate, $endDate, $payFrequency);
        }

        if ($enableEVE == 0) { // $this->final &&  Removed because it is not coming from execute payroll
            createLogFile('quickbooks', '['.now()." ] Inside Execute payroll job Before dispatching the job for Journal entry for: {$startDate} - {$endDate} and everee enabled = {$enableEVE}");
            // create journal entry for status 0
            CreateJournalEntryJob::dispatch($startDate, $endDate, $enableEVE);
            createLogFile('quickbooks', '['.now()." ] Inside Execute payroll job After dispatching the job for Journal entry for: {$startDate} - {$endDate} and everee enabled = {$enableEVE}");

        }
    }

    public function generatePdfAndSendMail($startDate, $endDate, $payFrequency)
    {
        $companyProfile = CompanyProfile::first();
        $managerReportsController = new ManagerReportsControllerV1;
        $tempFinalizeRecords = TempPayrollFinalizeExecuteDetail::with('user')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'type' => 'Execute'])->get();
        foreach ($tempFinalizeRecords as $tempFinalizeRecord) {
            $userId = $tempFinalizeRecord->user_id;
            $getTotalCalculations = $managerReportsController->getTotalnetPayAmount($userId, $startDate, $endDate, $payFrequency);
            $saleCount = UserCommissionLock::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })->where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 0])->selectRaw('COUNT(DISTINCT(pid)) AS count')->value('count');
            $payStub = [
                'net_pay' => isset($getTotalCalculations['net_pay']) ? $getTotalCalculations['net_pay'] : 0,
                'net_ytd' => isset($getTotalCalculations['net_ytd']) ? $getTotalCalculations['net_ytd'] : 0,
                'pay_frequency' => $payFrequency,
                'pay_period_from' => $startDate,
                'pay_period_to' => $endDate,
                'periodeCustomeFieldsSum' => isset($getTotalCalculations['customFieldSum']) ? $getTotalCalculations['customFieldSum'] : 0,
                'ytdCustomeFieldsSum' => isset($getTotalCalculations['customFieldSumYtd']) ? $getTotalCalculations['customFieldSumYtd'] : 0,
                'pay_date' => date('Y-m-d'),
                'period_sale_count' => $saleCount,
                'ytd_sale_count' => UserCommissionLock::where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->value('count'),
            ];

            $reconAmount = PayrollHistory::when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($startDate, $endDate) {
                $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
            })->where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 0])->sum('reconciliation');
            $reconYTD = PayrollHistory::where(['user_id' => $userId, 'status' => '3', 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('reconciliation');
            $earnings = [
                'commission' => [
                    'period_total' => isset($getTotalCalculations['userCommissionSum']) ? $getTotalCalculations['userCommissionSum'] : 0,
                    'ytd_total' => isset($getTotalCalculations['userCommissionSumYtd']) ? $getTotalCalculations['userCommissionSumYtd'] : 0,
                ],
                'overrides' => [
                    'period_total' => isset($getTotalCalculations['userOverrideSum']) ? $getTotalCalculations['userOverrideSum'] : 0,
                    'ytd_total' => isset($getTotalCalculations['userOverrideSumYtd']) ? $getTotalCalculations['userOverrideSumYtd'] : 0,
                ],
                'reconciliation' => [
                    'period_total' => $reconAmount,
                    'ytd_total' => $reconYTD,
                ],
            ];

            $deduction = [
                'standard_deduction' => [
                    'period_total' => isset($getTotalCalculations['deductionSum']) ? $getTotalCalculations['deductionSum'] : 0,
                    'ytd_total' => isset($getTotalCalculations['deductionSumYtd']) ? $getTotalCalculations['deductionSumYtd'] : 0,
                ],
            ];

            $miscellaneous = [
                'adjustment' => [
                    'period_total' => isset($getTotalCalculations['adjustment']) ? $getTotalCalculations['adjustment'] : 0,
                    'ytd_total' => isset($getTotalCalculations['adjustmentYtd']) ? $getTotalCalculations['adjustmentYtd'] : 0,
                ],
                'reimbursement' => [
                    'period_total' => isset($getTotalCalculations['reimbursement']) ? $getTotalCalculations['reimbursement'] : 0,
                    'ytd_total' => isset($getTotalCalculations['reimbursementYtd']) ? $getTotalCalculations['reimbursementYtd'] : 0,
                ],
            ];

            $user = User::with('positionDetailTeam')->where('id', $userId)->first();
            $customPayment = CustomFieldHistory::where(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'is_onetime_payment' => 0])->sum('value');
            $newData = [
                'pay_stub' => $payStub,
                'employee' => $user,
                'earnings' => $earnings,
                'deduction' => $deduction,
                'miscellaneous' => $miscellaneous,
            ];

            $commissionDetailsLock = $this->payStubCommissionDetails($tempFinalizeRecord);
            $overrideDetailsLock = $this->payStubOverrideDetails($tempFinalizeRecord);
            $adjustmentDetailsLock = $this->payStubAdjustmentDetails($tempFinalizeRecord);
            $reimbursementDetailsLock = $this->payStubReimbursementDetails($tempFinalizeRecord);
            $deductionsDetailsLock = $this->payStubDeductionsDetails($tempFinalizeRecord);
            $additionalValueDetailsLock = $this->additionalValueDetails($tempFinalizeRecord);

            $uniqueTime = time();
            $pdfPath = public_path('/template/'.$user->first_name.'_'.$user->last_name.'_'.$uniqueTime.'_pay_stub.pdf');
            $pdf = Pdf::loadView('mail.paystub_available', [
                'data' => $newData,
                'commission_details' => $commissionDetailsLock,
                'override_details' => $overrideDetailsLock,
                'adjustment_details' => $adjustmentDetailsLock,
                'reimbursement_details' => $reimbursementDetailsLock,
                'deductions_details' => $deductionsDetailsLock,
                'additional_value_details' => $additionalValueDetailsLock,
            ]);
            $pdf->save($pdfPath);

            $filePath = config('app.domain_name').'/'.'paystyb/'.$user->first_name.'_'.$user->last_name.'_'.time().'_pay_stub.pdf';
            $s3Data = s3_upload($filePath, $pdfPath, true, 'public');
            $s3filePath = config('app.aws_s3bucket_url').'/'.$filePath;

            $userMailName = preg_replace('/[^a-zA-Z0-9\s]/', '', $user->first_name).'-'.preg_replace('/[^a-zA-Z0-9\s]/', '', $user->last_name);
            $finalize['email'] = $user->email;
            $finalize['subject'] = 'New Paystub Available';
            $finalize['template'] = view('mail.executeUser', compact('newData', 'user', 'startDate', 'endDate', 's3filePath'))->render();
            $this->sendEmailNotification($finalize);
        }
    }

    private function payStubCommissionDetails($payroll)
    {
        $data = [];
        $userCommission = UserCommissionLock::with('saledata.productInfo')->where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->get();
        foreach ($userCommission as $value) {
            $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->payroll_id, 'user_id' => $payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'adjustment_type' => $value->schema_type, 'type' => $value->schema_type, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->sum('amount') ?? 0;
            $saleProduct = SaleProductMaster::where(['pid' => $value->pid, 'type' => $value->schema_type])->first();
            $date = isset($saleProduct->milestone_date) ? $saleProduct->milestone_date : '';
            $repRedline = null;
            if ($value->redline_type) {
                if (in_array($value->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                    $repRedline = $value->redline.' Per Watt';
                } else {
                    $repRedline = $value->redline.' '.ucwords($value->redline_type);
                }
            }
            $data[] = [
                'pid' => $value->pid,
                'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                'customer_state' => isset($value->saledata->customer_state) ? $value->saledata->customer_state : null,
                'rep_redline' => $repRedline,
                'kw' => isset($value->kw) ? $value->kw : null,
                'net_epc' => isset($value->net_epc) ? $value->net_epc : null,
                'amount' => isset($value->amount) ? $value->amount : null,
                'date' => isset($date) ? $date : null,
                'amount_type' => isset($value->schema_name) ? $value->schema_name : null,
                'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                'adjustAmount' => $adjustmentAmount,
                // Format the commission amount based on its type (percent, per kW, or per sale)
                'commission_amount' => isset($value->commission_amount) ? ($value->commission_type == 'percent' ? $value->commission_amount.' %' : ($value->commission_type == 'per kw' ? $value->commission_amount.' Per KW' : ($value->commission_type == 'per sale' ? $value->commission_amount.' Per Sale' : $value->commission_amount))) : null,
                // Get the gross account value from the related sale data if available
                'gross_value' => isset($value->saledata->gross_account_value) ? $value->saledata->gross_account_value : null,
                // Retrieve the product name from the related product info if available
                'product_name' => isset($value->saledata->productInfo->product_code) ? $value->saledata->productInfo->product_code : null,
            ];
        }

        $clawBackSettlement = ClawbackSettlementLock::with('salesDetail.productInfo')->where(['type' => 'commission', 'user_id' => $payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->get();
        foreach ($clawBackSettlement as $val) {
            $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->payroll_id, 'user_id' => $payroll->user_id, 'pid' => $val->pid, 'payroll_type' => 'commission', 'adjustment_type' => $val->schema_type, 'type' => 'clawback', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->sum('amount') ?? 0;
            $repRedline = null;
            if ($val->redline_type) {
                if (in_array($val->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                    $repRedline = $val->redline.' Per Watt';
                } else {
                    $repRedline = $val->redline.' '.ucwords($val->redline_type);
                }
            }
            $data[] = [
                'pid' => $val->pid,
                'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                'rep_redline' => $repRedline,
                'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                'amount_type' => 'clawback',
                'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                'adjustAmount' => $adjustmentAmount,
                // $val->salesDetail->total_commission_amount
                'commission_amount' => isset($val->clawback_cal_amount) ? ($val->clawback_cal_type == 'percent' ? $val->clawback_cal_amount.' %' : ($val->clawback_cal_type == 'per kw' ? $val->clawback_cal_amount.' Per KW' : ($val->clawback_cal_type == 'per sale' ? $val->clawback_cal_amount.' Per Sale' : $val->clawback_cal_amount))) : null,
                'gross_value' => isset($val->salesDetail->gross_account_value) ? $val->salesDetail->gross_account_value : null,
                'product_name' => isset($val->salesDetail->productInfo->product_code) ? $val->salesDetail->productInfo->product_code : null,
            ];
        }

        return $data;
    }

    private function payStubOverrideDetails($payroll)
    {
        $data = [];
        $userData = UserOverridesLock::with('userInfo', 'salesDetail.productInfo')->where(['user_id' => $payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->get();
        foreach ($userData as $value) {
            $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->payroll_id, 'user_id' => $payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'adjustment_type' => $value->type, 'type' => $value->type, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->sum('amount') ?? 0;
            $sale = SalesMaster::where(['pid' => $value->pid])->first();
            $data[] = [
                'pid' => $value->pid,
                'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
                'first_name' => isset($value->userInfo->first_name) ? $value->userInfo->first_name : null,
                'last_name' => isset($value->userInfo->last_name) ? $value->userInfo->last_name : null,
                'type' => isset($value->type) ? $value->type : null,
                'kw_installed' => $value->kw ?? 0,
                'override_amount' => $value->overrides_amount ?? 0,
                'override_type' => $value->overrides_type ?? null,
                'total_amount' => $value->amount ?? 0,
                'amount' => $adjustmentAmount,
                'product_name' => isset($value->salesDetail->productInfo->product_code) ? $value->salesDetail->productInfo->product_code : null,
                'gross_value' => isset($value->salesDetail->gross_account_value) ? $value->salesDetail->gross_account_value : null,
            ];
        }

        $clawBackSettlement = ClawbackSettlementLock::with('users', 'salesDetail.productInfo')->where(['type' => 'overrides', 'user_id' => $payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->get();
        foreach ($clawBackSettlement as $val) {
            $override = UserOverridesLock::where(['user_id' => $payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'type' => $val->adders_type, 'is_onetime_payment' => 0])->first();
            $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->payroll_id, 'user_id' => $payroll->user_id, 'pid' => $val->pid, 'payroll_type' => 'overrides', 'adjustment_type' => $val->adders_type, 'type' => 'clawback', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->sum('amount') ?? 0;
            $data[] = [
                'pid' => $val->pid,
                'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                'first_name' => isset($val->users->first_name) ? $val->users->first_name : null,
                'last_name' => isset($val->users->last_name) ? $val->users->last_name : null,
                'type' => 'clawback',
                'kw_installed' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                'override_amount' => $override->overrides_amount ?? 0,
                'override_type' => $override->overrides_type ?? null,
                'total_amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                'amount' => $adjustmentAmount,
                'product_name' => isset($val->salesDetail->productInfo->product_code) ? $val->salesDetail->productInfo->product_code : null,
                'gross_value' => isset($val->salesDetail->gross_account_value) ? $val->salesDetail->gross_account_value : null,
            ];
        }

        return $data;
    }

    private function payStubAdjustmentDetails($payroll)
    {
        $data = [];
        $adjustment = ApprovalsAndRequestLock::with('approvedBy', 'adjustment', 'PID')->where(['user_id' => $payroll->user_id, 'status' => 'Paid', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
        foreach ($adjustment as $value) {
            $data[] = [
                'customer_name' => isset($value->PID->customer_name) ? $value->PID->customer_name : null,
                'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                'amount' => isset($value->amount) ? $value->amount : null,
                'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                'description' => isset($value->description) ? $value->description : null,
            ];
        }

        $adjustmentNegative = ApprovalsAndRequestLock::with('approvedBy', 'adjustment', 'PID')->where(['user_id' => $payroll->user_id, 'status' => 'Paid', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'adjustment_type_id' => 5, 'is_onetime_payment' => 0])->get();
        foreach ($adjustmentNegative as $value) {
            $data[] = [
                'customer_name' => isset($value->PID->customer_name) ? $value->PID->customer_name : null,
                'first_name' => isset($value?->approvedBy?->first_name) ? $value?->approvedBy?->first_name : null,
                'last_name' => isset($value?->approvedBy?->last_name) ? $value?->approvedBy?->last_name : null,
                'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                'amount' => isset($value->amount) ? (0 - $value->amount) : null,
                'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                'description' => isset($value->description) ? $value->description : null,
            ];
        }

        $payrollHistoryPayrollIDs = PayrollHistory::where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->pluck('payroll_id');
        $payrollAdjustmentDetail = PayrollAdjustmentDetailLock::with('saledata')->whereIn('payroll_id', $payrollHistoryPayrollIDs)->where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->get();
        foreach ($payrollAdjustmentDetail as $value) {
            $data[] = [
                'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                'amount' => isset($value->amount) ? $value->amount : null,
                'type' => $value->payroll_type ?? null,
                'description' => $value->comment ?? null,
            ];
        }

        return $data;
    }

    public function payStubReimbursementDetails($payroll)
    {
        $data = [];
        $reimbursement = ApprovalsAndRequestLock::with('approvedBy')->where(['user_id' => $payroll->user_id, 'status' => 'Paid', 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'adjustment_type_id' => '2', 'is_onetime_payment' => 0])->get();
        foreach ($reimbursement as $value) {
            $data[] = [
                'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                'date' => isset($value->cost_date) ? $value->cost_date : null,
                'amount' => isset($value->amount) ? $value->amount : null,
                'description' => isset($value->description) ? $value->description : null,
            ];
        }

        return $data;
    }

    public function payStubDeductionsDetails($payroll)
    {
        $data = [];
        $deductions = PayrollDeductionLock::with('costcenter')
            ->leftJoin('payroll_adjustment_details_lock', function ($join) {
                $join->on('payroll_adjustment_details_lock.payroll_id', '=', 'payroll_deduction_locks.payroll_id')
                    ->on('payroll_adjustment_details_lock.cost_center_id', '=', 'payroll_deduction_locks.cost_center_id');
            })->where(['payroll_deduction_locks.user_id' => $payroll->user_id, 'payroll_deduction_locks.payroll_id' => $payroll->payroll_id, 'payroll_deduction_locks.is_next_payroll' => 0, 'payroll_deduction_locks.pay_period_from' => $payroll->pay_period_from, 'payroll_deduction_locks.pay_period_to' => $payroll->pay_period_to, 'payroll_deduction_locks.is_onetime_payment' => 0])
            ->select('payroll_deduction_locks.*', 'payroll_adjustment_details_lock.amount as adjustment_amount')->get();

        foreach ($deductions as $deduction) {
            $data[] = [
                'Type' => $deduction?->costcenter?->name ?? null,
                'Amount' => $deduction->amount ?? 0,
                'Limit' => $deduction->limit ?? 0,
                'Total' => $deduction->total ?? 0,
                'Outstanding' => $deduction->outstanding ?? 0,
                'adjustment_amount' => $deduction->adjustment_amount ?? 0,
            ];
        }

        return $data;
    }

    public function additionalValueDetails($payroll)
    {
        $customFields = [];
        $customFields = CustomFieldHistory::with(['getColumn', 'getApprovedBy'])->where(['payroll_id' => $payroll->payroll_id, 'user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_onetime_payment' => 0])->get();
        $customFields->transform(function ($customFields) {
            $date = $customFields->updated_at != null ? \Carbon\Carbon::parse($customFields->updated_at)->format('m/d/Y') : \Carbon\Carbon::parse($customFields->created_at)->format('m/d/Y');
            $approvedByDetail = [];
            if ($customFields->getApprovedBy) {
                $approvedByDetail = [
                    'first_name' => $customFields->getApprovedBy->first_name,
                    'middle_name' => $customFields->getApprovedBy->middle_name,
                    'last_name' => $customFields->getApprovedBy->last_name,
                ];
            }

            return [
                'amount' => isset($customFields->value) ? $customFields->value : 0,
                'type' => $customFields?->getColumn?->field_name ?? '',
                'date' => $date,
                'comment' => $customFields->comment ?? null,
                'adjustment_by_detail' => $approvedByDetail,
            ];
        });

        return $customFields;
    }

    public function failed(\Exception $e)
    {
        $error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job' => $this,
        ];

        \Illuminate\Support\Facades\Log::error('Failed to execute payroll job', $error);
    }
}
