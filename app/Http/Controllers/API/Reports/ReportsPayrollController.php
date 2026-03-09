<?php

namespace App\Http\Controllers\API\Reports;

use App\Core\Traits\PayFrequencyTrait;
use App\Exports\ExportPayroll;
use App\Exports\PayrollExport;
use App\Http\Controllers\API\Payroll\PayrollSingleController;
use App\Http\Controllers\Controller;
use App\Models\ApprovalsAndRequest;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\FrequencyType;
use App\Models\GetPayrollData;
use App\Models\Locations;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollHistory;
use App\Models\ReconciliationsAdjustement;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationCommission;
use App\Traits\PushNotificationTrait;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ReportsPayrollController extends Controller
{
    use PayFrequencyTrait;
    use PushNotificationTrait;

    public function __construct(Request $request)
    {
        // $user = auth('api')->user();
    }

    public function payroll_report(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $search = $request->search;
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }

        $payrollHistory = PayrollHistory::where('payroll_history.payroll_id', '!=', 0)
            ->selectRaw('payroll_history.*, payroll_history.created_at as get_date')
            ->with('usersdata', 'positionDetail', 'payroll')
            ->when($search, function ($q) {
                $q->whereHas('usersdata', function ($q) {
                    $q->where('first_name', 'Like', '%'.request()->input('search').'%')->orwhere('last_name', 'Like', '%'.request()->input('search').'%');
                });
            })->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->orderBy(
                User::select('first_name')
                    ->whereColumn('id', 'payroll_history.user_id')
                    ->orderBy('first_name', 'asc')
                    ->limit(1),
                'ASC'
            )->paginate($perpage);

        $payrollHistory->getCollection()->transform(function ($data) {
            if (isset($data->usersdata->image) && $data->usersdata->image != null) {
                $s3_request_url = s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image);
            } else {
                $s3_request_url = null;
            }

            if ($data->everee_payment_status == 3) {
                $everee_webhook_message = 'Payment Success From Everee ';
            } elseif ($data->everee_payment_status == 2 && $data->everee_webhook_json != null && $data->everee_webhook_json != '') {
                $everee_webhook_data = json_decode($data->everee_webhook_json, true);
                if ($everee_webhook_data['paymentStatus'] == 'ERROR') {
                    $everee_webhook_message = $everee_webhook_data['paymentErrorMessage'];
                } else {
                    $everee_webhook_message = $data->everee_webhook_json;
                }
            } elseif ($data->everee_payment_status == 1) {
                $everee_webhook_message = 'Waiting for payment status to be updated.';
            } elseif ($data->everee_payment_status == 0) {
                $everee_webhook_message = 'Everee Setting is Disabled. Payment Done';
            }

            $userIds = $data->user_id;
            $userCommissionPayrollIDs = UserCommissionLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
            $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
            $commissionIds = array_merge($userCommissionPayrollIDs, $ClawbackSettlementPayRollIDS);
            $commission = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $commissionIds)->sum('commission');

            $overridePayrollIDs = UserOverridesLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
            $override = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overridePayrollIDs)->sum('override');
            $reconciliation = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $userCommissionPayrollIDs)->sum('reconciliation');

            $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::where('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

            $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::where('user_id', $userIds)->whereIn('payroll_id', $overridePayrollIDs)->orWhereIn('payroll_id', $userCommissionPayrollIDs)->pluck('payroll_id')->toArray();
            $adjustmentIds = array_merge($approvalsAndRequestPayrollIDs, $PayrollAdjustmentDetailPayRollIDS, $ClawbackSettlementPayRollIDS);
            $miscellaneous = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $adjustmentIds)->sum('adjustment');
            $netPayIDS = array_merge($commissionIds, $overridePayrollIDs, $approvalsAndRequestPayrollIDs, $adjustmentIds);
            $net_pay = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $netPayIDS)->sum('net_pay');

            $reimbursement = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $data->payroll_id)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $approvalsAndRequestPayrollIDs)->sum('reimbursement');

            return [
                'id' => $data->usersdata->id,
                'payroll_id' => $data->payroll_id ?? null,
                'employee' => $data->usersdata->first_name.' '.$data->usersdata->last_name,
                'employee_image' => $data->usersdata->image,
                'employee_image_s3' => $s3_request_url,
                'position' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                'position_id' => isset($data->usersdata->position_id) ? $data->usersdata->position_id : null,
                'sub_position_id' => isset($data->usersdata->sub_position_id) ? $data->usersdata->sub_position_id : null,
                'is_super_admin' => isset($data->usersdata->is_super_admin) ? $data->usersdata->is_super_admin : null,
                'is_manager' => isset($data->usersdata->is_manager) ? $data->usersdata->is_manager : null,
                'commission' => $commission,
                'override' => $override,
                'adjustment' => $miscellaneous,
                'reimbursement' => $reimbursement,
                'clawback' => $data->clawback ?? 0,
                'deduction' => $data->deduction ?? 0,
                'reconciliation' => $reconciliation,
                'net_pay' => $net_pay,
                'everee_payment_status' => $data->everee_payment_status,
                'everee_webhook_json' => isset($everee_webhook_message) ? $everee_webhook_message : $data->everee_webhook_json,
            ];
        });

        return response()->json([
            'ApiName' => 'Payroll_Report_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $payrollHistory,
        ]);

        //        $result = PayrollHistory::with('usersdata','positionDetail','payroll');

        //        if($search)
        //        {
        //            $userids = User::where('first_name','Like','%'.$search.'%')->orwhere('last_name','Like','%'.$search.'%')->pluck('id')->toArray();
        //            $result->where(function($query) use ($userids) {
        //                return $query->whereIn('user_id',$userids);
        //            });
        //        }

        //        if($start_date && $end_date ){
        //            $result->where(function($query) use ($start_date, $end_date) {
        //                return $query->where(['pay_period_from'=> $start_date,'pay_period_to'=>$end_date]);
        //            });
        //        }

        //        $result = $result->where('payroll_id','>',0)->orderBy(
        //            User::select('first_name')
        //            ->whereColumn('id', 'payroll_history.user_id')
        //            ->orderBy('first_name', 'asc')
        //            ->limit(1),
        //            'ASC'
        //        );
        //        $payroll_report = $result->paginate($perpage);

        // $payroll_report = $result->where('payroll_id','>',0)->orderBy('id','desc')->paginate(env('PAGINATE'));

        //        if($payroll_report){
        //            $payroll_report->transform(function ($data) {
        //                if(isset($data->usersdata->image) && $data->usersdata->image!=null){
        //                    $s3_request_url = s3_getTempUrl(config('app.domain_name').'/'.$data->usersdata->image);
        //                }else{
        //                    $s3_request_url = null;
        //                }
        //
        //                if($data->everee_payment_status == 3){
        //                    $everee_webhook_message =  "Payment Success From Everee ";
        //                }
        //                elseif($data->everee_payment_status == 2 && $data->everee_webhook_json!=null && $data->everee_webhook_json!=""){
        //                    $everee_webhook_data = json_decode($data->everee_webhook_json,true);
        //                    if($everee_webhook_data['paymentStatus'] == "ERROR"){
        //                        $everee_webhook_message =  $everee_webhook_data['paymentErrorMessage'];
        //                    }else{
        //                        $everee_webhook_message = $data->everee_webhook_json;
        //                    }
        //                }
        //                elseif($data->everee_payment_status == 1){
        //                    $everee_webhook_message =  "Waiting for payment status to be updated.";
        //                }
        //                elseif($data->everee_payment_status == 0){
        //                    $everee_webhook_message =  "Everee Setting is Disabled. Payment Done";
        //                }
        //
        //                return [
        //                    'id' => $data->usersdata->id,
        //                    'payroll_id' => isset($data->payroll_id) ? $data->payroll_id:null,
        //                    'employee' => $data->usersdata->first_name .' '.$data->usersdata->last_name,
        //                    'employee_image'=> $data->usersdata->image,
        //                    'employee_image_s3'=> $s3_request_url,
        //                    'position' => $data->positionDetail->position_name,
        //                    'position_id' => isset($data->usersdata->position_id) ? $data->usersdata->position_id : null,
        //                    'sub_position_id' => isset($data->usersdata->sub_position_id) ? $data->usersdata->sub_position_id : null,
        //                    'is_super_admin' => isset($data->usersdata->is_super_admin) ? $data->usersdata->is_super_admin : null,
        //                    'is_manager' => isset($data->usersdata->is_manager) ? $data->usersdata->is_manager : null,
        //                    'commission' => $data->commission,
        //                    'override' => $data->override,
        //                    'adjustment' => $data->adjustment,
        //                    'reimbursement' => $data->reimbursement,
        //                    'clawback' => $data->clawback,
        //                    'deduction' => $data->deduction,
        //                    'reconciliation' => $data->reconciliation,
        //                    'net_pay' => $data->net_pay,
        //                    'everee_payment_status' => $data->everee_payment_status,
        //                    'everee_webhook_json' => isset($everee_webhook_message) ? $everee_webhook_message :$data->everee_webhook_json
        //                ];
        //            });
        //
        //            return response()->json([
        //                'ApiName' => 'Payroll_Report_list',
        //                'status' => true,
        //                'message' => 'Successfully.',
        //                'data' =>  $payroll_report,
        //            ], 200);
        //        }
        //        else{
        //                return response()->json([
        //                    'ApiName' => 'Payroll_Report_list',
        //                    'status' => true,
        //                    'message' => 'Successfully.',
        //                    'data' =>  [],
        //
        //                ], 200);
        //        }
    }

    // public function commissionDetails($id)
    // {
    //     $data = array();
    //     $Payroll = GetPayrollData::where('id', $id)->first();
    //     //return $Payroll;
    //     if($Payroll){
    //         $usercommission = UserCommission::with('userdata', 'saledata')->where('status',3)->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();
    //         //$usercommission = UserCommission::with('userdata', 'saledata')->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();
    //         $clawbackSettlement = ClawbackSettlement::with('users', 'salesDetail')->where(['user_id' =>  $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();

    //         $subtotal = 0;
    //         if (count($usercommission) > 0) {
    //             foreach ($usercommission as $key => $value) {
    //                 $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id'=> $id, 'user_id'=> $Payroll->user_id, 'pid'=> $value->pid, 'payroll_type' =>'commission', 'type'=> $value->amount_type])->first();

    //                 if($value->amount_type =='m1'){
    //                     $date = $value->saledata->m1_date;
    //                 }else{
    //                     $date = $value->saledata->m2_date;
    //                 }
    //                 $data['data'][] = [
    //                     'id' => $value->id,
    //                     'pid' => $value->pid,
    //                     'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
    //                     'customer_state' => isset($value->saledata->customer_state) ? $value->saledata->customer_state : null,
    //                     'rep_redline' => isset($value->redline) ? $value->redline : null,
    //                     'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
    //                     'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
    //                     'amount' => isset($value->amount) ? $value->amount : null,
    //                     'date' => isset($date) ? $date : null,
    //                     'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
    //                     'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
    //                     'amount_type' => isset($value->amount_type) ? $value->amount_type : null,
    //                     'adders' => isset($value->adders) ? $value->adders : null,
    //                     'commission_adjustment'=> isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,

    //                 ];
    //                 $subtotal = ($subtotal + $value->amount);
    //             }
    //             $data['subtotal'] = $subtotal;
    //         }

    //         if (count($clawbackSettlement) > 0) {
    //             foreach ($clawbackSettlement as $key1 => $val) {
    //                 $data['data'][] = [
    //                     'id' => $val->id,
    //                     'pid' => $val->pid,
    //                     'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
    //                     'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
    //                     'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
    //                     'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
    //                     'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
    //                     'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
    //                     'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
    //                     'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
    //                     'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
    //                     'amount_type' => 'clawback',
    //                     'adders' => isset($val->adders) ? $val->adders : null,
    //                 ];
    //                 $subtotal = ($subtotal - $val->clawback_amount);
    //             }
    //             $data['subtotal'] = $subtotal;
    //         }
    //             return response()->json([
    //                 'ApiName' => 'commission_details',
    //                 'status' => true,
    //                 'message' => 'Successfully.',
    //                 'payroll_status' => $Payroll->status,
    //                 'data' => $data,
    //             ], 200);
    //     }else{

    //         return response()->json([
    //             'ApiName' => 'commission_details',
    //             'status' => true,
    //             'message' => 'No Records.',
    //             'data' => [],
    //         ], 400);

    //     }

    // }

    public function commissionDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        if (! empty($Payroll)) {
            $usercommission = UserCommissionLock::with('userdata', 'saledata')->where('status', 3)->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            // $usercommission = UserCommission::with('userdata', 'saledata')->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();
            // $clawbackSettlement = ClawbackSettlement::with('users', 'salesDetail')->where(['user_id' =>  $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'status' => 3, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();

            $subtotal = 0;
            if (count($usercommission) > 0) {
                foreach ($usercommission as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type])->first();

                    if ($value->amount_type == 'm1') {
                        $date = $value->saledata->m1_date;
                    } else {
                        $date = $value->saledata->m2_date;
                    }
                    $location = Locations::with('State')->where('general_code', '=', $value->saledata->customer_state)->first();
                    if ($location) {
                        $state_code = $location->state->state_code;
                    } else {
                        $state_code = null;
                    }
                    $data['data'][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'position_id' => $value->position_id,
                        'state_id' => $state_code,
                        'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                        'customer_state' => isset($value->saledata->customer_state) ? $value->saledata->customer_state : null,
                        'rep_redline' => isset($value->redline) ? $value->redline : null,
                        'kw' => isset($value->saledata->kw) ? $value->saledata->kw : null,
                        'net_epc' => isset($value->saledata->net_epc) ? $value->saledata->net_epc : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'date' => isset($date) ? $date : null,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        'amount_type' => isset($value->amount_type) ? $value->amount_type : null,
                        'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                        'commission_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'is_mark_paid' => $value->is_mark_paid,

                    ];
                    $subtotal = ($subtotal + $value->amount);
                }
                $data['subtotal'] = $subtotal;
            }

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $key1 => $val) {
                    $location = Locations::with('State')->where('general_code', '=', $val->salesDetail->customer_state)->first();
                    if ($location) {
                        $state_code = $location->state->state_code;
                    } else {
                        $state_code = null;
                    }
                    $data['data'][] = [
                        'id' => $val->id,
                        'pid' => $val->pid,
                        'state_id' => $state_code,
                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                        'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
                        'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                        'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                        'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                        'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                        'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                        'amount_type' => 'clawback',
                        'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                        'is_mark_paid' => $val->is_mark_paid,
                    ];
                    $subtotal = ($subtotal - $val->clawback_amount);
                }
                $data['subtotal'] = $subtotal;
            }

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);

        }

    }

    // public function overrideDetails($id)
    // {
    //     $data = array();
    //     $Payroll = GetPayrollData::where('id', $id)->first();
    //     $userdata = UserOverrides::where('status',3)->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();

    //     $sub_total = 0;
    //     if($Payroll){
    //         if (count($userdata) > 0) {

    //             foreach ($userdata as $key => $value) {

    //                 $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id'=> $id, 'user_id'=> $Payroll->user_id, 'pid'=> $value->pid, 'payroll_type' =>'overrides', 'type'=> $value->type])->first();

    //                 $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
    //                 $sale = SalesMaster::where(['pid' => $value->pid])->first();
    //                 $sub_total = ($sub_total + $value->amount);
    //                 $data['data'][] = [
    //                     'id' => $value->sale_user_id,
    //                     'pid' => $value->pid,
    //                     'first_name' => isset($user->first_name) ? $user->first_name : null,
    //                     'last_name' => isset($user->last_name) ? $user->last_name : null,
    //                     'image' => isset($user->image) ? $user->image : null,
    //                     'type' => isset($value->type) ? $value->type : null,
    //                     'accounts' => 1,
    //                     'kw_installed' => $value->kw,
    //                     'total_amount' => $value->amount,
    //                     'override_type' => $value->overrides_type,
    //                     'override_amount' => $value->overrides_amount,
    //                     'calculated_redline' => $value->calculated_redline,
    //                     'state' => isset($user->state) ? $user->state->state_code : null,
    //                     'm2_date' => isset($sale->m2_date) ? $sale->m2_date : null,
    //                     'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
    //                     'override_adjustment'=>isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,

    //                 ];
    //             }
    //             $data['sub_total'] = $sub_total;
    //         }

    //         return response()->json([
    //             'ApiName' => 'override_details',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'payroll_status' => $Payroll->status,
    //             'data' => $data,
    //         ], 200);

    //     }else{
    //         return response()->json([
    //             'ApiName' => 'override_details',
    //             'status' => true,
    //             'message' => 'No Records.',
    //             'data' => [],
    //         ], 400);
    //     }
    // }

    // overrideDetails for post request
    public function overrideDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $sub_total = 0;

        if (! empty($Payroll)) {
            $userdata = UserOverridesLock::where('status', 3)->where(['payroll_id' => $Payroll->id, 'user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            if (count($userdata) > 0) {

                foreach ($userdata as $key => $value) {

                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();

                    $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                    $sale = SalesMaster::where(['pid' => $value->pid])->first();
                    $sub_total = ($sub_total + $value->amount);
                    $data['data'][] = [
                        'id' => $value->sale_user_id,
                        'pid' => $value->pid,
                        'first_name' => isset($user->first_name) ? $user->first_name : null,
                        'last_name' => isset($user->last_name) ? $user->last_name : null,
                        'image' => isset($user->image) ? $user->image : null,
                        'type' => isset($value->type) ? $value->type : null,
                        'accounts' => 1,
                        'kw_installed' => $value->kw,
                        'total_amount' => $value->amount,
                        'override_type' => $value->overrides_type,
                        'override_amount' => $value->overrides_amount,
                        'calculated_redline' => $value->calculated_redline,
                        'state' => isset($user->state) ? $user->state->state_code : null,
                        'm2_date' => isset($sale->m2_date) ? $sale->m2_date : null,
                        'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
                        'override_adjustment' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'is_mark_paid' => $value->is_mark_paid,

                    ];
                }
                $data['sub_total'] = $sub_total;
            }

            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    // public function adjustmentDetails($id)
    // {
    //     $data = array();
    //     $payroll = GetPayrollData::where(['id' => $id])->first();
    //     $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6])->get();
    //     $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();

    //     if (count($adjustment) > 0) {
    //         foreach ($adjustment as $key => $value) {
    //             $data[] = [
    //                 'id' => $value->user_id,
    //                 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
    //                 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
    //                 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
    //                 // 'date' => isset($value->cost_date) ? $value->cost_date : null,
    //                 'date' => isset($value->updated_at) ? date('Y-m-d',strtotime($value->updated_at)): null,
    //                 'amount' => isset($value->amount) ? $value->amount : null,
    //                 'type' => isset($value->adjustment) ? $value->adjustment->name : null,
    //                 'description' => isset($value->description) ? $value->description : null,
    //             ];
    //         }
    //     }

    //     if (count($adjustmentNegative) > 0) {
    //         foreach ($adjustmentNegative as $key => $value) {
    //             $data[] = [
    //                 'id' => $value->user_id,
    //                 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
    //                 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
    //                 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
    //                 // 'date' => isset($value->cost_date) ? $value->cost_date : null,
    //                 'date' => isset($value->updated_at) ? date('Y-m-d',strtotime($value->updated_at)): null,
    //                 'amount' => isset($value->amount) ? (0 - $value->amount) : null,
    //                 'type' => isset($value->adjustment) ? $value->adjustment->name : null,
    //                 'description' => isset($value->description) ? $value->description : null,
    //             ];
    //         }
    //     }
    //     // code  start by nikhil

    //     $dataAdjustment = PayrollAdjustment::with('detail')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();

    //     $totalAmount = DB::table('payroll_adjustments')->where(['payroll_id' => $payroll->id])->where('user_id', $payroll->user_id)
    //     ->sum(\DB::raw('commission_amount + overrides_amount + adjustments_amount + reimbursements_amount + deductions_amount + reconciliations_amount + clawbacks_amount'));

    //     if (count( $dataAdjustment) > 0) {

    //         foreach ( $dataAdjustment as $key => $val) {

    //             if($val->commission_amount>0 || $val->commission_amount<0){
    //                 $commission_adjustment = "commission_adjustment= ". $val->commission_amount;
    //             }else{
    //                 $commission_adjustment = "";
    //             }
    //             if($val->overrides_amount>0 || $val->overrides_amount<0){
    //                 $overrides_adjustment = ", overrides_adjustment= ". $val->overrides_amount;
    //             }else{
    //                 $overrides_adjustment = "";
    //             }
    //             if($val->adjustments_amount>0 || $val->adjustments_amount<0){
    //                 $adjustments = ", adjustments= ". $val->adjustments_amount;
    //             }else{
    //                 $adjustments = "";
    //             }
    //             if($val->reimbursements_amount>0 || $val->reimbursements_amount<0){
    //                 $reimbursements_adjustment = ", reimbursements_adjustment= ". $val->reimbursements_amount;
    //             }else{
    //                 $reimbursements_adjustment = "";
    //             }
    //             if($val->deductions_amount>0 || $val->deductions_amount<0){
    //                 $deduction_adjustment = ", deduction_adjustment= ". $val->deductions_amount;
    //             }else{
    //                 $deduction_adjustment = "";
    //             }
    //             if($val->reconciliations_amount>0 || $val->reconciliations_amount<0){
    //                 $reconciliations_adjustment = ", reconciliations_adjustment= ". $val->reconciliations_amount;
    //             }else{
    //                 $reconciliations_adjustment = "";
    //             }
    //             if($val->clawbacks_amount>0 ||$val->clawbacks_amount<0){
    //                 $clawbacks_adjustment = ", clawbacks_adjustment= ". $val->clawbacks_amount;
    //             }else{
    //                 $clawbacks_adjustment = "";
    //             }
    //             $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();

    //             $data[] = [
    //                 'id' => $val->user_id,
    //                 'first_name' => 'Super',
    //                 'last_name' => 'Admin',
    //                 'image' => null,
    //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
    //                 'amount' => isset($totalAmount) ? $totalAmount: null,
    //                 'type' => 'PayrollAdjustment',
    //                 'description'=> $commission_adjustment.$overrides_adjustment.$adjustments.$reimbursements_adjustment.$deduction_adjustment.$reconciliations_adjustment.$clawbacks_adjustment,
    //                 'comment' => isset($comment['comment']) ? $comment['comment'] : null,

    //             ];
    //         }
    //     }

    //     // code end by nikhil

    //     return response()->json([
    //         'ApiName' => 'adjustment_details',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'payroll_status' => $payroll->status,
    //         'data' => $data,

    //     ], 200);

    // }

    public function adjustmentDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        $sub_total = 0;

        if (! empty($payroll)) {

            // $adjustment = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6])->get();
            // $adjustmentNegative = ApprovalsAndRequest::with('user', 'approvedBy', 'adjustment')->where('status', 'Accept')->where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();
            $adjustment = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
            $adjustmentNegative = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();

            if (count($adjustment) > 0) {
                foreach ($adjustment as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            if (count($adjustmentNegative) > 0) {
                foreach ($adjustmentNegative as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                        'amount' => isset($value->amount) ? (0 - $value->amount) : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }
            //  /// Added by Gorakh
            //          $PayrollHistoryPayrollIDs = PayrollHistory::where(['user_id' => $payroll->user_id])->where(['pay_period_from'=> $payroll->pay_period_from,'pay_period_to'=>$payroll->pay_period_to])->pluck('payroll_id');
            $PayrollAdjustmentDetail = PayrollAdjustmentDetailLock::where('payroll_id', $payroll->id)->where(['user_id' => $payroll->user_id])->get();
            // dd($PayrollAdjustmentDetail);
            if (count($PayrollAdjustmentDetail) > 0) {
                foreach ($PayrollAdjustmentDetail as $key => $value) {
                    $checkUserCommission = UserCommissionLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $checkUserOverrides = UserOverridesLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $ClawbackSettlements = ClawbackSettlementLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    if ($checkUserCommission || $checkUserOverrides || $ClawbackSettlements) {
                        $is_mark_paid = 1;

                    } else {
                        $is_mark_paid = 0;
                    }
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => 'Super',
                        'last_name' => 'Admin',
                        'image' => null,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->updated_at) ? date('Y-m-d', strtotime($value->updated_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => $value->payroll_type,
                        'description' => $value->comment,
                        'is_mark_paid' => $is_mark_paid,

                    ];
                }
            }

            // End Gorakh
            // code  start by nikhil

            // $dataAdjustment = PayrollAdjustment::with('detail')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();

            // $totalAmount = DB::table('payroll_adjustments')->where(['payroll_id' => $payroll->id])->where('user_id', $payroll->user_id)
            // ->sum(\DB::raw('commission_amount + overrides_amount + adjustments_amount + reimbursements_amount + deductions_amount + reconciliations_amount + clawbacks_amount'));

            // if (count( $dataAdjustment) > 0) {

            //     foreach ( $dataAdjustment as $key => $val) {

            //         if($val->commission_amount>0 || $val->commission_amount<0){
            //             $commission_adjustment = "commission_adjustment= ". $val->commission_amount;
            //         }else{
            //             $commission_adjustment = "";
            //         }
            //         if($val->overrides_amount>0 || $val->overrides_amount<0){
            //             $overrides_adjustment = ", overrides_adjustment= ". $val->overrides_amount;
            //         }else{
            //             $overrides_adjustment = "";
            //         }
            //         if($val->adjustments_amount>0 || $val->adjustments_amount<0){
            //             $adjustments = ", adjustments= ". $val->adjustments_amount;
            //         }else{
            //             $adjustments = "";
            //         }
            //         if($val->reimbursements_amount>0 || $val->reimbursements_amount<0){
            //             $reimbursements_adjustment = ", reimbursements_adjustment= ". $val->reimbursements_amount;
            //         }else{
            //             $reimbursements_adjustment = "";
            //         }
            //         if($val->deductions_amount>0 || $val->deductions_amount<0){
            //             $deduction_adjustment = ", deduction_adjustment= ". $val->deductions_amount;
            //         }else{
            //             $deduction_adjustment = "";
            //         }
            //         if($val->reconciliations_amount>0 || $val->reconciliations_amount<0){
            //             $reconciliations_adjustment = ", reconciliations_adjustment= ". $val->reconciliations_amount;
            //         }else{
            //             $reconciliations_adjustment = "";
            //         }
            //         if($val->clawbacks_amount>0 ||$val->clawbacks_amount<0){
            //             $clawbacks_adjustment = ", clawbacks_adjustment= ". $val->clawbacks_amount;
            //         }else{
            //             $clawbacks_adjustment = "";
            //         }
            //         $comment = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();

            //         $data[] = [
            //             'id' => $val->user_id,
            //             'first_name' => 'Super',
            //             'last_name' => 'Admin',
            //             'image' => null,
            //             'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //             'amount' => isset($totalAmount) ? $totalAmount: null,
            //             'type' => 'PayrollAdjustment',
            //             'description'=> $commission_adjustment.$overrides_adjustment.$adjustments.$reimbursements_adjustment.$deduction_adjustment.$reconciliations_adjustment.$clawbacks_adjustment,
            //             'comment' => isset($comment['comment']) ? $comment['comment'] : null,

            //         ];
            //     }
            // }

            // code end by nikhil

            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll->status,
                'data' => $data,

            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    // public function reimbursementDetails($id)
    // {
    //     $data = array();
    //     // $payroll = GetPayrollData::where(['id' => $id])->first();
    //     $payroll = PayrollHistory::where(['id' => $id])->first();
    //     $reimbursement = ApprovalsAndRequest::with('user', 'approvedBy')->where(['user_id' => $payroll->user_id, 'status'=> 'Accept', 'adjustment_type_id' => '2'])->where(['pay_period_from'=> $payroll->pay_period_from, 'pay_period_to'=> $payroll->pay_period_to])->get();

    //     if (count($reimbursement) > 0) {
    //         foreach ($reimbursement as $key => $value) {
    //             $data[] = [
    //                 'id' => $value->user_id,
    //                 'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
    //                 'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
    //                 'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
    //                 'date' => isset($value->cost_date) ? $value->cost_date : null,
    //                 'amount' => isset($value->amount) ? $value->amount : null,
    //                 'description' => isset($value->description) ? $value->description : null,
    //             ];
    //         }
    //     }

    //     return response()->json([
    //         'ApiName' => 'reimbursement_details',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'payroll_status' => $payroll->status,
    //         'data' => $data,
    //     ], 200);

    // }

    public function reimbursementDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        $sub_total = 0;

        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequestLock::with('user', 'approvedBy')->where(['payroll_id' => $payroll->id, 'user_id' => $payroll->user_id, 'status' => 'Paid', 'adjustment_type_id' => '2'])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();

            if (count($reimbursement) > 0) {
                foreach ($reimbursement as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll->status,
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }

    }

    public function payrollReconciliationHistory(Request $request)
    {
        $data = [];
        $myArray = [];
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $reconciliation = UserReconciliationCommission::where('status', 'payroll')->where(['period_from' => $startDate, 'period_to' => $endDate]);
        $result = $reconciliation->get();
        // return $result;
        if (count($result) > 0) {
            foreach ($result as $key1 => $val) {
                $userdata = User::where('id', $val->user_id)->first();

                $reconciliationsAdjustment = ReconciliationsAdjustement::where('reconciliation_id', $val->id)->first();
                $commissionDue = isset($reconciliationsAdjustment->commission_due) ? $reconciliationsAdjustment->commission_due : 0;
                $overridesDue = isset($reconciliationsAdjustment->overrides_due) ? $reconciliationsAdjustment->overrides_due : 0;
                $clawbackDue = isset($reconciliationsAdjustment->clawback_due) ? $reconciliationsAdjustment->clawback_due : 0;

                $totalAdjustments = $commissionDue + $overridesDue + $clawbackDue;

                $myArray[] = [
                    'id' => $val->id,
                    'user_id' => $val->user_id,
                    'emp_img' => $userdata->image,
                    'emp_name' => $userdata->first_name.' '.$userdata->last_name,
                    'commissionWithholding' => $val->amount,
                    'overrideDue' => $val->overrides,
                    'clawbackDue' => $val->clawbacks,
                    'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                    'total_due' => $val->total_due,
                    'pay_period_from' => $val->pay_period_from,
                    'pay_period_to' => $val->pay_period_to,
                ];
            }
        }

        $data = $this->paginate($myArray);

        return response()->json([
            'ApiName' => 'payrollReconciliationHistory',
            'status' => true,
            'message' => 'Successfully.',
            'finalize_status' => 1,
            'data' => $data,
        ], 200);

    }

    public function paginate($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function getReportSummaryPayroll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'frequency_type_id' => 'required|in:'.FrequencyType::WEEKLY_ID.','.FrequencyType::MONTHLY_ID.','.FrequencyType::BI_WEEKLY_ID.','.FrequencyType::SEMI_MONTHLY_ID,
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        return (new PayrollSingleController)->getPayrollDataSummary($request, PayrollHistory::class);
    }

    public function reportYearMonthFrequencyWise(Request $request)
    {
        $commissions = 0;
        $override = 0;
        $deduction = 0;
        $reconciliation = 0;
        $adjustment = 0;
        $totalPay = 0;
        $reimbursement = 0;
        $overtime = 0;
        $hourlysalary = 0;
        $payrollList = [];

        if ($request->year && $request->frequency_type) {
            $payrollHistory_query = PayrollHistory::whereYear('payroll_history.created_at', request()->input('year'))
                ->where('payroll_history.payroll_id', '!=', 0);

            $frequency = FrequencyType::find($request->frequency_type);
            if ($frequency->name == 'Weekly') {
                $payrollHistory_query = $payrollHistory_query->leftJoin('weekly_pay_frequencies', function ($join) {
                    $join->on('weekly_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
                        ->on('weekly_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
                })->where('weekly_pay_frequencies.closed_status', 1);
            } elseif ($frequency->name == 'Monthly') {
                $payrollHistory_query = $payrollHistory_query->leftJoin('monthly_pay_frequencies', function ($join) {
                    $join->on('monthly_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
                        ->on('monthly_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
                })->where('monthly_pay_frequencies.closed_status', 1);
            } elseif ($frequency->name == 'Bi-Weekly') {
                $payrollHistory_query = $payrollHistory_query->leftJoin('additional_pay_frequencies', function ($join) {
                    $join->on('additional_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
                        ->on('additional_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
                })->where(['additional_pay_frequencies.closed_status' => 1, 'type' => '1']);
            } elseif ($frequency->name == 'Semi-Monthly') {
                $payrollHistory_query = $payrollHistory_query->leftJoin('additional_pay_frequencies', function ($join) {
                    $join->on('additional_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
                        ->on('additional_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
                })->where(['additional_pay_frequencies.closed_status' => 1, 'type' => '2']);
            }

            if ($request->pay_period_from_month != 'all') {
                $payrollHistory_query = $payrollHistory_query->where(function (Builder $q) {
                    //                    $q->orWhereMonth('payroll_history.pay_period_from', request()->input('pay_period_from_month'));
                    //                    $q->orWhereMonth('payroll_history.pay_period_to', request()->input('pay_period_from_month'));
                    $q->whereMonth('payroll_history.created_at', request()->input('pay_period_from_month'));
                });
            }

            $payrollHistory = $payrollHistory_query->orderBy('payroll_history.pay_period_from', 'desc')
                ->groupBy(['payroll_history.pay_period_from', 'payroll_history.pay_period_to'])
                ->selectRaw('payroll_history.*, sum(payroll_history.commission) as commission, sum(payroll_history.override) as override,
                    sum(payroll_history.reimbursement) as reimbursement, sum(payroll_history.clawback) as clawback, sum(payroll_history.deduction) as deduction,
                    sum(payroll_history.adjustment) as adjustment, sum(payroll_history.reconciliation) as reconciliation, sum(payroll_history.net_pay) as net_pay,
                    payroll_history.created_at as get_date, GROUP_CONCAT(payroll_history.user_id) as user_id, GROUP_CONCAT(payroll_history.payroll_id) as payroll_id')
                ->orderBy('payroll_history.id', 'DESC')->get();

            foreach ($payrollHistory as $data) {
                //                $userIds = $data->user_id;
                //                $userCommissionPayrollIDs = UserCommissionLock::where('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::where('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $commission1 = PayrollHistory::where('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->whereNotIn('payroll_id',$userCommissionPayrollIDs)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->sum('commission');
                //
                //                $overridePayrollIDs = UserOverridesLock::where('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $override1 = PayrollHistory::where('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->whereNotIn('payroll_id',$overridePayrollIDs)->sum('override');
                //                $reconciliation1 = PayrollHistory::where('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->whereNotIn('payroll_id',$userCommissionPayrollIDs)->sum('reconciliation');

                //                $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::where('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'Paid'])->pluck('payroll_id');
                //                $UserCommissionPayrollADS = UserCommissionLock::where('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $UserOverridesPayRollIDs = UserOverridesLock::where('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //
                //                $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::where('user_id', $userIds)->whereIn('payroll_id', $UserOverridesPayRollIDs)->orWhereIn('payroll_id', $UserCommissionPayrollADS)->pluck('payroll_id');
                //                $miscellaneous1 = PayrollHistory::where('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->where('payroll_id','!=',0)->whereNotIn('payroll_id',$approvalsAndRequestPayrollIDs)->whereNotIn('payroll_id',$PayrollAdjustmentDetailPayRollIDS)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->sum('adjustment');
                //                $net_pay2 = PayrollHistory::where('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->where('payroll_id','!=',0)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->whereNotIn('payroll_id',$approvalsAndRequestPayrollIDs)->whereNotIn('payroll_id',$UserCommissionPayrollADS)->whereNotIn('payroll_id',$UserOverridesPayRollIDs)->sum('net_pay');

                $userIds = explode(',', $data->user_id);
                $payrollIds = explode(',', $data->payroll_id);
                $userCommissionPayrollIDs = UserCommissionLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $commissionIds = array_merge($userCommissionPayrollIDs, $ClawbackSettlementPayRollIDS);
                $commission1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $commissionIds)->sum('commission');

                // hourlysalary
                $hourlysalaryPayrollIDs = PayrollHourlySalaryLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $hourlysalary1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $hourlysalaryPayrollIDs)->sum('hourly_salary');

                // overtime
                $overtimePayrollIDs = PayrollOvertimeLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $overtime1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overtimePayrollIDs)->sum('overtime');

                $overridePayrollIDs = UserOverridesLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();
                $override1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $overridePayrollIDs)->sum('override');
                $reconciliation1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $userCommissionPayrollIDs)->sum('reconciliation');

                $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::whereIn('user_id', $userIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

                $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::whereIn('user_id', $userIds)->whereIn('payroll_id', $overridePayrollIDs)->orWhereIn('payroll_id', $userCommissionPayrollIDs)->pluck('payroll_id')->toArray();
                $adjustmentIds = array_merge($approvalsAndRequestPayrollIDs, $PayrollAdjustmentDetailPayRollIDS, $ClawbackSettlementPayRollIDS);
                $miscellaneous1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $adjustmentIds)->sum('adjustment');
                $netPayIDS = array_merge($commissionIds, $overridePayrollIDs, $approvalsAndRequestPayrollIDs, $adjustmentIds);
                $net_pay2 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $netPayIDS)->sum('net_pay');

                $reimbursement1 = PayrollHistory::whereIn('user_id', $userIds)->whereIn('payroll_id', $payrollIds)->where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'status' => '3'])->whereIn('payroll_id', $approvalsAndRequestPayrollIDs)->sum('reimbursement');

                //                $userCommissionPayrollIDs = UserCommissionLock::whereIn('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::whereIn('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $commission1 = PayrollHistory::whereIn('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->whereNotIn('payroll_id',$userCommissionPayrollIDs)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->sum('commission');
                //
                //                $overridePayrollIDs = UserOverridesLock::whereIn('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $override1 = PayrollHistory::whereIn('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->whereNotIn('payroll_id',$overridePayrollIDs)->sum('override');
                //                $reconciliation1 = PayrollHistory::whereIn('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->whereNotIn('payroll_id',$userCommissionPayrollIDs)->sum('reconciliation');
                //
                //                $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::whereIn('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'Paid'])->pluck('payroll_id');
                //                $UserCommissionPayrollADS = UserCommissionLock::whereIn('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //                $UserOverridesPayRollIDs = UserOverridesLock::whereIn('user_id', $userIds)->where(['pay_period_from'=> $data->pay_period_from, 'pay_period_to'=> $data->pay_period_to, 'is_mark_paid'=> '1','status'=>'3'])->pluck('payroll_id');
                //
                //                $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::whereIn('user_id', $userIds)->whereIn('payroll_id', $UserOverridesPayRollIDs)->orWhereIn('payroll_id', $UserCommissionPayrollADS)->pluck('payroll_id');
                //                $miscellaneous1 = PayrollHistory::whereIn('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->where('payroll_id','!=',0)->whereNotIn('payroll_id',$approvalsAndRequestPayrollIDs)->whereNotIn('payroll_id',$PayrollAdjustmentDetailPayRollIDS)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->sum('adjustment');
                //                $net_pay2 = PayrollHistory::whereIn('user_id', $userIds)->where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'status'=>'3'])->where('payroll_id','!=',0)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->whereNotIn('payroll_id',$approvalsAndRequestPayrollIDs)->whereNotIn('payroll_id',$UserCommissionPayrollADS)->whereNotIn('payroll_id',$UserOverridesPayRollIDs)->sum('net_pay');

                $commissions += ($commission1);
                $override += ($override1);
                $adjustment += ($miscellaneous1);
                $reconciliation += ($reconciliation1);
                $deduction += ($data->deduction);
                $totalPay += ($net_pay2);
                $reimbursement += ($reimbursement1);
                $hourlysalary += ($hourlysalary1);
                $overtime += ($overtime1);

                $payrollList[] = [
                    'commission' => isset($commission1) ? $commission1 : '0',
                    'override' => isset($override1) ? $override1 : '0',
                    'hourlysalary' => isset($hourlysalary1) ? $hourlysalary1 : '0',
                    'overtime' => isset($overtime1) ? $overtime1 : '0',
                    'adjustment' => isset($miscellaneous1) ? $miscellaneous1 : '0',
                    'reconciliation' => isset($reconciliation1) ? $reconciliation1 : '0',
                    'deduction' => isset($data->deduction) ? $data->deduction : '0',
                    'netPay' => isset($net_pay2) ? $net_pay2 : '0',
                    'reimbursement' => isset($reimbursement1) ? $reimbursement1 : '0',
                    'payroll_date' => isset($data->get_date) ? date('Y-m-d', strtotime($data->get_date)) : null,
                    'pay_period_from' => $data->pay_period_from,
                    'pay_period_to' => $data->pay_period_to,
                ];
            }
        }

        $data = [
            'year' => $request->year,
            'total_commissions' => $commissions,
            'total_override' => $override,
            'total_hourlysalary' => $hourlysalary,
            'total_overtime' => $overtime,
            'total_adjustment' => $adjustment,
            'total_reconciliation' => $reconciliation,
            'total_deduction' => $deduction,
            'total_Pay' => $totalPay,
            'total_reimbursement' => $reimbursement,
            'payroll_report' => $payrollList,
        ];

        if (isset($request->is_export) && ($request->is_export == 1)) {
            $file_name = 'payroll_export_'.date('Y_m_d_H_i_s').'.xlsx';
            Excel::store(new \App\Exports\ExportPayroll\ExportPayroll($payrollList),
                'exports/payroll/exports/payroll/'.$file_name,
                'public',
                \Maatwebsite\Excel\Excel::XLSX);

            $url = getStoragePath('payroll/exports/payroll/'.$file_name);

            // $url = getStoragePath('exports/payroll/exports/payroll/' . $file_name);
            // $url = getExportBaseUrl().'storage/exports/payroll/exports/payroll/' . $file_name;
            return response()->json(['url' => $url]);

            // return Excel::download(new ExportPayroll($payrollList), $file_name);
        } else {
            return response()->json([
                'ApiName' => 'Report Year Month And Frequency Api',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        }

        //        $startYear = isset($request->pay_period_from_year)?$request->pay_period_from_year:'';
        //        $month   = isset($request->pay_period_from_month)?$request->pay_period_from_month:'';
        //        $frequency   = isset($request->frequency_type)?$request->frequency_type:'';
        //        $commissions = 0;
        //        $override = 0;
        //        $deduction = 0;
        //        $reconciliation = 0;
        //        $adjustment = 0;
        //        $totalPay = 0;
        //        $payrollList = [];
        //        $getFilterdata=[];
        //        if($startYear!='' && $month!='all' && $frequency!='')
        //        {
        //            $getFilterdata = PayrollHistory::
        //            selectRaw("sum(commission) as commission,sum(override) as override,sum(adjustment) as adjustment,sum(reconciliation) as reconciliation,
        //            sum(deduction) as deduction,sum(net_pay) as net_pay,pay_frequency_date,pay_period_from,pay_period_to,payroll_history.created_at as get_date")
        //            ->join('position_pay_frequencies','position_pay_frequencies.position_id','=','payroll_history.position_id')
        //            ->with('usersdata','positionDetail')
        //            ->whereYear('pay_period_from',$startYear)
        //            ->whereMonth('pay_period_from',$month)
        //            ->where('frequency_type_id',$frequency)
        //            ->groupBy('pay_period_from')
        //            ->groupBy('pay_period_to')
        //            ->orderBy('payroll_history.id','DESC')
        //            ->get();
        //        }else
        //        if($startYear!='' && $month == 'all' && $frequency!='')
        //        {
        //            $getFilterdata = PayrollHistory::
        //            selectRaw("sum(commission) as commission,sum(override) as override,sum(adjustment) as adjustment,sum(reconciliation) as reconciliation,
        //            sum(deduction) as deduction,sum(net_pay) as net_pay,pay_frequency_date,pay_period_from,pay_period_to,payroll_history.created_at as get_date")
        //            ->join('position_pay_frequencies','position_pay_frequencies.position_id','=','payroll_history.position_id')
        //            ->with('usersdata','positionDetail')
        //            ->whereYear('pay_period_from',$startYear)
        //            ->where('frequency_type_id',$frequency)
        //            ->groupBy('pay_period_from')
        //            ->groupBy('pay_period_to')
        //            ->orderBy('payroll_history.id','DESC')
        //            ->get();
        //        }
        //
        //        foreach ($getFilterdata as $key => $value) {
        //
        //            $commissions += ($value->commission);
        //            $override += ($value->override);
        //            $adjustment += ($value->adjustment);
        //            $reconciliation += ($value->reconciliation);
        //            $deduction += ($value->deduction);
        //            $totalPay += ($value->net_pay);
        //            $payrollList[] = [
        //                'commission' => isset($value->commission) ? $value->commission :'0',
        //                'override' => isset($value->override) ? $value->override :'0',
        //                'adjustment' => isset($value->adjustment) ? $value->adjustment :'0',
        //                'reconciliation' => isset($value->reconciliation) ? $value->reconciliation :'0',
        //                'deduction' => isset($value->deduction) ? $value->deduction :'0',
        //                'netPay' => isset($value->net_pay) ? $value->net_pay :'0',
        //                'payroll_date' =>isset($value->get_date) ? date('Y-m-d',strtotime($value->get_date)): null,
        //                'pay_period_from' => $value->pay_period_from,
        //                'pay_period_to' => $value->pay_period_to,
        //            ];
        //        }
        //
        //        $data =[
        //            'year'=>$startYear,
        //            'total_commissions'=>$commissions,
        //            'total_override'=>$override,
        //            'total_adjustment'=>$adjustment,
        //            'total_reconciliation'=>$reconciliation,
        //            'total_deduction'=>$deduction,
        //            'total_Pay'=>$totalPay,
        //            'payroll_report'=>$payrollList,
        //        ];
        //       // $data = paginate($data,$perpage);
        //      if(isset($request->is_export) && ($request->is_export == 1))
        //      {
        //        $file_name = 'payroll_export_'.date('Y_m_d_H_i_s').'.csv';
        //       return Excel::download(new PayrollExport($getFilterdata), $file_name);
        //      }
        //      else
        //      {
        //        return response()->json([
        //            'ApiName' => 'Report Year Month And Frequency Api',
        //            'status' => true,
        //            'message' => 'Successfully.',
        //            'data' => $data
        //          ], 200);
        //      }
    }
}
