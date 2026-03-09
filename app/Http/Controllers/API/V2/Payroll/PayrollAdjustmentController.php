<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\Payroll;
use Illuminate\Http\Request;
use App\Models\UserOverrides;
use App\Models\UserCommission;
use App\Models\PayrollOvertime;
use App\Models\PayrollDeductions;
use App\Models\ClawbackSettlement;
use App\Models\PayrollHourlySalary;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\PayrollAdjustmentDetail;
use Illuminate\Support\Facades\Validator;

class PayrollAdjustmentController extends Controller
{
    public function salaryAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'amount' => 'required',
            'comment' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "salary-adjustment",
                "error" => $validator->errors()
            ], 400);
        }

        $data = PayrollHourlySalary::find($request->id);
        if (!$data) {
            return response()->json([
                "status" => false,
                "ApiName" => "salary-adjustment",
                "message" => "Salary data not found"
            ], 400);
        }

        $payroll = Payroll::find($data->payroll_id);
        if (!$payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "salary-adjustment",
                "message" => "Payroll not found"
            ], 400);
        }

        if (checkPayrollStatus($payroll->pay_frequency, $payroll->worker_type, $payroll->pay_period_from, $payroll->pay_period_to)) {
            return response()->json([
                "status" => false,
                "ApiName" => "salary-adjustment",
                "message" => "Payroll has been finalized. No changes can be made after finalization."
            ], 400);
        }

        $adjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'salary_overtime_date' => $data->date, 'type' => 'hourlysalary', 'payroll_type' => 'hourlysalary'])->first();
        if ($adjustmentDetail) {
            $adjustmentDetail->amount = $request->amount;
            $adjustmentDetail->comment = $request->comment;
            $adjustmentDetail->comment_by = Auth::user()->id;
            $adjustmentDetail->save();
        } else {
            PayrollAdjustmentDetail::create([
                'payroll_id' => $payroll->id,
                'user_id' => $payroll->user_id,
                'payroll_type_id' => $data->id,
                'payroll_type' => 'hourlysalary',
                'type' => 'hourlysalary',
                'amount' => $request->amount,
                'comment' => $request->comment,
                'comment_by' => Auth::user()->id,
                'salary_overtime_date' => $data->date,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'user_worker_type' => $payroll->worker_type,
                'pay_frequency' => $payroll->pay_frequency
            ]);
        }

        return response()->json([
            'status' => true,
            'ApiName' => 'salary-adjustment',
            'message' => 'Hourly salary updated successfully.'
        ]);
    }

    public function overtimeAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'amount' => 'required',
            'comment' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "overtime-adjustment",
                "error" => $validator->errors()
            ], 400);
        }

        $data = PayrollOvertime::find($request->id);
        if (!$data) {
            return response()->json([
                "status" => false,
                "ApiName" => "overtime-adjustment",
                "message" => "Overtime data not found"
            ], 400);
        }

        $payroll = Payroll::find($data->payroll_id);
        if (!$payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "overtime-adjustment",
                "message" => "Payroll not found"
            ], 400);
        }

        if (checkPayrollStatus($payroll->pay_frequency, $payroll->worker_type, $payroll->pay_period_from, $payroll->pay_period_to)) {
            return response()->json([
                "status" => false,
                "ApiName" => "overtime-adjustment",
                "message" => "Payroll has been finalized. No changes can be made after finalization."
            ], 400);
        }

        $adjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'salary_overtime_date' => $data->date, 'type' => 'overtime', 'payroll_type' => 'overtime'])->first();
        if ($adjustmentDetail) {
            $adjustmentDetail->amount = $request->amount;
            $adjustmentDetail->comment = $request->comment;
            $adjustmentDetail->comment_by = Auth::user()->id;
            $adjustmentDetail->save();
        } else {
            PayrollAdjustmentDetail::create([
                'payroll_id' => $payroll->id,
                'user_id' => $payroll->user_id,
                'payroll_type_id' => $data->id,
                'payroll_type' => 'overtime',
                'type' => 'overtime',
                'amount' => $request->amount,
                'comment' => $request->comment,
                'comment_by' => Auth::user()->id,
                'salary_overtime_date' => $data->date,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'user_worker_type' => $payroll->worker_type,
                'pay_frequency' => $payroll->pay_frequency
            ]);
        }

        return response()->json([
            'status' => true,
            'ApiName' => 'overtime-adjustment',
            'message' => 'Overtime updated successfully.'
        ]);
    }

    public function commissionAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'amount' => 'required',
            'comment' => 'required',
            'type' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "commission-adjustment",
                "error" => $validator->errors()
            ], 400);
        }

        $id = $request->id;
        $type = $request->type;
        if ($type == 'clawback') {
            $data = ClawbackSettlement::find($id);
        } else {
            $data = UserCommission::find($id);
        }

        if (!$data) {
            return response()->json([
                "status" => false,
                "ApiName" => "commission-adjustment",
                "message" => "Commission data not found"
            ], 400);
        }

        $payroll = Payroll::find($data->payroll_id);
        if (!$payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "commission-adjustment",
                "message" => "Payroll not found"
            ], 400);
        }

        if (checkPayrollStatus($payroll->pay_frequency, $payroll->worker_type, $payroll->pay_period_from, $payroll->pay_period_to)) {
            return response()->json([
                "status" => false,
                "ApiName" => "commission-adjustment",
                "message" => "Payroll has been finalized. No changes can be made after finalization."
            ], 400);
        }

        $finalType = $data->schema_type;
        $adjustmentType = $data->schema_type;
        if ($type == 'clawback') {
            $finalType = 'clawback';
        }

        $adjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'pid' => $data->pid, 'payroll_type' => 'commission', 'type' => $finalType, 'adjustment_type' => $adjustmentType])->first();
        if ($adjustmentDetail) {
            $adjustmentDetail->amount = $request->amount;
            $adjustmentDetail->comment = $request->comment;
            $adjustmentDetail->comment_by = Auth::user()->id;
            $adjustmentDetail->save();
        } else {
            PayrollAdjustmentDetail::create([
                'payroll_id' => $payroll->id,
                'user_id' => $payroll->user_id,
                'pid' => $data->pid,
                'type' => $finalType,
                'payroll_type_id' => $data->id,
                'payroll_type' => 'commission',
                'adjustment_type' => $adjustmentType,
                'amount' => $request->amount,
                'comment' => $request->comment,
                'comment_by' => Auth::user()->id,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'user_worker_type' => $payroll->worker_type,
                'pay_frequency' => $payroll->pay_frequency
            ]);
        }

        return response()->json([
            'status' => true,
            'ApiName' => 'commission-adjustment',
            'message' => 'Commission updated successfully.'
        ]);
    }

    public function overrideAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'amount' => 'required',
            'comment' => 'required',
            'type' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "override-adjustment",
                "error" => $validator->errors()
            ], 400);
        }

        $id = $request->id;
        $type = $request->type;
        if ($type == 'clawback') {
            $data = ClawbackSettlement::find($id);
        } else {
            $data = UserOverrides::find($id);
        }

        if (!$data) {
            return response()->json([
                "status" => false,
                "ApiName" => "override-adjustment",
                "message" => "Override data not found"
            ], 400);
        }

        $payroll = Payroll::find($data->payroll_id);
        if (!$payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "override-adjustment",
                "message" => "Payroll not found"
            ], 400);
        }

        if (checkPayrollStatus($payroll->pay_frequency, $payroll->worker_type, $payroll->pay_period_from, $payroll->pay_period_to)) {
            return response()->json([
                "status" => false,
                "ApiName" => "override-adjustment",
                "message" => "Payroll has been finalized. No changes can be made after finalization."
            ], 400);
        }

        $saleUserId = $data?->sale_user_id;
        if ($type == 'clawback') {
            $finalType = 'clawback';
            $adjustmentType = $data->adders_type;
        } else {
            $finalType = $data->type;
            $adjustmentType = $data->type;
        }

        $adjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'sale_user_id' => $saleUserId, 'pid' => $data->pid, 'payroll_type' => 'overrides', 'type' => $finalType, 'adjustment_type' => $adjustmentType])->first();
        if ($adjustmentDetail) {
            $adjustmentDetail->amount = $request->amount;
            $adjustmentDetail->comment = $request->comment;
            $adjustmentDetail->comment_by = Auth::user()->id;
            $adjustmentDetail->save();
        } else {
            PayrollAdjustmentDetail::create([
                'payroll_id' => $payroll->id,
                'user_id' => $payroll->user_id,
                'sale_user_id' => $saleUserId,
                'pid' => $data->pid,
                'payroll_type_id' => $data->id,
                'type' => $finalType,
                'payroll_type' => 'overrides',
                'adjustment_type' => $adjustmentType,
                'amount' => $request->amount,
                'comment' => $request->comment,
                'comment_by' => Auth::user()->id,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'user_worker_type' => $payroll->worker_type,
                'pay_frequency' => $payroll->pay_frequency
            ]);
        }

        return response()->json([
            'status' => true,
            'ApiName' => 'override-adjustment',
            'message' => 'Override updated successfully.'
        ]);
    }

    public function deductionAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'amount' => 'required',
            'comment' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "deduction-adjustment",
                "error" => $validator->errors()
            ], 400);
        }

        $data = PayrollDeductions::find($request->id);
        if (!$data) {
            return response()->json([
                "status" => false,
                "ApiName" => "deduction-adjustment",
                "message" => "Deduction data not found"
            ], 400);
        }

        $payroll = Payroll::find($data->payroll_id);
        if (!$payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "deduction-adjustment",
                "message" => "Payroll not found"
            ], 400);
        }

        if (checkPayrollStatus($payroll->pay_frequency, $payroll->worker_type, $payroll->pay_period_from, $payroll->pay_period_to)) {
            return response()->json([
                "status" => false,
                "ApiName" => "deduction-adjustment",
                "message" => "Payroll has been finalized. No changes can be made after finalization."
            ], 400);
        }

        $adjustmentDetail = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'cost_center_id' => $data->cost_center_id, 'payroll_type' => 'deduction'])->first();
        if ($adjustmentDetail) {
            $adjustmentDetail->amount = $request->amount;
            $adjustmentDetail->comment = $request->comment;
            $adjustmentDetail->comment_by = Auth::user()->id;
            $adjustmentDetail->save();
        } else {
            PayrollAdjustmentDetail::create([
                'payroll_id' => $payroll->id,
                'user_id' => $payroll->user_id,
                'cost_center_id' => $data->cost_center_id,
                'type' => 'deduction',
                'payroll_type_id' => $data->id,
                'payroll_type' => 'deduction',
                'amount' => $request->amount,
                'comment' => $request->comment,
                'comment_by' => Auth::user()->id,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
                'user_worker_type' => $payroll->worker_type,
                'pay_frequency' => $payroll->pay_frequency
            ]);
        }

        return response()->json([
            'status' => true,
            'ApiName' => 'deduction-adjustment',
            'message' => 'Deduction updated successfully.'
        ]);
    }

    public function deleteAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "delete-adjustment",
                "error" => $validator->errors()
            ], 400);
        }

        $data = PayrollAdjustmentDetail::find($request->id);
        if (!$data) {
            return response()->json([
                "status" => false,
                "ApiName" => "delete-adjustment",
                "message" => "Data not found!!"
            ], 400);
        }

        $payroll = Payroll::find($data->payroll_id);
        if (!$payroll) {
            return response()->json([
                "status" => false,
                "ApiName" => "delete-adjustment",
                "message" => "Payroll not found!!"
            ], 400);
        }

        if (checkPayrollStatus($payroll->pay_frequency, $payroll->worker_type, $payroll->pay_period_from, $payroll->pay_period_to)) {
            return response()->json([
                "status" => false,
                "ApiName" => "delete-adjustment",
                "message" => "Payroll has been finalized. No changes can be made after finalization!!"
            ], 400);
        }

        $data->delete();
        return response()->json([
            "status" => true,
            "ApiName" => "delete-adjustment",
            "message" => "Adjustment deleted successfully!!"
        ]);
    }
}
