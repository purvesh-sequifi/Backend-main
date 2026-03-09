<?php

namespace App\Http\Controllers\API\Hiring;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\FieldRoutesTrait;
use App\Core\Traits\FieldRoutesUserDataTrait;
use App\Core\Traits\HubspotTrait;
use App\Core\Traits\JobNimbusTrait;
use App\Core\Traits\PermissionCheckTrait;
use App\Exports\OnboardingExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingEmployeeValidatedRequest;
use App\Services\EspQuickBaseService;
use App\Models\ActivityLog;
use App\Models\AdditionalInfoForEmployeeToGetStarted;
use App\Models\AdditionalLocations;
use App\Models\AdditionalRecruiters;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\Department;
use App\Models\Documents;
use App\Models\DocumentSigner;
use App\Models\DocumentType;
use App\Models\DomainSetting;
use App\Models\EmployeeAdminOnlyFields;
use App\Models\EmployeeIdSetting;
use App\Models\EmployeeOnboardingDeduction;
use App\Models\EmployeePersonalDetail;
use App\Models\EventCalendar;
use App\Models\HiringStatus;
use App\Models\Integration;
use App\Models\InterigationTransactionLog;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\ManagementTeamMember;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsDocumentComment;
use App\Models\Notification;
use App\Models\OnboardingAdditionalEmails;
use App\Models\OnboardingCommissionTiersRange;
use App\Models\OnboardingDirectOverrideTiersRange;
use App\Models\OnboardingEmployeeAdditionalOverride;
use App\Models\OnboardingEmployeeLocation;
use App\Models\OnboardingEmployeeLocations;
use App\Models\OnboardingEmployeeOverride;
use App\Models\OnboardingEmployeeRedline;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingEmployeeUpfront;
// use Illuminate\Support\Facades\Log;
use App\Models\OnboardingEmployeeWages;
use App\Models\OnboardingEmployeeWithheld;
use App\Models\OnboardingIndirectOverrideTiersRange;
use App\Models\OnboardingOfficeOverrideTiersRange;
use App\Models\OnboardingOverrideOfficeTiersRange;
use App\Models\OnboardingUpfrontsTiersRange;
use App\Models\OnboardingUserRedline;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionOverrideSettlement;
use App\Models\PositionPayFrequency;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionsDeductionLimit;
use App\Models\PositionTierOverride;
use App\Models\SClearanceConfiguration;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SentOfferLetter;
use App\Models\SequiDocsEmailSettings;
use App\Models\SequiDocsTemplate;
use App\Models\State;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UsersBusinessAddress;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use App\Traits\EmailNotificationTrait;
use App\Traits\HighLevelTrait;
use App\Traits\IntegrationTrait;
use App\Traits\PushNotificationTrait;
use App\Traits\SolerroAddUpdateEmployeeRequestTrait;
use Excel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mail;
use Pdf;

class OnboardingEmployeeController extends Controller
{
    use EmailNotificationTrait;
    use EvereeTrait;
    use HighLevelTrait;
    use HubspotTrait;
    use JobNimbusTrait;
    use PermissionCheckTrait;
    use PushNotificationTrait;

    protected $url;

    protected $companySettingtiers;

    use FieldRoutesTrait;
    use FieldRoutesUserDataTrait;
    use IntegrationTrait;
    use SolerroAddUpdateEmployeeRequestTrait;

    /**
     * Create an employee in FieldRoutes with proper dataLink formatting
     *
     * This implementation bypasses the trait collision issue and directly calls the API
     * with the correctly formatted dataLink parameter
     *
     * @param  object  $data  The employee data object
     * @param  string  $checkStatus  The check status
     * @param  int|null  $uid  User ID
     * @param  string  $authenticationKey  FieldRoutes authentication key
     * @param  string  $authenticationToken  FieldRoutes authentication token
     * @param  string  $baseURL  FieldRoutes base URL
     * @return mixed The response from FieldRoutes API
     */
    public function fieldRoutesCreateEmployee(object $data, string $checkStatus, ?int $uid, string $authenticationKey, string $authenticationToken, string $baseURL)
    {
        // Log what we're doing
        Log::info('OnboardingEmployeeController: Using direct API implementation with fixed dataLink format');

        // Convert the data to an array if it's an object
        $employeeData = (array) $data;

        // Add employee_id if present in the data object
        if (isset($data->employee_id)) {
            $employeeData['employee_id'] = $data->employee_id;
        }

        // Add current timestamp for the dataLink parameter
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');
        $employeeId = $employeeData['employee_id'] ?? '';

        // Instead of using nested dataLink structure, we'll directly set it properly
        // This is the key fix - we manually structure the dataLink for the API as a simple field
        $employeeData['dataLinkAlias'] = $employeeId;
        $employeeData['dataLink'] = '{"timeMark":"'.$currentDateTime.'"}';  // Escaping for JSON

        // Log the prepared data
        Log::info('FieldRoutes direct API: Prepared data with fixed dataLink format', [
            'dataLinkAlias' => $employeeData['dataLinkAlias'],
            'dataLink' => $employeeData['dataLink'],
        ]);

        // Setup the API call directly, bypassing the trait method
        $url = $baseURL.'/employee/create';

        // Create headers
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authenticationKey:'.$authenticationKey,
            'authenticationToken:'.$authenticationToken,
        ];

        // JSON encode the data
        $jsonData = json_encode($employeeData);
        Log::info(['fieldRoutesCreateEmployee direct API call data==>' => $jsonData]);

        // Make the API call directly using the curlRequestDataForFieldRoutes method from FieldRoutesTrait
        $curl_response = $this->curlRequestDataForFieldRoutes($url, $jsonData, $headers, 'POST');

        // Process the response
        $resp = json_decode($curl_response, true);

        Log::info(['fieldRoutesCreateEmployee direct API response==>' => $resp]);

