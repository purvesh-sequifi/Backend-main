<?php

namespace App\Jobs\Payroll;

use Illuminate\Bus\Queueable;
use App\Models\PayrollHistory;
use App\Models\OneTimePayments;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\API\V2\Payroll\PayrollController;

class PayrollPayStubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;
    public $isOneTimePayment, $historyId;

    public function __construct($isOneTimePayment = 0, $historyId = null)
    {
        $this->historyId = $historyId;
        $this->isOneTimePayment = $isOneTimePayment;
    }

    public function handle()
    {
        if ($this->isOneTimePayment) {
            $oneTimePayment = OneTimePayments::where('id', $this->historyId)->first();
            if ($oneTimePayment) {
                $payrollController = new PayrollController();
                $payrollController->payrollPayStubData($oneTimePayment->user_id, $oneTimePayment->pay_period_from, $oneTimePayment->pay_period_to, $oneTimePayment->pay_frequency, $oneTimePayment->user_worker_type, $this->isOneTimePayment, $this->historyId);
            }
        } else {
            $payrollHistory = PayrollHistory::where('payroll_id', $this->historyId)->first();
            if ($payrollHistory) {
                $payrollController = new PayrollController();
                $payrollController->payrollPayStubData($payrollHistory->user_id, $payrollHistory->pay_period_from, $payrollHistory->pay_period_to, $payrollHistory->pay_frequency, $payrollHistory->worker_type, $this->isOneTimePayment, $this->historyId);
            }
        }
    }
}
