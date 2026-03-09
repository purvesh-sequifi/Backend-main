<?php

namespace App\Jobs;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Models\AdditionalPayFrequency;
use App\Models\AdvancePaymentSetting;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\GetPayrollData;
use App\Models\Locations;
use App\Models\MonthlyPayFrequency;
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
use App\Models\PositionReconciliations;
use App\Models\SalesMaster;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationCommissionLock;
use App\Models\UserWagesHistory;
use App\Models\WeeklyPayFrequency;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class executeW2PayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use EmailNotificationTrait, EvereeTrait, PayFrequencyTrait, PushNotificationTrait;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $data;

    public $start_date;

    public $end_date;

    public $currentIndex;

    public $totalIIndex;

    public $timeout = 600; // 10 minutes

    public $tries = 3;

    public function __construct($data, $currentIndex, $totalIIndex, $start_date, $end_date)
    {
        $this->data = $data;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->currentIndex = $currentIndex;
        $this->totalIIndex = $totalIIndex;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // DB::beginTransaction();
        // initialize the variables
        $data = $this->data;
        $start_date = $this->start_date;
        $end_date = $this->end_date;
        $currentIndex = $this->currentIndex;
        $totalIIndex = $this->totalIIndex;

        $filePath = public_path('/' . $start_date . '_' . $end_date . '_executePayrollUsers.txt');
        $myArray = [];

        if ($currentIndex == 1) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // file_put_contents($filePath, '[', FILE_APPEND);
        }

        // get CRM setting status
        $CrmData = Crms::where('id', 3)->where('status', 1)->first();

        // $pusher_message = "Executing";
        // payrollFinalisePusherNotification($start_date, $end_date, $pusher_message, 3);

        $data->transform(function ($data) use ($start_date, $end_date, $CrmData, $filePath) {
            file_put_contents($filePath, $data->user_id . ',', FILE_APPEND);
            $user_id = $data->user_id;
            $external_id = $data['usersdata']['employee_id'] . '-' . $data->id;
            if (! empty($CrmData) && $data['is_mark_paid'] != 1 && $data['net_pay'] > 0 && $data['status'] != 6 && $data['status'] != 7) { // $data['is_next_payroll'] !=1 &&
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

                        $external_id = 'S-' . $data['usersdata']['employee_id'] . '-' . $data->id;
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
                        $untracked = $S_untracked;
                    }

                    if ($data['reimbursement'] > 0) {

                        $external_id = 'R-' . $data['usersdata']['employee_id'] . '-' . $data->id;
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
                        $untracked = $R_untracked;
                    }

                    if ($data['net_pay'] > 0) {

                        $reimbursement = ($data['reimbursement'] > 0) ? $data['reimbursement'] : 0;
                        $hourlySalary = ($data['hourly_salary'] > 0) ? $data['hourly_salary'] : 0;

                        $net_pay = ($data['net_pay'] - $reimbursement - $hourlySalary);
                        $external_id = 'C-' . $data['usersdata']['employee_id'] . '-' . $data->id;
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
                            $untracked = $C_untracked;
                        }
                    }
                }

                /*
                if ($data['reimbursement']>0 && $data['hourly_salary']>0) {

                }
                elseif ($data['reimbursement'] == 0 && $data['hourly_salary'] > 0) {

                    $S_external_id = 'S-'.$data['usersdata']['employee_id']."-".$data->id;
                    $hourly_salary = $data['hourly_salary'];
                    $net_pay = $data['net_pay'];
                    $data['net_pay'] = $hourly_salary;
                    $S_untracked = $this->create_gross_earning_data($data,$S_external_id,'REGULAR_SALARY');  //add  payable in everee
                    if((isset($S_untracked['success']['status']) && $S_untracked['success']['status'] == true)) {
                        $C_external_id = 'C-'.$data['usersdata']['employee_id']."-".$data->id;
                        $data['net_pay'] = $net_pay - $hourly_salary;
                        if($data['net_pay']> 0 ){
                            $C_untracked = $this->create_gross_earning_data($data,$C_external_id,'COMMISSION');  //Add payable in everee
                            if((isset($C_untracked['success']['status']) && $C_untracked['success']['status'] == true))
                            {
                                $external_id = $S_external_id.','.$C_external_id;
                                $untracked = $C_untracked;
                            }
                            else {
                                // $this->delete_payable($R_external_id);
                                // $external_id = '';
                                // $untracked = $C_untracked;
                            }
                        }
                        else{
                            $external_id = $S_external_id;
                            $untracked = $S_untracked;
                        }
                    } else {
                        $external_id = '';
                        $untracked = $S_untracked;
                    }

                    $enableEVE = 1;

                }
                elseif ($data['reimbursement']>0 && $data['hourly_salary'] == 0) {

                    $R_external_id = 'R-'.$data['usersdata']['employee_id']."-".$data->id;
                    $reimbursement = $data['reimbursement'];
                    $net_pay = $data['net_pay'];
                    $data['net_pay'] = $reimbursement;
                    $R_untracked = $this->create_gross_earning_data($data,$R_external_id,'REIMBURSEMENT');  //add  payable in everee
                    if((isset($R_untracked['success']['status']) && $R_untracked['success']['status'] == true)) {
                        $C_external_id = 'C-'.$data['usersdata']['employee_id']."-".$data->id;
                        $data['net_pay'] = $net_pay - $reimbursement;
                        if($data['net_pay']> 0 ){
                            $C_untracked = $this->create_gross_earning_data($data,$C_external_id,'COMMISSION');  //Add payable in everee
                            if((isset($C_untracked['success']['status']) && $C_untracked['success']['status'] == true))
                            {
                                $external_id = $R_external_id.','.$C_external_id;
                                $untracked = $C_untracked;
                            }
                            else {
                                // $this->delete_payable($R_external_id);
                                // $external_id = '';
                                // $untracked = $C_untracked;
                            }
                        }
                        else{
                            $external_id = $R_external_id;
                            $untracked = $R_untracked;
                        }
                    } else {
                        $external_id = '';
                        $untracked = $R_untracked;
                    }

                    $enableEVE = 1;

                }
                else{

                    $external_id = 'C-'.$data['usersdata']['employee_id']."-".$data->id;
                    $untracked = $this->create_gross_earning_data($data,$external_id,'COMMISSION');  //update payable in everee
                    $enableEVE = 1;

                }
                */

                $enableEVE = 1;
                // $untracked = $this->payable_request($data); //update payable in everee
                $pay_type = 'Bank';
            } else {
                $enableEVE = 0;
                $pay_type = 'Manualy';
            }
            // $payroll = Payroll::where(['id' => $data->id,'status'=>'2'])->first();
            if (isset($untracked) || $enableEVE == 0) {
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
                    // 'everee_paymentId' => isset($untracked['success']['everee_payment_id']) ? $untracked['success']['everee_payment_id'] : null,
                    // 'everee_payment_requestId' => isset($untracked['success']['paymentId']) ? $untracked['success']['paymentId'] : null,
                    'everee_json_response' => isset($untracked) ? json_encode($untracked) : null,
                ];
                $check = PayrollHistory::where(['payroll_id' => $data->id, 'user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->count();
                if ($check == 0) {
                    $insert = PayrollHistory::create($createdata);
                }
                $status = UserCommission::where(['user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->update(['status' => 3]);
                UserOverrides::where(['user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->update(['status' => 3]);
                ClawbackSettlement::where(['user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->update(['status' => 3]);
                $userReconcilationCommission = UserReconciliationCommission::where('user_id', $data->user_id)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'payroll_id' => $data->id])->update(['status' => 'paid']);
                $approvelAndRequest = ApprovalsAndRequest::where('status', 'Accept')->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->update(['status' => 'Paid']);
                PayrollAdjustment::where('user_id', $data->user_id)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'payroll_id' => $data->id])->update(['status' => 3]);
                PayrollAdjustmentDetail::where('user_id', $data->user_id)->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'payroll_id' => $data->id])->update(['status' => 3]);
                PayrollDeductions::where(['user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'payroll_id' => $data->id])->update(['status' => 3]);
                PayrollHourlySalary::where(['user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'payroll_id' => $data->id])->update(['status' => 3]);
                PayrollOvertime::where(['user_id' => $data->user_id, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'payroll_id' => $data->id])->update(['status' => 3]);
                $payrollDelete = Payroll::where(['id' => $data->id, 'status' => '2'])->delete();

                $reqs = ApprovalsAndRequest::where('status', 'Paid')->where('payroll_id', $data->id)->get();
                if ($reqs) {
                    foreach ($reqs as $key => $reqsValue) {
                        $ChielddReq = ApprovalsAndRequest::where('parent_id', $reqsValue->parent_id)->where('status', 'Paid')->sum('amount');
                        $parenntdReq = ApprovalsAndRequest::where('id', $reqsValue->parent_id)->where('status', 'Accept')->sum('amount');
                        if ($ChielddReq == $parenntdReq) {
                            ApprovalsAndRequest::where('id', $reqsValue->parent_id)->update(['status' => 'Paid']);
                        }
                    }
                }

                // Retrieve data from PayrollAdjustmentData - only non-zero commission amounts
                $PayrollAdjustmentData = PayrollAdjustment::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('commission_amount')->where('commission_amount', '!=', 0);
                    })
                    ->get();

                if ($PayrollAdjustmentData) {
                    foreach ($PayrollAdjustmentData->toArray() as $value) {
                        PayrollAdjustmentLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from PayrollAdjustmentDetailData - only non-zero amounts
                $PayrollAdjustmentDetailData = PayrollAdjustmentDetail::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into PayrollAdjustmentDetailLock using Eloquent
                if ($PayrollAdjustmentDetailData) {
                    foreach ($PayrollAdjustmentDetailData->toArray() as $value) {
                        PayrollAdjustmentDetailLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                $UserReconciliationCommissionlData = UserReconciliationCommission::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 'paid',
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('net_amount')->where('net_amount', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into UserReconciliationCommissionLock using Eloquent
                if ($UserReconciliationCommissionlData) {
                    foreach ($UserReconciliationCommissionlData->toArray() as $value) {
                        UserReconciliationCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from UserCommission - only non-zero amounts
                $userCommissionData = UserCommission::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into UserCommissionLock using Eloquent
                if ($userCommissionData) {
                    foreach ($userCommissionData->toArray() as $value) {
                        UserCommissionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from UserOverrides - only non-zero amounts
                $UserOverridesData = UserOverrides::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into UserOverridesLock using Eloquent
                if ($UserOverridesData) {
                    foreach ($UserOverridesData->toArray() as $value) {
                        UserOverridesLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from ClawbackSettlement - only non-zero amounts
                $ClawbackSettlementData = ClawbackSettlement::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into ClawbackSettlementLock using Eloquent
                if ($ClawbackSettlementData) {
                    foreach ($ClawbackSettlementData->toArray() as $value) {
                        ClawbackSettlementLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from ApprovalsAndRequest - only non-zero amounts
                $ApprovalsAndRequestData = ApprovalsAndRequest::where([
                    'user_id' => $data->user_id,
                    'status' => 'Paid',
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into ApprovalsAndRequestLock using Eloquent
                if ($ApprovalsAndRequestData) {
                    foreach ($ApprovalsAndRequestData->toArray() as $value) {
                        ApprovalsAndRequestLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from PayrollDeductions - only non-zero totals
                $payrollDeductionData = PayrollDeductions::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('total')->where('total', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into PayrollDeductionLock using Eloquent
                if (count($payrollDeductionData) > 0) {
                    foreach ($payrollDeductionData->toArray() as $value) {
                        PayrollDeductionLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from PayrollHourlySalary - only non-zero totals
                $payrollHourlySalaryData = PayrollHourlySalary::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('total')->where('total', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into PayrollHourlySalaryLock using Eloquent
                if (count($payrollHourlySalaryData) > 0) {
                    foreach ($payrollHourlySalaryData->toArray() as $value) {
                        PayrollHourlySalaryLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from PayrollOvertime - only non-zero totals
                $payrollOvertimeData = PayrollOvertime::where([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                    'payroll_id' => $data->id,
                    'status' => 3,
                ])
                    ->where(function ($q) {
                        $q->whereNotNull('total')->where('total', '!=', 0);
                    })
                    ->get();

                // Inserting data directly into PayrollOvertimeLock using Eloquent
                if (count($payrollOvertimeData) > 0) {
                    foreach ($payrollOvertimeData->toArray() as $value) {
                        PayrollOvertimeLock::updateOrCreate(['id' => $value['id'], 'payroll_id' => $data->id], $value);
                    }
                }

                // Retrieve data from Custom_Field based on your criteria
                $CustomFieldRecords = CustomField::where([
                    'user_id' => $data->user_id,
                    'payroll_id' => $data->id,
                    'is_next_payroll' => 0,
                ])->get();

                // Inserting data directly into ApprovalsAndRequest using Eloquent
                if ($CustomFieldRecords) {
                    foreach ($CustomFieldRecords->toArray() as $value) {
                        $customFieldHistory = CustomFieldHistory::where(['payroll_id' => $value['payroll_id'], 'user_id' => $value['user_id'], 'column_id' => $value['column_id']])->first();
                        if ($customFieldHistory == null) {
                            $customFieldHistory = new CustomFieldHistory;
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
                            $customField = CustomField::find($value['id']);
                            // dd($customField);
                            $customField->delete();
                        }
                    }
                }

                // Added By DeepaK
                $adwance_setting = AdvancePaymentSetting::find(1);
                if ($adwance_setting->adwance_setting == 'automatic') {
                    $payFrequency = $this->payFrequencyNew($start_date, $data['usersdata']['sub_position_id'], $data->user_id);
                    $startDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_from : null;
                    $endDateNext = isset($payFrequency) ? $payFrequency->next_pay_period_to : null;
                    $adwance_request_status = 'Accept';
                } else {
                    $startDateNext = null;
                    $endDateNext = null;
                    $adwance_request_status = 'Approved';
                }
                $adjustmentTotal = 0;
                $addApprovalsAndRequestIds = [];
                $approvelAndRequestData = ApprovalsAndRequest::where('amount', '>', 0)->whereNotNull('req_no')->where(['user_id' => $data->user_id, 'payroll_id' => $data->id, 'status' => 'Paid', 'adjustment_type_id' => 4, 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
                foreach ($approvelAndRequestData as $key => $appuser) {
                    $addApprovalsAndRequest = ApprovalsAndRequest::create([
                        'user_id' => $appuser->user_id,
                        'parent_id' => $appuser->id,
                        'manager_id' => $appuser->manager_id,
                        'approved_by' => $appuser->approved_by,
                        'adjustment_type_id' => $appuser->adjustment_type_id,
                        'state_id' => $appuser->state_id,
                        'dispute_type' => $appuser->dispute_type,
                        'customer_pid' => $appuser->customer_pid,
                        'cost_tracking_id' => $appuser->cost_tracking_id,
                        'cost_date' => $appuser->cost_date,
                        'request_date' => $appuser->request_date,
                        'amount' => (0 - $appuser->amount),
                        'status' => $adwance_request_status,
                        'pay_period_from' => isset($startDateNext) ? $startDateNext : null,
                        'pay_period_to' => isset($endDateNext) ? $endDateNext : null,
                    ]);
                    $addApprovalsAndRequestIds[] = $addApprovalsAndRequest->id;
                    $adjustmentTotal += $appuser->amount;
                }
                if ($adjustmentTotal > 0 && $adwance_setting == 'automatic') {

                    $payroll_id = updateExistingPayroll($data->user_id, $startDateNext, $endDateNext, $adjustmentTotal, 'adjustment', $data->position_id, 0);
                    ApprovalsAndRequest::whereIn('id', $addApprovalsAndRequestIds)->update(['payroll_id' => $payroll_id]);
                }

                create_paystub_employee([
                    'user_id' => $data->user_id,
                    'pay_period_from' => $start_date,
                    'pay_period_to' => $end_date,
                ]);
                $note = Notification::create([
                    'user_id' => $data->user_id,
                    'type' => 'Execute PayRoll',
                    'description' => 'Execute PayRoll Data',
                    'is_read' => 0,
                ]);

                $notificationData = [
                    'user_id' => $data['usersdata']['user_id'],
                    'device_token' => $data['usersdata']['device_token'],
                    'title' => 'Execute PayRoll Data.',
                    'sound' => 'sound',
                    'type' => 'Execute PayRoll',
                    'body' => 'Updated Execute PayRoll Data',
                ];
                $this->sendNotification($notificationData);
            }
        });

        if ($currentIndex == $totalIIndex) {
            if (file_exists($filePath)) {
                $fileContent = file_get_contents($filePath);
                $fileContent = explode(',', $fileContent);
                $userIdArray = array_unique($fileContent);
                unlink($filePath);
                // throw new Exception();
                // printtr();

                // $pusher_message = "Executed";
                // payrollFinalisePusherNotification($start_date, $end_date, $pusher_message, 4);
                $allUsersDetails = $this->generatePdfAndSendMail($userIdArray, $start_date, $end_date);
            }
        }

        // $payrollClosed = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->where('status', '!=', 2)->count();
        $payrollClosed = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->count();
        if ($payrollClosed == 0) {
            // CRITICAL: Use model instance save() to trigger observers (not mass update)
            // Observers auto-create next pay period
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

            $additional = AdditionalPayFrequency::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->first();
            if ($additional) {
                $additional->closed_status = 1;
                $additional->save();
            }
        }
        // DB::rollBack();
    }

    // create pdf
    public function generatePdfAndSendMail($processedUsers = [3], $start_date = '2025-09-23', $end_date = '2025-09-29')
    {
        foreach ($processedUsers as $userId) {

            if ($userId == '') {
                continue;
            }
            // ---------------  Genrete pdf -----------------------
            $newData['CompanyProfile'] = CompanyProfile::first();

            $newData['id'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('id');

            $payroll_id = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('payroll_id');

            $newData['pay_stub']['pay_date'] = date('Y-m-d', strtotime(PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->value('created_at')));
            $newData['pay_stub']['net_pay'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('id', '!=', 0)->sum('net_pay');

            $newData['pay_stub']['pay_period_from'] = $start_date;
            $newData['pay_stub']['pay_period_to'] = $end_date;

            $newData['pay_stub']['period_sale_count'] = UserCommissionLock::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
            $newData['pay_stub']['ytd_sale_count'] = UserCommissionLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];

            $user = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'middle_name', 'last_name', 'employee_id', 'social_sequrity_no', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'home_address', 'zip_code', 'email', 'work_email', 'position_id', 'entity_type', 'business_ein', 'business_name')->first();
            $newData['employee'] = $user;
            $newData['employee']['is_reconciliation'] = PositionReconciliations::where('position_id', $user->position_id)->value('status');

            $newData['earnings']['commission']['period_total'] = UserCommissionLock::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3', 'is_mark_paid' => '0'])->sum('amount');
            $newData['earnings']['commission']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('commission');
            // dd($newData['earnings']['commission']['period_total']); die();
            $newData['earnings']['overrides']['period_total'] = UserOverridesLock::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3', 'is_mark_paid' => '0'])->sum('amount');
            $newData['earnings']['overrides']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('override');

            $newData['earnings']['reconciliation']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->sum('reconciliation');
            $newData['earnings']['reconciliation']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->sum('reconciliation');

            $newData['deduction']['standard_deduction']['period_total'] = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->sum('deduction');
            $newData['deduction']['standard_deduction']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('payroll_id', '!=', 0)->sum('deduction');

            // $newData['miscellaneous']['adjustment']['period_total'] = PayrollHistory::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'3'])->where('id','!=',0)->sum('adjustment');
            // $newData['miscellaneous']['adjustment']['ytd_total'] = PayrollHistory::where(['user_id'=>$userId,'status'=>'3'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->where('id','!=',0)->sum('adjustment');

            // $newData['miscellaneous']['reimbursement']['period_total'] = PayrollHistory::where(['pay_period_from'=>$start_date,'pay_period_to'=>$end_date,'user_id'=>$userId,'status'=>'3'])->where('id','!=',0)->sum('reimbursement');
            // $newData['miscellaneous']['reimbursement']['ytd_total'] = PayrollHistory::where(['user_id'=>$userId,'status'=>'3'])->where('pay_period_to','<=',$end_date)->whereYear('pay_period_from',date('Y', strtotime($start_date)))->where('id','!=',0)->sum('reimbursement');

            // ------------------------------------

            $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(DB::raw('commission_amount + overrides_amount + deductions_amount'));
            $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));
            $adjustmentToAdd = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
            $adjustmentToNigative = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_mark_paid' => '0', 'status' => 'Paid'])->where('adjustment_type_id', 5)->sum('amount');
            $newData['miscellaneous']['adjustment']['period_total'] = ($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment);
            $newData['pay_stub']['net_pay'] = $this->getTotalnetPayAmount($userId, $start_date, $end_date);
            $newData['miscellaneous']['adjustment']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('payroll_id', '!=', 0)->sum('adjustment');

            $newData['miscellaneous']['reimbursement']['period_total'] = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date, 'is_mark_paid' => '0', 'status' => 'Paid'])->where('adjustment_type_id', 2)->sum('amount');
            $newData['miscellaneous']['reimbursement']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $end_date)->whereYear('pay_period_from', date('Y', strtotime($start_date)))->where('payroll_id', '!=', 0)->sum('reimbursement');

            // ------------------------------------

            $requestData = [
                'id' => $payroll_id,
                'user_id' => $userId,
                'pay_period_from' => $start_date,
                'pay_period_to' => $end_date,
            ];
            $commission_details_lock = $this->payStubCommissionDetails($requestData);
            $override_details_lock = $this->payStubOverrideDetails($requestData);
            $adjustment_details_lock = $this->payStubAdjustmentDetails($requestData);
            $reimbursement_details_lock = $this->payStubReimbursementDetails($requestData);

            // ----------------- create pdf of user information--------------------------
            $uniqueTime = time();
            $pdfPath = public_path('/template/' . $user->first_name . '_' . $user->last_name . '_' . $uniqueTime . '_pay_stub.pdf');
            $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
            $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
            if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                $image_file_path = $s3_bucket_public_url . config('app.domain_name');
                $file_link = $image_file_path . '/' . $newData['CompanyProfile']->logo;
                $newData['CompanyProfile']['logo'] = $file_link;
            } else {
                $newData['CompanyProfile']['logo'] = $newData['CompanyProfile']->logo;
            }
            $data = [
                'user' => $user,
                'email' => $user->email,
                'start_date' => $user->startDate,
                'end_date' => $user->endDate,
                'path' => $pdfPath,
                'data' => $newData,
                'commission_details' => $commission_details_lock,
                'override_details' => $override_details_lock,
                'adjustment_details' => $adjustment_details_lock,
                'reimbursement_details' => $reimbursement_details_lock,
            ];

            $pdf = Pdf::loadView('mail.paystub_available', [
                'user' => $user,
                'email' => $user->email,
                'start_date' => $user->startDate,
                'end_date' => $user->endDate,
                'path' => $pdfPath,
                'data' => $newData,
                'commission_details' => $commission_details_lock,
                'override_details' => $override_details_lock,
                'adjustment_details' => $adjustment_details_lock,
                'reimbursement_details' => $reimbursement_details_lock,
            ]);
            /* return view('mail.paystub_available', [
                'user' => $user,
                'email' => $user->email,
                'start_date' => $user->startDate,
                'end_date' => $user->endDate,
                'path' => $pdfPath,
                'data' => $newData,
                'commission_details' => $commission_details_lock,
                'override_details' => $override_details_lock,
                'adjustment_details' => $adjustment_details_lock,
                'reimbursement_details' => $reimbursement_details_lock,
            ]); */

            $pdf->save($pdfPath);

            $filePath = config('app.domain_name') . '/' . 'paystyb/' . $user->first_name . '_' . $user->last_name . '_' . time() . '_pay_stub.pdf';
            $s3Data = s3_upload($filePath, $pdfPath, true, 'public');
            $s3filePath = config('app.aws_s3bucket_url') . '/' . $filePath;
            // ----------------- end create pdf of user information--------------------------

            // ------------------- email sending to the users ---------------------
            $userMailName = preg_replace('/[^a-zA-Z0-9\s]/', '', $user->first_name) . '-' . preg_replace('/[^a-zA-Z0-9\s]/', '', $user->last_name);
            $finalize['email'] = $user->email;
            $finalize['subject'] = 'New Paystub Available';
            $finalize['template'] = view('mail.executeUser', compact('newData', 'user', 'start_date', 'end_date', 's3filePath'));
            if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod') {
                $mailSent = $this->sendEmailNotification($finalize);
            }
            // ---------------------- End email sending to the users-------------------------
        }
    }

    // breckdown methods start
    private function payStubCommissionDetails($request)
    {
        $data = [];
        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        if (! empty($Payroll)) {

            if ($Payroll->status == 3) {
                $usercommission = UserCommissionLock::with('userdata', 'saledata')->where('status', $Payroll->status)->where(['user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            } else {
                $usercommission = UserCommissionLock::with('userdata', 'saledata')->where('status', '<', '3')->where(['user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            }

            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['user_id' => $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            // return $clawbackSettlement;
            if (count($usercommission) > 0) {
                foreach ($usercommission as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type])->first();

                    if ($value->amount_type == 'm1') {
                        $date = isset($value->saledata->m1_date) ? $value->saledata->m1_date : '';
                    } else {
                        $date = isset($value->saledata->m2_date) ? $value->saledata->m2_date : '';
                    }
                    $data[] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'is_mark_paid' => $value->is_mark_paid,
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
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,

                    ];
                }
            }

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $key1 => $val) {
                    $data[] = [
                        'id' => $val->id,
                        'pid' => $val->pid,
                        'is_mark_paid' => $val->is_mark_paid,
                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                        'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
                        'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                        'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                        'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                        'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                        'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                        'amount_type' => 'clawback',
                        'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    ];
                }
            }

            return $data;
        } else {
            return $data;
        }
    }

    private function payStubOverrideDetails($request)
    {
        $data = [];
        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $sub_total = 0;

        if (! empty($Payroll)) {
            if ($Payroll->status == 3) {
                $userdata = UserOverridesLock::where('status', $Payroll->status)->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            } else {
                $userdata = UserOverridesLock::where('status', '<', '3')->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            }
            if (count($userdata) > 0) {

                foreach ($userdata as $key => $value) {

                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();

                    $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                    $sale = SalesMaster::where(['pid' => $value->pid])->first();
                    $sub_total = ($sub_total + $value->amount);
                    $data[] = [
                        'id' => $value->sale_user_id,
                        'is_mark_paid' => $value->is_mark_paid,
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
                        'amount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                    ];
                }
            }

            return $data;
        } else {
            return $data;
        }
    }

    private function payStubAdjustmentDetails($request)
    {
        // echo"asd";die;
        $data = [];

        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        if (! empty($payroll)) {
            $adjustment = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
            $adjustmentNegative = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();

            if (count($adjustment) > 0) {
                foreach ($adjustment as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            if (count($adjustmentNegative) > 0) {
                foreach ($adjustmentNegative as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? (0 - $value->amount) : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            //  /// Added by Gorakh
            $PayrollHistoryPayrollIDs = PayrollHistory::where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->pluck('payroll_id');
            $PayrollAdjustmentDetail = PayrollAdjustmentDetailLock::whereIn('payroll_id', $PayrollHistoryPayrollIDs)->where(['user_id' => $payroll->user_id])->get();
            // dd($PayrollAdjustmentDetail);
            if (count($PayrollAdjustmentDetail) > 0) {
                foreach ($PayrollAdjustmentDetail as $key => $value) {
                    $checkUserCommission = UserCommissionLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $checkUserOverrides = UserOverridesLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $ClawbackSettlements = ClawbackSettlementLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    if ($checkUserCommission || $checkUserOverrides || $ClawbackSettlements) {
                        $is_mark_paid = 1;
                    } else {
                        $is_mark_paid = 0;
                    }
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => 'Super',
                        'last_name' => 'Admin',
                        'image' => null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => $value->payroll_type,
                        'description' => $value->comment,
                        'is_mark_paid' => $is_mark_paid,

                    ];
                }
            }

            return $data;
        } else {
            return $data;
        }
    }

    public function payStubReimbursementDetails($request)
    {
        $data = [];
        $id = $request['id'];
        $user_id = $request['user_id'];
        $pay_period_from = $request['pay_period_from'];
        $pay_period_to = $request['pay_period_to'];

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequestLock::with('user', 'approvedBy')->where('status', 'Paid')->where(['user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
            if (count($reimbursement) > 0) {
                foreach ($reimbursement as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'is_mark_paid' => $value->is_mark_paid,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'description' => isset($value->description) ? $value->description : null,
                    ];
                }
            }

            return $data;
        } else {
            return $data;
        }
    }

    public function getTotalnetPayAmount($userId, $startDate, $endDate)
    {
        $payrollHistory = PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->first();
        $payroll_id = $payrollHistory->payroll_id;

        $userCommissionSum = UserCommissionLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('amount');
        $userOverrideSum = UserOverridesLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('amount');
        $clawbackSettlementSum = ClawbackSettlementLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('clawback_amount');

        $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(DB::raw('commission_amount + overrides_amount + deductions_amount'));
        $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));

        $adjustmentToAdd = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
        $adjustmentToNigative = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [5])->sum('amount');
        $reimbursement = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [2])->sum('amount');
        $adjustment = ($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment);

        $net_pay = ($userCommissionSum + $userOverrideSum + $adjustment + $reimbursement - $clawbackSettlementSum);

        return $net_pay;
    }
}
