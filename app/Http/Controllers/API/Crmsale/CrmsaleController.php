<?php

namespace App\Http\Controllers\API\Crmsale;

use App\Core\Traits\EditSaleTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Http\Controllers\API\ApiMissingDataController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiMissingDataValidatedRequest;
use App\Models\Bucketbyjob;
use App\Models\Buckets;
use App\Models\BucketSubTask;
use App\Models\BucketSubTaskByJob;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Crmattachments;
use App\Models\CrmComments;
use App\Models\Crmcustomfields;
use App\Models\Crms;
use App\Models\Crmsaleinfo;
use App\Models\CrmSetting;
use App\Models\ImportCategoryDetails;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\Payroll;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class CrmsaleController extends Controller
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

    protected $apiMissingDataController;

    public function __construct(ApiMissingDataController $apiMissingDataController)
    {
        $this->apiMissingDataController = $apiMissingDataController;
    }

    public function addcomment(Request $request): JsonResponse
    {
        $user_id = $request->user_id ?? 0;
        $job_id = $request->job_id ?? 0;
        $bucket_id = $request->bucket_id ?? 0;
        $comments_parent_id = $request->comments_parent_id ?? 0;
        $comments = $request->comments ?? '';
        $attachments = $request->attachments ?? '';
        // echo "<pre>";print_r($request->all());die();
        // echo "<pre>";print_r($attachments);die();
        try {

            if ($job_id > 0 && ! $this->checkjob($job_id)) {
                return response()->json([
                    'ApiName' => 'add-comment-replay',
                    'msg' => 'JOb not exist.',
                    'status' => false,
                ], 400);
            }
            if ($bucket_id > 0 && ! $this->checkbucket($bucket_id)) {
                return response()->json([
                    'ApiName' => 'add-comment-replay',
                    'msg' => 'Bucket not exist.',
                    'status' => false,
                ], 400);
            }
            if ($user_id > 0 && ! $this->checkuser($user_id)) {
                return response()->json([
                    'ApiName' => 'add-comment-replay',
                    'msg' => 'User not exist.',
                    'status' => false,
                ], 400);
            }
            $crmcomment = CrmComments::create([
                'user_id' => $user_id,
                'job_id' => $job_id,
                'bucket_id' => $bucket_id,
                'comments_parent_id' => $comments_parent_id,
                'comments' => $comments,
                'status' => 1,
            ]);
            $id = $crmcomment->id;
            if ($attachments != '') {
                foreach ($attachments as $attachment) {
                    $file = $attachment;
                    // s3 bucket
                    $img_path = time().$file->getClientOriginalName();

                    $img_path = str_replace(' ', '_', $img_path);
                    $ds_path = 'crmdocuments/'.$img_path;
                    $awsPath = config('app.domain_name').'/'.$ds_path;
                    // echo $awsPath;die();
                    s3_upload($awsPath, file_get_contents($file), false);
                    $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.$ds_path);

                    Crmattachments::create([
                        'user_id' => $user_id,
                        'job_id' => $job_id,
                        'bucket_id' => $bucket_id,
                        'comments_id' => $id,
                        'path_id' => 0,
                        'path' => $ds_path,
                        'status' => 1,
                    ]);

                }
            } else {
                // echo "null";
            }

            return response()->json([
                'ApiName' => 'add-comment-replay',
                // 'info'=>$bucket_info,
                'status' => true,
                'message' => 'Comment added successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add-comment-replay',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function editcomment(Request $request): JsonResponse
    {
        $commnet_id = $request->commnet_id ?? 0;
        $comments = $request->comments ?? '';

        try {
            $count = CrmComments::where('id', $commnet_id)->count();
            if ($count > 0) {
                CrmComments::where('id', $commnet_id)->update(['comments' => $comments]);

                return response()->json([
                    'ApiName' => 'editcomment',
                    // 'info'=>$bucket_info,
                    'status' => true,
                    'message' => 'Comment Update successfully',
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'editcomment',
                    'msg' => 'Comment not exist.',
                    'status' => false,
                ], 400);
            }

        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add-comment-replay',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deletecomment(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'commnet_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $commnet_id = $request->commnet_id ?? 0;
        try {
            $count = CrmComments::where('id', $commnet_id)->count();
            if ($count > 0) {
                CrmComments::find($commnet_id)->delete();
                Crmattachments::where(['comments_id' => $commnet_id])->delete();

                return response()->json([
                    'ApiName' => 'deletecomment',
                    // 'info'=>$bucket_info,
                    'status' => true,
                    'message' => 'Comment Delete successfully',
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'deletecomment',
                    'msg' => 'Comment not exist.',
                    'status' => false,
                ], 400);
            }

        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add-comment-replay',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function adddocuments(Request $request): JsonResponse
    {
        $user_id = $request->user_id ?? 0;
        $job_id = $request->job_id ?? 0;
        $bucket_id = $request->bucket_id ?? 0;
        $attachments = $request->attachments ?? '';
        try {

            if ($job_id > 0 && ! $this->checkjob($job_id)) {
                return response()->json([
                    'ApiName' => 'adddocuments',
                    'msg' => 'JOb not exist.',
                    'status' => false,
                ], 400);
            }
            if ($bucket_id > 0 && ! $this->checkbucket($bucket_id)) {
                return response()->json([
                    'ApiName' => 'adddocuments',
                    'msg' => 'Bucket not exist.',
                    'status' => false,
                ], 400);
            }
            if ($user_id > 0 && ! $this->checkuser($user_id)) {
                return response()->json([
                    'ApiName' => 'adddocuments',
                    'msg' => 'User not exist.',
                    'status' => false,
                ], 400);
            }

            if ($attachments != '') {
                foreach ($attachments as $attachment) {
                    $file = $attachment;
                    // s3 bucket
                    $img_path = time().$file->getClientOriginalName();

                    $img_path = str_replace(' ', '_', $img_path);
                    $ds_path = 'crmdocuments/'.$img_path;
                    $awsPath = config('app.domain_name').'/'.$ds_path;
                    // echo $awsPath;die();
                    s3_upload($awsPath, file_get_contents($file), false);
                    $s3_document_url = s3_getTempUrl(config('app.domain_name').'/'.$ds_path);

                    Crmattachments::create([
                        'user_id' => $user_id,
                        'job_id' => $job_id,
                        'bucket_id' => $bucket_id,
                        'comments_id' => 0,
                        'path_id' => 0,
                        'path' => $ds_path,
                        'status' => 1,
                    ]);

                }
            } else {
                // echo "null";
            }

            return response()->json([
                'ApiName' => 'adddocuments',
                // 'info'=>$bucket_info,
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

    public function getcomments(Request $request): JsonResponse
    {
        $user_id = $request->user_id ?? 0;
        $job_id = $request->job_id ?? 0;
        $bucket_id = $request->bucket_id ?? 0;
        // echo "<pre>";print_r($attachments);die();
        try {
            if ($job_id > 0 && ! $this->checkjob($job_id)) {
                return response()->json([
                    'ApiName' => 'getcomments',
                    'msg' => 'JOb not exist.',
                    'status' => false,
                ], 400);
            }
            if ($bucket_id > 0 && ! $this->checkbucket($bucket_id)) {
                return response()->json([
                    'ApiName' => 'getcomments',
                    'msg' => 'Bucket not exist.',
                    'status' => false,
                ], 400);
            }
            if ($user_id > 0 && ! $this->checkuser($user_id)) {
                return response()->json([
                    'ApiName' => 'getcomments',
                    'msg' => 'User not exist.',
                    'status' => false,
                ], 400);
            }
            // $crmcomments = CrmComments('users');
            $crmcomments = CrmComments::with('users', 'attachments');

            if ($job_id > 0 && $bucket_id == 0) {
                $crmcomments = $crmcomments->where('job_id', $job_id);
                $crmcomments = $crmcomments->where('bucket_id', 0);
            }
            if ($job_id > 0 && $bucket_id > 0) {
                $crmcomments = $crmcomments->where('job_id', $job_id);
                $crmcomments = $crmcomments->where('bucket_id', $bucket_id);
            }
            $crmcomments = $crmcomments->get();
            // echo "<pre>";print_r($crmcomments);die();
            $commentdatas = $this->commentset($crmcomments, $bucket_id);

            // echo "<pre>";print_r($crmcomments);die();
            // $commentdatas = [];
            /*foreach ($crmcomments as $key => $crmcomment) {
                $crmc = [];
                $crmc['id'] = $crmcomment->id;
                $crmc['user_id'] = $crmcomment->user_id;
                $crmc['job_id'] = $crmcomment->job_id;
                $crmc['bucket_id'] = $crmcomment->bucket_id;
                $crmc['comments'] = $crmcomment->comments;
                $crmc['user'] = $crmcomment->users->first_name;
                $crmc['image'] = $crmcomment->users->image;
                $commentdatas[] = $crmc;
            }*/
            return response()->json([
                'ApiName' => 'getcomments',
                'data' => $commentdatas,
                'status' => true,
                // 'message' => 'Comment added successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'getcomments',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getdocuments(Request $request): JsonResponse
    {
        $user_id = $request->user_id ?? 0;
        $job_id = $request->job_id ?? 0;
        $bucket_id = $request->bucket_id ?? 0;
        // echo "<pre>";print_r($attachments);die();
        try {
            if ($job_id > 0 && ! $this->checkjob($job_id)) {
                return response()->json([
                    'ApiName' => 'getdocuments',
                    'msg' => 'JOb not exist.',
                    'status' => false,
                ], 400);
            }
            if ($bucket_id > 0 && ! $this->checkbucket($bucket_id)) {
                return response()->json([
                    'ApiName' => 'getdocuments',
                    'msg' => 'Bucket not exist.',
                    'status' => false,
                ], 400);
            }

            // $crmcomments = CrmComments('users');
            $crmcomments = Crmattachments::select('*')->where('status', 1)->with('buckets');

            $crmcomments = $crmcomments->where('comments_id', 0);
            if ($job_id > 0 && $bucket_id == 0) {
                $crmcomments = $crmcomments->where('job_id', $job_id);
                // $crmcomments = $crmcomments->where('bucket_id',0);
            }
            if ($job_id > 0 && $bucket_id > 0) {
                $crmcomments = $crmcomments->where('job_id', $job_id);
                $crmcomments = $crmcomments->where('bucket_id', $bucket_id);
            }
            $crmcomments = $crmcomments->get();
            // echo "<pre>";print_r($crmcomments);die();
            $attachments = $this->documentsset($crmcomments);

            return response()->json([
                'ApiName' => 'getdocuments',
                'data' => $attachments,
                'status' => true,
                // 'message' => 'Comment added successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'getdocuments',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function addcrmjob(Request $request): JsonResponse
    {
        // echo "<pre>";print_r($request->all());die();
        $Validator = Validator::make(
            $request->all(),
            [
                'bucket_id' => 'required',
                'pid' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $validation = false;
        $pid = $request->pid ?? '';
        $bucket_id = $request->bucket_id ?? '';
        if ($bucket_id > 0 && ! $this->checkbucket($bucket_id)) {
            return response()->json([
                'ApiName' => 'addcrmjob',
                'msg' => 'Bucket not exist.',
                'status' => false,
            ], 400);
        }

        $closers = $request->rep_id ?? [];
        $setters = $request->setter_id ?? [];
        if (! empty($closers)) {
            $validation = true;
        }

        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        // PEST Flow STARTS
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($validation) {
                $validator = Validator::make($request->all(), [
                    'pid' => 'required',
                    'customer_name' => 'required',
                    'customer_state' => 'required',
                    'state_id' => 'required',
                    'gross_account_value' => 'required',
                    'approved_date' => 'required',
                    'rep_id' => 'required',
                    'rep_email' => 'required',
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                    'pid' => 'required',
                    'customer_name' => 'required',

                ]);
            }
        }
        $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();
        if ($validation) {
            $request = ApiMissingDataValidatedRequest::create('/api/missing-data', 'POST', $request->all());
            $res = $this->apiMissingDataController->addManualSaleData($request);
            $rs = $res->original;
            if ($rs['status']) {
                $saleMasterinfo = SalesMaster::where('pid', $pid)->first();
                $val = [
                    'prospect_id' => isset($request->prospect_id) ? $request->prospect_id : null,
                    'customer_latitude' => isset($request->customer_latitude) ? $request->customer_latitude : null,
                    'customer_longitude' => isset($request->customer_longitude) ? $request->customer_longitude : null,
                ];
                if ($saleMasterinfo) {
                    SalesMaster::where('pid', $pid)->update($val);
                    LegacyApiNullData::where('pid', $pid)->update($val);
                }
            } else {
                return response()->json($rs, 400);
            }
        } else {
            $apiData = [
                'pid' => $request->pid,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
                'customer_signoff' => date('Y-m-d'),
            ];
            if (! $saleMasterProcess) {
                LegacyApiRowData::create($apiData);
                $apiData['data_source_type'] = 'manual';
                $insertData = SalesMaster::create($apiData);
                LegacyApiNullData::create($apiData);
                $apiData['mark_account_status_id'] = null;
                $apiData['sale_master_id'] = $insertData->id;
                unset($apiData['customer_signoff']);
                SaleMasterProcess::create($apiData);
            }
        }
        Buckets::addcrmsale($pid, $request->bucket_id);

        return response()->json(['status' => true, 'Message' => 'Add Data successfully'], 200);
    }

    public function buckettaskinfo(Request $request): JsonResponse
    {
        $job_id = $request->job_id ?? 0;
        try {
            if ($job_id > 0 && ! $this->checkjob($job_id)) {
                return response()->json([
                    'ApiName' => 'buckettaskinfo',
                    'msg' => 'JOb not exist.',
                    'status' => false,
                ], 400);
            }
            $jobinfos = Crmsaleinfo::with('bucketbyjoballl', 'BucketSubTaskByJob', 'bucketbycomments', 'bucketbydocuments')->where('id', $job_id);

            $jobinfos = $jobinfos->get();
            // echo "<pre>";print_r($jobinfos);die();

            $jobtasks = [];
            foreach ($jobinfos as $jobinfo) {
                $subtaskjob_info = [];
                // bucket subtask
                $subtaskjob_info = $this->subtaskinfoset($jobinfo->BucketSubTaskByJob);
                $buckets = $jobinfo->bucketbyjoballl;
                foreach ($buckets as $bucket) {
                    $buckets_comments = [];
                    $bucketinfo = $bucket->bucketinfo;
                    // bucket comments
                    $buckets_comments = $this->commentset($jobinfo->bucketbycomments, $bucketinfo->id);
                    // bucket documents documentsset
                    $buckets_documents = $this->documentsset($jobinfo->bucketbydocuments, $bucketinfo->id);

                    $bucket_info['bucket_id'] = $bucketinfo->id;
                    $bucket_info['name'] = $bucketinfo->name;
                    $bucket_info['display_order'] = $bucketinfo->display_order;
                    $bucket_info['updated_at'] = $bucketinfo->updated_at;
                    $bucket_subtask = [];
                    foreach ($bucketinfo->bucketsubtasks as $subtask) {
                        if (isset($subtaskjob_info[$subtask->id])) {
                            $bucket_subtask[] = ['subtask_id' => $subtaskjob_info[$subtask->id]->id,
                                'bucket_id' => $subtask->bucket_id,
                                'name' => $subtask->name,
                                'status' => isset($subtaskjob_info[$subtask->id]['status']) ? $subtaskjob_info[$subtask->id]['status'] : '',
                                'date' => isset($subtaskjob_info[$subtask->id]['date']) ? $subtaskjob_info[$subtask->id]['date'] : '',
                            ];
                        }

                    }
                    $bucket_info['subtasklist'] = $bucket_subtask;
                    $bucket_info['comments'] = $buckets_comments;
                    $bucket_info['documents'] = $buckets_documents;

                    $jobtasks[$bucketinfo->id] = $bucket_info;
                }
            }
            $bucketlists = Buckets::orderBy('display_order', 'ASC')->get();
            $jobstaskinfos = [];
            foreach ($bucketlists as $bucketlist) {
                if (isset($jobtasks[$bucketlist->id])) {
                    $jobstaskinfos[] = $jobtasks[$bucketlist->id];
                } else {
                    $bucket_add = [];
                    $bucket_add['bucket_id'] = $bucketlist->id;
                    $bucket_add['name'] = $bucketlist->name;
                    $bucket_add['display_order'] = $bucketlist->display_order;
                    $bucket_add['comments'] = $this->commentset(CrmComments::where('bucket_id', $bucketlist->id)->where('job_id', $job_id)->get());
                    $bucket_add['documents'] = $this->documentsset(Crmattachments::where('bucket_id', $bucketlist->id)->where('job_id', $job_id)->get());
                    $jobstaskinfos[] = $bucket_add;
                }
            }

            return response()->json([
                'ApiName' => 'buckettaskinfo',
                'data' => $jobstaskinfos,
                'status' => true,
                'message' => '',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'buckettaskinfo',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function jobinformation(Request $request): JsonResponse
    {
        $job_id = $request->job_id ?? 0;
        try {
            if ($job_id > 0 && ! $this->checkjob($job_id)) {
                return response()->json([
                    'ApiName' => 'buckettaskinfo',
                    'msg' => 'JOb not exist.',
                    'status' => false,
                ], 400);
            }
            $jobinfos = Crmsaleinfo::select('*')->where('id', $job_id);
            $jobinfos = $jobinfos->get();

            return response()->json([
                'ApiName' => 'jobinformation',
                'data' => $jobinfos,
                'status' => true,
                'message' => '',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'jobinformation',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function listbucketmovejob(Request $request): JsonResponse
    {
        $job_id = $request->job_id ?? 0;
        try {
            if ($job_id > 0 && ! $this->checkjob($job_id)) {
                return response()->json([
                    'ApiName' => 'listbucketmovejob',
                    'msg' => 'JOb not exist.',
                    'status' => false,
                ], 400);
            }
            $jobinfos = Crmsaleinfo::with('bucketbyjoballl')->where('id', $job_id);
            $jobinfos = $jobinfos->get();
            $jobtasks = [];
            foreach ($jobinfos as $jobinfo) {
                $buckets = $jobinfo->bucketbyjoballl;
                foreach ($buckets as $bucket) {
                    $bucketinfo = $bucket->bucketinfo;

                    $bucket_info['bucket_id'] = $bucketinfo->id;
                    $bucket_info['name'] = $bucketinfo->name;
                    $bucket_info['display_order'] = $bucketinfo->display_order;

                    $jobtasks[] = $bucket_info;
                }
            }

            return response()->json([
                'ApiName' => 'listbucketmovejob',
                'data' => $jobtasks,
                'status' => true,
                'message' => '',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'listbucketmovejob',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function activedeactivecrm(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'crm_id' => 'required',
                    'status' => 'required',
                    // 'colour_code' => 'required'
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $crm_id = $request->crm_id;
            $crm_status = $request->status ?? '';

            $updatedata = [];
            if ($crm_status != '') {
                $updatedata['status'] = $crm_status;
            }

            $count = Crms::where('id', $crm_id)->count();
            if ($count > 0) {
                if (! empty($updatedata)) {
                    Crms::where('id', $crm_id)->update($updatedata);
                }
                // Buckets::addplanandsubscription($crm_id);
                $msg = 'successfully Updated';
                $status = true;
                $status_code = 200;
            } else {
                $msg = 'Crm not exists.';
                $status = false;
                $status_code = 400;
            }

            return response()->json([
                'ApiName' => 'activedeactivecrm',
                'msg' => $msg,
                'status' => $status,
            ], $status_code);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'activedeactivecrm',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getplanactive(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'crm_id' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $data = [];
            $crm_id = $request->crm_id;

            $count = Crms::where('id', $crm_id)->count();
            if ($count > 0) {
                $data = CrmSetting::where('crm_id', $crm_id)->where('plan_name', '!=', '')->get();

                $msg = '';
                $status = true;
                $status_code = 200;
            } else {
                $msg = 'Crm not exists.';
                $status = false;
                $status_code = 400;
            }

            return response()->json([
                'ApiName' => 'getplanactive',
                'msg' => $msg,
                'status' => $status,
                'data' => $data,
            ], $status_code);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'getplanactive',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function crmplanaddupgrade(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'crm_id' => 'required',
                    'plan_name' => 'required',
                    'amount_per_job' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $data = [];
            $crm_id = $request->crm_id;
            $plan_name = $request->plan_name ?? '';
            $amount_per_job = $request->amount_per_job ?? '';

            $updatedata = [];
            if ($plan_name != '') {
                $updatedata['plan_name'] = $plan_name;
            }
            if ($amount_per_job != '') {
                $updatedata['amount_per_job'] = $amount_per_job;
            }
            $updatedata['status'] = 1;

            $count = Crms::where('id', $crm_id)->count();
            if ($count > 0) {
                $ccount = CrmSetting::where('crm_id', $crm_id)->count();
                if ($ccount > 0) {
                    if (! empty($updatedata)) {
                        CrmSetting::where('crm_id', $crm_id)->update($updatedata);
                    }
                } else {
                    $updatedata['crm_id'] = $crm_id;
                    // print_r($updatedata);die();
                    $crmsetting = CrmSetting::create($updatedata);
                }
                Buckets::addplanandsubscription($crm_id);
                $data = CrmSetting::where('crm_id', $crm_id)->get();

                $msg = 'successfully Add Updated';
                $status = true;
                $status_code = 200;
            } else {
                $msg = 'Crm not exists.';
                $status = false;
                $status_code = 400;
            }

            return response()->json([
                'ApiName' => 'crmplanaddupgrade',
                'msg' => $msg,
                'status' => $status,
                'data' => $data,
            ], $status_code);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'crmplanaddupgrade',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deleteattachments(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'attachment_id' => 'required',
                    // 'colour_code' => 'required'
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            DB::beginTransaction();
            $attachment_id = $request->attachment_id ?? 0;
            $type = $request->type ?? 'delete'; // hide_show , title , display_order
            $updater_id = Auth::user()->id;
            $status = true;
            $status_code = 200;
            $count = Crmattachments::where('id', $attachment_id)->count();
            if ($count > 0) {
                $getattachment = Crmattachments::where('id', $attachment_id)->first();
                if ($getattachment->comments_id > 0) {
                    $this->checkcommentexistanddelete($getattachment->comments_id);
                }
                // die();

                Crmattachments::find($attachment_id)->delete();
                $msg = 'Attachment deleted successfully';

                DB::commit();

                return response()->json([
                    'ApiName' => 'deleteattachments',
                    'msg' => $msg,
                    'status' => $status,
                ], $status_code);

            } else {
                return response()->json([
                    'ApiName' => 'deleteattachments',
                    'msg' => 'Attachment not exist.',
                    'status' => false,
                ], 400);
            }

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'deleteattachments',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function addupdatecustomefield(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'custom_fields' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $data = [];
            $custom_fields = $request->custom_fields;

            foreach ($custom_fields as $custom_field) {
                if (isset($custom_field['id']) && $custom_field['id'] != '' && $custom_field['id'] > 0) {
                    $data = [
                        'name' => $custom_field['name'],
                        'type' => $custom_field['type'],
                        'visiblecustomer' => $custom_field['visiblecustomer'],
                        'status' => 1,

                    ];
                    Crmcustomfields::where('id', $custom_field['id'])->update($data);
                } else {
                    $data = [
                        'name' => $custom_field['name'],
                        'type' => $custom_field['type'],
                        'visiblecustomer' => $custom_field['visiblecustomer'],
                        'status' => 1,

                    ];
                    Crmcustomfields::create($data);
                }
            }
            $custom_fields = Crmcustomfields::get();

            $id = [];
            foreach ($custom_fields as $custom_field) {
                if ($custom_field->type == 'date') {
                    $field = ImportCategoryDetails::where(['category_id' => '1', 'name' => $custom_field['name'], 'is_custom' => 1])->first();
                    if ($field) {
                        $id[] = $field->id;
                    } else {
                        $last = ImportCategoryDetails::where('category_id', '1')->orderByRaw('CAST(sequence AS UNSIGNED) DESC')->first();
                        $sequence = 1;
                        if ($last) {
                            $sequence = $last->sequence + 1;
                        }
                        $category = ImportCategoryDetails::create([
                            'category_id' => 1,
                            'name' => $custom_field['name'],
                            'label' => $custom_field['name'],
                            'sequence' => $sequence,
                            'is_mandatory' => 0,
                            'is_custom' => 1,
                            'section_name' => 'Dates',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $id[] = $category->id;
                    }
                }
            }
            ImportCategoryDetails::whereNotIn('id', $id)->where('is_custom', 1)->delete();

            return response()->json([
                'ApiName' => 'addupdatecustomefield',
                'data' => $custom_fields,
                'msg' => 'Successfully add and update',
                'status' => true,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'addupdatecustomefield',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deletecustomfield(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'custom_filde_id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $custom_filde_id = $request->custom_filde_id ?? 0;
        try {
            $count = Crmcustomfields::where('id', $custom_filde_id)->count();
            if ($count > 0) {
                $custom = Crmcustomfields::find($custom_filde_id);
                ImportCategoryDetails::where(['category_id' => '1', 'name' => $custom['name']])->delete();
                $fields = ImportCategoryDetails::where(['category_id' => '1'])->get();
                foreach ($fields as $key => $field) {
                    $field->sequence = ($key + 1);
                    $field->save();
                }
                $custom->delete();
                $custom_fields = Crmcustomfields::get();

                return response()->json([
                    'ApiName' => 'deletecustomfield',
                    // 'info'=>$bucket_info,
                    'data' => $custom_fields,
                    'status' => true,
                    'message' => 'customfield Delete successfully',
                ]);
            } else {
                return response()->json([
                    'ApiName' => 'deletecustomfield',
                    'msg' => 'customfield not exist.',
                    'status' => false,
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add-comment-replay',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getcustomefields(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    // 'custom_fields' => 'required'
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $custom_fields = Crmcustomfields::get();

            return response()->json([
                'ApiName' => 'getcustomefields',
                'data' => $custom_fields,
                // 'msg' => 'Successfully add and update',
                'status' => true,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'getcustomefields',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function saveupdatecustomfildjob(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'custom_fields' => 'required',
                    'pid' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $data = [];
            $custom_fields = $request->custom_fields;
            $pid = $request->pid ?? 0;

            if ($pid > 0 && ! Crmsaleinfo::checksalejob($pid)) {
                return response()->json([
                    'ApiName' => 'saveupdatecustomfildjob',
                    'msg' => 'Job not exist.',
                    'status' => false,
                ], 400);
            } else {
                $data = isset($custom_fields) ? json_encode($custom_fields) : json_encode([]);
                Crmsaleinfo::where('pid', $pid)->update(['custom_fields' => $data]);

                return response()->json([
                    'ApiName' => 'saveupdatecustomfildjob',
                    // 'data'  =>$custom_fields,
                    'msg' => 'Successfully add and update',
                    'status' => true,
                ], 200);
            }

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'addupdatecustomefield',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    protected function checkcommentexistanddelete($comment_id)
    {
        $count = CrmComments::where('id', $comment_id)->where('comments', '=', '')->count();
        if ($count > 0) {
            $pcount = Crmattachments::where('comments_id', $comment_id)->count();
            if ($pcount == 1) {
                CrmComments::find($comment_id)->delete();
            }
        }
    }

    public function subroutine_process($pid)
    {
        (new ApiMissingDataController)->subroutine_process($pid);
    }

    protected function subtaskinfoset($datas)
    {
        $pdata = [];
        foreach ($datas as $data) {
            $pdata[$data->bucket_sutask_id] = $data;
        }

        return $pdata;
    }

    protected function documentsset($datas, $bucket_id = 0)
    {
        if ($bucket_id > 0) {
            $datas = $datas->where('bucket_id', $bucket_id);
        }
        // print_r($datas);die();
        $pdata = [];
        foreach ($datas as $attachment) {
            $pdata[] = $this->documnetinfo($attachment);
        }

        // print_r($pdata);die();
        // die();
        return $pdata;
    }

    // Optional: Convert bytes to a human-readable format
    private function humanReadableFileSize($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $base = log($size, 1024);
        $suffix = $units[floor($base)];
        $formattedSize = round(pow(1024, $base - floor($base)), $precision);

        return $formattedSize.' '.$suffix;
    }

    protected function commentset($datas, $bucket_id = 0)
    {
        $maincomments = $datas->where('comments_parent_id', 0);

        if ($bucket_id > 0) {
            $maincomments = $maincomments->where('bucket_id', $bucket_id);
        }
        //  echo "<pre>";print_r($maincomments);die();

        $pdata = [];
        foreach ($maincomments as $maincomment) {
            $comm_attachments = [];
            if (! empty($maincomment->attachments)) {
                foreach ($maincomment->attachments as $attachment) {
                    // $comm_attachments[] = $pdata[] = $this->documnetinfo($attachment);
                    $comm_attachments[] = $this->documnetinfo($attachment);
                }
            }
            $maincommentuser = $maincomment->users;
            $maincomment->user = $maincommentuser->first_name.' '.$maincommentuser->last_name;
            $maincomment->position_id = $maincommentuser->position_id;
            $maincomment->sub_position_id = $maincommentuser->sub_position_id;

            $maincomment->image = $maincommentuser->image;
            $maincomment->attachment = $comm_attachments;
            $maincomment->day_time = Crmsaleinfo::getdaytime($maincomment->created_at);
            unset($maincomment->users);
            unset($maincomment->attachments);
            $maincomment->replays = $this->replaycomment($datas, $maincomment->id, $bucket_id);
            $pdata[] = $maincomment;
        }

        // die();
        return $pdata;
    }

    protected function documnetinfo($attachment)
    {
        $bucket_name = '';
        if ($attachment->buckets) {
            $bucket_name = $attachment->buckets->name;
        }
        $doc_name = basename($attachment->path);
        $extension = pathinfo($doc_name, PATHINFO_EXTENSION);
        $path = s3_getTempUrl(config('app.domain_name').'/'.$attachment->path);
        $size = 0;
        $size = $this->getFileSize($path);

        $humanReadableSize = $this->humanReadableFileSize($size);
        $data = ['id' => $attachment->id, 'doc_name' => $doc_name, 'extension' => $extension, 'size' => $size, 'readablesize' => $humanReadableSize, 'created_at' => $attachment->created_at, 'updated_at' => $attachment->updated_at, 'path' => $path, 'bucket_name' => $bucket_name];

        return $data;
    }

    public function getFileSize($preSignedUrl)
    {
        try {
            // echo $preSignedUrl;die();
            $response = Http::head($preSignedUrl);
            /*$cacertPath = storage_path('ca/cacert.pem');
            //echo $cacertPath;die();
            $response = Http::withOptions([
                'verify' => $cacertPath,
            ])->head($preSignedUrl);
                echo "<pre>";print_r($response);die();*/

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
            // throw $th;
        }

    }

    protected function replaycomment($datas, $id, $bucket_id = 0)
    {
        $maincomments = $datas->where('comments_parent_id', $id);
        if ($bucket_id > 0) {
            $maincomments = $maincomments->where('bucket_id', $bucket_id);
        }
        $pdata = [];
        foreach ($maincomments as $maincomment) {
            $comm_attachments = [];
            if (! empty($maincomment->attachments)) {
                foreach ($maincomment->attachments as $attachment) {
                    // $comm_attachments[] = array('id'=>$attachment->id,'path'=>s3_getTempUrl(config('app.domain_name').'/'.$attachment->path));
                    $comm_attachments[] = $this->documnetinfo($attachment);

                }
            }
            $maincommentuser = $maincomment->users;
            $maincomment->user = $maincommentuser->first_name.' '.$maincommentuser->last_name;
            $maincomment->image = $maincommentuser->image;
            $maincomment->attachment = $comm_attachments;
            $maincomment->day_time = Crmsaleinfo::getdaytime($maincomment->created_at);
            unset($maincomment->users);
            unset($maincomment->attachments);
            $pdata[] = $maincomment;
        }

        return $pdata;
    }

    /*protected function addcrmsale($pid,$request){
        $bucket_id = $request->bucket_id??0;
        $CrmsaleinfoData = Crmsaleinfo::where('pid', $pid)->first();
        if (empty($CrmsaleinfoData)) {
            $data =[
                'pid' => $pid,
                'created_id'  => Auth()->user()->id
            ];
            $insertData = Crmsaleinfo::create($data);
            $job_id = $insertData->id;
            $pdata =[
                'job_id' => $job_id,
                'bucket_id'  => $bucket_id,
                'active'  => 1
            ];
            $insertData = Bucketbyjob::create($pdata);
            $getsubtasks = BucketSubTask::where('bucket_id', $bucket_id)->get();
            foreach($getsubtasks as $getsubtask){
                $pdata =[
                    'bucket_sutask_id' => $getsubtask->id,
                    'job_id'  => $job_id
                    ];
                $this->checksubtaskaddandupdate($pdata);
            }
        }
    }
    protected function checksubtaskaddandupdate($data){
        $getdata_buckets_subtask = BucketSubTaskByJob::where('bucket_sutask_id', $data['bucket_sutask_id'])->where('job_id', $data['job_id'])->get();
        if($getdata_buckets_subtask->isEmpty()){
            BucketSubTaskByJob::create($data);
        }
    }*/
    protected function salemasterprocessdo($saleMasterProcess, $request, $pid)
    {
        $request['data_source_type'] = 'manual';
        $error = [];
        $this->executedSalesData($request);
        // update sale with m1-m2 date
        $saleMasterData = SalesMaster::where('pid', $pid)->first();

        if (! empty($saleMasterData->m1_date) && empty($request->m1_date)) {
            $m1comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 3])->first();
            if ($m1comm) {
                return $error[] = 'This sale payroll is executed';
            }
            $this->m1dateSalesData($pid);
        }
        if (! empty($saleMasterData->m1_date) && ! empty($request->m1_date) && $saleMasterData->m1_date != $request->m1_date) {
            $this->m1datePayrollData($pid, $request->m1_date);
        }

        if (! empty($saleMasterData->m2_date) && empty($request->m2_date)) {
            $m2comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3])->first();
            if ($m2comm) {
                // return response()->json(['status' => false, 'Message' => 'This sale payroll is executed'], 200);
                return $error[] = 'This sale payroll is executed';
            }
            $this->m2dateSalesData($pid, $saleMasterData->m2_date);
        }
        if (! empty($saleMasterData->m2_date) && ! empty($request->m2_date) && $saleMasterData->m2_date != $request->m2_date) {
            $this->m2datePayrollData($pid, $request->m2_date);
        }
        // end update sale with m1-m2 date

        if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
            $this->updateSalesData($saleMasterProcess->closer1_id, 2, $pid);
            // changes 12-12-2023
            $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasterProcess->closer1_id, 'type' => 'commission'])->first();
            if (! $clawbackSett) {
                $this->clawbackSalesData($saleMasterProcess->closer1_id, $checked);
            }
            // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER CLOSER SELECTED AND CLAWBACK HASN'T PAID YET
            if ($clawbackSett) {
                ClawbackSettlement::where(['user_id' => $closers[0], 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                ClawbackSettlement::where(['sale_user_id' => $closers[0], 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
            }
            // end changes 12-12-2023

            $saleMasterProcess->setter1_m1_paid_status = null;
            $saleMasterProcess->closer1_m1 = 0;
            $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : null;
            $saleMasterProcess->save();
        }

        if (isset($saleMasterProcess->setter1_id) && isset($setters[0]) && $setters[0] != $saleMasterProcess->setter1_id) {
            $this->updateSalesData($saleMasterProcess->setter1_id, 3, $pid);
            // changes 12-12-2023
            $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $clawbackSettl = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasterProcess->setter1_id, 'type' => 'commission'])->first();
            if (! $clawbackSettl) {
                $this->clawbackSalesData($saleMasterProcess->setter1_id, $checked);
            }
            $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $setters[0], 'is_displayed' => '1', 'status' => '1'])->first();
            // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER SETTER SELECTED AND CLAWBACK HASN'T PAID YET
            if ($clawbackSett) {
                ClawbackSettlement::where(['user_id' => $setters[0], 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                ClawbackSettlement::where(['sale_user_id' => $setters[0], 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
            }
            // End changes 12-12-2023

            $saleMasterProcess->setter1_m1_paid_status = null;
            $saleMasterProcess->setter1_m1 = 0;
            $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : null;
            $saleMasterProcess->save();
        }

        return $error;
    }

    protected function salemasterprocesspestdo($saleMasterProcess, $request, $pid)
    {
        // $request['data_source_type'] = 'manual';
        $error = [];

        $saleMasterData = SalesMaster::where('pid', $pid)->first();
        // M1 IS PAID & M1 DATE GETS REMOVED
        if (! empty($saleMasterData->m1_date) && empty($request->m1_date)) {
            $m1Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 3, 'is_displayed' => '1'])->first();
            if ($m1Comm) {
                // return response()->json(['status' => false, 'message' => 'Apologies, The M1 date cannot be remove because the upfront amount has already been paid'], 400);
                return $error[] = 'Apologies, The M1 date cannot be remove because the upfront amount has already been paid';
            }
            $this->m1dateSalesData($pid);
        }
        // M1 DATE GETS CHANGED
        if (! empty($saleMasterData->m1_date) && ! empty($request->m1_date) && $saleMasterData->m1_date != $request->m1_date) {
            $m1Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 3, 'is_displayed' => '1'])->first();
            if ($m1Comm) {
                // return response()->json(['status' => false, 'message' => 'Apologies, The M1 date cannot be changed because the upfront amount has already been paid'], 400);
                return $error[] = 'Apologies, The M1 date cannot be changed because the upfront amount has already been paid';
            }
            $this->m1datePayrollData($pid, $request->m1_date);
        }

        // M2 IS PAID & M2 DATE GETS REMOVED
        if (! empty($saleMasterData->m2_date) && empty($request->m2_date)) {
            $m2Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
            if ($m2Comm) {
                // return response()->json(['status' => false, 'Message' => 'Apologies, The M2 date cannot be remove because the commission amount has already been paid'], 400);
                return $error[] = 'Apologies, The M2 date cannot be remove because the commission amount has already been paid';
            }
            $this->m2dateSalesData($pid, $saleMasterData->m2_date);
        }
        // M2 DATE GETS CHANGED
        if (! empty($saleMasterData->m2_date) && ! empty($request->m2_date) && $saleMasterData->m2_date != $request->m2_date) {
            $m2Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
            if ($m2Comm) {
                // return response()->json(['status' => false, 'message' => 'Apologies, The M2 date cannot be changed because the commission amount has already been paid'], 400);
                return $error[] = 'Apologies, The M2 date cannot be changed because the commission amount has already been paid';
            }
            $this->m2datePayrollData($pid, $request->m2_date);
        }
        // CLOSER GETS CHANGE & M2 IS PAID
        if (! empty($saleMasterData->closer1_id) && ! empty($request->rep_id[0]) && $saleMasterData->closer1_id != $request->rep_id[0]) {
            $m2Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
            if ($m2Comm) {
                // return response()->json(['status' => false, 'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid'], 400);
                return $error[] = 'Apologies, The closer cannot be changed because the commission amount has already been paid';
            }
            // $this->m2datePayrollData($pid, $request->m2_date);
        }

        // CLOSER 1 GOT CHANGE
        if (isset($saleMasterProcess->closer1_id) && isset($closers[0]) && $closers[0] != $saleMasterProcess->closer1_id) {
            $m2Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
            if ($m2Comm) {
                // return response()->json(['status' => false, 'message' => 'Apologies, The closer cannot be changed because the commission amount has already been paid'], 400);
                return $error[] = 'Apologies, The closer cannot be changed because the commission amount has already been paid';
            }

            $this->updateSalesData($saleMasterProcess->closer1_id, 2, $pid);
            $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasterProcess->closer1_id, 'type' => 'commission', 'is_displayed' => '1'])->first();
            if (! $clawbackSett) {
                $this->clawbackSalesData($saleMasterProcess->closer1_id, $checked);
            }
            $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closers[0], 'is_displayed' => '1', 'status' => '1'])->first();
            // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER CLOSER SELECTED AND CLAWBACK HASN'T PAID YET
            if ($clawbackSett) {
                ClawbackSettlement::where(['user_id' => $closers[0], 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                ClawbackSettlement::where(['sale_user_id' => $closers[0], 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
            }

            $saleMasterProcess->closer1_m1 = 0;
            $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : null;
            $saleMasterProcess->save();
        }

        // CLOSER 2 GOT CHANGE
        if (isset($saleMasterProcess->closer2_id) && isset($closers[1]) && $closers[1] != $saleMasterProcess->closer2_id) {
            $m2Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3, 'is_displayed' => '1'])->first();
            if ($m2Comm) {
                // return response()->json(['status' => false, 'message' => 'Apologies, The closer 2 cannot be changed because the commission amount has already been paid'], 400);
                return $error[] = 'Apologies, The closer 2 cannot be changed because the commission amount has already been paid';
            }

            $this->updateSalesData($saleMasterProcess->closer2_id, 2, $pid);
            $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $saleMasterProcess->closer2_id, 'type' => 'commission', 'is_displayed' => '1'])->first();
            if (! $clawbackSett) {
                $this->clawbackSalesData($saleMasterProcess->closer2_id, $checked);
            }
            $clawbackSett = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $closers[1], 'is_displayed' => '1', 'status' => '1'])->first();
            // REMOVE UNPAID CLAWBACK & OVERRIDES WHEN OLDER CLOSER SELECTED AND CLAWBACK HASN'T PAID YET
            if ($clawbackSett) {
                ClawbackSettlement::where(['user_id' => $closers[1], 'type' => 'commission', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
                ClawbackSettlement::where(['sale_user_id' => $closers[1], 'type' => 'overrides', 'status' => '1', 'is_displayed' => '1', 'pid' => $checked->pid])->delete();
            }

            $saleMasterProcess->closer2_m1 = 0;
            $saleMasterProcess->job_status = isset($request->job_status) ? $request->job_status : null;
            $saleMasterProcess->save();
        }

        return $error;
    }

    protected function somevalidation($request)
    {
        $closers = $request->rep_id ?? [];
        $setters = $request->setter_id ?? [];
        $error = [];
        if (empty(array_filter($closers)) || empty(array_filter($setters))) {
            $error[] = 'Select closer or setter field can not be blank';
            // return response()->json(['status' => false, 'Message' => 'Select closer or setter field can not be blank'], 400);
        }

        return $error;
    }

    protected function checkjob($id)
    {
        $jobcheck = Crmsaleinfo::where('id', $id)->first();
        if (! empty($jobcheck)) {
            return $jobcheck;
        } else {
            return false;
        }
        // print_r($jobcheck);die();

    }

    protected function checkbucket($id)
    {
        $bucketcheck = Buckets::where('id', $id)->first();
        if (! empty($bucketcheck)) {
            return $bucketcheck;
        } else {
            return false;
        }
    }

    protected function checkuser($id)
    {
        $usercheck = User::where('id', $id)->first();
        if (! empty($usercheck)) {
            return $usercheck;
        } else {
            return false;
        }
    }
}
