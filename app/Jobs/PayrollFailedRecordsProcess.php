<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Crms;
use App\Models\Payroll;
use App\Models\UserSchedule;
use Illuminate\Bus\Queueable;
use App\Models\PayrollHistory;
use App\Models\UserAttendance;
use App\Core\Traits\EvereeTrait;
use App\Models\UserWagesHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\UserAttendanceDetail;
use Illuminate\Support\Facades\Cache;
use App\Traits\PushNotificationTrait;
use App\Traits\EmailNotificationTrait;
use App\Core\Traits\PayFrequencyTrait;
use Illuminate\Queue\SerializesModels;
use App\Models\ApprovalsAndRequestLock;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Http\Controllers\API\V2\Payroll\PayrollController;

class PayrollFailedRecordsProcess implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, EvereeTrait, InteractsWithQueue, PayFrequencyTrait, PushNotificationTrait, Queueable, SerializesModels;

    public $userId;
    public $tries = 3;
    public $timeout = 1200; // 20 minutes

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $crm = Crms::where(['id' => 3, 'status' => 1])->first();
        if (!$crm) {
            Log::warning("Skipping job for user {$this->userId}: CRM is not active or not set up to use Sequifi's payment services.");
            return;
        }

        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            Log::warning("Skipping job for user {$this->userId}: payroll is already being finalized.");
            return;
        }

        $lockKey = "user_repayment_lock_{$this->userId}";
        $lock = Cache::lock($lockKey, 1200);
        if (! $lock->get()) {
            Log::warning("Skipping job for user {$this->userId}: lock already taken.");
            return;
        }

        try {
            // Only process payroll records from last 30 days to avoid reprocessing old failures
            $cutoffDate = Carbon::now()->subDays(30); // Exact 30-day rolling window with time
            $failedPayrollRecords = PayrollHistory::with('usersdata')->where(['user_id' => $this->userId, 'everee_status' => 2])->where('created_at', '>=', $cutoffDate)->get();
            foreach ($failedPayrollRecords as $failedPayrollRecord) {
                if ($failedPayrollRecord->worker_type == '1099') {
                    $this->contactorProcess($failedPayrollRecord);
                } else if ($failedPayrollRecord->worker_type == 'W2' || $failedPayrollRecord->worker_type == 'w2') {
                    $this->employeeProcess($failedPayrollRecord);
                }
            }
        } finally {
            // Always release lock even if job fails
            $lock->release();
        }
    }

    protected function contactorProcess($payroll)
    {
        $externalId = [];
        $bonusAmounts = 0;
        $commissionPayable = $reimbursementPayable = $bonusPayable = true;
        $externalWorkerId = isset($payroll->usersdata->employee_id) ? $payroll->usersdata->employee_id : NULL;
        $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
        if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
            foreach ($payAblesList['items'] as $payAbleValue) {
                $this->delete_payable($payAbleValue['id'], $payroll->user_id);
            }
        }

        $workerTypeId = isset($payroll?->usersdata?->everee_workerId) ? $payroll?->usersdata?->everee_workerId : NULL;
        if ($workerTypeId && $payroll->is_mark_paid != 1 && $payroll->is_onetime_payment != 1 && $payroll->net_pay > 0) {
            if ($payroll->reimbursement > 0) {
                $rExternalId = 'R-' . $externalWorkerId . "-" . $payroll->payroll_id;
                $data = clone $payroll;
                $data->net_pay = $payroll->reimbursement;
                $rUntracked = $this->add_payable($data, $rExternalId, 'REIMBURSEMENT');
                $reimbursementPayable = false;
                if ((isset($rUntracked['success']['status']) && $rUntracked['success']['status'] == true)) {
                    $externalId[] = $rExternalId;
                    $reimbursementPayable = true;
                }
            }

            $netPay = $payroll->net_pay - $payroll->reimbursement;
            if ($netPay > 0) {
                $bonusAmounts = ApprovalsAndRequestLock::where('payroll_id', $payroll->payroll_id)
                    ->whereIn('adjustment_type_id', [3, 6])
                    ->where('status', 'Accept')
                    ->sum('amount') ?? 0;

                $bonusExcludedNetPay = $netPay - $bonusAmounts;
                if ($bonusExcludedNetPay > 0) {
                    $cExternalId = 'C-' . $externalWorkerId . "-" . $payroll->payroll_id;
                    $data = clone $payroll;
                    $data->net_pay = $bonusExcludedNetPay;
                    $cUntracked = $this->add_payable($data, $cExternalId, 'COMMISSION');
                    $commissionPayable = false;
                    if (isset($cUntracked['success']['status']) && $cUntracked['success']['status'] == true) {
                        $commissionPayable = true;
                        $externalId[] = $cExternalId;
                    }

                    if ($bonusAmounts > 0) {
                        $cExternalId = 'B-' . $externalWorkerId . "-" . $payroll->payroll_id;
                        $data = clone $payroll;
                        $data->net_pay = $bonusAmounts;

                        // Determine earning type based on payment type            
                        $cUntracked = $this->add_payable($data, $cExternalId, 'BONUS');
                        $bonusPayable = false;
                        if (isset($cUntracked['success']['status']) && $cUntracked['success']['status'] == true) {
                            $bonusPayable = true;
                            $externalId[] = $cExternalId;
                        }
                    }
                } else {
                    $bonusAmount = $bonusExcludedNetPay + $bonusAmounts;
                    if ($bonusAmount > 0) {
                        $cExternalId = 'B-' . $externalWorkerId . "-" . $payroll->payroll_id;
                        $data = clone $payroll;
                        $data->net_pay = $bonusAmount;

                        // Determine earning type based on payment type            
                        $cUntracked = $this->add_payable($data, $cExternalId, 'BONUS');
                        $bonusPayable = false;
                        if (isset($cUntracked['success']['status']) && $cUntracked['success']['status'] == true) {
                            $bonusPayable = true;
                            $externalId[] = $cExternalId;
                        }
                    }
                }
            }

            if (!$reimbursementPayable || !$commissionPayable || !$bonusPayable) {
                $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                    foreach ($payAblesList['items'] as $payAblesValue) {
                        $this->delete_payable($payAblesValue['id'], $payroll->user_id);
                    }
                }
            } else {
                $enableEVE = 1;
                $paymentRequest = $this->payable_request($payroll);
                if (!isset($paymentRequest['success']['paymentId']) && strtolower($payroll->worker_type) != 'w2') {
                    $enableEVE = 2;
                }

                $update = [
                    'everee_status' => $enableEVE,
                    'everee_payment_status' => $enableEVE,
                    'everee_external_id' => implode(',', $externalId),
                    'everee_paymentId' => isset($paymentRequest['success']['everee_payment_id']) ? $paymentRequest['success']['everee_payment_id'] : null,
                    'everee_payment_requestId' => isset($paymentRequest['success']['paymentId']) ? $paymentRequest['success']['paymentId'] : null,
                    'everee_json_response' => isset($paymentRequest) ? json_encode($paymentRequest) : null
                ];
                PayrollHistory::where(['id' => $payroll->id])->update($update);
                $payrollController = new PayrollController();
                $payrollController->payrollPayStubData($payroll->user_id, $payroll->pay_period_from, $payroll->pay_period_to, $payroll->pay_frequency, $payroll->worker_type, 0, $payroll->payroll_id);
            }
        }
    }

    protected function employeeProcess($payroll)
    {
        $externalId = [];
        $bonusAmounts = 0;
        $userId = $payroll->user_id;
        $startDate = $payroll->pay_period_from;
        $endDate = $payroll->pay_period_to;
        $commissionPayable = $reimbursementPayable = $w2Payable = $bonusPayable = true;
        $workerId = isset($payroll->usersdata->everee_workerId) ? $payroll->usersdata->everee_workerId : NULL;
        $externalWorkerId = isset($payroll->usersdata->employee_id) ? $payroll->usersdata->employee_id : NULL;

        if ($workerId && $externalWorkerId && $payroll->is_mark_paid != 1 && $payroll->is_onetime_payment != 1 && $payroll->net_pay > 0) {
            $userWagesHistory = UserWagesHistory::where(['user_id' => $payroll->user_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
            $unitRate = isset($userWagesHistory->pay_type) ? $userWagesHistory->pay_type : NULL;
            $payRate = isset($userWagesHistory->pay_rate) ? $userWagesHistory->pay_rate : '0';
            if ($unitRate == 'Hourly' || $unitRate == 'Salary') {
                $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                    foreach ($payAblesList['items'] as $payAbleValue) {
                        $this->delete_payable($payAbleValue['id'], $payroll->user_id);
                    }
                }

                if ($payroll->reimbursement > 0) {
                    $rExternalId = 'R-' . $externalWorkerId . "-" . $payroll->payroll_id;
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

                    $rUntracked = $this->add_payable_emp($requestData, $userId);
                    $reimbursementPayable = false;
                    if ((isset($rUntracked['success']['status']) && $rUntracked['success']['status'] == true)) {
                        $externalId[] = $rExternalId;
                        $reimbursementPayable = true;
                    }
                }

                $netPay = $payroll->net_pay - $payroll->reimbursement;
                if ($netPay > 0) {
                    $bonusAmounts = ApprovalsAndRequestLock::where('payroll_id', $payroll->payroll_id)
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
                        $cExternalId = 'C-' . $externalWorkerId . "-" . $payroll->payroll_id;
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

                            $cUntracked = $this->add_payable_emp($requestData, $userId);
                            $commissionPayable = false;
                            if (isset($cUntracked['success']['status']) && $cUntracked['success']['status'] == true) {
                                $commissionPayable = true;
                                $externalId[] = $cExternalId;
                            }
                        }

                        if ($bonusAmounts > 0) {
                            $rExternalId = 'B-' . $externalWorkerId . "-" . $payroll->payroll_id;
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

                                if ($bonusAmount > 0) {
                                    $rUntracked = $this->add_payable_emp($requestData, $userId);
                                    $bonusPayable = false;
                                    if ((isset($rUntracked['success']['status']) && $rUntracked['success']['status'] == true)) {
                                        $externalId[] = $rExternalId;
                                        $bonusPayable = true;
                                    }
                                }
                            }
                        }
                    } else {
                        $rExternalId = 'B-' . $externalWorkerId . "-" . $payroll->payroll_id;
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

                            if ($bonusAmount > 0) {
                                $rUntracked = $this->add_payable_emp($requestData, $userId);
                                $bonusPayable = false;
                                if ((isset($rUntracked['success']['status']) && $rUntracked['success']['status'] == true)) {
                                    $externalId[] = $rExternalId;
                                    $bonusPayable = true;
                                }
                            }
                        }
                    }
                }

                if ($unitRate == 'Hourly') {
                    try {
                        $this->getUserSchedules($startDate, $endDate, $userId);
                    } catch (\Throwable $e) {
                        Log::error("Error in getUserSchedules for user {$userId}", [
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                } else if ($unitRate == 'Salary') {
                    if ($payroll->hourly_salary > 0) {
                        $sExternalId = 'S-' . $externalWorkerId . "-" . $payroll->payroll_id;
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

                        $w2Untracked = $this->add_payable_emp($requestData, $userId);
                        $w2Payable = false;
                        if ((isset($w2Untracked['success']['status']) && $w2Untracked['success']['status'] == true)) {
                            $w2Payable = true;
                            $externalId[] = $sExternalId;
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
                } else {
                    $enableEVE = 1;
                    $paymentRequest = $this->payable_request($payroll);
                    if (!isset($paymentRequest['success']['paymentId']) && strtolower($payroll->worker_type) != 'w2') {
                        $enableEVE = 2;
                    }

                    $update = [
                        'everee_status' => $enableEVE,
                        'everee_payment_status' => $enableEVE,
                        'everee_external_id' => implode(',', $externalId),
                        'everee_paymentId' => isset($paymentRequest['success']['everee_payment_id']) ? $paymentRequest['success']['everee_payment_id'] : null,
                        'everee_payment_requestId' => isset($paymentRequest['success']['paymentId']) ? $paymentRequest['success']['paymentId'] : null,
                        'everee_json_response' => isset($paymentRequest) ? json_encode($paymentRequest) : null
                    ];
                    PayrollHistory::where(['id' => $payroll->id])->update($update);
                }
            }
        }
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
                if ($schedule->schedule_from) {
                    $this->getTimeFromDateTime($schedule->schedule_from);
                }
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
                        $check_pto = ApprovalsAndRequestLock::where('user_id', $schedule->user_id)
                            ->where('start_date', '<=', $schedule_from_date)
                            ->where('end_date', '>=', $schedule_from_date)
                            ->where('adjustment_type_id', 8)
                            ->where('status', 'Approved')
                            ->first();
                        $get_request = ApprovalsAndRequestLock::find($user_attendance_obj->adjustment_id);
                        $user_checkin = isset($get_request) ? $get_request->clock_in : NULL;
                        $user_checkout = isset($get_request) ? $get_request->clock_out : NULL;
                        $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : 0;
                        $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : 0;

                        $clockIn = Carbon::parse($user_checkin);
                        if (!empty($check_pto) && isset($check_pto->pto_hours_perday) && !is_null($check_pto->pto_hours_perday)) {
                            $newClockOut = Carbon::parse($user_checkout);
                            $clockOut = $newClockOut->addHours($check_pto->pto_hours_perday)->toDateTimeString();
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
                        $check_pto = ApprovalsAndRequestLock::where('user_id', $schedule->user_id)
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

                        // Safely get array indices with null fallback if not found
                        $clockInIndex = array_search('clock in', $types);
                        $clockOutIndex = array_search('clock out', $types);
                        $lunchIndex = array_search('lunch', $types);
                        $lunchEndIndex = array_search('end lunch', $types);
                        $breakIndex = array_search('break', $types);
                        $breakEndIndex = array_search('end break', $types);

                        $payload['clockIn'] = ($clockInIndex !== false && isset($dates[$clockInIndex])) ? $dates[$clockInIndex] : null;
                        $payload['clockOut'] = ($clockOutIndex !== false && isset($dates[$clockOutIndex])) ? $dates[$clockOutIndex] : null;
                        $payload['lunch'] = ($lunchIndex !== false && isset($dates[$lunchIndex])) ? $dates[$lunchIndex] : null;
                        $payload['lunchEnd'] = ($lunchEndIndex !== false && isset($dates[$lunchEndIndex])) ? $dates[$lunchEndIndex] : null;
                        $payload['break'] = ($breakIndex !== false && isset($dates[$breakIndex])) ? $dates[$breakIndex] : null;
                        $payload['breakEnd'] = ($breakEndIndex !== false && isset($dates[$breakEndIndex])) ? $dates[$breakEndIndex] : null;
                        $payload['workerId'] = !empty($findUser->everee_workerId) ? $findUser->everee_workerId :  NULL;
                        $payload['externalWorkerId'] = !empty($findUser->employee_id) ? $findUser->employee_id : NULL;

                        if (!empty($check_pto) && isset($check_pto->pto_hours_perday) && !is_null($check_pto->pto_hours_perday)) {
                            $ptoHours = $check_pto->pto_hours_perday;
                            if ($clockOutIndex !== false && isset($dates[$clockOutIndex])) {
                                $clockOutTime = Carbon::parse($dates[$clockOutIndex]);
                                $newClockOutTime = $clockOutTime->addHours($ptoHours)->toDateTimeString(); // This adds the PTO hours
                                $payload['clockOut'] = $newClockOutTime;
                            }
                        }
                        Log::info(['2===>' => $payload]);
                        $untracked = $this->send_timesheet_data($payload);
                        $responses[] = $untracked;
                    }
                } else {
                    $req_approvals_data = ApprovalsAndRequestLock::where('user_id', $schedule->user_id)
                        ->where('adjustment_date', '=', $schedule_from_date)
                        ->where('adjustment_type_id', 9)
                        ->where('status', 'Approved')
                        ->first();
                    if (!empty($req_approvals_data)) {
                        $check_pto = ApprovalsAndRequestLock::where('user_id', $schedule->user_id)
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
                            $clockOut = $newClockOut->addHours($ptoHours)->toDateTimeString();
                            $payload['clockOut'] = $clockOut;
                        }
                        Log::info(['3===>' => $payload]);
                        $untracked = $this->send_timesheet_data($payload);
                        $responses[] = $untracked;
                    } else {
                        $check_pto = ApprovalsAndRequestLock::where('user_id', $schedule->user_id)
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

    public function failed(\Throwable $th)
    {
        Log::error("PayrollFailedRecordsProcess failed for user {$this->userId}", [
            'error' => $th->getMessage(),
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'trace' => $th->getTraceAsString()
        ]);
    }
}