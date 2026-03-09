<?php

namespace App\Http\Controllers\API\V2\EmploymentPackage;

use App\Core\Traits\EvereeTrait;
use App\Http\Controllers\API\V2\Sales\BaseController;
use App\Jobs\EmploymentPackage\ApplyHistoryOnUsersV2Job;
use App\Jobs\Sales\ProcessRecalculatesOpenSales;
use App\Services\JobNotificationService;
use Laravel\Pennant\Feature;
use App\Models\AdditionalLocations;
use App\Models\AdditionalRecruiters;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\Crms;
use App\Models\Lead;
use App\Models\OnboardingEmployees;
use App\Models\Payroll;
use App\Models\PayrollDeductions;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionPayFrequency;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionWage;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\TiersSchema;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserAdditionalOfficeOverrideHistoryTiersRange;
use App\Models\UserAgreementHistory;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDeductionHistory;
use App\Models\UserDepartmentHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UsersBusinessAddress;
use App\Models\UsersCurrentTierLevel;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use App\Models\W2UserTransferHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Traits\IntegrationTrait;
class EmploymentPackageController extends BaseController
{
    use EvereeTrait;
    use IntegrationTrait;

    public function __construct(
        protected \App\Services\EspQuickBaseService $espQuickBaseService
    ) {}

    public function userPersonalDetails($id)
    {
        $user = User::where('id', $id)
            ->with('groupDetail', 'state', 'city', 'additionalDetail', 'office', 'team', 'recruiter', 'flexibleIds')->first();
        if (! $user) {
            $this->errorResponse('User not found!!', 'userOrganizationDetails', '', 400);
        }

        $userId = $user->id;
        $effectiveDate = date('Y-m-d');
        $state = State::where('id', $user->state_id)->first();
        $crmData = Crms::where(['id' => 3, 'status' => 1])->first();
        if ($user && $state && empty($user->everee_workerId) && $crmData) {
            $this->update_emp_personal_info($user, $state);
        }

        $systemType = null;
        $additionalRecruiter = null;
        if (isset($user->additionalDetail[0])) {
            $systemType = $user?->additionalDetail[0]?->system_type;
            $additionalRecruiter = $user->additionalDetail[0]?->additionalRecruiterDetail?->first_name.' '.$user->additionalDetail[0]?->additionalRecruiterDetail?->last_name;
        }

        $systemType2 = null;
        $additionalRecruiter2 = null;
        if (isset($user->additionalDetail[1])) {
            $systemType2 = $user?->additionalDetail[1]?->system_type;
            $additionalRecruiter2 = $user->additionalDetail[1]?->additionalRecruiterDetail?->first_name.' '.$user->additionalDetail[1]?->additionalRecruiterDetail?->last_name;
        }

        $additionalOffice = [];
        $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $currentAdditional) {
            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $additionalLocations = AdditionalLocations::with('state', 'office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
        foreach ($additionalLocations as $additionalLocation) {
            $additionalOffice[] = [
                'state_id' => $additionalLocation?->state?->id,
                'state_name' => $additionalLocation?->state?->name,
                'office_id' => $additionalLocation?->office?->id,
                'office_name' => $additionalLocation?->office?->office_name,
            ];
        }

        $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $isManager) {
            $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $manager) {
            $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $userOrganization = UserOrganizationHistory::with('position', 'subPositionId')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userOrganization) {
            $userOrganization = UserOrganizationHistory::with('position', 'subPositionId')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }

        $totalEmployee = User::where('manager_id', $userId)->count() ?? 0;
        if ($crmData && $user->worker_type == '1099' && isset($user) && ! empty($user->everee_workerId)) {
            $evereeOnboardingProcess = 1;
        } elseif ($crmData && ($user->worker_type == 'w2' || $user->worker_type == 'W2') && isset($user) && ! empty($user->everee_workerId) && $user->everee_embed_onboard_profile == 1) {
            $evereeOnboardingProcess = 1;
        } else {
            $evereeOnboardingProcess = 0;
        }

        $userTransferData = W2UserTransferHistory::where(['user_id' => $userId])->first();
        $transfer_worker_location = $user?->worker_type;
        $transfer_worker_location_date = null;
        if ($userTransferData) {
            $transfer_worker_location = $userTransferData->type;
            if ($transfer_worker_location == '1099') {
                $transfer_worker_location_date = $userTransferData->contractor_transfer_date;
            } else {
                $transfer_worker_location_date = $userTransferData->employee_transfer_date;
            }
        }

        // Fetching the user business address data SIM-6582
        $address_data = UsersBusinessAddress::where('user_id', $userId)->first();
        $OnboardingEmployee = OnboardingEmployees::where('user_id', $user->id)->first();

        // Fetch draft/incomplete onboarding record (status_id in [4,7,8,9,10] are incomplete states)
        $DraftOnboardingEmployee = OnboardingEmployees::where('user_id', $user->id)
            ->whereIn('status_id', [8]) // Draft/incomplete statuses
            ->where('is_new_contract', 1) // Draft/incomplete statuses
            ->orderBy('created_at', 'desc')
            ->first();

        $response = [
            'id' => $userId,
            'onboarding_employee_id' => $OnboardingEmployee?->id,
            'draft_onboarding_employee_id' => $DraftOnboardingEmployee?->id ?? null,
            'employee_id' => $user->employee_id,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'position' => $userOrganization?->position?->position_name,
            'position_id' => $userOrganization?->position?->id,
            'sub_position_id' => $userOrganization?->subPositionId?->id,
            'sub_position_name' => $userOrganization?->subPositionId?->position_name,
            'main_role' => $userOrganization?->subPositionId?->is_selfgen,
            'group_id' => $user->group_id,
            'group_name' => $user?->groupDetail?->name,
            'manager_id' => $manager?->manager_id,
            'manager_name' => $manager?->user ? $manager?->user?->first_name.' '.$manager?->user?->last_name : null,
            'is_manager' => $isManager?->is_manager ?? 0,
            'is_super_admin' => $user->is_super_admin,
            'office_id' => $user->office_id,
            'total_employee' => $totalEmployee,
            'mobile_no' => $user->mobile_no,
            'recruiter_id' => $user->recruiter_id,
            'recruiter_name' => $user?->recruiter ? $user?->recruiter?->first_name.' '.$user?->recruiter?->last_name : null,
            'sex' => $user->sex,
            'dob' => $user->dob,
            'image' => $user->image,
            'email' => $user->email,
            'home_address' => $user->home_address,
            'home_address_line_1' => $user->home_address_line_1,
            'home_address_line_2' => $user->home_address_line_2,
            'home_address_state' => $user->home_address_state,
            'home_address_city' => $user->home_address_city,
            'home_address_zip' => $user->home_address_zip,
            'home_address_lat' => $user->home_address_lat,
            'home_address_long' => $user->home_address_long,
            'home_address_timezone' => $user->home_address_timezone,
            'business_address' => $address_data->business_address ?? null,
            'business_address_line_1' => $address_data->business_address_line_1 ?? null,
            'business_address_line_2' => $address_data->business_address_line_2 ?? null,
            'business_address_state' => $address_data->business_address_state ?? null,
            'business_address_city' => $address_data->business_address_city ?? null,
            'business_address_zip' => $address_data->business_address_zip ?? null,
            'business_address_lat' => $address_data->business_address_lat ?? null,
            'business_address_long' => $address_data->business_address_long ?? null,
            'business_address_timezone' => $address_data->business_address_timezone ?? null,
            'work_email' => $user->work_email,
            'city_id' => $user->city_id,
            'city' => $user?->city?->name,
            'state_id' => $user?->state_id,
            'state_name' => $user?->state?->name,
            'state_code' => $user?->state?->state_code,
            'state' => $user?->state,
            'office' => $user?->office,
            'zip_code' => $user->zip_code,
            'stop_payroll' => $user->stop_payroll,
            'disable_login' => $user->disable_login,
            'status_id' => $user->status_id,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_phone' => $user->emergency_phone,
            'emergency_contact_relationship' => $user->emergency_contact_relationship,
            'emergrncy_contact_address' => $user->emergrncy_contact_address,
            'emergency_address_line_1' => $user->emergency_address_line_1,
            'emergency_address_line_2' => $user->emergency_address_line_2,
            'emergency_address_lat' => $user->emergency_address_lat,
            'emergency_address_long' => $user->emergency_address_long,
            'emergency_address_timezone' => $user->emergency_address_timezone,
            'emergrncy_contact_city' => $user->emergrncy_contact_city,
            'emergrncy_contact_state' => $user->emergrncy_contact_state,
            'emergrncy_contact_zip_code' => $user->emergrncy_contact_zip_code,
            'additional_info_for_employee_to_get_started' => $user->additional_info_for_employee_to_get_started,
            'employee_personal_detail' => $user->employee_personal_detail,
            'tax_information' => $user->tax_information,
            'social_sequrity_no' => $user->social_sequrity_no,
            'name_of_bank' => $user->name_of_bank,
            'routing_no' => $user->routing_no,
            'account_no' => $user->account_no,
            'confirm_account_no' => $user->confirm_account_no,
            'account_name' => $user->account_name,
            'type_of_account' => $user->type_of_account,
            'additional_recruiter1_id' => @$user->additionalDetail[0]->recruiter_id,
            'additional_recruiter1_name' => $additionalRecruiter,
            'additional_recruiter1_type' => $systemType,
            'additional_recruiter2_id' => @$user->additionalDetail[1]->recruiter_id,
            'additional_recruiter2_name' => $additionalRecruiter2,
            'additional_recruiter2_type' => $systemType2,
            'team_id' => $user->team_id,
            'team_name' => $user?->team?->team_name,
            'entity_type' => $user->entity_type,
            'business_type' => $user->business_type,
            'business_name' => $user->business_name,
            'business_ein' => $user->business_ein,
            'user_profile_s3' => $user->image ? s3_getTempUrl(config('app.domain_name').'/'.$user->image) : null,
            'first_time_changed_password' => $user->first_time_changed_password,
            'additional_locations' => $additionalOffice,
            'everee_workerId' => $user->everee_workerId,
            'everee_onboarding_process' => $evereeOnboardingProcess,
            'worker_type' => ($user->worker_type != null) ? $user->worker_type : '1099',
            'dismiss' => isUserDismisedOn($userId, $effectiveDate) ? 1 : 0,
            'terminate' => $user?->isTodayTerminated() ? 1 : 0,
            'contract_ended' => $user?->contract_ended ?? 0,
            'last_effective_date' => null,
            'period_of_agreement_end_date' => $user?->end_date,
            'period_of_agreement_start_date' => $user?->period_of_agreement_start_date,
            'transfer_worker_location' => $transfer_worker_location,
            'transfer_worker_location_date' => $transfer_worker_location_date,
            'everee_error_msg' => checkEvereeErrorStructured($user->id),
            'employee_admin_only_fields' => $user?->employee_admin_only_fields,
            'arena_theme' => $user?->activeThemePreference?->theme_name ?? 'default',
            'theme_config' => $user?->activeThemePreference?->theme_config ?? null,
            'flexible_ids' => $user->flexibleIds ? $user->flexibleIds->map(function ($flexId) {
                return [
                    'id' => $flexId->id,
                    'user_id' => $flexId->user_id,
                    'flexible_id_type' => $flexId->flexible_id_type,
                    'flexible_id_value' => $flexId->flexible_id_value,
                ];
            })->toArray() : [],
        ];
        $this->successResponse('Successfully.', 'userPersonalDetails', $response);
    }

    public function userOrganizationDetails($id)
    {
        $user = User::withoutGlobalScopes()->with('state', 'office', 'departmentDetail', 'recruiter', 'additionalDetail')->find($id);
        if (! $user) {
            $this->errorResponse('User not found!!', 'userOrganizationDetails', '', 400);
        }

        $userId = $user->id;
        $effectiveDate = date('Y-m-d');
        $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $isManager) {
            $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $manager) {
            $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $userOrganization = UserOrganizationHistory::with('position', 'subPositionId')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userOrganization) {
            $userOrganization = UserOrganizationHistory::with('position', 'subPositionId')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $currentAdditional) {
            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $additionalLocations = AdditionalLocations::with('state', 'office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
        $additionalOffice = [];
        $dateArray = [$isManager?->effective_date, $manager?->effective_date, $userOrganization?->effective_date, $currentAdditional?->effective_date];
        
        // Check if Custom Sales Fields feature is enabled (for display formatting)
        // Use cached company profile to avoid repeated database queries
        $isCustomFieldsEnabledForDisplay = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled();
        
        foreach ($additionalLocations as $additionalLocation) {
            $officeId = $additionalLocation?->office?->id;
            $additionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'office_id' => $officeId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $additionalOverride) {
                $additionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'office_id' => $officeId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $additionalOffice[] = [
                'state_name' => $additionalLocation?->state?->name,
                'office_name' => $additionalLocation?->office?->office_name,
                'effective_date' => $additionalLocation->effective_date,
                'overrides_amount' => $additionalOverride?->office_overrides_amount ?? null,
                // Only use custom_field_X format when feature is enabled
                'overrides_type' => ($isCustomFieldsEnabledForDisplay && $additionalOverride?->office_overrides_type === 'custom field' && $additionalOverride?->office_custom_sales_field_id) ? 'custom_field_' . $additionalOverride->office_custom_sales_field_id : ($additionalOverride?->office_overrides_type ?? null),
            ];

            $dateArray[] = $additionalLocation?->effective_date;
            $dateArray[] = $additionalOverride?->override_effective_date;
        }

        $additionalRecruiter = $user->additionalDetail->map(function ($recruiter) {
            return [
                'recruiter_first_name' => $recruiter?->additionalRecruiterDetail?->first_name,
                'recruiter_last_name' => $recruiter?->additionalRecruiterDetail?->last_name,
            ];
        })->toArray();

        $wages = null;
        $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userWagesHistory) {
            $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        if ($userWagesHistory) {
            $positionFrequency = PositionPayFrequency::with('frequencyType')->where('position_id', $userOrganization?->sub_position_id)->first();
            $frequency = $userWagesHistory?->pay_rate_type;
            if ($positionFrequency) {
                $frequency = $positionFrequency?->frequencyType?->name;
            }

            $wages = [
                'pay_type' => $userWagesHistory?->pay_type,
                'pay_rate' => $userWagesHistory?->pay_rate,
                'pay_rate_type' => $userWagesHistory?->pay_rate_type,
                'frequency_name' => $frequency,
                'pto_hours' => $userWagesHistory?->pto_hours,
                'unused_pto_expires' => $userWagesHistory?->unused_pto_expires,
                'expected_weekly_hours' => $userWagesHistory?->expected_weekly_hours,
            ];
            $dateArray[] = $userWagesHistory?->effective_date;
        }

        $userAgreement = UserAgreementHistory::with('hiringBy')->where(['user_id' => $userId])->where('period_of_agreement', '<=', $effectiveDate)->orderBy('period_of_agreement', 'DESC')->orderBy('created_at', 'DESC')->first();
        if (! $userAgreement) {
            $userAgreement = UserAgreementHistory::with('hiringBy')->where(['user_id' => $userId])->where('period_of_agreement', '>=', $effectiveDate)->orderBy('period_of_agreement', 'ASC')->orderBy('created_at', 'DESC')->first();
        }
        $agreement = [
            'hired_date' => date('Y-m-d', strtotime($user?->created_at)),
            'probation_period' => ($userAgreement && $userAgreement?->probation_period != 'None') ? $userAgreement?->probation_period : null,
            'hiring_bonus_amount' => $userAgreement?->hiring_bonus_amount,
            'date_to_be_paid' => ($userAgreement && $userAgreement?->date_to_be_paid) ? $userAgreement?->date_to_be_paid : null,
            'period_of_agreement_start_date' => ($userAgreement && $userAgreement?->period_of_agreement) ? $userAgreement?->period_of_agreement : null,
            'end_date' => ($userAgreement && $userAgreement?->end_date) ? $userAgreement?->end_date : null,
            'offer_include_bonus' => $userAgreement?->offer_include_bonus,
            'offer_expiry_date' => $userAgreement?->offer_expiry_date,
            'is_background_verificaton' => $userAgreement?->is_background_verificaton,
            'hiring_signature' => $userAgreement?->hiring_signature,
            'hiring_by' => $userAgreement?->hiringBy ? $userAgreement?->hiringBy?->first_name.' '.$userAgreement?->hiringBy?->last_name : null,
        ];

        $deductions = [];
        $deductionHistory = UserDeductionHistory::select('user_id', 'effective_date')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
        if (! $deductionHistory) {
            $deductionHistory = UserDeductionHistory::select('user_id', 'effective_date')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->first();
        }
        if ($deductionHistory) {
            $dateArray[] = $deductionHistory?->effective_date;
            $userDeductions = UserDeductionHistory::with('costCenter:id,name,status')
                ->where(function ($query) {
                    $query->whereNull('cost_center_id')
                        ->orWhereHas('costcenter', function ($q) {
                            $q->where('status', 1);
                        });
                })
                ->where(['user_id' => $userId, 'effective_date' => $deductionHistory->effective_date])->get();
            $deductionPayPeriod = PayrollDeductions::select('user_id', 'pay_period_from', 'pay_period_to')->where('user_id', $userId)->orderBy('pay_period_from', 'DESC')->first();
            $positionDeduction = PositionCommissionDeduction::where('position_id', $user->sub_position_id)->get();
            $costCenterIds = $positionDeduction->pluck('cost_center_id')->toArray();

            foreach ($userDeductions as $userDeduction) {
                if ($deductionPayPeriod) {
                    $outstanding = PayrollDeductions::select('outstanding')->where(['user_id' => $userId, 'cost_center_id' => $userDeduction->cost_center_id, 'pay_period_from' => $deductionPayPeriod->pay_period_from, 'pay_period_to' => $deductionPayPeriod->pay_period_to])->first();
                }

                $checkOutstanding = isset($outstanding->outstanding) ? $outstanding->outstanding : 0;
                if (in_array($userDeduction->cost_center_id, $costCenterIds)) {
                    $isDelete = 0;
                } else {
                    $isDelete = 1;
                }
                if ($isDelete == 1 && $checkOutstanding == 0) {
                    continue;
                }

                $periods = $positionDeduction->where('cost_center_id', $userDeduction->cost_center_id)->first();
                $deductions[] = [
                    'deduction_type' => $userDeduction?->deduction_type ? $userDeduction?->deduction_type : '$',
                    'cost_center_name' => $userDeduction?->costCenter?->name,
                    'ammount_par_paycheck' => $userDeduction?->amount_par_paycheque ?? 0,
                    'outstanding' => $checkOutstanding,
                    'pay_period_from' => $periods?->pay_period_from ?? null,
                    'pay_period_to' => $periods?->pay_period_to ?? null,
                    'is_deleted' => $isDelete,
                ];
            }
        }

        $effectiveDate = Carbon::parse($effectiveDate);
        $closestDate = null;
        $minDiff = PHP_INT_MAX;
        foreach ($dateArray as $date) {
            if ($date) {
                $currentDate = Carbon::parse($date);
                $diff = $effectiveDate->diffInSeconds($currentDate);

                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $closestDate = $date;
                }
            }
        }

        $response = [
            'id' => $userId,
            'effective_date' => $closestDate,
            'organization' => [
                'state_name' => $user?->state?->name,
                'office_name' => $user?->office?->office_name,
                'department_name' => $user?->departmentDetail?->name,
                'position_name' => $userOrganization?->position?->position_name,
                'sub_position_name' => $userOrganization?->subPositionId?->position_name,
                'is_manager' => $isManager?->is_manager ?? 0,
                'manager_name' => $manager?->user ? $manager?->user?->first_name.' '.$manager?->user?->last_name : null,
                'team_name' => $manager?->team?->team_name,
                'recruiter_name' => $user?->recruiter ? $user?->recruiter?->first_name.' '.$user?->recruiter?->last_name : null,
                'additional_recruter' => $additionalRecruiter,
                'self_gen_accounts' => $userOrganization?->self_gen_accounts ?? 0,
                'additional_locations' => $additionalOffice,
            ],
            'user_wages' => $wages,
            'agreement' => $agreement,
            'deduction' => $deductions,
        ];

        $this->successResponse('Successfully.', 'userOrganizationDetails', $response);
    }

