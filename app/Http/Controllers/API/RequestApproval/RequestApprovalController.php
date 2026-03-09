<?php

namespace App\Http\Controllers\API\RequestApproval;

use App\Core\Traits\PayFrequencyTrait;
use App\Events\UserloginNotification;
use App\Exports\requestApprovalsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestApproval\StoreRequest;
use App\Mail\RequestArrovalMail;
use App\Models\AdjustementType;
use App\Models\ApprovalAndRequestComment;
use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\Locations;
use App\Models\Notification;
use App\Models\PayrollHistory;
use App\Models\RequestApprovelByPid;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserSchedule;
use App\Models\UserScheduleDetail;
use App\Models\UserWagesHistory;
use App\Models\OneTimePayments;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Mail;
use Validator;

class RequestApprovalController extends Controller
{
    use EmailNotificationTrait;
    use PayFrequencyTrait;
    use PushNotificationTrait;

    // public function __construct(
    //     // SendNotification $sendNotification,NotificationEvent $notificationEvent
    //     )
    // {
    //     dd(123);
    //     // $this->sendNotification = $sendNotification;
    //     // $this->notificationEvent = $notificationEvent;
    // }

    public function index(Request $request)
    {
        $user = Auth::user();
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }

        if (empty($request->name)) {

            // if($user->is_super_admin == 1)
            // {
            //     $data = ApprovalsAndRequest::with('adjustment','user')->where('status','Pending')->orderBy('id','DESC')->paginate(10);
            // }else
            // if($user->is_manager == 1){
            //     $data = ApprovalsAndRequest::with('adjustment','user')->Where('user_id',$user->id)->orderBy('id','DESC')->paginate(10);
            //     //$data = ApprovalsAndRequest::where('manager_id',$user->id)->where('user_id',$user->id)->with('adjustment','user')->where('status','Pending')->orderBy('id','DESC')->paginate(10);
            // }else{
            //     //$data = ApprovalsAndRequest::where('user_id',$user->id)->with('adjustment','user')->where('status','Approved')->orderBy('id','DESC')->paginate(10);
            //     $data = ApprovalsAndRequest::with('adjustment','user')->where('user_id',$user->id)->orderBy('id','DESC')->paginate(10);
            // }

            // $data = ApprovalsAndRequest::with('adjustment','user')->where('req_no','!=',null)->where('user_id',$user->id)->orWhere('created_by',$user->id)->where('req_no','!=',null)->orderBy('id','DESC')->paginate(10);
            $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
            }])->where('req_no', '!=', null)->where('user_id', $user->id)->orWhere('created_by', $user->id)->where('req_no', '!=', null)->orderBy('id', 'DESC');
            if ($request->has('filter') && ! empty($request->input('filter'))) {
                $search = $request->input('filter');
                $paymentRequest->where(function ($query) use ($search) {
                    $query->where('amount', 'LIKE', '%'.$search.'%')
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
                        })
                        ->orWhereHas('adjustment', function ($query) use ($search) {
                            $query->where('name', 'like', '%'.$search.'%');
                        });

                });
            }
            $data = $paymentRequest->get();

            $data = ApprovalsAndRequest::where('req_no', '!=', null)
                ->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                    $query->orWhere('created_by', $user->id);
                })->where('req_no', '!=', null)->orderBy('id', 'DESC');

            if ($request->has('filter') && ! empty($request->input('filter'))) {
                $search = $request->input('filter');
                $data->where(function ($query) use ($search) {
                    $query->where('amount', 'like', '%'.$search.'%')
                        ->orWhere('req_no', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
                        })
                        ->orWhereHas('adjustment', function ($query) use ($search) {
                            $query->where('name', 'like', '%'.$search.'%');
                        });
                });
            }
            if ($request->has('type') && ! empty($request->input('type'))) {
                $type = $request->input('type');
                $data->where(function ($query) use ($type) {
                    $query->orWhereHas('adjustment', function ($query) use ($type) {
                        $query->where('name', 'like', '%'.$type.'%');
                    });
                });
            }
            if ($request->has('status') && ! empty($request->input('status'))) {
                $status = $request->input('status');
                $data->where(function ($query) use ($status) {
                    $query->where('status', 'like', '%'.$status.'%');
                });
            }
            if ($request->has('sort') && $request->input('sort') == 'amount') {
                $data = $data->with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->get();
            } elseif ($request->has('sort') && $request->input('sort') == 'disputed') {
                $data = $data->with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->get();
            } else {
                $data = $data->with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->paginate($perpage);
            }

            $data->transform(function ($data) {
                // if(!empty($data->userComment)){
                //  $comment = $data->userComment->comment;
                // }else{
                //     $comment= '';
                // }
                // if($data->status=='Pending' && $comment!='')
                // {
                //    $status = 'New Comment';
                // }
                // else{
                $status = $data->status;
                // }

                if ($data->adjustment_type_id == 5) {
                    $data->amount = (0 - $data->amount);
                } elseif ($data->adjustment_type_id == 7) {
                    $start = Carbon::parse($data->start_date);
                    $end = Carbon::parse($data->end_date);
                    $daysCount = $start->diffInDays($end) + 1;

                    $data->amount = $daysCount;
                } elseif ($data->adjustment_type_id == 8) {
                    $start = Carbon::parse($data->start_date);
                    $end = Carbon::parse($data->end_date);
                    $daysCount = $start->diffInDays($end) + 1;
                    $ptoHoursPerday = ($data->pto_hours_perday * $daysCount);

                    $data->amount = $ptoHoursPerday;
                } elseif ($data->adjustment_type_id == 9) {

                    $timein = new Carbon($data->clock_in);
                    $timeout = new Carbon($data->clock_out);
                    $totalHoursWorkedsec = $timein->diffInSeconds($timeout);
                    $totallunch = isset($data->lunch_adjustment) ? $data->lunch_adjustment : 0;
                    $totalBreak = isset($data->break_adjustment) ? $data->break_adjustment : 0;
                    $totallunchBreakTime = ($totallunch + $totalBreak) * 60;
                    $totalworkhrs = $totalHoursWorkedsec - $totallunchBreakTime;
                    $totaltime = gmdate('H:i', $totalworkhrs);

                    $data->amount = isset($totaltime) ? $totaltime : 0;
                }

                return [
                    'id' => $data->id,
                    'req_no' => $data->req_no,
                    'employee_id' => isset($data->user->id) ? $data->user->id : null,
                    'employee_name' => isset($data->user->name) ? $data->user->name : null,
                    'employee_image' => isset($data->user->image) ? $data->user->image : null,
                    'position_id' => isset($data->user->position_id) ? $data->user->position_id : null,
                    'sub_position_id' => isset($data->user->sub_position_id) ? $data->user->sub_position_id : null,
                    'is_super_admin' => isset($data->user->is_super_admin) ? $data->user->is_super_admin : null,
                    'is_manager' => isset($data->user->is_manager) ? $data->user->is_manager : null,
                    'request_on' => $data->created_at->format('m/d/Y'),
                    // 'requestOn' =>  $data->created_at,
                    'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                    'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                    'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                    'amount' => isset($data->amount) ? $data->amount : null,
                    'description' => isset($data->description) ? $data->description : null,
                    'status' => $status,
                    'declined_at' => isset($data->declined_at) ? $data->declined_at : null,
                    'start_date' => isset($data->start_date) ? $data->start_date : null,
                    'end_date' => isset($data->end_date) ? $data->end_date : null,
                    'adjustment_date' => isset($data->adjustment_date) ? $data->adjustment_date : null,
                    'pto_hours_perday' => isset($data->pto_hours_perday) ? $data->pto_hours_perday : null,
                    'clock_in' => isset($data->clock_in) ? $data->clock_in : null,
                    'clock_out' => isset($data->clock_out) ? $data->clock_out : null,
                    'lunch_adjustment' => isset($data->lunch_adjustment) ? $data->lunch_adjustment : null,
                    'break_adjustment' => isset($data->break_adjustment) ? $data->break_adjustment : null,
                    'approvedBy' => $data->approvedBy,

                ];
            });
        } else {

            if ($user->position_id == 1) {
                $userIds = User::query()
                    ->whereRaw(
                        "TRIM(CONCAT(first_name, ' ', last_name, ' ')) like '%{$request->name}%'"
                    )->get('id');
                $data = ApprovalsAndRequest::with('adjustment')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->where('req_no', '!=', null)->whereIn('user_id', $userIds)->orWhere('created_by', $user->id)->where('req_no', '!=', null);
            } else {
                $userIds = User::query()
                    ->whereRaw(
                        "TRIM(CONCAT(first_name, ' ', last_name, ' ')) like '%{$request->name}%'"
                    )->get('id');
                $data = ApprovalsAndRequest::with('adjustment')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->where('req_no', '!=', null)->where('user_id', $user->id)->whereIn('user_id', $userIds)->orWhere('created_by', $user->id)->where('req_no', '!=', null);
            }
            if ($request->has('sort') && $request->input('sort') == 'amount') {

                $data->get();
            } elseif ($request->has('sort') && $request->input('sort') == 'disputed') {

                $data->get();
            } else {
                $data->paginate($perpage);
            }

            $data->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'req_no' => $data->req_no,
                    'employee_name' => isset($data->user->name) ? $data->user->name : null,
                    'employee_image' => isset($data->user->image) ? $data->user->image : null,
                    'position_id' => isset($data->user->position_id) ? $data->user->position_id : null,
                    'sub_position_id' => isset($data->user->sub_position_id) ? $data->user->sub_position_id : null,
                    'is_super_admin' => isset($data->user->is_super_admin) ? $data->user->is_super_admin : null,
                    'is_manager' => isset($data->user->is_manager) ? $data->user->is_manager : null,
                    'request_on' => $data->created_at->format('m/d/Y'),
                    // 'requestOn' =>  $data->created_at,
                    'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                    'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                    'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                    'amount' => isset($data->amount) ? $data->amount : null,
                    'description' => isset($data->description) ? $data->description : null,
                    'status' => $data->status,
                    'approvedBy' => $data->approvedBy,
                ];
            });
        }

        if ($request->has('sort') && $request->input('sort') == 'amount') {
            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'amount'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'amount'), SORT_ASC, $data);
            }
            $data = $this->paginates($data, $perpage);
        }
        if ($request->has('sort') && $request->input('sort') == 'disputed') {

            $val = $request->input('sort_val');
            $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($data, 'request_on'), SORT_DESC, $data);
            } else {
                array_multisort(array_column($data, 'request_on'), SORT_ASC, $data);
            }

            $data = $this->paginates($data, $perpage);
        }

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function paginates($items, $perPage = null, $page = null)
    {
        $total = count($items);

        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);

        $start = ($paginator->currentPage() - 1) * $perPage;

        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function approvallist(Request $request)
    {
        $user = Auth::user();
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $apiType = $request->input('api_type');
        if ($user->is_super_admin == 1) {

            // $paymentRequest = ApprovalsAndRequest::with('adjustment','user')->where('manager_id',null)->where('status','Pending')->orderBy('id','DESC');
            if ($apiType == 'approval') {
                $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->where('status', 'Pending')
                    ->when(request()->office_id && request()->office_id !== 'all', function ($query) {
                        $query->whereHas('user', function ($subQuery) {
                            $subQuery->where('office_id', request()->office_id);
                        });
                    })
                    ->orderBy('id', 'DESC');
            } elseif ($apiType == 'history') {
                $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->where('status', '!=', 'Pending')->where('req_no', '!=', null)
                    ->when(request()->office_id && request()->office_id !== 'all', function ($query) {
                        $query->whereHas('user', function ($subQuery) {
                            $subQuery->where('office_id', request()->office_id);
                        });
                    })
                    ->orderBy('id', 'DESC');
            }
            if ($request->has('filter') && ! empty($request->input('filter'))) {
                $search = $request->input('filter');
                $paymentRequest->where(function ($query) use ($search) {
                    $query->where('amount', 'LIKE', '%'.$search.'%')
                        ->orWhere('req_no', 'like', '%'.$search.'%');

                })->orWhereHas('user', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
                });
            }
            if ($request->has('type') && ! empty($request->input('type'))) {
                $type = $request->input('type');
                $paymentRequest->where(function ($query) use ($type) {
                    $query->orWhereHas('adjustment', function ($query) use ($type) {
                        $query->where('name', 'like', '%'.$type.'%');
                    });
                });
            }

            if ($request->has('status') && ! empty($request->input('status'))) {
                $status = $request->input('status');
                $paymentRequest->where(function ($query) use ($status) {
                    $query->where('status', 'LIKE', '%'.$status.'%')
                        ->orWhere('req_no', 'like', '%'.$status.'%');

                });
            }
            if ($request->has('sort') && $request->input('sort') != '') {
                // $data =  $paymentRequest->get();

            } else {
                // $data =  $paymentRequest->PAGINATE($perpage);
                // $data =  $paymentRequest->get();
            }
            // $data = $paymentRequest->paginate($perpage);
        } elseif ($user->is_manager == 1) {

            // $data = ApprovalsAndRequest::with('adjustment','user')->where('status','Pending')->orderBy('id','DESC')->paginate(10);

            if ($apiType == 'approval') {
                $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->where('manager_id', $user->id)->where('adjustment_type_id', '!=', 5)->where('status', 'Pending')
                    ->when(request()->office_id && request()->office_id !== 'all', function ($query) {
                        $query->whereHas('user', function ($subQuery) {
                            $subQuery->where('office_id', request()->office_id);
                        });
                    })
                    ->orderBy('id', 'DESC');
            } elseif ($apiType == 'history') {
                $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
                }])->where('manager_id', $user->id)->where('adjustment_type_id', '!=', 5)->where('status', '!=', 'Pending')->where('req_no', '!=', null)
                    ->when(request()->office_id && request()->office_id !== 'all', function ($query) {
                        $query->whereHas('user', function ($subQuery) {
                            $subQuery->where('office_id', request()->office_id);
                        });
                    })
                    ->orderBy('id', 'DESC');
            }

            if ($request->has('filter') && ! empty($request->input('filter'))) {
                $search = $request->input('filter');
                $paymentRequest->where(function ($query) use ($search) {
                    return $query->where('amount', 'LIKE', '%'.$search.'%');

                })->orWhereHas('user', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
                })
                    ->orWhereHas('adjustment', function ($query) use ($search) {
                        $query->where('name', 'like', '%'.$search.'%');
                    });
            }
            if ($request->has('type') && ! empty($request->input('type'))) {
                $type = $request->input('type');
                $paymentRequest->where(function ($query) use ($type) {
                    $query->orWhereHas('adjustment', function ($query) use ($type) {
                        $query->where('name', 'like', '%'.$type.'%');
                    });
                });
            }
            if ($request->has('status') && ! empty($request->input('status'))) {
                $status = $request->input('status');
                $paymentRequest->where(function ($query) use ($status) {
                    $query->where('status', 'LIKE', '%'.$status.'%')
                        ->orWhere('req_no', 'like', '%'.$status.'%');

                });
            }
            if ($request->has('sort') && $request->input('sort') != '') {
                // $data =  $paymentRequest->get();
            } else {

                // $data =  $paymentRequest->PAGINATE($perpage);
                // $data =  $paymentRequest->get();
            }

        } else {

            $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
                $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
            }])->where('user_id', $user->id)->where('status', 'Approved')
                ->when(request()->office_id && request()->office_id !== 'all', function ($query) {
                    $query->whereHas('user', function ($subQuery) {
                        $subQuery->where('office_id', request()->office_id);
                    });
                })
                ->orderBy('id', 'DESC');
            if ($request->has('filter') && ! empty($request->input('filter'))) {
                $search = $request->input('filter');
                $paymentRequest->where(function ($query) use ($search) {
                    return $query->where('amount', 'LIKE', '%'.$search.'%');

                })->orWhereHas('user', function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
                })
                    ->orWhereHas('adjustment', function ($query) use ($search) {
                        $query->where('name', 'like', '%'.$search.'%');
                    });
            }
            if ($request->has('type') && ! empty($request->input('type'))) {
                $type = $request->input('type');
                $paymentRequest->where(function ($query) use ($type) {
                    $query->orWhereHas('adjustment', function ($query) use ($type) {
                        $query->where('name', 'like', '%'.$type.'%');
                    });
                });
            }

            if ($request->has('status') && ! empty($request->input('status'))) {
                $status = $request->input('status');
                $paymentRequest->where(function ($query) use ($status) {
                    $query->where('status', 'LIKE', '%'.$status.'%')
                        ->orWhere('req_no', 'like', '%'.$status.'%');

                });
            }
            if ($request->has('sort') && $request->input('sort') != '') {
                // $data =  $paymentRequest->get();
            } else {
                // $data =  $paymentRequest->get();
                // $data =  $paymentRequest->PAGINATE($perpage);
            }
        }
        $data = $paymentRequest->paginate($perpage);
        // $data = $paymentRequest->get();
        // $data = $this->paginate($paymentRequest->get()->toArray(),$perpage);

        // return $data;
        // $data->transform(function ($data) {
        //     // if($data->status=='Accept')
        //     // {
        //     //     $status = 'Paid With Payroll';
        //     // }
        //     // else{
        //     //     $status = $data->status;
        //     // }

        //     return [
        //         'id' => $data->id,
        //         'req_no' => $data->req_no,
        //         'employee_id' => isset($data->user->id) ? $data->user->id : NULL,
        //         'manager_id' => isset($data->user->manager_id) ? $data->user->manager_id : NULL,
        //         'employee_name' => isset($data->user->name) ? $data->user->name : NULL,
        //         'employee_image' => isset($data->user->image) ? $data->user->image : NULL,
        //         'request_on' =>  $data->created_at->format('m/d/Y'),
        //         'requestOn' =>  $data->created_at,
        //         'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : NULL,
        //         'type' =>    isset($data->adjustment->name) ? $data->adjustment->name : NULL,
        //         'pay_period' => isset($data->pay_period )  ? $data->pay_period: NULL,
        //         'amount' => isset($data->amount) ? $data->amount : NULL,
        //         'description'  => isset($data->description) ? $data->description : NULL,
        //         'status' => $data->status,
        //         'declined_at' => isset($data->declined_at)?$data->declined_at:null,

        //     ];
        // });
        $values = [];
        /*foreach($data as $datas){
            // if($data->status=='Accept')
            // {
            //     $status = 'Paid With Payroll';
            // }
            // else{
            //     $status = $data->status;
            // }
            $type = $datas->adjustment_type_id;
            if($type==4 && $datas->amount<0)
            {
            }else{
                if(isset($datas->user->image) && $datas->user->image!=null){
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$datas->user->image);
                }else{
                    $s3_image = null;
                }
                if ($datas->adjustment_type_id == 5) {
                    $datas->amount = (0 - $datas->amount);
                }
                elseif ($datas->adjustment_type_id == 7) {
                    $start = Carbon::parse($datas->start_date);
                    $end = Carbon::parse($datas->end_date);
                    $daysCount = $start->diffInDays($end) + 1;

                    $datas->amount = $daysCount;
                }
                elseif ($datas->adjustment_type_id == 8) {
                    $start = Carbon::parse($datas->start_date);
                    $end = Carbon::parse($datas->end_date);
                    $daysCount = $start->diffInDays($end) + 1;
                    $ptoHoursPerday = ($datas->pto_hours_perday * $daysCount);

                    $datas->amount = $ptoHoursPerday;
                }
                elseif ($datas->adjustment_type_id == 9) {

                    $timein = new Carbon($datas->clock_in);
                    $timeout = new Carbon($datas->clock_out);
                    $totalHoursWorkedsec = $timein->diffInSeconds($timeout);
                    $totallunch = isset($datas->lunch_adjustment) ? $datas->lunch_adjustment : 0;
                    $totalBreak = isset($datas->break_adjustment) ? $datas->break_adjustment : 0;
                    $totallunchBreakTime = ($totallunch + $totalBreak) * 60;
                    $totalworkhrs = $totalHoursWorkedsec - $totallunchBreakTime;
                    $totaltime = gmdate('H:i', $totalworkhrs);

                    $datas->amount = isset($totaltime) ? $totaltime : 0;
                }

                $values[] = [
                    'id' => $datas->id,
                    'req_no' => $datas->req_no,
                    'employee_id' => isset($datas->user->id) ? $datas->user->id : NULL,
                    'manager_id' => isset($datas->user->manager_id) ? $datas->user->manager_id : NULL,
                    'employee_name' => isset($datas->user->name) ? $datas->user->name : NULL,
                    'position_id' => isset($datas->user->position_id) ? $datas->user->position_id : null,
                    'sub_position_id' => isset($datas->user->sub_position_id) ? $datas->user->sub_position_id : null,
                    'is_super_admin' => isset($datas->user->is_super_admin) ? $datas->user->is_super_admin : null,
                    'is_manager' => isset($datas->user->is_manager) ? $datas->user->is_manager : null,
                    'employee_image' => isset($datas->user->image) ? $datas->user->image : NULL,
                    'employee_image_s3' => $s3_image,
                    'request_on' =>  $datas->created_at->format('m/d/Y'),
                    'requestOn' =>  $datas->created_at,
                    'type_id' => isset($datas->adjustment_type_id) ? $datas->adjustment_type_id : NULL,
                    'type' =>    isset($datas->adjustment->name) ? $datas->adjustment->name : NULL,
                    'pay_period' => isset($datas->pay_period )  ? $datas->pay_period: NULL,
                    'amount' => isset($datas->amount) ? $datas->amount : NULL,
                    'description'  => isset($datas->description) ? $datas->description : NULL,
                    'status' => $datas->status,
                    'declined_at' => isset($datas->declined_at)?$datas->declined_at:null,

                ];
            }
        }*/

        /*if($request->has('sort') &&  $request->input('sort') =='amount')
        {
            $val = $request->input('sort_val');
            $values = $values;
            if($request->input('sort_val')=='desc')
            {
                array_multisort(array_column($values, 'amount'),SORT_DESC, $values);
            } else{
                array_multisort(array_column($values, 'amount'),SORT_ASC, $values);
            }

        }*/

        $data->transform(function ($datas) {
            if ($datas->status == 'Accept') {
                // $status = 'Paid With Payroll';
                $status = $datas->status;
            } else {
                $status = $datas->status;
            }
            $type = $datas->adjustment_type_id;
            if ($datas->amount < 0) {
            } else {
                if (isset($datas->user->image) && $datas->user->image != null) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$datas->user->image);
                } else {
                    $s3_image = null;
                }
                if ($datas->adjustment_type_id == 5) {
                    $datas->amount = (0 - $datas->amount);
                }
            }

            return [
                'id' => $datas->id,
                'req_no' => $datas->req_no,
                'employee_id' => isset($datas->user->id) ? $datas->user->id : null,
                'employee_name' => isset($datas->user->name) ? $datas->user->name : null,
                'employee_image' => isset($datas->user->image) ? $datas->user->image : null,
                'request_on' => $datas->created_at->format('m/d/Y'),
                'type_id' => isset($datas->adjustment_type_id) ? $datas->adjustment_type_id : null,
                'type' => isset($datas->adjustment->name) ? $datas->adjustment->name : null,
                'customer_pid' => isset($datas->PID->pid) ? $datas->PID->pid : null,
                'customer_name' => isset($datas->adjustment->customer_name) ? $datas->adjustment->customer_name : null,
                'cost_tracking_id' => isset($datas->costcenter->id) ? $datas->costcenter->id : null,
                'cost_head' => isset($datas->costcenter->name) ? $datas->costcenter->name : null,
                'state_id' => isset($datas->state->id) ? $datas->state->id : null,
                'state_name' => isset($datas->state->name) ? $datas->state->name : null,
                'pay_period' => isset($datas->pay_period) ? $datas->pay_period : null,
                'amount' => isset($datas->amount) ? $datas->amount : null,
                'dispute_type' => isset($datas->dispute_type) ? $datas->dispute_type : null,
                'description' => isset($datas->description) ? $datas->description : null,
                'emi' => isset($datas->emi) ? $datas->emi : null,
                'cost_date' => isset($datas->cost_date) ? $datas->cost_date : null,
                'status' => $status,
                'image' => isset($datas->image) ? $datas->image : null,
                'comments' => isset($comments) ? $comments : [],
                'getPid' => $datas->getPid,
                'approvedBy' => $datas->approvedBy,

            ];
        });
        // if($request->has('sort') &&  $request->input('sort') !='')
        // {
        // $values = $this->paginate($values,$perpage);
        //  }

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function paginate($items, $perPage = null, $page = null)
    {
        $total = count($items);

        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function approvalListForHistory(Request $request)
    {
        $user = Auth::user();
        $approvalsAndRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
        }])->orderBy('id', 'DESC');

        if ($request->has('status') && ! empty($request->input('status'))) {
            $status = $request->input('status');
            $approvalsAndRequest->where(function ($query) use ($status) {
                $query->where('status', $status);
            });
        }

        if ($user->is_super_admin == 1) {
            //   $data = ApprovalsAndRequest::with('adjustment','user')->orderBy('id','DESC')->where('req_no','!=',null)->where('status','!=','Pending')->paginate(10);
            // $data = ApprovalsAndRequest::with('adjustment','user')->orderBy('id','DESC')->where('req_no','!=',null)->paginate(10);
            $approvalsAndRequest->where('req_no', '!=', null);

        } elseif ($user->is_manager == 1) {
            //    $data = ApprovalsAndRequest::with('adjustment','user')->where('manager_id',$user->id)->where('approved_by',$user->id)->where('req_no','!=',null)->where('status','!=','Pending')->orderBy('id','DESC')->paginate(10);
            $approvalsAndRequest->where('manager_id', $user->id)->where('approved_by', $user->id)->where('req_no', '!=', null)->where('status', '!=', 'Pending');

        } else {
            // $data = ApprovalsAndRequest::with('adjustment','user')->where('status','Approved')->where('user_id',$user->id)->orWhere('status','Declined')->where('user_id',$user->id)->orderBy('id','DESC')->paginate(10);
            // $data = ApprovalsAndRequest::with('adjustment','user')->where('user_id',$user->id)->where('approved_by',$user->id)->where('req_no','!=',null)->where('status','!=','Pending')->paginate(10);
            $approvalsAndRequest->where('user_id', $user->id)->where('approved_by', $user->id)->where('req_no', '!=', null)->where('status', '!=', 'Pending');

        }
        $data = $approvalsAndRequest->paginate(10);
        // return $data;
        $data->transform(function ($data) {

            if ($data->comments) {
                foreach ($data->comments as $comment) {
                    $updater_detail = User::where('id', $comment->user_id)->first();
                    $comments[] =
                        [
                            'id' => $comment->id,
                            'request_id' => $comment->user_id,
                            'user_id' => $comment->user_id,
                            'user_name' => isset($updater_detail->employee_name) ? $updater_detail->employee_name : null,
                            'user_image' => isset($updater_detail->employee_image) ? $updater_detail->employee_image : null,
                            'type' => $comment->type,
                            'image' => $comment->image,
                            'comment' => $comment->comment,
                        ];
                }

            } else {
                // echo"sf";die;
                $comments = [];
            }

            if ($data->status == 'Accept') {
                $status = 'Paid With Payroll';
            } else {
                $status = $data->status;
            }

            return [
                'id' => $data->id,
                'req_no' => $data->req_no,
                'employee_id' => isset($data->user->id) ? $data->user->id : null,
                'employee_name' => isset($data->user->name) ? $data->user->name : null,
                'employee_image' => isset($data->user->image) ? $data->user->image : null,
                'request_on' => $data->created_at->format('m/d/Y'),
                'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                'customer_pid' => isset($data->PID->pid) ? $data->PID->pid : null,
                'customer_name' => isset($data->adjustment->customer_name) ? $data->adjustment->customer_name : null,
                'cost_tracking_id' => isset($data->costcenter->id) ? $data->costcenter->id : null,
                'cost_head' => isset($data->costcenter->name) ? $data->costcenter->name : null,
                'state_id' => isset($data->state->id) ? $data->state->id : null,
                'state_name' => isset($data->state->name) ? $data->state->name : null,
                'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                'amount' => isset($data->amount) ? $data->amount : null,
                'dispute_type' => isset($data->dispute_type) ? $data->dispute_type : null,
                'description' => isset($data->description) ? $data->description : null,
                'emi' => isset($data->emi) ? $data->emi : null,
                'cost_date' => isset($data->cost_date) ? $data->cost_date : null,
                'status' => $status,
                'image' => isset($data->image) ? $data->image : null,
                'comments' => isset($comments) ? $comments : [],
                'getPid' => $data->getPid,
                'approvedBy' => $data->approvedBy,

            ];
        });

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function RequestHistory()
    {
        $user = Auth::user();
        if ($user->is_super_admin == 1) {
            $data = ApprovalsAndRequest::with('adjustment', 'user')->orderBy('id', 'DESC')->paginate(10);
        } elseif ($user->position_id == 1) {

            $data = ApprovalsAndRequest::with('adjustment', 'user')->where(['status' => 'Approved', 'status' => 'Declined'])->orderBy('id', 'DESC')->get();
        } else {

            $data = ApprovalsAndRequest::where('user_id', $user->id)->with('adjustment', 'user')->where('status', 'Approved')->orderBy('id', 'DESC')->get();
        }

        // return $data;
        $data->transform(function ($data) {

            if ($data->status == 'Accept') {
                $status = 'Paid With Payroll';
            } else {
                $status = $data->status;
            }

            return [
                'id' => $data->id,
                'req_no' => $data->req_no,
                'employee_name' => isset($data->user->name) ? $data->user->name : null,
                'employee_image' => isset($data->user->image) ? $data->user->image : null,
                'request_on' => $data->created_at->format('m/d/Y'),
                'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                'amount' => isset($data->amount) ? $data->amount : null,
                'description' => isset($data->description) ? $data->description : null,
                'status' => $status,
            ];
        });

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getRequestApprovalStatusByReq_No(Request $request, $id)
    {
        $user = Auth::user();
        $data = ApprovalsAndRequest::with('getPid', 'adjustment', 'costcenter', 'state', 'user', 'PID', 'comments')->with(['approvedBy' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'is_super_admin');  // Select fields from Post
        }])->where('req_no', $id)->orWhere('txn_id', $id)->get();
        $name = isset($data[0]['adjustment']['name']) ? $data[0]['adjustment']['name'] : null;
        $comments = [];

        $data->transform(function ($data) {
            if (isset($data->image) && $data->image != null) {
                $s3_request_url = s3_getTempUrl(config('app.domain_name').'/'.$data->image);
            } else {
                $s3_request_url = null;
            }
            $officeName = Locations::where('id', $data->user->office_id)->first();
            if ($data->comments) {
                foreach ($data->comments as $comment) {
                    $updater_detail = User::where('id', $comment->user_id)->first();
                    if (isset($comment->image) && $comment->image != null) {
                        $s3_comment = s3_getTempUrl(config('app.domain_name').'/'.$comment->image);
                    } else {
                        $s3_comment = null;
                    }
                    $comments[] =
                     [
                         'id' => $comment->id,
                         'request_id' => $comment->user_id,
                         'user_id' => $comment->user_id,
                         'user_name' => isset($updater_detail->first_name) ? $updater_detail->first_name : null,
                         'user_image' => isset($updater_detail->image) ? $updater_detail->image : null,
                         'type' => $comment->type,
                         'image' => isset($comment->image) ? $comment->image : null,
                         'comment_image_s3' => $s3_comment,
                         'comment' => $comment->comment,
                     ];
                }

            } else {
                // echo"sf";die;
                $comments = [];
            }
            if ($data->status == 'Accept') {
                $status = 'Paid With Payroll';
            } else {
                $status = $data->status;
            }
            // dd($data->user->image);
            if (isset($data->user->image) && $data->user->image != null) {
                $s3_emp_url = s3_getTempUrl(config('app.domain_name').'/'.$data->user->image);
            } else {
                $s3_emp_url = null;
            }
            if ($data->adjustment_type_id == 5) {
                $data->amount = (0 - $data->amount);
            } elseif ($data->adjustment_type_id == 7) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;

                $data->amount = $daysCount;
            } elseif ($data->adjustment_type_id == 8) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;
                $ptoHoursPerday = ($data->pto_hours_perday * $daysCount);

                $data->amount = $ptoHoursPerday;
            } elseif ($data->adjustment_type_id == 9) {

                $timein = new Carbon($data->clock_in);
                $timeout = new Carbon($data->clock_out);
                $totalHoursWorkedsec = $timein->diffInSeconds($timeout);
                $totallunch = isset($data->lunch_adjustment) ? $data->lunch_adjustment : 0;
                $totalBreak = isset($data->break_adjustment) ? $data->break_adjustment : 0;
                $totallunchBreakTime = ($totallunch + $totalBreak) * 60;
                $totalworkhrs = $totalHoursWorkedsec - $totallunchBreakTime;
                $totaltime = gmdate('H:i', $totalworkhrs);

                $data->amount = isset($totaltime) ? $totaltime : 0;
            }

            if(isset($data->description) && empty($data->description)){
                $description = $data->description;
            }else{
                $req_no = $data->req_no??$data->txn_id;
                $oneTimePayment = OneTimePayments::where('req_no', $req_no)->first();
                $description = $oneTimePayment->description ?? null;
            }

            return [
                'id' => $data->id,
                'request_on' => $data->created_at->format('m/d/Y'),
                'req_no' => $data->req_no ?? $data->txn_id,
                'manager_id' => isset($data->user->manager_id) ? $data->user->manager_id : null,
                'employee_id' => isset($data->user->id) ? $data->user->id : null,
                'employee_name' => isset($data->user->name) ? $data->user->name : null,
                'employee_image' => isset($data->user->image) ? $data->user->image : null,
                'position_id' => isset($data->user->position_id) ? $data->user->position_id : null,
                'sub_position_id' => isset($data->user->sub_position_id) ? $data->user->sub_position_id : null,
                'is_super_admin' => isset($data->user->is_super_admin) ? $data->user->is_super_admin : null,
                'is_manager' => isset($data->user->is_manager) ? $data->user->is_manager : null,
                'employee_image_s3' => $s3_emp_url,
                'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                // 'customer_pid' => isset($data->PID->pid) ? $data->PID->pid : NULL,
                'customer_name' => isset($data->adjustment->customer_name) ? $data->adjustment->customer_name : null,
                'cost_tracking_id' => isset($data->costcenter->id) ? $data->costcenter->id : null,
                'cost_head' => isset($data->costcenter->name) ? $data->costcenter->name : null,
                'state_id' => isset($data->state->id) ? $data->state->id : null,
                'state_name' => isset($data->state->name) ? $data->state->name : null,
                'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                'dispute_type' => isset($data->dispute_type) ? $data->dispute_type : null,
                'description' => $description,
                'emi' => isset($data->emi) ? $data->emi : null,
                'cost_date' => isset($data->cost_date) ? $data->cost_date : null,
                'request_date' => isset($data->request_date) ? $data->request_date : null,
                'amount' => isset($data->amount) ? $data->amount : null,
                'image' => isset($data->image) ? $data->image : null,
                'request_s3' => $s3_request_url,
                'status' => isset($status) ? $status : null,
                'comments' => isset($comments) ? $comments : [],
                'location_id' => isset($data->user->office->id) ? $data->user->office->id : null,
                'location_name' => isset($data->user->office->office_name) ? $data->user->office->office_name : null,
                'getPid' => $data->getPid,
                'office_name' => isset($officeName->office_name) ? $officeName->office_name : null,
                'pay_period_from' => isset($data->pay_period_from) ? $data->pay_period_from : null,
                'pay_period_to' => isset($data->pay_period_to) ? $data->pay_period_to : null,
                'start_date' => isset($data->start_date) ? $data->start_date : null,
                'end_date' => isset($data->end_date) ? $data->end_date : null,
                'adjustment_date' => isset($data->adjustment_date) ? $data->adjustment_date : null,
                'pto_hours_perday' => isset($data->pto_hours_perday) ? $data->pto_hours_perday : null,
                'clock_in' => isset($data->clock_in) ? $data->clock_in : null,
                'clock_out' => isset($data->clock_out) ? $data->clock_out : null,
                'lunch_adjustment' => isset($data->lunch_adjustment) ? $data->lunch_adjustment : null,
                'break_adjustment' => isset($data->break_adjustment) ? $data->break_adjustment : null,
                'approvedBy' => $data->approvedBy,
            ];
        });

        $manager = User::where('id', $user->manager_id)->first();
        if ($user->manager_id) {
            // $data1 =  Notification::create([
            //     'user_id' => $user->manager_id,
            //     'type' => 'request-approval',
            //     'description' => 'A new request is generated by '.$user->first_name,
            //     'is_read' => 0,

            // ]);

            $notificationData = [

                'user_id' => $user->manager_id,
                'device_token' => $manager->device_token,
                'title' => 'A new request is generated.',
                'sound' => 'sound',
                'type' => 'request-approval',
                'body' => 'A new request is generated by '.$manager->first_name,
            ];

            // $this->sendNotification($notificationData);
        }

        return response()->json([
            'ApiName' => 'getRequestApprovalStatusByReq_No',
            'status' => true,
            'message' => 'Successfully.',
            'pid' => $id,
            'type' => $name,
            'data' => $data,
        ], 200);

    }

    public function store(StoreRequest $request)
    {
        // return $request->all();

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

        $user_id = Auth::user()->id;
        $user_data = User::where('id', $user_id)->first();
        if (! empty($request->user_id)) {
            $userID = $request->user_id;
            $userManager = $user_data = User::where('id', $userID)->first();
            $managerId = $userManager?->manager_id;

        } else {
            $userID = $user_id;
        }

        $effectiveDate = date('Y-m-d');
        if ($request->adjustment_type_id == 2) {
            $effectiveDate = $request->cost_date;
        } elseif ($request->adjustment_type_id == 3 || $request->adjustment_type_id == 5 || $request->adjustment_type_id == 6) {
            $effectiveDate = $request->request_date;
        } elseif ($request->adjustment_type_id == 7 || $request->adjustment_type_id == 8) {
            $effectiveDate = $request->end_date;
        } elseif ($request->adjustment_type_id == 9) {
            $effectiveDate = $request->adjustment_date;
        }

        $terminated = checkTerminateFlag($userID, $effectiveDate);
        if ($terminated && $terminated->is_terminate) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => 'You have been terminated',
            ], 400);
        }

        $dismissed = checkDismissFlag($userID, $effectiveDate);
        if ($dismissed && $dismissed->dismiss) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => 'You have been disabled',
            ], 400);
        }

        if ($user_data?->disable_login) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => 'Your access has been suspended',
            ], 400);
        }

        // echo $data->name;die;
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
            if (! empty($request->customer_pid)) {
                $req_no = 'C'.$request->customer_pid;
            } else {
                $req_no = 'C'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        } elseif ($adjustementType->id == 11) {
            if (! empty($approvalsAndRequest)) {
                $req_no = 'OV'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                $req_no = 'OV'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
            }
        } elseif ($adjustementType->id == 13) {
            // If there are existing approval requests, increment the count and generate a new request number
            if (! empty($approvalsAndRequest)) {
                // Format: 'PA' followed by the incremented number padded to 6 digits (e.g. PA000123)
                $req_no = 'PA'.str_pad($approvalsAndRequest + 1, 6, '0', STR_PAD_LEFT);
            } else {
                // If no existing requests, start from 'PA000001'
                $req_no = 'PA'.str_pad('000000' + 1, 6, '0', STR_PAD_LEFT);
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

        if ($adjustementType->id == 7 || $adjustementType->id == 8 || $adjustementType->id == 9) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            $insertUpdate = [
                'user_id' => $userID,
                'manager_id' => isset($managerId) ? $managerId : $user_data->manager_id,
                'created_by' => $user_id,
                'req_no' => $req_no,
                'approved_by' => $request->approved_by,
                'adjustment_type_id' => $request->adjustment_type_id,
                'pay_period' => $request->pay_period,
                'state_id' => $request->state_id,
                'dispute_type' => $request->dispute_type,
                'description' => $request->description,
                'cost_tracking_id' => $request->cost_tracking_id,
                'emi' => $request->emi,
                'request_date' => $request->request_date,
                'cost_date' => $request->cost_date,
                'amount' => $request->amount,
                'image' => $image_path,
                'status' => 'Pending',
                'start_date' => isset($request->start_date) ? $request->start_date : null,
                'end_date' => isset($request->end_date) ? $request->end_date : null,
                'pto_hours_perday' => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                'adjustment_date' => isset($request->adjustment_date) ? $request->adjustment_date : null,
                'clock_in' => isset($request->clock_in) ? $request->clock_in : null,
                'clock_out' => isset($request->clock_out) ? $request->clock_out : null,
                'lunch_adjustment' => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                'break_adjustment' => isset($request->break_adjustment) ? $request->break_adjustment : null,
            ];

            if ($adjustementType->id == 9) {
                $adjustmentDate = $request->adjustment_date;
                $clock_in = null;
                $clock_out = null;
                $userPosition = User::select('sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                $office_id = $userPosition?->office_id;

                // Check if the logged-in user's position has no office assigned.
                // If office_id is missing, return a 400 response with an appropriate error message.
                if (empty($userPosition?->office_id)) {
                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => false,
                        'message' => 'No office is assigned to your profile. Please add an office to your profile to submit the request.',
                    ], 400);
                }

                if (isset($request->clock_in) && ! empty($request->clock_in)) {
                    $clock_in = $request->clock_in;
                }
                if (isset($request->clock_out) && ! empty($request->clock_out)) {
                    $clock_out = $request->clock_out;
                }
                $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $adjustmentDate, $request->lunch_adjustment);
                $leaveData = ApprovalsAndRequest::where(['user_id' => $userID, 'adjustment_type_id' => 7])->where('start_date', '<=', $adjustmentDate)->where('end_date', '>=', $adjustmentDate)->where('status', 'Approved')->first();
                if ($leaveData) {
                    return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because this adjustment date has already been leave request'], 400);
                } else {
                    $approvalData = ApprovalsAndRequest::where('adjustment_type_id', $adjustementType->id)->where(['user_id' => $userID, 'adjustment_date' => $adjustmentDate])->first();
                    if ($approvalData) {
                        $insertUpdate['req_no'] = $approvalData->req_no;
                        ApprovalsAndRequest::where('id', $approvalData->id)->update($insertUpdate);
                    } else {
                        ApprovalsAndRequest::create($insertUpdate);
                    }
                }
            } elseif ($adjustementType->id == 7) {
                $userPosition = User::select('id', 'sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                $subPositionId = $userPosition?->sub_position_id;
                $spayFrequency = $this->payFrequency($startDate, $subPositionId, $userPosition?->id);
                $epayFrequency = $this->payFrequency($endDate, $subPositionId, $userPosition?->id);

                // Check if the logged-in user's position has no office assigned.
                // If office_id is missing, return a 400 response with an appropriate error message.
                if (empty($userPosition?->office_id)) {
                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => false,
                        'message' => 'No office is assigned to your profile. Please add an office to your profile to submit the request.',
                    ], 400);
                }

                $office_id = $userPosition?->office_id;
                $schedule_start_date = Carbon::parse($request->start_date);
                $schedule_end_date = Carbon::parse($request->end_date);
                $schedule_from = '08:00:00';
                $schedule_to = '16:00:00';
                for ($date = $schedule_start_date; $date->lte($schedule_end_date); $date->addDay()) {
                    $clock_in = $date->copy()->setTimeFromTimeString($schedule_from);
                    $clock_out = $date->copy()->setTimeFromTimeString($schedule_to);
                    // dd($schedule_from, $schedule_to, $clock_in, $clock_out);
                    $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $date, null);
                }

                if ((! empty($spayFrequency) && $spayFrequency->closed_status == 1) || (! empty($spayFrequency) && $epayFrequency->closed_status == 1)) {
                    return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the pay period has already been closed'], 400);

                } else {
                    $approvalData = ApprovalsAndRequest::where(['adjustment_type_id' => $adjustementType->id, 'user_id' => $userID])->where(['start_date' => $startDate, 'end_date' => $endDate])->first();
                    if ($approvalData) {
                        $insertUpdate['req_no'] = $approvalData->req_no;
                        ApprovalsAndRequest::where('id', $approvalData->id)->update($insertUpdate);
                    } else {
                        ApprovalsAndRequest::create($insertUpdate);
                    }
                }
            } elseif ($adjustementType->id == 8) {
                $userPosition = User::select('id', 'sub_position_id', 'office_id')->where('id', $request->user_id)->first();
                $subPositionId = $userPosition?->sub_position_id;
                $spayFrequency = $this->payFrequency($startDate, $subPositionId, $userPosition?->id);
                $epayFrequency = $this->payFrequency($endDate, $subPositionId, $userPosition?->id);

                // Check if the logged-in user's position has no office assigned.
                // If office_id is missing, return a 400 response with an appropriate error message.
                if (empty($userPosition?->office_id)) {
                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => false,
                        'message' => 'No office is assigned to your profile. Please add an office to your profile to submit the request.',
                    ], 400);
                }

                $office_id = $userPosition?->office_id;
                $schedule_start_date = Carbon::parse($request->start_date);
                $schedule_end_date = Carbon::parse($request->end_date);
                $pto_hours_perday = $request->pto_hours_perday;
                $schedule_from = '08:00:00';
                $sc_time = Carbon::createFromFormat('H:i:s', $schedule_from);
                $schedule_to = $sc_time->addHours($pto_hours_perday)->format('H:i:s');
                // dd($schedule_to);
                for ($date = $schedule_start_date; $date->lte($schedule_end_date); $date->addDay()) {
                    $clock_in = $date->copy()->setTimeFromTimeString($schedule_from);
                    $clock_out = $date->copy()->setTimeFromTimeString($schedule_to);
                    // dd($schedule_from, $schedule_to, $clock_in, $clock_out);
                    $this->createOrUpdateUserSchedules($request->user_id, $office_id, $clock_in, $clock_out, $date, null);
                }
                if ((! empty($spayFrequency) && $spayFrequency->closed_status == 1) || (! empty($spayFrequency) && $epayFrequency->closed_status == 1)) {
                    return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the pay period has already been closed'], 400);

                } else {
                    $start = Carbon::parse($startDate);
                    $end = Carbon::parse($endDate);
                    $daysCount = $start->diffInDays($end) + 1;
                    $ptoHoursPerday = ($request->pto_hours_perday * $daysCount);
                    $date = date('Y-m-d');
                    $calpto = $this->calculatePTOs($userID);
                    $usedpto = isset($calpto['total_user_ptos']) ? $calpto['total_user_ptos'] : 0;
                    // $userWagesHistory = UserWagesHistory::where('user_id', $userID)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
                    // print_r($userWagesHistory);die();
                    if ($calpto && ! empty($calpto['total_ptos']) && ($usedpto + $ptoHoursPerday) <= $calpto['total_ptos']) {
                        $checkstatus = $this->checkusedday($userID, $startDate, $endDate, $insertUpdate['pto_hours_perday']);
                        if (! empty($checkstatus)) {
                            return response()->json(['status' => false, 'message' => $checkstatus[0]], 400);
                        }
                        ApprovalsAndRequest::create($insertUpdate);
                    } else {
                        return response()->json(['status' => false, 'message' => 'Apologies, This request cannot be create because the PTO hour greater than PTO balance'], 400);
                    }
                }
            }

        } else {

            $data = ApprovalsAndRequest::create([
                'user_id' => $userID,
                'manager_id' => isset($managerId) ? $managerId : $user_data?->manager_id,
                'created_by' => $user_id,
                'req_no' => $req_no,
                'approved_by' => $request->approved_by,
                'adjustment_type_id' => $request->adjustment_type_id,
                'pay_period' => $request->pay_period,
                'state_id' => $request->state_id,
                'dispute_type' => $request->dispute_type,
                // 'customer_pid' => $request->customer_pid,
                'description' => $request->description,
                'cost_tracking_id' => $request->cost_tracking_id,
                'emi' => $request->emi,
                'request_date' => $request->request_date,
                'cost_date' => $request->cost_date,
                'amount' => $request->amount,
                'image' => $image_path,
                'status' => 'Pending',
                'start_date' => isset($request->start_date) ? $request->start_date : null,
                'end_date' => isset($request->end_date) ? $request->end_date : null,
                'pto_hours_perday' => isset($request->pto_hours_perday) ? $request->pto_hours_perday : null,
                'adjustment_date' => isset($request->adjustment_date) ? $request->adjustment_date : null,
                'clock_in' => isset($request->clock_in) ? $request->clock_in : null,
                'clock_out' => isset($request->clock_out) ? $request->clock_out : null,
                'lunch_adjustment' => isset($request->lunch_adjustment) ? $request->lunch_adjustment : null,
                'break_adjustment' => isset($request->break_adjustment) ? $request->break_adjustment : null,
            ]);

        }

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

        if ($user_data?->manager_id) {

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
                'body' => 'A new request is generated by '.$user_data->forst_name,
            ];
            $this->sendNotification($notificationData);
        }
        $user = [

            'user_id' => isset($user_data->manager_id) ? $user_data->manager_id : 1,
            'description' => 'A new request is generated by '.$user_data?->first_name.' '.$user_data?->last_name,
            'type' => 'request-approval',
            'is_read' => 0,
        ];
        $notify = event(new UserloginNotification($user));

        return response()->json([
            'ApiName' => 'add-request',
            'status' => true,
            'message' => 'Successfully',
        ], 200);

    }

    public function updateStatusOfRequest(Request $request): JsonResponse
    {
        $user_id = Auth::user()->id;
        if (Auth::user()->is_manager == 1 || Auth::user()->is_super_admin == 1) {

            $approvalsAndRequest = ApprovalsAndRequest::where('id', $request->request_id)->first();
            if ($approvalsAndRequest) {
                if ($approvalsAndRequest->user_id != $user_id) {
                    if ($request->status == 'Declined') {
                        $approvalsAndRequest->status = $request->status;
                        $approvalsAndRequest->declined_by = $user_id;
                        $approvalsAndRequest->save();
                    } else {
                        $approvalsAndRequest->status = $request->status;
                        $approvalsAndRequest->approved_by = $user_id;
                        $approvalsAndRequest->save();

                        if ($approvalsAndRequest->adjustment_type_id == 9) {
                            $this->approvedTimeAdjustment($approvalsAndRequest, $user_id);
                        }
                    }

                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => true,
                        'message' => 'Update Successfully',
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'add-request',
                        'status' => false,
                        'message' => 'You are not authorized for this action',
                    ], 400);
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Sorry the request you are looking for is not found.'], 400);

            }

        } else {
            return response()->json(['status' => false, 'message' => 'Sorry you dont have right.'], 400);
        }

    }

    public function approvedTimeAdjustment($approvalsAndRequest, $user_id)
    {
        $userId = $approvalsAndRequest->user_id;
        $adjustmentDate = $approvalsAndRequest->adjustment_date;
        if ($userId && $adjustmentDate) {

            $userAttendance = UserAttendance::where(['user_id' => $userId, 'date' => $adjustmentDate])->first();
            if ($userAttendance) {
                $detailDelete = UserAttendanceDetail::where(['user_attendance_id' => $userAttendance->id])->delete();

                $create = UserAttendanceDetail::create([
                    'user_attendance_id' => isset($userAttendance->id) ? $userAttendance->id : null,
                    'adjustment_id' => isset($approvalsAndRequest->id) ? $approvalsAndRequest->id : 0,
                    'attendance_date' => isset($approvalsAndRequest->adjustment_date) ? $approvalsAndRequest->adjustment_date : null,
                    'entry_type' => 'Adjustment',
                    'created_by' => $user_id,
                ]
                );

                // $userAttendanceDelete = $userAttendance->delete();
            }

        }
    }

    public function requestApprovalComment(Request $request): JsonResponse
    {

        $Validator = Validator::make($request->all(),
            [
                'request_id' => 'required',
                'comment' => 'required',

                // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',
            ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if (! $request->image == null) {
            //  echo"ADASD";DIE;
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

        $user_id = Auth::user();
        $approvalsAndRequest = ApprovalsAndRequest::where('id', $request->request_id)->first();
        $manager = User::where('id', $user_id->manager_id)->first();
        // if($manager == NULL)
        // {
        //     return   response()->json([
        //         'ApiName' => 'add-request',
        //         'status'  => false,
        //         'message' => '',
        //         ], 400);
        // }
        $emp = User::where('id', $approvalsAndRequest->user_id)->first();
        if ($emp == null) {
            return response()->json([
                'ApiName' => 'add-request',
                'status' => false,
                'message' => '',
            ], 400);
        }
        // dd($approvalsAndRequest->user_id);
        if ($approvalsAndRequest->user_id == $user_id->id) {

            $type = 'comment';
            // dd($manager->email);
            $mailData = [
                'name' => isset($user_id->first_name,$user_id->last_name) ? $user_id->first_name.' '.$user_id->first_name : '',
                'image' => $user_id->image,
                'comment' => $request->comment,
                'id' => $approvalsAndRequest->req_no,
                'type' => 'comment',
            ];
            if ($manager) {
                $approval = [];

                $approval['email'] = $user_id->email;
                $approval['subject'] = 'Request Approval';
                $approval['template'] = view('mail.requestapproval', compact('mailData'));
                $this->sendEmailNotification($approval);
                // Mail::to($manager->email)->send(new RequestArrovalMail($mailData));
            }

        } else {

            $type = 'reply';
            $mailData = [
                'name' => isset($user_id->first_name,$user_id->last_name) ? $user_id->first_name.' '.$user_id->first_name : '',
                'image' => $user_id->image,
                'comment' => $request->comment,
                'id' => $approvalsAndRequest->req_no,
                'type' => 'reply',
            ];
            $approval = [];

            $approval['email'] = $user_id->email;
            $approval['subject'] = 'Request Approval';
            $approval['template'] = view('mail.requestapproval', compact('mailData'));
            $this->sendEmailNotification($approval);
            // Mail::to($emp->email)->send(new RequestArrovalMail($mailData));
        }
        // echo $type;die;

        $data = ApprovalAndRequestComment::create([
            'user_id' => $user_id->id,
            'request_id' => $request->request_id,
            'type' => $type,
            'comment' => $request->comment,
            'image' => $image_path,

        ]);

        return response()->json([
            'ApiName' => 'add-request',
            'status' => true,
            'message' => 'Successfully',
        ], 200);
    }

    public function filter(Request $request)
    {
        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $data = ApprovalsAndRequest::with('adjustment', 'user')->where(function ($query) use ($request) {
                return $query->where('state_id', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('create_at', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('adjustment_type_id', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('req_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->get();
            });

            $data->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'req_no' => $data->req_no,
                    'employee_name' => isset($data->user->name) ? $data->user->name : null,
                    'employee_image' => isset($data->user->image) ? $data->user->image : null,
                    'request_on' => $data->created_at->format('m/d/Y'),
                    'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                    'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                    'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                    'amount' => isset($data->amount) ? $data->amount : null,
                    'description' => isset($data->description) ? $data->description : null,
                    'status' => $data->status,
                ];
            });
        }

        return response()->json([
            'ApiName' => 'filter-request',
            'status' => true,
            'message' => 'filter Successfully.',
            'data' => $data,
        ], 200);
    }

    public function getAdjustmenttype(): JsonResponse
    {
        $data = AdjustementType::get();

        return response()->json([
            'ApiName' => 'adjustment-type-list',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
        // }

    }

    public function searchApproval_old(Request $request)
    {
        $user_id = Auth::user()->id;
        // $result =  User::where('first_name'||'last_name', 'LIKE', '%' . $request->name . '%')->first();
        // return $result;
        $result = User::select('id', 'first_name', 'last_name')
            ->whereRaw("concat(first_name, ' ', last_name) like '%".$request->name."%' ")
            ->pluck('id');
        // dd($result);
        $data = ApprovalsAndRequest::with('status', 'adjustment', 'state', 'user')->where('manager_id', $user_id)->whereIn('user_id', $result)->get();
        // return $data;
        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'employee_image' => $data->user->image,
                'employe_name' => isset($data->user->first_name , $data->user->last_name) ? $data->user->first_name.' '.$data->user->last_name : 'NA',
                'request_on' => $data->created_at->format('m/d/Y'),
                'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : 'NA',
                'type' => isset($data->adjustment->name) ? $data->adjustment->name : 'NA',
                'disputed_date' => isset($data->disputed_data) ? $data->disputed_date : 'NA',
                'amount' => isset($data->amount) ? $data->amount : 'NA',
                'loction' => isset($data->state->name) ? $data->state->name : '',
                'reason' => isset($data->reason) ? $data->reason : 'NA',
                'status_id' => $data->status_id,
                'status_name' => $data->status->name,
            ];
        });
        if (count($data)) {
            return response()->json([
                'ApiName' => 'search-compen',
                'status' => true,
                'message' => 'search Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'search-location',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }

    }

    public function searchApproval(Request $request)
    {
        $user = Auth::user();
        if ($request->filter == null) {
            if ($user->position_id == 1) {

                $data = ApprovalsAndRequest::with('adjustment')->orderBy('id', 'asc')->get();
            } else {

                $data = ApprovalsAndRequest::with('adjustment')->where('user_id', $user->id)->orderBy('id', 'asc')->get();
            }

            // return $data;
            $data->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'req_no' => $data->req_no,
                    'employee_name' => isset($data->user->name) ? $data->user->name : null,
                    'employee_image' => isset($data->user->image) ? $data->user->image : null,
                    'request_on' => $data->created_at->format('m/d/Y'),
                    'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                    'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                    'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                    'amount' => isset($data->amount) ? $data->amount : null,
                    'description' => isset($data->description) ? $data->description : null,
                    'status' => $data->status,
                ];
            });
        } else {
            // return $request;
            $data = ApprovalsAndRequest::Where('req_no', 'like', '%'.$request->filter.'%')->get();

            // return $data;
            $data->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'req_no' => $data->req_no,
                    'employee_name' => isset($data->user->name) ? $data->user->name : null,
                    'employee_image' => isset($data->user->image) ? $data->user->image : null,
                    'request_on' => $data->created_at->format('m/d/Y'),
                    'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                    'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                    'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                    'amount' => isset($data->amount) ? $data->amount : null,
                    'description' => isset($data->description) ? $data->description : null,
                    'status' => $data->status,
                ];
            });
        }

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function filterapproval_old(Request $request)
    {
        //    return  $request;
        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $data = AdjustementType::where(function ($query) use ($request) {
                return $query->where('state_id', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('create_at', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('adjustment_type_id', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('req_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->get();
            });
        }

        return response()->json([
            'ApiName' => 'filter-request',
            'status' => true,
            'message' => 'filter Successfully.',
            'data' => $data,
        ], 200);
    }

    public function filterapproval(Request $request)
    {
        $user = Auth::user();
        if ($request->filter == null) {
            if ($user->position_id == 1) {

                $data = ApprovalsAndRequest::with('adjustment')->orderBy('id', 'asc')->get();
            } else {
                $data = ApprovalsAndRequest::with('adjustment')->where('user_id', $user->id)->orderBy('id', 'asc')->get();
            }

            // return $data;
            $data->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'req_no' => $data->req_no,
                    'employee_name' => isset($data->user->name) ? $data->user->name : null,
                    'employee_image' => isset($data->user->image) ? $data->user->image : null,
                    'request_on' => $data->created_at->format('m/d/Y'),
                    'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                    'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                    'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                    'amount' => isset($data->amount) ? $data->amount : null,
                    'description' => isset($data->description) ? $data->description : null,
                    'status' => $data->status,
                ];
            });
        } else {
            // return $request;
            $data = ApprovalsAndRequest::Where('req_no', 'like', '%'.$request->filter.'%')->get();

            // return $data;
            $data->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'req_no' => $data->req_no,
                    'employee_name' => isset($data->user->name) ? $data->user->name : null,
                    'employee_image' => isset($data->user->image) ? $data->user->image : null,
                    'request_on' => $data->created_at->format('m/d/Y'),
                    'type_id' => isset($data->adjustment_type_id) ? $data->adjustment_type_id : null,
                    'type' => isset($data->adjustment->name) ? $data->adjustment->name : null,
                    'pay_period' => isset($data->pay_period) ? $data->pay_period : null,
                    'amount' => isset($data->amount) ? $data->amount : null,
                    'description' => isset($data->description) ? $data->description : null,
                    'status' => $data->status,
                ];
            });
        }

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function searchRequestByPid(Request $request): JsonResponse
    {

        if ($request->has('filter') && ! empty($request->input('filter'))) {

            $data = SalesMaster::select('id', 'pid', 'customer_name', 'customer_state', 'kw')->where('pid', 'LIKE', '%'.$request->input('filter').'%')
                ->get();

            return response()->json([
                'ApiName' => 'Search pid Api',
                'status' => true,
                'message' => 'Search Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'Search pid Api',
                'status' => false,
                'message' => 'Bad Request.',
            ], 400);
        }

    }

    public function DeletePidForRequestApprovel($id): JsonResponse
    {

        if ($id) {
            $data = RequestApprovelByPid::where('id', $id)->delete();

            return response()->json([
                'ApiName' => 'Delete pid Api for Request',
                'status' => true,
                'message' => 'Search Successfully.',

            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'Delete pid Api for Request',
                'status' => false,
                'message' => 'Bad Request.',
            ], 400);
        }

    }

    public function exportRequestApprovalHistory(Request $request)
    {
        $type = $request->input('type');
        $filter = $request->input('filter');

        $file_name = 'approval_history_'.date('Y_m_d_H_i_s').'.xlsx';

        // return Excel::download(new requestApprovalsExport($type, $filter),$file_name);
        Excel::store(new requestApprovalsExport($type, $filter),
            'exports/request-and-approvals/'.$file_name,
            'public',
            \Maatwebsite\Excel\Excel::XLSX
        );

        // Get the URL for the stored file
        // Return the URL in the API response
        $url = getStoragePath('exports/request-and-approvals/'.$file_name);

        // $url = getExportBaseUrl().'storage/exports/request-and-approvals/' . $file_name;
        return response()->json(['url' => $url]);
        // return Excel::download(new ReportSalesExport($data), $file_name);
    }

    public function testApi(Request $request): JsonResponse
    {
        echo 'Do not run it again';
        $type = $request->type;

        // if($type == "Bank"){

        //     $datas = PayrollHistory::where('pay_type', $type)->where('payroll_id','!=',0)->select('pay_period_from','pay_period_to','user_id','payroll_id')->get();
        //     foreach($datas as $data){
        //         $approvel = ApprovalsAndRequest::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserCommission::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserOverrides::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = ClawbackSettlement::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //     }
        //     foreach($datas as $data){
        //         $approvel = ApprovalsAndRequest::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserCommission::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserOverrides::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = ClawbackSettlement::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //     }

        // }elseif($type == "Manualy"){
        //     $datas = PayrollHistory::where('pay_type', $type)->where('payroll_id','!=',0)->select('pay_period_from','pay_period_to','user_id','payroll_id')->get();
        //     foreach($datas as $data){
        //         $approvel = ApprovalsAndRequest::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>1,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserCommission::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>1,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserOverrides::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>1,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = ClawbackSettlement::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'is_mark_paid'=>1,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //     }

        //     foreach($datas as $data){
        //         $approvel = ApprovalsAndRequest::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserCommission::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = UserOverrides::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //         $approvel = ClawbackSettlement::where(['pay_period_from'=>$data->pay_period_from,'pay_period_to'=>$data->pay_period_to,'payroll_id'=>0,'user_id'=>$data->user_id])->update(['payroll_id'=>$data->payroll_id]);
        //     }
        // }

        return response()->json([
            'ApiName' => 'Data update',
            'status' => true,
            'message' => 'data update Successfully.',

        ], 200);

    }

    public function getTimeAdjustmentByUser(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'adjustment_date' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;
        $date = $request->adjustment_date;
        $userAttendance = UserAttendance::with('userattendancelist')->where('user_id', $userId)->where('date', $date)->orderBy('id', 'asc')->first();
        if ($userAttendance) {
            $data = [];
            if (count($userAttendance['userattendancelist']) > 0) {
                $userAttendanceDetail = $userAttendance['userattendancelist'];
                $clockIn = $userAttendanceDetail->where('type', 'clock in')->first();
                $clockOut = $userAttendanceDetail->where('type', 'clock out')->first();

                $clockInTime = isset($clockIn['attendance_date']) ? date('H:i', strtotime($clockIn['attendance_date'])) : null;
                $clockOutTime = isset($clockOut['attendance_date']) ? date('H:i', strtotime($clockOut['attendance_date'])) : null;

                $data = [
                    'id' => $userAttendance->id,
                    'user_id' => $userId,
                    'date' => $date,
                    'clock_in' => $clockInTime,
                    'clock_out' => $clockOutTime,
                    'lunch' => $userAttendance->lunch_time,
                    'break' => $userAttendance->break_time,
                ];

            }

            return response()->json([
                'ApiName' => 'get_time_adjustment_by_user',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'get_time_adjustment_by_user',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }

    }

    public function getPOThourByUser(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;
        $date = date('Y-m-d');

        $userWagesHistory = UserWagesHistory::where('user_id', $userId)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
        $calpto = $this->calculatePTOs($userId);
        if ($userWagesHistory) {
            $data = [
                'id' => $userWagesHistory->id,
                'user_id' => $userId,
                'pto_hours' => $userWagesHistory->pto_hours,
                'total_ptos' => $calpto['total_ptos'] ?? 0,
                'total_used_ptos' => $calpto['total_user_ptos'] ?? 0,
                'total_remaining_ptos' => $calpto['total_remaining_ptos'] ?? 0,
                'pto_hours_effective_date' => $userWagesHistory->pto_hours_effective_date,
            ];

            return response()->json([
                'ApiName' => 'get_pto_hours_by_user',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'get_pto_hours_by_user',
                'status' => false,
                'message' => 'data not found',
                'data' => [],
            ], 200);

        }

    }

    private function calculatePTOs($user_id = null, $date = null)
    {
        if ($date == null) {
            $date = date('Y-m-d');
        }
        if ($user_id == null) {
            $user_id = Auth::user()->id;
        }
        // $user = User::find($user_id);
        $total_used_pto_hours = 0;
        $total_pto_hours = 0;
        $date = Carbon::parse($date);
        $user = UserWagesHistory::where('user_id', $user_id)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
        if ($user?->unused_pto_expires == 'Monthly' || $user?->unused_pto_expires == 'Expires Monthly') {
            $total_pto_hours = $user?->pto_hours;
            $start_date = $date->copy()->startOfMonth()->toDateString();
            $end_date = $date->copy()->endOfMonth()->toDateString();
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }

                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user?->unused_pto_expires == 'Annually' || $user?->unused_pto_expires == 'Expires Annually') {
            $start_date = $date->copy()->startOfYear()->toDateString();
            $end_date = $date->copy()->endOfYear()->toDateString();

            $pto_start_date = (! empty($user?->created_at) ? (Carbon::parse($user?->created_at)->lt($date->copy()->startOfYear()) ? $date->copy()->startOfYear() : Carbon::parse($user?->created_at)) : $date->copy()->startOfYear());
            $monthCount = $pto_start_date->diffInMonths($date);
            $total_pto_hours = $user?->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('start_date', [$start_date, $end_date])
                        ->orWhereBetween('end_date', [$start_date, $end_date]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);
            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                if ($pto_end_date->lt($start_date) || $pto_start_date->gt($end_date)) {
                    continue; // Skip PTOs outside the current month
                }
                $overlap_start = $pto_start_date->gt($start_date) ? $pto_start_date : $start_date;
                $overlap_end = $pto_end_date->lt($end_date) ? $pto_end_date : $end_date;
                $days = $overlap_start->diffInDays($overlap_end) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user?->unused_pto_expires == 'Accrues Continuously' || $user?->unused_pto_expires == 'Expires Accrues Continuously') {
            $monthCount = Carbon::parse($user?->created_at)->diffInMonths($date);
            $total_pto_hours = $user?->pto_hours * ($monthCount + 1);
            $user_ptos = ApprovalsAndRequest::where('user_id', $user_id)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($user_ptos as $pto) {
                $pto_start_date = Carbon::parse($pto->start_date);
                $pto_end_date = Carbon::parse($pto->end_date);
                $days = $pto_start_date->diffInDays($pto_end_date) + 1;
                $total_used_pto_hours += $days * $pto->pto_hours_perday;
            }
        }

        return [
            'total_ptos' => (int) $total_pto_hours,
            'total_user_ptos' => (int) $total_used_pto_hours,
            'total_remaining_ptos' => (int) $total_pto_hours - $total_used_pto_hours,
        ];
    }

    private function checkUsedDay($id, $start_date, $end_date, $requestday)
    {
        $start = \Carbon\Carbon::parse($start_date);
        $end = \Carbon\Carbon::parse($end_date);
        $error = [];
        if ($start->isSameDay($end)) {
            $approvalData = ApprovalsAndRequest::where([
                'adjustment_type_id' => 8,
                'user_id' => $id,
            ])->whereDate('start_date', '<=', $start_date)
                ->whereDate('end_date', '>=', $start_date)
                ->where('status', '!=', 'Declined')->get();
            $tpto = 0;
            foreach ($approvalData as $approval) {
                $tpto += $approval->pto_hours_perday;
            }
            if ($approvalData && ($tpto + $requestday) > 8) {
                $error[] = $start->format('m/d/y').' request cannot be created because the PTO hours exceed 8.';
            }
        } else {
            foreach ($start->daysUntil($end) as $date) {
                $approvalData = ApprovalsAndRequest::where([
                    'adjustment_type_id' => 8,
                    'user_id' => $id,
                ])->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date)
                    ->where('status', '!=', 'Declined')
                    ->get();
                $tpto = 0;
                foreach ($approvalData as $approval) {
                    $tpto += $approval->pto_hours_perday;
                }
                if ($approvalData && ($tpto + $requestday) > 8) {
                    $error[] = $date->format('m/d/y').' request cannot be created because the PTO hours exceed 8.';
                }
            }
        }

        return $error;
    }

    private function createOrUpdateUserSchedules($user_id, $office_id, $clock_in, $clock_out, $adjustment_date, $lunch)
    {
        // dd($user_id, $office_id,$clock_in,$clock_out,$adjustment_date,$lunch);
        if (! empty($lunch) && ! is_null($lunch) && $lunch != 'None') {
            $lunch = $lunch.' Mins';
        }
        $userschedule = UserSchedule::where('user_id', $user_id)->first();
        // dd($userschedule);
        $s_date = Carbon::parse($clock_in);
        $dayNumber = $s_date->dayOfWeekIso;
        if ($userschedule) {
            $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $userschedule->id)->where('office_id', $office_id)->wheredate('schedule_from', $adjustment_date)->first();
            if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                $scheduleDetaisData = [
                    'schedule_id' => $userschedule->id,
                    'office_id' => $office_id,
                    'schedule_from' => $clock_in,
                    // 'schedule_to' => $scheduleTo->toDateTimeString(),
                    'schedule_to' => $clock_out,
                    'lunch_duration' => $lunch,
                    'work_days' => $dayNumber,
                    'repeated_batch' => 0,
                    'user_attendance_id' => null,
                ];
                $dataStored = UserScheduleDetail::create($scheduleDetaisData);
            } else {
                // update schedules
                // $checkUserScheduleDetail->where('schedule_id',$userschedule->id)->where('office_id',$office_id)->wheredate('schedule_from',$adjustment_date)->update(['schedule_from' => $clock_in, 'schedule_to' => $clock_out,'lunch_duration' => $lunch, 'work_days' => $dayNumber]);
            }
        } else {
            $create_userschedule = UserSchedule::create(['user_id' => $user_id, 'scheduled_by' => Auth::user()->id]);
            if ($create_userschedule) {
                $checkUserScheduleDetail = UserScheduleDetail::where('schedule_id', $create_userschedule->id)
                    ->where('office_id', $office_id)
                    ->wheredate('schedule_from', $adjustment_date)
                    ->first();
                if (empty($checkUserScheduleDetail) || $checkUserScheduleDetail == null) {
                    $scheduleDetaisData = [
                        'schedule_id' => $create_userschedule->id,
                        'office_id' => $office_id,
                        'schedule_from' => $clock_in,
                        'schedule_to' => $clock_out,
                        'lunch_duration' => null,
                        'work_days' => $dayNumber,
                        'repeated_batch' => 0,
                        'user_attendance_id' => null,
                    ];
                    $dataStored = UserScheduleDetail::create($scheduleDetaisData);
                }
            }
        }
    }
}
