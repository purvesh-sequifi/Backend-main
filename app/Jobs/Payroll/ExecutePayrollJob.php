<?php

namespace App\Jobs\Payroll;

use App\Models\Crms;
use App\Models\Payroll;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use App\Models\PayrollHistory;
use App\Core\Traits\EvereeTrait;
use App\Models\AdvancePaymentSetting;
use App\Traits\EmailNotificationTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\TempPayrollFinalizeExecuteDetail;
use App\Events\sendEventToPusher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ExecutePayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, EvereeTrait, EmailNotificationTrait;

    public $data, $payPeriodFrom, $payPeriodTo, $newPayPeriodFrom, $newPayPeriodTo, $payFrequency, $workerType;
    public $timeout = 1800;
    public $tries = 3;

    // Pusher notification properties
    protected int $progress = 0;
    protected string $jobId;
    protected ?string $sessionKey = null;
    protected array $stageBoundaries = [
        'init' => 10,
        'payment_processing' => 40,
        'payroll_processing' => 60,
        'notification' => 80,
        'pdf_generation' => 90,
        'complete' => 100,
    ];

    public function __construct($data, $nextPeriod)
    {
        $this->onQueue('payroll');
        $this->data = $data;
        $this->payPeriodFrom = $data->pay_period_from;
        $this->payPeriodTo = $data->pay_period_to;
        $this->newPayPeriodFrom = $nextPeriod['pay_period_from'];
        $this->newPayPeriodTo = $nextPeriod['pay_period_to'];
        $this->payFrequency = $data->pay_frequency;
        $this->workerType = $data->worker_type;

        // Initialize Pusher notification tracking
        $this->jobId = uniqid('payroll_', true);
        $this->sessionKey = request()->header('X-Session-Key') ?? session()->getId();
    }

    public function handle()
    {
        // Send initial notification
        $this->updateProgress(
            $this->stageBoundaries['init'],
            'started',
            "Starting payroll execution for {$this->data->usersdata->first_name} {$this->data->usersdata->last_name}",
            [
                'user_id' => $this->data->user_id,
                'pay_period_from' => $this->payPeriodFrom,
                'pay_period_to' => $this->payPeriodTo,
                'worker_type' => $this->workerType,
            ]
        );

        $data = $this->data;
        $crmData = Crms::where(['id' => 3, 'status' => 1])->first();
        $workerType = $this->workerType;
        $workerTypeId = isset($data?->usersdata?->everee_workerId) ? $data?->usersdata?->everee_workerId : NULL;
        $checkPayroll = TempPayrollFinalizeExecuteDetail::where('payroll_id', $data->id)->first();
        if ($checkPayroll->type != 'EVEREE_EXECUTE' && $checkPayroll->type != 'Execute') {
            // Progress: Payment processing
            $this->updateProgress(
                $this->stageBoundaries['payment_processing'],
                'processing',
                'Processing payment request...',
                ['stage' => 'payment_processing']
            );

            if ($crmData && $data->is_mark_paid != 1 && $data->is_onetime_payment != 1 && $data->net_pay > 0) {
                if ($workerTypeId) {
                    $enableEVE = 1;
                    $untracked = $this->payable_request($data);
                    if (!isset($untracked['success']['paymentId']) && strtolower($workerType) != 'w2') {
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

            $paymentDetail = [
                'everee_status' => $enableEVE,
                'pay_type' => $payType,
                'everee_external_id' => $data->everee_external_id,
                'everee_paymentId' => isset($untracked['success']['everee_payment_id']) ? $untracked['success']['everee_payment_id'] : NULL,
                'everee_payment_requestId' => isset($untracked['success']['paymentId']) ? $untracked['success']['paymentId'] : NULL,
                'everee_json_response' => isset($untracked) ? json_encode($untracked) : NULL
            ];

            TempPayrollFinalizeExecuteDetail::where('payroll_id', $data->id)->update(['type' => 'EVEREE_EXECUTE']);
        } else {
            $payrollHistory = PayrollHistory::where('payroll_id', $data->id)->first();
            if ($payrollHistory) {
                $paymentDetail = [
                    'everee_status' => $payrollHistory->everee_status,
                    'pay_type' => $payrollHistory->pay_type,
                    'everee_external_id' => $payrollHistory->everee_external_id,
                    'everee_paymentId' => $payrollHistory->everee_paymentId,
                    'everee_payment_requestId' => $payrollHistory->everee_payment_requestId,
                    'everee_json_response' => $payrollHistory->everee_json_response
                ];
            } else {
                $paymentDetail = [
                    'everee_status' => 0,
                    'pay_type' => 'Manualy',
                    'everee_external_id' => NULL,
                    'everee_paymentId' => NULL,
                    'everee_payment_requestId' => NULL,
                    'everee_json_response' => NULL
                ];
            }
        }

        // Progress: Processing payroll data
        $this->updateProgress(
            $this->stageBoundaries['payroll_processing'],
            'processing',
            'Processing payroll data...',
            ['stage' => 'payroll_data']
        );

        $nextPeriod = [
            'pay_period_from' => $this->newPayPeriodFrom,
            'pay_period_to' => $this->newPayPeriodTo
        ];
        $advanceSetting = AdvancePaymentSetting::first();
        processPayrollData($data, $nextPeriod, $advanceSetting, $paymentDetail);

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
            'pay_frequency' => $data->pay_frequency,
            'worker_type' => $data->worker_type,
            'message' => isset($untracked['fail']['everee_response']['errorMessage']) ? $untracked['fail']['everee_response']['errorMessage'] : NULL,
            'status' => $successStatus,
            'type' => 'Execute'
        ]);

        Payroll::where('id', $data->id)->delete();

        create_paystub_employee([
            'user_id' => $data->user_id,
            'pay_period_from' => $data->pay_period_from,
            'pay_period_to' => $data->pay_period_to
        ]);

        // Progress: Creating notifications
        $this->updateProgress(
            $this->stageBoundaries['notification'],
            'processing',
            'Creating notifications...',
            ['stage' => 'notifications']
        );

        Notification::create([
            'user_id' => $data->user_id,
            'type' => 'Execute PayRoll',
            'description' => 'Execute PayRoll Data',
            'is_read' => 0
        ]);

        $notificationData = array(
            'user_id' => $data?->usersdata?->user_id,
            'device_token' => $data?->usersdata?->device_token,
            'title' => 'Execute PayRoll Data.',
            'sound' => 'sound',
            'type' => 'Execute PayRoll',
            'body' => 'Updated Execute PayRoll Data'
        );
        $this->sendNotification($notificationData);

        // Check if all payrolls for this pay period are processed (status != 1 means not pending)
        $hasPendingPayrolls = Payroll::applyFrequencyFilter(
            [
                'pay_period_from' => $data->pay_period_from,
                'pay_period_to' => $data->pay_period_to,
                'pay_frequency' => $data->pay_frequency,
                'worker_type' => $data->worker_type
            ],
            ['status' => 1, 'is_onetime_payment' => 0]
        )->exists();

        // Only generate PDFs if all payrolls are processed and this job acquires the lock
        if (!$hasPendingPayrolls) {
            $lockKey = 'payroll_pdf_generation_' . $data->pay_period_from . '_' . $data->pay_period_to . '_' . $data->pay_frequency . '_' . $data->worker_type;

            // Use cache lock to ensure only one job generates PDFs (prevents race conditions)
            $lock = Cache::lock($lockKey, 300); // 5 minute lock

            if ($lock->get()) {
                try {
                    // Double-check: verify no pending payrolls exist (another job might have finished)
                    $hasPendingPayrolls = Payroll::applyFrequencyFilter(
                        [
                            'pay_period_from' => $data->pay_period_from,
                            'pay_period_to' => $data->pay_period_to,
                            'pay_frequency' => $data->pay_frequency,
                            'worker_type' => $data->worker_type
                        ],
                        ['status' => 1, 'is_onetime_payment' => 0]
                    )->exists();

                    if (!$hasPendingPayrolls) {
                        // Progress: Generating PDF
                        $this->updateProgress(
                            $this->stageBoundaries['pdf_generation'],
                            'processing',
                            'Generating PDF and sending emails...',
                            ['stage' => 'pdf_generation']
                        );

                        $this->generatePdfAndSendMail();
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
            "Payroll executed successfully for {$data->usersdata->first_name} {$data->usersdata->last_name}",
            [
                'net_pay' => $data->net_pay,
                'pay_type' => $paymentDetail['pay_type'],
                'status' => $successStatus,
                'completed_at' => now()->toISOString(),
            ]
        );
    }

    public function generatePdfAndSendMail()
    {
        $param = [
            'pay_period_from' => $this->payPeriodFrom,
            'pay_period_to' => $this->payPeriodTo,
            'pay_frequency' => $this->payFrequency,
            'worker_type' => $this->workerType
        ];

        $payrollHistoryRecords = PayrollHistory::applyFrequencyFilter($param, ['pay_type' => 'Manualy', 'is_onetime_payment' => 0])->get();
        foreach ($payrollHistoryRecords as $payrollHistoryRecord) {
            PayrollPayStubJob::dispatch(0, $payrollHistoryRecord->payroll_id);
        }
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

        Log::info('Payroll job progress', [
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
                'execute-payroll-progress',
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
            Log::error('Failed to send Pusher notification for payroll job', [
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
            "Payroll execution failed: {$e->getMessage()}",
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

        $endDate = $this->payPeriodFrom;
        $startDate = $this->payPeriodTo;
        $domainName = config('app.domain_name') . ' | ';
        $failedEmail['email'] = 'jay@sequifi.com';
        $failedEmail['subject'] = 'Failed to execute payroll job on ' . $domainName . ' server.';
        $failedEmail['template'] = view('mail.payroll_finalize_alert', compact('error', 'domainName', 'startDate', 'endDate'))->render();
        $this->sendEmailNotification($failedEmail, true);
    }
}
