<?php

namespace App\Http\Controllers\API\Hiring;

use App\Core\Traits\PermissionCheckTrait;
use App\Events\UserloginNotification;
use App\Exports\LeadsExport;
use App\Exports\LeadsExportSample;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadsValidatedRequest;
use App\Imports\LeadsImport;
use App\Models\AdditionalCustomField;
use App\Models\AdditionalLocations;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\EventCalendar;
use App\Models\Lead;
use App\Models\leadComment;
use App\Models\LeadCommentReply;
use App\Models\LeadDocument;
use App\Models\Notification;
use App\Models\PipelineLeadStatus;
use App\Models\PipelineLeadStatusHistory;
use App\Models\PipelineSubTask;
use App\Models\PipelineSubTaskCompleteByLead;
use App\Models\ScheduleTimeMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserScheduleTime;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Excel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LeadsController extends Controller
{
    use EmailNotificationTrait;
    use PermissionCheckTrait;
    use PushNotificationTrait;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }

        $is_manager = auth()->user()->is_manager;
        $is_super_admin = auth()->user()->is_super_admin;
        $leadQuery = $this->lead->with([
            'recruiter',
            'reportingManager',
            'state',
            'comment',
            'pipelineleadstatus.sub_tasks',
            'pipelineleadstatus' => function ($query) {
                $query->withCount([
                    // 'completedSubTasks',
                    // 'incompleteSubTasks',
                    'sub_tasks',
                ]);
            },
            'leadDocuments',
            'newSequiDocsDocuments',
        ])
        // ->withCount('pipelineleadstatus.incompletePipelineSubTasks')
        // ->withCount('pipelineleadstatus.completedPipelineSubTasks')
            ->withCount('comment')
            ->withCount('leadDocuments')
            ->withCount('newSequiDocsDocuments');

        if (! $is_super_admin && $is_manager == 1) {
            // dd(auth()->user()->is_super_admin);
            $lead = $leadQuery
                ->where(function ($query) {

                    // if(!$is_super_admin && $is_manager == 1){
                    /**
                     * Anyone with Manager's position can view leads ONLY for office/s they are associated with.
                     * Super Admin can view ALL across the system.
                     */
                    // dd(auth()->user()->id);

                    $additionalLocations = AdditionalLocations::where('user_id', auth()->user()->id)->pluck('office_id')->toArray();

                    $query->where([
                        'office_id' => auth()->user()->office_id,
                    ]);

                    if ($additionalLocations) {
                        $query->orWhereIn('office_id', $additionalLocations);
                    }

                    // get
                    $subordinateIds = $this->getSubordinateIds(auth()->user()->id);
                    $subOrdUsersOfficeIds = User::whereIn('id', $subordinateIds)->pluck('office_id')->toArray();
                    if ($subOrdUsersOfficeIds) {
                        $query->orWhereIn('office_id', $subOrdUsersOfficeIds);
                    }
                    $subOrdUsersAdditionalOfficeIds = AdditionalLocations::whereIn('user_id', $subordinateIds)->pluck('office_id')->toArray();
                    if ($subOrdUsersAdditionalOfficeIds) {
                        $query->orWhereIn('office_id', $subOrdUsersAdditionalOfficeIds);
                    }
                    // dd($subOrd);

                });

        } elseif ($is_super_admin) {
            $lead = $leadQuery;
        } else {
            $lead = $leadQuery->where('recruiter_id', auth()->user()->id);
            // return response()->json([
            //     'ApiName' => 'Lead_list',
            //     'status' => true,
            //     'message' => 'Successfully.',
            //     'data' => [],
            // ], 200);

        }

        // return $lead->get();

        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('filter') && ! empty($request->input('filter'))) {

            $lead->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('source', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereHas('reportingManager', function ($q) {
                        $q->where(function ($q) {
                            $q->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', [request()->input('filter')])
                                ->orWhere('first_name', 'LIKE', request()->input('filter'))
                                ->orWhere('last_name', 'LIKE', request()->input('filter'));
                        });
                    });
            });
            // ->orWhereHas('additionalEmails', function ($query) use ($request)  {
            //     $query->where('email', 'like', '%' . $request->input('filter') . '%');
            // });

        }

        if ($request->has('status_filter') && ! empty($request->input('status_filter'))) {
            $lead->where(function ($query) use ($request) {
                $leadBucket = PipelineLeadStatus::where('status_name', $request->input('status_filter'))->first();
                if ($leadBucket) {
                    return $query->where('pipeline_status_id', $leadBucket->id);
                } else {
                    return $query->where('status', $request->input('status_filter'));
                }
            });
        }

        if ($request->has('home_state_filter') && ! empty($request->input('home_state_filter'))) {
            $lead->where(function ($query) use ($request) {
                return $query->where('state_id', $request->input('home_state_filter'));
            });
        }
        if ($request->has('recruter_filter') && ! empty($request->input('recruter_filter'))) {
            $lead->where(function ($query) use ($request) {
                return $query->where('recruiter_id', $request->input('recruter_filter'));
            });
        }
        if ($request->has('reporting_manager') && ! empty($request->input('reporting_manager'))) {

            $lead->whereHas('reportingManager', function ($query) use ($request) {
                $query->where('id', $request->input('reporting_manager'));
            });

        }
        if ($request->has('overall_rating') && ! empty($request->input('overall_rating'))) {
            $lead->where(function ($query) use ($request) {
                $query->where('overall_rating', $request->input('overall_rating'));
            });
        }
        // $data = $lead->orderBy('id',$orderBy)->paginate(env('PAGINATE'));
        // start lead display listing  by nikhil
        $superAdmin = Auth::user()->is_super_admin;
        $user_id = Auth::user()->id;
        $positionId = Auth::user()->position_id;
        $recruiterId = Auth::user()->recruiter_id;
        // $lead->with('recruiter','reportingManager','state', 'pipelineleadstatus');

        if ($superAdmin != 1) {
            if ($positionId != 1) {
                if ($request->has('status_filter') && ! empty($request->input('status_filter')) && empty($request->input('home_state_filter'))) {

                    // $data = $lead->where('recruiter_id', $user_id);
                    $data = $lead->where(function ($query) use ($request) {
                        $leadBucket = PipelineLeadStatus::where('status_name', $request->input('status_filter'))->first();
                        if ($leadBucket) {
                            return $query->where('pipeline_status_id', $leadBucket->id);
                        } else {
                            return $query->where('status', $request->input('status_filter'));
                        }
                    })
                        ->orWhere('reporting_manager_id', $user_id);

                } elseif ($request->has('home_state_filter') && ! empty($request->input('home_state_filter')) && empty($request->input('status_filter'))) {

                    // $data = $lead->where('recruiter_id', $user_id);
                    $data = $lead->orWhere('reporting_manager_id', $user_id)
                        ->where(function ($query) use ($request) {
                            return $query->where('state_id', $request->input('home_state_filter'));
                        });
                } elseif ($request->has('status_filter') && ! empty($request->input('status_filter')) && $request->has('home_state_filter') && ! empty($request->input('home_state_filter'))) {
                    // $data = $lead->where('recruiter_id', $user_id);
                    $data = $lead->where(function ($query) use ($request) {
                        $leadBucket = PipelineLeadStatus::where('status_name', $request->input('status_filter'))->first();
                        if ($leadBucket) {
                            return $query->where('pipeline_status_id', $leadBucket->id);
                        } else {
                            return $query->where('status', $request->input('status_filter'));
                        }
                    })
                        ->where(function ($query) use ($request) {
                            return $query->where('state_id', $request->input('home_state_filter'));
                        })
                        ->orWhere('reporting_manager_id', $user_id);
                } else {

                    // $data = $lead->where('recruiter_id', $user_id);
                    $data = $lead->whereRaw('1 = 1');
                    $data = $lead->orWhere('reporting_manager_id', $user_id);

                }

            } else {
                $managerUser = User::select('id', 'manager_id')
                    ->where('manager_id', $user_id)->get();
                $csid = [];
                foreach ($managerUser as $managerUsers) {
                    $csid[] = $managerUsers->id;
                }
                $data = $lead->whereIn('recruiter_id', $csid)
                    ->Orwhere('recruiter_id', $user_id)
                    ->where('type', 'lead');
            }
        }
        $data = $lead->where('type', 'lead')
            ->where('status', '!=', 'Hired')
            ->orderBy('id', $orderBy)
            ->paginate($perpage);

        $data->transform(function ($lead) {

            // $lead->lead_custom_fields_details_OLDDATA = json_decode($lead->custom_fields_detail);

            $lead->custom_fields_detail = AdditionalCustomField::where('type', 'lead')
                ->where('is_deleted', 0)
                ->orderBy('id', 'Asc')
                ->get()
                ->map(function ($field) use ($lead) {

                    $lead_custom_fields_details = json_decode($lead->custom_fields_detail);

                    try {

                        if ($field->attribute_option_rating == '[null]') {
                            $field->attribute_option_rating = [];
                        }
                        $field->attribute_option = json_decode($field->attribute_option, true) ?? [];

                        if ($lead_custom_fields_details != null) {

                            $result = array_filter($lead_custom_fields_details, function ($item) use ($field) {
                                return $item->configuration_id === $field->configuration_id && $item->field_name === $field->field_name;
                            });

                            if (! empty($result)) {
                                $value = reset($result)->value;
                                $field->value = $value;
                            }
                        }

                    } catch (\Exception $e) {
                        // dump($e);
                        $field->attribute_option = [];
                    }

                    return $field;

                });

            // Calculate days_in_status
            $lead->days_in_status = DB::table('leads')
                ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                ->where('id', $lead->id)
                ->value('days_in_status');

            /*




            $tasksWithCompletionStatus = $tasks->map(function ($task) use ($completedTaskIds) {
                $task->completed = in_array($task->id, $completedTaskIds); // Check if the task is completed
                return $task;
            });
            */

            $completed_sub_tasks_count = PipelineSubTaskCompleteByLead::where([
                'lead_id' => $lead->id,
                'pipeline_lead_status_id' => $lead->pipeline_status_id,
                'completed' => '1', // should string
            ])->count();

            $alltasksCount = PipelineSubTask::where([
                'pipeline_lead_status_id' => $lead->pipeline_status_id,
            ])->count();

            // dd($alltasksCount);

            if ($lead->pipelineleadstatus) {
                $lead->completed_sub_tasks_count = $completed_sub_tasks_count;
                $lead->incomplete_sub_tasks_count = $alltasksCount - $completed_sub_tasks_count ?? 0;
                $lead->action_status = $lead->pipelineleadstatus->status_name;
            }

            $lead->document_count = $lead->lead_documents_count + $lead->new_sequi_docs_documents_count;

            return $lead;
        });

        // end lead display listing  by nikhil
        // return $data;
        if (! empty($data)) {
            return response()->json([
                'ApiName' => 'Lead_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Lead_list',
                'status' => false,
                'message' => 'Data is not available.',
            ], 200);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(LeadsValidatedRequest $request)
    {

        // $recru = User::where('id',$request['recruiter_id'])->first();
        $user = auth('api')->user();
        $lead_added_email_content = [];
        // DB::beginTransaction();
        if ($user->manager_id == null) {
            $recruiterId = $user->id;
        } else {
            $recruiterId = $user->manager_id;
        }
        // if(isset($recru) && $recru!='')
        // {
        if (! null == $request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'email' => 'required|email|unique:leads|unique:users|unique:onboarding_employees',
                    'mobile_no' => 'required|min:10|unique:leads,mobile_no|unique:onboarding_employees,mobile_no|unique:users,mobile_no',

                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $source = User::where('id', $user->id)->first();

            $lastName = isset($source->last_name) ? $source->last_name : null;
            $customFieldsDetail = json_encode($request['custom_fields_detail']);
            // return $customFieldsDetail;

            $data = Lead::create(
                [
                    'first_name' => $request['first_name'],
                    'last_name' => $request['last_name'],
                    'email' => $request['email'],
                    'mobile_no' => $request['mobile_no'],
                    'state_id' => $request['state_id'],
                    'office_id' => $user->office_id,
                    'comments' => $request['comments'],
                    'status' => $request['status'],
                    'action_status' => $request['action_status'],
                    'source' => isset($source->first_name) ? $source->first_name.' '.$lastName : null,
                    'reporting_manager_id' => $request['reporting_manager_id'],
                    'recruiter_id' => $user->id,
                    'type' => 'Lead',
                    'pipeline_status_date' => date('Y-m-d'),
                    'custom_fields_detail' => isset($customFieldsDetail) ? $customFieldsDetail : null,
                ]
            );

            $mySales = Lead::where('id', $data->id)->get();
            $CrmData = Crms::where('id', 1)->where('status', 1)->first();
            $companyProfile = CompanyProfile::first();

            // if(!empty($CrmData)){
            if ($mySales != '[]') {
                $datas = $mySales;
                $salesData = [];
                // Old code
                // $salesData['email'] = $request['email'];
                // $salesData['subject'] = 'Welcome to Sequifi ';
                // $salesData['template'] = view('mail.leadalert', compact('datas') );
                //  $this->sendEmailNotification($salesData);
                // new code based on dynamic mail.
                $lead_added_email_content = Lead::lead_added_email_content($datas);
                // return $lead_added_email_content['template'];
                $salesData['email'] = $request['email'];
                $salesData['subject'] = $lead_added_email_content['subject'];
                $salesData['template'] = $lead_added_email_content['template'];
                if ($lead_added_email_content['is_active'] == 1 && $lead_added_email_content['template'] != '') {
                    $this->sendEmailNotification($salesData);
                } else {
                    // $salesData =[];
                    // $salesData['email'] = $request['email'];
                    // $salesData['subject'] = 'Welcome to Sequifi ';
                    // $salesData['template'] = view('mail.leadalert', compact('datas') );
                    // if (config("app.domain_name") !== 'onyx' && $companyProfile->company_type != CompanyProfile::TURF_COMPANY_TYPE) {
                    //     $this->sendEmailNotification($salesData);
                    // }
                }
            }
            // }

            // $manager=User::where('id',$mySales->interview_schedule_by_id)->first();
            // $data = Notification::create([
            //     'user_id' => $manager->id,
            //     'type' => 'Add Lead',
            //     'description' => 'Add Lead Data by ' . auth()->user()->first_name,
            //     'is_read' => 0,
            // ]);
            // $notificationData = array(
            //     'user_id'      => $manager->id,
            //     'device_token' => $manager->device_token,
            //     'title'        => 'Add Lead Data.',
            //     'sound'        => 'sound',
            //     'type'         => 'Add Lead',
            //     'body'         => 'Add Lead Data by ' . auth()->user()->first_name,
            // );
            // $this->sendNotification($notificationData);

            $comment = null;

            if (! empty($request['comments'])) {
                $comment = leadComment::create(
                    [
                        'user_id' => $user->id,
                        'lead_id' => $data->id,
                        'comments' => $request['comments'],
                        'status' => 1,
                    ]
                );
            }

            return response()->json([
                'ApiName' => 'add-leads',
                'status' => true,
                'message' => 'Added Successfully.',
                'data' => $data,
                'comment' => $comment,
                'lead_added_email_content' => $lead_added_email_content,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'add-leads',
                'status' => false,
                'message' => 'Invalid Recruiter Id.',
            ], 200);
        }

    }

    public function interviewSchedule(Request $request, $id): JsonResponse
    {

        $interviewDate = Lead::find($id);
        if (! isset($interviewDate) && $interviewDate == null) {
            return response()->json([
                'ApiName' => 'Reschedule Interview',
                'status' => false,
                'message' => 'Invalid Lead Id.',
            ], 200);
        }
        $interviewDate->interview_date = $request['interview_date'];
        $interviewDate->interview_time = $request['schedule'];
        $interviewDate->interview_schedule_by_id = $request['interview_schedule_by_id'];
        $interviewDate->action_status = $request['action_status'];
        if ($request['action_status'] == 'Rejected') {
            $interviewDate->status = 'Rejected';
        }
        $interviewDate->save();
        $manager = Auth()->user()->position_id;
        if ($manager == 1) {
            $mySales = Lead::where('id', $id)->first();
            $request['name'] = $mySales->name;
            $CrmData = Crms::where('id', 1)->where('status', 1)->first();
            if (! empty($CrmData)) {
                if ($mySales != '[]') {
                    $data = $mySales;
                    $salesData = [];
                    $salesData['email'] = Auth()->user()->email;
                    $salesData['subject'] = 'Interview scheduled';
                    $salesData['template'] = view('mail.interview', compact('request'));
                    $this->sendEmailNotification($salesData);

                    $salesData = [];
                    $salesData['email'] = $mySales->email;
                    $salesData['subject'] = 'Interview scheduled ';
                    $salesData['template'] = view('mail.interview', compact('request'));
                    $this->sendEmailNotification($salesData);
                }
            }
        }
        $stateId = Lead::where('id', $id)->first();
        $event = EventCalendar::where('event_date', $request['interview_date'])->where('state_id', $stateId->state_id)->first();
        if (! isset($event) && $event == '') {
            $data = EventCalendar::create(
                [
                    'event_date' => $request['interview_date'],
                    'type' => 'interView',
                    'state_id' => $stateId->state_id,
                    'event_name' => $request['event_name'],
                ]
            );

        }

        $lead = Lead::where('id', $id)->first();
        $user = [

            'user_id' => $request['interview_schedule_by_id'],
            'description' => 'A Interview schedule for '.$lead->first_name.' '.$lead->last_name.' Date = '.$lead->interview_date.' Time = '.$lead->interview_time,
            'type' => 'Interview schedule',
            'is_read' => 0,
        ];

        $notify = event(new UserloginNotification($user));
        $leadComment = leadComment::create(
            [
                'lead_id' => $interviewDate->interview_schedule_by_id,
                'user_id' => auth::user()->id,
                'comments' => 'Interview Rescheduled'.','.$request['interview_date'],
                'status' => '1',
            ]
        );

        return response()->json([
            'ApiName' => 'Reschedule Interview',
            'status' => true,
            'message' => 'Reschedule Interview Successfully.',
            'data' => $interviewDate,
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {

        if (! null == $request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [

                    'email' => 'required|email|unique:leads,email,'.$id.'|unique:users|unique:onboarding_employees',
                    'mobile_no' => 'required|min:10|unique:leads,mobile_no,'.$id.'|unique:onboarding_employees,mobile_no|unique:users,mobile_no',
                    // 'mobile_no' => 'required|unique:leads,mobile_no,'.$id.'',

                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $data = Lead::find($id);
            if ($data == null) {
                return response()->json([
                    'ApiName' => 'Update Leads',
                    'status' => false,
                    'message' => 'Invalid ID',
                ], 404);
            }

            if (isset($request['custom_fields_detail'])) {
                $customFieldsDetail = json_encode($request['custom_fields_detail']);
            }

            $data->first_name = $request['first_name'];
            $data->last_name = $request['last_name'];
            $data->email = $request['email'];
            $data->mobile_no = $request['mobile_no'];
            $data->state_id = $request['state_id'];
            $data->status = $request['status'];
            // $data->recruiter_id = $request['recruiter_id'];
            $data->reporting_manager_id = $request['reporting_manager_id'];
            $data->custom_fields_detail = isset($customFieldsDetail) ? $customFieldsDetail : null;
            $data->save();

            // $data = $data;
            // $salesData =[];
            // $salesData['email'] = $request['email'];
            // $salesData['subject'] = 'Welcome to Sequifi ';
            // $salesData['template'] = view('mail.leadalert', compact('data') );
            // $this->sendEmailNotification($salesData);

            return response()->json([
                'ApiName' => 'Update Leads',
                'status' => true,
                'message' => 'Updated Lead Successfully.',
                'data' => $data,
            ], 200);
        }

        return response()->json([
            'ApiName' => 'Reschedule Interview',
            'status' => true,
            'message' => 'Reschedule Interview Successfully.',
            'data' => $interviewDate,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $data = Lead::find($id);
        if (! empty($data)) {
            $data->delete();

            return response()->json([
                'ApiName' => 'Delete Lead API',
                'status' => true,
                'message' => 'Lead Deleted Successfully.',
                'data' => [],
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Delete Lead API',
                'status' => false,
                'message' => 'Invaid ID',
            ], 404);
        }

    }

    public function scheduleTimeSlot()
    {
        $data = [];
        $weeks = ScheduleTimeMaster::select('id', 'day')->groupBy('day')->orderBy('id')->get();
        foreach ($weeks as $key => $value) {
            $weeks = ScheduleTimeMaster::select('time_slot')->where('day', $value->day)->orderBy('id')->get();
            // Convert day to lowercase 3-letter format to match frontend expectations
            $dayKey = strtolower(substr($value->day, 0, 3));
            $data[$dayKey] = $weeks;
        }
        // return $data;
        if (! empty($data)) {
            return response()->json([
                'ApiName' => 'Scheduletimeslot_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Scheduletimeslot_list',
                'status' => false,
                'message' => 'Data is not available.',
            ], 200);
        }
    }

    public function userScheduleTime(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $schedule = $request->schedule;
        $userSchedule = UserScheduleTime::where('user_id', $user_id)->first();
        if ($userSchedule) {
            $delete = UserScheduleTime::where('user_id', $user_id)->delete();
        }

        if (count($schedule) > 0) {
            foreach ($schedule as $key => $value) {
                $day = $value['day'];
                $times = $value['time'];
                foreach ($times as $key1 => $time) {

                    $insert = UserScheduleTime::create(
                        [
                            'user_id' => $user_id,
                            'day' => $day,
                            'time_slot' => $time['slot'],
                            'status' => 0,
                        ]
                    );

                }
            }
        }

        return response()->json([
            'ApiName' => 'user_schedule_time',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function getUserScheduleTime($id): JsonResponse
    {
        $data = [];
        $result = UserScheduleTime::where('user_id', $id)->get();
        if (count($result) > 0) {
            foreach ($result as $key => $value) {
                $day = $value['day'];
                $times = $value['time_slot'];
                $data[$day][] = $times;

            }
        }

        return response()->json([
            'ApiName' => 'get_user_schedule_time',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function scheduleInterview(Request $request): JsonResponse
    {
        $lead_id = $request->lead_id;
        $user_id = $request->user_id;
        $date = $request->date;
        $schedule = $request->schedule;
        $action_status = $request->action_status;

        $data = [
            'interview_schedule_by_id' => $user_id,
            'interview_date' => $date,
            'interview_time' => $schedule,
            'action_status' => isset($action_status) ? $action_status : 'Schedule Interview',
        ];
        $lead = Lead::find($lead_id);
        if ($lead != null) {
            $insert = Lead::where('id', $lead_id)->update($data);
            EventCalendar::where('user_id', $user_id)->delete();
            $event = EventCalendar::create(
                [
                    'event_date' => $date,
                    'event_time' => $schedule,
                    'type' => 'Interview',
                    'state_id' => $lead->state_id,
                    'user_id' => $user_id,
                    'event_name' => 'Interview Schedule',
                    'description' => 'Interview Schedule'.' '.$lead->first_name.' '.$lead->lastname,
                    'office_id' => null,
                ]
            );

            $leadComment = leadComment::create(
                [
                    'lead_id' => $lead_id,
                    'user_id' => $user_id,
                    'comments' => 'Interview Schedule'.','.$date,
                    'status' => '1',
                ]
            );

            $lead = Lead::where('id', $lead_id)->first();
            $user = [

                'user_id' => $user_id,
                'description' => 'A Interview schedule for '.$lead->first_name.' '.$lead->last_name.' Date = '.$lead->interview_date.' Time = '.$lead->interview_time,
                'type' => 'Interview schedule',
                'is_read' => 0,
            ];
            $notify = event(new UserloginNotification($user));

            $schedule_interview_id = 2;
            $updateStatusAsPerPipline = $this->updateStatusAsPerPipline($lead_id, $schedule_interview_id, $user_id);

            return response()->json([
                'ApiName' => 'schedule_interview',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'schedule_interview',
                'status' => false,
                'message' => 'Lead id is not found.',
            ], 200);
        }

    }

    // As per PipelineCOntroller=>update_lead_status
    public function updateStatusAsPerPipline($lead_id, $new_status_id, $updater_id)
    {
        try {
            $lead = Lead::where('id', $lead_id)->first();
            $old_status_id = $lead->pipeline_status_id;

            $lead->pipeline_status_id = $new_status_id;
            $lead->pipeline_status_date = date('Y-m-d');
            if ($lead->save()) {
                if ($old_status_id != $new_status_id) {
                    $lead_history = PipelineLeadStatusHistory::create([
                        'lead_id' => $lead_id,
                        'old_status_id' => $old_status_id,
                        'new_status_id' => $new_status_id,
                        'updater_id' => $updater_id,
                    ]);
                }
            }
            DB::commit();

            return [
                'ApiName' => 'update_lead_status',
                'status' => true,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'ApiName' => 'update_lead_status',
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function alertScheduleInterview($id): JsonResponse
    {
        $lead = Notification::where('is_read', 0)->where('user_id', $id)->get();

        return response()->json([
            'ApiName' => 'Alert for schedule interview Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $lead,
        ], 200);
    }

    public function alertScheduleInterviewStatusUpdate($id): JsonResponse
    {
        $lead = Notification::where('user_id', $id)->update(['is_read' => 1]);

        return response()->json([
            'ApiName' => 'Alert for schedule interview Status update Api',
            'status' => true,
            'message' => 'Successfully.',

        ], 200);
    }

    public function scheduleTime(Request $request): JsonResponse
    {
        $userId = $request->user_id;
        $date = $request->date;
        $weekday = date('D', strtotime($date));
        $timeslot = Lead::where('interview_schedule_by_id', $userId)->where('interview_date', $date)->pluck('interview_time')->toArray();
        $slots = UserScheduleTime::where('user_id', $userId)->where('day', $weekday)->orderBy('id')->get();

        if (count($slots) > 0) {
            foreach ($slots as $key => $value) {
                if (! in_array($value->time_slot, $timeslot)) {
                    $slot = $value->time_slot;
                    $data[] = $slot;
                }

            }
        }

        return response()->json([
            'ApiName' => 'schedule_time',
            'status' => true,
            'message' => 'Successfully.',
            'data' => isset($data) ? $data : null,
        ], 200);
    }

    public function assign(Request $request): JsonResponse
    {
        $user = User::where('id', $request['transfer_to_user_id'])->first();
        $auth_user_id = Auth::user()->id;
        $interviewDate = Lead::find($request->lead_id);

        if ($user->manager_id == null) {
            $recruiterId = $user->id;
        } else {
            $recruiterId = $user->manager_id;
        }

        $sourceName = isset($user->first_name) ? $user->first_name.' '.$user->last_name : null;

        // $interviewDate->assign_by_id = $request['transfer_to_user_id'];
        $interviewDate->assign_by_id = $auth_user_id;
        $interviewDate->reporting_manager_id = $recruiterId;
        $interviewDate->recruiter_id = $user->id;
        $interviewDate->source = $sourceName;
        $interviewDate->save();
        $lead = Lead::where('id', $request->lead_id)->first();
        $CrmData = Crms::where('id', 1)->where('status', 1)->first();
        if (! empty($CrmData)) {
            $data = $lead;
            $salesData = [];
            $salesData['email'] = $user->email;
            $salesData['subject'] = 'Assign Interview scheduled';
            $salesData['template'] = view('mail.assignInterview', compact('lead', 'user'));
            $this->sendEmailNotification($salesData);
        }
        $data = Notification::create([
            'user_id' => $user->id,
            'type' => 'Add Lead',
            'description' => 'Add Lead Data by'.auth()->user()->first_name,
            'is_read' => 0,
        ]);
        $notificationData = [
            'user_id' => $user->id,
            'device_token' => $user->device_token,
            'title' => 'Add Lead Data.',
            'sound' => 'sound',
            'type' => 'Add Lead',
            'body' => 'Add Lead Data by '.auth()->user()->first_name,
        ];
        $this->sendNotification($notificationData);

        return response()->json([
            'ApiName' => 'Assign Interview scheduled',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function changeStatus(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'lead_id' => 'required|integer|exists:leads,id',
                'status' => 'required|string',
            ], [
                'lead_id.required' => 'Lead ID is required.',
                'lead_id.integer' => 'Lead ID must be a valid integer.',
                'lead_id.exists' => 'The specified lead does not exist.',
                'status.required' => 'Status is required.',
                'status.string' => 'Status must be a valid string.',
            ]);

            if ($validator->fails()) {
                // Convert validation errors to a single message string
                $errorMessages = $validator->errors()->all();
                $message = implode(' ', $errorMessages);

                return response()->json([
                    'ApiName' => 'Change Status',
                    'status' => false,
                    'message' => $message,
                ], 422);
            }

            // Check if user is authenticated
            if (! auth::check()) {
                return response()->json([
                    'ApiName' => 'Change Status',
                    'status' => false,
                    'message' => 'User not authenticated.',
                ], 401);
            }

            $user_id = auth::user()->id;

            // Find the lead (keeping original variable name for consistency)
            $interviewDate = Lead::find($request->lead_id);

            // Create lead comment (keeping original logic - but with null safety)
            if ($interviewDate) {
                $data = [
                    'lead_id' => $interviewDate->id,
                    'user_id' => $user_id,
                    'comments' => $request->status,
                ];
                $insert = leadComment::create($data);

                if (! $insert) {
                    return response()->json([
                        'ApiName' => 'Change Status',
                        'status' => false,
                        'message' => 'Failed to create lead comment.',
                    ], 500);
                }
            }

            // Handle rejected status - clear interview details (FIRST rejected check - before main if)
            if ($request->status == 'Rejected') {
                $lead = Lead::where('id', $request->lead_id)->update([
                    'interview_date' => null,
                    'interview_time' => null,
                ]);
            }

            // Main logic - keeping original interviewDate condition
            if ($interviewDate) {
                $interviewDate->status = $request['status'];
                $interviewDate->save();

                // Handle rejected status - update pipeline status (SECOND rejected check - inside main if)
                if ($request['status'] == 'Rejected') {
                    $status_id = 3;
                    $updateStatusAsPerPipline = $this->updateStatusAsPerPipline($request->lead_id, $status_id, $user_id);

                    if (! $updateStatusAsPerPipline['status']) {
                        Log::warning("Failed to update pipeline status for lead ID: {$request->lead_id}", [
                            'error_message' => $updateStatusAsPerPipline['message'] ?? 'Unknown error',
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'ApiName' => 'Change Status',
                    'status' => false,
                    'message' => 'Lead not found.',
                ], 404);
            }

            Log::info('Lead status changed successfully', [
                'lead_id' => $request->lead_id,
                'new_status' => $request->status,
                'user_id' => $user_id,
            ]);

            return response()->json([
                'ApiName' => 'Change Status',
                'status' => true,
                'message' => 'Status changed successfully.',
                'data' => [
                    'lead_id' => $interviewDate->id,
                    'new_status' => $interviewDate->status,
                    'updated_at' => $interviewDate->updated_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in changeStatus method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'lead_id' => $request->lead_id ?? 'unknown',
                'status' => $request->status ?? 'unknown',
            ]);

            return response()->json([
                'ApiName' => 'Change Status',
                'status' => false,
                'message' => 'An error occurred while changing the status. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function leadsComments(Request $request): JsonResponse
    {
        $validate = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'user_id' => 'required|exists:users,id',
            'comments' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'ApiName' => 'leads_comments',
                'status' => false,
                'message' => $validate->errors()->first(),
                'data' => [],
            ], 400);
        }

        $created = leadComment::create([
            'lead_id' => $request->lead_id,
            'user_id' => $request->user_id,
            'comments' => $request->comments,
            'status' => '1',
        ]);

        return response()->json([
            'ApiName' => 'leads_comments',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $created,
        ]);
    }

    public function leadsCommentsReaply(Request $request): JsonResponse
    {

        $comment_id = $request->comment_id;
        $comment_reply = $request->comment_reply;
        $data = [
            'comment_id' => $request->comment_id,
            'comment_reply' => $request->comment_reply,

        ];
        $insert = LeadCommentReply::create($data);

        return response()->json([
            'ApiName' => 'leads_comment_reply',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function getLeadsComments($id)
    {
        $leads = LeadComment::select(
            'lead_comments.id',
            'users.first_name',
            'users.last_name',
            'users.image',
            'users.is_super_admin',
            'lead_comments.comments',
            'lead_comments.created_at',
            'user_organization_history.effective_date',
            'user_organization_history.is_manager',
            'user_organization_history.position_id',
            'user_organization_history.sub_position_id'
        )
            ->leftJoin('users', 'users.id', 'lead_comments.user_id')
            ->leftJoin('user_organization_history', function ($join) {
                $join->on('user_organization_history.user_id', '=', 'users.id')
                    ->where('user_organization_history.id', '=', DB::raw('(SELECT MAX(id) FROM user_organization_history WHERE user_organization_history.user_id = users.id and DATE(user_organization_history.effective_date) <= DATE(lead_comments.created_at))'));
            })
            ->where('lead_comments.lead_id', $id)
            ->where('lead_comments.status', '1')
            ->whereNotNull('lead_comments.comments')
            ->where('lead_comments.comments', '!=', '')
            ->groupBy('lead_comments.id')
            ->get();

        $leads->transform(function ($data) {
            $pastDate = Carbon::parse($data->created_at);
            $currentTime = Carbon::now();
            $timeDifference = $pastDate->diff($currentTime);
            $days = $timeDifference->d;
            $hours = $timeDifference->h;
            $minutes = $timeDifference->i;
            $seconds = $timeDifference->s;

            $dateTime = '';
            if ($days > 0) {
                $str = ' days';
                if ($days == 1) {
                    $str = ' day';
                }
                $dateTime = $days.$str;
            } elseif ($hours > 0) {
                $str = ' hours';
                if ($hours == 1) {
                    $str = ' hour';
                }
                $dateTime = $hours.$str;
            } elseif ($minutes > 0) {
                $str = ' minutes';
                if ($minutes == 1) {
                    $str = ' minute';
                }
                $dateTime = $minutes.$str;
            } elseif ($seconds > 0) {
                $str = ' seconds';
                if ($seconds == 1) {
                    $str = ' second';
                }
                $dateTime = $seconds.$str;
            }

            return [
                'id' => $data->id,
                'employee' => $data->first_name.' '.$data->last_name,
                'employee_image' => $data->image,
                'comments' => $data->comments,
                'created_at' => $data->created_at,
                'day_time' => $dateTime,
                'position_id' => $data->position_id,
                'sub_position_id' => $data->sub_position_id,
                'is_super_admin' => @$data->is_super_admin ? 1 : 0,
                'is_manager' => @$data->is_manager ? 1 : 0,
            ];
        });

        return response()->json([
            'ApiName' => 'get_leads_comments',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $leads,
        ]);
    }

    public function getLeadById($id): JsonResponse
    {

        $data = Lead::find($id);
        $stateName = State::where('id', $data->state_id)->pluck('name')->first();

        $data['state_name'] = $stateName;
        // dd($data);
        if (! empty($data)) {
            return response()->json([
                'ApiName' => 'GetLeadById_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'GetLeadById_list',
                'status' => false,
                'message' => 'Data is not available.',
            ], 200);
        }
    }

    public function checkStatusEmailAndMobile(Request $request): JsonResponse
    {
        $email = $request->email;
        $mobile = $request->mobile_number;
        $leadEmail = Lead::where('email', $email)->first();
        $leadMobile = Lead::Where('mobile_no', $mobile)->where('mobile_no', '!=', null)->first();
        if ($leadEmail) {
            $email_status = 1;
        } else {
            $email_status = 0;

        }
        if ($leadMobile) {
            $mobile_status = 1;
        } else {
            $mobile_status = 0;

        }
        $data = [
            'email_status' => $email_status,
            'mobile_status' => $mobile_status,
        ];
        if (! empty($data)) {
            return response()->json([
                'ApiName' => 'check Status Email And Mobile',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'GetLeadById_list',
                'status' => false,
                'message' => 'Data is not available.',
            ], 200);
        }
    }

    public function checkStatusEmailAndMobileWithoutAuth(Request $request): JsonResponse
    {
        $Validator = \Validator::make(
            $request->all(),
            [
                'email' => 'required|email|unique:leads|unique:users|unique:onboarding_employees',
                'mobile_no' => 'nullable|min:10|unique:leads,mobile_no|unique:onboarding_employees,mobile_no|unique:users,mobile_no',
            ]
        );
        if ($Validator->fails()) {
            return response()->json([
                'ApiName' => 'check Status Email And Mobile',
                'status' => true,
                'data' => [
                    'email_status' => $Validator->errors()->first('email') ? 1 : 0, // Get the first error for 'email'
                    'mobile_status' => $Validator->errors()->first('number') ? 1 : 0, // Get the first error for 'number'
                ],
                'message' => 'Successfully.',
            ], 200);
        }
        $email = $request->email;
        $mobile = $request->mobile_number;
        $leadEmail = Lead::where('email', $email)->first();
        $leadMobile = Lead::Where('mobile_no', $mobile)->first();
        if ($leadEmail) {
            $email_status = 1;
        } else {
            $email_status = 0;

        }
        if ($leadMobile) {
            $mobile_status = 1;
        } else {
            $mobile_status = 0;

        }

        $data = [
            'email_status' => $email_status,
            'mobile_status' => $mobile_status,
        ];
        if (! empty($data)) {
            return response()->json([
                'ApiName' => 'check Status Email And Mobile',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'param required',
                'status' => false,
                'message' => 'Data is not available.',
            ], 200);
        }
    }

    public function addLeadWithoutAuth(Request $request): JsonResponse
    {
        if ($request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'user_id' => 'required',

                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $user = User::where('id', $request->user_id)->first();
            if ($user->manager_id == null) {
                $recruiterId = $user->id;
            } else {
                $recruiterId = $user->manager_id;
            }

            $sourceName = isset($user->first_name) ? $user->first_name.' '.$user->last_name : null;

            $data = Lead::create(
                [
                    'first_name' => $request['first_name'],
                    'last_name' => $request['last_name'],
                    'email' => $request['email'],
                    'mobile_no' => $request['mobile_no'],
                    'state_id' => $request['state_id'],
                    'office_id' => $user->office_id,
                    'comments' => $request['comments'],
                    'status' => $request['status'],
                    'action_status' => $request['action_status'],
                    'source' => isset($sourceName) ? $sourceName : null,
                    'reporting_manager_id' => $recruiterId,
                    'recruiter_id' => $user->id,
                    'type' => 'Lead',
                    'pipeline_status_date' => date('Y-m-d'),
                    'custom_fields_detail' => json_encode($request['custom_fields_detail']),
                ]
            );

            $comment = leadComment::create(
                [
                    'user_id' => $user->id,
                    'lead_id' => $data->id,
                    'comments' => $request['comments'],
                    'status' => 1,
                ]
            );

            // $leads = Lead::where('id', $data->id)->first();
            // if($leads){
            //     $datas = $leads;
            //     $leadData = [];
            //     $leadData['email'] = $request['email'];
            //     $leadData['subject'] = 'Welcome to Sequifi ';
            //     $leadData['template'] = view('mail.leadalert', compact('datas') );
            //     $this->sendEmailNotification($leadData);
            // }

            return response()->json([
                'ApiName' => 'add-leads',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => $data,
                'comment' => $comment,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'add-leads',
                'status' => false,
                'message' => 'Invalid User Id.',
            ], 200);
        }

    }

    public function leads_import(Request $request)
    {
        try {
            // Validate if a file is uploaded
            if (! $request->hasFile('file')) {
                return response()->json([
                    'ApiName' => 'import_excel_api',
                    'status' => false,
                    'message' => 'No file uploaded',
                ], 400);
            }

            // Retrieve the uploaded file
            $file = $request->file('file');

            // Log the uploaded file's details for debugging
            \Log::info('Uploaded file details:', [
                'originalName' => $file->getClientOriginalName(),
                'mimeType' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'path' => $file->getRealPath(),
            ]);

            // Import the file directly
            \Excel::import(new LeadsImport, $file);

            // If successful, return a success response
            return response()->json([
                'ApiName' => 'import_excel_api',
                'status' => true,
                'message' => 'Upload Sheet Successfully',
            ], 200);

        } catch (\Exception $e) {
            // Log the exception for debugging
            \Log::error('Error during Excel import:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Use the exception message directly without appending additional details
            $errorMessage = $e->getMessage();

            // Return an error response with the simplified message in the 'message' field
            return response()->json([
                'ApiName' => 'import_excel_api',
                'status' => false,
                'message' => $errorMessage,
            ], 500);
        }
    }

    // This method should extract row details from the exception
    private function extractRowDetailsFromError(\Exception $e)
    {
        $rowDetails = null;

        // Check if the exception message contains structured information about the row
        // Example of expected format: "Duplicate entry detected for email: support1234@g3solar.com in row: { first_name: 'John', last_name: 'Doe' }"
        if (preg_match('/in row:\s*(\{.*?\})/', $e->getMessage(), $matches)) {
            $rowData = json_decode($matches[1], true);
            if ($rowData) {
                $rowDetails = [
                    'first_name' => $rowData['first_name'] ?? 'Unknown',
                    'last_name' => $rowData['last_name'] ?? 'Unknown',
                ];
            }
        }

        return $rowDetails;
    }

    public function download_sample()
    {

        $file_name = 'sample_report_'.date('Y_m_d_H_i_s').'.csv';

        return Excel::download(new LeadsExportSample, $file_name);

    }

    public function exportLeads(Request $request)
    {
        $file_name = 'leads_'.date('Y_m_d_H_i_s').'.xlsx';

        Excel::store(new LeadsExport($request),
            'exports/hiring/leads/'.$file_name,
            'public',
            \Maatwebsite\Excel\Excel::XLSX
        );

        $url = getStoragePath('exports/hiring/leads/'.$file_name);
        // $url = getExportBaseUrl().'storage/exports/hiring/leads/' . $file_name;
        $url = str_replace('public/public', 'public', $url);

        return response()->json(['url' => $url]);

        return Excel::download(new LeadsExport($request), $file_name);
    }

    public function reportingManager(): JsonResponse
    {
        $reportingManager = Lead::where('reporting_manager_id', '!=', null)->pluck('reporting_manager_id')->toArray();
        $leadmanager_id = array_unique($reportingManager);
        $managers = User::whereIn('id', $leadmanager_id)->select('id', 'first_name', 'last_name')->get();

        return response()->json([
            'ApiName' => 'Reporting-Manager-api',
            'status' => true,
            'message' => 'show Successfully.',
            'data' => $managers,
        ], 200);
    }

    public function deleteRejectedLeads(): JsonResponse
    {
        $rejectedLeads = Lead::where('status', 'Rejected')->get();
        if ($rejectedLeads->isNotEmpty()) {
            Lead::where('status', 'Rejected')->delete();

            return response()->json([
                'ApiName' => 'Delete All Rejected Lead API',
                'status' => true,
                'message' => 'All Rejected Leads Deleted Successfully.',
                'data' => [],
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Delete All Rejected Lead API',
                'status' => false,
                'message' => 'No rejected leads found',
            ], 404);
        }

    }

    public function getSubordinateIds($userId, &$ids = [])
    {
        // OPTIMIZATION: Use caching to avoid repeated queries
        $cacheKey = "subordinate_ids_{$userId}";

        return cache()->remember($cacheKey, 300, function () use ($userId) {
            // OPTIMIZATION: Use recursive CTE for single query solution
            try {
                $subordinateIds = DB::select('
                    WITH RECURSIVE subordinate_hierarchy AS (
                        -- Base case: direct subordinates
                        SELECT id, manager_id, 1 as level
                        FROM users 
                        WHERE manager_id = ?
                        
                        UNION ALL
                        
                        -- Recursive case: subordinates of subordinates
                        SELECT u.id, u.manager_id, sh.level + 1
                        FROM users u
                        INNER JOIN subordinate_hierarchy sh ON u.manager_id = sh.id
                        WHERE sh.level < 10  -- Prevent infinite recursion
                    )
                    SELECT DISTINCT id FROM subordinate_hierarchy
                ', [$userId]);

                return array_column($subordinateIds, 'id');
            } catch (\Exception $e) {
                // Fallback to original method if CTE fails
                \Log::warning('CTE query failed, falling back to original method: '.$e->getMessage());

                return $this->getSubordinateIdsFallback($userId);
            }
        });
    }

    /**
     * FALLBACK: Original recursive method (optimized with single query)
     */
    private function getSubordinateIdsFallback($userId)
    {
        $ids = [];
        // Single query to get all user relationships
        $allUsers = User::select('id', 'manager_id')->get()->groupBy('manager_id');
        $this->getSubordinateIdsRecursive($userId, $allUsers, $ids);

        return $ids;
    }

    /**
     * Recursive helper for fallback method
     */
    private function getSubordinateIdsRecursive($userId, $allUsers, &$ids = [])
    {
        if (isset($allUsers[$userId])) {
            foreach ($allUsers[$userId] as $subordinate) {
                $ids[] = $subordinate->id;
                $this->getSubordinateIdsRecursive($subordinate->id, $allUsers, $ids);
            }
        }

        return $ids;
    }

    public function updateLeadRating(Request $request): JsonResponse
    {
        $this->validate($request, [
            'lead_id' => [
                'required',
                'exists:leads,id',
            ],
            'lead_rating' => [
                'required',
                'numeric',
                'min:0',
                'max:5',
            ],
        ]);
        $lead = Lead::find($request->lead_id);
        // $lead->lead_rating = number_format($request->lead_rating, 2);
        $lead->lead_rating = $request->lead_rating;
        $lead->save();

        // dd(number_format($request->lead_rating, 2));
        return response()->json([
            'ApiName' => 'updateLeadRating',
            'status' => true,
            'message' => 'Reating updated.',
            'data' => Lead::find($request->lead_id),
        ], 200);
    }

    public function leadDetails($leadId)
    {

        $lead = Lead::where('id', $leadId)->with([
            'recruiter',
            'reportingManager',
            'state',
            'comment',
            'pipelineleadstatus.sub_tasks.completedByLeads' => function ($query) use ($leadId) {
                $query->where('lead_id', $leadId);
            },
            'pipelineleadstatus.pipelineComments.user',
            'pipelineleadstatus' => function ($query) {
                $query->withCount([
                    // 'completedSubTasks',
                    // 'incompleteSubTasks',
                    'sub_tasks',
                ]);
            },
            'leadDocuments',
            'newSequiDocsDocuments',
        ])
            ->withCount('comment')->first();

        if (! $lead) {
            return response()->json([
                'status' => true,
                'message' => 'Lead not found',
                'data' => [],
            ], 200);
        }

        // $lead->transform(function($data){

        // });

        $lead->custom_fields_detail = json_decode($lead->custom_fields_detail);

        if (isset($lead->recruiter->image) && $lead->recruiter->image != 'Employee_profile/default-user.png') {

            $aws_path = s3_getTempUrl(config('app.domain_name').'/'.$lead->recruiter->image);
            $lead->recruiter->image = $aws_path;

        }

        $lead->pipelineleadstatus->pipelineComments->transform(function ($comment) {
            // dump($comment->user);

            if ($comment->user->image && $comment->user->image != 'Employee_profile/default-user.png') {
                $aws_path = s3_getTempUrl(config('app.domain_name').'/'.$comment->user->image);
                $comment->user->user_image_aws_path = $aws_path;
            } else {
                $comment->user->user_image_aws_path = null;
            }

            if ($comment->path) {

                $doc_name = basename($comment->path);
                $aws_path = s3_getTempUrl(config('app.domain_name').'/'.$comment->path);

                $humanReadableSize = getRemoteFileSize($aws_path);

                $comment->aws_path = $aws_path;
                $comment->doc_name = $doc_name;
                $comment->extension = pathinfo($doc_name, PATHINFO_EXTENSION);

                if ($humanReadableSize !== false) {
                    $comment->size = formatFileSize($humanReadableSize);
                } else {
                    $comment->size = ' ';
                }

            } else {
                $comment->doc_name = null;
                $comment->extension = null;
                $comment->aws_path = null;
                $comment->size = 0;
            }

            return $comment;
        });

        $lead->leadDocuments->transform(function ($docs) {
            if ($docs->path) {

                $doc_name = basename($docs->path);
                $aws_path = s3_getTempUrl(config('app.domain_name').'/'.$docs->path);
                $size = $this->getFileSize($aws_path);
                $humanReadableSize = $this->humanReadableFileSize($size);

                $docs->aws_path = $aws_path;
                $docs->doc_name = $doc_name;
                $docs->extension = pathinfo($doc_name, PATHINFO_EXTENSION);
                $docs->size = $humanReadableSize;

            } else {
                $docs->doc_name = null;
                $docs->extension = null;
                $docs->aws_path = null;
                $docs->size = 0;
            }

            return $docs;
        });

        $pipelines = PipelineLeadStatus::where('hide_status', 0)->orWhere('status_name', 'New Lead')->with([
            'sub_tasks.completedByLeads' => function ($query) use ($leadId) {
                $query->where('lead_id', $leadId);
            },
        ])->get();

        $pipelines->transform(function ($pipeline) use ($lead) {

            $pipeline->overall_bucket_task_status = 'incomplete';
            $completed_task_count = 0;
            $incompleted_task_count = 0;
            $sub_tasks_count = 0;
            $last_task_mark_completed = null;

            $existsInHistory = PipelineLeadStatusHistory::where('lead_id', $lead->id)
                ->where(function ($query) use ($pipeline) {
                    $query->where('old_status_id', $pipeline->id)
                        ->orWhere('new_status_id', $pipeline->id);
                })
                ->exists();

            // if($pipeline->id == $lead->pipeline_status_id){
            //     $pipeline->selected = true;
            // } else {
            //     $pipeline->selected = false;
            // }

            if ($existsInHistory) {
                $pipeline->selected = true;
            } else {
                $pipeline->selected = false;
            }
            foreach ($pipeline->sub_tasks as $sub_task) {

                $sub_tasks_count++;

                $completedTask = PipelineSubTaskCompleteByLead::where([
                    'lead_id' => $lead->id,
                    // 'pipeline_lead_status_id' => $lead->pipeline_status_id,
                    'pipeline_sub_task_id' => $sub_task->id,
                    'completed' => '1',
                ])->first();

                if ($completedTask) {
                    $completed_task_count++;
                } else {
                    $incompleted_task_count++;
                }

                try {

                    if ($completedTask && $completedTask->completed_at != null) {

                        if ($pipeline->last_task_mark_completed == null) {
                            $last_task_mark_completed = '1900-00-00 00:00:00';
                        }

                        $currentDateTime = Carbon::parse($last_task_mark_completed);
                        $newDateTime = $completedTask->completed_at;
                        if ($newDateTime->gt($currentDateTime)) {
                            $last_task_mark_completed = $newDateTime;
                        }

                    } else {
                        $last_task_mark_completed = null;
                    }

                } catch (\Exception $e) {
                    // Handle invalid date format
                    // dump('Invalid date format: ' . $e->getMessage());
                    // \Log::error('Invalid date format: ' . $e->getMessage());
                }

            }

            $pipeline->last_task_mark_completed = $last_task_mark_completed;

            if (($sub_tasks_count == $completed_task_count) && $sub_tasks_count > 0) {
                $pipeline->overall_bucket_task_status = 'complete';
            } elseif ($sub_tasks_count > $completed_task_count && $completed_task_count > 0) {
                $pipeline->overall_bucket_task_status = 'not yet complete';
            }

            // completed_sub_tasks_count
            $pipeline->completed_sub_tasks_count = $completed_task_count;
            // incomplete_sub_tasks_count
            $pipeline->incomplete_sub_tasks_count = $incompleted_task_count;

            return $pipeline;
        });

        $lead->all_pipelines = $pipelines;

        $completed_sub_tasks_count = PipelineSubTaskCompleteByLead::where([
            'lead_id' => $lead->id,
            'pipeline_lead_status_id' => $lead->pipeline_status_id,
            'completed' => '1', // should string
        ])->count();

        $alltasksCount = PipelineSubTask::where([
            'pipeline_lead_status_id' => $lead->pipeline_status_id,
        ])->count();

        // dd($alltasksCount);

        if ($lead->pipelineleadstatus) {
            $lead->completed_sub_tasks_count = $completed_sub_tasks_count;
            $lead->incomplete_sub_tasks_count = $alltasksCount - $completed_sub_tasks_count ?? 0;
        }

        return response()->json([
            'status' => true,
            'data' => $lead,
        ], 200);

    }

    public function saveLeadDocument(Request $request): JsonResponse
    {

        $this->validate($request, [
            'lead_id' => 'required|integer|exists:leads,id',
            'attachments' => 'required|array',
            'attachments.*' => 'file',
        ]);

        $user_id = $request->user_id ?? 0;
        $lead_id = $request->lead_id;
        $attachments = $request->file('attachments');
        try {

            foreach ($attachments as $attachment) {
                $file = $attachment;
                $img_path = time().$file->getClientOriginalName();

                $img_path = str_replace(' ', '_', $img_path);
                $ds_path = 'leaddocuments/'.$img_path;
                $awsPath = config('app.domain_name').'/'.$ds_path;
                // echo $awsPath;die();
                s3_upload($awsPath, file_get_contents($file), false);
                $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.$ds_path);

                LeadDocument::create([
                    'user_id' => $user_id,
                    'lead_id' => $lead_id,
                    'path' => $ds_path,
                    'status' => 1,
                ]);

            }

            return response()->json([
                'ApiName' => 'saveLeadDocument',
                'status' => true,
                'message' => 'documents added successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'adddocuments',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getFileSize($preSignedUrl)
    {
        try {
            $response = Http::head($preSignedUrl);
            if ($response->successful()) {
                $fileSize = $response->header('Content-Length');
                if ($fileSize !== null) {
                    return (int) $fileSize;
                } else {
                    return 0;
                }
            } else {
                return 0;
            }
        } catch (\Throwable $th) {
            // dd($th);
            // return 0;
        }

    }

    private function humanReadableFileSize($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $base = log($size, 1024);
        $suffix = $units[floor($base)];
        $formattedSize = round(pow(1024, $base - floor($base)), $precision);

        return $formattedSize.' '.$suffix;
    }

    public function updateTaskStatus(Request $request)
    {

        $validated = $request->validate([
            'pipeline_lead_status_id' => 'required|integer|exists:pipeline_lead_status,id',
            'lead_id' => 'required|integer|exists:leads,id',
            'sub_task_status' => 'array',
            // 'sub_task_id' => 'required|integer|exists:pipeline_sub_tasks,id',
            // 'completed' => 'required|boolean', // Ensures completed is either 0 or 1
        ]);

        $sub_task_status = $request->sub_task_status;
        foreach ($sub_task_status as $status) {

            if (isset($status['sub_task_id']) && isset($status['completed'])) {

                PipelineSubTaskCompleteByLead::updateOrCreate(
                    [
                        'lead_id' => $request->lead_id,
                        'pipeline_sub_task_id' => $status['sub_task_id'],
                        'pipeline_lead_status_id' => $request->pipeline_lead_status_id,
                    ],
                    [
                        'completed' => (string) $status['completed'],
                        'completed_at' => $status['completed'] == 1 ? now() : null,
                    ]
                );

            }

        }

        // fetch completed tasks list associated with this lead
        // $tasks = PipelineLeadStatus::where('id', $request->sub_task_id)->with([
        //     'sub_tasks'
        // ])->withCount('sub_tasks')->get();

        // $completed_task_count = PipelineSubTaskCompleteByLead::where([
        //     'lead_id' => $request->lead_id,
        //     'pipeline_sub_task_id' => $request->sub_task_id,
        //     'completed' => '1' //should string
        // ])->count();

        // $total_task_count = PipelineSubTask::where([
        //     'pipeline_lead_status_id' => $request->sub_task_id,
        // ])->count();

        return $this->subTaskStatus($request);

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully',
            'data' => [

                // 'completed_task_count' => $completed_task_count,
                // 'total_task_count' => $total_task_count,
                // 'tasks' => $tasks,
            ],
        ], 200);

    }

    public function subTaskStatus(Request $request)
    {

        $validated = $request->validate([
            // 'pipeline_lead_status_id' => 'required|integer|exists:pipeline_sub_task_complete_by_leads,pipeline_lead_status_id',
            'pipeline_lead_status_id' => 'required|integer',
            'lead_id' => 'required|integer',
        ]);

        $completedTaskIds = PipelineSubTaskCompleteByLead::where([
            'lead_id' => $request->lead_id,
            'pipeline_lead_status_id' => $request->pipeline_lead_status_id,
            'completed' => '1',
        ])->pluck('pipeline_sub_task_id')->toArray();

        $tasks = PipelineSubTask::where([
            'pipeline_lead_status_id' => $request->pipeline_lead_status_id,
        ])->get();

        $tasksWithCompletionStatus = $tasks->map(function ($task) use ($completedTaskIds) {
            $task->completed = in_array($task->id, $completedTaskIds); // Check if the task is completed

            return $task;
        });

        return response()->json([
            'status' => true,
            'message' => '',
            'data' => [
                'tasksWithCompletionStatus' => $tasksWithCompletionStatus,
            ],
        ], 200);

    }

    public function user_preference_update(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'status' => 'required',
                    'remember' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $this->setbucketpreference($request);
            $msg = 'successfully Updated';

            return response()->json([
                'ApiName' => 'user_preference_update',
                'msg' => $msg,
                'status' => true,
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'user_preference_update',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getuserpreference(Request $request): JsonResponse
    {
        try {

            $updater_id = Auth::user()->id;
            $data = DB::table('users_preference')->where('user_id', $updater_id)->get();
            $this->setbucketpreference($request);

            return response()->json([
                'ApiName' => 'getuserpreference',
                'data' => $data,
                'status' => true,
            ], 200);

        } catch (Exception $e) {

            // DB::rollBack();
            return response()->json([
                'ApiName' => 'getuserpreference',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);

        }
    }

    protected function setbucketpreference($request)
    {

        $status_update = $request->status ?? 0;
        $subtaskpreference = $request->subtaskpreference ?? '';

        if ($subtaskpreference != '') {
            $status_update = $subtaskpreference;
        }

        $remember = $request->remember ?? 0;
        $updater_id = Auth::user()->id;
        $count = DB::table('users_preference')->where('user_id', $updater_id)->count();

        if ($count > 0) {

            DB::table('users_preference')->where('user_id', $updater_id)
                ->update(['move_lead' => $status_update, 'remember' => $remember]);

        } else {

            DB::table('users_preference')->insert([
                'user_id' => $updater_id,
                'move_lead' => $status_update,
                'remember' => $remember,
            ]);

        }
    }
}
