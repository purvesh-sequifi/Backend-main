<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use App\Models\PayrollHourlySalary;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollObserversLog;

class PayrollObserver
{
    /**
     * Handle the Payroll "updated" event.
     */
    public function updated(Payroll $payroll)
    {
        try {
            DB::beginTransaction();
            if ($payroll->is_onetime_payment == 1) {
                DB::rollBack();
                return;
            }

            $user = User::find($payroll->user_id);
            if (!$user) {
                DB::rollBack();
                return;
            }

            $param = [
                "pay_frequency" => $payroll->pay_frequency,
                "worker_type" => $payroll->worker_type,
                "pay_period_from" => $payroll->pay_period_from,
                "pay_period_to" => $payroll->pay_period_to
            ];
            calculateDeduction($user, $param, $payroll);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payroll->id ?? 0,
                'action' => 'updated',
                'observer' => 'PayrollObserver',
                'old_value' => json_encode($payroll),
                'error' => json_encode([
                    'user_payroll_id' => $payroll->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the Payroll "created" event.
     */
    public function created(Payroll $payroll)
    {
        try {
            DB::beginTransaction();
            $user = User::find($payroll->user_id);
            if (!$user) {
                DB::rollBack();
                return;
            }

            $param = [
                "pay_frequency" => $payroll->pay_frequency,
                "worker_type" => $payroll->worker_type,
                "pay_period_from" => $payroll->pay_period_from,
                "pay_period_to" => $payroll->pay_period_to
            ];
            calculateDeduction($user, $param, $payroll);
            $salaryExists = PayrollHourlySalary::applyFrequencyFilter($param)->where('user_id', $user->id)->exists();
            if (!$salaryExists) {
                calculateSalary($user, $param);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payroll->id ?? 0,
                'action' => 'created',
                'observer' => 'PayrollObserver',
                'old_value' => json_encode($payroll),
                'error' => json_encode([
                    'user_payroll_id' => $payroll->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the Payroll "deleted" event.
     */
    public function deleted(Payroll $payroll)
    {
        try {
            DB::beginTransaction();
            // 
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payroll->id ?? 0,
                'action' => 'deleted',
                'observer' => 'PayrollObserver',
                'old_value' => json_encode($payroll),
                'error' => json_encode([
                    'user_payroll_id' => $payroll->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
