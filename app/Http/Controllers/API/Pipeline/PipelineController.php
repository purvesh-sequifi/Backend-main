<?php

namespace App\Http\Controllers\API\Pipeline;

use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\EmployeeIdSetting;
use App\Models\GroupPermissions;
use App\Models\HiringStatus;
use App\Models\Lead;
use App\Models\leadComment;
use App\Models\NewSequiDocsDocumentComment;
use App\Models\OnboardingEmployees;
use App\Models\Permissions;
use App\Models\PipelineLeadStatus;
use App\Models\PipelineLeadStatusHistory;
use App\Models\PipelineSubTask;
use App\Models\PipelineSubTaskCompleteByLead;
use App\Models\SClearanceConfiguration;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\State;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PipelineController extends Controller
{
    // ALTER TABLE `leads` ADD `pipeline_status_id` int NULL AFTER `type`;
    // ALTER TABLE `leads` ADD `pipeline_status_date` date NULL AFTER `pipeline_status_id`;
    // ALTER TABLE `hiring_status`
    // ADD `display_order` int(11) NULL AFTER `status`,
    // ADD `hide_status` tinyint(4) NULL DEFAULT '0' AFTER `display_order`,
    // ADD `colour_code` varchar(20) COLLATE 'utf8mb4_unicode_ci' NULL DEFAULT '#E4E9FF' AFTER `hide_status`;
    // table migration created pipeline_leads_status_history , pipline_lead_status
    // ALTER TABLE `onboarding_employees` ADD `status_date` date NULL COMMENT 'date when this status is set' AFTER `status_id`;

    public function update_lead_card(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $lead_status_id = $request->lead_status_id;
            $type = $request->type; // hide_show , title , display_order
            $updater_id = Auth::user()->id;
            $status = true;
            $status_code = 200;

            switch ($type) {
                case 'hide_show':
                    $hide_status = $request->hide_status;
                    $new_status = PipelineLeadStatus::where('id', $lead_status_id)->update(['hide_status' => $hide_status]);
                    $msg = 'Status Show/Hide  updated successfully';
                    break;
                    // case 'title':
                    //     $status_name = $request->status_name;
                    //     $new_status = PipelineLeadStatus::where('id',$lead_status_id)->update(['status_name' => $status_name]);
                    //     $msg = "Status name updated successfully";
                    //     break;
                case 'delete':
                    $count = Lead::where('pipeline_status_id', $lead_status_id)->count();
                    if ($count > 0) {
                        $msg = 'Status not empty, first remove all leads from this status.';
                        $status = false;
                        $status_code = 400;
                    } else {
                        $new_status = PipelineLeadStatus::where('id', $lead_status_id)->delete();
                        $msg = 'Status deleted successfully';
                    }
                    break;
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'update_lead_card',
                'msg' => $msg,
                'status' => $status,
            ], $status_code);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'update_lead_card',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function history_lead_status(Request $request)
    {
        $lead_id = $request->lead_id;
        $list = PipelineLeadStatusHistory::with('old_status', 'new_status', 'updater')->where('lead_id', $lead_id)->orderBy('id', 'desc')->get();
        $list->transform(function ($list) {
            return [
                'id' => $list->id,
                'lead_id' => $list->lead_id,
                'updater_id' => isset($list->updater->first_name) ? $list->updater->first_name.' '.$list->updater->last_name : '',
                'old_status_name' => $list->old_status->status_name ?? '',
                'new_status' => $list->new_status->status_name ?? '',
                'created_at' => $list->created_at,
            ];
        });

        return response()->json([
            'ApiName' => 'history_lead_status',
            'status' => true,
            'data' => $list,
        ], 200);
    }

    public function update_lead_status(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $lead_id = $request->lead_id;
            $new_status_id = $request->new_status_id;
            $updater_id = Auth::user()->id;
            $lead = Lead::where('id', $lead_id)->first();
            if ($request->filled('move_option')) {
                $move_option = $request->move_option;
                if ($move_option == 'complete') {

                    $subtasks = PipelineSubTask::where('pipeline_lead_status_id', $lead->pipeline_status_id)->get();

                    foreach ($subtasks as $subtask) {

                        PipelineSubTaskCompleteByLead::updateOrCreate([
                            'lead_id' => $lead_id,
                            'pipeline_sub_task_id' => $subtask->id,
                            'pipeline_lead_status_id' => $lead->pipeline_status_id,
                        ], [
                            'completed' => '1',
                            'completed_at' => now(),
                        ]);

                    }

                }
            }

            // $new_status = leadStatus::where('id',$new_status_id)->first();
            $update = Lead::where('id', $lead_id)->update(['pipeline_status_id' => $new_status_id, 'pipeline_status_date' => date('Y-m-d'), 'background_color' => '#FFFFFF']);
            if ($update) {
                $old_status_id = $lead->pipeline_status_id; // old status id
                if ($old_status_id != $new_status_id) {
                    $lead_history = PipelineLeadStatusHistory::create([
                        'lead_id' => $lead_id,
                        'old_status_id' => $old_status_id,
                        'new_status_id' => $new_status_id,
                        'updater_id' => $updater_id,
                    ]);
                }

                // Update Lead list from card
                $newLeadBucket = PipelineLeadStatus::where('id', $new_status_id)->first();
                if ($newLeadBucket) {
                    $lead->status = $newLeadBucket->status_name;
                    $lead->update();
                }
                // $statusToUpdates = [1, 2, 3];
                // if(in_array($new_status_id, $statusToUpdates)){
                //     if($new_status_id==1){
                //         $lead->status = "Follow Up";
                //     }
                //     else if($new_status_id==2){
                //         $lead->status = "Interview Scheduled";
                //     }
                //     else if($new_status_id==3){
                //         $lead->status = "Rejected";
                //     }
                //     $lead->update();
                // }
            }
            DB::commit();

            return response()->json([
                'ApiName' => 'update_lead_status',
                'status' => true,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'update_lead_status',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function delete_lead_comment(Request $request): JsonResponse
    {
        $comment_id = $request->comment_id;
        leadComment::where('id', $comment_id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted successfully',
        ], 200);
    }

    public function add_lead_comment(Request $request): JsonResponse
    {

        $comment = $request->comments;
        $lead_id = $request->lead_id;
        // $user_id = Auth::user()->id;
        $user_id = ! empty($request->input('user_id')) ? $request->input('user_id') : Auth::user()->id;
        $attachments = $request->file('attachments');

        try {

            if (! empty($attachments)) {

                foreach ($attachments as $attachment) {

                    $file = $attachment;
                    $img_path = time().$file->getClientOriginalName();

                    $img_path = str_replace(' ', '_', $img_path);
                    $ds_path = 'lead_comment_documents/'.$img_path;
                    $awsPath = config('app.domain_name').'/'.$ds_path;
                    // echo $awsPath;die();
                    s3_upload($awsPath, file_get_contents($file), false);
                    // $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.$ds_path);

                    $commentData = [
                        'path' => $ds_path,
                        'user_id' => $user_id,
                        'lead_id' => $lead_id,
                        'comments' => $comment,
                        'status' => 1,
                        'pipeline_lead_status_id' => $request->pipeline_lead_status_id ?? null,
                    ];

                    $lead_comment = leadComment::create($commentData);

                }

            } else {

                $lead_comment = leadComment::create([
                    'user_id' => $user_id,
                    'lead_id' => $lead_id,
                    'comments' => $comment,
                    'status' => 1,
                    'pipeline_lead_status_id' => $request->pipeline_lead_status_id ?? null,
                ]);

            }

            return response()->json([
                'ApiName' => 'add_lead_comment',
                'status' => true,
                'data' => $lead_comment,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add_lead_comment',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

    }

    public function list_lead_comment(Request $request)
    {
        $lead_id = $request->lead_id;
        $user_id = Auth::user()->id;

        try {
            // leadComment::where(function ($query) {
            //     $query->whereNull('comments')
            //         ->orWhere('comments', '');
            // })
            // ->delete();
            $comments = leadComment::with('usersdata')
                ->where('lead_id', $lead_id)
                // ->whereNotNull('comments')
                // ->where('comments', '!=', '')
                ->get();

            $comments->transform(function ($comments) {
                if (isset($comments->usersdata->image) && $comments->usersdata->image != null) {
                    $s3_image = s3_getTempUrl(config('app.domain_name').'/'.$comments->usersdata->image);
                } else {
                    $s3_image = null;
                }

                if (isset($comments->path) && $comments->path != null) {

                    $attachment = s3_getTempUrl(config('app.domain_name').'/'.$comments->path);
                    $doc_name = basename($comments->path);
                    $extension = pathinfo($doc_name, PATHINFO_EXTENSION);
                    $humanReadableSize = getRemoteFileSize($attachment);
                    if ($humanReadableSize !== false) {
                        $size = formatFileSize($humanReadableSize);
                    } else {
                        $size = ' ';
                    }

                } else {
                    $attachment = null;
                    $extension = null;
                    $size = null;
                    $doc_name = null;
                }

                return [
                    'id' => $comments->id,
                    'comments' => $comments->comments,
                    'comment_by' => (! empty($comments->usersdata->first_name)) ? $comments->usersdata->first_name.' '.$comments->usersdata->last_name : '',
                    'comment_by_image' => $s3_image,
                    'comment_date' => $comments->created_at,
                    'attachment' => $doc_name,
                    'attachment_url' => $attachment,
                    'extension' => $extension,
                    'size' => $size,
                    'user' => $comments->usersdata,
                ];
            });

            return response()->json([
                'ApiName' => 'list_lead_comment',
                'status' => true,
                'data' => $comments,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_lead_comment',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function addUpdateLeadStatus(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'status_name' => 'required',
                    'colour_code' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = ! empty($request->id) ? $request->id : 0;
            $status_name = ! empty($request->status_name) ? $request->status_name : '';
            $colour_code = ! empty($request->colour_code) ? $request->colour_code : '';
            $display_order = ! empty($request->display_order) ? $request->display_order : '';

            if ($id > 0 && $status_name != '') {
                $newStatus = PipelineLeadStatus::where('id', $id)->update([
                    'status_name' => $status_name,
                    'colour_code' => $colour_code,
                    'hide_status' => 0,
                ]);
                if ($display_order != '') {
                    PipelineLeadStatus::where('id', $id)->update(['display_order' => $display_order]);
                }

                return response()->json([
                    'ApiName' => 'addUpdateLeadStatus',
                    'status' => true,
                    'message' => 'Lead status updated successfully',
                ], 200);
            }
            $max_display_order = PipelineLeadStatus::orderBy('display_order', 'desc')->value('display_order');
            $statusData = PipelineLeadStatus::select('id')->where(['status_name' => $status_name])->get()->toArray();
            if (empty($statusData)) {
                $newStatus = PipelineLeadStatus::create([
                    'status_name' => $status_name,
                    'colour_code' => $colour_code,
                    'display_order' => $max_display_order + 1,
                    'hide_status' => 0,
                ]);

                return response()->json([
                    'ApiName' => 'addNewLeadStatus',
                    'status' => true,
                    'message' => 'Lead status added successfully',
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'addNewLeadStatus',
                    'status' => false,
                    'message' => 'Lead status already exist',
                    'data' => $statusData,
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'addNewLeadStatus ',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function list_lead_status(Request $request)
    {
        $search = $request->search ?? '';
        $officeIds = $request->filter['office'] ?? []; // Get office array from filter
        try {
            Lead::whereNull('pipeline_status_id')->update(['pipeline_status_id' => 1]);

            $bucket_list = PipelineLeadStatus::orderBy('display_order', 'ASC')->get();

            // Don't show Delect option for Leads, Schedule Interview, Reject.
            $notToShowDeleteOptionIds = [1, 2, 3];
            foreach ($bucket_list as $key => $bucket) {
                $bucket->show_delete_option = in_array($bucket->id, $notToShowDeleteOptionIds) ? 0 : 1;
            }

            $recruiterIds = [];
            if (! empty($officeIds)) {
                $recruiterIds = User::whereIn('office_id', $officeIds)->pluck('id')->toArray();
            }

            $leads = PipelineLeadStatus::withCount('leads');
            $leads->withWhereHas('leads', function ($qry) use ($search, $recruiterIds) {
                $superAdmin = Auth::user()->is_super_admin;
                $user_id = Auth::user()->id;
                $positionId = Auth::user()->position_id;
                $recruiterId = Auth::user()->recruiter_id;
                $is_manager = Auth::user()->is_manager;

                if (! $superAdmin) {

                    $qry->where([
                        'leads.office_id' => auth()->user()->office_id,
                    ]);

                }

                if (! empty($search)) {
                    $qry->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', '%'.trim($search).'%')
                            ->orWhere('last_name', 'like', '%'.trim($search).'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.trim($search).'%']);
                    });
                }

                if (! empty($recruiterIds)) {
                    $qry->whereIn('recruiter_id', $recruiterIds);
                }

                if ($superAdmin == 1) {
                } elseif ($positionId != 1) {
                    if ($is_manager != 1) {
                        $qry->where('recruiter_id', auth()->user()->id);
                    } else {
                        $qry->orWhere('reporting_manager_id', $user_id);
                    }
                } else {
                    $qry->where('manager_id', $user_id);
                }
            });

            $leads = $leads->orderBy('display_order', 'ASC')->get();

            $leads->transform(function ($leads_data) {
                $leads_data->leads->transform(function ($lead) {
                    $leadComment = leadComment::where('lead_id', $lead->id)
                        ->whereNotNull('comments')
                        ->where('comments', '!=', '')
                        ->count();

                    $lead->totalcomment = $leadComment;

                    $pipleLinSubTaskIds = PipelineSubTask::where('pipeline_lead_status_id', $lead->pipeline_status_id)->pluck('id');

                    $completed_sub_tasks_count = PipelineSubTaskCompleteByLead::where([
                        'lead_id' => $lead->id,
                        'pipeline_lead_status_id' => $lead->pipeline_status_id,
                        'completed' => '1', // should string
                    ])->whereIn('pipeline_sub_task_id', $pipleLinSubTaskIds)->count();

                    $alltasksCount = PipelineSubTask::where([
                        'pipeline_lead_status_id' => $lead->pipeline_status_id,
                    ])->count();

                    // dd($alltasksCount);

                    if ($lead->pipelineleadstatus) {
                        $lead->alltasksCount = $alltasksCount;
                        $lead->completed_sub_tasks_count = $completed_sub_tasks_count;
                        $lead->incomplete_sub_tasks_count = $alltasksCount - $completed_sub_tasks_count ?? 0;
                    }

                    $lead->documentCount = count($lead->leadDocuments) + count($lead->newSequiDocsDocuments);

                    return $lead;
                });

                return [
                    'id' => $leads_data->id,
                    'status_name' => $leads_data->status_name,
                    'display_order' => $leads_data->display_order,
                    'hide_status' => $leads_data->hide_status,
                    'colour_code' => $leads_data->colour_code,
                    'leads_count' => $leads_data->leads_count,
                    'leads' => $leads_data->leads,
                ];
            });

            return response()->json([
                'ApiName' => 'list_lead_status',
                'status' => true,
                'data' => $leads,
                'bucket_list' => $bucket_list,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_lead_status',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function list_onboarding_status(Request $request)
    {
        $search = $request->search ?? '';
        $filter = $request->filter ?? '';
        try {
            $days_in_status = null;
            $checkDayStatus = false;
            if (count($filter) > 0) {
                $checkDayStatus = isset($filter['days_in_status']) ?? null;
                if ($checkDayStatus == 1) {
                    $days_in_status = (int) $filter['days_in_status'];
                }
            }

            $superAdmin = Auth::user()->is_super_admin;
            $user_id = Auth::user()->id;
            $positionId = Auth::user()->position_id;
            $recruiterId = Auth::user()->recruiter_id;
            $officeIds = [];
            if ($superAdmin == 1) {
            } elseif ($positionId != 1) {

                $additional_location = AdditionalLocations::with('state', 'city')->where('user_id', $user_id)->get();
                foreach ($additional_location as $key => $value) {
                    if (isset($value->office->id)) {
                        $officeIds[] = $value->office->id;
                    }
                }
                $officeIds[] = Auth::user()->office_id;

                // $officeStates = State::with('office')->whereHas('office',
                // function ($user_qry) {
                //     $user_qry->whereNull('archived_at');
                // })->get();
                // foreach ($officeStates as $key => $state) {
                //     if(isset($state->office)){
                //         foreach ($state->office as $key => $office) {
                //             if(isset($office->id)){
                //                 $officeIds[] = $office->id;
                //             }
                //         }
                //     }
                // }
            }

            $bucket_list = HiringStatus::where('show_on_card', 1)->orderBy('display_order', 'ASC')->get();
            $bucket_list->transform(function ($bucket_list) {
                $EmployeeIdSetting = EmployeeIdSetting::select('require_approval_status', 'special_approval_status')->first();

                $status = $bucket_list->status;
                $show_on_card = $bucket_list->show_on_card;
                $hide_status = $bucket_list->hide_status;
                if ($status == 'Requested Change') {
                    $status = 'Request Change';
                } elseif ($status == 'Offer Letter Accepted') {
                    $status = 'Offer Accepted';
                }

                if ($status == 'Offer Review') {
                    if ($EmployeeIdSetting->require_approval_status == 0) {
                        $show_on_card = 0;
                        $hide_status = 1;
                    }
                }
                if ($status == 'Special Review') {
                    if ($EmployeeIdSetting->special_approval_status == 0) {
                        $show_on_card = 0;
                        $hide_status = 1;
                    }
                }

                return [
                    'id' => $bucket_list->id,
                    'status' => $status,
                    'show_on_card' => $show_on_card,
                    'hide_status' => $hide_status,
                    'display_order' => $bucket_list->display_order,
                    'colour_code' => $bucket_list->colour_code,
                ];
            });
            // dd($bucket_list);

            $condOnboardingEmployees = OnboardingEmployees::with('recruiter', 'positionDetail:id,position_name', 'onboarding_user_resend_offer_status', 'office:id,office_name,state_id', 'OnboardingEmployeesDocuments')
                ->select('id', 'user_id', 'recruiter_id', 'first_name', 'last_name', 'status_id', 'sub_position_id', 'office_id', 'is_background_verificaton', 'position_id', 'status_date', DB::raw('DATEDIFF(now(),`updated_at`) as days_in_status'));

            $condOnboardingEmployees = $condOnboardingEmployees->where(function ($qry) use ($search, $filter, $days_in_status, $checkDayStatus) {
                // FILTERS
                if (! empty($filter['office'])) {
                    $qry->whereIn('office_id', $filter['office']);
                }
                if (! empty($filter['position'])) {
                    $qry->whereIn('sub_position_id', $filter['position']);
                }
                if ($checkDayStatus) {
                    // $days_in_status = $filter['days_in_status'];
                    $filter_date = date('Y-m-d', strtotime("-$days_in_status day"));
                    $qry->whereDate('updated_at', $filter_date);
                }
                // SEARCH
                if (! empty($search)) {
                    $qry->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', '%'.trim($search).'%')
                            ->orWhere('last_name', 'like', '%'.trim($search).'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.trim($search).'%']);
                    });
                }
            });

            if (count($officeIds) > 0) {
                $condOnboardingEmployees = $condOnboardingEmployees->whereIn('office_id', $officeIds);
            }

            // Offer Letter Accepted
            // Check Status ID 1 for Offer Letter Accept
            $offerLetterAcceptList = $condOnboardingEmployees->with('newSequiDocsOfferAccept')->where('status_id', 1)->get();

            $offerAcceptCardList = [];
            $offerAcceptCardListIds = [];
            foreach ($offerLetterAcceptList as $key => $onboardingEmployees) {
                $isHireNow = false;
                $onboardingEmployees->background_verification_status = '';
                $onboardingEmployees->background_verification_approval_required = 0;
                $is_all_doc_sign = $is_all_new_doc_sign = false;
                $other_doc_status = [];

                $totalcomment = NewSequiDocsDocumentComment::where('user_id_from', 'onboarding_employees')->where('document_send_to_user_id', $onboardingEmployees->user_id)->count();
                $onboardingEmployees->totalcomment = $totalcomment;
                // Logic for all docs sign or not
                $onboarding_employees_documents = $onboardingEmployees->OnboardingEmployeesDocuments ?? null;
                $is_offer_accept = $onboardingEmployees?->newSequiDocsOfferAccept?->is_offer_accept ?? null;

                if ($onboarding_employees_documents != null) {
                    $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
                    $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];
                    $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
                }

                if ($onboarding_employees_documents != null) {
                    $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
                    $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];
                    $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
                }

                // Hire button show hide as per new tables of sequidoc
                $onboarding_employees_new_documents = $onboardingEmployees->newOnboardingEmployeesDocuments ?? null;
                if ($onboarding_employees_new_documents != null) {
                    $onboarding_employees_new_document_status = OnboardingEmployees::onboarding_employees_new_document_status($onboarding_employees_new_documents);
                    $is_all_new_doc_sign = $onboarding_employees_new_document_status['is_all_new_doc_sign'];
                }

                if ($is_all_doc_sign == false && $is_all_new_doc_sign == true) {
                    $isHireNow = true;
                }

                if ($onboardingEmployees->is_background_verificaton == 1) {
                    $position_id = $onboardingEmployees->position_id;
                    $user_id = $onboardingEmployees->id;
                    $user_type = 'Onboarding';
                    $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id, 'hiring_status' => 1])->first();
                    if (empty($configurationDetails)) {// get default
                        $configurationDetails = SClearanceConfiguration::where(['id' => 1])->first();
                    }

                    // if(!empty($configurationDetails)){
                    $reportData = SClearanceTurnScreeningRequestList::where(['user_type_id' => $user_id, 'user_type' => $user_type])->first();

                    $background_verification_status = $reportData->status ?? null;
                    $approved_declined_by = $reportData->approved_declined_by ?? null;
                    if (! empty($reportData)) {
                        $background_verification_approval_required = $configurationDetails->is_approval_required ?? 0;

                        $onboardingEmployees->screening_request_applicant_id = $reportData->screening_request_applicant_id ?? null;
                        $onboardingEmployees->screening_request_id = $reportData->screening_request_id ?? null;
                        $onboardingEmployees->background_verification_status = $background_verification_status;
                        $onboardingEmployees->background_verification_approval_required = $background_verification_approval_required;

                        if ($background_verification_status == 'approved' || $background_verification_status == 'pending') {
                            if ($background_verification_approval_required == 0) {
                                $isHireNow = true;
                            } elseif ($background_verification_approval_required == 1 && $approved_declined_by != null) {
                                $isHireNow = true;
                            }
                        }
                    }
                    // }
                }

                $onboardingEmployees->is_all_doc_sign = $is_all_doc_sign;
                $onboardingEmployees->is_all_new_doc_sign = $is_all_new_doc_sign;
                $onboardingEmployees->other_doc_status = $other_doc_status;

                $push_data = false;
                if (! $is_all_new_doc_sign) {
                    $push_data = true;
                }

                if ($is_all_new_doc_sign) {
                    if (($other_doc_status['backgroundVerification'] == 1 || $other_doc_status['backgroundVerification'] == 2) && ($other_doc_status['w9'] == 1 || $other_doc_status['w9'] == 2)) {
                        $push_data = false;
                    } elseif (($other_doc_status['backgroundVerification'] == 0 || $other_doc_status['backgroundVerification'] == 2) || ($other_doc_status['w9'] == 0 || $other_doc_status['w9'] == 2)) {
                        $push_data = true;
                    }
                }

                if ($onboardingEmployees->hire_now_eligible != 1) {
                    $push_data = true;
                }

                /* If user have no offer accept then it will not listed */
                if ($is_offer_accept != 1) {
                    $push_data = false;
                }

                if ($push_data) {
                    $offerAcceptCardList[] = $onboardingEmployees;
                    $offerAcceptCardListIds[] = $onboardingEmployees->id;
                }
            }
            // Hiring Now
            $hiringList = $condOnboardingEmployees->with('newSequiDocsDocRview')->where('status_id', 1)
            // ->where('hire_now_eligible', 1)
                ->get();

            $hiringCardList = [];
            $hiringCardListIds = [];
            foreach ($hiringList as $key => $hiringOnboardingEmployees) {
                // foreach ($hiring->hiringOnboardingEmployees as $hiringOnboardingEmployees) {
                $isHireNow = false;
                $hiringOnboardingEmployees->background_verification_status = '';
                $hiringOnboardingEmployees->background_verification_approval_required = 0;
                $is_all_doc_sign = $is_all_new_doc_sign = false;
                $other_doc_status = [];

                $onboarding_employees_documents = [];

                // Logic for all docs sign or not
                $onboarding_employees_documents = $hiringOnboardingEmployees->OnboardingEmployeesDocuments ?? null;
                $is_doc_review = $hiringOnboardingEmployees?->newSequiDocsDocRview?->is_doc_review ?? 0;

                if ($onboarding_employees_documents != null) {
                    $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
                    $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];
                    $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
                }

                // Hire button show hide as per new tables of sequidoc
                $onboarding_employees_new_documents = $hiringOnboardingEmployees->newOnboardingEmployeesDocuments ?? null;
                if ($onboarding_employees_new_documents != null) {
                    $onboarding_employees_new_document_status = OnboardingEmployees::onboarding_employees_new_document_status($onboarding_employees_new_documents);
                    $is_all_new_doc_sign = $onboarding_employees_new_document_status['is_all_new_doc_sign'];
                }

                // if($is_all_doc_sign==false && $is_all_new_doc_sign==true){
                //     $isHireNow = true;
                // }

                if ($hiringOnboardingEmployees->is_background_verificaton == 1) {
                    $position_id = $hiringOnboardingEmployees->position_id;
                    $user_id = $hiringOnboardingEmployees->id;
                    $user_type = 'Onboarding';
                    $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id, 'hiring_status' => 1])->first();
                    if (empty($configurationDetails)) {// get default
                        $configurationDetails = SClearanceConfiguration::where(['id' => 1])->first();
                    }

                    // if(!empty($configurationDetails)){
                    $reportData = SClearanceTurnScreeningRequestList::where(['user_type_id' => $user_id, 'user_type' => $user_type])->first();

                    $background_verification_status = $reportData->status ?? null;
                    $approved_declined_by = $reportData->approved_declined_by ?? null;
                    if (! empty($reportData)) {
                        $background_verification_approval_required = $configurationDetails->is_approval_required ?? 0;

                        $hiringOnboardingEmployees->turn_id = $reportData->turn_id ?? null;
                        $hiringOnboardingEmployees->worker_id = $reportData->worker_id ?? null;
                        $hiringOnboardingEmployees->background_verification_status = $background_verification_status;
                        $hiringOnboardingEmployees->background_verification_approval_required = $background_verification_approval_required;
                    }

                    if ($is_all_doc_sign == false && $is_all_new_doc_sign == true) {
                        $isHireNow = true;
                    }

                    if ($background_verification_status == 'approved' || $background_verification_status == 'pending') {
                        if ($background_verification_approval_required == 0) {
                            $isHireNow = true;
                        } elseif ($background_verification_approval_required == 1 && $approved_declined_by != null) {
                            $isHireNow = true;
                        }
                    }

                    // if(!$is_all_new_doc_sign){
                    //     $isHireNow = false;
                    // }else{
                    //     $isHireNow = true;
                    // }

                    if ($is_all_new_doc_sign && ($other_doc_status['backgroundVerification'] == '0' || $other_doc_status['w9'] == '0')) {
                        $isHireNow = false;
                    }
                    // }
                } else {
                    if ($is_all_new_doc_sign) {
                        $isHireNow = true;
                    }

                    if ($is_all_new_doc_sign && ($other_doc_status['backgroundVerification'] == '0' || $other_doc_status['w9'] == '0')) {
                        $isHireNow = false;
                    }
                }

                /* 1 is behalf of not signed. if not singed than user will not listed in doc review. */
                if ($is_doc_review != 0) {
                    $isHireNow = false;
                }

                if ($isHireNow == true) {
                    $hiringOnboardingEmployees->is_all_doc_sign = $is_all_doc_sign;
                    $hiringOnboardingEmployees->is_all_new_doc_sign = $is_all_new_doc_sign;
                    $hiringOnboardingEmployees->other_doc_status = $other_doc_status;

                    $hiringCardList[] = $hiringOnboardingEmployees;
                    $hiringCardListIds[] = $hiringOnboardingEmployees->id;
                }
                // }
            }

            // Offer Letter Resent
            $offerLetterResendList = $condOnboardingEmployees->where('status_id', 12)->get();

            $list = HiringStatus::withCount('OnboardingEmployees');

            $list = $list->withWhereHas('OnboardingEmployees', function ($qry) use ($search, $filter, $hiringCardListIds, $days_in_status, $checkDayStatus, $officeIds) {
                // FILTERS
                if (! empty($filter['office'])) {
                    $qry->whereIn('office_id', $filter['office']);
                }
                if (! empty($filter['position'])) {
                    $qry->whereIn('sub_position_id', $filter['position']);
                }
                if ($checkDayStatus) {
                    // $days_in_status = $filter['days_in_status'];
                    $filter_date = date('Y-m-d', strtotime("-$days_in_status day"));
                    $qry->whereDate('updated_at', $filter_date);
                }
                // SEARCH
                if (! empty($search)) {
                    $qry->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', '%'.trim($search).'%')
                            ->orWhere('last_name', 'like', '%'.trim($search).'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.trim($search).'%']);
                    });
                }

                if (count($officeIds) > 0) {
                    $qry->whereIn('office_id', $officeIds);
                }

                // Remove Hire Now From other cards
                $qry->whereNotIn('id', $hiringCardListIds);
            });

            // DB::enableQueryLog();
            $list = $list->where('show_on_card', 1)->orderBy('display_order', 'ASC')->get();
            // dd(DB::getQueryLog());

            if (count($hiringCardList) > 0) {
                $hiringBucketList = HiringStatus::where('id', 16)->where('show_on_card', 1)->orderBy('display_order', 'ASC')->first();
                if ($hiringBucketList != null) {
                    $hiringBucketList['onboarding_employees_count'] = count($hiringCardList);
                    $hiringBucketList['OnboardingEmployees'] = $hiringCardList;
                    $list[] = $hiringBucketList;
                }
            }

            if (count($offerAcceptCardList) > 0) {
                $offerAcceptBucketList = HiringStatus::where('id', 13)->where('show_on_card', 1)->orderBy('display_order', 'ASC')->first();
                if ($offerAcceptBucketList != null) {
                    $offerAcceptBucketList['onboarding_employees_count'] = count($offerAcceptCardList);
                    $offerAcceptBucketList['OnboardingEmployees'] = $offerAcceptCardList;
                    $list[] = $offerAcceptBucketList;
                }
            }

            if (count($offerLetterResendList) > 0) {
                // Offer Letter Reseend Shows in Offer Letter Send Card
                $offerLetterSendBucketList = HiringStatus::select('id', 'status', 'display_order', 'hide_status', 'colour_code', 'show_on_card')->where('id', 4)->where('show_on_card', 1)->orderBy('display_order', 'ASC')->first();
                if ($offerLetterSendBucketList != null) {
                    if (count($list) == 0) {
                        $list = HiringStatus::select('id', 'status', 'display_order', 'hide_status', 'colour_code', 'show_on_card')->where('id', 4)->orderBy('display_order', 'ASC')->get();

                        // Offer Letter Resend
                        // dd($list);
                        foreach ($list as $key => $li) {
                            if ($li->id == $offerLetterSendBucketList->id) {
                                $li->OnboardingEmployees = $offerLetterResendList;
                                $li->onboarding_employees_count = count($offerLetterResendList);
                            }
                        }
                    } else {
                        foreach ($list as $key => $li) {
                            if ($li->id == $offerLetterSendBucketList->id) {
                                if (count($li->OnboardingEmployees) != 0) {
                                    $original = new Collection($li->OnboardingEmployees);
                                    $latest = new Collection($offerLetterResendList);
                                    $merged = $original->merge($latest);

                                    $li->OnboardingEmployees = $merged;
                                } else {
                                    $li->OnboardingEmployees = $offerLetterResendList;
                                }

                                $li->onboarding_employees_count = $li->onboarding_employees_count + count($offerLetterResendList);
                                // $list[] = $li;
                            }
                        }
                    }
                }
            }
            // dd($list[0]);
            $list->transform(function ($list) {
                $OnboardingEmployees = collect($list->OnboardingEmployees)->transform(function ($OnboardingEmployees) {

                    //     // return $OnboardingEmployees;
                    $totalcomment = NewSequiDocsDocumentComment::where('user_id_from', 'onboarding_employees')->where('document_send_to_user_id', $OnboardingEmployees->id)->count();
                    $OnboardingEmployees->totalcomment = $totalcomment;

                    return $OnboardingEmployees;
                    //    return [
                    //     'totalcomment' => $totalcomment,
                    //     // 'onboarding_employees' => $OnboardingEmployees,
                    //    ];
                });

                // dd($list);
                return [
                    'id' => $list->id,
                    'status' => $list->status,
                    'display_order' => $list->display_order,
                    'hide_status' => $list->hide_status,
                    'colour_code' => $list->colour_code,
                    'show_on_card' => $list->show_on_card,
                    'onboarding_employees_count' => $list->onboarding_employees_count,
                    'onboarding_employees' => $list->OnboardingEmployees,
                ];
            });

            return response()->json([
                'ApiName' => 'list_onboarding_status',
                'status' => true,
                'data' => $list,
                'bucket_list' => $bucket_list,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_onboarding_status',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function show_hide_onboarding_status(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $id = $request->id;
            $updater_id = Auth::user()->id;
            $status = true;
            $status_code = 200;

            $hide_status = $request->hide_status ?? 0;
            $new_status = HiringStatus::where('id', $id)->update(['hide_status' => $hide_status]);
            $msg = 'Onboarding Status Show/Hide  updated successfully';

            DB::commit();

            return response()->json([
                'ApiName' => 'show_hide_onboarding_status',
                'msg' => $msg,
                'status' => $status,
            ], $status_code);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'show_hide_onboarding_status',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function get_take_interviews_list(Request $request): JsonResponse
    {
        try {
            // $Validator = Validator::make(
            //     $request->all(), ['lead_id' => 'required']
            // );
            // if ($Validator->fails()) {
            //     return response()->json(['error' => $Validator->errors()], 400);
            // }

            $resData = [];
            // $leadData = Lead::find($request->lead_id);
            // if(isset($leadData->reportingManager)){
            //     $resData[] = [
            //         'id'=>$leadData->reportingManager->id,
            //         'first_name'=>$leadData->reportingManager->first_name,
            //         'last_name'=>$leadData->reportingManager->last_name,
            //     ];
            // }

            $addOnboardPermission = Permissions::where('name', 'onboarding-employees-add')->first();
            if ($addOnboardPermission != null) {
                $permissionGroupIds = GroupPermissions::where('permissions_id', $addOnboardPermission->id)->pluck('group_id')->toArray();
                if ($permissionGroupIds != null) {
                    $users = User::whereIn('group_id', $permissionGroupIds)->get();
                    foreach ($users as $key => $user) {
                        $resData[] = [
                            'id' => $user->id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                        ];
                    }
                }
            }

            return response()->json([
                'ApiName' => 'get_take_interviews_list',
                'status' => true,
                'data' => $resData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'get_take_interviews_list',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function pipelineBucketList(Request $request)
    {
        try {
            $bucket_list = PipelineLeadStatus::orderBy('display_order', 'ASC')->get();

            // $bucket_list = $bucket_list->filter(function ($list) {
            //     return $list->hide_status == 0 || $list->status_name == 'New Lead';
            // })->values();

            return response()->json([
                'ApiName' => 'list_lead_status',
                'status' => true,
                'bucket_list' => $bucket_list,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_lead_status',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
