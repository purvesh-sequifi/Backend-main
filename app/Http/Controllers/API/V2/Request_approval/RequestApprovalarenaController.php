<?php

namespace App\Http\Controllers\API\V2\Request_approval;

use App\Events\UserloginNotification;
use App\Http\Controllers\Controller;
use App\Models\AdjustementType;
use App\Models\ApprovalsAndRequest;
use App\Models\RequestApprovelByPid;
use App\Models\SalesMaster;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RequestApprovalarenaController extends Controller
{
    use EmailNotificationTrait;

    public function request_approval(Request $request)
    {
        $Validator = Validator::make($request->all(),
            [
                'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',
            ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        if (! $request->image == null) {
            $file = $request->file('image');
            if (isset($file) && $file != null && $file != '') {
                // s3 bucket
                $img_path = time().$file->getClientOriginalName();
                $img_path = str_replace(' ', '_', $img_path);
                $awsPath = config('app.domain_name').'/'.'request-image/'.$img_path;
                s3_upload($awsPath, file_get_contents($file), false);
                // s3 bucket end
            }
            $image_path = time().$file->getClientOriginalName();
            $ex = $file->getClientOriginalExtension();
            $destinationPath = 'request-image';
            $image_path = $file->move($destinationPath, $img_path);
        } else {
            $image_path = '';
        }
        $game_id = $request->game_id;
        $managerId = null;
        foreach ($request->data as $userdata) {
            // return $userdata['user_id'];
            $user_id = Auth::user()->id;
            $user_data = User::where('id', $userdata['user_id'])->first();
            if (! empty($user_data)) {
                $userID = $userdata['user_id'];
                $userManager = $user_data = User::where('id', $userID)->first();
                $managerId = $userManager->manager_id;

            } else {
                $userID = $user_id;
                $managerId = null;
            }
            // echo $data->name;die;
            // return $request->adjustment_type_id;
            $adjustementType = AdjustementType::where('id', $request->adjustment_type_id)->first();

            $approvalsAndRequest = ApprovalsAndRequest::where('adjustment_type_id', $adjustementType->id)->whereNotNull('req_no')->latest('id')->first();
            if ($approvalsAndRequest) {
                $approvalsAndRequest = preg_replace('/[A-Za-z]+/', '', $approvalsAndRequest->req_no);
            }
            if ($adjustementType->id == 1) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'PD'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'PD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 2) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'R'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'R'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 3) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'B'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'B'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 4) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'A'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'A'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 5) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'FF'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'FF'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 6) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'I'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'I'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 7) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'L'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'L'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 8) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'PT'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'PT'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 9) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'TA'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'TA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }

            } elseif ($adjustementType->id == 10) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'BA'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'BA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 11) {

                if (! empty($approvalsAndRequest)) {
                    $req_no = 'RR'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'RR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 12) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'LD'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'LD'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 13) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'BR'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'BR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 14) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'SR'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'SR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 15) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'PR'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'PR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 16) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'CP'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'CP'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } elseif ($adjustementType->id == 17) {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'CR'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'CR'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            } else {
                if (! empty($approvalsAndRequest)) {
                    $req_no = 'O'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
                } else {
                    $req_no = 'O'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
                }
            }
            // echo $req_no;die;
            // return $request;
            $userwithgame = ApprovalsAndRequest::where(['adjustment_type_id' => $adjustementType->id, 'game_id' => $game_id, 'user_id' => $userdata['user_id']])->first();
            if (empty($userwithgame)) {
                $data = ApprovalsAndRequest::create([
                    'user_id' => $userdata['user_id'],
                    'manager_id' => $managerId,
                    'created_by' => $user_id,
                    'req_no' => $req_no,
                    'approved_by' => $request->approved_by,
                    'adjustment_type_id' => $request->adjustment_type_id,
                    'pay_period' => $request->pay_period,
                    'state_id' => $request->state_id,
                    'dispute_type' => $request->dispute_type,
                    // 'customer_pid' => $request->customer_pid,
                    'description' => $userdata['description'],
                    'cost_tracking_id' => $request->cost_tracking_id,
                    'emi' => $request->emi,
                    'request_date' => $request->request_date,
                    'cost_date' => $request->cost_date,
                    'amount' => $userdata['amount'],
                    'image' => $image_path,
                    'status' => 'Approved',
                    'game_id' => $game_id,
                ]);

                $customerPid = $request->customer_pid;
                if ($customerPid) {
                    //  $pid = implode(',',$customerPid);
                    $valPid = explode(',', $customerPid);
                    foreach ($valPid as $val) {
                        $customerName = SalesMaster::where('pid', $val)->first();
                        RequestApprovelByPid::create([
                            'request_id' => $data->id,
                            'pid' => $val,
                            'customer_name' => isset($customerName->customer_name) ? $customerName->customer_name : null,
                        ]);
                    }
                }

                if ($managerId) {

                    // $data =  Notification::create([
                    //     'user_id' => isset($user_data->manager_id)?$user_data->manager_id:1,
                    //     'type' => 'request-approval',
                    //     'description' => 'A new request is generated by '.$user_data->first_name,
                    //     'is_read' => 0,

                    // ]);

                    $notificationData = [
                        'user_id' => isset($user_data->manager_id) ? $user_data->manager_id : 1,
                        'device_token' => $user_data->device_token,
                        'title' => 'A new request is generated.',
                        'sound' => 'sound',
                        'type' => 'request-approval',
                        'body' => 'A new request is generated by '.$user_data->first_name,
                    ];
                    $this->sendNotification($notificationData);
                }

                if ($user_data) {
                    $full_name = $user_data->first_name.''.$user_data->last_name;
                } else {
                    $full_name = null;
                }
                $user = [

                    'user_id' => isset($user_data->manager_id) ? $user_data->manager_id : 1,
                    'description' => 'A new request is generated by '.$full_name,
                    'type' => 'request-approval',
                    'is_read' => 0,
                ];
                $notify = event(new UserloginNotification($user));
            }
        }

        return response()->json([
            'ApiName' => 'add-request',
            'status' => true,
            'message' => 'Successfully',
        ], 200);
    }
}