        if (isset($resp) && isset($resp['success']) && $resp['success'] == false) {
            Log::info(['fieldRoutesCreateEmployee direct API error==>' => $resp]);
        } else {
            Log::info(['fieldRoutesCreateEmployee direct API success==>' => true]);

            if (isset($resp['result'])) {
                $hs_object_id = $resp['result'];
            } else {
                $hs_object_id = 0;
            }

            if ($table == 'User') {
                $updateuser = User::where('id', $uid)->first();

                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                    Log::info(['fieldRoutesCreateEmployee direct API UserUpdate==>' => true]);
                }
            } elseif ($table == 'Onboarding_employee') {
                $updateuser = OnboardingEmployees::where('id', $check_employee_id)->first();

                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                    Log::info(['fieldRoutesCreateEmployee direct API OnboardingEmployeeUpdate==>' => true]);
                }
            }
        }

        return $resp;
    }

    public function __construct(
        OnboardingEmployees $OnboardingEmployees,
        UrlGenerator $url,
        protected EspQuickBaseService $espQuickBaseService,
        protected \App\Services\OnyxRepDataPushService $onyxRepDataPushService
    ) {
        $this->OnboardingEmployees = $OnboardingEmployees;
        $this->url = $url;
        $this->companySettingtiers = CompanySetting::where('type', 'tier')->first();

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

    public function update_empid()
    {
        $data = OnboardingEmployees::where('employee_id', null)->orderBy('id', 'Asc')->select('employee_id', 'id')->get();
        foreach ($data as $d) {
            if (empty($d['employee_id'])) {
                $eId = 'ONB0018';
            } else {
                $eId = $d['employee_id'];
            }

            $substr = substr($eId, 3);
            $val = $d['id']; // $substr+1;
            $EmpId = str_pad($val, 4, '0', STR_PAD_LEFT);
            $emp_id = OnboardingEmployees::where('id', $d['id'])->update(['employee_id' => 'ONB'.$EmpId]);
        }
    }

    public function update_aveyoid_ondb()
    {
        $CrmData = Crms::where('id', 2)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 2)->first();
        if (! empty($CrmData) && ! empty($CrmSetting)) {
            $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            $val = json_decode($decreptedValue);
            $token = $val->api_key;

            $data = OnboardingEmployees::where('aveyo_hs_id', null)->orderBy('id', 'Asc')->select('id', 'email')->get();
            if (empty($data)) {
                return 'no data found';
            }
            foreach ($data as $d) {
                $filtergrp_arr = [];
                $url = 'https://api.hubapi.com/crm/v3/objects/sales/search';
                // $Hubspotdata=json_encode($Hubspotdata);
                $headers = [
                    'accept: application/json',
                    'content-type: application/json',
                    'authorization: Bearer '.$token,
                ];

                $filters[] = [
                    'propertyName' => 'email',
                    'operator' => 'EQ',
                    'value' => $d['email'],
                ];

                $filtergrp_arr['filterGroups'][] = ['filters' => $filters];
                $filtergrp = json_encode($filtergrp_arr);
                $curl_response = $this->curlRequestSalesData($url, $headers, $filtergrp, 'POST');
                $resp = json_decode($curl_response, true);
                if (isset($resp['results'][0]['id'])) {
                    $emp_id = OnboardingEmployees::where('id', $d['id'])->update(['aveyo_hs_id' => $resp['results'][0]['id']]);

                }
                exit();
            }
        }
    }

    public function update_sequifiid_hubspot()
    {
        $CrmData = Crms::where('id', 2)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 2)->first();
        if (! empty($CrmData) && ! empty($CrmSetting)) {
            $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            $val = json_decode($decreptedValue);
            $token = $val->api_key;

            $data = OnboardingEmployees::where('aveyo_hs_id', '!=', null)
                ->where('user_id', null)
                ->orderBy('id', 'Asc')
                ->get();
            if (empty($data)) {
                return 'no data found';
            }
            foreach ($data as $d) {
                $userId = $d['id'];
                $recruiter = User::select('first_name', 'last_name')->where('id', $d['recruiter_id'])->first();
                $manager = User::select('first_name', 'last_name')->where('id', $d['manager_id'])->first();
                $team = ManagementTeam::select('team_name')->where('id', $d['team_id'])->first();
                $office = Locations::select('office_name')->where('id', $d['office_id'])->first();
                $positions = Positions::select('position_name')->where('id', $d['position_id'])->first();
                $department = Department::where('id', $d['department_id'])->first();
                $office = Locations::select('office_name', 'work_site_id')->where('id', $d['office_id'])->first();
                $state = State::where('id', $d['state_id'])->first();
                $hiring_status = HiringStatus::where('id', $d['status_id'])->value('status');

                if ($d['position_id'] == 2) {
                    $payGroup = 'Closer';
                    $closer_redline = $d['redline'];
                    $setter_redline = $d['self_gen_redline'];
                }
                if ($d['position_id'] == 3) {
                    $payGroup = 'Setter';
                    $closer_redline = $d['self_gen_redline'];
                    $setter_redline = $d['redline'];
                }
                if ($d['self_gen_accounts'] == 1) {
                    $payGroup = 'Setter&Closer';
                }

                if ($d['upfront_sale_type'] == 'per sale') {
                    $upfrontType = 'Per Sale';
                } elseif ($d['upfront_sale_type'] == 'per KW') {
                    $upfrontType = 'Per kw';
                }
                $Hubspotdata['properties'] = [
                    'first_name' => $d['first_name'],
                    'last_name' => $d['last_name'],
                    'sales_name' => $d['first_name'].' '.$d['last_name'],
                    'email' => $d['email'],
                    'phone' => $d['mobile_no'],
                    'state' => isset($state['name']) ? $state['name'] : null,
                    // "city" => $d['city_id'],
                    'position_id' => $d['position_id'],
                    'position' => isset($positions['position_name']) ? $positions['position_name'] : null,
                    'manager' => isset($manager['first_name']) ? $manager['first_name'].' '.$manager['last_name'] : null,
                    'manager_id' => $d['manager_id'],
                    'team_id' => $d['team_id'],
                    'team' => isset($team['team_name']) ? $team['team_name'] : null,
                    'sequifi_id' => $d['employee_id'],
                    'commission' => $d['commission'],
                    // "redline" => $d['redline'],
                    'redline' => $closer_redline, //  in hubspot this is  closer redline
                    'setter_redline' => $setter_redline,
                    'pay_group' => isset($payGroup) ? $payGroup : null,
                    'office_id' => isset($office['work_site_id']) ? $office['work_site_id'] : null,
                    'office' => isset($office['office_name']) ? $office['office_name'] : null,
                    'department_id' => isset($d['department_id']) ? $d['department_id'] : null,
                    'department' => isset($department['name']) ? $department['name'] : null,
                    'recruiter_id' => isset($d['recruiter_id']) ? $d['recruiter_id'] : null,
                    'recruiter' => isset($recruiter['first_name']) ? $recruiter['first_name'].' '.$recruiter['last_name'] : null,
                    'upfront_pay_amount' => $d['upfront_pay_amount'],
                    'upfront_type' => isset($upfrontType) ? $upfrontType : null,
                    'status' => isset($hiring_status) ? $hiring_status : null,
                ];
                // $token = 'pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641'; // live
                // $token = 'pat-na1-4a7ea2a4-c60f-41f1-9392-31f9fd6fd0e8'; // demo
                $url = 'https://api.hubapi.com/crm/v3/objects/sales/'.$d['aveyo_hs_id'];
                $Hubspotdata = json_encode($Hubspotdata);
                $headers = [
                    'accept: application/json',
                    'content-type: application/json',
                    'authorization: Bearer '.$token,
                ];

                $curl_response = $this->curlRequestDataUpdate($url, $Hubspotdata, $headers, 'PATCH');
                // return $curl_response;
                // exit();
            }
        }
    }

    public function create_sequifiid_hubspot()
    {
        $CrmData = Crms::where('id', 2)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 2)->first();
        if (! empty($CrmData) && ! empty($CrmSetting)) {
            $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            $val = json_decode($decreptedValue);
            $token = $val->api_key;

            $data = User::where('id', 139)
            // ->where('user_id',null)
                ->orderBy('id', 'Asc')
                ->get();
            if (empty($data)) {
                return 'no data found';
            }
            foreach ($data as $d) {
                $Hubspotdata = [];
                $userId = $d['id'];
                $recruiter = User::select('first_name', 'last_name')->where('id', $d['recruiter_id'])->first();
                $manager = User::select('first_name', 'last_name')->where('id', $d['manager_id'])->first();
                $team = ManagementTeam::select('team_name')->where('id', $d['team_id'])->first();
                $office = Locations::select('office_name')->where('id', $d['office_id'])->first();
                $positions = Positions::select('position_name')->where('id', $d['position_id'])->first();
                $department = Department::where('id', $d['department_id'])->first();
                $office = Locations::select('office_name', 'work_site_id')->where('id', $d['office_id'])->first();
                $state = State::where('id', $d['state_id'])->first();
                $hiring_status = HiringStatus::where('id', $d['status_id'])->value('status');

                if ($d['position_id'] == 2) {
                    $payGroup = 'Closer';
                    $closer_redline = $d['redline'];
                    $setter_redline = $d['self_gen_redline'];
                }
                if ($d['position_id'] == 3) {
                    $payGroup = 'Setter';
                    $closer_redline = $d['self_gen_redline'];
                    $setter_redline = $d['redline'];
                }
                if ($d['self_gen_accounts'] == 1) {
                    $payGroup = 'Setter&Closer';
                }

                if ($d['upfront_sale_type'] == 'per sale') {
                    $upfrontType = 'Per Sale';
                } elseif ($d['upfront_sale_type'] == 'per KW') {
                    $upfrontType = 'Per kw';
                }
                $Hubspotdata['properties'] = [
                    'first_name' => $d['first_name'],
                    'last_name' => $d['last_name'],
                    'sales_name' => $d['first_name'].' '.$d['last_name'],
                    'email' => $d['email'],
                    'phone' => $d['mobile_no'],
                    'state' => isset($state['name']) ? $state['name'] : null,
                    // "city" => $d['city_id'],
                    'position_id' => $d['position_id'],
                    'position' => isset($positions['position_name']) ? $positions['position_name'] : null,
                    'manager' => isset($manager['first_name']) ? $manager['first_name'].' '.$manager['last_name'] : null,
                    'manager_id' => $d['manager_id'],
                    'team_id' => $d['team_id'],
                    'team' => isset($team['team_name']) ? $team['team_name'] : null,
                    'sequifi_id' => $d['employee_id'],
                    'commission' => $d['commission'],
                    // "redline" => $d['redline'],
                    'redline' => $closer_redline, //  in hubspot this is  closer redline
                    'setter_redline' => $setter_redline,
                    'pay_group' => isset($payGroup) ? $payGroup : null,
                    'office_id' => isset($office['work_site_id']) ? $office['work_site_id'] : null,
                    'office' => isset($office['office_name']) ? $office['office_name'] : null,
                    'department_id' => isset($d['department_id']) ? $d['department_id'] : null,
                    'department' => isset($department['name']) ? $department['name'] : null,
                    'recruiter_id' => isset($d['recruiter_id']) ? $d['recruiter_id'] : null,
                    'recruiter' => isset($recruiter['first_name']) ? $recruiter['first_name'].' '.$recruiter['last_name'] : null,
                    'upfront_pay_amount' => $d['upfront_pay_amount'],
                    'upfront_type' => isset($upfrontType) ? $upfrontType : null,
                    'status' => isset($hiring_status) ? $hiring_status : null,
                ];
                // dd($Hubspotdata);
                // $token = 'pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641'; // live
                // $token = 'pat-na1-4a7ea2a4-c60f-41f1-9392-31f9fd6fd0e8'; // demo
                $url = 'https://api.hubapi.com/crm/v3/objects/sales';
                $Hubspotdata = json_encode($Hubspotdata);

                $headers = [
                    'accept: application/json',
                    'content-type: application/json',
                    'authorization: Bearer '.$token,
                ];

                $curl_response = $this->curlRequestData($url, $Hubspotdata, $headers, 'POST');

                return $curl_response;
                // exit();
            }
        }
    }

    public function status_update_hubspot()
    {
        $date = date('Y-m-d');
        $arr = [];
        $data = OnboardingEmployees::where('offer_expiry_date', '<', $date)
            ->whereIn('status_id', [4, 6, 12, 5])->get(); // Offer Letter Sent, Requested Change, Offer Letter Resent
        // update staus in hubspot
        $CrmData = Crms::where('id', 2)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 2)->first();
        foreach ($data as $d) {
            if (! empty($CrmData) && ! empty($CrmSetting)) {
                $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                $val = json_decode($decreptedValue);
                $token = $val->api_key;
                if (! empty($d['aveyo_hs_id'])) {
                    $Hubspotdata['properties'] = ['status' => 'Offer Expired'];
                    $this->update_hubspot_data($Hubspotdata, $token, $d['aveyo_hs_id']);
                }
            }
            $arr[] = $d['aveyo_hs_id'];
        }

        return json_encode($arr);
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
                'background_verification_status' => '',
                'background_verification_approval_required' => 0,
            ];

            $push_data = true;

            if ($user_row->is_background_verificaton == 1) {
                $position_id = $user_row->position_id;
                $user_id = $user_row->id;
                $user_type = 'Onboarding';
                $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id, 'hiring_status' => 1])->first();

                if (empty($configurationDetails)) {// get default
                    $configurationDetails = SClearanceConfiguration::where(['id' => 1])->first();
                }
                // if(!empty($configurationDetails)){

                $reportData = SClearanceTurnScreeningRequestList::where(['user_type_id' => $user_id, 'user_type' => $user_type])
                    ->where(function ($query) {
                        $query->where('is_report_generated', '=', 1);
                        $query->orWhereNotNull('report_date');
                    })
                    ->first();
                if (! empty($reportData)) {
                    $data['turn_id'] = $reportData->turn_id;
                    $data['worker_id'] = $reportData->worker_id;
                    $data['is_report_generated'] = $reportData->is_report_generated;
                    $data['background_verification_status'] = $reportData->status;
                    $data['background_verification_approval_required'] = $configurationDetails->is_approval_required ?? 0;
                    $data['approved_declined_by'] = $reportData->approved_declined_by;
                }
                // }
            }

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

    public function onboarding_employee_listing(Request $request): JsonResponse
    {
        if (isset($request->perpage) && $request->perpage != '') {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $user = $this->OnboardingEmployees->newQuery();
        $status_id_filter = '';

        $user->select('onboarding_employees.*')->leftJoin('users', 'users.id', 'onboarding_employees.user_id')->where(function ($query) {
            $query->where('users.dismiss', 0)->orWhereNull('onboarding_employees.user_id');
        });
        $other_status_filter = isset($request->other_status_filter) ? $request->other_status_filter : '';
        $user->with('departmentDetail', 'positionDetail', 'managerDetail', 'statusDetail', 'state', 'teamsDetail', 'subpositionDetail', 'office', 'OnboardingAdditionalEmails');

        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('onboarding_employees.first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('onboarding_employees.last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(onboarding_employees.first_name, " ", onboarding_employees.last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('onboarding_employees.email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('onboarding_employees.mobile_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereHas('OnboardingAdditionalEmails', function ($query) use ($request) {
                        $query->where('email', 'like', '%'.$request->input('filter').'%');
                    });
            });
        }

        if ($request->has('position_filter') && ! empty($request->input('position_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('onboarding_employees.sub_position_id', $request->input('position_filter'));
            });
        }

        if ($request->has('manager_filter') && ! empty($request->input('manager_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('onboarding_employees.manager_id', $request->input('manager_filter'));
            });
        }

        if ($request->has('office_id') && ! empty($request->input('office_id'))) {
            if ($request->input('office_id') !== 'all') {
                $data = $user->where('onboarding_employees.office_id', $request->input('office_id'));
            }
        }

        $hire_now_filter = '';
        $offer_letter_accepted_filter = '';
        if ($request->has('status_filter') && ! empty($request->input('status_filter')) && $other_status_filter == '') {
            $status_id_filter = $request->query('status_filter');
            if ($status_id_filter == 13) {
                $user->where(function ($query) {
                    $query->where('onboarding_employees.status_id', 1);
                });
                $offer_letter_accepted_filter = 1;
            } elseif ($status_id_filter == 1) {
                $user->where(function ($query) {
                    $query->where('onboarding_employees.status_id', 1);
                });
                $hire_now_filter = 1;
            } else {
                $user->where(function ($query) use ($status_id_filter) {
                    $query->where('onboarding_employees.status_id', $status_id_filter);
                });
            }
        }
        $user->with('OnboardingEmployeesDocuments')
            ->orderBy('onboarding_employees.id', 'DESC')->where('onboarding_employees.status_id', '!=', 14);

        // New Logic for all doc signature.
        if ($hire_now_filter == 1 || $offer_letter_accepted_filter == 1) {
            $user_data = $user->get();
        } else {
            $user_data = $user->paginate($request->perpage ?? 10);
        }

        $final_data = [];
        foreach ($user_data as $user_row) {
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
                'email' => $user_row->email,
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
                'last_update_ts' => strtotime($user_row->updated_at),
                'work_email' => $user_row->OnboardingAdditionalEmails,
                'is_background_verificaton' => $user_row->is_background_verificaton,
                'background_verification_status' => '',
                'background_verification_approval_required' => 0,
            ];

            $push_data = true;
            if ($user_row->is_background_verificaton == 1) {
                $position_id = $user_row->position_id;
                $user_id = $user_row->id;
                $user_type = 'Onboarding';
                $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id, 'hiring_status' => 1])->first();
                if (empty($configurationDetails)) { // get default
                    $configurationDetails = SClearanceConfiguration::where(['id' => 1])->first();
                }

                $reportData = SClearanceTurnScreeningRequestList::where(['user_type_id' => $user_id, 'user_type' => $user_type])
                    ->first();
                if (! empty($reportData)) {
                    $data['turn_id'] = $reportData->turn_id;
                    $data['worker_id'] = $reportData->worker_id;
                    $data['is_report_generated'] = $reportData->is_report_generated;
                    $data['background_verification_status'] = $reportData->status;
                    $data['background_verification_approval_required'] = $configurationDetails->is_approval_required ?? 0;
                    $data['approved_declined_by'] = $reportData->approved_declined_by;
                }
            }

            if ($hire_now_filter == 1) {
                $push_data = false;
                /*
                S Clearace
                is_background_verificaton = 1

                background_verification_status = "Approval Pending" && background_verification_approval_required = 1
                Show View Report button

                background_verification_status = "Approval Pending" && background_verification_approval_required = 0
                Show Hire Now Button

                background_verification_status = "Approved" && background_verification_approval_required = 1
                Show Hire Now Button

                background_verification_status = "Approved" && background_verification_approval_required = 0
                Show Hire Now Button

                is_background_verificaton = 0
                Show Hire Now Button
                */

                if ($data['is_background_verificaton'] == 1) {

                    if ($data['background_verification_status'] == 'approved' || $data['background_verification_status'] == 'pending') {
                        if ($data['background_verification_approval_required'] == 0) {
                            $push_data = true;
                        } elseif ($data['background_verification_approval_required'] == 1 && $data['approved_declined_by'] != null) {
                            $push_data = true;
                        } else {
                            $push_data = false;
                        }
                    }

                    if (! $is_all_new_doc_sign) {
                        $push_data = false;
                    } else {
                        $push_data = true;
                    }

                    if ($is_all_new_doc_sign && ($other_doc_status['backgroundVerification'] == '0' || $other_doc_status['w9'] == '0')) {
                        $push_data = false;
                    }
                } else {
                    if ($is_all_new_doc_sign) {
                        $push_data = true;
                    }

                    if ($is_all_new_doc_sign && ($other_doc_status['backgroundVerification'] == '0' || $other_doc_status['w9'] == '0')) {
                        $push_data = false;
                    }
                }
            } elseif ($offer_letter_accepted_filter == 1) {
                $push_data = false;
                if (! $is_all_new_doc_sign) {
                    $push_data = true;
                }
                if ($is_all_new_doc_sign) {
                    // filter send now
                    // for send now
                    // either other doc not send
                    // or send and signed
                    // for filter $push_data = false;
                    if (($other_doc_status['backgroundVerification'] == 1 || $other_doc_status['backgroundVerification'] == 2) && ($other_doc_status['w9'] == 1 || $other_doc_status['w9'] == 2)) {
                        $push_data = false;
                    } elseif (($other_doc_status['backgroundVerification'] == 0 || $other_doc_status['backgroundVerification'] == 2) || ($other_doc_status['w9'] == 0 || $other_doc_status['w9'] == 2)) {
                        $push_data = true;
                    }
                }
            }

            if ($push_data) {
                $final_data[] = $data;
            }
        }

        if ($hire_now_filter == 1 || $offer_letter_accepted_filter == 1) {
            $data = paginate($final_data, $perpage);
        } else {
            $data = $user_data->toArray();
            $data['data'] = $final_data;
        }

        return response()->json([
            'ApiName' => 'onboarding_employee_list ',
            'status' => true,
            'message' => 'Successfully.',
            'offer_letter_accepted_filter' => $offer_letter_accepted_filter,
            'hire_now_filter' => $hire_now_filter,
            'data' => $data,
        ]);
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
     */
    public function addUpdateOnboardingEmployee(OnboardingEmployeeValidatedRequest $request): JsonResponse
    {
        $OnboardingEmployees = OnboardingEmployees::where('id', $request->user_id)->first();
        $onboardingId = '';
        if (! empty($OnboardingEmployees)) {
            $onboardingId = $OnboardingEmployees->id;
        }

        $customMessages = [
            'employee_deatils.first_name.required' => 'The first name is required.',
            'employee_deatils.last_name.required' => 'The last name is required.',
            'employee_deatils.email.required' => 'The email address is required.',
            'employee_deatils.email.email' => 'Please enter a valid email address.',
            'employee_deatils.email.unique' => 'The email address is already in use.',
            'employee_deatils.*mobile_no.required' => 'The mobile number is required.',
            'employee_deatils.*mobile_no.min' => 'The mobile number must be at least :min characters.',
            'employee_deatils.*mobile_no.unique' => 'The mobile number is already in use.',
            'employee_deatils.*position_id.required' => 'The position ID is required.',
        ];

        $Validator = Validator::make($request->all(), [
            'employee_deatils.first_name' => 'required',
            'employee_deatils.last_name' => 'required',
            'employee_deatils.email' => 'required|email',
            'employee_deatils.*mobile_no' => 'required|min:10',
            'employee_deatils.*position_id' => 'required',
        ], $customMessages);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        foreach ($request->employee_deatils['work_email'] as $work) {
            if ($work['email'] == $request->employee_deatils['email']) {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'Additional email could not include personal email.',
                ], 400);
            }
        }

        if ($request->user_id == null) {
            $getUserEmail = User::where('email', $request->employee_deatils['email'])->first();
            if ($getUserEmail != '') {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This email id already exist in Users List',
                ], 400);
            }

            $getUsermobile_no = User::where('mobile_no', $request->employee_deatils['mobile_no'])->first();
            if ($getUsermobile_no != '') {
                $fullName = trim($getUsermobile_no->first_name . ' ' . $getUsermobile_no->last_name);
                $userEmail = $getUsermobile_no->email;
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => "This mobile number is being used by {$fullName} ({$userEmail}). Please use a different mobile number or update this current user with their correct mobile number.",
                ], 400);
            }

            $getOnboardingEmployeesEmail = OnboardingEmployees::where('email', $request->employee_deatils['email'])->first();
            if ($getOnboardingEmployeesEmail != '') {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This email id already in onboarding exist',
                ], 400);
            }

            $getOnboardingEmployeesMobile = OnboardingEmployees::where('mobile_no', $request->employee_deatils['mobile_no'])->first();
            if ($getOnboardingEmployeesMobile != '') {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This mobile no already exist in onboarding List',
                ], 400);
            }

            $getOnboardingEmployeesEmail = Lead::where('email', $request->employee_deatils['email'])->where('id', '!=', $request->employee_deatils['lead_id'])->first();
            if ($getOnboardingEmployeesEmail != '') {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This email id already in Leads exist',
                ], 400);
            }

            $getOnboardingEmployeesMobile = Lead::where('mobile_no', $request->employee_deatils['mobile_no'])->where('id', '!=', $request->employee_deatils['lead_id'])->first();
            if ($getOnboardingEmployeesMobile != '') {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This mobile no already exist in Leads List',
                ], 400);
            }
        }

        if (array_key_exists('city_id', $request->employee_deatils)) {
            if (isset($request->employee_deatils['city_id'])) {
                $city = $request->employee_deatils['city_id'];
            }
        } else {
            $city = null;
        }
        $workEmail = $request->employee_deatils['work_email'];

        if ($request->user_id == null) {
            if (! empty($request->employee_deatils['office_id'])) {
                $office_id = $request->employee_deatils['office_id'];
            } else {
                $office_id = '';
            }

            if (count($workEmail) > 0) {
                $additionalEmails = [];
                foreach ($request->employee_deatils['work_email'] as $work_email) {
                    $additionalEmails[] = $work_email['email'];
                }
                $additionalEmail = OnboardingAdditionalEmails::whereIn('email', $additionalEmails)->count();
                if ($additionalEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additionl email id already exist',
                    ], 400);
                }

                $onboardingEmail = OnboardingEmployees::whereIn('email', $additionalEmails)->count();
                if ($onboardingEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additionl email id already exist in onboarding list',
                    ], 400);
                }

                $userEmail = User::whereIn('email', $additionalEmails)->count();
                if ($userEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additionl email id already exist in user list',
                    ], 400);
                }

                $leadEmail = Lead::whereIn('email', $additionalEmails)->count();
                if ($leadEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additionl email id already exist in lead list',
                    ], 400);
                }
            }

            $array = [
                'first_name' => $request->employee_deatils['first_name'],
                'last_name' => $request->employee_deatils['last_name'],
                'email' => $request->employee_deatils['email'],
                'mobile_no' => $request->employee_deatils['mobile_no'],
                'state_id' => $request->employee_deatils['state_id'],
                'office_id' => $office_id,
                'lead_id' => $request->employee_deatils['lead_id'],
                'recruiter_id' => isset($request->employee_deatils['recruiter_id']) ? $request->employee_deatils['recruiter_id'] : null,
            ];

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $array['redline_type'] = 'per sale';
                $array['upfront_sale_type'] = 'per sale';
                $array['direct_overrides_type'] = 'per sale';
                $array['indirect_overrides_type'] = 'per sale';
                $array['office_overrides_type'] = 'per sale';
            }

            $data = OnboardingEmployees::create($array);
            if ($data->department_id == null || $data->position_id == null || $data->manager_id == null || $data->team_id == null || $data->recruiter_id == null
                || $data->commission == null || $data->redline == null || $data->redline_amount == null || $data->redline_type == null || $data->upfront_pay_amount == null || $data->upfront_sale_type == null
                || $data->direct_overrides_amount == null || $data->direct_overrides_type == null || $data->indirect_overrides_amount == null || $data->indirect_overrides_type == null || $data->office_overrides_amount == null || $data->office_overrides_type == null
                || $data == null) {
                $data5 = OnboardingEmployees::find($data->id);
                $data5->status_id = 8;
                $data5->save();
            } else {
                $data6 = OnboardingEmployees::find($data->id);
                $data6->status_id = 4;
                $data6->save();
            }
            if ($data && isset($request->employee_deatils['lead_id'])) {
                Lead::where('id', $request->employee_deatils['lead_id'])->update([
                    'email' => $request->employee_deatils['email'],
                    'first_name' => $request->employee_deatils['first_name'],
                    'last_name' => $request->employee_deatils['last_name'],
                    'mobile_no' => $request->employee_deatils['mobile_no'],
                ]);
            }
            $user_id = $data->id;
            $description = 'First Name =>'.$data->first_name.','.'Last Name =>'.$data->last_name.','.'email =>'.$data->email.','.'Mobile Number =>'.$data->mobile_no.','.'State Id =>'.$data->state_id.','.'Office Id =>'.$data->office_id.','.'City Id =>'.$data->city_id.','.'Recruiter Id =>'.$data->recruiter_id.','.'User Id =>'.$data->id;
            $page = 'Employee hiring';
            $action = 'Employee create';
            user_activity_log($page, $action, $description);

            $workEmail = $request->employee_deatils['work_email'];
            if (count($workEmail) > 0) {
                foreach ($workEmail as $workEmails) {
                    OnboardingAdditionalEmails::create(['onboarding_user_id' => $data->id, 'email' => $workEmails['email']]);
                }
            }
            $datas = OnboardingEmployees::where('id', $data->id)->with('OnboardingAdditionalEmails')->first();
        }
        if (isset($request->employee_deatils['lead_id'])) {
            Lead::where('id', $request->employee_deatils['lead_id'])->update(['status' => 'Hired']);
        }

        if (! null == $request->user_id) {
            if ($request->employee_deatils['email'] != $OnboardingEmployees->email) {
                $getUserEmail = User::where('email', $request->employee_deatils['email'])->first();
                if ($getUserEmail != '') {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This email id already exist in Users List',
                    ], 400);
                }

                $getUserEmail = OnboardingEmployees::where('email', $request->employee_deatils['email'])->where('id', '!=', $OnboardingEmployees->id)->first();
                if ($getUserEmail != '') {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This email id already exist in Users List',
                    ], 400);
                }

                $getUserEmail = Lead::where('email', $request->employee_deatils['email'])->where('id', '!=', $OnboardingEmployees->lead_id)->first();
                if ($getUserEmail != '') {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This email id already exist in Users List',
                    ], 400);
                }
            }

            if ($request->employee_deatils['mobile_no'] != $OnboardingEmployees->mobile_no) {
                $getUsermobile_no = User::where('mobile_no', $request->employee_deatils['mobile_no'])->first();
                if ($getUsermobile_no != '') {
                    $fullName = trim($getUsermobile_no->first_name . ' ' . $getUsermobile_no->last_name);
                    $userEmail = $getUsermobile_no->email;
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => "This mobile number is being used by {$fullName} ({$userEmail}). Please use a different mobile number or update this current user with their correct mobile number.",
                    ], 400);
                }

                $getUsermobile_no = OnboardingEmployees::where('mobile_no', $request->employee_deatils['mobile_no'])->where('id', '!=', $OnboardingEmployees->id)->first();
                if ($getUsermobile_no != '') {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This mobile no already exist in Onboarding List',
                    ], 400);
                }

                $getUsermobile_no = Lead::where('mobile_no', $request->employee_deatils['mobile_no'])->where('id', '!=', $OnboardingEmployees->lead_id)->first();
                if ($getUsermobile_no != '') {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This mobile no already exist in Lead List',
                    ], 400);
                }
            }

            if (count($workEmail) > 0) {
                $additionalEmails = [];
                foreach ($request->employee_deatils['work_email'] as $work_email) {
                    $additionalEmails[] = $work_email['email'];
                }
                $additionalEmail = OnboardingAdditionalEmails::whereIn('email', $additionalEmails)->where('onboarding_user_id', '!=', $OnboardingEmployees->id)->count();
                if ($additionalEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additional email id already exist',
                    ], 400);
                }

                $onboardingEmail = OnboardingEmployees::whereIn('email', $additionalEmails)->count();
                if ($onboardingEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additional email id already exist in onboarding list',
                    ], 400);
                }

                $userEmail = User::whereIn('email', $additionalEmails)->count();
                if ($userEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additional email id already exist in user list',
                    ], 400);
                }

                $leadEmail = Lead::whereIn('email', $additionalEmails)->where('id', '!=', $OnboardingEmployees->lead_id)->count();
                if ($leadEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'additional email id already exist in lead list',
                    ], 400);
                }
            }

            $employeeData = OnboardingEmployees::where('id', $request->user_id)->first();
            if ($employeeData != null && $employeeData != '') {
                $employeeData = $employeeData->toArray();
            }

            $data = OnboardingEmployees::find($request->user_id);

            $leadEmployees = Lead::where('email', $data->email)->first();

            DocumentSigner::where('signer_email', $data->email)->update([
                'signer_name' => $request->employee_deatils['first_name'].' '.$request->employee_deatils['last_name'],
                'signer_email' => $request->employee_deatils['email'],
            ]);

            $data->first_name = $request->employee_deatils['first_name'];
            $data->last_name = $request->employee_deatils['last_name'];
            $data->email = $request->employee_deatils['email'];
            $data->mobile_no = $request->employee_deatils['mobile_no'];
            $data->state_id = $request->employee_deatils['state_id'];
            $data->office_id = $request->employee_deatils['office_id'];
            $data->city_id = $city;
            $data->recruiter_id = isset($request->employee_deatils['recruiter_id']) ? $request->employee_deatils['recruiter_id'] : null;
            $data->save();
            if ($data->save() && ! empty($data->lead_id)) {
                Lead::where('id', $data->lead_id)->update([
                    'email' => $request->employee_deatils['email'],
                    'first_name' => $request->employee_deatils['first_name'],
                    'last_name' => $request->employee_deatils['last_name'],
                    'mobile_no' => $request->employee_deatils['mobile_no'],
                ]);
            }

            /* Update data in sclearance table if exists */
            $sclearanceIds = SClearanceTurnScreeningRequestList::where(['user_type_id' => $request->user_id, 'user_type' => 'Onboarding'])->pluck('id')->toArray();

            if (! empty($sclearanceIds)) {
                SClearanceTurnScreeningRequestList::whereIn('id', $sclearanceIds)->update([
                    'first_name' => $request->employee_deatils['first_name'],
                    'last_name' => $request->employee_deatils['last_name'],
                    'email' => $request->employee_deatils['email'],
                ]);
            }
            /* Update data in sclearance table if exists */

            // lead email and mobile number update code end
            $employee = [];
            foreach ($employeeData as $key => $value) {
                if ($value != $data[$key]) {
                    $employee[$key] = $key.' =>'.$data[$key];
                }
            }
            $desc = implode(',', $employee);

            OnboardingAdditionalEmails::where('onboarding_user_id', $request->user_id)->delete();
            if (count($workEmail) > 0) {
                foreach ($workEmail as $workEmails) {
                    OnboardingAdditionalEmails::create(['onboarding_user_id' => $data->id, 'email' => $workEmails['email']]);
                }
            }
            if ($data) {
                $page = 'Employee hiring';
                $action = 'Onboarding Employee update';
                $description = $desc;
                user_activity_log($page, $action, $description);
            }

            $datas = OnboardingEmployees::where('id', $request->user_id)->with('OnboardingAdditionalEmails')->first();
            if ($datas) {
                $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $datas->id)->first();
                $datas->template_id = $sentOfferLetter ? $sentOfferLetter->template_id : null; // Dynamically add template_id
            }

            return response()->json([
                'ApiName' => 'update-onboarding_employee',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => $datas,
            ]);
        }

        /********* employee id code  **********/
        $eId = OnboardingEmployees::where('employee_id', '!=', null)->orderBy('id', 'Desc')->pluck('employee_id')->first();
        // $lettersOnly = preg_replace("/[^A-Za-z]/", "", $eId);

        $substr = 0;
        if (empty($eId)) {
            $eId = 'ONB0000';
        }

        if ($eId != '' && $eId != null) {
            $lettersOnly = preg_replace("/\d+$/", '', $eId);
            $substr = str_replace($lettersOnly, '', $eId);
            $numericCount = strlen($substr);
        }

        $val = $substr + 1;
        $EmpId = str_pad($val, $numericCount, '0', STR_PAD_LEFT);
        $empid_code = EmployeeIdSetting::orderBy('id', 'asc')->first();

        if (! empty($empid_code)) {
            $emp_id = OnboardingEmployees::where('id', $user_id)->update(['employee_id' => $empid_code->onbording_id_code.$EmpId]); // $empid_code->id_code
        } else {
            $emp_id = OnboardingEmployees::where('id', $user_id)->update(['employee_id' => 'ONB'.$EmpId]);
        }

        // Mail::to($request['email'])->send(new OnboardingEmployee());
        return response()->json([
            'ApiName' => 'add-onboarding_employee',
            'status' => true,
            'message' => 'add Successfully.',
            'data' => $datas,
        ], 200);

    }

    public function store(OnboardingEmployeeValidatedRequest $request)
    {

        // echo "sf";die;
        $manager_id = Auth::user()->id;
        // dd($manager_id);
        // $Validator = Validator::make(
        //     $request->all(),
        //     [
        //         'employee_deatils.first_name' => 'required',
        //         'employee_deatils.last_name' => 'required',
        //         'employee_deatils.*email' => 'required|max:100|email|unique:users',
        //         'employee_deatils.*mobile_no' => 'required|max:12|unique:users',
        //         'employee_deatils.*position_id' => 'required',
        //     ]
        // );
        // return $request->user_id;
        // dd($request);
        if ($request->user_id == null) {
            // dd($request->user_id);
            $data = OnboardingEmployees::create(
                [
                    'first_name' => $request->employee_deatils['first_name'],
                    'last_name' => $request->employee_deatils['last_name'],
                    'email' => $request->employee_deatils['email'],
                    'mobile_no' => $request->employee_deatils['mobile_no'],
                    // 'state_id' => $request->employee_deatils['state_id'],
                    // 'city_id' => $request->employee_deatils['city_id'],
                    // 'manager_id' => $manager_id,
                    // 'password' => Hash::make('123456'),
                ]);
            // }

            if ($data->id < 10) {

                $emp_id = OnboardingEmployees::where('id', $data->id)->update(['employee_id' => 'EMP00'.$data->id]);
            } elseif ($data->id < 100 && $data->id > 9) {
                $emp_id = OnboardingEmployees::where('id', $data->id)->update(['employee_id' => 'EMP0'.$data->id]);

            } else {
                $emp_id = OnboardingEmployees::where('id', $data->id)->update(['employee_id' => 'EMP'.$data->id]);
            }

            if ($data->department_id == null || $data->position_id == null || $data->manager_id == null || $data->team_id == null || $data->recruiter_id == null
            || $data->commission == null || $data->redline == null || $data->redline_amount == null || $data->redline_type == null || $data->upfront_pay_amount == null || $data->upfront_sale_type == null
            || $data->direct_overrides_amount == null || $data->direct_overrides_type == null || $data->indirect_overrides_amount == null || $data->indirect_overrides_type == null || $data->office_overrides_amount == null || $data->office_overrides_type == null
            || $data == null) {
                $data5 = OnboardingEmployees::find($data->id);
                $data5->status_id = 8;
                $data5->save();
            } else {
                $data6 = OnboardingEmployees::find($data->id);
                $data6->status_id = 4;
                $data6->save();
            }
        }
        if (! null == $request->user_id) {
            $data = OnboardingEmployees::find($request->user_id);
            $data->first_name = $request->employee_deatils['first_name'];
            // $data->middle_name = $request->employee_deatils['middle_name'];
            $data->last_name = $request->employee_deatils['last_name'];
            $data->email = $request->employee_deatils['email'];
            $data->mobile_no = $request->employee_deatils['mobile_no'];
            // $data->state_id = $request->employee_deatils['state_id'];
            // $data->city_id = $request->employee_deatils['city_id'];
            // $data->department_id = $request['department_id'];
            $data->save();

            return response()->json([
                'ApiName' => 'update-onboarding_employee',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => $data,
            ], 200);
        }

        // Mail::to($request['email'])->send(new OnboardingEmployee());
        return response()->json([
            'ApiName' => 'add-onboarding_employee',
            'status' => true,
            'message' => 'add Successfully.',
            'data' => $data,
        ], 200);

    }

    public function directHiredEmployee(Request $request)
    {
        try {
            DB::beginTransaction();
            $randPassForUsers = randPassForUsers();
            $onbarding_user_id = $request->employee_id;
            $checkStatus = OnboardingEmployees::with('positionDetail')->where('id', $request->employee_id)->first();
            // dd($checkStatus);
            $group_id = $checkStatus->positionDetail->group_id;
            $userId = Auth()->user();
            $uid = ($userId->is_super_admin == 0) ? $userId->id : null;
            $substr = 0;

            if (isset($checkStatus) && $checkStatus != '') {
                $usereEail = User::where('email', $checkStatus['email'])->first();
                if (empty($usereEail)) {
                    if (! in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {

                        $additional_user_id = UsersAdditionalEmail::where('email', $checkStatus['email'])->value('user_id');
                        // dd($additional_user_id);
                        if (! empty($additional_user_id)) {
                            $usereEail = User::where('id', $additional_user_id)->first();
                        }

                    }
                }

                if ($usereEail) {
                    return response()->json([
                        'ApiName' => 'Send Credentials',
                        'status' => false,
                        'message' => 'Email is already exist',
                    ], 400);
                }

                $user_mobile_no = User::where('mobile_no', $checkStatus['mobile_no'])->first();
                if ($user_mobile_no) {
                    return response()->json([
                        'ApiName' => 'Send Credentials',
                        'status' => false,
                        'message' => 'Mobile no is already exist',
                    ], 400);
                }
                // $randomPassword = Str::random(10);
                $companyProfile = CompanyProfile::first();
                $userDataToCreate = [
                    'aveyo_hs_id' => $checkStatus['aveyo_hs_id'],
                    'first_name' => $checkStatus['first_name'],
                    'last_name' => $checkStatus['last_name'],
                    'email' => $checkStatus['email'],
                    'mobile_no' => $checkStatus['mobile_no'],
                    'state_id' => $checkStatus['state_id'],
                    'city_id' => $checkStatus['city_id'],
                    'self_gen_accounts' => $checkStatus['self_gen_accounts'],
                    'self_gen_type' => $checkStatus['self_gen_type'],
                    'department_id' => isset($checkStatus['department_id']) ? $checkStatus['department_id'] : null,
                    'position_id' => $checkStatus['position_id'],
                    'sub_position_id' => $checkStatus['sub_position_id'],
                    'is_manager' => $checkStatus['is_manager'],
                    'is_manager_effective_date' => ($checkStatus['is_manager'] == 1) ? $checkStatus['period_of_agreement_start_date'] : null,
                    'manager_id' => $checkStatus['manager_id'],
                    'manager_id_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'team_id' => $checkStatus['team_id'],
                    'team_id_effective_date' => (! empty($checkStatus['team_id'])) ? $checkStatus['period_of_agreement_start_date'] : null,
                    'recruiter_id' => isset($checkStatus['recruiter_id']) ? $checkStatus['recruiter_id'] : $uid,
                    'group_id' => $group_id,
                    'commission' => $checkStatus['commission'],
                    'commission_type' => $checkStatus['commission_type'],
                    'self_gen_commission' => $checkStatus['self_gen_commission'],
                    'self_gen_commission_type' => $checkStatus['self_gen_commission_type'],
                    'self_gen_upfront_amount' => $checkStatus['self_gen_upfront_amount'],
                    'self_gen_upfront_type' => $checkStatus['self_gen_upfront_type'],
                    'self_gen_withheld_amount' => $checkStatus['self_gen_withheld_amount'],
                    'self_gen_withheld_type' => $checkStatus['self_gen_withheld_type'],
                    'upfront_pay_amount' => $checkStatus['upfront_pay_amount'],
                    'upfront_sale_type' => $checkStatus['upfront_sale_type'],
                    'direct_overrides_amount' => $checkStatus['direct_overrides_amount'],
                    'direct_overrides_type' => $checkStatus['direct_overrides_type'],
                    'indirect_overrides_amount' => $checkStatus['indirect_overrides_amount'],
                    'indirect_overrides_type' => $checkStatus['indirect_overrides_type'],
                    'office_overrides_amount' => $checkStatus['office_overrides_amount'],
                    'office_overrides_type' => $checkStatus['office_overrides_type'],
                    'office_stack_overrides_amount' => $checkStatus['office_stack_overrides_amount'],
                    'withheld_amount' => $checkStatus['withheld_amount'],
                    'withheld_type' => $checkStatus['withheld_type'],
                    'probation_period' => $checkStatus['probation_period'],
                    'hiring_bonus_amount' => $checkStatus['hiring_bonus_amount'],
                    'date_to_be_paid' => $checkStatus['date_to_be_paid'],
                    'period_of_agreement_start_date' => $checkStatus['period_of_agreement_start_date'],
                    'end_date' => $checkStatus['end_date'],
                    'offer_include_bonus' => $checkStatus['offer_include_bonus'],
                    'offer_expiry_date' => $checkStatus['offer_expiry_date'],
                    'office_id' => $checkStatus['office_id'],
                    // 'password' => Hash::make($randomPassword),
                    'password' => $randPassForUsers['password'],
                    'status_id' => 1,
                    'commission_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'self_gen_commission_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'upfront_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'self_gen_upfront_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'withheld_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'self_gen_withheld_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'override_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    'position_id_effective_date' => $checkStatus['period_of_agreement_start_date'],
                    // 'entity_type' => 'individual'
                    // W2 emp
                    'worker_type' => $checkStatus['positionDetail']['worker_type'],
                    'pay_type' => $checkStatus['pay_type'],
                    'pay_rate' => $checkStatus['pay_rate'],
                    'pay_rate_type' => $checkStatus['pay_rate_type'],
                    'expected_weekly_hours' => $checkStatus['expected_weekly_hours'],
                    'overtime_rate' => $checkStatus['overtime_rate'],
                    'pto_hours' => $checkStatus['pto_hours'],
                    'unused_pto_expires' => $checkStatus['unused_pto_expires'],
                    'employee_admin_only_fields' => $checkStatus['employee_admin_only_fields'],
                ];

                // dd(CompanyProfile::PEST_COMPANY_TYPE);
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $userDataToCreate['redline'] = null;
                    $userDataToCreate['redline_amount_type'] = null;
                    $userDataToCreate['redline_type'] = null;
                    $userDataToCreate['self_gen_redline'] = null;
                    $userDataToCreate['self_gen_redline_amount_type'] = null;
                    $userDataToCreate['self_gen_redline_type'] = null;
                    $userDataToCreate['redline_effective_date'] = null;
                    $userDataToCreate['self_gen_redline_effective_date'] = null;

                    $userDataToCreate['self_gen_accounts'] = null;
                    $userDataToCreate['self_gen_type'] = null;
                    $userDataToCreate['self_gen_commission'] = null;
                    $userDataToCreate['self_gen_commission_type'] = null;
                    $userDataToCreate['self_gen_upfront_amount'] = null;
                    $userDataToCreate['self_gen_upfront_type'] = null;
                    $userDataToCreate['self_gen_withheld_amount'] = null;
                    $userDataToCreate['self_gen_withheld_type'] = null;
                    $userDataToCreate['self_gen_commission_effective_date'] = null;
                    $userDataToCreate['self_gen_upfront_effective_date'] = null;
                    $userDataToCreate['self_gen_withheld_effective_date'] = null;
                } else {
                    $userDataToCreate['redline'] = $checkStatus['redline'];
                    $userDataToCreate['redline_amount_type'] = $checkStatus['redline_amount_type'];
                    $userDataToCreate['redline_type'] = $checkStatus['redline_type'];
                    $userDataToCreate['self_gen_redline'] = $checkStatus['self_gen_redline'];
                    $userDataToCreate['self_gen_redline_amount_type'] = $checkStatus['self_gen_redline_amount_type'];
                    $userDataToCreate['self_gen_redline_type'] = $checkStatus['self_gen_redline_type'];
                    $userDataToCreate['redline_effective_date'] = $checkStatus['period_of_agreement_start_date'];
                    $userDataToCreate['self_gen_redline_effective_date'] = $checkStatus['period_of_agreement_start_date'];

                    $userDataToCreate['self_gen_accounts'] = $checkStatus['self_gen_accounts'];
                    $userDataToCreate['self_gen_type'] = $checkStatus['self_gen_type'];
                    $userDataToCreate['self_gen_commission'] = $checkStatus['self_gen_commission'];
                    $userDataToCreate['self_gen_commission_type'] = $checkStatus['self_gen_commission_type'];
                    $userDataToCreate['self_gen_upfront_amount'] = $checkStatus['self_gen_upfront_amount'];
                    $userDataToCreate['self_gen_upfront_type'] = $checkStatus['self_gen_upfront_type'];
                    $userDataToCreate['self_gen_withheld_amount'] = $checkStatus['self_gen_withheld_amount'];
                    $userDataToCreate['self_gen_withheld_type'] = $checkStatus['self_gen_withheld_type'];
                    $userDataToCreate['self_gen_commission_effective_date'] = $checkStatus['period_of_agreement_start_date'];
                    $userDataToCreate['self_gen_upfront_effective_date'] = $checkStatus['period_of_agreement_start_date'];
                    $userDataToCreate['self_gen_withheld_effective_date'] = $checkStatus['period_of_agreement_start_date'];
                }

                if ($checkStatus['self_gen_commission_type'] == null) {
                    unset($userDataToCreate['self_gen_commission_type']);
                }

                $data = User::create($userDataToCreate);
                $new_created_user_id = $data->id;
                // update send document
                $workEmail = OnboardingAdditionalEmails::where('onboarding_user_id', $onbarding_user_id)->get();
                if (count($workEmail) > 0) {
                    foreach ($workEmail as $workEmails) {
                        $userAddiemail = UsersAdditionalEmail::where('email', $workEmails->email)->first();
                        if ($userAddiemail == '') {
                            UsersAdditionalEmail::create(['user_id' => $data->id, 'email' => $workEmails->email]);
                        }
                    }
                }

                // dump($request->all());

                $sentDocs = NewSequiDocsDocument::where('user_id', '=', $onbarding_user_id)
                    ->where('user_id_from', '=', 'onboarding_employees')->get();

                if ($sentDocs->isNotEmpty()) {

                    foreach ($sentDocs as $sentDoc) {
                        // dump($sentDoc);
                        if ($sentDoc->smart_text_template_fied_keyval) {

                            $decodedVal = json_decode($sentDoc->smart_text_template_fied_keyval);
                            // dump(json_decode($sentDoc->smart_text_template_fied_keyval));

                            if (! empty($decodedVal)) {

                                $smartTxtTmplID = null;
                                $smartTxtTmplCatID = null;
                                $smartTxtTmplName = null;

                                if ($decodedVal->id) {
                                    $smartTxtTmplID = $decodedVal->id;
                                }
                                if ($decodedVal->category_id) {
                                    $smartTxtTmplCatID = $decodedVal->category_id;
                                }
                                if ($decodedVal->template_name) {
                                    $smartTxtTmplName = $decodedVal->template_name;
                                }

                                // update val
                                $updated_smart_text_template_fied_keyval = [];
                                foreach ($request->custom_fields as $key => $custom_field) {
                                    if ($custom_field['id'] == $smartTxtTmplID) {
                                        $updated_smart_text_template_fied_keyval = $request->custom_fields[$key];
                                    }
                                }
                                NewSequiDocsDocument::where('user_id', '=', $onbarding_user_id)
                                    ->where('user_id_from', '=', 'onboarding_employees')
                                    ->where('is_active', 1)
                                    ->where('smart_text_template_fied_keyval', 'like', '%{"id":'.$smartTxtTmplID.',"category_id":'.$smartTxtTmplCatID.',"template_name":"'.$smartTxtTmplName.'"%')
                                    ->update([
                                        'smart_text_template_fied_keyval' => $updated_smart_text_template_fied_keyval,
                                    ]);

                            }

                        }
                    }

                }

                // return $sentDocs;
                NewSequiDocsDocument::where('user_id', '=', $onbarding_user_id)
                    ->where('user_id_from', '=', 'onboarding_employees')
                    ->where('is_active', 1)
                    ->Update(['user_id' => $data->id, 'user_id_from' => 'users']); // update all new sequi doc documents when hired
                Documents::where('user_id', '=', $onbarding_user_id)->where('user_id_from', '=', 'onboarding_employees')->Update(['user_id' => $new_created_user_id, 'user_id_from' => 'users']); // update all documents when hired

                $numericCount = 6;
                if ($new_created_user_id) {
                    $numericCount = strlen($new_created_user_id) <= $numericCount ? $numericCount : strlen($new_created_user_id);
                    $EmpId = str_pad($new_created_user_id, $numericCount, '0', STR_PAD_LEFT);
                    $empid = EmployeeIdSetting::orderBy('id', 'asc')->first();
                    if (! empty($empid)) {
                        User::where('id', $data->id)->update(['employee_id' => $empid->id_code.$EmpId]);
                    } else {
                        User::where('id', $data->id)->update(['employee_id' => 'EMP'.$EmpId]);
                    }
                }

                // team member create code start
                if (! empty($data->team_id)) {
                    $teamLeadId = ManagementTeam::where('id', $data->team_id)->first();
                    if ($teamLeadId) {
                        ManagementTeamMember::Create([
                            'team_id' => $teamLeadId->id,
                            'team_lead_id' => $teamLeadId->team_lead_id,
                            'team_member_id' => $data->id,
                        ]);
                    }
                }

                // team member create code end
                OnboardingEmployees::where('email', $data->email)->update(['user_id' => $data->id]);

                $userdata = User::where('id', $data->id)->first();

                // MANAGER HISTORY CREATE
                if ($userdata->manager_id) {
                    UserManagerHistory::create([
                        'user_id' => $userdata->id,
                        'updater_id' => Auth()->user()->id,
                        'effective_date' => $userdata->period_of_agreement_start_date,
                        'manager_id' => $userdata->manager_id,
                        'team_id' => $userdata->team_id,
                        'position_id' => $userdata->position_id,
                        'sub_position_id' => $userdata->sub_position_id,
                    ]);
                }

                // IS MANAGER HISTORY CREATE
                UserIsManagerHistory::create([
                    'user_id' => $userdata->id,
                    'updater_id' => Auth()->user()->id,
                    'effective_date' => $userdata->period_of_agreement_start_date,
                    'is_manager' => $userdata->is_manager,
                    'position_id' => $userdata->position_id,
                    'sub_position_id' => $userdata->sub_position_id,
                ]);

                // UserTransferHistory data create code start
                if (isset($checkStatus['commission_selfgen']) && $checkStatus['commission_selfgen'] != null) {
                    if ($checkStatus['position_id'] == '2') {
                        $selfGenPosition = '3';
                        $selfGenSubPosition = '3';
                    } else {
                        $selfGenPosition = '2';
                        $selfGenSubPosition = '2';
                    }
                    UserSelfGenCommmissionHistory::create([
                        'user_id' => $data->id,
                        'updater_id' => Auth()->user()->id,
                        'commission' => $checkStatus['commission_selfgen'],
                        'commission_type' => $checkStatus['commission_selfgen_type'],
                        'commission_effective_date' => $checkStatus['period_of_agreement_start_date'],
                        'old_commission' => 0,
                        'position_id' => $selfGenPosition,
                        'sub_position_id' => $selfGenSubPosition,
                    ]);
                }

                $transfer = [
                    'user_id' => $userdata->id,
                    'transfer_effective_date' => $userdata->period_of_agreement_start_date,
                    'updater_id' => Auth()->user()->id,
                    'state_id' => $userdata->state_id,
                    'old_state_id' => null,
                    'office_id' => $userdata->office_id,
                    'old_office_id' => null,
                    'department_id' => $userdata->department_id,
                    'old_department_id' => null,
                    'position_id' => $userdata->position_id,
                    'old_position_id' => null,
                    'sub_position_id' => $userdata->sub_position_id,
                    'old_sub_position_id' => null,
                    'is_manager' => $userdata->is_manager,
                    'old_is_manager' => null,
                    'manager_id' => $userdata->manager_id,
                    'old_manager_id' => null,
                    'team_id' => $userdata->team_id,
                    'old_team' => null,
                    'existing_employee_new_manager_id' => null,
                    'existing_employee_old_manager_id' => null,
                ];
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $transfer['redline_amount_type'] = null;
                    $transfer['old_redline_amount_type'] = null;
                    $transfer['redline'] = null;
                    $transfer['old_redline'] = null;
                    $transfer['redline_type'] = null;
                    $transfer['old_redline_type'] = null;
                    $transfer['self_gen_redline_amount_type'] = null;
                    $transfer['old_self_gen_redline_amount_type'] = null;
                    $transfer['self_gen_redline'] = null;
                    $transfer['old_self_gen_redline'] = null;
                    $transfer['self_gen_redline_type'] = null;
                    $transfer['old_self_gen_redline_type'] = null;
                    $transfer['self_gen_accounts'] = null;
                    $transfer['old_self_gen_accounts'] = null;
                } else {
                    $transfer['redline_amount_type'] = $userdata->redline_amount_type;
                    $transfer['old_redline_amount_type'] = null;
                    $transfer['redline'] = $userdata->redline;
                    $transfer['old_redline'] = null;
                    $transfer['redline_type'] = $userdata->redline_type;
                    $transfer['old_redline_type'] = null;
                    $transfer['self_gen_redline_amount_type'] = $userdata->self_gen_redline_amount_type;
                    $transfer['old_self_gen_redline_amount_type'] = null;
                    $transfer['self_gen_redline'] = $userdata->self_gen_redline;
                    $transfer['old_self_gen_redline'] = null;
                    $transfer['self_gen_redline_type'] = $userdata->self_gen_redline_type;
                    $transfer['old_self_gen_redline_type'] = null;
                    $transfer['self_gen_accounts'] = $userdata->self_gen_accounts;
                    $transfer['old_self_gen_accounts'] = null;
                }
                UserTransferHistory::create($transfer);

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    // No Need To Create HubSpot Data
                } else {
                    $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                    $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                    if (! empty($CrmData) && ! empty($CrmSetting)) {
                        $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                        $val = json_decode($decreptedValue);
                        $token = $val->api_key;
                        $checkStatus->status = 'Onboarding';
                        $this->hubspotSaleDataCreate($data, $checkStatus, $uid, $token);
                    }
                    // Push Rep Data to Hubspot Current Energy
                    $integration = Integration::where(['name' => 'Hubspot Current Energy', 'status' => 1])->first();
                    $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                    if (! empty($integration) && ! empty($hubspotCurrentEnergyToken)) {
                        // $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                        $this->pushRepDataToHubspotCurrentEnergy($data, $checkStatus, $uid, $hubspotCurrentEnergyToken);
                    }

                }

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    // No Need To Create JobNimbus Data
                } else {
                    $jobNimbusCrmData = Crms::whereHas('crmSetting')->with('crmSetting')->where('id', 4)->where('status', 1)->first();
                    if (! empty($jobNimbusCrmData)) {
                        $decreptedValue = openssl_decrypt($jobNimbusCrmData->crmSetting->value, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                        $jobNimbusCrmSetting = json_decode($decreptedValue);
                        $jobNimbusToken = $jobNimbusCrmSetting->api_key;
                        $postDataToJobNimbus = [
                            'display_name' => $userdata['first_name'].' '.$userdata['last_name'],
                            'email' => $userdata['email'],
                            'home_phone' => $userdata['mobile_no'],
                            'first_name' => $userdata['first_name'],
                            'last_name' => $userdata['last_name'],
                            'record_type_name' => 'Subcontractor',
                            'status_name' => 'Solar Reps',
                            'external_id' => $userdata['employee_id'],
                            // 'date_end' => $userdata['end_date'],
                            // 'date_start' => $userdata['period_of_agreement_start_date']
                        ];
                        $responseJobNimbuscontats = $this->storeJobNimbuscontats($postDataToJobNimbus, $jobNimbusToken);
                        if ($responseJobNimbuscontats['status'] === true) {
                            User::where('id', $data->id)->update([
                                'jobnimbus_jnid' => $responseJobNimbuscontats['data']['jnid'],
                                'jobnimbus_number' => $responseJobNimbuscontats['data']['number'],
                            ]);
                        }
                    }
                }

                $additionalRecruters = AdditionalRecruiters::where('hiring_id', $request->employee_id)->where('recruiter_id', '<>', null)->get();
                if (count($additionalRecruters)) {
                    $idd = $data->id;
                    foreach ($additionalRecruters as $key => $value) {
                        AdditionalRecruiters::where('id', $value['id'])->update(['user_id' => $idd]);
                        if ($key == 0) {
                            $data1 = [
                                'additional_recruiter_id1' => $value['recruiter_id'],
                                'additional_recruiter1_per_kw_amount' => $value['system_per_kw_amount'],
                            ];
                            User::where('id', $idd)->update($data1);
                        } else {
                            $data2 = [
                                'additional_recruiter_id2' => $value['recruiter_id'],
                                'additional_recruiter2_per_kw_amount' => $value['system_per_kw_amount'],
                            ];
                            User::where('id', $idd)->update($data2);
                        }
                    }
                }

                $additionalLocations = OnboardingEmployeeLocations::where('user_id', $request->employee_id)->get();
                $additionalOfficeCheck = PositionOverride::where(['position_id' => $userdata->sub_position_id, 'override_id' => PositionOverride::OFFICE_OVERRIDE_TYPE_ID, 'status' => '1'])->first();
                if ($additionalLocations) {
                    foreach ($additionalLocations as $additional_location) {
                        AdditionalLocations::create([
                            'updater_id' => Auth()->user()->id,
                            'effective_date' => $checkStatus['period_of_agreement_start_date'],
                            'state_id' => $additional_location['state_id'],
                            'city_id' => null, // $additional_location['city_id'],
                            'user_id' => $data->id,
                            'office_id' => $additional_location['office_id'],
                            'overrides_amount' => isset($additional_location['overrides_amount']) ? $additional_location['overrides_amount'] : 0,
                            'overrides_type' => isset($additional_location['overrides_type']) ? $additional_location['overrides_type'] : '',
                        ]);

                        UserAdditionalOfficeOverrideHistory::create([
                            'user_id' => $data->id,
                            'updater_id' => Auth()->user()->id,
                            'override_effective_date' => $checkStatus['period_of_agreement_start_date'],
                            'state_id' => $additional_location['state_id'],
                            'office_id' => $additional_location['office_id'],
                            'office_overrides_amount' => isset($additional_location['overrides_amount']) ? $additional_location['overrides_amount'] : 0,
                            'office_overrides_type' => isset($additional_location['overrides_type']) ? $additional_location['overrides_type'] : '',
                        ]);
                    }
                }
            }
            $statusUpdate = OnboardingEmployees::find($request->employee_id);
            $statusUpdate->status_id = 7;
            if ($request->hiring_type == 'Directly') {
                $statusUpdate->hiring_type = 'Directly';
            }
            $statusUpdate->save();

            UserOrganizationHistory::create([
                'user_id' => $userdata->id,
                'updater_id' => auth()->user()->id,
                'old_manager_id' => null,
                'old_team_id' => null,
                'manager_id' => $userdata->manager_id,
                'team_id' => $userdata->team_id,
                'old_position_id' => null,
                'old_sub_position_id' => null,
                'position_id' => $userdata->position_id,
                'sub_position_id' => $userdata->sub_position_id,
                'existing_employee_new_manager_id' => null,
                'effective_date' => $checkStatus['period_of_agreement_start_date'],
                'is_manager' => $userdata->is_manager,
                'old_is_manager' => null,
                'self_gen_accounts' => $checkStatus['self_gen_accounts'],
                'old_self_gen_accounts' => null,
            ]);

            $deduction = EmployeeOnboardingDeduction::where('user_id', $request->employee_id)->get();
            UserDeduction::where('user_id', $data->id)->delete();
            foreach ($deduction as $deductions) {
                UserDeduction::create([
                    'deduction_type' => $deductions['deduction_type'],
                    'cost_center_name' => $deductions['cost_center_name'],
                    'cost_center_id' => $deductions['cost_center_id'],
                    'ammount_par_paycheck' => $deductions['ammount_par_paycheck'],
                    'deduction_setting_id' => isset($deductions['deduction_setting_id']) ? $deductions['deduction_setting_id'] : null,
                    'position_id' => $deductions['position_id'],
                    'sub_position_id' => $userdata->sub_position_id,
                    'user_id' => $data->id,
                    'effective_date' => $checkStatus['period_of_agreement_start_date'],
                ]);

                UserDeductionHistory::create([
                    'user_id' => $data->id,
                    'updater_id' => auth()->user()->id,
                    'cost_center_id' => $deductions['cost_center_id'],
                    'amount_par_paycheque' => $deductions['ammount_par_paycheck'],
                    'old_amount_par_paycheque' => null,
                    'effective_date' => $checkStatus['period_of_agreement_start_date'],
                ]);
            }
            $onboard_redline_data = OnboardingUserRedline::where('user_id', $request->employee_id)->get();

            $user_data = User::where('id', $data->id)->first();
            foreach ($onboard_redline_data as $key => $ord) {
                if ($key == 0) {
                    $self_gen_user = 0;
                    $sub_position_id = $user_data->sub_position_id;
                } else {
                    $self_gen_user = 1;
                    $sub_position_id = $ord['position_id'];
                }
                UserCommissionHistory::create([
                    'user_id' => $data->id,
                    'commission_effective_date' => $checkStatus['period_of_agreement_start_date'], // $ord['commission_effective_date'],
                    'position_id' => $ord['position_id'],
                    'sub_position_id' => $sub_position_id,
                    'updater_id' => $ord['updater_id'],
                    'self_gen_user' => $self_gen_user,
                    'commission' => $ord['commission'],
                    'commission_type' => $ord['commission_type'],
                    'custom_sales_field_id' => $ord['custom_sales_field_id'] ?? null,
                ]);

                if (isset($ord['upfront_pay_amount'])) {
                    UserUpfrontHistory::create([
                        'user_id' => $data->id,
                        'upfront_effective_date' => $checkStatus['period_of_agreement_start_date'], // $ord['upfront_effective_date'],
                        'position_id' => $ord['position_id'],
                        'sub_position_id' => $sub_position_id,
                        'updater_id' => $ord['updater_id'],
                        'self_gen_user' => $self_gen_user,
                        'upfront_pay_amount' => $ord['upfront_pay_amount'],
                        'upfront_sale_type' => $ord['upfront_sale_type'],
                        'custom_sales_field_id' => $ord['upfront_custom_sales_field_id'] ?? null,
                    ]);
                }

                if (CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first()) {
                    if (isset($ord['withheld_amount']) && $ord['withheld_amount'] > 0) {
                        UserWithheldHistory::create([
                            'user_id' => $data->id,
                            'updater_id' => $ord['updater_id'],
                            'position_id' => $ord['position_id'],
                            'sub_position_id' => $sub_position_id,
                            'withheld_type' => $ord['withheld_type'],
                            'withheld_amount' => $ord['withheld_amount'],
                            'self_gen_user' => $self_gen_user,
                            'withheld_effective_date' => $checkStatus['period_of_agreement_start_date'], // $ord['withheld_effective_date']
                        ]);
                    }
                }

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    UserRedlines::where(['user_id' => $data->id])->delete();
                } else {
                    UserRedlines::create([
                        'user_id' => $data->id,
                        'start_date' => $checkStatus['period_of_agreement_start_date'], // $ord['start_date'],
                        'position_type' => $ord['position_id'],
                        'sub_position_type' => $sub_position_id,
                        'updater_id' => $ord['updater_id'],
                        'redline_amount_type' => $ord['redline_amount_type'],
                        'redline' => $ord['redline'],
                        'redline_type' => $ord['redline_type'],
                        'withheld_amount' => isset($ord['withheld_amount']) ? $ord['withheld_amount'] : '',
                        'self_gen_user' => $self_gen_user,
                    ]);
                }

                if ($key == 0) {
                    $user_data->commission_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['commission_effective_date'];
                    $user_data->withheld_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['withheld_effective_date'];
                    $user_data->upfront_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['upfront_effective_date'];
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $user_data->redline_effective_date = null;
                    } else {
                        $user_data->redline_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['redline_effective_date'];
                    }
                } else {
                    $user_data->self_gen_commission_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['commission_effective_date'];
                    $user_data->self_gen_withheld_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['withheld_effective_date'];
                    $user_data->self_gen_upfront_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['upfront_effective_date'];
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $user_data->self_gen_redline_effective_date = null;
                    } else {
                        $user_data->self_gen_redline_effective_date = $checkStatus['period_of_agreement_start_date']; // $ord['redline_effective_date'];
                    }
                }
            }

            $onboard_override_data = OnboardingEmployeeOverride::where('user_id', $request->employee_id)->first();
            if (! empty($onboard_override_data)) {
                UserOverrideHistory::create([
                    'user_id' => $data->id,
                    'override_effective_date' => $checkStatus['period_of_agreement_start_date'], // isset($onboard_override_data->override_effective_date)?$onboard_override_data->override_effective_date:date('Y-m-d'),
                    'updater_id' => $onboard_override_data->updater_id,
                    'direct_overrides_amount' => $onboard_override_data->direct_overrides_amount,
                    'direct_overrides_type' => $onboard_override_data->direct_overrides_type,
                    'indirect_overrides_amount' => $onboard_override_data->indirect_overrides_amount,
                    'indirect_overrides_type' => $onboard_override_data->indirect_overrides_type,
                    'office_overrides_amount' => $onboard_override_data->office_overrides_amount,
                    'office_overrides_type' => $onboard_override_data->office_overrides_type,
                    'office_stack_overrides_amount' => $onboard_override_data->office_stack_overrides_amount,
                    // Custom Sales Field IDs
                    'direct_custom_sales_field_id' => $onboard_override_data->direct_custom_sales_field_id ?? null,
                    'indirect_custom_sales_field_id' => $onboard_override_data->indirect_custom_sales_field_id ?? null,
                    'office_custom_sales_field_id' => $onboard_override_data->office_custom_sales_field_id ?? null,
                ]);
                $user_data->override_effective_date = $checkStatus['period_of_agreement_start_date']; // isset($onboard_override_data->override_effective_date)?$onboard_override_data->override_effective_date:date('Y-m-d');
            }

            UserWagesHistory::create([
                'user_id' => $data->id,
                'updater_id' => auth()->user()->id,
                'effective_date' => isset($checkStatus['period_of_agreement_start_date']) ? $checkStatus['period_of_agreement_start_date'] : null,
                'pay_type' => $checkStatus['pay_type'],
                'pay_rate' => $checkStatus['pay_rate'],
                'pay_rate_type' => $checkStatus['pay_rate_type'],
                'expected_weekly_hours' => $checkStatus['expected_weekly_hours'],
                'overtime_rate' => $checkStatus['overtime_rate'],
                'pto_hours' => $checkStatus['pto_hours'],
                'unused_pto_expires' => $checkStatus['unused_pto_expires'],
                'pto_hours_effective_date' => isset($checkStatus['period_of_agreement_start_date']) ? $checkStatus['period_of_agreement_start_date'] : null,
            ]);

            $user_data->save();

            $new_user_data = $check = User::where('id', $data->id)->first();
            // $check['new_password'] = $randomPassword;
            $check['new_password'] = $randPassForUsers['plain_password'];

            // New mail send funcnality.
            $other_data = [];
            // $other_data['new_password'] = $randomPassword;
            $other_data['new_password'] = $randPassForUsers['plain_password'];
            $welcome_email_content = SequiDocsEmailSettings::welcome_email_content($new_user_data, $other_data);
            $email_content['email'] = $new_user_data->email;
            $email_content['subject'] = $welcome_email_content['subject'];
            $email_content['template'] = $welcome_email_content['template'];
            $message = 'Employee Hired Credentials Send Successfully.';
            $user_email_for_send_email = $new_user_data->email;
            $check_domain_setting = DomainSetting::check_domain_setting($user_email_for_send_email);
            if ($check_domain_setting['status'] == true) {
                if ($welcome_email_content['is_active'] == 1 && $welcome_email_content['template'] != '') {
                    $this->sendEmailNotification($email_content);
                } else {
                    $salesData = [];
                    $salesData['email'] = $check->email;
                    $salesData['subject'] = 'Login Credentials';
                    $salesData['template'] = view('mail.credentials', compact('check'));
                    $this->sendEmailNotification($salesData);
                }
            } else {
                $message = 'Employee Hired but '.$check_domain_setting['message'];
            }

            Notification::create([
                'user_id' => $check->id,
                'type' => 'Employee Hired',
                'description' => 'Employee Hired by'.auth()->user()->first_name,
                'is_read' => 0,
            ]);

            $notificationData = [
                'user_id' => $check->id,
                'device_token' => $check->device_token,
                'title' => 'Employee Hired.',
                'sound' => 'sound',
                'type' => 'Employee Hired',
                'body' => 'Employee Hired by '.auth()->user()->first_name,
            ];
            $this->sendNotification($notificationData);

            DB::commit();

            // code added by anurag
            $IntegrationCheck = Integration::where(['name' => 'Solerro', 'status' => 1])->first();
            if ($IntegrationCheck) {
                $data = [
                    'sequifi_id' => $data->id,
                    'employee_id' => $data->employee_id,
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'email' => $data->email,
                    'mobile_no' => $data->mobile_no,
                ];
                // Call the trait method to send the request to the API
                $sendEmployeeRequestresponse = $this->SolerroSendEmployeeRequest($data);
            }
            // end code

            // Process FieldRoutes data for the new user
            // Load the user with additionalEmails relationship for complete email checking
            $userWithEmails = User::with('additionalEmails')->find($check->id);
            $this->processFieldRoutesUserData($userWithEmails);

            return response()->json([
                'ApiName' => 'Send Credentials',
                'status' => true,
                'message' => $message,
                'welcome_email_content' => $welcome_email_content,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::debug($e);

            return response()->json([
                'ApiName' => 'Send Credentials',
                'status' => false,
                'message' => 'Something went wrong while processing this request!',
                'welcome_email_content' => '',
            ], 500);
        }

    }

    public function hiredEmployee(Request $request)
    {
        /* Note any type of changes in hiredEmployee this function, need to change same in hiredEmployee_from_call_back function return in hiredEmployee_from_call_back controller. */
        try {
            DB::beginTransaction();
            $randPassForUsers = randPassForUsers();
            $onbarding_user_id = $request->employee_id;
            $onbardingUserId = $onbarding_user_id;
            $employee = OnboardingEmployees::find($onbardingUserId);
            $onBoardingEmployee = OnboardingEmployees::with('positionDetail')->where('id', $onbarding_user_id)->first();

            if (! $onBoardingEmployee) {
                return [
                    'ApiName' => 'Send Credentials',
                    'status' => false,
                    'message' => 'Employee Id Not Found.',
                ];
            }

            $group_id = $onBoardingEmployee->positionDetail->group_id;
            $userId = Auth()->user();
            $uid = ($userId->is_super_admin == 0) ? $userId->id : null;
            $substr = 0;

            if ($onBoardingEmployee) {
                $usereEail = User::where('email', $onBoardingEmployee['email'])->first();
                if (empty($usereEail)) {
                    $additional_user_id = UsersAdditionalEmail::where('email', $onBoardingEmployee['email'])->value('user_id');
                    if (! empty($additional_user_id)) {
                        $usereEail = User::where('id', $additional_user_id)->first();
                    }
                }

                if ($usereEail) {
                    return response()->json([
                        'ApiName' => 'Send Credentials',
                        'status' => false,
                        'message' => 'Email is already exist',
                    ], 400);
                }

                $user_mobile_no = User::where('mobile_no', $onBoardingEmployee['mobile_no'])->first();
                if ($user_mobile_no) {
                    return response()->json([
                        'ApiName' => 'Send Credentials',
                        'status' => false,
                        'message' => 'Mobile no is already exist',
                    ], 400);
                }

                $randomPassword = Str::random(10);
                $companyProfile = CompanyProfile::first();
                $effectiveDate = $onBoardingEmployee->period_of_agreement_start_date;
                $groupId = $onBoardingEmployee->positionDetail->group_id;
                $userDataToCreate = [
                    'aveyo_hs_id' => $onBoardingEmployee->aveyo_hs_id,
                    'first_name' => $onBoardingEmployee->first_name,
                    'last_name' => $onBoardingEmployee->last_name,
                    'email' => $onBoardingEmployee->email,
                    'mobile_no' => $onBoardingEmployee->mobile_no,
                    'state_id' => $onBoardingEmployee->state_id,
                    'city_id' => $onBoardingEmployee->city_id,
                    'self_gen_accounts' => $onBoardingEmployee->self_gen_accounts,
                    'self_gen_type' => $onBoardingEmployee->self_gen_type,
                    'department_id' => isset($onBoardingEmployee->department_id) ? $onBoardingEmployee->department_id : null,
                    'position_id' => $onBoardingEmployee->position_id,
                    'sub_position_id' => $onBoardingEmployee->sub_position_id,
                    'is_manager' => $onBoardingEmployee->is_manager,
                    'is_manager_effective_date' => ($onBoardingEmployee->is_manager == 1) ? $effectiveDate : null,
                    'manager_id' => $onBoardingEmployee->manager_id,
                    'manager_id_effective_date' => $effectiveDate,
                    'team_id' => $onBoardingEmployee->team_id,
                    'team_id_effective_date' => (! empty($onBoardingEmployee->team_id)) ? $effectiveDate : null,
                    'recruiter_id' => isset($onBoardingEmployee->recruiter_id) ? $onBoardingEmployee->recruiter_id : $uid,
                    'group_id' => $groupId,
                    'probation_period' => $onBoardingEmployee->probation_period,
                    'hiring_bonus_amount' => $onBoardingEmployee->hiring_bonus_amount,
                    'date_to_be_paid' => $onBoardingEmployee->date_to_be_paid,
                    'period_of_agreement_start_date' => $effectiveDate,
                    'end_date' => $onBoardingEmployee->end_date,
                    'offer_include_bonus' => $onBoardingEmployee->offer_include_bonus,
                    'offer_expiry_date' => $onBoardingEmployee->offer_expiry_date,
                    'office_id' => $onBoardingEmployee->office_id,
                    // 'password' => Hash::make('Newuser#123'),
                    'password' => $randPassForUsers['password'],
                    'status_id' => 1,
                    'commission_effective_date' => $effectiveDate,
                    'self_gen_commission_effective_date' => $effectiveDate,
                    'upfront_effective_date' => $effectiveDate,
                    'self_gen_upfront_effective_date' => $effectiveDate,
                    'withheld_effective_date' => $effectiveDate,
                    'self_gen_withheld_effective_date' => $effectiveDate,
                    'override_effective_date' => $effectiveDate,
                    'position_id_effective_date' => $effectiveDate,
                    'worker_type' => $onBoardingEmployee->positionDetail->worker_type,
                    'pay_type' => $onBoardingEmployee->pay_type,
                    'pay_rate' => $onBoardingEmployee->pay_rate,
                    'pay_rate_type' => $onBoardingEmployee->pay_rate_type,
                    'expected_weekly_hours' => $onBoardingEmployee->expected_weekly_hours,
                    'overtime_rate' => $onBoardingEmployee->overtime_rate,
                    'pto_hours' => $onBoardingEmployee->pto_hours,
                    'unused_pto_expires' => $onBoardingEmployee->unused_pto_expires,
                ];
                $data = User::create($userDataToCreate);
                $userId = $data->id;

                $eId = User::whereNotNull('employee_id')->orderBy('employee_id', 'DESC')->pluck('employee_id')->first();
                $numericCount = 6;
                if ($eId) {
                    $lettersOnly = preg_replace("/\d+$/", '', $eId);
                    $substr = str_replace($lettersOnly, '', $eId);
                    $numericCount = strlen($substr);
                }

                $val = $substr + 1;
                $EmpId = str_pad($val, $numericCount, '0', STR_PAD_LEFT);
                $empId = EmployeeIdSetting::orderBy('id', 'asc')->first();
                if (! empty($empId)) {
                    User::where('id', $userId)->update(['employee_id' => $empId->id_code.$EmpId]);
                } else {
                    User::where('id', $userId)->update(['employee_id' => 'EMP'.$EmpId]);
                }

                $workEmail = OnboardingAdditionalEmails::where('onboarding_user_id', $onbardingUserId)->get();
                foreach ($workEmail as $workEmails) {
                    $userAddiemail = UsersAdditionalEmail::where('email', $workEmails->email)->first();
                    if ($userAddiemail == '') {
                        UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $workEmails->email]);
                    }
                }

                NewSequiDocsDocument::where('user_id', '=', $onbardingUserId)->where('user_id_from', '=', 'onboarding_employees')->where('is_active', 1)->Update(['user_id' => $data->id, 'user_id_from' => 'users']);
                Documents::where('user_id', '=', $onbardingUserId)->where('user_id_from', '=', 'onboarding_employees')->Update(['user_id' => $userId, 'user_id_from' => 'users']);

                if (! empty($data->team_id)) {
                    $teamLeadId = ManagementTeam::where('id', $data->team_id)->first();
                    if ($teamLeadId) {
                        ManagementTeamMember::Create([
                            'team_id' => $teamLeadId->id,
                            'team_lead_id' => $teamLeadId->team_lead_id,
                            'team_member_id' => $userId,
                        ]);
                    }
                }
                OnboardingEmployees::where('email', $data->email)->update(['user_id' => $userId]);

                $userData = User::where('id', $userId)->first();
                if ($userData->manager_id) {
                    UserManagerHistory::create([
                        'user_id' => $userId,
                        'updater_id' => Auth()->user()->id,
                        'effective_date' => $effectiveDate,
                        'manager_id' => $userData->manager_id,
                        'team_id' => $userData->team_id,
                        'position_id' => $userData->position_id,
                        'sub_position_id' => $userData->sub_position_id,
                    ]);
                }

                UserIsManagerHistory::create([
                    'user_id' => $userId,
                    'updater_id' => Auth()->user()->id,
                    'effective_date' => $effectiveDate,
                    'is_manager' => $userData->is_manager,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                ]);

                $transfer = [
                    'user_id' => $userId,
                    'transfer_effective_date' => $effectiveDate,
                    'updater_id' => Auth()->user()->id,
                    'state_id' => $userData->state_id,
                    'office_id' => $userData->office_id,
                    'department_id' => $userData->department_id,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'is_manager' => $userData->is_manager,
                    'manager_id' => $userData->manager_id,
                    'team_id' => $userData->team_id,
                ];
                UserTransferHistory::create($transfer);

                $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                if (! empty($CrmData) && ! empty($CrmSetting)) {
                    $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                    $val = json_decode($decreptedValue);
                    $token = $val->api_key;
                    $onBoardingEmployee->status = 'Onboarding';
                    $this->hubspotSaleDataCreate($data, $onBoardingEmployee, $uid, $token);
                }
                // Push Rep Data to Hubspot Current Energy
                $integration = Integration::where(['name' => 'Hubspot Current Energy', 'status' => 1])->first();
                if (! empty($integration)) {
                    $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                    $this->pushRepDataToHubspotCurrentEnergy($data, $onBoardingEmployee, $uid, $hubspotCurrentEnergyToken);
                }

                $jobNimbusCrmData = Crms::whereHas('crmSetting')->with('crmSetting')->where('id', 4)->where('status', 1)->first();
                if (! empty($jobNimbusCrmData)) {
                    $decreptedValue = openssl_decrypt($jobNimbusCrmData->crmSetting->value, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                    $jobNimbusCrmSetting = json_decode($decreptedValue);
                    $jobNimbusToken = $jobNimbusCrmSetting->api_key;
                    $postDataToJobNimbus = [
                        'display_name' => $userData['first_name'].' '.$userData['last_name'],
                        'email' => $userData['email'],
                        'home_phone' => $userData['mobile_no'],
                        'first_name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                        'record_type_name' => 'Subcontractor',
                        'status_name' => 'Solar Reps',
                        'external_id' => $userData['employee_id'],
                    ];
                    $responseJobNimbuscontats = $this->storeJobNimbuscontats($postDataToJobNimbus, $jobNimbusToken);
                    if ($responseJobNimbuscontats['status'] === true) {
                        User::where('id', $userId)->update([
                            'jobnimbus_jnid' => $responseJobNimbuscontats['data']['jnid'],
                            'jobnimbus_number' => $responseJobNimbuscontats['data']['number'],
                        ]);
                    }
                }

                $additionalRecruters = AdditionalRecruiters::where('hiring_id', $onbardingUserId)->whereNotNull('recruiter_id')->get();
                AdditionalRecruiters::where('hiring_id', $onbardingUserId)->whereNotNull('recruiter_id')->update(['user_id' => $userId]);
                foreach ($additionalRecruters as $key => $value) {
                    if ($key == 0) {
                        User::where('id', $userId)->update([
                            'additional_recruiter_id1' => $value->recruiter_id,
                            'additional_recruiter1_per_kw_amount' => $value->system_per_kw_amount,
                        ]);
                    } else {
                        User::where('id', $userId)->update([
                            'additional_recruiter_id2' => $value->recruiter_id,
                            'additional_recruiter2_per_kw_amount' => $value->system_per_kw_amount,
                        ]);
                    }
                }

                $additionalLocations = OnboardingEmployeeLocations::where('user_id', $onbardingUserId)->get();
                foreach ($additionalLocations as $additionalLocation) {
                    AdditionalLocations::updateOrCreate([
                        'user_id' => $userId,
                        'state_id' => $additionalLocation->state_id,
                        'office_id' => $additionalLocation->office_id,
                    ], [
                        'updater_id' => Auth()->user()->id,
                        'effective_date' => $effectiveDate,
                        'overrides_amount' => isset($additionalLocation->overrides_amount) ? $additionalLocation->overrides_amount : 0,
                        'overrides_type' => isset($additionalLocation->overrides_type) ? $additionalLocation->overrides_type : null,
                    ]);
                }

                $additionalLocationsOverrides = OnboardingEmployeeAdditionalOverride::with('OnboardingEmployeeLocations')->where('user_id', $onbardingUserId)->get();
                foreach ($additionalLocationsOverrides as $additionalLocationsOverride) {
                    $info = $additionalLocationsOverride->OnboardingEmployeeLocations;
                    $useraddofficeoverhist = UserAdditionalOfficeOverrideHistory::updateOrCreate([
                        'user_id' => $userId,
                        'state_id' => $info->state_id ?? 0,
                        'office_id' => $info->office_id ?? 0,
                        'product_id' => $additionalLocationsOverride->product_id,
                    ], [
                        'updater_id' => Auth()->user()->id,
                        'override_effective_date' => $effectiveDate,
                        'office_overrides_amount' => isset($additionalLocationsOverride->overrides_amount) ? $additionalLocationsOverride->overrides_amount : 0,
                        'office_overrides_type' => isset($additionalLocationsOverride->overrides_type) ? $additionalLocationsOverride->overrides_type : null,
                        'tiers_id' => isset($info->tiers_id) ? $info->tiers_id : null,
                    ]);
                }
            }

            $statusUpdate = OnboardingEmployees::find($onbardingUserId);
            $statusUpdate->status_id = 7;
            if ($request->hiring_type == 'Directly') {
                $statusUpdate->hiring_type = 'Directly';
            }
            $statusUpdate->save();

            $porudcts = PositionProduct::where('position_id', $employee['sub_position_id'])->get();
            foreach ($porudcts as $porudct) {
                UserOrganizationHistory::create([
                    'user_id' => $userId,
                    'updater_id' => auth()->user()->id,
                    'product_id' => $porudct->product_id,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'effective_date' => $effectiveDate,
                    'self_gen_accounts' => $onBoardingEmployee->self_gen_accounts,
                ]);
            }

            $deductions = EmployeeOnboardingDeduction::where('user_id', $onbardingUserId)->get();
            UserDeduction::where('user_id', $userId)->delete();
            foreach ($deductions as $deduction) {
                UserDeduction::create([
                    'deduction_type' => $deduction->deduction_type,
                    'cost_center_name' => $deduction->cost_center_name,
                    'cost_center_id' => $deduction->cost_center_id,
                    'ammount_par_paycheck' => $deduction->ammount_par_paycheck,
                    'deduction_setting_id' => isset($deduction->deduction_setting_id) ? $deduction->deduction_setting_id : null,
                    'position_id' => $deduction->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'user_id' => $userId,
                    'effective_date' => $effectiveDate,
                ]);

                UserDeductionHistory::create([
                    'user_id' => $userId,
                    'updater_id' => auth()->user()->id,
                    'cost_center_id' => $deduction->cost_center_id,
                    'amount_par_paycheque' => $deduction->ammount_par_paycheck,
                    'effective_date' => $effectiveDate,
                ]);
            }

            $commissions = OnboardingUserRedline::where('user_id', $onbardingUserId)->get();
            foreach ($commissions as $commission) {
                $usercommissiondata = UserCommissionHistory::create([
                    'user_id' => $userId,
                    'commission_effective_date' => $effectiveDate,
                    'product_id' => $commission->product_id,
                    'position_id' => $userData->position_id,
                    'core_position_id' => $commission->core_position_id,
                    'sub_position_id' => $commission->position_id,
                    'updater_id' => auth()->user()->id,
                    'self_gen_user' => $commission->self_gen_user,
                    'commission' => $commission->commission,
                    'commission_type' => $commission->commission_type,
                    'tiers_id' => $commission->tiers_id,
                    'custom_sales_field_id' => $commission->custom_sales_field_id ?? null,
                ]);
                if ($this->companySettingtiers?->status) {
                    $range = OnboardingCommissionTiersRange::where('onboarding_commission_id', $commission->id)->get();
                    if ($commission->tiers_id > 0) {
                        if ($range->isNotEmpty()) {
                            foreach ($range as $rang) {
                                UserCommissionHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_commission_history_id' => $usercommissiondata->id ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $redLines = OnboardingEmployeeRedline::where('user_id', $onbardingUserId)->get();
            foreach ($redLines as $redLine) {
                UserRedlines::create([
                    'user_id' => $userId,
                    'start_date' => $effectiveDate,
                    'position_type' => $userData->position_id,
                    'core_position_id' => $redLine->core_position_id,
                    'sub_position_type' => $redLine->position_id,
                    'updater_id' => auth()->user()->id,
                    'redline_amount_type' => $redLine->redline_amount_type,
                    'redline' => $redLine->redline,
                    'redline_type' => $redLine->redline_type,
                    'self_gen_user' => $redLine->self_gen_user,
                ]);
            }

            $withHeld = OnboardingEmployeeWithheld::where('user_id', $onbardingUserId)->get();
            foreach ($withHeld as $value) {
                UserWithheldHistory::create([
                    'user_id' => $userId,
                    'updater_id' => auth()->user()->id,
                    'position_id' => $userData->position_id,
                    'product_id' => $value->product_id,
                    'sub_position_id' => $value->position_id,
                    'withheld_type' => $value->withheld_type,
                    'withheld_amount' => $value->withheld_amount,
                    'withheld_effective_date' => $effectiveDate,
                ]);
            }

            $upfronts = OnboardingEmployeeUpfront::where('user_id', $onbardingUserId)->get();
            foreach ($upfronts as $upfront) {
                $userupfrontdata = UserUpfrontHistory::create([
                    'user_id' => $userId,
                    'upfront_effective_date' => $effectiveDate,
                    'position_id' => $userData->position_id,
                    'core_position_id' => $upfront->core_position_id,
                    'product_id' => $upfront->product_id,
                    'milestone_schema_id' => $upfront->milestone_schema_id,
                    'milestone_schema_trigger_id' => $upfront->milestone_schema_trigger_id,
                    'sub_position_id' => $upfront->position_id,
                    'updater_id' => auth()->user()->id,
                    'self_gen_user' => $upfront->self_gen_user,
                    'upfront_pay_amount' => $upfront->upfront_pay_amount,
                    'upfront_sale_type' => $upfront->upfront_sale_type,
                    'tiers_id' => $upfront->tiers_id,
                    'custom_sales_field_id' => $upfront->custom_sales_field_id ?? null,
                ]);
                if ($this->companySettingtiers?->status) {
                    $range = OnboardingUpfrontsTiersRange::where('onboarding_upfront_id', $upfront->id)->get();
                    if ($upfront->tiers_id > 0) {
                        if ($range->isNotEmpty()) {
                            foreach ($range as $rang) {
                                UserUpfrontHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_upfront_history_id' => $userupfrontdata->id ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $overrides = OnboardingEmployeeOverride::where('user_id', $onbardingUserId)->get();
            foreach ($overrides as $override) {
                $useroverridedata = UserOverrideHistory::create([
                    'user_id' => $userId,
                    'override_effective_date' => $effectiveDate,
                    'updater_id' => auth()->user()->id,
                    'product_id' => $override->product_id,
                    'direct_overrides_amount' => $override->direct_overrides_amount,
                    'direct_overrides_type' => $override->direct_overrides_type,
                    'indirect_overrides_amount' => $override->indirect_overrides_amount,
                    'indirect_overrides_type' => $override->indirect_overrides_type,
                    'office_overrides_amount' => $override->office_overrides_amount,
                    'office_overrides_type' => $override->office_overrides_type,
                    'office_stack_overrides_amount' => $override->office_stack_overrides_amount,
                    'direct_tiers_id' => $override->direct_tiers_id ?? null,
                    'indirect_tiers_id' => $override->indirect_tiers_id ?? null,
                    'office_tiers_id' => $override->office_tiers_id ?? null,
                    // Custom Sales Field IDs
                    'direct_custom_sales_field_id' => $override->direct_custom_sales_field_id ?? null,
                    'indirect_custom_sales_field_id' => $override->indirect_custom_sales_field_id ?? null,
                    'office_custom_sales_field_id' => $override->office_custom_sales_field_id ?? null,
                ]);
                if ($this->companySettingtiers?->status) {
                    $range = OnboardingDirectOverrideTiersRange::where('onboarding_direct_override_id', $override->id)->get();
                    if ($override->direct_tiers_id > 0) {
                        if ($range->isNotEmpty()) {
                            foreach ($range as $rang) {
                                UserDirectOverrideHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_override_history_id' => $useroverridedata->id ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                    $ind_range = OnboardingIndirectOverrideTiersRange::where('onboarding_indirect_override_id', $override->id)->get();
                    if ($override->indirect_tiers_id > 0) {
                        if ($ind_range->isNotEmpty()) {
                            foreach ($ind_range as $rang) {
                                UserIndirectOverrideHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_override_history_id' => $useroverridedata->id ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                    $overoff_range = OnboardingOverrideOfficeTiersRange::where('onboarding_override_office_id', $override->id)->get();
                    if ($override->office_tiers_id > 0) {
                        if ($overoff_range->isNotEmpty()) {
                            foreach ($overoff_range as $rang) {
                                UserOfficeOverrideHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_office_override_history_id' => $useroverridedata->id ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            UserWagesHistory::create([
                'user_id' => $userId,
                'updater_id' => auth()->user()->id,
                'effective_date' => isset($effectiveDate) ? $effectiveDate : null,
                'pay_type' => $onBoardingEmployee->pay_type,
                'pay_rate' => $onBoardingEmployee->pay_rate,
                'pay_rate_type' => $onBoardingEmployee->pay_rate_type,
                'expected_weekly_hours' => $onBoardingEmployee->expected_weekly_hours,
                'overtime_rate' => $onBoardingEmployee->overtime_rate,
                'pto_hours' => $onBoardingEmployee->pto_hours,
                'unused_pto_expires' => $onBoardingEmployee->unused_pto_expires,
                'pto_hours_effective_date' => $effectiveDate,
            ]);

            UserAgreementHistory::create([
                'user_id' => $userId,
                'updater_id' => auth()->user()->id,
                'probation_period' => $onBoardingEmployee->probation_period,
                'offer_include_bonus' => $onBoardingEmployee->offer_include_bonus,
                'hiring_bonus_amount' => $onBoardingEmployee->hiring_bonus_amount,
                'date_to_be_paid' => $onBoardingEmployee->date_to_be_paid,
                'period_of_agreement' => $effectiveDate,
                'end_date' => $onBoardingEmployee->end_date,
                'offer_expiry_date' => $onBoardingEmployee->offer_expiry_date,
                'hired_by_uid' => $onBoardingEmployee->hired_by_uid,
                'hiring_signature' => $onBoardingEmployee->hiring_signature,
            ]);

            // $userData['new_password'] = 'Newuser#123';
            $userData['new_password'] = $randPassForUsers['plain_password'];
            $otherData = [];
            // $otherData['new_password'] = 'Newuser#123';
            $otherData['new_password'] = $randPassForUsers['plain_password'];
            $welcomeEmailContent = SequiDocsEmailSettings::welcome_email_content($userData, $otherData);
            $emailContent['email'] = $userData->email;
            $emailContent['subject'] = $welcomeEmailContent['subject'];
            $emailContent['template'] = $welcomeEmailContent['template'];
            $message = 'Employee Hired Credentials Send Successfully.';
            $checkDomainSetting = DomainSetting::check_domain_setting($userData->email);
            if ($checkDomainSetting['status'] == true) {
                if ($welcomeEmailContent['is_active'] == 1 && $welcomeEmailContent['template'] != '') {
                    $this->sendEmailNotification($emailContent);
                } else {
                    $salesData = [];
                    $salesData['email'] = $userData->email;
                    $salesData['subject'] = 'Login Credentials';
                    $salesData['template'] = view('mail.credentials', compact('userData'));
                    $this->sendEmailNotification($salesData);
                }
            } else {
                $message = 'Employee Hired but '.$checkDomainSetting['message'];
            }

            Notification::create([
                'user_id' => $userData->id,
                'type' => 'Employee Hired',
                'description' => 'Employee Hired by'.auth()->user()->first_name,
                'is_read' => 0,
            ]);

            $notificationData = [
                'user_id' => $userData->id,
                'device_token' => $userData->device_token,
                'title' => 'Employee Hired.',
                'sound' => 'sound',
                'type' => 'Employee Hired',
                'body' => 'Employee Hired by '.auth()->user()->first_name,
            ];
            $this->sendNotification($notificationData);

            DB::commit();

            // code added by anurag
            $IntegrationCheck = Integration::where(['name' => 'Solerro', 'status' => 1])->first();
            if ($IntegrationCheck) {
                $data = [
                    'sequifi_id' => $data->id,
                    'employee_id' => $data->employee_id,
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'email' => $data->email,
                    'mobile_no' => $data->mobile_no,
                ];
                // Call the trait method to send the request to the API
                $sendEmployeeRequestresponse = $this->SolerroSendEmployeeRequest($data);
            }
            // Implements the field routes API for sending onboarding employee data
            $integrationFieldRoutes = Integration::where(['name' => 'FieldRoutes', 'status' => 1])->first();

            if (! empty($integrationFieldRoutes)) {

                $enc_value = openssl_decrypt(
                    $integrationFieldRoutes->value,
                    config('app.encryption_cipher_algo'),
                    config('app.encryption_key'),
                    0,
                    config('app.encryption_iv')
                );
                $dnc_value = json_decode($enc_value);

                $authenticationKey = $dnc_value->authenticationKey;
                $authenticationToken = $dnc_value->authenticationToken;
                $baseURL = $dnc_value->base_url;
                $api_office = $dnc_value->office;
                $checkStatus = 'Onboarding';
                $userId = Auth()->user();
                $uid = ($userId->is_super_admin == 0) ? $userId->id : null;

                // DIRECT FIX: Instead of using the trait method that's causing issues, we'll make a direct API call
                // with the correct dataLink format
                $employeeData = (array) $data;

                // Remove any existing dataLink values that might be causing problems
                if (isset($employeeData['dataLink'])) {
                    unset($employeeData['dataLink']);
                }

                // Set timeMark as a simple string - this is what FieldRoutes API expects
                $employeeData['timeMark'] = Carbon::now()->format('Y-m-d H:i:s');

                // Set dataLinkAlias if we have an employee_id
                if (isset($data->employee_id)) {
                    $employeeData['dataLinkAlias'] = $data->employee_id;
                }

                // Log what we're sending with the exact format
                Log::info('DIRECT FIX: FieldRoutes employee creation with fixed parameters', [
                    'employeeData' => $employeeData,
                ]);

                // Make direct API call
                $url = $baseURL.'/employee/create';
                $jsonData = json_encode($employeeData);

                $headers = [
                    'accept: application/json',
                    'content-type: application/json',
                    'authenticationKey:'.$authenticationKey,
                    'authenticationToken:'.$authenticationToken,
                ];

                // Use curlRequestDataForFieldRoutes from FieldRoutesTrait
                $curl_response = $this->curlRequestDataForFieldRoutes($url, $jsonData, $headers, 'POST');
                $resp = json_decode($curl_response, true);

                Log::info(['DIRECT FIX: FieldRoutes API response' => $resp]);

                // Process the response as usual
                if (isset($resp) && isset($resp['success']) && $resp['success'] == false) {
                    Log::info(['DIRECT FIX: FieldRoutes API error' => $resp]);
                } else {
                    Log::info(['DIRECT FIX: FieldRoutes API success' => true]);

                    if (isset($resp['result'])) {
                        $hs_object_id = $resp['result'];

                        // Update the user with the returned ID
                        $table = 'Onboarding_employee';
                        $check_employee_id = $data->id ?? null;

                        if ($check_employee_id) {
                            $updateuser = OnboardingEmployees::where('id', $check_employee_id)->first();

                            if ($updateuser) {
                                $updateuser->aveyo_hs_id = $hs_object_id;
                                $updateuser->save();
                                Log::info(['DIRECT FIX: FieldRoutes user updated' => true]);
                            }
                        }
                    }
                }
            }
            // End here

            // end code

            // Process FieldRoutes data for the new user
            // Load the user with additionalEmails relationship for complete email checking
            $userWithEmails = User::with('additionalEmails')->find($userData->id);
            $this->processFieldRoutesUserData($userWithEmails);

            return [
                'ApiName' => 'Send Credentials',
                'status' => true,
                'message' => 'Send Credentials Successfully.',
                'onboarding_employee_id' => $request->employee_id,
                'hired_user_id' => $userData->id,
            ];
        } catch (Exception $error) {
            $message = 'something went wrong!!!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();
            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            return ['status_code' => 400, 'message' => $message, 'error' => $error, 'errorDetail' => $errorDetail];
        }
    }

    public function getOnboardingEmployee($id): JsonResponse
    {
        $data = User::with('state', 'city', 'office')->where('id', $id)->first();
        $positionId = $data->position_id;
        $sub_position_id = $data->sub_position_id;
        $company = CompanySetting::where('type', 'reconciliation')->first();
        $data['user_profile_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$data->image);
        if ($company->status == 1) {
            $withHeld = PositionReconciliations::where('position_id', $sub_position_id)->where('status', 1)->first();
            $data['withheld'] = isset($withHeld->commission_withheld) ? $withHeld->commission_withheld : 0;
        } else {
            $data['withheld'] = null;
        }

        return response()->json([
            'ApiName' => 'Get Employee Detail',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * Save employee data to HighLevel
     *
     * @param  object  $user  User object with employee data
     */
    protected function saveEmployeeToHighLevel(object $user): ?array
    {
        try {
            // Format user data for HighLevel
            $data = User::with('office', 'managerDetail')->where('id', $user->id)->first();
            $office = null;
            $manager = null;
            if ($data) {
                $office = $data->office;
                $manager = $data->managerDetail;
            }

            $contactData = [
                'locationId' => config('services.highlevel.location_id'),
                'email' => $user->email ?? null,
                'firstName' => $user->first_name ?? null,
                'lastName' => $user->last_name ?? null,
                'phone' => $user->mobile_no ?? null,
                'address1' => $user->home_address ?? null,
                'city' => $user->city ?? null,
                'state' => isset($user->state) ? $user->state->name : null,
                'postalCode' => $user->zip ?? null,
                'dateOfBirth' => $user->dob ?? null,
                // Add custom fields if needed
                'customFields' => [
                    ['key' => 'sequifi_id', 'value' => $user->employee_id ?? null],
                    ['key' => 'status', 'value' => 'Active'],
                    ['key' => 'office_id', 'value' => isset($office) ? $office->id : null],
                    ['key' => 'office_name', 'value' => isset($office) ? $office->office_name : null],
                    ['key' => 'manager_id', 'value' => isset($manager) ? $manager->id : null],
                    ['key' => 'manager_name', 'value' => isset($manager) ? $manager->first_name ?? ''.' '.$manager->last_name ?? '' : null],
                    ['key' => 'manager_email', 'value' => isset($manager) ? $manager->email ?? '' : null],
                ],
            ];

            // Log the attempt
            \Illuminate\Support\Facades\Log::info('Pushing employee data to HighLevel', [
                'employee_id' => $user->id,
                'email' => $user->email,
            ]);

            // Send to HighLevel
            $response = $this->upsertHighLevelContact($contactData);

            try {
                InterigationTransactionLog::create([
                    'interigation_name' => 'HighLevelRepPush Push',
                    'api_name' => 'Push Rep Data',
                    'payload' => json_encode($contactData),
                    'response' => json_encode($response),
                    'url' => 'https://services.leadconnectorhq.com/contacts/upsert',
                ]);
            } catch (\Exception $e) {
                // Log::error('Error upserting HighLevel contact: ' . $e->getMessage());
            }

            // If we got a successful response with a contact ID, save it to the user record
            if ($response && isset($response['contact']['id'])) {
                $contactId = $response['contact']['id'];

                // Update the user record with the HighLevel contact ID
                \App\Models\User::where('id', $user->id)->update([
                    'aveyo_hs_id' => $contactId,
                ]);

                \Illuminate\Support\Facades\Log::info('Updated user with HighLevel contact ID', [
                    'user_id' => $user->id,
                    'aveyo_hs_id' => $contactId,
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error pushing employee data to HighLevel', [
                'error' => $e->getMessage(),
                'employee_id' => $user->id ?? null,
            ]);

            return null;
        }
    }

    public function UpdateOnboardingEmployee(Request $request)
    {
        $workerId = '';
        if (! $request->image == null) {
            $file = $request->file('image');
            if (isset($file) && $file != null && $file != '') {
                // s3 bucket
                $img_path = time().$file->getClientOriginalName();
                $img_path = str_replace(' ', '_', $img_path);
                $awsPath = config('app.domain_name').'/'.'Employee_profile/'.$img_path;
                s3_upload($awsPath, file_get_contents($file), false);
                // s3 bucket end

                $image_path = time().$file->getClientOriginalName();
                $ex = $file->getClientOriginalExtension();
                $destinationPath = 'Employee_profile';
                $image_path = $file->move($destinationPath, $img_path);
            }
        } else {
            $image_path = 'Employee_profile/default-user.png';
        }

        $uid = auth()->user()->id;
        $id = $uid;
        $empUpdate = User::find($uid);

        $aveyoid = auth()->user()->aveyo_hs_id;

        $empUpdate->home_address = $request['home_address'];
        $empUpdate->first_name = $request->first_name;
        $empUpdate->last_name = $request->last_name;
        $empUpdate->dob = $request->birth_date;
        $empUpdate->emergency_contact_name = $request->emergency_contact_name;
        $empUpdate->emergency_phone = $request->emergency_phone;
        $empUpdate->emergency_contact_relationship = $request->emergency_contact_relationship;
        $empUpdate->emergrncy_contact_address = $request->emergrncy_contact_address;
        $empUpdate->emergrncy_contact_city = $request->emergrncy_contact_city;
        $empUpdate->emergrncy_contact_state = $request->emergrncy_contact_state;
        $empUpdate->emergrncy_contact_zip_code = $request->emergrncy_contact_zip_code;

        if ($empUpdate->worker_type == '1099') {

            $empUpdate->social_sequrity_no = isset($request->social_sequrity_no) ? $request->social_sequrity_no : '';
            $empUpdate->tax_information = isset($request->tax_information) ? $request->tax_information : '';
            $empUpdate->name_of_bank = isset($request->name_of_bank) ? $request->name_of_bank : '';
            $empUpdate->routing_no = isset($request->routing_no) ? $request->routing_no : '';
            $empUpdate->account_no = isset($request->account_no) ? $request->account_no : '';
            $empUpdate->account_name = isset($request->account_name) ? $request->account_name : '';
            $empUpdate->confirm_account_no = isset($request->confirm_account_no) ? $request->confirm_account_no : '';
            $empUpdate->type_of_account = isset($request->type_of_account) ? $request->type_of_account : '';
            $empUpdate->entity_type = isset($request->entity_type) ? $request->entity_type : '';
            $empUpdate->business_name = isset($request->business_name) ? $request->business_name : '';
            $empUpdate->business_type = isset($request->business_type) ? $request->business_type : '';
            $empUpdate->business_ein = isset($request->business_ein) ? $request->business_ein : '';
            // Added business address as per requirements of SIM-6582
            if (isset($request['business_name']) && ! empty($request['business_name'])) {
                UsersBusinessAddress::updateOrCreate(
                    ['user_id' => $uid],
                    [
                        'business_address' => isset($request->business_address) ? $request->business_address : null,
                        'business_address_line_1' => isset($request->business_address_line_1) ? $request->business_address_line_1 : null,
                        'business_address_line_2' => isset($request->business_address_line_2) ? $request->business_address_line_2 : null,
                        'business_address_state' => isset($request->business_address_state) ? $request->business_address_state : null,
                        'business_address_city' => isset($request->business_address_city) ? $request->business_address_city : null,
                        'business_address_zip' => isset($request->business_address_zip) ? $request->business_address_zip : null,
                        'business_address_lat' => isset($request->business_address_lat) ? $request->business_address_lat : null,
                        'business_address_long' => isset($request->business_address_long) ? $request->business_address_long : null,
                        'business_address_timezone' => isset($request->business_address_timezone) ? $request->business_address_timezone : null,
                    ]
                );

            }
            // end business address

        }
        $empUpdate->shirt_size = isset($request->shirt_size) ? $request->shirt_size : '';
        $empUpdate->hat_size = isset($request->hat_size) ? $request->hat_size : '';
        $empUpdate->sex = isset($request->gender) ? $request->gender : '';
        $empUpdate->image = isset($image_path) ? $image_path : '';
        $empUpdate->onboardProcess = $request->onboardProcess;
        $empUpdate->employee_additional_fields = $request->employee_additional_fields;
        $empUpdate->employee_personal_detail = $request->employee_personal_detail;
        $empUpdate->additional_info_for_employee_to_get_started = $request->additional_info_for_employee_to_get_started;

        $empUpdate->mobile_no = isset($request->mobile_no) ? $request->mobile_no : '';

        $empUpdate->home_address_line_1 = isset($request['home_address_line_1']) ? $request['home_address_line_1'] : $empUpdate->home_address_line_1;
        $empUpdate->home_address_line_2 = isset($request['home_address_line_2']) ? $request['home_address_line_2'] : $empUpdate->home_address_line_2;
        $empUpdate->home_address_state = isset($request['home_address_state']) ? $request['home_address_state'] : $empUpdate->home_address_state;
        $empUpdate->home_address_city = isset($request['home_address_city']) ? $request['home_address_city'] : $empUpdate->home_address_city;
        $empUpdate->home_address_zip = isset($request['home_address_zip']) ? $request['home_address_zip'] : $empUpdate->home_address_zip;
        $empUpdate->home_address_lat = isset($request['home_address_lat']) ? $request['home_address_lat'] : $empUpdate->home_address_lat;
        $empUpdate->home_address_long = isset($request['home_address_long']) ? $request['home_address_long'] : $empUpdate->home_address_long;
        $empUpdate->home_address_timezone = isset($request['home_address_timezone']) ? $request['home_address_timezone'] : $empUpdate->home_address_timezone;
        $empUpdate->emergency_address_line_1 = isset($request['emergency_address_line_1']) ? $request['emergency_address_line_1'] : $empUpdate->emergency_address_line_1;
        $empUpdate->emergency_address_line_2 = isset($request['emergency_address_line_2']) ? $request['emergency_address_line_2'] : $empUpdate->emergency_address_line_2;
        $empUpdate->emergency_address_lat = isset($request['emergency_address_lat']) ? $request['emergency_address_lat'] : $empUpdate->emergency_address_lat;
        $empUpdate->emergency_address_long = isset($request['emergency_address_long']) ? $request['emergency_address_long'] : $empUpdate->emergency_address_long;
        $empUpdate->emergency_address_timezone = isset($request['emergency_address_timezone']) ? $request['emergency_address_timezone'] : $empUpdate->emergency_address_timezone;

        $empUpdate->save();

        // Invalidate cache to ensure fresh data on next request
        // get_userdata() has complex processing that shouldn't be duplicated here
        $cacheKey = "user:basic_data:{$id}";
        Cache::forget($cacheKey);

        $JobnimbusMessage = '';
        $userdata = User::with('state')->where('id', $uid)->first();

        $createComponentSessionOfWorkerIdRespose = [];
        $worker_type = '';

        if ($userdata) {
            $worker_type = strtolower($userdata->worker_type);
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {

                // if worker_type == w2
                if ($worker_type == 'w2') {

                    $evereeEmbedOnboardingURL = '';
                    $response = $this->addEmployeeForEmbeddedOnboarding($userdata);
                    // update workerId
                    Log::debug('Everee addEmployeeForEmbeddedOnboarding resp');
                    Log::debug($response);
                    if (isset($response['errorMessage'])) {
                        return response()->json([
                            'ApiName' => 'Update Employee Detail',
                            'status' => false,
                            'message' => 'Everee Error Message:'.$response['errorMessage'],
                        ], 400);
                    }
                    if (isset($response['workerId'])) {
                        $workerId = $response['workerId'];
                        User::where('id', $userdata->id)->update([
                            'everee_workerId' => $workerId,
                        ]);

                        if ($request->passEvereeOnboardingIframeUrl == 1) {
                            $userdata = User::with('state')->where('id', $uid)->first();
                            // $this->update_emp_personal_info($userdata, $userdata->state);
                            $this->update_w2_emp_data($userdata, $userdata->state);

                            $createComponentSessionOfWorkerIdRespose = $this->createComponentSessionOfWorkerId($workerId);
                            Log::debug('Everee Component Session API Response');
                            Log::debug($createComponentSessionOfWorkerIdRespose);
                        }
                    }
                }
            }
        }

        $jobNimbusCrmData = Crms::whereHas('crmSetting')->with('crmSetting')->where('id', 4)->where('status', 1)->first();
        if (! empty($jobNimbusCrmData)) {
            $decreptedValue = openssl_decrypt($jobNimbusCrmData->crmSetting->value, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            $jobNimbusCrmSetting = json_decode($decreptedValue);
            $jobNimbusToken = $jobNimbusCrmSetting->api_key;
            $jobnimbus_jnid = auth()->user()->jobnimbus_jnid;
            $postDataToJobNimbus = [
                'email' => $userdata->email,
                'home_phone' => $userdata->mobile_no,
                'record_type_name' => 'Subcontractor',
                'status_name' => 'Solar Reps',
                'city' => $userdata->home_address_city,
                'address_line1' => $userdata->home_address_line_1,
                'state_text' => $userdata->state->state_code,
                'external_id' => $userdata->employee_id,
            ];
            if (! empty($jobnimbus_jnid) || $jobnimbus_jnid != null) {
                return $responseJobNimbuscontats = $this->updateJobNimbuscontats($postDataToJobNimbus, $jobnimbus_jnid, $jobNimbusToken);
            } else {
                $postDataToJobNimbus['display_name'] = $userdata->first_name.' '.$userdata->last_name;
                $postDataToJobNimbus['first_name'] = $userdata->first_name;
                $postDataToJobNimbus['last_name'] = $userdata->last_name;
                $responseJobNimbuscontats = $this->storeJobNimbuscontats($postDataToJobNimbus, $jobNimbusToken);
            }
            if ($responseJobNimbuscontats['status'] === true) {
                User::where('id', $uid)->update([
                    'jobnimbus_jnid' => $responseJobNimbuscontats['data']['jnid'],
                    'jobnimbus_number' => $responseJobNimbuscontats['data']['number'],
                ]);
            } else {
                $JobnimbusMessage = ' but '.$responseJobNimbuscontats['message'];
            }
        }

        // dd($userdata);

        $worker_type = strtolower($userdata->worker_type);
        if ($worker_type == 'w2' && $workerId) {
            // if($worker_type == 'w2' &&  $userdata->everee_embed_onboard_profile == 1)

            // Fetch data from everee and update local DB
            $workerDataFromEveree = $this->retrieveWorkerByEvereeWorkerID($workerId);
            Log::debug('Fetch data from everee and update local DB');
            Log::debug('$workerDataFromEveree');
            Log::debug($workerDataFromEveree);
            $state = State::where('state_code', $workerDataFromEveree['homeAddress']['current']['state'] ?? null)->first();
            $home_address_state = null;
            if ($state) {
                $home_address_state = $state->state_code;
            }
            // $res = str_replace( array( '\'', '"',',' , ';', '<', '>','-' ), '', $data->social_sequrity_no);
            // $res_ein = str_replace( array( '\'', '"',',' , ';', '<', '>','-' ), '', $data->business_ein);
            // $taxpayerIdentifier = !empty($res) ? $res : $res_ein;

            // unverifiedTinType
            if (isset($workerDataFromEveree['unverifiedTinType']) && $workerDataFromEveree['unverifiedTinType'] == 'SSN') {
                User::where('everee_workerId', $workerId)->update([
                    'social_sequrity_no' => $workerDataFromEveree['taxpayerIdentifier'],
                    'entity_type' => 'individual',
                ]);
            }
            if (isset($workerDataFromEveree['unverifiedTinType']) && $workerDataFromEveree['unverifiedTinType'] == 'ITIN') {
                User::where('everee_workerId', $workerId)->update([
                    'business_ein' => $workerDataFromEveree['taxpayerIdentifier'],
                    'entity_type' => 'business',
                ]);
            }

            // dd($workerDataFromEveree);

            $dataToUpdate = [
                'first_name' => isset($workerDataFromEveree['firstName']) ? $workerDataFromEveree['firstName'] : null,
                'middle_name' => isset($workerDataFromEveree['middleName']) ? $workerDataFromEveree['middleName'] : null,
                'last_name' => isset($workerDataFromEveree['lastName']) ? $workerDataFromEveree['lastName'] : null,
                'dob' => isset($workerDataFromEveree['dateOfBirth']) ? $workerDataFromEveree['dateOfBirth'] : null,
                'email' => isset($workerDataFromEveree['email']) ? $workerDataFromEveree['email'] : null,
                'mobile_no' => isset($workerDataFromEveree['phoneNumber']) ? $workerDataFromEveree['phoneNumber'] : null,
                // 'pay_rate' => $workerDataFromEveree['position']['current']['payRate']['amount'],
                // 'pay_type' => $workerDataFromEveree['position']['current']['payType'],
                // address
                'home_address_line_1' => isset($workerDataFromEveree['homeAddress']['current']['line1']) ? $workerDataFromEveree['homeAddress']['current']['line1'] : null,
                // 'home_address_line_2' => $workerDataFromEveree['homeAddress']['current']['asd'],
                'home_address_city' => isset($workerDataFromEveree['homeAddress']['current']['city']) ? $workerDataFromEveree['homeAddress']['current']['city'] : null,
                'home_address_state' => $home_address_state,
                'home_address_zip' => isset($workerDataFromEveree['homeAddress']['current']['postalCode']) ? $workerDataFromEveree['homeAddress']['current']['postalCode'] : null,
                // banking info
                'type_of_account' => isset($workerDataFromEveree['bankAccounts'][0]) ? $workerDataFromEveree['bankAccounts'][0]['accountType'] : null,
                'name_of_bank' => isset($workerDataFromEveree['bankAccounts'][0]) ? $workerDataFromEveree['bankAccounts'][0]['bankName'] : null,
                'account_name' => isset($workerDataFromEveree['bankAccounts'][0]) ? $workerDataFromEveree['bankAccounts'][0]['accountName'] : null,
                'routing_no' => isset($workerDataFromEveree['bankAccounts'][0]) ? $workerDataFromEveree['bankAccounts'][0]['routingNumber'] : null,
                'account_no' => isset($workerDataFromEveree['bankAccounts'][0]) ? $workerDataFromEveree['bankAccounts'][0]['accountNumberLast4'] : null,

            ];

            if (isset($workerDataFromEveree['homeAddress']['current']['latitude']) && isset($workerDataFromEveree['homeAddress']['current']['longitude'])) {
                $dataToUpdate['home_address_lat'] = $workerDataFromEveree['homeAddress']['current']['latitude'];
                $dataToUpdate['home_address_long'] = $workerDataFromEveree['homeAddress']['current']['longitude'];
            }

            if (isset($workerDataFromEveree['homeAddress']['current']['timeZone'])) {
                $dataToUpdate['home_address_timezone'] = $workerDataFromEveree['homeAddress']['current']['timeZone'];
            }

            User::where('everee_workerId', $workerId)->update($dataToUpdate);

        }

        if ($request->onboardProcess == 1) {
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {
                if ($userdata && $worker_type == '1099') {
                    $this->update_emp_personal_info($userdata, $userdata->state);  // update emp in everee
                }
            }

            $this->saveDataToLGCY($userdata->id);
            $this->saveDataToSourceMarketing($userdata->id, 'hired_employee');
            // EspQuickBase Rep Data Push integration (Silent - fire and forget)
            $this->espQuickBaseService->sendUserDataSilently($userdata->id, 'self_employee_onboarding_complete');
            // End EspQuickBase Rep Data Push integration

            // Onyx Rep Data Push integration (Silent - fire and forget)
            $this->onyxRepDataPushService->sendUserDataSilently($userdata->id, 'new_rep');
            // End Onyx Rep Data Push integration

            OnboardingEmployees::where('user_id', $uid)->update(['status_id' => 14]);
            $CrmData = Crms::where('id', 2)->where('status', 1)->first();
            $CrmSetting = CrmSetting::where('crm_id', 2)->first();
            if (! empty($CrmData) && ! empty($CrmSetting) && ! empty($aveyoid)) {
                // $token ="pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641";
                $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                $val = json_decode($decreptedValue);
                $token = $val->api_key;
                $Hubspotdata['properties'] = [
                    'address' => isset($empUpdate->home_address) ? $empUpdate->home_address : null,
                    'dob' => isset($empUpdate->dob) ? $empUpdate->dob : null,
                    'birthday' => isset($empUpdate->dob) ? $empUpdate->dob : null,
                    'sex' => isset($empUpdate->sex) ? $empUpdate->sex : null,
                    'mobile_no' => isset($empUpdate->mobile_no) ? $empUpdate->mobile_no : null,
                    'sequifi_id' => isset($empUpdate->employee_id) ? $empUpdate->employee_id : null,
                    'status' => 'Active',
                ];
                $this->update_employees($Hubspotdata, $token, $uid, $aveyoid);
            }

            // Check if HighLevel integration is enabled
            /*$integration = Integration::where(['name' => 'GoHighLevel', 'status' => 1])->first();
                        if ($integration) {
                            if (config('services.highlevel.token')) {
                                // Push employee data to HighLevel
                                $highLevelResponse = $this->saveEmployeeToHighLevel($userdata);

                                // The saveEmployeeToHighLevel method now handles updating the aveyo_hs_id field
                                // We just need to log the result for monitoring
                                if ($highLevelResponse) {
                                    $contactId = $highLevelResponse['contact']['id'] ?? null;
                                    Log::info('Employee successfully synced to HighLevel', [
                                        'employee_id' => $userdata->id,
                                        'highlevel_contact_id' => $contactId,
                                        'is_new_contact' => $highLevelResponse['new'] ?? false
                                    ]);
                                } else {
                                    Log::error('Failed to sync employee to HighLevel', [
                                        'employee_id' => $userdata->id
                                    ]);
                                }
                            }
                        }
                    */
            // End HighLevel integration

            // code for Push Rep Data to HubspotCurrentEnergy
            $integration = Integration::where(['name' => 'Hubspot Current Energy', 'status' => 1])->first();
            $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
            if (! empty($integration) && ! empty($hubspotCurrentEnergyToken)) {
                // $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                $hubspotCurrentEnergyData['properties'] = [
                    'address' => isset($empUpdate->home_address) ? $empUpdate->home_address : null,
                    'date_of_birth' => isset($empUpdate->dob) ? $empUpdate->dob : null,
                    'gender' => isset($empUpdate->sex) ? $empUpdate->sex : null,
                    'phone' => isset($empUpdate->mobile_no) ? $empUpdate->mobile_no : null,
                    'sales_rep_id' => isset($empUpdate->employee_id) ? $empUpdate->employee_id : null,
                    'contact_status' => 'Active',
                    'contact_type' => 'Sales Rep',
                ];
                $this->updateContactForHubspotCurrentEnergy($hubspotCurrentEnergyData, $hubspotCurrentEnergyToken, $uid, $aveyoid);
            }

            if (config('app.domain_name') == 'onyx') {
                // Send onboarding completion email to Onyx team
                $this->sendOnyxOnboardingCompletionEmail($empUpdate, $uid);
            }

        }

        return response()->json([
            'ApiName' => 'Update Employee Detail',
            'status' => true,
            'message' => 'Update Successfully.'.$JobnimbusMessage,
            'createComponentSessionOfWorkerIdRespose' => $createComponentSessionOfWorkerIdRespose,
            'worker_type' => $worker_type,

        ]);
    }

    public function EmployeeOriginization(Request $request): JsonResponse
    {
        $userId = Auth()->user();
        $data1 = OnboardingEmployees::find($request->user_id);
        if (! $data1 == null) {
            $data1->department_id = $request->employee_originization['department_id'];
            $data1->position_id = $request->employee_originization['position_id'];
            $data1->is_manager = $request->employee_originization['is_manager'];

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $data1->self_gen_accounts = null;
                $data1->self_gen_type = null;
            } else {
                $data1->self_gen_accounts = isset($request->employee_originization['self_gen_accounts']) ? $request->employee_originization['self_gen_accounts'] : 0;
                $selfGen = isset($request->employee_originization['self_gen_accounts']) ? $request->employee_originization['self_gen_accounts'] : 0;
                if ($selfGen == 1) {
                    $position = $request->employee_originization['position_id'];

                    if ($position == 2) {
                        $data1->self_gen_type = 3;
                    }
                    if ($position == 3) {
                        $data1->self_gen_type = 2;
                    }
                    if ($position != 2 && $position != 3) {
                        $findPosition = Positions::where('id', $position)->first();
                        if ($findPosition->parent_id == 2) {
                            $data1->self_gen_type = 3;
                        }
                        if ($findPosition->parent_id == 3) {
                            $data1->self_gen_type = 2;
                        }
                    }
                }
            }

            if (isset($request->employee_originization['sub_position_id'])) {
                $data1->sub_position_id = $request->employee_originization['sub_position_id'];
            }
            if (! empty($request->employee_originization['manager_id'])) {
                $data1->manager_id = $request->employee_originization['manager_id'];
            }
            // if user is manager and has manager then
            if (! empty($request->employee_originization['is_manager']) && $request->employee_originization['is_manager'] == 1) {
                $data1->manager_id = isset($request->employee_originization['manager_id']) ? $request->employee_originization['manager_id'] : null;
            }
            if (! empty($request->state_id)) {
                $data1->state_id = $request->state_id;
            }
            if (! empty($request->city_id)) {
                $data1->city_id = $request->city_id;
            }
            if (! empty($request->office_id)) {
                $data1->office_id = $request->office_id;
            }

            $data1->team_id = $request->employee_originization['team_id'];
            $data1->hired_by_uid = $userId->id;
            $data1->recruiter_id = isset($request->employee_originization['recruiter_id']) ? $request->employee_originization['recruiter_id'] : null;

            $recruiter_id = $request->employee_originization['additional_recruiter_id'];
            $data1->additional_recruiter_id1 = isset($recruiter_id[0]) ? $recruiter_id[0] : null;
            $data1->additional_recruiter_id2 = isset($recruiter_id[1]) ? $recruiter_id[1] : null;

            if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {
                $data1->additional_recruiter_id2 = $request->experience_level;
            }

            $data1->save();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $description = 'Department Id =>'.$data1->department_id.','.'Position Id =>'.$data1->position_id.','.'Is Manager =>'.$data1->is_manager.','.'Sub Position Id =>'.$data1->sub_position_id.','.'Manager Id =>'.$data1->manager_id.','.'Recruiter Id =>'.$data1->recruiter_id.','.'Team Id =>'.$data1->team_id;
            } else {
                $description = 'Department Id =>'.$data1->department_id.','.'Position Id =>'.$data1->position_id.','.'Is Manager =>'.$data1->is_manager.','.'Self Gen Accounts =>'.$data1->self_gen_accounts.','.'Sub Position Id =>'.$data1->sub_position_id.','.'Manager Id =>'.$data1->manager_id.','.'Recruiter Id =>'.$data1->recruiter_id.','.'Team Id =>'.$data1->team_id;
            }
            if ($data1) {
                $page = 'Employee hiring';
                $action = 'Employee create';
                user_activity_log($page, $action, $description);
            }
            // $system_amount = $request->employee_originization['system_per_kw_amount'];

            $additional_locations = $request->employee_originization['additional_locations'];
            AdditionalRecruiters::where('hiring_id', $data1->id)->delete();
            foreach ($recruiter_id as $value) {
                AdditionalRecruiters::create([
                    'hiring_id' => $data1->id,
                    'recruiter_id' => $value,
                    // 'system_per_kw_amount' => $system_amount[$key],
                ]);
            }
            if ($additional_locations) {
                OnboardingEmployeeLocations::where('user_id', $request->user_id)->delete();
                foreach ($additional_locations as $additional_location) {
                    OnboardingEmployeeLocations::create([
                        'state_id' => $additional_location['state_id'],
                        'city_id' => null, // $additional_location['city_id'],
                        'user_id' => $request->user_id,
                        'office_id' => isset($additional_location['office_id']) ? $additional_location['office_id'] : '',
                    ]);
                }
            }

            $deduction = $request->deduction;
            if (isset($deduction)) {
                EmployeeOnboardingDeduction::where('user_id', $request->user_id)->delete();
                foreach ($deduction as $deductions) {
                    EmployeeOnboardingDeduction::create([
                        'deduction_type' => $deductions['deduction_type'],
                        'cost_center_name' => $deductions['cost_center_name'],
                        'cost_center_id' => $deductions['cost_center_id'],
                        'ammount_par_paycheck' => $deductions['ammount_par_paycheck'],
                        'deduction_setting_id' => isset($deductions['deduction_setting_id']) ? $deductions['deduction_setting_id'] : null,
                        'position_id' => $request->employee_originization['position_id'],
                        'user_id' => $request->user_id,
                    ]);
                }
            }

            if ($request->filled('template_id')) {
                SentOfferLetter::updateOrCreate(
                    ['onboarding_employee_id' => $data1->id],
                    ['template_id' => $request->template_id]
                );
            }

            return response()->json([
                'ApiName' => 'add-onboarding_employee_originization',
                'status' => true,
                'message' => 'add Successfully.',
            ]);
        } else {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_originization',
                'status' => false,
                'message' => 'Employee not found.',
            ], 400);
        }
    }

    public function EmployeeCompencation(Request $request): JsonResponse
    {
        $rules = [
            'user_id' => 'required',
        ];
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $rules['employee_compensation.*.commission_type'] = 'nullable|in:percent,per sale';
            $rules['employee_compensation.*.upfront_sale_type'] = 'nullable|in:per sale,percent';
            $rules['employee_compensation.*.withheld_type'] = 'nullable|in:per sale';
        }
        $validator = Validator::make($request->all(), $rules, [
            'employee_compensation.*.commission_type.in' => 'Invalid Commission Type.',
            'employee_compensation.*.upfront_sale_type.in' => 'Invalid Upfront Type.',
            'employee_compensation.*.withheld_type.in' => 'Invalid Withheld Type.',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $data2 = OnboardingEmployees::find($request->user_id);
        if (! $data2) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_compencation',
                'status' => false,
                'message' => 'User Not found',
            ], 400);
        }

        $employee_compensation = $request->employee_compensation;
        $normal_data = [];
        $self_gen_data = [];
        foreach ($employee_compensation as $key => $ec) {
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                if ($key == 0) {
                    $normal_data['commission'] = $ec['commission'];
                    $normal_data['commission_type'] = isset($ec['commission_type']) ? $ec['commission_type'] : null;
                    $normal_data['commission_effective_date'] = isset($ec['commission_effective_date']) ? $ec['commission_effective_date'] : date('Y-m-d');
                    $normal_data['redline_amount_type'] = null;
                    $normal_data['redline'] = null;
                    $normal_data['redline_type'] = null;
                    $normal_data['start_date'] = isset($ec['start_date']) ? $ec['start_date'] : date('Y-m-d');
                    $normal_data['upfront_pay_amount'] = isset($ec['upfront_pay_amount']) ? $ec['upfront_pay_amount'] : '';
                    $normal_data['upfront_sale_type'] = isset($ec['upfront_sale_type']) ? $ec['upfront_sale_type'] : '';
                    $normal_data['upfront_effective_date'] = isset($ec['upfront_effective_date']) ? $ec['upfront_effective_date'] : date('Y-m-d');
                    $normal_data['withheld_amount'] = isset($ec['withheld_amount']) ? $ec['withheld_amount'] : '';
                    $normal_data['withheld_type'] = isset($ec['withheld_type']) ? $ec['withheld_type'] : '';
                    $normal_data['withheld_effective_date'] = isset($ec['withheld_effective_date']) ? $ec['withheld_effective_date'] : date('Y-m-d');

                    $description = 'Commission =>'.$ec['commission'].', '.'Upfront pay amount =>'.$ec['upfront_pay_amount'].','.'Upfront sale type =>'.$ec['upfront_sale_type'].','.' Withheld Amount =>'.$ec['withheld_amount'].','.' withheld type =>'.$ec['withheld_type'];
                    $page = 'Employee hiring';
                    $action = 'Employee create';
                    user_activity_log($page, $action, $description);
                }
            } else {
                if ($key == 0) {
                    $normal_data['commission'] = $ec['commission'];
                    $normal_data['commission_type'] = isset($ec['commission_type']) ? $ec['commission_type'] : null;
                    $normal_data['commission_effective_date'] = isset($ec['commission_effective_date']) ? $ec['commission_effective_date'] : date('Y-m-d');
                    if ($ec['commission_type'] == 'percent') {
                        $normal_data['redline_amount_type'] = isset($ec['redline_amount_type']) ? $ec['redline_amount_type'] : 'Fixed';
                        $normal_data['redline'] = isset($ec['redline']) ? $ec['redline'] : 0;
                        $normal_data['redline_type'] = isset($ec['redline_type']) ? $ec['redline_type'] : 'per watt';
                    } else {
                        $normal_data['redline_amount_type'] = null;
                        $normal_data['redline'] = 0;
                        $normal_data['redline_type'] = null;
                    }
                    $normal_data['start_date'] = isset($ec['start_date']) ? $ec['start_date'] : date('Y-m-d');
                    $normal_data['upfront_pay_amount'] = isset($ec['upfront_pay_amount']) ? $ec['upfront_pay_amount'] : '';
                    $normal_data['upfront_sale_type'] = isset($ec['upfront_sale_type']) ? $ec['upfront_sale_type'] : '';
                    $normal_data['upfront_effective_date'] = isset($ec['upfront_effective_date']) ? $ec['upfront_effective_date'] : date('Y-m-d');
                    $normal_data['withheld_amount'] = isset($ec['withheld_amount']) ? $ec['withheld_amount'] : '';
                    $normal_data['withheld_type'] = isset($ec['withheld_type']) ? $ec['withheld_type'] : '';
                    $normal_data['withheld_effective_date'] = isset($ec['withheld_effective_date']) ? $ec['withheld_effective_date'] : date('Y-m-d');
                }
                if ($key == 1) {
                    $self_gen_data['commission'] = $ec['commission'];
                    $self_gen_data['commission_type'] = isset($ec['commission_type']) ? $ec['commission_type'] : null;
                    $self_gen_data['commission_effective_date'] = isset($ec['commission_effective_date']) ? $ec['commission_effective_date'] : date('Y-m-d');
                    if ($ec['commission_type'] == 'percent') {
                        $self_gen_data['redline_amount_type'] = isset($ec['redline_amount_type']) ? $ec['redline_amount_type'] : 'Fixed';
                        $self_gen_data['redline'] = isset($ec['redline']) ? $ec['redline'] : 0;
                        $self_gen_data['redline_type'] = isset($ec['redline_type']) ? $ec['redline_type'] : 'per watt';
                    } else {
                        $self_gen_data['redline_amount_type'] = null;
                        $self_gen_data['redline'] = 0;
                        $self_gen_data['redline_type'] = null;
                    }
                    $self_gen_data['start_date'] = isset($ec['start_date']) ? $ec['start_date'] : date('Y-m-d');
                    $self_gen_data['upfront_pay_amount'] = isset($ec['upfront_pay_amount']) ? $ec['upfront_pay_amount'] : '';
                    $self_gen_data['upfront_sale_type'] = isset($ec['upfront_sale_type']) ? $ec['upfront_sale_type'] : '';
                    $self_gen_data['upfront_effective_date'] = isset($ec['upfront_effective_date']) ? $ec['upfront_effective_date'] : date('Y-m-d');
                    // $self_gen_data['withheld_amount'] = isset($ec['withheld_amount']) ? $ec['withheld_amount'] : '';
                    // $self_gen_data['withheld_type'] = isset($ec['withheld_type']) ? $ec['withheld_type'] : '';
                    // $self_gen_data['withheld_effective_date'] = isset($ec['withheld_effective_date']) ? $ec['withheld_effective_date'] : date('Y-m-d');
                }
                $description = 'Commission =>'.$ec['commission'].', '.'Redline =>'.$ec['redline'].','.'Redline Type =>'.$ec['redline_type'].','.'Upfront pay amount =>'.$ec['upfront_pay_amount'].','.'Upfront sale type =>'.$ec['upfront_sale_type'].','.' Withheld Amount =>'.$ec['withheld_amount'].','.' withheld type =>'.$ec['withheld_type'];
                $page = 'Employee hiring';
                $action = 'Employee create';
                user_activity_log($page, $action, $description);
            }
        }

        $data2->commission = $normal_data['commission'];
        $data2->commission_type = isset($normal_data['commission_type']) ? $normal_data['commission_type'] : null;
        $data2->redline = $normal_data['redline'];
        $data2->redline_amount_type = $normal_data['redline_amount_type'];
        $data2->redline_type = $normal_data['redline_type'];
        $data2->upfront_pay_amount = $normal_data['upfront_pay_amount'];
        $data2->upfront_sale_type = $normal_data['upfront_sale_type'];
        $data2->withheld_amount = $normal_data['withheld_amount'];
        $data2->withheld_type = $normal_data['withheld_type'];

        $data2->self_gen_redline = isset($self_gen_data['redline']) ? $self_gen_data['redline'] : null;
        $data2->self_gen_redline_amount_type = isset($self_gen_data['redline_amount_type']) ? $self_gen_data['redline_amount_type'] : null;
        $data2->self_gen_redline_type = isset($self_gen_data['redline_type']) ? $self_gen_data['redline_type'] : null;
        $data2->self_gen_commission = isset($self_gen_data['commission']) ? $self_gen_data['commission'] : null;
        $data2->self_gen_commission_type = isset($self_gen_data['commission_type']) ? $self_gen_data['commission_type'] : null;
        $data2->self_gen_upfront_amount = isset($self_gen_data['upfront_pay_amount']) ? $self_gen_data['upfront_pay_amount'] : null;
        $data2->self_gen_upfront_type = isset($self_gen_data['upfront_sale_type']) ? $self_gen_data['upfront_sale_type'] : null;
        // $data2->self_gen_withheld_amount = isset($self_gen_data['withheld_amount']) ? $self_gen_data['withheld_amount'] : null;
        // $data2->self_gen_withheld_type = isset($self_gen_data['withheld_type']) ? $self_gen_data['withheld_type'] : null;
        $data2->save();

        $redline_data = $request->employee_compensation;
        if ($redline_data) {
            $updater_id = Auth()->user()->id;
            if ($request->user_id) {
                OnboardingUserRedline::where('user_id', $request->user_id)->delete();
            }
            foreach ($redline_data as $value) {
                $redline_amount_type = null;
                if (! empty($value['redline_amount_type'])) {
                    $redline_amount_type = $value['redline_amount_type'];
                } else {
                    if ($value['commission_type'] == 'percent') {
                        $redline_amount_type = 'Fixed';
                    }
                }

                $array = [
                    'updater_id' => $updater_id,
                    'start_date' => isset($value['redline_effective_date']) ? date('Y-m-d', strtotime($value['redline_effective_date'])) : date('Y-m-d'),
                    'commission' => $value['commission'],
                    'redline' => $value['redline'],
                    'redline_type' => $value['redline_type'],
                    'redline_amount_type' => $redline_amount_type,
                    'commission_type' => isset($value['commission_type']) ? $value['commission_type'] : null,
                    'commission_effective_date' => isset($value['commission_effective_date']) ? date('Y-m-d', strtotime($value['commission_effective_date'])) : date('Y-m-d'),
                    'upfront_pay_amount' => isset($value['upfront_pay_amount']) ? $value['upfront_pay_amount'] : '',
                    'upfront_sale_type' => isset($value['upfront_sale_type']) ? $value['upfront_sale_type'] : '',
                    'upfront_effective_date' => isset($value['upfront_effective_date']) ? date('Y-m-d', strtotime($value['upfront_effective_date'])) : date('Y-m-d'),
                    'withheld_amount' => isset($value['withheld_amount']) ? $value['withheld_amount'] : '',
                    'withheld_type' => isset($value['withheld_type']) ? $value['withheld_type'] : '',
                    'withheld_effective_date' => isset($value['withheld_effective_date']) ? date('Y-m-d', strtotime($value['withheld_effective_date'])) : date('Y-m-d'),
                ];
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $array['start_date'] = null;
                    $array['redline'] = null;
                    $array['redline_type'] = null;
                    $array['redline_amount_type'] = null;
                }
                OnboardingUserRedline::updateOrCreate(['user_id' => $request['user_id'], 'position_id' => $value['position_id']], $array);
            }
        }

        $commissionSelfgen = isset($request->commission_selfgen) ? $request->commission_selfgen : '';
        if (! empty($commissionSelfgen)) {
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $update = [
                    'commission_selfgen' => null,
                    'commission_selfgen_type' => null,
                    'commission_selfgen_effective_date' => null,
                ];
            } else {
                $update = [
                    'commission_selfgen' => $commissionSelfgen,
                    'commission_selfgen_type' => isset($request->commission_selfgen_type) ? $request->commission_selfgen_type : null,
                    'commission_selfgen_effective_date' => $request->commission_selfgen_effective_date,
                ];
            }
            OnboardingEmployees::where('id', $request->user_id)->update($update);
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_compencation',
            'status' => true,
            'message' => 'add Successfully.',
        ]);
    }

    public function EmployeeOverride(Request $request): JsonResponse
    {
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $validator = Validator::make($request->all(), [
                'employee_override.direct_overrides_type' => 'nullable|in:per sale,percent',
                'employee_override.indirect_overrides_type' => 'nullable|in:per sale,percent',
                'employee_override.office_overrides_type' => 'nullable|in:per sale,percent',
            ], [
                'employee_override.direct_overrides_type.in' => 'Invalid Direct Override Type.',
                'employee_override.indirect_overrides_type.in' => 'Invalid Indirect Override Type.',
                'employee_override.office_overrides_type.in' => 'Invalid Office Override Type.',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 400);
            }
        }

        $data3 = OnboardingEmployees::find($request->user_id);

        if (! $data3) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_override',
                'status' => false,
                'message' => 'User Not Found',
            ], 400);
        }

        $data3->direct_overrides_amount = $request->employee_override['direct_overrides_amount'];
        $data3->direct_overrides_type = $request->employee_override['direct_overrides_type'];
        $data3->indirect_overrides_amount = $request->employee_override['indirect_overrides_amount'];
        $data3->indirect_overrides_type = $request->employee_override['indirect_overrides_type'];
        $data3->office_overrides_amount = $request->employee_override['office_overrides_amount'];
        $data3->office_overrides_type = $request->employee_override['office_overrides_type'];
        if (! empty($request->employee_override['office_stack_overrides_amount'])) {
            $office_stack_overrides_amount = $request->employee_override['office_stack_overrides_amount'];
        } else {
            $office_stack_overrides_amount = null;
        }
        $data3->office_stack_overrides_amount = $office_stack_overrides_amount;
        $data3->save();

        OnboardingEmployeeOverride::updateOrCreate(['user_id' => $request->user_id], [
            'updater_id' => auth()->user()->id,
            'override_effective_date' => isset($request->override_effective_date) ? $request->override_effective_date : date('Y-m-d'),
            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
            'office_overrides_type' => $request->employee_override['office_overrides_type'],
            'office_stack_overrides_amount' => $office_stack_overrides_amount,
        ]);

        $additional_office_overrides = $request->additional_office_override;
        if ($additional_office_overrides) {
            foreach ($additional_office_overrides as $additional_location) {
                $condition = [
                    'user_id' => $request->user_id,
                    'state_id' => $additional_location['state_id'],
                    'office_id' => $additional_location['office_id'],
                ];

                $update = [
                    'overrides_amount' => $additional_location['overrides_amount'],
                    'overrides_type' => $additional_location['overrides_type'],
                ];
                OnboardingEmployeeLocations::where($condition)->update($update);
            }
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_override',
            'status' => true,
            'message' => 'add Successfully.',
        ]);
    }

    public function EmployeeAgreement(Request $request): JsonResponse
    {
        $data4 = OnboardingEmployees::find($request->user_id);

        if (! $data4 == null) {
            $data4->probation_period = isset($request->employee_agreement['probation_period']) ? $request->employee_agreement['probation_period'] : null;
            $data4->hiring_bonus_amount = isset($request->employee_agreement['hiring_bonus_amount']) ? $request->employee_agreement['hiring_bonus_amount'] : null;
            $data4->date_to_be_paid = isset($request->employee_agreement['date_to_be_paid']) ? $request->employee_agreement['date_to_be_paid'] : null;

            $companyProfile = CompanyProfile::first();

            if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {

                $startDate = isset($request->employee_agreement['period_of_agreement']) ? $request->employee_agreement['period_of_agreement'] : null;
                $endDate = isset($request->employee_agreement['end_date']) ? $request->employee_agreement['end_date'] : null;

                if (! empty($startDate) && ! empty($endDate)) {

                    $inSeason = seasonValidator($startDate, $endDate);

                    // Check if the start date and end date fall within the season range
                    if ($inSeason) {
                        // Dates are valid, proceed with setting the values
                        $data4->period_of_agreement_start_date = $startDate;
                        $data4->end_date = $endDate;
                    } else {

                        // Handle the case when dates are outside the allowed range
                        return response()->json([
                            'ApiName' => 'add-onboarding_employee_override',
                            'status' => false,
                            'message' => 'The dates must lie between October 1st and September 30th.',
                        ], 422);

                    }

                } else {

                    return response()->json([
                        'ApiName' => 'add-onboarding_employee_override',
                        'status' => false,
                        'message' => 'Period of Agreement dates must lie between October 1st and September 30th.',
                    ], 422);

                }

            } else {

                $data4->period_of_agreement_start_date = isset($request->employee_agreement['period_of_agreement']) ? $request->employee_agreement['period_of_agreement'] : null;
                $data4->end_date = isset($request->employee_agreement['end_date']) ? $request->employee_agreement['end_date'] : null;

            }

            $data4->offer_include_bonus = isset($request->employee_agreement['offer_include_bonus']) ? $request->employee_agreement['offer_include_bonus'] : null;

            if (isset($request->employee_agreement['offer_expiry_date'])) {
                // update offer expiry date
                $data4->offer_expiry_date = $request->employee_agreement['offer_expiry_date'];

                // update offer status only for expired
                if ($data4->status_id == 5) {

                    $status_id = 4; // Offer Letter Sent

                    $activityLog = ActivityLog::where('subject_type', \App\Models\OnboardingEmployees::class)
                        ->where('properties', 'like', '%status_id%')
                        ->where('subject_id', $data4->id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($activityLog) {

                        $properties = json_decode($activityLog->properties);

                        if (isset($properties->old->status_id) && $properties->old->status_id != 5) {
                            $status_id = $properties->old->status_id;
                        }

                    }

                    $data4->status_id = $status_id;

                }

            }

            // $data4->offer_expiry_date = isset($request->employee_agreement['offer_expiry_date'])?$request->employee_agreement['offer_expiry_date']:null;

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

    public function wages(Request $request)
    {
        // return $request->employee_wages['pay_type'];

        $data3 = OnboardingEmployees::with(['positionDetail'])->find($request->user_id);

        // return $data3;

        if (! $data3) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_wages',
                'status' => false,
                'message' => 'User Not Found',
            ], 400);
        }

        $data = [
            'updater_id' => auth()->user()->id,
            'pay_type' => $request->employee_wages['pay_type'],
            'pay_rate' => $request->employee_wages['pay_rate'],
            'expected_weekly_hours' => $request->employee_wages['expected_weekly_hours'],
            'overtime_rate' => $request->employee_wages['overtime_rate'],
        ];

        if (isset($request->employee_wages['pto_hours'])) {
            $data['pto_hours'] = $request->employee_wages['pto_hours'];
        }
        if (isset($request->employee_wages['unused_pto_expires'])) {
            $data['unused_pto_expires'] = $request->employee_wages['unused_pto_expires'];
        }
        // if(isset($request->employee_wages['worker_type']))
        // {
        //     $data['worker_type'] = $request->employee_wages['worker_type'];
        // }
        if (isset($data3->positionDetail->worker_type)) {
            $data['worker_type'] = $data3->positionDetail->worker_type;
        }

        $data['pay_rate_type'] = 'Weekly';

        if (isset($request->employee_wages['pay_rate_type'])) {
            $data['pay_rate_type'] = $request->employee_wages['pay_rate_type'];
        }

        OnboardingEmployees::find($request->user_id)->update($data);

        return response()->json([
            'ApiName' => 'add-onboarding_employee_wages',
            'status' => true,
            'message' => 'add Successfully.',
        ]);
    }

    public function sendEmailOnBoardingEmployee_old($id): JsonResponse
    {
        $data = OnboardingEmployees::Select('id', 'first_name', 'last_name', 'email', 'mobile_no')->where('id', $id)->first();
        // echo $data->email;die;
        if ($data) {
            $pdf = PDF::loadView('mail.pdf', [
                'title' => $data->first_name.' '.$data->last_name,
                'email' => $data->email,
                'mobile_no' => $data->mobile_no,
            ]);

            // file_put_contents("template/".$data->first_name.'-'.$data->last_name."_offer_letter.pdf", $pdf->output());
            $userId = Crypt::encrypt($data->id, 12);
            $data['encrypt_id'] = $userId;
            $data['url'] = $this->url->to('/');
            $data['email'] = $data->email;
            $data['name'] = $data->first_name.' '.$data->last_name;
            $data['subject'] = 'Welcome To Sequifi';
            $data['template'] = view('mail.onboarding', compact('data'));
            // Mail::to($data->email)->send(new OnboardingEmployee($data));
            $this->sendEmailNotification($data);

            return response()->json([
                'ApiName' => 'Send email Onboarding Employee',
                'status' => true,
                'message' => 'Send email Successfully.',
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'sendEmailOnBoardingEmployee',
                'status' => false,
                'message' => 'User Not Found',
                // 'data' => $data,
            ], 400);

        }

    }

    public function sendEmailOnBoardingEmployee_01_05_2023($id)
    {
        $company = CompanyProfile::first();
        $result = OnboardingEmployees::with('positionDetail', 'state')->where('id', $id)->first();
        $positionId = $result->position_id;
        $positionCommission = PositionCommission::where('position_id', $positionId)->first();
        $positionCommissionUpfronts = PositionCommissionUpfronts::where('position_id', $positionId)->first();
        $positionCommissionDeduction = PositionCommissionDeduction::where('position_id', $positionId)->first();
        $positionsDeductionLimit = PositionsDeductionLimit::where('position_id', $positionId)->first();
        $positionOverride = PositionOverride::where('position_id', $positionId)->first();
        $positionTierOverride = PositionTierOverride::where('position_id', $positionId)->first();
        $positionReconciliations = PositionReconciliations::where('position_id', $positionId)->first();
        $positionPayFrequency = PositionPayFrequency::with('frequencyType')->where('position_id', $positionId)->first();
        $positionOverrideSettlement = PositionOverrideSettlement::where('position_id', $positionId)->first();

        $recruiterId = $result->recruiter_id;
        $recruiter = User::with('positionDetail')->Select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'position_id')->where('id', $recruiterId)->first();
        // return $recruiter;
        if ($result) {
            $pdf = PDF::loadView('mail.pdf', [
                'title' => $result->first_name.' '.$result->last_name,
                'email' => $result->email,
                'mobile_no' => $result->mobile_no,
            ]);

            // file_put_contents("template/".$result->first_name.'-'.$result->last_name."_offer_letter.pdf", $pdf->output());
            $userId = Crypt::encrypt($result->id, 12);
            $data['encrypt_id'] = $userId;
            $data['url'] = $this->url->to('/');
            $data['full_company_name'] = $company->name;
            $data['company_address_line1'] = $company->address;
            $data['company_address_line2'] = $company->address;
            $data['company_phone'] = $company->phone_number;
            $data['company_email'] = $company->company_email;
            $data['company_website'] = 'https://dev.sequifi.com';
            // $data['company_logo'] = $company->logo;
            $data['company_logo'] = 'https://dev.sequifi.com/sequifi_backend/company-image/1680154843cca.jpeg';
            $data['current_date'] = date('d-m-Y');
            $data['employee_name'] = $result->first_name.' '.$result->last_name;
            $data['position'] = isset($result->positionDetail->position_name) ? $result->positionDetail->position_name : '';
            $data['office_location'] = isset($result->state->name) ? $result->state->name : '';
            $data['commission'] = isset($result->commission) ? $result->commission : '';
            $data['redline_par_watt'] = isset($result->redline_type) ? $result->redline_type : '';
            $data['upfront_amount'] = isset($result->upfront_pay_amount) ? $result->upfront_pay_amount : '';
            $data['sliding_scale_metric'] = isset($positionTierOverride->sliding_scale) ? $positionTierOverride->sliding_scale : '';
            $data['pay_frequency'] = isset($positionPayFrequency->frequencyType) ? $positionPayFrequency->frequencyType->name : '';
            $data['withholding_amount'] = isset($positionReconciliations->commission_withheld) ? $positionReconciliations->commission_withheld : '';
            $data['reconciliation_period_length'] = isset($positionReconciliations->maximum_withheld) ? $positionReconciliations->maximum_withheld : '';
            $data['direct_override_value'] = isset($result->direct_overrides_amount) ? $result->direct_overrides_amount : '';
            $data['indirect_override_value'] = isset($result->indirect_overrides_amount) ? $result->indirect_overrides_amount : '';
            $data['office_override_value'] = isset($result->office_overrides_amount) ? $result->office_overrides_amount : '';
            $data['bonus_amount'] = isset($result->offer_include_bonus) ? $result->offer_include_bonus : '';
            $data['bonus_pay_date'] = isset($result->date_to_be_paid) ? $result->date_to_be_paid : '';
            $data['pay_period'] = isset($positionPayFrequency->pay_period) ? $positionPayFrequency->pay_period : '';
            $data['deduction1_name'] = isset($positionCommissionDeduction->deduction_type) ? $positionCommissionDeduction->deduction_type : '';
            $data['deduction1_value'] = isset($positionCommissionDeduction->ammount_par_paycheck) ? $positionCommissionDeduction->ammount_par_paycheck : '';
            $data['deduction2_name'] = '';
            $data['deduction2_value'] = '';
            $data['start_date'] = isset($result->period_of_agreement_start_date) ? $result->period_of_agreement_start_date : '';
            $data['end_date'] = isset($result->end_date) ? $result->end_date : '';
            $data['probation_length'] = isset($result->probation_period) ? $result->probation_period : '';
            $data['recruiter_phone_number'] = isset($recruiter->mobile_no) ? $recruiter->mobile_no : '';
            $data['recruiter_manager_name'] = isset($recruiter->first_name) ? $recruiter->first_name : '';
            $data['recruiter_manager_position'] = isset($recruiter->positionDetail->position_name) ? $recruiter->positionDetail->position_name : '';

            $data['email'] = $result->email;
            $data['name'] = $result->first_name.' '.$result->last_name;
            $data['subject'] = 'Welcome To Sequifi';
            $data['template'] = view('mail.onboardingnew', compact('data'));
            // return $data['template'];
            // Mail::to($data->email)->send(new OnboardingEmployee($data));
            $this->sendEmailNotification($data);

            return response()->json([
                'ApiName' => 'Send email Onboarding Employee',
                'status' => true,
                'message' => 'Send email Successfully.',
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'sendEmailOnBoardingEmployee',
                'status' => false,
                'message' => 'User Not Found',
                // 'data' => $data,
            ], 400);

        }

    }

    // not in use
    public function sendEmailOnBoardingEmployee(Request $request, $id)
    {
        // $requestKeys = collect($request->all())->keys();
        // $requestvalue = collect($request->all())->values();
        try {
            $company = CompanyProfile::first();
            $result = OnboardingEmployees::with('positionDetail', 'state')->where('id', $id)->first();
            $positionId = $result->sub_position_id;
            $positionCommission = PositionCommission::where('position_id', $positionId)->first();
            $positionCommissionUpfronts = PositionCommissionUpfronts::where('position_id', $positionId)->first();
            $positionCommissionDeduction = PositionCommissionDeduction::with('costcenter')->where('position_id', $positionId)->get();
            $positionsDeductionLimit = PositionsDeductionLimit::where('position_id', $positionId)->first();
            $positionOverride = PositionOverride::where('position_id', $positionId)->first();
            $positionTierOverride = PositionTierOverride::where('position_id', $positionId)->first();
            $positionReconciliations = PositionReconciliations::where('position_id', $positionId)->first();
            $positionPayFrequency = PositionPayFrequency::with('frequencyType')->where('position_id', $positionId)->first();
            $positionOverrideSettlement = PositionOverrideSettlement::where('position_id', $positionId)->first();
            $recruiterId = $result->recruiter_id;
            $recruiter = User::with('positionDetail')->Select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'position_id', 'sub_position_id')->where('id', $recruiterId)->first();
            $deductionhtml = '';
            if (count($positionCommissionDeduction) > 0) {
                foreach ($positionCommissionDeduction as $key1 => $deduction) {
                    $deductionhtml .= '<p><strong>'.$deduction->costcenter->name.':</strong> '.$deduction->ammount_par_paycheck.' </p>';
                }
            }

            $data6 = OnboardingEmployees::find($id);
            $data6->status_id = 4;
            $data6->save();

            $userId = Crypt::encrypt($result->id, 12);
            $data1['encrypt_id'] = $userId;
            $data1['url'] = $this->url->to('/');
            $data1['Company_Name'] = $company->name;
            $data1['Company_Address'] = $company->business_address;
            $data1['company_address_line2'] = $company->business_address;
            $data1['Company_Phone'] = $company->phone_number;
            $data1['Company_Email'] = $company->company_email;
            $data1['Company_Website'] = $company->company_website;
            $data['company_logo'] = $company->logo;
            $data1['Company_Logo'] = config('app.base_url').$company->logo;
            // $data1['Company_Logo'] = 'https://dev.sequifi.com/sequifi/company-image/1686658138feximg.jpg';
            $data1['Current_Date'] = date('d-m-Y');
            $data1['Employee_Name'] = $result->first_name.' '.$result->last_name;
            $data1['Employee_Position'] = isset($result->positionDetail->position_name) ? $result->positionDetail->position_name : '';
            $data1['Office_Location'] = isset($result->state->name) ? $result->state->name : '';
            $data1['basic_job_description'] = '';
            $data1['commission'] = isset($result->commission) ? $result->commission : '';
            $data1['redline'] = isset($result->redline) ? $result->redline : '';
            $data1['redline_per_watt'] = isset($result->redline_type) ? $result->redline_type : '';
            $data1['upfront_amount'] = isset($result->upfront_pay_amount) ? $result->upfront_pay_amount : '';
            $data1['Sliding_Scale_Metric'] = isset($positionTierOverride->sliding_scale) ? $positionTierOverride->sliding_scale : '';
            $data1['pay_frequency'] = isset($positionPayFrequency->frequencyType) ? $positionPayFrequency->frequencyType->name : '';
            $data1['Withholding_Amount'] = isset($positionReconciliations->commission_withheld) ? $positionReconciliations->commission_withheld : '';
            $data1['Reconciliation_period_length'] = isset($positionReconciliations->maximum_withheld) ? $positionReconciliations->maximum_withheld : '';
            $data1['Direct_Override_Value'] = isset($result->direct_overrides_amount) ? $result->direct_overrides_amount : '';
            $data1['Indirect_Override_Value'] = isset($result->indirect_overrides_amount) ? $result->indirect_overrides_amount : '';
            $data1['Office_Override_Value'] = isset($result->office_overrides_amount) ? $result->office_overrides_amount : '';
            $data1['Bonus_amount'] = isset($result->hiring_bonus_amount) ? $result->hiring_bonus_amount : '';
            $data1['Bonus_Pay_Date'] = isset($result->date_to_be_paid) ? $result->date_to_be_paid : '';
            $data1['pay_period'] = isset($positionPayFrequency->pay_period) ? $positionPayFrequency->pay_period : '';
            // $data1['Deduction_#1_Name'] = isset($positionCommissionDeduction->deduction_type)? $positionCommissionDeduction->deduction_type:'';
            // $data1['Deduction_#1_Value'] = isset($positionCommissionDeduction->ammount_par_paycheck)? $positionCommissionDeduction->ammount_par_paycheck:'';
            // $data1['Deduction_#2_Name'] = '';
            // $data1['Deduction_#2_Value'] = '';
            $data1['deductions'] = $deductionhtml;
            $data1['start_date'] = isset($result->period_of_agreement_start_date) ? $result->period_of_agreement_start_date : '';
            $data1['End_Date'] = isset($result->end_date) ? $result->end_date : '';
            $data1['probation_length'] = isset($result->probation_period) ? $result->probation_period : '';
            $data1['Employee_first_name'] = isset($result->first_name) ? $result->first_name : '';
            $data1['Recrruiter_phone_number'] = isset($recruiter->mobile_no) ? $recruiter->mobile_no : '';
            $data1['Recruiter_Manager_First_and_last_Name'] = isset($recruiter->first_name) ? $recruiter->first_name : '';
            $data1['Recruiter_Manager_Position'] = isset($recruiter->positionDetail->position_name) ? $recruiter->positionDetail->position_name : '';
            $data1['accept'] = $data1['url'].'/api/accepted_declined_requested_change_hiring_process/'.$data1['encrypt_id'].'/Accepted';
            $data1['request_change'] = $data1['url'].'/api/requested_change_hiring_process/'.$data1['encrypt_id'].'/Requested Change';
            $data1['reject'] = $data1['url'].'/api/accepted_declined_requested_change_hiring_process/'.$data1['encrypt_id'].'/Declined';
            $data['email'] = $result->email;
            $data['name'] = $result->first_name.' '.$result->last_name;
            $data['subject'] = 'Welcome To '.$company->name.'!';
            // $data['template'] = view('mail.onboardingnew', compact('data'));

            $teplateId = 30;
            $data5 = SequiDocsTemplate::where('id', $teplateId)->first();
            $html = $data5['template_description'];
            $header = view('mail.header');
            $button = view('mail.button');
            $footer = view('mail.footer');

            $html = $header.$html.$button.$footer;

            foreach ($data1 as $key => $value) {
                $html = str_replace('['.$key.']', $value, $html);
            }

            $data['template'] = $html;

            $this->sendEmailNotification($data);

            // if ($html) {
            //     $dom = new \DOMDocument();
            //     $dom->loadHTML($html);

            //     $divToRemove = $dom->getElementById('hideButton');

            //     if ($divToRemove) {
            //         $divToRemove->parentNode->removeChild($divToRemove);
            //     }

            //     $html = $dom->saveHTML();
            // }

            $htmll = $data5['template_description'];
            $htmlls = $header.$htmll.$footer;

            foreach ($data1 as $key => $value) {
                $htmlls = str_replace('['.$key.']', $value, $htmlls);
            }

            $user = OnboardingEmployees::Select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'user_offer_letter')->where('id', $id)->first();
            // $pdf = PDF::loadView($html);

            $pdf = PDF::loadHTML($htmlls, 'UTF-8');

            $fileName = 'template/'.$user->first_name.'-'.$user->last_name.'_offer_letter.pdf';
            $filePath = config('app.domain_name').'/'.$fileName;
            $stored_bucket = 'private';

            $s3_return = s3_upload($filePath, $pdf->setPaper('A2', 'portrait')->output(), false, $stored_bucket);

            if (isset($s3_return['status']) && $s3_return['status'] == true) {
                $pdfPath = $s3_return['ObjectURL'];
                $user->user_offer_letter = $fileName;
            } else {
                return response()->json([
                    'ApiName' => 'sendEmailOnBoardingEmployee',
                    'status' => false,
                    'message' => 'Failed to upload PDF to S3.',
                ], 500);
            }
            $user->status_id = 4;
            $user->save();

            return response()->json([
                'ApiName' => 'Send email Onboarding Employee',
                'status' => true,
                'message' => 'Send email Successfully.',
                'path' => $pdfPath,
            ], 200);
        } catch (Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    // public function show($id)
    // {
    //     //
    // }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        // echo"DASD";die;

        $user = $this->OnboardingEmployees->newQuery();
        $user->with('departmentDetail', 'positionDetail', 'state', 'city', 'managerDetail', 'statusDetail', 'recruiter', 'additionalDetail', 'subpositionDetail', 'office', 'teamsDetail', 'OnboardingAdditionalEmails', 'wage');
        $data = $user->where('id', $id)->first();
        $additionalData = User::select('id', 'first_name', 'last_name', 'recruiter_id')->where('id', $data->additional_recruiter_id1)->orWhere('id', $data->additional_recruiter_id2)->get();
        $user_row = $data;

        if (isset($data) && $data != '') {

            $additional = [];
            // foreach ($additionalDetail as $deducationname) {
            //     $additional[] =
            //         array(
            //             // dd($deducationname->system_per_kw_amount),
            //             'id' => isset($deducationname->id)?$deducationname->id:null,
            //             'recruiter_id' => isset($deducationname->recruiter_id)?$deducationname->recruiter_id:null,
            //             'recruiter_first_name' => isset($deducationname->additionalRecruiterDetail->first_name)?$deducationname->additionalRecruiterDetail->first_name:null,
            //             'recruiter_last_name' => isset($deducationname->additionalRecruiterDetail->last_name)?$deducationname->additionalRecruiterDetail->last_name:null,
            //             'system_per_kw_amount' => isset($deducationname->system_per_kw_amount)?$deducationname->system_per_kw_amount:null,
            //         );
            // }
            foreach ($additionalData as $deducationname) {
                $additional[] =
                    [
                        // dd($deducationname->system_per_kw_amount),
                        'id' => isset($deducationname->id) ? $deducationname->id : null,
                        'recruiter_id' => isset($deducationname->recruiter_id) ? $deducationname->recruiter_id : null,
                        'recruiter_first_name' => isset($deducationname->first_name) ? $deducationname->first_name : null,
                        'recruiter_last_name' => isset($deducationname->last_name) ? $deducationname->last_name : null,
                        'system_per_kw_amount' => isset($deducationname->system_per_kw_amount) ? $deducationname->system_per_kw_amount : null,
                    ];
            }
            // echo $id;die;
            $additional_location = OnboardingEmployeeLocation::with('state', 'city', 'office')->where('user_id', $id)->get();
            if ($additional_location) {

                // OnboardingEmployeeOverride
                $employee_compensation = OnboardingUserRedline::select('position_id', 'commission', 'commission_type', 'commission_effective_date', 'updater_id', 'withheld_amount', 'withheld_type', 'withheld_effective_date',
                    'upfront_pay_amount', 'upfront_sale_type', 'upfront_effective_date', 'redline_amount_type', 'redline', 'redline_type', 'start_date')
                    ->where('user_id', $id)->get();
                $employee_compensation_result = [];

                foreach ($employee_compensation as $key => $res) {
                    $employee_compensation_result[$key]['position_id'] = $res->position_id;
                    $employee_compensation_result[$key]['updater_id'] = $res->updater_id;
                    if (strtotime($res->commission_effective_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['commission'] = $res->commission;
                        $employee_compensation_result[$key]['commission_type'] = $res->commission_type;
                        $employee_compensation_result[$key]['commission_effective_date'] = $res->commission_effective_date;
                    }
                    if (strtotime($res->upfront_effective_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['upfront_pay_amount'] = $res->upfront_pay_amount;
                        $employee_compensation_result[$key]['upfront_sale_type'] = $res->upfront_sale_type;
                        $employee_compensation_result[$key]['upfront_effective_date'] = $res->upfront_effective_date;
                    }
                    if (strtotime($res->withheld_effective_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['withheld_amount'] = $res->withheld_amount;
                        $employee_compensation_result[$key]['withheld_type'] = $res->withheld_type;
                        $employee_compensation_result[$key]['withheld_effective_date'] = $res->withheld_effective_date;
                    }
                    if (strtotime($res->start_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['redline_amount_type'] = $res->redline_amount_type;
                        $employee_compensation_result[$key]['redline'] = $res->redline;
                        $employee_compensation_result[$key]['redline_type'] = $res->redline_type;
                        $employee_compensation_result[$key]['redline_effective_date'] = $res->start_date;
                    }
                }
                $additional_locations = [];
                foreach ($additional_location as $d) {
                    $additional_locations[] = [
                        'state_id' => isset($d->state_id) ? $d->state_id : 'NA',
                        'state_name' => isset($d->state->name) ? $d->state->name : 'NA',
                        'city_id' => isset($d->city->id) ? $d->city->id : 'NA',
                        'city_name' => isset($d->city->name) ? $d->city->name : 'NA',
                        'office_id' => isset($d->office_id) ? $d->office_id : null,
                        'office_name' => isset($d->office->office_name) ? $d->office->office_name : null,
                        'overrides_amount' => isset($d->overrides_amount) ? $d->overrides_amount : null,
                        'overrides_type' => isset($d->overrides_type) ? $d->overrides_type : null,
                    ];
                }
                // $additional_location->transform(function ($data) {
                //         return [
                //     'state_id' =>  isset($data->state_id) ? $data->state_id : 'NA',
                //     'state_name' => isset($data->state->name) ? $data->state->name : 'NA',
                //     'city_id' => isset($data->city->id) ? $data->city->id : 'NA',
                //     'city_name' => isset($data->city->name) ? $data->city->name : 'NA',
                //     'office_id' => isset($data->office_id) ? $data->office_id : NULL,
                //     'office_name' => isset($data->office->office_name) ? $data->office->office_name :  NULL,
                // ];
                // });
                $onboarding_redline_data = OnboardingUserRedline::where('user_id', $id)->get();
                if ($onboarding_redline_data) {
                    $onboarding_redline_data->transform(function ($data) {
                        return [
                            'user_id' => isset($data->user_id) ? $data->user_id : null,
                            'updater_id' => isset($data->updater_id) ? $data->updater_id : null,
                            'redline' => isset($data->redline) ? $data->redline : null,
                            'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                            'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                            'start_date' => isset($data->start_date) ? $data->start_date : null,

                        ];
                    });

                    $overrideresult = OnboardingEmployeeOverride::where(['user_id' => $id])->first();
                    $response = [];
                    if (! empty($overrideresult)) {
                        $data->override_effective_date = $overrideresult->override_effective_date;
                        $data->direct_overrides_amount = $overrideresult->direct_overrides_amount;
                        $data->direct_overrides_type = $overrideresult->direct_overrides_type;
                        $data->indirect_overrides_amount = $overrideresult->indirect_overrides_amount;
                        $data->indirect_overrides_type = $overrideresult->indirect_overrides_type;
                        $data->office_overrides_amount = $overrideresult->office_overrides_amount;
                        $data->office_overrides_type = $overrideresult->office_overrides_type;
                        $data->office_stack_overrides_amount = $overrideresult->office_stack_overrides_amount;
                    }

                    // Logic for all docs sign or not
                    $onboarding_employees_documents = $user_row->OnboardingEmployeesDocuments;
                    $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
                    $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
                    $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];

                    $data1 =
                    [
                        'id' => isset($data->id) ? $data->id : null,
                        'is_all_doc_sign' => $is_all_doc_sign,
                        'other_doc_status' => $other_doc_status,
                        'onboarding_employees_documents' => $onboarding_employees_documents,
                        'first_name' => isset($data->first_name) ? $data->first_name : null,
                        'middle_name' => isset($data->middle_name) ? $data->middle_name : null,
                        'last_name' => isset($data->last_name) ? $data->last_name : null,
                        'sex' => isset($data->sex) ? $data->sex : null,
                        'dob' => isset($data->dob) ? dateToYMD($data->dob) : null,
                        'image' => isset($data->image) ? $data->image : null,
                        'zip_code' => isset($data->zip_code) ? $data->zip_code : null,
                        'email' => isset($data->email) ? $data->email : null,
                        'is_manager' => isset($data->is_manager) ? $data->is_manager : null,
                        'is_manager_effective_date' => isset($data->is_manager_effective_date) ? $data->is_manager_effective_date : null,
                        // 'self_gen_accounts' =>  isset($data->self_gen_accounts)?$data->self_gen_accounts:null,
                        'home_address' => isset($data->home_address) ? $data->home_address : null,
                        'mobile_no' => isset($data->mobile_no) ? $data->mobile_no : null,
                        'state_id' => isset($data->state_id) ? $data->state_id : null,
                        'state_name' => isset($data['state']->name) ? $data['state']->name : null,
                        'city_id' => isset($data->city_id) ? $data->city_id : null,
                        'city_name' => isset($data['city']->name) ? $data['city']->name : null,
                        'location' => isset($data->location) ? $data->location : null,
                        'department_id' => isset($data->department_id) ? $data->department_id : null,
                        'department_name' => isset($data->departmentDetail->name) ? $data->departmentDetail->name : null,
                        'employee_position_id' => isset($data->employee_position_id) ? $data->employee_position_id : null,
                        'manager_id' => isset($data->manager_id) ? $data->manager_id : null,
                        'manager_id_effective_date' => isset($data->manager_id_effective_date) ? $data->manager_id_effective_date : null,
                        'manager_name' => isset($data->managerDetail->id) ? $data->managerDetail->name : null,
                        'team_id' => isset($data->team_id) ? $data->team_id : null,
                        'team_id_effective_date' => isset($data->team_id_effective_date) ? $data->team_id_effective_date : null,
                        // 'team_id' => isset($data->teamsDetail->id)?$data->teamsDetail->id:null,
                        'team_name' => isset($data->teamsDetail->team_name) ? $data->teamsDetail->team_name : null,
                        'office_id' => isset($data->office_id) ? $data->office_id : null,
                        'status_id' => isset($data->status_id) ? $data->status_id : null,
                        'status_name' => isset($data->statusDetail->status) ? $data->statusDetail->status : null,
                        'recruiter_id' => isset($data->recruiter_id) ? $data->recruiter_id : null,
                        'recruiter_name' => isset($data->recruiter->first_name) ? $data->recruiter->first_name.' '.$data->recruiter->last_name : null,
                        'offer_include_bonus' => isset($data->offer_include_bonus) ? $data->offer_include_bonus : null,
                        // 'additional_recruiter' => $additional,
                        'position_id' => isset($data->position_id) ? $data->position_id : null,
                        'position_name' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                        'sub_position_id' => isset($data->sub_position_id) ? $data->sub_position_id : null,
                        'sub_position_name' => isset($data->subpositionDetail->position_name) ? $data->subpositionDetail->position_name : null,
                        'redline' => isset($data->redline) ? $data->redline : null,
                        'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                        'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                        'start_date' => isset($data->start_date) ? $data->start_date : null,
                        'self_gen_redline' => isset($data->self_gen_redline) ? $data->self_gen_redline : null,
                        'self_gen_redline_amount_type' => isset($data->self_gen_redline_amount_type) ? $data->self_gen_redline_amount_type : null,
                        'self_gen_redline_type' => isset($data->self_gen_redline_type) ? $data->self_gen_redline_type : null,
                        'self_gen_commission' => isset($data->self_gen_commission) ? $data->self_gen_commission : null,
                        'self_gen_upfront_amount' => isset($data->self_gen_upfront_amount) ? $data->self_gen_upfront_amount : null,
                        'self_gen_upfront_type' => isset($data->self_gen_upfront_type) ? $data->self_gen_upfront_type : null,
                        'self_gen_accounts' => isset($data->self_gen_accounts) ? $data->self_gen_accounts : null,
                        'upfront_pay_amount' => isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null,
                        'upfront_sale_type' => isset($data->upfront_sale_type) ? $data->upfront_sale_type : null,
                        'upfront_effective_date' => isset($data->upfront_effective_date) ? $data->upfront_effective_date : null,
                        'override_effective_date' => isset($data->override_effective_date) ? $data->override_effective_date : null,
                        'direct_overrides_amount' => isset($data->direct_overrides_amount) ? $data->direct_overrides_amount : null,
                        'direct_overrides_type' => isset($data->direct_overrides_type) ? $data->direct_overrides_type : null,
                        'indirect_overrides_amount' => isset($data->indirect_overrides_amount) ? $data->indirect_overrides_amount : null,
                        'indirect_overrides_type' => isset($data->indirect_overrides_type) ? $data->indirect_overrides_type : null,
                        'office_overrides_amount' => isset($data->office_overrides_amount) ? $data->office_overrides_amount : null,
                        'office_overrides_type' => isset($data->office_overrides_type) ? $data->office_overrides_type : null,
                        'office_stack_overrides_amount' => isset($data->office_stack_overrides_amount) ? $data->office_stack_overrides_amount : null,
                        'withheld_amount' => isset($data->withheld_amount) ? $data->withheld_amount : null,
                        'withheld_type' => isset($data->withheld_type) ? $data->withheld_type : null,
                        'probation_period' => (isset($data->probation_period) && $data->probation_period != 'None') ? $data->probation_period : null,
                        'commission' => isset($data->commission) ? $data->commission : null,
                        'commission_effective_date' => isset($data->commission_effective_date) ? $data->commission_effective_date : null,
                        'hiring_bonus_amount' => isset($data->hiring_bonus_amount) ? $data->hiring_bonus_amount : null,
                        'date_to_be_paid' => isset($data->date_to_be_paid) ? dateToYMD($data->date_to_be_paid) : null,
                        'period_of_agreement_start_date' => isset($data->period_of_agreement_start_date) ? dateToYMD($data->period_of_agreement_start_date) : null,
                        'end_date' => isset($data->end_date) ? dateToYMD($data->end_date) : null,
                        'offer_expiry_date' => isset($data->offer_expiry_date) ? $data->offer_expiry_date : null,
                        'type' => isset($data->type) ? $data->type : null,
                        'additional_recruter' => isset($additional) ? $additional : null,
                        'office' => isset($data->office) ? $data->office : null,
                        'additional_locations' => isset($additional_locations) ? $additional_locations : null,
                        'redline_data' => isset($onboarding_redline_data) ? $onboarding_redline_data : null,
                        'employee_compensation' => $employee_compensation_result,
                        'work_email' => $data->OnboardingAdditionalEmails,
                        'commission_selfgen' => $data->commission_selfgen,
                        'commission_selfgen_type' => $data->commission_selfgen_type,
                        'commission_selfgen_effective_date' => $data->commission_selfgen_effective_date,
                        'is_background_verificaton' => $data->is_background_verificaton,
                        // 'wage' =>  $data->wage
                        // 'created_at' => $data->created_at,
                        // 'updated_at' => $data->updated_at,
                        'worker_type' => $data->worker_type,
                        'pay_type' => $data->pay_type,
                        'pay_rate' => ($data->pay_rate == 0) ? null : $data->pay_rate,
                        'pay_rate_type' => $data->pay_rate_type,
                        'expected_weekly_hours' => $data->expected_weekly_hours,
                        'overtime_rate' => $data->overtime_rate,
                        'pto_hours' => ($data->pto_hours == 0) ? null : $data->pto_hours,
                        'unused_pto_expires' => $data->unused_pto_expires,
                        // 'created_at' => $data->created_at,
                        // 'updated_at' => $data->updated_at,
                        'experience_level' => isset($data->experience_level) ? $data->experience_level : null,
                    ];

                    $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $data->id)->first();
                    $data1['template_id'] = $sentOfferLetter ? $sentOfferLetter->template_id : null;

                    return response()->json([
                        'ApiName' => 'update-onboarding-employee',
                        'status' => true,
                        'message' => 'Successfully.',
                        'data' => $data1,
                    ], 200);
                }
            }

        } else {
            return response()->json([
                'ApiName' => 'show-onboarding-employee',
                'status' => true,
                'message' => 'Invalid user id',
            ], 400);
        }

    }

    public function onboarding_employee_details_by_id($id)
    {

        $user = $this->OnboardingEmployees->with('departmentDetail', 'positionDetail', 'state', 'city', 'managerDetail', 'statusDetail', 'recruiter', 'additionalDetail', 'subpositionDetail', 'office', 'teamsDetail', 'OnboardingAdditionalEmails');

        $data = $user->where('id', $id)->first();

        if (isset($data) && $data != '') {
            $additionalData = User::select('id', 'first_name', 'last_name', 'recruiter_id')->where('id', $data->additional_recruiter_id1)->orWhere('id', $data->additional_recruiter_id2)->get();
            $user_row = $data;

            $additional = [];

            foreach ($additionalData as $deducationname) {
                $additional[] =
                    [
                        // dd($deducationname->system_per_kw_amount),
                        'id' => isset($deducationname->id) ? $deducationname->id : null,
                        'recruiter_id' => isset($deducationname->recruiter_id) ? $deducationname->recruiter_id : null,
                        'recruiter_first_name' => isset($deducationname->first_name) ? $deducationname->first_name : null,
                        'recruiter_last_name' => isset($deducationname->last_name) ? $deducationname->last_name : null,
                        'system_per_kw_amount' => isset($deducationname->system_per_kw_amount) ? $deducationname->system_per_kw_amount : null,
                    ];
            }
            // echo $id;die;
            $additional_location = OnboardingEmployeeLocation::with('state', 'city', 'office')->where('user_id', $id)->get();
            if ($additional_location) {

                // OnboardingEmployeeOverride
                $employee_compensation = OnboardingUserRedline::select('position_id', 'commission', 'commission_effective_date', 'updater_id', 'withheld_amount', 'withheld_type', 'withheld_effective_date',
                    'upfront_pay_amount', 'upfront_sale_type', 'upfront_effective_date', 'redline_amount_type', 'redline', 'redline_type', 'start_date')
                    ->where('user_id', $id)->get();
                $employee_compensation_result = [];

                foreach ($employee_compensation as $key => $res) {
                    $employee_compensation_result[$key]['position_id'] = $res->position_id;
                    $employee_compensation_result[$key]['updater_id'] = $res->updater_id;
                    if (strtotime($res->commission_effective_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['commission'] = $res->commission;
                        $employee_compensation_result[$key]['commission_effective_date'] = $res->commission_effective_date;
                    }
                    if (strtotime($res->upfront_effective_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['upfront_pay_amount'] = $res->upfront_pay_amount;
                        $employee_compensation_result[$key]['upfront_sale_type'] = $res->upfront_sale_type;
                        $employee_compensation_result[$key]['upfront_effective_date'] = $res->upfront_effective_date;
                    }
                    if (strtotime($res->withheld_effective_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['withheld_amount'] = $res->withheld_amount;
                        $employee_compensation_result[$key]['withheld_type'] = $res->withheld_type;
                        $employee_compensation_result[$key]['withheld_effective_date'] = $res->withheld_effective_date;
                    }
                    if (strtotime($res->start_date) <= strtotime(date('Y-m-d H:i:s'))) {
                        $employee_compensation_result[$key]['redline_amount_type'] = $res->redline_amount_type;
                        $employee_compensation_result[$key]['redline'] = $res->redline;
                        $employee_compensation_result[$key]['redline_type'] = $res->redline_type;
                        $employee_compensation_result[$key]['redline_effective_date'] = $res->start_date;
                    }
                }
                $additional_locations = [];
                foreach ($additional_location as $d) {
                    $additional_locations[] = [
                        'state_id' => isset($d->state_id) ? $d->state_id : 'NA',
                        'state_name' => isset($d->state->name) ? $d->state->name : 'NA',
                        'city_id' => isset($d->city->id) ? $d->city->id : 'NA',
                        'city_name' => isset($d->city->name) ? $d->city->name : 'NA',
                        'office_id' => isset($d->office_id) ? $d->office_id : null,
                        'office_name' => isset($d->office->office_name) ? $d->office->office_name : null,
                        'overrides_amount' => isset($d->overrides_amount) ? $d->overrides_amount : null,
                        'overrides_type' => isset($d->overrides_type) ? $d->overrides_type : null,
                    ];
                }
                // $additional_location->transform(function ($data) {
                //         return [
                //     'state_id' =>  isset($data->state_id) ? $data->state_id : 'NA',
                //     'state_name' => isset($data->state->name) ? $data->state->name : 'NA',
                //     'city_id' => isset($data->city->id) ? $data->city->id : 'NA',
                //     'city_name' => isset($data->city->name) ? $data->city->name : 'NA',
                //     'office_id' => isset($data->office_id) ? $data->office_id : NULL,
                //     'office_name' => isset($data->office->office_name) ? $data->office->office_name :  NULL,
                // ];
                // });
                $onboarding_redline_data = OnboardingUserRedline::where('user_id', $id)->get();
                if ($onboarding_redline_data) {
                    $onboarding_redline_data->transform(function ($data) {
                        return [
                            'user_id' => isset($data->user_id) ? $data->user_id : null,
                            'updater_id' => isset($data->updater_id) ? $data->updater_id : null,
                            'redline' => isset($data->redline) ? $data->redline : null,
                            'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                            'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                            'start_date' => isset($data->start_date) ? $data->start_date : null,

                        ];
                    });

                    $overrideresult = OnboardingEmployeeOverride::where(['user_id' => $id])->first();
                    $response = [];
                    if (! empty($overrideresult)) {
                        $data->override_effective_date = $overrideresult->override_effective_date;
                        $data->direct_overrides_amount = $overrideresult->direct_overrides_amount;
                        $data->direct_overrides_type = $overrideresult->direct_overrides_type;
                        $data->indirect_overrides_amount = $overrideresult->indirect_overrides_amount;
                        $data->indirect_overrides_type = $overrideresult->indirect_overrides_type;
                        $data->office_overrides_amount = $overrideresult->office_overrides_amount;
                        $data->office_overrides_type = $overrideresult->office_overrides_type;
                        $data->office_stack_overrides_amount = $overrideresult->office_stack_overrides_amount;
                    }

                    // $employeeWages = OnboardingEmployeeWages::where(['user_id'=>$id])->first();
                    $empWages = [
                        'pay_type' => isset($data->pay_type) ? $data->pay_type : null,
                        'pay_rate' => isset($data->pay_rate) ? $data->pay_rate : null,
                        'pay_rate_type' => isset($data->pay_rate_type) ? $data->pay_rate_type : null,
                        'pto_hours' => isset($data->pto_hours) ? $data->pto_hours : null,
                        'unused_pto_expires' => isset($data->unused_pto_expires) ? $data->unused_pto_expires : null,
                        'expected_weekly_hours' => isset($data->expected_weekly_hours) ? $data->expected_weekly_hours : null,
                        'overtime_rate' => isset($data->overtime_rate) ? $data->overtime_rate : null,
                    ];

                    // Logic for all docs sign or not
                    $onboarding_employees_documents = $user_row->OnboardingEmployeesDocuments;
                    $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
                    $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
                    $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];

                    // Hire button show hide as per new tables of sequidoc
                    $onboarding_employees_new_documents = $user_row->newOnboardingEmployeesDocuments;
                    $onboarding_employees_new_document_status = OnboardingEmployees::onboarding_employees_new_document_status($onboarding_employees_new_documents);
                    $is_all_new_doc_sign = $onboarding_employees_new_document_status['is_all_new_doc_sign'];

                    $data1 =
                    [
                        'id' => isset($data->id) ? $data->id : null,
                        'user_id' => $data->user_id ?? null,
                        'is_all_doc_sign' => $is_all_doc_sign,
                        'is_all_new_doc_sign' => $is_all_new_doc_sign,
                        'other_doc_status' => $other_doc_status,
                        'onboarding_employees_documents' => $onboarding_employees_documents,
                        'onboarding_employees_new_documents' => $onboarding_employees_new_documents,
                        'first_name' => isset($data->first_name) ? $data->first_name : null,
                        'middle_name' => isset($data->middle_name) ? $data->middle_name : null,
                        'last_name' => isset($data->last_name) ? $data->last_name : null,
                        'sex' => isset($data->sex) ? $data->sex : null,
                        'dob' => isset($data->dob) ? dateToYMD($data->dob) : null,
                        'image' => isset($data->image) ? $data->image : null,
                        'zip_code' => isset($data->zip_code) ? $data->zip_code : null,
                        'email' => isset($data->email) ? $data->email : null,
                        'is_manager' => isset($data->is_manager) ? $data->is_manager : null,
                        'self_gen_accounts' => isset($data->self_gen_accounts) ? $data->self_gen_accounts : null,
                        'home_address' => isset($data->home_address) ? $data->home_address : null,
                        'mobile_no' => isset($data->mobile_no) ? $data->mobile_no : null,
                        'state_id' => isset($data->state_id) ? $data->state_id : null,
                        'state_name' => isset($data['state']->name) ? $data['state']->name : null,
                        'city_id' => isset($data->city_id) ? $data->city_id : null,
                        'city_name' => isset($data['city']->name) ? $data['city']->name : null,
                        'location' => isset($data->location) ? $data->location : null,
                        'department_id' => isset($data->department_id) ? $data->department_id : null,
                        'department_name' => isset($data->departmentDetail->name) ? $data->departmentDetail->name : null,
                        'employee_position_id' => isset($data->employee_position_id) ? $data->employee_position_id : null,
                        'manager_id' => isset($data->manager_id) ? $data->manager_id : null,
                        'manager_name' => isset($data->managerDetail->id) ? $data->managerDetail->name : null,
                        'team_id' => isset($data->team_id) ? $data->team_id : null,
                        'custom_fields' => isset($data->custom_fields) ? json_decode($data->custom_fields) : null,
                        // 'team_id' => isset($data->teamsDetail->id)?$data->teamsDetail->id:null,
                        'team_name' => isset($data->teamsDetail->team_name) ? $data->teamsDetail->team_name : null,
                        'office_id' => isset($data->office_id) ? $data->office_id : null,
                        'status_id' => isset($data->status_id) ? $data->status_id : null,
                        'status_name' => isset($data->statusDetail->status) ? $data->statusDetail->status : null,
                        'recruiter_id' => isset($data->recruiter_id) ? $data->recruiter_id : null,
                        'recruiter_name' => isset($data->recruiter->first_name) ? $data->recruiter->first_name.' '.$data->recruiter->last_name : null,
                        'offer_include_bonus' => isset($data->offer_include_bonus) ? $data->offer_include_bonus : null,
                        // 'additional_recruiter' => $additional,
                        'position_id' => isset($data->position_id) ? $data->position_id : null,
                        'position_name' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                        'sub_position_id' => isset($data->sub_position_id) ? $data->sub_position_id : null,
                        'sub_position_name' => isset($data->subpositionDetail->position_name) ? $data->subpositionDetail->position_name : null,
                        'redline' => isset($data->redline) ? $data->redline : null,
                        'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                        'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                        'start_date' => isset($data->start_date) ? $data->start_date : null,
                        'self_gen_redline' => isset($data->self_gen_redline) ? $data->self_gen_redline : null,
                        'self_gen_redline_amount_type' => isset($data->self_gen_redline_amount_type) ? $data->self_gen_redline_amount_type : null,
                        'self_gen_redline_type' => isset($data->self_gen_redline_type) ? $data->self_gen_redline_type : null,
                        'self_gen_commission' => isset($data->self_gen_commission) ? $data->self_gen_commission : null,
                        'self_gen_upfront_amount' => isset($data->self_gen_upfront_amount) ? $data->self_gen_upfront_amount : null,
                        'self_gen_upfront_type' => isset($data->self_gen_upfront_type) ? $data->self_gen_upfront_type : null,
                        'self_gen_accounts' => isset($data->self_gen_accounts) ? $data->self_gen_accounts : null,
                        'upfront_pay_amount' => isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null,
                        'upfront_sale_type' => isset($data->upfront_sale_type) ? $data->upfront_sale_type : null,
                        'upfront_effective_date' => isset($data->upfront_effective_date) ? $data->upfront_effective_date : null,
                        'override_effective_date' => isset($data->override_effective_date) ? $data->override_effective_date : null,
                        'direct_overrides_amount' => isset($data->direct_overrides_amount) ? $data->direct_overrides_amount : null,
                        'direct_overrides_type' => isset($data->direct_overrides_type) ? $data->direct_overrides_type : null,
                        'indirect_overrides_amount' => isset($data->indirect_overrides_amount) ? $data->indirect_overrides_amount : null,
                        'indirect_overrides_type' => isset($data->indirect_overrides_type) ? $data->indirect_overrides_type : null,
                        'office_overrides_amount' => isset($data->office_overrides_amount) ? $data->office_overrides_amount : null,
                        'office_overrides_type' => isset($data->office_overrides_type) ? $data->office_overrides_type : null,
                        'office_stack_overrides_amount' => isset($data->office_stack_overrides_amount) ? $data->office_stack_overrides_amount : null,
                        'withheld_amount' => isset($data->withheld_amount) ? $data->withheld_amount : null,
                        'withheld_type' => isset($data->withheld_type) ? $data->withheld_type : null,
                        'probation_period' => isset($data->probation_period) && $data->probation_period != 'None' ? $data->probation_period : null,
                        'commission' => isset($data->commission) ? $data->commission : null,
                        'commission_effective_date' => isset($data->commission_effective_date) ? $data->commission_effective_date : null,
                        'hiring_bonus_amount' => isset($data->hiring_bonus_amount) ? $data->hiring_bonus_amount : null,
                        'date_to_be_paid' => isset($data->date_to_be_paid) ? dateToYMD($data->date_to_be_paid) : null,
                        'period_of_agreement_start_date' => isset($data->period_of_agreement_start_date) ? dateToYMD($data->period_of_agreement_start_date) : null,
                        'end_date' => isset($data->end_date) ? dateToYMD($data->end_date) : null,
                        'offer_expiry_date' => isset($data->offer_expiry_date) ? $data->offer_expiry_date : null,
                        'type' => isset($data->type) ? $data->type : null,
                        'additional_recruter' => isset($additional) ? $additional : null,
                        'office' => isset($data->office) ? $data->office : null,
                        'additional_locations' => isset($additional_locations) ? $additional_locations : null,
                        'redline_data' => isset($onboarding_redline_data) ? $onboarding_redline_data : null,
                        'employee_compensation' => $employee_compensation_result,
                        'work_email' => $data->OnboardingAdditionalEmails,
                        'commission_selfgen' => $data->commission_selfgen,
                        'employee_wages' => $empWages,
                        // 'created_at' => $data->created_at,
                        // 'updated_at' => $data->updated_at,

                    ];

                    return response()->json([
                        'ApiName' => Route::currentRouteName(),
                        'status' => true,
                        'message' => 'Successfully.',
                        'data' => $data1,
                    ], 200);
                }
            }

        } else {
            return response()->json([
                'ApiName' => 'show-onboarding-employee',
                'status' => true,
                'message' => 'Invalid user id',
            ], 400);
        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        // echo"DSAD";DIE;
        // return $request;
        $data = OnboardingEmployees::find($id);
        $data->first_name = $request['first_name'];
        $data->middle_name = $request['middle_name'];
        $data->last_name = $request['last_name'];
        $data->email = $request['email'];
        $data->mobile_no = $request['mobile_no'];
        $data->state_id = $request['state_id'];
        $data->city_id = $request['city_id'];
        // $data->department_id = $request['department_id'];
        $data->save();

        //  $system_amount = $request['system_per_kw_amount'];
        //  $recruiter_id = $request['additional_recruiter_id'];
        // $dd = AdditionalRecruiters::where('id',$id)->delete();
        // foreach($system_amount as $key => $value)
        // {

        //     $val =   AdditionalRecruiters::create([
        //         'user_id' => $data->id,
        //         'recruiter_id' => $recruiter_id[$key],
        //         'system_per_kw_amount' => $value,
        //     ]);
        // }

        return response()->json([
            'ApiName' => 'update-onboarding-employee',
            'status' => true,
            'message' => 'update Successfully.',
            'data' => $data,
        ], 200);

    }

    public function OnboardingConfigurationSetting_old(Request $request): JsonResponse
    {
        $settingId = isset($request->id) ? $request->id : '';

        if (isset($request['automatic_hiring_status'])) {
            if ($settingId == '') {
                $EmployeeIdSetting = EmployeeIdSetting::first();

                if (! empty($EmployeeIdSetting)) {
                    $EmployeeIdSetting->automatic_hiring_status = $request['automatic_hiring_status'];
                    $EmployeeIdSetting->save();

                    return response()->json([
                        'ApiName' => 'Onboarding Automatic hiring status Setting',
                        'status' => true,
                        'data' => $EmployeeIdSetting,
                        'message' => 'Automatic hiring status updated successfully.',
                    ], 200);
                }

                $data = EmployeeIdSetting::create([
                    'automatic_hiring_status' => $request['automatic_hiring_status'],
                ]);
                $values = EmployeeIdSetting::where('id', $data->id)
                    ->with('AdditionalInfoForEmployeeToGetStarted', 'EmployeePersonalDetail', 'DocumentType')
                    ->first();

                return response()->json([
                    'ApiName' => 'Onboarding Employee Configuration Setting',
                    'status' => true,
                    'message' => 'Configuration Setting Successfully.',
                    'data' => $values,
                ], 200);
            } else {
                $employeePositionId = EmployeeIdSetting::where('id', $settingId)->first();

                if ($employeePositionId == '') {
                    return response()->json([
                        'ApiName' => 'Onboarding Automatic hiring status Setting',
                        'status' => false,
                        'message' => 'Bad Request',
                    ], 400);
                }

                $data = EmployeeIdSetting::where('id', $settingId)->first();
                $data->automatic_hiring_status = $request['automatic_hiring_status'];
                $data->save();

                $values = EmployeeIdSetting::where('id', $settingId)
                    ->with('AdditionalInfoForEmployeeToGetStarted', 'EmployeePersonalDetail', 'DocumentType')
                    ->first();

                return response()->json([
                    'ApiName' => 'Onboarding Employee Configuration Setting',
                    'status' => true,
                    'message' => 'Configuration Setting Successfully.',
                    'data' => $values,
                ], 200);
            }
        }

    }

    public function OnboardingConfigurationSetting(Request $request): JsonResponse
    {
        $settingId = isset($request->id) ? $request->id : '';

        $Validator = Validator::make($request->all(),
            [
                'require_approval_status' => 'required',
                'automatic_hiring_status' => 'required',
            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        if (isset($request['automatic_hiring_status'])) {
            if ($request->require_approval_status == 1) {
                $approval_position = ! empty($request->approval_position) ? json_encode($request->approval_position) : null;
            } else {
                $approval_position = null;
            }
            if ($request->automatic_hiring_status == 1) {
                $approval_onboarding_position = ! empty($request->approval_onboarding_position) ? json_encode($request->approval_onboarding_position) : null;
            } else {
                $approval_onboarding_position = null;
            }

            if ($settingId == '') {
                $EmployeeIdSetting = EmployeeIdSetting::first();

                if (! empty($EmployeeIdSetting)) {
                    $EmployeeIdSetting->require_approval_status = $request['require_approval_status'];
                    $EmployeeIdSetting->approval_position = $approval_position;
                    $EmployeeIdSetting->automatic_hiring_status = $request['automatic_hiring_status'];
                    $EmployeeIdSetting->approval_onboarding_position = $approval_onboarding_position;
                    $EmployeeIdSetting->special_approval_status = $request['special_approval_status'];
                    $EmployeeIdSetting->save();

                    return response()->json([
                        'ApiName' => 'Onboarding Automatic hiring status Setting',
                        'status' => true,
                        'data' => $EmployeeIdSetting,
                        'message' => 'Automatic hiring status updated successfully.',
                    ], 200);
                }

                $data = EmployeeIdSetting::create([
                    'require_approval_status' => $request['require_approval_status'],
                    'approval_position' => $approval_position,
                    'automatic_hiring_status' => $request['automatic_hiring_status'],
                    'approval_onboarding_position' => $approval_onboarding_position,
                    'special_approval_status' => $request['special_approval_status'],
                ]);
                $values = EmployeeIdSetting::where('id', $data->id)
                    ->with('AdditionalInfoForEmployeeToGetStarted', 'EmployeePersonalDetail', 'DocumentType')
                    ->first();

                return response()->json([
                    'ApiName' => 'Onboarding Employee Configuration Setting',
                    'status' => true,
                    'message' => 'Configuration Setting Successfully.',
                    'data' => $values,
                ], 200);
            } else {
                $employeePositionId = EmployeeIdSetting::where('id', $settingId)->first();

                if ($employeePositionId == '') {
                    return response()->json([
                        'ApiName' => 'Onboarding Automatic hiring status Setting',
                        'status' => false,
                        'message' => 'Bad Request',
                    ], 400);
                }

                $data = EmployeeIdSetting::where('id', $settingId)->first();
                $data->require_approval_status = $request['require_approval_status'];
                $data->approval_position = $approval_position;
                $data->automatic_hiring_status = $request['automatic_hiring_status'];
                $data->approval_onboarding_position = $approval_onboarding_position;
                $data->special_approval_status = $request['special_approval_status'];
                $data->save();

                $values = EmployeeIdSetting::where('id', $settingId)
                    ->with('AdditionalInfoForEmployeeToGetStarted', 'EmployeePersonalDetail', 'DocumentType')
                    ->first();

                return response()->json([
                    'ApiName' => 'Onboarding Employee Configuration Setting',
                    'status' => true,
                    'message' => 'Configuration Setting Successfully.',
                    'data' => $values,
                ], 200);
            }
        }

    }

    public function onboardingConfigurationSettingAdd(Request $request): JsonResponse
    {
        // $configurationId = $request->id;
        $configurationId = 1;
        $additionalInfoArr = $request->additional_info_for_employee_to_get_started ?? [];
        AdditionalInfoForEmployeeToGetStarted::query()->update(['is_deleted' => 1]);

        if (! empty($additionalInfoArr)) {
            // dd('here');
            $newRecords = [];

            foreach ($additionalInfoArr as $additionalInfo) {
                if (! empty($additionalInfo)) {
                    // dd('here');
                    $attribute = isset($additionalInfo['field_type']) && $additionalInfo['field_type'] === 'dropdown'
                        ? json_encode($additionalInfo['attribute_option'], true)
                        : null;

                    if (isset($additionalInfo['id'])) {

                        AdditionalInfoForEmployeeToGetStarted::where('id', $additionalInfo['id'])->update([
                            'configuration_id' => $configurationId,
                            'field_name' => $additionalInfo['field_name'] ?? '',
                            'field_type' => $additionalInfo['field_type'] ?? '',
                            'field_required' => $additionalInfo['field_required'] ?? '',
                            'attribute_option' => $attribute,
                            'is_deleted' => $additionalInfo['is_deleted'] ?? 0,
                        ]);

                    } else {

                        $record = new AdditionalInfoForEmployeeToGetStarted;
                        $record->configuration_id = $configurationId;
                        $record->field_name = $additionalInfo['field_name'] ?? '';
                        $record->field_type = $additionalInfo['field_type'] ?? '';
                        $record->field_required = $additionalInfo['field_required'] ?? '';
                        $record->attribute_option = $attribute;
                        $record->save();

                    }
                }
            }

        }

        $data11 = DocumentType::where('configuration_id', $configurationId)->where('is_deleted', 0)->pluck('id');
        // dd($data11);
        $doccs = Documents::whereIn('document_type_id', $data11)->first();

        $docupdate = isset($request->document_to_update) ? $request->document_to_update : null;
        if ($docupdate) {

            $docsId = $docupdate['id'];
            if ($docsId) {
                $param = DocumentType::where('id', $docsId)->first();
                $param->configuration_id = $configurationId;
                $param->field_name = isset($docupdate['field_name']) ? $docupdate['field_name'] : null;
                $param->field_required = isset($docupdate['field_required']) ? $docupdate['field_required'] : null;
                $param->field_link = isset($docupdate['field_link']) ? $docupdate['field_link'] : null;
                $param->save();
            } else {
                $param = [
                    'configuration_id' => $configurationId,
                    'field_name' => isset($docupdate['field_name']) ? $docupdate['field_name'] : null,
                    'field_required' => isset($docupdate['field_required']) ? $docupdate['field_required'] : null,
                    'field_link' => isset($docupdate['field_link']) ? $docupdate['field_link'] : null,
                ];
                DocumentType::create($param);
            }

        }

        return response()->json([
            'ApiName' => 'Onboarding Employee Configuration Setting Add',
            'status' => true,
            'message' => 'Configuration Setting Successfully.',
        ], 200);
    }

    public function onboardingConfigurationSettingDelete(Request $request): JsonResponse
    {
        // $configurationId = $request->id;
        $configurationId = 1;
        $employeefield = isset($request->employee_personal_detail_id) ? $request->employee_personal_detail_id : null;
        if ($employeefield) {

            $employeePersonal = EmployeePersonalDetail::find($employeefield);
            $employeePersonal->is_deleted = 1;
            $employeePersonal->save();

        }

        $additional_info_id = isset($request->additional_info_id) ? $request->additional_info_id : null;
        if ($additional_info_id) {

            $employeeAdditional = AdditionalInfoForEmployeeToGetStarted::find($additional_info_id);
            $employeeAdditional->is_deleted = 1;
            $employeeAdditional->save();

        }

        // $data11 = DocumentType::where('configuration_id',$configurationId)->pluck('id');
        $document_type_id = isset($request->document_type_id) ? $request->document_type_id : null;
        if ($document_type_id) {
            $employeeDocument = DocumentType::find($document_type_id);
            $employeeDocument->is_deleted = 1;
            $employeeDocument->save();
            $doccs = Documents::where('document_type_id', $document_type_id)->first();
            // if ($doccs) {
            //     return response()->json([
            //         'ApiName' => 'Onboarding Employee Configuration Setting Delete',
            //         'status' => false,
            //         'message' => 'Cannot delete this field. There are documents linked to it. Please remove the documents associated with this field before attempting to delete it.',
            //     ], 400);

            // }else {
            //     DocumentType::where('id',$document_type_id)->update(['is_deleted'=> 1]);
            // }

        }

        return response()->json([
            'ApiName' => 'Onboarding Employee Configuration Setting Add',
            'status' => true,
            'message' => 'Configuration Setting Successfully.',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        //
    }

    public function getOnboardingConfigurationSetting(Request $request): JsonResponse
    {
        $configurationId = $request->id;

        if (empty($configurationId)) {
            return response()->json([
                'ApiName' => 'Get OnboardingConfigurationSetting list',
                'status' => false,
                'message' => 'Configuration ID is missing.',
                'data' => [],
            ], 400);
        }

        $data = EmployeeIdSetting::find($configurationId);

        if ($data) {
            $userProfileS3 = null;
            if (Auth::check() && Auth::user()->image) {
                $userProfileS3 = s3_getTempUrl(config('app.domain_name').'/'.Auth::user()->image);
            }

            return response()->json([
                'ApiName' => 'Get OnboardingConfigurationSetting list',
                'status' => true,
                'message' => 'Successfully retrieved the data.',
                'data' => $data,
                'user_profile_s3' => $userProfileS3,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Get OnboardingConfigurationSetting list',
                'status' => false,
                'message' => 'Data is not available.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'ApiName' => 'Get OnboardingConfigurationSetting list',
            'status' => true,
            'message' => 'Successfully retrieved the data.',
            'data' => $data,
        ], 200);
    }

    public function getOnboardingConfigurationSettingData(Request $request): JsonResponse
    {

        $configurationId = $request->id;

        $data = EmployeeIdSetting::with([
            'AdditionalInfoForEmployeeToGetStarted' => function ($query) {
                $query->where('is_deleted', 0);
            }, 'EmployeePersonalDetail' => function ($query) {
                $query->where('is_deleted', 0);
            }, 'DocumentToUpdate' => function ($query) {
                $query->where('is_deleted', 0);
            }, 'EmployeeAdminOnlyFields' => function ($query) {
                $query->where('is_deleted', 0);
            },
        ])->where('id', $configurationId)->get();

        if (! empty($data)) {
            $data['user_profile_s3'] = s3_getTempUrl(config('app.domain_name').'/'.Auth::user()->image);

            return response()->json([
                'ApiName' => 'Get OnboardingConfigurationSetting list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Get OnboardingConfigurationSetting list',
                'status' => false,
                'message' => 'Data is not available.',
                'data' => [],
            ], 200);
        }
    }

    public function onBoardingChangeStatus(Request $request): JsonResponse
    {
        $status = 0;
        if (isset($request['status_id']) && $request['status_id'] == 14) {
            return response()->json([
                'ApiName' => 'Change Status',
                'status' => false,
                'message' => 'You can not change manually user status to active.',
            ], 400);
        }

        $emp = OnboardingEmployees::find($request->onboardingEmployee_id);
        if ($emp) {
            // if(date('Y-m-d')> $emp->offer_expiry_date && ($emp->status_id!=1)){
            // $request['status_id']= 5;
            // }
            $emp->status_id = $request['status_id'];
            $emp->save();

            if ($request['status_id'] == 15) {
                $status = 'Admin Reject';
            }
            // update staus in hubspot
            $CrmData = Crms::where('id', 2)->where('status', 1)->first();
            $CrmSetting = CrmSetting::where('crm_id', 2)->first();
            if (! empty($CrmData) && ! empty($CrmSetting)) {
                $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                $val = json_decode($decreptedValue);
                $token = $val->api_key;
                if (! empty($emp->aveyo_hs_id) && $status != 0) {
                    $Hubspotdata['properties'] = ['status' => $status];
                    $this->update_hubspot_data($Hubspotdata, $token, $emp->aveyo_hs_id);
                }
            }

            return response()->json([
                'ApiName' => 'Change Status',
                'status' => true,
                'message' => 'Change Status Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Change Status',
                'status' => true,
                'message' => 'Bad Request.',
            ], 400);
        }
    }

    public function deleteEmployeePersonalDetail($id): JsonResponse
    {
        //  $id = $request->id;

        if (! null == $id) {

            $data = EmployeePersonalDetail::where('id', $id);
            $data->delete();

            return response()->json([
                'ApiName' => 'delete EmployeePersonalDetail',
                'status' => true,
                'message' => 'delete Successfully.',
                'data' => $id,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'delete EmployeePersonalDetail',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function deleteAdditionalInfo($id): JsonResponse
    {
        // $id = $request->id;

        if (! null == $id) {

            $data = AdditionalInfoForEmployeeToGetStarted::where('id', $id);
            $data->delete();

            return response()->json([
                'ApiName' => 'delete AdditionalInfoForEmployeeToGetStarted',
                'status' => true,
                'message' => 'delete Successfully.',
                'data' => $id,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'delete AdditionalInfoForEmployeeToGetStarted',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function deleteDocumentUpload($id): JsonResponse
    {
        // $id = $request->id;

        if (! null == $id) {

            $data = DocumentType::where('id', $id);
            $data->delete();

            return response()->json([
                'ApiName' => 'delete Document To Update',
                'status' => true,
                'message' => 'delete Successfully.',
                'data' => $id,
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'delete Document To Update',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function deleteIncompleteHiredForm($id): JsonResponse
    {

        $data = OnboardingEmployees::where('id', $id)
            ->where('status_id', '=', 8)
            ->first();
        if ($data == null) {
            return response()->json([
                'ApiName' => 'Delete an incomplete hire form',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        } else {
            $data->delete();

            return response()->json([
                'ApiName' => 'Delete an incomplete hire form',
                'status' => true,
                'message' => 'delete Successfully.',
                'data' => $id,
            ], 200);
        }
    }

    // // Code by Nikhil

    // public function create_employees($Hubspotdata, $token,$user_id){

    //     //$url = "https://api.hubapi.com/crm/v3/objects/contacts";
    //     $url = "https://api.hubapi.com/crm/v3/objects/sales";
    //     $Hubspotdata=json_encode($Hubspotdata);
    //     $headers=array(
    //         'accept: application/json' ,
    //         'content-type: application/json',
    //         'authorization: Bearer '.$token,
    //     );

    //     $curl_response = $this->curlRequestData($url,$Hubspotdata,$headers,'POST');

    //     $resp = json_decode($curl_response, true);

    //     if(count($resp) > 0){
    //             $hs_object_id = $resp['properties']['hs_object_id'];
    //             //$email = $resp['properties']['email'];

    //         $updateuser = User::where('id', $user_id)->first();
    //         if ($updateuser) {
    //             $updateuser->aveyo_hs_id = $hs_object_id;
    //             $updateuser->save();
    //         }
    //     }

    //  }

    //  function curlRequestData($url,$Hubspotdata,$headers,$method='POST'){

    //     $curl = curl_init();

    //     curl_setopt_array($curl, array(
    //         CURLOPT_URL =>  $url,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => $method,
    //         CURLOPT_POSTFIELDS => $Hubspotdata,
    //         CURLOPT_HTTPHEADER => $headers,

    //     ));

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     return $response;

    // }

    // // sales data get with uniq mobile number code start
    // public function get_sales($token,$user_id,$mobile){

    //     $url = "https://api.hubapi.com/crm/v3/objects/sales/search";
    //     // $Hubspotdata=json_encode($Hubspotdata);
    //     $headers=array(
    //         'accept: application/json' ,
    //         'content-type: application/json',
    //         'authorization: Bearer '.$token,
    //     );

    //     $filters[] = array(
    //         'propertyName'=> "phone",
    //         'operator'=> "EQ",
    //         'value'=> $mobile,
    //     );

    //     $data['filterGroups'][] =  array('filters'=> $filters);

    //     $data = json_encode($data);
    //     $curl_response = $this->curlRequestSalesData($url,$headers,$data,'POST');
    //     $resp =  json_decode($curl_response, true);
    //     if($resp['total']==0){
    //       $newData = $resp['results'];
    //     }else{
    //       $newData = $resp['results'][0]['id'];
    //     }
    //     return $newData;
    // }

    // function curlRequestSalesData($url,$headers,$data,$method='POST'){

    //     $curl = curl_init();

    //     curl_setopt_array($curl, array(
    //         CURLOPT_URL =>  $url,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => $method,
    //         CURLOPT_POSTFIELDS => $data,
    //         CURLOPT_HTTPHEADER => $headers,

    //     ));

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     return $response;

    // }
    // // sales data get with uniq mobile number code end

    // //update hubspot sales data start

    // public function update_employees($Hubspotdata, $token,$user_id,$aveyoid){

    //     //$url = "https://api.hubapi.com/crm/v3/objects/contacts";
    //     $url = "https://api.hubapi.com/crm/v3/objects/sales/$aveyoid";
    //     $Hubspotdata=json_encode($Hubspotdata);
    //     $headers=array(
    //         'accept: application/json' ,
    //         'content-type: application/json',
    //         'authorization: Bearer '.$token,
    //     );

    //     $curl_response = $this->curlRequestDataUpdate($url,$Hubspotdata,$headers,'PATCH');

    //     $resp = json_decode($curl_response, true);

    //     if(count($resp) > 0){
    //             $hs_object_id = $resp['properties']['hs_object_id'];
    //             //$email = $resp['properties']['email'];

    //         $updateuser = User::where('id', $user_id)->first();
    //         if ($updateuser) {
    //             $updateuser->aveyo_hs_id = $hs_object_id;
    //             $updateuser->update();
    //         }
    //     }

    //     }

    //     function curlRequestDataUpdate($url,$Hubspotdata,$headers,$method='PATCH'){

    //     $curl = curl_init();

    //     curl_setopt_array($curl, array(
    //         CURLOPT_URL =>  $url,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_ENCODING => '',
    //         CURLOPT_MAXREDIRS => 10,
    //         CURLOPT_TIMEOUT => 0,
    //         CURLOPT_FOLLOWLOCATION => true,
    //         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //         CURLOPT_CUSTOMREQUEST => $method,
    //         CURLOPT_POSTFIELDS => $Hubspotdata,
    //         CURLOPT_HTTPHEADER => $headers,

    //     ));

    //     $response = curl_exec($curl);
    //     curl_close($curl);
    //     return $response;

    // }
    //     //update hubspot sales data end

    // // End code by Nikhil

    public function deleteOnboardingEmployee($id): JsonResponse
    {

        $OnboardingEmployees = OnboardingEmployees::find($id);

        $user_id = $OnboardingEmployees->user_id;
        $lead_id = $OnboardingEmployees->lead_id;

        if (! $user_id || ($OnboardingEmployees->status_id == 8 && $OnboardingEmployees->is_new_contract == 1)) {

            // Start database transaction for data integrity
            DB::beginTransaction();

            try {
                // Delete main onboarding employee record
                $OnboardingEmployees->delete();

                // Delete onboarding-related tables
                EmployeeOnboardingDeduction::where('user_id', $id)->delete();
                OnboardingUserRedline::where('user_id', $id)->delete();
                OnboardingEmployeeLocation::where('user_id', $id)->delete();
                OnboardingEmployeeOverride::where('user_id', $id)->delete();
                OnboardingEmployeeLocations::where('user_id', $id)->delete();
                OnboardingEmployeeWages::where('user_id', $id)->delete();
                OnboardingEmployeeRedline::where('user_id', $id)->delete();
                OnboardingEmployeeUpfront::where('user_id', $id)->delete();
                OnboardingEmployeeWithheld::where('user_id', $id)->delete();
                OnboardingEmployeeAdditionalOverride::where('user_id', $id)->delete();
                OnboardingAdditionalEmails::where('onboarding_user_id', $id)->delete();

                // Delete tier range tables
                OnboardingUpfrontsTiersRange::where('user_id', $id)->delete();
                OnboardingCommissionTiersRange::where('user_id', $id)->delete();
                OnboardingDirectOverrideTiersRange::where('user_id', $id)->delete();
                OnboardingOfficeOverrideTiersRange::where('user_id', $id)->delete();
                OnboardingOverrideOfficeTiersRange::where('user_id', $id)->delete();
                OnboardingIndirectOverrideTiersRange::where('user_id', $id)->delete();

                // Delete associated lead if exists
                if ($lead_id) {
                    Lead::where('id', $lead_id)->delete();
                }

                // Commit transaction
                DB::commit();

                return response()->json([
                    'ApiName' => 'delete Onboarding Employee',
                    'status' => true,
                    'message' => 'Onboarding employee and all related data deleted successfully.',
                ], 200);

            } catch (\Exception $e) {
                // Rollback transaction on error
                DB::rollback();

                Log::error('Error deleting onboarding employee: '.$e->getMessage(), [
                    'onboarding_employee_id' => $id,
                    'user_id' => $user_id,
                    'lead_id' => $lead_id,
                ]);

                return response()->json([
                    'ApiName' => 'delete Onboarding Employee',
                    'status' => false,
                    'message' => 'Error occurred while deleting onboarding employee. Please try again.',
                ], 500);
            }

        } else {

            return response()->json([
                'ApiName' => 'delete Onboarding Employee',
                'status' => true,
                'message' => 'Can\'t delete this onboarding employee because they are already hired.',
            ], 400);

        }

    }
    // public function exportOnboarding(Request $request)
    // {
    //     $status = $request->status_filter;
    //     $position = $request->position_filter;
    //     $manager = $request->manager_filter;
    //     $officeId = $request->office_id;
    //     $filter = $request->filter;

    //         $file_name = 'onboarding_' . date('Y_m_d_H_i_s') . '.csv';
    //         if($status != '' || $position != '' || $manager != '')
    //         {
    //             return Excel::download(new OnboardingExport($status, $position, $manager, $officeId,$filter),$file_name);
    //         }
    //         else{
    //             return Excel::download(new OnboardingExport($status, $position, $manager, $officeId,$filter), $file_name);
    //         }
    // }

    public function exportOnboarding(Request $request)
    {
        $user = [];

        $all_paid = true;
        $file_name = 'onboarding_'.date('Y_m_d_H_i_s').'.xlsx';

        Excel::store(new OnboardingExport($request), 'exports/reports/onboarding/'.$file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);

        // Get the URL for the stored file
        $url = getStoragePath('exports/reports/onboarding/'.$file_name);
        // $url = getExportBaseUrl().'storage/exports/reports/onboarding/' . $file_name;
        $url = str_replace('public/public', 'public', $url);

        // Return the URL in the API response
        return response()->json(['url' => $url]);

        return Excel::download(new OnboardingExport($request), $file_name);
    }

    public function getWorkerFilesListFromEveree(Request $request): JsonResponse
    {

        $workerId = $request->workerId;
        $page = isset($request->page) ? $request->page : 0;
        $size = isset($request->size) ? $request->size : 20;

        if (empty($workerId)) {
            return response()->json([
                'ApiName' => 'getWorkerFilesListFromEveree',
                'status' => false,
                'message' => 'Worker ID is required.',
            ], 200);
        }

        $response = $this->listWorkerFiles($workerId, $page, $size);

        return response()->json([
            'ApiName' => 'getWorkerFilesListFromEveree',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
        ], 200);
    }

    public function deleteExpiredOnboarding(): JsonResponse
    {
        $expiredOnboardingData = OnboardingEmployees::where('status_id', 5)->get();
        if ($expiredOnboardingData->isNotEmpty()) {
            OnboardingEmployees::where('status_id', 5)->delete();

            return response()->json([
                'ApiName' => 'Delete All Expired Onboarding data API',
                'status' => true,
                'message' => 'All Expired Onboarding data Deleted Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Delete All Expired Onboarding data API',
                'status' => false,
                'message' => 'No Expired Onboarding data found',
            ], 404);
        }

    }

    public function deleteRejectedOnboarding(): JsonResponse
    {
        $expiredOnboardingData = OnboardingEmployees::whereIn('status_id', [11, 15])->get();
        if ($expiredOnboardingData->isNotEmpty()) {
            OnboardingEmployees::whereIn('status_id', [11, 15])->delete();

            return response()->json([
                'ApiName' => 'Delete All Rejected Onboarding data API',
                'status' => true,
                'message' => 'All Rejected Onboarding data Deleted Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Delete All Rejected Onboarding data API',
                'status' => false,
                'message' => 'No Rejected Onboarding data found',
            ], 404);
        }

    }

    public function sendRequestHiring(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        try {
            $description = $request->description;
            if ($description) {
                $emails = ['nikhil@sequifi.com'];
                foreach ($emails as $email) {
                    $data['email'] = $email;
                    $data['subject'] = 'Welcome To Sequifi';
                    $data['template'] = $description;
                    $this->sendEmailNotification($data);
                }

                return response()->json([
                    'ApiName' => 'Send email hiring request',
                    'status' => true,
                    'message' => 'Send email Successfully.',
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'Send email hiring request',
                    'status' => false,
                    'message' => 'Description Not Found',
                    // 'data' => $data,
                ], 400);
            }
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
    }

    // Send offer review
    public function offer_review_to_onboarding_employee(Request $request)
    {
        $ApiName = 'offer_review_to_onboarding_employee';
        $status_code = 400;
        $status = false;
        $message = 'User not found invailid user id';
        $user_data = [];
        $response_array = [];
        $response = [];

        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required|integer',
                // 'sub_position_id' => 'required|integer'
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        try {

            // DB::beginTransaction();

            $onboarding_user_id = $request->user_id;
            $category_id = 1;

            $Onboarding_Employees_query = OnboardingEmployees::where('id', $onboarding_user_id);
            $Onboarding_Employees_count = $Onboarding_Employees_query->count();
            $Onboarding_Employee_data_row = $Onboarding_Employees_query->first();
            // return $Onboarding_Employee_data_row;
            if ($Onboarding_Employees_count != 0) {

                if ($Onboarding_Employee_data_row->status_id == 5) {
                    $offerExpiryDate = Carbon::parse($Onboarding_Employee_data_row->offer_expiry_date);
                    $tomorrow = Carbon::tomorrow();

                    if ($offerExpiryDate->lessThan($tomorrow)) {
                        $message = 'Offer Expiry Date should be in the future';
                    }

                    return response()->json([
                        'ApiName' => $ApiName,
                        'status' => $status,
                        'message' => $message,
                    ], $status_code);

                }

                $update_OnboardingEmployees = OnboardingEmployees::find($onboarding_user_id);
                $update_OnboardingEmployees->status_id = 17;
                $update_OnboardingEmployees->save();

                /* $sendOfferLetterStatus = HiringStatus::where('id', 4)->first();
                $onboardingEmployeesStatus =  OnboardingEmployees::find($users_row['id']);
                if($sendOfferLetterStatus!=null && $onboardingEmployeesStatus!=null){
                    $onboardingEmployeesStatus->status_id = $sendOfferLetterStatus->id ?? $onboardingEmployeesStatus->status_id;
                    $onboardingEmployeesStatus->save();
                } */

                return response()->json([
                    'ApiName' => $ApiName,
                    'status' => true,
                    'message' => 'offer review to onboarding employee',
                ], 200);

            }

            // DB::commit();
        } catch (Exception $error) {
            Log::debug($error);
            $message = 'something went wrong!!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();
            $Trace = $error->getTraceAsString();
            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            // DB::rollBack();
            return response()->json(['error' => $error, 'message' => $message, 'errorDetail' => $errorDetail], 400);
        }
    }

    // offer review approved or reject
    public function offer_review_approved_or_reject(Request $request)
    {

        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required|integer',
                'status' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        try {
            // $first_name = auth()->user()->first_name;
            $onboarding_user_id = $request->user_id;
            $Onboarding_Employees_query = OnboardingEmployees::where('id', $onboarding_user_id);
            $Onboarding_Employees_count = $Onboarding_Employees_query->count();
            $Onboarding_Employee_data_row = $Onboarding_Employees_query->first();
            // return $Onboarding_Employee_data_row;
            if ($Onboarding_Employees_count != 0) {

                if ($Onboarding_Employee_data_row->status_id == 5) {
                    $offerExpiryDate = Carbon::parse($Onboarding_Employee_data_row->offer_expiry_date);
                    $tomorrow = Carbon::tomorrow();

                    if ($offerExpiryDate->lessThan($tomorrow)) {
                        $message = 'Offer Expiry Date should be in the future';
                    }

                    return response()->json([
                        'ApiName' => 'offer_review_approved_or_reject',
                        'status' => false,
                        'message' => $message,
                    ], 400);

                }

                if (! empty($request->status) && $request->status == 'approved') {
                    $status = 18;
                    $message = 'offer review approved';
                    $update_OnboardingEmployees = OnboardingEmployees::find($onboarding_user_id);
                    $update_OnboardingEmployees->status_id = $status;
                    $update_OnboardingEmployees->offer_review_uid = auth()->user()->id;
                    $update_OnboardingEmployees->save();

                } else {

                    $status = 19;
                    $message = 'offer review reject';
                    $update_OnboardingEmployees = OnboardingEmployees::find($onboarding_user_id);
                    $hired_by_uid = $update_OnboardingEmployees->hired_by_uid;
                    $user = User::select('id', 'email')->where(['id' => $hired_by_uid])->first();
                    $description = $request->description ?? '';
                    $data['email'] = $user->email;
                    $data['subject'] = 'User Offer Reject';
                    $data['template'] = $description;
                    // $data['name'] = $update_OnboardingEmployees->first_name.' '.$update_OnboardingEmployees->last_name;
                    // $data['template'] = view('mail.onboarding', compact('data') );
                    // $data['template'] = '<p>Ashutosh</p>';
                    $res = $this->sendEmailNotification($data);

                    $update_OnboardingEmployees->status_id = $status;
                    $update_OnboardingEmployees->offer_review_uid = auth()->user()->id;
                    $update_OnboardingEmployees->save();

                    $auth_user_data = Auth::user();
                    $comment_by_id = $auth_user_data->id;
                    $comment = isset($request->description) ? $request->description : null;

                    $sequi_docs_document_comments = new NewSequiDocsDocumentComment;
                    $sequi_docs_document_comments->comment = $comment;
                    $sequi_docs_document_comments->user_id_from = 'onboarding_employees';
                    $sequi_docs_document_comments->comment_user_id_from = 'users';
                    $sequi_docs_document_comments->comment_by_id = $comment_by_id;
                    $sequi_docs_document_comments->document_send_to_user_id = $onboarding_user_id;
                    $is_saved = $sequi_docs_document_comments->save();

                }

                /* $sendOfferLetterStatus = HiringStatus::where('id', 4)->first();
                $onboardingEmployeesStatus =  OnboardingEmployees::find($users_row['id']);
                if($sendOfferLetterStatus!=null && $onboardingEmployeesStatus!=null){
                    $onboardingEmployeesStatus->status_id = $sendOfferLetterStatus->id ?? $onboardingEmployeesStatus->status_id;
                    $onboardingEmployeesStatus->save();
                } */

                return response()->json([
                    'ApiName' => 'offer_review_approved_or_reject',
                    'status' => true,
                    'message' => $message,
                ], 200);

            }

            // DB::commit();
        } catch (Exception $error) {
            Log::debug($error);
            $message = 'something went wrong!!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();
            $Trace = $error->getTraceAsString();
            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            // DB::rollBack();
            return response()->json(['error' => $error, 'message' => $message, 'errorDetail' => $errorDetail], 400);
        }
    }

    // special review offer reject
    public function special_review_offer_reject(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required|integer',
                'description' => 'required',
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        try {

            $onboarding_user_id = $request->user_id;
            $Onboarding_Employees_query = OnboardingEmployees::where('id', $onboarding_user_id);
            $Onboarding_Employees_count = $Onboarding_Employees_query->count();
            $Onboarding_Employee_data_row = $Onboarding_Employees_query->first();
            // return $Onboarding_Employee_data_row;
            if ($Onboarding_Employees_count != 0) {

                if ($Onboarding_Employee_data_row->status_id == 5) {
                    $offerExpiryDate = Carbon::parse($Onboarding_Employee_data_row->offer_expiry_date);
                    $tomorrow = Carbon::tomorrow();

                    if ($offerExpiryDate->lessThan($tomorrow)) {
                        $message = 'Offer Expiry Date should be in the future';
                    }

                    return response()->json([
                        'ApiName' => 'offer_review_approved_or_reject',
                        'status' => false,
                        'message' => $message,
                    ], 400);

                }

                $status = 20;
                $message = 'special review reject';
                $update_OnboardingEmployees = OnboardingEmployees::find($onboarding_user_id);
                $offer_review_uid = $update_OnboardingEmployees->offer_review_uid;
                $user = User::select('id', 'email')->where(['id' => $offer_review_uid])->first();
                $description = $request->description ?? '';
                $data['email'] = $user->email;
                $data['subject'] = 'User Offer Reject';
                $data['template'] = $description;
                // $data['name'] = $update_OnboardingEmployees->first_name.' '.$update_OnboardingEmployees->last_name;
                // $data['template'] = view('mail.onboarding', compact('data') );
                // $data['template'] = '<p>Ashutosh</p>';
                $res = $this->sendEmailNotification($data);

                $update_OnboardingEmployees->status_id = $status;
                $update_OnboardingEmployees->save();

                $auth_user_data = Auth::user();
                $comment_by_id = $auth_user_data->id;
                $comment = isset($request->description) ? $request->description : null;

                $sequi_docs_document_comments = new NewSequiDocsDocumentComment;
                $sequi_docs_document_comments->comment = $comment;
                $sequi_docs_document_comments->user_id_from = 'onboarding_employees';
                $sequi_docs_document_comments->comment_user_id_from = 'users';
                $sequi_docs_document_comments->comment_by_id = $comment_by_id;
                $sequi_docs_document_comments->document_send_to_user_id = $onboarding_user_id;
                $is_saved = $sequi_docs_document_comments->save();

                return response()->json([
                    'ApiName' => 'special_review_offer_reject',
                    'status' => true,
                    'message' => $message,
                ], 200);

            }

            // DB::commit();
        } catch (Exception $error) {
            Log::debug($error);
            $message = 'something went wrong!!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();
            $Trace = $error->getTraceAsString();
            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            // DB::rollBack();
            return response()->json(['error' => $error, 'message' => $message, 'errorDetail' => $errorDetail], 400);
        }
    }

    public function employee_additional_personal_details(Request $request): JsonResponse
    {

        // $configurationId = $request->id;
        $configurationId = 1;
        $employeeFields = $request->employee_personal_details ?? [];

        // Mark all old records as deleted
        EmployeePersonalDetail::query()->update(['is_deleted' => 1]);

        if (! empty($employeeFields)) {

            $newRecords = [];

            foreach ($employeeFields as $employeeField) {
                if (! empty($employeeField)) {
                    $attribute = isset($employeeField['field_type']) && $employeeField['field_type'] === 'dropdown'
                        ? json_encode($employeeField['attribute_option'], true)
                        : null;

                    if (isset($employeeField['id'])) {

                        EmployeePersonalDetail::where('id', $employeeField['id'])->update([
                            'configuration_id' => $configurationId,
                            'field_name' => $employeeField['field_name'] ?? '',
                            'field_type' => $employeeField['field_type'] ?? '',
                            'field_required' => $employeeField['field_required'] ?? '',
                            'attribute_option' => $attribute,
                            'is_deleted' => $employeeField['is_deleted'] ?? 0,
                        ]);

                    } else {

                        $epd = new EmployeePersonalDetail;
                        $epd->configuration_id = $configurationId;
                        $epd->field_name = $employeeField['field_name'] ?? '';
                        $epd->field_type = $employeeField['field_type'] ?? '';
                        $epd->field_required = $employeeField['field_required'] ?? '';
                        $epd->attribute_option = $attribute;
                        $epd->save();

                    }

                }
            }

        }

        return response()->json([
            'ApiName' => 'employee_additional_personal_details',
            'status' => true,
            'message' => 'Configuration Setting Successfully.',
        ], 200);

    }

    public function getWorkerTaxListFromEveree($userId): JsonResponse
    {

        $user = User::withoutGlobalScopes()->find($userId);
        if (! isset($user->everee_workerId) || empty($user->everee_workerId)) {
            return response()->json([
                'ApiName' => 'Worker Tax Documents From Everee',
                'status' => false,
                'message' => 'User profile is not complete or does not exist on SequiPay!',
            ], 400);
        }

        $response = $this->listWorkerTaxFiles($userId);

        if (isset($response['errorCode']) && ! empty($response['errorCode'])) {
            return response()->json([
                'ApiName' => 'Worker Tax Documents From Everee',
                'status' => false,
                'message' => $response['errorMessage'],
            ], $response['errorCode']);
        }

        return response()->json([
            'ApiName' => 'Worker Tax Documents From Everee',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
        ], 200);
    }

    /**
     * Send onboarding completion email to Onyx team
     *
     * @param  OnboardingEmployees  $employee
     */
    private function sendOnyxOnboardingCompletionEmail($userModel, int $userId): void
    {
        try {
            // Get OnboardingEmployees record with related data using user_id
            $employeeData = OnboardingEmployees::with(['positionDetail', 'office', 'state'])
                ->where('user_id', $userId)
                ->first();

            if (! $employeeData) {
                Log::error('OnboardingEmployee record not found for Onyx onboarding completion email', ['user_id' => $userId]);

                return;
            }

            // Merge User model data with OnboardingEmployees data for complete information
            $employeeData->email = $userModel->email;
            $employeeData->mobile_no = $userModel->mobile_no;
            $employeeData->employee_id = $userModel->employee_id;
            $employeeData->home_address = $userModel->home_address;

            // Get products associated with the employee (smart text list)
            $products = [];
            try {
                $productData = OnboardingEmployees::getProductIds($userId);
                if ($productData && count($productData) > 0) {
                    $products = $productData->pluck('name')->toArray();
                }
            } catch (\Exception $e) {
                Log::warning('Could not retrieve products for employee', [
                    'employee_id' => $employeeData->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }

            // If no products found, add a default message
            if (empty($products)) {
                $products = ['No specific products assigned - General Sales Representative'];
            }

            // Get license documents (front and back) for the employee
            $licenseDocuments = [];
            try {
                Log::info('Searching for documents for onboarding email', [
                    'searching_user_id' => $userId,
                    'employee_data_id' => $employeeData->id,
                ]);

                $userDocuments = NewSequiDocsDocument::where([
                    'user_id' => $userId,
                    'document_uploaded_type' => 'manual_doc',
                    'is_post_hiring_document' => '0',
                    'is_active' => '1',
                ])
                    ->with(['upload_document_file', 'upload_document_types'])
                    ->orderBy('document_uploaded_type', 'DESC')
                    ->get();

                Log::info('Documents found for processing', [
                    'user_id' => $userId,
                    'documents_count' => count($userDocuments),
                ]);

                // Process all documents and get S3 signed URLs
                foreach ($userDocuments as $document) {
                    $documentFiles = [];
                    foreach ($document->upload_document_file as $file) {
                        // Get S3 signed URL for the file
                        $s3Url = $this->getS3SignedUrl($file->document_file_path);

                        $documentFiles[] = [
                            'file_path' => $file->document_file_path,
                            's3_signed_url' => $s3Url,
                            'version' => $file->file_version,
                        ];
                    }

                    $licenseDocuments[] = [
                        'id' => $document->id,
                        'description' => $document->description,
                        'type_name' => $document->upload_document_types->name ?? 'Document',
                        'files' => $documentFiles,
                        'envelope_id' => $document->envelope_id,
                    ];

                    Log::info('Document processed for onboarding email', [
                        'document_id' => $document->id,
                        'description' => $document->description,
                        'files_count' => count($documentFiles),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error retrieving documents for employee', [
                    'employee_id' => $employeeData->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            Log::info('Final documents result for onboarding email', [
                'user_id' => $userId,
                'documents_count' => count($licenseDocuments),
                'documents' => array_map(function ($doc) {
                    return [
                        'id' => $doc['id'],
                        'description' => $doc['description'],
                        'files_count' => count($doc['files']),
                    ];
                }, $licenseDocuments),
            ]);

            // Onyx team email addresses
            $onyxTeamEmails = config('notification-emails.onyx-onboarding-complete-email-address');

            // Send one email with primary recipient and others as CC
            $primaryEmail = $onyxTeamEmails[0]; // First email as primary recipient
            $ccEmails = array_slice($onyxTeamEmails, 1); // Remaining emails as CC

            $emailData = [
                'email' => $primaryEmail,
                'subject' => 'Onboarding Completed - '.$employeeData->first_name.' '.$employeeData->last_name.' Ready for Action',
                'template' => view('mail.onyx_onboarding_completion', [
                    'employee' => $employeeData,
                    'products' => $products,
                    'licenseDocuments' => $licenseDocuments,
                ]),
            ];

            // Add CC emails if there are any
            if (! empty($ccEmails)) {
                $emailData['cc_emails_arr'] = $ccEmails;
            }

            // Send the email
            $result = $this->sendEmailNotification($emailData);

            // Log the result
            if ($result) {
                Log::info('Onyx onboarding completion email sent successfully', [
                    'employee_id' => $employeeData->id,
                    'employee_name' => $employeeData->first_name.' '.$employeeData->last_name,
                    'primary_recipient' => $primaryEmail,
                    'cc_recipients' => implode(', ', $ccEmails),
                    'products_count' => count($products),
                    'license_documents_count' => count($licenseDocuments),
                ]);
            } else {
                Log::error('Failed to send Onyx onboarding completion email', [
                    'employee_id' => $employeeData->id,
                    'primary_recipient' => $primaryEmail,
                    'cc_recipients' => implode(', ', $ccEmails),
                ]);
            }

            // Log completion notification
            Log::info('Onyx onboarding completion notification process completed', [
                'employee_id' => $employeeData->id,
                'employee_name' => $employeeData->first_name.' '.$employeeData->last_name,
                'recipients_count' => count($onyxTeamEmails),
                'products' => $products,
                'license_documents_count' => count($licenseDocuments),
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending Onyx onboarding completion email', [
                'employee_id' => $employeeData->id ?? 'unknown',
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get S3 signed URL for a document file path
     */
    private function getS3SignedUrl(string $filePath): ?string
    {
        try {
            // Use the helper functions directly instead of HTTP request
            $documentPath = config('app.domain_name').'/'.$filePath;
            $check = checkIfS3FileExists($documentPath, 'private');

            if ($check['status']) {
                // Generate signed URL with 60 minutes expiration
                $signedUrl = s3_getTempUrl($documentPath, 'private', 60);

                Log::info('S3 signed URL generated successfully', [
                    'file_path' => $filePath,
                    'document_path' => $documentPath,
                ]);

                return $signedUrl;
            } else {
                Log::warning('S3 file does not exist', [
                    'file_path' => $filePath,
                    'document_path' => $documentPath,
                    'check_result' => $check,
                ]);

                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error getting S3 signed URL', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Configure employee admin only fields globally
     *
     * This function manages the configuration of admin-only fields that are displayed
     * on employee and onboarding employee pages. It handles both creation and updates
     * of field configurations including field types, validation rules, and permissions.
     *
     * @param  Request  $request  The HTTP request containing field configuration data
     * @return \Illuminate\Http\JsonResponse JSON response with operation status
     *
     * @throws \Exception When database operations fail
     *
     * Request Structure:
     * {
     *   "employee_admin_only_fields": [
     *     {
     *       "id": "optional_existing_field_id",
     *       "field_name": "Field Display Name",
     *       "field_type": "text|dropdown|textarea|checkbox|radio|date|number",
     *       "field_required": "1|0",
     *       "attribute_option": ["option1", "option2"], // for dropdown/radio fields
     *       "field_permission": "1|0", // field visibility permission
     *       "field_data_entry": "1|0", // field editability permission
     *       "is_deleted": "0|1" // soft delete flag
     *     }
     *   ]
     * }
     */
    public function employeeAdminOnlyFields(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'employee_admin_only_fields' => 'nullable|array',
                'employee_admin_only_fields.*.field_name' => 'required_with:employee_admin_only_fields|string|max:255',
                'employee_admin_only_fields.*.field_type' => 'required_with:employee_admin_only_fields|string|in:text,dropdown,textarea,checkbox,radio,date,number,email,phone number,url,select',
                'employee_admin_only_fields.*.field_required' => 'required_with:employee_admin_only_fields|string|max:255',
                'employee_admin_only_fields.*.attribute_option' => 'nullable|array|required_if:employee_admin_only_fields.*.field_type,dropdown,radio,select',
                'employee_admin_only_fields.*.field_permission' => 'required_with:employee_admin_only_fields|integer',
                'employee_admin_only_fields.*.field_data_entry' => 'required_with:employee_admin_only_fields|integer',
                'employee_admin_only_fields.*.is_deleted' => 'nullable|boolean',
                'employee_admin_only_fields.*.id' => 'nullable|integer|exists:employee_admin_only_fields,id',
            ], [
                'employee_admin_only_fields.*.field_name.required_with' => 'Field name is required for each field configuration.',
                'employee_admin_only_fields.*.field_type.required_with' => 'Field type is required for each field configuration.',
                'employee_admin_only_fields.*.field_type.in' => 'Field type must be one of: text, dropdown, textarea, checkbox, radio, date, number, email, phone, url, select.',
                'employee_admin_only_fields.*.attribute_option.required_if' => 'Attribute options are required for dropdown, radio, and select field types.',
                'employee_admin_only_fields.*.id.exists' => 'The specified field ID does not exist in the database.',
            ]);

            // Check if validation fails
            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'employee_admin_only_fields',
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'data' => null,
                ], 422);
            }

            // Get employee admin fields from request or default to empty array
            $employeeAdminFields = $request->employee_admin_only_fields ?? [];
            $configurationId = 1; // Default configuration ID

            // Begin database transaction for data consistency
            DB::beginTransaction();

            try {
                // Soft delete all existing records by marking them as deleted
                // This approach preserves data integrity while allowing for complete field reconfiguration
                EmployeeAdminOnlyFields::query()->update(['is_deleted' => 1]);

                // Process each field configuration if provided
                if (! empty($employeeAdminFields)) {
                    foreach ($employeeAdminFields as $employeeField) {
                        // Skip empty field configurations
                        if (empty($employeeField)) {
                            continue;
                        }

                        // Prepare attribute options for dropdown/radio/select fields
                        $attribute = null;
                        if (isset($employeeField['field_type']) &&
                            in_array($employeeField['field_type'], ['dropdown', 'radio', 'select']) &&
                            isset($employeeField['attribute_option'])) {

                            // Validate and sanitize attribute options
                            $attributeOptions = array_filter($employeeField['attribute_option'], function ($option) {
                                return ! empty(trim($option));
                            });

                            if (! empty($attributeOptions)) {
                                $attribute = json_encode(array_values($attributeOptions), JSON_UNESCAPED_UNICODE);
                            }
                        }

                        // Check if this is an update to an existing field
                        if (isset($employeeField['id']) && ! empty($employeeField['id'])) {
                            // Update existing field configuration
                            $updateResult = EmployeeAdminOnlyFields::where('id', $employeeField['id'])
                                ->update([
                                    'field_name' => trim($employeeField['field_name'] ?? ''),
                                    'field_type' => $employeeField['field_type'] ?? 'text',
                                    'field_required' => $employeeField['field_required'] ?? false,
                                    'attribute_option' => $attribute,
                                    'field_permission' => $employeeField['field_permission'] ?? true,
                                    'field_data_entry' => $employeeField['field_data_entry'] ?? true,
                                    'is_deleted' => $employeeField['is_deleted'] ?? false,
                                    'updated_at' => now(),
                                ]);

                            // Log the update operation
                            if ($updateResult) {
                                Log::info('Employee admin only field updated', [
                                    'field_id' => $employeeField['id'],
                                    'field_name' => $employeeField['field_name'],
                                    'updated_by' => auth()->id() ?? 'system',
                                ]);
                            }

                        } else {
                            // Create new field configuration
                            $eaof = new EmployeeAdminOnlyFields;
                            $eaof->field_name = trim($employeeField['field_name'] ?? '');
                            $eaof->field_type = $employeeField['field_type'] ?? 'text';
                            $eaof->field_required = $employeeField['field_required'] ?? false;
                            $eaof->attribute_option = $attribute;
                            $eaof->field_permission = $employeeField['field_permission'] ?? true;
                            $eaof->field_data_entry = $employeeField['field_data_entry'] ?? true;
                            $eaof->configuration_id = $configurationId;
                            $eaof->is_deleted = false;
                            $eaof->save();

                            // Log the creation operation
                            Log::info('Employee admin only field created', [
                                'field_id' => $eaof->id,
                                'field_name' => $eaof->field_name,
                                'created_by' => auth()->id() ?? 'system',
                            ]);
                        }
                    }
                }

                // Commit the transaction
                DB::commit();

                // Return success response
                return response()->json([
                    'ApiName' => 'employee_admin_only_fields',
                    'status' => true,
                    'message' => 'Admin Only Fields Configuration Updated Successfully.',
                    'data' => [
                        'total_fields_processed' => count($employeeAdminFields),
                        'configuration_id' => $configurationId,
                        'timestamp' => now()->toISOString(),
                    ],
                ], 200);

            } catch (\Exception $dbError) {
                // Rollback transaction on database error
                DB::rollBack();

                Log::error('Database error in employeeAdminOnlyFields', [
                    'error' => $dbError->getMessage(),
                    'file' => $dbError->getFile(),
                    'line' => $dbError->getLine(),
                ]);

                throw $dbError;
            }

        } catch (\Exception $error) {
            // Log the error for debugging
            Log::error('Error in employeeAdminOnlyFields function', [
                'error_message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'code' => $error->getCode(),
                'trace' => $error->getTraceAsString(),
            ]);

            // Return error response
            return response()->json([
                'ApiName' => 'employee_admin_only_fields',
                'status' => false,
                'message' => 'Something went wrong while processing the request.',
                'errorDetail' => [
                    'error_message' => $error->getMessage(),
                    'File' => $error->getFile(),
                    'Line' => $error->getLine(),
                    'Code' => $error->getCode(),
                ],
            ], 500);
        }
    }

    /**
     * Update employee admin only fields for either User or OnboardingEmployee based on type parameter
     *
     * @param  Request  $request  - Contains type, entity_id, and employee_admin_only_fields
     */
    public function updateEmployeeAdminOnlyFields(Request $request): JsonResponse
    {
        try {
            $type = $request->type; // 'user' or 'onboarding_employee'
            $entityId = $request->entity_id;
            $adminOnlyFields = $request->employee_admin_only_fields;

            // Validate required parameters
            if (! $type || ! in_array($type, ['user', 'onboarding_employee'])) {
                return response()->json([
                    'ApiName' => 'update_employee_admin_only_fields',
                    'status' => false,
                    'message' => 'Type parameter is required and must be either "user" or "onboarding_employee".',
                ], 400);
            }

            if (! $entityId) {
                $idFieldName = $type === 'user' ? 'User ID' : 'Onboarding employee ID';

                return response()->json([
                    'ApiName' => 'update_employee_admin_only_fields',
                    'status' => false,
                    'message' => $idFieldName.' is required.',
                ], 400);
            }

            // Find the appropriate model based on type
            if ($type === 'user') {
                $entity = User::find($entityId);
                $entityName = 'User';
                $idKey = 'user_id';
            } else {
                $entity = OnboardingEmployees::find($entityId);
                $entityName = 'Onboarding employee';
                $idKey = 'onboarding_employee_id';
            }

            if (! $entity) {
                return response()->json([
                    'ApiName' => 'update_employee_admin_only_fields',
                    'status' => false,
                    'message' => $entityName.' not found.',
                ], 404);
            }

            // Convert to JSON if it's an array, otherwise store as is
            $adminOnlyFieldsData = is_array($adminOnlyFields) ? json_encode($adminOnlyFields) : $adminOnlyFields;

            $entity->employee_admin_only_fields = $adminOnlyFieldsData;
            $entity->save();

            return response()->json([
                'ApiName' => 'update_employee_admin_only_fields',
                'status' => true,
                'message' => ucfirst($entityName).' admin only fields updated successfully.',
                'data' => [
                    'type' => $type,
                    $idKey => $entityId,
                    'employee_admin_only_fields' => json_decode($entity->employee_admin_only_fields),
                ],
            ], 200);

        } catch (\Exception $error) {
            $message = 'Something went wrong!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();

            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            return response()->json([
                'ApiName' => 'update_employee_admin_only_fields',
                'status' => false,
                'message' => $message,
                'errorDetail' => $errorDetail,
            ], 500);
        }
    }

    /**
     * Combined function: Migrate table data and merge employee fields with no duplicate processing
     * 1. First performs table migration from additional_info_for_employee_to_get_started to employee_personal_detail
     * 2. Then merges user column data from additional_info_for_employee_to_get_started to employee_personal_detail column
     *
     * @param  Request  $request  - Optional clear_source flag and batch_size
     */
    public function mergeAllEmployeeFieldsReversed(Request $request): JsonResponse
    {
        try {
            $clearSource = $request->get('clear_source', true); // Default to true
            $batchSize = $request->get('batch_size', 500); // Process in batches to prevent memory issues

            // STEP 1: TABLE MIGRATION - Migrate records from additional_info_for_employee_to_get_started to employee_personal_detail
            $additionalInfoRecords = AdditionalInfoForEmployeeToGetStarted::where('is_deleted', 0)->get();

            $tableMigrationResults = [
                'source_records_found' => $additionalInfoRecords->count(),
                'migrated' => 0,
                'skipped' => 0,
                'errors' => [],
                'id_mappings' => [], // Track old_id => new_id mappings
            ];

            // Perform table migration with duplicate prevention
            foreach ($additionalInfoRecords as $sourceRecord) {
                try {
                    // Check if record already exists in employee_personal_detail (no duplicate processing)
                    $existingRecord = EmployeePersonalDetail::where('configuration_id', $sourceRecord->configuration_id)
                        ->where('field_name', $sourceRecord->field_name)
                        ->where('field_type', $sourceRecord->field_type)
                        ->where('is_deleted', 0)
                        ->first();

                    if ($existingRecord) {
                        $tableMigrationResults['skipped']++;
                        $tableMigrationResults['id_mappings'][$sourceRecord->id] = $existingRecord->id;

                        continue;
                    }

                    // Create new record in employee_personal_detail
                    $newRecord = EmployeePersonalDetail::create([
                        'configuration_id' => $sourceRecord->configuration_id,
                        'field_name' => $sourceRecord->field_name,
                        'field_type' => $sourceRecord->field_type,
                        'field_required' => $sourceRecord->field_required,
                        'attribute_option' => $sourceRecord->attribute_option,
                        'height_value' => null, // This field doesn't exist in source table
                        'is_deleted' => 0,
                    ]);

                    $tableMigrationResults['migrated']++;
                    $tableMigrationResults['id_mappings'][$sourceRecord->id] = $newRecord->id;

                } catch (\Exception $recordError) {
                    $tableMigrationResults['errors'][] = [
                        'source_record_id' => $sourceRecord->id,
                        'field_name' => $sourceRecord->field_name,
                        'error' => $recordError->getMessage(),
                    ];
                }
            }

            // STEP 2: BUILD ID MAPPING from table migration results and existing records
            $personalDetailRecords = EmployeePersonalDetail::where('is_deleted', 0)->get();
            $idMapping = $tableMigrationResults['id_mappings']; // Use mappings from migration

            // Add existing mappings for any records that weren't migrated
            foreach ($additionalInfoRecords as $additionalRecord) {
                if (! isset($idMapping[$additionalRecord->id])) {
                    $matchingPersonalRecord = $personalDetailRecords->where('field_name', $additionalRecord->field_name)
                        ->where('configuration_id', $additionalRecord->configuration_id)
                        ->first();
                    if ($matchingPersonalRecord) {
                        $idMapping[$additionalRecord->id] = $matchingPersonalRecord->id;
                    }
                }
            }

            // Get all users who have additional_info_for_employee_to_get_started data
            $totalUsers = User::whereNotNull('additional_info_for_employee_to_get_started')
                ->where('additional_info_for_employee_to_get_started', '!=', '')
                ->where('additional_info_for_employee_to_get_started', '!=', 'null')
                ->count();

            // STEP 3: USER DATA MERGING - Merge user column data
            if ($totalUsers === 0) {
                // Return table migration results even if no user data to merge
                return response()->json([
                    'ApiName' => 'merge_all_employee_fields_reversed',
                    'status' => true,
                    'message' => 'Table migration completed! No users found with additional_info_for_employee_to_get_started data to merge.',
                    'data' => [
                        'table_migration' => $tableMigrationResults,
                        'user_data_migration' => [
                            'total_users_found' => 0,
                            'processed' => 0,
                            'merged' => 0,
                            'skipped' => 0,
                            'errors' => [],
                        ],
                    ],
                ], 200);
            }

            $userDataResults = [
                'id_mappings_available' => count($idMapping),
                'total_users_found' => $totalUsers,
                'processed' => 0,
                'merged' => 0,
                'skipped' => 0,
                'errors' => [],
                'batch_details' => [],
            ];

            // Process in batches
            $offset = 0;
            $batchNumber = 1;

            while ($offset < $totalUsers) {
                $users = User::whereNotNull('additional_info_for_employee_to_get_started')
                    ->where('additional_info_for_employee_to_get_started', '!=', '')
                    ->where('additional_info_for_employee_to_get_started', '!=', 'null')
                    ->offset($offset)
                    ->limit($batchSize)
                    ->get();

                $batchResults = [
                    'batch_number' => $batchNumber,
                    'batch_processed' => 0,
                    'batch_merged' => 0,
                    'batch_skipped' => 0,
                    'batch_errors' => 0,
                ];

                foreach ($users as $user) {
                    $userDataResults['processed']++;
                    $batchResults['batch_processed']++;

                    try {
                        // Decode existing data
                        $additionalInfoData = [];
                        $personalDetailData = [];

                        // Get additional_info_for_employee_to_get_started data (SOURCE)
                        if (! empty($user->additional_info_for_employee_to_get_started)) {
                            $additionalInfoDecoded = json_decode($user->additional_info_for_employee_to_get_started, true);
                            if (is_array($additionalInfoDecoded)) {
                                $additionalInfoData = $additionalInfoDecoded;
                            }
                        }

                        // Get existing employee_personal_detail data (TARGET)
                        if (! empty($user->employee_personal_detail)) {
                            $personalDetailDecoded = json_decode($user->employee_personal_detail, true);
                            if (is_array($personalDetailDecoded)) {
                                $personalDetailData = $personalDetailDecoded;
                            }
                        }

                        if (empty($additionalInfoData)) {
                            $userDataResults['skipped']++;
                            $batchResults['batch_skipped']++;

                            continue;
                        }

                        // Create lookup for existing personal detail fields - NO DUPLICATE PROCESSING
                        $existingFieldNames = [];
                        foreach ($personalDetailData as $item) {
                            if (isset($item['field_name'])) {
                                $existingFieldNames[] = $item['field_name'];
                            }
                        }

                        // Merge additional info data into personal detail, with ID mapping - NO DUPLICATES
                        $mergedCount = 0;
                        foreach ($additionalInfoData as $additionalItem) {
                            // Skip fields that are marked as deleted
                            $isDeleted = isset($additionalItem['is_deleted']) && ($additionalItem['is_deleted'] == 1 || $additionalItem['is_deleted'] === true);

                            if (isset($additionalItem['field_name']) && ! in_array($additionalItem['field_name'], $existingFieldNames) && ! $isDeleted) {
                                // Create updated item preserving ALL original fields
                                $updatedItem = [];

                                // Copy all fields from original item
                                foreach ($additionalItem as $key => $value) {
                                    $updatedItem[$key] = $value;
                                }

                                // Update the ID if mapping exists
                                if (isset($additionalItem['id']) && isset($idMapping[$additionalItem['id']])) {
                                    $updatedItem['id'] = $idMapping[$additionalItem['id']];
                                }

                                // Ensure critical fields are preserved
                                if (! isset($updatedItem['value']) && isset($additionalItem['value'])) {
                                    $updatedItem['value'] = $additionalItem['value'];
                                }

                                $personalDetailData[] = $updatedItem;
                                $existingFieldNames[] = $additionalItem['field_name'];
                                $mergedCount++;
                            }
                        }

                        if ($mergedCount === 0) {
                            $userDataResults['skipped']++;
                            $batchResults['batch_skipped']++;

                            continue;
                        }

                        // Update user - REVERSED: merge into employee_personal_detail
                        $user->employee_personal_detail = json_encode($personalDetailData);

                        // Clear source column if requested
                        if ($clearSource) {
                            // $user->additional_info_for_employee_to_get_started = null;
                        }

                        $user->save();

                        $userDataResults['merged']++;
                        $batchResults['batch_merged']++;

                    } catch (\Exception $userError) {
                        $userDataResults['errors'][] = [
                            'user_id' => $user->id,
                            'error' => $userError->getMessage(),
                        ];
                        $batchResults['batch_errors']++;
                    }
                }

                $userDataResults['batch_details'][] = $batchResults;
                $offset += $batchSize;
                $batchNumber++;

                // Add small delay between batches
                if ($offset < $totalUsers) {
                    usleep(100000); // 0.1 second delay
                }
            }

            return response()->json([
                'ApiName' => 'merge_all_employee_fields_reversed',
                'status' => true,
                'message' => "Combined migration completed! Table Migration - Found: {$tableMigrationResults['source_records_found']}, Migrated: {$tableMigrationResults['migrated']}, Skipped: {$tableMigrationResults['skipped']}. User Data Migration - Found: {$userDataResults['total_users_found']}, Processed: {$userDataResults['processed']}, Merged: {$userDataResults['merged']}, Skipped: {$userDataResults['skipped']}, Total Errors: ".(count($tableMigrationResults['errors']) + count($userDataResults['errors'])),
                'data' => [
                    'table_migration' => [
                        'source_records_found' => $tableMigrationResults['source_records_found'],
                        'migrated' => $tableMigrationResults['migrated'],
                        'skipped' => $tableMigrationResults['skipped'],
                        'error_count' => count($tableMigrationResults['errors']),
                        'errors' => $tableMigrationResults['errors'],
                        'id_mappings' => $tableMigrationResults['id_mappings'],
                    ],
                    'user_data_migration' => [
                        'summary' => [
                            'id_mappings_available' => $userDataResults['id_mappings_available'],
                            'total_users_found' => $userDataResults['total_users_found'],
                            'processed' => $userDataResults['processed'],
                            'merged' => $userDataResults['merged'],
                            'skipped' => $userDataResults['skipped'],
                            'error_count' => count($userDataResults['errors']),
                            'source_cleared' => $clearSource,
                        ],
                        'batch_details' => $userDataResults['batch_details'],
                        'errors' => $userDataResults['errors'],
                    ],
                ],
            ], 200);

        } catch (\Exception $error) {
            $message = 'Something went wrong during combined migration (table + user data)!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();

            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            return response()->json([
                'ApiName' => 'merge_all_employee_fields_reversed',
                'status' => false,
                'message' => $message,
                'errorDetail' => $errorDetail,
            ], 500);
        }
    }

    public function getUserEmployeeAdminOnlyFields($userId): JsonResponse
    {
        try {

            $user = User::find($userId);

            if (! $user) {
                return response()->json([
                    'ApiName' => 'get_user_employee_admin_only_fields',
                    'status' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            $adminOnlyFields = $user->employee_admin_only_fields
                ? json_decode($user->employee_admin_only_fields, true)
                : null;

            return response()->json([
                'ApiName' => 'get_user_employee_admin_only_fields',
                'status' => true,
                'message' => 'Employee admin only fields retrieved successfully.',
                'data' => [
                    'user_id' => $userId,
                    'employee_admin_only_fields' => $adminOnlyFields,
                ],
            ], 200);

        } catch (\Exception $error) {
            $message = 'Something went wrong!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();

            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            return response()->json([
                'ApiName' => 'get_user_employee_admin_only_fields',
                'status' => false,
                'message' => $message,
                'errorDetail' => $errorDetail,
            ], 500);
        }
    }

    public function getAllEmployeeAdminOnlyFields(): JsonResponse
    {
        try {
            // Get all admin only fields configuration
            $adminOnlyFieldsConfig = EmployeeAdminOnlyFields::where('is_deleted', 0)
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'ApiName' => 'get_all_employee_admin_only_fields',
                'status' => true,
                'message' => 'Employee admin only fields configuration retrieved successfully.',
                'data' => [
                    'admin_only_fields_config' => $adminOnlyFieldsConfig,
                ],
            ], 200);

        } catch (\Exception $error) {
            $message = 'Something went wrong!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();

            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            return response()->json([
                'ApiName' => 'get_all_employee_admin_only_fields',
                'status' => false,
                'message' => $message,
                'errorDetail' => $errorDetail,
            ], 500);
        }
    }
}
