<?php

namespace App\Http\Controllers\API\Payroll;

use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\FrequencyType;
use App\Models\GetPayrollData;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollDeductions;
use App\Models\PositionReconciliations;
use App\Models\ReconciliationsAdjustement;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use App\Traits\EmailNotificationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PayrollAdjustmentController extends Controller
{
    use EmailNotificationTrait;

    public function payrollCommission(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payroll_id' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $payrollId = $request->payroll_id;
        $id = $request->user_id;
        $pid = $request->pid;
        $type = $request->type;
        $amount = $request->amount;
        $comment = $request->comment;
        $payroll = Payroll::where('id', $payrollId)->first();
        if (Payroll::where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => '2'])->first()) {
            return response()->json([
                'ApiName' => 'payrollCommission',
                'status' => false,
                'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
            ], 400);
        }

        $finalType = null;
        $adjustmentType = null;
        if ($type == 'clawback') {
            $clawBack = ClawbackSettlement::find($id);
            $adjustmentType = $clawBack->schema_type;
            $finalType = 'clawback';
        } else {
            $commission = UserCommission::find($id);
            $adjustmentType = $commission->schema_type;
            $finalType = $commission->schema_type;
        }
        $userId = $payroll->user_id;

        PayrollAdjustmentDetail::updateOrCreate(['payroll_id' => $payrollId, 'user_id' => $userId, 'pid' => $pid, 'type' => $finalType, 'payroll_type' => 'commission', 'adjustment_type' => $adjustmentType], [
            'amount' => $amount,
            'comment' => $comment,
            'comment_by' => Auth::user()->id,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ]);
        $totalAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'payroll_type' => 'commission'])->sum('amount');
        PayrollAdjustment::updateOrCreate(['payroll_id' => $payrollId, 'user_id' => $userId], [
            'commission_amount' => $totalAmount,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ]);

        return response()->json([
            'ApiName' => 'payroll_commission_edit',
            'status' => true,
            'message' => 'Successfully.',
            'data' => [],
        ]);
    }

    public function updatePayrollOverrides(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(), [
            'payroll_id' => 'required',
            'user_id' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $payrollId = $request->payroll_id;
        $pid = $request->pid;
        $id = $request->user_id;
        $type = $request->type;
        $amount = $request->amount;
        $comment = $request->comment;
        $payroll = Payroll::where('id', $payrollId)->first();

        if (Payroll::where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => '2'])->first()) {
            return response()->json([
                'ApiName' => 'updatePayrollOverrides',
                'status' => false,
                'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
            ], 400);
        }

        $finalType = null;
        $userId = $payroll->user_id;
        $adjustmentType = null;
        if ($type == 'clawback') {
            $clawBack = ClawbackSettlement::find($id);
            $adjustmentType = $clawBack?->adders_type;
            $saleUserId = $clawBack?->sale_user_id;
            $finalType = 'clawback';
        } else {
            $override = UserOverrides::find($id);
            $adjustmentType = $override?->type;
            $saleUserId = $override?->sale_user_id;
            $finalType = $override?->type;
        }

        PayrollAdjustmentDetail::updateOrCreate(['payroll_id' => $payrollId, 'user_id' => $userId, 'pid' => $pid, 'type' => $finalType, 'payroll_type' => 'overrides', 'adjustment_type' => $adjustmentType, 'sale_user_id' => $saleUserId], [
            'amount' => $amount,
            'comment' => $comment,
            'comment_by' => Auth::user()->id,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ]);
        $totalAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'payroll_type' => 'overrides'])->sum('amount');
        PayrollAdjustment::updateOrCreate(['payroll_id' => $payrollId, 'user_id' => $userId], [
            'overrides_amount' => $totalAmount,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ]);

        return response()->json([
            'ApiName' => 'update_overrides_payroll',
            'status' => true,
            'message' => 'Successfully.',
            'data' => [],
        ]);
    }

    public function updatepayrollDeduction(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
                // 'user_id'    => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        // return $request;
        $data = [];
        $payrollId = $request->payroll_id;
        // $userId = $request->user_id;
        $pid = $request->pid;
        $type = $request->type;
        $cost_center_id = $request->cost_center_id;
        $amount = $request->amount;
        $comment = $request->comment;
        $payroll = Payroll::where('id', $payrollId)->first();

        if (Payroll::when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($payroll) {
            $query->whereBetween('pay_period_from', [$payroll->pay_period_from, $payroll->pay_period])
                ->whereBetween('pay_period_to', [$payroll->pay_period_from, $payroll->pay_period])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($payroll) {
            $query->where([
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period]);
        })
            ->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => '2'])->first()) {
            return response()->json([
                'ApiName' => 'updatepayrollDeduction',
                'status' => false,
                'message' => 'The payroll has already been finalized. No changes can be made after finalization.',
            ], 400);
        }

        $userId = $payroll->user_id;
        $data = [
            'payroll_id' => $payrollId,
            'user_id' => $userId,
            'pid' => $pid,
            'payroll_type' => 'deduction',
            'type' => $type,
            'amount' => $amount,
            'comment' => $comment,
            'cost_center_id' => $cost_center_id,
            'status' => 1,
            'pay_period_from' => $payroll->pay_period_from,
            'pay_period_to' => $payroll->pay_period_to,
        ];
        $dataPayroll = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'pid' => $pid, 'type' => $type])->first();
        if ($dataPayroll) {
            PayrollAdjustmentDetail::where('id', $dataPayroll->id)->update($data);
            Payroll::where('id', $payrollId)->update(['status' => 1, 'finalize_status' => 0]);
        } else {
            PayrollAdjustmentDetail::create($data);
        }

        $PayrollAdjustment = PayrollAdjustment::where(['payroll_id' => $payrollId, 'user_id' => $userId])->first();
        $totalamount = PayrollAdjustmentDetail::where(['payroll_id' => $payrollId, 'user_id' => $userId, 'payroll_type' => 'deduction'])->sum('amount');
        if ($PayrollAdjustment) {
            $updateAjustment = PayrollAdjustment::where(['payroll_id' => $payrollId, 'user_id' => $userId])->update(['deductions_amount' => $totalamount, 'status' => 1, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to]);
        } else {

            $data1 = [
                'payroll_id' => $payrollId,
                'user_id' => $userId,
                'commission_amount' => 0,
                'overrides_amount' => 0,
                'adjustments_amount' => 0,
                'reimbursements_amount' => 0,
                'deductions_amount' => $totalamount,
                'reconciliations_amount' => 0,
                'clawbacks_amount' => 0,
                'status' => 1,
                'pay_period_from' => $payroll->pay_period_from,
                'pay_period_to' => $payroll->pay_period_to,
            ];
            $addPayrollAdjustment = PayrollAdjustment::Create($data1);
        }

        return response()->json([
            'ApiName' => 'payroll_deduction_edit',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function payrollDeductionsByEmployeeId_old(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $payroll = GetPayrollData::where(['id' => $request->payroll_id, 'user_id' => $request->user_id, 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to])->first();

        // if (!empty($payroll)) {
        // $Payroll_status = $payroll->status;
        // }else{
        //      $payroll = PayrollHistory::where(['payroll_id' => $request->payroll_id])->first();
        //      $Payroll_status = $payroll->status;
        // }
        $paydata = [];
        $Payroll_status = '';
        if (! empty($payroll)) {
            $Payroll_status = $payroll->status;
            $paydata = PayrollDeductions::with('costcenter:id,name,status')
                ->leftjoin('payroll_adjustment_details', function ($join) {
                    $join->on('payroll_adjustment_details.payroll_id', '=', 'payroll_deductions.payroll_id')
                        ->on('payroll_adjustment_details.cost_center_id', '=', 'payroll_deductions.cost_center_id');
                })
                ->where(function ($query) {
                    $query->whereNull('payroll_deductions.cost_center_id')
                        ->orWhereHas('costcenter', function ($q) {
                            $q->where('status', 1);
                        });
                })
                ->where('payroll_deductions.user_id', $payroll->user_id)
                ->where('payroll_deductions.payroll_id', $request->payroll_id)
                ->select('payroll_deductions.*', 'payroll_adjustment_details.amount as adjustment_amount')
                ->get();
        }

        $response_arr = [];
        $subtotal = 0;
        foreach ($paydata as $d) {
            // $subtotal = $d->subtotal;
            if ($d->is_mark_paid == 0 && $d->is_next_payroll == 0) {
                $subtotal += $d->total;
            }
            $response_arr[] = [
                'id' => $d->id,
                'payroll_id' => $d->payroll_id,
                'is_mark_paid' => $d->is_mark_paid,
                'is_next_payroll' => $d->is_next_payroll,
                'Type' => $d->costcenter->name,
                'Amount' => $d->amount,
                'Limit' => $d->limit,
                'Total' => $d->total,
                'Outstanding' => $d->outstanding,
                'cost_center_id' => $d->cost_center_id,
                'adjustment_amount' => isset($d->adjustment_amount) ? $d->adjustment_amount : 0,
                'is_move_to_recon' => $d->is_move_to_recon,
            ];
        }

        $response = ['list' => $response_arr, 'subtotal' => $subtotal];

        return response()->json([
            'ApiName' => 'payroll_Deductions_By_EmployeeId',
            'status' => true,
            'message' => 'Successfully.',
            'payroll_status' => $Payroll_status,
            'data' => $response,
            'is_recon' => $this->checkPositionReconStatus($request->user_id),
        ], 200);
    }

    public function payrollDeductionsByEmployeeId(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                'pay_frequency' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID.','.FrequencyType::DAILY_PAY_ID,
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $payroll_id = $request->payroll_id; // payroll_id
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = Payroll::where(['id' => $payroll_id, 'user_id' => $user_id])
            ->when($request->pay_frequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($request) {
                $query->whereBetween('pay_period_from', [$request->pay_period_from, $request->pay_period_to])
                    ->whereBetween('pay_period_to', [$request->pay_period_from, $request->pay_period_to])
                    ->whereColumn('pay_period_from', 'pay_period_to');
            }, function ($query) use ($request) {
                $query->where([
                    'pay_period_from' => $request->pay_period_from,
                    'pay_period_to' => $request->pay_period_to,
                ]);
            })
            ->first();

        $paydata = [];
        $Payroll_status = '';
        if (! empty($payroll)) {

            $Payroll_status = $payroll->status;
            $paydata = PayrollDeductions::with('costcenter:id,name,status', 'payrollcommon')
                ->leftjoin('payroll_adjustment_details', function ($join) {
                    $join->on('payroll_adjustment_details.payroll_id', '=', 'payroll_deductions.payroll_id')
                        ->on('payroll_adjustment_details.cost_center_id', '=', 'payroll_deductions.cost_center_id');
                })
                ->where(function ($query) {
                    $query->where('payroll_deductions.outstanding', '!=', 0)
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('payroll_deductions.outstanding', '=', 0)
                                ->where(function ($q) {
                                    $q->whereNull('payroll_deductions.cost_center_id')
                                        ->orWhereHas('costcenter', function ($q2) {
                                            $q2->where('status', 1);
                                        });
                                });
                        });
                })
                ->where('payroll_deductions.user_id', $payroll->user_id)
                ->where('payroll_deductions.payroll_id', $request->payroll_id)
                ->select('payroll_deductions.*', 'payroll_adjustment_details.amount as adjustment_amount')
                ->get();

            $response_arr = [];
            $subtotal = 0;
            if (count($paydata) > 0) {
                foreach ($paydata as $d) {
                    $dataPayroll = PayrollAdjustmentDetail::where([
                        'payroll_id' => $request->payroll_id,
                        'user_id' => $user_id,
                        'type' => $d->costcenter->name,
                        'payroll_type' => 'deduction',
                    ])->first();

                    $payroll_status = (empty($d->payrollcommon)) ? 'current_payroll' : 'next_payroll';
                    $payroll_calculate = ($d->is_mark_paid == 1 || $d->is_next_payroll == 1 || $d->is_move_to_recon == 1) ? 'ignore' : 'count';

                    $period = (empty($d->payrollcommon)) ? 'current' : (date('m/d/Y', strtotime($d->payrollcommon->orig_payfrom)).' - '.date('m/d/Y', strtotime($d->payrollcommon->orig_payto)));
                    $payroll_modified_date = isset($d->payrollcommon->payroll_modified_date) ? date('m/d/Y', strtotime($d->payrollcommon->payroll_modified_date)) : '';

                    if ($d->is_mark_paid == 0 && $d->is_next_payroll == 0 && $d->is_move_to_recon == 0) {
                        $subtotal += $d->total;
                    }
                    $response_arr[$payroll_status][$period][] = [
                        'id' => $d->id,
                        'payroll_id' => $d->payroll_id,
                        'is_mark_paid' => $d->is_mark_paid,
                        'is_next_payroll' => $d->is_next_payroll,
                        'Type' => $d->costcenter->name,
                        'Amount' => $d->amount,
                        'Limit' => $d->limit,
                        'Total' => $d->total,
                        'Outstanding' => $d->outstanding,
                        'cost_center_id' => $d->cost_center_id,
                        'payroll_modified_date' => ($period == 'current') ? '' : $payroll_modified_date,
                        'adjustment_amount' => isset($d->adjustment_amount) ? $d->adjustment_amount : 0,
                        'is_stop_payroll' => isset($d->is_stop_payroll) ? $d->is_stop_payroll : 0,
                        'is_recon' => isset($payroll->usersdata?->positionDetail?->reconciliation?->status) ? $payroll->usersdata?->positionDetail?->reconciliation?->status : 0,
                        'is_move_to_recon' => $d->is_move_to_recon,
                        // "adjustment_by"=> "Super Admin",
                        'adjustment_comment' => $dataPayroll?->comment,
                        'adjustment_id' => $dataPayroll?->id,
                        'is_onetime_payment' => $d->is_onetime_payment,
                    ];
                }

                $response_arr['subtotal'] = $subtotal;

            }

            // $response = array('list'=>$response_arr , 'subtotal'=>$subtotal);
            $response = $response_arr;

            return response()->json([
                'ApiName' => 'payroll_Deductions_By_EmployeeId',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll_status,
                'is_recon' => isset($payroll->usersdata?->positionDetail?->reconciliation?->status) ? $payroll->usersdata?->positionDetail?->reconciliation?->status : 0,
                'data' => $response,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'payroll_Deductions_By_EmployeeId',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function checkPositionReconStatus($userId)
    {
        $userPosition = User::find($userId);
        $reconPositionData = PositionReconciliations::where('position_id', $userPosition->sub_position_id)->first();
        $reconStatus = false;
        if ($reconPositionData) {
            $reconStatus = $reconPositionData->status === 1 ? 1 : 0;
        }

        return $reconStatus;
    }

    public function updatepayrollDeductionsByEmployeeId(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required', // 11
                'amount' => 'required',
                'cost_center_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $paydata = PayrollDeductions::with('payrolldetails')->where('user_id', $request->user_id)->where('payroll_id', $request->payroll_id)->get();
        // $data = json_encode($paydata);
        $data_arr = [];
        $total_amount = 0;
        foreach ($paydata as $key => $d) {
            $amount = ($request->cost_center_id == $d->cost_center_id) ? $request->amount : $d->amount;
            $total_amount += $amount;
        }
        foreach ($paydata as $key => $d) {
            $amount = ($request->cost_center_id == $d->cost_center_id) ? $request->amount : $d->amount;
            $subtotal = ($d->payrolldetails->commission + $d->payrolldetails->override) * ($d->limit / 100);
            $subtotal = ($total_amount < $subtotal) ? $total_amount : $subtotal;
            $total = round($subtotal * ($amount / $total_amount));
            $limit = $d->limit;
            $outstanding = $amount - $total;
            // Log::info($outstanding);

            $payrolldeduction = PayrollDeductions::where('cost_center_id', $d->cost_center_id)->where('user_id', $request->user_id)->where('payroll_id', $request->payroll_id)->first();
            $payrolldeduction->amount = $amount;
            $payrolldeduction->limit = $limit;
            $payrolldeduction->total = $total;
            $payrolldeduction->outstanding = $outstanding;
            $payrolldeduction->save();
        }

        return response()->json([
            'ApiName' => 'update_payroll_Deductions_By_EmployeeId',
            'status' => true,
            'message' => 'Updated Successfully.',
        ], 200);
    }

    public function deleteAdjustement(Request $request): JsonResponse
    {

        $payrollId = $request->payroll_id;
        $adjustment_details_id = $request->user_id;
        // $adjustment_details_id = $request->adjustment_details_id;
        try {

            $payrollAdjustmentDetail = PayrollAdjustmentDetail::where('payroll_id', $payrollId)
                ->where('id', $adjustment_details_id);

            $data = $payrollAdjustmentDetail->first();

            $delete_adjustment = $payrollAdjustmentDetail->delete();
            if ($delete_adjustment) {
                Payroll::where('id', $payrollId)->update(['status' => 1, 'finalize_status' => 0]);
            }

            $adjustment_amount = PayrollAdjustmentDetail::where('payroll_id', $payrollId)->sum('amount');

            if ($data->payroll_type == 'overrides') {
                PayrollAdjustment::where('payroll_id', $payrollId)->update(['overrides_amount' => $adjustment_amount]);
            }

            if ($data->payroll_type == 'commission') {
                PayrollAdjustment::where('payroll_id', $payrollId)->update(['commission_amount' => $adjustment_amount]);
            }

            if ($data->payroll_type == 'adjustments') {
                PayrollAdjustment::where('payroll_id', $payrollId)->update(['adjustments_amount' => $adjustment_amount]);
            }

            if ($data->payroll_type == 'deduction') {
                PayrollAdjustment::where('payroll_id', $payrollId)->update(['deductions_amount' => $adjustment_amount]);
            }

            if ($data->payroll_type == 'clawbacks') {
                PayrollAdjustment::where('payroll_id', $payrollId)->update(['clawbacks_amount' => $adjustment_amount]);
            }

            if ($data->payroll_type == 'reconciliations') {
                PayrollAdjustment::where('payroll_id', $payrollId)->update(['reconciliations_amount' => $adjustment_amount]);
            }

            $message = 'Deleted Successfully.';

        } catch (Exception $e) {

            $message = $e->getMessage();
        }

        // $payrollId = $request->payroll_id;
        // $userId   = $request->user_id;

        // $payrollAdjustmentDetail = PayrollAdjustmentDetail::where('payroll_id',$payrollId)->where('user_id',$userId)->delete();
        // $adjustmentDetail = PayrollAdjustment::where('payroll_id',$payrollId)->where('user_id',$userId)->delete();

        return response()->json([
            'ApiName' => 'delete adjustement',
            'status' => true,
            'message' => $message,
        ], 200);
    }

    public function finalisePayrollEmail(Request $request)
    {

        $payrollId = $request->payroll_ids;
        // return $payrollId;
        if (count($payrollId) > 0) {
            foreach ($payrollId as $key => $val) {
                //
            }

        }

        return response()->json([
            'ApiName' => 'Finalise Payroll Email',
            'status' => true,
            'message' => 'Send Successfully.',
        ], 200);
    }

    public function payrollReconciliationRollback(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $data = [];
        $payrollId = $request->payroll_id;
        // $period_from = $request->period_from;
        // $period_to = $request->period_to;

        $payroll = Payroll::where(['id' => $payrollId, 'status' => 6])->first();
        if ($payroll) {

            $userReconciliationCommission = UserReconciliationCommission::where('payroll_id', $payrollId)->first();
            if ($userReconciliationCommission) {
                $reconciliationsAdjustement = ReconciliationsAdjustement::where(['reconciliation_id' => $userReconciliationCommission->id, 'adjustment_type' => 'reconciliations'])->where(['start_date' => $userReconciliationCommission->period_from, 'end_date' => $userReconciliationCommission->period_to])->first();
                if ($reconciliationsAdjustement) {
                    ReconciliationsAdjustement::where('id', $reconciliationsAdjustement->id)->delete();
                    // $reconciliationsAdjustement->commission_due = 0;
                    // $reconciliationsAdjustement->save();
                }
                UserReconciliationCommission::where('id', $userReconciliationCommission->id)->delete();
            }

            UserCommission::where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->update(['status' => 1]);
            UserOverrides::where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->update(['status' => 1]);
            Payroll::where(['id' => $payroll->id])->update(['status' => 1]);

        }

        return response()->json([
            'ApiName' => 'payroll_reconciliation_rollback',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
        ], 200);

    }

    public function payrollsReconciliationRollback(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $data = [];
        $payrollId = $request->payroll_id;
        // $period_from = $request->period_from;
        // $period_to = $request->period_to;

        $payroll = Payroll::where(['id' => $payrollId, 'status' => 6])->first();
        if ($payroll) {

            $userReconciliationCommission = UserReconciliationWithholding::where('payroll_id', $payrollId)->first();
            if ($userReconciliationCommission) {
                $userId = isset($userReconciliationCommission->closer_id) ? $userReconciliationCommission->closer_id : $userReconciliationCommission->setter_id;
                $reconciliationsAdjustement = ReconciliationsAdjustement::where(['adjustment_type' => 'reconciliations', 'user_id' => $userId, 'payroll_move_status' => 'from_payroll'])->first();
                if ($reconciliationsAdjustement) {
                    ReconciliationsAdjustement::where('id', $reconciliationsAdjustement->id)->delete();
                    // $reconciliationsAdjustement->commission_due = 0;
                    // $reconciliationsAdjustement->save();
                }
                UserReconciliationWithholding::where('id', $userReconciliationCommission->id)->delete();
            }

            UserCommission::where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->update(['status' => 1]);
            UserOverrides::where(['user_id' => $payroll->user_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->update(['status' => 1]);
            Payroll::where(['id' => $payroll->id])->update(['status' => 1]);
        }

        return response()->json([
            'ApiName' => 'payroll_reconciliation_rollback',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
        ], 200);

    }
}
