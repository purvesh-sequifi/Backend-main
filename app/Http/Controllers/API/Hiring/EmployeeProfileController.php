<?php

namespace App\Http\Controllers\API\Hiring;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\FieldRoutesTrait;
use App\Core\Traits\HubspotTrait;
use App\Core\Traits\StopPayrollTrait;
use App\Http\Controllers\API\HiredUserController;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeValidatedRequest;
use App\Models\AdditionalLocations;
use App\Models\AdditionalRecruiters;
use App\Models\Cities;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\DomainSetting;
use App\Models\EmployeeBanking;
use App\Models\EmployeeTaxInfo;
use App\Models\Integration;
use App\Models\InterigationTransactionLog;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\OnboardingEmployees;
use App\Models\Payroll;
use App\Models\Positions;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SequiDocsEmailSettings;
use App\Models\State;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserDeductionHistory;
use App\Models\UserFlexibleId;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserProfileHistory;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UsersBusinessAddress;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserThemePreference;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use App\Rules\UniqueFlexibleId;
use App\Traits\EmailNotificationTrait;
use App\Traits\HighLevelTrait;
use App\Traits\PushNotificationTrait;
use App\Traits\SolerroAddUpdateEmployeeRequestTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Traits\IntegrationTrait;
class EmployeeProfileController extends Controller
{
    use EmailNotificationTrait;
    use EvereeTrait;
    use FieldRoutesTrait;
    use HighLevelTrait;
    use HubspotTrait;
    use PushNotificationTrait;
    use SolerroAddUpdateEmployeeRequestTrait;
    use StopPayrollTrait;
    use IntegrationTrait;

