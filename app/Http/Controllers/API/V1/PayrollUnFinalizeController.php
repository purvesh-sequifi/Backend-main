<?php

namespace App\Http\Controllers\API\V1;

use App\Core\Traits\EvereeTrait;
use App\Http\Controllers\Controller;
use App\Models\Crms;
use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\TempPayrollFinalizeExecuteDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\DailyPayFrequency;

class PayrollUnFinalizeController extends Controller
{
    use EvereeTrait;

    public function unFinalizePayroll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d',
                'pay_frequency' => 'required|in:' . FrequencyType::WEEKLY_ID . ',' . FrequencyType::MONTHLY_ID . ',' . FrequencyType::BI_WEEKLY_ID . ',' . FrequencyType::SEMI_MONTHLY_ID . ',' . FrequencyType::DAILY_PAY_ID,
            ]);

            if ($validator->fails()) {
                return [
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'finalize_payroll',
                    'error' => $validator->errors(),
                    'code' => 400,
                ];
            }

            $frequencyTypeId = $request->pay_frequency;
            if ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
                $validator = Validator::make($request->all(), [
                    'start_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                    'end_date' => 'required|date_format:Y-m-d|before_or_equal:today',
                ]);

                if ($validator->fails()) {
                    return [
                        'status' => false,
                        'success' => false,
                        'ApiName' => 'finalize_payroll',
                        'error' => $validator->errors(),
                        'code' => 400,
                    ];
                }
            }

            $endDate = $request->end_date;
            $startDate = $request->start_date;
            $frequencyTypeId = $request->pay_frequency;
            $workerType = isset($request->worker_type) ? $request->worker_type : '1099';

            $payrolls = Payroll::with('usersdata')->where(['is_stop_payroll' => 0, 'is_onetime_payment' => 0, 'worker_type' => $workerType])->whereIn('finalize_status', [1, 2])
                ->when($frequencyTypeId == FrequencyType::DAILY_PAY_ID, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_period_from', [$startDate, $endDate])->whereBetween('pay_period_to', [$startDate, $endDate])->whereColumn('pay_period_from', 'pay_period_to');
                }, function ($query) use ($startDate, $endDate) {
                    $query->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate]);
                })->get();

            $crm = Crms::where(['id' => 3, 'status' => 1])->first();
            foreach ($payrolls as $payroll) {
                if ($crm) {
                    $externalWorkerId = isset($payroll->usersdata->employee_id) ? $payroll->usersdata->employee_id : null;
                    $payAblesList = $this->list_unpaid_payables_of_worker($externalWorkerId);
                    if (isset($payAblesList['items']) && count($payAblesList['items']) > 0) {
                        foreach ($payAblesList['items'] as $payAbleValue) {
                            $this->delete_payable($payAbleValue['id'], $payroll->user_id);
                        }
                    }
                }

                TempPayrollFinalizeExecuteDetail::where(['payroll_id' => $payroll->id])->delete();
                $payroll->status = 1;
                $payroll->finalize_status = 0;
                $payroll->everee_external_id = null;
                $payroll->everee_message = null;
                $payroll->save();
            }

            if ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
                $dailyPayFrequency = DailyPayFrequency::where(['pay_period_from' => $startDate])->orderBy('id', 'DESC')->first();
                if ($dailyPayFrequency && $dailyPayFrequency->closed_status == 0) {
                    $dailyPayFrequency->delete();
                }
            }

            return response()->json([
                'status' => true,
                'ApiName' => 'un-finalize-payroll',
                'message' => 'Payroll un-finalized successfully!!',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'ApiName' => 'un-finalize-payroll',
                'message' => $e->getMessage() . ' ' . $e->getLine(),
            ], 500);
        }
    }
}
