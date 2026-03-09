<?php

namespace App\Jobs\Payroll;

use Carbon\Carbon;
use App\Models\Crms;
use App\Models\User;
use App\Models\Payroll;
use App\Models\CustomField;
use App\Models\UserSchedule;
use Illuminate\Bus\Queueable;
use App\Models\UserAttendance;
use App\Core\Traits\EvereeTrait;
use App\Models\UserWagesHistory;
use Illuminate\Support\Facades\DB;
use App\Models\ApprovalsAndRequest;
use Illuminate\Support\Facades\Log;
use App\Models\UserAttendanceDetail;
use App\Traits\EmailNotificationTrait;
use Illuminate\Queue\SerializesModels;
use App\Models\SequiDocsEmailSettings;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\TempPayrollFinalizeExecuteDetail;
use Illuminate\Support\Facades\Cache;

class FinalizeW2PayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EvereeTrait, EmailNotificationTrait;

    public $tries = 3;
    public $timeout = 120;
    public $data, $startDate, $endDate, $adminMail, $auth, $frequencyTypeId;
    public function __construct($data, $startDate, $endDate, $auth, $frequencyTypeId)
    {
        $this->onQueue('payroll');
        $this->data = $data;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->adminMail = $auth->email;
        $this->frequencyTypeId = $frequencyTypeId;
        $this->auth = $auth;
    }

    public function handle()
    {
        $payroll = $this->data;
        $userId = $payroll->user_id;
        $endDate = $this->endDate;
        $startDate = $this->startDate;
        $actualNetPay = $payroll->net_pay;
        $commissionPayable = $reimbursementPayable = $w2Payable = $bonusPayable = true;
        $userWagesHistory = UserWagesHistory::where(['user_id' => $payroll->user_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
        $unitRate = isset($userWagesHistory->pay_type) ? $userWagesHistory->pay_type : NULL;
        $payRate = isset($userWagesHistory->pay_rate) ? $userWagesHistory->pay_rate : '0';
        $workerId = isset($payroll->usersdata->everee_workerId) ? $payroll->usersdata->everee_workerId : NULL;
        $externalWorkerId = isset($payroll->usersdata->employee_id) ? $payroll->usersdata->employee_id : NULL;
        $bonusAmounts = 0;
        $externalId = [];
        $errorMessage = [];
        $domainName = config('app.domain_name');

        if ($payroll->is_next_payroll == 1 || $payroll->usersdata?->stop_payroll == 1) {
            Payroll::where('id', $payroll->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => NULL, 'everee_message' => NULL]);
        } else {
            if ($workerId && $externalWorkerId) {
                if (Crms::where(['id' => 3, 'status' => 1])->first()) {
                    if ($payroll->is_mark_paid != 1 && $payroll->is_onetime_payment != 1 && $payroll->net_pay > 0) {
                        if ($unitRate == 'Hourly' || $unitRate == 'Salary') {
                            $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                            if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                                foreach ($payAblesList['items'] as $payAbleValue) {
                                    $this->delete_payable($payAbleValue['id'], $payroll->user_id);
                                }
                            }

                            if ($payroll->reimbursement > 0) {
                                $rExternalId = 'R-' . $externalWorkerId . "-" . $payroll->id;
                                $reimbursement = $payroll->reimbursement;
                                $earningType = "REIMBURSEMENT";

                                $requestData = [
                                    'earningAmount' => [
                                        'amount' => round($reimbursement, 2),
                                        'currency' => 'USD'
                                    ],
                                    'externalId' => $rExternalId,
                                    'externalWorkerId' => $externalWorkerId,
                                    'type' => 'payroll',
                                    'label' => 'payroll',
                                    'verified' => true,
                                    'payableModel' => 'PRE_CALCULATED',
                                    'earningType' => $earningType,
                                    'earningTimestamp' => strtotime('-5 days', strtotime($endDate))
                                ];

                                $checkPayroll = Payroll::where('id', $payroll->id)->first();
                                if ($checkPayroll->net_pay == $actualNetPay) {
                                    $rUntracked = $this->add_payable_emp($requestData, $userId);
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
                                    $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                                }
                            }

                            $netPay = $payroll->net_pay - $payroll->reimbursement;
                            if ($netPay > 0) {
                                $bonusAmounts = ApprovalsAndRequest::where('payroll_id', $payroll->id)
                                    ->whereIn('adjustment_type_id', [3, 6])
                                    ->where('status', 'Accept')
                                    ->sum('amount') ?? 0;

                                $hourlySalary = ($payroll->hourly_salary > 0) ? $payroll->hourly_salary : 0;
                                $bonusExcludedNetPay = $netPay - $bonusAmounts;
                                if ($unitRate == 'Hourly') {
                                    $overTimeAmount = ($payroll->overtime > 0) ? $payroll->overtime : 0;
                                    $bonusExcludedNetPay = ($bonusExcludedNetPay - $hourlySalary - $overTimeAmount);
                                } else if ($unitRate == 'Salary') {
                                    $bonusExcludedNetPay = ($bonusExcludedNetPay - $hourlySalary);
                                }
                                if ($bonusExcludedNetPay > 0) {
                                    $cExternalId = 'C-' . $externalWorkerId . "-" . $payroll->id;
                                    $earningType = "COMMISSION";

                                    if ($bonusExcludedNetPay > 0) {
                                        $requestData = [
                                            'earningAmount' => [
                                                'amount' => round($bonusExcludedNetPay, 2),
                                                'currency' => 'USD'
                                            ],
                                            'externalId' => $cExternalId,
                                            'externalWorkerId' => $externalWorkerId,
                                            'type' => 'payroll',
                                            'label' => 'payroll',
                                            'verified' => true,
                                            'payableModel' => 'PRE_CALCULATED',
                                            'earningType' => $earningType,
                                            'earningTimestamp' => strtotime('-5 days', strtotime($endDate))
                                        ];

                                        $checkPayroll = Payroll::where('id', $payroll->id)->first();
                                        if ($checkPayroll->net_pay == $actualNetPay) {
                                            $cUntracked = $this->add_payable_emp($requestData, $userId);
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
                                            $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                                        }
                                    }

                                    if ($bonusAmounts > 0) {
                                        $rExternalId = 'B-' . $externalWorkerId . "-" . $payroll->id;
                                        $bonusAmount = $bonusExcludedNetPay + $bonusAmounts;
                                        if ($bonusAmount > 0) {
                                            $earningType = "BONUS";
                                            $requestData = [
                                                'earningAmount' => [
                                                    'amount' => round($bonusAmount, 2),
                                                    'currency' => 'USD'
                                                ],
                                                'externalId' => $rExternalId,
                                                'externalWorkerId' => $externalWorkerId,
                                                'type' => 'payroll',
                                                'label' => 'payroll',
                                                'verified' => true,
                                                'payableModel' => 'PRE_CALCULATED',
                                                'earningType' => $earningType,
                                                'earningTimestamp' => strtotime('-5 days', strtotime($endDate))
                                            ];

                                            $checkPayroll = Payroll::where('id', $payroll->id)->first();
                                            if ($bonusAmount > 0) {
                                                if ($checkPayroll->net_pay == $actualNetPay) {
                                                    $rUntracked = $this->add_payable_emp($requestData, $userId);
                                                    $bonusPayable = false;
                                                    if ((isset($rUntracked['success']['status']) && $rUntracked['success']['status'] == true)) {
                                                        $externalId[] = $rExternalId;
                                                        $bonusPayable = true;
                                                    } else {
                                                        if (isset($rUntracked['fail']['everee_response']['errorMessage'])) {
                                                            $errorMessage[] = $rUntracked['fail']['everee_response']['errorMessage'];
                                                        }
                                                    }
                                                } else {
                                                    $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $rExternalId = 'B-' . $externalWorkerId . "-" . $payroll->id;
                                    $bonusAmount = $bonusExcludedNetPay + $bonusAmounts;
                                    if ($bonusAmount > 0) {
                                        $earningType = "BONUS";
                                        $requestData = [
                                            'earningAmount' => [
                                                'amount' => round($bonusAmount, 2),
                                                'currency' => 'USD'
                                            ],
                                            'externalId' => $rExternalId,
                                            'externalWorkerId' => $externalWorkerId,
                                            'type' => 'payroll',
                                            'label' => 'payroll',
                                            'verified' => true,
                                            'payableModel' => 'PRE_CALCULATED',
                                            'earningType' => $earningType,
                                            'earningTimestamp' => strtotime('-5 days', strtotime($endDate))
                                        ];

                                        $checkPayroll = Payroll::where('id', $payroll->id)->first();
                                        if ($bonusAmount > 0) {
                                            if ($checkPayroll->net_pay == $actualNetPay) {
                                                $rUntracked = $this->add_payable_emp($requestData, $userId);
                                                $bonusPayable = false;
                                                if ((isset($rUntracked['success']['status']) && $rUntracked['success']['status'] == true)) {
                                                    $externalId[] = $rExternalId;
                                                    $bonusPayable = true;
                                                } else {
                                                    if (isset($rUntracked['fail']['everee_response']['errorMessage'])) {
                                                        $errorMessage[] = $rUntracked['fail']['everee_response']['errorMessage'];
                                                    }
                                                }
                                            } else {
                                                $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                                            }
                                        }
                                    }
                                }
                            }

                            if ($unitRate == 'Hourly') {
                                $this->getUserSchedules($startDate, $endDate, $userId);
                            } else if ($unitRate == 'Salary') {
                                if ($payroll->hourly_salary > 0) {
                                    $sExternalId = 'S-' . $externalWorkerId . "-" . $payroll->id;
                                    $hourlySalary = $payroll->hourly_salary;
                                    $earningType = "REGULAR_SALARY";
                                    $requestData = [
                                        'earningAmount' => [
                                            'amount' => round($hourlySalary, 2),
                                            'currency' => 'USD'
                                        ],
                                        'unitRate' => [
                                            'amount' => $payRate,
                                            'currency' => 'USD'
                                        ],
                                        'unitCount' => '40.0',
                                        'externalId' => $sExternalId,
                                        'externalWorkerId' => $externalWorkerId,
                                        'type' => 'payroll',
                                        'label' => 'payroll',
                                        'verified' => true,
                                        'payableModel' => 'PRE_CALCULATED',
                                        'earningType' => $earningType,
                                        'earningTimestamp' => strtotime('-5 days', strtotime($endDate))
                                    ];

                                    $checkPayroll = Payroll::where('id', $payroll->id)->first();
                                    if ($checkPayroll->net_pay == $actualNetPay) {
                                        $w2Untracked = $this->add_payable_emp($requestData, $userId);
                                        $w2Payable = false;
                                        if ((isset($w2Untracked['success']['status']) && $w2Untracked['success']['status'] == true)) {
                                            $w2Payable = true;
                                            $externalId[] = $sExternalId;
                                        } else {
                                            if (isset($w2Untracked['fail']['everee_response']['errorMessage'])) {
                                                $errorMessage[] = $w2Untracked['fail']['everee_response']['errorMessage'];
                                            }
                                        }
                                    } else {
                                        $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                                    }
                                }
                            }

                            if (!$reimbursementPayable || !$commissionPayable || !$w2Payable || !$bonusPayable) {
                                $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                                if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                                    foreach ($payAblesList['items'] as $payAblesValue) {
                                        $this->delete_payable($payAblesValue['id'], $payroll->user_id);
                                    }
                                }
                                Payroll::where('id', $payroll->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_external_id' => implode(',', $externalId), 'everee_message' => implode(',', $errorMessage)]);
                                if ($payroll->is_onetime_payment != 1) {
                                    $this->createFinalizeDataForMail([
                                        'user_id' => $payroll->user_id,
                                        'payroll_id' => $payroll->id,
                                        'net_amount' => 0,
                                        'pay_period_from' => $payroll->pay_period_from,
                                        'pay_period_to' => $payroll->pay_period_to,
                                        'pay_frequency' => $payroll->pay_frequency,
                                        'worker_type' => $payroll->worker_type,
                                        'status' => 'ERROR',
                                        'message' => implode(',', $errorMessage),
                                        'type' => 'Finalize W2'
                                    ]);
                                }
                            } else {
                                Payroll::where('id', $payroll->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => implode(',', $externalId), 'everee_message' => NULL]);
                                if ($payroll->is_onetime_payment != 1) {
                                    $this->createFinalizeDataForMail([
                                        'user_id' => $payroll->user_id,
                                        'payroll_id' => $payroll->id,
                                        'net_amount' => $payroll->net_pay,
                                        'pay_period_from' => $payroll->pay_period_from,
                                        'pay_period_to' => $payroll->pay_period_to,
                                        'pay_frequency' => $payroll->pay_frequency,
                                        'worker_type' => $payroll->worker_type,
                                        'message' => NULL,
                                        'status' => 'SUCCESS',
                                        'type' => 'Finalize W2'
                                    ]);
                                }
                            }
                        }
                    } else {
                        Payroll::where('id', $payroll->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => NULL, 'everee_message' => NULL]);
                        if ($payroll->is_onetime_payment != 1) {
                            $this->createFinalizeDataForMail([
                                'user_id' => $payroll->user_id,
                                'payroll_id' => $payroll->id,
                                'net_amount' => $payroll->net_pay,
                                'pay_period_from' => $payroll->pay_period_from,
                                'pay_period_to' => $payroll->pay_period_to,
                                'pay_frequency' => $payroll->pay_frequency,
                                'worker_type' => $payroll->worker_type,
                                'message' => NULL,
                                'status' => 'SUCCESS',
                                'type' => 'Finalize W2'
                            ]);
                        }
                    }
                } else {
                    Payroll::where('id', $payroll->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => NULL, 'everee_message' => NULL]);
                    if ($payroll->is_onetime_payment != 1) {
                        $this->createFinalizeDataForMail([
                            'user_id' => $payroll->user_id,
                            'payroll_id' => $payroll->id,
                            'net_amount' => $payroll->net_pay,
                            'pay_period_from' => $payroll->pay_period_from,
                            'pay_period_to' => $payroll->pay_period_to,
                            'pay_frequency' => $payroll->pay_frequency,
                            'worker_type' => $payroll->worker_type,
                            'message' => NULL,
                            'status' => 'SUCCESS',
                            'type' => 'Finalize W2'
                        ]);
                    }
                }
            } else {
                Payroll::where('id', $payroll->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_external_id' => NULL, 'everee_message' => 'Employee not found on Everee.']);
                if ($payroll->is_onetime_payment != 1) {
                    $this->createFinalizeDataForMail([
                        'user_id' => $payroll->user_id,
                        'payroll_id' => $payroll->id,
                        'net_amount' => 0,
                        'pay_period_from' => $payroll->pay_period_from,
                        'pay_period_to' => $payroll->pay_period_to,
                        'pay_frequency' => $payroll->pay_frequency,
                        'worker_type' => $payroll->worker_type,
                        'status' => 'ERROR',
                        'message' => 'Employee not found on Everee.',
                        'type' => 'Finalize W2'
                    ]);
                }
            }
        }

        // Check if all payrolls for this pay period are finalized
        // A payroll is finalized when status=1 and finalize_status=2 (SUCCESS)
        // We check if there are any payrolls that are still pending (finalize_status in [0,1])
        $hasPendingFinalization = Payroll::applyFrequencyFilter(
            [
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'pay_frequency' => $payroll->pay_frequency,
                'worker_type' => $payroll->worker_type
            ],
            ['status' => 1, 'is_onetime_payment' => 0]
        )->whereIn('finalize_status', [0, 1])->exists();

        // Only generate PDFs if all payrolls are finalized and this job acquires the lock
        if (!$hasPendingFinalization) {
            $lockKey = 'payroll_finalize_w2_pdf_' . $payroll->pay_period_from . '_' . $payroll->pay_period_to . '_' . $payroll->pay_frequency . '_' . $payroll->worker_type;

            // Use cache lock to ensure only one job generates PDFs (prevents race conditions)
            $lock = Cache::lock($lockKey, 300); // 5 minute lock

            if ($lock->get()) {
                try {
                    // Double-check: verify no pending finalizations exist
                    $hasPendingFinalization = Payroll::applyFrequencyFilter(
                        [
                            'pay_period_from' => $payroll->pay_period_from,
                            'pay_period_to' => $payroll->pay_period_to,
                            'pay_frequency' => $payroll->pay_frequency,
                            'worker_type' => $payroll->worker_type
                        ],
                        ['status' => 1, 'is_onetime_payment' => 0]
                    )->whereIn('finalize_status', [0, 1])->exists();

                    if (!$hasPendingFinalization) {
                        $allUsersDetails = $this->generatePdfAndSendMail($startDate, $endDate);
                        $frequencyType = $this->frequencyTypeId;

                        if (sizeOf($allUsersDetails) != 0) {
                            $array = [];
                            $array['email'] = $this->adminMail;
                            $array['subject'] = 'PayRoll Processes Info.';
                            $array['template'] = view('mail.payroll_prossed', compact('allUsersDetails', 'startDate', 'endDate', 'frequencyType'))->render();
                            $this->sendEmailNotification($array);

                            $newArray = $array;
                            $newArray['email'] = 'jay@sequifi.com';
                            $array['subject'] = 'W2 PayRoll Processes Info.';
                            $this->sendEmailNotification($newArray, true);
                        }
                    }
                } finally {
                    $lock->release();
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
        $adminMailDetail = [];
        $param = [
            "pay_frequency" => $this->frequencyTypeId,
            "worker_type" => "w2",
            "pay_period_from" => $startDate,
            "pay_period_to" => $endDate
        ];
        $emailSettings = SequiDocsEmailSettings::where('id', 3)->where('is_active', 1)->first();
        $tempFinalizeRecords = TempPayrollFinalizeExecuteDetail::with('user')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'type' => 'Finalize W2'])->get();
        foreach ($tempFinalizeRecords as $tempFinalizeRecord) {
            $userId = $tempFinalizeRecord->user_id;

            $payrollSums = Payroll::selectRaw('
                SUM(net_pay) as total_net_pay,
                SUM(hourly_salary) as total_hourly_salary,
                SUM(overtime) as total_overtime,
                SUM(commission) as total_commission,
                SUM(`override`) as total_override,
                SUM(reconciliation) as total_reconciliation,
                SUM(deduction) as total_deduction,
                SUM(adjustment) as total_adjustment,
                SUM(reimbursement) as total_reimbursement
            ')->applyFrequencyFilter($param, ['user_id' => $userId, 'status' => '2'])->first();

            $payStub = [
                'net_pay' => $payrollSums->total_net_pay ?? 0,
                'pay_frequency' => $this->frequencyTypeId
            ];

            $employee = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'last_name')->first();
            $salaryTotal = $payrollSums->total_hourly_salary ?? 0;
            $overtimeTotal = $payrollSums->total_overtime ?? 0;

            $earnings = [
                'commission' => [
                    'period_total' => $payrollSums->total_commission ?? 0
                ],
                'overrides' => [
                    'period_total' => $payrollSums->total_override ?? 0
                ],
                'reconciliation' => [
                    'period_total' => $payrollSums->total_reconciliation ?? 0
                ],
                'wages' => [
                    'period_total' => ($salaryTotal + $overtimeTotal)
                ]
            ];

            $deduction = [
                'standard_deduction' => [
                    'period_total' => $payrollSums->total_deduction ?? 0
                ]
            ];

            $miscellaneous = [
                'adjustment' => [
                    'period_total' => $payrollSums->total_adjustment ?? 0
                ],
                'reimbursement' => [
                    'period_total' => $payrollSums->total_reimbursement ?? 0
                ]
            ];

            $customPayment = CustomField::where(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->sum('value');
            $newData = [
                'pay_stub' => $payStub,
                'employee' => $employee,
                'earnings' => $earnings,
                'deduction' => $deduction,
                'miscellaneous' => $miscellaneous,
                'custom_payment' => $customPayment
            ];

            if (!empty($emailSettings)) {
                $array = [];
                $array['email'] = $tempFinalizeRecord?->user?->email;
                $array['subject'] = 'Finalize PayRoll info';
                $array['template'] = view('mail.payroll_finalized', compact('newData', 'startDate', 'endDate'))->render();
                $this->sendEmailNotification($array);
            }
            if ($tempFinalizeRecord->status == 'ERROR') {
                $adminMailDetail['error'][] =  [
                    'name' => $tempFinalizeRecord?->user?->first_name . ' ' . $tempFinalizeRecord?->user?->last_name,
                    'remark' => $tempFinalizeRecord?->message,
                    'net_pay' => $tempFinalizeRecord?->net_amount
                ];
            } else {
                $adminMailDetail['success'][] =  [
                    'name' => $tempFinalizeRecord?->user?->first_name . ' ' . $tempFinalizeRecord?->user?->last_name,
                    'remark' => NULL,
                    'net_pay' => $tempFinalizeRecord?->net_amount
                ];
            }
        }
        return $adminMailDetail;
    }

    protected function getUserSchedules($startDate, $endDate, $userId)
    {
        $responses = [];
        if (!empty($startDate) && !empty($endDate)) {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
                'user_schedule_details.is_flexible as is_flexible_flag',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween(DB::raw('DATE(user_schedule_details.schedule_from)'), [$startDate, $endDate])
                ->where('user_schedules.user_id', $userId)
                ->orderBy('users.id')
                ->get();
            foreach ($userSchedulesData as $schedule) {
                $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
                $this->getTimeFromDateTime($schedule->schedule_from);
                $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                    ->where('date', $schedule_from_date)
                    ->first();
                $user_checkin = NULL;
                $user_checkout = NULL;

                if (!empty($user_attendence)) {
                    $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        ->whereDate('attendance_date', $schedule_from_date)
                        ->where('adjustment_id', '>', 0)
                        ->first();
                    if ($user_attendance_obj) {
                        $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                            ->where('start_date', '<=', $schedule_from_date)
                            ->where('end_date', '>=', $schedule_from_date)
                            ->where('adjustment_type_id', 8)
                            ->where('status', 'Approved')
                            ->first();
                        $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                        $user_checkin = isset($get_request) ? $get_request->clock_in : NULL;
                        $user_checkout = isset($get_request) ? $get_request->clock_out : NULL;
                        $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : 0;
                        $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : 0;

                        $clockIn = Carbon::parse($user_checkin);
                        if (!empty($check_pto) && isset($check_pto->pto_hours_perday) && !is_null($check_pto->pto_hours_perday)) {
                            $newClockOut = Carbon::parse($user_checkout);
                            $clockOut = $newClockOut->addHours($check_pto->pto_hours_perday);
                        } else {
                            $clockOut = isset($get_request) ? $get_request->clock_out : NULL;
                        }

                        $lunchStart = $clockIn->copy()->addMinutes(1); // Adding 3 hours to clock_in
                        $lunchEnd = $lunchStart->copy()->addMinutes($lunchBreak); // Adding  minutes for lunch
                        $breakStart = $lunchEnd->copy()->addMinutes(1); // Adding 6 hours to clock_in
                        $breakEnd = $lunchEnd->copy()->addMinutes($breakTime); // Adding minutes for break
                        $payload = [];
                        $findUser = User::find($schedule->user_id);
                        $payload['user_id'] = $schedule->user_id;
                        $payload['clockIn'] = $user_checkin;
                        $payload['clockOut'] = $clockOut;
                        $payload['lunch'] = $lunchStart->format('Y-m-d H:i:s');
                        $payload['lunchEnd'] = $lunchEnd->format('Y-m-d H:i:s');
                        $payload['break'] = $breakStart->format('Y-m-d H:i:s');
                        $payload['breakEnd'] = $breakEnd->format('Y-m-d H:i:s');
                        $payload['workerId'] = !empty($findUser->everee_workerId) ? $findUser->everee_workerId :  NULL;
                        $payload['externalWorkerId'] = !empty($findUser->employee_id) ? $findUser->employee_id : NULL;
                        Log::info(['1===>' => $payload]);
                        $untracked = $this->send_timesheet_data($payload);
                        $responses[] = $untracked;
                    } else {
                        $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                            ->where('start_date', '<=', $schedule_from_date)
                            ->where('end_date', '>=', $schedule_from_date)
                            ->where('adjustment_type_id', 8)
                            ->where('status', 'Approved')
                            ->first();
                        $attendance_details_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)->get()->toArray();
                        $types = array_column($attendance_details_obj, 'type');
                        $dates = array_column($attendance_details_obj, 'attendance_date');
                        $payload = [];
                        $findUser = User::find($schedule->user_id);
                        $payload['user_id'] = $schedule->user_id;
                        $payload['clockIn'] = $dates[array_search('clock in', $types)];
                        $payload['clockOut'] = $dates[array_search('clock out', $types)];
                        $payload['lunch'] = $dates[array_search('lunch', $types)];
                        $payload['lunchEnd'] = $dates[array_search('end lunch', $types)];
                        $payload['break'] = $dates[array_search('break', $types)];
                        $payload['breakEnd'] = $dates[array_search('end break', $types)];
                        $payload['workerId'] = !empty($findUser->everee_workerId) ? $findUser->everee_workerId :  NULL;
                        $payload['externalWorkerId'] = !empty($findUser->employee_id) ? $findUser->employee_id : NULL;

                        if (!empty($check_pto) && isset($check_pto->pto_hours_perday) && !is_null($check_pto->pto_hours_perday)) {
                            $ptoHours = $check_pto->pto_hours_perday;
                            $clockOutTime = Carbon::parse($dates[array_search('clock out', $types)]);
                            $newClockOutTime = $clockOutTime->addHours($ptoHours)->toDateTimeString(); // This adds the PTO hours
                            $payload['clockOut'] = $newClockOutTime;
                        }
                        Log::info(['2===>' => $payload]);
                        $untracked = $this->send_timesheet_data($payload);
                        $responses[] = $untracked;
                    }
                } else {
                    $req_approvals_data = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                        ->where('adjustment_date', '=', $schedule_from_date)
                        ->where('adjustment_type_id', 9)
                        ->where('status', 'Approved')
                        ->first();
                    if (!empty($req_approvals_data)) {
                        $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                            ->where('start_date', '<=', $schedule_from_date)
                            ->where('end_date', '>=', $schedule_from_date)
                            ->where('adjustment_type_id', 8)
                            ->where('status', 'Approved')
                            ->first();
                        $user_checkin = isset($req_approvals_data) ? $req_approvals_data->clock_in : NULL;
                        $user_checkout = isset($req_approvals_data) ? $req_approvals_data->clock_out : NULL;
                        $lunchBreak = isset($req_approvals_data->lunch_adjustment) ? $req_approvals_data->lunch_adjustment : 0;
                        $breakTime = isset($req_approvals_data->break_adjustment) ? $req_approvals_data->break_adjustment : 0;
                        $clockIn = Carbon::parse($user_checkin);
                        $clockOut = Carbon::parse($user_checkout);
                        $lunchStart = $clockIn; // Adding 3 hours to clock_in
                        $lunchEnd = $lunchStart->copy()->addMinutes($lunchBreak); // Adding  minutes for lunch

                        $breakStart = $lunchEnd; // Adding 6 hours to clock_in
                        $breakEnd = $lunchEnd->copy()->addMinutes($breakTime); // Adding minutes for break
                        $payload = [];
                        $findUser = User::find($schedule->user_id);
                        $payload['user_id'] = $schedule->user_id;
                        $payload['clockIn'] = $user_checkin;
                        $payload['clockOut'] = $user_checkout;
                        $payload['lunch'] = $lunchStart->format('Y-m-d H:i:s');
                        $payload['lunchEnd'] = $lunchEnd->format('Y-m-d H:i:s');
                        $payload['break'] = $breakStart->format('Y-m-d H:i:s');
                        $payload['breakEnd'] = $breakEnd->format('Y-m-d H:i:s');
                        $payload['workerId'] = !empty($findUser->everee_workerId) ? $findUser->everee_workerId :  NULL;
                        $payload['externalWorkerId'] = !empty($findUser->employee_id) ? $findUser->employee_id : NULL;

                        if (!empty($check_pto) && isset($check_pto->pto_hours_perday) && !is_null($check_pto->pto_hours_perday)) {
                            $ptoHours = $check_pto->pto_hours_perday;
                            $newClockOut = Carbon::parse($user_checkout);
                            $clockOut = $newClockOut->addHours($ptoHours);
                            $payload['clockOut'] = $clockOut;
                        }
                        Log::info(['3===>' => $payload]);
                        $untracked = $this->send_timesheet_data($payload);
                        $responses[] = $untracked;
                    } else {
                        $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                            ->where('start_date', '<=', $schedule_from_date)
                            ->where('end_date', '>=', $schedule_from_date)
                            ->where('adjustment_type_id', 8)
                            ->where('status', 'Approved')
                            ->first();
                        if (!empty($check_pto)) {
                            $user_checkin = $schedule_from_date . " 08:00:00";
                            $clockIn = Carbon::parse($user_checkin);
                            if (!empty($check_pto) && isset($check_pto->pto_hours_perday) && !is_null($check_pto->pto_hours_perday)) {
                                $newClockOut = Carbon::parse($user_checkin);
                                $clockOut = $newClockOut->addHours($check_pto->pto_hours_perday)->toDateTimeString();
                            } else {
                                $clockOut = $user_checkin;
                            }
                            $lunchStart = $clockIn;
                            Log::info(['lunchStart' => $lunchStart->format('Y-m-d H:i:s')]);
                            $lunchEnd = $lunchStart->copy();
                            $breakStart = $lunchEnd;
                            $breakEnd = $lunchEnd->copy();
                            Log::info(['lunchStart' => $lunchStart->format('Y-m-d H:i:s'), 'lunchEnd' => $lunchEnd->format('Y-m-d H:i:s'), 'breakStart' => $breakStart->format('Y-m-d H:i:s'), 'breakEnd' => $breakEnd->format('Y-m-d H:i:s')]);
                            $payload = [];
                            $findUser = User::find($schedule->user_id);
                            $payload['user_id'] = $schedule->user_id;
                            $payload['clockIn'] = $user_checkin;
                            $payload['clockOut'] = $clockOut;
                            $payload['lunch'] = $lunchStart->format('Y-m-d H:i:s');
                            $payload['lunchEnd'] = $lunchEnd->format('Y-m-d H:i:s');
                            $payload['break'] = $breakStart->format('Y-m-d H:i:s');
                            $payload['breakEnd'] = $breakEnd->format('Y-m-d H:i:s');
                            $payload['workerId'] = !empty($findUser->everee_workerId) ? $findUser->everee_workerId :  NULL;
                            $payload['externalWorkerId'] = !empty($findUser->employee_id) ? $findUser->employee_id : NULL;
                            Log::info(['4===>' => $payload]);
                            $untracked = $this->send_timesheet_data($payload);
                            $responses[] = $untracked;
                        }
                    }
                }
            }
        }
        return $responses;
    }

    protected function getTimeFromDateTime($datetime)
    {
        $date = Carbon::parse($datetime);
        return $date->format('H:i:s');
    }

    public function failed(\Throwable $e)
    {
        $error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job' => $this
        ];

        $data = $this->data;
        $endDate = $this->endDate;
        $startDate = $this->startDate;
        $domainName = config('app.domain_name') . ' | ';
        $failedEmail['email'] = 'jay@sequifi.com';
        $failedEmail['subject'] = 'Failed to finalize W2 payroll on ' . $domainName . ' server.';
        $failedEmail['template'] = view('mail.payroll_finalize_alert', compact('error', 'domainName', 'startDate', 'endDate', 'data'))->render();
        $this->sendEmailNotification($failedEmail, true);
    }
}
