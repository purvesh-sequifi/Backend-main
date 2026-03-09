<?php

namespace App\Http\Controllers\API\Bucket;

use App\Core\Traits\EditSaleTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Http\Controllers\API\ApiMissingDataController;
use App\Http\Controllers\Controller;
use App\Imports\ImportCrmsale;
use App\Jobs\SaleMasterJob;
use App\Models\Bucketbyjob;
use App\Models\Buckets;
use App\Models\BucketSubTask;
use App\Models\BucketSubTaskByJob;
use App\Models\CompanyProfile;
use App\Models\Crmsaleinfo;
use App\Models\ExcelImportHistory;
use App\Models\Locations;
use App\Models\Payroll;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UsersAdditionalEmail;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class BucketController extends Controller
{
    use EditSaleTrait, SetterSubroutineListTrait {
        EditSaleTrait::updateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m1dateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m1datePayrollData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m2dateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m2datePayrollData insteadof SetterSubroutineListTrait;
        EditSaleTrait::executedSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::salesDataHistory insteadof SetterSubroutineListTrait;
    }

    public function list_buckets(Request $request): JsonResponse
    {
        $search = $request->search ?? '';
        $bucket_type = $request->bucket_type ?? 'CRM';
        // echo $request->input('hide_status');die();
        try {
            $bucket_list = Buckets::where('bucket_type', $bucket_type);
            if ($request->has('hide_status') && ! empty($request->input('hide_status'))) {
                $hide_status = $request->input('hide_status');
                $bucket_list = $bucket_list->where('hide_status', $hide_status);
            }
            $bucket_list = $bucket_list->orderBy('display_order', 'ASC')->get();

            return response()->json([
                'ApiName' => 'list_buckets',
                'status' => true,
                // 'data' => $leads,
                'bucket_list' => $bucket_list,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_buckets_with_jobs',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function addUpdateBucket(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    // 'colour_code' => 'required'
                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $id = $request->input('id', 0);
            $bucket_type = $request->input('bucket_type', 'CRM');
            $name = $request->input('name', '');
            $colour_code = $request->input('colour_code', '');
            $warning_day = $request->input('warning_day', '');
            $danger_day = $request->input('danger_day', '');
            $hide_status = $request->input('hide_status', '');
            $display_order = $request->input('display_order', '');

            // Check if a bucket with the same name exists
            $existingBucket = Buckets::where('name', $name)->first();

            if ($existingBucket) {
                // If a bucket with the same name exists, update it
                $updatedata = [];
                if ($name) {
                    $updatedata['name'] = $name;
                }
                if ($colour_code) {
                    $updatedata['colour_code'] = $colour_code;
                }
                if ($warning_day !== null && $warning_day !== '') {
                    $updatedata['warning_day'] = ($warning_day == 0) ? '0' : $warning_day;
                }
                if ($danger_day !== null && $danger_day !== '') {
                    $updatedata['danger_day'] = ($danger_day == 0) ? '0' : $danger_day;
                }
                if ($hide_status !== null && $hide_status !== '') {
                    $updatedata['hide_status'] = ($hide_status == 0) ? '0' : $hide_status;
                }
                if ($display_order !== null && $display_order !== '') {
                    $updatedata['display_order'] = ($display_order == 0) ? '0' : $display_order;
                }

                Buckets::where('id', $existingBucket->id)->update($updatedata);

                $bucket_info = Buckets::where('id', $existingBucket->id)->first();

                return response()->json([
                    'ApiName' => 'addUpdateBucket',
                    'info' => $bucket_info,
                    'status' => true,
                    'message' => 'Bucket updated successfully',
                ], 200);
            } else {
                // If no bucket with the same name exists, create a new one
                $max_display_order = Buckets::max('display_order') ?? 0;

                $newStatus = Buckets::create([
                    'name' => $name,
                    'colour_code' => $colour_code,
                    'bucket_type' => $bucket_type,
                    'display_order' => $max_display_order + 1,
                    'warning_day' => $warning_day,
                    'danger_day' => $danger_day,
                    'hide_status' => $hide_status !== '' ? $hide_status : 0,
                ]);

                $bucket_info = Buckets::where('id', $newStatus->id)->first();

                return response()->json([
                    'ApiName' => 'addUpdateBucket',
                    'info' => $bucket_info,
                    'status' => true,
                    'message' => 'Bucket added successfully',
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'addUpdateBucket',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deletebucket(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'bucket_id' => 'required',
                    // 'colour_code' => 'required'
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            DB::beginTransaction();
            $bucket_id = $request->bucket_id;
            $type = $request->type ?? 'delete'; // hide_show , title , display_order
            $updater_id = Auth::user()->id;
            $status = true;
            $status_code = 200;
            $count = Buckets::where('id', $bucket_id)->count();
            if ($count == 0) {
                return response()->json([
                    'ApiName' => 'deletebucket',
                    'msg' => 'Bucket not exist.',
                    'status' => false,
                ], 400);
            }

            switch ($type) {

                case 'delete':
                    $count = Bucketbyjob::where('bucket_id', $bucket_id)->where('active', 1)->count();
                    if ($count > 0) {
                        $msg = 'Status not empty, first remove all Jobs from this status.';
                        $status = false;
                        $status_code = 400;
                    } else {
                        // $new_status = PipelineLeadStatus::where('id',$lead_status_id)->delete();
                        $new_status = Buckets::find($bucket_id)->delete();
                        $msg = 'Bucket deleted successfully';
                    }
                    break;
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'deletebucket',
                'msg' => $msg,
                'status' => $status,
            ], $status_code);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'deletebucket',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function list_buckets_with_jobs(Request $request)
    {
        $search = $request->search ?? '';
        $bucket_type = $request->bucket_type ?? 'CRM';

        try {
            // $bucket_list = [];
            $bucket_list = Buckets::where('bucket_type', $bucket_type)->orderBy('display_order', 'ASC')->get()->keyBy('id');
            // print_r($bucket_list);
            $fillter = [];

            if ($request->has('orderBy') && ! empty($request->input('orderBy'))) {
                $fillter['orderBy'] = $request->input('orderBy');
            } else {
                $fillter['orderBy'] = 'desc';
            }
            if ($request->has('list') && ! empty($request->input('list'))) {
                $fillter['list'] = $request->input('list');
            } else {
                $fillter['list'] = '';
            }
            if ($request->has('bucket_id') && ! empty($request->input('bucket_id'))) {
                $fillter['bucket_id'] = $request->input('bucket_id');
            } else {
                $fillter['bucket_id'] = '';
            }
            if ($request->has('search') && ! empty($request->input('search'))) {
                $fillter['search'] = $request->input('search');
            } else {
                $fillter['search'] = '';
            }
            if ($request->has('office_id') && ! empty($request->input('office_id'))) {
                $fillter['office_id'] = $request->input('office_id');
            } else {
                $fillter['office_id'] = '';
            }
            if ($request->has('location') && ! empty($request->input('location'))) {
                $fillter['location'] = $request->input('location');
            } else {
                $fillter['location'] = '';
            }
            if ($request->has('timer') && ! empty($request->input('timer'))) {
                $fillter['timer'] = $request->input('timer');
            } else {
                $fillter['timer'] = '';
            }
            if (! empty($request->perpage)) {
                $fillter['perpage'] = $request->perpage;
            } else {
                $fillter['perpage'] = 10;
            }

            $today = Carbon::now();

            // print_r($bucket_list[1]->name);die();
            $jobandsales = $this->getSales($fillter);
            // echo "<pre>";print_r($jobandsales);die();
            $jobs = [];
            $jobs_listing = [];
            foreach ($jobandsales as $jobandsale) {
                $bucketbyjob = $jobandsale->bucketbyjob;
                // echo "<pre>"print_r($jobandsale->BucketSubTaskByJob);die();
                $bucketsubtasktotal = BucketSubTask::where('bucket_subtask.bucket_id', $bucketbyjob['bucket_id'])->count();
                $bucketsubtasktotal_done = BucketSubTask::leftjoin('bucket_subtask_by_job', 'bucket_subtask_by_job.bucket_sutask_id', '=', 'bucket_subtask.id')->where('bucket_subtask.bucket_id', $bucketbyjob['bucket_id'])->where('bucket_subtask_by_job.job_id', $jobandsale->id)->where('bucket_subtask_by_job.status', 1)->count();
                // $bucketsubtasktotal = $jobandsale->BucketSubTaskByJob->count();
                // $bucketsubtasktotal_done = $jobandsale->BucketSubTaskByJob()->where('status', 1)->count();
                $totalcomment = $jobandsale->jobbycomments->count();
                $totalattachment = $jobandsale->jobbydocuments->count();

                // print_r($jobandsale->cancel_date);die();
                // print_r($bucketbyjob);die();
                $data = $jobandsale->sales;
                if ($data == '') {
                    continue;
                }
                // print_r($data);die();
                $bucket_info = $bucket_list[$bucketbyjob['bucket_id']];
                /*$locationData = Locations::with('State')->where('general_code','=', $data->customer_state)->first();
                if($locationData){
                    $state_code = $locationData->state->state_code;
                }else{
                    $state_code = null;
                }*/
                $customer_state = isset($data->customer_state) ? $data->customer_state : 0;
                if (config('app.domain_name') == 'flex') {
                    $location_code = isset($data->customer_state) ? $data->customer_state : 0;
                } else {
                    $location_code = isset($data->location_code) ? $data->location_code : 0;
                }
                $location = Locations::with('State')->where('general_code', '=', $location_code)->first();
                if ($location) {
                    $state_code = $location->state->state_code;

                    $redline_standard = $location->redline_standard;
                } else {
                    $state_code = null;
                    $state = State::where('state_code', '=', $customer_state)->first();
                    // echo $customer_state;die;
                    if ($state) {
                        $location = Locations::where(['state_id' => $state->id, 'type' => 'Redline'])->first();
                        $redline_standard = isset($location->redline_standard) ? $location->redline_standard : null;
                    } else {
                        $location = null;
                        $redline_standard = null;
                    }

                }
                $commissionData = UserCommission::where(['pid' => $data->pid, 'status' => 3])->first();
                if (! in_array($data->salesMasterProcess->mark_account_status_id, [1, 6]) && $commissionData) {
                    $mark_account_status_name = ($commissionData) ? 'Paid' : null;
                } else {
                    $mark_account_status_name = isset($data->salesMasterProcess->status->account_status) ? $data->salesMasterProcess->status->account_status : null;
                }
                $closer1_detail = isset($data->salesMasterProcess->closer1_id) ? $data->salesMasterProcess->closer1Detail : null;
                $closer2_detail = isset($data->salesMasterProcess->closer2_id) ? $data->salesMasterProcess->closer2Detail : null;
                $setter1_detail = isset($data->salesMasterProcess->setter1_id) ? $data->salesMasterProcess->setter1Detail : null;
                $setter2_detail = isset($data->salesMasterProcess->setter2_id) ? $data->salesMasterProcess->setter2Detail : null;
                $approveDate = $data->customer_signoff;
                $sale = [
                    'job_id' => $jobandsale->id,
                    'job_cancel_date' => $jobandsale->cancel_date,
                    'jobstatus' => $jobandsale->status,
                    'jobsubtasttotal' => $bucketsubtasktotal,
                    'jobsubtasttotal_done' => $bucketsubtasktotal_done,
                    'totalcomment' => $totalcomment,
                    'totalattachment' => $totalattachment,
                    'id' => $data->id,
                    'pid' => $data->pid,
                    'sale_status' => $data->job_status,
                    // 'alertcentre_status'=>$alertcentre_status,
                    'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                    'state_id' => $state_code,
                    'customer_phone' => $data->customer_phone,
                    'customer_email' => $data->customer_email,

                    'state' => isset($data->customer_state) ? $data->customer_state : null,
                    'city' => isset($data->customer_city) ? $data->customer_city : null,
                    'sales_rep_name' => isset($data->sales_rep_name) ? $data->sales_rep_name : null,
                    'mark_account_status_id' => isset($data->salesMasterProcess->mark_account_status_id) ? $data->salesMasterProcess->mark_account_status_id : null,
                    'mark_account_status_name' => $mark_account_status_name,
                    'approved_date' => $approveDate,
                    'epc' => isset($data->epc) ? $data->epc : null,
                    'install_partner' => isset($data->install_partner) ? $data->install_partner : null,
                    'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                    'adders' => isset($data->adders) ? $data->adders : null,
                    'kw' => isset($data->kw) ? $data->kw : null,
                    'date_cancelled' => isset($data->date_cancelled) ? dateToYMD($data->date_cancelled) : null,
                    'bucket_id' => isset($bucket_info['id']) ? $bucket_info['id'] : null,

                    'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                    'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,

                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'data_source_type' => $data->data_source_type,

                    'closer1_detail' => $closer1_detail,
                    'closer2_detail' => $closer2_detail,
                    'setter1_detail' => $setter1_detail,
                    'setter2_detail' => $setter2_detail,

                    /*'closer1_m1' => $closer1_m1,
                    'closer1_m2' => $closer1_m2,
                    'closer2_m1' => $closer2_m1,
                    'closer2_m2' => $closer2_m2,
                    'setter1_m1' => $setter1_m1,
                    'setter1_m2' => $setter1_m2,
                    'setter2_m1' => $setter2_m1,
                    'setter2_m2' => $setter2_m2,*/

                ];

                $givenDate = Carbon::parse($jobandsale->bucketbyjob->updated_at)->toDateString();
                $today = Carbon::now()->toDateString();
                $daysAgo = Carbon::parse($givenDate)->diffInDays($today);

                // echo $daysAgo;die();
                if ($daysAgo >= $bucket_info['warning_day'] && $bucket_info['warning_day'] > 0 && ($bucket_info['danger_day'] != 0 && $daysAgo < $bucket_info['danger_day'])) {
                    $data->warning = $daysAgo;
                    $sale['warning'] = $daysAgo;
                } elseif ($daysAgo >= $bucket_info['danger_day'] && $bucket_info['danger_day'] > 0) {
                    $sale['danger'] = $daysAgo;
                } else {
                    $data->normalday = $daysAgo;
                    $sale['normalday'] = $daysAgo;
                }

                if ($fillter['list'] == '') {
                    if (isset($jobs[$bucketbyjob['bucket_id']])) {
                        $jobs[$bucketbyjob['bucket_id']]['jobs'][] = $sale;
                    } else {
                        $jobs[$bucketbyjob['bucket_id']] = [
                            'id' => $bucket_info['id'],
                            'name' => $bucket_info['name'],
                            'display_order' => $bucket_info['display_order'],
                            'hide_status' => $bucket_info['hide_status'],
                            'updated_at' => $bucket_info['updated_at'],
                            'colour_code' => $bucket_info['colour_code'],
                            // 'jobs' =>
                        ];
                        if (! empty($sale)) {
                            $jobs[$bucketbyjob['bucket_id']]['jobs'] = ['0' => $sale];
                        }

                    }
                } else {

                    if (! empty($sale)) {
                        $sale['bucket_name'] = $bucket_info['name'];
                        $jobs_listing[] = $sale;
                    } else {
                        // $jobandsales->total() =  $jobandsales->total()-1;
                    }

                }

            }
            if ($fillter['list'] == '') {
                usort($jobs, function ($a, $b) {
                    $ageComparison = $a['display_order'] <=> $b['display_order'];

                    return $ageComparison;
                });
            } else {
                $jobs['currentPage'] = $jobandsales->currentPage();

                $jobs['data'] = $jobs_listing;
                $jobs['next_page_url'] = $jobandsales->nextPageUrl();
                $jobs['path'] = $jobandsales->path();
                $jobs['per_page'] = $jobandsales->perPage();
                $jobs['prev_page_url'] = $jobandsales->previousPageUrl();
                // $jobs['to'] = $jobandsales->to();
                $jobs['total'] = $jobandsales->total();
                // $jobs['current_page'] = $jobandsales->current_page;
                // $jobs['current_page'] = $jobandsales->current_page;
                // $jobs['current_page'] = $jobandsales->current_page;
            }
            // echo "<pre>";print_r($jobs);die();
            $bucket_list = Buckets::where('bucket_type', $bucket_type)->orderBy('display_order', 'ASC')->get();

            // print_r($bucket_list);die();
            return response()->json([
                'ApiName' => 'list_buckets_with_jobs',
                'status' => true,
                'data' => $jobs,
                'bucket_list' => $bucket_list,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_buckets_with_jobs',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function add_job(Request $request): JsonResponse
    {
        $pid = $request->pid ?? '';
        $bucket_id = $request->bucket_id ?? '';

        try {
            $newData = Crmsaleinfo::where('pid', $pid)->get();
            if ($newData->isEmpty()) {
                Buckets::addcrmsale($pid, $request->bucket_id);

                return response()->json([
                    'ApiName' => 'add_job',
                    'status' => true,
                    'message' => 'Successfully',
                    // 'data' => $leads,
                    // 'bucket_list' => $bucket_list
                ], 200);

            } else {
                return response()->json([
                    'ApiName' => 'add_job',
                    'status' => true,
                    // 'data' => $leads,
                    'message' => 'Already Exist.',
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add_job',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function move_job(Request $request): JsonResponse
    {
        $job_id = $request->job_id ?? '';
        $bucket_id = $request->bucket_id ?? '';
        $subtaskpreference = $request->subtaskpreference ?? DB::table('users_preference')->where('user_id', Auth::user()->id)->value('move_job');
        $remember = $request->remember ?? 0;
        if ($remember > 0) {
            $this->setbucketpreference($request);
        }
        $bucketcheck = Buckets::where('id', $bucket_id)->count();
        if ($bucketcheck == 0) {
            return response()->json([
                'ApiName' => 'getbucketsubtasks',
                'msg' => 'Bucket not exist.',
                'status' => false,
            ], 400);
        }
        $jobcheck = Crmsaleinfo::where('id', $job_id)->count();
        if ($jobcheck == 0) {
            return response()->json([
                'ApiName' => 'move_job',
                'msg' => 'Job not exist.',
                'status' => false,
            ], 400);
        }

        try {
            $current_bucket_id = Bucketbyjob::where('job_id', $job_id)->where('active', 1)->value('bucket_id');
            Buckets::movejobprocess($job_id, $bucket_id);

            // cancelled job
            $job = Crmsaleinfo::select('pid')->where('id', $job_id)->first();
            if ($bucket_id == 2) {
                SalesMaster::where('pid', $job->pid)->update(['date_cancelled' => NOW(), 'job_status' => 'cancel']);
                // PEST Flow STARTS
                $companyProfile = CompanyProfile::first();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    (new ApiMissingDataController)->subroutine_process($job->pid);
                } else {
                    (new ApiMissingDataController)->subroutine_process($job->pid);
                }
            }
            if ($current_bucket_id == 2 && $bucket_id != 2) {
                // SalesMaster::where('pid', $job->pid)->update(['date_cancelled' => null,'return_sales_date'=>null,'job_status'=>'cancel']);
            }
            // cancelled job

            if ($subtaskpreference != '') {
                if ($subtaskpreference == 'move_complete') {
                    $this->subtaskupdated($job_id, $current_bucket_id, 1);
                } elseif ($subtaskpreference == 'move_without_complete') {
                    // $this->subtaskupdated($current_bucket_id,2);
                }
            }

            return response()->json([
                'ApiName' => 'move_job',
                'status' => true,
                'message' => 'Successfully move',
                // 'data' => $leads,
                // 'bucket_list' => $bucket_list
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'move_job',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function add_bucket_subtask(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'bucket_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $bucket_id = $request->bucket_id ?? '';
        $bucketcheck = Buckets::where('id', $bucket_id)->count();
        if ($bucketcheck == 0) {
            return response()->json([
                'ApiName' => 'add_bucket_subtask',
                'msg' => 'Bucket not exist.',
                'status' => false,
            ], 400);
        }

        $names = $request->name ?? '';
        if (! is_array($names)) {
            $names = Arr::wrap($names);
        }

        try {
            if ($names) {
                foreach ($names as $name) {
                    $data = [
                        'bucket_id' => $bucket_id,
                        'name' => $name,
                        'created_id' => Auth()->user()->id,
                    ];
                    $insert = BucketSubTask::create($data);
                    $subtaskid = $insert->id;

                    $getdata_buckets = Bucketbyjob::where('bucket_id', $bucket_id)->where('active', 1)->get();
                    foreach ($getdata_buckets as $getdata_bucket) {
                        $pdata = [
                            'bucket_sutask_id' => $subtaskid,
                            'job_id' => $getdata_bucket->job_id,
                        ];
                        // $this->checksubtaskaddandupdate($pdata);
                        Buckets::checksubtaskaddandupdate($pdata);
                    }
                }

                return response()->json([
                    'ApiName' => 'add_bucket_subtask',
                    'status' => true,
                    'message' => 'successfully Added.',
                    // 'data' => $leads,
                    // 'bucket_list' => $bucket_list
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add_bucket_subtask',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function subtaskupdate(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'sub_task_id' => 'required',
                    // 'colour_code' => 'required'
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $sub_task_id = $request->sub_task_id;
            $bucket_id = $request->bucket_id ?? 0;
            $task_status = $request->status ?? '';
            $date = $request->date ?? '';

            $updatedata = [];
            if ($task_status != '') {
                $updatedata['status'] = $task_status;
            }
            if ($date != '') {
                $updatedata['date'] = $date.' 00:00:00';
            }
            $status = true;
            $status_code = 200;
            if (! is_array($sub_task_id)) {
                $sub_task_id = (array) $sub_task_id;
            }

            if (is_array($sub_task_id)) {
                foreach ($sub_task_id as $sub_taskid) {
                    $count = BucketSubTaskByJob::where('id', $sub_taskid)->count();
                    if ($count > 0) {
                        if (! empty($updatedata)) {
                            BucketSubTaskByJob::where('id', $sub_taskid)->update($updatedata);
                        }
                        $msg = 'successfully Updated';
                    }
                }
                if ($bucket_id > 0) {
                    Buckets::where('id', $bucket_id)->update(['updated_at' => NOW()]);
                }
            }

            return response()->json([
                'ApiName' => 'subtaskupdate',
                'msg' => $msg,
                'status' => $status,
            ], $status_code);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'subtaskupdate',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deletesubtask(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'sub_task_id' => 'required',
                    // 'colour_code' => 'required'
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            DB::beginTransaction();
            $sub_task_id = $request->sub_task_id;

            $type = $request->type ?? 'delete'; // hide_show , title , display_order
            $updater_id = Auth::user()->id;
            $status = true;
            $status_code = 200;

            switch ($type) {
                case 'delete':
                    $count = BucketSubTask::where('id', $sub_task_id)->count();
                    if ($count > 0) {
                        $new_status = BucketSubTask::find($sub_task_id)->delete();
                        BucketSubTaskByJob::where('bucket_sutask_id', $sub_task_id)->delete();
                        $msg = 'Status deleted successfully';
                    } else {
                        $msg = 'Subtask not exist.';
                        $status = false;
                        $status_code = 400;
                    }
                    break;
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'deletesubtask',
                'msg' => $msg,
                'status' => $status,
            ], $status_code);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'deletesubtask',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function jobinfoupdate(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'job_id' => 'required',
                    'type' => 'required',
                    'cancel_date' => 'required',
                    // 'colour_code' => 'required'
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            // DB::beginTransaction();
            $job_id = $request->job_id;
            $type = $request->type ?? '';
            $cancel_date = $request->cancel_date ?? '';
            $jobcheck = Crmsaleinfo::where('id', $job_id)->count();
            if ($jobcheck == 0) {
                return response()->json([
                    'ApiName' => 'jobinfoupdate',
                    'msg' => 'Job not exist.',
                    'status' => false,
                ], 400);
            }

            $updater_id = Auth::user()->id;
            $status = true;
            $status_code = 200;
            $count = Crmsaleinfo::where('id', $job_id)->count();
            if ($count == 0) {
                return response()->json([
                    'ApiName' => 'jobinfoupdate',
                    'msg' => 'Job not exist.',
                    'status' => false,
                ], 400);
            }

            switch ($type) {
                case 'cancel':
                    $crminfo = Crmsaleinfo::where('id', $job_id)->first();
                    $pid = $crminfo['pid'];
                    // echo $pid;die();

                    $updatedata['cancel_date'] = $cancel_date;
                    $updatedata['status'] = 'cancel';
                    Crmsaleinfo::where('id', $job_id)->update($updatedata);
                    SalesMaster::where('pid', $pid)->update(['date_cancelled' => $cancel_date, 'job_status' => 'cancel']);
                    Buckets::movejobprocess($job_id, 2);
                    // PEST Flow STARTS
                    $companyProfile = CompanyProfile::first();
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        (new ApiMissingDataController)->subroutine_process($pid);
                    } else {
                        (new ApiMissingDataController)->subroutine_process($pid);
                    }
                    $msg = 'successfully cancel job';
                    break;
            }

            // DB::commit();
            return response()->json([
                'ApiName' => 'jobinfoupdate',
                'msg' => $msg,
                'status' => $status,
            ], $status_code);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'jobinfoupdate',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    protected function cancel_job_subroutine_process($pid)
    {
        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        // print_r($checked);die();
        $dateCancelled = $checked->date_cancelled;
        $m1_date = $checked->m1_date;
        $m2_date = $checked->m2_date;

        $m1_paid_status = $checked->salesMasterProcess->setter1_m1_paid_status;

        $closer1_id = $checked->salesMasterProcess->closer1_id;
        $closer2_id = $checked->salesMasterProcess->closer2_id;
        $setter1_id = $checked->salesMasterProcess->setter1_id;
        $setter2_id = $checked->salesMasterProcess->setter2_id;

        // Is there a clawback date = dateCancelled ?
        // if ($dateCancelled || $returnSalesDate) {
        if ($dateCancelled) {
            if ($checked->salesMasterProcess->mark_account_status_id == 1 || $checked->salesMasterProcess->mark_account_status_id == 6) {
                // 'No clawback calculations required ';
            } elseif (empty($m1_date) && empty($m2_date)) {
                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                if ($saleMasterProcess) {
                    $saleMasterProcess->mark_account_status_id = 6;
                    $saleMasterProcess->save();
                }
            } else {
                $this->subroutineFive($checked);
            }
        }
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
            DB::rollBack();

            return response()->json([
                'ApiName' => 'getuserpreference',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getbucketsubtasks(Request $request): JsonResponse
    {
        // $search = $request->search??'';
        $bucket_id = $request->bucket_id ?? 0;
        // echo $request->input('hide_status');die();
        $count = Buckets::where('id', $bucket_id)->count();
        if ($count == 0) {
            return response()->json([
                'ApiName' => 'getbucketsubtasks',
                'msg' => 'Bucket not exist.',
                'status' => false,
            ], 400);
        }
        try {
            $bucket_list_task = BucketSubTask::where('bucket_id', $bucket_id);

            $bucket_list_task = $bucket_list_task->orderBy('id', 'ASC')->get();

            return response()->json([
                'ApiName' => 'getbucketsubtasks',
                'status' => true,
                // 'data' => $leads,
                'data' => $bucket_list_task,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'getbucketsubtasks',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function jobs_export(Request $request)
    {
        $fillter = [];

        if ($request->has('orderBy') && ! empty($request->input('orderBy'))) {
            $fillter['orderBy'] = $request->input('orderBy');
        } else {
            $fillter['orderBy'] = 'desc';
        }
        if ($request->has('bucket_id') && ! empty($request->input('bucket_id'))) {
            $fillter['bucket_id'] = $request->input('bucket_id');
        } else {
            $fillter['bucket_id'] = '';
        }
        if ($request->has('search') && ! empty($request->input('search'))) {
            $fillter['search'] = $request->input('search');
        } else {
            $fillter['search'] = '';
        }
        if ($request->has('office_id') && ! empty($request->input('office_id'))) {
            $fillter['office_id'] = $request->input('office_id');
        } else {
            $fillter['office_id'] = '';
        }
        if ($request->has('location') && ! empty($request->input('location'))) {
            $fillter['location'] = $request->input('location');
        } else {
            $fillter['location'] = '';
        }
        if (! empty($request->perpage)) {
            $fillter['perpage'] = $request->perpage;
        } else {
            $fillter['perpage'] = 10;
        }
        if ($request->has('timer') && ! empty($request->input('timer'))) {
            $fillter['timer'] = $request->input('timer');
        } else {
            $fillter['timer'] = '';
        }
        $fillter['list'] = '';
        $fillter['reports'] = 1;

        $today = Carbon::now();

        // print_r($bucket_list[1]->name);die();
        $data = $this->getSales($fillter);
        // echo "<pre>";print_r($data);
        // die();
        if (count($data) == 0) {
            return response()->json([
                'ApiName' => 'jobs_export',
                'status' => false,
                'message' => 'Data not found!',
            ], 400);
        }
        $companyProfile = CompanyProfile::first();
        $data->transform(function ($data) {

            $setter1Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                ->where(['user_id' => $data->sales->salesMasterProcess->setter1_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
            // print_r($setter1Commissions);die();
            $setter1_m1 = 0;
            $setter1_m2 = 0;
            foreach ($setter1Commissions as $setter1Commission) {
                if ($setter1Commission->amount_type == 'm1') {
                    $setter1_m1 = $setter1Commission->commission;
                } elseif ($setter1Commission->amount_type == 'm2') {
                    $setter1_m2 = $setter1Commission->commission;
                }
            }

            $setter2Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                ->where(['user_id' => $data->sales->salesMasterProcess->setter2_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
            $setter2_m1 = 0;
            $setter2_m2 = 0;
            foreach ($setter2Commissions as $setter2Commission) {
                if ($setter2Commission->amount_type == 'm1') {
                    $setter1_m1 = $setter2Commission->commission;
                } elseif ($setter2Commission->amount_type == 'm2') {
                    $setter1_m2 = $setter2Commission->commission;
                }
            }

            $closer1Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                ->where(['user_id' => $data->sales->salesMasterProcess->closer1_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
            $closer1_m1 = 0;
            $closer1_m2 = 0;
            foreach ($closer1Commissions as $closer1Commission) {
                if ($closer1Commission->amount_type == 'm1') {
                    $closer1_m1 = $closer1Commission->commission;
                } elseif ($closer1Commission->amount_type == 'm2') {
                    $closer1_m2 = $closer1Commission->commission;
                }
            }

            $closer2Commissions = UserCommission::selectRaw('SUM(amount) as commission, amount_type')
                ->where(['user_id' => $data->sales->salesMasterProcess->closer2_id, 'pid' => $data->pid, 'is_displayed' => '1'])->groupBy('amount_type')->get();
            $closer2_m1 = 0;
            $closer2_m2 = 0;
            foreach ($closer2Commissions as $closer2Commission) {
                if ($closer2Commission->amount_type == 'm1') {
                    $closer2_m1 = $closer2Commission->commission;
                } elseif ($closer2Commission->amount_type == 'm2') {
                    $closer2_m2 = $closer2Commission->commission;
                }
            }

            $total_m1 = ($closer1_m1 + $closer2_m1 + $setter1_m1 + $setter2_m1);
            $total_m2 = ($closer1_m2 + $closer2_m2 + $setter1_m2 + $setter2_m2);
            $total_commission = $total_m1 + $total_m2;

            $location = Locations::where('general_code', '=', $data->sales->customer_state)->first();
            if ($location) {
                $redline_standard = $location->redline_standard;
            } else {
                $state = State::where('state_code', '=', $data->sales->customer_state)->first();
                if ($state) {
                    $location = Locations::where(['state_id' => $state->id, 'type' => 'Redline'])->first();
                    $redline_standard = isset($location->redline_standard) ? $location->redline_standard : null;
                } else {
                    $location = null;
                    $redline_standard = null;
                }
            }

            $accountOverrides = $data->sales->override;
            if (count($accountOverrides) > 0) {
                $accountOverrides->transform(function ($override) use ($data) {
                    if ($override->sale_user_id == $data->sales->salesMasterProcess->closer1_id || $override->sale_user_id == $data->sales->salesMasterProcess->closer2_id) {
                        $positionName = 'Closer';
                    } else {
                        $positionName = 'Setter';
                    }
                    $image = $override->user->image ?? null;
                    $first_name = $override->user->first_name ?? null;
                    $last_name = $override->user->last_name ?? null;

                    return [
                        'through' => $positionName,
                        'image' => $image,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'type' => $override->type,
                        'amount' => $override->overrides_amount,
                        'weight' => $override->overrides_type,
                        'total' => $override->amount,
                        'calculated_redline' => $override->calculated_redline,
                        'assign_cost' => null,
                    ];
                });
            } else {
                $accountOverrides = '';
            }

            $day = Crmsaleinfo::getdaytime($data->bucketbyjob->updated_at);
            $bucket_info = Buckets::where('id', $data->bucketbyjob->bucket_id)->first();

            return [
                'pid' => $data->pid,
                'bucket_id' => $bucket_info['name'],
                'customer_name' => $data->sales->customer_name,
                'source' => $data->sales->data_source_type,
                'status' => $data->sales->salesMasterProcess->status->account_status ?? null,
                'state' => $data->sales->customer_state,
                'closer' => $data->sales->salesMasterProcess->closer1Detail->first_name ?? null,
                'closer_1' => $data->sales->salesMasterProcess?->closer1Detail?->first_name.' '.$data->sales->salesMasterProcess?->closer1Detail?->last_name,
                'closer_2' => $data->sales->salesMasterProcess?->closer2Detail?->first_name.' '.$data->sales->salesMasterProcess?->closer2Detail?->last_name,
                'setter_1' => $data->sales->salesMasterProcess?->setter1Detail?->first_name.' '.$data->sales->salesMasterProcess?->setter1Detail?->last_name,
                'setter_2' => $data->sales->salesMasterProcess?->setter2Detail?->first_name.' '.$data->sales->salesMasterProcess?->setter2Detail?->last_name,
                'kw' => $data->sales->kw,
                'm1' => $total_m1,
                'm1_date' => $data->sales->m1_date,
                'm2' => $total_m2,
                'm2_date' => $data->sales->m2_date,
                'epc' => $data->sales->epc,
                'net_epc' => $data->sales->net_epc,
                'adders' => $data->sales->adders,
                'total_commission' => $total_commission,
                'installer' => $data->sales->installer ?? '',
                'prospect_id' => $data->sales->prospect_id ?? '',
                'customer_address' => $data->sales->customer_address ?? '',
                'homeowner_id' => $data->sales->homeowner_id ?? '',
                'customer_city' => $data->sales->customer_city ?? '',
                'customer_zip' => $data->sales->customer_zip ?? '',
                'customer_email' => $data->sales->customer_email ?? '',
                'customer_phone' => $data->sales->customer_phone ?? '',
                'proposal_id' => $data->sales->proposal_id ?? '',
                'sale_state_redline' => $redline_standard ?? '',
                'redline' => $data->sales->redline ?? '',
                'redline_amount_type' => $data->sales->redline_amount_type ?? '',
                'date_cancelled' => $data->sales->date_cancelled ?? '',
                'approved_date' => $data->sales->approved_date ?? '',
                'product' => $data->sales->product ?? '',
                'gross_account_value' => $data->sales->gross_account_value ?? '',
                'dealer_fee_percentage' => $data->sales->dealer_fee_percentage ?? '',
                'dealer_fee_amount' => $data->sales->dealer_fee_amount ?? '',
                'show' => $data->sales->show ?? '',
                'adders_description' => $data->sales->adders_description ?? '',
                'total_amount_for_acct' => $data->sales->total_amount_for_acct ?? '',
                'cancel_fee' => $data->sales->cancel_fee ?? '',
                'cancel_deduction' => $data->sales->cancel_deduction ?? '',
                'account_status' => $data->sales->account_status ?? '',
                'info' => $accountOverrides,
                'days' => $day,
                'job_status' => $data->sales->job_status ?? '',
            ];
        });
        // echo "<pre>";print_r($data);die();

        try {

            $file_name = 'jobs_export_'.date('Y_m_d_H_i_s').'.xlsx';
            Excel::store(new \App\Exports\Jobssalesexports($data, $companyProfile), 'exports/jobs/'.$file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);

            // Get the URL for the stored file
            $url = getStoragePath('exports/jobs/'.$file_name);

            // $url = getExportBaseUrl().'storage/exports/jobs/' . $file_name;
            // Return the URL in the API response
            return response()->json(['url' => $url]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'jobs_exports',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function jobs_import(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }
        try {
            // INSERTING DATA INTO HISTORY TABLE
            DB::beginTransaction();
            $importSales = new ImportCrmsale;

            $importSales->total_records = 0;
            $user_data = User::where('id', '!=', 1)->select('id', 'email', DB::raw("CONCAT(first_name, ' ', last_name) AS full_name"))->get();
            $user_email_arr = [];

            foreach ($user_data as $ud) {
                $user_email_arr[strtolower($ud['email'])] = $ud['id'];
            }
            $additional_emails = UsersAdditionalEmail::select('user_id', 'email')->get();
            foreach ($additional_emails as $ad) {
                $user_email_arr[strtolower($ad['email'])] = $ad['user_id'];
            }

            $importSales->users = $user_email_arr;
            $state_locations = State::join('locations', 'locations.state_id', '=', 'states.id')->select('states.state_code', 'locations.general_code')->get();
            $state_locations_arr = [];
            if (! empty($state_locations)) {
                foreach ($state_locations as $st) {
                    $state_locations_arr[$st['state_code']][] = $st['general_code'];
                }
            }
            $importSales->state_locations_arr = $state_locations_arr;
            $importSales->import_id = time();
            // echo "<pre>";print_r($importSales);die();
            Excel::import($importSales, $request->file('file'));
            // echo $importSales->status;die();
            if ($importSales->status) {
                DB::commit();
                // STORE FILE ON S3 PRIVATE BUCKET
                $original_file_name = str_replace(' ', '_', $request->file('file')->getClientOriginalName());
                $file_name = config('app.domain_name').'/'.'excel_uploads/'.$importSales->import_id.'_'.$original_file_name;
                s3_upload($file_name, $request->file('file'), true);

                $user_id = Auth::user()->id;
                $user = User::find($user_id);

                // JOB QUEUE FOR INSERT INTO SALES MASTER
                $companyProfile = CompanyProfile::first();
                $dataForPusher = [
                    'user_id' => $user_id,
                    'file_name' => $file_name,
                ]; // send this data to pusher event
                if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
                    dispatch(new SaleMasterJob($user, true, $dataForPusher));
                } else {
                    dispatch(new SaleMasterJob($user, false, $dataForPusher));
                }
                // SEND EMAIL NOTIFICATION WHNE EXCEL IMPORT DONE.
                // $email_data = [
                //     'email' => $user->email,
                //     'subject' => 'Excel import for file - ' . $original_file_name . ' imported successfully',
                //     'template' => 'Excel file ' . $original_file_name . ' upload done successfully'
                // ];
                // $this->sendEmailNotification($email_data);

                ExcelImportHistory::create([
                    'user_id' => $user_id,
                    'uploaded_file' => $file_name,
                    'total_records' => $importSales->total_records,
                    'created_at' => now()->setTimezone('UTC'),
                    'updated_at' => now()->setTimezone('UTC'),
                ]);

                return response()->json([
                    'ApiName' => 'jobs_import',
                    'status' => $importSales->status,
                    'message' => $importSales->message,
                    'error' => $importSales->errors,
                ]);
            } else {
                DB::rollBack();

                return response()->json([
                    'ApiName' => 'jobs_import',
                    'status' => $importSales->status,
                    'message' => $importSales->message,
                    'error' => $importSales->errors,
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_buckets_with_jobs',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function testdocupload(Request $request): JsonResponse
    {

        $file = $request->attachment;
        echo config('services.aws.s3_bucket_url');
        $number = $request->number ?? 0;
        // print_r($file);die();
        // s3 bucket
        $img_path = time().$file->getClientOriginalName();
        // $img_path = str_replace(' ', '_',$img_path);
        if ($number == 0) {
            $img_path = str_replace(' ', '_', $img_path);
            $ds_path = 'documents/'.$img_path;
            $awsPath = config('app.domain_name').'/'.$ds_path;
        } elseif ($number == 1) {
            $img_path = str_replace(' ', '_', $img_path);
            $ds_path = 'crmdocuments/'.$img_path;
            $awsPath = config('app.domain_name').'/'.$ds_path;
        } elseif ($number == 2) {
            $img_path = str_replace(' ', '_', $img_path);
            $ds_path = 'documnet_image/'.$img_path;
            $awsPath = config('app.domain_name').'/'.$ds_path;
        } elseif ($number == 4) {
            $img_path = str_replace(' ', '_', $img_path);
            $ds_path = 'documnet_image/'.$img_path;
            $awsPath = 'dev/'.$ds_path;
        } else {
            $img_path = str_replace(' ', '_', $img_path);
            $ds_path = 'crmdocuments/'.$img_path;
            $awsPath = config('app.domain_name').'/'.$ds_path;
        }
        $awsPath = 'demo/'.$ds_path;

        try {
            $ee1 = '';
            if ($number == 1) {
                $ee1 = s3_upload($awsPath, file_get_contents($file), true);
            } elseif ($number == 2) {
                $ee1 = s3_upload($awsPath, file_get_contents($file), false);
            } else {
                $ee1 = s3_upload($awsPath, file_get_contents($file));
            }

            $dd = s3_getTempUrl(config('app.domain_name').'/'.$ds_path);

            print_r($dd);
            exit();
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'list_buckets_with_jobs',
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
                ->update(['move_job' => $status_update, 'remember' => $remember]);
        } else {
            DB::table('users_preference')->insert([
                'user_id' => $updater_id,
                'move_job' => $status_update,
                'remember' => $remember,
            ]);
        }
    }

    protected function subtaskupdated($job_id, $bucket_id, $status)
    {
        $getsubtasks = BucketSubTask::where('bucket_id', $bucket_id)->get();
        foreach ($getsubtasks as $getsubtask) {
            $pdata = [
                'status' => 1,
                'date' => NOW(),
            ];
            BucketSubTaskByJob::where('bucket_sutask_id', $getsubtask->id)->where('job_id', $job_id)->update($pdata);
        }
    }

    protected function getSales($fillter)
    {
        // $result = SalesMaster::with('crmsaleinfo','salesMasterProcess','userDetail');
        if (isset($fillter['reports']) && $fillter['reports'] == 1) {
            $result = Crmsaleinfo::with(['bucketbyjob', 'BucketSubTaskByJob', 'sales']);
        } else {
            $result = Crmsaleinfo::with(['bucketbyjob', 'BucketSubTaskByJob', 'sales']);
        }
        $orderBy = $fillter['orderBy'];
        if ($fillter['timer'] != '') {
            $timer = $fillter['timer'];
            $result->whereHas('bucketbyjob', function ($query) use ($timer) {
                $query->join('buckets', 'buckets.id', '=', 'bucket_by_job.bucket_id');
                if ($timer == 'ontime') {
                    return $query->whereRaw('DATE(bucket_by_job.updated_at) > (NOW() - INTERVAL buckets.warning_day DAY)');
                } elseif ($timer == 'warning') {
                    return $query->whereRaw('DATE(bucket_by_job.updated_at) <= (NOW() - INTERVAL buckets.warning_day DAY)')
                        ->whereRaw('DATE(bucket_by_job.updated_at) > (NOW() - INTERVAL buckets.danger_day DAY)');
                } elseif ($timer == 'danger') {
                    return $query->whereRaw('DATE(bucket_by_job.updated_at) < (NOW() - INTERVAL buckets.danger_day DAY)');

                }

            });
        }
        if ($fillter['office_id'] != '') {
            $office_id = $fillter['office_id'];
            // echo $office_id;die();
            if ($office_id != 'all') {
                $userId = User::where('office_id', $office_id)->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
                // print_r($salesPid);die();
                $result->where(function ($query) use ($salesPid) {
                    return $query->whereIn('pid', $salesPid);
                });
            }
        }

        if ($fillter['bucket_id'] != '') {
            $search = $fillter['bucket_id'];
            if (! is_array($search)) {
                $search = (array) ($search);
            }
            $result->whereHas('bucketbyjob', function ($query) use ($search) {
                return $query->whereIn('bucket_id', $search);
            });
        }

        if ($fillter['location'] != '') {
            $location = $fillter['location'];
            if ($location != 'all') {
                $result->whereHas('sales', function ($query) use ($location) {
                    $query->join('locations', 'locations.general_code', '=', 'sale_masters.location_code');
                    $query->join('states', 'states.id', '=', 'locations.state_id');

                    return $query->where('states.state_code', '=', $location);
                });
            }
        }

        if ($fillter['search'] != '') {
            $search = $fillter['search'];
            $result->whereHas('sales', function ($query) use ($search) {
                return $query->where('customer_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('date_cancelled', 'LIKE', '%'.$search.'%')
                    ->orWhere('customer_state', 'LIKE', '%'.$search.'%')
                    ->orWhere('customer_city', 'LIKE', '%'.$search.'%')
                    ->orWhere('sales_rep_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('net_epc', 'LIKE', '%'.$search.'%')
                    ->orWhere('pid', 'LIKE', '%'.$search.'%')
                    ->orWhere('job_status', 'LIKE', '%'.$search.'%')
                    ->orWhere('kw', 'LIKE', '%'.$search.'%');
            });
        }
        if ($fillter['list'] != '') {
            $result = $result->paginate($fillter['perpage']);
        } else {
            $result = $result->orderBy('id', $orderBy)->get();
        }

        return $result;
    }
}