    public function userCompensationDetails($id, $productId)
    {
        $user = User::find($id);
        if (! $user) {
            $this->errorResponse('User not found!!', 'userOrganizationDetails', '', 400);
        }

        $userId = $user->id;
        Artisan::call('tier:sync', ['user_id' => $userId]);
        $effectiveDate = date('Y-m-d');
        $employeeCompensation = [];
        $tierSetting = CompanySetting::where(['type' => 'tier', 'status' => '1'])->first();
        $userOrganization = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userOrganization) {
            $userOrganization = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $position = Positions::withoutGlobalScope('notSuperAdmin')->where('id', $userOrganization?->sub_position_id)->first();
        $corePositions = [];
        if ($position?->is_selfgen == '1') {
            $corePositions = [2, 3, null];
        } elseif ($position?->is_selfgen == '2' || $position?->is_selfgen == '3') {
            $corePositions = [$position?->is_selfgen];
        } elseif ($position?->is_selfgen == '0') {
            $corePositions = [2];
        }

        foreach ($corePositions as $corePosition) {
            $positionCommission = PositionCommission::where(['position_id' => $position->id, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $positionCommission) {
                $positionCommission = PositionCommission::where(['position_id' => $position->id, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereNull('effective_date')->first();
            }
            $commissionLimit = $positionCommission?->commission_limit ?? 0;
            $positionUpFront = PositionCommissionUpfronts::where(['position_id' => $position->id, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $positionUpFront) {
                $positionUpFront = PositionCommissionUpfronts::where(['position_id' => $position->id, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereNull('effective_date')->first();
            }
            $upfrontLimit = $positionUpFront?->upfront_limit ?? 0;

            $upFronts = [];
            $userUpfront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereDate('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userUpfront) {
                $userUpfront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereDate('upfront_effective_date', '>=', $effectiveDate)->orderBy('upfront_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $upfrontHistories = UserUpfrontHistory::with('tiers', 'schema')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition, 'upfront_effective_date' => $userUpfront?->upfront_effective_date])->get();
            foreach ($upfrontHistories as $key => $upfrontHistory) {
                $upFrontCurrentTier = null;
                $currentTierDuration = null;
                if ($tierSetting) {
                    $upFrontCurrentTier = UsersCurrentTierLevel::where(['user_id' => $userId, 'product_id' => $productId, 'type' => 'Upfront', 'sub_type' => 'm'.($key + 1)])->first();
                    if ($upfrontHistory?->tiers_id) {
                        $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $upfrontHistory?->tiers_id)->first();
                        if ($tierSchema) {
                            $currentTierDuration = getDurationForTier($tierSchema, $userOrganization, date('Y-m-d'));
                        }
                    }
                }

                $transformedType = $this->transformCustomFieldType($upfrontHistory?->upfront_sale_type, $upfrontHistory?->custom_sales_field_id);
                $upFronts[] = [
                    'name' => $upfrontHistory?->schema?->name,
                    'on_trigger' => $upfrontHistory?->schema?->on_trigger,
                    'upfront_pay_amount' => $upfrontHistory?->upfront_pay_amount,
                    'upfront_sale_type' => $transformedType,
                    'upfront_effective_date' => $upfrontHistory?->upfront_effective_date,
                    'upfront_current_tiers' => isset($upFrontCurrentTier->current_level) ? $upFrontCurrentTier->current_level : null,
                    'upfront_upcoming_tiers' => isset($upFrontCurrentTier->remaining_level) ? $upFrontCurrentTier->remaining_level : null,
                    'upfront_tiers_sales_amount' => isset($upFrontCurrentTier->current_value) ? $upFrontCurrentTier->current_value : null,
                    'upfront_revenue_need_to_sold' => isset($upFrontCurrentTier->remaining_value) ? $upFrontCurrentTier->remaining_value : null,
                    'upfront_tiers_maxed' => isset($upFrontCurrentTier->maxed) ? $upFrontCurrentTier->maxed : null,
                    'upfront_tiers_start_end' => @$currentTierDuration['start_date'].' - '.@$currentTierDuration['end_date'],
                    'upfront_limit' => $upfrontLimit,
                    'upfront_tiers_status' => @$upfrontHistory?->tiers_id ? 1 : 0,
                    'tiers_id' => $upfrontHistory?->tiers_id,
                    'tiers_range' => $upfrontHistory?->tiers,
                    'custom_sales_field_id' => $this->getCustomFieldIdForDisplay($upfrontHistory?->upfront_sale_type, $upfrontHistory?->custom_sales_field_id),
                ];
            }

            $redLine = null;
            $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '<=', $effectiveDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $redLineHistory) {
                $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '>=', $effectiveDate)->orderBy('start_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            if ($redLineHistory) {
                $redLine = [
                    'redline' => $redLineHistory->redline,
                    'redline_type' => $redLineHistory->redline_type,
                    'redline_amount_type' => $redLineHistory->redline_amount_type,
                    'redline_effective_date' => $redLineHistory->start_date,
                ];
            }

            // Use date + 1 day to account for timezone differences between client and server
            $effectiveDatePlusOne = date('Y-m-d', strtotime($effectiveDate . ' +1 day'));
            $userCommissionHistory = UserCommissionHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereDate('commission_effective_date', '<=', $effectiveDatePlusOne)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userCommissionHistory) {
                $userCommissionHistory = UserCommissionHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->whereDate('commission_effective_date', '>=', $effectiveDate)->orderBy('commission_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $currentTierDuration = null;
            $commissionCurrentTier = null;
            if ($tierSetting) {
                $commissionCurrentTier = UsersCurrentTierLevel::where(['user_id' => $userId, 'product_id' => $productId, 'type' => 'Commission', 'sub_type' => 'Commission'])->first();
                if ($userCommissionHistory?->tiers_id) {
                    $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $userCommissionHistory?->tiers_id)->first();
                    if ($tierSchema) {
                        $currentTierDuration = getDurationForTier($tierSchema, $userOrganization, date('Y-m-d'));
                    }
                }
            }
            $employeeCompensation[] = [
                'core_position_id' => $corePosition,
                'redline' => $redLine,
                'milestone_count' => count($upfrontHistories),
                'compensation_current_tiers' => isset($commissionCurrentTier->current_level) ? $commissionCurrentTier->current_level : null,
                'compensation_upcoming_tiers' => isset($commissionCurrentTier->remaining_level) ? $commissionCurrentTier->remaining_level : null,
                'compensation_tiers_sales_amount' => isset($commissionCurrentTier->current_value) ? $commissionCurrentTier->current_value : null,
                'compensation_revenue_need_to_sold' => isset($commissionCurrentTier->remaining_value) ? $commissionCurrentTier->remaining_value : null,
                'compensation_tiers_maxed' => isset($commissionCurrentTier->maxed) ? $commissionCurrentTier->maxed : null,
                'compensation_tiers_start_end' => @$currentTierDuration['start_date'].' - '.@$currentTierDuration['end_date'],
                'compensation_limit' => $commissionLimit,
                'commission' => [
                    'commission' => $userCommissionHistory?->commission,
                    'commission_type' => $this->transformCustomFieldType($userCommissionHistory?->commission_type, $userCommissionHistory?->custom_sales_field_id),
                    'commission_tiers_status' => @$userCommissionHistory?->tiers_id ? 1 : 0,
                    'tiers_id' => $userCommissionHistory?->tiers_id,
                    'tiers_range' => $userCommissionHistory?->tiers,
                    'commission_effective_date' => $userCommissionHistory?->commission_effective_date,
                    'custom_sales_field_id' => $this->getCustomFieldIdForDisplay($userCommissionHistory?->commission_type, $userCommissionHistory?->custom_sales_field_id),
                ],
                'upfront' => $upFronts,
            ];
        }

        $override = null;
        $additionalOffice = [];
        $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $currentAdditional) {
            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $additionalLocations = AdditionalLocations::with('state', 'office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
        foreach ($additionalLocations as $additionalLocation) {
            $officeId = $additionalLocation?->office?->id;
            $additionalOverride = UserAdditionalOfficeOverrideHistory::with('tearsRange')->where(['user_id' => $userId, 'product_id' => $productId, 'office_id' => $officeId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $additionalOverride) {
                $additionalOverride = UserAdditionalOfficeOverrideHistory::with('tearsRange')->where(['user_id' => $userId, 'product_id' => $productId, 'office_id' => $officeId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }

            $currentTier = null;
            $currentTierDuration = null;
            if ($tierSetting) {
                $currentTier = UsersCurrentTierLevel::where(['user_id' => $userId, 'product_id' => $productId, 'office_id' => $officeId, 'type' => 'Override', 'sub_type' => 'Additional Office'])->first();
                if ($additionalOverride?->tiers_id) {
                    $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $additionalOverride?->tiers_id)->first();
                    if ($tierSchema) {
                        $currentTierDuration = getDurationForTier($tierSchema, $userOrganization, date('Y-m-d'));
                    }
                }
            }
            $additionalOffice[] = [
                'state_name' => $additionalLocation?->state?->name,
                'office_name' => $additionalLocation?->office?->office_name,
                'effective_date' => $additionalOverride?->override_effective_date,
                'overrides_amount' => $additionalOverride?->office_overrides_amount ?? null,
                'overrides_type' => $this->transformCustomFieldType($additionalOverride?->office_overrides_type, $additionalOverride?->office_custom_sales_field_id),
                'overrides_tiers_status' => @$additionalOverride?->tiers_id ? 1 : 0,
                'current_tiers' => isset($currentTier->current_level) ? $currentTier->current_level : null,
                'upcoming_tiers' => isset($currentTier->remaining_level) ? $currentTier->remaining_level : null,
                'tiers_sales_amount' => isset($currentTier->current_value) ? $currentTier->current_value : null,
                'revenue_need_to_sold' => isset($currentTier->remaining_value) ? $currentTier->remaining_value : null,
                'tiers_maxed' => isset($currentTier->maxed) ? $currentTier->maxed : null,
                'tiers_start_end' => @$currentTierDuration['start_date'].' - '.@$currentTierDuration['end_date'],
                'tiers_id' => $additionalOverride?->tiers_id,
                'tiers_range' => $additionalOverride?->tearsRange,
                'office_custom_sales_field_id' => $this->getCustomFieldIdForDisplay($additionalOverride?->office_overrides_type, $additionalOverride?->office_custom_sales_field_id),
            ];
        }

        $overrideHistory = UserOverrideHistory::with('directTiers', 'indirectTiers', 'officeTiers')->where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $overrideHistory) {
            $overrideHistory = UserOverrideHistory::with('directTiers', 'indirectTiers', 'officeTiers')->where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        if ($position) {
            $over = PositionOverride::where(['position_id' => $position->id, 'product_id' => $productId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($over) {
                $positionOverrides = PositionOverride::with('overridesDetail')->where(['position_id' => $position->id, 'product_id' => $productId, 'effective_date' => $over->effective_date])->get();
            } else {
                $positionOverrides = PositionOverride::with('overridesDetail')->where(['position_id' => $position->id, 'product_id' => $productId])->whereNull('effective_date')->get();
            }
            foreach ($positionOverrides as $positionOverride) {
                $overrideKey = strtolower(str_replace(' ', '_', $positionOverride?->overridesDetail?->overrides_type)).'_limit';
                $override[$overrideKey] = $positionOverride->override_limit;
            }
        }
        if ($overrideHistory) {
            $directCurrentTier = null;
            $inDirectCurrentTier = null;
            $officeCurrentTier = null;
            $directTierDuration = null;
            $inDirectTierDuration = null;
            $officeTierDuration = null;
            if ($tierSetting) {
                $directCurrentTier = UsersCurrentTierLevel::where(['user_id' => $userId, 'product_id' => $productId, 'type' => 'Override', 'sub_type' => 'Direct'])->first();
                $inDirectCurrentTier = UsersCurrentTierLevel::where(['user_id' => $userId, 'product_id' => $productId, 'type' => 'Override', 'sub_type' => 'InDirect'])->first();
                $officeCurrentTier = UsersCurrentTierLevel::where(['user_id' => $userId, 'product_id' => $productId, 'type' => 'Override', 'sub_type' => 'Office'])->first();
                if ($directCurrentTier) {
                    $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $overrideHistory?->direct_tiers_id)->first();
                    if ($tierSchema) {
                        $directTierDuration = getDurationForTier($tierSchema, $userOrganization, date('Y-m-d'));
                    }
                }
                if ($inDirectCurrentTier) {
                    $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $overrideHistory?->indirect_tiers_id)->first();
                    if ($tierSchema) {
                        $inDirectTierDuration = getDurationForTier($tierSchema, $userOrganization, date('Y-m-d'));
                    }
                }
                if ($officeCurrentTier) {
                    $tierSchema = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration')->where('id', $overrideHistory?->office_tiers_id)->first();
                    if ($tierSchema) {
                        $officeTierDuration = getDurationForTier($tierSchema, $userOrganization, date('Y-m-d'));
                    }
                }
            }
            $override = array_merge($override, [
                'direct_overrides_amount' => $overrideHistory?->direct_overrides_amount,
                'direct_overrides_type' => $this->transformCustomFieldType($overrideHistory?->direct_overrides_type, $overrideHistory?->direct_custom_sales_field_id),
                'direct_overrides_tiers_status' => @$overrideHistory?->direct_tiers_id ? 1 : 0,
                'direct_tiers_id' => $overrideHistory?->direct_tiers_id,
                'indirect_overrides_amount' => $overrideHistory?->indirect_overrides_amount,
                'indirect_overrides_type' => $this->transformCustomFieldType($overrideHistory?->indirect_overrides_type, $overrideHistory?->indirect_custom_sales_field_id),
                'indirect_overrides_tiers_status' => @$overrideHistory?->indirect_tiers_id ? 1 : 0,
                'indirect_tiers_id' => $overrideHistory?->indirect_tiers_id,
                'office_overrides_amount' => $overrideHistory?->office_overrides_amount,
                'office_overrides_type' => $this->transformCustomFieldType($overrideHistory?->office_overrides_type, $overrideHistory?->office_custom_sales_field_id),
                'office_overrides_tiers_status' => @$overrideHistory?->office_tiers_id ? 1 : 0,
                'office_tiers_id' => $overrideHistory?->office_tiers_id,
                'direct_custom_sales_field_id' => $this->getCustomFieldIdForDisplay($overrideHistory?->direct_overrides_type, $overrideHistory?->direct_custom_sales_field_id),
                'indirect_custom_sales_field_id' => $this->getCustomFieldIdForDisplay($overrideHistory?->indirect_overrides_type, $overrideHistory?->indirect_custom_sales_field_id),
                'office_custom_sales_field_id' => $this->getCustomFieldIdForDisplay($overrideHistory?->office_overrides_type, $overrideHistory?->office_custom_sales_field_id),
                'office_stack_overrides_amount' => $overrideHistory?->office_stack_overrides_amount,
                'override_effective_date' => $overrideHistory?->override_effective_date,
                'direct_current_tiers' => isset($directCurrentTier->current_level) ? $directCurrentTier->current_level : null,
                'direct_upcoming_tiers' => isset($directCurrentTier->remaining_level) ? $directCurrentTier->remaining_level : null,
                'direct_tiers_sales_amount' => isset($directCurrentTier->current_value) ? $directCurrentTier->current_value : null,
                'direct_revenue_need_to_sold' => isset($directCurrentTier->remaining_value) ? $directCurrentTier->remaining_value : null,
                'direct_tiers_maxed' => isset($directCurrentTier->maxed) ? $directCurrentTier->maxed : null,
                'direct_tiers_start_end' => @$directTierDuration['start_date'].' - '.@$directTierDuration['end_date'],
                'indirect_current_tiers' => isset($inDirectCurrentTier->current_level) ? $inDirectCurrentTier->current_level : null,
                'indirect_upcoming_tiers' => isset($inDirectCurrentTier->remaining_level) ? $inDirectCurrentTier->remaining_level : null,
                'indirect_tiers_sales_amount' => isset($inDirectCurrentTier->current_value) ? $inDirectCurrentTier->current_value : null,
                'indirect_revenue_need_to_sold' => isset($inDirectCurrentTier->remaining_value) ? $inDirectCurrentTier->remaining_value : null,
                'indirect_tiers_maxed' => isset($inDirectCurrentTier->maxed) ? $directCurrentTier->maxed : null,
                'indirect_tiers_start_end' => @$inDirectTierDuration['start_date'].' - '.@$inDirectTierDuration['end_date'],
                'office_current_tiers' => isset($officeCurrentTier->current_level) ? $officeCurrentTier->current_level : null,
                'office_upcoming_tiers' => isset($officeCurrentTier->remaining_level) ? $officeCurrentTier->remaining_level : null,
                'office_tiers_sales_amount' => isset($officeCurrentTier->current_value) ? $officeCurrentTier->current_value : null,
                'office_revenue_need_to_sold' => isset($officeCurrentTier->remaining_value) ? $officeCurrentTier->remaining_value : null,
                'office_tiers_maxed' => isset($officeCurrentTier->maxed) ? $directCurrentTier->maxed : null,
                'office_tiers_start_end' => @$officeTierDuration['start_date'].' - '.@$officeTierDuration['end_date'],
                'direct_tiers' => $overrideHistory?->directTiers,
                'indirect_tiers' => $overrideHistory?->indirectTiers,
                'office_tiers' => $overrideHistory?->officeTiers,
            ]);
        }

        $withheld = null;
        $withheldHistory = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '<=', $effectiveDate)->orderBy('withheld_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $withheldHistory) {
            $withheldHistory = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '>=', $effectiveDate)->orderBy('withheld_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        if ($withheldHistory) {
            $withheld = [
                'withheld_amount' => $withheldHistory?->withheld_amount,
                'withheld_type' => $withheldHistory?->withheld_type,
                'withheld_effective_date' => $withheldHistory?->withheld_effective_date,
            ];
        }

        $effectiveSince = getLastEffectiveDates($userId, $effectiveDate, $productId);
        $effectiveDate = Carbon::parse($effectiveDate);
        $closestDate = null;
        $minDiff = PHP_INT_MAX;
        foreach ($effectiveSince as $date) {
            if ($date) {
                $currentDate = Carbon::parse($date);
                $diff = $effectiveDate->diffInSeconds($currentDate);

                if ($diff < $minDiff) {
                    $minDiff = $diff;
                    $closestDate = $date;
                }
            }
        }

        $response = [
            'id' => $userId,
            'effective_date' => $closestDate,
            'main_role' => $position?->is_selfgen,
            'sub_position_id' => $position?->id,
            'employee_compensation' => $employeeCompensation,
            'organization' => [
                'additional_locations' => $additionalOffice,
            ],
            'override' => $override,
            'withheld' => $withheld,
        ];

        $this->successResponse('Successfully.', 'userOrganizationDetails', $response);
    }

    public function employmentPackageDetails(Request $request)
    {
        $this->checkValidations($request->all(), [
            'user_id' => 'required',
            'wizard_type' => 'required|in:change_position,transfer_user,update_commissions,update_organization',
            'date_selection_type' => 'required|in:apply_to_all_sales,immediately,select_start_date,these_changes_are_temporary',
            'effective_start_date' => 'required_if:date_selection_type,select_start_date|required_if:date_selection_type,these_changes_are_temporary',
            'effective_end_date' => 'required_if:date_selection_type,these_changes_are_temporary',
        ]);

        $userId = $request->user_id;
        $user = User::with('state', 'office', 'departmentDetail', 'recruiter', 'additionalDetail')->find($userId);
        if (! $user) {
            $this->errorResponse('User not found!!', 'userOrganizationDetails', '', 400);
        }

        $effectiveDate = date('Y-m-d');
        $effectiveStartDate = $request->effective_start_date;
        $effectiveEndDate = $request->effective_end_date ?? null;
        $userOrganization = UserOrganizationHistory::with('position')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $userOrganization) {
            $userOrganization = UserOrganizationHistory::with('position')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }

        $position = Positions::withoutGlobalScope('notSuperAdmin')->with([
            'deductionName.costCenter',
            'deductionLimit',
            'deductionSetting',
            'payFrequency.frequencyType',
        ])->where('id', $userOrganization?->sub_position_id)->first();

        $positionProducts = PositionProduct::where(['position_id' => $userOrganization?->sub_position_id])->where('effective_date', '<=', $effectiveDate)->first();
        if ($positionProducts) {
            $positionProducts = PositionProduct::with('productName')->where(['position_id' => $userOrganization?->sub_position_id, 'effective_date' => $positionProducts->effective_date])->get();
        } else {
            $positionProducts = PositionProduct::with('productName')->where(['position_id' => $userOrganization?->sub_position_id])->whereNull('effective_date')->get();
        }

        $upfrontData = [];
        $overrideData = [];
        $commissionData = [];
        $settlementData = [];
        $overrideSetting = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();
        $reconciliationSetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
        
        // Check if Custom Sales Fields feature is enabled (for display formatting)
        // Use cached company profile to avoid repeated database queries
        $isCustomFieldsEnabledForPackage = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled();
        $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $currentAdditional) {
            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $additionalLocations = AdditionalLocations::with('state', 'city', 'office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
        $additionalOffice = [];
        $additionalOfficeEffectiveDate = $currentAdditional?->effective_date;
        foreach ($additionalLocations as $additionalLocation) {
            $additionalOffice[] = [
                'id' => $additionalLocation?->id,
                'state_id' => $additionalLocation?->state?->id,
                'state_name' => $additionalLocation?->state?->name,
                'office_id' => $additionalLocation?->office?->id,
                'office_name' => $additionalLocation?->office?->office_name,
                'effective_date' => $additionalLocation->effective_date,
            ];
        }
        foreach ($positionProducts as $product) {
            if (($request->wizard_type == 'change_position' || $request->wizard_type == 'update_commissions')) {
                $commission = PositionCommission::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($commission) {
                    $commissions = PositionCommission::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'commission_status' => 1, 'effective_date' => $commission->effective_date])->get();
                } else {
                    $commissions = PositionCommission::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'commission_status' => 1])->whereNull('effective_date')->get();
                }

                $commissionData = array_merge($commissionData, $commissions->groupBy('commission_status')
                    ->map(function ($groupByStatus) use ($userId, $effectiveDate, $effectiveStartDate, $effectiveEndDate, $isCustomFieldsEnabledForPackage) {
                        $productId = $groupByStatus->first()->product_id;

                        return [
                            'product_id' => $groupByStatus->first()->product_id,
                            'commission_status' => $groupByStatus->first()->commission_status,
                            'commission_data' => $groupByStatus->map(function ($item) use ($userId, $productId, $effectiveDate, $effectiveStartDate, $effectiveEndDate, $isCustomFieldsEnabledForPackage) {
                                $commissionConflicts = [];
                                $corePositionId = $item->core_position_id;
                                if (request()->input('wizard_type') == 'update_commissions') {
                                    if (request()->input('date_selection_type') == 'these_changes_are_temporary') {
                                        $commissionConflicts = [];
                                        $userCommissions = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->whereBetween('commission_effective_date', [$effectiveStartDate, $effectiveEndDate])->groupBy('commission_effective_date')->orderBy('commission_effective_date')->get();
                                        if (count($userCommissions) != 0) {
                                            foreach ($userCommissions as $key => $userCommission) {
                                                $endDate = @$userCommissions[$key + 1]->commission_effective_date ? $userCommissions[$key + 1]->commission_effective_date : null;
                                                $infinite = $endDate ? false : true;
                                                $commissionConflicts[] = [
                                                    'title' => 'Commission',
                                                    'value' => $userCommission->commission,
                                                    // Only use custom_field_X format when feature is enabled
                                                    'value_type' => ($isCustomFieldsEnabledForPackage && $userCommission->commission_type === 'custom field' && $userCommission->custom_sales_field_id) ? 'custom_field_' . $userCommission->custom_sales_field_id : $userCommission->commission_type,
                                                    'start_time' => $userCommission->commission_effective_date,
                                                    'end_time' => $endDate,
                                                    'infinite' => $infinite,
                                                    'conflicted' => 0,
                                                ];
                                            }

                                            $commissionConflicts[] = [
                                                'title' => 'Commission',
                                                'value' => null,
                                                'value_type' => null,
                                                'start_time' => $effectiveStartDate,
                                                'end_time' => $effectiveEndDate,
                                                'infinite' => false,
                                                'conflicted' => count($userCommissions) != 0 ? 1 : 0,
                                            ];
                                        } else {
                                            $commissionConflicts[] = [
                                                'title' => 'Commission',
                                                'value' => null,
                                                'value_type' => null,
                                                'start_time' => $effectiveStartDate,
                                                'end_time' => $effectiveEndDate,
                                                'infinite' => false,
                                                'conflicted' => 0,
                                            ];
                                        }
                                    } else {
                                        $conflictEndDate = null;
                                        $commissionConflicts = [];
                                        $userCommissions = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('commission_effective_date', '>=', $effectiveStartDate)->groupBy('commission_effective_date')->orderBy('commission_effective_date')->get();
                                        if (count($userCommissions) != 0) {
                                            foreach ($userCommissions as $key => $userCommission) {
                                                $endDate = @$userCommissions[$key + 1]->commission_effective_date ? $userCommissions[$key + 1]->commission_effective_date : null;
                                                $infinite = $endDate ? false : true;
                                                $commissionConflicts[] = [
                                                    'title' => 'Commission',
                                                    'value' => $userCommission->commission,
                                                    // Only use custom_field_X format when feature is enabled
                                                    'value_type' => ($isCustomFieldsEnabledForPackage && $userCommission->commission_type === 'custom field' && $userCommission->custom_sales_field_id) ? 'custom_field_' . $userCommission->custom_sales_field_id : $userCommission->commission_type,
                                                    'start_time' => $userCommission->commission_effective_date,
                                                    'end_time' => $endDate,
                                                    'infinite' => $infinite,
                                                    'conflicted' => 0,
                                                ];
                                                $conflictEndDate = $endDate;
                                            }

                                            $infinite = $conflictEndDate ? false : true;
                                            $commissionConflicts[] = [
                                                'title' => 'Commission',
                                                'value' => null,
                                                'value_type' => null,
                                                'start_time' => $effectiveStartDate,
                                                'end_time' => $conflictEndDate,
                                                'infinite' => $infinite,
                                                'conflicted' => count($userCommissions) != 0 ? 1 : 0,
                                            ];
                                        } else {
                                            $commissionConflicts[] = [
                                                'title' => 'Commission',
                                                'value' => null,
                                                'value_type' => null,
                                                'start_time' => $effectiveStartDate,
                                                'end_time' => null,
                                                'infinite' => true,
                                                'conflicted' => 0,
                                            ];
                                        }
                                    }
                                }

                                $userCommission = UserCommissionHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('commission_effective_date', '<=', $effectiveDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if (! $userCommission) {
                                    $userCommission = UserCommissionHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('commission_effective_date', '>=', $effectiveDate)->orderBy('commission_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                                }

                                return [
                                    'core_position_id' => $corePositionId,
                                    'self_gen_user' => $item->self_gen_user,
                                    'commission' => $userCommission?->commission,
                                    // Only use custom_field_X format when feature is enabled
                                    'commission_type' => ($isCustomFieldsEnabledForPackage && $userCommission?->commission_type === 'custom field' && $userCommission?->custom_sales_field_id) ? 'custom_field_' . $userCommission->custom_sales_field_id : $userCommission?->commission_type,
                                    'custom_sales_field_id' => ($isCustomFieldsEnabledForPackage && $userCommission?->commission_type === 'custom field') ? $userCommission?->custom_sales_field_id : null,
                                    'commission_effective_date' => $userCommission?->commission_effective_date,
                                    'commision_tiers_status' => @$userCommission?->tiers_id ? 1 : 0,
                                    'tiers_id' => $userCommission?->tiers_id,
                                    'tiers_range' => $userCommission?->tiers,
                                    'conflicts' => $commissionConflicts,
                                ];
                            })->values(),
                        ];
                    })->values()->toArray());

                $upfront = PositionCommissionUpfronts::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($upfront) {
                    $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'upfront_status' => 1, 'effective_date' => $upfront->effective_date])->get();
                } else {
                    $upFronts = PositionCommissionUpfronts::with('milestoneHistory.milestone', 'milestoneTrigger')->where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'upfront_status' => 1])->whereNull('effective_date')->get();
                }

                $upfrontData = array_merge($upfrontData, $upFronts->groupBy('upfront_status')->map(function ($groupByStatus) use ($userId, $effectiveDate, $effectiveStartDate, $effectiveEndDate, $isCustomFieldsEnabledForPackage) {
                    $productId = $groupByStatus->first()->product_id;

                    return [
                        'product_id' => $groupByStatus->first()->product_id,
                        'upfront_status' => $groupByStatus->first()->upfront_status,
                        'data' => $groupByStatus->groupBy('core_position_id')->map(function ($groupByCorePosition) use ($userId, $effectiveDate, $productId, $effectiveStartDate, $effectiveEndDate, $isCustomFieldsEnabledForPackage) {
                            $upFrontConflicts = [];
                            $corePositionId = $groupByCorePosition->first()->core_position_id;
                            if (request()->input('wizard_type') == 'update_commissions') {
                                if (request()->input('date_selection_type') == 'these_changes_are_temporary') {
                                    $userUpFronts = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->whereBetween('upfront_effective_date', [$effectiveStartDate, $effectiveEndDate])->groupBy('upfront_effective_date', 'core_position_id')->orderBy('upfront_effective_date')->get();
                                    if (count($userUpFronts) != 0) {
                                        foreach ($userUpFronts as $key => $userUpFront) {
                                            $endDate = @$userUpFronts[$key + 1]->upfront_effective_date ? $userUpFronts[$key + 1]->upfront_effective_date : null;
                                            $infinite = $endDate ? false : true;
                                            $upFrontConflicts[] = [
                                                'title' => 'Milestones',
                                                'value' => $userUpFront->upfront_pay_amount,
                                                // Only use custom_field_X format when feature is enabled
                                                'value_type' => ($isCustomFieldsEnabledForPackage && $userUpFront->upfront_sale_type === 'custom field' && $userUpFront->custom_sales_field_id) ? 'custom_field_' . $userUpFront->custom_sales_field_id : $userUpFront->upfront_sale_type,
                                                'start_time' => $userUpFront->upfront_effective_date,
                                                'end_time' => $endDate,
                                                'infinite' => $infinite,
                                                'conflicted' => 0,
                                            ];
                                        }

                                        $upFrontConflicts[] = [
                                            'title' => 'Milestones',
                                            'value' => null,
                                            'value_type' => null,
                                            'start_time' => $effectiveStartDate,
                                            'end_time' => $effectiveEndDate,
                                            'infinite' => false,
                                            'conflicted' => count($userUpFronts) != 0 ? 1 : 0,
                                        ];
                                    } else {
                                        $upFrontConflicts[] = [
                                            'title' => 'Milestones',
                                            'value' => null,
                                            'value_type' => null,
                                            'start_time' => $effectiveStartDate,
                                            'end_time' => $effectiveEndDate,
                                            'infinite' => false,
                                            'conflicted' => 0,
                                        ];
                                    }
                                } else {
                                    $conflictEndDate = null;
                                    $userUpFronts = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('upfront_effective_date', '>=', $effectiveStartDate)->groupBy('upfront_effective_date', 'core_position_id')->orderBy('upfront_effective_date')->get();
                                    if (count($userUpFronts) != 0) {
                                        foreach ($userUpFronts as $key => $userUpFront) {
                                            $endDate = @$userUpFronts[$key + 1]->upfront_effective_date ? $userUpFronts[$key + 1]->upfront_effective_date : null;
                                            $infinite = $endDate ? false : true;
                                            $upFrontConflicts[] = [
                                                'title' => 'Milestones',
                                                'value' => $userUpFront->upfront_pay_amount,
                                                // Only use custom_field_X format when feature is enabled
                                                'value_type' => ($isCustomFieldsEnabledForPackage && $userUpFront->upfront_sale_type === 'custom field' && $userUpFront->custom_sales_field_id) ? 'custom_field_' . $userUpFront->custom_sales_field_id : $userUpFront->upfront_sale_type,
                                                'start_time' => $userUpFront->upfront_effective_date,
                                                'end_time' => $endDate,
                                                'infinite' => $infinite,
                                                'conflicted' => 0,
                                            ];
                                            $conflictEndDate = $endDate;
                                        }

                                        $infinite = $conflictEndDate ? false : true;
                                        $upFrontConflicts[] = [
                                            'title' => 'Milestones',
                                            'value' => null,
                                            'value_type' => null,
                                            'start_time' => $effectiveStartDate,
                                            'end_time' => $conflictEndDate,
                                            'infinite' => $infinite,
                                            'conflicted' => count($userUpFronts) != 0 ? 1 : 0,
                                        ];
                                    } else {
                                        $upFrontConflicts[] = [
                                            'title' => 'Milestones',
                                            'value' => null,
                                            'value_type' => null,
                                            'start_time' => $effectiveStartDate,
                                            'end_time' => null,
                                            'infinite' => true,
                                            'conflicted' => 0,
                                        ];
                                    }
                                }
                            }

                            $upFront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (! $upFront) {
                                $upFront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('upfront_effective_date', '>=', $effectiveDate)->orderBy('upfront_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                            }

                            return [
                                'milestone_id' => $groupByCorePosition->first()->milestone_schema_id,
                                'core_position_id' => $corePositionId,
                                'self_gen_user' => $groupByCorePosition->first()->self_gen_user,
                                'upfront_effective_date' => $upFront?->upfront_effective_date,
                                'schemas' => $groupByCorePosition->groupBy('milestone_schema_id')->flatMap(function ($groupByMilestone) use ($userId, $effectiveDate, $productId, $corePositionId, $isCustomFieldsEnabledForPackage) {
                                    return $groupByMilestone->map(function ($item) use ($userId, $effectiveDate, $productId, $corePositionId, $isCustomFieldsEnabledForPackage) {
                                        $schemaId = $item->milestone_schema_trigger_id;
                                        $upFront = UserUpfrontHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId, 'milestone_schema_trigger_id' => $schemaId])->where('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                        if (! $upFront) {
                                            $upFront = UserUpfrontHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId, 'milestone_schema_trigger_id' => $schemaId])->where('upfront_effective_date', '>=', $effectiveDate)->orderBy('upfront_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                                        }

                                        return [
                                            'milestone_schema_trigger_id' => $schemaId,
                                            'upfront_pay_amount' => $upFront?->upfront_pay_amount,
                                            // Only use custom_field_X format when feature is enabled
                                            'upfront_sale_type' => ($isCustomFieldsEnabledForPackage && $upFront?->upfront_sale_type === 'custom field' && $upFront?->custom_sales_field_id) ? 'custom_field_' . $upFront->custom_sales_field_id : $upFront?->upfront_sale_type,
                                            'custom_sales_field_id' => ($isCustomFieldsEnabledForPackage && $upFront?->upfront_sale_type === 'custom field') ? $upFront?->custom_sales_field_id : null,
                                            'upfront_tiers_status' => @$upFront?->tiers_id ? 1 : 0,
                                            'tiers_id' => $upFront?->tiers_id,
                                            'tiers_range' => $upFront?->tiers,
                                        ];
                                    })->values();
                                })->values(),
                                'conflicts' => $upFrontConflicts,
                            ];
                        })->values(),
                    ];
                })->values()->toArray());

                $settlement = PositionReconciliations::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'status' => 1])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $settlement) {
                    $settlement = PositionReconciliations::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'status' => 1])->whereNull('effective_date')->first();
                }

                if ($reconciliationSetting && $settlement) {
                    $withHeldConflicts = null;
                    $productId = $settlement->product_id;
                    if (request()->input('wizard_type') == 'update_commissions') {
                        if (request()->input('date_selection_type') == 'these_changes_are_temporary') {
                            $withHeldConflicts = [];
                            $userWithHolds = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereBetween('withheld_effective_date', [$effectiveStartDate, $effectiveEndDate])->groupBy('withheld_effective_date')->orderBy('withheld_effective_date')->get();
                            if (count($userWithHolds) != 0) {
                                foreach ($userWithHolds as $key => $userWithHold) {
                                    $endDate = @$userWithHolds[$key + 1]->withheld_effective_date ? $userWithHolds[$key + 1]->withheld_effective_date : null;
                                    $infinite = $endDate ? false : true;
                                    $withHeldConflicts[] = [
                                        'title' => 'Withheld',
                                        'value' => $userWithHold->withheld_amount,
                                        'value_type' => $userWithHold->withheld_type,
                                        'start_time' => $userWithHold->withheld_effective_date,
                                        'end_time' => $endDate,
                                        'infinite' => $infinite,
                                        'conflicted' => 0,
                                    ];
                                }

                                $withHeldConflicts[] = [
                                    'title' => 'Withheld',
                                    'value' => null,
                                    'value_type' => null,
                                    'start_time' => $effectiveStartDate,
                                    'end_time' => $effectiveEndDate,
                                    'infinite' => false,
                                    'conflicted' => count($userWithHolds) != 0 ? 1 : 0,
                                ];
                            } else {
                                $withHeldConflicts[] = [
                                    'title' => 'Withheld',
                                    'value' => null,
                                    'value_type' => null,
                                    'start_time' => $effectiveStartDate,
                                    'end_time' => $effectiveEndDate,
                                    'infinite' => false,
                                    'conflicted' => 0,
                                ];
                            }
                        } else {
                            $withHeldConflicts = [];
                            $conflictEndDate = null;
                            $userWithHolds = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '>=', $effectiveStartDate)->groupBy('withheld_effective_date')->orderBy('withheld_effective_date')->get();
                            if (count($userWithHolds) != 0) {
                                foreach ($userWithHolds as $key => $userWithHold) {
                                    $endDate = @$userWithHolds[$key + 1]->withheld_effective_date ? $userWithHolds[$key + 1]->withheld_effective_date : null;
                                    $infinite = $endDate ? false : true;
                                    $withHeldConflicts[] = [
                                        'title' => 'Withheld',
                                        'value' => $userWithHold->withheld_amount,
                                        'value_type' => $userWithHold->withheld_type,
                                        'start_time' => $userWithHold->withheld_effective_date,
                                        'end_time' => $endDate,
                                        'infinite' => $infinite,
                                        'conflicted' => 0,
                                    ];
                                    $conflictEndDate = $endDate;
                                }

                                $infinite = $conflictEndDate ? false : true;
                                $withHeldConflicts[] = [
                                    'title' => 'Withheld',
                                    'value' => null,
                                    'value_type' => null,
                                    'start_time' => $effectiveStartDate,
                                    'end_time' => $conflictEndDate,
                                    'infinite' => $infinite,
                                    'conflicted' => count($userWithHolds) != 0 ? 1 : 0,
                                ];
                            } else {
                                $withHeldConflicts[] = [
                                    'title' => 'Withheld',
                                    'value' => null,
                                    'value_type' => null,
                                    'start_time' => $effectiveStartDate,
                                    'end_time' => null,
                                    'infinite' => true,
                                    'conflicted' => 0,
                                ];
                            }
                        }
                    }

                    $userWithHeld = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '<=', $effectiveDate)->orderBy('withheld_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $userWithHeld) {
                        $userWithHeld = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '>=', $effectiveDate)->orderBy('withheld_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                    }
                    $settlementData[] = [
                        'status' => $settlement->status,
                        'product_id' => $settlement->product_id,
                        'withheld_amount' => $userWithHeld?->withheld_amount,
                        'withheld_type' => $userWithHeld?->withheld_type,
                        'withheld_effective_date' => $userWithHeld?->withheld_effective_date,
                        'conflicts' => $withHeldConflicts,
                    ];
                }
            }

            if ($request->wizard_type == 'change_position' || $request->wizard_type == 'transfer_user' || $request->wizard_type == 'update_commissions' || $request->wizard_type == 'update_organization') {
                $groupedData = [];
                if ($overrideSetting) {
                    $override = PositionOverride::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($override) {
                        $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'effective_date' => $override->effective_date])->get();
                    } else {
                        $overrides = PositionOverride::with('overridesDetail')->where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id])->whereNull('effective_date')->get();
                    }

                    foreach ($overrides as $item) {
                        $productId = $item->product_id;
                        $override = UserOverrideHistory::with('directTiers', 'indirectTiers', 'officeTiers')->where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $override) {
                            $override = UserOverrideHistory::with('directTiers', 'indirectTiers', 'officeTiers')->where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                        }
                        $overrideStatus = count(collect($overrides)->where('product_id', $productId)->where('status', 1)->values()) != 0 ? 1 : 0;
                        if ($overrideStatus) {
                            if (! isset($groupedData[$productId])) {
                                $groupedData[$productId] = [
                                    'product_id' => $productId,
                                    'status' => count(collect($overrides)->where('product_id', $productId)->where('status', 1)->values()) != 0 ? 1 : 0,
                                    'override_effective_date' => $override?->override_effective_date,
                                ];
                            }

                            if ($item->override_id == 1) {
                                $groupedData[$productId]['direct_overrides_amount'] = $override?->direct_overrides_amount;
                                // Only use custom_field_X format when feature is enabled
                                $groupedData[$productId]['direct_overrides_type'] = ($isCustomFieldsEnabledForPackage && $override?->direct_overrides_type === 'custom field' && $override?->direct_custom_sales_field_id) ? 'custom_field_' . $override->direct_custom_sales_field_id : $override?->direct_overrides_type;
                                $groupedData[$productId]['direct_tiers_status'] = @$override?->direct_tiers_id ? 1 : 0;
                                $groupedData[$productId]['direct_tiers_id'] = $override?->direct_tiers_id;
                                $groupedData[$productId]['direct_tiers_range'] = $override?->directTiers;
                            } elseif ($item->override_id == 2) {
                                $groupedData[$productId]['indirect_overrides_amount'] = $override?->indirect_overrides_amount;
                                // Only use custom_field_X format when feature is enabled
                                $groupedData[$productId]['indirect_overrides_type'] = ($isCustomFieldsEnabledForPackage && $override?->indirect_overrides_type === 'custom field' && $override?->indirect_custom_sales_field_id) ? 'custom_field_' . $override->indirect_custom_sales_field_id : $override?->indirect_overrides_type;
                                $groupedData[$productId]['indirect_tiers_status'] = @$override?->indirect_tiers_id ? 1 : 0;
                                $groupedData[$productId]['indirect_tiers_id'] = $override?->indirect_tiers_id;
                                $groupedData[$productId]['indirect_tiers_range'] = $override?->indirectTiers;
                            } elseif ($item->override_id == 3) {
                                $groupedData[$productId]['office_overrides_amount'] = $override?->office_overrides_amount;
                                // Only use custom_field_X format when feature is enabled
                                $groupedData[$productId]['office_overrides_type'] = ($isCustomFieldsEnabledForPackage && $override?->office_overrides_type === 'custom field' && $override?->office_custom_sales_field_id) ? 'custom_field_' . $override->office_custom_sales_field_id : $override?->office_overrides_type;
                                $groupedData[$productId]['office_tiers_status'] = @$override?->office_tiers_id ? 1 : 0;
                                $groupedData[$productId]['office_tiers_id'] = $override?->office_tiers_id;
                                $groupedData[$productId]['office_tiers_range'] = $override?->officeTiers;
                            } elseif ($item->override_id == 4) {
                                $groupedData[$productId]['office_stack_overrides_amount'] = $override?->office_stack_overrides_amount;
                            }
                        }
                    }

                    foreach ($groupedData as $key => $grouped) {
                        foreach ($additionalLocations as $additionalLocation) {
                            $override = UserAdditionalOfficeOverrideHistory::with('tearsRange')->where(['user_id' => $userId, 'office_id' => $additionalLocation->office_id, 'product_id' => $key])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (! $override) {
                                $override = UserAdditionalOfficeOverrideHistory::with('tearsRange')->where(['user_id' => $userId, 'office_id' => $additionalLocation->office_id, 'product_id' => $key])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                            }
                            $groupedData[$key]['additional_office_override'][] = [
                                'onboarding_location_id' => $additionalLocation?->id,
                                'state_id' => $additionalLocation?->state_id,
                                'state_name' => $additionalLocation?->state?->name,
                                'city_id' => $additionalLocation?->city_id,
                                'city_name' => $additionalLocation?->city?->name,
                                'office_id' => $additionalLocation?->office_id,
                                'office_name' => $additionalLocation?->office?->office_name,
                                'overrides_amount' => $override?->office_overrides_amount,
                                // Only use custom_field_X format when feature is enabled
                                'overrides_type' => ($isCustomFieldsEnabledForPackage && $override?->office_overrides_type === 'custom field' && $override?->office_custom_sales_field_id) ? 'custom_field_' . $override->office_custom_sales_field_id : $override?->office_overrides_type,
                                'office_custom_sales_field_id' => ($isCustomFieldsEnabledForPackage && $override?->office_overrides_type === 'custom field') ? $override?->office_custom_sales_field_id : null,
                                'overrides_tiers_status' => @$override?->tiers_id ? 1 : 0,
                                'tiers_id' => $override?->tiers_id,
                                'tiers_range' => $override?->tearsRange,
                            ];
                        }

                        $overrideConflicts = [];
                        if (request()->input('wizard_type') == 'update_commissions') {
                            if (request()->input('date_selection_type') == 'these_changes_are_temporary') {
                                $userOverrides = UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $key])->whereBetween('override_effective_date', [$effectiveStartDate, $effectiveEndDate])->groupBy('override_effective_date')->orderBy('override_effective_date')->get();
                                if (count($userOverrides) != 0) {
                                    $conflictEndDate = null;
                                    foreach ($userOverrides as $i => $userOverride) {
                                        $endDate = @$userOverrides[$i + 1]->override_effective_date ? $userOverrides[$i + 1]->override_effective_date : null;
                                        $infinite = $endDate ? false : true;
                                        $overrideConflicts[] = [
                                            'title' => 'Overrides',
                                            'value' => null,
                                            'value_type' => null,
                                            'start_time' => $userOverride->override_effective_date,
                                            'end_time' => $endDate,
                                            'infinite' => $infinite,
                                            'conflicted' => 0,
                                        ];
                                    }

                                    $overrideConflicts[] = [
                                        'title' => 'Overrides',
                                        'value' => null,
                                        'value_type' => null,
                                        'start_time' => $effectiveStartDate,
                                        'end_time' => $effectiveEndDate,
                                        'infinite' => false,
                                        'conflicted' => count($userOverrides) != 0 ? 1 : 0,
                                    ];
                                } else {
                                    $overrideConflicts[] = [
                                        'title' => 'Overrides',
                                        'value' => null,
                                        'value_type' => null,
                                        'start_time' => $effectiveStartDate,
                                        'end_time' => $effectiveEndDate,
                                        'infinite' => false,
                                        'conflicted' => 0,
                                    ];
                                }
                            } else {
                                $conflictEndDate = null;
                                $userOverrides = UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $key])->where('override_effective_date', '>=', $effectiveStartDate)->groupBy('override_effective_date')->orderBy('override_effective_date')->get();
                                if (count($userOverrides) != 0) {
                                    foreach ($userOverrides as $i => $userOverride) {
                                        $endDate = @$userOverrides[$i + 1]->override_effective_date ? $userOverrides[$i + 1]->override_effective_date : null;
                                        $infinite = $endDate ? false : true;
                                        $overrideConflicts[] = [
                                            'title' => 'Overrides',
                                            'value' => null,
                                            'value_type' => null,
                                            'start_time' => $userOverride->override_effective_date,
                                            'end_time' => $endDate,
                                            'infinite' => $infinite,
                                            'conflicted' => 0,
                                        ];
                                        $conflictEndDate = $endDate;
                                    }

                                    $infinite = $conflictEndDate ? false : true;
                                    $overrideConflicts[] = [
                                        'title' => 'Overrides',
                                        'value' => null,
                                        'value_type' => null,
                                        'start_time' => $effectiveStartDate,
                                        'end_time' => $conflictEndDate,
                                        'infinite' => $infinite,
                                        'conflicted' => count($userOverrides) != 0 ? 1 : 0,
                                    ];
                                } else {
                                    $overrideConflicts[] = [
                                        'title' => 'Overrides',
                                        'value' => null,
                                        'value_type' => null,
                                        'start_time' => $effectiveStartDate,
                                        'end_time' => null,
                                        'infinite' => true,
                                        'conflicted' => 0,
                                    ];
                                }
                            }
                        }
                        $groupedData[$key]['conflicts'] = $overrideConflicts;
                    }
                    $overrideData = array_merge($overrideData, array_values($groupedData));
                }
            }
        }

        $redLine = [];
        $companyProfile = CompanyProfile::first();
        if ($position && ! in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) && $companyProfile->company_type != CompanyProfile::TURF_COMPANY_TYPE && $companyProfile->company_type != CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $corePositions = [];
            if ($position?->is_selfgen == '1') {
                $corePositions = [2, 3, null];
            } elseif ($position?->is_selfgen == '2' || $position?->is_selfgen == '3') {
                $corePositions = [$position?->is_selfgen];
            } elseif ($position?->is_selfgen == '0') {
                $corePositions = [2];
            }

            foreach ($corePositions as $corePosition) {
                $redLineConflicts = [];
                if (request()->input('wizard_type') == 'update_commissions') {
                    if ($request->date_selection_type == 'these_changes_are_temporary') {
                        $redLineConflicts = [];
                        $userRedLines = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->whereBetween('start_date', [$effectiveStartDate, $effectiveEndDate])->groupBy('start_date')->orderBy('start_date')->get();
                        if (count($userRedLines) != 0) {
                            foreach ($userRedLines as $key => $userRedLine) {
                                $endDate = @$userRedLines[$key + 1]->start_date ? $userRedLines[$key + 1]->start_date : null;
                                $infinite = $endDate ? false : true;
                                $redLineConflicts[] = [
                                    'title' => 'Redline',
                                    'value' => $userRedLine->redline,
                                    'value_type' => $userRedLine->redline_amount_type,
                                    'start_time' => $userRedLine->start_date,
                                    'end_time' => $endDate,
                                    'infinite' => $infinite,
                                    'conflicted' => 0,
                                ];
                            }

                            $redLineConflicts[] = [
                                'title' => 'Redline',
                                'value' => null,
                                'value_type' => null,
                                'start_time' => $effectiveStartDate,
                                'end_time' => $effectiveEndDate,
                                'infinite' => false,
                                'conflicted' => count($userRedLines) != 0 ? 1 : 0,
                            ];
                        } else {
                            $redLineConflicts[] = [
                                'title' => 'Redline',
                                'value' => null,
                                'value_type' => null,
                                'start_time' => $effectiveStartDate,
                                'end_time' => $effectiveEndDate,
                                'infinite' => false,
                                'conflicted' => 0,
                            ];
                        }
                    } else {
                        $redLineConflicts = [];
                        $conflictEndDate = null;
                        $userRedLines = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '>=', $effectiveStartDate)->groupBy('start_date')->orderBy('start_date')->get();
                        if (count($userRedLines) != 0) {
                            foreach ($userRedLines as $key => $userRedLine) {
                                $endDate = @$userRedLines[$key + 1]->start_date ? $userRedLines[$key + 1]->start_date : null;
                                $infinite = $endDate ? false : true;
                                $redLineConflicts[] = [
                                    'title' => 'Redline',
                                    'value' => $userRedLine->redline,
                                    'value_type' => $userRedLine->redline_amount_type,
                                    'start_time' => $userRedLine->start_date,
                                    'end_time' => $endDate,
                                    'infinite' => $infinite,
                                    'conflicted' => 0,
                                ];
                                $conflictEndDate = $endDate;
                            }

                            $infinite = $conflictEndDate ? false : true;
                            $redLineConflicts[] = [
                                'title' => 'Redline',
                                'value' => null,
                                'value_type' => null,
                                'start_time' => $effectiveStartDate,
                                'end_time' => $conflictEndDate,
                                'infinite' => $infinite,
                                'conflicted' => count($userRedLines) != 0 ? 1 : 0,
                            ];
                        } else {
                            $redLineConflicts[] = [
                                'title' => 'Redline',
                                'value' => null,
                                'value_type' => null,
                                'start_time' => $effectiveStartDate,
                                'end_time' => null,
                                'infinite' => true,
                                'conflicted' => 0,
                            ];
                        }
                    }
                }

                $userRedLine = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '<=', $effectiveDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $userRedLine) {
                    $userRedLine = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '>=', $effectiveDate)->orderBy('start_date', 'ASC')->orderBy('id', 'DESC')->first();
                }
                if ($request->input('wizard_type') == 'update_organization') {
                    $redLine = [];
                } else {
                    $redLine[] = [
                        'core_position_id' => $corePosition,
                        'self_gen_user' => $corePosition ? 0 : 1,
                        'redline' => $userRedLine?->redline,
                        'redline_type' => $userRedLine?->redline_type,
                        'redline_amount_type' => $userRedLine?->redline_amount_type,
                        'redline_effective_date' => $userRedLine?->start_date,
                        'conflicts' => $redLineConflicts,
                    ];
                }

            }
        }

        $deductionData = [];
        $positionDeductionLimit = $position?->deductionLimit;
        if (($request->wizard_type == 'change_position' || $request->wizard_type == 'update_organization') && isset($position->deductionName) && count($position->deductionName) != 0) {
            $deductionData = $position->deductionName->map(function ($deductionName) use ($userId, $effectiveDate) {
                $userDeduction = UserDeductionHistory::where(['user_id' => $userId, 'cost_center_id' => $deductionName->cost_center_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
                if (! $userDeduction) {
                    $userDeduction = UserDeductionHistory::where(['user_id' => $userId, 'cost_center_id' => $deductionName->cost_center_id])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->first();
                }

                return [
                    'id' => $deductionName->id,
                    'cost_center_id' => $deductionName->cost_center_id,
                    'deduction_type' => $deductionName->deduction_type,
                    'ammount_par_paycheck' => $userDeduction?->amount_par_paycheque,
                    'effective_date' => $userDeduction?->effective_date,
                ];
            });
        }

        $wages = null;
        $positionWage = PositionWage::where(['position_id' => $userOrganization?->sub_position_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if ($positionWage) {
            $positionWage = PositionWage::where(['position_id' => $userOrganization?->sub_position_id, 'effective_date' => $positionWage->effective_date, 'wages_status' => 1])->first();
        } else {
            $positionWage = PositionWage::where(['position_id' => $userOrganization?->sub_position_id, 'wages_status' => 1])->whereNull('effective_date')->first();
        }
        if (isset($positionWage)) {
            $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userWagesHistory) {
                $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            if (($request->wizard_type == 'change_position' || $request->wizard_type == 'update_organization') && $userWagesHistory) {
                $wages = [
                    'pay_type' => $userWagesHistory->pay_type ?? null,
                    'pay_rate' => $userWagesHistory->pay_rate ?? null,
                    'frequency_name' => $position?->payFrequency?->frequencyType?->name,
                    'pay_rate_type' => $userWagesHistory->pay_rate_type ?? null,
                    'pto_hours' => $userWagesHistory->pto_hours ?? null,
                    'unused_pto_expires' => $userWagesHistory->unused_pto_expires ?? null,
                    'expected_weekly_hours' => $userWagesHistory->expected_weekly_hours ?? null,
                    'overtime_rate' => $userWagesHistory->overtime_rate ?? null,
                    'effective_date' => $userWagesHistory->effective_date ?? null,
                ];
            }
        }

        $userAgreement = UserAgreementHistory::where(['user_id' => $userId])->where('period_of_agreement', '<=', $effectiveDate)->orderBy('period_of_agreement', 'DESC')->orderBy('created_at', 'DESC')->first();
        if (! $userAgreement) {
            $userAgreement = UserAgreementHistory::where(['user_id' => $userId])->where('period_of_agreement', '>=', $effectiveDate)->orderBy('period_of_agreement', 'ASC')->orderBy('created_at', 'DESC')->first();
        }
        $agreement = [
            'hired_date' => date('Y-m-d', strtotime($user?->created_at)),
            'probation_period' => ($userAgreement && $userAgreement?->probation_period != 'None') ? $userAgreement?->probation_period : null,
            'period_of_agreement' => ($userAgreement && $userAgreement?->period_of_agreement) ? $userAgreement?->period_of_agreement : null,
            'end_date' => ($userAgreement && $userAgreement?->end_date) ? $userAgreement?->end_date : null,
        ];

        $products = [];
        if (! empty($position)) {
            foreach ($position->product as $product) {
                $products[] = [
                    'id' => $product->id,
                    'name' => $product?->productName?->name,
                    'product_id' => $product?->productName?->product_id,
                ];
            }
        }

        $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $isManager) {
            $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }
        $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $manager) {
            $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
        }

        $additionalRecruiter = $user->additionalDetail->map(function ($recruiter) {
            return [
                'recruiter_id' => $recruiter?->additionalRecruiterDetail?->id,
                'recruiter_first_name' => $recruiter?->additionalRecruiterDetail?->first_name,
                'recruiter_last_name' => $recruiter?->additionalRecruiterDetail?->last_name,
            ];
        })->toArray();

        $lastEffectiveDate = null;
        if ($request->wizard_type == 'change_position') {
            $lastEffectiveDate = $userOrganization?->effective_date;
            $changePosition = UserOrganizationHistory::with('position')->where(['user_id' => $userId])->where('sub_position_id', '!=', $userOrganization?->sub_position_id)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $changePosition) {
                $changePosition = UserOrganizationHistory::with('position')->where(['user_id' => $userId])->where('sub_position_id', '!=', $userOrganization?->sub_position_id)->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            if ($changePosition) {
                $lastEffectiveDate = $changePosition?->effective_date;
            }
        } elseif ($request->wizard_type == 'transfer_user') {
            $userTransfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '<=', $effectiveDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userTransfer) {
                $userTransfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '>=', $effectiveDate)->orderBy('transfer_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }
            $lastEffectiveDate = $userTransfer?->transfer_effective_date;
            if ($userTransfer) {
                $secondLastTransfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '<=', $lastEffectiveDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $secondLastTransfer) {
                    $secondLastTransfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '>=', $lastEffectiveDate)->orderBy('transfer_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }
                if ($secondLastTransfer) {
                    $lastEffectiveDate = $secondLastTransfer?->transfer_effective_date;
                }
            }
        }

        $organization = [
            'is_manager' => $isManager?->is_manager ?? 0,
            'is_manager_effective_date' => $isManager?->effective_date ?? null,
            'manager_id' => $manager?->user?->id,
            'manager_name' => $manager?->user ? $manager?->user?->first_name.' '.$manager?->user?->last_name : null,
            'manager_effective_date' => $manager?->effective_date,
            'team_id' => $manager?->team?->id,
            'team_name' => $manager?->team?->team_name,
            'recruiter_id' => $user?->recruiter?->id,
            'recruiter_name' => $user?->recruiter ? $user?->recruiter?->first_name.' '.$user?->recruiter?->last_name : null,
            'additional_recruter' => $additionalRecruiter,
            'additional_locations_effective_date' => $additionalOfficeEffectiveDate,
            'additional_locations' => $additionalOffice,
            'position_id' => $userOrganization?->position?->id,
            'position_name' => $userOrganization?->position?->position_name,
            'sub_position_id' => $position?->id,
            'sub_position_name' => $position?->position_name,
            'department_id' => $position?->positionDepartmentDetail?->id,
            'department_name' => $position?->positionDepartmentDetail?->name,
            'total_employee' => $isManager?->is_manager ? User::where('manager_id', $userId)->count() ?? 0 : 0,
            'last_effective_date' => $lastEffectiveDate,
        ];

        $response = [
            'id' => $user->id,
            'main_role' => $position?->is_selfgen,
            'details' => [
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'work_email' => $user?->OnboardingAdditionalEmails,
                'mobile_no' => $user->mobile_no,
                'state_id' => $user?->state?->id,
                'state_name' => $user?->state?->name,
                'city_id' => $user?->city_id,
                'city_name' => $user?->city?->name,
                'office_id' => $user?->office?->id,
                'office_name' => $user?->office?->office_name,
            ],
            'worker_type' => $position?->worker_type,
            'deduction_status' => $positionDeductionLimit?->status ?? 0,
            'products' => $products,
            'organization' => $organization,
            'wages' => $wages,
            'employee_commision' => $commissionData,
            'employee_redline' => $redLine,
            'employee_upfronts' => $upfrontData,
            'settlement' => $settlementData,
            'employee_overrides' => $overrideData,
            'deductions' => $deductionData,
            'employee_agreement' => $agreement,
        ];

        $this->successResponse('Successfully.', 'userOrganizationDetails', $response);
    }

    public function salesCount(Request $request)
    {
        $this->checkValidations($request->all(), [
            'id' => 'required',
            'date_selection_type' => 'required|in:apply_to_all_sales,immediately,select_start_date,these_changes_are_temporary',
            'effective_start_date' => 'required_if:date_selection_type,select_start_date|required_if:date_selection_type,these_changes_are_temporary',
            'effective_end_date' => 'required_if:date_selection_type,these_changes_are_temporary',
        ]);

        $m2Paid = UserCommission::where(['is_last' => '1', 'status' => 3, 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->pluck('pid');
        $reconM2Paid = UserCommission::where(['is_last' => '1', 'recon_status' => 3, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->pluck('pid');
        $paidSale = array_merge($m2Paid->toArray(), $reconM2Paid->toArray());

        $userId = $request->id;
        $effectiveStartDate = $request->effective_start_date;
        $effectiveEndDate = $request->effective_end_date ?? null;
        $count = SalesMaster::whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
            $q->where(function ($q) use ($userId) {
                $q->where('closer1_id', $userId)->orWhere('setter1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter2_id', $userId);
            });
        })->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
            $q->whereBetween('customer_signoff', [$effectiveStartDate, $effectiveEndDate]);
        })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
            $q->where('customer_signoff', '>=', $effectiveStartDate);
        })->whereNotIn('pid', $paidSale)->whereNull('date_cancelled')->count();

        $this->successResponse('Successfully.', 'salesCount', ['sales_count' => $count]);
    }

    public function lastEffectiveDate(Request $request)
    {
        $this->checkValidations($request->all(), [
            'id' => 'required',
            'wizard_type' => 'required|in:change_position,transfer_user,update_commissions,update_organization',
        ]);

        $userId = $request->id;
        $effectiveDate = date('Y-m-d');
        $lastEffectiveDate = null;
        if ($request->wizard_type == 'change_position') {
            $userOrganization = UserOrganizationHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if (! $userOrganization) {
                $userOrganization = UserOrganizationHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->first();
            }
            if ($userOrganization) {
                $lastEffectiveDate = $userOrganization->effective_date;
            }
        } elseif ($request->wizard_type == 'transfer_user') {
            $lastTransfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '<=', $effectiveDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if (! $lastTransfer) {
                $lastTransfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '>=', $effectiveDate)->orderBy('transfer_effective_date', 'ASC')->first();
            }
            if ($lastTransfer) {
                $lastEffectiveDate = $lastTransfer->transfer_effective_date;
            }
        } elseif ($request->wizard_type == 'update_commissions') {
            $redLine = UserRedlines::where(['user_id' => $userId])->where('start_date', '<=', $effectiveDate)->orderBy('start_date', 'DESC')->first();
            if (! $redLine) {
                $redLine = UserRedlines::where(['user_id' => $userId])->where('start_date', '>=', $effectiveDate)->orderBy('start_date', 'ASC')->first();
            }
            $commission = UserCommissionHistory::where(['user_id' => $userId])->where('commission_effective_date', '<=', $effectiveDate)->orderBy('commission_effective_date', 'DESC')->first();
            if (! $commission) {
                $commission = UserCommissionHistory::where(['user_id' => $userId])->where('commission_effective_date', '>=', $effectiveDate)->orderBy('commission_effective_date', 'ASC')->first();
            }
            $upFront = UserUpfrontHistory::where(['user_id' => $userId])->where('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->first();
            if (! $upFront) {
                $upFront = UserUpfrontHistory::where(['user_id' => $userId])->where('upfront_effective_date', '>=', $effectiveDate)->orderBy('upfront_effective_date', 'ASC')->first();
            }
            $override = UserOverrideHistory::where(['user_id' => $userId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->first();
            if (! $override) {
                $override = UserOverrideHistory::where(['user_id' => $userId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->first();
            }
            $withHold = UserWithheldHistory::where(['user_id' => $userId])->where('withheld_effective_date', '<=', $effectiveDate)->orderBy('withheld_effective_date', 'DESC')->first();
            if (! $withHold) {
                $withHold = UserWithheldHistory::where(['user_id' => $userId])->where('withheld_effective_date', '>=', $effectiveDate)->orderBy('withheld_effective_date', 'ASC')->first();
            }

            $closestDate = null;
            $minDiff = PHP_INT_MAX;
            $effectiveDate = Carbon::parse($effectiveDate);
            $dateArray = [$redLine?->start_date, $commission?->commission_effective_date, $upFront?->upfront_effective_date, $override?->override_effective_date, $withHold?->withheld_effective_date];
            foreach ($dateArray as $date) {
                if ($date) {
                    $currentDate = Carbon::parse($date);
                    $diff = $effectiveDate->diffInSeconds($currentDate);

                    if ($diff < $minDiff) {
                        $minDiff = $diff;
                        $closestDate = $date;
                    }
                }
            }
            $lastEffectiveDate = $closestDate;
        } elseif ($request->wizard_type == 'update_organization') {
            $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if (! $isManager) {
                $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->first();
            }
            if ($isManager) {
                $lastEffectiveDate = $isManager->effective_date;
            }
        }

        $effectiveDate = date('Y-m-d');
        $userAgreement = UserAgreementHistory::where(['user_id' => $userId])->where('period_of_agreement', '<=', $effectiveDate)->orderBy('period_of_agreement', 'DESC')->orderBy('created_at', 'DESC')->first();
        if (! $userAgreement) {
            $userAgreement = UserAgreementHistory::where(['user_id' => $userId])->where('period_of_agreement', '>=', $effectiveDate)->orderBy('period_of_agreement', 'ASC')->orderBy('created_at', 'DESC')->first();
        }
        $this->successResponse('Successfully.', 'salesCount', ['last_effective_date' => $lastEffectiveDate, 'period_of_agreement' => ($userAgreement && $userAgreement?->period_of_agreement) ? $userAgreement?->period_of_agreement : null]);
    }

    public function updateEmploymentPackage(Request $request)
    {
        // Validation happens outside of transaction
        $this->checkValidations($request->all(), [
            'id' => 'required',
            'wizard_type' => 'required|in:change_position,transfer_user,update_commissions,update_organization',
            'date_selection_type' => 'required|in:apply_to_all_sales,immediately,select_start_date,these_changes_are_temporary',
            'effective_start_date' => 'required_if:date_selection_type,select_start_date|required_if:date_selection_type,these_changes_are_temporary',
            'effective_end_date' => 'required_if:date_selection_type,these_changes_are_temporary',
            'employee_commision.*.product_id' => 'required',
            'employee_commision.*.commission_status' => 'required|in:0,1',
            'employee_commision.*.commission_data.*.self_gen_user' => 'required|in:0,1',
            'employee_commision.*.commission_data.*.commission' => 'required',
            'employee_commision.*.commission_data.*.commission_type' => 'required',
            'employee_commision.*.commission_data.*.conflict_date_setting' => 'required|in:keep_effective_dates,overwrite_conflicting_dates',
            'employee_redline.*.self_gen_user' => 'required|in:0,1',
            'employee_redline.*.redline' => 'required',
            // 'employee_redline.*.redline_type' => 'required',
            'employee_redline.*.conflict_date_setting' => 'required|in:keep_effective_dates,overwrite_conflicting_dates',
            'employee_upfronts.*.product_id' => 'required',
            'employee_upfronts.*.upfront_status' => 'required|in:0,1',
            'employee_upfronts.*.data.*.milestone_id' => 'required',
            'employee_upfronts.*.data.*.self_gen_user' => 'required|in:0,1',
            'employee_upfronts.*.data.*.conflict_date_setting' => 'required|in:keep_effective_dates,overwrite_conflicting_dates',
            'employee_upfronts.*.data.*.schemas.*.milestone_schema_trigger_id' => 'required',
            'employee_upfronts.*.data.*.schemas.*.upfront_pay_amount' => 'required',
            'employee_upfronts.*.data.*.schemas.*.upfront_sale_type' => 'required',
            'settlement.*.product_id' => 'required',
            'settlement.*.withheld_amount' => 'required',
            'settlement.*.withheld_type' => 'required',
            'overrides.*.product_id' => 'required',
            // 'deductions.*.cost_center_id' => 'required',
            // 'deductions.*.deduction_type' => 'required',
            // 'deductions.*.ammount_par_paycheck' => 'required'
        ]);

        $response = [
            'status' => true,
            'message' => 'Employment package updated Successfully!!',
            'ApiName' => 'updateEmploymentPackage',
            'code' => 200,
        ];

        // Check payroll status outside of transaction
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        // Additional validation checks
        $terminated = checkTerminateFlag($request->id, $request->effective_start_date);
        if ($terminated && $terminated->is_terminate) {
            return response()->json(['status' => false, 'Message' => 'User has been terminated. So can not change the Employment Package.'], 400);
        }
        $dismissed = checkDismissFlag($request->id, $request->effective_start_date);
        if ($dismissed && $dismissed->dismiss) {
            return response()->json(['status' => false, 'Message' => 'User has been Disabled. So can not change the Employment Package.'], 400);
        }

        if ($request->wizard_type != 'update_organization') {
            $contractEnded = checkContractEndFlag($request->id, $request->effective_start_date);
            if ($contractEnded) {
                return response()->json(['status' => false, 'Message' => 'User Contract has been Ended. So can not change the Employment Package.'], 400);
            }
        }

        // Realtime notification (broadcast + Redis) for all 4 wizards - best effort only.
        // Emit only after early-return validations pass.
        $notificationInitiatedAt = now()->toIso8601String();
        $notificationUniqueKey = 'employment_package_update_' . (string) $request->wizard_type . '_' . (int) $request->id . '_' . time();
        $initiatorUserId = auth()->check() ? (int) auth()->id() : null;
        try {
            app(JobNotificationService::class)->notify(
                $initiatorUserId,
                'employment_package_update',
                'Employment package update',
                'started',
                0,
                'Employment package update started.',
                $notificationUniqueKey,
                $notificationInitiatedAt,
                null,
                [
                    'wizard_type' => (string) $request->wizard_type,
                    'user_id' => (int) $request->id,
                    'date_selection_type' => (string) $request->date_selection_type,
                    'effective_start_date' => (string) $request->effective_start_date,
                    'effective_end_date' => $request->effective_end_date ?? null,
                ]
            );
        } catch (\Throwable) {
            // best-effort only
        }

        // Prepare organization data outside of transaction
        $positionId = $request->organization['position_id'] ?? null;
        $subPositionId = $request->organization['sub_position_id'] ?? null;
        $existingEmployeeNewManagerId = @$request->organization['existing_employee_new_manager_id'];
        $userIds = [$request->id];
        $userId = $request->id;
        $effectiveStartDate = $request->effective_start_date;
        $effectiveEndDate = $request->effective_end_date ?? null;
        try {
            // Begin transaction only when ready to do database operations
            DB::beginTransaction();
            if ($request->wizard_type == 'change_position' || $request->wizard_type == 'transfer_user' || $request->wizard_type == 'update_organization' || $request->wizard_type == 'update_commissions') {

                // VALIDATE FIRST - Before any deletions
                if (! $subPositionId) {
                    throw new \Exception("sub_position_id is required for wizard type: {$request->wizard_type}");
                }

                $position = Positions::withoutGlobalScope('notSuperAdmin')->with('product')->where('id', $subPositionId)->first();

                if (! $position) {
                    throw new \Exception("Position with ID {$subPositionId} not found");
                }

                if (! $position->product || $position->product->isEmpty()) {
                    throw new \Exception("Position {$subPositionId} has no associated products");
                }

                // Now safe to proceed with data operations
                $selfGen = ($position->is_selfgen == '1') ? 1 : 0;
                $orgId = [];

                if ($request->date_selection_type == 'these_changes_are_temporary') {
                    if ($userPrevPosition = UserOrganizationHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveEndDate)->orderBy('effective_date', 'DESC')->first()) {
                        $prevPositionData = UserOrganizationHistory::where(['user_id' => $userId, 'effective_date' => $userPrevPosition->effective_date])->get();
                        foreach ($prevPositionData as $prevPositionData) {
                            $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                            if (! UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $prevPositionData->product_id, 'sub_position_id' => $prevPositionData->sub_position_id, 'self_gen_accounts' => $prevPositionData->self_gen_accounts, 'effective_date' => $effectiveDate])->first()) {
                                $prevPositionData['effective_date'] = $effectiveDate;
                                $org = UserOrganizationHistory::create($prevPositionData->toArray());
                                $orgId[] = $org->id;
                            }
                        }
                    }
                }

                UserOrganizationHistory::where(['user_id' => $userId])
                    ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                        $q->whereBetween('effective_date', [$effectiveStartDate, $effectiveEndDate]);
                    })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                        $q->where('effective_date', '>=', $effectiveStartDate);
                    })->whereNotIn('id', $orgId)->delete();

                foreach ($position->product as $product) {
                    UserOrganizationHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $product->product_id, 'sub_position_id' => $subPositionId, 'self_gen_accounts' => $selfGen, 'effective_date' => $effectiveStartDate], [
                        'updater_id' => auth()->user()->id,
                        'position_id' => $positionId,
                        'effective_end_date' => $effectiveEndDate ?? null,
                        'existing_employee_new_manager_id' => $existingEmployeeNewManagerId,
                    ]);
                }
            }
            if ($request->wizard_type == 'transfer_user') {
                $transferId = [];
                if ($request->date_selection_type == 'these_changes_are_temporary') {
                    if ($userPrevTransfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '<=', $effectiveEndDate)->orderBy('transfer_effective_date', 'DESC')->first()) {
                        $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                        if (! UserTransferHistory::where(['user_id' => $userId, 'transfer_effective_date' => $effectiveDate])->first()) {
                            $userPrevTransfer['transfer_effective_date'] = $effectiveDate;
                            $transfer = UserTransferHistory::create($userPrevTransfer->toArray());
                            $transferId[] = $transfer->id;
                        }
                    }
                }

                UserTransferHistory::where(['user_id' => $userId])->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                    $q->whereBetween('transfer_effective_date', [$effectiveStartDate, $effectiveEndDate]);
                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                    $q->where('transfer_effective_date', '>=', $effectiveStartDate);
                })->whereNotIn('id', $transferId)->delete();

                UserTransferHistory::updateOrCreate(['user_id' => $userId, 'transfer_effective_date' => $effectiveStartDate], [
                    'updater_id' => auth()->user()->id,
                    'state_id' => isset($request->details['state_id']) ? $request->details['state_id'] : '',
                    'office_id' => isset($request->details['office_id']) ? $request->details['office_id'] : '',
                    'department_id' => isset($request->organization['department_id']) ? $request->organization['department_id'] : '',
                    'position_id' => $positionId ? $positionId : '',
                    'sub_position_id' => $subPositionId ? $subPositionId : '',
                    'effective_end_date' => $effectiveEndDate ? $effectiveEndDate : null,
                    'existing_employee_new_manager_id' => $existingEmployeeNewManagerId ? $existingEmployeeNewManagerId : '',
                ]);
            } elseif ($request->wizard_type == 'update_organization') {
                $user = User::where('id', $userId)->first();
                $user->recruiter_id = $request->organization['recruiter_id'];
                if (@$request->organization['additional_recruter']) {
                    AdditionalRecruiters::where('user_id', $userId)->delete();
                    $additionalRecruiter1Id = null;
                    $additionalRecruiter2Id = null;
                    foreach ($request->organization['additional_recruter'] as $key => $additionalRecruiter) {
                        if (isset($additionalRecruiter['id'])) {
                            if ($key == 0) {
                                $additionalRecruiter1Id = $additionalRecruiter['id'];
                            } elseif ($key == 1) {
                                $additionalRecruiter2Id = $additionalRecruiter['id'];
                            }

                            if ($additionalRecruiter['id']) {
                                AdditionalRecruiters::create([
                                    'user_id' => $userId,
                                    'recruiter_id' => $additionalRecruiter['id'],
                                ]);
                            }
                        }
                    }
                    $user->additional_recruiter_id1 = $additionalRecruiter1Id;
                    $user->additional_recruiter_id2 = $additionalRecruiter2Id;
                }
                $user->save();

                if ($request->organization['additional_locations']) {
                    $recordId = [];
                    foreach ($request->organization['additional_locations'] as $additionalLocation) {
                        $record = AdditionalLocations::updateOrCreate([
                            'user_id' => $userId,
                            'state_id' => isset($additionalLocation['state_id']) ? $additionalLocation['state_id'] : '',
                            'office_id' => isset($additionalLocation['office_id']) ? $additionalLocation['office_id'] : '',
                            'effective_date' => $effectiveStartDate ? $effectiveStartDate : '',
                        ], [
                            'overrides_amount' => 0,
                            'overrides_type' => 'per kw',
                            'updater_id' => auth()->user()->id,
                        ]);
                        $recordId[] = $record->id;
                    }

                    AdditionalLocations::where('user_id', $userId)->whereNotIn('id', $recordId)->delete();
                } else {
                    AdditionalLocations::where('user_id', $userId)->delete();
                }
            }

            $userIds = [$userId];
            if ($request->wizard_type == 'change_position' || $request->wizard_type == 'transfer_user' || $request->wizard_type == 'update_organization') {
                $isManagerId = [];
                if ($request->date_selection_type == 'these_changes_are_temporary') {
                    if ($userPrevIsManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveEndDate)->orderBy('effective_date', 'DESC')->first()) {
                        $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($userPrevIsManager['effective_date'])));
                        if (! UserIsManagerHistory::where(['user_id' => $userId, 'effective_date' => $effectiveDate])->first()) {
                            $userPrevIsManager['effective_date'] = $effectiveDate;
                            $isManager = UserIsManagerHistory::create($userPrevIsManager->toArray());
                            $isManagerId[] = $isManager->id;
                        }
                    }
                }

                UserIsManagerHistory::where(['user_id' => $userId])->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                    $q->whereBetween('effective_date', [$effectiveStartDate, $effectiveEndDate]);
                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                    $q->where('effective_date', '>=', $effectiveStartDate);
                })->whereNotIn('id', $isManagerId)->delete();

                $managerId = [];
                if ($request->date_selection_type == 'these_changes_are_temporary') {
                    if ($userPrevManager = UserManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveEndDate)->orderBy('effective_date', 'DESC')->first()) {
                        $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($userPrevManager['effective_date'])));
                        if (! UserManagerHistory::where(['user_id' => $userId, 'effective_date' => $effectiveDate])->first()) {
                            $userPrevManager['effective_date'] = $effectiveDate;
                            $manager = UserManagerHistory::create($userPrevManager->toArray());
                            $managerId[] = $manager->id;
                        }
                    }
                }

                UserManagerHistory::where(['user_id' => $userId])->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                    $q->whereBetween('effective_date', [$effectiveStartDate, $effectiveEndDate]);
                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                    $q->where('effective_date', '>=', $effectiveStartDate);
                })->whereNotIn('id', $managerId)->delete();

                UserIsManagerHistory::updateOrCreate(['user_id' => $userId, 'effective_date' => $effectiveStartDate], [
                    'updater_id' => Auth()->user()->id,
                    'is_manager' => $request->organization['is_manager'],
                    'position_id' => $positionId,
                    'sub_position_id' => $subPositionId,
                ]);

                // Determine manager ID - use user's own ID if they are a manager, otherwise use provided manager_id or fallback to user ID
                $requestManagerId = isset($request->organization['is_manager']) && $request->organization['is_manager'] == 1 ? $userId : ($request->organization['manager_id'] ?? $userId);

                UserManagerHistory::updateOrCreate(['user_id' => $userId, 'effective_date' => $effectiveStartDate], [
                    'updater_id' => Auth()->user()->id,
                    'manager_id' => $requestManagerId,
                    'team_id' => $request->organization['team_id'],
                    'position_id' => $positionId,
                    'sub_position_id' => $subPositionId,
                ]);

                // UPDATE MANAGERS
                if (Carbon::parse($effectiveStartDate)->lessThan(Carbon::today())) {
                    if ($existingEmployeeNewManagerId) {
                        $userEmployeeIds = User::where('manager_id', $userId)->get();
                        foreach ($userEmployeeIds as $userEmployeeId) {
                            $organizationHistory = UserOrganizationHistory::where('user_id', $userEmployeeId->id)->where('effective_date', '<=', $effectiveStartDate)->orderBy('effective_date', 'DESC')->first();
                            $lastManager = UserManagerHistory::where(['user_id' => $userEmployeeId->id])->orderBy('effective_date', 'DESC')->first();
                            $date = $effectiveStartDate;
                            $system = 0;
                            if ($lastManager && Carbon::parse($effectiveStartDate)->lessThan(Carbon::parse($lastManager->effective_date))) {
                                $date = Carbon::parse($lastManager->effective_date)->addDay()->format('Y-m-d');
                                $system = 1;
                            }

                            UserManagerHistory::updateOrCreate(['user_id' => $userEmployeeId->id, 'effective_date' => $date], [
                                'updater_id' => Auth()->user()->id,
                                'manager_id' => $existingEmployeeNewManagerId,
                                'position_id' => @$organizationHistory->position_id ? $organizationHistory->position_id : $userEmployeeId->position_id,
                                'sub_position_id' => @$organizationHistory->sub_position_id ? $organizationHistory->sub_position_id : $userEmployeeId->sub_position_id,
                                'system_generated' => $system,
                            ]);
                            $userIds[] = $userEmployeeId->id;
                        }

                        $leadData = Lead::where('recruiter_id', $userId)->pluck('id')->toArray();
                        if (count($leadData) != 0) {
                            Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $existingEmployeeNewManagerId]);
                        }
                    }
                }
            }

            if ($request->filled('wages')) {
                $wagesId = [];
                if ($request->date_selection_type == 'these_changes_are_temporary') {
                    if ($userPrevWages = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveEndDate)->orderBy('effective_date', 'DESC')->first()) {
                        $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                        if (! UserWagesHistory::where(['user_id' => $userId, 'effective_date' => $effectiveDate])->first()) {
                            $userPrevWages['effective_date'] = $effectiveDate;
                            $wages = UserWagesHistory::create($userPrevWages->toArray());
                            $wagesId[] = $wages->id;
                        }
                    }
                }

                UserWagesHistory::where(['user_id' => $userId])
                    ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                        $q->whereBetween('effective_date', [$effectiveStartDate, $effectiveEndDate]);
                    })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                        $q->where('effective_date', '>=', $effectiveStartDate);
                    })->whereNotIn('id', $wagesId)->delete();

                $wages = $request->wages;
                UserWagesHistory::updateOrCreate(['user_id' => $userId, 'effective_date' => $effectiveStartDate], [
                    'updater_id' => auth()->user()->id,
                    'pay_type' => $wages['pay_type'],
                    'pay_rate' => $wages['pay_rate'],
                    'pay_rate_type' => $wages['pay_rate_type'],
                    'expected_weekly_hours' => $wages['expected_weekly_hours'],
                    'overtime_rate' => $wages['overtime_rate'],
                    'pto_hours' => $wages['pto_hours'],
                    'unused_pto_expires' => $wages['unused_pto_expires'],
                    'pto_hours_effective_date' => $effectiveStartDate,
                    'effective_end_date' => $effectiveEndDate ?? null,
                ]);
            }

            if ($request->filled('employee_redline') && count($request->employee_redline) != 0) {
                $redLines = $request->employee_redline;
                foreach ($redLines as $redLine) {
                    $redLineId = [];
                    if ($request->date_selection_type == 'these_changes_are_temporary') {
                        if ($userPrevRedLine = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $redLine['core_position_id'], 'self_gen_user' => $redLine['self_gen_user']])->where('start_date', '<=', $effectiveEndDate)->orderBy('start_date', 'DESC')->first()) {
                            $prevRedLineData = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $redLine['core_position_id'], 'self_gen_user' => $redLine['self_gen_user'], 'start_date' => $userPrevRedLine->start_date])->get();
                            $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                            foreach ($prevRedLineData as $prevRedLine) {
                                if (! UserRedlines::where(['user_id' => $userId, 'core_position_id' => $prevRedLine->core_position_id, 'self_gen_user' => $prevRedLine->self_gen_user, 'start_date' => $effectiveDate])->first()) {
                                    $prevRedLine['start_date'] = $effectiveDate;
                                    $createRedLine = UserRedlines::create($prevRedLine->toArray());
                                    $redLineId[] = $createRedLine->id;
                                }
                            }
                        }
                    }
                    if ($request->input('wizard_type') !== 'update_organization') {
                        if ($redLine['conflict_date_setting'] == 'overwrite_conflicting_dates') {
                            UserRedlines::where(['user_id' => $userId, 'core_position_id' => $redLine['core_position_id'], 'self_gen_user' => $redLine['self_gen_user']])
                                ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                                    $q->whereBetween('start_date', [$effectiveStartDate, $effectiveEndDate]);
                                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                                    $q->where('start_date', '>=', $effectiveStartDate);
                                })->whereNotIn('id', $redLineId)->delete();
                        }
                    }

                    UserRedlines::updateOrCreate(['user_id' => $userId, 'core_position_id' => $redLine['core_position_id'], 'self_gen_user' => $redLine['self_gen_user'], 'start_date' => $effectiveStartDate], [
                        'position_type' => $positionId,
                        'sub_position_type' => $subPositionId,
                        'updater_id' => auth()->user()->id,
                        'redline' => $redLine['redline'],
                        'redline_type' => @$redLine['redline_type'] ? $redLine['redline_type'] : 'per watt',
                        'redline_amount_type' => $redLine['redline_amount_type'],
                        'effective_end_date' => $effectiveEndDate ?? null,
                    ]);
                }
            }

            $companySettingTiers = CompanySetting::where('type', 'tier')->first();
            
            // Check if Custom Sales Fields feature is enabled ONCE before loops
            $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled();
            
            if ($request->filled('employee_commision') && count($request->employee_commision) != 0) {
                $commissions = $request->employee_commision;
                foreach ($commissions as $commission) {
                    foreach ($commission['commission_data'] as $data) {
                        $commissionDataId = [];
                        if ($request->date_selection_type == 'these_changes_are_temporary') {
                            if ($userPrevCommission = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $commission['product_id'], 'core_position_id' => $data['core_position_id'], 'self_gen_user' => $data['self_gen_user']])->where('commission_effective_date', '<=', $effectiveEndDate)->orderBy('commission_effective_date', 'DESC')->first()) {
                                $prevCommissionData = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $commission['product_id'], 'core_position_id' => $data['core_position_id'], 'self_gen_user' => $data['self_gen_user'], 'commission_effective_date' => $userPrevCommission->commission_effective_date])->get();
                                $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                                foreach ($prevCommissionData as $prevCommission) {
                                    if (! UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $prevCommission['product_id'], 'core_position_id' => $prevCommission['core_position_id'], 'sub_position_id' => $prevCommission['sub_position_id'], 'self_gen_user' => $prevCommission['self_gen_user'], 'commission_effective_date' => $effectiveStartDate])->first()) {
                                        $prevCommission['commission_effective_date'] = $effectiveDate;
                                        $createCommission = UserCommissionHistory::create($prevCommission->toArray());
                                        $commissionDataId[] = $createCommission->id;
                                    }
                                }
                            }
                        }

                        if ($data['conflict_date_setting'] == 'overwrite_conflicting_dates') {
                            UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $commission['product_id'], 'core_position_id' => $data['core_position_id'], 'self_gen_user' => $data['self_gen_user']])
                                ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                                    $q->whereBetween('commission_effective_date', [$effectiveStartDate, $effectiveEndDate]);
                                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                                    $q->where('commission_effective_date', '>=', $effectiveStartDate);
                                })->whereNotIn('id', $commissionDataId)->delete();
                        }

                        // Parse custom_field_X format for commission_type
                        $commissionType = $data['commission_type'];
                        $customSalesFieldId = $data['custom_sales_field_id'] ?? null; // Use original value by default
                        
                        // Only parse and modify custom field values if feature is enabled (using cached check)
                        if ($isCustomFieldsEnabled) {
                            // If commission_type is in custom_field_X format, parse it
                            if (preg_match('/^custom_field_(\d+)$/', $commissionType, $matches)) {
                                $commissionType = 'custom field';
                                $customSalesFieldId = (int) $matches[1];
                            }
                            // Ensure custom_sales_field_id is null for non-custom field types
                            if ($commissionType !== 'custom field') {
                                $customSalesFieldId = null;
                            }
                        }

                        $commissionHistory = UserCommissionHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $commission['product_id'], 'core_position_id' => $data['core_position_id'], 'commission_effective_date' => $effectiveStartDate], [
                            'position_id' => $positionId,
                            'sub_position_id' => $subPositionId,
                            'updater_id' => auth()->user()->id,
                            'self_gen_user' => $data['self_gen_user'],
                            'commission' => $data['commission'],
                            'commission_type' => $commissionType,
                            'custom_sales_field_id' => $customSalesFieldId,
                            'effective_end_date' => $effectiveEndDate ?? null,
                            'tiers_id' => $data['tiers_id'] ?? null,
                        ]);

                        $commissionId = $commissionHistory->id;
                        if ($companySettingTiers?->status) {
                            UserCommissionHistoryTiersRange::where('user_commission_history_id', $commissionId)->delete();
                            if (isset($data['tiers_id']) && $data['tiers_id'] > 0) {
                                foreach ($data['tiers_range'] as $range) {
                                    UserCommissionHistoryTiersRange::create([
                                        'user_id' => $userId,
                                        'user_commission_history_id' => $commissionId,
                                        'tiers_schema_id' => $data['tiers_id'] ?? null,
                                        'tiers_levels_id' => $range['id'] ?? null,
                                        'value' => $range['value'] ?? null,
                                        'value_type' => $data['commission_type'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            if ($request->filled('employee_upfronts') && count($request->employee_upfronts) != 0) {
                $upFronts = $request->employee_upfronts;
                foreach ($upFronts as $upFront) {
                    foreach ($upFront['data'] as $data) {
                        $upFrontDataId = [];
                        if ($request->date_selection_type == 'these_changes_are_temporary') {
                            if ($userPrevUpFront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $upFront['product_id'], 'core_position_id' => $data['core_position_id']])->where('upfront_effective_date', '<=', $effectiveEndDate)->orderBy('upfront_effective_date', 'DESC')->first()) {
                                $userPrevUpFrontData = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $upFront['product_id'], 'core_position_id' => $data['core_position_id'], 'upfront_effective_date' => $userPrevUpFront->upfront_effective_date])->get();
                                $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                                foreach ($userPrevUpFrontData as $userPrevUpFront) {
                                    if (! UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $userPrevUpFront['product_id'], 'milestone_schema_trigger_id' => $userPrevUpFront['milestone_schema_trigger_id'], 'core_position_id' => $userPrevUpFront['core_position_id'], 'upfront_effective_date' => $effectiveDate])->first()) {
                                        $userPrevUpFront['upfront_effective_date'] = $effectiveDate;
                                        $createUpFront = UserUpfrontHistory::create($userPrevUpFront->toArray());
                                        $upFrontDataId[] = $createUpFront->id;
                                    }
                                }
                            }
                        }

                        if ($data['conflict_date_setting'] == 'overwrite_conflicting_dates') {
                            UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $upFront['product_id'], 'core_position_id' => $data['core_position_id']])
                                ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                                    $q->whereBetween('upfront_effective_date', [$effectiveStartDate, $effectiveEndDate]);
                                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                                    $q->where('upfront_effective_date', '>=', $effectiveStartDate);
                                })->whereNotIn('id', $upFrontDataId)->delete();
                        }

                        foreach ($data['schemas'] as $schema) {
                            // Parse custom_field_X format to extract type and ID
                            $upfrontSaleType = $schema['upfront_sale_type'] ?? null;
                            $customSalesFieldId = $schema['custom_sales_field_id'] ?? null; // Use original value by default
                            
                            // Only parse and modify custom field values if feature is enabled (using cached check)
                            if ($isCustomFieldsEnabled) {
                                // If upfront_sale_type is in custom_field_X format, parse it
                                if ($upfrontSaleType && preg_match('/^custom_field_(\d+)$/', $upfrontSaleType, $matches)) {
                                    $upfrontSaleType = 'custom field';
                                    $customSalesFieldId = (int) $matches[1];
                                }
                                // Ensure custom_sales_field_id is only set for 'custom field' type
                                if ($upfrontSaleType !== 'custom field') {
                                    $customSalesFieldId = null;
                                }
                            }
                            
                            $upFrontHistory = UserUpfrontHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $upFront['product_id'], 'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'], 'core_position_id' => $data['core_position_id'], 'upfront_effective_date' => $effectiveStartDate], [
                                'position_id' => $positionId,
                                'sub_position_id' => $subPositionId,
                                'milestone_schema_id' => $data['milestone_id'],
                                'self_gen_user' => $data['self_gen_user'],
                                'updater_id' => auth()->user()->id,
                                'upfront_pay_amount' => $schema['upfront_pay_amount'],
                                'upfront_sale_type' => $upfrontSaleType,
                                'effective_end_date' => $effectiveEndDate ?? null,
                                'tiers_id' => $schema['tiers_id'] ?? null,
                                'custom_sales_field_id' => $customSalesFieldId,
                            ]);

                            $upFrontId = $upFrontHistory->id;
                            if ($companySettingTiers?->status) {
                                UserUpfrontHistoryTiersRange::where('user_upfront_history_id', $upFrontId)->delete();
                                if (isset($schema['tiers_id']) && $schema['tiers_id'] > 0) {
                                    foreach ($schema['tiers_range'] as $range) {
                                        UserUpfrontHistoryTiersRange::create([
                                            'user_id' => $userId,
                                            'user_upfront_history_id' => $upFrontId,
                                            'tiers_schema_id' => $schema['tiers_id'] ?? null,
                                            'tiers_levels_id' => $range['id'] ?? null,
                                            'value' => $range['value'] ?? null,
                                            'value_type' => $upfrontSaleType,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($request->filled('employee_overrides') && count($request->employee_overrides) != 0) {
                $overrides = $request->employee_overrides;
                $overrideSetting = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();
                if ($overrideSetting) {
                    foreach ($overrides as $override) {
                        $overrideDataId = [];
                        $additionalOverrideDataId = [];
                        if ($request->date_selection_type == 'these_changes_are_temporary') {
                            if ($userPrevOverride = UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $override['product_id']])->where('override_effective_date', '<=', $effectiveEndDate)->orderBy('override_effective_date', 'DESC')->first()) {
                                $userPrevOverrideData = UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $override['product_id'], 'override_effective_date' => $userPrevOverride->override_effective_date])->get();
                                $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                                foreach ($userPrevOverrideData as $userPrevOverride) {
                                    if (! UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $userPrevOverride['product_id'], 'override_effective_date' => $effectiveDate])->first()) {
                                        $userPrevOverride['override_effective_date'] = $effectiveDate;
                                        $createOverride = UserOverrideHistory::create($userPrevOverride->toArray());
                                        $overrideDataId[] = $createOverride->id;
                                    }
                                }
                            }

                            if ($userPrevAdditionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'product_id' => $override['product_id']])->where('override_effective_date', '<=', $effectiveEndDate)->orderBy('override_effective_date', 'DESC')->first()) {
                                $userPrevAdditionalOverrideData = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'product_id' => $override['product_id'], 'override_effective_date' => $userPrevAdditionalOverride->override_effective_date])->get();
                                $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                                foreach ($userPrevAdditionalOverrideData as $userPrevAdditionalOverride) {
                                    if (! UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'product_id' => $userPrevAdditionalOverride['product_id'], 'office_id' => $userPrevAdditionalOverride['office_id'], 'override_effective_date' => $effectiveDate])->first()) {
                                        $userPrevAdditionalOverride['override_effective_date'] = $effectiveDate;
                                        $createAdditionalOverride = UserAdditionalOfficeOverrideHistory::create($userPrevAdditionalOverride->toArray());
                                        $additionalOverrideDataId[] = $createAdditionalOverride->id;
                                    }
                                }
                            }
                        }

                        if (isset($override['conflict_date_setting']) && $override['conflict_date_setting'] == 'overwrite_conflicting_dates') {
                            UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $override['product_id']])
                                ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                                    $q->whereBetween('override_effective_date', [$effectiveStartDate, $effectiveEndDate]);
                                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                                    $q->where('override_effective_date', '>=', $effectiveStartDate);
                                })->whereNotIn('id', $overrideDataId)->delete();

                            UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'product_id' => $override['product_id']])
                                ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                                    $q->whereBetween('override_effective_date', [$effectiveStartDate, $effectiveEndDate]);
                                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                                    $q->where('override_effective_date', '>=', $effectiveStartDate);
                                })->whereNotIn('id', $additionalOverrideDataId)->delete();
                        }

                        // Parse custom_field_X format for override types
                        $directType = $override['direct_overrides_type'];
                        $directCustomFieldId = $override['direct_custom_sales_field_id'] ?? null;
                        $indirectType = $override['indirect_overrides_type'];
                        $indirectCustomFieldId = $override['indirect_custom_sales_field_id'] ?? null;
                        $officeType = $override['office_overrides_type'];
                        $officeCustomFieldId = $override['office_custom_sales_field_id'] ?? null;
                        
                        // Only parse and modify custom field values if feature is enabled (scoped to company)
                        if (\App\Helpers\CustomSalesFieldHelper::isFeatureEnabled()) {
                            // Direct override
                            if ($directType && preg_match('/^custom_field_(\d+)$/', $directType, $matches)) {
                                $directType = 'custom field';
                                $directCustomFieldId = (int) $matches[1];
                            } elseif ($directType !== 'custom field') {
                                $directCustomFieldId = null;
                            }
                            // Indirect override
                            if ($indirectType && preg_match('/^custom_field_(\d+)$/', $indirectType, $matches)) {
                                $indirectType = 'custom field';
                                $indirectCustomFieldId = (int) $matches[1];
                            } elseif ($indirectType !== 'custom field') {
                                $indirectCustomFieldId = null;
                            }
                            // Office override
                            if ($officeType && preg_match('/^custom_field_(\d+)$/', $officeType, $matches)) {
                                $officeType = 'custom field';
                                $officeCustomFieldId = (int) $matches[1];
                            } elseif ($officeType !== 'custom field') {
                                $officeCustomFieldId = null;
                            }
                        }
                        
                        $overrideHistory = UserOverrideHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $override['product_id'], 'override_effective_date' => $effectiveStartDate], [
                            'updater_id' => auth()->user()->id,
                            'position_id' => $positionId,
                            'sub_position_id' => $subPositionId,
                            'direct_overrides_amount' => $override['direct_overrides_amount'],
                            'direct_overrides_type' => $directType,
                            'direct_custom_sales_field_id' => $directCustomFieldId,
                            'indirect_overrides_amount' => $override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $indirectType,
                            'indirect_custom_sales_field_id' => $indirectCustomFieldId,
                            'office_overrides_amount' => $override['office_overrides_amount'],
                            'office_overrides_type' => $officeType,
                            'office_custom_sales_field_id' => $officeCustomFieldId,
                            'office_stack_overrides_amount' => $override['office_stack_overrides_amount'],
                            'effective_end_date' => $effectiveEndDate ?? null,
                            'direct_tiers_id' => $override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $override['office_tiers_id'] ?? null,
                        ]);

                        $overrideId = $overrideHistory->id;
                        if ($companySettingTiers?->status) {
                            UserDirectOverrideHistoryTiersRange::where('user_override_history_id', $overrideId)->delete();
                            if ($override['direct_tiers_id'] > 0) {
                                foreach ($override['direct_tiers_range'] as $range) {
                                    UserDirectOverrideHistoryTiersRange::create([
                                        'user_id' => $userId,
                                        'user_override_history_id' => $overrideId,
                                        'tiers_schema_id' => $override['direct_tiers_id'] ?? null,
                                        'tiers_levels_id' => $range['id'] ?? null,
                                        'value' => $range['value'] ?? null,
                                        'value_type' => $directType,
                                    ]);
                                }
                            }

                            UserIndirectOverrideHistoryTiersRange::where('user_override_history_id', $overrideId)->delete();
                            if ($override['indirect_tiers_id'] > 0) {
                                foreach ($override['indirect_tiers_range'] as $range) {
                                    UserIndirectOverrideHistoryTiersRange::create([
                                        'user_id' => $userId,
                                        'user_override_history_id' => $overrideId,
                                        'tiers_schema_id' => $override['indirect_tiers_id'] ?? null,
                                        'tiers_levels_id' => $range['id'] ?? null,
                                        'value' => $range['value'] ?? null,
                                        'value_type' => $indirectType,
                                    ]);
                                }
                            }

                            UserOfficeOverrideHistoryTiersRange::where('user_office_override_history_id', $overrideId)->delete();
                            if ($override['office_tiers_id'] > 0) {
                                foreach ($override['office_tiers_range'] as $range) {
                                    UserOfficeOverrideHistoryTiersRange::create([
                                        'user_id' => $userId,
                                        'user_office_override_history_id' => $overrideId,
                                        'tiers_schema_id' => $override['office_tiers_id'] ?? null,
                                        'tiers_levels_id' => $range['id'] ?? null,
                                        'value' => $range['value'] ?? null,
                                        'value_type' => $officeType,
                                    ]);
                                }
                            }
                        }

                        if (isset($override['additional_office_override']) && count($override['additional_office_override']) != 0) {
                            foreach ($override['additional_office_override'] as $additionalOverride) {
                                $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $override['product_id'], 'office_id' => $additionalOverride['office_id'], 'override_effective_date' => $effectiveStartDate], [
                                    'updater_id' => auth()->user()->id,
                                    'state_id' => isset($additionalOverride['state_id']) ? $additionalOverride['state_id'] : '',
                                    'effective_end_date' => $effectiveEndDate ? $effectiveEndDate : null,
                                    'office_overrides_amount' => isset($additionalOverride['overrides_amount']) ? $additionalOverride['overrides_amount'] : '',
                                    'office_overrides_type' => isset($additionalOverride['overrides_type']) ? $additionalOverride['overrides_type'] : '',
                                    'tiers_id' => isset($additionalOverride['tiers_id']) ? @$additionalOverride['tiers_id'] : '',
                                ]);

                                UserAdditionalOfficeOverrideHistoryTiersRange::where('user_add_office_override_history_id', $additionalOfficeOverride->id)->delete();
                                if (isset($additionalOverride['tiers_range']) && count($additionalOverride['tiers_range']) != 0) {
                                    foreach ($additionalOverride['tiers_range'] as $range) {
                                        UserAdditionalOfficeOverrideHistoryTiersRange::create([
                                            'user_id' => $userId,
                                            'user_add_office_override_history_id' => $additionalOfficeOverride->id ? $additionalOfficeOverride->id : '',
                                            'tiers_schema_id' => $additionalOverride['tiers_id'] ?? null,
                                            'tiers_levels_id' => $range['id'] ?? null,
                                            'value' => $range['value'] ?? null,
                                            'value_type' => isset($additionalOverride['overrides_type']) ? $additionalOverride['overrides_type'] : null,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($request->filled('settlement') && count($request->settlement) != 0) {
                $settlements = $request->settlement;
                $reconciliationSetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
                if ($reconciliationSetting) {
                    foreach ($settlements as $settlement) {
                        $withHeldDataId = [];
                        if ($request->date_selection_type == 'these_changes_are_temporary') {
                            if ($userPrevWithHeld = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $settlement['product_id']])->where('withheld_effective_date', '<=', $effectiveEndDate)->orderBy('withheld_effective_date', 'DESC')->first()) {
                                $userPrevWithHeldData = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $settlement['product_id'], 'withheld_effective_date' => $userPrevWithHeld->withheld_effective_date])->get();
                                $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                                foreach ($userPrevWithHeldData as $userPrevWithHeld) {
                                    if (! UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $userPrevWithHeld['product_id'], 'withheld_effective_date' => $effectiveDate])->first()) {
                                        $userPrevWithHeld['withheld_effective_date'] = $effectiveDate;
                                        $withHeld = UserWithheldHistory::create($userPrevWithHeld->toArray());
                                        $withHeldDataId[] = $withHeld->id;
                                    }
                                }
                            }
                        }

                        if ($settlement['conflict_date_setting'] == 'overwrite_conflicting_dates') {
                            UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $settlement['product_id']])
                                ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                                    $q->whereBetween('withheld_effective_date', [$effectiveStartDate, $effectiveEndDate]);
                                })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                                    $q->where('withheld_effective_date', '>=', $effectiveStartDate);
                                })->whereNotIn('id', $withHeldDataId)->delete();
                        }

                        UserWithheldHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $settlement['product_id'], 'withheld_effective_date' => $effectiveStartDate], [
                            'updater_id' => auth()->user()->id,
                            'position_id' => $positionId,
                            'sub_position_id' => $subPositionId,
                            'effective_end_date' => $effectiveEndDate ?? null,
                            'withheld_type' => $settlement['withheld_type'],
                            'withheld_amount' => $settlement['withheld_amount'],
                        ]);
                    }
                }
            }

            if ($request->filled('deductions') && count($request->deductions) != 0) {
                $deductionDataId = [];
                if ($request->date_selection_type == 'these_changes_are_temporary') {
                    if ($userPrevDeduction = UserDeductionHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveEndDate)->orderBy('effective_date', 'DESC')->first()) {
                        $userPrevDeductionData = UserDeductionHistory::where(['user_id' => $userId, 'effective_date' => $userPrevDeduction->effective_date])->get();
                        $effectiveDate = date('Y-m-d', strtotime('+1 day', strtotime($effectiveEndDate)));
                        foreach ($userPrevDeductionData as $userPrevDeduction) {
                            if (! UserDeductionHistory::where(['user_id' => $userId, 'cost_center_id' => $userPrevDeduction['cost_center_id'], 'effective_date' => $effectiveDate])->first()) {
                                $userPrevDeduction['effective_date'] = $effectiveDate;
                                $deduction = UserDeductionHistory::create($userPrevDeduction->toArray());
                                $deductionDataId[] = $deduction->id;
                            }
                        }
                    }
                }

                UserDeductionHistory::where(['user_id' => $userId])
                    ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                        $q->whereBetween('effective_date', [$effectiveStartDate, $effectiveEndDate]);
                    })->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                        $q->where('effective_date', '>=', $effectiveStartDate);
                    })->whereNotIn('id', $deductionDataId)->delete();

                $deductions = $request->deductions;
                foreach ($deductions as $deduction) {
                    $positionDeduction = PositionCommissionDeduction::where(['position_id' => $positionId, 'cost_center_id' => $deduction['cost_center_id']])->first();
                    UserDeductionHistory::updateOrCreate(['user_id' => $userId, 'cost_center_id' => $deduction['cost_center_id'], 'effective_date' => $effectiveStartDate], [
                        'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                        'pay_period_from' => $positionDeduction?->pay_period_from ?? null,
                        'pay_period_to' => $positionDeduction?->pay_period_to ?? null,
                        'effective_end_date' => $effectiveEndDate ?? null,
                    ]);
                }
            }

            if ($request->filled('employee_agreement') && count($request->employee_agreement) != 0) {
                UserAgreementHistory::updateOrCreate(['user_id' => $userId], [
                    'probation_period' => $request->employee_agreement['probation_period'],
                    'period_of_agreement' => $request->employee_agreement['period_of_agreement'],
                    'end_date' => $request->employee_agreement['end_date'],
                ]);

                $userData = User::where('id', $userId)->first();
                User::where('id', $userId)->update([
                    'created_at' => $request->employee_agreement['hired_date'].' '.date('H:i:s'),
                    'probation_period' => $request->employee_agreement['probation_period'],
                    'period_of_agreement_start_date' => $request->employee_agreement['period_of_agreement'],
                    'end_date' => $request->employee_agreement['end_date'],
                ]);

                if (date('Y-m-d', strtotime($userData->created_at)) == $request->employee_agreement['hired_date']) {
                    if (Crms::where('id', 3)->where('status', 1)->first()) {
                        $this->update_hireDate($request);
                    }
                }
            }

            if (isset($request->organization['department_id'])) {
                UserDepartmentHistory::updateOrCreate(['user_id' => $userId, 'effective_date' => $effectiveStartDate], [
                    'updater_id' => auth()->user()->id,
                    'department_id' => $request->organization['department_id'],
                ]);
            }

            // Commit transaction before dispatching jobs
            DB::commit();

            // Dispatch history sync job immediately (Octane compatible)
                try {
                    ApplyHistoryOnUsersV2Job::dispatch(
                    implode(',', $userIds),
                    auth()->user()->id
                )->onQueue('sales-process');

                Log::info('History sync job dispatched (Octane-compatible)', [
                    'users' => implode(',', $userIds),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch history sync job', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }

            // Dispatch sales recalculation job immediately (Octane compatible)
            try {
                // Query for open sales that need recalculation
                    $m2Paid = UserCommission::where(['is_last' => '1', 'status' => 3, 'settlement_type' => 'during_m2', 'is_displayed' => '1'])
                        ->select('pid')
                        ->get()
                        ->pluck('pid')
                        ->toArray();

                    $reconM2Paid = UserCommission::where(['is_last' => '1', 'recon_status' => 3, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])
                        ->select('pid')
                        ->get()
                        ->pluck('pid')
                        ->toArray();

                    $paidSale = array_merge($m2Paid, $reconM2Paid);

                    $pid = SalesMaster::select('pid')
                        ->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                            $q->where(function ($q) use ($userId) {
                                $q->where('closer1_id', $userId)
                                    ->orWhere('setter1_id', $userId)
                                    ->orWhere('closer2_id', $userId)
                                    ->orWhere('setter2_id', $userId);
                            });
                        })
                        ->when($effectiveEndDate, function ($q) use ($effectiveStartDate, $effectiveEndDate) {
                            $q->whereBetween('customer_signoff', [$effectiveStartDate, $effectiveEndDate]);
                        })
                        ->when(! $effectiveEndDate, function ($q) use ($effectiveStartDate) {
                            $q->where('customer_signoff', '>=', $effectiveStartDate);
                        })
                        ->whereNotIn('pid', $paidSale)
                        ->whereNull('date_cancelled')
                        ->pluck('pid')
                        ->toArray();

                    if (! empty($pid)) {
                        ProcessRecalculatesOpenSales::dispatch($pid, ['user_id' => $userId])
                        ->onQueue('sales-process');

                    Log::info('Sales recalculation job dispatched (Octane-compatible)', [
                            'user_id' => $userId,
                            'sales_count' => count($pid),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch sales recalculation job', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }

            // EspQuickBase Rep Data Push integration (Silent - fire and forget)
            if (isset($GLOBALS['recalculate_sales_user_id'])) {
                $userId = $GLOBALS['recalculate_sales_user_id'];
                $this->espQuickBaseService->sendUserDataSilently($userId, 'update_employment_package');
            }
            // End EspQuickBase Rep Data Push integration

            // Completion notification after DB commit (jobs may still run, but update is committed).
            try {
                app(JobNotificationService::class)->notify(
                    $initiatorUserId,
                    'employment_package_update',
                    'Employment package update',
                    'completed',
                    100,
                    'Employment package update completed.',
                    $notificationUniqueKey,
                    $notificationInitiatedAt,
                    now()->toIso8601String(),
                    [
                        'wizard_type' => (string) $request->wizard_type,
                        'user_id' => (int) $request->id,
                    ]
                );
            } catch (\Throwable) {
                // best-effort only
            }

            // Transaction is already committed if we reach here without exceptions
        } catch (Throwable $e) {
            // Only roll back if we're in a transaction
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Log the error for debugging purposes
            Log::error('Employment package update failed', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId ?? null,
            ]);

            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
                'ApiName' => 'updateEmploymentPackage',
                'code' => 500,
            ];

            // Failure notification (best-effort). Keep the uniqueKey so UI updates the same card.
            try {
                app(JobNotificationService::class)->notify(
                    $initiatorUserId ?? (auth()->check() ? (int) auth()->id() : null),
                    'employment_package_update',
                    'Employment package update',
                    'failed',
                    0,
                    'Employment package update failed: ' . $e->getMessage(),
                    $notificationUniqueKey ?? ('employment_package_update_' . (int) ($request->id ?? 0) . '_' . time()),
                    $notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                    now()->toIso8601String(),
                    [
                        'wizard_type' => (string) ($request->wizard_type ?? ''),
                        'user_id' => (int) ($request->id ?? 0),
                    ]
                );
            } catch (\Throwable) {
                // best-effort only
            }
        }

        // Return response (jobs will dispatch after response is sent)
        return response()->json($response, $response['code']);
    }

    public function userw2TransferLocation(Request $request)
    {
        $this->checkValidations($request->all(), [
            'user_id' => 'required',
            'transfer_worker_location' => 'required', // 1099, w2
            'transfer_worker_location_date' => 'required',
        ]);

        $userId = $request->user_id;
        $transferWorkerLocation = $request->transfer_worker_location;
        $transferWorkerLocationDate = date('Y-m-d');
        // $currentDate = date('Y-m-d');
        $response = [];
        $user = User::select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'terminate', 'worker_type', 'period_of_agreement_start_date')->find($userId);

        if ($transferWorkerLocation == '1099') {
            $contractorTransferDate = $transferWorkerLocationDate;
            $employeeTransferDate = null;

            $transferData = W2UserTransferHistory::where(['user_id' => $userId, 'contractor_transfer_date' => $transferWorkerLocationDate])->first();
            if (! $transferData) {
                W2UserTransferHistory::where('user_id', $userId)->delete();
                W2UserTransferHistory::create([
                    'user_id' => $userId,
                    'updater_id' => isset(auth()->user()->id) ? auth()->user()->id : 0,
                    'period_of_agreement' => $user?->period_of_agreement_start_date,
                    'employee_transfer_date' => $employeeTransferDate ?? null,
                    'contractor_transfer_date' => $contractorTransferDate ?? null,
                    'type' => $transferWorkerLocation ?? null,
                ]);
            }
        } else {
            $contractorTransferDate = null;
            $employeeTransferDate = $transferWorkerLocationDate;

            $transferData = W2UserTransferHistory::where(['user_id' => $userId, 'employee_transfer_date' => $transferWorkerLocationDate])->first();
            if (! $transferData) {
                W2UserTransferHistory::where('user_id', $userId)->delete();
                W2UserTransferHistory::create([
                    'user_id' => $userId,
                    'updater_id' => isset(auth()->user()->id) ? auth()->user()->id : 0,
                    'period_of_agreement' => $user?->period_of_agreement_start_date,
                    'employee_transfer_date' => $employeeTransferDate ?? null,
                    'contractor_transfer_date' => $contractorTransferDate ?? null,
                    'type' => $transferWorkerLocation ?? null,
                ]);
            }
        }

        $this->successResponse('Successfully.', 'user-transfer-location', $response);
    }

    // re-hiring process
    public function reHireEmploymentPackageDetails(Request $request)
    {
        $this->checkValidations($request->all(), [
            'user_id' => 'required',
        ]);

        $userId = $request->user_id;
        $user = User::with('state', 'office', 'departmentDetail', 'positionDetail', 'subPositionDetail', 'recruiter', 'additionalDetail')->find($userId);
        if (! $user) {
            $this->errorResponse('User not found!!', 'userOrganizationDetails', '', 400);
        }
        $user->rehire = 1;
        $user->save();

        $effectiveDate = date('Y-m-d');
        
        // Check if Custom Sales Fields feature is enabled (for display formatting)
        // Use cached company profile to avoid repeated database queries
        $isCustomFieldsEnabledForRehire = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled();
        
        $userOrganization = UserOrganizationHistory::with('position')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $position = Positions::with([
            'product.productName',
            'Commission' => function ($q) {
                $q->where('commission_status', '1');
            },
            'Upfront' => function ($q) {
                $q->where('upfront_status', '1');
            },
            'Upfront.milestoneHistory.milestone',
            'Upfront.milestoneTrigger',
            'deductionName.costCenter',
            'deductionLimit',
            'deductionSetting',
            'Override.overridesDetail',
            'reconciliation' => function ($q) {
                $q->where('status', '1');
            },
            'payFrequency.frequencyType',
            'position_wage' => function ($q) {
                $q->where('wages_status', '1');
            },
        ])->where('id', $userOrganization?->sub_position_id)->first();

        $commissionData = [];
        if ($position && count($position->Commission) != 0) {
            $commissionData = $position->Commission->groupBy('product_id')->map(function ($groupByProduct) use ($userId, $effectiveDate, $isCustomFieldsEnabledForRehire) {
                return $groupByProduct->groupBy('commission_status')->map(function ($groupByStatus) use ($userId, $effectiveDate, $isCustomFieldsEnabledForRehire) {
                    $productId = $groupByStatus->first()->product_id;

                    return [
                        'product_id' => $groupByStatus->first()->product_id,
                        'commission_status' => $groupByStatus->first()->commission_status,
                        'commission_data' => $groupByStatus->map(function ($item) use ($userId, $productId, $effectiveDate, $isCustomFieldsEnabledForRehire) {
                            $corePositionId = $item->core_position_id;
                            $userCommission = UserCommissionHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('commission_effective_date', '<=', $effectiveDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();

                            return [
                                'core_position_id' => $userCommission?->core_position_id,
                                'self_gen_user' => $userCommission?->self_gen_user,
                                'commission' => $userCommission?->commission,
                                // Only use custom_field_X format when feature is enabled
                                'commission_type' => ($isCustomFieldsEnabledForRehire && $userCommission?->commission_type === 'custom field' && $userCommission?->custom_sales_field_id) ? 'custom_field_' . $userCommission->custom_sales_field_id : $userCommission?->commission_type,
                                'custom_sales_field_id' => ($isCustomFieldsEnabledForRehire && $userCommission?->commission_type === 'custom field') ? $userCommission?->custom_sales_field_id : null,
                                'commission_effective_date' => $userCommission?->commission_effective_date,
                                'tiers_id' => $userCommission?->tiers_id,
                                'tiers_range' => $userCommission?->tiers,
                            ];
                        })->values(),
                    ];
                })->values();
            })->flatten(1);
        }

        $redLine = [];
        $companyProfile = CompanyProfile::first();
        if ($position && ! in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $corePositions = [];
            if ($position?->is_selfgen == '1') {
                $corePositions = [2, 3, null];
            } elseif ($position?->is_selfgen == '2' || $position?->is_selfgen == '3') {
                $corePositions = [$position?->is_selfgen];
            } elseif ($position?->is_selfgen == '0') {
                $corePositions = [2];
            }

            foreach ($corePositions as $corePosition) {
                $userRedLine = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '<=', $effectiveDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                $redLine[] = [
                    'core_position_id' => $userRedLine?->core_position_id,
                    'self_gen_user' => $userRedLine?->self_gen_user,
                    'redline' => $userRedLine?->redline,
                    'redline_type' => $userRedLine?->redline_type,
                    'redline_amount_type' => $userRedLine?->redline_amount_type,
                    'redline_effective_date' => $userRedLine?->start_date,
                ];
            }
        }

        $upfrontData = [];
        if ($position && count($position->Upfront) != 0) {
            $upfrontData = $position->Upfront->groupBy('product_id')->map(function ($groupByProduct) use ($userId, $effectiveDate, $isCustomFieldsEnabledForRehire) {
                return $groupByProduct->groupBy('upfront_status')->map(function ($groupByStatus) use ($userId, $effectiveDate, $isCustomFieldsEnabledForRehire) {
                    $productId = $groupByStatus->first()->product_id;

                    return [
                        'product_id' => $groupByStatus->first()->product_id,
                        'upfront_status' => $groupByStatus->first()->upfront_status,
                        'data' => $groupByStatus->groupBy('core_position_id')->map(function ($groupByCorePosition) use ($userId, $effectiveDate, $productId, $isCustomFieldsEnabledForRehire) {
                            $corePositionId = $groupByCorePosition->first()->core_position_id;
                            $upFront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();

                            return [
                                'milestone_id' => $groupByCorePosition->first()->milestone_schema_id,
                                'core_position_id' => $corePositionId,
                                'self_gen_user' => $groupByCorePosition->first()->self_gen_user,
                                'upfront_effective_date' => $upFront?->upfront_effective_date,
                                'schemas' => $groupByCorePosition->groupBy('milestone_schema_id')->flatMap(function ($groupByMilestone) use ($userId, $effectiveDate, $productId, $corePositionId, $isCustomFieldsEnabledForRehire) {
                                    return $groupByMilestone->map(function ($item) use ($userId, $effectiveDate, $productId, $corePositionId, $isCustomFieldsEnabledForRehire) {
                                        $schemaId = $item->milestone_schema_trigger_id;
                                        $upFront = UserUpfrontHistory::with('tiers')->where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId, 'milestone_schema_trigger_id' => $schemaId])->where('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();

                                        return [
                                            'milestone_schema_trigger_id' => $schemaId,
                                            'upfront_pay_amount' => $upFront?->upfront_pay_amount,
                                            // Only use custom_field_X format when feature is enabled
                                            'upfront_sale_type' => ($isCustomFieldsEnabledForRehire && $upFront?->upfront_sale_type === 'custom field' && $upFront?->custom_sales_field_id) ? 'custom_field_' . $upFront->custom_sales_field_id : $upFront?->upfront_sale_type,
                                            'custom_sales_field_id' => ($isCustomFieldsEnabledForRehire && $upFront?->upfront_sale_type === 'custom field') ? $upFront?->custom_sales_field_id : null,
                                            'tiers_id' => $upFront?->tiers_id,
                                            'tiers_range' => $upFront?->tiers,
                                        ];
                                    })->values();
                                })->values(),
                            ];
                        })->values(),
                    ];
                })->values();
            })->values()->flatten(1);
        }

        $settlement = [];
        if ($position && count($position->reconciliation) != 0) {
            $settlement = $position->reconciliation->map(function ($item) use ($userId, $effectiveDate) {
                $productId = $item->product_id;
                $userWithHeld = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '<=', $effectiveDate)->orderBy('withheld_effective_date', 'DESC')->orderBy('id', 'DESC')->first();

                return [
                    'status' => $item->status,
                    'product_id' => $item->product_id,
                    'withheld_amount' => $userWithHeld?->withheld_amount,
                    'withheld_type' => $userWithHeld?->withheld_type,
                    'withheld_effective_date' => $userWithHeld?->withheld_effective_date,
                ];
            });
        }

        $overrideData = [];
        $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $additionalLocations = AdditionalLocations::with('state', 'city', 'office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
        $additionalOffice = [];
        $additionalOfficeEffectiveDate = $currentAdditional?->effective_date;
        foreach ($additionalLocations as $additionalLocation) {
            $additionalOffice[] = [
                'id' => $additionalLocation?->id,
                'state_id' => $additionalLocation?->state?->id,
                'state_name' => $additionalLocation?->state?->name,
                'office_id' => $additionalLocation?->office?->id,
                'office_name' => $additionalLocation?->office?->office_name,
                'effective_date' => $additionalLocation->effective_date,
            ];
        }

        $groupedData = [];
        if ($position && count($position->Override) != 0) {
            foreach ($position->Override as $item) {
                $productId = $item->product_id;
                $override = UserOverrideHistory::with('directTiers', 'indirectTiers', 'officeTiers')->where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $overrideStatus = count(collect($position->Override)->where('product_id', $productId)->where('status', 1)->values()) != 0 ? 1 : 0;
                if ($overrideStatus) {
                    if (! isset($groupedData[$productId])) {
                        $groupedData[$productId] = [
                            'product_id' => $productId,
                            'status' => count(collect($position->Override)->where('product_id', $productId)->where('status', 1)->values()) != 0 ? 1 : 0,
                            'override_effective_date' => $override?->override_effective_date,
                        ];
                    }

                    if ($item->override_id == 1) {
                        $groupedData[$productId]['direct_overrides_amount'] = $override?->direct_overrides_amount;
                        // Only use custom_field_X format when feature is enabled
                        $groupedData[$productId]['direct_overrides_type'] = ($isCustomFieldsEnabledForRehire && $override?->direct_overrides_type === 'custom field' && $override?->direct_custom_sales_field_id) ? 'custom_field_' . $override->direct_custom_sales_field_id : $override?->direct_overrides_type;
                        $groupedData[$productId]['direct_tiers_id'] = $override?->direct_tiers_id;
                        $groupedData[$productId]['direct_tiers_range'] = $override?->directTiers;
                    } elseif ($item->override_id == 2) {
                        $groupedData[$productId]['indirect_overrides_amount'] = $override?->indirect_overrides_amount;
                        // Only use custom_field_X format when feature is enabled
                        $groupedData[$productId]['indirect_overrides_type'] = ($isCustomFieldsEnabledForRehire && $override?->indirect_overrides_type === 'custom field' && $override?->indirect_custom_sales_field_id) ? 'custom_field_' . $override->indirect_custom_sales_field_id : $override?->indirect_overrides_type;
                        $groupedData[$productId]['indirect_tiers_id'] = $override?->indirect_tiers_id;
                        $groupedData[$productId]['indirect_tiers_range'] = $override?->indirectTiers;
                    } elseif ($item->override_id == 3) {
                        $groupedData[$productId]['office_overrides_amount'] = $override?->office_overrides_amount;
                        // Only use custom_field_X format when feature is enabled
                        $groupedData[$productId]['office_overrides_type'] = ($isCustomFieldsEnabledForRehire && $override?->office_overrides_type === 'custom field' && $override?->office_custom_sales_field_id) ? 'custom_field_' . $override->office_custom_sales_field_id : $override?->office_overrides_type;
                        $groupedData[$productId]['office_tiers_id'] = $override?->office_tiers_id;
                        $groupedData[$productId]['office_tiers_range'] = $override?->officeTiers;
                    } elseif ($item->override_id == 4) {
                        $groupedData[$productId]['office_stack_overrides_amount'] = $override?->office_stack_overrides_amount;
                    }
                }
            }

            foreach ($groupedData as $key => $grouped) {
                foreach ($additionalLocations as $additionalLocation) {
                    $override = UserAdditionalOfficeOverrideHistory::with('tearsRange')->where(['user_id' => $userId, 'office_id' => $additionalLocation->office_id, 'product_id' => $key])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $groupedData[$key]['additional_office_override'][] = [
                        'onboarding_location_id' => $additionalLocation?->id,
                        'state_id' => $additionalLocation?->state_id,
                        'state_name' => $additionalLocation?->state?->name,
                        'city_id' => $additionalLocation?->city_id,
                        'city_name' => $additionalLocation?->city?->name,
                        'office_id' => $additionalLocation?->office_id,
                        'office_name' => $additionalLocation?->office?->office_name,
                        'overrides_amount' => $override?->office_overrides_amount,
                        // Only use custom_field_X format when feature is enabled
                        'overrides_type' => ($isCustomFieldsEnabledForRehire && $override?->office_overrides_type === 'custom field' && $override?->office_custom_sales_field_id) ? 'custom_field_' . $override->office_custom_sales_field_id : $override?->office_overrides_type,
                        'office_custom_sales_field_id' => ($isCustomFieldsEnabledForRehire && $override?->office_overrides_type === 'custom field') ? $override?->office_custom_sales_field_id : null,
                        'tiers_id' => $override?->tiers_id,
                        'tiers_range' => $override?->tearsRange,
                    ];
                }
            }
            $overrideData = array_values($groupedData);
        }

        $deductionData = [];
        $positionDeductionLimit = $position?->deductionLimit;
        if ($position && count($position->deductionName) != 0) {
            $deductionData = $position->deductionName->map(function ($deductionName) use ($userId, $effectiveDate) {
                $userDeduction = UserDeductionHistory::where(['user_id' => $userId, 'cost_center_id' => $deductionName->cost_center_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();

                return [
                    'id' => $deductionName->id,
                    'cost_center_id' => $deductionName->cost_center_id,
                    'deduction_type' => $deductionName->deduction_type,
                    'ammount_par_paycheck' => $userDeduction?->amount_par_paycheque,
                    'effective_date' => $userDeduction?->effective_date,
                ];
            });
        }

        $wages = null;
        if ($position && isset($position->position_wage)) {
            $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($userWagesHistory) {
                $wages = [
                    'pay_type' => $userWagesHistory->pay_type ?? null,
                    'pay_rate' => $userWagesHistory->pay_rate ?? null,
                    'frequency_name' => $position?->payFrequency?->frequencyType?->name,
                    'pay_rate_type' => $userWagesHistory->pay_rate_type ?? null,
                    'pto_hours' => $userWagesHistory->pto_hours ?? null,
                    'unused_pto_expires' => $userWagesHistory->unused_pto_expires ?? null,
                    'expected_weekly_hours' => $userWagesHistory->expected_weekly_hours ?? null,
                    'effective_date' => $userWagesHistory->effective_date ?? null,
                ];
            }
        }

        $effectiveDate = date('Y-m-d');
        $userAgreement = UserAgreementHistory::where(['user_id' => $userId])->where('period_of_agreement', '<=', $effectiveDate)->orderBy('period_of_agreement', 'DESC')->orderBy('created_at', 'DESC')->first();
        if (! $userAgreement) {
            $userAgreement = UserAgreementHistory::where(['user_id' => $userId])->where('period_of_agreement', '>=', $effectiveDate)->orderBy('period_of_agreement', 'ASC')->orderBy('created_at', 'DESC')->first();
        }
        // $agreement = [
        //     'hired_date' => date('Y-m-d', strtotime($user?->created_at)),
        //     'probation_period' => ($userAgreement && $userAgreement?->probation_period != 'None') ? $userAgreement?->probation_period : NULL,
        //     'period_of_agreement' => ($userAgreement && $userAgreement?->period_of_agreement) ? $userAgreement?->period_of_agreement : NULL,
        //     'end_date' => ($userAgreement && $userAgreement?->end_date) ? $userAgreement?->end_date : NULL
        // ];
        // for rehiring period_of_agreement and end_date will not show prefilled, user will fill new dates.
        $agreement = [
            'hired_date' => date('Y-m-d', strtotime($user?->created_at)),
            'probation_period' => ($userAgreement && $userAgreement?->probation_period != 'None') ? $userAgreement?->probation_period : null,
            'period_of_agreement' => null,
            'end_date' => null,
        ];

        $products = [];
        if ($position) {

            foreach ($position->product as $product) {
                $products[] = [
                    'id' => $product->id,
                    'name' => $product?->productName?->name,
                    'product_id' => $product?->productName?->product_id,
                ];
            }

        }

        $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        $manager = UserManagerHistory::with('team', 'user')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();

        $additionalRecruiter = $user->additionalDetail->map(function ($recruiter) {
            return [
                'recruiter_id' => $recruiter?->additionalRecruiterDetail?->id,
                'recruiter_first_name' => $recruiter?->additionalRecruiterDetail?->first_name,
                'recruiter_last_name' => $recruiter?->additionalRecruiterDetail?->last_name,
            ];
        })->toArray();

        $organization = [
            'is_manager' => $isManager?->is_manager ?? 0,
            'is_manager_effective_date' => $isManager?->effective_date ?? null,
            'manager_id' => $manager?->user?->id,
            'manager_name' => $manager?->user ? $manager?->user?->first_name.' '.$manager?->user?->last_name : null,
            'manager_effective_date' => $manager?->effective_date,
            'team_id' => $manager?->team?->id,
            'team_name' => $manager?->team?->team_name,
            'recruiter_id' => $user?->recruiter?->id,
            'recruiter_name' => $user?->recruiter ? $user?->recruiter?->first_name.' '.$user?->recruiter?->last_name : null,
            'additional_recruter' => $additionalRecruiter,
            'additional_locations_effective_date' => $additionalOfficeEffectiveDate,
            'additional_locations' => $additionalOffice,
            'position_id' => $userOrganization?->position?->id,
            'position_name' => $userOrganization?->position?->position_name,
            'sub_position_id' => $position?->id,
            'sub_position_name' => $position?->position_name,
            'department_id' => $user?->departmentDetail?->id,
            'department_name' => $user?->departmentDetail?->name,
            'total_employee' => $manager?->user?->id ? User::where('manager_id', $manager?->user?->id)->count() ?? 0 : 0,
        ];

        $OnboardingEmployee = OnboardingEmployees::where('user_id', $user->id)->first();
        $response = [
            'id' => $user->id,
            'onboarding_id' => $OnboardingEmployee?->id,
            'dismiss' => $user->dismiss,
            'main_role' => $position?->is_selfgen,
            'details' => [
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'work_email' => $user?->OnboardingAdditionalEmails,
                'mobile_no' => $user->mobile_no,
                'state_id' => $user?->state?->id,
                'state_name' => $user?->state?->name,
                'city_id' => $user?->city_id,
                'city_name' => $user?->city?->name,
                'office_id' => $user?->office?->id,
                'office_name' => $user?->office?->office_name,
            ],
            'worker_type' => $position?->worker_type,
            'deduction_status' => $positionDeductionLimit?->status ?? 0,
            'products' => $products,
            'organization' => $organization,
            'wages' => $wages,
            'employee_commision' => $commissionData,
            'employee_redline' => $redLine,
            'employee_upfronts' => $upfrontData,
            'settlement' => $settlement,
            'employee_overrides' => $overrideData,
            'deductions' => $deductionData,
            'employee_agreement' => $agreement,
            'employee_admin_only_fields' => $user->employee_admin_only_fields ? json_decode($user->employee_admin_only_fields) : null,
        ];

        $this->successResponse('Successfully.', 'reHireEmploymentPackageDetails', $response);
    }

    /**
     * Transform custom field type for display - only when Custom Sales Fields feature is enabled.
     * 
     * @param string|null $type The type field (e.g., 'custom field', 'percent', 'per kw')
     * @param int|null $customFieldId The custom_sales_field_id
     * @return string|null Returns 'custom_field_X' if feature enabled and valid, otherwise original type
     */
    private function transformCustomFieldType(?string $type, ?int $customFieldId): ?string
    {
        // Only transform if Custom Sales Fields feature is enabled (use cached company profile)
        if (!\App\Helpers\CustomSalesFieldHelper::isFeatureEnabled()) {
            return $type;
        }

        // Handle custom field: only transform if we have both type AND ID (data integrity check)
        if ($type === 'custom field' && $customFieldId) {
            return 'custom_field_' . $customFieldId;
        }

        // Data without custom field ID: return original type so it displays as "(custom field)"
        // User should re-save to properly link the custom field
        if ($type === 'custom field' && !$customFieldId) {
            return 'custom field';
        }

        return $type;
    }

    /**
     * Get custom_sales_field_id for display - returns null when using custom_field_X format or feature disabled.
     * 
     * @param string|null $type The type field
     * @param int|null $customFieldId The custom_sales_field_id
     * @return int|null
     */
    private function getCustomFieldIdForDisplay(?string $type, ?int $customFieldId): ?int
    {
        // If feature disabled, return the original ID (use cached company profile)
        if (!\App\Helpers\CustomSalesFieldHelper::isFeatureEnabled()) {
            return $customFieldId;
        }

        // If type is 'custom field', we're using custom_field_X format, so don't return redundant ID
        return ($type === 'custom field') ? null : $customFieldId;
    }
}
