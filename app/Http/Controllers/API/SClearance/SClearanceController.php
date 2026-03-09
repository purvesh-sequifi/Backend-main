<?php

namespace App\Http\Controllers\API\SClearance;

use App\Http\Controllers\Controller;
use App\Models\CrmSetting;
use App\Models\DomainSetting;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\OnboardingEmployees;
use App\Models\SClearanceConfiguration;
use App\Models\SClearancePlan;
use App\Models\SClearanceScreeningRequestList;
use App\Models\SClearanceStatus;
use App\Models\SClearanceTransunionResponse;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use App\Traits\SClearanceTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Log;

class SClearanceController extends Controller
{
    use EmailNotificationTrait, SClearanceTrait;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    /**
     * @method getPlanLists
     * this method is used to get available plan lists
     */
    public function getPlanLists(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $planData = SClearancePlan::select('id', 'plan_name', 'price', 'bundle_id')->get()->toArray();

            return response()->json([
                'ApiName' => 'Get S-Clearance Plan Lists ',
                'status' => true,
                'message' => 'successfully',
                'data' => $planData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'Get S-Clearance Plan Lists ',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method getStatusLists
     * this method is used to get available plan lists
     */
    public function getStatusLists(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $statusData = SClearanceStatus::select('id', 'status_name')->get()->toArray();

            return response()->json([
                'ApiName' => 'Get S-Clearance Status Lists ',
                'status' => true,
                'message' => 'successfully',
                'data' => $statusData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'Get S-Clearance Status Lists ',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @method getToken
     * Used to get token from Transunion Sharabale API
     */
    public function getToken()
    {
        $tokenData = $this->generateToken();

        return $tokenData;
    }

    /**
     * @method reportStatusCallbackURLForSharable
     * A callback(webhook/notification) URL for Transunion Sharabale API to notify us
     */
    public function reportStatusCallbackURLForSharable(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->message);
            $response = '';
            Log::channel('sclearance_log')->info('Report Status Callback '.print_r($data, true));
            $status = '';
            if (isset($data->ReportsDeliveryStatus) && $data->ReportsDeliveryStatus == 'ReportCompleted') {
                if (! empty($data->ScreeningRequestApplicantId)) {

                    $screening_request = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $data->ScreeningRequestApplicantId, 'is_report_generated' => 0])->first();
                    if (isset($screening_request->screening_request_applicant_id)) {
                        // Request for Report
                        $reportResponse = $this->getScreeningReports($data->ScreeningRequestApplicantId);

                        if (isset($reportResponse['reportResponseModelDetails']) && isset($reportResponse['reportResponseModelDetails'][0]['reportData'])) {

                            $screening_request->status = 'Approval Pending';
                            $screening_request->is_report_generated = 1;
                            $screening_request->report_date = date('Y-m-d');
                            $screening_request->report_expiry_date = date('Y-m-d', strtotime('+'.$reportResponse['reportsExpireNumberOfDays'].' days'));
                            $screening_request->save(); // Added for activity log

                            $status = 'Record Updated';
                        }
                    }
                } else {
                    $response = [
                        'message' => 'Screening Request Applicant Id is missing',
                    ];
                    $status = 'Screening Request Applicant Id is missing';
                }
            } else {
                $response = [
                    'message' => 'Report not found',
                ];
                $status = 'Report not found';
            }

            SClearanceTransunionResponse::insert([
                'screening_request_applicant_id' => @$data->ScreeningRequestApplicantId,
                'is_manual_verification' => 0,
                'status' => $status,
                'response' => $request->message,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json($response, 200);
        } catch (Exception $e) {
            Log::channel('sclearance_log')->info('error in report webhook '.print_r($e->getMessage(), true));
            SClearanceTransunionResponse::insert([
                'screening_request_applicant_id' => @$data->ScreeningRequestApplicantId,
                'is_manual_verification' => 0,
                'status' => 'Error in catch',
                'response' => @$request->message,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * @method manualAuthCallbackURLForSharable
     * A callback(webhook/notification) URL for Transunion Sharabale API to notify us
     */
    public function manualAuthCallbackURLForSharable(Request $request): JsonResponse
    {
        try {
            // $data = $request->all();
            $response = '';
            $data = json_decode($request->message);
            Log::channel('sclearance_log')->info('Manual Authentication Callback '.print_r($data, true));
            $status = '';
            if (isset($data->ManualAuthenticationStatus) && ! empty($data->ManualAuthenticationStatus)) {
                if (! empty($data->ScreeningRequestApplicantId) && ($data->ManualAuthenticationStatus == 'Passed' || $data->ManualAuthenticationStatus == 'UserAuthenticated')) {

                    // Request for Report
                    $screening_request = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $data->ScreeningRequestApplicantId, 'is_report_generated' => 0])->first();
                    if (isset($screening_request->screening_request_applicant_id)) {
                        $reportResponse = $this->getScreeningReports($data->ScreeningRequestApplicantId);
                        if (isset($reportResponse['reportResponseModelDetails']) && isset($reportResponse['reportResponseModelDetails'][0]['reportData'])) {
                            $screening_request->status = 'Approval Pending';
                            $screening_request->is_report_generated = 1;
                            $screening_request->report_date = date('Y-m-d');
                            $screening_request->report_expiry_date = date('Y-m-d', strtotime('+'.$reportResponse['reportsExpireNumberOfDays'].' days'));
                            $screening_request->save(); // Added for activity log

                            $status = 'Updated Record';
                        }
                    }

                    $screening_request_count = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $data->ScreeningRequestApplicantId])->count();
                    if ($screening_request_count == 0) {
                        $status = 'Screening Request not Found';
                    }

                    $response = [
                        'status' => true,
                    ];
                } else {
                    $response = [
                        'message' => 'Screening Request Applicant Id is missing',
                    ];
                    $status = 'Screening Request Applicant Id is missing';
                }
            } else {
                $response = [
                    'message' => 'Manual Authentication Failed',
                ];
                $status = 'Authentication Failed';
            }

            SClearanceTransunionResponse::insert([
                'screening_request_applicant_id' => @$data->ScreeningRequestApplicantId,
                'is_manual_verification' => 1,
                'status' => $status,
                'response' => $request->message,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json($response, 200);
        } catch (Exception $e) {
            Log::channel('sclearance_log')->info('error in manual webhook '.print_r($e->getMessage(), true));
            SClearanceTransunionResponse::insert([
                'screening_request_applicant_id' => @$data->ScreeningRequestApplicantId,
                'is_manual_verification' => 1,
                'status' => 'Error in catch',
                'response' => @$request->message,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * @method configure_setting
     * This is used to s-clearance configurations
     */
    public function configure_setting(Request $request): JsonResponse
    {
        try {
            $input = $request->all();
            $Validator = Validator::make(
                $request->all(),
                [
                    'data' => 'required',
                ]
            );

            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            if (isset($input['data']) && ! empty($input['data'])) {
                $oldDataIds = SClearanceConfiguration::Where('id', '<>', 1)->pluck('id')->toArray();
                foreach ($input['data'] as $data) {
                    $Validator = Validator::make(
                        $data,
                        [
                            'position_id' => 'required',
                            'hiring_status' => 'required',
                        ]
                    );

                    if ($Validator->fails()) {
                        return response()->json(['error' => $Validator->errors()], 400);
                    }

                    $configureData = [];
                    if (isset($data['id']) && ! empty($data['id'])) {
                        $oldData = SClearanceConfiguration::where(['id' => $data['id']])->select('id')->get();
                        if (! empty($oldData)) {
                            // $configureData = array(
                            //     'position_id' => ($data['position_id'] == 'All') ? null : $data['position_id'],
                            //     'hiring_status' => ($data['hiring_status'] == 'All') ? null : $data['hiring_status'],
                            //     'is_mandatory' => ((isset($data['is_mandatory']) && $data['is_mandatory'] == true) ? 1 : 0),
                            //     'is_approval_required' => ((isset($data['is_approval_required']) &&  $data['is_approval_required'] == true) ? 1 : 0)
                            // );

                            // SClearanceConfiguration::where(['id' => $data['id']])->update($configureData);

                            $configUpdate = SClearanceConfiguration::where(['id' => $data['id']])->first(); // added for activity log
                            $configUpdate->position_id = ($data['position_id'] == 'All') ? null : $data['position_id'];
                            $configUpdate->hiring_status = ($data['hiring_status'] == 'All') ? null : $data['hiring_status'];
                            $configUpdate->is_mandatory = ((isset($data['is_mandatory']) && $data['is_mandatory'] == true) ? 1 : 0);
                            $configUpdate->is_approval_required = ((isset($data['is_approval_required']) && $data['is_approval_required'] == true) ? 1 : 0);
                            $configUpdate->save(); // Added for activity log

                            if (($key = array_search($data['id'], $oldDataIds)) !== false) {
                                unset($oldDataIds[$key]);
                            }
                        } else {
                            $configureData = SClearanceConfiguration::create([
                                'position_id' => ($data['position_id'] == 'All') ? null : $data['position_id'],
                                'hiring_status' => ($data['hiring_status'] == 'All') ? null : $data['hiring_status'],
                                'is_mandatory' => ((isset($data['is_mandatory']) && $data['is_mandatory'] == true) ? 1 : 0),
                                'is_approval_required' => ((isset($data['is_approval_required']) && $data['is_approval_required'] == true) ? 1 : 0),
                            ]);
                            $configureData->save();
                        }
                    } else {
                        $configureData = SClearanceConfiguration::create([
                            'position_id' => ($data['position_id'] == 'All') ? null : $data['position_id'],
                            'hiring_status' => ($data['hiring_status'] == 'All') ? null : $data['hiring_status'],
                            'is_mandatory' => ((isset($data['is_mandatory']) && $data['is_mandatory'] == true) ? 1 : 0),
                            'is_approval_required' => ((isset($data['is_approval_required']) && $data['is_approval_required'] == true) ? 1 : 0),
                        ]);
                        $configureData->save();
                    }
                }

                if (! empty($oldDataIds)) {
                    foreach ($oldDataIds as $id) {
                        SClearanceConfiguration::find($id)->delete();
                    }
                }
            }

            return response()->json(['status' => true, 'message' => 'Added Successfully.'], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong'], 400);
        }

    }

    /**
     * @method get_configurations
     * This is used to get s-clearance configurations
     */
    public function get_configurations()
    {
        try {
            $configureData = SClearanceConfiguration::select('id', DB::raw("IFNULL(position_id, 'All') as position_id"), DB::raw("IFNULL(hiring_status, 'All') as hiring_status"), DB::raw('(CASE WHEN is_mandatory = 1 THEN true ELSE false END) AS is_mandatory'), DB::raw('(CASE WHEN is_approval_required = 1 THEN true ELSE false END) AS is_approval_required'))->orderBy('id', 'desc')->get();

            $configureData = $configureData->map(function ($item) {
                return [
                    'id' => $item->id,
                    'position_id' => $item->position_id,
                    'hiring_status' => $item->hiring_status,
                    'is_mandatory' => $item->is_mandatory == 1 ? true : false,
                    'is_approval_required' => $item->is_approval_required == 1 ? true : false,
                ];
            });

            return response()->json(['status' => true, 'configureData' => $configureData], 200);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong'], 400);
        }

    }

    /**
     * @method new_clearance_external
     * This is used to create screening request for external recipients
     */
    public function new_clearance_external(Request $request): JsonResponse
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'email' => 'required',
                    'description' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $input = $request->all();
            $input['user_type'] = 'External';
            $newClearanceMailResponse = $this->sendNewClearanceMail($input);
            if ($newClearanceMailResponse['status'] == true) {
                return response()->json([
                    'ApiName' => 'New Clearance External',
                    'status' => true,
                    'message' => 'Mail sent for background check',
                    'encryptedRequestId' => $newClearanceMailResponse['encryptedRequestId'],
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'New Clearance External',
                    'status' => false,
                    'message' => $newClearanceMailResponse['message'],
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'New Clearance External -  Mail',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    public function add_new_sclearance(Request $request): JsonResponse
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'email' => 'required',
                    'description' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $input = $request->all();
            $input['user_type'] = 'Onboarding';

            $newClearanceMailResponse = $this->sendNewClearanceMail($input);

            if ($newClearanceMailResponse['status'] == true) {
                return response()->json([
                    'ApiName' => 'New Clearance Internal Doc',
                    'status' => true,
                    'message' => 'Mail sent for background check',
                    'encryptedRequestId' => $newClearanceMailResponse['encryptedRequestId'],
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'New Clearance Internal Doc',
                    'status' => false,
                    'message' => $newClearanceMailResponse['message'],
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'New Clearance Internal Doc -  Mail',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    public function resend_sclearance_request(Request $request): JsonResponse
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'id' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }
            $input = $request->all();
            $SrDetails = SClearanceScreeningRequestList::where('id', $input['id'])->first()->toArray();
            $SrDetails['frontend_url'] = $request->frontend_url ?? '';
            $newClearanceMailResponse = $this->resendClearanceMail($SrDetails);
            if (isset($newClearanceMailResponse['status']) && $newClearanceMailResponse['status'] == true) {
                // Added for activity log
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn(SClearanceScreeningRequestList::find($input['id']))
                    ->withProperties(['attributes' => ['user' => $SrDetails['first_name'].' '.$SrDetails['last_name'], 'action' => 'Resent Mail']])
                    ->event('updated')
                    ->log('updated');

                return response()->json([
                    'ApiName' => 'SClearance request send',
                    'status' => true,
                    'message' => 'Mail sent for background check',
                    'encryptedRequestId' => $newClearanceMailResponse['encryptedRequestId'],
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'SClearance request send',
                    'status' => false,
                    'message' => $newClearanceMailResponse['message'],
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'SClearance request send -  Mail',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    public function resendClearanceMail($requestData)
    {
        try {
            $mailResponse = '';

            $check_domain_setting = DomainSetting::check_domain_setting($requestData['email']);
            if ($check_domain_setting['status'] == true) {
                $request_id = $requestData['id'];
                $mailData['subject'] = 'Request for Background Check';
                $mailData['email'] = $requestData['email'];
                $mailData['request_id'] = $request_id;
                // $encryptedRequestId = Crypt::encrypt($request_id);
                $encryptedRequestId = encryptData($request_id);
                $mailData['encrypted_request_id'] = $encryptedRequestId;
                $mailData['url'] = $requestData['frontend_url'];
                $mailData['template'] = view('mail.backgroundCheckMail', compact('mailData'));
                $mailResponse = $this->sendEmailNotification($mailData);
                if ($mailResponse) {
                    return ['status' => true, 'message' => 'Mail sent', 'encryptedRequestId' => $encryptedRequestId];
                } else {
                    return ['status' => false, 'message' => 'Error in sending mail'];
                }
            } else {
                return ['status' => false, 'message' => "Domain setting isn't allowed to send e-mail on this domain."];

            }
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Something went wrong'];
        }
    }

    /**
     * @method new_clearance_internal
     * This is used to create screening request for internal recipients
     */
    public function new_clearance_internal(Request $request): JsonResponse
    {
        $requestData = $request->all();

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            if (isset($requestData['user_data']) && ! empty($requestData['user_data'])) {
                $data = [];
                $processedEmails = [];
                $mailSentCount = 0;
                $mailNotSentCount = 0;
                $mailNotSent = [];
                $mailSent = [];
                foreach ($requestData['user_data'] as $user) {
                    if (! in_array($user['email'], $processedEmails)) {
                        $Validator = Validator::make(
                            $user,
                            [
                                'first_name' => 'required',
                                'last_name' => 'required',
                                'email' => 'required',
                            ]
                        );
                        if ($Validator->fails()) {
                            return response()->json(['error' => $Validator->errors()], 400);
                        }

                        $newClearanceMailResponse = $this->sendNewClearanceMail($user);
                        $data[] = [
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name'],
                            'email' => $user['email'],
                            'status' => $newClearanceMailResponse['status'],
                        ];
                        $processedEmails[] = $user['email'];
                        if ($newClearanceMailResponse['status'] == true) {
                            $mailSentCount++;
                            array_push($mailSent, $user['first_name'].' '.$user['last_name']);
                        } else {
                            $mailNotSentCount++;
                            $mailNotSentReason = $user['first_name'].' '.$user['last_name'].' : '.$newClearanceMailResponse['message'];
                            array_push($mailNotSent, $mailNotSentReason);
                        }
                    }
                }

                $response = [
                    'ApiName' => 'New Clearance Internal',
                    'mailSentMessage' => '',
                    'mailNotSentMessage' => '',
                    'mailNotSentReasons' => '',
                    'status' => true,
                ];

                if ($mailSentCount > 0) {
                    $response['mailSentMessage'] = 'Mail sent to ('.implode(', ', $mailSent).')';
                }

                if ($mailNotSentCount > 0) {
                    $response['mailNotSentMessage'] = $mailNotSentCount.' Mails failed to send, due to following reasons';
                    $response['mailNotSentReasons'] = $mailNotSent;
                }

                return response()->json($response, 200);
            } else {
                return response()->json([
                    'ApiName' => 'New Clearance Internal',
                    'status' => false,
                    'message' => 'Please select user',
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'New Clearance Internal -  Mail',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method add_screening_request
     * This is a common code to create screening request for external/internal both recipients
     */
    public function add_screening_request(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    // 'employer_id' => 'required',
                    'email' => 'required',
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'phone_number' => 'required',
                    'phone_type' => 'required',
                    'address_line_1' => 'required',
                    'locality' => 'required',
                    'region' => 'required',
                    'postal_code' => 'required',
                    'accepted_terms_conditions' => 'required',
                    'date_of_birth' => 'required',
                    'social_security_number' => 'required',
                    'request_id' => 'required',
                    // 'description' => 'required',
                    // 'credit_state_restriction_apply' => 'required',
                    // 'salary_range' => 'required',
                    // 'employment_type' => 'required'
                ]
            );

            $requestData = $request->all();
            // $request_id = Crypt::decrypt($requestData['request_id']);
            $request_id = decryptData($requestData['request_id']);
            $requestData['request_id'] = $request_id;
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
            if ($crmSetting) {
                $employerResponse = '';
                $crmData = json_decode($crmSetting->value, true);
                $employer_id = @$crmData['employer_id'];
                $employerResponse = $this->getEmployer($employer_id);
                if (isset($employerResponse['employerId']) && ! empty($employerResponse['employerId'])) {
                    $aplicantVerified = 0;
                    $requestData['employer'] = $employerResponse;
                    $requestData['bundle_id'] = 0;
                    $plan_id = @$crmData['plan_id'];
                    if (isset($crmData['bundle_id']) && ! empty($crmData['bundle_id'])) {
                        $requestData['bundle_id'] = $crmData['bundle_id'];
                    } else {
                        $planData = SClearancePlan::where('plan_id', $plan_id)->first();
                        $requestData['bundle_id'] = @$planData['bundle_id'];
                    }

                    $getRequestData = SClearanceScreeningRequestList::select('*')->where(['id' => $request_id])->get()->toArray();
                    if (! empty($getRequestData)) {
                        $applicantResponse = '';
                        $existingApplicant = SClearanceScreeningRequestList::where(['email' => $requestData['email']])->whereNotNull('applicant_id')->orderBy('id', 'desc')->get()->toArray();
                        if (count($existingApplicant) > 0 && isset($existingApplicant[0]['applicant_id']) && ! empty($existingApplicant[0]['applicant_id'])) {
                            $srRequestSave = SClearanceScreeningRequestList::where(['id' => $request_id])->update([
                                'applicant_id' => $existingApplicant[0]['applicant_id'],
                            ]);
                            $applicantResponse = [
                                'applicantId' => $existingApplicant[0]['applicant_id'],
                            ];
                            // Check if applicant is already verified
                            if (! empty($existingApplicant[0]['screening_request_applicant_id'])) {
                                $validateSR = $this->validateScreeningRequest($existingApplicant[0]['screening_request_applicant_id'], $existingApplicant[0]['applicant_id']);
                                if ($validateSR == 'Verified') {
                                    $aplicantVerified = 1;
                                    // Update with previous one to current record
                                    $srRequestSave = SClearanceScreeningRequestList::find($request_id);
                                    $srRequestSave->applicant_id = $existingApplicant[0]['applicant_id'];
                                    $srRequestSave->screening_request_id = $existingApplicant[0]['screening_request_id'];
                                    $srRequestSave->screening_request_applicant_id = $existingApplicant[0]['screening_request_applicant_id'];
                                    $srRequestSave->exam_id = $existingApplicant[0]['exam_id'];
                                    $srRequestSave->is_report_generated = $existingApplicant[0]['is_report_generated'];
                                    $srRequestSave->is_manual_verification = $existingApplicant[0]['is_manual_verification'];
                                    $srRequestSave->date_sent = $existingApplicant[0]['date_sent'];
                                    $srRequestSave->report_date = $existingApplicant[0]['report_date'];
                                    $srRequestSave->status = $existingApplicant[0]['status'];
                                    $srRequestSave->approved_declined_by = $existingApplicant[0]['approved_declined_by'];
                                    $srRequestSave->exam_attempts = $existingApplicant[0]['exam_attempts'];
                                    $srRequestSave->plan_id = $existingApplicant[0]['plan_id'];
                                    $srRequestSave->save();
                                }
                            }
                        } else {
                            $applicantResponse = $this->addApplicant($requestData);
                        }

                        if ($aplicantVerified == 0) {
                            $requestData['description'] = $getRequestData[0]['description'];
                            if (isset($applicantResponse['applicantId']) && ! empty($applicantResponse['applicantId'])) {
                                $requestData['applicant'] = $applicantResponse;
                                $SrResponse = $this->createScreeningRequest($requestData);
                                if (isset($applicantResponse['applicantId']) && ! empty($applicantResponse['applicantId']) && isset($SrResponse['screeningRequestId']) && isset($SrResponse['screeningRequestId'])) {
                                    $srRequestSave = SClearanceScreeningRequestList::where(['id' => $request_id])->update([
                                        //     'applicant_id' => $applicantResponse['applicantId'],
                                        //     'screening_request_id' => $SrResponse['screeningRequestId'],
                                        //     'screening_request_applicant_id' => $SrResponse['screeningRequestApplicantId'],
                                        // 'status' => 'Pending Verification', // 2
                                        // 'status' => 'In Progress',
                                        'date_sent' => date('Y-m-d'),
                                        'plan_id' => $plan_id,
                                    ]);

                                    $screening_request = SClearanceScreeningRequestList::where(['id' => $request_id])->first();
                                    $screening_request->status = 'In Progress';
                                    $screening_request->save(); // Added for activity log

                                    return response()->json([
                                        'ApiName' => 'new clearance',
                                        'status' => true,
                                        'message' => 'New Clearance added successfully',
                                        'data' => [
                                            'applicant_id' => @$applicantResponse['applicantId'],
                                            'screening_request_id' => @$SrResponse['screeningRequestId'],
                                            'screening_request_applicant_id' => @$SrResponse['screeningRequestApplicantId'],
                                            'status' => 'Pending Verification',
                                        ],
                                    ], 200);
                                } else {
                                    if (isset($SrResponse['name']) && $SrResponse['name'] == 'UnauthorizedAccess') {
                                        $this->sendMailforUnAuthorized();

                                        return response()->json([
                                            'ApiName' => 'new clearance - ScreeningRequest',
                                            'status' => false,
                                            'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                                            'apiResponse' => @$SrResponse,
                                        ], 400);
                                    } else {
                                        return response()->json([
                                            'ApiName' => 'new clearance - ScreeningRequest',
                                            'status' => false,
                                            'apiResponse' => @$SrResponse,
                                        ], 400);
                                    }
                                }
                            } elseif (isset($applicantResponse['name']) && $applicantResponse['name'] == 'UnauthorizedAccess') {
                                $this->sendMailforUnAuthorized();

                                return response()->json([
                                    'ApiName' => 'new clearance - Applicant',
                                    'status' => false,
                                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                                    'apiResponse' => @$applicantResponse,
                                ], 400);
                            } else {
                                return response()->json([
                                    'ApiName' => 'new clearance - Applicant',
                                    'status' => false,
                                    'apiResponse' => @$applicantResponse,
                                ], 400);
                            }
                        } else {
                            return response()->json([
                                'ApiName' => 'Add Screening Request',
                                'status' => true,
                                'message' => 'Your are already verified',
                                'verified' => true,
                            ], 200);
                        }
                    }
                } elseif (isset($employerResponse['name']) && $employerResponse['name'] == 'UnauthorizedAccess') {
                    $this->sendMailforUnAuthorized();

                    return response()->json([
                        'ApiName' => 'new clearance - Employer',
                        'status' => false,
                        'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                        'apiResponse' => @$employerResponse,
                    ], 400);
                } else {
                    return response()->json([
                        'ApiName' => 'new clearance - Employer',
                        'status' => false,
                        'apiResponse' => @$employerResponse,
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'new clearance - Screening Request',
                    'status' => false,
                    'message' => 'Please Activate S-Clearance First',
                ], 400);
            }

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong', 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * @method background_verification_exam
     * This is used to complete background verification of user
     */
    public function background_verification_exam(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'applicantId' => 'required',
                    'screeningRequestId' => 'required',
                    'screeningRequestApplicantId' => 'required',
                ]
            );

            $requestData = $request->all();
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $backgroundVerification = $this->backgroundVerificationExam($requestData);
            if (isset($backgroundVerification['examId']) && ! empty($backgroundVerification['examId'])) {
                $backgroundVerification['screeningRequestApplicantId'] = $requestData['screeningRequestApplicantId'];

                $srRequestSave = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $requestData['screeningRequestApplicantId']])->update([
                    'exam_id' => $backgroundVerification['examId'],
                ]);

                if (isset($backgroundVerification['result']) && $backgroundVerification['result'] == 'ManualVerificationRequired') {
                    $srRequestSave = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $requestData['screeningRequestApplicantId']])->update([
                        'exam_id' => $backgroundVerification['examId'],
                        // 'status' => 'Manual Verification Pending',
                        'is_manual_verification' => 1,
                    ]);

                    $sreening_request = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $requestData['screeningRequestApplicantId']])->first();

                    $sreening_request->status = 'Manual Verification Pending';
                    $sreening_request->save(); // Added for activity log

                    return response()->json([
                        'ApiName' => 'Background Verification Exam',
                        'status' => true,
                        'message' => 'Dear '.$sreening_request->first_name.' '.$sreening_request->last_name.', we regret to inform you that we encountered difficulty verifying your identity online. To proceed with completing your screening, we kindly request you to reach out to our customer support team at 888.710.0272. They will assist you in finalizing the verification process over the phone. Your email is '.$sreening_request->email.' and the screening request id is '.$sreening_request->screening_request_id.'.',
                        'manual_authentication' => 1,
                        'data' => $backgroundVerification,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'Background Verification Exam',
                        'status' => true,
                        'message' => 'Please clear the exam to be verified',
                        'data' => $backgroundVerification,
                    ], 200);
                }

            } elseif (isset($backgroundVerification['status']) && $backgroundVerification['status'] == 'Verified') {
                // $saveData = [
                //     'status' => 'In Progress',
                //     'is_report_generated' => 0,
                //     'report_date' => date('Y-m-d')
                // ];
                // Request for Report
                // $reportResponse = $this->getScreeningReports($requestData['screeningRequestApplicantId']);
                // if(isset($reportResponse['reportResponseModelDetails']) && isset($reportResponse['reportResponseModelDetails'][0]['reportData'])){
                //     // Create pdf and Upload to S3
                //     // Not Need to generate PDF here
                //     // $this->createPDFAndSavetoS3($reportResponse['reportResponseModelDetails'][0]['reportData'], $requestData['screeningRequestApplicantId']);
                //     $saveData['is_report_generated'] = 0;
                // }
                // $srRequestSave = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $requestData['screeningRequestApplicantId']])->update($saveData);
                $existingApplicant = SClearanceScreeningRequestList::where('applicant_id', $requestData['applicantId'])->orderBy('id', 'desc')->get()->toArray();
                $srRequestSave = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $requestData['screeningRequestApplicantId']])->first();
                if (! empty($existingApplicant) && ! empty($srRequestSave)) {
                    $srRequestSave->applicant_id = $existingApplicant[0]['applicant_id'];
                    $srRequestSave->screening_request_id = $existingApplicant[0]['screening_request_id'];
                    $srRequestSave->screening_request_applicant_id = $existingApplicant[0]['screening_request_applicant_id'];
                    $srRequestSave->exam_id = $existingApplicant[0]['exam_id'];
                    $srRequestSave->is_report_generated = $existingApplicant[0]['is_report_generated'];
                    $srRequestSave->is_manual_verification = $existingApplicant[0]['is_manual_verification'];
                    $srRequestSave->date_sent = $existingApplicant[0]['date_sent'];
                    $srRequestSave->report_date = $existingApplicant[0]['report_date'];
                    $srRequestSave->status = $existingApplicant[0]['status'];
                    $srRequestSave->approved_declined_by = $existingApplicant[0]['approved_declined_by'];
                    $srRequestSave->exam_attempts = $existingApplicant[0]['exam_attempts'];
                    $srRequestSave->plan_id = $existingApplicant[0]['plan_id'];
                    $srRequestSave->save();
                }

                return response()->json([
                    'ApiName' => 'Background Verification Exam',
                    'status' => true,
                    'message' => 'Your are already verified',
                    'verified' => true,
                    'apiResponse' => @$backgroundVerification,
                ], 200);
            } elseif (isset($backgroundVerification['name']) && $backgroundVerification['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'Background Verification Exam',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$backgroundVerification,
                ], 400);
            } else {
                return response()->json([
                    'ApiName' => 'Background Verification Exam',
                    'status' => false,
                    'apiResponse' => @$backgroundVerification,
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong'], 400);
        }
    }

    /**
     * @method background_verification_exam_answers
     * This is used to submit background verification exam answers
     */
    public function background_verification_exam_answers(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'examId' => 'required',
                    'screeningRequestApplicantId' => 'required',
                    'answers' => 'required',
                ]
            );

            $requestData = $request->all();
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $examDetails = SClearanceScreeningRequestList::select('id', 'exam_attempts')->where(['exam_id' => $requestData['examId']])->get()->toArray();
            if (isset($examDetails[0]['exam_attempts']) && $examDetails[0]['exam_attempts'] < 50) {
                $backgroundVerification = $this->backgroundVerificationExamAnswer($requestData);
                if (isset($backgroundVerification['result']) && $backgroundVerification['result'] == 'ManualVerificationRequired') {

                    $srRequestSave = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $requestData['screeningRequestApplicantId']])->update([
                        'exam_id' => $backgroundVerification['examId'],
                        // 'status' => 'Manual Verification Pending', // 3
                        'is_manual_verification' => 1,
                    ]);

                    $sreening_request = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $requestData['screeningRequestApplicantId']])->first();
                    $sreening_request->status = 'Manual Verification Pending';
                    $sreening_request->save(); // Added for activity log

                    return response()->json([
                        'ApiName' => 'Background Verification Exam',
                        'status' => true,
                        'message' => 'We are unable to verify your identity online. Please call customer support at 888.710.0272 for assistance with completing your screening over the phone.',
                        'manual_authentication' => 1,
                        'data' => $backgroundVerification,
                    ], 200);
                } elseif (isset($backgroundVerification['name']) && $backgroundVerification['name'] == 'UnauthorizedAccess') {
                    $this->sendMailforUnAuthorized();

                    return response()->json([
                        'ApiName' => 'Identity Verification Answer API',
                        'status' => false,
                        'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                        'apiResponse' => @$backgroundVerification,
                    ], 400);
                }

                if (isset($backgroundVerification['examId']) && ! empty(($backgroundVerification['examId']))) {
                    if ($backgroundVerification['result'] == 'Failed') {
                        $srRequestSave = SClearanceScreeningRequestList::where(['exam_id' => $requestData['examId']])->update([
                            'exam_attempts' => DB::raw('exam_attempts + 1'),
                            // 'status' => 'Verification Failed' // 5
                        ]);

                        $sreening_request = SClearanceScreeningRequestList::where(['exam_id' => $requestData['examId']])->first();
                        $sreening_request->status = 'Verification Failed';
                        $sreening_request->save(); // Added for activity log

                        return response()->json([
                            'ApiName' => 'Identity Verification Answer API',
                            'status' => false,
                            'message' => 'Verification Failed',
                            'apiResponse' => @$backgroundVerification,
                        ], 200);
                    } else {

                        $ApplicantReportsResponse = $this->postApplicantReport($requestData);
                        $postReportStatus = true;
                        if (isset($ApplicantReportsResponse['message']) && ! empty($ApplicantReportsResponse['message'])) {
                            $postReportStatus = false;
                        }

                        $saveData = [
                            'exam_attempts' => DB::raw('exam_attempts + 1'),
                        ];
                        $status = 'Report Pending';

                        if (isset($ApplicantReportsResponse['applicantStatus'])) {

                            $reportResponse = $this->getScreeningReports($requestData['screeningRequestApplicantId']);

                            if (isset($reportResponse['reportResponseModelDetails']) && isset($reportResponse['reportResponseModelDetails'][0]['reportData'])) {

                                $saveData = [
                                    'exam_attempts' => DB::raw('exam_attempts + 1'),
                                    'report_date' => date('Y-m-d'),
                                    'report_expiry_date' => date('Y-m-d', strtotime('+'.$reportResponse['reportsExpireNumberOfDays'].' days')),
                                    'is_report_generated' => 1,
                                ];

                                $status = 'Approval Pending';
                            }

                        }

                        $srRequestSave = SClearanceScreeningRequestList::where(['exam_id' => $requestData['examId']])->update($saveData);

                        $sreening_request = SClearanceScreeningRequestList::where(['exam_id' => $requestData['examId']])->first();
                        $sreening_request->status = $status;
                        $sreening_request->save(); // Added for activity log

                        // Add details to sclearance mediator server
                        $this->addToSClearanceServer($requestData);

                        return response()->json([
                            'ApiName' => 'Identity Verification Answer API',
                            'status' => true,
                            'message' => 'Verification SuccessFul',
                            'postReportStatus' => $postReportStatus,
                            'postReportResponse' => @$ApplicantReportsResponse,
                            'apiResponse' => @$backgroundVerification,
                        ], 200);
                    }
                } elseif (isset($backgroundVerification['name']) && $backgroundVerification['name'] == 'UnauthorizedAccess') {
                    $this->sendMailforUnAuthorized();

                    return response()->json([
                        'ApiName' => 'Identity Verification Answer API',
                        'status' => false,
                        'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                        'apiResponse' => @$backgroundVerification,
                    ], 400);
                } else {
                    return response()->json([
                        'ApiName' => 'Identity Verification Answer API',
                        'status' => false,
                        'apiResponse' => @$backgroundVerification,
                    ], 400);
                }
            } else {
                return response()->json([
                    'ApiName' => 'Identity Verification Answer API',
                    'status' => false,
                    'exam_attempts' => 7,
                    'message' => 'You have reached your exam attempts limit',
                ], 400);
            }

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong'], 400);
        }
    }

    /**
     * @method update_employer
     * This method is used to update employer from integration update innfo
     */
    public function update_employer(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'employer_id' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $requestData = $request->all();
            $employerResponse = $this->updateEmployer($requestData);
            if (isset($employerResponse['name']) && $employerResponse['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'update s-clearance employer',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$employerResponse,
                ], 400);
            } elseif (isset($employerResponse['errors']) && ! empty($employerResponse['errors'])) {
                return response()->json([
                    'ApiName' => 'update s-clearance employer',
                    'status' => false,
                    'message' => $employerResponse['errors'][0]['message'],
                    'apiResponse' => @$employerResponse,
                ], 400);
            } elseif (isset($employerResponse['message']) && ! empty($employerResponse['message'])) {
                return response()->json([
                    'ApiName' => 'update s-clearance employer',
                    'status' => false,
                    'message' => $employerResponse['message'],
                    'apiResponse' => @$employerResponse,
                ], 400);
            } else {
                return response()->json([
                    'ApiName' => 'update s-clearance employer',
                    'status' => true,
                    'message' => 'Updated s-clearance Successfully',
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'new clearance - Screening Request',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method get_employer
     * This method is used to get employer details from transunion
     */
    public function get_employer($employer_id): JsonResponse
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $employerResponse = $this->getEmployer($employer_id);
            if (isset($employerResponse['name']) && $employerResponse['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'get employer',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$employerResponse,
                ], 400);
            } elseif (isset($employerResponse['errors']) && ! empty($employerResponse['errors'])) {
                return response()->json([
                    'ApiName' => 'get employer',
                    'status' => false,
                    'message' => $employerResponse['errors'][0]['message'],
                    'apiResponse' => @$employerResponse,
                ], 400);
            } elseif (isset($employerResponse['message']) && ! empty($employerResponse['message'])) {
                return response()->json([
                    'ApiName' => 'get employer',
                    'status' => false,
                    'message' => $employerResponse['message'],
                    'apiResponse' => @$employerResponse,
                ], 400);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'Employer info',
                    'data' => @$employerResponse,
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'get employer',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method get_all_screening_requests_list
     * This method is used to get all the screening request list of employer
     */
    public function get_all_screening_requests_list_old(Request $request): JsonResponse
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'employer_id' => 'required',
                'page_number' => 'required',
                'page_size' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $employer_id = $input['employer_id'];
        $pageNumber = $input['page_number'];
        $pageSize = $input['page_size'];

        $recipient_type = '';
        if (isset($input['recipient_type']) && ! empty($input['recipient_type'])) {
            $recipient_type = $input['recipient_type'];
        }

        $search_text = '';
        if (isset($input['search_text']) && ! empty($input['search_text'])) {
            $search_text = $input['search_text'];
        }

        if (isset($input['column_name']) && ! empty($input['column_name'])) {
            $column = $input['column_name'];
        } else {
            $column = 'id';
        }

        if (isset($input['sort_order']) && ! empty($input['sort_order'])) {
            $sort = $input['sort_order'];
        } else {
            $sort = 'desc';
        }

        $pageSize = $input['page_size'];
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $SrResponsee = $this->getEmployerScreeningRequests($employer_id, $pageNumber, $pageSize);
            if (isset($SrResponsee['message']) && ! empty($SrResponsee['message'])) {
                return response()->json([
                    'status' => false,
                    'apiResponse' => @$SrResponsee,
                ], 400);
            } elseif (isset($SrResponsee['name']) && $SrResponsee['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'get all screening requests',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$SrResponsee,
                ], 400);
            } else {
                $SRList = [];
                if (! empty($SrResponsee)) {
                    foreach ($SrResponsee as $list) {
                        if (isset($list['screeningRequestApplicant']) && ! empty($list['screeningRequestApplicant'])) {
                            if ($recipient_type == 'External') {
                                $SrDetails = SClearanceScreeningRequestList::with('positionDetail')
                                    ->where([
                                        'screening_request_applicant_id' => $list['screeningRequestApplicant']['screeningRequestApplicantId'],
                                        'user_type' => $recipient_type,
                                    ])
                                    ->where(function ($query) use ($search_text) {
                                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                                            ->orWhere('screening_request_id', 'LIKE', '%'.$search_text.'%');
                                    });

                                if (isset($input['position_id']) && ! empty($input['position_id'])) {
                                    $position_id = $input['position_id'];
                                    $SrDetails->where('position_id', '=', $position_id);
                                }

                                if (isset($input['office_id']) && ! empty($input['office_id'])) {
                                    $office_id = $input['office_id'];
                                    $SrDetails->where('office_id', '=', $office_id);
                                }

                                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                                    $date_sent = $input['date_sent'];
                                    $SrDetails->where('date_sent', '=', $date_sent);
                                }

                                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                                    $report_date = $input['report_date'];
                                    $SrDetails->where('report_date', '=', $report_date);
                                }

                                if (isset($input['status']) && ! empty($input['status'])) {
                                    $status = $input['status'];
                                    $SrDetails->where('status', 'like', "%$status%");
                                    // $SrDetails->where('status', '=', $status);
                                }

                                $SrDetails->orderBy($column, $sort);
                                $SrDetails = $SrDetails->get()->toArray();
                            } elseif ($recipient_type == 'Internal') {
                                $SrDetails = SClearanceScreeningRequestList::with('positionDetail')
                                    ->where([
                                        'screening_request_applicant_id' => $list['screeningRequestApplicant']['screeningRequestApplicantId'],
                                    ])
                                    ->where('user_type', '<>', 'External')
                                    ->where(function ($query) use ($search_text) {
                                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                                            ->orWhere('screening_request_id', 'LIKE', '%'.$search_text.'%');
                                    });

                                if (isset($input['position_id']) && ! empty($input['position_id']) && $input['position_id'] != 'all') {
                                    $position_id = $input['position_id'];
                                    $SrDetails->where('position_id', '=', $position_id);
                                }

                                if (isset($input['office_id']) && ! empty($input['office_id']) && $input['office_id'] != 'all') {
                                    $office_id = $input['office_id'];
                                    $SrDetails->where('office_id', '=', $office_id);
                                }

                                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                                    $date_sent = $input['date_sent'];
                                    $SrDetails->where('date_sent', '=', $date_sent);
                                }

                                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                                    $report_date = $input['report_date'];
                                    $SrDetails->where('report_date', '=', $report_date);
                                }

                                if (isset($input['status']) && ! empty($input['status'])) {
                                    $status = $input['status'];
                                    $SrDetails->where('status', 'like', "%$status%");
                                }

                                $SrDetails->orderBy($column, $sort);
                                $SrDetails = $SrDetails->get()->toArray();
                            } else {
                                $SrDetails = SClearanceScreeningRequestList::with('positionDetail')
                                    ->where([
                                        'screening_request_applicant_id' => $list['screeningRequestApplicant']['screeningRequestApplicantId'],
                                    ])
                                    ->where(function ($query) use ($search_text) {
                                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                                            ->orWhere('screening_request_id', 'LIKE', '%'.$search_text.'%');
                                    });

                                if (isset($input['position_id']) && ! empty($input['position_id']) && $input['position_id'] != 'all') {
                                    $position_id = $input['position_id'];
                                    $SrDetails->where('position_id', '=', $position_id);
                                }

                                if (isset($input['office_id']) && ! empty($input['office_id']) && $input['office_id'] != 'all') {
                                    $office_id = $input['office_id'];
                                    $SrDetails->where('office_id', '=', $office_id);
                                }

                                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                                    $date_sent = $input['date_sent'];
                                    $SrDetails->where('date_sent', '=', $date_sent);
                                }

                                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                                    $report_date = $input['report_date'];
                                    $SrDetails->where('report_date', '=', $report_date);
                                }

                                if (isset($input['status']) && ! empty($input['status'])) {
                                    $status = $input['status'];
                                    $SrDetails->where('status', 'like', "%$status%");
                                }

                                $SrDetails->orderBy($column, $sort);
                                $SrDetails = $SrDetails->get()->toArray();
                            }

                            if (! empty($SrDetails)) {
                                $office = '';
                                if (isset($SrDetails[0]['office_id']) && ! empty($SrDetails[0]['office_id'])) {
                                    $office = Locations::select('office_name')->where('id', $SrDetails[0]['office_id'])->first();
                                }

                                $image = '';
                                if ($SrDetails[0]['user_type'] == 'Onboarding') {
                                    $userData = OnboardingEmployees::select('image')->where('id', '=', $SrDetails[0]['user_type_id'])->get()->toArray();
                                    $image = @$userData[0]['image'];
                                } elseif ($SrDetails[0]['user_type'] == 'Hired') {
                                    $userData = User::select('image')->where('id', '=', $SrDetails[0]['user_type_id'])->get()->toArray();
                                    $image = @$userData[0]['image'];
                                }

                                $newList = [
                                    'applicantId' => @$list['screeningRequestApplicant']['applicantId'],
                                    'screening_request_id' => $list['screeningRequestId'],
                                    'screeningRequestApplicantId' => @$list['screeningRequestApplicant']['screeningRequestApplicantId'],
                                    'date_sent' => @$SrDetails[0]['date_sent'],
                                    'report_date' => @$SrDetails[0]['report_date'],
                                    // ((isset($list['screeningRequestApplicant']['applicantStatus']) && $list['screeningRequestApplicant']['applicantStatus'] == 'ReadyForReportRequest')) ? $list['modifiedOn'] : '',
                                    'applicant_status' => @$list['screeningRequestApplicant']['applicantStatus'],
                                    'first_name' => @$list['screeningRequestApplicant']['applicantFirstName'],
                                    'last_name' => @$list['screeningRequestApplicant']['applicantLastName'],
                                    'stage' => @$SrDetails[0]['user_type'],
                                    'status' => @$SrDetails[0]['status'],
                                    'position' => @$SrDetails[0]['position_detail']['position_name'],
                                    'office' => @$office->office_name,
                                    'position_id' => @$SrDetails[0]['position_id'],
                                    'office_id' => @$SrDetails[0]['office_id'],
                                    'id' => @$SrDetails[0]['id'],
                                    'image' => $image,
                                ];
                                array_push($SRList, $newList);
                            }
                        }
                    }

                    return response()->json([
                        'ApiName' => 'get all screening requests',
                        'status' => true,
                        'message' => 'Screening Request List',
                        'data' => @$SRList,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'get all screening requests',
                        'status' => false,
                        'message' => 'Screening Requests Not Found',
                        'apiResponse' => @$SrResponsee,
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'ApiName' => 'new clearance - Screening Request',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    public function get_all_screening_requests_list(Request $request): JsonResponse
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                // 'employer_id' => 'required',
                'page' => 'required',
                'page_size' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        // $employer_id = $input['employer_id'];

        if (isset($request->page_size) && $request->page_size != '') {
            $perpage = $request->page_size;
        } else {
            $perpage = 10;
        }

        $recipient_type = '';
        if (isset($input['recipient_type']) && ! empty($input['recipient_type'])) {
            $recipient_type = $input['recipient_type'];
        }

        $search_text = '';
        if (isset($input['search_text']) && ! empty($input['search_text'])) {
            $search_text = $input['search_text'];
        }

        if (isset($input['column_name']) && ! empty($input['column_name'])) {
            $column = $input['column_name'];
        } else {
            $column = 'id';
        }

        if (isset($input['sort_order']) && ! empty($input['sort_order'])) {
            $sort = $input['sort_order'];
        } else {
            $sort = 'desc';
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            if ($recipient_type == 'External') {
                $SrDetails = SClearanceScreeningRequestList::with('positionDetail')
                    ->where([
                        'user_type' => $recipient_type,
                    ]);

                if (isset($input['search_text']) && ! empty($input['search_text'])) {
                    $SrDetails->where(function ($query) use ($search_text) {
                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                            ->orWhere('screening_request_id', 'LIKE', '%'.$search_text.'%');
                    });
                }

                if (isset($input['position_id']) && ! empty($input['position_id'])) {
                    $position_id = $input['position_id'];
                    $SrDetails->where('position_id', '=', $position_id);
                }

                if (isset($input['office_id']) && ! empty($input['office_id'])) {
                    $office_id = $input['office_id'];
                    $SrDetails->where('office_id', '=', $office_id);
                }

                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                    $date_sent = $input['date_sent'];
                    $SrDetails->where('date_sent', '=', $date_sent);
                }

                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                    $report_date = $input['report_date'];
                    $SrDetails->where('report_date', '=', $report_date);
                }

                if (isset($input['status']) && ! empty($input['status'])) {
                    $status = $input['status'];
                    // $SrDetails->where('status', 'like', "%$status%");
                    // $SrDetails->where('status', '=', $status);
                    if ($status == 'Approval Pending') {
                        $SrDetails->where('status', 'like', "%$status%");
                        $SrDetails->where('is_report_generated', 1);
                    } elseif ($status == 'Report Pending') {
                        $SrDetails->where('status', 'like', '%Approval Pending%');
                        $SrDetails->where('is_report_generated', 0);
                    } else {
                        $SrDetails->where('status', 'like', "%$status%");
                    }
                }

                $SrDetails->orderBy($column, $sort);
                $SrDetails = $SrDetails->get()->toArray();
            } elseif ($recipient_type == 'Internal') {
                $SrDetails = SClearanceScreeningRequestList::with('positionDetail')
                    ->where('user_type', '<>', 'External');
                if (isset($input['search_text']) && ! empty($input['search_text'])) {
                    $SrDetails->where(function ($query) use ($search_text) {
                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                            ->orWhere('screening_request_id', 'LIKE', '%'.$search_text.'%');
                    });
                }

                if (isset($input['position_id']) && ! empty($input['position_id']) && $input['position_id'] != 'all') {
                    $position_id = $input['position_id'];
                    $SrDetails->where('position_id', '=', $position_id);
                }

                if (isset($input['office_id']) && ! empty($input['office_id']) && $input['office_id'] != 'all') {
                    $office_id = $input['office_id'];
                    $SrDetails->where('office_id', '=', $office_id);
                }

                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                    $date_sent = $input['date_sent'];
                    $SrDetails->where('date_sent', '=', $date_sent);
                }

                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                    $report_date = $input['report_date'];
                    $SrDetails->where('report_date', '=', $report_date);
                }

                if (isset($input['status']) && ! empty($input['status'])) {
                    $status = $input['status'];
                    // $SrDetails->where('status', 'like', "%$status%");

                    if ($status == 'Approval Pending') {
                        $SrDetails->where('status', 'like', "%$status%");
                        $SrDetails->where('is_report_generated', 1);
                    } elseif ($status == 'Report Pending') {
                        $SrDetails->where('status', 'like', '%Approval Pending%');
                        $SrDetails->where('is_report_generated', 0);
                    } else {
                        $SrDetails->where('status', 'like', "%$status%");
                    }
                }

                $SrDetails->orderBy($column, $sort);
                $SrDetails = $SrDetails->get()->toArray();
            } else {
                $SrDetails = SClearanceScreeningRequestList::with('positionDetail');
                if (isset($input['search_text']) && ! empty($input['search_text'])) {
                    $SrDetails->where(function ($query) use ($search_text) {
                        $query->where('first_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search_text.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                            ->orWhere('screening_request_id', 'LIKE', '%'.$search_text.'%');
                    });
                }

                if (isset($input['position_id']) && ! empty($input['position_id']) && $input['position_id'] != 'all') {
                    $position_id = $input['position_id'];
                    $SrDetails->where('position_id', '=', $position_id);
                }

                if (isset($input['office_id']) && ! empty($input['office_id']) && $input['office_id'] != 'all') {
                    $office_id = $input['office_id'];
                    $SrDetails->where('office_id', '=', $office_id);
                }

                if (isset($input['date_sent']) && ! empty($input['date_sent'])) {
                    $date_sent = $input['date_sent'];
                    $SrDetails->where('date_sent', '=', $date_sent);
                }

                if (isset($input['report_date']) && ! empty($input['report_date'])) {
                    $report_date = $input['report_date'];
                    $SrDetails->where('report_date', '=', $report_date);
                }

                if (isset($input['status']) && ! empty($input['status'])) {
                    $status = $input['status'];
                    $SrDetails->where('status', 'like', "%$status%");
                }

                $SrDetails->orderBy($column, $sort);
                $SrDetails = $SrDetails->get()->toArray();
            }

            if (! empty($SrDetails)) {
                $SRList = [];
                foreach ($SrDetails as $list) {
                    $office = '';
                    if (isset($list['office_id']) && ! empty($list['office_id'])) {
                        $office = Locations::select('office_name')->where('id', $list['office_id'])->first();
                    }

                    $image = '';
                    if ($list['user_type'] == 'Onboarding') {
                        $userData = OnboardingEmployees::select('image')->where('id', '=', $list['user_type_id'])->get()->toArray();
                        if (isset($userData[0]['image']) && $userData[0]['image'] != null) {
                            $image = s3_getTempUrl(config('app.domain_name').'/'.$userData[0]['image']);
                        } else {
                            $image = @$userData[0]['image'];
                        }
                    } elseif ($list['user_type'] == 'Hired') {
                        $userData = User::select('image')->where('id', '=', $list['user_type_id'])->get()->toArray();
                        if (isset($userData[0]['image']) && $userData[0]['image'] != null) {
                            $image = s3_getTempUrl(config('app.domain_name').'/'.$userData[0]['image']);
                        } else {
                            $image = @$userData[0]['image'];
                        }
                    }

                    $is_application_expired = 0;
                    $is_report_expired = 0;
                    if ($list['status'] == 'Application Expired') {
                        $is_application_expired = 1;
                    }

                    if ($list['status'] == 'Report Expired') {
                        $is_report_expired = 1;
                    }

                    $approverData = [];
                    if (isset($list['approved_declined_by']) && ! empty($list['approved_declined_by'])) {
                        $approverData = User::where('id', $list['approved_declined_by'])->select(DB::raw("CONCAT(first_name, ' ', last_name) as user_name"), 'image')->first();
                    }

                    $newList = [
                        'id' => @$list['id'],
                        'image' => $image,
                        'stage' => @$list['user_type'],
                        'position' => @$list['position_detail']['position_name'],
                        'office' => @$office->office_name,
                        'position_id' => @$list['position_id'],
                        'office_id' => @$list['office_id'],
                        'status' => $list['status'],
                        'is_report_generated' => @$list['is_report_generated'],
                        'date_sent' => @$list['date_sent'],
                        'report_date' => @$list['report_date'],
                        'is_application_expired' => $is_application_expired,
                        'is_report_expired' => $is_report_expired,
                        'approved_declined_user_name' => @$approverData->user_name,
                        'first_name' => @$list['first_name'],
                        'last_name' => @$list['last_name'],
                        'screening_request_id' => @$list['screening_request_id'],
                        'screeningRequestApplicantId' => @$list['screening_request_applicant_id'],
                    ];

                    if (! empty($list['screening_request_id']) && $list['is_report_generated'] == 0 && $list['screening_request_applicant_id'] == 0) {
                        $SrResponsee = $this->getScreeningRequest($list['screening_request_id']);
                        if (isset($SrResponsee['screeningRequestId']) && ! empty($SrResponsee['screeningRequestId'])) {
                            $newList['applicantId'] = @$SrResponsee['screeningRequestId']['screeningRequestApplicant']['applicantId'];
                            $newList['screeningRequestApplicantId'] = @$SrResponsee['screeningRequestApplicant']['screeningRequestApplicantId'];
                            $newList['applicant_status'] = @$SrResponsee['screeningRequestApplicant']['applicantStatus'];
                            $newList['first_name'] = @$SrResponsee['screeningRequestApplicant']['applicantFirstName'];
                            $newList['last_name'] = @$SrResponsee['screeningRequestApplicant']['applicantLastName'];
                        }
                    }

                    array_push($SRList, $newList);
                }

                $data = paginate($SRList, $perpage);

                return response()->json([
                    'ApiName' => 'get all screening requests',
                    'status' => true,
                    'message' => 'Screening Request List',
                    'data' => $data,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'get all screening requests',
                    'status' => false,
                    'message' => 'Screening Requests Not Found',
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'new clearance - Screening Request',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method cancel_screening_request
     * This method is used to cancel a screening request by employer
     */
    public function cancel_screening_request(Request $request): JsonResponse
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'screening_request_applicant_id' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $screeningRequestApplicantId = $input['screening_request_applicant_id'];

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $cancelSrResponsee = $this->cancelScreeningRequest($screeningRequestApplicantId);
            if (isset($cancelSrResponsee['name']) && $cancelSrResponsee['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'Cancel Screening Request',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$cancelSrResponsee,
                ], 400);
            } elseif (isset($cancelSrResponsee['errors']) && ! empty($cancelSrResponsee['errors'])) {
                return response()->json([
                    'ApiName' => 'Cancel Screening Request',
                    'status' => false,
                    'message' => $cancelSrResponsee['errors'][0]['message'],
                    'apiResponse' => @$cancelSrResponsee,
                ], 400);
            } elseif (isset($cancelSrResponsee['message']) && ! empty($cancelSrResponsee['message'])) {
                return response()->json([
                    'ApiName' => 'Cancel Screening Request',
                    'status' => false,
                    'message' => $cancelSrResponsee['message'],
                    'apiResponse' => @$cancelSrResponsee,
                ], 400);
            } else {
                // $srRequestSave = SClearanceScreeningRequestList::where(['screening_request_applicant_id' => $screeningRequestApplicantId])->update(['status' => 'Canceled']); // 7
                $srUpdate = SClearanceScreeningRequestList::where('screening_request_applicant_id', $screeningRequestApplicantId)->first(); // added for activity log
                $srUpdate->status = 'Canceled';
                $srUpdate->save();

                return response()->json([
                    'ApiName' => 'Cancel Screening Request',
                    'status' => true,
                    'message' => 'Screening Request Canceled Successfully',
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'Cancel Screening Request',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    // /**
    //  * @method get_all_screening_requests_list
    //  * This method is used to get all the screening request list of employer
    //  */
    // public function generated_reports_count(Request $request){
    //     $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
    //     if($crmSetting){
    //         $generatedReportCount = SClearanceScreeningRequestList::count('id')->where(['status' => 'Completed']);
    //         return response()->json([
    //             'ApiName' => 'get all screening requests',
    //             'status' => true,
    //             'message' => 'Generated report count',
    //             'reportCount' => @$generatedReportCount
    //         ], 200);
    //     }else{
    //         return response()->json([
    //             'ApiName' => 'new clearance - Screening Request',
    //             'status' => false,
    //             'message' => 'Please Activate S-Clearance First',
    //         ], 400);
    //     }
    // }

    /**
     * @method sendNewClearanceMail
     * This is a common code to send mail for background check
     */
    public function sendNewClearanceMail($requestData)
    {
        try {
            $mailResponse = '';
            $sendMail = 0;
            $existingBC = SClearanceScreeningRequestList::where(['email' => $requestData['email']])->orderBy('id', 'desc')->get()->toArray();
            if (count($existingBC) > 0) {
                if (! empty($existingBC[0]['screening_request_applicant_id']) && ! empty($existingBC[0]['applicant_id'])) {
                    $validateSR = $this->validateScreeningRequest($existingBC[0]['screening_request_applicant_id'], $existingBC[0]['applicant_id']);
                    if ($validateSR == 'Verified') {
                        return ['status' => false, 'message' => 'This user is already verified and report generated'];
                    } else {
                        $sendMail = 1;
                    }
                }

                $statuArr = ['Mail Sent', 'Pending Verification', 'In Progress', 'Manual Verification Pending'];
                if (in_array($existingBC[0]['status'], $statuArr)) {
                    return ['status' => false, 'message' => 'This user has already an open Background Verification Check', 'sr_status' => $existingBC[0]['status']];
                } else {
                    $sendMail = 1;
                }
            } else {
                $sendMail = 1;
            }

            if ($sendMail == 1) {
                $check_domain_setting = DomainSetting::check_domain_setting($requestData['email']);
                if ($check_domain_setting['status'] == true) {
                    $srRequestSave = SClearanceScreeningRequestList::create([
                        'email' => $requestData['email'],
                        'user_type' => $requestData['user_type'],
                        'user_type_id' => @$requestData['user_type_id'],
                        'position_id' => @$requestData['position_id'],
                        'office_id' => @$requestData['office_id'],
                        'first_name' => $requestData['first_name'],
                        'middle_name' => @$requestData['middle_name'],
                        'last_name' => $requestData['last_name'],
                        'description' => (isset($requestData['description']) ? $requestData['description'] : 'Background Check'),
                        'status' => 'Mail Sent', // 1
                    ]);
                    $srRequestSave->save();
                    $request_id = $srRequestSave->id;
                    $mailData['subject'] = 'Request for Background Check';
                    $mailData['email'] = $requestData['email'];
                    $mailData['request_id'] = $request_id;
                    // $encryptedRequestId = Crypt::encrypt($request_id);
                    $encryptedRequestId = encryptData($request_id);
                    $mailData['encrypted_request_id'] = $encryptedRequestId;
                    $mailData['url'] = $requestData['frontend_url'];
                    $mailData['template'] = view('mail.backgroundCheckMail', compact('mailData'));
                    $mailResponse = $this->sendEmailNotification($mailData);
                    if ($mailResponse) {
                        return ['status' => true, 'message' => 'Mail sent', 'encryptedRequestId' => $encryptedRequestId];
                    } else {
                        return ['status' => false, 'message' => 'Error in sending mail, Please check domain settiings.'];
                    }
                } else {
                    return ['status' => false, 'message' => "Domain setting isn't allowed to send e-mail on this domain."];

                }
            }
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'Something went wrong', 'error' => $e->getMessage()];
        }
    }

    /**
     * @method get_user_details
     * This method is used to get the details added in new clearance to show in mail landing page
     */
    public function get_user_details($encrypted_request_id): JsonResponse
    {
        try {
            // $request_id = Crypt::decrypt($encrypted_request_id);
            $request_id = decryptData($encrypted_request_id);
            // $userData = SClearanceScreeningRequestList::select('id', 'first_name', 'last_name', 'email')->where('id', '=', $request_id)->get()->toArray();
            $userData = SClearanceScreeningRequestList::where('id', '=', $request_id)->get()->toArray();

            return response()->json([
                'ApiName' => 'background check details',
                'status' => true,
                'data' => $userData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'background check details',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function add_employer(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'plan_id' => 'required',
                'email' => 'required',
                'first_name' => 'required',
                'last_name' => 'required',
                'phone_number' => 'required',
                'phone_type' => 'required',
                'business_name' => 'required',
                'address_line_1' => 'required',
                'address_line_2' => 'required',
                // 'address_line_3' => 'required',
                // 'address_line_4' => 'required',
                'locality' => 'required',
                'region' => 'required',
                'postal_code' => 'required',
                // 'country' => 'required',
                'accepted_terms_conditions' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        if ($crmSetting) {
            $requestData = $request->all();
            $employerResponse = $this->addEmployer($requestData);

            if (isset($employerResponse['employerId']) && ! empty($employerResponse['employerId'])) {
                $configureOldData = SClearanceConfiguration::select('id')->get()->toArray();
                if (empty($configureOldData)) {
                    $configureData = SClearanceConfiguration::create([
                        'position_id' => null,
                        'hiring_status' => null,
                        'is_mandatory' => 1,
                        'is_approval_required' => 1,
                    ]);
                    $configureData->save();
                }

                $employer_id = isset($employerResponse['employerId']) ? $employerResponse['employerId'] : '';

                return response()->json([
                    'status' => true,
                    'message' => 'Successfully',
                    'employer_id' => $employer_id,
                ], 200);
            } elseif (isset($employerResponse['name']) && $employerResponse['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'addEmployer',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$employerResponse,
                ], 400);
            } else {
                if (isset($employerResponse['errors']) && ! empty($employerResponse['errors'])) {
                    return response()->json([
                        'ApiName' => 'addEmployer',
                        'status' => false,
                        'message' => $employerResponse['errors'][0]['message'],
                        'apiResponse' => @$employerResponse,
                    ], 400);
                } else {
                    return response()->json([
                        'ApiName' => 'addEmployer',
                        'status' => false,
                        'message' => $employerResponse['message'],
                        'apiResponse' => @$employerResponse,
                    ], 400);
                }
            }
        } else {
            return response()->json([
                'ApiName' => 'addEmployer',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }
    }

    /**
     * @method SClearance_office_and_position_wise_user_list
     *  This is used to fetch lead, user, onboarding employees data
     *  */
    public function SClearance_office_and_position_wise_user_list(Request $request): JsonResponse
    {
        $ApiName = 'office_and_position_wise_user_list_new';
        $status_code = 200;
        $status = true;
        $message = 'User list based on Office and Position';
        $user_data = [];
        $search_type = (isset($request->search_type) && $request->search_type == 'textfield') ? $request->search_type : '';
        $search_text = (isset($request->search_text)) ? $request->search_text : '';

        try {
            $office_id = isset($request->office_id) ? $request->office_id : 'All';
            $position_id = isset($request->position_id) ? $request->position_id : 'All';

            /* User Data */
            $user_data_query = User::whereNotNull('office_id')
                ->select(
                    'id as user_type_id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'email',
                    'sub_position_id as position_id',
                    'office_id',
                    DB::raw("'Hired' as user_type"),
                    'image'
                )
                ->with(['office' => function ($query) {
                    $query->select('id', 'office_name', 'type');
                }])
                ->with(['positionDetail' => function ($query) {
                    $query->select('id', 'position_name');
                }])
                ->orderBy('office_id', 'ASC');

            if ((int) $position_id > 0 && empty($search_type)) {
                $user_data_query = $user_data_query->where('sub_position_id', $position_id);
                // ->orWhere('position_id' , $position_id);
            }

            if ((int) $office_id > 0 && empty($search_type)) {
                $user_data_query = $user_data_query->where('office_id', $office_id);
            }

            if (! empty($search_type) && ! empty($search_text)) {
                $user_data_query = $user_data_query->where('first_name', 'like', "%$search_text%")
                    ->orWhere('last_name', 'like', "%$search_text%")
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                    ->orWhere('email', 'like', "%$search_text%");
            }

            $user_data = $user_data_query->get()->toArray();

            /* Lead Data */
            $lead_data_query = Lead::whereNotNull('office_id')
                ->select(
                    'id as user_type_id',
                    'first_name',
                    'last_name',
                    'email',
                    DB::raw("'' as position_id"),
                    'office_id',
                    DB::raw("'Lead' as user_type"),
                    DB::raw("'' as image")
                )
                ->orderBy('office_id', 'ASC');

            if ((int) $office_id > 0 && empty($search_type)) {
                $lead_data_query = $lead_data_query->where('office_id', $office_id);
            }

            if (! empty($search_type) && ! empty($search_text)) {
                $lead_data_query = $lead_data_query->where(function ($query) use ($search_text) {
                    $query->where('first_name', 'like', "%$search_text%")
                        ->orWhere('last_name', 'like', "%$search_text%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                        ->orWhere('email', 'like', "%$search_text%");
                });
            }

            $lead_data = $lead_data_query->whereNotIn('status', ['Hired', 'Rejected'])->get()->toArray();

            /* Onboarding Employee Data */
            $onb_emp_data_query = OnboardingEmployees::whereNotNull('office_id')
                ->select(
                    'id as user_type_id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'email',
                    'sub_position_id as position_id',
                    'office_id',
                    DB::raw("'Onboarding' as user_type"),
                    'image'
                )
                ->with(['office' => function ($query) {
                    $query->select('id', 'office_name', 'type');
                }])
                ->with(['positionDetail' => function ($query) {
                    $query->select('id', 'position_name');
                }])
                ->orderBy('office_id', 'ASC');

            if ((int) $position_id > 0 && empty($search_type)) {
                $onb_emp_data_query = $onb_emp_data_query->where('sub_position_id', $position_id);
                // ->orWhere('position_id' , $position_id);
            }

            if ((int) $office_id > 0 && empty($search_type)) {
                $onb_emp_data_query = $onb_emp_data_query->where('office_id', $office_id);
            }

            if (! empty($search_type) && ! empty($search_text)) {
                $onb_emp_data_query = $onb_emp_data_query->where(function ($query) use ($search_text) {
                    $query->where('first_name', 'like', "%$search_text%")
                        ->orWhere('last_name', 'like', "%$search_text%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search_text.'%'])
                        ->orWhere('email', 'like', "%$search_text%");
                });
            }

            $onb_emp_data = $onb_emp_data_query->whereNull('user_id')->get()->toArray();

            $allData = array_merge($user_data, $lead_data, $onb_emp_data);

        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }

        return response()->json([
            'ApiName' => $ApiName,
            'status' => $status,
            'message' => $message,
            'user_count' => count($allData),
            'data' => $allData,
        ], $status_code);
    }

    /**
     * @method createPDFAndSavetoS3
     * This method is used to create pdf of background verificationreport and save to s3
     */
    public function createPDFAndSavetoS3($reportResponse, $screeningRequestApplicantId)
    {
        // ----------------- create pdf of user information--------------------------

        $template_name = 'background_verification_report_'.$screeningRequestApplicantId;
        $generateTemplate = $template_name.'.pdf';
        $template = 'template/'.$generateTemplate;
        $string = '<!DOCTYPE html >'.$reportResponse;
        // $string = view('mail.backgroundVerificationReport',[
        //         'reportResponse' => new HtmlString($reportResponse),
        // ]);
        // echo $string;exit;
        $pdf = PDF::loadHTML($string, 'UTF-8');
        file_put_contents($template, $pdf->setPaper('A4', 'portrait')->output());
        $filePath = config('app.domain_name').'/'.$template;
        s3_upload($filePath, $pdf->setPaper('A4', 'portrait')->output());

        // $pdfPath = public_path("/template/background_verification_report_".$screeningRequestApplicantId.".pdf");
        // $report = view('mail.backgroundVerificationReport', ['reportResponse' => new HtmlString($reportResponse)]);
        // $pdf = \PDF::loadView('mail.backgroundVerificationReport',[
        //     'reportResponse' => new HtmlString($reportResponse),
        // ]);

        // $pdf->save($pdfPath);
        // $filePath = config('app.domain_name').'/'."background-verification/background_verification_report_".$screeningRequestApplicantId.".pdf";
        // $s3Data =   s3_upload($filePath,$pdfPath,true,'public');
        // $s3filePath = "https://sequifi.s3.us-west-1.amazonaws.com/". $filePath;
        // ----------------- end create pdf of user information--------------------------
    }

    /**
     * @method approve_decline_bv_report
     * this method is used to approve and decline background verification report
     */
    public function approve_decline_bv_report(Request $request): JsonResponse
    {
        try {
            $screeningResquest = SClearanceScreeningRequestList::where('screening_request_id', $request->screening_request_id)->first();
            if (! empty($screeningResquest)) {
                $msg = '';
                $updateData = [];
                if ($request->approval_status == 'approve') {
                    $status = $updateData['status'] = 'Approved'; // 8
                    $msg = 'Approved Successfully';
                } else {
                    $status = $updateData['status'] = 'Declined'; // 9
                    $msg = 'Declined Successfully';
                }
                $userid = auth()->user()->id;
                $updateData['approved_declined_by'] = $userid;
                $screeningResquest->approved_declined_by = $userid;
                $screeningResquest->status = $status;
                $screeningResquest->save(); // aadded for activity log
                // SClearanceScreeningRequestList::where('screening_request_id', $request->screening_request_id)->update($updateData);

                return response()->json([
                    'ApiName' => 'approve_decline_bv_report',
                    'status' => true,
                    'message' => $msg,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'approve_decline_bv_report',
                    'status' => false,
                    'message' => 'Record not found',
                ], 400);
            }

        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }
    }

    /**
     * @method view_report_details
     * this method is used to get view report popup required details
     */
    public function view_report_details(Request $request): JsonResponse
    {
        try {
            $screeningResquest = SClearanceScreeningRequestList::where('screening_request_applicant_id', $request->screening_request_applicant_id)->first();
            if (! empty($screeningResquest)) {
                $userData = User::where('id', $screeningResquest->approved_declined_by)->select(DB::raw('CONCAT(first_name, last_name) as user_name'), 'image')->get()->toArray();
                $data = [
                    'user' => @$userData[0],
                    'status' => $screeningResquest->status,
                ];

                return response()->json([
                    'ApiName' => 'view_report_details',
                    'status' => true,
                    'data' => $data,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'view_report_details',
                    'status' => false,
                    'message' => 'Record not found',

                ], 400);
            }

        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }
    }

    /**
     * @method get_configurations_position_based
     * this method is used to configurations for position selected
     */
    public function get_configurations_position_based(Request $request): JsonResponse
    {
        try {
            // $configurationDetails = SClearanceConfiguration::where(['position_id' => $request->position_id, 'hiring_status' => $request->hiring_status])->first();

            $configurationDetails = SClearanceConfiguration::where(['position_id' => $request->position_id])->first();
            if (empty($configurationDetails)) {
                $configurationDetails = SClearanceConfiguration::where(['position_id' => null])->first();

                return response()->json([
                    'ApiName' => 'get_configurations_position_based',
                    'status' => true,
                    'data' => $configurationDetails,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'get_configurations_position_based',
                    'status' => true,
                    'data' => $configurationDetails,

                ], 200);
            }
        } catch (Exception $error) {
            $message = $error->getMessage();

            return response()->json(['error' => $error, 'message' => $message], 400);
        }
    }

    /**
     * @method post_applicant_report
     * This is used to post applicant data for report
     */
    public function post_applicant_report(Request $request): JsonResponse
    {
        try {
            $Validator = Validator::make(
                $request->all(),
                [
                    'applicantId' => 'required',
                    'screeningRequestApplicantId' => 'required',
                ]
            );

            $requestData = $request->all();
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 400);
            }

            $ApplicantReportsResponse = $this->postApplicantReport($requestData);

            if (isset($ApplicantReportsResponse['name']) && $ApplicantReportsResponse['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'Post Applicant Report',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$ApplicantReportsResponse,
                ], 400);
            } elseif (isset($ApplicantReportsResponse['message']) && ! empty($ApplicantReportsResponse['message'])) {
                return response()->json([
                    'ApiName' => 'Post Applicant Report',
                    'status' => false,
                    'apiResponse' => @$ApplicantReportsResponse,
                ], 200);
            } else {
                $saveData = [
                    'status' => 'Approval Pending',
                    'report_date' => date('Y-m-d'),
                    'report_expiry_date' => date('Y-m-d', strtotime('+'.$reportResponse['reportsExpireNumberOfDays'].' days')),
                    'is_report_generated' => 1,
                ];

                $srRequestSave = SClearanceScreeningRequestList::where(['screening_Request_applicant_id' => $requestData['screeningRequestApplicantId']])->update($saveData);

                return response()->json([
                    'ApiName' => 'Post Applicant Report',
                    'status' => true,
                    'message' => 'Report for applicant posted successfully',
                    'apiResponse' => @$ApplicantReportsResponse,
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'Something went wrong'], 400);
        }
    }

    /**
     * @method users_billing_report_old
     * This is used to get users report of screening requests with success
     */
    public function users_billing_report_old(Request $request)
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'page' => 'required',
                'page_size' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();

        if (isset($request->page_size) && $request->page_size != '') {
            $perpage = $request->page_size;
        } else {
            $perpage = 10;
        }

        if (isset($input['column_name']) && ! empty($input['column_name'])) {
            $column = $input['column_name'];
        } else {
            $column = 'id';
        }

        if (isset($input['sort_order']) && ! empty($input['sort_order'])) {
            $sort = $input['sort_order'];
        } else {
            $sort = 'desc';
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5])->first();
        if ($crmSetting) {
            $crmData = json_decode($crmSetting->value, true);
            $employer_id = @$crmData['employer_id'];
            $currentMonth = Carbon::now()->month;
            $SrDetails = SClearanceScreeningRequestList::whereMonth('report_date', $currentMonth)
                // ->orderBy($column, $sort)
                ->get()->toArray();

            $SRList = [];
            $successReportCount = 0;
            $totalPrice = 0;

            if (! empty($SrDetails)) {
                foreach ($SrDetails as $list) {
                    $plan_price = 0;
                    $plan_id = 0;
                    $plan_name = '';
                    $planData = SClearancePlan::select('id', 'plan_name', 'price', 'bundle_id')->where('id', '=', $list['plan_id'])->get()->toArray();
                    if (! empty($planData)) {
                        $plan_price = $planData[0]['price'];
                        $plan_id = $planData[0]['id'];
                        $plan_name = $planData[0]['plan_name'];
                        $bundle_id = $planData[0]['bundle_id'];
                    }

                    $newList = [
                        'id' => $list['id'],
                        'email' => $list['email'],
                        'first_name' => $list['first_name'],
                        'last_name' => $list['last_name'],
                        'screening_request_id' => $list['screening_request_id'],
                        'bundle_id' => @$bundle_id,
                        'plan_id' => @$plan_id,
                        'plan_name' => @$plan_name,
                        'price' => @$plan_price,
                    ];

                    if (! empty($list['screening_request_id'])) {
                        $SrResponsee = $this->getScreeningRequest($list['screening_request_id']);
                        if (isset($SrResponsee['screeningRequestId']) && ! empty($SrResponsee['screeningRequestId'])) {
                            $newList['applicantId'] = @$SrResponsee['screeningRequestId']['screeningRequestApplicant']['applicantId'];
                            $newList['screeningRequestApplicantId'] = @$SrResponsee['screeningRequestApplicant']['screeningRequestApplicantId'];
                            $newList['applicant_status'] = @$SrResponsee['screeningRequestApplicant']['applicantStatus'];
                            $newList['first_name'] = @$SrResponsee['screeningRequestApplicant']['applicantFirstName'];
                            $newList['last_name'] = @$SrResponsee['screeningRequestApplicant']['applicantLastName'];
                        }
                    }

                    array_push($SRList, $newList);

                    $totalPrice += $plan_price;
                    $successReportCount++;
                }

                if (! empty($SRList) && $sort == 'asc') {
                    usort($SRList, function ($a, $b) {
                        return $a['price'] - $b['price'];
                    });
                } else {
                    usort($SRList, function ($a, $b) {
                        return $b['price'] - $a['price'];
                    });
                }

                $data = paginate($SRList, $perpage);

                return response()->json([
                    'ApiName' => 'user billing report',
                    'status' => true,
                    'message' => 'Screening Request List',
                    'data' => $data,
                    'report_count' => $successReportCount,
                    'total_price' => $totalPrice,
                ], 200);

            } else {
                return response()->json([
                    'ApiName' => 'user billing report',
                    'status' => false,
                    'message' => 'Screening Requests Not Found',
                    'apiResponse' => '',
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'user billing report',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    /**
     * @method users_billing_report
     * This is used to get users report of screening requests with success
     */
    public function users_billing_report(Request $request)
    {
        $input = $request->all();
        $Validator = Validator::make(
            $request->all(),
            [
                'page' => 'required',
                'page_size' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if (isset($request->page_size) && $request->page_size != '') {
            $perpage = $request->page_size;
        } else {
            $perpage = 10;
        }

        if (isset($input['column_name']) && ! empty($input['column_name'])) {
            $column = $input['column_name'];
            if ($input['column_name'] == 'price') {
                $column = 'plan_id';
            }
        } else {
            $column = 'id';
        }

        if (isset($input['sort_order']) && ! empty($input['sort_order'])) {
            $sort = $input['sort_order'];
        } else {
            $sort = 'desc';
        }

        $crmSetting = CrmSetting::where(['crm_id' => 5])->first();
        if ($crmSetting) {
            if ($request->start_date != '') {
                $date = Carbon::createFromFormat('Y-m-d', $request->start_date);
                $currentMonth = $date->format('m');
            } else {
                $currentMonth = Carbon::now()->month;
            }

            $successReportCount = 0;
            $totalPrice = 0;

            $crmData = json_decode($crmSetting->value, true);
            $employer_id = @$crmData['employer_id'];

            $planData = SClearancePlan::select('id', 'plan_name', 'price', 'bundle_id')->get()->toArray();
            $allPlans = [];
            if (! empty($planData)) {
                foreach ($planData as $plan) {
                    $allPlans[$plan['id']] = $plan;
                }
            }

            $SrDetails = SClearanceScreeningRequestList::whereMonth('report_date', $currentMonth)
                ->orderBy($column, $sort)
                ->paginate($perpage);

            $SrDetails->transform(function ($response) use ($allPlans) {
                $plan_price = 0;
                $plan_id = 0;
                $plan_name = '';
                $planDetails = $allPlans[$response->plan_id];
                if (! empty($planDetails)) {
                    $plan_price = $planDetails['price'];
                    $plan_id = $planDetails['id'];
                    $plan_name = $planDetails['plan_name'];
                    $bundle_id = $planDetails['bundle_id'];
                }

                $newList = [
                    'id' => $response->id,
                    'email' => $response->email,
                    'first_name' => $response->first_name,
                    'last_name' => $response->last_name,
                    'screening_request_id' => $response->screening_request_id,
                    'bundle_id' => @$bundle_id,
                    'plan_id' => @$plan_id,
                    'plan_name' => @$plan_name,
                    'price' => @$plan_price,
                ];

                return $newList;

            });

            if (! empty($SrDetails)) {
                $data = $SrDetails->toArray();
                $successReportCount = $data['total'];
                $priceCal = SClearanceScreeningRequestList::join('s_clearance_plans', 's_clearance_plans.id', '=', 's_clearance_screening_request_lists.plan_id')
                    ->select(DB::Raw('SUM(s_clearance_plans.price) as totalPrice'))
                    ->whereMonth('report_date', $currentMonth)
                    ->get()->toArray();
                $totalPrice = @$priceCal[0]['totalPrice'];

                return response()->json([
                    'ApiName' => 'user billing report',
                    'status' => true,
                    'message' => 'Screening Request List',
                    'data' => $data,
                    'report_count' => $successReportCount,
                    'total_price' => $totalPrice,
                ], 200);

            } else {
                return response()->json([
                    'ApiName' => 'user billing report',
                    'status' => false,
                    'message' => 'Screening Requests Not Found',
                    'apiResponse' => '',
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'user billing report',
                'status' => false,
                'message' => 'Please Activate S-Clearance First',
            ], 400);
        }

    }

    /**
     * @method validate_request
     * This is used to check validate request for exam attempts or report generated
     */
    public function validate_request($encrypted_request_id): JsonResponse
    {
        // $request_id = Crypt::decrypt($encrypted_request_id);
        $request_id = decryptData($encrypted_request_id);
        $getRequestData = SClearanceScreeningRequestList::select('*')->where(['id' => $request_id])->get()->toArray();
        if (! empty($getRequestData)) {
            $screeningRequestData = $this->getScreeningRequestApplicant($getRequestData[0]['screening_request_applicant_id']);
            // $validateSR = $this->validateScreeningRequest($getRequestData[0]['screening_request_applicant_id'], $getRequestData[0]['applicant_id']);
            if (isset($screeningRequestData['name']) && $screeningRequestData['name'] == 'UnauthorizedAccess') {
                $this->sendMailforUnAuthorized();

                return response()->json([
                    'ApiName' => 'validate_request',
                    'status' => false,
                    'message' => 'Token has expired, and we are generating a new one. Please try again shortly.',
                    'apiResponse' => @$screeningRequestData,
                ], 400);
            } elseif (! empty($screeningRequestData) && isset($screeningRequestData['applicantStatus']) && $screeningRequestData['applicantStatus'] == 'ScreeningRequestCanceled') {
                return response()->json([
                    'ApiName' => 'validate_request',
                    'status' => false,
                    'message' => 'Your background verification has been canceled',
                    'validate_status' => $getRequestData[0]['status'],
                    'data' => $getRequestData,
                ], 400);
            } elseif (! empty($screeningRequestData) && isset($screeningRequestData['applicantStatus']) && $screeningRequestData['applicantStatus'] == 'ScreeningRequestExpired') {
                return response()->json([
                    'ApiName' => 'validate_request',
                    'status' => false,
                    'message' => 'Your background verification application has been expired, Please contact admin to generate new one.',
                    'validate_status' => 'Appliication Expired',
                    'data' => $getRequestData,
                ], 400);
            } elseif ($getRequestData[0]['status'] == 'Approval Pending') {
                return response()->json([
                    'ApiName' => 'validate_request',
                    'status' => false,
                    'message' => 'You are already verified',
                    'validate_status' => $getRequestData[0]['status'],
                    'data' => $getRequestData,
                ], 400);
            } elseif ($getRequestData[0]['is_report_generated'] == 1) {
                return response()->json([
                    'ApiName' => 'validate_request',
                    'status' => false,
                    'message' => 'Your verification report is already generated.',
                    'validate_status' => $getRequestData[0]['status'],
                    'data' => $getRequestData,
                ], 400);
            } else {
                return response()->json([
                    'ApiName' => 'validate_request',
                    'status' => true,
                    'validate_status' => $getRequestData[0]['status'],
                    'data' => $getRequestData,
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'validate_request',
                'status' => false,
                'message' => 'Request Not Found',
            ], 400);
        }
    }

    /**
     * @method get_onboarding_employee_bv_status
     * This is used to get status of background verification of an onboarding employee
     */
    public function get_onboarding_employee_bv_status(Request $request): JsonResponse
    {
        $position_id = $request->position_id;
        $user_id = $request->id;
        $user_type = $request->user_type;
        $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id, 'hiring_status' => 1, 'is_approval_required' => 1])->first();
        if (! empty($configurationDetails)) {
            $data = [];
            $reportData = SClearanceScreeningRequestList::where(['user_type_id' => $user_id, 'user_type' => $user_type])
                ->where(function ($query) {
                    $query->where('is_report_generated', '=', 1);
                    $query->orWhereNotNull('report_date');
                })
                ->first();

            if (! empty($reportData)) {
                $data = [
                    'screening_request_applicant_id' => $reportData->screening_request_applicant_id,
                    'screening_request_id' => $reportData->screening_request_id,
                    'is_report_generated' => $reportData->is_report_generated,
                    'background_verification_status' => $reportData->status,
                    'report_date' => $reportData->report_date,
                ];
            }

            return response()->json([
                'ApiName' => 'get_onboarding_employee_bv_status',
                'status' => true,
                'message' => 'Background Verification Report',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'get_onboarding_employee_bv_status',
                'status' => false,
                'message' => 'Approval of Backgorund Verification is not required',
            ], 400);
        }
    }

    public function getapplicantreport(Request $request): JsonResponse
    {
        $appliicantReportResponse = $this->getScreeningRequestApplicant($request->screeningRequestApplicantId);
        if (isset($appliicantReportResponse['applicantStatus']) && $appliicantReportResponse['applicantStatus'] == 'ScreeningRequestExpired') {
            return response()->json([
                'ApiName' => 'get_applicant_report',
                'status' => false,
                'message' => 'Report has been Expired',
            ], 400);
        } else {
            $reportResponse = $this->getScreeningReports($request->screeningRequestApplicantId);
            if (isset($reportResponse['reportResponseModelDetails']) && isset($reportResponse['reportResponseModelDetails'][0]['reportData'])) {
                // Create pdf and Upload to S3
                $data = str_replace('\r\n', '', str_replace('\"', '"', str_replace('\/', '/', $reportResponse['reportResponseModelDetails'][0]['reportData'])));

                return response()->json([
                    'ApiName' => 'get_applicant_report',
                    'status' => true,
                    'message' => 'Applicant Report',
                    'data' => '<!DOCTYPE html >'.$data,
                ], 200);

            }
        }

        return response()->json([
            'ApiName' => 'get_applicant_report',
            'status' => false,
            'message' => 'Applicant Report',
            'apiResponse' => @$reportResponse,
        ], 400);

    }

    public function getPlan(Request $request): JsonResponse
    {
        $crmSetting = CrmSetting::where(['crm_id' => 5, 'status' => 1])->first();
        $statusCode = 400;
        $status = false;
        $message = '';
        if ($crmSetting) {
            $decodeJson = json_decode($crmSetting->value, true);
            if (isset($decodeJson['plan_id'])) {
                $planData = SClearancePlan::where('id', $decodeJson['plan_id'] ?? 0)->first();
                if ($planData != null && isset($planData->plan_name)) {
                    $plan_name = 'S-Clearance '.$planData->plan_name;

                    return response()->json([
                        'ApiName' => 'get_plan',
                        'status' => true,
                        'message' => 'S-Clearance Plan!',
                        'data' => [
                            'plan_name' => $plan_name,
                        ],
                    ], 200);
                } else {
                    $message = 'S-Clearance Plan not found!';
                }
            } else {
                $message = 'S-Clearance Plan not found!';
            }
        } else {
            $message = 'Please Activate S-Clearance First';
        }

        return response()->json([
            'ApiName' => 'get_plan',
            'status' => $status,
            'message' => $message,
        ], $statusCode);
    }

    public function sendMailforUnAuthorized()
    {
        // $domain_name = config('app.domain_name');
        // $emailData['email'] = '';
        // $emailData['subject'] = 'Sclearance Unauthorized access error on | ' . $domain_name . ' server';
        // $emailData['template'] = "S Clearance - Getting error 'Unauthorized access' on | ' . $domain_name . ' server, Please check. There, may be credentials are wrong or may be same token will be using for both auth tokens";
        // $maidData = $this->sendEmailNotification($emailData, true);
    }

    /**
     * @method addToSClearanceServer
     * This is used to add background verification details to s clearance mediator server
     */
    public function addToSClearanceServer($requestData)
    {
        try {
            $data = [
                'screening_request_applicant_id' => $requestData['screeningRequestApplicantId'],
                'domain_name' => config('app.domain_name'),
            ];
            DB::connection('sclearance')->table('screening_domain_details')->insert($data);
        } catch (Exception $e) {
            Log::channel('sclearance_log')->info('addToSClearanceServer error '.print_r($e->getMessage(), true));
        }
    }
}
