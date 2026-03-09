<?php

namespace App\Http\Controllers;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\HubspotTrait;
use App\Core\Traits\JobNimbusTrait;
use App\Core\Traits\PermissionCheckTrait;
use App\Models\AdditionalLocations;
use App\Models\AdditionalRecruiters;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\Documents;
use App\Models\DomainSetting;
use App\Models\EmployeeIdSetting;
use App\Models\EmployeeOnboardingDeduction;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\ManagementTeamMember;
use App\Models\NewSequiDocsDocument;
use App\Models\Notification;
use App\Models\OnboardingAdditionalEmails;
use App\Models\OnboardingEmployeeLocations;
use App\Models\OnboardingEmployeeOverride;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingUserRedline;
use App\Models\OtherImportantLog;
use App\Models\PositionOverride;
use App\Models\SequiDocsEmailSettings;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use App\Traits\EmailNotificationTrait;
use App\Traits\IntegrationTrait;
use App\Traits\PushNotificationTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class hiredEmployee_from_call_back extends Controller
{
    use EmailNotificationTrait;
    use EvereeTrait;
    use HubspotTrait;
    use IntegrationTrait;
    use JobNimbusTrait;
    use PermissionCheckTrait;
    use PushNotificationTrait;

    public function hiredEmployee_from_call_back($request, $authUserId = 0)
    {
        /* Note any type of changes in hiredEmployee_from_call_back() this function, need to change same in hiredEmployee() function return in OnboardingEmployeeController controller. */
        try {
            DB::beginTransaction();
            $randPassForUsers = randPassForUsers();
            $onbarding_user_id = $request->employee_id;
            $checkStatus = OnboardingEmployees::with('positionDetail')->where('id', $request->employee_id)->first();

            if (! $checkStatus) {
                return [
                    'ApiName' => 'Send Credentials',
                    'status' => false,
                    'message' => 'Employee Id Not Found.',
                ];
            }

            $group_id = $checkStatus->positionDetail->group_id;
            // $userId = Auth()->user();
            if ($authUserId > 0) {
                $userId = User::find($authUserId);
            } else {
                $userId = auth()->user();
            }
            $uid = ($userId->is_super_admin == 0) ? $userId->id : null;
            $substr = 0;

            $usereEail = User::where('email', $checkStatus['email'])->first();
            if (empty($usereEail)) {
                $additional_user_id = UsersAdditionalEmail::where('email', $checkStatus['email'])->value('user_id');
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
                // 'password' => Hash::make($randomPassword), // Use the random password
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
            ];

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
                $userDataToCreate['self_gen_withheld_type'] = $checkStatus['self_gen_withheld_amount'];
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

            NewSequiDocsDocument::where('user_id', '=', $onbarding_user_id)->where('user_id_from', '=', 'onboarding_employees')->where('is_active', 1)->Update(['user_id' => $data->id, 'user_id_from' => 'users']); // update all new sequi doc documents when hired
            Documents::where('user_id', '=', $onbarding_user_id)->where('user_id_from', '=', 'onboarding_employees')->Update(['user_id' => $new_created_user_id, 'user_id_from' => 'users']); // update all documents when hired

            if ($new_created_user_id) {
                try {
                    $proposedEmployeeId = DB::transaction(function () use ($data, $new_created_user_id) {
                        $empid = EmployeeIdSetting::orderBy('id', 'asc')->first();
                        $idCode = !empty($empid) ? $empid->id_code : 'EMP';
                        
                        // Lock the table to prevent concurrent access
                        User::where('employee_id', 'like', $idCode.'%')
                            ->whereNotNull('employee_id')
                            ->lockForUpdate()
                            ->get();
                        
                        // Get the highest existing employee_id numeric value
                        $maxNumericValue = User::where('employee_id', 'like', $idCode.'%')
                            ->whereNotNull('employee_id')
                            ->where('id', '!=', $data->id)
                            ->selectRaw('CAST(SUBSTRING(employee_id, ?) AS UNSIGNED) as num', [strlen($idCode) + 1])
                            ->orderByRaw('CAST(SUBSTRING(employee_id, ?) AS UNSIGNED) DESC', [strlen($idCode) + 1])
                            ->value('num');
                        
                        // Get the maximum padding length using SQL (much more efficient)
                        $maxPaddingLength = User::where('employee_id', 'like', $idCode.'%')
                            ->whereNotNull('employee_id')
                            ->where('id', '!=', $data->id)
                            ->selectRaw('MAX(LENGTH(employee_id) - ?) as max_len', [strlen($idCode)])
                            ->value('max_len');
                        
                        // Determine numeric count: preserve existing format, or use user ID length/default to 6
                        $numericCount = $maxPaddingLength ?: 6;
                        // Use the higher value: user ID or max existing ID + 1
                        $baseNumericValue = max((int) $new_created_user_id, ($maxNumericValue ?? 0) + 1);
                        
                        // Check if this value already exists (edge case for concurrent requests)
                        // Add max iteration limit to prevent infinite loops
                        $maxIterations = 100;
                        $iterationCount = 0;
                        while (User::where('employee_id', $idCode.str_pad($baseNumericValue, $numericCount, '0', STR_PAD_LEFT))
                            ->where('id', '!=', $data->id)
                            ->exists()) {
                            $baseNumericValue++;
                            $iterationCount++;
                            if ($iterationCount >= $maxIterations) {
                                Log::error('Employee ID generation: Max iterations reached', [
                                    'user_id' => $data->id,
                                    'id_code' => $idCode,
                                    'last_attempted_value' => $baseNumericValue,
                                ]);
                                throw new Exception('Unable to generate unique employee ID after '.$maxIterations.' attempts');
                            }
                        }
                        
                        $EmpId = str_pad($baseNumericValue, $numericCount, '0', STR_PAD_LEFT);
                        $proposedEmployeeId = $idCode.$EmpId;
                        
                        User::where('id', $data->id)->update(['employee_id' => $proposedEmployeeId]);
                        
                        return $proposedEmployeeId;
                    });
                } catch (Exception $e) {
                    Log::error('Failed to generate employee ID for user from callback', [
                        'user_id' => $data->id,
                        'new_created_user_id' => $new_created_user_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    // Continue execution but log the error - don't fail the entire hiring process
                    // The employee_id will remain null and can be manually assigned later
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
                    // 'updater_id' => Auth()->user()->id,
                    'updater_id' => isset($userId->id) ? $userId->id : null,
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
                // 'updater_id' => Auth()->user()->id,
                'updater_id' => isset($userId->id) ? $userId->id : null,
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
                    // 'updater_id' => Auth()->user()->id,
                    'updater_id' => isset($userId->id) ? $userId->id : null,
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
                // 'updater_id' => Auth()->user()->id,
                'updater_id' => isset($userId->id) ? $userId->id : null,
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

            if (! empty($userdata->id)) {
                Locations::where('id', $userdata->office_id)->update(['archived_at' => null]);
            }

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                // No Need To Create Hubspot Data
            } else {
                $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                if (! empty($CrmData) && ! empty($CrmSetting)) {
                    $val = json_decode($CrmSetting['value']);
                    $token = $val->api_key;
                    $checkStatus->status = 'Onboarding';
                    $this->hubspotSaleDataCreate($data, $checkStatus, $uid, $token);
                }
            }

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                // No Need To Create JobNimbus Data
            } else {
                $jobNimbusCrmData = Crms::whereHas('crmSetting')->with('crmSetting')->where('id', 4)->where('status', 1)->first();
                if (! empty($jobNimbusCrmData)) {
                    $jobNimbusCrmSetting = json_decode($jobNimbusCrmData->crmSetting->value);
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
                        // 'updater_id' => Auth()->user()->id,
                        'updater_id' => isset($userId->id) ? $userId->id : null,
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
                        // 'updater_id' => Auth()->user()->id,
                        'updater_id' => isset($userId->id) ? $userId->id : null,
                        'override_effective_date' => $checkStatus['period_of_agreement_start_date'],
                        'state_id' => $additional_location['state_id'],
                        'office_id' => $additional_location['office_id'],
                        'office_overrides_amount' => isset($additional_location['overrides_amount']) ? $additional_location['overrides_amount'] : 0,
                        'office_overrides_type' => isset($additional_location['overrides_type']) ? $additional_location['overrides_type'] : '',
                    ]);
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
                // 'updater_id' => auth()->user()->id,
                'updater_id' => isset($userId->id) ? $userId->id : null,
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
                    // 'updater_id' => auth()->user()->id,
                    'updater_id' => isset($userId->id) ? $userId->id : null,
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

                if (isset($ord['withheld_amount'])) {
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
                'description' => 'Employee Hired by'.$userId->first_name,
                'is_read' => 0,
            ]);

            $notificationData = [
                'user_id' => $check->id,
                'device_token' => $check->device_token,
                'title' => 'Employee Hired.',
                'sound' => 'sound',
                'type' => 'Employee Hired',
                'body' => 'Employee Hired by '.$userId->first_name,
            ];
            $this->sendNotification($notificationData);

            DB::commit();

            return [
                'ApiName' => 'Send Credentials',
                'status' => true,
                'message' => 'Send Credentials Successfully.',
                'onboarding_employee_id' => $request->employee_id,
                'hired_user_id' => $new_user_data->id,
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

        //        $super_admin_data = $userId = User::where('id', 1)->first();
        //        $uid = ($userId->is_super_admin == 0) ? $userId->id : null;
        //        $onbarding_user_id = $request->employee_id;
        //        $checkStatus = OnboardingEmployees::with('subpositionDetail')->where('id', $request->employee_id)->where('status_id', 1)->first();
        //
        //        try {
        //            DB::beginTransaction();
        //            if ($checkStatus != null) {
        //
        //                $group_id = $checkStatus->subpositionDetail->group_id;
        //                if (isset($checkStatus) && $checkStatus != '') {
        //                    $usereEail = User::where('email', $checkStatus['email'])->first();
        //                    if (empty($usereEail)) {
        //                        $additional_user_id = UsersAdditionalEmail::where('email', $checkStatus['email'])->value('user_id');
        //                        if (!empty($additional_user_id)) {
        //                            $usereEail = User::where('id', $additional_user_id)->first();
        //                        }
        //                    }
        //                    if ($usereEail) {
        //                        return [
        //                            'ApiName' => 'Send Credentials',
        //                            'status' => false,
        //                            'message' => 'Email is already exist'
        //                        ];
        //                    }
        //
        //                    $user_mobile_no = User::where('mobile_no', $checkStatus['mobile_no'])->first();
        //                    if ($user_mobile_no) {
        //                        return [
        //                            'ApiName' => 'Send Credentials',
        //                            'status' => false,
        //                            'message' => 'Mobile no is already exist'
        //                        ];
        //                    }
        //                    $eId = User::where('employee_id', '!=', null)->orderBy('id', "Desc")->pluck('employee_id')->first();
        //
        //                    $lettersOnly = preg_replace("/\d+$/", "", $eId);
        //                    $substr = str_replace($lettersOnly, "", $eId);
        //                    $numericCount = strlen($substr);
        //
        //                    $val = $substr + 1;
        //                    $EmpId = str_pad($val, $numericCount, "0", STR_PAD_LEFT);
        //
        //                    $companyProfile = CompanyProfile::first();
        //                    $userDataToCreate = [
        //                        'aveyo_hs_id' => $checkStatus['aveyo_hs_id'],
        //                        'first_name' => $checkStatus['first_name'],
        //                        'last_name' => $checkStatus['last_name'],
        //                        'email' => $checkStatus['email'],
        //                        'mobile_no' => $checkStatus['mobile_no'],
        //                        'state_id' => $checkStatus['state_id'],
        //                        'city_id' => $checkStatus['city_id'],
        //                        'self_gen_accounts' => $checkStatus['self_gen_accounts'],
        //                        'self_gen_type' => $checkStatus['self_gen_type'],
        //                        'department_id' => isset($checkStatus['department_id']) ? $checkStatus['department_id'] : null,
        //                        'position_id' => $checkStatus['position_id'],
        //                        'sub_position_id' => $checkStatus['sub_position_id'],
        //                        'is_manager' => $checkStatus['is_manager'],
        //                        'is_manager_effective_date' => ($checkStatus['is_manager'] == 1) ? $checkStatus['period_of_agreement_start_date'] : null,
        //                        'manager_id' => $checkStatus['manager_id'],
        //                        'manager_id_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'team_id' => $checkStatus['team_id'],
        //                        'team_id_effective_date' => (!empty($checkStatus['team_id'])) ? $checkStatus['period_of_agreement_start_date'] : null,
        //                        'recruiter_id' => isset($checkStatus['recruiter_id']) ? $checkStatus['recruiter_id'] : $uid,
        //                        'group_id' => $group_id,
        //                        'commission' => $checkStatus['commission'],
        //                        'commission_type' => $checkStatus['commission_type'],
        //                        'self_gen_commission' => $checkStatus['self_gen_commission'],
        //                        'self_gen_commission_type' => $checkStatus['self_gen_commission_type'],
        //                        'self_gen_upfront_amount' => $checkStatus['self_gen_upfront_amount'],
        //                        'self_gen_upfront_type' => $checkStatus['self_gen_upfront_type'],
        //                        'self_gen_withheld_amount' => $checkStatus['self_gen_withheld_amount'],
        //                        'self_gen_withheld_type' => $checkStatus['self_gen_withheld_type'],
        //                        'upfront_pay_amount' => $checkStatus['upfront_pay_amount'],
        //                        'upfront_sale_type' => $checkStatus['upfront_sale_type'],
        //                        'direct_overrides_amount' => $checkStatus['direct_overrides_amount'],
        //                        'direct_overrides_type' => $checkStatus['direct_overrides_type'],
        //                        'indirect_overrides_amount' => $checkStatus['indirect_overrides_amount'],
        //                        'indirect_overrides_type' => $checkStatus['indirect_overrides_type'],
        //                        'office_overrides_amount' => $checkStatus['office_overrides_amount'],
        //                        'office_overrides_type' => $checkStatus['office_overrides_type'],
        //                        'office_stack_overrides_amount' => $checkStatus['office_stack_overrides_amount'],
        //                        'withheld_amount' => $checkStatus['withheld_amount'],
        //                        'withheld_type' => $checkStatus['withheld_type'],
        //                        'probation_period' => $checkStatus['probation_period'],
        //                        'hiring_bonus_amount' => $checkStatus['hiring_bonus_amount'],
        //                        'date_to_be_paid' => $checkStatus['date_to_be_paid'],
        //                        'period_of_agreement_start_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'end_date' => $checkStatus['end_date'],
        //                        'offer_include_bonus' => $checkStatus['offer_include_bonus'],
        //                        'offer_expiry_date' => $checkStatus['offer_expiry_date'],
        //                        'office_id' => $checkStatus['office_id'],
        //                        'password' => Hash::make('Newuser#123'),
        //                        'status_id' => 1,
        //                        'commission_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'self_gen_commission_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'upfront_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'self_gen_upfront_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'withheld_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'self_gen_withheld_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'override_effective_date' => $checkStatus['period_of_agreement_start_date'],
        //                        'position_id_effective_date' => $checkStatus['period_of_agreement_start_date']
        //                        //'entity_type' => 'individual'
        //                    ];
        //
        //                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
        //                        $userDataToCreate['redline'] = NULL;
        //                        $userDataToCreate['redline_amount_type'] = NULL;
        //                        $userDataToCreate['redline_type'] = NULL;
        //                        $userDataToCreate['self_gen_redline'] = NULL;
        //                        $userDataToCreate['self_gen_redline_amount_type'] = NULL;
        //                        $userDataToCreate['self_gen_redline_type'] = NULL;
        //                        $userDataToCreate['redline_effective_date'] = NULL;
        //                        $userDataToCreate['self_gen_redline_effective_date'] = NULL;
        //
        //                        $userDataToCreate['self_gen_accounts'] = NULL;
        //                        $userDataToCreate['self_gen_type'] = NULL;
        //                        $userDataToCreate['self_gen_commission'] = NULL;
        //                        $userDataToCreate['self_gen_commission_type'] = NULL;
        //                        $userDataToCreate['self_gen_upfront_amount'] = NULL;
        //                        $userDataToCreate['self_gen_upfront_type'] = NULL;
        //                        $userDataToCreate['self_gen_withheld_amount'] = NULL;
        //                        $userDataToCreate['self_gen_withheld_type'] = NULL;
        //                        $userDataToCreate['self_gen_commission_effective_date'] = NULL;
        //                        $userDataToCreate['self_gen_upfront_effective_date'] = NULL;
        //                        $userDataToCreate['self_gen_withheld_effective_date'] = NULL;
        //                    } else {
        //                        $userDataToCreate['redline'] = $checkStatus['redline'];
        //                        $userDataToCreate['redline_amount_type'] = $checkStatus['redline_amount_type'];
        //                        $userDataToCreate['redline_type'] = $checkStatus['redline_type'];
        //                        $userDataToCreate['self_gen_redline'] = $checkStatus['self_gen_redline'];
        //                        $userDataToCreate['self_gen_redline_amount_type'] = $checkStatus['self_gen_redline_amount_type'];
        //                        $userDataToCreate['self_gen_redline_type'] = $checkStatus['self_gen_redline_type'];
        //                        $userDataToCreate['redline_effective_date'] = $checkStatus['period_of_agreement_start_date'];
        //                        $userDataToCreate['self_gen_redline_effective_date'] = $checkStatus['period_of_agreement_start_date'];
        //
        //                        $userDataToCreate['self_gen_accounts'] = $checkStatus['self_gen_accounts'];
        //                        $userDataToCreate['self_gen_type'] = $checkStatus['self_gen_type'];
        //                        $userDataToCreate['self_gen_commission'] = $checkStatus['self_gen_commission'];
        //                        $userDataToCreate['self_gen_commission_type'] = $checkStatus['self_gen_commission_type'];
        //                        $userDataToCreate['self_gen_upfront_amount'] = $checkStatus['self_gen_upfront_amount'];
        //                        $userDataToCreate['self_gen_upfront_type'] = $checkStatus['self_gen_upfront_type'];
        //                        $userDataToCreate['self_gen_withheld_amount'] = $checkStatus['self_gen_withheld_amount'];
        //                        $userDataToCreate['self_gen_withheld_type'] = $checkStatus['self_gen_withheld_amount'];
        //                        $userDataToCreate['self_gen_commission_effective_date'] = $checkStatus['period_of_agreement_start_date'];
        //                        $userDataToCreate['self_gen_upfront_effective_date'] = $checkStatus['period_of_agreement_start_date'];
        //                        $userDataToCreate['self_gen_withheld_effective_date'] = $checkStatus['period_of_agreement_start_date'];
        //                    }
        //
        //                    if ($checkStatus['self_gen_commission_type'] == null) {
        //                        unset($userDataToCreate['self_gen_commission_type']);
        //                    }
        //
        //                    $data = User::create($userDataToCreate);
        //
        //                    if (isset($checkStatus['commission_selfgen']) && $checkStatus['commission_selfgen'] != null) {
        //                        $dateCurrent = date('Y-m-d');
        //                        $dateEffective = isset($checkStatus['commission_selfgen_effective_date']) ? $checkStatus['commission_selfgen_effective_date'] : null;
        //                        if ($dateEffective == null) {
        //                            $dateEffective = $dateCurrent;
        //                        } else {
        //                            $dateEffective = $dateEffective;
        //                        }
        //                        UserSelfGenCommmissionHistory::create([
        //                            'user_id' => $data->id,
        //                            'updater_id' => $super_admin_data->id,
        //                            'commission' => $checkStatus['commission_selfgen'],
        //                            'commission_effective_date' => $dateEffective,
        //                            'old_commission' => 0,
        //                            'position_id' => $checkStatus['sub_position_id']
        //                        ]);
        //                    }
        //
        //                    $empid = EmployeeIdSetting::orderBy('id', 'asc')->first();
        //                    if (!empty($empid)) {
        //                        User::where('id', $data->id)->update(['employee_id' => $empid->id_code . $EmpId]);
        //                    } else {
        //                        User::where('id', $data->id)->update(['employee_id' => 'EMP' . $EmpId]);
        //                    }
        //
        //                    //team member create code start
        //                    if (!empty($data->team_id)) {
        //                        $teamLeadId = ManagementTeam::where('id', $data->team_id)->first();
        //                        if ($teamLeadId) {
        //                            ManagementTeamMember::Create([
        //                                'team_id' => $teamLeadId->id,
        //                                'team_lead_id' => $teamLeadId->team_lead_id,
        //                                'team_member_id' => $data->id
        //                            ]);
        //                        }
        //                    }
        //
        //                    //team member create code end
        //                    OnboardingEmployees::where('email', $data->email)->update(['user_id' => $data->id]);
        //
        //                    $workEmail = OnboardingAdditionalEmails::where("onboarding_user_id", $onbarding_user_id)->get();
        //                    if (count($workEmail) > 0) {
        //                        foreach ($workEmail as $workEmails) {
        //                            $userAddiemail = UsersAdditionalEmail::where("email", $workEmails->email)->first();
        //                            if ($userAddiemail == '') {
        //                                UsersAdditionalEmail::create(["user_id" => $data->id, "email" => $workEmails->email]);
        //                            }
        //                        }
        //                    }
        //
        //                    // update staus in hubspot
        //                    $CrmData = Crms::where('id', 2)->where('status', 1)->first();
        //                    $CrmSetting = CrmSetting::where('crm_id', 2)->first();
        //                    if (!empty($CrmData) && !empty($CrmSetting)) {
        //                        $val = json_decode($CrmSetting['value']);
        //                        $token = $val->api_key;
        //                        $user = User::where(
        //                            'id',
        //                            $data->id
        //                        )->first();
        //                        if (!empty($user->aveyo_hs_id)) {
        //                            $Hubspotdata['properties'] = ['status' => 'Onboarding'];
        //                            $this->update_hubspot_data($Hubspotdata, $token, $user->aveyo_hs_id);
        //                        }
        //                    }
        //
        //
        //                    $userdata = User::where('id', $data->id)->first();
        //                    //UserTransferHistory data create code start
        //                    $transfer = [
        //                        'user_id' => $userdata->id,
        //                        'transfer_effective_date' => $userdata->period_of_agreement_start_date,
        //                        'updater_id' => Auth()->user()->id,
        //                        'state_id' => $userdata->state_id,
        //                        'old_state_id' => null,
        //                        'office_id' => $userdata->office_id,
        //                        'old_office_id' => null,
        //                        'department_id' => $userdata->department_id,
        //                        'old_department_id' => null,
        //                        'position_id' => $userdata->position_id,
        //                        'old_position_id' => null,
        //                        'sub_position_id' => $userdata->sub_position_id,
        //                        'old_sub_position_id' => null,
        //                        'is_manager' => $userdata->is_manager,
        //                        'old_is_manager' => null,
        //                        'manager_id' => $userdata->manager_id,
        //                        'old_manager_id' => null,
        //                        'team_id' => $userdata->team_id,
        //                        'old_team' => null,
        //                        'existing_employee_new_manager_id' => null,
        //                        'existing_employee_old_manager_id' => null
        //                    ];
        //                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
        //                        $transfer['redline_amount_type'] = NULL;
        //                        $transfer['old_redline_amount_type'] = NULL;
        //                        $transfer['redline'] = NULL;
        //                        $transfer['old_redline'] = NULL;
        //                        $transfer['redline_type'] = NULL;
        //                        $transfer['old_redline_type'] = NULL;
        //                        $transfer['self_gen_redline_amount_type'] = NULL;
        //                        $transfer['old_self_gen_redline_amount_type'] = NULL;
        //                        $transfer['self_gen_redline'] = NULL;
        //                        $transfer['old_self_gen_redline'] = NULL;
        //                        $transfer['self_gen_redline_type'] = NULL;
        //                        $transfer['old_self_gen_redline_type'] = NULL;
        //                        $transfer['self_gen_accounts'] = NULL;
        //                        $transfer['old_self_gen_accounts'] = NULL;
        //                    } else {
        //                        $transfer['redline_amount_type'] = $userdata->redline_amount_type;
        //                        $transfer['old_redline_amount_type'] = NULL;
        //                        $transfer['redline'] = $userdata->redline;
        //                        $transfer['old_redline'] = NULL;
        //                        $transfer['redline_type'] = $userdata->redline_type;
        //                        $transfer['old_redline_type'] = NULL;
        //                        $transfer['self_gen_redline_amount_type'] = $userdata->self_gen_redline_amount_type;
        //                        $transfer['old_self_gen_redline_amount_type'] = NULL;
        //                        $transfer['self_gen_redline'] = $userdata->self_gen_redline;
        //                        $transfer['old_self_gen_redline'] = NULL;
        //                        $transfer['self_gen_redline_type'] = $userdata->self_gen_redline_type;
        //                        $transfer['old_self_gen_redline_type'] = NULL;
        //                        $transfer['self_gen_accounts'] = $userdata->self_gen_accounts;
        //                        $transfer['old_self_gen_accounts'] = NULL;
        //                    }
        //                    UserTransferHistory::create($transfer);
        //
        //                    $jobNimbusCrmData = Crms::whereHas('crmSetting')->with('crmSetting')->where('id', 4)->where('status', 1)->first();
        //                    if (!empty($jobNimbusCrmData)) {
        //                        $jobNimbusCrmSetting = json_decode($jobNimbusCrmData->crmSetting->value);
        //                        $jobNimbusToken = $jobNimbusCrmSetting->api_key;
        //                        $postDataToJobNimbus = array(
        //                            'display_name' => $userdata['first_name'] . ', ' . $userdata['last_name'] . ' ' . $userdata['employee_id'],
        //                            'email' => $userdata['email'],
        //                            'home_phone' => $userdata['mobile_no'],
        //                            'first_name' => $userdata['first_name'],
        //                            'last_name' => $userdata['last_name'],
        //                            'record_type_name' => 'Subcontractor',
        //                            'status_name' => 'Solar Reps',
        //                            'external_id' => $userdata['employee_id'],
        //                            // 'date_end' => $userdata['end_date'],
        //                            // 'date_start' => $userdata['period_of_agreement_start_date']
        //                        );
        //                        $responseJobNimbuscontats = $this->storeJobNimbuscontats($postDataToJobNimbus, $jobNimbusToken);
        //                        if ($responseJobNimbuscontats['status'] === true) {
        //                            User::where('id', $data->id)->update([
        //                                'jobnimbus_jnid' => $responseJobNimbuscontats['data']['jnid'],
        //                                'jobnimbus_number' => $responseJobNimbuscontats['data']['number']
        //                            ]);
        //                        }
        //                    }
        //
        //                    Documents::where('user_id', '=', $onbarding_user_id)->where('user_id_from', '=', 'onboarding_employees')->Update(['user_id' => $data->id, 'user_id_from' => 'users']); // update all documents when hired
        //                    NewSequiDocsDocument::where('user_id', '=', $onbarding_user_id)->where('user_id_from', '=', 'onboarding_employees')->where('is_active', 1)->Update(['user_id' => $data->id, 'user_id_from' => 'users']); // update all new sequi doc documents when hired
        //
        //                    $additionalRecruters = AdditionalRecruiters::where('hiring_id', $request->employee_id)->where('recruiter_id', '<>', null)->get();
        //                    if (sizeof($additionalRecruters)) {
        //                        $idd = $data->id;
        //                        foreach ($additionalRecruters as $key => $value) {
        //                            AdditionalRecruiters::where("id", $value['id'])->update(['user_id' => $idd]);
        //                            if ($key == 0) {
        //                                $data1 = array(
        //                                    'additional_recruiter_id1' => $value['recruiter_id'],
        //                                    'additional_recruiter1_per_kw_amount' => $value['system_per_kw_amount']
        //                                );
        //                                User::where("id", $idd)->update($data1);
        //                            } else {
        //                                $data2 = array(
        //                                    'additional_recruiter_id2' => $value['recruiter_id'],
        //                                    'additional_recruiter2_per_kw_amount' => $value['system_per_kw_amount']
        //                                );
        //                                User::where("id", $idd)->update($data2);
        //                            }
        //                        }
        //                    }
        //
        //                    $additionalLocations = OnboardingEmployeeLocations::where('user_id', $request->employee_id)->get();
        //                    if ($additionalLocations) {
        //                        foreach ($additionalLocations as $additional_location) {
        //                            AdditionalLocations::create([
        //                                'state_id' => $additional_location['state_id'],
        //                                'city_id' => null, //$additional_location['city_id'],
        //                                'user_id' => $data->id,
        //                                'office_id' => $additional_location['office_id'],
        //                                'overrides_amount' => isset($additional_location['overrides_amount']) ? $additional_location['overrides_amount'] : 0,
        //                                'overrides_type' => isset($additional_location['overrides_type']) ? $additional_location['overrides_type'] : ''
        //                            ]);
        //                        }
        //                    }
        //                }
        //                $statusUpdate = OnboardingEmployees::find($request->employee_id);
        //                $statusUpdate->status_id = 7;
        //                $statusUpdate->save();
        //
        //
        //                $deduction = EmployeeOnboardingDeduction::where('user_id', $request->employee_id)->get();
        //                UserDeduction::where('user_id', $data->id)->delete();
        //                foreach ($deduction as $deductions) {
        //                    UserDeduction::create([
        //                        'deduction_type' => $deductions['deduction_type'],
        //                        'cost_center_name' => $deductions['cost_center_name'],
        //                        'cost_center_id' => $deductions['cost_center_id'],
        //                        'ammount_par_paycheck' => $deductions['ammount_par_paycheck'],
        //                        'deduction_setting_id' => isset($deductions['deduction_setting_id']) ? $deductions['deduction_setting_id'] : null,
        //                        'position_id' => $deductions['position_id'],
        //                        'sub_position_id' => $userdata->sub_position_id,
        //                        'user_id' => $data->id,
        //                        'effective_date' => $checkStatus['period_of_agreement_start_date']
        //                    ]);
        //
        //                    UserDeductionHistory::create([
        //                        'user_id' => $data->id,
        //                        'updater_id' => auth()->user()->id,
        //                        'cost_center_id' => $deductions['cost_center_id'],
        //                        'amount_par_paycheque' => $deductions['ammount_par_paycheck'],
        //                        'old_amount_par_paycheque' => null,
        //                        'effective_date' => $checkStatus['period_of_agreement_start_date']
        //                    ]);
        //                }
        //                $onboard_redline_data = OnboardingUserRedline::where('user_id', $request->employee_id)->get();
        //
        //                $user_data = User::where('id', $data->id)->first();
        //                foreach ($onboard_redline_data as $key => $ord) {
        //                    if ($key == 0) {
        //                        $self_gen_user = 0;
        //                        $sub_position_id = $user_data->sub_position_id;
        //                    } else {
        //                        $self_gen_user = 1;
        //                        $sub_position_id = $ord['position_id'];
        //                    }
        //                    UserCommissionHistory::create([
        //                        'user_id' => $data->id,
        //                        'commission_effective_date' => $checkStatus['period_of_agreement_start_date'], //$ord['commission_effective_date'],
        //                        'position_id' => $ord['position_id'],
        //                        'sub_position_id' => $sub_position_id,
        //                        'updater_id' => $ord['updater_id'],
        //                        'self_gen_user' => $self_gen_user,
        //                        'commission' => $ord['commission'],
        //                        'commission_type' => $ord['commission_type']
        //                    ]);
        //
        //                    if (isset($ord['upfront_pay_amount'])) {
        //                        UserUpfrontHistory::create([
        //                            'user_id' => $data->id,
        //                            'upfront_effective_date' => $checkStatus['period_of_agreement_start_date'],//$ord['upfront_effective_date'],
        //                            'position_id' => $ord['position_id'],
        //                            'sub_position_id' => $sub_position_id,
        //                            'updater_id' => $ord['updater_id'],
        //                            'self_gen_user' => $self_gen_user,
        //                            'upfront_pay_amount' => $ord['upfront_pay_amount'],
        //                            'upfront_sale_type' => $ord['upfront_sale_type']
        //                        ]);
        //                    }
        //
        //                    if (isset($ord['withheld_amount'])) {
        //                        UserWithheldHistory::create([
        //                            'user_id' => $data->id,
        //                            'updater_id' => $ord['updater_id'],
        //                            'position_id' => $ord['position_id'],
        //                            'sub_position_id' => $sub_position_id,
        //                            'withheld_type' => $ord['withheld_type'],
        //                            'withheld_amount' => $ord['withheld_amount'],
        //                            'self_gen_user' => $self_gen_user,
        //                            'withheld_effective_date' => $checkStatus['period_of_agreement_start_date'] //$ord['withheld_effective_date']
        //                        ]);
        //                    }
        //
        //                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
        //                        UserRedlines::where(['user_id' => $data->id])->delete();
        //                    } else {
        //                        UserRedlines::create([
        //                            'user_id' => $data->id,
        //                            'start_date' => $checkStatus['period_of_agreement_start_date'],// $ord['start_date'],
        //                            'position_type' => $ord['position_id'],
        //                            'sub_position_type' => $sub_position_id,
        //                            'updater_id' => $ord['updater_id'],
        //                            'redline_amount_type' => $ord['redline_amount_type'],
        //                            'redline' => $ord['redline'],
        //                            'redline_type' => $ord['redline_type'],
        //                            'withheld_amount' => isset($ord['withheld_amount']) ? $ord['withheld_amount'] : '',
        //                            'self_gen_user' => $self_gen_user
        //                        ]);
        //                    }
        //
        //                    if ($key == 0) {
        //                        $user_data->commission_effective_date = $checkStatus['period_of_agreement_start_date'];// $ord['commission_effective_date'];
        //                        $user_data->withheld_effective_date = $checkStatus['period_of_agreement_start_date'];// $ord['withheld_effective_date'];
        //                        $user_data->upfront_effective_date = $checkStatus['period_of_agreement_start_date'];// $ord['upfront_effective_date'];
        //                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
        //                            $user_data->redline_effective_date = NULL;
        //                        } else {
        //                            $user_data->redline_effective_date = $checkStatus['period_of_agreement_start_date'];// $ord['redline_effective_date'];
        //                        }
        //                    } else {
        //                        $user_data->self_gen_commission_effective_date = $checkStatus['period_of_agreement_start_date'];// $ord['commission_effective_date'];
        //                        $user_data->self_gen_withheld_effective_date = $checkStatus['period_of_agreement_start_date'];//$ord['withheld_effective_date'];
        //                        $user_data->self_gen_upfront_effective_date = $checkStatus['period_of_agreement_start_date'];//$ord['upfront_effective_date'];
        //                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
        //                            $user_data->self_gen_redline_effective_date = NULL;
        //                        } else {
        //                            $user_data->self_gen_redline_effective_date = $checkStatus['period_of_agreement_start_date'];//$ord['redline_effective_date'];
        //                        }
        //                    }
        //                }
        //                $onboard_override_data = OnboardingEmployeeOverride::where('user_id', $request->employee_id)->first();
        //                if (!empty($onboard_override_data)) {
        //                    UserOverrideHistory::create([
        //                        'user_id' => $data->id,
        //                        'override_effective_date' => isset($onboard_override_data->override_effective_date) ? $onboard_override_data->override_effective_date : date('Y-m-d'),
        //                        'updater_id' => $onboard_override_data->updater_id,
        //                        'direct_overrides_amount' => $onboard_override_data->direct_overrides_amount,
        //                        'direct_overrides_type' => $onboard_override_data->direct_overrides_type,
        //                        'indirect_overrides_amount' => $onboard_override_data->indirect_overrides_amount,
        //                        'indirect_overrides_type' => $onboard_override_data->indirect_overrides_type,
        //                        'office_overrides_amount' => $onboard_override_data->office_overrides_amount,
        //                        'office_overrides_type' => $onboard_override_data->office_overrides_type,
        //                        'office_stack_overrides_amount' => $onboard_override_data->office_stack_overrides_amount
        //                    ]);
        //                    $user_data->override_effective_date = $onboard_override_data->override_effective_date;
        //                    $user_data->save();
        //                }
        //
        //                $new_user_data = $check = User::where('id', $data->id)->first();
        //                $check['new_password'] = 'Newuser#123';
        //                $salesData = [];
        //
        //                // New mail send funcnality.
        //                $other_data = [];
        //                $other_data['new_password'] = 'Newuser#123';
        //                $welcome_email_content = SequiDocsEmailSettings::welcome_email_content($new_user_data, $other_data);
        //                // return $welcome_email_content['template'];
        //                $email_content['email'] = $new_user_data->email;
        //                $email_content['subject'] = $welcome_email_content['subject'];
        //                $email_content['template'] = $welcome_email_content['template'];
        //                // return $email_content;
        //                if ($welcome_email_content['is_active'] == 1 && $welcome_email_content['template'] != '') {
        //                    $email_content_response = $this->sendEmailNotification($email_content);
        //                } else {
        //                    $salesData['email'] = $data->email;
        //                    $salesData['subject'] = 'Login Credentials';
        //                    $salesData['template'] = view('mail.credentials', compact('check'));
        //                    $email_content_response = $this->sendEmailNotification($salesData);
        //                }
        //
        //                $data = Notification::create([
        //                    'user_id' => $check->id,
        //                    'type' => 'Employee Hired',
        //                    'description' => 'Employee Hired by' . $super_admin_data->first_name,
        //                    'is_read' => 0,
        //                ]);
        //                $notificationData = array(
        //                    'user_id' => $check->id,
        //                    'device_token' => $check->device_token,
        //                    'title' => 'Employee Hired.',
        //                    'sound' => 'sound',
        //                    'type' => 'Employee Hired',
        //                    'body' => 'Employee Hired by ' . $super_admin_data->first_name,
        //                );
        //                $this->sendNotification($notificationData);
        //
        //                DB::commit();
        //
        //                return [
        //                    'ApiName' => 'Send Credentials',
        //                    'status' => true,
        //                    'message' => 'Send Credentials Successfully.',
        //                    'onboarding_employee_id' => $request->employee_id,
        //                    'hired_user_id' => $new_user_data->id,
        //                ];
        //            } else {
        //
        //                return [
        //                    'ApiName' => 'Send Credentials',
        //                    'status' => false,
        //                    'message' => 'Employee Id Not Found.'
        //                ];
        //            }
        //        }
        //        catch (Exception $error) {
        //            $message = "something went wrong!!!";
        //            $error_message = $error->getMessage();
        //            $File = $error->getFile();
        //            $Line = $error->getLine();
        //            $Code = $error->getCode();
        //            $errorDetail = [
        //                "error_message" => $error_message,
        //                "File" => $File,
        //                "Line" => $Line,
        //                "Code" => $Code,
        //            ];
        //            return ["status_code" => 400, 'message' => $message, 'error' => $error, "errorDetail" => $errorDetail];
        //        }
    }

    public function check_all_doc_signed($signed_document, $authUserId = 0)
    {
        try {
            $user_id_from = $signed_document->user_id_from;
            $user_id = $signed_document->user_id;
            $is_all_doc_signed = false;
            $users_document = [];
            if ($user_id_from == 'onboarding_employees') {
                $onb_user_data = OnboardingEmployees::where('id', $user_id)->first();
                if ($onb_user_data != null && $onb_user_data != '') {
                    $status_id = $onb_user_data->status_id;
                    $created_user_id = $onb_user_data->user_id;

                    if ($created_user_id == null && $status_id == 1) {
                        $users_document = NewSequiDocsDocument::where(['user_id' => $user_id, 'user_id_from' => 'onboarding_employees', 'is_active' => 1, 'is_post_hiring_document' => 0, 'is_sign_required_for_hire' => 1])->get()->toArray();

                        $is_all_doc_signed = true;

                        if (count($users_document) > 0) {
                            foreach ($users_document as $doc_key => $doc_row) {
                                $document_response_status = $doc_row['document_response_status'];
                                if ($document_response_status != 1) {
                                    $is_all_doc_signed = false;
                                }
                            }
                            if ($is_all_doc_signed == true) {
                                $namespace = app()->getNamespace();
                                $OnboardingEmployeeController = app()->make($namespace.\Http\Controllers\API\V2\Hiring\OnboardingEmployeeController::class);
                                $hireing_response = $OnboardingEmployeeController->hiredEmployee(new Request(['employee_id' => $user_id]), $authUserId);

                                $other_important_logs = new OtherImportantLog;
                                $other_important_logs->user_id = $user_id;
                                $other_important_logs->response_data = json_encode($hireing_response);
                                $other_important_logs->ApiName = 'new_sequidocs_document_callback_signed_response';
                                $other_important_logs->save();
                            }
                        }
                    }
                }
            }

            return [
                'status' => true,
                'API' => 'check_all_doc_signed',
                'user_id_from' => $user_id_from,
                'user_id' => $user_id,
                'is_all_doc_signed' => $is_all_doc_signed,
                'users_document_count' => count($users_document),
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

    public function offerExpiredEventPush($id)
    {
        try {
            $this->saveDataToSourceMarketing($id, 'offer_expired');
        } catch (\Exception $e) {
        }
    }

    public function updateOfferExpiryDate($date = '')
    {
        $date = $date ? $date : date('Y-m-d');
        try {
            $employees = OnboardingEmployees::where('offer_expiry_date', '<', $date)->whereNull('user_id')
                ->whereIn('status_id', [1, 4, 6, 12])->get(); // Offer Letter Sent, Requested Change, Offer Letter Resent

            // update staus in hubspot
            $CrmData = Crms::where(['id' => 2, 'status' => 1])->first();
            $CrmSetting = CrmSetting::where('crm_id', 2)->first();
            if (! empty($CrmData) && ! empty($CrmSetting)) {
                foreach ($employees as $employee) {
                    $isSigned = false;
                    if ($employee->status_id == '1') {
                        $isSigned = $this->checkAllDocumentsSignedOrNot($employee);
                    } else {
                        $this->offerExpiredEventPush($employee->id);
                        $employee->update(['status_id' => 5]);
                    }

                    if (! $isSigned) {
                        $val = json_decode($CrmSetting['value']);
                        $token = $val->api_key;
                        if (! empty($employee['aveyo_hs_id'])) {
                            $Hubspotdata['properties'] = ['status' => 'Offer Expired'];
                            $this->update_hubspot_data($Hubspotdata, $token, $employee['aveyo_hs_id']);
                        }
                        $this->offerExpiredEventPush($employee->id);
                        $employee->update(['status_id' => 5]);
                    }
                }
            } else {
                foreach ($employees as $employee) {
                    if ($employee->status_id == '1') {
                        $isSigned = $this->checkAllDocumentsSignedOrNot($employee);
                        if (! $isSigned) {
                            $this->offerExpiredEventPush($employee->id);
                            $employee->update(['status_id' => 5]);
                        }
                    } else {
                        $this->offerExpiredEventPush($employee->id);
                        $employee->update(['status_id' => 5]);
                    }
                }
            }

            return ['success' => true, 'message' => 'Status Updated Successfully!'];
        } catch (Exception $e) {
            $errors[] = [
                'pid' => 'Offer Expired',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            return ['success' => false, 'message' => $e->getMessage().' '.$e->getLine()];
        }
    }

    // CHECK IF MANDATORY DOCS HAVE BEEN SIGNED OR NOT
    private function checkAllDocumentsSignedOrNot($employee)
    {
        if ($employee->status_id == '1') {
            if (NewSequiDocsDocument::where(['user_id' => $employee->id, 'user_id_from' => 'onboarding_employees', 'is_active' => 1, 'is_post_hiring_document' => 0, 'is_sign_required_for_hire' => 1])->where('document_response_status', '!=', '1')->first()) {
                return false;
            }
        }

        return true;
    }
}
