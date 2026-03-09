<?php

namespace App\Jobs;

use App\Core\Traits\EvereeTrait;
use App\Models\ApprovalsAndRequest;
use App\Models\Crms;
use App\Models\CustomField;
use App\Models\Payroll;
use App\Models\SequiDocsEmailSettings;
use App\Models\TempPayrollFinalizeExecuteDetail;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class finalizePayrollJob implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, EvereeTrait, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 120;

    public $data;

    public $startDate;

    public $endDate;

    public $adminMail;

    public $auth;

    public $frequencyTypeId;

    public $final;

    public function __construct($data, $startDate, $endDate, $auth, $frequencyTypeId, $final)
    {
        // $this->onQueue('finalize');
        $this->onQueue('payroll');
        $this->data = $data;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->adminMail = $auth->email;
        $this->frequencyTypeId = $frequencyTypeId;
        $this->auth = $auth;
        $this->final = $final;
    }

    public function handle(): void
    {
        $val = $this->data;
        $userId = $val->user_id;
        $endDate = $this->endDate;
        $startDate = $this->startDate;
        $actualNetPay = $val->net_pay;
        $commissionPayable = $reimbursementPayable = $bonusPayable = true;
        $workerId = isset($val->usersdata->everee_workerId) ? $val->usersdata->everee_workerId : null;
        $externalWorkerId = isset($val->usersdata->employee_id) ? $val->usersdata->employee_id : null;
        $domainName = config('app.domain_name');
        $bonusAmounts = 0;
        $externalId = [];
        $errorMessage = [];
        $status = 'SUCCESS';
        $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
        if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
            foreach ($payAblesList['items'] as $payAbleValue) {
                $this->delete_payable($payAbleValue['id'], $val->user_id);
            }
        }
        if ($val->is_next_payroll == 1) {
            Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => null, 'everee_message' => null]);
        } else {
            if (Crms::where(['id' => 3, 'status' => 1])->first()) {

                if ($val->is_mark_paid != 1 && $val->is_onetime_payment != 1 && $val->net_pay > 0) {
                    if ($val->reimbursement > 0) {
                        $rExternalId = 'R-'.$externalWorkerId.'-'.$val->id;
                        $checkPayroll = Payroll::where('id', $val->id)->first();
                        if ($checkPayroll->net_pay == $actualNetPay) {
                            $data = clone $val;
                            $data->net_pay = $val->reimbursement;
                            $rUntracked = $this->add_payable($data, $rExternalId, 'REIMBURSEMENT');
                            $reimbursementPayable = false;
                            if ((isset($rUntracked['success']['status']) && $rUntracked['success']['status'] == true)) {
                                $externalId[] = $rExternalId;
                                $reimbursementPayable = true;
                            } else {
                                if (isset($rUntracked['fail']['everee_response']['errorMessage'])) {
                                    $errorMessage[] = $rUntracked['fail']['everee_response']['errorMessage'];
                                }
                            }
                        } else {
                            $errorMessage[] = 'The net pay amount being sent to Everee is '.$val->net_pay.', while the net pay in payroll is currently '.$checkPayroll->net_pay.'.';
                        }
                    }

                    if ($val->net_pay > 0) {
                        // Get adjustment amounts for types 3 (bonus) and 6 (incentive) from approvals_and_requests table
                        $bonusAmounts = ApprovalsAndRequest::where('payroll_id', $val->id)
                            ->whereIn('adjustment_type_id', [3, 6])
                            ->where('status', 'Accept')
                            ->sum('amount');

                        $cExternalId = 'B-'.$externalWorkerId.'-'.$val->id;

                        $netPay = $bonusAmounts;

                        if ($netPay > 0) {
                            $checkPayroll = Payroll::where('id', $val->id)->first();
                            if ($checkPayroll->net_pay == $actualNetPay) {
                                $data = clone $val;
                                $data->net_pay = $netPay;

                                // Determine earning type based on payment type

                                $cUntracked = $this->add_payable($data, $cExternalId, 'BONUS');
                                $bonusPayable = false;
                                if (isset($cUntracked['success']['status']) && $cUntracked['success']['status'] == true) {
                                    $bonusPayable = true;
                                    $externalId[] = $cExternalId;
                                } else {
                                    if (isset($cUntracked['fail']['everee_response']['errorMessage'])) {
                                        $errorMessage[] = $cUntracked['fail']['everee_response']['errorMessage'];
                                    }
                                }
                            } else {
                                $errorMessage[] = 'The net pay amount being sent to Everee is '.$val->net_pay.', while the net pay in payroll is currently '.$checkPayroll->net_pay.'.';
                            }
                        }
                    }

                    if ($val->net_pay > 0) {
                        $reimbursement = ($val->reimbursement > 0) ? $val->reimbursement : 0;
                        $cExternalId = 'C-'.$externalWorkerId.'-'.$val->id;
                        $netPay = $val->net_pay - $reimbursement - $bonusAmounts;
                        if ($netPay > 0) {
                            $checkPayroll = Payroll::where('id', $val->id)->first();
                            if ($checkPayroll->net_pay == $actualNetPay) {
                                $data = clone $val;
                                $data->net_pay = $netPay;
                                $cUntracked = $this->add_payable($data, $cExternalId, 'COMMISSION');
                                $commissionPayable = false;
                                if (isset($cUntracked['success']['status']) && $cUntracked['success']['status'] == true) {
                                    $commissionPayable = true;
                                    $externalId[] = $cExternalId;
                                } else {
                                    if (isset($cUntracked['fail']['everee_response']['errorMessage'])) {
                                        $errorMessage[] = $cUntracked['fail']['everee_response']['errorMessage'];
                                    }
                                }
                            } else {
                                $errorMessage[] = 'The net pay amount being sent to Everee is '.$val->net_pay.', while the net pay in payroll is currently '.$checkPayroll->net_pay.'.';
                            }
                        }
                    }

                    if (! $reimbursementPayable || ! $commissionPayable || ! $bonusPayable) {
                        $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                        if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                            foreach ($payAblesList['items'] as $payAblesValue) {
                                $this->delete_payable($payAblesValue['id'], $val->user_id);
                            }
                        }
                        $status = 'ERROR';
                    }
                }
            }

            Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => implode(',', $externalId), 'everee_message' => implode(',', $errorMessage)]);
            if ($val->is_onetime_payment != 1) {
                $this->createFinalizeDataForMail([
                    'user_id' => $val->user_id,
                    'payroll_id' => $val->id,
                    'net_amount' => $val->net_pay,
                    'pay_period_from' => $val->pay_period_from,
                    'pay_period_to' => $val->pay_period_to,
                    'message' => implode(',', $errorMessage),
                    'status' => $status,
                    'type' => 'Finalize 1099',
                ]);
            }
        }

        if ($this->final) {
            $allUsersDetails = $this->generatePdfAndSendMail($startDate, $endDate);
            $frequencyType = $this->frequencyTypeId;

            if (count($allUsersDetails) != 0) {
                $array = [];
                $array['email'] = $this->adminMail;
                $array['subject'] = 'PayRoll Processes Info.';
                $array['template'] = view('mail.payroll_prossed', compact('allUsersDetails', 'startDate', 'endDate', 'frequencyType'))->render();
                if (config('app.domain_name') != 'aveyo' && config('app.domain_name') != 'aveyo2') {
                    $this->sendEmailNotification($array);
                }
            }
        }
    }

    protected function createFinalizeDataForMail($data)
    {
        TempPayrollFinalizeExecuteDetail::updateOrCreate(['payroll_id' => $data['payroll_id']], $data);
    }

    protected function generatePdfAndSendMail($startDate, $endDate)
    {
        // Checkinng status
        $emailSettings = SequiDocsEmailSettings::where('id', 3)->where('is_active', 1)->first();

        $adminMailDetail = [];
        $tempFinalizeRecords = TempPayrollFinalizeExecuteDetail::with('user')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'type' => 'Finalize 1099'])->get();
        foreach ($tempFinalizeRecords as $tempFinalizeRecord) {
            $userId = $tempFinalizeRecord->user_id;
            $payStub = [
                'net_pay' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('net_pay'),
                'pay_frequency' => $this->frequencyTypeId,
            ];

            $employee = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'last_name')->first();
            $earnings = [
                'commission' => [
                    'period_total' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('commission'),
                ],
                'overrides' => [
                    'period_total' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('override'),
                ],
                'reconciliation' => [
                    'period_total' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('reconciliation'),
                ],
            ];

            $deduction = [
                'standard_deduction' => [
                    'period_total' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('deduction'),
                ],
            ];

            $miscellaneous = [
                'adjustment' => [
                    'period_total' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('adjustment'),
                ],
                'reimbursement' => [
                    'period_total' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('reimbursement'),
                ],
            ];

            $customPayment = CustomField::where(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0'])->sum('value');
            $newData = [
                'pay_stub' => $payStub,
                'employee' => $employee,
                'earnings' => $earnings,
                'deduction' => $deduction,
                'miscellaneous' => $miscellaneous,
                'custom_payment' => $customPayment,
            ];

            if (! empty($emailSettings)) {
                $array = [];
                $array['email'] = $tempFinalizeRecord?->user?->email;
                $array['subject'] = 'Finalize PayRoll info';
                $array['template'] = view('mail.payroll_finalized', compact('newData', 'startDate', 'endDate'))->render();
                $this->sendEmailNotification($array);
            }

            if ($tempFinalizeRecord->status == 'ERROR') {
                $adminMailDetail['error'][] = [
                    'name' => $tempFinalizeRecord?->user?->first_name.' '.$tempFinalizeRecord?->user?->last_name,
                    'remark' => $tempFinalizeRecord?->message,
                    'net_pay' => $tempFinalizeRecord?->net_amount,
                ];
            } else {
                $adminMailDetail['success'][] = [
                    'name' => $tempFinalizeRecord?->user?->first_name.' '.$tempFinalizeRecord?->user?->last_name,
                    'remark' => null,
                    'net_pay' => $tempFinalizeRecord?->net_amount,
                ];
            }
        }

        return $adminMailDetail;
    }

    public function failed(\Throwable $e)
    {
        $error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job' => $this,
        ];
        \Illuminate\Support\Facades\Log::error('Failed to finalize payroll job', $error);
    }
}