    public function __construct(
        User $user,
        protected \App\Services\EspQuickBaseService $espQuickBaseService,
        protected \App\Services\OnyxRepDataPushService $onyxRepDataPushService
    ) {
        $this->user = $user;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // return Auth()->user()->email;
        $data1 = user::where('email', Auth()->user()->email)->with('positionDetail', 'state', 'city')->first();
        if (empty($data1)) {
            $additional_user_id = UsersAdditionalEmail::where('email', Auth()->user()->email)->value('user_id');
            if (! empty($additional_user_id)) {
                $data1 = User::where('id', $additional_user_id)->with('positionDetail', 'state', 'city')->first();
            }
        }
        // return $data1;
        $data =
            [
                'id' => $data1->id,
                'first_name' => isset($data1->first_name) ? $data1->first_name : null,
                'middle_name' => isset($data1->middle_name) ? $data1->middle_name : null,
                'last_name' => isset($data1->last_name) ? $data1->last_name : null,
                'position_id' => isset($data1->position_id) ? $data1->position_id : null,
                'position' => isset($data1->positionDetail->position_name) ? $data1->positionDetail->position_name : null,
                'manager_id' => isset($data1->manager_id) ? $data1->manager_id : null,
                'mobile_no' => isset($data1->mobile_no) ? $data1->mobile_no : null,
                'image' => isset($data1->image) ? $data1->image : null,
                'email' => isset($data1->email) ? $data1->email : null,
                'home_address' => isset($data1->home_address) ? $data1->home_address : null,
                'city' => isset($data1->city->name) ? $data1->city->name : null,
                'state' => isset($data1->state->name) ? $data1->state->name : null,

            ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(EmployeeValidatedRequest $request)
    {
        // return $request;
        $file = $request->file('image');
        $image_path = time().$file->getClientOriginalName();
        $ex = $file->getClientOriginalExtension();
        $destinationPath = 'Employee_profile';
        $image_path = $file->move($destinationPath, time().$file->getClientOriginalName());

        // $image_path =  "Employee_profile/".time() . $file->getClientOriginalName();
        // \Storage::disk("s3")->put($image_path,file_get_contents($file));

        $data = User::where('id', Auth()->user()->id)->first();
        $data->first_name = $request['first_name'];
        $data->middle_name = $request['middle_name'];
        $data->last_name = $request['last_name'];
        $data->image = $image_path;
        $data->sex = $request['sex'];
        $data->dob = $request['dob'];
        $data->mobile_no = $request['mobile_no'];
        $data->email = $request['email'];
        $data->work_email = $request['work_email'];
        $data->home_address = $request['home_address'];
        $data->city_id = $request['city_id'];
        $data->state_id = $request['state_id'];
        $data->zip_code = $request['zip_code'];
        $data->emergency_contact_name = $request['emergency_contact_name'];
        $data->emergency_phone = $request['emergency_phone'];
        $data->emergency_contact_relationship = $request['emergency_contact_relationship'];
        $data->emergrncy_contact_address = $request['emergrncy_contact_address'];
        $data->emergrncy_contact_city = $request['emergrncy_contact_city'];
        $data->save();

        return response()->json([
            'ApiName' => 'add-onboarding_employee',
            'status' => true,
            'message' => 'add Successfully.',
            'data' => $data,
        ], 200);

    }

    public function updateCommission(Request $request): JsonResponse
    {
        // echo $request['user_id'];die;
        $data = User::where('id', $request['user_id'])->first();
        $data->upfront_pay_amount = $request['upfront_pay_amount'];
        $data->upfront_sale_type = $request['upfront_sale_type'];
        $data->direct_overrides_type = $request['direct_overrides_type'];
        $data->indirect_overrides_type = $request['indirect_overrides_type'];
        $data->office_overrides_type = $request['office_overrides_type'];
        $data->direct_overrides_amount = $request['direct_overrides_amount'];
        $data->indirect_overrides_amount = $request['indirect_overrides_amount'];
        $data->office_overrides_amount = $request['office_overrides_amount'];
        $data->save();

        return response()->json([
            'ApiName' => 'update-commission',
            'status' => true,
            'message' => 'update Successfully.',
            'data' => $data,
        ], 200);

    }

    public function updateUserPosition(Request $request): JsonResponse
    {
        // echo $request['user_id'];die;
        $data = User::where('id', $request['user_id'])->first();
        $old_redline = $data->redline;
        $data->department_id = $request['department_id'];
        $data->manager_id = $request['manager_id'];
        $data->team_id = $request['team_id'];
        $data->position_id = $request['position_id'];
        $data->position_id = $request['position_id'];
        // $data->redline = $request['redline'];
        $data->redline_type = $request['redline_type'];
        $data->redline_amount_type = $request['redline_amount_type'];
        // echo $request['period_of_agreement_start_date'];die;
        $data->probation_period = $request['probation_period'];
        $data->period_of_agreement_start_date = $request['period_of_agreement_start_date'];
        $data->end_date = $request['end_date'];
        $data->hiring_bonus_amount = $request['hiring_bonus_amount'];
        $data->commission = $request['commission'];
        $data->save();
        // echo $old_redline ;DIE;
        if ($old_redline !== $request['redline']) {
            // echo"DAD";DIE;
            $updater_id = Auth()->user()->id;

            // function not in use
            $data2 = UserRedlines::create([
                'user_id' => $request['user_id'],
                'updater_id' => $updater_id,
                'redline' => $request['redline'],
                'redline_type' => $request['redline_type'],
                'redline_amount_type' => $request['redline_amount_type'],
                'start_date' => $request['start_date'],

            ]);
        }

        return response()->json([
            'ApiName' => 'update-UserPosition',
            'status' => true,
            'message' => 'update Successfully.',
            'data' => $data,
        ], 200);

    }

    public function EmployeePersonalinfo(): JsonResponse
    {
        $data1 = user::where('email', Auth()->user()->email)->with('positionDetail', 'managerDetail', 'state', 'city')->first();
        if (empty($data1)) {
            $additional_user_id = UsersAdditionalEmail::where('email', Auth()->user()->email)->value('user_id');
            if (! empty($additional_user_id)) {
                $data1 = User::where('id', $additional_user_id)->with('positionDetail', 'managerDetail', 'state', 'city')->first();
            }
        }
        $data =
        [
            'id' => $data1->id,
            'first_name' => isset($data1->first_name) ? $data1->first_name : null,
            'middle_name' => isset($data1->middle_name) ? $data1->middle_name : null,
            'last_name' => isset($data1->last_name) ? $data1->last_name : null,
            'position' => isset($data1->positionDetail->position_name) ? $data1->positionDetail->position_name : null,
            'manager_id' => isset($data1->manager_id) ? $data1->manager_id : null,
            'manager_name' => isset($data1->managerDetail->first_name) ? $data1->managerDetail->first_name : null,
            'location' => isset($data1->location) ? $data1->location : null,
            'mobile_no' => isset($data1->mobile_no) ? $data1->mobile_no : null,
            'sex' => isset($data1->sex) ? $data1->sex : null,
            'dob' => isset($data1->dob) ? $data1->dob : null,
            'zip_code' => isset($data1->zip_code) ? $data1->zip_code : null,
            'email' => isset($data1->email) ? $data1->email : null,
            'work_email' => isset($data1->work_email) ? $data1->work_email : null,
            'home_address' => isset($data1->home_address) ? $data1->home_address : null,
            'city' => isset($data1->city->name) ? $data1->city->name : null,
            'state' => isset($data1->state->name) ? $data1->state->name : null,
            'Emergency_contact' => isset($data1->emergency_contact_name) ? $data1->emergency_contact_name : null,
            'Phone' => isset($data1->emergency_phone) ? $data1->emergency_phone : null,
            'Relationship' => isset($data1->emergency_contact_relationship) ? $data1->emergency_contact_relationship : null,
            'Address' => isset($data1->emergrncy_contact_address) ? $data1->emergrncy_contact_address : null,
            'City' => isset($data1->emergrncy_contact_city) ? $data1->emergrncy_contact_city : null,

        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function EmployeePackege(): JsonResponse
    {
        $data1 = User::where('id', Auth()->user()->id)->with('departmentDetail', 'positionDetail', 'managerDetail', 'Commission')->first();
        $data =
        [
            // dd($data1->departmentDetail),
            'department' => $data1->departmentDetail->name,
            'position' => isset($data1->positionDetail->position_name) ? $data1->positionDetail->position_name : null,
            'manager' => isset($data1->managerDetail->name) ? $data1->managerDetail->name : null,
            'Team' => isset($data1->team_id) ? $data1->team_id : null,
            'probation_period' => isset($data1->probation_period) && $data1->probation_period != 'None' ? $data1->probation_period : null,
            'start_date' => isset($data1->period_of_agreement_start_date) ? $data1->period_of_agreement_start_date : null,
            'Commission' => data_get($data1, 'Commission.0.commission_parentage'),
            'upfront' => isset($data1->upfront_pay_amount) ? $data1->upfront_pay_amounte : null,
            'upfront_type' => isset($data1->per_sale) ? $data1->per_sale : null,
            'direct_override' => isset($data1->direct_overrides_amount) ? $data1->direct_overrides_amount : null,
            'direct_override_type' => isset($data1->direct_per_kw) ? $data1->direct_per_kw : null,
            'indirect_override' => isset($data1->indirect_overrides_amount) ? $data1->indirect_overrides_amount : null,
            'indirect_override_type' => isset($data1->indirect_per_kw) ? $data1->indirect_per_kw : null,
            'office_override' => isset($data1->office_overrides_amount) ? $data1->office_overrides_amount : null,
            'office_override_type' => isset($data1->office_per_kw) ? $data1->office_per_kw : null,
            'deducation' => $deducation[''] =
            [
                'rent' => isset($data1->rent) ? $data1->rent : null,
                'travel' => isset($data1->travel) ? $data1->travel : null,
                'phone_Bill' => isset($data1->phone_bill) ? $data1->phone_bill : null,
            ],
        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);

    }

    public function EmployeeTaxInfo(): JsonResponse
    {
        $data1 = EmployeeTaxInfo::where('user_id', Auth()->user()->id)->first();
        $data =
        [
            // dd($data1->departmentDetail),
            'SSN' => isset($data1->ssn) ? $data1->ssn : null,
            'document_type' => isset($data1->document_type) ? $data1->document_type : null,
            'filling_status' => isset($data1->filling_status) ? $data1->filling_status : null,
        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function userRedlineHistory($id)
    {
        // echo$id;DIE;
        $UserRedlines = UserRedlines::where('user_id', $id)->orderBy('id', 'DESC')->get();
        // return $data1;
        $data = [];
        if ($UserRedlines) {
            foreach ($UserRedlines as $UserRedline) {
                $updater_detail = User::where('id', $UserRedline->updater_id)->orderBy('id', 'DESC')->first();

                $data[] =
                    [
                        'id' => $UserRedline->id,
                        'redline_amount' => $UserRedline->redline_amount,
                        'redline' => $UserRedline->redline,
                        'redline_type' => $UserRedline->redline_type,
                        'start_date' => $UserRedline->start_date,
                        'updater_name' => $updater_detail->first_name,
                        'image' => $updater_detail->image,
                    ];
            }

            return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
        } else {
            return response()->json(['status' => true, 'message' => 'No record found.', 'data' => []], 200);
        }
    }

    public function Employeebanking()
    {
        //    return 'Hello';
        $data1 = EmployeeBanking::where('user_id', Auth()->user()->id)->first();
        $data =
        [
            // dd($data1->departmentDetail),
            'bank_name' => isset($data1->bank_name) ? $data1->bank_name : null,
            'routing_number' => isset($data1->routing_number) ? $data1->routing_number : null,
            'account_number' => isset($data1->acconut_number) ? $data1->acconut_number : null,
            'account_type' => isset($data1->acconut_type) ? $data1->acconut_type : null,
        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function userProfile($id)
    {
        $data1 = user::where('id', $id)->with('positionDetail', 'groupDetail', 'managerDetail', 'state', 'city', 'additionalDetail', 'office', 'subpositionDetail', 'team', 'recruiter')->first();
        // Check if user data is not found
        if (! $data1) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
                'code' => 404,
            ], 404);
        }

        if ($data1) {
            $state = State::where('id', $data1->state_id)->first();
            if ($data1 && $state && ($data1->everee_workerId == null || $data1->everee_workerId == '')) {
                $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                if ($CrmData) {
                    $this->update_emp_personal_info($data1, $state);  // update emp in everee
                }
            }

            $total_employee = User::where('manager_id', $data1->id)->count();
            if (isset($data1->additionalDetail[0])) {
                $additionalName1 = isset($data1->additionalDetail[0]->additionalRecruiterDetail->first_name) ? $data1->additionalDetail[0]->additionalRecruiterDetail->first_name : null;
                $additionalName2 = isset($data1->additionalDetail[0]->additionalRecruiterDetail->last_name) ? $data1->additionalDetail[0]->additionalRecruiterDetail->last_name : null;
                $fullname = $additionalName1.' '.$additionalName2;
                $system_type = $data1->additionalDetail[0]->system_type;
            }

            if (isset($data1->additionalDetail[1])) {
                $additionalName3 = isset($data1->additionalDetail[1]->additionalRecruiterDetail->first_name) ? $data1->additionalDetail[1]->additionalRecruiterDetail->first_name : null;
                $additionalName4 = isset($data1->additionalDetail[1]->additionalRecruiterDetail->last_name) ? $data1->additionalDetail[1]->additionalRecruiterDetail->last_name : null;
                $fullname2 = $additionalName3.' '.$additionalName4;
                $system_type2 = isset($data1->additionalDetail[1]->system_type) ? $data1->additionalDetail[1]->system_type : null;
            }

            if (isset($data1->image) && $data1->image != null) {
                $s3_user_profile_url = s3_getTempUrl(config('app.domain_name').'/'.$data1->image);
            } else {
                $s3_user_profile_url = null;
            }

            if (isset($data1->recruiter->first_name, $data1->recruiter->last_name)) {
                $recruiter_name = $data1->recruiter->first_name.' '.$data1->recruiter->last_name;
            } else {
                if ($data1->recruiter) {
                    $recruiter_name = $data1->recruiter->first_name;
                } else {
                    $recruiter_name = null;
                }
            }

            $additionalLocation = [];
            $latest_effective_date = AdditionalLocations::select('effective_date')->where('effective_date', '<=', date('Y-m-d'))->where('user_id', $data1->id)->orderBy('effective_date', 'desc')->groupBy('effective_date')->first();
            if (isset($latest_effective_date->effective_date)) {
                $additionalLocation = AdditionalLocations::with('state', 'office')->where('user_id', $data1->id)->where('effective_date', $latest_effective_date->effective_date)->get();
            }

            if ($additionalLocation) {
                $additionalLocation->transform(function ($data) {
                    if (isset($data->office->id) && isset($data->user_id)) {
                        return [
                            'state_id' => isset($data->state_id) ? $data->state_id : null,
                            'state_name' => isset($data->state->name) ? $data->state->name : null,
                            'office_id' => isset($data->office->id) ? $data->office->id : null,
                            'office_name' => isset($data->office->office_name) ? $data->office->office_name : null,
                        ];
                    }
                });
            }

            $manager = (new HiredUserController)->managerCheckr($data1->id);
            $managerName = null;
            if ($manager && $manager->manager_id) {
                $managerUser = User::with('positionDetail', 'departmentDetail')->find($manager->manager_id);
                $managerName = $managerUser->first_name.' '.$managerUser->last_name;
            }
            $isManager = (new HiredUserController)->isManagerCheckr($data1->id);
            $organization = (new HiredUserController)->organizationCheckr($data1->id);
            $positionName = $positionId = $subPositionName = $subPositionId = null;
            if ($organization) {
                $position = Positions::where('id', $organization->position_id)->first();
                $subPosition = Positions::where('id', $organization->sub_position_id)->first();

                $positionName = $position->position_name;
                $positionId = $position->id;
                $subPositionName = $subPosition->position_name;
                $subPositionId = $subPosition->id;
            }
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData && $data1->worker_type == '1099' && isset($data1) && ! empty($data1->everee_workerId)) {
                $everee_onboarding_process = 1;
            } elseif ($CrmData && ($data1->worker_type == 'w2' || $data1->worker_type == 'W2') && isset($data1) && ! empty($data1->everee_workerId) && $data1->everee_embed_onboard_profile == 1) {
                $everee_onboarding_process = 1;
            } else {
                $everee_onboarding_process = 0;
            }
            $data = [
                'id' => $data1->id,
                'worker_id' => isset($data1->everee_workerId) ? $data1->everee_workerId : null,
                'worker_type' => isset($data1->worker_type) ? $data1->worker_type : null,
                'employee_id' => isset($data1->employee_id) ? $data1->employee_id : null,
                'first_name' => isset($data1->first_name) ? $data1->first_name : null,
                'middle_name' => isset($data1->middle_name) ? $data1->middle_name : null,
                'last_name' => isset($data1->last_name) ? $data1->last_name : null,
                'position' => $positionName,
                'position_id' => $positionId,
                'sub_position_id' => $subPositionId,
                'sub_position_name' => $subPositionName,
                'main_role' => $data1?->subpositionDetail?->is_selfgen,
                'group_id' => isset($data1->group_id) ? $data1->group_id : null,
                'group_name' => isset($data1->groupDetail->name) ? $data1->groupDetail->name : null,
                'manager_id' => isset($manager->manager_id) ? $manager->manager_id : null,
                'manager_name' => $managerName,
                'is_manager' => isset($isManager->is_manager) ? $isManager->is_manager : null,
                'is_super_admin' => isset($data1->is_super_admin) ? $data1->is_super_admin : null,
                'office_id' => isset($data1->office_id) ? $data1->office_id : null,
                'total_employee' => isset($total_employee) ? $total_employee : null,
                'mobile_no' => isset($data1->mobile_no) ? $data1->mobile_no : null,
                'recruiter_id' => isset($data1->recruiter_id) ? $data1->recruiter_id : null,
                'recruiter_name' => $recruiter_name,
                'sex' => isset($data1->sex) ? ($data1->sex == 'other' ? 'not to be mention' : $data1->sex) : null,
                'dob' => isset($data1->dob) ? $data1->dob : null,
                'image' => isset($data1->image) ? $data1->image : null,
                'email' => isset($data1->email) ? $data1->email : null,
                'home_address' => isset($data1->home_address) ? $data1->home_address : null,
                'home_address_line_1' => isset($data1->home_address_line_1) ? $data1->home_address_line_1 : null,
                'home_address_line_2' => isset($data1->home_address_line_2) ? $data1->home_address_line_2 : null,
                'home_address_state' => isset($data1->home_address_state) ? $data1->home_address_state : null,
                'home_address_city' => isset($data1->home_address_city) ? $data1->home_address_city : null,
                'home_address_zip' => isset($data1->home_address_zip) ? $data1->home_address_zip : null,
                'home_address_lat' => isset($data1->home_address_lat) ? $data1->home_address_lat : null,
                'home_address_long' => isset($data1->home_address_long) ? $data1->home_address_long : null,
                'home_address_timezone' => isset($data1->home_address_timezone) ? $data1->home_address_timezone : null,
                'work_email' => isset($data1->work_email) ? $data1->work_email : null,
                'city_id' => isset($data1->city_id) ? $data1->city_id : null,
                'city' => isset($data1->city->name) ? $data1->city->name : null,
                'state_id' => isset($data1->state_id) ? $data1->state_id : null,
                'state_name' => isset($data1->state->name) ? $data1->state->name : null,
                'state_code' => isset($data1->state->state_code) ? $data1->state->state_code : null,
                'state' => isset($data1->state) ? $data1->state : null,
                'office' => isset($data1->office) ? $data1->office : null,
                'zip_code' => isset($data1->zip_code) ? $data1->zip_code : null,
                'stop_payroll' => isset($data1->stop_payroll) ? $data1->stop_payroll : null,
                'dismiss' => isset($data1->dismiss) ? $data1->dismiss : null,
                'disable_login' => isset($data1->disable_login) ? $data1->disable_login : null,
                'status_id' => isset($data1->status_id) ? $data1->status_id : null,
                'emergency_contact_name' => isset($data1->emergency_contact_name) ? $data1->emergency_contact_name : null,
                'emergency_phone' => isset($data1->emergency_phone) ? $data1->emergency_phone : null,
                'emergency_contact_relationship' => isset($data1->emergency_contact_relationship) ? $data1->emergency_contact_relationship : null,
                'emergrncy_contact_address' => isset($data1->emergrncy_contact_address) ? $data1->emergrncy_contact_address : null,
                'emergency_address_line_1' => isset($data1->emergency_address_line_1) ? $data1->emergency_address_line_1 : null,
                'emergency_address_line_2' => isset($data1->emergency_address_line_2) ? $data1->emergency_address_line_2 : null,
                'emergency_address_lat' => isset($data1->emergency_address_lat) ? $data1->first_name : null,
                'emergency_address_long' => isset($data1->emergency_address_long) ? $data1->emergency_address_long : null,
                'emergency_address_timezone' => isset($data1->emergency_address_timezone) ? $data1->emergency_address_timezone : null,
                'emergrncy_contact_city' => isset($data1->emergrncy_contact_city) ? $data1->emergrncy_contact_city : null,
                'emergrncy_contact_state' => isset($data1->emergrncy_contact_state) ? $data1->emergrncy_contact_state : null,
                'emergrncy_contact_zip_code' => isset($data1->emergrncy_contact_zip_code) ? $data1->emergrncy_contact_zip_code : null,
                'additional_info_for_employee_to_get_started' => isset($data1->additional_info_for_employee_to_get_started) ? $data1->additional_info_for_employee_to_get_started : null,
                'employee_personal_detail' => isset($data1->employee_personal_detail) ? $data1->employee_personal_detail : null,
                'tax_information' => isset($data1->tax_information) ? $data1->tax_information : null,
                'social_sequrity_no' => isset($data1->social_sequrity_no) ? $data1->social_sequrity_no : null,
                'name_of_bank' => isset($data1->name_of_bank) ? $data1->name_of_bank : null,
                'routing_no' => isset($data1->routing_no) ? $data1->routing_no : null,
                'account_no' => isset($data1->account_no) ? $data1->account_no : null,
                'confirm_account_no' => isset($data1->confirm_account_no) ? $data1->confirm_account_no : null,
                'account_name' => isset($data1->account_name) ? $data1->account_name : null,
                'type_of_account' => isset($data1->type_of_account) ? $data1->type_of_account : null,
                'additional_recruiter1_id' => isset($data1->additionalDetail[0]->recruiter_id) ? $data1->additionalDetail[0]->recruiter_id : null,
                'additional_recruiter1_name' => isset($fullname) ? $fullname : null,
                'additional_recruiter1_type' => isset($system_type) ? $system_type : null,
                'additional_recruiter2_id' => isset($data1->additionalDetail[1]->recruiter_id) ? $data1->additionalDetail[1]->recruiter_id : null,
                'additional_recruiter2_name' => isset($fullname2) ? $fullname2 : null,
                'additional_recruiter2_type' => isset($system_type2) ? $system_type2 : null,
                'team_id' => isset($data1->team_id) ? $data1->team_id : null,
                'team_name' => isset($data1->team->team_name) ? $data1->team->team_name : null,
                'entity_type' => isset($data1->entity_type) ? $data1->entity_type : null,
                'business_type' => isset($data1->business_type) ? $data1->business_type : null,
                'business_name' => isset($data1->business_name) ? $data1->business_name : null,
                'business_ein' => isset($data1->business_ein) ? $data1->business_ein : null,
                'user_profile_s3' => $s3_user_profile_url,
                'first_time_changed_password' => isset($data1->first_time_changed_password) ? $data1->first_time_changed_password : 0,
                'additional_locations' => $additionalLocation,
                'everee_workerId' => isset($data1->everee_workerId) ? $data1->everee_workerId : null,
                'everee_onboarding_process' => $everee_onboarding_process,
                'worker_type' => ($data1->worker_type != null) ? $data1->worker_type : '1099',
                'terminate' => isset($data1->terminate) ? $data1->terminate : 0,
            ];

            if (empty($data['dob']) || is_null($data['dob'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your date.',
                    'data' => $data,
                ]);
            }

            if (empty($data['office_id']) || is_null($data['office_id'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your office id.',
                    'data' => $data,
                ]);
            }

            // regarding to account field
            if (empty($data['name_of_bank']) || is_null($data['name_of_bank'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your name_of_bank.',
                    'data' => $data,
                ]);
            }
            if (empty($data['account_name']) || is_null($data['account_name'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your account_name.',
                    'data' => $data,
                ]);
            }
            if (empty($data['routing_no']) || is_null($data['routing_no'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your routing_no.',
                    'data' => $data,
                ]);
            }
            if (empty($data['account_no']) || is_null($data['account_no'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your account_no.',
                    'data' => $data,
                ]);
            }

            // regarding to tax info
            if ($data['entity_type'] == 'individual') {
                if (empty($data['social_sequrity_no']) || is_null($data['social_sequrity_no'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Please complete your profile by providing your social_sequrity_no.',
                        'data' => $data,
                    ]);
                }
            }
            if ($data['entity_type'] == 'business') {
                if (empty($data['business_ein']) || is_null($data['business_ein'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Please complete your profile by providing your business_ein.',
                        'data' => $data,
                    ]);
                }
            }

            // regarding to home address field
            if (empty($data['home_address_line_1']) || is_null($data['home_address_line_1'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your home_address_line_1.',
                    'data' => $data,
                ]);
            }
            if (empty($data['home_address_city']) || is_null($data['home_address_city'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your home_address_city.',
                    'data' => $data,
                ]);
            }
            if (empty($data['state_code']) || is_null($data['state_code'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your state_code.',
                    'data' => $data,
                ]);
            }
            if (empty($data['home_address_zip']) || is_null($data['home_address_zip'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please complete your profile by providing your home_address_zip.',
                    'data' => $data,
                ]);
            }

            $emails = [];

            return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data, 'emails' => $emails]);
        }
    }

    public function UserPersonalinfo($id): JsonResponse
    {
        $user = User::with('positionDetail', 'managerDetail', 'state', 'city', 'recruiter', 'additionalRecruiterOne', 'additionalRecruiterTwo', 'additionalDetail')->where('id', $id)->first();
        if (isset($user->additionalDetail[0])) {
            $additionalName1 = isset($user->additionalDetail[0]->additionalRecruiterDetail->first_name) ? $user->additionalDetail[0]->additionalRecruiterDetail->first_name : null;
            $additionalName2 = isset($user->additionalDetail[0]->additionalRecruiterDetail->last_name) ? $user->additionalDetail[0]->additionalRecruiterDetail->last_name : null;
            $fullname = $additionalName1.' '.$additionalName2;
            $system_type = isset($user->additionalDetail[0]->system_type) ? $user->additionalDetail[0]->system_type : null;

        }
        if (isset($user->additionalDetail[1])) {

            $additionalName3 = isset($user->additionalDetail[1]->additionalRecruiterDetail->first_name) ? $user->additionalDetail[1]->additionalRecruiterDetail->first_name : null;
            $additionalName4 = isset($user->additionalDetail[1]->additionalRecruiterDetail->last_name) ? $user->additionalDetail[1]->additionalRecruiterDetail->last_name : null;
            $fullname2 = $additionalName3.' '.$additionalName4;
            $system_type2 = isset($user->additionalDetail[1]->system_type) ? $user->additionalDetail[1]->system_type : null;
        }

        $data =
        [
            'id' => $user->id,
            'first_name' => isset($user->first_name) ? $user->first_name : null,
            'middle_name' => isset($user->middle_name) ? $user->middle_name : null,
            'last_name' => isset($user->last_name) ? $user->last_name : null,
            'position' => isset($user->positionDetail->position_name) ? $user->positionDetail->position_name : null,
            'manager_id' => isset($user->manager_id) ? $user->manager_id : null,
            'recruiter_id' => isset($user->recruiter_id) ? $user->recruiter_id : null,
            'recruiter_name' => isset($user->recruiter->first_name) ? $user->recruiter->first_name : null,
            'manager_name' => isset($user->managerDetail->first_name) ? $user->managerDetail->first_name : null,
            'location' => isset($user->location) ? $user->location : null,
            'mobile_no' => isset($user->mobile_no) ? $user->mobile_no : null,
            'sex' => isset($user->sex) ? $user->sex : null,
            'dob' => isset($user->dob) ? $user->dob : null,
            'zip_code' => isset($user->zip_code) ? $user->zip_code : null,
            'email' => isset($user->email) ? $user->email : null,
            'work_email' => isset($user->work_email) ? $user->work_email : null,
            'home_address' => isset($user->home_address) ? $user->home_address : null,
            'city_id' => isset($user->city_id) ? $user->city_id : null,
            'city' => isset($user->city->name) ? $user->city->name : null,
            'state_id' => isset($user->state_id) ? $user->state_id : null,
            'state' => isset($user->state->name) ? $user->state->name : null,
            'stop_payroll' => isset($user->stop_payroll) ? $user->stop_payroll : null,
            'dismiss' => isset($user->dismiss) ? $user->dismiss : null,
            'disable_login' => isset($user->disable_login) ? $user->disable_login : null,
            'status_id' => isset($user->status_id) ? $user->status_id : null,
            'additional_recruiter1_id' => isset($user->additionalDetail[0]->recruiter_id) ? $user->additionalDetail[0]->recruiter_id : null,
            'additional_recruiter1_name' => isset($fullname) ? $fullname : null,
            // 'additional_recruiter1_amount'  => isset($data1->additionalDetail[0]->system_per_kw_amount) ? $data1->additionalDetail[0]->system_per_kw_amount : null,
            'additional_recruiter1_type' => isset($system_type) ? $system_type : null,
            'additional_recruiter2_id' => isset($user->additionalDetail[1]->recruiter_id) ? $user->additionalDetail[1]->recruiter_id : null,
            'additional_recruiter2_name' => isset($fullname2) ? $fullname2 : null,
            // 'additional_recruiter2_amount'  => isset($data1->additionalDetail[1]->system_per_kw_amount) ? $data1->additionalDetail[1]->system_per_kw_amount : null,
            'additional_recruiter2_type' => isset($system_type2) ? $system_type2 : null,
            'emergency_contact_name' => isset($user->emergency_contact_name) ? $user->emergency_contact_name : null,
            'emergency_phone' => isset($user->emergency_phone) ? $user->emergency_phone : null,
            'emergency_contact_relationship' => isset($user->emergency_contact_relationship) ? $user->emergency_contact_relationship : null,
            'emergrncy_contact_address' => isset($user->emergrncy_contact_address) ? $user->emergrncy_contact_address : null,
            'emergrncy_contact_city' => isset($user->emergrncy_contact_city) ? $user->emergrncy_contact_city : null,
            'emergrncy_contact_state' => isset($user->emergrncy_contact_state) ? $user->emergrncy_contact_state : null,
            'emergrncy_contact_zip_code' => isset($user->emergrncy_contact_zip_code) ? $user->emergrncy_contact_zip_code : null,
            'additional_info_for_employee_to_get_started' => isset($user->additional_info_for_employee_to_get_started) ? $user->additional_info_for_employee_to_get_started : null,
            'employee_personal_detail' => isset($user->employee_personal_detail) ? $user->employee_personal_detail : null,
            'additional_detail' => isset($user->additionalDetail) ? $user->additionalDetail : null,
        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function userPackege($id): JsonResponse
    {
        // echo"cccs";die;
        $data1 = User::where('id', $id)->with('departmentDetail', 'positionDetail', 'managerDetail', 'Commission')->first();
        // echo $data1->upfront_pay_amount;die;
        $data2 = Locations::where('id', $data1->office_id)->pluck('office_name')->first();
        $data3 = State::where('id', $data1->state_id)->pluck('name')->first();

        $data =
        [
            // dd($data1->departmentDetail),
            'department' => isset($data1->departmentDetail->name) ? $data1->departmentDetail->name : null,
            'department_id' => isset($data1->departmentDetail->id) ? $data1->departmentDetail->id : null,
            'position_id' => isset($data1->position_id) ? $data1->position_id : null,
            'position' => isset($data1->positionDetail->position_name) ? $data1->positionDetail->position_name : null,
            'manager_id' => isset($data1->manager_id) ? $data1->manager_id : null,
            'manager' => isset($data1->managerDetail->name) ? $data1->managerDetail->name : null,
            'team_id' => isset($data1->team_id) ? $data1->team_id : null,
            'team_name' => isset($data1->teamDetail->name) ? $data1->teamDetail->name : null,
            'redline' => isset($data1->redline) ? $data1->redline : null,
            'redline_amount_type' => isset($data1->redline_amount_type) ? $data1->redline_amount_type : null,
            'redline_type' => isset($data1->redline_type) ? $data1->redline_type : null,
            'redline_effective_date' => null,
            'probation_period' => isset($data1->probation_period) && $data1->probation_period != 'None' ? $data1->probation_period : null,
            'start_date' => isset($data1->period_of_agreement_start_date) ? $data1->period_of_agreement_start_date : null,
            'end_date' => isset($data1->end_date) ? $data1->end_date : null,
            'hiring_bonus_amount' => isset($data1->hiring_bonus_amount) ? $data1->hiring_bonus_amount : null,
            'office_name' => isset($data2) ? $data2 : null,
            'office_state' => isset($data3) ? $data3 : null,
            'commission' => $commission[''] =
            [
                'commission' => isset($data1->commission) ? $data1->commission : null,
                'commission_parentage' => data_get($data1, 'Commission.0.commission_parentage'),
                'upfront_pay_amount' => $data1->upfront_pay_amount,
                'upfront_sale_type' => isset($data1->upfront_sale_type) ? $data1->upfront_sale_type : null,
                'per_sale' => isset($data1->per_sale) ? $data1->per_sale : null,
                'direct_overrides_amount' => isset($data1->direct_overrides_amount) ? $data1->direct_overrides_amount : null,
                'direct_overrides_type' => isset($data1->direct_overrides_type) ? $data1->direct_overrides_type : null,
                'indirect_overrides_amount' => isset($data1->indirect_overrides_amount) ? $data1->indirect_overrides_amount : null,
                'indirect_overrides_type' => isset($data1->indirect_overrides_type) ? $data1->indirect_overrides_type : null,
                'office_overrides_amount' => isset($data1->office_overrides_amount) ? $data1->office_overrides_amount : null,
                'office_overrides_type' => isset($data1->office_overrides_type) ? $data1->office_overrides_type : null,
            ],
            'deducation' => $deducation[''] =
            [
                'rent' => isset($data1->rent) ? $data1->rent : null,
                'travel' => isset($data1->travel) ? $data1->travel : null,
                'phone_Bill' => isset($data1->phone_bill) ? $data1->phone_bill : null,
            ],
        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);

    }

    public function userTaxInfo($id): JsonResponse
    {
        $data1 = EmployeeTaxInfo::where('user_id', $id)->first();
        $data =
        [
            // dd($data1->departmentDetail),
            'SSN' => isset($data1->ssn) ? $data1->ssn : null,
            'document_type' => isset($data1->document_type) ? $data1->document_type : null,
            'filling_status' => isset($data1->filling_status) ? $data1->filling_status : null,
        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function userBanking($id)
    {
        //    return 'Hello';
        $data1 = EmployeeBanking::where('user_id', $id)->first();
        $data =
        [
            // dd($data1->departmentDetail),
            'bank_name' => isset($data1->bank_name) ? $data1->bank_name : null,
            'routing_number' => isset($data1->routing_number) ? $data1->routing_number : null,
            'account_number' => isset($data1->acconut_number) ? $data1->acconut_number : null,
            'account_type' => isset($data1->acconut_type) ? $data1->acconut_type : null,
            'account_name' => isset($data1->account_name) ? $data1->account_name : null,
        ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function updateUserProfile(request $request)
    {
        $data = User::where('id', $request->user_id)->first();
        $old_data = $data->toArray();
        $id = $data->id;
        if ($data != null && $data != '') {
            $profileData = $data->toArray();
        }
        $OnboardingEmployees = OnboardingEmployees::where('user_id', $id)->first();
        $onboardingId = '';
        $leadId = '';
        if (! empty($OnboardingEmployees)) {
            $onboardingId = $OnboardingEmployees->id;
            $leadId = ! empty($OnboardingEmployees->lead_id) ? $OnboardingEmployees->lead_id : '';
        }

        // Check for duplicate mobile number and provide detailed error message
        if ($request->mobile_no != $data->mobile_no) {
            $duplicateUser = User::where('mobile_no', $request->mobile_no)
                ->where('id', '!=', $id)
                ->first();

            if ($duplicateUser) {
                $fullName = trim($duplicateUser->first_name . ' ' . $duplicateUser->last_name);
                $userEmail = $duplicateUser->email;
                return response()->json([
                    'error' => [
                        'mobile_no' => ["This mobile number is being used by {$fullName} ({$userEmail}). Please use a different mobile number or update this current user with their correct mobile number."]
                    ]
                ], 400);
            }
        }

        // Build validation rules dynamically
        // Exclude by user_id column for onboarding_employees to handle multiple records per user
        // Format: unique:table,column,except_value,except_column
        $mobileRule = 'required|min:10|unique:users,mobile_no,'.$id;
        $emailRule = 'required|email|unique:users,email,'.$id;

        // Only add onboarding_employees unique check if we have a valid record
        // Use user_id column to exclude ALL records for this user (handles multiple onboarding records)
        if (!empty($onboardingId)) {
            $mobileRule .= '|unique:onboarding_employees,mobile_no,'.$id.',user_id';
            $emailRule .= '|unique:onboarding_employees,email,'.$id.',user_id';
        }

        // Only add leads unique check if we have a valid lead ID to exclude
        if (!empty($leadId)) {
            $mobileRule .= '|unique:leads,mobile_no,'.$leadId;
            $emailRule .= '|unique:leads,email,'.$leadId;
        }

        $validationRules = [
            'mobile_no' => $mobileRule,
            'email' => $emailRule,
        ];

        // Add flexible ID validation rules
        if ($request->has('flexi_id_1') && ! empty($request->flexi_id_1)) {
            $validationRules['flexi_id_1'] = ['nullable', 'string', 'max:100', new UniqueFlexibleId($request->user_id)];
        }
        if ($request->has('flexi_id_2') && ! empty($request->flexi_id_2)) {
            $validationRules['flexi_id_2'] = ['nullable', 'string', 'max:100', new UniqueFlexibleId($request->user_id)];
        }
        if ($request->has('flexi_id_3') && ! empty($request->flexi_id_3)) {
            $validationRules['flexi_id_3'] = ['nullable', 'string', 'max:100', new UniqueFlexibleId($request->user_id)];
        }

        // Check for duplicates within the same request (case-insensitive)
        $flexiIds = array_filter([
            $request->flexi_id_1,
            $request->flexi_id_2,
            $request->flexi_id_3,
        ]);

        // Convert to lowercase for case-insensitive comparison
        $flexiIdsLower = array_map('strtolower', $flexiIds);

        if (count($flexiIdsLower) !== count(array_unique($flexiIdsLower))) {
            return response()->json([
                'error' => [
                    'flexible_ids' => ['Each flexible ID must have a unique value. You cannot use the same value for multiple flexible IDs.'],
                ],
            ], 400);
        }

        $validator = Validator::make($request->all(), $validationRules);

        // if ($request->user()->is_super_admin != 1){
        //     if((empty($request->dob) || $request->dob == 'null')) {
        //         $validator->sometimes('dob', 'required', function ($request) {
        //             return empty($request->dob) || $request->dob === 'null';
        //         });
        //     }

        //     if (empty($request->home_address) || $request->home_address == 'null' ) {
        //         $validator->sometimes('home_address', 'required', function ($request) {
        //             return empty($request->home_address) || $request->home_address === 'null';
        //         });
        //     }
        // }

        if ($request->entity_type == 'individual') {
            if (empty($request->social_sequrity_no) || $request->social_sequrity_no == 'null') {
                $validator->sometimes('social_sequrity_no', 'required', function ($request) {
                    return empty($request->social_sequrity_no) || $request->social_sequrity_no === 'null';
                });
            }
        }

        if ($request->entity_type == 'business') {
            if (empty($request->business_ein) || $request->business_ein == 'null') {
                $validator->sometimes('business_ein', 'required', function ($request) {
                    return empty($request->business_ein) || $request->business_ein === 'null';
                });
            }
        }

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Check New Email Exists On Lead Or Not When New Email Comes
        if ($data->email != $request->email && ! $data->is_super_admin && $OnboardingEmployees) {
            $lead = Lead::where('email', $request->email)->where('id', '!=', $OnboardingEmployees->lead_id)->count();
            if ($lead) {
                return response()->json(['error' => ['email' => ['Email Already Exists In the Lead']]], 400);
            }
            Lead::where('id', $OnboardingEmployees->lead_id)->update(['email' => $request->email]);
            OnboardingEmployees::where('user_id', $id)->update(['email' => $request->email]);
        }
        if ($data->mobile_no != $request->mobile_no && ! $data->is_super_admin && $OnboardingEmployees) {
            $lead = Lead::where('mobile_no', $request->mobile_no)->where('id', '!=', $OnboardingEmployees->lead_id)->count();
            if ($lead) {
                return response()->json(['error' => ['mobile_no' => ['Mobile Number Already Exists In the Lead']]], 400);
            }
            Lead::where('id', $OnboardingEmployees->lead_id)->update(['mobile_no' => $request->mobile_no]);
            OnboardingEmployees::where('user_id', $id)->update(['mobile_no' => $request->mobile_no]);
        }

        if ($data->first_name != $request->first_name && $data->last_name != $request->last_name && ! $data->is_super_admin && $OnboardingEmployees) {
            $lead = Lead::where('first_name', $request->first_name)->where('last_name', $request->last_name)->where('id', '!=', $OnboardingEmployees->lead_id)->count();
            if ($lead) {
                return response()->json(['error' => ['first_name' => ['First Name Already Exists In the Lead'], 'last_name' => ['Last Name Already Exists In the Lead']]], 400);
            }
            Lead::where('id', $OnboardingEmployees->lead_id)->update(['first_name' => $request->first_name, 'last_name' => $request->last_name]);
            OnboardingEmployees::where('user_id', $id)->update(['first_name' => $request->first_name, 'last_name' => $request->last_name]);
        }

        /* send Mail to Projects Team and CS Team about entity type change - to update in everee */
        $entityType = $old_data['entity_type'];
        $newEntityType = $request->entity_type;

        if (! empty($entityType) && ! empty($newEntityType) && $entityType != $newEntityType) {
            $newData = [
                'entity_type' => $newEntityType,
                'social_security_no' => @$request->social_sequrity_no,
                'business_name' => @$request->business_name,
                'business_type' => @$request->business_type,
                'business_ein' => @$request->business_ein,
            ];
            $this->sendMailToProjectsTeamAndCSTeam($data, $newData);
        }

        $uid = $data->id;
        $aveyoid = $data->aveyo_hs_id;
        $personalDetail = json_decode($request['employee_personal_detail']);
        $additionalInfo = json_decode($request['additional_info_for_employee_to_get_started']);
        $data->first_name = $request['first_name'];
        $data->middle_name = $request['middle_name'];
        $data->last_name = $request['last_name'];
        $data->sex = ($request['sex'] === 'not to be mention') ? 'other' : $request['sex'];
        // $data->sex = $request['sex'];
        $data->dob = $request['dob'];
        $data->mobile_no = $request['mobile_no'];
        $data->recruiter_id = $request['recruiter_id'];
        $data->email = $request['email'];
        $data->work_email = $request['work_email'];
        $data->home_address = $request['home_address'];
        $data->city_id = $request['city_id'];
        $data->state_id = $request['state_id'];
        $data->zip_code = $request['zip_code'];
        $data->name_of_bank = $request['name_of_bank'];
        $data->routing_no = $request['routing_no'];
        $data->tax_information = $request['tax_information'];
        $data->type_of_account = $request['type_of_account'];
        $data->social_sequrity_no = $request['social_sequrity_no'];
        $data->account_no = $request['account_no'];
        $data->account_name = $request['account_name'];
        $data->confirm_account_no = $request['confirm_account_no'];
        $data->emergency_contact_name = $request['emergency_contact_name'];
        $data->emergency_phone = $request['emergency_phone'];
        $data->emergency_contact_relationship = $request['emergency_contact_relationship'];
        $data->emergrncy_contact_address = $request['emergrncy_contact_address'];
        $data->emergrncy_contact_city = $request['emergrncy_contact_city'];
        $data->emergrncy_contact_state = $request['emergrncy_contact_state'];
        $data->emergrncy_contact_zip_code = $request['emergrncy_contact_zip_code'];
        $data->additional_info_for_employee_to_get_started = isset($additionalInfo) ? $additionalInfo : null;
        $data->employee_personal_detail = isset($personalDetail) ? $personalDetail : null;
        $data->entity_type = isset($request['entity_type']) ? $request['entity_type'] : '';
        $data->business_name = isset($request['business_name']) ? $request['business_name'] : '';
        $data->business_type = isset($request['business_type']) ? $request['business_type'] : '';
        $data->business_ein = isset($request['business_ein']) ? $request['business_ein'] : '';
        $data->employee_admin_only_fields = isset($request['employee_admin_only_fields']) ? json_decode($request['employee_admin_only_fields']) : null;

        // Added business address as per requirements of SIM-6582
        if (isset($request['business_name']) && ! empty($request['business_name'])) {
            UsersBusinessAddress::updateOrCreate(
                ['user_id' => $request->user_id],
                [
                    'business_address' => isset($request['business_address']) ? $request['business_address'] : null,
                    'business_address_line_1' => isset($request['business_address_line_1']) ? $request['business_address_line_1'] : null,
                    'business_address_line_2' => isset($request['business_address_line_2']) ? $request['business_address_line_2'] : null,
                    'business_address_state' => isset($request['business_address_state']) ? $request['business_address_state'] : null,
                    'business_address_city' => isset($request['business_address_city']) ? $request['business_address_city'] : null,
                    'business_address_zip' => isset($request['business_address_zip']) ? $request['business_address_zip'] : null,
                    'business_address_lat' => isset($request['business_address_lat']) ? $request['business_address_lat'] : null,
                    'business_address_long' => isset($request['business_address_long']) ? $request['business_address_long'] : null,
                    'business_address_timezone' => isset($request['business_address_timezone']) ? $request['business_address_timezone'] : null,
                ]
            );

        }
        // end business address
        $data->home_address_line_1 = isset($request['home_address_line_1']) ? $request['home_address_line_1'] : $data->home_address_line_1;
        $data->home_address_line_2 = isset($request['home_address_line_2']) ? $request['home_address_line_2'] : $data->home_address_line_2;
        $data->home_address_state = isset($request['home_address_state']) ? $request['home_address_state'] : $data->home_address_state;
        $data->home_address_city = isset($request['home_address_city']) ? $request['home_address_city'] : $data->home_address_city;
        $data->home_address_zip = isset($request['home_address_zip']) ? $request['home_address_zip'] : $data->home_address_zip;
        $data->home_address_lat = isset($request['home_address_lat']) ? $request['home_address_lat'] : $data->home_address_lat;
        $data->home_address_long = isset($request['home_address_long']) ? $request['home_address_long'] : $data->home_address_long;
        $data->home_address_timezone = isset($request['home_address_timezone']) ? $request['home_address_timezone'] : $data->home_address_timezone;
        $data->emergency_address_line_1 = isset($request['emergency_address_line_1']) ? $request['emergency_address_line_1'] : $data->emergency_address_line_1;
        $data->emergency_address_line_2 = isset($request['emergency_address_line_2']) ? $request['emergency_address_line_2'] : $data->emergency_address_line_2;
        $data->emergency_address_lat = isset($request['emergency_address_lat']) ? $request['emergency_address_lat'] : $data->emergency_address_lat;
        $data->emergency_address_long = isset($request['emergency_address_long']) ? $request['emergency_address_long'] : $data->emergency_address_long;
        $data->emergency_address_timezone = isset($request['emergency_address_timezone']) ? $request['emergency_address_timezone'] : $data->emergency_address_timezone;
        $data->action_item_status = 0;

        // Use database transaction to ensure atomicity (either everything succeeds or everything fails)
        DB::beginTransaction();
        try {
            // Save user data
            $data->save();

            // Process Flexible IDs - Batch operation to support shuffling
            // Collect all flexible ID updates
            $flexibleIdUpdates = [];
            if ($request->has('flexi_id_1')) {
                $flexibleIdUpdates[UserFlexibleId::TYPE_FLEXI_ID_1] = $request->flexi_id_1;
            }
            if ($request->has('flexi_id_2')) {
                $flexibleIdUpdates[UserFlexibleId::TYPE_FLEXI_ID_2] = $request->flexi_id_2;
            }
            if ($request->has('flexi_id_3')) {
                $flexibleIdUpdates[UserFlexibleId::TYPE_FLEXI_ID_3] = $request->flexi_id_3;
            }

            // Batch update all flexible IDs (supports shuffling)
            if (! empty($flexibleIdUpdates)) {
                $data->setFlexibleIds($flexibleIdUpdates);
            }

            // If we get here, everything succeeded - commit the transaction
            DB::commit();

        } catch (\Illuminate\Database\QueryException $e) {
            // Rollback transaction on any database error
            DB::rollback();

            // Handle database constraint violations for flexible IDs
            if ($e->getCode() == 23000) { // Integrity constraint violation
                return response()->json([
                    'ApiName' => 'update-user-profile',
                    'status' => false,
                    'message' => 'Flexible ID validation error: One or more flexible IDs are already in use by another user. Each flexible ID must be globally unique.',
                    'error' => [
                        'flexible_ids' => ['One or more flexible IDs are already in use. Please use unique values.'],
                    ],
                ], 400);
            }
            throw $e; // Re-throw if not a constraint violation
        } catch (\Exception $e) {
            // Rollback transaction on any other error
            DB::rollback();
            throw $e;
        }

        /* Update data in sclearance table if exists */
        $sclearanceIds = SClearanceTurnScreeningRequestList::where(['user_type_id' => $data->id, 'user_type' => 'Hired'])->pluck('id')->toArray();

        if (! empty($sclearanceIds)) {
            SClearanceTurnScreeningRequestList::whereIn('id', $sclearanceIds)->update([
                'first_name' => $request['first_name'],
                'last_name' => $request['last_name'],
                'email' => $request['email'],
            ]);
        }
        /* Update data in sclearance table if exists */

        if (isset($request['entity_type']) && ($request['entity_type'] != $old_data['entity_type'] || (isset($request['business_name']) && $request['business_name'] != $old_data['business_name']) || (isset($request['business_type']) && $request['business_type'] != $old_data['business_type']) || (isset($request['business_ein']) && $request['business_ein'] != $old_data['business_ein']) || (isset($request['social_sequrity_no']) && $request['social_sequrity_no'] != $old_data['social_sequrity_no']))) {
            if (config('app.domain_name') != 'dev' && config('app.domain_name') != 'demo' && config('app.domain_name') != 'testing' && config('app.domain_name') != 'preprod' && ! strpos(url(''), '127.0.0.1') && ! strpos(url(''), 'localhost')) {
                $this->createJiraTicket($request, $old_data);
            }
        }

        // onboarding and lead email and mobile number update start
        // if(!empty($OnboardingEmployees)){
        //     $updateOnboarding = $OnboardingEmployees->update(['email'=>$data->email,'mobile_no'=>$data->mobile_no]);
        // }

        // if(!empty($leadEmployees)){
        //     $updateLead = $leadEmployees->update(['email'=>$data->email,'mobile_no'=>$data->mobile_no]);
        // }

        // onboarding and lead email and mobile number update code end
        $recruiter = User::select('first_name', 'last_name')->where('id', $data->recruiter_id)->first();
        $state = State::where('id', $data->state_id)->first();
        $city = Cities::where('id', $data->city_id)->first();
        $data = $data->find($request->id);
        if ($data && $state) {
            $res = User::where('id', $request->user_id)->select('everee_workerId', 'updated_at')->first();
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {
                $this->update_emp_personal_info($data, $state);  // update emp in everee
            }
        }

        $CrmData = Crms::where('id', 2)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 2)->first();
        if (! empty($CrmData) && ! empty($CrmSetting) && ! empty($aveyoid)) {
            // $token ="pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641";
            $val = json_decode($CrmSetting['value']);
            $token = $val->api_key;

            $Hubspotdata['properties'] = [
                'first_name' => isset($data->first_name) ? $data->first_name : null,
                'last_name' => isset($data->last_name) ? $data->last_name : null,
                'sales_name' => isset($data->first_name) ? $data->first_name.' '.$data->last_name : null,
                'email' => isset($data->email) ? $data->email : null,
                'work_email' => isset($data->work_email) ? $data->work_email : null,
                'address' => isset($data->home_address) ? $data->home_address : null,
                'dob' => isset($data->dob) ? $data->dob : null,
                'birthday' => isset($data->dob) ? $data->dob : null,
                'sex' => isset($data->sex) ? $data->sex : null,
                'mobile_no' => isset($data->mobile_no) ? $data->mobile_no : null,
                'state' => isset($state->name) ? $state->name : null,
                'city' => isset($city->name) ? $city->name : null,
                'recruiter_id' => isset($data->recruiter_id) ? $data->recruiter_id : null,
                'recruiter' => isset($recruiter->first_name) ? $recruiter->first_name.' '.$recruiter->last_name : null,
                'zip_code' => isset($data->zip_code) ? $data->zip_code : null,

            ];
            $update_employees = $this->update_employees($Hubspotdata, $token, $uid, $aveyoid);

        }
        // code for Push Rep Data to HubspotCurrentEnergy
        $integration = Integration::where(['name' => 'Hubspot Current Energy', 'status' => 1])->first();
        $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
        if (! empty($integration) && ! empty($hubspotCurrentEnergyToken) && ! empty($aveyoid)) {
            // $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
            $hubspotCurrentEnergyData['properties'] = [
                'address' => isset($data->home_address) ? $data->home_address : null,
                'date_of_birth' => isset($data->dob) ? $data->dob : null,
                'gender' => isset($data->sex) ? $data->sex : null,
                'phone' => isset($data->mobile_no) ? $data->mobile_no : null,
                'sales_rep_id' => isset($data->employee_id) ? $data->employee_id : null,
                'contact_status' => 'Active',
                'contact_type' => 'Sales Rep',
            ];
            $this->updateContactForHubspotCurrentEnergy($hubspotCurrentEnergyData, $hubspotCurrentEnergyToken, $uid, $aveyoid);
        }

        // update reps on field routes
        $integration = Integration::where(['name' => 'FieldRoutes', 'status' => 1])->first();
        if (! empty($integration)) {

            $enc_value = openssl_decrypt(
                $integration->value,
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
            $aveyo_hs_id = isset($data->aveyo_hs_id) ? $data->aveyo_hs_id : null;

            $employeeData = [
                'fname' => isset($data->first_name) ? $data->first_name : null,
                'lname' => isset($data->last_name) ? $data->last_name : null,
                'phone' => isset($data->mobile_no) ? $data->mobile_no : null,
                'sequifi_id' => isset($data->employee_id) ? $data->employee_id : null,
                'employeeID' => isset($data->aveyo_hs_id) ? $data->aveyo_hs_id : null,
                'active' => 1,
            ];
            $this->updateEmployeeForFieldRoutes($employeeData, $authenticationKey, $authenticationToken, $baseURL, $data->id, $aveyo_hs_id);
        }
        // End reps on field routes

        // Check if HighLevel integration is enabled
        $integration = Integration::where(['name' => 'GoHighLevel', 'status' => 1])->first();
        if ($integration) {
            // Update contact in HighLevel
            if (config('services.highlevel.token')) {
                // Get updated user data with fresh data
                // $updatedUserData = User::find($uid);
                $updatedUserData = User::with('office', 'managerDetail')->where('id', $uid)->first();
                $office = null;
                $manager = null;
                if ($updatedUserData) {
                    $office = $updatedUserData->office;
                    $manager = $updatedUserData->managerDetail;
                }

                $contactData = [
                    'locationId' => config('services.highlevel.location_id'),
                    'email' => $updatedUserData->email ?? null,
                    'firstName' => $updatedUserData->first_name ?? null,
                    'lastName' => $updatedUserData->last_name ?? null,
                    'phone' => $updatedUserData->mobile_no ?? null,
                    'address1' => $updatedUserData->home_address ?? null,
                    'city' => isset($city->name) ? $city->name : null,
                    'state' => isset($state->name) ? $state->name : null,
                    'postalCode' => $updatedUserData->zip_code ?? null,
                    'dateOfBirth' => $updatedUserData->dob ?? null,
                    'customFields' => [
                        ['key' => 'sequifi_id', 'value' => $updatedUserData->employee_id ?? null],
                        ['key' => 'status', 'value' => 'Active'],
                        ['key' => 'office_id', 'value' => isset($office) ? $office->id : null],
                        ['key' => 'office_name', 'value' => isset($office) ? $office->office_name : null],
                        ['key' => 'manager_id', 'value' => isset($manager) ? $manager->id : null],
                        ['key' => 'manager_name', 'value' => isset($manager) ? $manager->first_name ?? ''.' '.$manager->last_name ?? '' : null],
                        ['key' => 'manager_email', 'value' => isset($manager) ? $manager->email ?? '' : null],
                    ],
                ];

                $highLevelResponse = $this->upsertHighLevelContact($contactData);
                try {
                    InterigationTransactionLog::create([
                        'interigation_name' => 'HighLevelRepPush Upsert',
                        'api_name' => 'Upsert Rep Data',
                        'payload' => json_encode($contactData),
                        'response' => json_encode($highLevelResponse),
                        'url' => 'https://services.leadconnectorhq.com/contacts/upsert',
                    ]);
                } catch (\Exception $e) {
                    // Log::error('Error upserting HighLevel contact: ' . $e->getMessage());
                }

                // If we got a successful response with a contact ID, save it to the user record
                if ($highLevelResponse && isset($highLevelResponse['contact']['id'])) {
                    $contactId = $highLevelResponse['contact']['id'];

                    // Update the user record with the HighLevel contact ID if it's different
                    if (empty($updatedUserData->aveyo_hs_id) || $updatedUserData->aveyo_hs_id != $contactId) {
                        User::where('id', $uid)->update([
                            'aveyo_hs_id' => $contactId,
                        ]);

                        \Illuminate\Support\Facades\Log::info('Updated user with HighLevel contact ID from profile update', [
                            'user_id' => $uid,
                            'aveyo_hs_id' => $contactId,
                        ]);
                    }
                }
            }
        }
        // End HighLevel integration

        // EspQuickBase Rep Data Push integration (Silent - fire and forget)
        $this->espQuickBaseService->sendUserDataSilently($uid, 'user_profile_update');
        // End EspQuickBase Rep Data Push integration

        // Onyx Rep Data Push integration (Silent - fire and forget)
        $this->onyxRepDataPushService->sendUserDataSilently($uid, 'rep_update');
        // End Onyx Rep Data Push integration

        $additionalRecuiterData = AdditionalRecruiters::where('user_id', $request->user_id)->get();

        $data1 = OnboardingEmployees::find($request->user_id);
        $additionalRecuiter = AdditionalRecruiters::where('hiring_id', $request->user_id)->delete();
        if (! empty($additionalRecuiterData)) {
            $system_amount = $request->system_type;
            $recruiter_id = $request->additional_recruiter_id;
            $additional_locations = $request->additional_locations;

            if ($system_amount) {
                foreach ($system_amount as $key => $value) {
                    $val = AdditionalRecruiters::create([
                        'hiring_id' => $data1->id,
                        'recruiter_id' => $recruiter_id[$key],
                        // 'system_per_kw_amount' => $value,
                        'system_type' => $value,
                        'user_id' => $request->user_id,
                    ]);
                }
            }

        }
        $check = User::where('id', $request->user_id)->first();
        $new_data = $check->toArray();

        $batch_no = UserProfileHistory::where([
            'user_id' => $request->user_id,
            'updated_by' => Auth()->user()->id,
        ])->max('batch_no') + 1;

        // dd($batch_no);

        $oldVals = [];
        $newVals = [];
        $personalDetail = is_array($personalDetail) ? $personalDetail : [$personalDetail];
        if (! empty($personalDetail)) {
            foreach ($personalDetail as $array_key => $detail) {
                if (! empty($detail)) {
                    $newVals[$detail->field_name] = (! empty($detail->value)) ? $detail->value : '';
                    $oldVals[$detail->field_name] = '';
                }
            }
        }
        if (! empty($old_data['employee_personal_detail'])) {
            $oldvalues = json_decode($old_data['employee_personal_detail']);
            foreach ($oldvalues as $array_key => $oldvalue) {
                $oldVals[$oldvalue->field_name] = (! empty($oldvalue->value)) ? $oldvalue->value : '';
            }
        }
        foreach ($newVals as $key => $newValue) {
            if ($oldVals[$key] != $newVals[$key]) {

                if ($key == 'dob' || $key == 'date') {
                    $oldVals[$key] = isset($oldVals[$key]) && ($oldVals[$key] != '') ? date('m/d/Y', strtotime($oldVals[$key])) : '';
                    $newVals[$key] = isset($newVals[$key]) && ($newVals[$key] != '') ? date('m/d/Y', strtotime($newVals[$key])) : '';
                }

                UserProfileHistory::create([
                    'user_id' => $request->user_id,
                    'updated_by' => Auth()->user()->id,
                    'field_name' => $key,
                    'old_value' => isset($oldVals[$key]) ? $oldVals[$key] : '',
                    'new_value' => isset($newVals[$key]) ? $newVals[$key] : '',
                    'batch_no' => $batch_no,
                ]);
            }
        }
        $oldInfoVals = [];
        $newAddiVals = [];
        $additionalInfo = is_array($additionalInfo) ? $additionalInfo : [$additionalInfo];
        if (! empty($additionalInfo)) {
            foreach ($additionalInfo as $data_key => $info) {
                if (! empty($info)) {
                    $newAddiVals[$info->field_name] = (! empty($info->value)) ? $info->value : '';
                    $oldInfoVals[$info->field_name] = '';
                }
            }
        }
        if (! empty($old_data['additional_info_for_employee_to_get_started'])) {
            $oldInfoValues = json_decode($old_data['additional_info_for_employee_to_get_started']);
            foreach ($oldInfoValues as $data_key => $oldInValue) {
                $oldInfoVals[$oldInValue->field_name] = (! empty($oldInValue->value)) ? $oldInValue->value : '';
            }
        }
        foreach ($newAddiVals as $key => $newValue) {
            if ($oldInfoVals[$key] != $newAddiVals[$key]) {

                if ($key == 'dob' || $key == 'date') {
                    $oldInfoVals[$key] = isset($oldInfoVals[$key]) && ($oldInfoVals[$key] != '') ? date('m/d/Y', strtotime($oldInfoVals[$key])) : '';
                    $newAddiVals[$key] = isset($newAddiVals[$key]) && ($newAddiVals[$key] != '') ? date('m/d/Y', strtotime($newAddiVals[$key])) : '';
                }

                UserProfileHistory::create([
                    'user_id' => $request->user_id,
                    'updated_by' => Auth()->user()->id,
                    'field_name' => $key,
                    'old_value' => isset($oldInfoVals[$key]) ? $oldInfoVals[$key] : '',
                    'new_value' => isset($newAddiVals[$key]) ? $newAddiVals[$key] : '',
                    'batch_no' => $batch_no,
                ]);
            }
        }

        // $data = Notification::create([
        //     'user_id' => $check->id,
        //     'type' => 'Add Lead',
        //     'description' => 'Add Lead Data by ' . auth()->user()->first_name,
        //     'is_read' => 0,
        // ]);
        // $notificationData = array(
        //     'user_id'      => $check->id,
        //     'device_token' => $check->device_token,
        //     'title'        => 'Add Lead Data.',
        //     'sound'        => 'sound',
        //     'type'         => 'Add Lead',
        //     'body'         => 'Add Lead Data by ' . auth()->user()->first_name,
        // );
        // $this->sendNotification($notificationData);
        $userData = [];
        foreach ($profileData as $key => $value) {
            // return $value;
            if ($key != 'additional_info_for_employee_to_get_started' && $key != 'employee_personal_detail' && $value != $data[$key]) {
                $userData[$key] = $key.' =>'.$data[$key];
            }
        }
        $desc = implode(',', $userData);
        if ($data) {
            $page = 'Setting';
            $action = 'User Profile Update';
            $description = $desc;
            user_activity_log($page, $action, $description);
        }

        // for user_profile history
        $this->userProfileHistory($old_data, $request, $batch_no);

        // notify to user about changes in profile
        // $profileHistory = UserProfileHistory::where([
        //     'user_id' => $request->user_id,
        //     'updated_by' => Auth()->user()->id,
        // ])->orderBy('batch_no','desc')->first();
        // if($profileHistory){
        //     $batch_no = $profileHistory->batch_no;
        // } else {
        //     $batch_no = 0;
        // }

        $check->setAttribute('batch_no', $batch_no);
        // send mail
        $salesData = [];
        // get email template with resolved keys
        $salesData = SequiDocsEmailSettings::profile_or_employment_package_change_notification_email_content($check);

        $salesData['email'] = $request['email'];

        unset($old_data['updated_at']);
        unset($new_data['updated_at']);
        if ($old_data != $new_data) {
            if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                $this->sendEmailNotification($salesData);
            } else {
                $salesData['subject'] = 'User Profile Update';
                $salesData['template'] = view('mail.profileupdate', compact('check'));
                $email_content_response = $this->sendEmailNotification($salesData);
            }
        }

        // code added by anurag
        $responseData = User::where('id', $request->user_id)->with('flexibleIds')->first();

        // Explicitly append flexi_id accessors to this response only (prevents N+1 queries on other endpoints)
        $responseData->append(['flexi_id_1', 'flexi_id_2', 'flexi_id_3']);

        // Added business address fields as per requirements of SIM-6582
        $address_data = UsersBusinessAddress::where('user_id', $request->user_id)->first();

        $responseData->business_address = $address_data->business_address ?? null;
        $responseData->business_address_line_1 = $address_data->business_address_line_1 ?? null;
        $responseData->business_address_line_2 = $address_data->business_address_line_2 ?? null;
        $responseData->business_address_state = $address_data->business_address_state ?? null;
        $responseData->business_address_city = $address_data->business_address_city ?? null;
        $responseData->business_address_zip = $address_data->business_address_zip ?? null;
        $responseData->business_address_lat = $address_data->business_address_lat ?? null;
        $responseData->business_address_long = $address_data->business_address_long ?? null;
        $responseData->business_address_timezone = $address_data->business_address_timezone ?? null;
        // end business address

        $IntegrationCheck = Integration::where(['name' => 'Solerro', 'status' => 1])->first();
        if ($IntegrationCheck) {
            $solerroData = [
                'sequifi_id' => $responseData->id,
                'employee_id' => $responseData->employee_id,
                'first_name' => $responseData->first_name,
                'last_name' => $responseData->last_name,
                'email' => $responseData->email,
                'mobile_no' => $responseData->mobile_no,
            ];
            // Call the trait method to send the request to the API
            $sendEmployeeRequestresponse = $this->SolerroSendEmployeeRequest($solerroData);
        }
        // end code

        return response()->json([
            'ApiName' => 'update-user-profile',
            'status' => true,
            'message' => 'User profile updated successfully.',
            'data' => $responseData,
        ], 200);

    }

    protected function createJiraTicket(Request $request, $oldData): array
    {
        try {
            $summary = ucfirst($oldData['first_name']).' '.$oldData['last_name']."'s tax information has been changed by ".ucfirst(auth()->user()->first_name).' '.auth()->user()->last_name.' And new information is as below.';
            $description = 'Entity Type: '.$oldData['entity_type'].' (Old)'.' '.$request['entity_type']." (New)\n";
            if ($request['entity_type'] == 'business') {
                $description .= 'Business Name: '.$oldData['business_name'].' (Old)'.' '.$request['business_name']." (New)\n";
                $description .= 'Business Type: '.$oldData['business_type'].' (Old)'.' '.$request['business_type']." (New)\n";
                $description .= 'EIN: '.$oldData['business_ein'].' (Old)'.' '.$request['business_ein']." (New)\n";
            } else {
                $description .= 'Social Security Number: '.$oldData['social_sequrity_no'].' (Old)'.' '.$request['social_sequrity_no']." (New)\n";
            }
            $description .= 'Domain: '.config('app.domain_name', url(''));
            $param = [
                'fields' => [
                    'summary' => $summary,
                    'description' => $description,
                    'issuetype' => [
                        'id' => Ticket::JIRA_ISSUE_TYPE_Id,
                    ],
                    'assignee' => [
                        'id' => Ticket::JIRA_ASSIGNEE_ID,
                    ],
                    'labels' => [
                        'From-'.config('app.domain_name', url('')).'-Tax_info_updates',
                    ],
                    'project' => [
                        'id' => Ticket::JIRA_PROJECT_Id,
                    ],
                    'priority' => [
                        'id' => Ticket::JIRA_HIGH_PRIORITY_Id,
                    ],
                    'parent' => [
                        'id' => Ticket::JIRA_TAX_TYPE_PARENT_ID,
                    ],
                ],
            ];
            $ticket = app('TicketController')->make(app()->getNamespace().\Http\Controllers\API\TicketSystem\Ticket\TicketController::class)->createTicketOnJiraCloud($param);
            //            $ticket = Http::withBasicAuth(env('JIRA_EMAIL'), env('JIRA_SECRET_KEY'))->withHeaders(['Accept' => 'application/json'])->post(env('JIRA_API_BASE_URL') . 'rest/api/2/issue', $param);
            if ($ticket->status() != 201) {
                return ['success' => false, 'message' => 'Error On Jira Create API!!'];
            }

            return ['success' => true, 'message' => 'Jira Ticket Created Successfully!!'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage().' '.$e->getLine()];
        }
    }

    public function userProfileHistory($old_data, $new_data, $batch_no = 0)
    {
        foreach ($old_data as $key => $old) {
            if ($key == 'everee_json_response' || $key == 'employee_personal_detail' || $key == 'additional_info_for_employee_to_get_started') {
                continue;
            }
            if (isset($new_data[$key])) {
                $old_value = isset($old_data->$key) ? $old_data->$key : $old_data[$key];
                $new_value = isset($new_data->$key) ? $new_data->$key : $new_data[$key];

                if ($key == 'dob' || $key == 'date') {
                    $old_value = isset($old_value) ? date('m/d/Y', strtotime($old_value)) : '';
                    $new_value = isset($new_value) ? date('m/d/Y', strtotime($new_value)) : '';
                }

                if ($old_value !== $new_value) {
                    UserProfileHistory::create([
                        'user_id' => $old_data['id'],
                        'updated_by' => Auth()->user()->id,
                        'field_name' => $key,
                        'old_value' => $old_value,
                        'new_value' => $new_value,
                        'batch_no' => $batch_no,
                    ]);
                }
            }
        }
    }

    public function updateUserAccountStatus(request $request): JsonResponse
    {
        // dd($request);
        $data = User::with('state')->where('id', $request->user_id)->first();

        if ($data) {
            if ($request->type == 'dismiss') {
                $payroll = Payroll::where(['user_id' => $request->user_id, 'status' => 1])->first();
                if ($payroll && $request->value == 1) {

                    return response()->json([
                        'ApiName' => 'updateUserAccountStatus',
                        'status' => false,
                        'message' => 'Employee have some payroll values you can not dismiss.',
                    ], 400);

                }
                if ($data->dismiss == 0 && $request->value == 1) {
                    UserProfileHistory::create([
                        'user_id' => $request->user_id,
                        'updated_by' => Auth()->user()->id,
                        'field_name' => 'dismiss',
                        'old_value' => 'Dismiss',
                        'new_value' => 'Enable',
                    ]);
                }
                if ($data->dismiss == 1 && $request->value == 0) {
                    UserProfileHistory::create([
                        'user_id' => $request->user_id,
                        'updated_by' => Auth()->user()->id,
                        'field_name' => 'dismiss',
                        'old_value' => 'Enable',
                        'new_value' => 'Dismiss',
                    ]);
                }
                $data->status_id = $request->value == 0 ? 1 : 2;
                $data->dismiss = $request->value;
                // update status in hubspot
                $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                if (! empty($CrmData) && ! empty($CrmSetting)) {
                    $val = json_decode($CrmSetting['value']);
                    $token = $val->api_key;
                    $user = User::where('id', $data->id)->first();
                    if (! empty($user->aveyo_hs_id)) {
                        if ($data->dismiss == 1) {
                            $Hubspotdata['properties'] = ['status' => 'Dismiss'];
                        } else {
                            $Hubspotdata['properties'] = ['status' => 'Active'];
                        }
                        $this->update_employees($Hubspotdata, $token, $user->id, $user->aveyo_hs_id);
                    }
                }
                $integration = Integration::where(['name' => 'Hubspot Current Energy', 'status' => 1])->first();
                $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                if (! empty($integration) && ! empty($hubspotCurrentEnergyToken)) {
                    // $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                    if (! empty($user->aveyo_hs_id)) {
                        if ($data->dismiss == 1) {
                            $hubspotCurrentEnergyData['properties'] = ['contact_status' => 'Dismiss'];
                        } else {
                            $hubspotCurrentEnergyData['properties'] = ['contact_status' => 'Active'];
                        }
                    }
                    $this->updateContactForHubspotCurrentEnergy($hubspotCurrentEnergyData, $hubspotCurrentEnergyToken, $user->id, $user->aveyo_hs_id);
                }

                // onboarding on everee when user enabled
                if ($data->dismiss == 0 && ($data->everee_workerID == null || $data->everee_workerID == '')) {
                    $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                    if ($CrmData) {
                        $this->update_emp_personal_info($data, $data->state);  // update emp in everee
                    }
                }
                // end onboarding on everee when user enabled

            } elseif ($request->type == 'stop_payroll') {
                if ($data->stop_payroll == 1 && $request->value == 0) {
                    $this->updatePayrollData($data);
                    UserProfileHistory::create([
                        'user_id' => $request->user_id,
                        'updated_by' => Auth()->user()->id,
                        'field_name' => 'payroll_status',
                        'old_value' => 'Stop payroll',
                        'new_value' => 'Start payroll',
                    ]);
                }
                if ($data->stop_payroll == 0 && $request->value == 1) {
                    $this->updatePayrollStop($data);
                    UserProfileHistory::create([
                        'user_id' => $request->user_id,
                        'updated_by' => Auth()->user()->id,
                        'field_name' => 'payroll_status',
                        'old_value' => 'Start payroll',
                        'new_value' => 'Stop payroll',
                    ]);
                }

                $data->status_id = 3;
                $data->stop_payroll = $request->value;

            } elseif ($request->type == 'disable_login') {
                // Store old value before updating
                $old_disable_login = $data->disable_login;

                $data->status_id = 6;
                $data->disable_login = $request->value;

                // Log the change only if there's an actual change
                if ($old_disable_login != $request->value) {
                    if ($request->value == 1) {
                        // Changing TO disabled (0 -> 1): Enable to Disable
                        UserProfileHistory::create([
                            'user_id' => $request->user_id,
                            'updated_by' => Auth()->user()->id,
                            'field_name' => 'disable_login',
                            'old_value' => 'Enable login',
                            'new_value' => 'Disable login',
                        ]);
                    } else {
                        // Changing TO enabled (1 -> 0): Disable to Enable
                        UserProfileHistory::create([
                            'user_id' => $request->user_id,
                            'updated_by' => Auth()->user()->id,
                            'field_name' => 'disable_login',
                            'old_value' => 'Disable login',
                            'new_value' => 'Enable login',
                        ]);
                    }
                }
            } elseif ($request->type == 'delete') {
                $data->status_id = 4;
            } else {
                $data->status_id = 1;
            }
            $data->save();
            if ($request->type == 'dismiss' || $request->type == 'disable_login' || ($request->type == 'delete')) {
                $data = DB::table('personal_access_tokens')->where('tokenable_id', $request->user_id)->delete();
                // dd($data);
            }

            return response()->json([
                'ApiName' => 'updateUserAccountStatus',
                'status' => true,
                'message' => 'Update account status successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'updateUserAccountStatus',
                'status' => true,
                'message' => 'Invalid User ID.',
            ], 400);
        }

    }

    // update hubspot data start

    public function update_employees($Hubspotdata, $token, $user_id, $aveyoid)
    {
        // $url = "https://api.hubapi.com/crm/v3/objects/contacts";
        $url = "https://api.hubapi.com/crm/v3/objects/sales/$aveyoid";
        $Hubspotdata = json_encode($Hubspotdata);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $curl_response = $this->curlRequestDataUpdate($url, $Hubspotdata, $headers, 'PATCH');

        $resp = json_decode($curl_response, true);

        if (count($resp) > 0) {
            $hs_object_id = $resp['properties']['hs_object_id'] ?? 0;
            // $email = $resp['properties']['email'];

            $updateuser = User::where('id', $user_id)->first();
            if ($updateuser) {
                $updateuser->aveyo_hs_id = $hs_object_id;
                $updateuser->save();
            }
        }

    }

    public function curlRequestDataUpdate($url, $Hubspotdata, $headers, $method = 'PATCH')
    {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $Hubspotdata,
            CURLOPT_HTTPHEADER => $headers,

        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }
    // update hubspot data end

    // End code by Nikhil
    public function getAuditLog(Request $req): JsonResponse
    {
        $id = $req->id;
        if (! empty($req->perpage)) {
            $perpage = $req->perpage;
        } else {
            $perpage = 10;
        }
        $userActivityLogs = DB::table('activity_log')->where('subject_id', $id)->where('subject_type', \App\Models\User::class)->orderBy('id', 'DESC')->get();
        if (count($userActivityLogs) > 0) {
            $userLog = [];
            foreach ($userActivityLogs as $key => $logs) {
                $action = DB::table('users')->where('id', $logs->causer_id)->first();
                if ($logs->subject_type == \App\Models\User::class) {
                    $change = DB::table('users')->where('id', $logs->subject_id)->first();
                    $emp = $change->first_name.' '.$change->last_name;
                }
                $userLog[$key]['action_user_id'] = $logs->causer_id;
                $userLog[$key]['action_by'] = $action->first_name.' '.$action->last_name;
                $userLog[$key]['description'] = $logs->description;
                $userLog[$key]['subject'] = $logs->subject_type;
                $userLog[$key]['user_id'] = $logs->subject_id;
                $userLog[$key]['user_name'] = $emp;
                $userLog[$key]['user_event'] = $logs->event;
                $userLog[$key]['properties'] = json_decode($logs->properties);
                $userLog[$key]['created_date'] = $logs->created_at;
            }
            $response = paginate($userLog, $perpage);

            return response()->json([
                'ApiName' => 'Audit-user-log',
                'status' => true,
                'message' => 'Audit user log open successfully.',
                'data' => $response,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Audit-user-log',
                'status' => false,
                'message' => 'Invalid User ID.',
            ], 400);
        }
    }

    // public function userProfileActivityLog(Request $request){
    //     $log_data = ActivityLog::where('subject_id',$request->user_id)
    //     //->where('event','updated')
    //     ->select('properties','created_at','event')
    //     ->orderBy('id','asc')
    //     ->get();
    //     $new_array = [];
    //     $old_array = [];
    //     $response = [];
    //     $logdatetime = [];
    //     $action = [];
    //     foreach($log_data as $key => $log){
    //         $datetime = $log->created_at;
    //         $properties = json_decode($log)->properties;
    //         $new_values = isset(json_decode($properties)->attributes)?json_decode($properties)->attributes:'';
    //         $old_values = isset(json_decode($properties)->old)?json_decode($properties)->old:'';
    //         $new_array[$key] = $new_values;
    //         $old_array[$key] = $old_values;
    //         $logdatetime[$key] = $log->created_at;
    //         $action[$key] = $log->event;
    //     }
    //     $i=0;
    //     foreach($new_array as $key => $array){
    //         foreach($array as $array_key => $array_data){
    //             $old_value = !empty($old_array[$key]->$array_key)?$old_array[$key]->$array_key:'null';
    //             $new_value = !empty($new_array[$key]->$array_key)?$new_array[$key]->$array_key:'null';
    //             $logdt = !empty($logdatetime[$i])?$logdatetime[$i]:'empty';
    //             $response[$key]['activity_date'] = $logdt;
    //             $response[$key]['type'] = $action[$i];
    //             if($array_key == 'updated_at' || $array_key == 'password' || $array_key == 'employee_personal_detail' || $array_key == 'additional_info_for_employee_to_get_started' || $old_value == $new_value){
    //                 continue;
    //             }
    //             if($action[$i] == 'created'){
    //                 $response[$key]['user_actions'][] = ['action'=>$new_value ,'modified_key'=>$array_key];
    //             }if($action[$i] == 'deleted'){
    //                 $response[$key]['user_actions'][] = ['action'=>$old_value ,'modified_key'=>$array_key];
    //             }
    //             else{
    //                 $response[$key]['user_actions'][] = ['action'=>$old_value.' to '.$new_value ,'modified_key'=>$array_key];
    //             }

    //             // 'updated '.$array_key.', '.$old_value.' to '.$new_value.' at '.$logdt;
    //         }
    //         $i++;
    //     }
    //     return json_encode($response);
    //     // Log::info(json_encode($response));
    // }

    // public function userProfileActivityLog(Request $request){
    //     $log_data = ActivityLog::where('subject_id',$request->user_id)
    //     //->where('event','updated')
    //     ->select('properties','created_at','event')
    //     ->orderBy('id','asc')
    //     ->get();
    //     $new_array = [];
    //     $old_array = [];
    //     $response = [];
    //     $logdatetime = [];
    //     $action = [];
    //     foreach($log_data as $key => $log){
    //         $datetime = $log->created_at;
    //         $properties = json_decode($log)->properties;
    //         $new_values = isset(json_decode($properties)->attributes)?json_decode($properties)->attributes:'';
    //         $old_values = isset(json_decode($properties)->old)?json_decode($properties)->old:'';
    //         // $new_array[$key] = $new_values;
    //         // $old_array[$key] = $old_values;
    //         $logdatetime = $log->created_at;
    //         $action = $log->event;
    //         if($action == 'created' || $action == 'updated'){
    //             foreach($new_values as $array_key => $array_data){
    //                 $old_value = !empty($old_values->$array_key)?$old_values->$array_key:'null';
    //                 $new_value = !empty($new_values->$array_key)?$new_values->$array_key:'null';
    //                 $logdttm = !empty($logdatetime)?$logdatetime:'empty';
    //                 if(!empty($logdatetime)){
    //                     $log_date = date('Y-m-d',strtotime($logdatetime));
    //                 }
    //                 $logdttm = !empty($logdatetime)?$logdatetime:'empty';
    //                 if($array_key == 'created_at' || $array_key == 'updated_at' || $array_key == 'password' || $array_key == 'employee_personal_detail' || $array_key == 'additional_info_for_employee_to_get_started' || $old_value == $new_value){
    //                     continue;
    //                 }
    //                 if($action == 'created'){
    //                     $response[$key]['user_actions'][] = ['action'=>$new_value ,'modified_key'=>$array_key];
    //                 }
    //                 else{
    //                     $response[$key]['user_actions'][] = ['action'=>$old_value.' to '.$new_value ,'modified_key'=>$array_key];
    //                 }
    //                 $response[$key]['activity_date'] = $logdttm;
    //                 $response[$key]['type'] = $action;
    //             }
    //         }
    //         if($action == 'deleted'){
    //             foreach($old_values as $array_key => $array_data){
    //                 $old_value = !empty($old_values->$array_key)?$old_values->$array_key:'null';
    //                 $logdttm = !empty($logdatetime)?$logdatetime:'empty';
    //                 $response[$key]['activity_date'] = $logdttm;
    //                 $response[$key]['type'] = $action;
    //                 if($array_key == 'created_at' || $array_key == 'updated_at' || $array_key == 'password' || $array_key == 'employee_personal_detail' || $array_key == 'additional_info_for_employee_to_get_started' || $old_value == $new_value){
    //                     continue;
    //                 }
    //                 $response[$key]['user_actions'][] = ['action'=>$old_value ,'modified_key'=>$array_key];
    //             }
    //         }

    //     }

    //     return json_encode($response);
    //     // Log::info(json_encode($response));
    // }

    public function userProfileActivityLog(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $data = UserProfileHistory::with('updater:id,first_name,last_name,image,is_super_admin,is_manager,position_id,sub_position_id')->where('user_id', $request->user_id);

        // if ($request->has('search') && !empty($request->input('search'))) {
        //     $data->whereHas('updater',function ($query) use ($request) {
        //             $query->where('first_name' , 'like', '%' . $request->input('search') . '%')
        //             ->orWhere('last_name', 'like', '%' . $request->input('search') . '%')
        //             ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%' . $request->input('search') . '%'])
        //             ->orWhere('image', 'like', '%' . $request->input('search') . '%');
        //         });
        //     }
        // $log_data = UserProfileHistory::with('updater:id,first_name,last_name,image')->where('user_id',$request->user_id)

        if ($startDate != '' && $endDate != '') {
            $data = $data->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
        }
        if ($request->has('search') && ! empty($request->input('search'))) {
            $data->where(function ($query) use ($request) {
                $query->where('field_name', 'LIKE', '%'.$request->input('search').'%');
            });
        }

        $log_data = $data->get()->toArray();
        // return $log_data;
        $result = [];
        $user_actions = [];
        $actions = [];
        // $log_data = json_decode($log_data);
        // $commission_histories = UserCommissionHistory::with('updater:id,first_name,last_name,image')
        // ->where('user_id',$request->user_id)
        // ->orderBy('updated_at','asc')
        // ->get()->toArray();
        // foreach($commission_histories as $comm_history){
        //     array_push($log_data,[
        //         'field_name'=>'Commission',
        //         'updated_at' => $comm_history['updated_at'],
        //         'old_value' => $comm_history['old_commission'],
        //         'new_value' => $comm_history['commission'],
        //         'position_id' => $comm_history['position_id'],
        //         'effective_date' => $comm_history['commission_effective_date'],
        //         'updater' => $comm_history['updater'],
        //     ]);
        // }

        // $override_histories = UserOverrideHistory::with('updater:id,first_name,last_name,image')->where('user_id',$request->user_id)->orderBy('updated_at','asc')->get()->toArray();
        // foreach($override_histories as $over_history){
        //     array_push($log_data,[
        //         'field_name'=>'Direct Overrides',
        //         'updated_at' => $over_history['updated_at'],
        //         'old_value' => $over_history['old_direct_overrides_amount'].' '.$over_history['direct_overrides_type'],
        //         'new_value' => $over_history['direct_overrides_amount'].' '.$over_history['old_direct_overrides_type'],
        //         'effective_date' => $over_history['override_effective_date'],
        //         'updater' => $over_history['updater'],
        //     ]);
        //     array_push($log_data,[
        //         'field_name'=>'Indirect Overrides',
        //         'updated_at' => $over_history['updated_at'],
        //         'old_value' => $over_history['old_indirect_overrides_amount'].' '.$over_history['old_indirect_overrides_type'],
        //         'new_value' => $over_history['indirect_overrides_amount'].' '.$over_history['indirect_overrides_type'],
        //         'effective_date' => $over_history['override_effective_date'],
        //         'updater' => $over_history['updater'],
        //     ]);
        //     array_push($log_data,[
        //         'field_name'=>'Office Overrides',
        //         'updated_at' => $over_history['updated_at'],
        //         'old_value' => $over_history['old_office_overrides_amount'].' '.$over_history['old_office_overrides_type'],
        //         'new_value' => $over_history['office_overrides_amount'].' '.$over_history['office_overrides_type'],
        //         'effective_date' => $over_history['override_effective_date'],
        //         'updater' => $over_history['updater'],
        //     ]);
        //     array_push($log_data,[
        //         'field_name'=>'Office Stack Overrides',
        //         'updated_at' => $over_history['updated_at'],
        //         'old_value' => $over_history['old_office_stack_overrides_amount'],
        //         'new_value' => $over_history['office_stack_overrides_amount'],
        //         'effective_date' => $over_history['override_effective_date'],
        //         'updater' => $over_history['updater'],
        //     ]);

        // }

        // $redline_histories = UserRedlines::with('updater:id,first_name,last_name,image')->where('user_id',$request->user_id)->orderBy('updated_at','asc')->get()->toArray();
        // foreach($redline_histories as $redline_history){
        //     array_push($log_data,[
        //         'field_name'=>'Redline',
        //         'updated_at' => $redline_history['updated_at'],
        //         'old_value' => $redline_history['old_redline'].' '.$redline_history['old_redline_type'].' '.$redline_history['old_redline_amount_type'],
        //         'new_value' => $redline_history['redline'].' '.$redline_history['redline_type'].' '.$redline_history['redline_amount_type'],
        //         'position_id' => $redline_history['position_type'],
        //         'effective_date' => $redline_history['start_date'],
        //         'updater' => $redline_history['updater']
        //     ]);
        // }

        // return $log_data;
        foreach ($log_data as $data) {
            $created_date = date('Y-m-d', strtotime($data['updated_at']));
            if (trim($data['old_value']) != trim($data['new_value'])) {
                $data['updater']['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$data['updater']['image']);
                if ($data['field_name'] == 'everee_json_response' || $data['field_name'] == 'employee_personal_detail' || $data['field_name'] == 'additional_info_for_employee_to_get_started') {
                    continue;
                }

                $field_name_arr = [
                    'self_gen_accounts' => 'Self Gen Accounts',
                    'self_gen_type' => 'Self Gen Type',
                    'first_name' => 'First Name',
                    'middle_name' => 'Middle Name',
                    'last_name' => 'Last Name',
                    'sex' => 'Sex',
                    'dob' => 'Dob',
                    'zip_code' => 'Zip Code',
                    'email' => 'Email',
                    'work_email' => 'Work Email',
                    'home_address' => 'Home Address',
                    'home_address_line_1' => 'Home Address Line_1',
                    'home_address_line_2' => 'Home Address Line_2',
                    'home_address_state' => 'Home Address State',
                    'home_address_city' => 'Home Address City',
                    'home_address_zip' => 'Home Address Zip',
                    'home_address_lat' => 'Home Address Lat',
                    'home_address_long' => 'Home Address Long',
                    'emergency_address_line_1' => 'Emergency Address line_1',
                    'emergency_address_line_2' => 'Emergency Address line_2',
                    'emergency_address_lat' => 'Emergency Address Lat',
                    'emergency_address_long' => 'Emergency Address Long',
                    'emergency_contact_name' => 'Emergency Contact Name',
                    'emergency_phone' => 'Emergency Phone',
                    'emergency_contact_relationship' => 'Emergency Relationship',
                    'emergrncy_contact_address' => 'Emergency Contact Address',
                    'emergrncy_contact_zip_code' => 'Emergency Contact Zip Code',
                    'emergrncy_contact_state' => 'Emergency Contact State',
                    'emergrncy_contact_city' => 'Emergency Contact City',
                    'mobile_no' => 'Mobile No',
                    'state_id' => 'State id',
                    'city_id' => 'City id',
                    'location' => 'Location',
                    'stop_payroll' => 'Stop Payroll',
                    'dismiss' => 'Dismiss',
                    'disable_login' => 'Disable Login',
                    'entity_type' => 'Entity Type',
                    'social_sequrity_no' => 'Social Security Number',
                    'business_name' => 'Business Name',
                    'business_type' => 'Business Type',
                    'business_ein' => 'EIN',
                    'name_of_bank' => 'Name of Bank',
                    'account_no' => 'Account Number',
                    'routing_no' => 'Routing Number:',
                    'account_name' => 'Account Name',
                    'account_name' => 'Type of Account',
                    'type_of_account' => 'Account Name',
                    'confirm_account_no' => 'Confirm Account Name',
                    'payroll_status' => 'Payroll Status',
                    'is_manager' => 'Manager',
                    'is_super_admin' => 'Super Admin',
                    'image' => 'Image',
                    'position_id' => 'Position Id',
                    'sub_position_id' => 'Sub Position Id',
                    'reset_password' => 'Reset Password',
                    'status_id' => 'Status Id',
                    'employee_personal_detail' => 'Additional Information',
                    'additional_info_for_employee_to_get_started' => 'Get Started',
                    'manager_id' => 'Manager Id',
                    'recruiter_id' => 'Recruiter Id',
                    'is_manager' => 'Manager',
                    'group_id' => 'Group Id',
                    'home_address_timezone' => 'Home Address Timezone',
                    'emergency_address_timezone' => 'Emergency Address Timezone',
                ];
                if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $data['new_value'])) {
                    $data['new_value'] = date('m/d/Y', strtotime($data['new_value']));
                }
                if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $data['old_value'])) {
                    $data['old_value'] = date('m/d/Y', strtotime($data['old_value']));
                }

                $newValue = $data['new_value'];
                $oldValue = $data['old_value'];

                $unix = strtotime($newValue);
                if ($unix !== false && ! preg_match('/^[a-zA-Z0-9_\-\.@]+$/', $newValue)) {
                    $newValue = date('m/d/Y ', $unix);
                }

                $unix = strtotime($oldValue);
                if ($unix !== false && ! preg_match('/^[+-]?\d+(\.\d+)?$/', $oldValue)) {
                    $oldValue = date('m/d/Y ', $unix);
                }

                // Fix for disable_login historical data bug - swap values for correct display
                if ($data['field_name'] == 'disable_login') {
                    // Swap old_value and new_value to fix historical data bug
                    $temp = $oldValue;
                    $oldValue = $newValue;
                    $newValue = $temp;
                }

                $actions[$created_date][] = [
                    'modified_key' => isset($field_name_arr[$data['field_name']]) ? $field_name_arr[$data['field_name']] : $data['field_name'],
                    'updated_date' => $data['updated_at'],
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'position_id' => isset($data['position_id']) ? $data['position_id'] : null,
                    'effective_date' => isset($data['effective_date']) ? $data['effective_date'] : null,
                    'updater' => isset($data['updater']) ? $data['updater'] : null,
                ];

            }
        }

        foreach ($actions as $key => $action) {

            $result[] = [
                'activity_date' => $key,
                'user_actions' => $action,
                'type' => 'updated',
            ];
        }

        return json_encode($result);
        // Log::info(json_encode($response));
    }

    public function getEmployeeDataByDateType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'effective_date' => 'required|date|date_format:Y-m-d',
            'type' => 'required|in:position,is_manager,manager,additional_location,commission,redline,upfront,withheld,selfgen_commission,override,deduction',
            'is_self_gen' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type ',
                'status' => false,
                'message' => $validator->errors(),
            ], 400);
        }

