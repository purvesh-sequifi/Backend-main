<?php

namespace App\Jobs\Payroll;

use Illuminate\Bus\Queueable;
use App\Models\OneTimePayments;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\API\V2\Payroll\PayrollController;

class OneTimePaymentPayStubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 1200;
    public $oneTimePaymentId;

    public function __construct($oneTimePaymentId)
    {
        $this->onQueue('payroll');
        $this->oneTimePaymentId = $oneTimePaymentId;
    }

    public function handle()
    {
        $oneTimePayment = OneTimePayments::where('id', $this->oneTimePaymentId)->first();
        if ($oneTimePayment) {
            $payrollController = new PayrollController();
            $payrollController->payrollPayStubData($oneTimePayment->user_id, $oneTimePayment->pay_period_from, $oneTimePayment->pay_period_to, $oneTimePayment->pay_frequency, $oneTimePayment->worker_type, 1, $this->oneTimePaymentId);
        }
    }

    public function failed(\Throwable $e)
    {
        $error = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'job' => $this,
        ];
        \Log::error('OneTimePaymentPayStubJob failed', $error);
        //     $domain_name = config('app.domain_name') . ' | ';
        //     $failedEmail['email'] = 'anurag@sequifi.com';
        //     $failedEmail['subject'] = 'Failed to execute payroll job  on ' . $domain_name . ' server ';
        //     $failedEmail['template'] = view('mail.payroll_finalize_alert', compact('error', 'domain_name'));
        //     $this->sendEmailNotification($failedEmail, true);
    }
}
