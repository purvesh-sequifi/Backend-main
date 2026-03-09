<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\User;
use App\Models\Locations;
use App\Models\SalesMaster;
use Illuminate\Http\Request;
use App\Models\UserAttendance;
use Illuminate\Support\Carbon;
use App\Models\AdjustementType;
use App\Models\UserWagesHistory;
use App\Models\ApprovalsAndRequest;
use App\Http\Controllers\Controller;
use App\Models\RequestApprovelByPid;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Events\UserloginNotification;
use App\Core\Traits\PayFrequencyTrait;
use App\Traits\EmailNotificationTrait;
use App\Exports\requestApprovalsExport;
use App\Models\ApprovalAndRequestComment;
use Illuminate\Support\Facades\Validator;

class RequestApprovalController extends Controller
{
    use EmailNotificationTrait, PayFrequencyTrait;

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // "amount" => "required",
            "adjustment_type_id" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "create-request",
                "error" => $validator->errors()
            ], 400);
        }

        $path = NULL;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = 'request-image/' . str_replace(' ', '_', time() . $file->getClientOriginalName());
            $awsPath = config('app.domain_name') . '/' . $path;
            $s3Return = uploadS3UsingEnv($awsPath, file_get_contents($file), false, 'private');
            if (!$s3Return['status']) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "create-request",
                    "error" => $s3Return['message']
                ], 400);
            }
        }

        $user = Auth::user();
        $userId = $user->id;
        if ($request->user_id) {
            $user = User::find($request->user_id);
            if (!$user) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "create-request",
                    "error" => "User not found"
                ], 400);
            }
            $userId = $user->id;
        }
        $managerId = $user?->manager_id;

        $effectiveDate = date('Y-m-d');
        if ($request->adjustment_type_id == 2) {
            $effectiveDate = $request->cost_date;
        } else if ($request->adjustment_type_id == 3 || $request->adjustment_type_id == 5 || $request->adjustment_type_id == 6) {
            $effectiveDate = $request->request_date;
        } else if ($request->adjustment_type_id == 7 || $request->adjustment_type_id == 8) {
            $effectiveDate = $request->end_date;
        } else if ($request->adjustment_type_id == 9) {
            $effectiveDate = $request->adjustment_date;
        }

        $terminated = checkTerminateFlag($userId, $effectiveDate);
        if ($terminated && $terminated->is_terminate) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => 'You have been terminated'
            ], 400);
        }

        $dismissed = checkDismissFlag($userId, $effectiveDate);
        if ($dismissed && $dismissed->dismiss) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => 'You have been disabled'
            ], 400);
        }

        if ($user?->disable_login) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => 'Your access has been suspended'
            ], 400);
        }

        $adjustmentType = AdjustementType::find($request->adjustment_type_id);
        if (!$adjustmentType) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => 'Adjustment type not found'
            ], 400);
        }

        $adjustmentTypeId = $adjustmentType->id;
        $approvalsAndRequest = ApprovalsAndRequest::where('adjustment_type_id', $adjustmentTypeId)->whereNotNull('req_no')->orderBy('id', 'DESC')->first();
        if ($approvalsAndRequest) {
            $approvalsAndRequest = preg_replace('/[A-Za-z]+/', '', $approvalsAndRequest->req_no);
        }

        $prefix = requestApprovalPrefix($adjustmentTypeId);
        if (!empty($approvalsAndRequest)) {
            $reqNo = $prefix . str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $reqNo = $prefix . str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
        }

        $subPositionId = $user->sub_position_id;
        $payFrequency = $this->payFrequencyNew(date('Y-m-d'), $subPositionId, $userId);
        if (in_array($adjustmentTypeId, [7, 8, 9])) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            $insertUpdate = [
                'user_id' => $userId,
                'manager_id' => $managerId,
                'created_by' => Auth::user()->id,
                'req_no' => $reqNo,
                'approved_by' => $request->approved_by,
                'adjustment_type_id' => $adjustmentTypeId,
                'pay_period' => $request->pay_period,
                'state_id' => $request->state_id,
                'dispute_type' => $request->dispute_type,
                "description" => $request->description,
                "cost_tracking_id" => $request->cost_tracking_id,
                "emi" => $request->emi,
                "request_date" => $request->request_date,
                "cost_date" => $request->cost_date,
                "amount" => $request->amount,
                "image" => $path,
                "status" => "Pending",
                "start_date" => isset($request->start_date) ? $request->start_date : null,
                "end_date" => isset($request->end_date) ? $request->end_date : null,
                "pto_hours_perday" => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                "adjustment_date" => isset($request->adjustment_date) ? $request->adjustment_date : null,
                "clock_in" => isset($request->clock_in) ? $request->clock_in : null,
                "clock_out" => isset($request->clock_out) ? $request->clock_out : null,
                "lunch_adjustment" => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                "break_adjustment" => isset($request->break_adjustment) ? $request->break_adjustment : null,
                "user_worker_type" => $user->worker_type,
                "pay_frequency" => $payFrequency->pay_frequency
            ];

            if ($adjustmentTypeId == 7) {
                $start = $this->payFrequency($startDate, $subPositionId, $userId);
                $end = $this->payFrequency($endDate, $subPositionId, $userId);

                $officeId = $user->office_id;
                $scheduleStartDate = Carbon::parse($request->start_date);
                $scheduleEndDate = Carbon::parse($request->end_date);
                $scheduleFrom = "08:00:00";
                $scheduleTo = "16:00:00";
                for ($date = $scheduleStartDate; $date->lte($scheduleEndDate); $date->addDay()) {
                    $clockIn = $date->copy()->setTimeFromTimeString($scheduleFrom);
                    $clockOut = $date->copy()->setTimeFromTimeString($scheduleTo);
                    createOrUpdateUserSchedules($userId, $officeId, $clockIn, $clockOut, $date, null);
                }

                if ((!empty($start) && $start->closed_status == 1) || (!empty($end) && $end->closed_status == 1)) {
                    return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be created because the pay period has already been closed'], 400);
                } else {
                    $approvalData = ApprovalsAndRequest::where(['adjustment_type_id' => $adjustmentTypeId, 'user_id' => $userId])->where(['start_date' => $startDate, 'end_date' => $endDate])->first();
                    if ($approvalData) {
                        $insertUpdate['req_no'] = $approvalData->req_no;
                        $approvalData = ApprovalsAndRequest::find($approvalData->id);
                        $approvalData->update($insertUpdate);
                        $data = $approvalData;
                    } else {
                        $data = ApprovalsAndRequest::create($insertUpdate);
                    }
                }
            } else if ($adjustmentTypeId == 8) {
                $start = $this->payFrequency($startDate, $subPositionId, $userId);
                $end = $this->payFrequency($endDate, $subPositionId, $userId);

                $officeId = $user->office_id;
                $scheduleStartDate = Carbon::parse($request->start_date);
                $scheduleEndDate = Carbon::parse($request->end_date);
                $ptoHoursPerDay = $request->pto_hours_perday;
                $scheduleFrom = "08:00:00";
                $scTime = Carbon::createFromFormat('H:i:s', $scheduleFrom);
                $scheduleTo = $scTime->addHours($ptoHoursPerDay)->format('H:i:s');
                for ($date = $scheduleStartDate; $date->lte($scheduleEndDate); $date->addDay()) {
                    $clockIn = $date->copy()->setTimeFromTimeString($scheduleFrom);
                    $clockOut = $date->copy()->setTimeFromTimeString($scheduleTo);
                    createOrUpdateUserSchedules($userId, $officeId, $clockIn, $clockOut, $date, null);
                }
                if ((!empty($start) && $start->closed_status == 1) || (!empty($end) && $end->closed_status == 1)) {
                    return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be created because the pay period has already been closed'], 400);
                } else {
                    $start = Carbon::parse($startDate);
                    $end = Carbon::parse($endDate);
                    $daysCount = $start->diffInDays($end) + 1;
                    $ptoHoursPerDay = ($request->pto_hours_perday * $daysCount);
                    $date = date('Y-m-d');
                    $calPTO = calculatePTOs($userId);
                    $usedPTO = isset($calPTO['total_user_ptos']) ? $calPTO['total_user_ptos'] : 0;
                    if ($calPTO && !empty($calPTO['total_ptos']) && ($usedPTO + $ptoHoursPerDay) <= $calPTO['total_ptos']) {
                        $checkStatus = checkusedday($userId, $startDate, $endDate, $insertUpdate['pto_hours_perday']);
                        if (!empty($checkStatus)) {
                            return response()->json(['status' => false, 'message' => $checkStatus[0]], 400);
                        }
                        $data = ApprovalsAndRequest::create($insertUpdate);
                    } else {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the PTO hour greater than PTO balance'], 400);
                    }
                }
            } else if ($adjustmentTypeId == 9) {
                $adjustmentDate = $request->adjustment_date;
                $clockIn = null;
                $clockOut = null;
                $officeId = $user->office_id;
                if (isset($request->clock_in) && !empty($request->clock_in)) {
                    $clockIn = $request->clock_in;
                }
                if (isset($request->clock_out) && !empty($request->clock_out)) {
                    $clockOut = $request->clock_out;
                }
                createOrUpdateUserSchedules($userId, $officeId, $clockIn, $clockOut, $adjustmentDate, $request->lunch_adjustment);
                $leaveData = ApprovalsAndRequest::where(['user_id' => $userId, 'adjustment_type_id' => 7])->where('start_date', '<=', $adjustmentDate)->where('end_date', '>=', $adjustmentDate)->where('status', 'Approved')->first();
                if ($leaveData) {
                    return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be created because this adjustment date has already been leave request'], 400);
                } else {
                    $approvalData = ApprovalsAndRequest::where('adjustment_type_id', $adjustmentTypeId)->where(['user_id' => $userId, 'adjustment_date' => $adjustmentDate])->first();
                    if ($approvalData) {
                        $insertUpdate['req_no'] = $approvalData->req_no;
                        $approvalData = ApprovalsAndRequest::find($approvalData->id);
                        $approvalData->update($insertUpdate);
                        $data = $approvalData;
                    } else {
                        $data = ApprovalsAndRequest::create($insertUpdate);
                    }
                }
            }
        } else {
            $data = ApprovalsAndRequest::create([
                'user_id' => $userId,
                'manager_id' => $managerId,
                'created_by' => Auth::user()->id,
                'req_no' => $reqNo,
                'approved_by' => $request->approved_by,
                'adjustment_type_id' => $adjustmentTypeId,
                'pay_period' => $request->pay_period,
                'state_id' => $request->state_id,
                'dispute_type' => $request->dispute_type,
                "description" => $request->description,
                "cost_tracking_id" => $request->cost_tracking_id,
                "emi" => $request->emi,
                "request_date" => $request->request_date,
                "cost_date" => $request->cost_date,
                "amount" => $request->amount,
                "image" => $path,
                "status" => "Pending",
                "start_date" => isset($request->start_date) ? $request->start_date : null,
                "end_date" => isset($request->end_date) ? $request->end_date : null,
                "pto_hours_perday" => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                "adjustment_date" => isset($request->adjustment_date) ? $request->adjustment_date : null,
                "clock_in" => isset($request->clock_in) ? $request->clock_in : null,
                "clock_out" => isset($request->clock_out) ? $request->clock_out : null,
                "lunch_adjustment" => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                "break_adjustment" => isset($request->break_adjustment) ? $request->break_adjustment : null,
                "user_worker_type" => $request->filled('user_worker_type') ? $request->user_worker_type : $user->worker_type,
                "pay_frequency" => $request->filled('pay_frequency') ? $request->pay_frequency : $payFrequency->pay_frequency
            ]);

            $customerPid = $request->customer_pid;
            if ($customerPid) {
                $pid = explode(',', $customerPid);
                $sales = SalesMaster::select('pid', 'customer_name')->whereIn('pid', $pid)->get();
                foreach ($sales as $sale) {
                    RequestApprovelByPid::create([
                        'request_id' => $data->id,
                        'pid' => $sale->pid,
                        'customer_name' => $sale->customer_name
                    ]);
                }
            }
        }

        if ($managerId) {
            $notificationData = array(
                'user_id' => $managerId,
                'device_token' => $user->device_token,
                'title' => 'A new request is generated.',
                'sound' => 'sound',
                'type' => 'request-approval',
                'body' => 'A new request is generated by ' . $user->first_name . ' ' . $user->last_name
            );
            $this->sendNotification($notificationData);
        }

        $user = array(
            'user_id' => $managerId ?? 1,
            'description' => 'A new request is generated by ' . $user?->first_name . ' ' . $user?->last_name,
            'type' => 'request-approval',
            'is_read' => 0
        );
        event(new UserloginNotification($user));

        return response()->json([
            'ApiName' => 'add-request',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data
        ]);
    }

    public function list(Request $request)
    {
        $user = Auth::user();
        if (!empty($request->perpage)) {
            $perPage = $request->perpage;
        } else {
            $perPage = 10;
        }

        $data = ApprovalsAndRequest::whereNotNull('req_no')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id);
                $query->orWhere('created_by', $user->id);
            });

        if ($request->filled('filter')) {
            $search = $request->input('filter');
            $data->where(function ($query) use ($search) {
                $query->where('amount', 'like', '%' . $search . '%')
                    ->orWhere('req_no', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($query) use ($search) {
                        $query->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%' . $search . '%']);
                    });
            });
        }
        if ($request->filled('type')) {
            $type = $request->input('type');
            $data->whereHas('adjustment', function ($query) use ($type) {
                $query->where('name', 'like', '%' . $type . '%');
            });
        }
        if ($request->filled('status')) {
            $status = $request->input('status');
            $data->where('status', 'like', '%' . $status . '%');
        }
        if ($request->filled('sort')) {
            $sort = $request->input('sort');
            $sort = ($sort == 'disputed' ? 'created_at' : ($sort == 'amount' ? 'amount' : $sort));
            $data->orderBy($sort, 'DESC');
        } else {
            $data->orderBy('id', 'DESC');
        }

        $data = $data->with('adjustment', 'user')->with(['approvedBy' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'is_super_admin');
        }])->paginate($perPage);

        $data->transform(function ($data) {
            if ($data->adjustment_type_id == 5) {
                $data->amount = (0 - $data->amount);
            } else if ($data->adjustment_type_id == 7) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;

                $data->amount = $daysCount;
            } else if ($data->adjustment_type_id == 8) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;
                $ptoHoursPerday = ($data->pto_hours_perday * $daysCount);

                $data->amount = $ptoHoursPerday;
            } else if ($data->adjustment_type_id == 9) {
                $timeIn = new Carbon($data->clock_in);
                $timeOut = new Carbon($data->clock_out);
                $totalHoursWorkedSec = $timeIn->diffInSeconds($timeOut);
                $totalLunch = isset($data->lunch_adjustment) ? $data->lunch_adjustment : 0;
                $totalBreak = isset($data->break_adjustment) ? $data->break_adjustment : 0;
                $totalLunchBreakTime = ($totalLunch + $totalBreak) * 60;
                $totalWorkHrs = $totalHoursWorkedSec - $totalLunchBreakTime;
                $totalTime = gmdate('H:i', $totalWorkHrs);

                $data->amount = isset($totalTime) ? $totalTime : 0;
            }

            return [
                'id' => $data->id,
                'req_no' => $data->req_no,
                'request_on' => $data->created_at->format('m/d/Y'),
                'type_id' => $data->adjustment_type_id,
                'type' => isset($data->adjustment->name) ? $data->adjustment->name : NULL,
                'amount' => isset($data->amount) ? $data->amount : NULL,
                'description' => isset($data->description) ? $data->description : NULL,
                'status' => $data->status,
                'declined_at' => isset($data->declined_at) ? $data->declined_at : null,
                'approvedBy' => $data->approvedBy
            ];
        });

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "request_id" => "required",
            "status" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "update-status",
                "error" => $validator->errors()
            ], 400);
        }

        $authUser = Auth::user();
        if (!$authUser->is_manager && !$authUser->is_super_admin) {
            return response()->json([
                "status" => false,
                "ApiName" => "update-status",
                "message" => "You are not authorized for this action"
            ], 400);
        }

        $approvalsAndRequest = ApprovalsAndRequest::where('id', $request->request_id)->first();
        if (!$approvalsAndRequest) {
            return response()->json([
                "status" => false,
                "ApiName" => "update-status",
                "message" => "Sorry the request you are looking for is not found."
            ], 400);
        }

        if ($approvalsAndRequest->user_id == $authUser->id) {
            return response()->json([
                "status" => false,
                "ApiName" => "update-status",
                "message" => "You are not authorized for this action"
            ], 400);
        }

        if ($request->status == 'Declined') {
            $approvalsAndRequest->status = $request->status;
            $approvalsAndRequest->declined_by = $authUser->id;
            $approvalsAndRequest->save();
        } else {
            $approvalsAndRequest->status = $request->status;
            $approvalsAndRequest->approved_by = $authUser->id;
            $approvalsAndRequest->save();

            if ($approvalsAndRequest->adjustment_type_id == 9) {
                approvedTimeAdjustment($approvalsAndRequest, $authUser->id);
            }
        }

        return response()->json([
            "status" => true,
            "ApiName" => "update-status",
            "message" => "Update Successfully"
        ]);
    }

    public function view($id)
    {
        $data = ApprovalsAndRequest::with('getPid', 'adjustment', 'costcenter', 'state', 'user', 'comments')->with(['approvedBy' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'is_super_admin');
        }])->where('req_no', $id)->orWhere('txn_id', $id)->get();
        $name = isset($data[0]['adjustment']['name']) ? $data[0]['adjustment']['name'] : null;

        $data->transform(function ($data) {
            $reqImage = NULL;
            if (isset($data->image) && $data->image && $data->image != 'Employee_profile/default-user.png') {
                $reqImage = s3_getTempUrl(config('app.domain_name') . '/' . $data->image);
            }

            $comments = [];
            if ($data->comments) {
                foreach ($data->comments as $comment) {
                    $updaterDetail = User::where('id', $comment->user_id)->first();
                    $s3Comment = null;
                    if (isset($comment->image) && $comment->image) {
                        $s3Comment = s3_getTempUrl(config('app.domain_name') . '/' . $comment->image);
                    }
                    $comments[] = array(
                        'id' => $comment->id,
                        'request_id' => $comment->user_id,
                        'user_id' => $comment->user_id,
                        'user_name' => isset($updaterDetail->first_name) ? $updaterDetail->first_name : null,
                        'user_image' => isset($updaterDetail->image) ? $updaterDetail->image : null,
                        'type' => $comment->type,
                        'image' => isset($comment->image) ? $comment->image : null,
                        'comment_image_s3' => $s3Comment,
                        'comment' => $comment->comment
                    );
                }
            }

            $status = $data->status;
            if ($data->status == 'Accept') {
                $status = 'Paid With Payroll';
            }

            $s3EmpUrl = null;
            if (isset($data->user->image) && $data->user->image && $data->user->image != 'Employee_profile/default-user.png') {
                $s3EmpUrl = s3_getTempUrl(config('app.domain_name') . '/' . $data->user->image);
            }

            $amount = $data->amount;
            if ($data->adjustment_type_id == 5) {
                $amount = (0 - $data->amount);
            } else if ($data->adjustment_type_id == 7) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;

                $amount = $daysCount;
            } else if ($data->adjustment_type_id == 8) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;
                $ptoHoursPerDay = ($data->pto_hours_perday * $daysCount);

                $amount = $ptoHoursPerDay;
            } else if ($data->adjustment_type_id == 9) {
                $timeIn = new Carbon($data->clock_in);
                $timeOut = new Carbon($data->clock_out);
                $totalHoursWorkedSec = $timeIn->diffInSeconds($timeOut);
                $totalLunch = isset($data->lunch_adjustment) ? $data->lunch_adjustment : 0;
                $totalBreak = isset($data->break_adjustment) ? $data->break_adjustment : 0;
                $totalLunchBreakTime = ($totalLunch + $totalBreak) * 60;
                $totalWorkHrs = $totalHoursWorkedSec - $totalLunchBreakTime;
                $totalTime = gmdate('H:i', $totalWorkHrs);

                $amount = isset($totalTime) ? $totalTime : 0;
            }

            $officeName = Locations::where('id', $data?->user?->office_id)->first();
            return [
                'id' => $data->id,
                'request_on' => $data->created_at->format('m/d/Y'),
                'req_no' => $data->req_no ?? $data->txn_id,
                'manager_id' => isset($data->user->manager_id) ? $data->user->manager_id : NULL,
                'employee_id' => isset($data->user->id) ? $data->user->id : NULL,
                'employee_name' => isset($data->user->name) ? $data->user->name : NULL,
                'employee_image' => isset($data->user->image) ? $data->user->image : NULL,
                'position_id' => isset($data->user->position_id) ? $data->user->position_id : null,
                'sub_position_id' => isset($data->user->sub_position_id) ? $data->user->sub_position_id : null,
                'is_super_admin' => isset($data->user->is_super_admin) ? $data->user->is_super_admin : null,
                'is_manager' => isset($data->user->is_manager) ? $data->user->is_manager : null,
                'employee_image_s3' => $s3EmpUrl,
                'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : NULL,
                'type' => isset($data->adjustment->name) ? $data->adjustment->name : NULL,
                'customer_name' => isset($data->adjustment->customer_name) ? $data->adjustment->customer_name : NULL,
                'cost_tracking_id' => isset($data->costcenter->id) ? $data->costcenter->id : NULL,
                'cost_head' => isset($data->costcenter->name) ? $data->costcenter->name : NULL,
                'state_id' => isset($data->state->id) ? $data->state->id : NULL,
                'state_name' => isset($data->state->name) ? $data->state->name : NULL,
                'pay_period' => isset($data->pay_period) ? $data->pay_period : NULL,
                'dispute_type' => isset($data->dispute_type) ? $data->dispute_type : NULL,
                'description' => isset($data->description) ? $data->description : NULL,
                'emi' => isset($data->emi) ? $data->emi : NULL,
                'cost_date' => isset($data->cost_date) ? $data->cost_date : NULL,
                'request_date' => isset($data->request_date) ? $data->request_date : NULL,
                'amount' => $amount,
                'image' => isset($data->image) ? $data->image : NULL,
                'request_s3' => $reqImage,
                'status' => isset($status) ? $status : NULL,
                'comments' => isset($comments) ? $comments : [],
                'location_id' => isset($data->user->office->id) ? $data->user->office->id : NULL,
                'location_name' => isset($data->user->office->office_name) ? $data->user->office->office_name : NULL,
                'getPid' => $data?->getPid,
                'office_name' => isset($officeName->office_name) ? $officeName->office_name : NULL,
                'pay_period_from' => isset($data->pay_period_from) ? $data->pay_period_from : NULL,
                'pay_period_to' => isset($data->pay_period_to) ? $data->pay_period_to : NULL,
                'start_date' => isset($data->start_date) ? $data->start_date : NULL,
                'end_date' => isset($data->end_date) ? $data->end_date : NULL,
                'adjustment_date' => isset($data->adjustment_date) ? $data->adjustment_date : NULL,
                'pto_hours_perday' => isset($data->pto_hours_perday) ? $data->pto_hours_perday : NULL,
                'clock_in' => isset($data->clock_in) ? $data->clock_in : NULL,
                'clock_out' => isset($data->clock_out) ? $data->clock_out : NULL,
                'lunch_adjustment' => isset($data->lunch_adjustment) ? $data->lunch_adjustment : NULL,
                'break_adjustment' => isset($data->break_adjustment) ? $data->break_adjustment : NULL,
                'approvedBy' => $data->approvedBy
            ];
        });

        return response()->json([
            'ApiName' => 'getRequestApprovalStatusByReq_No',
            'status' => true,
            'message' => 'Successfully.',
            'pid' => $id,
            'type' => $name,
            'data' => $data
        ]);
    }

    public function adjustmentType()
    {
        return response()->json([
            'ApiName' => 'adjustment-type',
            'status' => true,
            'message' => 'Successfully',
            'data' => AdjustementType::get()
        ]);
    }

    public function approvalList(Request $request)
    {
        $user = Auth::user();
        if (!empty($request->perpage)) {
            $perPage = $request->perpage;
        } else {
            $perPage = 10;
        }

        $apiType = $request->input('api_type');
        $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'is_super_admin');
        }])->when(request()->office_id && request()->office_id !== 'all', function ($query) {
            $query->whereHas('user', function ($subQuery) {
                $subQuery->where('office_id', request()->office_id);
            });
        });
        if ($user->is_super_admin == 1) {
            if ($apiType == 'approval') {
                $paymentRequest->where(['status' => 'Pending']);
            } else {
                $paymentRequest->where('status', '!=', 'Pending')->whereNotNull('req_no');
            }
        } else if ($user->is_manager == 1) {
            if ($apiType == 'approval') {
                $paymentRequest->where(['manager_id' => $user->id, 'status' => 'Pending'])->where('adjustment_type_id', '!=', 5);
            } else {
                $paymentRequest->where(['manager_id' => $user->id])->where('adjustment_type_id', '!=', 5)->where('status', '!=', 'Pending')->whereNotNull('req_no');
            }
        } else {
            $paymentRequest->where(['user_id' => $user->id, 'status' => 'Approved']);
        }

        if ($request->filled('filter')) {
            $search = $request->input('filter');
            $paymentRequest->where(function ($query) use ($search) {
                $query->where('amount', 'LIKE', '%' . $search . '%')
                    ->orWhere('req_no', 'like', '%' . $search . '%');
            })->orWhereHas('user', function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%' . $search . '%']);
            });
        }
        if ($request->filled('type')) {
            $type = $request->input('type');
            $paymentRequest->where(function ($query) use ($type) {
                $query->orWhereHas('adjustment', function ($query) use ($type) {
                    $query->where('name', 'like', '%' . $type . '%');
                });
            });
        }
        if ($request->filled('status')) {
            $status = $request->input('status');
            $paymentRequest->where(function ($query) use ($status) {
                $query->where('status', 'LIKE', '%' . $status . '%')
                    ->orWhere('req_no', 'like', '%' . $status . '%');
            });
        }
        $paymentRequest = $paymentRequest->paginate($perPage);

        $paymentRequest->transform(function ($data) {
            if ($data->adjustment_type_id == 5) {
                $data->amount = (0 - $data->amount);
            } else if ($data->adjustment_type_id == 7) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;

                $data->amount = $daysCount;
            } else if ($data->adjustment_type_id == 8) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;
                $ptoHoursPerDay = ($data->pto_hours_perday * $daysCount);

                $data->amount = $ptoHoursPerDay;
            } else if ($data->adjustment_type_id == 9) {
                $timeIn = new Carbon($data->clock_in);
                $timeOut = new Carbon($data->clock_out);
                $totalHoursWorkedSec = $timeIn->diffInSeconds($timeOut);
                $totalLunch = isset($data->lunch_adjustment) ? $data->lunch_adjustment : 0;
                $totalBreak = isset($data->break_adjustment) ? $data->break_adjustment : 0;
                $totalLunchBreakTime = ($totalLunch + $totalBreak) * 60;
                $totalWorkHrs = $totalHoursWorkedSec - $totalLunchBreakTime;
                $totalTime = gmdate('H:i', $totalWorkHrs);

                $data->amount = isset($totalTime) ? $totalTime : 0;
            }

            $employeeImage = NULL;
            if (isset($data->user->image) && $data->user->image && $data->user->image != 'Employee_profile/default-user.png') {
                $employeeImage = s3_getTempUrl(config('app.domain_name') . '/' . $data->user->image);
            }

            return [
                'id' => $data->id,
                'req_no' => $data->req_no,
                'employee_id' => isset($data->user->id) ? $data->user->id : NULL,
                'employee_name' => isset($data->user->name) ? $data->user->name : NULL,
                'employee_image' => $employeeImage,
                'position_id' => isset($data->user->position_id) ? $data->user->position_id : NULL,
                'sub_position_id' => isset($data->user->sub_position_id) ? $data->user->sub_position_id : NULL,
                'is_manager' => isset($data->user->is_manager) ? $data->user->is_manager : NULL,
                'is_super_admin' => isset($data->user->is_super_admin) ? $data->user->is_super_admin : NULL,
                'request_on' => $data->created_at->format('m/d/Y'),
                'type_id' => $data->adjustment_type_id,
                'type' => isset($data->adjustment->name) ? $data->adjustment->name : NULL,
                'amount' => $data->amount,
                'description' => $data->description,
                'status' => $data->status,
                'declined_at' => isset($data->declined_at) ? $data->declined_at : null,
                'approvedBy' => $data->approvedBy
            ];
        });
        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $paymentRequest
        ]);
    }

    public function export(Request $request)
    {
        $fileName = 'approval_history_' . date('Y_m_d_H_i_s') . '.xlsx';
        Excel::store(
            new requestApprovalsExport($request),
            'exports/request-and-approvals/' . $fileName,
            'public',
            \Maatwebsite\Excel\Excel::XLSX
        );

        $url = getStoragePath('exports/request-and-approvals/' . $fileName);
        return response()->json(['url' => $url]);
    }

    public function comment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "request_id" => "required",
            "comment" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "comment",
                "error" => $validator->errors()
            ], 400);
        }

        $authUser = Auth::user();
        $approvalsAndRequest = ApprovalsAndRequest::find($request->request_id);
        if (!$approvalsAndRequest) {
            return response()->json([
                "status" => false,
                "ApiName" => "comment",
                "message" => "Request not found"
            ], 400);
        }

        $user = User::find($approvalsAndRequest->user_id);
        if (!$user) {
            return response()->json([
                "status" => false,
                "ApiName" => "comment",
                "message" => "User not found"
            ], 400);
        }

        $manager = User::find($authUser->manager_id);
        if ($approvalsAndRequest->user_id == $authUser->id) {
            $type = 'comment';
        } else {
            $type = 'reply';
        }

        $path = NULL;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = 'request-image/' . str_replace(' ', '_', time() . $file->getClientOriginalName());
            $awsPath = config('app.domain_name') . '/' . $path;
            $s3Return = uploadS3UsingEnv($awsPath, file_get_contents($file), false, 'private');
            if (!$s3Return['status']) {
                return response()->json([
                    "status" => false,
                    "ApiName" => "comment",
                    "error" => $s3Return['message']
                ], 400);
            }
        }

        ApprovalAndRequestComment::create([
            'user_id' => $authUser->id,
            'request_id' => $request->request_id,
            'type' => $type,
            'comment' => $request->comment,
            "image" => $path
        ]);

        $mailData = [
            "name" => isset($authUser->first_name, $authUser->last_name) ? $authUser->first_name . " " . $authUser->last_name : '',
            "image" => $authUser->image,
            "comment" => $request->comment,
            "id" => $approvalsAndRequest->req_no,
            "type" => $type
        ];
        if (($manager && $type == 'comment') || $type == 'reply') {
            $approval = [];
            $approval['email'] = $authUser->email;
            $approval['subject'] = 'Request Approval';
            $approval['template'] = view('mail.requestapproval', compact('mailData'));
            $this->sendEmailNotification($approval);
        }

        return response()->json([
            'ApiName' => 'comment',
            'status' => true,
            'message' => 'Successfully'
        ]);
    }

    public function userPtoHours(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "user-pto-hours",
                "error" => $validator->errors()
            ], 400);
        }

        $date = date('Y-m-d');
        $userId = $request->user_id;
        $userWagesHistory = UserWagesHistory::where('user_id', $userId)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
        if (!$userWagesHistory) {
            return response()->json([
                'ApiName' => 'user-pto-hours',
                'status' => false,
                'message' => 'data not found',
                'data' => []
            ]);
        }

        $calPto = calculatePTOs($userId, $date);
        $data = [
            'id' => $userWagesHistory->id,
            'user_id' => $userId,
            'pto_hours' => $userWagesHistory->pto_hours,
            'total_ptos' => $calPto['total_ptos'] ?? 0,
            'total_used_ptos' => $calPto['total_user_ptos'] ?? 0,
            'total_remaining_ptos' => $calPto['total_remaining_ptos'] ?? 0,
            'pto_hours_effective_date' => $userWagesHistory->pto_hours_effective_date
        ];

        return response()->json([
            'ApiName' => 'user-pto-hours',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }

    public function userTimeAdjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'adjustment_date' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "user-time-adjustment",
                "error" => $validator->errors()
            ], 400);
        }

        $userId = $request->user_id;
        $date = $request->adjustment_date;
        $userAttendance = UserAttendance::with('userattendancelist')->where('user_id', $userId)->where('date', $date)->orderBy('id', 'asc')->first();
        if (!$userAttendance) {
            return response()->json([
                'ApiName' => 'user-time-adjustment',
                'status' => false,
                'message' => 'data not found',
                'data' => []
            ]);
        }

        $data = [];
        if (count($userAttendance['userattendancelist']) > 0) {
            $userAttendanceDetail =  $userAttendance['userattendancelist'];
            $clockIn = $userAttendanceDetail->where('type', "clock in")->first();
            $clockOut = $userAttendanceDetail->where('type', "clock out")->first();

            $clockInTime = isset($clockIn['attendance_date']) ? date("H:i", strtotime($clockIn['attendance_date'])) : null;
            $clockOutTime = isset($clockOut['attendance_date']) ? date("H:i", strtotime($clockOut['attendance_date'])) : null;

            $data = [
                'id' => $userAttendance->id,
                'user_id' => $userId,
                'date' => $date,
                'clock_in' => $clockInTime,
                'clock_out' => $clockOutTime,
                'lunch' => $userAttendance->lunch_time,
                'break' => $userAttendance->break_time
            ];
        }

        return response()->json([
            'ApiName' => 'user-time-adjustment',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }
}