        if ($request->type == 'position') {
            $organization = UserOrganizationHistory::where('user_id', $request->user_id)->where('effective_date', '<=', $request->effective_date)->orderBy('effective_date', 'DESC')->first();

            if ($organization) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Position Data By Effective Date!!',
                    'data' => [
                        'position_id' => $organization->position_id,
                        'sub_position_id' => $organization->sub_position_id,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Position Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'manager') {
            $manager = UserManagerHistory::where('user_id', $request->user_id)->where('effective_date', '<=', $request->effective_date)->orderBy('effective_date', 'DESC')->first();

            if ($manager && ! empty($manager->manager_id)) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Manager Data By Effective Date!!',
                    'data' => [
                        'manager_id' => $manager->manager_id,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Manager Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'is_manager') {
            $isManager = UserIsManagerHistory::where('user_id', $request->user_id)->where('effective_date', '<=', $request->effective_date)->orderBy('effective_date', 'DESC')->first();

            if ($isManager) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Is Manager Data By Effective Date!!',
                    'data' => [
                        'is_manager' => $isManager->is_manager,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Manager Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'additional_location') {
            $organization = UserOrganizationHistory::where('user_id', $request->user_id)->where('effective_date', '<=', $request->effective_date)->orderBy('effective_date', 'DESC')->first();

            if ($organization && ! empty($organization->manager_id)) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Additional Office Data By Effective Date!!',
                    'data' => [
                        'manager_id' => $organization->manager_id,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Additional Office Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'commission') {
            $commission = UserCommissionHistory::where(['user_id' => $request->user_id, 'self_gen_user' => $request->is_self_gen])->where('commission_effective_date', '<=', $request->effective_date)->orderBy('commission_effective_date', 'DESC')->first();

            if ($commission) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Commission Data By Effective Date!!',
                    'data' => [
                        'commission_amount' => $commission->commission,
                        'commission_amount_type' => $commission->commission_type,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Commission Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'redline') {
            $redLine = UserRedlines::where(['user_id' => $request->user_id, 'self_gen_user' => $request->is_self_gen])->where('start_date', '<=', $request->effective_date)->orderBy('start_date', 'DESC')->first();

            if ($redLine) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'RedLine Data By Effective Date!!',
                    'data' => [
                        'redline_amount' => $redLine->redline,
                        'redline_type' => $redLine->redline_type,
                        'redline_amount_type' => $redLine->redline_amount_type,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'RedLine Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'upfront') {
            $upFront = UserUpfrontHistory::where(['user_id' => $request->user_id, 'self_gen_user' => $request->is_self_gen])->where('upfront_effective_date', '<=', $request->effective_date)->orderBy('upfront_effective_date', 'DESC')->first();

            if ($upFront) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'UpFront Data By Effective Date!!',
                    'data' => [
                        'upfront_amount' => $upFront->upfront_pay_amount,
                        'upfront_amount_type' => $upFront->upfront_sale_type,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'UpFront Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'withheld') {
            $upFront = UserWithheldHistory::where(['user_id' => $request->user_id, 'self_gen_user' => $request->is_self_gen])->where('withheld_effective_date', '<=', $request->effective_date)->orderBy('withheld_effective_date', 'DESC')->first();

            if ($upFront) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'WithHeld Data By Effective Date!!',
                    'data' => [
                        'withheld_amount' => $upFront->withheld_amount,
                        'withheld_amount_type' => $upFront->withheld_type,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'WithHeld Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'selfgen_commission' && $request->is_self_gen == '1') {
            $selfGenCommission = UserSelfGenCommmissionHistory::where(['user_id' => $request->user_id, 'self_gen_user' => $request->is_self_gen])->where('commission_effective_date', '<=', $request->effective_date)->orderBy('commission_effective_date', 'DESC')->first();

            if ($selfGenCommission) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Self Gen Data By Effective Date!!',
                    'data' => [
                        'self_gen_commission_amount' => $selfGenCommission->commission,
                        'self_gen_commission_amount_type' => $selfGenCommission->commission_type,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Self Gen Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'override') {
            $override = UserOverrideHistory::where(['user_id' => $request->user_id, 'self_gen_user' => $request->is_self_gen])->where('override_effective_date', '<=', $request->effective_date)->orderBy('override_effective_date', 'DESC')->first();
            $additional_offices = AdditionalLocations::where(['user_id' => $request->user_id])->pluck('office_id');
            // $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::select(['office_id','office_overrides_amount as overrides_amount','office_overrides_type as overrides_type'])->where(['user_id' => $request->user_id])->where('override_effective_date', '<=', $request->effective_date)->whereIn('office_id',$additional_offices)->groupBy('office_id')->orderByRaw('MAX(override_effective_date) DESC')->get();

            $subquery = DB::table('user_additional_office_override_histories')
                ->select('office_id', DB::raw('MAX(override_effective_date) as max_effective_date'))
                ->where('user_id', $request->user_id)
                ->where('override_effective_date', '<=', $request->effective_date)
                ->whereIn('office_id', $additional_offices)
                ->groupBy('office_id');

            $additionalOfficeOverride = DB::table('user_additional_office_override_histories as uaoh')
                ->select('uaoh.office_id', 'uaoh.office_overrides_amount as overrides_amount', 'uaoh.office_overrides_type as overrides_type')
                ->joinSub($subquery, 'subquery', function ($join) {
                    $join->on('uaoh.office_id', '=', 'subquery.office_id')
                        ->on('uaoh.override_effective_date', '=', 'subquery.max_effective_date');
                })
                ->where('uaoh.user_id', $request->user_id)
                ->whereIn('uaoh.office_id', $additional_offices)
                ->orderBy('uaoh.override_effective_date', 'desc')
                ->get();

            if ($override) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Override Data By Effective Date!!',
                    'data' => [
                        'direct_overrides_amount' => $override->direct_overrides_amount,
                        'direct_overrides_type' => $override->direct_overrides_type,
                        'indirect_overrides_amount' => $override->indirect_overrides_amount,
                        'indirect_overrides_type' => $override->indirect_overrides_type,
                        'office_overrides_amount' => $override->office_overrides_amount,
                        'office_overrides_type' => $override->office_overrides_type,
                        'office_stack_overrides_amount' => $override->office_stack_overrides_amount,
                        'additional_office_overrides' => $additionalOfficeOverride,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Override Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }

        if ($request->type == 'deduction') {
            if (! empty($request->cost_center_id)) {
                $deduction = UserDeductionHistory::where(['user_id' => $request->user_id, 'cost_center_id' => $request->cost_center_id])->where('effective_date', '<=', $request->effective_date)->orderBy('effective_date', 'DESC')->first();
            } else {
                $deduction = UserDeductionHistory::where(['user_id' => $request->user_id])->where('effective_date', '<=', $request->effective_date)->orderBy('effective_date', 'DESC')->first();
            }

            if ($deduction) {
                return response()->json([
                    'ApiName' => 'get-employee-data-by-date-type',
                    'status' => true,
                    'message' => 'Deduction Data By Effective Date!!',
                    'data' => [
                        'effective_date' => $deduction->effective_date,
                        'deduction_amount' => $deduction->amount_par_paycheque,
                        'cost_center_id' => $deduction->cost_center_id,
                    ],
                ]);
            }

            return response()->json([
                'ApiName' => 'get-employee-data-by-date-type',
                'status' => false,
                'message' => 'Deduction Data Not Found For Given Effective Date!!',
                'data' => [],
            ], 400);
        }
    }

    public function welcomeMail(Request $request): JsonResponse
    {
        $randPassForUsers = randPassForUsers();
        $user = $check = User::where('id', $request->id)->first();
        if (! $user) {
            return response()->json([
                'ApiName' => 'Welcome Mail',
                'status' => false,
                'message' => 'User not found!',
            ], 400);
        }

        if ($user->first_time_changed_password) {
            return response()->json([
                'ApiName' => 'Welcome Mail',
                'status' => false,
                'message' => 'User has already resetted his first time password!',
            ], 400);
        }
        User::where('id', $request->id)->update(['password' => $randPassForUsers['password']]);

        // $check['new_password'] = 'Newuser#123';
        $check['new_password'] = $randPassForUsers['plain_password'];

        $check['new_password'] = $randPassForUsers['plain_password'];
        $other_data = [];
        // $other_data['new_password'] = 'Newuser#123';
        $other_data['new_password'] = $randPassForUsers['plain_password'];
        $welcome_email_content = SequiDocsEmailSettings::welcome_email_content($user, $other_data);
        $email_content['email'] = $user->email;
        $email_content['subject'] = $welcome_email_content['subject'];
        $email_content['template'] = $welcome_email_content['template'];
        $message = 'Welcome Mail Send Successfully.';
        $user_email_for_send_email = $user->email;
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
            $message = $check_domain_setting['message'];
        }

        return response()->json([
            'ApiName' => 'Send Credentials',
            'status' => true,
            'message' => $message,
            'welcome_email_content' => $welcome_email_content,
        ]);
    }

    public function updateUserArenaTheme(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'arena_theme' => ['required', 'string'],
                'theme_config' => ['nullable', 'array'],
            ]);

            $user = User::where('id', $validated['user_id'])->first();

            if (! $user) {
                return response()->json([
                    'ApiName' => 'update-user-arena-theme',
                    'status' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Use database transaction for data integrity
            DB::beginTransaction();

            try {
                // Use the safe method from UserThemePreference model
                $themePreference = UserThemePreference::setActiveThemeForUser(
                    $user->id,
                    $validated['arena_theme'],
                    $validated['theme_config'] ?? []
                );

                DB::commit();

                // Load the user with the new theme preference
                $user->load('activeThemePreference');

                return response()->json([
                    'ApiName' => 'update-user-arena-theme',
                    'status' => true,
                    'message' => 'Arena theme updated successfully.',
                    'data' => [
                        'user_id' => $user->id,
                        'arena_theme' => $themePreference->theme_name,
                        'theme_config' => $themePreference->theme_config,
                        'updated_at' => $themePreference->updated_at,
                    ],
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json([
                'ApiName' => 'update-user-arena-theme',
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'update-user-arena-theme',
                'status' => false,
                'message' => 'Failed to update theme. Please try again later.',
                'error_id' => uniqid('theme_error_'),
            ], 500);
        }
    }

    public function sendMailToProjectsTeamAndCSTeam($data, $newData)
    {
        $mailData['email'] = config('notification-emails.entity-type-change-email-address.email');
        $mailData['cc_emails_arr'] = config('notification-emails.entity-type-change-email-address.cc_emails');
        $mailData['subject'] = 'Entity Type Update for '.$data->first_name.' '.$data->last_name.' on '.config('app.domain_name');
        $mailData['template'] = view('mail.entity_type_change', compact('data', 'newData'));
        $this->sendEmailNotification($mailData);
    }
}
