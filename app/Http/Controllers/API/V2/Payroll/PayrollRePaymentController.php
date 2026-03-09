<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\User;
use App\Models\Payroll;
use Illuminate\Http\Request;
use App\Models\FrequencyType;
use Illuminate\Support\Carbon;
use App\Models\ApprovalsAndRequest;
use App\Http\Controllers\Controller;
use App\Models\AdvancePaymentSetting;
use App\Core\Traits\PayFrequencyTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayrollRePaymentController extends Controller
{
    use PayFrequencyTrait;

    public function paymentRequestList(Request $request)
    {
        try {
            $perPage = 10;
            $requestType = $request->type;
            if (!empty($request->perpage)) {
                $perPage = $request->perpage;
            }

            $paymentRequest = ApprovalsAndRequest::with('user.positionPayFrequency.frequencyType', 'approvedBy', 'adjustment')->whereNotNull('req_no')
                ->whereNotIn('adjustment_type_id', [7, 8, 9]);

            if ($request->has('filter') && !empty($request->input('filter'))) {
                $filter = $request->input('filter');
                $paymentRequest->whereHas('adjustment', function ($query) use ($filter) {
                    return $query->where('name', 'like', '%' . $filter . '%');
                });
            }

            if ($request->has('user_id') && !empty($request->input('user_id'))) {
                $userId = $request->input('user_id');
                $paymentRequest->where('user_id', $userId);
            }

            if ($request->has('search') && !empty($request->input('search'))) {
                $search = $request->input('search');
                $paymentRequest->where(function ($query) use ($search) {
                    $query->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' . $search . '%']);
                    })->orWhere('req_no', 'like', '%' . $search . '%');
                });
            }

            if ($requestType == "PaymentRequest") {
                $paymentRequest->where('adjustment_type_id', '!=', '4')->where('status', 'Approved');
            } else if ($requestType == "AdvancePaymentRequest") {
                $paymentRequest->where(['adjustment_type_id' => '4', 'status' => 'Approved']);
            } else if ($requestType == "Both" || $requestType == "both") {
                $paymentRequest->where('status', 'Approved');
            }

            if ($request->has('sort') && !empty($request->input('sort'))) {
                $sortField = $request->input('sort');
                $sortDirection = $request->input('sort_val', 'asc');

                if ($sortField == 'requested_on') {
                    $paymentRequest->orderBy('created_at', $sortDirection);
                } elseif ($sortField == 'amount') {
                    $paymentRequest->orderBy('amount', $sortDirection);
                } else {
                    $paymentRequest->orderBy('id', 'desc');
                }
            } else {
                $paymentRequest->orderBy('id', 'desc');
            }

            $paymentRequestData = $paymentRequest->paginate($perPage);
            $paymentRequestData->transform(function ($value) {
                $imageS3 = null;
                if (isset($value->user->image) && $value->user->image && $value->user->image != 'Employee_profile/default-user.png') {
                    $imageS3 = s3_getTempUrl(config('app.domain_name') . '/' . $value->user->image);
                }

                $amount = $value->amount;
                if ($value->adjustment_type_id == 5) {
                    $amount = (0 - $value->amount);
                }

                return [
                    'id' => $value->id,
                    'req_no' => $value->req_no,
                    'user_id' => $value->user_id,
                    'first_name' => $value?->user?->first_name ?? null,
                    'last_name' => $value?->user?->last_name ?? null,
                    'position_id' => $value?->user?->position_id ?? null,
                    'sub_position_id' => $value?->user?->sub_position_id ?? null,
                    'frequency_type_id' => $value?->user?->positionPayFrequency?->frequency_type_id ?? null,
                    'frequency_type_name' => $value?->user?->positionPayFrequency?->frequencyType?->name ?? null,
                    'is_super_admin' => $value?->user?->is_super_admin ?? null,
                    'is_manager' => $value?->user?->is_manager ?? null,
                    'image' => $value?->user?->image ?? null,
                    'image_s3' => $imageS3,
                    'approved_by' => $value?->approvedBy?->first_name . ' ' . $value?->approvedBy?->last_name ?? null,
                    'request_on' => $value->created_at ? $value->created_at->format('Y-m-d') : null,
                    'amount' => $amount,
                    'type' => $value->adjustment->name ?? null,
                    'description' => $value->description ?? null,
                    'adjustment_type_id' => $value->adjustment_type_id ?? null,
                    'is_stop_payroll' => $value?->user?->stop_payroll ?? 0,
                    'is_onetime_payment' => $value->is_onetime_payment ?? 0,
                    'worker_type' => $value?->user?->worker_type ?? null,
                    'status' => $value->status ?? null,
                    'payroll_id' => $value->payroll_id ?? null,
                    'pay_period_from' => $value->pay_period_from ?? null,
                    'pay_period_to' => $value->pay_period_to ?? null
                ];
            });

            return response()->json([
                'ApiName' => 'payment_request',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $paymentRequestData
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ApiName' => 'payment_request',
                'status' => false,
                'message' => 'An error occurred while fetching payment requests: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function negativePaymentRequestList(Request $request)
    {
        try {
            $page = 1;
            $perPage = 10;
            if (!empty($request->page)) {
                $page = $request->page;
            }
            if (!empty($request->perpage)) {
                $perPage = $request->perpage;
            }
            $sortColumnName = 'created_at';
            $sortType = isset($request->sort_val) ? $request->sort_val : 'asc';
            if (isset($request->sort) && $request->sort == 'amount') {
                $sortColumnName = 'total_amount';
            } else if (isset($request->sort) && $request->sort == 'age') {
                $sortColumnName = 'daysDifference';
            }
            $searchText = $request->search;
            if (!in_array($sortType, ["asc", "desc"])) {
                return response()->json([
                    'ApiName' => 'advance negative payment requests',
                    'status' => false,
                    'message' => "Parameter is wrong",
                    'data' => []
                ], 400);
            }

            $data = User::with('positionpayfrequencies.frequencyType')->whereHas('ApprovalsAndRequests', function ($query) {
                $query->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved');
            })->where(function ($query) use ($searchText) {
                $query->where('first_name', 'LIKE', '%' . $searchText . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $searchText . '%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' . $searchText . '%'])
                    ->orWhereHas('ApprovalsAndRequests.ChildApprovalsAndRequests', function ($query) use ($searchText) {
                        $query->where('req_no', 'LIKE', '%' . $searchText . '%');
                    });
            })->with('ApprovalsAndRequests.approvedBy:id,first_name,last_name,image,manager_id,position_id,sub_position_id,is_super_admin')
                ->with('ApprovalsAndRequests', function ($query) {
                    $query->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved')->select('id', 'parent_id', 'user_id', 'amount', 'txn_id', 'created_at', 'approved_by');
                })->select('first_name', 'last_name', 'image', 'id', 'manager_id', 'position_id', 'sub_position_id', 'is_super_admin', 'worker_type')
                ->when($request->user_id, function ($q) use ($request) {
                    $q->where("id", $request->user_id);
                })->get();

            $data->transform(function ($value) {
                $value->image = null;
                if (isset($value->image) && $value->image && $value->image != 'Employee_profile/default-user.png') {
                    $value->image = s3_getTempUrl(config('app.domain_name') . '/' . $value->image);
                }

                $value->ApprovalsAndRequests->transform(function ($req) {
                    $childRequestAmount = ApprovalsAndRequest::where('parent_id', $req->id)->sum('amount');
                    $reqAmount = $req->amount - $childRequestAmount;
                    $req->amount = $reqAmount;
                    if (!$req->txn_id) {
                        $reqData = ApprovalsAndRequest::where('id', $req->parent_id)->whereNull('txn_id')->first();
                        $req->req_no = $reqData?->req_no;
                    } else {
                        $req->req_no = $req->txn_id;
                    }
                    $image = null;
                    if (isset($req->approvedBy->image) && $req->approvedBy->image && $req->approvedBy->image != 'Employee_profile/default-user.png') {
                        $image = s3_getTempUrl(config('app.domain_name') . '/' . $req->approvedBy->image);
                    }
                    $req->approvedBy->s3Image = $image;
                    return $req;
                });

                $payFrequency = $this->openPayFrequency($value->sub_position_id, $value->id);
                $param = [
                    "pay_frequency" => $payFrequency?->pay_frequency,
                    "worker_type" => $value->worker_type,
                    "pay_period_from" => $payFrequency?->pay_period_from,
                    "pay_period_to" => $payFrequency?->pay_period_to
                ];

                $value->current_payroll = 0;
                $payroll = Payroll::applyFrequencyFilter($param, ['user_id' => $value->id])->first();
                if ($payroll) {
                    $value->current_payroll = $payroll->net_pay;
                }
                $value->total_request = count($value->ApprovalsAndRequests);
                $value->total_amount  = $value->ApprovalsAndRequests->sum('amount');
                $date = Carbon::parse($value->ApprovalsAndRequests->min('created_at'));
                $value->frequency_type_id = isset($value->positionpayfrequencies->frequency_type_id) ? $value->positionpayfrequencies->frequency_type_id : null;
                $value->frequency_type_name = isset($value->positionpayfrequencies->frequencyType->name) ? $value->positionpayfrequencies->frequencyType->name : null;
                $currentDate = Carbon::now();
                $value->daysDifference = $date->diffInDays($currentDate) . ' days';
                return $value;
            });

            // Sort data dynamically based on custom key
            if ($sortType == "asc") {
                $sortedData = $data->sortBy($sortColumnName);
            } else {
                $sortedData = $data->sortByDesc($sortColumnName);
            }

            // Ensure you reset the keys after sorting if necessary
            $data = $sortedData->values();
            $data = paginate($data->toArray(), $perPage, $page);

            return response()->json([
                'ApiName' => 'negative-payment-request-list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'negative-payment-request-list',
                'status' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => []
            ], 400);
        }
    }

    public function paymentRequestUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'request_ids' => 'required|array|min:1',
            'pay_periods.*.frequency_type_id' => 'required',
            'pay_periods.*.pay_period_from' => 'required',
            'pay_periods.*.pay_period_to' => 'required',
            'pay_periods.*.worker_type' => 'required|in:1099,w2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "update-payment-request",
                "error" => $validator->errors()
            ], 400);
        }

        $paymentRequest = $request->request_ids;
        $status = $request->type;
        $approvalAndRequests = ApprovalsAndRequest::with('payrollUser')->whereIn('id', $paymentRequest)->where('status', 'Approved')->get();
        foreach ($approvalAndRequests as $approvalAndRequest) {
            $user = $approvalAndRequest->payrollUser;
            if ($user && $user->stop_payroll == 0 && $status != "Declined") {
                $payPeriods = $request->pay_periods;
                foreach ($payPeriods as $payPeriod) {
                    $payPeriod = (array) $payPeriod;
                    if ($payPeriod['frequency_type_id'] == FrequencyType::DAILY_PAY_ID) {
                        $payPeriodFrom = $payPeriod['pay_period_to'];
                        $payPeriodTo = $payPeriod['pay_period_to'];
                    } else {
                        $payPeriodFrom = $payPeriod['pay_period_from'];
                        $payPeriodTo = $payPeriod['pay_period_to'];
                    }
                    $workerType = $payPeriod['worker_type'];
                    $frequencyTypeId = $payPeriod['frequency_type_id'];
                }

                $declinedAt = NULL;
                if (isset($request->declined_at) && $request->declined_at) {
                    $declinedAt = $request->declined_at;
                }

                $param = [
                    "pay_frequency" => $frequencyTypeId,
                    "worker_type" => $workerType,
                    "pay_period_from" => $payPeriodFrom,
                    "pay_period_to" => $payPeriodTo
                ];
                $check = Payroll::applyFrequencyFilter($param, ["status" => 2])->whereIn("finalize_status", [1, 2])->count();
                if ($check) {
                    return response()->json([
                        'ApiName' => 'update-payment-request',
                        'status' => false,
                        'message' => 'Cannot send to payroll. this pay period has been Already Finalize for this employee.'
                    ], 400);
                }

                $approvalAndRequest->status = $status;
                $approvalAndRequest->pay_period_from = $payPeriodFrom;
                $approvalAndRequest->pay_period_to = $payPeriodTo;
                $approvalAndRequest->declined_at = $declinedAt;
                $approvalAndRequest->save();
            } else if ($status == 'Declined') {
                $declinedAt = isset($request->declined_at) ? $request->declined_at : null;
                $update = [
                    'status' => $status,
                    'declined_at' => $declinedAt
                ];
                $paymentRequest = ApprovalsAndRequest::where(['id' => $approvalAndRequest->id])->update($update);
            }
        }

        return response()->json([
            'ApiName' => 'update-payment-request',
            'status' => true,
            'message' => 'Successfully.'
        ]);
    }

    public function undoPaymentRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required',
            'pay_period_from' => 'required',
            'pay_period_to' => 'required',
            'worker_type' => 'required|in:1099,w2',
            'pay_frequency' => 'required|in:' . FrequencyType::WEEKLY_ID . ',' . FrequencyType::MONTHLY_ID . ',' . FrequencyType::BI_WEEKLY_ID . ',' . FrequencyType::SEMI_MONTHLY_ID . ',' . FrequencyType::DAILY_PAY_ID
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "undo-payment-request",
                "error" => $validator->errors()
            ], 400);
        }

        $workerType = $request->worker_type;
        $frequencyTypeId = $request->pay_frequency;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $param = [
            "pay_frequency" => $frequencyTypeId,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];
        $check = Payroll::applyFrequencyFilter($param, ["status" => 2])->whereIn("finalize_status", [1, 2])->count();
        if ($check) {
            return response()->json([
                "status" => false,
                "ApiName" => "undo-payment-request",
                "message" => 'You cannot undo this request because payroll has been finalized or is in the process of being finalized.'
            ], 400);
        }

        $approvalsAndRequest = ApprovalsAndRequest::applyFrequencyFilter($param, ['id' => $request->request_id, 'status' => 'Accept'])->first();
        if (!$approvalsAndRequest) {
            return response()->json([
                "status" => false,
                "ApiName" => "undo-payment-request",
                "message" => 'Request not found'
            ], 400);
        }

        if ($approvalsAndRequest->adjustment_type_id == 4 && !$approvalsAndRequest->req_no) {
            $advanceSetting = AdvancePaymentSetting::first();
            if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                return response()->json([
                    "status" => false,
                    "ApiName" => "undo-payment-request",
                    "message" => "You cannot undo the advance negative drawback request while the Advance Settings is Automatically deduct from the next available payroll"
                ], 400);
            } else {
                ApprovalsAndRequest::where('id', $approvalsAndRequest->parent_id)->update([
                    'pay_period_from' => null,
                    'pay_period_to' => null,
                    'payroll_id' => 0,
                    'ref_id' => 0,
                    'is_next_payroll' => 0,
                    'is_mark_paid' => 0,
                    'status' => 'Approved'
                ]);

                $approvalsAndRequest->delete();
            }
        } else {
            $approvalsAndRequest->pay_period_from = null;
            $approvalsAndRequest->pay_period_to = null;
            $approvalsAndRequest->payroll_id = 0;
            $approvalsAndRequest->ref_id = 0;
            $approvalsAndRequest->is_next_payroll = 0;
            $approvalsAndRequest->is_mark_paid = 0;
            $approvalsAndRequest->status = 'Approved';
            $approvalsAndRequest->save();
        }

        return response()->json([
            "status" => true,
            "ApiName" => "undo-payment-request",
            "message" => "Request undone successfully."
        ]);
    }

    public function advanceRepayment(Request $request)
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'repay_type' => 'required',
                'req_id' => 'required',
                'pay_period_from' => 'required|date',
                'pay_period_to' => 'required|date|after:pay_period_from',
                'pay_frequency' => 'required',
                'worker_type' => 'required'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return response()->json([
                    "status" => false,
                    "ApiName" => "advance-repayment",
                    "error" => $validator->errors()
                ], 400);
            }

            $payPeriodFrom = $request->pay_period_from;
            $payPeriodTo = $request->pay_period_to;
            $workerType = $request->worker_type;
            $frequencyTypeId = $request->pay_frequency;
            if ($request->repay_type == 'repay_all') {
                $user = User::with('positionpayfrequencies')->where('id', $request->req_id)->first();
                if (!$user) {
                    DB::rollBack();
                    return response()->json([
                        'ApiName' => 'advance-repayment',
                        'status' => false,
                        'message' => 'User not found!!'
                    ], 400);
                }
            } else {
                $approvalAndRequest = ApprovalsAndRequest::with('payrollUser.positionpayfrequencies')->where('id', $request->req_id)->first();
                if (!$approvalAndRequest) {
                    DB::rollBack();
                    return response()->json([
                        'ApiName' => 'advance-repayment',
                        'status' => false,
                        'message' => 'Request not found!!'
                    ], 400);
                }
                $user = $approvalAndRequest->payrollUser;
                if (!$user) {
                    DB::rollBack();
                    return response()->json([
                        'ApiName' => 'advance-repayment',
                        'status' => false,
                        'message' => 'User not found!!'
                    ], 400);
                }
            }

            $param = [
                "pay_frequency" => $frequencyTypeId,
                "worker_type" => $workerType,
                "pay_period_from" => $payPeriodFrom,
                "pay_period_to" => $payPeriodTo
            ];
            $check = Payroll::applyFrequencyFilter($param, ["status" => 2])->whereIn("finalize_status", [1, 2])->count();
            if ($check) {
                DB::rollBack();
                return response()->json([
                    'ApiName' => 'advance-repayment',
                    'status' => false,
                    'message' => 'Cannot send to payroll. This pay period has been Already Finalize for this employee!!'
                ], 400);
            }

            if ($request->repay_type == 'repay_all') {
                $usersAdvanceRequests = ApprovalsAndRequest::where(['user_id' => $request->req_id, 'adjustment_type_id' => 4, 'status' => 'Approved'])->whereNull('req_no')->get();
                $usersAdvanceRequests->transform(function ($usersAdvanceRequest) use ($payPeriodFrom, $payPeriodTo, $frequencyTypeId, $workerType) {
                    ApprovalsAndRequest::where('id', $usersAdvanceRequest->id)->update(['status' => 'Accept']);
                    $childRequestAmount = ApprovalsAndRequest::where('parent_id', $usersAdvanceRequest->id)->sum('amount');
                    $reqAmount = $usersAdvanceRequest->amount - $childRequestAmount;
                    $usersAdvanceRequest->amount = $reqAmount;
                    if ($reqAmount < 0) {
                        $newData = $usersAdvanceRequest->toArray();
                        $newData['amount'] = $reqAmount;
                        $newData['parent_id'] = $usersAdvanceRequest->id;
                        $newData['status'] = "Accept";
                        $newData['pay_period_from'] = $payPeriodFrom;
                        $newData['pay_period_to'] = $payPeriodTo;
                        $newData['pay_frequency'] = $frequencyTypeId;
                        $newData['user_worker_type'] = $workerType;
                        $newData['approved_by'] = Auth::user()->id;
                        unset($newData['id']);
                        ApprovalsAndRequest::create($newData);
                    }
                });
            } else {
                if (!$request->amount) {
                    DB::rollBack();
                    return response()->json([
                        'ApiName' => 'advance-repayment',
                        'status' => false,
                        'message' => "The Amount field is required!!"
                    ], 400);
                }

                $usersAdvanceRequest = ApprovalsAndRequest::where(['id' => $request->req_id, 'adjustment_type_id' => 4, 'status' => 'Approved'])->whereNull('req_no')->first();
                if (!$usersAdvanceRequest) {
                    DB::rollBack();
                    return response()->json([
                        'ApiName' => 'advance-repayment',
                        'status' => false,
                        'message' => 'Request not found!!'
                    ], 400);
                }

                $newData = $usersAdvanceRequest->toArray();
                $newData['amount'] = $request->amount < 0 ? $request->amount : "-" . $request->amount;
                $newData['parent_id'] = $usersAdvanceRequest->id;
                $newData['status'] = "Accept";
                $newData['pay_period_from'] = $payPeriodFrom;
                $newData['pay_period_to'] = $payPeriodTo;
                $newData['pay_frequency'] = $frequencyTypeId;
                $newData['user_worker_type'] = $workerType;
                $newData['approved_by'] = Auth::user()->id;
                unset($newData['id']);
                ApprovalsAndRequest::create($newData);

                $childRequestAmount = ApprovalsAndRequest::where('parent_id', $usersAdvanceRequest->id)->sum('amount');
                if (($usersAdvanceRequest->amount - $childRequestAmount) == 0) {
                    ApprovalsAndRequest::where('parent_id', $usersAdvanceRequest->id)->update(['status' => 'Accept']);
                }
            }

            DB::commit();
            return response()->json([
                'ApiName' => 'advance-repayment',
                'status' => true,
                'message' => "Send to payroll successfully!!"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ApiName' => 'advance-repayment',
                'status' => false,
                'message' => $e->getMessage() . ' Line: ' . $e->getLine()
            ], 500);
        }
    }
}
