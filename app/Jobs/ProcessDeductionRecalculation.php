<?php

namespace App\Jobs;

use App\Traits\EmailNotificationTrait;
use Http\Controllers\API\Payroll\PayrollSingleController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDeductionRecalculation implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    protected $pay_period_from;

    protected $pay_period_to;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($pay_period_from, $pay_period_to)
    {
        $this->pay_period_from = $pay_period_from;
        $this->pay_period_to = $pay_period_to;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('innsiide ProcessDeductionRecalculation');

        $namespace = app()->getNamespace();
        $PayrollSingleController = app()->make($namespace.PayrollSingleController::class);

        $res = $PayrollSingleController->deduction_for_all_deduction_enable_users($this->start_date, $this->end_date, 0);

        Log::debug('PayrollSingleController $res');
        Log::debug($res);

    }
}
