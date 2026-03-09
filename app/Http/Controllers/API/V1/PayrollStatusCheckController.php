<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayrollStatusCheckController extends Controller
{
    public function checkFinalizeStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
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

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $payroll = Payroll::whereIn('finalize_status', [1, 2])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate) {
                    $q->where('pay_period_from', '<=', $startDate)
                        ->where('pay_period_to', '>=', $startDate);
                })->orWhere(function ($q) use ($endDate) {
                    $q->where('pay_period_from', '<=', $endDate)
                        ->where('pay_period_to', '>=', $endDate);
                })->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('pay_period_from', '>=', $startDate)
                        ->where('pay_period_to', '<=', $endDate);
                });
            })->exists();
        if ($payroll) {
            return response()->json([
                'ApiName' => 'check-finalize-status',
                'status' => true,
                'message' => 'Payroll is being Finalized.',
                'display' => 0,
            ]);
        }

        return response()->json([
            'ApiName' => 'check-finalize-status',
            'status' => true,
            'message' => 'No data found!!',
            'display' => 1,
        ]);
    }
}
