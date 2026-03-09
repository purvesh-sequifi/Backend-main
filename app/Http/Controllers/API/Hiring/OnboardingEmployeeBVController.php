<?php

namespace App\Http\Controllers\API\Hiring;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\HubspotTrait;
use App\Core\Traits\JobNimbusTrait;
use App\Core\Traits\PermissionCheckTrait;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\DocumentFiles;
use App\Models\Documents;
use App\Models\DomainSetting;
use App\Models\EventCalendar;
use App\Models\Notification;
use App\Models\OnboardingAdditionalEmails;
use App\Models\OnboardingEmployees;
use App\Models\SClearanceConfiguration;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SequiDocsEmailSettings;
use App\Models\SequiDocsTemplate;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use App\Traits\PushNotificationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mail;
use Pdf;

class OnboardingEmployeeBVController extends Controller
{
    use EmailNotificationTrait;
    use EvereeTrait;
    use HubspotTrait;
    use JobNimbusTrait;
    use PermissionCheckTrait;
    use PushNotificationTrait;

    protected $url;

    public function __construct(OnboardingEmployees $OnboardingEmployees, UrlGenerator $url)
    {
        $this->OnboardingEmployees = $OnboardingEmployees;
        $this->url = $url;

        // $routeName = Route::currentRouteName();
        // $roleId = auth('api')->user()->position_id;
        //  //dd($routeName); die();
        // $result = $this->checkPermission($roleId, '3', $routeName);

        // if ($result == false)
        // {
        //    $response = [
        //         'status' => false,
        //         'message' => 'this module not access permission.',
        //     ];
        //     print_r(json_encode($response));die();
        // }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (isset($request->perpage) && $request->perpage != '') {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $officeId = auth()->user()->office_id;
        $user = $this->OnboardingEmployees->newQuery();
        $status_id_filter = '';

        $other_status_filter = isset($request->other_status_filter) ? $request->other_status_filter : '';
        $hire_now_filter = '';
        $offer_letter_accepted_filter = '';

        $user->with('departmentDetail', 'positionDetail', 'managerDetail', 'statusDetail', 'recruiter', 'additionalDetail', 'state', 'city', 'teamsDetail', 'subpositionDetail', 'office');
        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%');
            })
                ->orWhereHas('OnboardingAdditionalEmails', function ($query) use ($request) {
                    $query->where('email', 'like', '%'.$request->input('filter').'%');
                });
        }

        if ($request->has('status_filter') && ! empty($request->input('status_filter')) && $other_status_filter == '') {

            if ($request->input('status_filter') == 13) {
                $user->where(function ($query) {
                    $query->where('status_id', 1);
                });
                $offer_letter_accepted_filter = 1;
            } elseif ($request->input('status_filter') == 1) {
                $user->where(function ($query) {
                    $query->where('status_id', 1);
                });
                $hire_now_filter = 1;
            } else {
                $user->where(function ($query) use ($request) {
                    $query->where('status_id', $request->input('status_filter'));
                });

            }
            $status_id_filter = $request->input('status_filter');
        }

        if ($other_status_filter == 1) {
            $user->where(function ($query) {
                $query->where('status_id', 7);
            });
        }

        if ($other_status_filter == 2) {
            $user->where(function ($query) {
                $query->where('status_id', 1);
            });
        }

        if ($request->has('position_filter') && ! empty($request->input('position_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('sub_position_id', $request->input('position_filter'));
            });
        }

        if ($request->has('manager_filter') && ! empty($request->input('manager_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('manager_id', $request->input('manager_filter'));
            });

        }

        if ($request->has('office_id') && ! empty($request->input('office_id'))) {
            if ($request->input('office_id') !== 'all') {

                $data = $user->where('office_id', $request->input('office_id'));
            }

        } else {
            $data = $user->where('office_id', $officeId);
        }

        // New Logic for all doc signature.
        // $user_data = $user->with('OnboardingEmployeesDocuments','OnboardingAdditionalEmails')
        // ->orderBy('id','DESC')->get();

        $user_data = $user->with('OnboardingEmployeesDocuments', 'OnboardingAdditionalEmails')
            ->orderBy('id', 'DESC')->where('status_id', '!=', 14)->get();

        // return $user_data;

        $final_data = [];
        foreach ($user_data as $user_key => $user_row) {

            $onboarding_employees_documents = [];
            // Logic for all docs sign or not
            $onboarding_employees_documents = $user_row->OnboardingEmployeesDocuments;
            $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
            $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
            $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];

            // Hire button show hide as per new tables of sequidoc
            $onboarding_employees_new_documents = $user_row->newOnboardingEmployeesDocuments;
            $onboarding_employees_new_document_status = OnboardingEmployees::onboarding_employees_new_document_status($onboarding_employees_new_documents);
            $is_all_new_doc_sign = $onboarding_employees_new_document_status['is_all_new_doc_sign'];

            $data = [
                'id' => $user_row->id,
                'is_all_doc_sign' => $is_all_doc_sign,
                'is_all_new_doc_sign' => $is_all_new_doc_sign,
                'other_doc_status' => $other_doc_status,
                'onboarding_employees_documents' => $onboarding_employees_documents,
                'onboarding_employees_new_documents' => $onboarding_employees_new_documents,
                'first_name' => isset($user_row->first_name) ? $user_row->first_name : null,
                'last_name' => isset($user_row->last_name) ? $user_row->last_name : null,
                'mobile_no' => $user_row->mobile_no,
                'state_id' => $user_row->state_id,
                'state_name' => isset($user_row->state->name) ? $user_row->state->name : null,
                'department_id' => $user_row->department_id,
                'team_id' => isset($user_row->teamsDetail->id) ? $user_row->teamsDetail->id : null,
                'team_name' => isset($user_row->teamsDetail->team_name) ? $user_row->teamsDetail->team_name : null,
                'department_name' => isset($user_row->departmentDetail->name) ? $user_row->departmentDetail->name : null,
                'manager_id' => $user_row->manager_id,
                'manager_name' => isset($user_row->managerDetail->id) ? $user_row->managerDetail->name : null,
                'office_id' => isset($user_row->office_id) ? $user_row->office_id : null,
                'office_name' => isset($user_row->office->office_name) ? $user_row->office->office_name : null,
                'status_id' => $user_row->status_id,
                'hiring_type' => $user_row->hiring_type,
                'status_name' => isset($user_row->statusDetail->status) ? $user_row->statusDetail->status : null,
                'position_id' => $user_row->position_id,
                'position_name' => isset($user_row->positionDetail->position_name) ? $user_row->positionDetail->position_name : null,
                'sub_position_id' => isset($user_row->sub_position_id) ? $user_row->sub_position_id : null,
                'sub_position_name' => isset($user_row->subpositionDetail->position_name) ? $user_row->subpositionDetail->position_name : null,
                'progress' => '1/18',
                'onboardProcess' => ! empty($user_row->mainUserData->onboardProcess) ? $user_row->mainUserData->onboardProcess : 0,
                'last_update' => Carbon::parse($user_row->updated_at)->format('m/d/Y'),
                'work_email' => $user_row->OnboardingAdditionalEmails,
                'is_background_verificaton' => $user_row->is_background_verificaton,
            ];

            $data['screening_request_applicant_id'] = '';
            $data['is_report_generated'] = '';
            $data['background_verification_status'] = '';

            if ($user_row->is_background_verificaton == 1) {
                $configurationDetails = SClearanceConfiguration::where(['position_id' => $user_row->position_id, 'hiring_status' => 1, 'is_approval_required' => 1])->first();
                if (! empty($configurationDetails)) {
                    $reportData = SClearanceTurnScreeningRequestList::where(['user_type_id' => $user_row->id, 'user_type' => 'Onboarding', 'is_report_generated' => 1])->first();
                    if (! empty($reportData)) {
                        $data['turn_id'] = $reportData->turn_id;
                        $data['worker_id'] = $reportData->worker_id;
                        $data['is_report_generated'] = $reportData->is_report_generated;
                        $data['background_verification_status'] = $reportData->status;
                        $data['approved_declined_by'] = $reportData->approved_declined_by;
                    }
                }
            }

            $push_data = true;

            if ($other_status_filter != '') {
                $push_data = false;
                if ($other_status_filter == 1) {
                    if ($data['onboardProcess'] == 1) {
                        $push_data = true;
                    }
                } elseif ($other_status_filter == 2) {
                    // if($data['is_all_doc_sign'] == false && in_array($data['status_id'], [1,2,4,5,6,12,13])){
                    //     $push_data = true;
                    // }

                    if ($data['other_doc_status']['w9'] == 0 || $data['other_doc_status']['backgroundVerification'] == 0) {
                        $push_data = true;
                    }
                }
            }

            if ($hire_now_filter == 1) {
                $push_data = false;
                if (($data['other_doc_status']['w9'] == 1 || $data['other_doc_status']['w9'] == 2) && ($data['other_doc_status']['backgroundVerification'] == 1 || $data['other_doc_status']['backgroundVerification'] == 2)) {
                    $push_data = true;
                }
            } elseif ($offer_letter_accepted_filter == 1) {
                $push_data = false;
                if (($data['other_doc_status']['w9'] == 0) || $data['other_doc_status']['backgroundVerification'] == 0) {
                    $push_data = true;
                }
            }

            if ($push_data) {
                $final_data[] = $data;
            }

        }

        if ($request->has('sort') && $request->input('sort') == 'last_update') {
            $val = $request->input('sort_val');
            // $data = json_decode($data);
            if ($request->input('sort_val') == 'desc') {
                array_multisort(array_column($final_data, 'last_update'), SORT_DESC, $final_data);
            } else {
                array_multisort(array_column($final_data, 'last_update'), SORT_ASC, $final_data);
            }
        }
        // if($request->has('sort') &&  $request->input('sort') =='start_date')
        // {
        //     $val = $request->input('sort_val');
        //     $data = json_decode($data);
        //     if($request->input('sort_val')=='desc')
        //     {
        //         array_multisort(array_column($data, 'start_date'),SORT_DESC, $data);
        //     } else{
        //         array_multisort(array_column($data, 'start_date'),SORT_ASC, $data);
        //     }
        // }

        $data = paginate($final_data, $perpage);

        return response()->json([
            'ApiName' => 'onboarding_employee_list ',
            'status' => true,
            'message' => 'Successfully.',
            'offer_letter_accepted_filter' => $offer_letter_accepted_filter,
            'hire_now_filter' => $hire_now_filter,
            'data' => $data,
        ], 200);
    }

    public function EmployeeAgreement(Request $request): JsonResponse
    {
        $data4 = OnboardingEmployees::find($request->user_id);
        if (! $data4 == null) {
            $data4->probation_period = isset($request->employee_agreement['probation_period']) ? $request->employee_agreement['probation_period'] : null;
            $data4->hiring_bonus_amount = isset($request->employee_agreement['hiring_bonus_amount']) ? $request->employee_agreement['hiring_bonus_amount'] : null;
            $data4->date_to_be_paid = isset($request->employee_agreement['date_to_be_paid']) ? $request->employee_agreement['date_to_be_paid'] : null;
            $data4->period_of_agreement_start_date = isset($request->employee_agreement['period_of_agreement']) ? $request->employee_agreement['period_of_agreement'] : null;
            $data4->end_date = isset($request->employee_agreement['end_date']) ? $request->employee_agreement['end_date'] : null;
            $data4->offer_include_bonus = isset($request->employee_agreement['offer_include_bonus']) ? $request->employee_agreement['offer_include_bonus'] : null;
            $data4->offer_expiry_date = isset($request->employee_agreement['offer_expiry_date']) ? $request->employee_agreement['offer_expiry_date'] : null;
            $data4->is_background_verificaton = (isset($request->employee_agreement['is_background_verificaton']) && $request->employee_agreement['is_background_verificaton'] == true) ? 1 : 0;
            $data4->save();
            $description = 'Probation Period =>'.$data4->probation_period.','.'Hiring Bonus Amount =>'.$data4->hiring_bonus_amount.', '.'Date to be paid =>'.$data4->date_to_be_paid.', '.'Period of agreement =>'.$data4->period_of_agreement.', '.'End date =>'.$data4->end_date.','.'Offer expiry date =>'.$data4->offer_expiry_date.','.'User Id =>'.$data4->user_id;
            $page = 'Employee hiring';
            $action = 'Employee create';
            user_activity_log($page, $action, $description);

            // $data6 =  OnboardingEmployees::find($data4->id);
            // $data6->status_id = 4;
            // $data6->save();

            $ViewData = OnboardingEmployees::Select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'state_id')->where('id', $request->user_id)->first();
            EventCalendar::where('user_id', $ViewData->id)->delete();
            $data = EventCalendar::create(
                [
                    'event_date' => $request->employee_agreement['period_of_agreement'],
                    'type' => 'Hired',
                    'state_id' => $ViewData->state_id,
                    'user_id' => $ViewData->id,
                    'event_name' => 'Joining',
                    'description' => null,
                ]
            );
            $pdf = PDF::loadView('mail.pdf', [
                'title' => $ViewData->first_name.' '.$ViewData->last_name,
                'email' => $ViewData->email,
                'mobile_no' => $ViewData->mobile_no,
            ]);
            
            // Upload to S3 instead of local file system
            $fileName = "{$ViewData->first_name}-{$ViewData->last_name}_offer_letter.pdf";
            $filePath = config('app.domain_name').'/template/'.$fileName;
            $stored_bucket = 'private';
            
            $s3_return = s3_upload($filePath, $pdf->output(), false, $stored_bucket);
            
            if (isset($s3_return['status']) && $s3_return['status'] == true) {
                $pdfPath = $s3_return['ObjectURL'];
                
                // $ViewData->status_id = 4;
                // $ViewData->save();
                return response()->json([
                    'ApiName' => 'add-onboarding_employee_override',
                    'status' => true,
                    'message' => 'add Successfully.',
                    'pdf' => $pdfPath,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'add-onboarding_employee_override',
                    'status' => false,
                    'message' => 'Failed to upload PDF to S3.',
                ], 500);
            }
        } else {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_override',
                'status' => false,
                'message' => 'User Not Found',
                // 'data' => $data,
            ], 400);
        }
    }

    public function sendEmailOnBoardingEmployee(Request $request, $id)
    {
        $request_type = isset($request->type) && $request->type == 'resend' ? 'Resend' : 'Send';
        $send_documents_to_user = isset($request->documents) && $request->documents != 'all' ? 'Offer Letter' : 'All';

        $status = false;
        $status_code = 400;
        $message = 'Template not found';
        $template = '';
        $file_link = '';
        $email_template_data = [];
        try {
            $serverIP = $this->url->to('/');
            $company = CompanyProfile::first();
            $user_data = $result = OnboardingEmployees::with('positionDetail', 'state')->where('id', $id)->first();

            $positionId = $result->sub_position_id;
            $position_name = $result->positionDetail->position_name;

            // Logic for send new temlate
            $SequiDocsTemplatePermissions = SequiDocsTemplatePermissions::where('position_id', $positionId)->where('position_type', 'receipient')->where('category_id', 1)->get()->toArray();
            $template_count = count($SequiDocsTemplatePermissions);
            if ($template_count > 0) {
                $template_id = $SequiDocsTemplatePermissions[0]['template_id'];
            } else {
                return response()->json([
                    'ApiName' => 'sendEmailOnBoardingEmployee',
                    'status' => false,
                    'message' => 'Template not created for '.$position_name,
                ], 400);
            }

            // getiing offer letter
            $SequiDocsTemplate_data = SequiDocsTemplate::with(['permissions', 'receipient', 'SequiDocsEmailSettings'])->where('id', $template_id)->first();
            $message = 'Tamplate not found';

            DB::beginTransaction();
            if (! empty($SequiDocsTemplate_data)) {
                // Template Email
                $SequiDocsEmailSettings = $SequiDocsTemplate_data->SequiDocsEmailSettings;
                $email_content = $SequiDocsEmailSettings->email_content;
                $email_subject = $SequiDocsEmailSettings->email_subject;

                // Company Data  And other data
                $CompanyProfile = CompanyProfile::first();
                $Company_Website = $CompanyProfile->company_website;
                $Company_Email = $CompanyProfile->company_email;
                $mailing_address = $CompanyProfile->mailing_address;
                $business_address = $CompanyProfile->business_address;
                $business_phone = $CompanyProfile->business_phone;
                $Company_name = $business_name = $CompanyProfile->business_name;
                $logo = $CompanyProfile->logo;

                // replace contents
                $Business_Name_With_Other_Details = "$business_name | + $business_phone | $business_address";

                $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($CompanyProfile);
                $Header_Image = $company_and_other_static_images['header_image'];
                $Company_Logo = $company_and_other_static_images['Company_Logo'];
                $Sequifi_Logo = $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
                $Letter_Box = $company_and_other_static_images['letter_box'];
                $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

                $message = 'Tamplate is not ready for send to user';

                if (($SequiDocsTemplate_data->categery_id == 1 && $SequiDocsTemplate_data->completed_step == 4) || ($SequiDocsTemplate_data->categery_id != 1 && $SequiDocsTemplate_data->completed_step == 3)) {

                    $Document_Type = $SequiDocsTemplate_data->categories->categories;
                    $Document_Type = rtrim($Document_Type, 's');

                    // Check for send permition
                    $permissions_data = $SequiDocsTemplate_data->permissions;
                    $permission_id_arr = [];
                    $permission_name_arr = [];

                    foreach ($permissions_data as $permission_row) {
                        $permission_id_arr[] = $permission_row['position_id'];
                        $permission_name_arr[] = $permission_row['positionDetail']['position_name'];
                    }

                    $auth_user_data = Auth::user();
                    $send_permission = false;
                    $permission_position_ids = [];

                    if ($auth_user_data->is_super_admin != 1) {
                        $permission_position_ids[] = $auth_user_data['sub_position_id']; // user postion Closer / Setter
                        if ($auth_user_data->is_manager == 1) {
                            // $permission_position_ids[] = 1;
                        }
                        foreach ($permission_position_ids as $position_id) {
                            if (in_array($position_id, $permission_id_arr)) {
                                $send_permission = true;
                            }
                        }
                    } elseif ($auth_user_data->is_super_admin == 1) {
                        $send_permission = true;
                    }

                    if (! $send_permission) {
                        $message = "You don't have permission to send this template";
                    }

                    // Check for receipient  permition
                    $user_id = $user_data->id;
                    $userId = Crypt::encrypt($user_id, 12);
                    // $user_position_id = $user_data->sub_position_id; // user position_id
                    $user_position_id = $user_data->sub_position_id > 0 ? $user_data->sub_position_id : $user_data->position_id;
                    // user position_id

                    $receipient_data = $SequiDocsTemplate_data->receipient;
                    $receipient_id_arr = [];
                    $receipient_name_arr = [];
                    foreach ($receipient_data as $receipient_row) {
                        $receipient_id_arr[] = $receipient_row['position_id'];
                        $receipient_name_arr[] = $receipient_row['positionDetail']['position_name'];
                    }

                    $send_to_receipient = false;
                    $position_ids = [];
                    $position_ids[] = $user_position_id; // user postion Closer / Setter
                    if ($user_data->is_manager == 1) {
                        // $position_ids[] = 1;
                    }
                    foreach ($position_ids as $position_id) {
                        if (in_array($position_id, $receipient_id_arr)) {
                            $send_to_receipient = true;
                        }
                    }

                    if ($send_permission && ! $send_to_receipient) {
                        $message = 'This template will send only '.implode(',', $receipient_name_arr).'.';
                    }

                    // $send_to_receipient = true;
                    if ($send_to_receipient && $send_permission) {
                        $message = "Domain setting isn't allowed to send e-mail on this domain";
                        $send_email_arr = [];

                        $send_email_arr[] = [
                            'email' => $user_data->email,
                            'type' => 'employee',
                        ];
                        $recipient_sign_req = $SequiDocsTemplate_data->recipient_sign_req;
                        $manager_sign_req = $SequiDocsTemplate_data->manager_sign_req;
                        $recruiter_sign_req = $SequiDocsTemplate_data->recruiter_sign_req;
                        $recipient_sign_req = $SequiDocsTemplate_data->recipient_sign_req;

                        if ($manager_sign_req) {
                            if ($user_data->managerDetail) {
                                $send_email_arr[] = [
                                    'email' => $user_data->managerDetail->email,
                                    'type' => 'manager',
                                ];
                            }
                        }

                        // final list of email to send.
                        $final_emailArray = [];
                        $domain_setting = false;
                        $domain_error_on_email = [];
                        foreach ($send_email_arr as $row) {
                            $email = $row['email'];
                            $emailId = explode('@', $email);
                            $user_email_for_send_email = $email;
                            $check_domain_setting = DomainSetting::check_domain_setting($user_email_for_send_email);
                            if ($check_domain_setting['status'] == true) {
                                $emailArray[] = ['email' => $email];
                                $final_emailArray[] = ['email' => $email, 'role' => $row['type']];

                                if ($row['type'] == 'employee') {
                                    $domain_setting = true;
                                }

                            } else {
                                array_push($domain_error_on_email, $email);
                            }

                            // $domain = DomainSetting::where('domain_name',$emailId[1])->where('status',1)->orWhere('email_setting_type',1)->first();

                            // if($domain && $row['type'] == 'employee'){
                            //     $domain_setting = true;
                            // }

                            // if ($domain) {
                            //     $final_emailArray[] = ['email' => $email,'role'=> $row['type']];
                            // }
                        }
                        $html = (isset($SequiDocsTemplate_data['template_content'])) ? $SequiDocsTemplate_data['template_content'] : null;
                        if ($domain_setting) {

                            /** Send background verification mail */
                            if ($user_data->is_background_verificaton == 1) {
                                $configurationDetails = SClearanceConfiguration::where('position_id', $user_data->position_id)->where('hiring_status', 1)->orWhere('position_id', $user_data->sub_position_id)->first();
                                if (empty($configurationDetails)) {
                                    $configurationDetails = SClearanceConfiguration::where(['position_id' => null])->first();
                                }
                                if (! empty($configurationDetails)) {
                                    if ($configurationDetails->hiring_status == 1) {
                                        $screeningRequest = SClearanceTurnScreeningRequestList::where(['email' => $user_data->email])->first();
                                        if (! $screeningRequest) {
                                            $package_id = $configurationDetails->package_id;
                                            $srRequestSave = SClearanceTurnScreeningRequestList::create([
                                                'email' => $user_data->email,
                                                'user_type' => 'Onboarding',
                                                'user_type_id' => $user_data->id,
                                                'position_id' => $user_data->sub_position_id,
                                                'office_id' => $user_data->office_id,
                                                'first_name' => $user_data->firstt_name,
                                                'middle_name' => @$user_data->middle_name,
                                                'last_name' => $user_data->last_name,
                                                'package_id' => $package_id,
                                                'description' => 'Background Check',
                                                'status' => 'emailed',
                                            ]);
                                            $srRequestSave->save();
                                            $request_id = $srRequestSave->id;
                                        } else {
                                            $request_id = $screeningRequest->id;
                                        }

                                        $mailData['subject'] = 'Request for Background Check';
                                        $mailData['email'] = $user_data->email;
                                        $mailData['request_id'] = $request_id;
                                        $encryptedRequestId = Crypt::encrypt($request_id, 12);
                                        $mailData['url'] = $request->input('frontend_url');
                                        $mailData['template'] = view('mail.backgroundCheckMail', compact('mailData'));
                                        $this->sendEmailNotification($mailData);
                                    }
                                }
                            }

                            $SequiDocsSendAgreementWithTemplate = SequiDocsSendAgreementWithTemplate::select('position_id', DB::raw('GROUP_CONCAT(aggrement_template_id) as aggrement_template_ids'))->where('template_id', $template_id)->where('position_id', $user_position_id)->groupBy('position_id')->get()->toArray();
                            foreach ($SequiDocsSendAgreementWithTemplate as $key => $row) {
                                $aggrement_template_ids = explode(',', $row['aggrement_template_ids']);
                                $SequiDocsSendAgreementWithTemplate[$key]['aggrement_template_ids'] = $aggrement_template_ids;
                            }

                            $SequiDocsTemplate_data->template_agreements = $SequiDocsSendAgreementWithTemplate;
                            $template_agreements = $SequiDocsSendAgreementWithTemplate;

                            // return $template_agreements; // send_email_to_onboarding_employee
                            // if(count($template_agreements) == 1){
                            //     $other_data['template_agreements'] = $template_agreements;
                            //     $other_data['user_data'] = $user_data;
                            //     $other_data['auth_user_data'] = $auth_user_data;
                            //     $other_data['mail_to'] = "OnboardingEmployees";
                            //     $this->send_aggrements($request , $other_data);
                            // }

                            // Data for resolve key
                            $resolve_key_data = SequiDocsTemplate::resolve_key_data($user_data, $auth_user_data, $company);

                            $string = $html;
                            $page_brack = "<div style='page-break-before: always;'></div>";
                            $string = str_replace('[Page Break]', $page_brack, $string);
                            $string = str_replace('[Page_Break]', $page_brack, $string);
                            $string = str_replace('[page_break]', $page_brack, $string);

                            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: auto; max-height: 90px; margin: 0px auto;">';
                            $string = str_replace('[Company_Logo]', $Company_Logo_is, $string);
                            // return $string;

                            // [Compensation Plan]
                            $Compensation_plan = '';
                            if ($user_id > 0) {
                                $Compensation_plan = SequiDocsTemplate::Compensation_plan_data($user_id, 'OnboardingEmployees');
                            }
                            $string = str_replace('[Compensation Plan]', $Compensation_plan, $string);
                            $string = str_replace('[Compensation_Plan]', $Compensation_plan, $string);
                            foreach ($resolve_key_data as $key => $value) {
                                $string = str_replace('['.$key.']', $value, $string);
                            }
                            foreach ($resolve_key_data as $key => $value) {
                                $email_content = str_replace('['.$key.']', $value, $email_content);
                            }

                            // content with header and footer
                            $header_footer = SequiDocsTemplate::header_footer();
                            $string = str_replace('[Main_Content]', $string, $header_footer);
                            // content with header and footer End

                            // return $string;
                            if ($string) {
                                $dom = new \DOMDocument;
                                @$dom->loadHTML($string);
                                $divToRemove = $dom->getElementById('hideButton');
                                if ($divToRemove) {
                                    $divToRemove->parentNode->removeChild($divToRemove);
                                }
                                $string = $dom->saveHTML();
                            }

                            // return $string;

                            $template_name = isset($SequiDocsTemplate_data->template_name) ? str_replace(' ', '_', $SequiDocsTemplate_data->template_name) : null;
                            $generateTemplate = $template_name.'-'.'_'.date('m-d-Y').time().'.pdf';
                            $template = 'template/'.$generateTemplate;

                            // Pdf genration
                            $pdf = PDF::loadHTML($string, 'UTF-8');
                            // Set options for header and footer
                            // $pdf->setOptions([
                            //     'isHtml5ParserEnabled' => true,
                            //     'isPhpEnabled' => true,
                            //     'isHtmlInlineStylesEnabled' => true,
                            // ]);

                            // // Add header and footer
                            // $pdf->setOption('header-html', 'path/to/header.html');
                            // $pdf->setOption('footer-html', 'path/to/footer.html');
                            // End header footer Add

                            // Upload to S3 instead of local file system
                            $filePath = config('app.domain_name').'/'.'template/'.$generateTemplate;
                            $stored_bucket = 'private';
                            $s3_return = s3_upload($filePath, $pdf->setPaper('A4', 'portrait')->output(), false, $stored_bucket);
                            
                            if (!isset($s3_return['status']) || $s3_return['status'] != true) {
                                return response()->json([
                                    'ApiName' => 'sendEmailOnBoardingEmployee',
                                    'status' => false,
                                    'message' => 'Failed to upload PDF to S3.',
                                ], 500);
                            }
                            
                            $file_link = $s3_return['ObjectURL'];
                            $template = 'template/'.$generateTemplate;
                            
                            // Create temporary local file for DigiSigner upload
                            $tempDir = storage_path('app/temp');
                            if (!file_exists($tempDir)) {
                                mkdir($tempDir, 0755, true);
                            }
                            $path = $tempDir.'/'.$generateTemplate;
                            file_put_contents($path, $pdf->setPaper('A4', 'portrait')->output());
                            
                            $uriSegments = explode('/', $template);
                            $filename = end($uriSegments);

                            // $newEnvelope = $this->createEnvelope();
                            // // send envelope id in mail
                            // if($newEnvelope->id){
                            //     $responseData = $this->addDocumentsInToEnvelope($newEnvelope->id, asset($template), $final_emailArray);
                            // } else {
                            //     //Envelope not created
                            // }

                            $responseData = $this->uploadDocument($path, $filename); // upload template to digisigner
                            
                            // Clean up temporary file
                            if (file_exists($path)) {
                                unlink($path);
                            }
                            if (isset($responseData['error'])) {
                                return response()->json(['error' => $responseData['error']], 400);
                            }
                            $message = isset($responseData['message']) ? $responseData['message'] : $message;

                            if (isset($responseData['document_id'])) {

                                // incative old offer letter
                                $users_old_documents = Documents::where('user_id', $user_data->id)->where('document_uploaded_type', 'secui_doc_uploaded')->where('user_id_from', 'onboarding_employees')->where(function ($query) use ($SequiDocsTemplate_data) {
                                    $query->where('template_id', $SequiDocsTemplate_data->id)->orWhere('category_id', 1);
                                })->update(['is_active' => 0]);

                                // creating new offer letter doc
                                $documentId = $responseData['document_id'];

                                $url = 'https://api.digisigner.com/v1/signature_requests';
                                $token = config('services.digisigner.token');
                                $headers = [
                                    'Content-Type: application/json',
                                    'Authorization: Basic '.$token,
                                ];
                                $subject = $SequiDocsTemplate_data->template_name;
                                // $message = config('app.domain_name'); //"Send ".$SequiDocsTemplate_data->template_name;

                                $data = '{
                                    "use_text_tags": true,
                                    "hide_text_tags": true,
                                    "send_emails":false,
                                    "documents": [
                                        {
                                            "document_id": "'.$documentId.'",
                                            "subject": "'.$subject.'",
                                            "message": "'.config('app.domain_name').'",
                                            "signers": '.json_encode($final_emailArray).'
                                        }
                                    ]
                                }';

                                $response = $this->curlRequest($url, $data, $headers);
                                $data_obj = json_decode($response, true);
                                $signers = $data_obj['documents'][0]['signers'];
                                // return $data_obj;

                                if (isset($data_obj['signature_request_id']) && $data_obj['signature_request_id']) {

                                    $document = Documents::create([
                                        'user_id' => $user_data->id,
                                        'user_id_from' => 'onboarding_employees',
                                        'document_uploaded_type' => 'secui_doc_uploaded',
                                        'send_by' => $auth_user_data->id,
                                        'document_send_date' => date('Y-m-d'),
                                        'description' => $SequiDocsTemplate_data->template_name,
                                        // 'document_type_id' => $SequiDocsTemplate_data->id
                                        'category_id' => $SequiDocsTemplate_data->categery_id,
                                        'template_id' => $SequiDocsTemplate_data->id,
                                    ]);

                                    $DocumentFiles = DocumentFiles::create([
                                        'document_id' => $document->id,
                                        'document' => $template,
                                        'signed_document_id' => $documentId,
                                        'signature_request_id_for_callback' => $data_obj['signature_request_id'],
                                    ]);

                                    foreach ($signers as $signer) {

                                        $signer_role = '';
                                        foreach ($final_emailArray as $emai_row) {
                                            if ($emai_row['email'] == $signer['email']) {
                                                $signer_role = $emai_row['role'];
                                            }
                                        }

                                        $request_links_url = SequiDocsTemplate::request_links_url($serverIP, $user_id, $documentId);
                                        $accept_url = $request_links_url['accept_url'];
                                        $Request_Change_Link = $request_links_url['Request_Change_Link'];
                                        $Reject_Link = $request_links_url['Reject_Link'];
                                        if ($signer_role == 'employee' && $SequiDocsTemplate_data->categery_id == 1) {
                                            $offer_letter_Email_format = SequiDocsTemplate::offer_letter_Email_format_for_use();
                                        } else {
                                            $offer_letter_Email_format = SequiDocsTemplate::offer_letter_Email_format_for_test();
                                        }

                                        $email_template_data['email'] = $signer['email'];
                                        $Review_Document_Link = $signer['sign_document_url'];

                                        $offer_letter_Email_format = str_replace('[Email_Content]', $email_content, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Business_Name_With_Other_Details]', $Business_Name_With_Other_Details, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Company_Email]', $Company_Email, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Document_Type]', $Document_Type, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Company_Website]', $Company_Website, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Company_Logo]', $Company_Logo, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Header_Image]', $Header_Image, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Letter_Box]', $Letter_Box, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Sequifi_Logo]', $Sequifi_Logo, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Company_name]', $Company_name, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Review_Document_Link]', $Review_Document_Link, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Reject_Link]', $Reject_Link, $offer_letter_Email_format);
                                        $offer_letter_Email_format = str_replace('[Request_Change_Link]', $Request_Change_Link, $offer_letter_Email_format);

                                        foreach ($resolve_key_data as $key => $value) {
                                            if ($value != 'emails' && $value != 'email') {
                                                $offer_letter_Email_format = str_replace('['.$key.']', $value, $offer_letter_Email_format);
                                            }
                                        }

                                        $email_template_data['subject'] = $email_subject;
                                        $email_template_data['template'] = $offer_letter_Email_format;
                                        $email_response = $this->sendEmailNotification($email_template_data);
                                    }

                                    if (count($template_agreements) == 1 && $send_documents_to_user == 'All') {
                                        $other_data['template_agreements'] = $template_agreements;
                                        $other_data['user_data'] = $user_data;
                                        $other_data['auth_user_data'] = $auth_user_data;
                                        $other_data['mail_to'] = 'OnboardingEmployees';
                                        $this->send_aggrements($request, $other_data);
                                    }

                                    // sending Other docs like W9 and etc
                                    if ($request_type == 'Resend' && $send_documents_to_user == 'All') {
                                        $send_other_documents_to_user = Documents::where('user_id', $user_data->id)->where('document_uploaded_type', 'secui_doc_uploaded')->where('is_active', '1')->where('user_id_from', 'onboarding_employees')->whereNull('template_id')->whereNull('category_id')->whereNotNull('signature_request_document_id')->get()->toArray();

                                        foreach ($send_other_documents_to_user as $other_document) {
                                            $other_document['document_id'] = $other_document['signature_request_document_id'];
                                            $other_document['document_name'] = $other_document['description'];
                                            $other_document['email'] = $user_data->email;
                                            $other_document['request_type'] = $request_type;
                                            $http_request = Request::create('/custom_doc_for_sign', 'post', $other_document);
                                            $customDocForSign_response = $this->customDocForSign($http_request, 'function');
                                        }
                                    }
                                    $update_OnboardingEmployees = OnboardingEmployees::find($id);
                                    $update_OnboardingEmployees->status_id = 4;
                                    $update_OnboardingEmployees->document_id = $documentId;
                                    $update_OnboardingEmployees->save();
                                    $message = 'Offer letter '.$request_type.' to Employee';
                                    $status = true;
                                    $status_code = 200;
                                    DB::commit();
                                    $OnboardingEmployees_status = 'Offer Letter sent';
                                    if ($request_type == 'Resend') {
                                        $OnboardingEmployees_status = 'Offer Letter Resent';
                                        OnboardingEmployees::where('id', $id)->update(['status_id' => 12]);
                                    }
                                    /************  hubspot code starts here **************** */
                                    $OnboardingEmployees = OnboardingEmployees::find($id);
                                    $userId = Auth()->user();
                                    $recruiter_id = ($userId->is_super_admin == 0) ? $userId->id : null;
                                    $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                                    $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                                    if (! empty($CrmData) && ! empty($CrmSetting)) {
                                        $val = json_decode($CrmSetting['value']);
                                        $token = $val->api_key;
                                        $OnboardingEmployees->status = $OnboardingEmployees_status;

                                        $hubspotSaleDataCreate = $this->hubspotOnboardemployee($OnboardingEmployees, $recruiter_id, $token);
                                    }
                                    /************  hubspot code starts here **************** */

                                    /************  jobNimbus code starts here **************** */
                                    // $OnboardingEmployees =  OnboardingEmployees::find($id);
                                    // $userId = Auth()->user();
                                    // $recruiter_id = ($userId->is_super_admin==0)? $userId->id : null;
                                    // $jobNimbusCrmData = Crms::with('crmSetting')->where('id',4)->where('status',1)->first();
                                    // if(!empty($jobNimbusCrmData)){
                                    //     $jobNimbusCrmSetting = json_decode($jobNimbusCrmData->crmSetting->value);
                                    //     $jobNimbusToken = $jobNimbusCrmSetting->api_key;
                                    //     $postDataToJobNimbus = array(
                                    //         'display_name' => $OnboardingEmployees['first_name'] .', '. $OnboardingEmployees['last_name'] .', ' . $OnboardingEmployees['id'] ,
                                    //         'email' => $OnboardingEmployees['email'],
                                    //         'first_name' => $OnboardingEmployees['first_name'],
                                    //         'last_name' => $OnboardingEmployees['last_name'],
                                    //         'mobile_phone' =>  $OnboardingEmployees['mobile_no'],
                                    //     );
                                    //     $responseJobNimbuscontats = $this->storeJobNimbuscontats($postDataToJobNimbus,$jobNimbusToken);
                                    // }
                                    /************  jobNimbus code starts here **************** */
                                }
                            }
                        } else {
                            /* Send Notification to SA about Domain Configurations */
                            $errorDetails = [
                                'message' => "The email couldn't be sent due to Domain settings not allowing to send email, Please check the domain configurations and try to send email again.",
                                'domain_name' => '',
                                'recipient_email' => $domain_error_on_email,
                            ];
                            $this->sendEmailErrorNotificationToSA($errorDetails, 'domain_error');
                        }
                    }
                }
            }
        } catch (Exception $error) {
            $message = $error->getMessage();
            $error_line = $error->getLine();

            return response()->json(['error' => $error, 'message' => $message, 'error_line' => $error_line], 400);
        }

        return response()->json([
            'ApiName' => 'sendEmailOnBoardingEmployee',
            'status' => $status,
            'message' => $message,
            'file_link' => $file_link,
            'data' => $template,
        ], $status_code);
    }
}
