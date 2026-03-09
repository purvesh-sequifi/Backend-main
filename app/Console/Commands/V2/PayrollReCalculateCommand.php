<?php

namespace App\Console\Commands\V2;

use App\Models\Payroll;
use Illuminate\Http\Request;
use App\Models\FrequencyType;
use Illuminate\Console\Command;
use App\Models\DailyPayFrequency;
use Illuminate\Support\Facades\DB;
use App\Traits\EmailNotificationTrait;

class PayrollReCalculateCommand extends Command
{
    use EmailNotificationTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:re-calculate {--memory-limit=2048 : Memory limit in MB} {--timeout=3600 : Timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-calculates payroll data every twelve hours';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get memory limit and timeout from command options
        $memoryLimitMB = (int) $this->option('memory-limit');
        $timeoutSeconds = (int) $this->option('timeout');

        // Clear stale scheduler lock if it exists and is older than 2 hours
        // Use only command name in hash to prevent multiple instances with different options
        $lockFile = storage_path('framework/schedule-' . sha1('payroll:re-calculate'));
        if (file_exists($lockFile)) {
            $lockAge = time() - filemtime($lockFile);
            if ($lockAge > 7200) { // 2 hours
                @unlink($lockFile);
                $this->info("Cleared stale scheduler lock (age: {$lockAge} seconds)");
            }
        }

        // Set memory and timeout from command options
        ini_set('memory_limit', $memoryLimitMB . 'M');
        set_time_limit($timeoutSeconds);

        $errors = [];
        $report = [];
        try {
            DB::beginTransaction();
            $uniquePayrolls = Payroll::select('pay_frequency', 'worker_type', 'pay_period_from', 'pay_period_to')->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('pay_frequency')->whereNotNull('worker_type')->where('pay_frequency', '!=', FrequencyType::DAILY_PAY_ID)->groupBy('pay_frequency', 'worker_type', 'pay_period_from', 'pay_period_to')->get();
            foreach ($uniquePayrolls as $uniquePayroll) {
                $param = [
                    "pay_frequency" => $uniquePayroll->pay_frequency,
                    "worker_type" => $uniquePayroll->worker_type,
                    "pay_period_from" => $uniquePayroll->pay_period_from,
                    "pay_period_to" => $uniquePayroll->pay_period_to
                ];
                $request = new Request($param);
                payrollRemoveDuplicateData($request); // REMOVES DUPLICATE PAYROLL ENTRIES
                $payrolls = Payroll::applyFrequencyFilter($param)->get();
                foreach ($payrolls as $payroll) {
                    reCalculatePayrollData($payroll->id, $param);
                }
                payrollRemoveZeroData($request); // REMOVES PAYROLL DATA WHERE TOTAL IS 0
                $report[] = $param;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = [
                "error" => "Payroll Re-Calculate Command Failed in normal case!! " . $e->getMessage() . " Line No. :- " . $e->getLine() . " File :- " . $e->getFile()
            ];
        }

        // 1099 DAILY PAY
        try {
            DB::beginTransaction();
            $lastDailyPayPeriod = DailyPayFrequency::orderBy('pay_period_to', 'desc')->where('closed_status', 1)->first();
            if ($lastDailyPayPeriod) {
                $payPeriodFrom = $lastDailyPayPeriod->pay_period_to;
            } else {
                $payPeriodFrom = '1969-12-31';
            }
            $payPeriodTo = now()->format('Y-m-d');
            $param = [
                "pay_frequency" => FrequencyType::DAILY_PAY_ID,
                "worker_type" => '1099',
                "pay_period_from" => $payPeriodFrom,
                "pay_period_to" => $payPeriodTo
            ];
            $request = new Request($param);
            payrollRemoveDuplicateData($request); // REMOVES DUPLICATE PAYROLL ENTRIES
            $payrolls = Payroll::applyFrequencyFilter($param)->get();
            foreach ($payrolls as $payroll) {
                reCalculatePayrollData($payroll->id, $param);
            }
            payrollRemoveZeroData($request); // REMOVES PAYROLL DATA WHERE TOTAL IS 0
            $report[] = $param;
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = [
                "error" => "Payroll Re-Calculate Command Failed in 1099 DAILY PAY case!! " . $e->getMessage() . " Line No. :- " . $e->getLine() . " File :- " . $e->getFile()
            ];
        }

        // W2 DAILY PAY
        try {
            DB::beginTransaction();
            $lastDailyPayPeriod = DailyPayFrequency::orderBy('pay_period_to', 'desc')->where('w2_closed_status', 1)->first();
            if ($lastDailyPayPeriod) {
                $payPeriodFrom = $lastDailyPayPeriod->pay_period_to;
            } else {
                $payPeriodFrom = '1969-12-31';
            }
            $payPeriodTo = now()->format('Y-m-d');
            $param = [
                "pay_frequency" => FrequencyType::DAILY_PAY_ID,
                "worker_type" => 'w2',
                "pay_period_from" => $payPeriodFrom,
                "pay_period_to" => $payPeriodTo
            ];
            $request = new Request($param);
            payrollRemoveDuplicateData($request); // REMOVES DUPLICATE PAYROLL ENTRIES
            $payrolls = Payroll::applyFrequencyFilter($param)->get();
            foreach ($payrolls as $payroll) {
                reCalculatePayrollData($payroll->id, $param);
            }
            payrollRemoveZeroData($request); // REMOVES PAYROLL DATA WHERE TOTAL IS 0
            DB::commit();
            $report[] = $param;
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = [
                "error" => "Payroll Re-Calculate Command Failed in W2 DAILY PAY case!! " . $e->getMessage() . " Line No. :- " . $e->getLine() . " File :- " . $e->getFile()
            ];
        }

        // if (sizeof($errors) != 0) {
        $errorTable = '<table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Error Message</th>
                </tr>
            </thead>
        <tbody>';
        if (sizeof($errors) != 0) {
            foreach ($errors as $index => $error) {
                $errorTable .= "<tr>
                    <td>" . ($index + 1) . "</td>
                    <td>" . htmlspecialchars($error['error']) . "</td>
                </tr>";
            }
        } else {
            if (sizeof($report) != 0) {
                foreach ($report as $index => $param) {
                    $errorTable .= "<tr>
                        <td>" . ($index + 1) . "</td>
                        <td>" . htmlspecialchars("Payroll data has been re-calculated successfully for " . FrequencyType::getFrequencyType($param['pay_frequency']) . " " . strtoupper($param['worker_type']) . " " . $param['pay_period_from'] . " to " . $param['pay_period_to'] . ".") . "</td>
                    </tr>";
                }
            } else {
                $errorTable .= "<tr>
                    <td>" . 1 . "</td>
                    <td>" . htmlspecialchars("Payroll data not found for recalculation.") . "</td>
                </tr>";
            }
        }
        $errorTable .= '</tbody></table>';

        $domainName = '| ' . config('app.domain_name') . ' | ';
        $failedEmail['email'] = 'jay@sequifi.com';
        $failedEmail['subject'] = 'Payroll Re-Calculate Report For ' . $domainName . ' server.';
        $failedEmail['template'] = $errorTable;
        $this->sendEmailNotification($failedEmail, true);

        // return Command::FAILURE;
        // }

        return Command::SUCCESS;
    }

    public function failed(\Throwable $e)
    {
        $error = "Payroll Re-Calculate Command Failed on " . config('app.domain_name') . " server. " . $e->getMessage() . " Line No. :- " . $e->getLine() . " File :- " . $e->getFile();
        $errorTable = '<table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Error Message</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>' . 1 . '</td>
                    <td>' . htmlspecialchars($error) . '</td>
                </tr>
            </tbody>
        </table>';

        $domainName = '| ' . config('app.domain_name') . ' | ';
        $failedEmail['email'] = 'jay@sequifi.com';
        $failedEmail['subject'] = 'Payroll Re-Calculate Command Failed on ' . $domainName . ' server.';
        $failedEmail['template'] = $errorTable;
        $this->sendEmailNotification($failedEmail, true);
    }
}
