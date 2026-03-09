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
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Models\UserSchedule;
use App\Models\UserWagesHistory;
use App\Traits\EmailNotificationTrait;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class finalizeW2PayrollJob implements ShouldQueue
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
        $commissionPayable = $reimbursementPayable = $w2Payable = $bonusPayable = true;
        $userWagesHistory = UserWagesHistory::where(['user_id' => $val->user_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
        $unitRate = isset($userWagesHistory->pay_type) ? $userWagesHistory->pay_type : null;
        $payRate = isset($userWagesHistory->pay_rate) ? $userWagesHistory->pay_rate : '0';
        $workerId = isset($val->usersdata->everee_workerId) ? $val->usersdata->everee_workerId : null;
        $externalWorkerId = isset($val->usersdata->employee_id) ? $val->usersdata->employee_id : null;
        $bonusAmounts = 0;
        $externalId = [];
        $errorMessage = [];
        $domainName = config('app.domain_name');
        if ($val->is_next_payroll == 1) {
            Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => null, 'everee_message' => null]);
        } else {
            if ($workerId && $externalWorkerId) {
                if (Crms::where(['id' => 3, 'status' => 1])->first()) {
                    if ($val->is_mark_paid != 1 && $val->is_onetime_payment != 1 && $val->net_pay > 0) {
                        if ($unitRate == 'Hourly' || $unitRate == 'Salary') {
                            $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                            if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                                foreach ($payAblesList['items'] as $payAbleValue) {
                                    $this->delete_payable($payAbleValue['id'], $val->user_id);
                                }
                            }

                            if ($val->reimbursement > 0) {
                                $rExternalId = 'R-'.$externalWorkerId.'-'.$val->id;
                                $reimbursement = $val->reimbursement;
                                $earningType = 'REIMBURSEMENT';

                                $requestData = [
                                    'earningAmount' => [
                                        'amount' => round($reimbursement, 2),
                                        'currency' => 'USD',
                                    ],
                                    'externalId' => $rExternalId,
                                    'externalWorkerId' => $externalWorkerId,
                                    'type' => 'payroll',
                                    'label' => 'payroll',
                                    'verified' => true,
                                    'payableModel' => 'PRE_CALCULATED',
                                    'earningType' => $earningType,
                                    'earningTimestamp' => strtotime('-5 days', strtotime($endDate)),
                                ];

                                $checkPayroll = Payroll::where('id', $val->id)->first();
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
                                    $errorMessage[] = 'The net pay amount being sent to Everee is '.$val->net_pay.', while the net pay in payroll is currently '.$checkPayroll->net_pay.'.';
                                }
                            }

                            if ($val->net_pay > 0) {
                                $rExternalId = 'B-'.$externalWorkerId.'-'.$val->id;

                                // Get adjustment amounts for types 3 (bonus) and 6 (incentive) from approvals_and_requests table
                                $bonusAmounts = ApprovalsAndRequest::where('payroll_id', $val->id)
                                    ->whereIn('adjustment_type_id', [3, 6])
                                    ->where('status', 'Accept')
                                    ->sum('amount');

                                $earningType = 'BONUS';

                                $requestData = [
                                    'earningAmount' => [
                                        'amount' => round($bonusAmounts, 2),
                                        'currency' => 'USD',
                                    ],
                                    'externalId' => $rExternalId,
                                    'externalWorkerId' => $externalWorkerId,
                                    'type' => 'payroll',
                                    'label' => 'payroll',
                                    'verified' => true,
                                    'payableModel' => 'PRE_CALCULATED',
                                    'earningType' => $earningType,
                                    'earningTimestamp' => strtotime('-5 days', strtotime($endDate)),
                                ];

                                $checkPayroll = Payroll::where('id', $val->id)->first();
                                if ($bonusAmounts > 0) {
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
                                        $errorMessage[] = 'The net pay amount being sent to Everee is '.$val->net_pay.', while the net pay in payroll is currently '.$checkPayroll->net_pay.'.';
                                    }
                                }
                            }

                            if ($val->net_pay > 0) {
                                $reimbursement = ($val->reimbursement > 0) ? $val->reimbursement : 0;
                                $hourlySalary = ($val->hourly_salary > 0) ? $val->hourly_salary : 0;
                                $cExternalId = 'C-'.$externalWorkerId.'-'.$val->id;
                                $earningType = 'COMMISSION';
                                if ($unitRate == 'Hourly') {
                                    $overTimeAmount = ($val->overtime > 0) ? $val->overtime : 0;
                                    $netPay = ($val->net_pay - $reimbursement - $hourlySalary - $overTimeAmount - $bonusAmounts);
                                } elseif ($unitRate == 'Salary') {
                                    $netPay = ($val->net_pay - $reimbursement - $hourlySalary - $bonusAmounts);
                                }

                                if ($netPay > 0) {
                                    $requestData = [
                                        'earningAmount' => [
                                            'amount' => round($netPay, 2),
                                            'currency' => 'USD',
                                        ],
                                        'externalId' => $cExternalId,
                                        'externalWorkerId' => $externalWorkerId,
                                        'type' => 'payroll',
                                        'label' => 'payroll',
                                        'verified' => true,
                                        'payableModel' => 'PRE_CALCULATED',
                                        'earningType' => $earningType,
                                        'earningTimestamp' => strtotime('-5 days', strtotime($endDate)),
                                    ];

                                    $checkPayroll = Payroll::where('id', $val->id)->first();
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
                                        $errorMessage[] = 'The net pay amount being sent to Everee is '.$val->net_pay.', while the net pay in payroll is currently '.$checkPayroll->net_pay.'.';
                                    }
                                }
                            }

                            if ($unitRate == 'Hourly') {
                                $this->getUserSchedules($startDate, $endDate, $userId);
                            } elseif ($unitRate == 'Salary') {
                                if ($val->hourly_salary > 0) {
                                    $sExternalId = 'S-'.$externalWorkerId.'-'.$val->id;
                                    $hourlySalary = $val->hourly_salary;
                                    $earningType = 'REGULAR_SALARY';
                                    $requestData = [
                                        'earningAmount' => [
                                            'amount' => round($hourlySalary, 2),
                                            'currency' => 'USD',
                                        ],
                                        'unitRate' => [
                                            'amount' => $payRate,
                                            'currency' => 'USD',
                                        ],
                                        'unitCount' => '40.0',
                                        'externalId' => $sExternalId,
                                        'externalWorkerId' => $externalWorkerId,
                                        'type' => 'payroll',
                                        'label' => 'payroll',
                                        'verified' => true,
                                        'payableModel' => 'PRE_CALCULATED',
                                        'earningType' => $earningType,
                                        'earningTimestamp' => strtotime('-5 days', strtotime($endDate)),
                                    ];

                                    $checkPayroll = Payroll::where('id', $val->id)->first();
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
                                        $errorMessage[] = 'The net pay amount being sent to Everee is '.$val->net_pay.', while the net pay in payroll is currently '.$checkPayroll->net_pay.'.';
                                    }
                                }
                            }

                            if (! $reimbursementPayable || ! $commissionPayable || ! $w2Payable || ! $bonusPayable) {
                                $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                                if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                                    foreach ($payAblesList['items'] as $payAblesValue) {
                                        $this->delete_payable($payAblesValue['id'], $val->user_id);
                                    }
                                }
                                Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_external_id' => implode(',', $externalId), 'everee_message' => implode(',', $errorMessage)]);
                                if ($val->is_onetime_payment != 1) {
                                    $this->createFinalizeDataForMail([
                                        'user_id' => $val->user_id,
                                        'payroll_id' => $val->id,
                                        'net_amount' => 0,
                                        'pay_period_from' => $val->pay_period_from,
                                        'pay_period_to' => $val->pay_period_to,
                                        'status' => 'ERROR',
                                        'message' => implode(',', $errorMessage),
                                        'type' => 'Finalize W2',
                                    ]);
                                }
                            } else {
                                Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => implode(',', $externalId), 'everee_message' => null]);
                                if ($val->is_onetime_payment != 1) {
                                    $this->createFinalizeDataForMail([
                                        'user_id' => $val->user_id,
                                        'payroll_id' => $val->id,
                                        'net_amount' => $val->net_pay,
                                        'pay_period_from' => $val->pay_period_from,
                                        'pay_period_to' => $val->pay_period_to,
                                        'message' => null,
                                        'status' => 'SUCCESS',
                                        'type' => 'Finalize W2',
                                    ]);
                                }
                            }
                        }
                    } else {
                        Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => null, 'everee_message' => null]);
                        if ($val->is_onetime_payment != 1) {
                            $this->createFinalizeDataForMail([
                                'user_id' => $val->user_id,
                                'payroll_id' => $val->id,
                                'net_amount' => $val->net_pay,
                                'pay_period_from' => $val->pay_period_from,
                                'pay_period_to' => $val->pay_period_to,
                                'message' => null,
                                'status' => 'SUCCESS',
                                'type' => 'Finalize W2',
                            ]);
                        }
                    }
                } else {
                    Payroll::where('id', $val->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => null, 'everee_message' => null]);
                    if ($val->is_onetime_payment != 1) {
                        $this->createFinalizeDataForMail([
                            'user_id' => $val->user_id,
                            'payroll_id' => $val->id,
                            'net_amount' => $val->net_pay,
                            'pay_period_from' => $val->pay_period_from,
                            'pay_period_to' => $val->pay_period_to,
                            'message' => null,
                            'status' => 'SUCCESS',
                            'type' => 'Finalize W2',
                        ]);
                    }
                }
            } else {
                Payroll::where('id', $val->id)->update(['status' => 1, 'finalize_status' => 3, 'everee_external_id' => null, 'everee_message' => 'Employee not found on Everee.']);
                if ($val->is_onetime_payment != 1) {
                    $this->createFinalizeDataForMail([
                        'user_id' => $val->user_id,
                        'payroll_id' => $val->id,
                        'net_amount' => 0,
                        'pay_period_from' => $val->pay_period_from,
                        'pay_period_to' => $val->pay_period_to,
                        'status' => 'ERROR',
                        'message' => 'Employee not found on Everee.',
                        'type' => 'Finalize W2',
                    ]);
                }
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
                $this->sendEmailNotification($array);
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
        $tempFinalizeRecords = TempPayrollFinalizeExecuteDetail::with('user')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'type' => 'Finalize W2'])->get();
        foreach ($tempFinalizeRecords as $tempFinalizeRecord) {
            $userId = $tempFinalizeRecord->user_id;
            $payStub = [
                'net_pay' => Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('net_pay'),
                'pay_frequency' => $this->frequencyTypeId,
            ];

            $employee = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'last_name')->first();
            $salaryTotal = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('hourly_salary');
            $overtimeTotal = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '2'])->sum('overtime');
            $newData['earnings']['wages']['period_total'] = ($salaryTotal + $overtimeTotal);
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
                'wages' => [
                    'period_total' => ($salaryTotal + $overtimeTotal),
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

    protected function getUserSchedules($startDate, $endDate, $userId)
    {
        $responses = [];
        if (! empty($startDate) && ! empty($endDate)) {
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
                $user_checkin = null;
                $user_checkout = null;

                if (! empty($user_attendence)) {
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
                        $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                        $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                        $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : 0;
                        $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : 0;

                        $clockIn = Carbon::parse($user_checkin);
                        if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
                            $newClockOut = Carbon::parse($user_checkout);
                            $clockOut = $newClockOut->addHours($check_pto->pto_hours_perday);
                        } else {
                            $clockOut = isset($get_request) ? $get_request->clock_out : null;
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
                        $payload['workerId'] = ! empty($findUser->everee_workerId) ? $findUser->everee_workerId : null;
                        $payload['externalWorkerId'] = ! empty($findUser->employee_id) ? $findUser->employee_id : null;
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
                        $payload['workerId'] = ! empty($findUser->everee_workerId) ? $findUser->everee_workerId : null;
                        $payload['externalWorkerId'] = ! empty($findUser->employee_id) ? $findUser->employee_id : null;

                        if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
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
                    if (! empty($req_approvals_data)) {
                        $check_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                            ->where('start_date', '<=', $schedule_from_date)
                            ->where('end_date', '>=', $schedule_from_date)
                            ->where('adjustment_type_id', 8)
                            ->where('status', 'Approved')
                            ->first();
                        $user_checkin = isset($req_approvals_data) ? $req_approvals_data->clock_in : null;
                        $user_checkout = isset($req_approvals_data) ? $req_approvals_data->clock_out : null;
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
                        $payload['workerId'] = ! empty($findUser->everee_workerId) ? $findUser->everee_workerId : null;
                        $payload['externalWorkerId'] = ! empty($findUser->employee_id) ? $findUser->employee_id : null;

                        if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
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
                        if (! empty($check_pto)) {
                            $user_checkin = $schedule_from_date.' 08:00:00';
                            $clockIn = Carbon::parse($user_checkin);
                            if (! empty($check_pto) && isset($check_pto->pto_hours_perday) && ! is_null($check_pto->pto_hours_perday)) {
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
                            $payload['workerId'] = ! empty($findUser->everee_workerId) ? $findUser->everee_workerId : null;
                            $payload['externalWorkerId'] = ! empty($findUser->employee_id) ? $findUser->employee_id : null;
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
            'job' => $this,
        ];
        \Illuminate\Support\Facades\Log::error('Failed to finalize W2 payroll job', $error);
    }
}
