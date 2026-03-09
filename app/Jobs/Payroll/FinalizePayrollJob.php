<?php

namespace App\Jobs\Payroll;

use App\Models\Crms;
use App\Models\User;
use App\Models\Payroll;
use App\Models\CustomField;
use Illuminate\Bus\Queueable;
use App\Core\Traits\EvereeTrait;
use App\Models\ApprovalsAndRequest;
use App\Models\SequiDocsEmailSettings;
use App\Traits\EmailNotificationTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\TempPayrollFinalizeExecuteDetail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\sendEventToPusher;

class FinalizePayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EvereeTrait, EmailNotificationTrait;

    public $tries = 3;
    public $timeout = 120;
    public $data, $startDate, $endDate, $adminMail, $auth, $frequencyTypeId;

    // Pusher notification properties
    protected int $progress = 0;
    protected string $jobId;
    protected ?string $sessionKey = null;
    protected array $stageBoundaries = [
        'init' => 15,
        'payables_processing' => 40,
        'finalization' => 65,
        'pdf_generation' => 85,
        'complete' => 100,
    ];

    public function __construct($data, $startDate, $endDate, $auth, $frequencyTypeId)
    {
        $this->onQueue('payroll');
        $this->data = $data;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->adminMail = $auth->email;
        $this->frequencyTypeId = $frequencyTypeId;
        $this->auth = $auth;

        // Initialize Pusher notification tracking
        $this->jobId = uniqid('finalize_', true);
        $this->sessionKey = request()->header('X-Session-Key') ?? session()->getId();
    }

    public function handle()
    {
        // Send initial notification
        $this->updateProgress(
            $this->stageBoundaries['init'],
            'started',
            "Starting payroll finalization for {$this->data->usersdata->first_name} {$this->data->usersdata->last_name}",
            [
                'user_id' => $this->data->user_id,
                'pay_period_from' => $this->startDate,
                'pay_period_to' => $this->endDate,
                'worker_type' => $this->data->worker_type,
            ]
        );

        $payroll = $this->data;
        $userId = $payroll->user_id;
        $endDate = $this->endDate;
        $startDate = $this->startDate;
        $actualNetPay = $payroll->net_pay;
        $commissionPayable = $reimbursementPayable = $bonusPayable = true;
        $workerId = isset($payroll->usersdata->everee_workerId) ? $payroll->usersdata->everee_workerId : NULL;
        $externalWorkerId = isset($payroll->usersdata->employee_id) ? $payroll->usersdata->employee_id : NULL;
        $domainName = config('app.domain_name');
        $bonusAmounts = 0;
        $externalId = [];
        $errorMessage = [];
        $status = 'SUCCESS';

        // Progress: Processing payables
        $this->updateProgress(
            $this->stageBoundaries['payables_processing'],
            'processing',
            'Processing payables and Everee integration...',
            ['stage' => 'payables_processing']
        );

        $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
        if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
            foreach ($payAblesList['items'] as $payAbleValue) {
                $this->delete_payable($payAbleValue['id'], $payroll->user_id);
            }
        }

        if ($payroll->is_next_payroll == 1 || $payroll->usersdata?->stop_payroll == 1) {
            Payroll::where('id', $payroll->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => NULL, 'everee_message' => NULL]);
        } else {
            if (Crms::where(['id' => 3, 'status' => 1])->first()) {
                if ($payroll->is_mark_paid != 1 && $payroll->is_onetime_payment != 1 && $payroll->net_pay > 0) {
                    if ($payroll->reimbursement > 0) {
                        $rExternalId = 'R-' . $externalWorkerId . "-" . $payroll->id;
                        $checkPayroll = Payroll::where('id', $payroll->id)->first();
                        if ($checkPayroll->net_pay == $actualNetPay) {
                            $data = clone $payroll;
                            $data->net_pay = $payroll->reimbursement;
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
                            $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                        }
                    }

                    $netPay = $payroll->net_pay - $payroll->reimbursement;
                    if ($netPay > 0) {
                        $bonusAmounts = ApprovalsAndRequest::where('payroll_id', $payroll->id)
                            ->whereIn('adjustment_type_id', [3, 6])
                            ->where('status', 'Accept')
                            ->sum('amount') ?? 0;

                        $bonusExcludedNetPay = $netPay - $bonusAmounts;
                        if ($bonusExcludedNetPay > 0) {
                            $cExternalId = 'C-' . $externalWorkerId . "-" . $payroll->id;
                            $checkPayroll = Payroll::where('id', $payroll->id)->first();
                            if ($checkPayroll->net_pay == $actualNetPay) {
                                $data = clone $payroll;
                                $data->net_pay = $bonusExcludedNetPay;
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
                                $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                            }

                            if ($bonusAmounts > 0) {
                                $cExternalId = 'B-' . $externalWorkerId . "-" . $payroll->id;
                                $checkPayroll = Payroll::where('id', $payroll->id)->first();
                                if ($checkPayroll->net_pay == $actualNetPay) {
                                    $data = clone $payroll;
                                    $data->net_pay = $bonusAmounts;

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
                                    $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
                                }
                            }
                        } else {
                            $bonusAmount = $bonusExcludedNetPay + $bonusAmounts;
                            if ($bonusAmount > 0) {
                                $cExternalId = 'B-' . $externalWorkerId . "-" . $payroll->id;
                                $checkPayroll = Payroll::where('id', $payroll->id)->first();
                                if ($checkPayroll->net_pay == $actualNetPay) {
                                    $data = clone $payroll;
                                    $data->net_pay = $bonusAmount;

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
                                    $errorMessage[] = "The net pay amount being sent to Everee is " . $payroll->net_pay . ", while the net pay in payroll is currently " . $checkPayroll->net_pay . ".";
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
                        $status = 'ERROR';
                    }
                }
            }

            // Progress: Finalizing payroll
            $this->updateProgress(
                $this->stageBoundaries['finalization'],
                'processing',
                'Finalizing payroll data...',
                ['stage' => 'finalization', 'status' => $status]
            );

            Payroll::where('id', $payroll->id)->update(['status' => 2, 'finalize_status' => 2, 'everee_external_id' => implode(',', $externalId), 'everee_message' => implode(',', $errorMessage)]);
            if ($payroll->is_onetime_payment != 1) {
                $this->createFinalizeDataForMail([
                    'user_id' => $payroll->user_id,
                    'payroll_id' => $payroll->id,
                    'net_amount' => $payroll->net_pay,
                    'pay_period_from' => $payroll->pay_period_from,
                    'pay_period_to' => $payroll->pay_period_to,
                    'pay_frequency' => $payroll->pay_frequency,
                    'worker_type' => $payroll->worker_type,
                    'message' => implode(',', $errorMessage),
                    'status' => $status,
                    'type' => 'Finalize 1099'
                ]);
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
            $lockKey = 'payroll_finalize_pdf_' . $payroll->pay_period_from . '_' . $payroll->pay_period_to . '_' . $payroll->pay_frequency . '_' . $payroll->worker_type;

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
                        // Progress: Generating PDFs and sending emails
                        $this->updateProgress(
                            $this->stageBoundaries['pdf_generation'],
                            'processing',
                            'Generating PDFs and sending emails...',
                            ['stage' => 'pdf_generation']
                        );

                        $allUsersDetails = $this->generatePdfAndSendMail($payroll->pay_period_from, $payroll->pay_period_to, $payroll->pay_frequency, $payroll->worker_type);
                        $frequencyType = $this->frequencyTypeId;

                        if (sizeOf($allUsersDetails) != 0) {
                            $array = [];
                            $array['email'] = $this->adminMail;
                            $array['subject'] = 'PayRoll Processes Info.';
                            $array['template'] = view('mail.payroll_prossed', compact('allUsersDetails', 'startDate', 'endDate', 'frequencyType'))->render();
                            if (config('app.domain_name') != 'aveyo' && config('app.domain_name') != 'aveyo2') {
                                $this->sendEmailNotification($array);
                            }

                            $newArray = $array;
                            $newArray['email'] = 'jay@sequifi.com';
                            $this->sendEmailNotification($newArray, true);
                        }
                    }
                } finally {
                    $lock->release();
                }
            }
        }

        // Complete
        $this->updateProgress(
            $this->stageBoundaries['complete'],
            'completed',
            "Payroll finalized successfully for {$payroll->usersdata->first_name} {$payroll->usersdata->last_name}",
            [
                'status' => $status,
                'net_pay' => $payroll->net_pay,
                'completed_at' => now()->toISOString(),
            ]
        );
    }

    protected function createFinalizeDataForMail($data)
    {
        TempPayrollFinalizeExecuteDetail::updateOrCreate(['payroll_id' => $data['payroll_id']], $data);
    }

    protected function generatePdfAndSendMail($startDate, $endDate)
    {
        // Checking status
        $adminMailDetail = [];
        $param = [
            "pay_frequency" => $this->frequencyTypeId,
            "worker_type" => "1099",
            "pay_period_from" => $startDate,
            "pay_period_to" => $endDate
        ];
        $emailSettings = SequiDocsEmailSettings::where('id', 3)->where('is_active', 1)->first();
        $tempFinalizeRecords = TempPayrollFinalizeExecuteDetail::with('user')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'type' => 'Finalize 1099'])->get();
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

    protected function updateProgress(
        int $targetProgress,
        string $status,
        string $message,
        array $metadata = []
    ): void {
        $this->progress = max($this->progress, $targetProgress);
        $this->progress = min(100, $this->progress);

        $this->sendPusherNotification($status, $this->progress, $message, $metadata);

        Log::info('Finalize payroll job progress', [
            'job_id' => $this->jobId,
            'progress' => $this->progress,
            'status' => $status,
            'message' => $message,
        ]);
    }

    protected function sendPusherNotification(
        string $status,
        int $progress,
        string $message,
        array $metadata
    ): void {
        try {
            $domainName = config('app.domain_name');

            event(new sendEventToPusher(
                $domainName,
                'finalize-payroll-progress',
                $message,
                array_merge($metadata, [
                    'status' => $status,
                    'progress' => $progress,
                    'session_key' => $this->sessionKey,
                    'job_id' => $this->jobId,
                    'user_id' => $this->data->user_id,
                ])
            ));

            Log::debug('Pusher notification sent', [
                'job_id' => $this->jobId,
                'status' => $status,
                'progress' => $progress,
                'session_key' => $this->sessionKey,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Pusher notification for finalize payroll job', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e)
    {
        // Send failure notification
        $this->sendPusherNotification(
            'failed',
            $this->progress,
            "Payroll finalization failed: {$e->getMessage()}",
            [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]
        );

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
        $failedEmail['subject'] = 'Failed to finalize payroll on ' . $domainName . ' server.';
        $failedEmail['template'] = view('mail.payroll_finalize_alert', compact('error', 'domainName', 'startDate', 'endDate', 'data'))->render();
        $this->sendEmailNotification($failedEmail, true);
    }
}
