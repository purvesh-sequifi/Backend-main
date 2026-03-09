<?php

namespace App\Http\Controllers\API\V2\Useremploymentpackage;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Events\UserloginNotification;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessRecalculatesOpenSales;
use App\Jobs\PullFieldRoutesBackDateSalesJob;
use App\Models\AdditionalLocations;
use App\Models\AdditionalRecruiters;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\Crmcustomfields;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\Department;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\PayrollDeductions;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SequiDocsEmailSettings;
use App\Models\State;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserAdditionalOfficeOverrideHistoryTiersRange;
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
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use App\Traits\EmailNotificationTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\CustomSalesFieldHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Pennant\Feature;

class HiredUserController extends Controller
{
    use EmailNotificationTrait, EvereeTrait, SetterSubroutineListTrait;

    protected $companySettingtiers;

    public function __construct()
    {
        $this->companySettingtiers = CompanySetting::where('type', 'tier')->first();
    }

    public function userDetailById(Request $request, $id)
    {
        $product_id = $request->product_id ?? '';
        $currentDate = Carbon::now()->toDateString();
        $user = User::orderBy('id', 'desc')->with(['office', 'userSelfGenCommission' => function ($query) use ($currentDate) {
            $query->whereDate('commission_effective_date', '<=', $currentDate)
                ->orderBy('commission_effective_date', 'DESC');
        }])->newQuery();

        $user->with('departmentDetail', 'positionDetail', 'state', 'city', 'managerDetail', 'statusDetail', 'recruiter', 'additionalDetail', 'subpositionDetail', 'teamsDetail', 'recruiter');
        $data = $user->where('id', $id)->first();
        $totalMember = User::where('manager_id', $id)->count();
        if (isset($data) && $data != '') {
            $additional = $data->additionalDetail->map(function ($deducationname) {
                return [
                    'id' => $deducationname->id ?? null,
                    'recruiter_id' => $deducationname->recruiter_id ?? null,
                    'recruiter_first_name' => $deducationname->additionalRecruiterDetail->first_name ?? null,
                    'recruiter_last_name' => $deducationname->additionalRecruiterDetail->last_name ?? null,
                    'system_per_kw_amount' => $deducationname->system_per_kw_amount ?? null,
                ];
            })->toArray();

            $latest_effective_date = AdditionalLocations::where('effective_date', '<=', $currentDate)
                ->where('user_id', $id)
                ->orderBy('effective_date', 'desc')
                ->groupBy('effective_date')
                ->first();

            $additional_location = [];
            if (! empty($latest_effective_date->effective_date)) {
                $additional_location = AdditionalLocations::with('state', 'office')
                    ->where('user_id', $id)
                    ->where('effective_date', $latest_effective_date->effective_date)
                    ->get();
            }
            if ($additional_location) {
                $additional_location = $additional_location->map(function ($data) {
                    $additionalOverRide = $this->additionalOfficeChecker($data->user_id, $data->office?->id);

                    return [
                        'effective_date' => $additionalOverRide->override_effective_date ?? null,
                        'state_id' => $data->state_id ?? null,
                        'state_name' => $data->state->name ?? null,
                        'office_id' => $data->office->id ?? null,
                        'office_name' => $data->office->office_name ?? null,
                        'overrides_amount' => $additionalOverRide->office_overrides_amount ?? null,
                        'overrides_type' => $additionalOverRide->office_overrides_type ?? null,
                    ];
                })->toArray();
            }

            /* if ($user_redline_data) { */
            $product_id = $product_id ?? 0;
            $user_redline_data = UserRedlines::where('user_id', $id)->get();
            $user_redline_data->transform(function ($data) {
                return [
                    'user_id' => isset($data->user_id) ? $data->user_id : null,
                    'core_position_id' => isset($data->core_position_id) ? $data->core_position_id : null,
                    'updater_id' => isset($data->updater_id) ? $data->updater_id : null,
                    'redline' => isset($data->redline) ? $data->redline : null,
                    'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                    'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                    'self_gen_user' => isset($data->self_gen_user) ? $data->self_gen_user : null,
                    'start_date' => isset($data->start_date) ? $data->start_date : null,
                ];
            });
            $overRide = $this->overRideCheckr($data->id, $product_id);
            $overridedata = [
                'product_id' => isset($overRide->product_id) ? $overRide->product_id : null,
                'override_effective_date' => isset($overRide->override_effective_date) ? $overRide->override_effective_date : (isset($data->override_effective_date) ? $data->override_effective_date : null),
                'direct_overrides_amount' => isset($overRide->direct_overrides_amount) ? $overRide->direct_overrides_amount : (isset($data->direct_overrides_amount) ? $data->direct_overrides_amount : null),
                'direct_overrides_type' => isset($overRide->direct_overrides_type) ? $overRide->direct_overrides_type : (isset($data->direct_overrides_type) ? $data->direct_overrides_type : null),
                'indirect_overrides_amount' => isset($overRide->indirect_overrides_amount) ? $overRide->indirect_overrides_amount : (isset($data->indirect_overrides_amount) ? $data->indirect_overrides_amount : null),
                'indirect_overrides_type' => isset($overRide->indirect_overrides_type) ? $overRide->indirect_overrides_type : (isset($data->indirect_overrides_type) ? $data->indirect_overrides_type : null),
                'office_overrides_amount' => isset($overRide->office_overrides_amount) ? $overRide->office_overrides_amount : (isset($data->office_overrides_amount) ? $data->office_overrides_amount : null),
                'office_overrides_type' => isset($overRide->office_overrides_type) ? $overRide->office_overrides_type : (isset($data->office_overrides_type) ? $data->office_overrides_type : null),
                'office_stack_overrides_amount' => isset($overRide->office_stack_overrides_amount) ? $overRide->office_stack_overrides_amount : (isset($data->office_stack_overrides_amount) ? $data->office_stack_overrides_amount : null),
                // Custom Sales Field IDs
                'direct_custom_sales_field_id' => $overRide->direct_custom_sales_field_id ?? $data->direct_custom_sales_field_id ?? null,
                'indirect_custom_sales_field_id' => $overRide->indirect_custom_sales_field_id ?? $data->indirect_custom_sales_field_id ?? null,
                'office_custom_sales_field_id' => $overRide->office_custom_sales_field_id ?? $data->office_custom_sales_field_id ?? null,
                'direct_tiers_id' => isset($overRide->direct_tiers_id) ? $overRide->direct_tiers_id : null,
                'direct_tiers' => isset($overRide->direct_tiers) ? $overRide->direct_tiers : null,
                'indirect_tiers_id' => isset($overRide->indirect_tiers_id) ? $overRide->indirect_tiers_id : null,
                'indirect_tiers' => isset($overRide->indirect_tiers) ? $overRide->indirect_tiers : null,
                'office_tiers_id' => isset($overRide->office_tiers_id) ? $overRide->office_tiers_id : null,
                'office_tiers' => isset($overRide->office_tiers) ? $overRide->office_tiers : null,
            ];

            if (isset($data->recruiter->first_name, $data->recruiter->last_name)) {
                $recruiter_name = $data->recruiter->first_name.' '.$data->recruiter->last_name;
            } else {
                if ($data->recruiter) {
                    $recruiter_name = $data->recruiter->first_name;
                } else {
                    $recruiter_name = null;
                }
            }
            $manager = $this->managerCheckr($data->id);
            $managerName = $managerPosition = $managerDepartment = null;
            if ($manager && $manager->manager_id) {
                $managerUser = User::with('positionDetail', 'departmentDetail')->find($manager->manager_id);
                $managerName = $managerUser->first_name.' '.$managerUser->last_name;
                $managerPosition = isset($managerUser->positionDetail) ? $managerUser->positionDetail->position_name : null;
                $managerDepartment = isset($managerUser->departmentDetail) ? $managerUser->departmentDetail->name : null;
            }
            $teamId = @$manager->team_id;
            $teamName = @$manager->team->team_name;
            $teamEffectiveDate = @$manager->team_effetctive_date;
            $isManager = $this->isManagerCheckr($data->id);
            $user_redlinedata = UserCommissionHistory::where('user_id', $id)->groupBy('core_position_id')->get();
            $employee_compensation_result = [];
            if ($user_redlinedata && $product_id != '') {
                foreach ($user_redlinedata as $user_redlined) {
                    $emp_result = []; // Initialize emp_result for each user redline

                    $core_position_id = ! empty($user_redlined['core_position_id']) ? $user_redlined['core_position_id'] : '0';
                    $emp_result['core_position_id'] = $core_position_id;

                    // Retrieve readline data
                    $readline = $this->redLineCheckr($data->id, $product_id, $core_position_id);
                    $emp_result['redline'] = ! empty($readline) ? $readline : null;

                    // Retrieve commission data
                    $comm = $this->commissionCheckr($data->id, $product_id, $core_position_id);
                    $emp_result['commission'] = ! empty($comm) ? $comm : null;

                    // Retrieve upfront data
                    $upfronts = $this->upfrontCheckr($data->id, $product_id, $core_position_id);
                    $emp_result['upfront'] = ! empty($upfronts) ? $upfronts : null;
                    $withhelds = $this->withHeldCheckr($data->id, $product_id, $core_position_id);
                    $emp_result['withhelds'] = $withhelds;

                    $emp_result['core_position_id'] = $emp_result['core_position_id'] == 0 ? null : $emp_result['core_position_id'];
                    // Append the result for this user redline
                    $employee_compensation_result[] = $emp_result;
                }
            }
            $organization = [
                'office_id' => isset($data->office_id) ? $data->office_id : null,
                'is_manager' => isset($isManager->is_manager) ? $isManager->is_manager : null,
                'is_manager_effective_date' => isset($isManager->effective_date) ? $isManager->effective_date : null,
                'state_id' => isset($data->state_id) ? $data->state_id : null,
                'state_name' => isset($data['state']->name) ? $data['state']->name : null,
                'city_id' => isset($data->city_id) ? $data->city_id : null,
                'city_name' => isset($data['city']->name) ? $data['city']->name : null,
                'location' => isset($data->location) ? $data->location : null,
                'department_id' => isset($data->department_id) ? $data->department_id : null,
                'department_name' => isset($data->departmentDetail->name) ? $data->departmentDetail->name : null,
                'employee_position_id' => isset($data->employee_position_id) ? $data->employee_position_id : null,
                'manager_id' => isset($manager->manager_id) ? $manager->manager_id : null,
                'manager_id_effective_date' => isset($manager->effective_date) ? $manager->effective_date : null,
                'manager_name' => $managerName,
                'Employee_Manager_Position' => $managerPosition,
                'Employee_Manager_Department' => $managerDepartment,
                'team_id' => $teamId,
                'team_id_effective_date' => $teamEffectiveDate,
                'team_name' => $teamName,
                'recruiter_id' => isset($data->recruiter_id) ? $data->recruiter_id : null,
                // 'recruiter_name' =>isset($data->recruiter->first_name)?$data->recruiter->first_name:null,
                'recruiter_name' => $recruiter_name,
                'offer_include_bonus' => ($data->hiring_bonus_amount > 0) ? 1 : 0,
                'hiring_signature' => $data->hiring_signature ?? '',
                // 'additional_recruiter' => $additional,
                'position_id' => isset($data->position_id) ? $data->position_id : null,
                'position_name' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                'sub_position_id' => isset($data->sub_position_id) ? $data->sub_position_id : null,
                'sub_position_name' => isset($data->subpositionDetail->position_name) ? $data->subpositionDetail->position_name : null,
                'office' => isset($data->office) ? $data->office : null,
                'additional_locations' => isset($additional_location) ? $additional_location : null,
            ];

            $data1 = [
                'id' => isset($data->id) ? $data->id : null,
                'first_name' => isset($data->first_name) ? $data->first_name : null,
                'middle_name' => isset($data->middle_name) ? $data->middle_name : null,
                'last_name' => isset($data->last_name) ? $data->last_name : null,
                'sex' => isset($data->sex) ? $data->sex : null,
                'dob' => isset($data->dob) ? dateToYMD($data->dob) : null,
                'image' => isset($data->image) ? $data->image : null,
                'zip_code' => isset($data->zip_code) ? $data->zip_code : null,
                'email' => isset($data->email) ? $data->email : null,
                'self_gen_accounts' => isset($data->self_gen_accounts) ? $data->self_gen_accounts : null,
                'home_address' => isset($data->home_address) ? $data->home_address : null,
                'mobile_no' => isset($data->mobile_no) ? $data->mobile_no : null,
                'status_id' => isset($data->status_id) ? $data->status_id : null,
                'status_name' => isset($data->statusDetail->status) ? $data->statusDetail->status : null,
                'probation_period' => isset($data->probation_period) && $data->probation_period != 'None' ? $data->probation_period : null,
                'hiring_bonus_amount' => isset($data->hiring_bonus_amount) ? $data->hiring_bonus_amount : null,
                'date_to_be_paid' => isset($data->date_to_be_paid) ? dateToYMD($data->date_to_be_paid) : null,
                'period_of_agreement_start_date' => isset($data->period_of_agreement_start_date) ? dateToYMD($data->period_of_agreement_start_date) : null,
                'end_date' => isset($data->end_date) ? dateToYMD($data->end_date) : null,
                'offer_expiry_date' => isset($data->offer_expiry_date) ? $data->offer_expiry_date : null,
                'hired_date' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
                'type' => isset($data->type) ? $data->type : null,
                'organization' => $organization,
                'additional_recruter' => isset($additional) ? $additional : null,
                'user_wages' => $this->userwages($id, $data),
                'agreement' => $this->useragreement($id, $data),
                'employee_compensation' => $employee_compensation_result,
                'override' => $overridedata,
                'total_employee' => isset($totalMember) ? $totalMember : 0,
            ];
            $company = CompanySetting::where('type', 'reconciliation')->first();
            if ($company->status == 1) {
                $withHeld = PositionReconciliations::where('position_id', $data->sub_position_id)->where('status', 1)->first();
                if ($withHeld) {
                    $data1['withheld'] = $withHeld->commission_withheld;
                } else {
                    $data1['withheld'] = null;
                }
            } else {
                $data1['withheld'] = null;
            }

            $effectiveDate = UserDeductionHistory::select('user_id', 'effective_date')->where(['user_id' => $id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
            if ($effectiveDate) {
                $deductions = UserDeductionHistory::with('costcenter')->where(['user_id' => $id, 'effective_date' => $effectiveDate->effective_date])->get();
            } else {
                $deductions = [];
            }
            if (count($deductions) > 0) {
                $deductionPayPeriod = PayrollDeductions::select('user_id', 'pay_period_from', 'pay_period_to')->where('user_id', $id)->orderBy('pay_period_from', 'DESC')->first();
                $costCenterIds = PositionCommissionDeduction::where('position_id', $data->sub_position_id)->pluck('cost_center_id')->toArray();

                foreach ($deductions as $deduction) {
                    if ($deductionPayPeriod) {
                        $outstanding = PayrollDeductions::select('outstanding')->where(['user_id' => $id, 'cost_center_id' => $deduction->cost_center_id, 'pay_period_from' => $deductionPayPeriod->pay_period_from, 'pay_period_to' => $deductionPayPeriod->pay_period_to])->first();
                    }

                    $checkOutstanding = isset($outstanding->outstanding) ? $outstanding->outstanding : 0;
                    if (in_array($deduction->cost_center_id, $costCenterIds)) {
                        $isDelete = 0;
                    } else {
                        $isDelete = 1;
                    }
                    // echo $isDelete;die();
                    if ($isDelete == 1 && $checkOutstanding == 0) {
                        continue;
                    }

                    $data1['deduction'][] = [
                        'deduction_id' => $deduction->id,
                        'user_id' => $deduction->user_id,
                        'effective_date' => $deduction->effective_date,
                        'deduction_type' => isset($deduction->deduction_type) ? $deduction->deduction_type : '$',
                        'cost_center_name' => isset($deduction->costcenter->name) ? $deduction->costcenter->name : null,
                        'cost_center_id' => $deduction->cost_center_id,
                        'ammount_par_paycheck' => $deduction->amount_par_paycheque,
                        'deduction_setting_id' => isset($deduction->deduction_setting_id) ? $deduction->deduction_setting_id : null,
                        'position_id' => $deduction->sub_position_id,
                        'outstanding' => isset($outstanding->outstanding) ? $outstanding->outstanding : 0,
                        'is_deleted' => $isDelete,

                    ];
                }
            } else {
                $data1['deduction'] = [];
            }

            // echo "<pre>";print_r($data1);die();
            return response()->json([
                'ApiName' => 'Get User By Id',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data1,
            ], 200);
            /* } */
        } else {
            return response()->json([
                'ApiName' => 'Get User By Id',
                'status' => false,
                'message' => 'Invalid user id',
            ], 400);
        }
    }

    public function userOrganization(Request $request): JsonResponse
    {
        $udata = $this->userDataById($request->user_id);
        $reqdata = $request;
        $result = $this->organizationDataComp($udata, $reqdata);

        $data1 = User::find($request->user_id);
        if (! $data1) {
            return response()->json([
                'ApiName' => 'Update User Originization',
                'status' => false,
                'message' => 'Bad Request.',
            ], 400);
        }

        $is_manager = $request->employee_originization['is_manager'];
        $manager_id = $request->employee_originization['manager_id'];
        $team_id = $request->employee_originization['team_id'];
        $position_id = $request->employee_originization['position_id'];
        $sub_position_id = $request->employee_originization['sub_position_id'];
        $effective_date = date('Y-m-d', strtotime($request->employee_originization['effective_date']));
        $existing_employee_new_manager_id = $request->employee_originization['existing_employee_new_manager_id'] ?? null;
        $companyProfile = CompanyProfile::first();

        // Determine self_gen_accounts based on company type and domain
        $self_gen_accounts = ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK')))
            ? null
            : $request->employee_originization['self_gen_accounts'];

        // Get previous and current organization history for the user
        $user_organization_prev = UserOrganizationHistory::where('user_id', $request->user_id)
            ->where('effective_date', '<', $effective_date)
            ->orderBy('effective_date', 'desc')->first();

        $user_organization = UserOrganizationHistory::where([
            'user_id' => $request->user_id,
            'effective_date' => $effective_date,
        ])->orderBy('effective_date', 'desc')->first();

        // Check if organization exists and update or create as needed
        if ($user_organization) {
            $user_organization->updater_id = auth()->user()->id;
            if ($position_id != $user_organization->position_id) {
                $user_organization->old_position_id = $user_organization->position_id;
                $user_organization->position_id = $position_id;
            }
            if ($sub_position_id != $user_organization->sub_position_id) {
                $user_organization->old_sub_position_id = $user_organization->sub_position_id;
                $user_organization->sub_position_id = $sub_position_id;
            }
            $user_organization->effective_date = $effective_date;
            $user_organization->existing_employee_new_manager_id = $existing_employee_new_manager_id;
            $user_organization->old_self_gen_accounts = $user_organization->self_gen_accounts;
            $user_organization->self_gen_accounts = $self_gen_accounts;
            $user_organization->save();
        } else {
            UserOrganizationHistory::create([
                'user_id' => $request->user_id,
                'updater_id' => auth()->user()->id,
                'old_manager_id' => $user_organization_prev->manager_id ?? null,
                'old_team_id' => $user_organization_prev->team_id ?? null,
                'manager_id' => $manager_id,
                'team_id' => $team_id,
                'old_position_id' => $user_organization_prev->position_id ?? null,
                'old_sub_position_id' => $user_organization_prev->sub_position_id ?? null,
                'position_id' => $position_id,
                'sub_position_id' => $sub_position_id,
                'existing_employee_new_manager_id' => $existing_employee_new_manager_id,
                'effective_date' => $effective_date,
                'is_manager' => $is_manager,
                'old_is_manager' => $user_organization_prev->is_manager ?? null,
                'self_gen_accounts' => $self_gen_accounts,
                'old_self_gen_accounts' => $user_organization_prev->self_gen_accounts ?? null,
            ]);

            // Update old sub position ids in future records
            $user_organization_next = UserOrganizationHistory::where('user_id', $request->user_id)
                ->where('effective_date', '>', $effective_date)
                ->orderBy('effective_date', 'asc')->get();

            $updateOldPositionId = $sub_position_id;
            foreach ($user_organization_next as $value) {
                $value->old_sub_position_id = $updateOldPositionId;
                $value->save();
                $updateOldPositionId = $value->sub_position_id;
            }
        }

        if (isset($request->employee_originization['manager_id_effective_date'])) {
            $prevManager = UserManagerHistory::where('user_id', $data1->id)
                ->where('effective_date', '<=', $request->employee_originization['manager_id_effective_date'])
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            $managerData = [
                'user_id' => $data1->id,
                'updater_id' => Auth()->user()->id,
                'effective_date' => $request->employee_originization['manager_id_effective_date'],
                'manager_id' => $request->employee_originization['manager_id'],
                'team_id' => $request->employee_originization['team_id'],
                'position_id' => $request->employee_originization['position_id'],
                'sub_position_id' => $request->employee_originization['sub_position_id'],
            ];

            if ($prevManager) {
                if ($prevManager->manager_id != $request->employee_originization['manager_id'] || $prevManager->team_id != $request->employee_originization['team_id']) {
                    $managerData['old_manager_id'] = $prevManager->manager_id;
                    $managerData['old_team_id'] = $prevManager->team_id;
                    $managerData['old_position_id'] = $prevManager->position_id;
                    $managerData['old_sub_position_id'] = $prevManager->sub_position_id;
                    UserManagerHistory::create($managerData);
                }
            } else {
                UserManagerHistory::create($managerData);
            }
        }

        if (isset($request->employee_originization['is_manager_effective_date'])) {
            $prevManager = UserIsManagerHistory::where('user_id', $data1->id)
                ->whereDate('effective_date', '<=', $request->employee_originization['is_manager_effective_date'])
                ->orderBy('effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            $isManagerData = [
                'user_id' => $data1->id,
                'updater_id' => Auth()->user()->id,
                'effective_date' => $request->employee_originization['is_manager_effective_date'],
                'is_manager' => $request->employee_originization['is_manager'],
                'position_id' => $request->employee_originization['position_id'],
                'sub_position_id' => $request->employee_originization['sub_position_id'],
            ];

            if ($prevManager) {
                if ($prevManager->is_manager != $request->employee_originization['is_manager']) {
                    $isManagerData['old_is_manager'] = $prevManager->is_manager;
                    $isManagerData['old_position_id'] = $prevManager->position_id;
                    $isManagerData['old_sub_position_id'] = $prevManager->sub_position_id;
                    UserIsManagerHistory::create($isManagerData);
                }
            } else {
                UserIsManagerHistory::create($isManagerData);
            }
        }

        $everee_worker_id = $data1->everee_workerId;
        $aveyoid = $data1->aveyo_hs_id;
        $uid = auth()->user()->id;

        if (isset($effective_date) && $effective_date >= $data1->position_id_effective_date && $effective_date <= date('Y-m-d')) {
            $data1->department_id = $request->employee_originization['department_id'];
            $data1->position_id = $request->employee_originization['position_id'];
            $data1->self_gen_accounts = $request->employee_originization['self_gen_accounts'];

            // Set self_gen_type based on position_id and self_gen_accounts
            if (! empty($request->employee_originization['self_gen_accounts'])) {
                $data1->self_gen_type = ($request->employee_originization['position_id'] == 2) ? 3 : 2;
            } else {
                $data1->self_gen_type = null;
            }

            $data1->sub_position_id = $request->employee_originization['sub_position_id'];
            $data1->position_id_effective_date = $effective_date;

            // Update office and state data if provided
            $data1->office_id = $request->employee_originization['office_id'] ?? $request->office_id ?? $data1->office_id;
            $data1->state_id = $request->employee_originization['state_id'] ?? $request->state_id ?? $data1->state_id;
            $data1->city_id = $request->city_id ?? $data1->city_id;

            // Set sub_position_id and group_id if applicable
            if (! empty($request->employee_originization['sub_position_id'])) {
                $data1->sub_position_id = $request->employee_originization['sub_position_id'];
                $data1->group_id = Positions::where('id', $request->employee_originization['sub_position_id'])->value('group_id');
            }
        }

        $data1->recruiter_id = $request->employee_originization['recruiter_id'];
        $data1->save();

        $recruiter_id = $request->employee_originization['additional_recruiter_id'];
        $additional_locations = $request->employee_originization['additional_locations'];

        AdditionalRecruiters::where('user_id', $data1->id)->delete();
        foreach ($recruiter_id as $key => $value) {
            if ($key == 0) {
                User::where('id', $request->user_id)->update(['additional_recruiter_id1' => $value]);
            } else {
                User::where('id', $request->user_id)->update(['additional_recruiter_id2' => $value]);
            }

            $val = AdditionalRecruiters::create([
                'user_id' => $data1->id,
                'recruiter_id' => $value,
            ]);
        }

        if ($additional_locations) {
            $state_id_arr = [];
            $office_id_arr = [];
            foreach ($additional_locations as $additional_location) {
                $state_id_arr[] = isset($additional_location['state_id']) ? $additional_location['state_id'] : '';
                $office_id_arr[] = isset($additional_location['office_id']) ? trim($additional_location['office_id']) : '';
                $val = AdditionalLocations::updateOrCreate([
                    'user_id' => $request->user_id,
                    'state_id' => isset($additional_location['state_id']) ? $additional_location['state_id'] : '',
                    'office_id' => isset($additional_location['office_id']) ? trim($additional_location['office_id']) : '',
                    'effective_date' => isset($additional_location['effective_date']) ? trim($additional_location['effective_date']) : null,
                ], [
                    'overrides_amount' => isset($additional_location['overrides_amount']) ? trim($additional_location['overrides_amount']) : 0,
                    'overrides_type' => isset($additional_location['overrides_type']) ? trim($additional_location['overrides_type']) : 'per kw',
                    'updater_id' => auth()->user()->id,
                ]);

                $val = UserAdditionalOfficeOverrideHistory::updateOrCreate([
                    'user_id' => $request->user_id,
                    'state_id' => isset($additional_location['state_id']) ? $additional_location['state_id'] : '',
                    'office_id' => isset($additional_location['office_id']) ? trim($additional_location['office_id']) : '',
                    'override_effective_date' => isset($additional_location['effective_date']) ? trim($additional_location['effective_date']) : null,
                ], [
                    'office_overrides_amount' => isset($additional_location['overrides_amount']) ? trim($additional_location['overrides_amount']) : 0,
                    'office_overrides_type' => isset($additional_location['overrides_type']) ? trim($additional_location['overrides_type']) : 'per kw',
                    'updater_id' => auth()->user()->id,
                ]);
            }

            if (isset($request->employee_originization['additional_locations'][0]['effective_date'])) {
                $locations_query = AdditionalLocations::where('user_id', $request->user_id)->where('effective_date', date('Y-m-d', strtotime($request->employee_originization['additional_locations'][0]['effective_date'])));
                $locations_query->where(function ($query) use ($state_id_arr, $office_id_arr) {
                    $query->orWhereNotIn('state_id', $state_id_arr)
                        ->orWhereNotIn('office_id', $office_id_arr);
                });
                $locations_query->delete();
            }
        } else {
            /* if additionnal location request data is empty then delete all location */
            AdditionalLocations::where('user_id', $request->user_id)->delete();
        }

        // hubspot sale update code start
        $recruiter = User::select('first_name', 'last_name')->where('id', $data1->recruiter_id)->first();
        $manager = User::select('first_name', 'last_name')->where('id', $data1->manager_id)->first();
        $team = ManagementTeam::select('team_name')->where('id', $data1->team_id)->first();
        $office = Locations::select('office_name', 'work_site_id', 'everee_location_id', 'updated_at', 'general_code')->where('id', $data1->office_id)->first();
        $positions = Positions::select('position_name')->where('id', $data1->position_id)->first();
        $department = Department::where('id', $data1->department_id)->first();
        $state = State::where('id', $data1->state_id)->first();
        $additionalOfficeId = AdditionalLocations::where('user_id', $request->user_id)->pluck('office_id');
        $additionalOfficeName = Locations::whereNotNull('office_name')->whereIn('id', $additionalOfficeId)->pluck('office_name')->implode(',');
        $additionalWorkSiteId = Locations::whereNotNull('work_site_id')->whereIn('id', $additionalOfficeId)->pluck('work_site_id')->implode(',');

        if (! empty($office) && ! empty($state) && ! empty($everee_worker_id)) {
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {
                $this->update_emp_work_location($office, $state, $everee_worker_id);
            }
        }

        if ($data1['position_id'] == 2) {
            $payGroup = 'Closer';
            if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
                $closer_redline = null;
                $setter_redline = null;
            } else {
                $closer_redline = $data1['redline'];
                $setter_redline = $data1['self_gen_redline'];
            }
        }
        if ($data1['position_id'] == 3) {
            $payGroup = 'Setter';
            if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
                $closer_redline = null;
                $setter_redline = null;
            } else {
                $closer_redline = $data1['self_gen_redline'];
                $setter_redline = $data1['redline'];
            }
        }

        if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
            $payGroup = 'Closer';
        } else {
            if ($data1['self_gen_accounts'] == 1) {
                $payGroup = 'Setter&Closer';
            }
        }

        if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
            // No Need To Update Hubspot Data
        } else {
            $CrmData = Crms::where('id', 2)->where('status', 1)->first();
            $CrmSetting = CrmSetting::where('crm_id', 2)->first();
            if (! empty($CrmData) && ! empty($CrmSetting) && ! empty($aveyoid)) {
                $val = json_decode($CrmSetting['value']);
                $token = $val->api_key;

                $Hubspotdata['properties'] = [
                    'state' => isset($state->name) ? $state->name : null,
                    'department_id' => isset($data1->department_id) ? $data1->department_id : null,
                    'department' => isset($department->name) ? $department->name : null,
                    'position_id' => isset($data1->position_id) ? $data1->position_id : null,
                    'position' => isset($positions->position_name) ? $positions->position_name : null,
                    'redline' => $closer_redline, // in hubspot this is closer redline
                    'setter_redline' => $setter_redline,
                    'pay_group' => isset($payGroup) ? $payGroup : null,
                    'manager' => isset($manager->first_name) ? $manager->first_name.' '.$manager->last_name : null,
                    'manager_id' => isset($data1->manager_id) ? $data1->manager_id : null,
                    'team_id' => isset($data1->team_id) ? $data1->team_id : null,
                    'team' => isset($team->team_name) ? $team->team_name : null,
                    'office_id' => isset($office->work_site_id) ? $office->work_site_id : null,
                    'office' => isset($office->office_name) ? $office->office_name : null,
                    'recruiter_id' => isset($data1->recruiter_id) ? $data1->recruiter_id : null,
                    'recruiter' => isset($recruiter->first_name) ? $recruiter->first_name.' '.$recruiter->last_name : null,
                    'installer_on_file' => isset($office->general_code) ? $office->general_code : null,
                    'office_additional_id' => isset($additionalWorkSiteId) ? $additionalWorkSiteId : null,
                    'office_additional' => isset($additionalOfficeName) ? $additionalOfficeName : null,
                ];

                $this->update_employees($Hubspotdata, $token, $uid, $aveyoid);
            }
        }
        // hubspot sale update code end

        $deduction = $request->deductions;
        if (isset($deduction)) {
            UserDeduction::where('user_id', $request->user_id)->delete();
            foreach ($deduction as $deductions) {
                UserDeduction::create([
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

        $user = [
            'user_id' => $request->user_id,
            'description' => 'Updated Organization Data by '.auth()->user()->first_name,
            'type' => 'Organization',
            'is_read' => 0,
        ];

        $check = User::where('id', $request->user_id)->first();

        // send mail here
        if (! empty($result)) {
            $salesData = [];
            $salesData = SequiDocsEmailSettings::originization_employment_package_change_notification_email_content($check, $result);
            $salesData['email'] = $data1->email;

            if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                $this->sendEmailNotification($salesData);
            } else {
                // no default blade based email template here
            }
        }
        event(new UserloginNotification($user));

        return response()->json([
            'ApiName' => 'Updated Originization',
            'status' => true,
            'message' => 'Updated Successfully.',
        ]);
    }

    public function updateUserDeduction(Request $request): JsonResponse
    {
        $deductions = $request->deductions;
        if (! empty($deductions)) {
            foreach ($deductions as $key => $deduction) {
                $user = User::where('id', $request->user_id)->first();
                if ($key == 0) {
                    $self_gen_user = 0;
                    $sub_position_id = (isset($deduction['sub_position_id'])) ? $deduction['sub_position_id'] : $user->sub_position_id;
                } else {
                    $self_gen_user = 1;
                    $sub_position_id = (isset($deduction['sub_position_id'])) ? $deduction['sub_position_id'] : $deduction['position_id'];
                }

                $check_data = UserDeductionHistory::where('user_id', $request->user_id)
                    ->where('cost_center_id', $deduction['cost_center_id'])
                    ->where('effective_date', '=', date('Y-m-d', strtotime($deduction['effective_date'])))
                    ->first();
                $prev_check_data = UserDeductionHistory::where('user_id', $request->user_id)
                    ->where('effective_date', '<', date('Y-m-d', strtotime($deduction['effective_date'])))
                    ->where('cost_center_id', $deduction['cost_center_id'])
                    ->orderBy('effective_date', 'DESC')
                    ->first();
                $next_check_data = UserDeductionHistory::where('user_id', $request->user_id)
                    ->where('effective_date', '>', date('Y-m-d', strtotime($deduction['effective_date'])))
                    ->where('cost_center_id', $deduction['cost_center_id'])
                    ->orderBy('effective_date', 'ASC')
                    ->first();

                if (empty($prev_check_data) && empty($next_check_data)) {
                    $history = UserDeductionHistory::updateOrCreate([
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                    ], [
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'updater_id' => auth()->user()->id,
                        'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                        'old_amount_par_paycheque' => isset($prev_check_data) ? $prev_check_data->amount_par_paycheck : 0.00,
                    ]);
                } elseif (! empty($prev_check_data) && empty($next_check_data)) {

                    $history = UserDeductionHistory::updateOrCreate([
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                    ], [
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'updater_id' => auth()->user()->id,
                        'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                        'old_amount_par_paycheque' => isset($prev_check_data) ? $prev_check_data->amount_par_paycheque : 0.00,
                    ]);

                    $prev_check_data->amount_par_paycheque = isset($prev_check_data) ? $prev_check_data->amount_par_paycheque : 0.00;
                    $prev_check_data->save();
                } elseif (! empty($prev_check_data) && ! empty($next_check_data)) {

                    $history = UserDeductionHistory::updateOrCreate([
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                    ], [
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'updater_id' => auth()->user()->id,
                        'amount_par_paycheque' => isset($next_check_data) ? $next_check_data->amount_par_paycheck : 0.00,
                        'old_amount_par_paycheque' => isset($prev_check_data) ? $prev_check_data->amount_par_paycheck : 0.00,
                    ]);

                    $prev_check_data->amount_par_paycheque = isset($prev_check_data) ? $prev_check_data->amount_par_paycheck : 0.00;
                    $prev_check_data->save();

                    $next_check_data->old_amount_par_paycheque = isset($next_check_data) ? $next_check_data->amount_par_paycheck : 0.00;
                    $next_check_data->save();
                } elseif (empty($prev_check_data) && ! empty($next_check_data)) {

                    $history = UserDeductionHistory::updateOrCreate([
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                    ], [
                        'user_id' => $request->user_id,
                        'effective_date' => $deduction['effective_date'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'updater_id' => auth()->user()->id,
                        'amount_par_paycheque' => isset($next_check_data) ? $next_check_data->amount_par_paycheck : 0.00,
                        'old_amount_par_paycheque' => 0.00,
                    ]);

                    $next_check_data->old_amount_par_paycheque = isset($next_check_data) ? $next_check_data->amount_par_paycheck : 0.00;
                    $next_check_data->save();
                }

                $today = date('Y-m-d');
                $UserDeductionHistory = UserDeductionHistory::where('user_id', $request->user_id)
                    ->where('cost_center_id', $deduction['cost_center_id'])
                    ->where('effective_date', '<=', $today)
                    ->orderBy('effective_date', 'DESC')
                    ->first();

                $UserDeduction = UserDeduction::where('user_id', $request->user_id)
                    ->where('cost_center_id', $deduction['cost_center_id'])
                    ->first();

                if ($UserDeduction->ammount_par_paycheck != $deduction['ammount_par_paycheck'] || $UserDeduction->cost_center_id != $deduction['cost_center_id']) {
                    if (! empty($UserDeduction->effective_date) && strtotime($UserDeduction->effective_date) < strtotime($deduction['effective_date']) && strtotime($deduction['effective_date']) <= strtotime(date('Y-m-d'))) {
                        $UserDeduction->ammount_par_paycheck = $deduction['ammount_par_paycheck'];
                        $UserDeduction->cost_center_id = $deduction['cost_center_id'];
                        $UserDeduction->effective_date = $deduction['effective_date'];
                    } elseif (empty($UserDeduction->effective_date) || (isset($UserDeductionHistory->effective_date) && strtotime($UserDeduction->effective_date) <= strtotime($UserDeductionHistory->effective_date))) {
                        $UserDeduction->ammount_par_paycheck = isset($UserDeductionHistory->amount_par_paycheque) ? $UserDeductionHistory->amount_par_paycheque : $UserDeduction->ammount_par_paycheck;
                        $UserDeduction->effective_date = isset($UserDeductionHistory->effective_date) ? $UserDeductionHistory->effective_date : $UserDeduction->effective_date;
                    }
                }
                $UserDeduction->save();
            }
        }

        // create history code

        return response()->json([
            'ApiName' => 'Update User Deduction API',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);
    }

    public function userOverrides(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required',
                // 'override_effective_date' => 'required'
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        /* /* check additional office effective date validation */
        if ($request->additional_office_override && ! empty($request->additional_office_override)) {
            foreach ($request->additional_office_override as $key => $value) {
                $additionalLocation = AdditionalLocations::where('user_id', $request->user_id)
                    ->where('office_id', $value['office_id'])
                    ->first();
                if ($additionalLocation->effective_date > $request->employee_override['override_effective_date']) {
                    $locationData = Locations::find($additionalLocation->office_id);
                    $formattedEffectDate = Carbon::parse($request->employee_override['override_effective_date'])->format('m/d/Y');

                    return response()->json([
                        'ApiName' => 'User Overrides APi',
                        'status' => false,
                        'message' => "Effective date not applicable for this user at {$locationData->office_name} | {$locationData->State->name} on {$formattedEffectDate}. Please update to match the office's effective date.",
                    ], 400);
                }
            }
        }
        $udata = $this->userDataById($request->user_id);
        $reqdata = $request;
        $result = $this->overridesDataComp($udata, $reqdata);

        $user = User::where('id', $request->user_id)->first();
        if (! empty($user)) {
            // Custom Sales Field support: Parse custom_field_X format for override types
            $directParsed = $this->parseCustomFieldType(
                $request->employee_override['direct_overrides_type'] ?? null,
                $request->employee_override['direct_custom_sales_field_id'] ?? null
            );
            $indirectParsed = $this->parseCustomFieldType(
                $request->employee_override['indirect_overrides_type'] ?? null,
                $request->employee_override['indirect_custom_sales_field_id'] ?? null
            );
            $officeParsed = $this->parseCustomFieldType(
                $request->employee_override['office_overrides_type'] ?? null,
                $request->employee_override['office_custom_sales_field_id'] ?? null
            );

            // Update the request data with parsed values
            $request->merge([
                'employee_override' => array_merge($request->employee_override, [
                    'direct_overrides_type' => $directParsed['type'],
                    'direct_custom_sales_field_id' => $directParsed['custom_sales_field_id'],
                    'indirect_overrides_type' => $indirectParsed['type'],
                    'indirect_custom_sales_field_id' => $indirectParsed['custom_sales_field_id'],
                    'office_overrides_type' => $officeParsed['type'],
                    'office_custom_sales_field_id' => $officeParsed['custom_sales_field_id'],
                ]),
            ]);

            $data = (object) [];
            $override = UserOverrideHistory::where('user_id', $request->user_id)
                ->where('product_id', $request->employee_override['product_id'] ?? 0)
                ->where('override_effective_date', '=', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                ->first();
            $old_data = UserOverrideHistory::where('user_id', $request->user_id)
                ->where('override_effective_date', '<', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                ->where('product_id', $request->employee_override['product_id'] ?? 0)
                ->orderBy('override_effective_date', 'DESC')
                ->first();
            $next_data = UserOverrideHistory::where('user_id', $request->user_id)
                ->where('override_effective_date', '>', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                ->where('product_id', $request->employee_override['product_id'] ?? 0)
                ->orderBy('override_effective_date', 'ASC')
                ->first();

            if (empty($old_data) && empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'product_id' => $request->employee_override['product_id'],
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            // Custom Sales Field IDs
                            'direct_custom_sales_field_id' => $request->employee_override['direct_custom_sales_field_id'] ?? null,
                            'indirect_custom_sales_field_id' => $request->employee_override['indirect_custom_sales_field_id'] ?? null,
                            'office_custom_sales_field_id' => $request->employee_override['office_custom_sales_field_id'] ?? null,
                            'old_direct_overrides_amount' => 0,
                            'old_direct_overrides_type' => '',
                            'old_indirect_overrides_amount' => 0,
                            'old_indirect_overrides_type' => '',
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                            'old_office_stack_overrides_amount' => 0,
                        ]
                    );
                } else {
                    UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'product_id' => $request->employee_override['product_id'],
                        ]
                    )->update(
                        [
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            // Custom Sales Field IDs
                            'direct_custom_sales_field_id' => $request->employee_override['direct_custom_sales_field_id'] ?? null,
                            'indirect_custom_sales_field_id' => $request->employee_override['indirect_custom_sales_field_id'] ?? null,
                            'office_custom_sales_field_id' => $request->employee_override['office_custom_sales_field_id'] ?? null,
                            'action_item_status' => 0,
                            'old_direct_overrides_amount' => 0,
                            'old_direct_overrides_type' => '',
                            'old_indirect_overrides_amount' => 0,
                            'old_indirect_overrides_type' => '',
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                            'old_office_stack_overrides_amount' => 0,
                        ]
                    );
                    $checkdata = UserOverrideHistory::where(['user_id' => $request->user_id,
                        'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        'product_id' => $request->employee_override['product_id']])->first();
                }
            } elseif (empty($old_data) && ! empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'product_id' => $request->employee_override['product_id'],
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            // Custom Sales Field IDs
                            'direct_custom_sales_field_id' => $request->employee_override['direct_custom_sales_field_id'] ?? null,
                            'indirect_custom_sales_field_id' => $request->employee_override['indirect_custom_sales_field_id'] ?? null,
                            'office_custom_sales_field_id' => $request->employee_override['office_custom_sales_field_id'] ?? null,
                            'old_direct_overrides_amount' => 0,
                            'old_direct_overrides_type' => '',
                            'old_indirect_overrides_amount' => 0,
                            'old_indirect_overrides_type' => '',
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                            'old_office_stack_overrides_amount' => 0,
                        ]
                    );
                } else {
                    UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'product_id' => $request->employee_override['product_id'],
                        ]
                    )->update(
                        [
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            // Custom Sales Field IDs
                            'direct_custom_sales_field_id' => $request->employee_override['direct_custom_sales_field_id'] ?? null,
                            'indirect_custom_sales_field_id' => $request->employee_override['indirect_custom_sales_field_id'] ?? null,
                            'office_custom_sales_field_id' => $request->employee_override['office_custom_sales_field_id'] ?? null,
                            'action_item_status' => 0,
                            'old_direct_overrides_amount' => 0,
                            'old_direct_overrides_type' => '',
                            'old_indirect_overrides_amount' => 0,
                            'old_indirect_overrides_type' => '',
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                            'old_office_stack_overrides_amount' => 0,
                        ]
                    );
                    $checkdata = UserOverrideHistory::where(['user_id' => $request->user_id,
                        'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        'product_id' => $request->employee_override['product_id']])->first();
                }
                $next_data->old_direct_overrides_amount = $request->employee_override['direct_overrides_amount'];
                $next_data->old_direct_overrides_type = $request->employee_override['direct_overrides_type'];
                $next_data->old_indirect_overrides_amount = $request->employee_override['indirect_overrides_amount'];
                $next_data->old_indirect_overrides_type = $request->employee_override['indirect_overrides_type'];
                $next_data->old_office_overrides_amount = $request->employee_override['office_overrides_amount'];
                $next_data->old_office_overrides_type = $request->employee_override['office_overrides_type'];
                $next_data->old_office_stack_overrides_amount = $request->employee_override['office_stack_overrides_amount'];
                $next_data->old_direct_tiers_id = $request->employee_override['direct_tiers_id'] ?? '';
                $next_data->old_indirect_tiers_id = $request->employee_override['indirect_tiers_id'] ?? '';
                $next_data->old_office_tiers_id = $request->employee_override['office_tiers_id'] ?? '';
                $next_data->action_item_status = 0;
                $next_data->save();
            } elseif (! empty($old_data) && empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'product_id' => $request->employee_override['product_id'],
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'old_direct_overrides_amount' => isset($old_data->direct_overrides_amount) ? $old_data->direct_overrides_amount : 0,
                            'old_direct_overrides_type' => isset($old_data->direct_overrides_type) ? $old_data->direct_overrides_type : '',
                            'old_indirect_overrides_amount' => isset($old_data->indirect_overrides_amount) ? $old_data->indirect_overrides_amount : 0,
                            'old_indirect_overrides_type' => isset($old_data->indirect_overrides_type) ? $old_data->indirect_overrides_type : '',
                            'old_office_overrides_amount' => isset($old_data->office_overrides_amount) ? $old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($old_data->office_overrides_type) ? $old_data->office_overrides_type : '',
                            'old_office_stack_overrides_amount' => isset($old_data->office_stack_overrides_amount) ? $old_data->office_stack_overrides_amount : 0,
                            'old_direct_tiers_id' => isset($old_data->direct_tiers_id) ? $old_data->direct_tiers_id : 0,
                            'old_indirect_tiers_id' => isset($old_data->indirect_tiers_id) ? $old_data->indirect_tiers_id : '',
                            'old_office_tiers_id' => isset($old_data->office_tiers_id) ? $old_data->office_tiers_id : 0,
                        ]
                    );
                } else {
                    UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'product_id' => $request->employee_override['product_id'],
                        ]
                    )->update(
                        [
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            'action_item_status' => 0,
                            'old_direct_overrides_amount' => isset($old_data->direct_overrides_amount) ? $old_data->direct_overrides_amount : 0,
                            'old_direct_overrides_type' => isset($old_data->direct_overrides_type) ? $old_data->direct_overrides_type : '',
                            'old_indirect_overrides_amount' => isset($old_data->indirect_overrides_amount) ? $old_data->indirect_overrides_amount : 0,
                            'old_indirect_overrides_type' => isset($old_data->indirect_overrides_type) ? $old_data->indirect_overrides_type : '',
                            'old_office_overrides_amount' => isset($old_data->office_overrides_amount) ? $old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($old_data->office_overrides_type) ? $old_data->office_overrides_type : '',
                            'old_office_stack_overrides_amount' => isset($old_data->office_stack_overrides_amount) ? $old_data->office_stack_overrides_amount : 0,
                            'old_direct_tiers_id' => isset($old_data->direct_tiers_id) ? $old_data->direct_tiers_id : 0,
                            'old_indirect_tiers_id' => isset($old_data->indirect_tiers_id) ? $old_data->indirect_tiers_id : '',
                            'old_office_tiers_id' => isset($old_data->office_tiers_id) ? $old_data->office_tiers_id : 0,
                        ]
                    );
                    $checkdata = UserOverrideHistory::where(['user_id' => $request->user_id,
                        'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        'product_id' => $request->employee_override['product_id']])->first();
                }
            } elseif (! empty($old_data) && ! empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'product_id' => $request->employee_override['product_id'],
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            'old_direct_overrides_amount' => isset($old_data->direct_overrides_amount) ? $old_data->direct_overrides_amount : 0,
                            'old_direct_overrides_type' => isset($old_data->direct_overrides_type) ? $old_data->direct_overrides_type : '',
                            'old_indirect_overrides_amount' => isset($old_data->indirect_overrides_amount) ? $old_data->indirect_overrides_amount : 0,
                            'old_indirect_overrides_type' => isset($old_data->indirect_overrides_type) ? $old_data->indirect_overrides_type : '',
                            'old_office_overrides_amount' => isset($old_data->office_overrides_amount) ? $old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($old_data->office_overrides_type) ? $old_data->office_overrides_type : '',
                            'old_office_stack_overrides_amount' => isset($old_data->office_stack_overrides_amount) ? $old_data->office_stack_overrides_amount : 0,
                            'old_direct_tiers_id' => isset($old_data->direct_tiers_id) ? $old_data->direct_tiers_id : 0,
                            'old_indirect_tiers_id' => isset($old_data->indirect_tiers_id) ? $old_data->indirect_tiers_id : '',
                            'old_office_tiers_id' => isset($old_data->office_tiers_id) ? $old_data->office_tiers_id : 0,

                        ]
                    );
                } else {
                    UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'product_id' => $request->employee_override['product_id'],
                        ]
                    )->update(
                        [
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'direct_tiers_id' => $request->employee_override['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $request->employee_override['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $request->employee_override['office_tiers_id'] ?? null,
                            'action_item_status' => 0,
                            'old_direct_overrides_amount' => isset($old_data->direct_overrides_amount) ? $old_data->direct_overrides_amount : 0,
                            'old_direct_overrides_type' => isset($old_data->direct_overrides_type) ? $old_data->direct_overrides_type : '',
                            'old_indirect_overrides_amount' => isset($old_data->indirect_overrides_amount) ? $old_data->indirect_overrides_amount : 0,
                            'old_indirect_overrides_type' => isset($old_data->indirect_overrides_type) ? $old_data->indirect_overrides_type : '',
                            'old_office_overrides_amount' => isset($old_data->office_overrides_amount) ? $old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($old_data->office_overrides_type) ? $old_data->office_overrides_type : '',
                            'old_office_stack_overrides_amount' => isset($old_data->office_stack_overrides_amount) ? $old_data->office_stack_overrides_amount : 0,
                        ]
                    );
                    $checkdata = UserOverrideHistory::where(['user_id' => $request->user_id,
                        'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        'product_id' => $request->employee_override['product_id']])->first();
                }
                $next_data->old_direct_overrides_amount = $request->employee_override['direct_overrides_amount'];
                $next_data->old_direct_overrides_type = $request->employee_override['direct_overrides_type'];
                $next_data->old_indirect_overrides_amount = $request->employee_override['indirect_overrides_amount'];
                $next_data->old_indirect_overrides_type = $request->employee_override['indirect_overrides_type'];
                $next_data->old_office_overrides_amount = $request->employee_override['office_overrides_amount'];
                $next_data->old_office_overrides_type = $request->employee_override['office_overrides_type'];
                $next_data->old_office_stack_overrides_amount = $request->employee_override['office_stack_overrides_amount'];
                $next_data->old_direct_tiers_id = $request->employee_override['direct_tiers_id'] ?? '';
                $next_data->old_indirect_tiers_id = $request->employee_override['indirect_tiers_id'] ?? '';
                $next_data->old_office_tiers_id = $request->employee_override['office_tiers_id'] ?? '';

                $next_data->action_item_status = 0;
                $next_data->save();
            }
            $override_lastid = $checkdata->id ?? 0;
            if ($this->companySettingtiers?->status) {
                UserDirectOverrideHistoryTiersRange::where([
                    'user_id' => $request->user_id,
                    'user_override_history_id' => $override_lastid,
                ])->delete();
                $overridepost = $request->employee_override;
                $direct_tiers_id = isset($overridepost['direct_tiers_id']) && $overridepost['direct_tiers_id'] != '' ? $overridepost['direct_tiers_id'] : 0;
                $direct_range = isset($overridepost['direct_tiers']) && $overridepost['direct_tiers'] != '' ? $overridepost['direct_tiers'] : '';
                if ($direct_tiers_id > 0 && $override_lastid > 0) {
                    if (is_array($direct_range) && ! empty($direct_range)) {
                        if (is_array($direct_range) && ! empty($direct_range)) {
                            foreach ($direct_range as $rang) {
                                UserDirectOverrideHistoryTiersRange::create([
                                    'user_override_history_id' => $override_lastid,
                                    'user_id' => $request->user_id,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }

                $indirect_tiers_id = isset($overridepost['indirect_tiers_id']) && $overridepost['indirect_tiers_id'] != '' ? $overridepost['indirect_tiers_id'] : 0;
                $indirect_range = isset($overridepost['indirect_tiers']) && $overridepost['indirect_tiers'] != '' ? $overridepost['indirect_tiers'] : '';
                UserIndirectOverrideHistoryTiersRange::where([
                    'user_id' => $request->user_id,
                    'user_override_history_id' => $override_lastid,
                ])->delete();
                if ($indirect_tiers_id > 0) {
                    if (is_array($indirect_range) && ! empty($indirect_range)) {
                        if (is_array($indirect_range) && ! empty($indirect_range)) {
                            foreach ($indirect_range as $rang) {
                                UserIndirectOverrideHistoryTiersRange::create([
                                    'user_override_history_id' => $override_lastid,
                                    'user_id' => $request->user_id,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }

                $office_tiers_id = isset($overridepost['office_tiers_id']) && $overridepost['office_tiers_id'] != '' ? $overridepost['office_tiers_id'] : 0;
                $office_tiers_range = isset($overridepost['office_tiers']) && $overridepost['office_tiers'] != '' ? $overridepost['office_tiers'] : '';
                UserOfficeOverrideHistoryTiersRange::where([
                    'user_id' => $request->user_id,
                    'user_office_override_history_id' => $override_lastid,
                ])->delete();
                if ($office_tiers_id > 0) {
                    if (is_array($office_tiers_range) && ! empty($office_tiers_range)) {
                        if (is_array($office_tiers_range) && ! empty($office_tiers_range)) {
                            foreach ($office_tiers_range as $rang) {
                                UserOfficeOverrideHistoryTiersRange::create([
                                    'user_office_override_history_id' => $override_lastid,
                                    'user_id' => $request->user_id,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $additionalOverride = UserAdditionalOfficeOverrideHistory::where('user_id', $request->user_id)
                ->where('override_effective_date', '=', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                ->first();
            foreach ($request->additional_office_override as $additional) {

                $additional_old_data = UserAdditionalOfficeOverrideHistory::where('user_id', $request->user_id)->where('office_id', $additional['office_id'])
                    ->where('override_effective_date', '<', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                    ->orderBy('override_effective_date', 'DESC')
                    ->first();
                $additional_next_data = UserAdditionalOfficeOverrideHistory::where('user_id', $request->user_id)->where('office_id', $additional['office_id'])
                    ->where('override_effective_date', '>', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                    ->orderBy('override_effective_date', 'ASC')
                    ->first();

                if (empty($additional_old_data) && empty($additional_next_data)) {
                    if (empty($additionalOverride)) {
                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                                'updater_id' => auth()->user()->id,
                                'state_id' => $additional['state_id'],
                                'office_id' => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => 0,
                                'old_office_overrides_type' => '',
                            ]
                        );
                    } else {
                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'office_id' => $additional['office_id'],
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            ]
                        )->update(
                            [

                                'updater_id' => auth()->user()->id,
                                // 'state_id'  => $additional['state_id'],
                                // 'office_id'  => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => 0,
                                'old_office_overrides_type' => '',

                            ]
                        );
                    }
                } elseif (empty($additional_old_data) && ! empty($additional_next_data)) {
                    if (empty($additionalOverride)) {
                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                                'updater_id' => auth()->user()->id,
                                'state_id' => $additional['state_id'],
                                'office_id' => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => 0,
                                'old_office_overrides_type' => '',
                            ]
                        );
                    } else {
                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'office_id' => $additional['office_id'],
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            ]
                        )->update(
                            [

                                'updater_id' => auth()->user()->id,
                                // 'state_id'  => $additional['state_id'],
                                // 'office_id'  => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => 0,
                                'old_office_overrides_type' => '',

                            ]
                        );
                    }
                    $additional_next_data->old_office_overrides_amount = $additional['overrides_amount'];
                    $additional_next_data->old_office_overrides_type = $additional['overrides_type'];
                    $additional_next_data->save();
                } elseif (! empty($additional_old_data) && empty($additional_next_data)) {
                    if (empty($additionalOverride)) {
                        if (
                            $additional_old_data->user_id != $request->user_id
                            || $additional_old_data->state_id != $additional['state_id']
                            || $additional_old_data->office_id != $additional['office_id']
                            || $additional_old_data->office_overrides_amount != $additional['overrides_amount']
                            || $additional_old_data->office_overrides_type != $additional['overrides_type']
                        ) {
                            $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                                    'updater_id' => auth()->user()->id,
                                    'state_id' => $additional['state_id'],
                                    'office_id' => $additional['office_id'],
                                    'office_overrides_amount' => $additional['overrides_amount'],
                                    'office_overrides_type' => $additional['overrides_type'],
                                    'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                                    'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',
                                ]
                            );
                        }
                    } else {
                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'office_id' => $additional['office_id'],
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            ]
                        )->update(
                            [

                                'updater_id' => auth()->user()->id,
                                // 'state_id'  => $additional['state_id'],
                                // 'office_id'  => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                                'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',

                            ]
                        );
                    }
                } elseif (! empty($additional_old_data) && ! empty($additional_next_data)) {
                    if (empty($additionalOverride)) {
                        if (
                            $additional_old_data->user_id != $request->user_id
                            || $additional_old_data->state_id != $additional['state_id']
                            || $additional_old_data->office_id != $additional['office_id']
                            || $additional_old_data->office_overrides_amount != $additional['overrides_amount']
                            || $additional_old_data->office_overrides_type != $additional['overrides_type']
                        ) {
                            $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                                    'updater_id' => auth()->user()->id,
                                    'state_id' => $additional['state_id'],
                                    'office_id' => $additional['office_id'],
                                    'office_overrides_amount' => $additional['overrides_amount'],
                                    'office_overrides_type' => $additional['overrides_type'],
                                    'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                                    'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',
                                ]
                            );
                        }
                    } else {

                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'office_id' => $additional['office_id'],
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            ]
                        )->update(
                            [

                                'updater_id' => auth()->user()->id,
                                // 'state_id'  => $additional['state_id'],
                                // 'office_id'  => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                                'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',

                            ]
                        );
                    }
                    $additional_next_data->old_office_overrides_amount = $additional['overrides_amount'];
                    $additional_next_data->old_office_overrides_type = $additional['overrides_type'];
                    $additional_next_data->save();
                }
            }

            $UserOverrideHistory = UserOverrideHistory::where('user_id', $request->user_id)
                ->where('override_effective_date', '<=', date('Y-m-d'))
                ->where('product_id', '<=', $request->employee_override['product_id'])
                ->orderBy('override_effective_date', 'DESC')
                ->first();

            if (
                $user->direct_overrides_amount != $request->employee_override['direct_overrides_amount'] || $user->direct_overrides_type != $request->employee_override['direct_overrides_type'] || $user->indirect_overrides_amount != $request->employee_override['indirect_overrides_amount'] || $user->indirect_overrides_type != $request->employee_override['indirect_overrides_type']
                // || $user->office_overrides_amount  != $request->employee_override['office_overrides_amount']
                // || $user->office_overrides_type  != $request->employee_override['office_overrides_type']
                || $user->office_stack_overrides_amount != $request->employee_override['office_stack_overrides_amount']
            ) {
                if (strtotime($request->employee_override['override_effective_date']) == strtotime(date('Y-m-d'))) {
                    $user->direct_overrides_amount = $request->employee_override['direct_overrides_amount'];
                    $user->direct_overrides_type = $request->employee_override['direct_overrides_type'];
                    $user->indirect_overrides_amount = $request->employee_override['indirect_overrides_amount'];
                    $user->indirect_overrides_type = $request->employee_override['indirect_overrides_type'];
                    $user->office_overrides_amount = $request->employee_override['office_overrides_amount'];
                    $user->office_overrides_type = $request->employee_override['office_overrides_type'];
                    $user->office_stack_overrides_amount = $request->employee_override['office_stack_overrides_amount'];
                    $user->override_effective_date = $request->employee_override['override_effective_date'];
                } elseif (strtotime($request->employee_override['override_effective_date']) < strtotime(date('Y-m-d'))) {
                    $user->direct_overrides_amount = isset($UserOverrideHistory->direct_overrides_amount) ? $UserOverrideHistory->direct_overrides_amount : $user->direct_overrides_amount;
                    $user->direct_overrides_type = isset($UserOverrideHistory->direct_overrides_type) ? $UserOverrideHistory->direct_overrides_type : $user->direct_overrides_type;
                    $user->indirect_overrides_amount = isset($UserOverrideHistory->indirect_overrides_amount) ? $UserOverrideHistory->indirect_overrides_amount : $user->indirect_overrides_amount;
                    $user->indirect_overrides_type = isset($UserOverrideHistory->indirect_overrides_type) ? $UserOverrideHistory->indirect_overrides_type : $user->indirect_overrides_type;
                    $user->office_overrides_amount = isset($UserOverrideHistory->office_overrides_amount) ? $UserOverrideHistory->office_overrides_amount : $user->office_overrides_amount;
                    $user->office_overrides_type = isset($UserOverrideHistory->office_overrides_type) ? $UserOverrideHistory->office_overrides_type : $user->office_overrides_type;
                    $user->office_stack_overrides_amount = isset($UserOverrideHistory->office_stack_overrides_amount) ? $UserOverrideHistory->office_stack_overrides_amount : $user->office_stack_overrides_amount;
                    $user->override_effective_date = isset($UserOverrideHistory->override_effective_date) ? $UserOverrideHistory->override_effective_date : $user->override_effective_date;
                }
            }
            $user->save();

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

                    $val = AdditionalLocations::where($condition)->update($update);
                }
            }

            // send mail here
            if (! empty($result)) {

                $salesData = [];
                $salesData = SequiDocsEmailSettings::originization_employment_package_change_notification_email_content($user, $result);
                $salesData['email'] = $user->email;

                if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                    $this->sendEmailNotification($salesData);
                } else {
                    // no default blade based email template here
                }
            }

            $user = [
                'user_id' => $request->user_id,
                'description' => 'Updated Overrides Data by'.auth()->user()->first_name,
                'type' => 'Overrides',
                'is_read' => 0,
            ];
            $notify = event(new UserloginNotification($user));

            // Recalculate
            $userId = $request->user_id;
            $salesPid = SaleMasterProcess::where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId)->pluck('pid');
            $pids = SalesMaster::whereIn('pid', $salesPid)->pluck('pid');
            $pidsFromUserOverrides = UserOverrides::where([
                'user_id' => $userId,
            ])->pluck('pid');
            if ($pidsFromUserOverrides->isNotEmpty()) {
                ProcessRecalculatesOpenSales::dispatch($pidsFromUserOverrides);
            }

            return response()->json([
                'ApiName' => 'user_override',
                'status' => true,
                'message' => 'Saved Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'User not found',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }
    }

    public function hireDateUpdate(Request $request): JsonResponse
    {
        Log::info('hireDateUpdate request V2', $request->all());
        $validator = Validator::make($request->all(), [
            'created_at' => 'required|date_format:Y-m-d',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 200);
        }

        $data = User::where('id', $request->user_id)->first();
        if (! empty($data)) {

            $udata = $this->userDataById($request->user_id);
            $result = [];
            $oldHireDate = $data['created_at'];
            if (! empty($udata) && ! empty($request)) {
                if ($udata['hired_date'] != $request->created_at) {
                    $old_value = (! empty($udata['hired_date'])) ? date('m-d-Y', strtotime($udata['hired_date'])) : '';
                    $new_value = (! empty($request->created_at)) ? date('m-d-Y', strtotime($request->created_at)) : '';

                    $result['hired_date'] = [
                        'old_value' => $old_value,
                        'new_value' => $new_value,
                    ];
                }
            }

            $data->created_at = $request['created_at'].date('H:i:s');
            if ($data->save()) {
                // Sales pull from FieldRoutes when hiring date changes to backdate
                if ($data->wasChanged('created_at') && $request->created_at) {

                    $newHireDate = date('Y-m-d', strtotime($request->created_at));
                    if (! empty($oldHireDate)) {
                        $oldHireDate = date('Y-m-d', strtotime($oldHireDate));
                    } else {
                        $oldHireDate = date('Y-m-d');
                    }
                    if ($newHireDate < $oldHireDate) {
                        $integration = Integration::where(['name' => 'FieldRoutes', 'status' => 1])->first();
                        if ($integration) {
                            $this->pullSalesFromFieldRoutes($request->user_id, $newHireDate, $oldHireDate);
                        }
                    }

                }

                $CrmData = Crms::where('id', 3)->where('status', 1)->first();
                if ($CrmData) {
                    $this->update_hireDate($request);   // update hire date everee
                }
            }

            // send mail here
            if (! empty($result)) {

                $salesData = [];
                $salesData = SequiDocsEmailSettings::originization_employment_package_change_notification_email_content($data, $result);
                $salesData['email'] = $data->email;

                if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                    $this->sendEmailNotification($salesData);
                } else {
                    // no default blade based email template here
                }
            }

            return response()->json([
                'ApiName' => 'update Hired date for user',
                'status' => true,
                'message' => 'Updated Successfully.',
                // 'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'update Hired date for user',
                'status' => false,
                'message' => 'Successfully.',

            ], 200);
        }
    }

    public function userWagesUpdate(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $user = User::find($request->user_id);
        $uid = auth()->user()->id;

        if (! empty($user)) {
            $today = date('Y-m-d');

            if (! empty($request->employee_wages)) {
                $employee_wages = $request->employee_wages;

                $wages = UserWagesHistory::where('user_id', $request->user_id)
                    ->where('effective_date', '=', date('Y-m-d', strtotime($employee_wages['effective_date'])))
                    ->first();
                $prev_wages = UserWagesHistory::where('user_id', $request->user_id)
                    ->where('effective_date', '<', date('Y-m-d', strtotime($employee_wages['effective_date'])))
                    ->orderBy('effective_date', 'DESC')
                    ->first();
                $next_wages = UserWagesHistory::where('user_id', $request->user_id)
                    ->where('effective_date', '>', date('Y-m-d', strtotime($employee_wages['effective_date'])))
                    ->orderBy('effective_date', 'ASC')
                    ->first();

                if (empty($prev_wages) && empty($next_wages)) {
                    if (empty($wages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                // 'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                            ]
                        )->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'old_pay_type' => isset($wages->old_pay_type) ? $wages->old_pay_type : null,
                                'old_pay_rate' => isset($wages->old_pay_rate) ? $wages->old_pay_rate : null,
                                'old_pay_rate_type' => isset($wages->old_pay_rate_type) ? $wages->old_pay_rate_type : null,
                                'old_pto_hours' => isset($wages->old_pto_hours) ? $wages->old_pto_hours : null,
                                'old_unused_pto_expires' => isset($wages->old_unused_pto_expires) ? $wages->old_unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($wages->old_expected_weekly_hours) ? $wages->old_expected_weekly_hours : null,
                                'old_overtime_rate' => isset($wages->old_overtime_rate) ? $wages->old_overtime_rate : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),

                            ]
                        );
                    }
                } elseif (! empty($prev_wages) && empty($next_wages)) {
                    if (empty($wages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                            ]
                        )->update(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    }
                } elseif (! empty($prev_wages) && ! empty($next_wages)) {
                    if (empty($wages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                            ]
                        )->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    }
                    // $next_wages->old_pay_rate = $employee_wages['pay_rate'];
                    // $next_wages->save();
                } elseif (empty($prev_wages) && ! empty($next_wages)) {
                    if (empty($wages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                            ]
                        )->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    }
                    // $next_wages->pay_rate = $employee_wages['pay_rate'];
                    // $next_wages->save();
                }

                $ptowages = UserWagesHistory::where('user_id', $request->user_id)
                    ->where('pto_hours_effective_date', '=', date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])))
                    ->first();
                $prev_ptowages = UserWagesHistory::where('user_id', $request->user_id)
                    ->where('pto_hours_effective_date', '<', date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])))
                    ->orderBy('pto_hours_effective_date', 'DESC')
                    ->first();
                $next_ptowages = UserWagesHistory::where('user_id', $request->user_id)
                    ->where('pto_hours_effective_date', '>', date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])))
                    ->orderBy('pto_hours_effective_date', 'ASC')
                    ->first();

                if (empty($prev_ptowages) && empty($next_ptowages)) {
                    if (empty($ptowages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                // 'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        )->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'old_pay_type' => isset($wages->old_pay_type) ? $wages->old_pay_type : null,
                                'old_pay_rate' => isset($wages->old_pay_rate) ? $wages->old_pay_rate : null,
                                'old_pay_rate_type' => isset($wages->old_pay_rate_type) ? $wages->old_pay_rate_type : null,
                                'old_pto_hours' => isset($wages->old_pto_hours) ? $wages->old_pto_hours : null,
                                'old_unused_pto_expires' => isset($wages->old_unused_pto_expires) ? $wages->old_unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($wages->old_expected_weekly_hours) ? $wages->old_expected_weekly_hours : null,
                                'old_overtime_rate' => isset($wages->old_overtime_rate) ? $wages->old_overtime_rate : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),

                            ]
                        );
                    }
                } elseif (! empty($prev_ptowages) && empty($next_ptowages)) {
                    if (empty($ptowages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        )->update(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                            ]
                        );
                    }
                } elseif (! empty($prev_ptowages) && ! empty($next_ptowages)) {
                    if (empty($ptowages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        )->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'old_pay_type' => isset($prev_wages->pay_type) ? $prev_wages->pay_type : null,
                                'old_pay_rate' => isset($prev_wages->pay_rate) ? $prev_wages->pay_rate : null,
                                'old_pay_rate_type' => isset($prev_wages->pay_rate_type) ? $prev_wages->pay_rate_type : null,
                                'old_pto_hours' => isset($prev_wages->pto_hours) ? $prev_wages->pto_hours : null,
                                'old_unused_pto_expires' => isset($prev_wages->unused_pto_expires) ? $prev_wages->unused_pto_expires : null,
                                'old_expected_weekly_hours' => isset($prev_wages->expected_weekly_hours) ? $prev_wages->expected_weekly_hours : null,
                                'old_overtime_rate' => isset($prev_wages->overtime_rate) ? $prev_wages->overtime_rate : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                            ]
                        );
                    }
                    // $next_wages->old_pay_rate = $employee_wages['pay_rate'];
                    // $next_wages->save();
                } elseif (empty($prev_ptowages) && ! empty($next_ptowages)) {
                    if (empty($ptowages)) {
                        UserWagesHistory::Create(
                            [
                                'user_id' => $request->user_id,
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        );
                    } else {
                        UserWagesHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'pto_hours_effective_date' => date('Y-m-d', strtotime($employee_wages['pto_hours_effective_date'])),
                            ]
                        )->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'pay_type' => $employee_wages['pay_type'],
                                'pay_rate' => $employee_wages['pay_rate'],
                                'pay_rate_type' => isset($employee_wages['pay_rate_type']) ? $employee_wages['pay_rate_type'] : null,
                                'expected_weekly_hours' => $employee_wages['expected_weekly_hours'],
                                'overtime_rate' => $employee_wages['overtime_rate'],
                                'pto_hours' => isset($employee_wages['pto_hours']) ? $employee_wages['pto_hours'] : null,
                                'unused_pto_expires' => isset($employee_wages['unused_pto_expires']) ? $employee_wages['unused_pto_expires'] : null,
                                'effective_date' => date('Y-m-d', strtotime($employee_wages['effective_date'])),
                            ]
                        );
                    }
                    // $next_wages->pay_rate = $employee_wages['pay_rate'];
                    // $next_wages->save();
                }
            }

            // send mail here
            // if (!empty($result)) {
            //     $check = User::where('id',$request->user_id)->first();
            //     $salesData =[];
            //     $salesData = SequiDocsEmailSettings::originization_employment_package_change_notification_email_content($check,$result);
            //     $salesData['email'] = $check->email;

            //     if ($salesData['is_active'] == 1 &&  $salesData['template'] != '') {
            //         $this->sendEmailNotification($salesData);
            //     }
            // }

            $description = 'Updated Wages Data by '.auth()->user()->first_name;
            $type = 'Wages';
            $user = [
                'user_id' => $request['user_id'],
                'description' => $description,
                'type' => $type,
                'is_read' => 0,
            ];

            $notify = event(new UserloginNotification($user));

            // Artisan::call('generate:alert');

            return response()->json([
                'ApiName' => 'user_wages',
                'status' => true,
                'message' => 'Saved Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'No User found',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }
    }

    public function userAgreemnetUpdate(Request $request): JsonResponse
    {
        $user = User::find($request->user_id);
        $uid = auth()->user()->id;

        if ($user && ! empty($request->employee_agreement)) {
            $employee_agreement = $request->employee_agreement;

            $agreement = UserAgreementHistory::where('user_id', $request->user_id)->latest()->first();
            $fieldsToCheck = [
                'probation_period',
                'offer_include_bonus',
                'hiring_bonus_amount',
                'date_to_be_paid',
                'period_of_agreement',
                'end_date',
                'offer_expiry_date',
                'hired_by_uid',
                'hiring_signature',
            ];

            $changedFields = [];
            foreach ($fieldsToCheck as $field) {
                if (isset($employee_agreement[$field]) && $agreement && $employee_agreement[$field] != $agreement->$field) {
                    $changedFields[$field] = [
                        'field' => $field,
                        'old_value' => $agreement->$field,
                        'new_value' => $employee_agreement[$field],
                    ];
                }
            }

            if (! empty($changedFields)) {
                $fielddata = [];
                foreach ($changedFields as $key => $changedField) {
                    $fielddata['old_'.$key] = $changedField['old_value'];
                    if ($key !== 'hired_by_uid' || $key !== 'hiring_signature') {
                        $key = $key == 'period_of_agreement' ? 'period_of_agreement_start_date' : $key;
                        $user->$key = $changedField['new_value'];
                    }
                }
                $user->save();
                $pdata = array_merge([
                    'user_id' => $request->user_id,
                    'updater_id' => $uid,
                    'probation_period' => $employee_agreement['probation_period'] ?? '',
                    'offer_include_bonus' => $employee_agreement['offer_include_bonus'] ?? '',
                    'hiring_bonus_amount' => $employee_agreement['hiring_bonus_amount'] ?? '',
                    'date_to_be_paid' => $employee_agreement['date_to_be_paid'] ?? '',
                    'period_of_agreement' => $employee_agreement['period_of_agreement'] ?? '',
                    'end_date' => $employee_agreement['end_date'] ?? '',
                    'offer_expiry_date' => $employee_agreement['offer_expiry_date'] ?? '',
                    'hired_by_uid' => $employee_agreement['hired_by_uid'] ?? '',
                    'hiring_signature' => $employee_agreement['hiring_signature'] ?? '',
                ], $fielddata);
                UserAgreementHistory::create($pdata);
            } else {
                UserAgreementHistory::create([
                    'user_id' => $request->user_id,
                    'updater_id' => $uid,
                    'probation_period' => $employee_agreement['probation_period'] ?? '',
                    'offer_include_bonus' => $employee_agreement['offer_include_bonus'] ?? '',
                    'hiring_bonus_amount' => $employee_agreement['hiring_bonus_amount'] ?? '',
                    'date_to_be_paid' => $employee_agreement['date_to_be_paid'] ?? '',
                    'period_of_agreement' => $employee_agreement['period_of_agreement'] ?? '',
                    'end_date' => $employee_agreement['end_date'] ?? '',
                    'offer_expiry_date' => $employee_agreement['offer_expiry_date'] ?? '',
                    'hired_by_uid' => $employee_agreement['hired_by_uid'] ?? '',
                    'hiring_signature' => $employee_agreement['hiring_signature'] ?? '',
                ]);
            }

            return response()->json([
                'ApiName' => 'user_agreement',
                'status' => true,
                'message' => 'Saved Successfully.',
            ], 200);
        }

        return response()->json([
            'ApiName' => 'user_agreement',
            'status' => false,
            'message' => 'Bad Request',
        ], 400);
    }

    public function userCompensation(Request $request) // this code is use from demo branch
    {
        $product_id = $request->product_id;
        $core_position_id = $request->core_position_id != '' ? $request->core_position_id : null;
        $user_id = $request->user_id;
        $user = User::find($user_id);
        if (! empty($user->aveyo_hs_id)) {
            $aveyoid = $user->aveyo_hs_id;
        }
        $position_id = $user->position_id;
        $sub_position_id = $user->sub_position_id;
        $uid = auth()->user()->id;
        if (! empty($user)) {
            $res = $request->data;
            if (! empty($res['commission'])) {
                $ec = $res['commission'];

                // Custom Sales Field support: Parse custom_field_X format for commission_type
                $commissionParsed = $this->parseCustomFieldType(
                    $ec['commission_type'] ?? null,
                    $ec['custom_sales_field_id'] ?? null
                );

                $commission = UserCommissionHistory::where('user_id', $request->user_id)
                    ->where('commission_effective_date', date('Y-m-d', strtotime($ec['commission_effective_date'])))
                    ->where('position_id', $position_id)
                    ->where('core_position_id', $core_position_id)
                    ->where('product_id', $product_id)
                    ->first();
                $prev_commission = UserCommissionHistory::where('user_id', $request->user_id)
                    ->where('commission_effective_date', '<', date('Y-m-d', strtotime($ec['commission_effective_date'])))
                    ->where('position_id', $position_id)
                    ->where('core_position_id', $core_position_id)
                    ->where('product_id', $product_id)
                    ->first();
                $next_commission = UserCommissionHistory::where('user_id', $request->user_id)
                    ->where('commission_effective_date', '>', date('Y-m-d', strtotime($ec['commission_effective_date'])))
                    ->where('position_id', $position_id)
                    ->where('core_position_id', $core_position_id)
                    ->where('product_id', $product_id)
                    ->first();
                $data = [
                    'user_id' => $request->user_id,
                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                    'position_id' => $position_id,
                    'core_position_id' => $core_position_id,
                    'product_id' => $product_id,
                    'sub_position_id' => $position_id,
                    'updater_id' => auth()->user()->id,
                    'commission' => $ec['commission'],
                    'commission_type' => $commissionParsed['type'],
                    'tiers_id' => $ec['tiers_id'],
                    // Custom Sales Field support
                    'custom_sales_field_id' => $commissionParsed['custom_sales_field_id'],
                ];
                $lastId = null;
                if (empty($prev_commission) && empty($next_commission)) {
                    if (empty($commission)) {
                        $created = UserCommissionHistory::create($data);
                        $lastId = $created->id; // Get the last created ID
                    } else {
                        $data['old_commission'] = $commission->old_commission ?? 0;
                        $commission->update($data);
                        $lastId = $commission->id; // Get the updated record ID
                    }
                } elseif (! empty($prev_commission) && empty($next_commission)) {
                    $data['old_commission'] = $prev_commission->commission ?? 0;
                    $data['old_commission_type'] = $prev_commission->commission_type ?? '';
                    $data['old_custom_sales_field_id'] = $prev_commission->custom_sales_field_id ?? null;
                    if (empty($commission)) {
                        $created = UserCommissionHistory::create($data);
                        $lastId = $created->id;
                    } else {
                        $commission->update($data);
                        $lastId = $commission->id;
                    }
                } elseif (! empty($prev_commission) && ! empty($next_commission)) {
                    $data['old_commission'] = $prev_commission->commission ?? 0;
                    $data['old_commission_type'] = $prev_commission->commission_type ?? '';
                    $data['old_custom_sales_field_id'] = $prev_commission->custom_sales_field_id ?? null;
                    if (empty($commission)) {
                        $created = UserCommissionHistory::create($data);
                        $lastId = $created->id;
                    } else {
                        $commission->update($data);
                        $lastId = $commission->id;
                    }
                    $next_commission->update(['old_commission' => $ec['commission'], 'old_custom_sales_field_id' => $commissionParsed['custom_sales_field_id']]);
                } elseif (empty($prev_commission) && ! empty($next_commission)) {
                    if (empty($commission)) {
                        $created = UserCommissionHistory::create($data);
                        $lastId = $created->id;
                    } else {
                        $commission->update($data);
                        $lastId = $commission->id;
                    }
                    $data['old_commission_type'] = $prev_commission->commission_type ?? '';
                    $next_commission->update(['old_commission' => $ec['commission'], 'old_custom_sales_field_id' => $commissionParsed['custom_sales_field_id']]);
                }

                if ($this->companySettingtiers?->status) {
                    $tiers_id = isset($ec['tiers_id']) && $ec['tiers_id'] != '' ? $ec['tiers_id'] : 0;
                    $range = isset($ec['tiers_range']) && $ec['tiers_range'] != '' ? $ec['tiers_range'] : '';
                    UserCommissionHistoryTiersRange::where([
                        'user_id' => $user_id,
                        'user_commission_history_id' => $lastId,
                    ])->delete();
                    if ($tiers_id > 0 && $lastId != null) {
                        if (is_array($range) && ! empty($range)) {
                            foreach ($range as $rang) {
                                UserCommissionHistoryTiersRange::create([
                                    'user_commission_history_id' => $lastId,
                                    'user_id' => $user_id,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }
            if (isset($res['upfront'])) {
                $ec = $res['upfront'];
                $milestone_id = $ec['milestone_id'] ?? '';
                if (! empty($ec['schemas']) && $milestone_id != '') {
                    foreach ($ec['schemas'] as $key => $upfronts) {
                        // Custom Sales Field support: Parse custom_field_X format for upfront_sale_type
                        $upfrontParsed = $this->parseCustomFieldType(
                            $upfronts['upfront_sale_type'] ?? null,
                            $upfronts['custom_sales_field_id'] ?? null
                        );
                        $upfronts['upfront_sale_type'] = $upfrontParsed['type'];
                        $upfronts['custom_sales_field_id'] = $upfrontParsed['custom_sales_field_id'];

                        $upfront = UserUpfrontHistory::where('user_id', $request->user_id)
                            ->where('position_id', $position_id)
                            ->where('core_position_id', $core_position_id)
                            ->where('product_id', $product_id)
                            ->where('milestone_schema_id', $milestone_id)
                            ->where('milestone_schema_trigger_id', $upfronts['milestone_schema_trigger_id'])
                            ->where('upfront_effective_date', '=', date('Y-m-d', strtotime($upfronts['upfront_effective_date'])))
                            ->first();
                        $upfront_prev = UserUpfrontHistory::where('user_id', $request->user_id)
                            ->where('position_id', $position_id)
                            ->where('core_position_id', $core_position_id)
                            ->where('product_id', $product_id)
                            ->where('milestone_schema_id', $milestone_id)
                            ->where('milestone_schema_trigger_id', $upfronts['milestone_schema_trigger_id'])
                            ->where('upfront_effective_date', '<', date('Y-m-d', strtotime($upfronts['upfront_effective_date'])))
                            ->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfront_next = UserUpfrontHistory::where('user_id', $request->user_id)
                            ->where('position_id', $position_id)
                            ->where('core_position_id', $core_position_id)
                            ->where('product_id', $product_id)
                            ->where('milestone_schema_id', $milestone_id)
                            ->where('milestone_schema_trigger_id', $upfronts['milestone_schema_trigger_id'])
                            ->where('upfront_effective_date', '>', date('Y-m-d', strtotime($upfronts['upfront_effective_date'])))->orderBy('upfront_effective_date', 'ASC')->first();
                        $record_data = [
                            'user_id' => $request->user_id,
                            'upfront_effective_date' => date('Y-m-d', strtotime($upfronts['upfront_effective_date'])),
                            'position_id' => $position_id,
                            'core_position_id' => $core_position_id,
                            'product_id' => $product_id,
                            'sub_position_id' => $sub_position_id,
                            'updater_id' => auth()->user()->id,
                            'milestone_schema_id' => $milestone_id,
                            'milestone_schema_trigger_id' => $upfronts['milestone_schema_trigger_id'],
                            'upfront_pay_amount' => $upfronts['upfront_pay_amount'],
                            'upfront_sale_type' => $upfronts['upfront_sale_type'],
                            'tiers_id' => $upfronts['tiers_id'] ?? '',
                            // Custom Sales Field support
                            'custom_sales_field_id' => $upfronts['custom_sales_field_id'] ?? null,
                        ];
                        if (empty($upfront_prev) && empty($upfront_next)) {
                            if (empty($upfront)) {
                                $record_data += [
                                    'old_upfront_pay_amount' => 0,
                                    'old_upfront_sale_type' => '',
                                    'old_tiers_id' => '',
                                ];
                                $checkdata = UserUpfrontHistory::create($record_data);
                            } else {
                                $checkdata = UserUpfrontHistory::where(['user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id])->first();
                                UserUpfrontHistory::where([
                                    'user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'upfront_pay_amount' => $upfronts['upfront_pay_amount'],
                                    'upfront_sale_type' => $upfronts['upfront_sale_type'],
                                    'tiers_id' => $upfronts['tiers_id'],
                                    'old_upfront_pay_amount' => $upfront->old_upfront_pay_amount ?? 0,
                                    'old_upfront_sale_type' => $upfront->old_upfront_sale_type ?? '',
                                    'old_tiers_id' => $upfront->old_tiers_id ?? '',
                                ]);
                            }
                        } elseif (empty($upfront_prev) && ! empty($upfront_next)) {
                            if (empty($upfront)) {
                                $record_data += [
                                    'old_upfront_pay_amount' => 0,
                                    'old_upfront_sale_type' => '',
                                ];
                                $checkdata = UserUpfrontHistory::create($record_data);
                            } else {
                                $checkdata = UserUpfrontHistory::where(['user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id])->first();
                                UserUpfrontHistory::where([
                                    'user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'upfront_pay_amount' => $upfronts['upfront_pay_amount'],
                                    'upfront_sale_type' => $upfronts['upfront_sale_type'],
                                    'tiers_id' => $upfronts['tiers_id'],
                                    'old_upfront_pay_amount' => $upfront->old_upfront_pay_amount ?? 0,
                                    'old_upfront_sale_type' => $upfront->old_upfront_sale_type ?? '',
                                    'old_tiers_id' => $upfront->old_tiers_id ?? '',
                                ]);
                            }
                            $upfront_next->old_upfront_pay_amount = $upfronts['upfront_pay_amount'];
                            $upfront_next->old_upfront_sale_type = $upfronts['upfront_sale_type'];
                            $upfront_next->old_tiers_id = $upfronts['tiers_id'] ?? '';
                            $upfront_next->save();
                        } elseif (! empty($upfront_prev) && empty($upfront_next)) {
                            if (empty($upfront)) {
                                $record_data += [
                                    'old_upfront_pay_amount' => $upfront_prev->upfront_pay_amount ?? 0,
                                    'old_upfront_sale_type' => $upfront_prev->upfront_sale_type ?? '',
                                ];
                                $checkdata = UserUpfrontHistory::create($record_data);
                            } else {
                                $checkdata = UserUpfrontHistory::where(['user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id])->first();
                                UserUpfrontHistory::where([
                                    'user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'upfront_pay_amount' => $upfronts['upfront_pay_amount'],
                                    'upfront_sale_type' => $upfronts['upfront_sale_type'],
                                    'tiers_id' => $upfronts['tiers_id'],
                                    'old_upfront_pay_amount' => $upfront_prev->upfront_pay_amount ?? 0,
                                    'old_upfront_sale_type' => $upfront_prev->upfront_sale_type ?? '',
                                    'old_tiers_id' => $upfront_prev->tiers_id ?? '',
                                ]);
                            }
                        } elseif (! empty($upfront_prev) && ! empty($upfront_next)) {
                            if (empty($upfront)) {
                                $record_data += [
                                    'old_upfront_pay_amount' => $upfront_prev->upfront_pay_amount ?? 0,
                                    'old_upfront_sale_type' => $upfront_prev->upfront_sale_type ?? '',
                                ];
                                $checkdata = UserUpfrontHistory::create($record_data);
                            } else {
                                $checkdata = UserUpfrontHistory::where(['user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id])->first();
                                UserUpfrontHistory::where([
                                    'user_id' => $request->user_id,
                                    'upfront_effective_date' => $record_data['upfront_effective_date'],
                                    'position_id' => $record_data['position_id'],
                                    'milestone_schema_id' => $record_data['milestone_schema_id'],
                                    'milestone_schema_trigger_id' => $record_data['milestone_schema_trigger_id'],
                                    'core_position_id' => $core_position_id,
                                    'product_id' => $product_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'upfront_pay_amount' => $upfronts['upfront_pay_amount'],
                                    'upfront_sale_type' => $upfronts['upfront_sale_type'],
                                    'tiers_id' => $upfronts['tiers_id'],
                                    'old_upfront_pay_amount' => $upfront_prev->upfront_pay_amount ?? 0,
                                    'old_upfront_sale_type' => $upfront_prev->upfront_sale_type ?? '',
                                    'old_tiers_id' => $upfront_prev->tiers_id ?? '',
                                ]);
                            }
                            $upfront_next->old_upfront_pay_amount = $upfronts['upfront_pay_amount'];
                            $upfront_next->old_upfront_sale_type = $upfronts['upfront_sale_type'];
                            $upfront_next->old_tiers_id = $upfronts['tiers_id'] ?? '';
                            $upfront_next->save();
                        }
                        // Tiers
                        $upfront_lastid = $checkdata->id ?? null;
                        if ($this->companySettingtiers?->status) {
                            $tiers_id = isset($upfronts['tiers_id']) && $upfronts['tiers_id'] != '' ? $upfronts['tiers_id'] : 0;
                            $range = isset($upfronts['tiers_range']) && $upfronts['tiers_range'] != '' ? $upfronts['tiers_range'] : '';
                            if ($tiers_id > 0 && $upfront_lastid != null) {
                                if (is_array($range) && ! empty($range)) {
                                    UserUpfrontHistoryTiersRange::where([
                                        'user_id' => $user_id,
                                        'user_upfront_history_id' => $upfront_lastid,
                                    ])->delete();
                                    if (is_array($range) && ! empty($range)) {
                                        foreach ($range as $rang) {
                                            UserUpfrontHistoryTiersRange::create([
                                                'user_id' => $userId,
                                                'user_upfront_history_id' => $upfront_lastid ?? null,
                                                'tiers_schema_id' => $range['tiers_schema_id'] ?? null,
                                                'tiers_levels_id' => $rang['id'] ?? null,
                                                'value' => $range['value'] ?? null,
                                                'value_type' => $upfronts['upfront_sale_type'] ?? null,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if (isset($res['withheld'])) {
                $ec = $res['withheld'];
                $withheld = UserWithheldHistory::where('user_id', $request->user_id)
                    ->where('withheld_effective_date', '=', date('Y-m-d', strtotime($ec['withheld_effective_date'])))
                    ->where('position_id', $position_id)
                    ->where('product_id', $product_id)
                    ->first();
                $withheld_prev = UserWithheldHistory::where('user_id', $request->user_id)
                    ->where('withheld_effective_date', '<', date('Y-m-d', strtotime($ec['withheld_effective_date'])))
                    ->where('position_id', $position_id)
                    ->where('product_id', $product_id)
                    ->orderBy('withheld_effective_date', 'DESC')
                    ->first();

                $withheld_next = UserWithheldHistory::where('user_id', $request->user_id)
                    ->where('withheld_effective_date', '>', date('Y-m-d', strtotime($ec['withheld_effective_date'])))
                    ->where('position_id', $position_id)
                    ->where('product_id', $product_id)
                    ->orderBy('withheld_effective_date', 'ASC')
                    ->first();

                $record_data = [
                    'user_id' => $request->user_id,
                    'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                    'position_id' => $position_id,
                    'product_id' => $product_id,
                    'sub_position_id' => $position_id,
                    'updater_id' => auth()->user()->id,
                    'withheld_amount' => $ec['withheld_amount'],
                    'withheld_type' => $ec['withheld_type'],
                ];
                if (empty($withheld_prev) && empty($withheld_next)) {
                    if (empty($withheld)) {
                        $record_data += [
                            'old_withheld_amount' => 0,
                            'old_withheld_type' => '',
                        ];
                        $checkdata = UserWithheldHistory::create($record_data);
                    } else {
                        $checkdata = UserWithheldHistory::where([
                            'user_id' => $request->user_id,
                            'withheld_effective_date' => $record_data['withheld_effective_date'],
                            'position_id' => $record_data['position_id'],
                            'product_id' => $product_id,
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'withheld_amount' => $ec['withheld_amount'],
                            'withheld_type' => $ec['withheld_type'],
                            'old_withheld_amount' => $withheld->old_withheld_amount ?? 0,
                            'old_withheld_type' => $withheld->old_withheld_type ?? '',
                        ]);
                    }
                } elseif (empty($withheld_prev) && ! empty($withheld_next)) {
                    if (empty($withheld)) {
                        $record_data += [
                            'old_withheld_amount' => 0,
                            'old_withheld_type' => '',
                        ];
                        $checkdata = UserWithheldHistory::create($record_data);
                    } else {
                        $checkdata = UserWithheldHistory::where([
                            'user_id' => $request->user_id,
                            'withheld_effective_date' => $record_data['withheld_effective_date'],
                            'position_id' => $record_data['position_id'],
                            'product_id' => $product_id,
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'withheld_amount' => $ec['withheld_amount'],
                            'withheld_type' => $ec['withheld_type'],
                            'old_withheld_amount' => 0,
                            'old_withheld_type' => '',
                        ]);
                    }
                    $withheld_next->old_withheld_amount = $ec['withheld_amount'];
                    $withheld_next->old_withheld_type = $ec['withheld_type'];
                    $withheld_next->save();
                } elseif (! empty($withheld_prev) && empty($withheld_next)) {
                    if (empty($withheld)) {
                        $record_data += [
                            'old_self_gen_user' => $withheld_prev->self_gen_user ?? 0,
                            'old_withheld_amount' => $withheld_prev->withheld_amount ?? 0,
                            'old_withheld_type' => $withheld_prev->withheld_type ?? '',
                        ];
                        $checkdata = UserWithheldHistory::create($record_data);
                    } else {
                        $checkdata = UserWithheldHistory::where([
                            'user_id' => $request->user_id,
                            'withheld_effective_date' => $record_data['withheld_effective_date'],
                            'position_id' => $record_data['position_id'],
                            'product_id' => $product_id,
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'withheld_amount' => $ec['withheld_amount'],
                            'withheld_type' => $ec['withheld_type'],
                            'old_self_gen_user' => $withheld_prev->self_gen_user ?? 0,
                            'old_withheld_amount' => $withheld_prev->withheld_amount ?? 0,
                            'old_withheld_type' => $withheld_prev->withheld_type ?? '',
                        ]);
                    }
                } elseif (! empty($withheld_prev) && ! empty($withheld_next)) {
                    if (empty($withheld)) {
                        $record_data += [
                            'old_self_gen_user' => $withheld_prev->self_gen_user ?? 0,
                            'old_withheld_amount' => $withheld_prev->withheld_amount ?? 0,
                            'old_withheld_type' => $withheld_prev->withheld_type ?? '',
                        ];
                        $checkdata = UserWithheldHistory::create($record_data);
                    } else {
                        $checkdata = UserWithheldHistory::where([
                            'user_id' => $request->user_id,
                            'withheld_effective_date' => $record_data['withheld_effective_date'],
                            'position_id' => $record_data['position_id'],
                            'product_id' => $product_id,
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'withheld_amount' => $ec['withheld_amount'],
                            'withheld_type' => $ec['withheld_type'],
                            'old_self_gen_user' => $withheld_prev->self_gen_user ?? 0,
                            'old_withheld_amount' => $withheld_prev->withheld_amount ?? 0,
                            'old_withheld_type' => $withheld_prev->withheld_type ?? '',
                        ]);
                    }
                    $withheld_next->old_withheld_amount = $ec['withheld_amount'];
                    $withheld_next->old_withheld_type = $ec['withheld_type'];
                    $withheld_next->save();
                }
            }
            if (isset($res['redline'])) {
                $this->addupdateredline($request, $position_id);
            }

            return response()->json([
                'ApiName' => 'user_compensation',
                'status' => true,
                'message' => 'Saved Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'No User found',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }
    }

    public function addupdateredline($request, $position_id)
    {

        $res = $request->data;
        $core_position_id = $request->core_position_id != '' ? $request->core_position_id : null;
        $ec = $res['redline'];
        // print_r($ec);die();
        $redline = UserRedlines::where('user_id', $request->user_id)
            ->where('start_date', date('Y-m-d', strtotime($ec['redline_effective_date'])))
            ->where('position_type', $position_id)
            ->where('core_position_id', $core_position_id)
            ->first();
        $prev_redline = UserRedlines::where('user_id', $request->user_id)
            ->where('start_date', '<', date('Y-m-d', strtotime($ec['redline_effective_date'])))
            ->where('position_type', $position_id)
            ->where('core_position_id', $core_position_id)
            ->orderBy('start_date', 'DESC')
            ->first();

        $next_redline = UserRedlines::where('user_id', $request->user_id)
            ->where('start_date', '>', date('Y-m-d', strtotime($ec['redline_effective_date'])))
            ->where('position_type', $position_id)
            ->where('core_position_id', $core_position_id)
            ->orderBy('start_date', 'ASC')
            ->first();
        $record_data = [
            'user_id' => $request->user_id,
            'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
            'position_type' => $position_id,
            'core_position_id' => $core_position_id,
            // 'sub_position_type' => $ec['sub_position_type'],
            'updater_id' => auth()->user()->id,
            'redline_amount_type' => $ec['redline_amount_type'] ?? '',
            'redline_type' => $ec['redline_type'] ?? 'per watt',
            'redline' => $ec['redline'] ?? '',
        ];

        if (isset($request->self_gen_user)) {
            $record_data += [
                'self_gen_user' => $request->self_gen_user,
            ];
        }

        if (empty($prev_redline) && empty($next_redline) && ! empty($ec['redline_effective_date'])) {
            if (empty($redline)) {
                $record_data += [
                    'old_redline' => 0,
                    'old_redline_amount_type' => '',
                    'old_redline_type' => '',
                ];
                $checkdata = UserRedlines::create($record_data);
            } else {
                $checkdata = UserRedlines::where([
                    'user_id' => $request->user_id,
                    'start_date' => $record_data['start_date'],
                    'position_type' => $record_data['position_type'],
                    'core_position_id' => $core_position_id,
                ])->update([
                    'updater_id' => auth()->user()->id,
                    'redline_amount_type' => $ec['redline_amount_type'] ?? '',
                    'redline' => $ec['redline'],
                    'redline_type' => $ec['redline_type'] ?? 'per watt',
                    'old_redline' => $redline->old_redline ?? 0,
                    'old_redline_amount_type' => $redline->old_redline_amount_type ?? '',
                    'old_redline_type' => $redline->old_redline_type ?? '',
                ]);
            }
        } elseif (empty($prev_redline) && ! empty($next_redline) && ! empty($ec['redline_effective_date'])) {
            if (empty($redline)) {
                $record_data += [
                    'old_redline' => 0,
                    'old_redline_amount_type' => '',
                    'old_redline_type' => '',
                ];
                $checkdata = UserRedlines::create($record_data);
            } else {
                $checkdata = UserRedlines::where([
                    'user_id' => $request->user_id,
                    'start_date' => $record_data['start_date'],
                    'position_type' => $record_data['position_type'],
                    'core_position_id' => $core_position_id,
                ])->update([
                    'updater_id' => auth()->user()->id,
                    'redline_amount_type' => $ec['redline_amount_type'] ?? '',
                    'redline' => $ec['redline'],
                    'old_redline' => 0,
                    'old_redline_amount_type' => '',
                    'old_redline_type' => '',
                ]);
            }
            $next_redline->old_redline = $ec['redline'];
            $next_redline->old_redline_type = $ec['redline_type'] ?? 'per watt';
            $next_redline->old_redline_amount_type = $ec['redline_amount_type'] ?? '';
            $next_redline->save();
        } elseif (! empty($prev_redline) && empty($next_redline) && ! empty($ec['redline_effective_date'])) {
            if (empty($redline)) {
                $record_data += [
                    'old_redline' => $prev_redline->redline ?? 0,
                    'old_redline_amount_type' => $prev_redline->redline_amount_type ?? '',
                    'old_redline_type' => $prev_redline->redline_type ?? '',
                ];
                $checkdata = UserRedlines::create($record_data);
            } else {
                $checkdata = UserRedlines::where([
                    'user_id' => $request->user_id,
                    'start_date' => $record_data['start_date'],
                    'position_type' => $record_data['position_type'],
                    'core_position_id' => $core_position_id,
                ])->update([
                    'updater_id' => auth()->user()->id,
                    'redline_amount_type' => $ec['redline_amount_type'] ?? '',
                    'redline' => $ec['redline'],
                    'redline_type' => $ec['redline_type'] ?? 'per watt',
                    'old_redline' => $prev_redline->redline ?? 0,
                    'old_redline_amount_type' => $prev_redline->redline_amount_type ?? '',
                    'old_redline_type' => $prev_redline->redline_type ?? '',
                ]);
            }
        } elseif (! empty($prev_redline) && ! empty($next_redline) && ! empty($ec['redline_effective_date'])) {
            if (empty($redline)) {
                $record_data += [
                    'old_redline' => $prev_redline->redline ?? 0,
                    'old_redline_amount_type' => $prev_redline->redline_amount_type ?? '',
                    'old_redline_type' => $prev_redline->redline_type ?? '',
                ];
                $checkdata = UserRedlines::create($record_data);
            } else {
                $checkdata = UserRedlines::where([
                    'user_id' => $request->user_id,
                    'start_date' => $record_data['start_date'],
                    'position_type' => $record_data['position_type'],
                    'core_position_id' => $core_position_id,
                ])->update([
                    'updater_id' => auth()->user()->id,
                    'redline_amount_type' => $ec['redline_amount_type'] ?? '',
                    'redline' => $ec['redline'],
                    'redline_type' => $ec['redline_type'] ?? 'per watt',
                    'old_redline' => $prev_redline->redline ?? 0,
                    'old_redline_amount_type' => $prev_redline->redline_amount_type ?? '',
                    'old_redline_type' => $prev_redline->redline_type ?? '',
                ]);
            }
            $next_redline->old_redline = $ec['redline'];
            $next_redline->old_redline_type = $ec['redline_type'] ?? 'per watt';
            $next_redline->old_redline_amount_type = $ec['redline_amount_type'] ?? '';
            $next_redline->save();
        }
    }

    public function combine_organization_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';

        $oraganization_history = UserOrganizationHistory::with('updater', 'oldManager', 'manager', 'oldTeam', 'team', 'position', 'oldPosition', 'subPositionId', 'oldSubPositionId')->where('user_id', $user_id);
        if ($future_only) {
            $oraganization_history = $oraganization_history->where('effective_date', '>', date('Y-m-d'));
        }
        $oraganization_history = $oraganization_history->orderBy('effective_date')->get();
        $oldSelf = 0;
        foreach ($oraganization_history as $key => $org_history) {
            $go = 0;
            if ($key == 0 && ! empty($org_history->self_gen_accounts)) {
                $go = 1;
            } elseif ($key != 0) {
                if ($oldSelf != $org_history->self_gen_accounts) {
                    $go = 1;
                }
            }

            if ($go) {
                if (empty($filter) || ($filter == 'Self Gen')) {
                    $history_array[] = [
                        'id' => $org_history->id,
                        'type' => 'Self Gen',
                        'effective_date' => isset($org_history->effective_date) ? $org_history->effective_date : null,
                        'old_value' => isset($org_history->old_self_gen_accounts) ? $org_history->old_self_gen_accounts : null,
                        'new_value' => isset($org_history->self_gen_accounts) ? $org_history->self_gen_accounts : null,
                        'updated_at' => $org_history->updated_at,
                        'updater' => isset($org_history->updater) ? $org_history->updater : null,
                    ];
                }
            }
            $oldSelf = $org_history->self_gen_accounts;

            if (! empty($org_history->old_sub_position_id) || ! empty($org_history->sub_position_id)) {
                if (empty($filter) || ($filter == 'Position')) {
                    $history_array[] = [
                        'id' => $org_history->id,
                        'type' => 'Position',
                        'effective_date' => isset($org_history->effective_date) ? $org_history->effective_date : null,
                        'old_value' => isset($org_history->oldSubPositionId->position_name) ? $org_history->oldSubPositionId->position_name : null,
                        'new_value' => isset($org_history->subPositionId->position_name) ? $org_history->subPositionId->position_name : null,
                        'updated_at' => $org_history->updated_at,
                        'updater' => isset($org_history->updater) ? $org_history->updater : null,
                    ];
                }
            }
        }

        $isManagers = UserIsManagerHistory::with('updater')->where('user_id', $user_id);
        if ($future_only) {
            $isManagers = $isManagers->where('effective_date', '>', date('Y-m-d'));
        }
        $isManagers = $isManagers->get();

        if ($isManagers) {
            foreach ($isManagers as $isManager) {
                if (empty($filter) || ($filter == 'is manager')) {
                    $history_array[] = [
                        'id' => $isManager->id,
                        'type' => 'is manager',
                        'effective_date' => isset($isManager->effective_date) ? $isManager->effective_date : null,
                        'old_value' => isset($isManager->old_is_manager) ? $isManager->old_is_manager : null,
                        'new_value' => isset($isManager->is_manager) ? $isManager->is_manager : null,
                        'updated_at' => $isManager->updated_at,
                        'updater' => isset($isManager->updater) ? $isManager->updater : null,
                    ];
                }
            }
        }

        $managers = UserManagerHistory::with('updater', 'manager', 'oldManager', 'team', 'oldTeam')->where('user_id', $user_id);
        if ($future_only) {
            $managers = $managers->where('effective_date', '>', date('Y-m-d'));
        }
        $managers = $managers->get();

        if ($managers) {
            foreach ($managers as $manager) {
                if (empty($filter) || ($filter == 'Manager')) {
                    $history_array[] = [
                        'id' => $manager->id,
                        'type' => 'Manager',
                        'effective_date' => isset($manager->effective_date) ? $manager->effective_date : null,
                        'old_value' => isset($manager->oldManager->first_name) ? $manager->oldManager->first_name.' '.$manager->oldManager->last_name : null,
                        'new_value' => isset($manager->manager->first_name) ? $manager->manager->first_name.' '.$manager->manager->last_name : null,
                        'updated_at' => $manager->updated_at,
                        'updater' => isset($manager->updater) ? $manager->updater : null,
                    ];
                }

                if ($manager->team_id) {
                    if (empty($filter) || ($filter == 'Team')) {
                        $history_array[] = [
                            'id' => $manager->id,
                            'type' => 'Team',
                            'effective_date' => isset($manager->effective_date) ? $manager->effective_date : null,
                            'old_value' => isset($manager->oldTeam->team_name) ? $manager->oldTeam->team_name : null,
                            'new_value' => isset($manager->team->team_name) ? $manager->team->team_name : null,
                            'updated_at' => $manager->updated_at,
                            'updater' => isset($manager->updater) ? $manager->updater : null,
                        ];
                    }
                }
            }
        }

        if (empty($filter) || ($filter == 'Additional Location')) {
            $additional_locations = AdditionalLocations::with('state', 'office', 'updater')->where('user_id', $user_id)->withTrashed();
            if ($future_only) {
                $additional_locations = $additional_locations->where('effective_date', '>', date('Y-m-d'));
            }
            $additional_locations = $additional_locations->get();
            foreach ($additional_locations as $locations) {
                $state = isset($locations->state->name) ? $locations->state->name : '';
                $office = isset($locations->office->office_name) ? $locations->office->office_name : '';
                if (empty($locations->deleted_at)) {
                    $history_array[] = [
                        'id' => $locations->id,
                        'type' => 'Additional Location',
                        'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                        'old_value' => '-',
                        'new_value' => $state.' | '.$office,
                        'updated_at' => $locations->updated_at,
                        'updater' => isset($locations->updater) ? $locations->updater : null,
                    ];
                } else {
                    $history_array[] = [
                        'id' => $locations->id,
                        'type' => 'Additional Location',
                        'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                        'old_value' => $state.' | '.$office,
                        'new_value' => 'Deleted',
                        'updated_at' => $locations->updated_at,
                        'updater' => isset($locations->updater) ? $locations->updater : null,
                    ];
                }
            }
        }

        if ($sort_type == 'asc') {
            $history_array = collect($history_array)->sortBy($sort_by)->toArray();
        } else {
            $history_array = collect($history_array)->sortByDesc($sort_by)->toArray();
        }
        $data = paginate($history_array, $perpage);

        return response()->json([
            'ApiName' => 'combine_organization_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ]);
    }

    public function combine_transfer_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';

        $user_transfer_history = UserTransferHistory::with('user', 'department', 'oldDepartment', 'updater', 'position', 'oldPosition', 'subposition', 'oldSubPosition', 'office', 'oldOffice', 'state', 'oldState')
            ->where(['user_id' => $request->user_id])
            ->orderby('transfer_effective_date', 'DESC');
        if ($future_only) {
            $user_transfer_history = $user_transfer_history->where('transfer_effective_date', '>', date('Y-m-d'));
        }
        $user_transfer_history = $user_transfer_history->get();

        foreach ($user_transfer_history as $key => $res) {
            $history_array[] = [
                'id' => $res->id,
                'effective_date' => $res->transfer_effective_date,
                'updated_at' => $res->updated_at,
                'department_id' => $res->department_id,
                'old_department_id' => $res->old_department_id,
                'department_name' => isset($res->department->name) ? $res->department->name : null,
                'old_department_name' => isset($res->oldDepartment->name) ? $res->oldDepartment->name : null,

                'position_id' => $res->position_id,
                'old_position_id' => $res->old_position_id,
                'sub_position_id' => isset($res->sub_position_id) ? $res->sub_position_id : null,
                'old_sub_position_id' => isset($res->old_sub_position_id) ? $res->old_sub_position_id : null,
                'position_name' => isset($res->position->position_name) ? $res->position->position_name : null,
                'old_position_name' => isset($res->oldPosition->position_name) ? $res->oldPosition->position_name : null,
                'sub_position_name' => isset($res->subposition->position_name) ? $res->position->position_name : null,
                'old_sub_position_name' => isset($res->oldSubPosition->position_name) ? $res->oldSubPosition->position_name : null,

                'state_id' => $res->state_id,
                'old_state_id' => $res->old_state_id,
                'office_id' => $res->office_id,
                'old_office_id' => $res->old_office_id,
                'state_name' => isset($res->state->name) ? $res->state->name : null,
                'old_state_name' => isset($res->oldState->name) ? $res->oldState->name : null,
                'office_name' => isset($res->office->office_name) ? $res->office->office_name : null,
                'old_office_name' => isset($res->oldOffice->office_name) ? $res->oldOffice->office_name : null,

                'manager_id' => $res->manager_id,
                'old_manager_id' => $res->old_manager_id,
                'manager_name' => isset($res->manager->first_name) ? $res->manager->first_name.' '.$res->manager->last_name : null,
                'old_manager_name' => isset($res->oldManager->first_name) ? $res->oldManager->first_name.' '.$res->oldManager->last_name : null,

                'updater' => isset($res->updater) ? $res->updater : null,
                'user_details' => $res->user,
            ];
        }

        if ($sort_type == 'asc') {
            $history_array = collect($history_array)->sortBy($sort_by)->toArray();
        } else {
            $history_array = collect($history_array)->sortByDesc($sort_by)->toArray();
        }
        $data = paginate($history_array, $perpage);

        return response()->json([
            'ApiName' => 'combine_transfer_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,

        ], 200);
    }

    public function combine_redline_commission_upfront_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $product_id = $request->product_id;
        $core_position_id = $request->core_position_id;
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';
        $history_array = [];

        if (empty($filter) || ($filter == 'Commission')) {
            // UserCommissionHistory
            $user_commission_history = UserCommissionHistory::with('updater', 'subposition', 'position')->where('user_id', $user_id)->where('core_position_id', $core_position_id)->where('product_id', $product_id);
            if ($future_only) {
                $user_commission_history = $user_commission_history->where('commission_effective_date', '>', date('Y-m-d'));
            }
            $user_commission_history = $user_commission_history->get();
            // echo "<pre>";print_r($user_commission_history);die();
            foreach ($user_commission_history as $commission_history) {
                if (($commission_history->old_commission != null && $commission_history->old_commission != '') || ($commission_history->commission != null && $commission_history->commission != '')) {
                    $position_name = $this->check_selfgen_user($commission_history, 'UserCommissionHistory');
                    // $is_selfgen = $this->check_selfgen_user($commission_history->user_id,$commission_history->commission_effective_date,$commission_history->id);
                    // $main_position_name = isset($commission_history->subposition->position_name)?$commission_history->subposition->position_name:$commission_history->position->position_name;
                    // $selfgen_position_name = isset($commission_history->position->position_name)?$commission_history->position->position_name:null;
                    // $position_name = ($is_selfgen=='false')?$main_position_name:$selfgen_position_name;
                    // Get commission type display value (with custom field support)
                    $commissionType = $this->getCommissionTypeDisplayForAudit($commission_history->commission_type, $commission_history->custom_sales_field_id ?? null);
                    $oldCommissionType = $this->getCommissionTypeDisplayForAudit($commission_history->old_commission_type, $commission_history->old_custom_sales_field_id ?? null);

                    $history_array[] = [
                        'id' => $commission_history->id,
                        'product_id' => $commission_history->product_id,
                        'core_position_id' => $commission_history->core_position_id,
                        'id' => $commission_history->id,
                        'effective_date' => $commission_history->commission_effective_date,
                        'type' => 'Commission',
                        'position_name' => $position_name,
                        'position_role' => isset($commission_history->position->position_name) ? $commission_history->position->position_name : null,
                        'position_id' => $commission_history->position_id,
                        'sub_position_id' => isset($commission_history->sub_position_id) ? $commission_history->sub_position_id : null,
                        'sub_position_name' => isset($commission_history->subposition->position_name) ? $commission_history->subposition->position_name : null,
                        'old_value' => $commission_history->old_commission.$oldCommissionType,
                        'new_value' => $commission_history->commission.$commissionType,

                        'old_amount' => $commission_history->old_commission,
                        'old_amount_type' => $commission_history->old_commission_type,
                        'new_amount' => $commission_history->commission,
                        'new_amount_type' => $commission_history->commission_type,

                        'updated_on' => $commission_history->updated_at,
                        'updater' => $commission_history->updater,
                        'percentage' => get_growth_percentage($commission_history->old_commission, $commission_history->commission),
                    ];
                }
            }
        }
        // UserRedlines
        $user_redline_history = UserRedlines::with('updater', 'subposition', 'position')->where('user_id', $user_id)->where('core_position_id', $core_position_id)->where('product_id', $product_id);
        if ($future_only) {
            $user_redline_history = $user_redline_history->where('start_date', '>', date('Y-m-d'));
        }
        $user_redline_history = $user_redline_history->get();
        foreach ($user_redline_history as $redline_history) {
            if (($redline_history->old_redline != null && $redline_history->old_redline != '') || ($redline_history->redline != null && $redline_history->redline != '')) {
                $position_name = $this->check_selfgen_user($redline_history, 'UserRedlines');
                if ($redline_history->redline_amount_type == 'Fixed') {
                    if (empty($filter) || ($filter == 'Fixed Redline')) {
                        $history_array[] = [
                            'id' => $redline_history->id,
                            'product_id' => $commission_history->product_id,
                            'core_position_id' => $commission_history->core_position_id,
                            'effective_date' => $redline_history->start_date,
                            'type' => 'Fixed Redline',
                            'position_name' => $position_name,
                            'position_role' => isset($redline_history->position->position_name) ? $redline_history->position->position_name : null,
                            'position_id' => $redline_history->position_type,
                            'sub_position_id' => isset($redline_history->sub_position_type) ? $redline_history->sub_position_type : null,
                            'sub_position_name' => isset($redline_history->subposition->position_name) ? $redline_history->subposition->position_name : null,
                            'old_value' => $redline_history->old_redline.' '.$redline_history->old_redline_type,
                            'new_value' => $redline_history->redline.' '.$redline_history->redline_type,

                            'old_amount' => $redline_history->old_redline,
                            'old_amount_type' => $redline_history->old_redline_type,
                            'new_amount' => $redline_history->redline,
                            'new_amount_type' => $redline_history->redline_type,

                            'updated_on' => $redline_history->updated_at,
                            'updater' => $redline_history->updater,
                            'percentage' => get_growth_percentage($redline_history->old_redline, $redline_history->redline),
                        ];
                    }
                } else {
                    if (empty($filter) || ($filter == 'Location Redline')) {
                        $history_array[] = [
                            'id' => $redline_history->id,
                            'product_id' => $commission_history->product_id,
                            'core_position_id' => $commission_history->core_position_id,
                            'effective_date' => $redline_history->start_date,
                            'type' => 'Location Redline',
                            'position_name' => $position_name,
                            'position_role' => isset($redline_history->position->position_name) ? $redline_history->position->position_name : null,
                            'position_id' => $redline_history->position_type,
                            'sub_position_id' => isset($redline_history->sub_position_type) ? $redline_history->sub_position_type : null,
                            'sub_position_name' => isset($redline_history->subposition->position_name) ? $redline_history->subposition->position_name : null,
                            'old_value' => $redline_history->old_redline.' '.$redline_history->old_redline_type,
                            'new_value' => $redline_history->redline.' '.$redline_history->redline_type,

                            'old_amount' => $redline_history->old_redline,
                            'old_amount_type' => $redline_history->old_redline_type,
                            'new_amount' => $redline_history->redline,
                            'new_amount_type' => $redline_history->redline_type,

                            'updated_on' => $redline_history->updated_at,
                            'updater' => $redline_history->updater,
                            'percentage' => get_growth_percentage($redline_history->old_redline, $redline_history->redline),
                        ];
                    }
                }
            }
        }
        if (empty($filter) || ($filter == 'Upfront')) {
            // UserUpfrontHistory
            $user_upfront_history = UserUpfrontHistory::with('updater', 'subposition', 'position')->where('user_id', $user_id)->where('core_position_id', $core_position_id)->where('product_id', $product_id);
            if ($future_only) {
                $user_upfront_history = $user_upfront_history->where('upfront_effective_date', '>', date('Y-m-d'));
            }
            $user_upfront_history = $user_upfront_history->get();
            foreach ($user_upfront_history as $upfront_history) {
                if (($upfront_history->old_upfront_pay_amount != null && $upfront_history->old_upfront_pay_amount != '') || ($upfront_history->upfront_pay_amount != null && $upfront_history->upfront_pay_amount != '')) {
                    $position_name = $this->check_selfgen_user($upfront_history, 'UserUpfrontHistory');
                    $history_array[] = [
                        'id' => $upfront_history->id,
                        'product_id' => $commission_history->product_id,
                        'core_position_id' => $commission_history->core_position_id,
                        'effective_date' => $upfront_history->upfront_effective_date,
                        'type' => 'Upfront',
                        'position_name' => $position_name,
                        'position_role' => isset($upfront_history->position->position_name) ? $upfront_history->position->position_name : null,
                        'position_id' => $upfront_history->position_id,
                        'sub_position_id' => isset($upfront_history->sub_position_id) ? $upfront_history->sub_position_id : null,
                        'sub_position_name' => isset($upfront_history->subposition->position_name) ? $upfront_history->subposition->position_name : null,
                        'old_value' => $upfront_history->old_upfront_pay_amount.' '.$upfront_history->old_upfront_sale_type,
                        'new_value' => $upfront_history->upfront_pay_amount.' '.$upfront_history->upfront_sale_type,

                        'old_amount' => $upfront_history->old_upfront_pay_amount,
                        'old_amount_type' => $upfront_history->old_upfront_sale_type,
                        'new_amount' => $upfront_history->upfront_pay_amount,
                        'new_amount_type' => $upfront_history->upfront_sale_type,

                        'updated_on' => $upfront_history->updated_at,
                        'updater' => $upfront_history->updater,
                        'percentage' => get_growth_percentage($upfront_history->old_upfront_pay_amount, $upfront_history->upfront_pay_amount),
                    ];
                }
            }
        }

        if (empty($filter) || ($filter == 'Withheld')) {
            // UserWithheldHistory
            $user_withheld_history = UserWithheldHistory::with('updater', 'subposition', 'position')->where('user_id', $user_id)->where('core_position_id', $core_position_id)->where('product_id', $product_id);
            if ($future_only) {
                $user_withheld_history = $user_withheld_history->where('withheld_effective_date', '>', date('Y-m-d'));
            }
            $user_withheld_history = $user_withheld_history->get();
            foreach ($user_withheld_history as $withheld_history) {
                if (($withheld_history->old_withheld_amount != null && $withheld_history->old_withheld_amount != '') || ($withheld_history->withheld_amount != null && $withheld_history->withheld_amount != '')) {
                    $position_name = $this->check_selfgen_user($withheld_history, 'UserWithheldHistory');
                    $history_array[] = [
                        'id' => $withheld_history->id,
                        'product_id' => $commission_history->product_id,
                        'core_position_id' => $commission_history->core_position_id,
                        'effective_date' => $withheld_history->withheld_effective_date,
                        'type' => 'Withheld',
                        'position_name' => $position_name,
                        'position_role' => isset($withheld_history->position->position_name) ? $withheld_history->position->position_name : null,
                        'position_id' => $withheld_history->position_id,
                        'sub_position_id' => isset($withheld_history->sub_position_id) ? $withheld_history->sub_position_id : null,
                        'sub_position_name' => isset($withheld_history->subposition->position_name) ? $withheld_history->subposition->position_name : null,
                        'old_value' => $withheld_history->old_withheld_amount.' '.$withheld_history->old_withheld_type,
                        'new_value' => $withheld_history->withheld_amount.' '.$withheld_history->withheld_type,

                        'old_amount' => $withheld_history->old_withheld_amount,
                        'old_amount_type' => $withheld_history->old_withheld_type,
                        'new_amount' => $withheld_history->withheld_amount,
                        'new_amount_type' => $withheld_history->withheld_type,

                        'updated_on' => $withheld_history->updated_at,
                        'updater' => $withheld_history->updater,
                        'percentage' => get_growth_percentage($withheld_history->old_withheld_amount, $withheld_history->withheld_amount),
                    ];
                }
            }
        }
        /*if(empty($filter) || ($filter=='Self Gen Commission')){
            // UserSelfGenCommmissionHistory
            $user_self_gen_history = UserSelfGenCommmissionHistory::with('updater','subposition','position')->where('user_id',$user_id);
            if($future_only){
                $user_self_gen_history = $user_self_gen_history->where('commission_effective_date','>',date('Y-m-d'));
            }
            $user_self_gen_history = $user_self_gen_history->get();
            foreach($user_self_gen_history as $self_gen_history){
                if($self_gen_history->old_commission != NULL || $self_gen_history->commission != NULL){
                    $history_array[] = [
                        'id' => $self_gen_history->id,
                        'effective_date' => $self_gen_history->commission_effective_date,
                        'type' => 'Self Gen Commission',
                        'position_id' => $self_gen_history->position_id,
                        'sub_position_id' => isset($self_gen_history->sub_position_id)?$self_gen_history->sub_position_id:null,
                        'sub_position_name' => isset($self_gen_history->subposition->position_name)?$self_gen_history->subposition->position_name:null,
                        'old_value' => $self_gen_history->old_commission,
                        'new_value' => $self_gen_history->commission,
                        'position_role' => isset($self_gen_history->subposition->position_name)?$self_gen_history->subposition->position_name:null,

                        'old_amount' => $self_gen_history->old_commission,
                        'new_amount' => $self_gen_history->commission,


                        'updated_on' => $self_gen_history->updated_at,
                        'updater' => $self_gen_history->updater,
                        'percentage' => get_growth_percentage($self_gen_history->old_commission,$self_gen_history->commission)
                    ];
                }
            }
        }*/
        if ($sort_type == 'asc') {
            $history_array = collect($history_array)->sortBy($sort_by)->toArray();
        } else {
            $history_array = collect($history_array)->sortByDesc($sort_by)->toArray();
        }
        $data = paginate($history_array, $perpage);

        return response()->json([
            'ApiName' => 'combine_redline_commission_upfront_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
    }

    public function combine_override_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $product_id = $request->product_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';

        // UserOverrideHistory
        $user_override_history = UserOverrideHistory::with('updater')->where('user_id', $user_id)->where('product_id', $product_id);
        if ($future_only) {
            $user_override_history = $user_override_history->where('override_effective_date', '>', date('Y-m-d'));
        }

        foreach ($user_override_history->get() as $override_history) {
            if (empty($filter) || ($filter == 'Direct')) {
                // if(!empty($override_history->direct_overrides_amount)){
                if ($override_history->old_direct_overrides_amount.' '.$override_history->old_direct_overrides_type != $override_history->direct_overrides_amount.' '.$override_history->direct_overrides_type) {
                    $history_array[] = [
                        'id' => $override_history->id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Direct',
                        'old_value' => $override_history->old_direct_overrides_amount.' '.$override_history->old_direct_overrides_type,
                        'new_value' => $override_history->direct_overrides_amount.' '.$override_history->direct_overrides_type,
                        'old_amount' => $override_history->old_direct_overrides_amount,
                        'old_amonut_type' => $override_history->old_direct_overrides_type,
                        'new_amount' => $override_history->direct_overrides_amount,
                        'new_amount_type' => $override_history->direct_overrides_type,
                        'updated_on' => $override_history->updated_at,
                        'updater' => $override_history->updater,
                        'percentage' => get_growth_percentage($override_history->old_direct_overrides_amount, $override_history->direct_overrides_amount),
                    ];
                }
                // }
            }
            if (empty($filter) || ($filter == 'Indirect')) {
                // if(!empty($override_history->indirect_overrides_amount)){
                if ($override_history->old_indirect_overrides_amount.' '.$override_history->old_indirect_overrides_type != $override_history->indirect_overrides_amount.' '.$override_history->indirect_overrides_type) {
                    $history_array[] = [
                        'id' => $override_history->id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Indirect',
                        'old_value' => $override_history->old_indirect_overrides_amount.' '.$override_history->old_indirect_overrides_type,
                        'new_value' => $override_history->indirect_overrides_amount.' '.$override_history->indirect_overrides_type,
                        'old_amount' => $override_history->old_indirect_overrides_amount,
                        'old_amonut_type' => $override_history->old_indirect_overrides_type,
                        'new_amount' => $override_history->indirect_overrides_amount,
                        'new_amount_type' => $override_history->indirect_overrides_type,
                        'updated_on' => $override_history->updated_at,
                        'updater' => $override_history->updater,
                        'percentage' => get_growth_percentage($override_history->old_direct_overrides_amount, $override_history->direct_overrides_amount),
                    ];
                }
                // }
            }
            if (empty($filter) || ($filter == 'Office')) {
                // if(!empty($override_history->office_overrides_amount)){
                if ($override_history->old_office_overrides_amount.' '.$override_history->old_office_overrides_type != $override_history->office_overrides_amount.' '.$override_history->office_overrides_type) {
                    $history_array[] = [
                        'id' => $override_history->id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Office',
                        'old_value' => $override_history->old_office_overrides_amount.' '.$override_history->old_office_overrides_type,
                        'new_value' => $override_history->office_overrides_amount.' '.$override_history->office_overrides_type,
                        'old_amount' => $override_history->old_office_overrides_amount,
                        'old_amonut_type' => $override_history->old_office_overrides_type,
                        'new_amount' => $override_history->office_overrides_amount,
                        'new_amount_type' => $override_history->office_overrides_type,
                        'updated_on' => $override_history->updated_at,
                        'updater' => $override_history->updater,
                        'percentage' => get_growth_percentage($override_history->old_direct_overrides_amount, $override_history->direct_overrides_amount),
                    ];
                }
                // }
            }
            if (empty($filter) || ($filter == 'Office Stack')) {
                // if(!empty($override_history->office_stack_overrides_amount)){
                if ($override_history->old_office_stack_overrides_amount != $override_history->office_stack_overrides_amount) {
                    $history_array[] = [
                        'id' => $override_history->id,
                        'product_id' => $override_history->product_id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Office Stack',
                        'old_value' => $override_history->old_office_stack_overrides_amount,
                        'new_value' => $override_history->office_stack_overrides_amount,
                        'old_amount' => $override_history->old_office_stack_overrides_amount,
                        'old_amonut_type' => $override_history->old_office_stack_overrides_amount ? 'percent' : null,
                        'new_amount' => $override_history->office_stack_overrides_amount,
                        'new_amount_type' => 'percent',
                        'updated_on' => $override_history->updated_at,
                        'updater' => $override_history->updater,
                        'percentage' => get_growth_percentage($override_history->old_direct_overrides_amount, $override_history->direct_overrides_amount),
                    ];
                }
                // }
            }
        }
        /* show data order by date getting from this date  UserAdditionalOfficeOverrideHistory */
        /* Get user_additional_office_override_histories data based on user id */
        $userAdditionnOffHistoryData = UserAdditionalOfficeOverrideHistory::with('updater')->where('user_id', $user_id)->get();
        $office_history = [];
        if ($userAdditionnOffHistoryData) {
            foreach ($userAdditionnOffHistoryData as $key => $value) {

                if ($filter == 'Office' || $filter == '') {
                    $office_history[] = [
                        'id' => $value->id,
                        'effective_date' => $value->override_effective_date,
                        'type' => 'Office',
                        'old_value' => $value->old_office_overrides_type,
                        'new_value' => $value->office_overrides_type,
                        'old_amount' => $value->old_office_overrides_amount,
                        'old_amonut_type' => $value->office_overrides_type,
                        'new_amount' => $value->office_overrides_amount,
                        'new_amount_type' => $value->office_overrides_type,
                        'updated_on' => $value->updated_at,
                        'updater' => $value->updater,
                        'percentage' => get_growth_percentage($value->old_office_overrides_amount, $value->office_overrides_amount),
                    ];
                } else {
                    $office_history = [];
                }
            }
        }
        $data = array_merge($history_array, $office_history);
        if ($sort_type == 'asc') {
            $data = collect($data)->sortBy($sort_by)->toArray();
        } else {
            $data = collect($data)->sortByDesc($sort_by)->toArray();
        }

        $data = paginate($data, $perpage);

        return response()->json([
            'ApiName' => 'combine_override_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
    }

    public function combine_wages_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';

        // UserWagesHistory
        $user_wages_history = UserWagesHistory::with('updater')->where('user_id', $user_id);
        if ($future_only) {
            $user_wages_history = $user_wages_history->where('effective_date', '>', date('Y-m-d'));
        }
        $user_wages_history = $user_wages_history->get();
        foreach ($user_wages_history as $wages) {

            $history_array[] = [
                'id' => $wages->id,
                'pay_type' => $wages->pay_type,
                'pay_rate' => $wages->pay_rate,
                'pay_rate_type' => $wages->pay_rate_type,
                'expected_weekly_hours' => $wages->expected_weekly_hours,
                'overtime_rate' => $wages->overtime_rate,
                'pto_hours' => isset($wages->pto_hours) ? $wages->pto_hours : null,
                'unused_pto_expires' => isset($wages->unused_pto_expires) ? $wages->unused_pto_expires : null,
                'effective_date' => $wages->effective_date,
                'pto_hours_effective_date' => $wages->pto_hours_effective_date,
                'old_pay_type' => $wages->old_pay_type,
                'old_pay_rate' => $wages->old_pay_rate,
                'old_pay_rate_type' => $wages->old_pay_rate_type,
                'old_pto_hours' => $wages->old_pto_hours,
                'old_unused_pto_expires' => $wages->old_unused_pto_expires,
                'old_expected_weekly_hours' => $wages->old_expected_weekly_hours,
                'old_overtime_rate' => $wages->old_overtime_rate,
                'updated_on' => $wages->updated_at,
                'updater' => $wages->updater,
            ];
        }

        if ($sort_type == 'asc') {
            $history_array = collect($history_array)->sortBy($sort_by)->toArray();
        } else {
            $history_array = collect($history_array)->sortByDesc($sort_by)->toArray();
        }
        $data = paginate($history_array, $perpage);

        return response()->json([
            'ApiName' => 'combine_wages_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
    }

    public function combine_agreement_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';

        // UserAgreementHistory
        $user_agreement_history = UserAgreementHistory::with('updater', 'hiringby', 'old_hiringby')->where('user_id', $user_id);
        if ($future_only) {
            // $user_wages_history = $user_wages_history->where('effective_date','>',date('Y-m-d'));
        }
        $user_agreement_history = $user_agreement_history->get();
        foreach ($user_agreement_history as $wages) {

            $history_array[] = [
                'id' => $wages->id,
                'probation_period' => $wages->probation_period,
                'old_probation_period' => $wages->old_probation_period,
                'offer_include_bonus' => $wages->offer_include_bonus,
                'old_offer_include_bonus' => $wages->old_offer_include_bonus,
                'old_hiring_bonus_amount' => $wages->old_hiring_bonus_amount,
                'hiring_bonus_amount' => $wages->hiring_bonus_amount,
                'date_to_be_paid' => $wages->date_to_be_paid,
                'old_date_to_be_paid' => $wages->old_date_to_be_paid,
                'period_of_agreement' => $wages->period_of_agreement,
                'old_period_of_agreement' => $wages->old_period_of_agreement,
                'end_date' => $wages->end_date,
                'old_end_date' => $wages->old_end_date,
                'offer_expiry_date' => $wages->offer_expiry_date,
                'old_offer_expiry_date' => $wages->old_offer_expiry_date,
                'hiring_signature' => $wages->hiring_signature,
                'old_hiring_signature' => $wages->old_hiring_signature,
                'hired_by' => $wages->hiringby,
                'old_hired_by' => $wages->old_hiringby,

                'updated_on' => $wages->updated_at,
                'updater' => $wages->updater,
                'hiring_signature' => $wages->hiring_signature,
                'old_hiring_signature' => $wages->old_hiring_signature,
            ];
        }

        if ($sort_type == 'asc') {
            $history_array = collect($history_array)->sortBy($sort_by)->toArray();
        } else {
            $history_array = collect($history_array)->sortByDesc($sort_by)->toArray();
        }
        $data = paginate($history_array, $perpage);

        return response()->json([
            'ApiName' => 'combine_wages_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
    }

    public function combine_deduction_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';

        // UserOverrideHistory
        $user_deduction_history = UserDeductionHistory::with('updater', 'costcenter')->where('user_id', $user_id);
        if ($future_only) {
            $user_deduction_history = $user_deduction_history->where('effective_date', '>', date('Y-m-d'));
        }
        $user_deduction_history = $user_deduction_history->get();
        foreach ($user_deduction_history as $deduction_history) {

            if ($deduction_history->old_amount_par_paycheque != $deduction_history->amount_par_paycheque) {
                $history_array[] = [
                    'id' => $deduction_history->id,
                    'effective_date' => $deduction_history->effective_date,
                    'type' => isset($deduction_history->costcenter->name) ? $deduction_history->costcenter->name : null,
                    'old_value' => $deduction_history->old_amount_par_paycheque,
                    'new_value' => $deduction_history->amount_par_paycheque,
                    'updated_on' => $deduction_history->updated_at,
                    'updater' => $deduction_history->updater,
                    'percentage' => get_growth_percentage($deduction_history->old_amount_par_paycheque, $deduction_history->amount_par_paycheque),
                ];
            }
        }

        if ($sort_type == 'asc') {
            $history_array = collect($history_array)->sortBy($sort_by)->toArray();
        } else {
            $history_array = collect($history_array)->sortByDesc($sort_by)->toArray();
        }
        $data = paginate($history_array, $perpage);

        return response()->json([
            'ApiName' => 'combine_deduction_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
    }

    public function employee_transfer(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'user_id' => 'required',
                // 'position_id' => 'required'
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 200);
        }

        $userTransInfo = UserTransferHistory::where('user_id', $request->user_id)
            ->where('transfer_effective_date', '=', $request->effective_date)
            ->orderBy('transfer_effective_date', 'desc')->first();
        $TransInfo_prev = UserTransferHistory::where('user_id', $request->user_id)
            ->where('transfer_effective_date', '<', $request->effective_date)
            ->orderBy('transfer_effective_date', 'desc')
            ->first();

        $TransInfo_next = UserTransferHistory::where('user_id', $request->user_id)
            ->where('transfer_effective_date', '>', $request->effective_date)
            ->orderBy('transfer_effective_date', 'asc')
            ->first();
        $userInfo = User::where('id', $request->user_id)->first();
        // if(!empty($userTransInfo)){
        //     $stateId = $userTransInfo->state_id;
        // }else{
        //     $stateId = $userInfo->state_id;
        // }

        if (empty($TransInfo_prev) && empty($TransInfo_next)) {
            if (empty($userTransInfo)) {
                $data = $this->createTransferHistory($request, $userTransInfo);
            } else {
                $data = $this->updateTransferHistory($request, $userInfo);
            }
        } elseif (! empty($TransInfo_prev) && empty($TransInfo_next)) {
            if (empty($userTransInfo)) {
                $data = $this->createTransferHistory($request, $TransInfo_prev);
            } else {
                $data = $this->updateTransferHistory($request, $TransInfo_prev);
            }
        } elseif (empty($TransInfo_prev) && ! empty($TransInfo_next)) {
            if (empty($userTransInfo)) {
                $data = $this->createTransferHistory($request, []);
            } else {
                $data = $this->updateTransferHistory($request, []);
            }
            $this->assignTransInfoNextValues($TransInfo_next, $request);
        } elseif (! empty($TransInfo_prev) && ! empty($TransInfo_next)) {
            if (empty($userTransInfo)) {
                $data = $this->createTransferHistory($request, $TransInfo_prev);
            } else {
                $data = $this->updateTransferHistory($request, $userInfo);
            }
            $this->assignTransInfoNextValues($TransInfo_next, $request);
        }
        $TransInfo_latest = UserTransferHistory::where('user_id', $request->user_id)
            ->where('transfer_effective_date', '<=', date('Y-m-d'))
            // ->where('transfer_effective_date', '<=',$request->effective_date)
            ->orderBy('transfer_effective_date', 'desc')
            ->first();
        // if(date('Y-m-d',strtotime($request->effective_date)) == date('Y-m-d')){
        if (! empty($TransInfo_latest)) {
            $userEmployeeId = User::where('manager_id', $request->user_id)->pluck('id');
            User::whereIn('id', $userEmployeeId)->update(['manager_id' => $request->existing_employee_new_manager_id]);

            $leadData = Lead::where('recruiter_id', $request->user_id)->pluck('id')->toArray();
            if (count($leadData) > 0) {
                Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $request->manager_id]);
            }
        }
        $organizations = UserOrganizationHistory::where(['user_id' => $request->user_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date')->first();
        $pos_id = $organizations->position_id ?? '';
        $reddata = new \stdClass; // Initialize object
        $reddata->user_id = $request->user_id;
        $reddata->data = [];
        $position_id = $request->position_id ?? $pos_id;

        $redlineData = [
            'setter' => ['id' => 3, 'field' => 'setter_redline', 'self_gen' => 0],
            'closer' => ['id' => 2, 'field' => 'closer_redline', 'self_gen' => 0],
            'selfgen' => ['id' => '', 'field' => 'selfgen_redline', 'self_gen' => 1],
        ];

        foreach ($redlineData as $type => $info) {
            if (isset($request->{$info['field']})) {
                $reddata->core_position_id = $info['id'];
                $reddata->self_gen_user = $info['self_gen'];
                $reddata->data['redline'] = [
                    'redline' => $request->{$info['field']},
                    'redline_amount_type' => $request->redline_amount_type ?? null,
                    'redline_effective_date' => $request->effective_date,
                ];
                $this->addupdateredline($reddata, $position_id);
            }
        }

        return response()->json([
            'ApiName' => 'Add Employement Transfer History',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function getField($request, $userInfo, $field, $defaultField = null)
    {
        return isset($request->$field) ? $request->$field : (isset($userInfo->$defaultField) ? $userInfo->$defaultField : null);
    }

    // Create user transfer history entry
    public function createTransferHistory($request, $userInfo)
    {
        return UserTransferHistory::Create([
            'user_id' => $request->user_id,
            'transfer_effective_date' => $request->effective_date,
            'updater_id' => auth()->user()->id,
            'state_id' => $request->state_id,
            'office_id' => $request->office_id,
            'department_id' => $this->getField($request, $userInfo, 'department_id'),
            'is_manager' => $this->getField($request, $userInfo, 'is_manager'),
            'self_gen_accounts' => $this->getField($request, $userInfo, 'self_gen_accounts'),
            'manager_id' => $this->getField($request, $userInfo, 'manager_id', 'manager_id'),
            'team_id' => $request->team_id,
            'redline_amount_type' => $request->redline_amount_type ?? '',
            'redline' => $request->redline,
            'redline_type' => $request->redline_type,
            'self_gen_redline_amount_type' => $request->self_gen_redline_amount_type,
            'self_gen_redline' => $request->self_gen_redline,
            'self_gen_redline_type' => $request->self_gen_redline_type,
            'existing_employee_new_manager_id' => $request->existing_employee_new_manager_id,
            'old_state_id' => $userInfo->state_id ?? null,
            'old_office_id' => $userInfo->office_id ?? null,
            'old_department_id' => $userInfo->department_id ?? null,
            'old_is_manager' => $userInfo->is_manager ?? null,
            'old_manager_id' => $userInfo->manager_id ?? null,
            'old_team_id' => $userInfo->team_id ?? null,
            'existing_employee_old_manager_id' => $userInfo->existing_employee_new_manager_id,
        ]);
    }

    // Update user transfer history entry
    public function updateTransferHistory($request, $userInfo)
    {
        return UserTransferHistory::where([
            'user_id' => $request->user_id,
            'transfer_effective_date' => $request->effective_date,
        ])->update([
            'updater_id' => auth()->user()->id,
            'state_id' => $request->state_id,
            'office_id' => $request->office_id,
            'department_id' => $this->getField($request, $userInfo, 'department_id'),
            'position_id' => $this->getField($request, $userInfo, 'position_id'),
            'sub_position_id' => $this->getField($request, $userInfo, 'sub_position_id'),
            'is_manager' => $this->getField($request, $userInfo, 'is_manager'),
            'self_gen_accounts' => $this->getField($request, $userInfo, 'self_gen_accounts'),
            'manager_id' => $this->getField($request, $userInfo, 'manager_id', 'manager_id'),
            'team_id' => $request->team_id,
            'redline_amount_type' => $request->redline_amount_type ?? '',
            'redline' => $request->redline,
            'redline_type' => $request->redline_type,
            'self_gen_redline_amount_type' => $request->self_gen_redline_amount_type,
            'self_gen_redline' => $request->self_gen_redline,
            'self_gen_redline_type' => $request->self_gen_redline_type,
            'existing_employee_new_manager_id' => $request->existing_employee_new_manager_id,
            'old_state_id' => $userInfo->state_id ?? null,
            'old_office_id' => $userInfo->office_id ?? null,
            'old_department_id' => $userInfo->department_id ?? null,
            'old_position_id' => $userInfo->position_id ?? null,
            'old_sub_position_id' => $userInfo->sub_position_id ?? null,
            'old_is_manager' => $userInfo->is_manager ?? null,
            'old_self_gen_accounts' => $userInfo->self_gen_accounts ?? null,
            'old_manager_id' => $userInfo->manager_id ?? null,
            'old_team_id' => $userInfo->team_id ?? null,
            'existing_employee_old_manager_id' => $userInfo->existing_employee_new_manager_id,
        ]);
    }

    public function assignTransInfoNextValues($TransInfo_next, $request)
    {
        $TransInfo_next->old_state_id = $request->state_id ?? null;
        $TransInfo_next->old_office_id = $request->office_id ?? null;
        $TransInfo_next->old_department_id = $request->department_id ?? null;
        $TransInfo_next->old_position_id = $request->position_id ?? null;
        $TransInfo_next->old_sub_position_id = $request->sub_position_id ?? null;
        $TransInfo_next->old_is_manager = $request->is_manager ?? null; // This was previously using sub_position_id
        $TransInfo_next->old_self_gen_accounts = $request->self_gen_accounts ?? null;
        $TransInfo_next->old_manager_id = $request->manager_id ?? null;
        $TransInfo_next->old_team_id = $request->team_id ?? null;
        $TransInfo_next->old_redline_amount_type = $request->redline_amount_type ?? null;
        $TransInfo_next->old_redline = $request->redline ?? null;
        $TransInfo_next->old_redline_type = $request->redline_type ?? null;
        $TransInfo_next->old_self_gen_redline_amount_type = $request->self_gen_redline_amount_type ?? null;
        $TransInfo_next->old_self_gen_redline = $request->self_gen_redline ?? null;
        $TransInfo_next->old_self_gen_redline_type = $request->self_gen_redline_type ?? null;
        $TransInfo_next->existing_employee_old_manager_id = $request->existing_employee_new_manager_id;
    }

    public function check_selfgen_user($history, $table = '')
    {
        $user_id = $history->user_id;
        $product_id = $history->product_id;
        $core_position_id = $history->core_position_id;
        // $effective_date = $history->commission_effective_date;
        $id = $history->id;
        $main_position_name = isset($history->subposition->position_name) ? $history->subposition->position_name : null;
        $selfgen_position_name = isset($history->position->position_name) ? $history->position->position_name : null;
        $position_name = null;
        $data = [];
        if ($table == 'UserCommissionHistory') {
            $data = UserCommissionHistory::where(['user_id' => $user_id, 'product_id' => $product_id, 'core_position_id' => $core_position_id, 'commission_effective_date' => $history->commission_effective_date])->get();
        }
        if ($table == 'UserRedlines') {
            $data = UserRedlines::where(['user_id' => $user_id, 'product_id' => $product_id, 'core_position_id' => $core_position_id, 'start_date' => $history->start_date])->get();
        }
        if ($table == 'UserUpfrontHistory') {
            $data = UserUpfrontHistory::where(['user_id' => $user_id, 'product_id' => $product_id, 'core_position_id' => $core_position_id, 'upfront_effective_date' => $history->upfront_effective_date])->get();
        }
        if ($table == 'UserWithheldHistory') {
            $data = UserWithheldHistory::where(['user_id' => $user_id, 'product_id' => $product_id, 'core_position_id' => $core_position_id, 'withheld_effective_date' => $history->withheld_effective_date])->get();
        }
        if ($table == 'UserSelfGenCommmissionHistory') {
            $data = UserSelfGenCommmissionHistory::where(['user_id' => $user_id, 'commission_effective_date' => $history->commission_effective_date])->get();
        }

        if (count($data) > 1 && (isset($data[1]->id)) && ($data[1]->id == $id)) {
            $position_name = $selfgen_position_name;
        } else {
            $position_name = $main_position_name;
        }

        return $position_name;
    }

    public function compensationDataComp($udata, $reqdata)
    {

        $companyProfile = CompanyProfile::first();
        $data = [];
        if (! empty($udata) && ! empty($reqdata)) {

            if (! empty($reqdata['employee_compensation'])) {
                foreach ($reqdata['employee_compensation'] as $key => $value) {
                    $value = $value['data'][0];
                    if ($key == 0) {
                        $olddata = $udata['employee_compensation'][0];
                        if ($udata['commission'] != $value['commission']) {
                            $data['commission'] = [
                                'old_value' => $udata['commission'],
                                'new_value' => $value['commission'],
                            ];
                        }

                        if ($udata['commission_type'] != $value['commission_type']) {
                            $data['commission_type'] = [
                                'old_value' => $udata['commission_type'],
                                'new_value' => $value['commission_type'],
                            ];
                        }

                        if ($olddata['commission_effective_date'] != $value['commission_effective_date']) {
                            $data['commission_effective_date'] = [
                                'old_value' => (! empty($olddata['commission_effective_date'])) ? date('m-d-Y', strtotime($olddata['commission_effective_date'])) : '',
                                'new_value' => (! empty($value['commission_effective_date'])) ? date('m-d-Y', strtotime($value['commission_effective_date'])) : '',
                            ];
                        }

                        if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
                            // No Need To Include RedLine
                        } else {
                            if ($udata['redline_amount'] != $value['redline']) {
                                $data['redline'] = [
                                    'old_value' => $udata['redline_amount'],
                                    'new_value' => $value['redline'],
                                ];
                            }

                            if ($udata['redline_type'] != $value['redline_type']) {
                                $data['redline_type'] = [
                                    'old_value' => $udata['redline_type'],
                                    'new_value' => $value['redline_type'],
                                ];
                            }

                            if ($udata['redline'] != $value['redline_amount_type']) {
                                $data['redline_amount_type'] = [
                                    'old_value' => $udata['redline'],
                                    'new_value' => $value['redline_amount_type'],
                                ];
                            }

                            if ($olddata['redline_effective_date'] != $value['redline_effective_date']) {
                                $data['redline_effective_date'] = [
                                    'old_value' => (! empty($olddata['redline_effective_date'])) ? date('m-d-Y', strtotime($olddata['redline_effective_date'])) : '',
                                    'new_value' => (! empty($value['redline_effective_date'])) ? date('m-d-Y', strtotime($value['redline_effective_date'])) : '',
                                ];
                            }
                        }

                        if ($udata['upfront_pay_amount'] != $value['upfront_pay_amount']) {
                            $data['upfront_pay_amount'] = [
                                'old_value' => $udata['upfront_pay_amount'],
                                'new_value' => $value['upfront_pay_amount'],
                            ];
                        }

                        if ($udata['upfront_sale_type'] != $value['upfront_sale_type']) {
                            $data['upfront_sale_type'] = [
                                'old_value' => $udata['upfront_sale_type'],
                                'new_value' => $value['upfront_sale_type'],
                            ];
                        }

                        if ($olddata['upfront_effective_date'] != $value['upfront_effective_date']) {
                            $data['upfront_effective_date'] = [
                                'old_value' => (! empty($olddata['upfront_effective_date'])) ? date('m-d-Y', strtotime($olddata['upfront_effective_date'])) : '',
                                'new_value' => (! empty($value['upfront_effective_date'])) ? date('m-d-Y', strtotime($value['upfront_effective_date'])) : '',
                            ];
                        }

                        if ($udata['withheld_amount'] != $value['withheld_amount']) {
                            $data['withheld_amount'] = [
                                'old_value' => $udata['withheld_amount'],
                                'new_value' => $value['withheld_amount'],
                            ];
                        }

                        if ($udata['withheld_type'] != $value['withheld_type']) {
                            $data['withheld_type'] = [
                                'old_value' => $udata['withheld_type'],
                                'new_value' => $value['withheld_type'],
                            ];
                        }

                        if ($olddata['withheld_effective_date'] != $value['withheld_effective_date']) {
                            $data['withheld_effective_date'] = [
                                'old_value' => (! empty($olddata['withheld_effective_date'])) ? date('m-d-Y', strtotime($olddata['withheld_effective_date'])) : '',
                                'new_value' => (! empty($value['withheld_effective_date'])) ? date('m-d-Y', strtotime($value['withheld_effective_date'])) : '',
                            ];
                        }
                    }

                    if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
                        // No Need To Include SelfGen Data
                    } else {
                        if ($key == 1) {
                            if ($udata['self_gen_commission'] != $value['commission']) {
                                $data['self_gen_commission'] = [
                                    'old_value' => $udata['self_gen_commission'],
                                    'new_value' => $value['commission'],
                                ];
                            }

                            if ($udata['self_gen_commission_type'] != $value['commission_type']) {
                                $data['self_gen_commission_type'] = [
                                    'old_value' => $udata['self_gen_commission_type'],
                                    'new_value' => $value['commission_type'],
                                ];
                            }

                            if ($udata['self_gen_redline'] != $value['redline']) {
                                $data['self_gen_redline'] = [
                                    'old_value' => $udata['self_gen_redline'],
                                    'new_value' => $value['redline'],
                                ];
                            }

                            if ($udata['self_gen_redline_type'] != $value['redline_type']) {
                                $data['self_gen_redline_type'] = [
                                    'old_value' => $udata['self_gen_redline_type'],
                                    'new_value' => $value['redline_type'],
                                ];
                            }

                            if ($udata['self_gen_redline_amount_type'] != $value['redline_amount_type']) {
                                $data['self_gen_redline_amount_type'] = [
                                    'old_value' => $udata['self_gen_redline_amount_type'],
                                    'new_value' => $value['redline_amount_type'],
                                ];
                            }

                            if ($udata['self_gen_upfront_amount'] != $value['upfront_pay_amount']) {
                                $data['self_gen_upfront_amount'] = [
                                    'old_value' => $udata['self_gen_upfront_amount'],
                                    'new_value' => $value['upfront_pay_amount'],
                                ];
                            }

                            if ($udata['self_gen_upfront_type'] != $value['upfront_sale_type']) {
                                $data['self_gen_upfront_type'] = [
                                    'old_value' => $udata['self_gen_upfront_type'],
                                    'new_value' => $value['upfront_sale_type'],
                                ];
                            }

                            if ($udata['self_gen_withheld_amount'] != $value['withheld_amount']) {
                                $data['self_gen_withheld_amount'] = [
                                    'old_value' => $udata['self_gen_withheld_amount'],
                                    'new_value' => $value['withheld_amount'],
                                ];
                            }

                            if ($udata['self_gen_withheld_type'] != $value['withheld_type']) {
                                $data['self_gen_withheld_type'] = [
                                    'old_value' => $udata['self_gen_withheld_type'],
                                    'new_value' => $value['withheld_type'],
                                ];
                            }
                        }
                    }
                }
            }

            if ($companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE && in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'))) {
                // No Need To Include SelfGen Data
            } else {
                if (! empty($reqdata['commission_selfgen']) && $udata['commission_selfgen'] != $reqdata['commission_selfgen']) {
                    $data['commission_selfgen'] = [
                        'old_value' => $udata['commission_selfgen'],
                        'new_value' => $reqdata['commission_selfgen'],
                    ];
                }
                if (! empty($reqdata['commission_selfgen_type']) && $udata['commission_selfgen_type'] != $reqdata['commission_selfgen_type']) {
                    $data['commission_selfgen_type'] = [
                        'old_value' => $udata['commission_selfgen_type'],
                        'new_value' => $reqdata['commission_selfgen_type'],
                    ];
                }
                if (! empty($reqdata['commission_selfgen_effective_date']) && $udata['commission_selfgen_effective_date'] != $reqdata['commission_selfgen_effective_date']) {
                    $data['commission_selfgen_effective_date'] = [
                        'old_value' => (! empty($udata['commission_selfgen_effective_date'])) ? date('m-d-Y', strtotime($udata['commission_selfgen_effective_date'])) : '',
                        'new_value' => (! empty($reqdata['commission_selfgen_effective_date'])) ? date('m-d-Y', strtotime($reqdata['commission_selfgen_effective_date'])) : '',
                    ];
                }
            }
        }

        return $data;
    }

    private function organizationDataComp($udata, $reqdata)
    {
        $data = [];
        if (! empty($udata) && ! empty($reqdata)) {

            $oldAdditionalRecruiters = ['', ''];
            $newAdditionalRecruiters = ['', ''];

            // Process old additional recruiters
            if (! empty($udata['additional_recruter'])) {
                foreach ($udata['additional_recruter'] as $key => $value) {
                    if ($key < 2) {
                        $oldAdditionalRecruiters[$key] = $value['recruiter_id'];
                    }
                }
            }

            // Process new additional recruiters
            if (! empty($reqdata['employee_originization']['additional_recruiter_id'])) {
                foreach ($reqdata['employee_originization']['additional_recruiter_id'] as $key => $value) {
                    if ($key < 2) {
                        $newAdditionalRecruiters[$key] = $value;
                    }
                }
            }

            // Compare manager
            $this->compareFields($data, 'manager', $udata['manager_id'], $reqdata['employee_originization']['manager_id'], User::class);

            // Compare team
            $this->compareFields($data, 'team', $udata['team_id'], $reqdata['employee_originization']['team_id'], ManagementTeam::class, 'team_name');

            // Compare recruiter
            $this->compareFields($data, 'recruiter', $udata['recruiter_id'], $reqdata['employee_originization']['recruiter_id'], User::class);

            // Compare additional recruiters
            foreach ([0, 1] as $key) {
                if ($oldAdditionalRecruiters[$key] != $newAdditionalRecruiters[$key]) {
                    $this->compareFields($data, 'additional_recruiter'.($key + 1), $oldAdditionalRecruiters[$key], $newAdditionalRecruiters[$key], User::class);
                }
            }

            // Compare effective date
            if (isset($reqdata['employee_originization']['effective_date']) && $udata['manager_id_effective_date'] != $reqdata['employee_originization']['effective_date']) {
                $data['position_effective_date'] = [
                    'old_value' => ! empty($udata['manager_id_effective_date']) ? date('m-d-Y', strtotime($udata['manager_id_effective_date'])) : '',
                    'new_value' => ! empty($reqdata['employee_originization']['effective_date']) ? date('m-d-Y', strtotime($reqdata['employee_originization']['effective_date'])) : '',
                ];
            }
        }

        return $data;
    }

    public function userDataById($id)
    {
        $user = User::orderBy('id', 'desc')->with('office', 'userSelfGenCommission')->newQuery();
        $user->with('departmentDetail', 'positionDetail', 'state', 'city', 'managerDetail', 'statusDetail', 'recruiter', 'additionalDetail', 'subpositionDetail', 'teamsDetail', 'recruiter');
        $data = $user->where('id', $id)->first();

        $totalMember = User::where('manager_id', $id)->count();
        if (isset($data) && $data != '') {
            $data->additionalDetail;
            $additional = [];
            /* getting recruiter data */
            foreach ($data->additionalDetail as $deductionName) {

                $additional[] =
                    [
                        'id' => isset($deducationname->id) ? $deducationname->id : null,
                        'recruiter_id' => isset($deducationname->recruiter_id) ? $deducationname->recruiter_id : null,
                        'recruiter_first_name' => isset($deducationname->additionalRecruiterDetail->first_name) ? $deducationname->additionalRecruiterDetail->first_name : null,
                        'recruiter_last_name' => isset($deducationname->additionalRecruiterDetail->last_name) ? $deducationname->additionalRecruiterDetail->last_name : null,
                        'system_per_kw_amount' => isset($deducationname->system_per_kw_amount) ? $deducationname->system_per_kw_amount : null,
                    ];
            }
            // echo $id;die;
            $additional_location = '';
            $latest_effective_date = AdditionalLocations::where('effective_date', '<=', date('Y-m-d'))->select('effective_date')->orderBy('effective_date', 'desc')->groupBy('effective_date')->first();
            if (isset($latest_effective_date->effective_date)) {
                $additional_location = AdditionalLocations::with('state', 'office')->where('user_id', $id)->where('effective_date', $latest_effective_date->effective_date)->get();
            }
            $currentDate = now()->toDateString();

            if ($additional_location) {
                $additional_location->transform(function ($data) use ($currentDate) {
                    if (isset($data->office->id) && isset($data->user_id)) {
                        $overrideEffectiveDate = UserAdditionalOfficeOverrideHistory::where('override_effective_date', '<=', $currentDate)->where('user_id', $data->user_id)->where('office_id', $data->office->id)->orderBy('override_effective_date', 'desc')->first();

                        return [

                            'effective_date' => isset($data->effective_date) ? $data->effective_date : null,
                            'state_id' => isset($data->state_id) ? $data->state_id : null,
                            'state_name' => isset($data->state->name) ? $data->state->name : null,
                            'office_id' => isset($data->office->id) ? $data->office->id : null,
                            'office_name' => isset($data->office->office_name) ? $data->office->office_name : null,
                            'overrides_amount' => isset($overrideEffectiveDate->office_overrides_amount) ? $overrideEffectiveDate->office_overrides_amount : null,
                            'overrides_type' => isset($overrideEffectiveDate->office_overrides_type) ? $overrideEffectiveDate->office_overrides_type : null,
                            // 'history_overrides_amount' => isset($overrideEffectiveDate->office_overrides_amount) ? $overrideEffectiveDate->office_overrides_amount : NULL,
                            // 'history_overrides_type' => isset($overrideEffectiveDate->office_overrides_type) ? $overrideEffectiveDate->office_overrides_type : NULL,
                        ];
                    }
                });
            }

            $user_redline_data = UserRedlines::where('user_id', $id)->get();
            if ($user_redline_data) {
                $user_redline_data->transform(function ($data) {
                    return [
                        'user_id' => isset($data->user_id) ? $data->user_id : null,
                        'updater_id' => isset($data->updater_id) ? $data->updater_id : null,
                        'redline' => isset($data->redline) ? $data->redline : null,
                        'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                        'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                        'start_date' => isset($data->start_date) ? $data->start_date : null,

                    ];
                });

                $user_redline_data = UserRedlines::where('user_id', $id)->where('position_type', 1)->get();
                if ($user_redline_data) {
                    $user_redline_data->transform(function ($data) {
                        return [
                            'user_id' => isset($data->user_id) ? $data->user_id : null,
                            'updater_id' => isset($data->updater_id) ? $data->updater_id : null,
                            'redline' => isset($data->redline) ? $data->redline : null,
                            'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                            'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                            'start_date' => isset($data->start_date) ? $data->start_date : null,

                        ];
                    });

                    $self_gen_redline_data = UserRedlines::where('user_id', $id)->where('position_type', 2)->get();
                    if ($self_gen_redline_data) {
                        $self_gen_redline_data->transform(function ($data) {
                            return [
                                'user_id' => isset($data->user_id) ? $data->user_id : null,
                                'updater_id' => isset($data->updater_id) ? $data->updater_id : null,
                                'redline' => isset($data->redline) ? $data->redline : null,
                                'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                                'redline_amount_type' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                                'start_date' => isset($data->start_date) ? $data->start_date : null,

                            ];
                        });

                        $employee_compensation_result = [];
                        
                        // Check if Custom Sales Fields feature is enabled (for display formatting, using cached helper)
                        $isCustomFieldsEnabledForDisplay = CustomSalesFieldHelper::isFeatureEnabled();

                        // if(empty($employee_compensation_result)){
                        $employee_compensation_result[$data->sub_position_id]['commission'] = isset($data->commission) ? $data->commission : null;
                        // Only use custom_field_X format if feature enabled AND BOTH commission_type is 'custom field' AND custom_sales_field_id is set
                        $employee_compensation_result[$data->sub_position_id]['commission_type'] = ($isCustomFieldsEnabledForDisplay && $data->commission_type === 'custom field' && $data->commission_custom_sales_field_id) ? 'custom_field_' . $data->commission_custom_sales_field_id : (isset($data->commission_type) ? $data->commission_type : null);
                        $employee_compensation_result[$data->sub_position_id]['commission_custom_sales_field_id'] = ($isCustomFieldsEnabledForDisplay && $data->commission_type === 'custom field') ? ($data->commission_custom_sales_field_id ?? null) : null;
                        $employee_compensation_result[$data->sub_position_id]['commission_effective_date'] = isset($data->commission_effective_date) ? $data->commission_effective_date : dateToYMD($data->period_of_agreement_start_date);
                        $employee_compensation_result[$data->sub_position_id]['commission_position_id'] = $data->sub_position_id;
                        $employee_compensation_result[$data->sub_position_id]['upfront_pay_amount'] = isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null;
                        // Only use custom_field_X format if feature enabled AND BOTH upfront_sale_type is 'custom field' AND custom_sales_field_id is set
                        $employee_compensation_result[$data->sub_position_id]['upfront_sale_type'] = ($isCustomFieldsEnabledForDisplay && $data->upfront_sale_type === 'custom field' && $data->upfront_custom_sales_field_id) ? 'custom_field_' . $data->upfront_custom_sales_field_id : (isset($data->upfront_sale_type) ? $data->upfront_sale_type : null);
                        $employee_compensation_result[$data->sub_position_id]['upfront_custom_sales_field_id'] = ($isCustomFieldsEnabledForDisplay && $data->upfront_sale_type === 'custom field') ? ($data->upfront_custom_sales_field_id ?? null) : null;
                        $employee_compensation_result[$data->sub_position_id]['upfront_effective_date'] = isset($data->upfront_effective_date) ? $data->upfront_effective_date : dateToYMD($data->period_of_agreement_start_date);
                        $employee_compensation_result[$data->sub_position_id]['upfront_position_id'] = $data->sub_position_id;
                        $employee_compensation_result[$data->sub_position_id]['withheld_amount'] = isset($data->withheld_amount) ? $data->withheld_amount : null;
                        $employee_compensation_result[$data->sub_position_id]['withheld_type'] = isset($data->withheld_type) ? $data->withheld_type : null;
                        $employee_compensation_result[$data->sub_position_id]['withheld_effective_date'] = isset($data->withheld_effective_date) ? $data->withheld_effective_date : dateToYMD($data->period_of_agreement_start_date);
                        $employee_compensation_result[$data->sub_position_id]['withheld_position_id'] = $data->sub_position_id;
                        $employee_compensation_result[$data->sub_position_id]['redline_amount_type'] = isset($data->redline_amount_type) ? $data->redline_amount_type : null;
                        $employee_compensation_result[$data->sub_position_id]['redline'] = isset($data->redline) ? $data->redline : null;
                        $employee_compensation_result[$data->sub_position_id]['redline_type'] = isset($data->redline_type) ? $data->redline_type : null;
                        $employee_compensation_result[$data->sub_position_id]['redline_effective_date'] = isset($data->redline_effective_date) ? $data->redline_effective_date : dateToYMD($data->period_of_agreement_start_date);
                        $employee_compensation_result[$data->sub_position_id]['redline_position_id'] = $data->sub_position_id;
                        if (! empty($data->self_gen_type)) {
                            $employee_compensation_result[$data->self_gen_type]['commission'] = isset($data->self_gen_commission) ? $data->self_gen_commission : null;
                            // Only use custom_field_X format if feature enabled AND BOTH commission_type is 'custom field' AND custom_sales_field_id is set
                            $employee_compensation_result[$data->self_gen_type]['commission_type'] = ($isCustomFieldsEnabledForDisplay && $data->self_gen_commission_type === 'custom field' && $data->self_gen_commission_custom_sales_field_id) ? 'custom_field_' . $data->self_gen_commission_custom_sales_field_id : (isset($data->self_gen_commission_type) ? $data->self_gen_commission_type : null);
                            $employee_compensation_result[$data->self_gen_type]['commission_custom_sales_field_id'] = ($isCustomFieldsEnabledForDisplay && $data->self_gen_commission_type === 'custom field') ? ($data->self_gen_commission_custom_sales_field_id ?? null) : null;
                            $employee_compensation_result[$data->self_gen_type]['commission_effective_date'] = isset($data->self_gen_commission_effective_date) ? $data->self_gen_commission_effective_date : dateToYMD($data->period_of_agreement_start_date);
                            $employee_compensation_result[$data->self_gen_type]['commission_position_id'] = $data->self_gen_type;
                            $employee_compensation_result[$data->self_gen_type]['upfront_pay_amount'] = isset($data->self_gen_upfront_amount) ? $data->self_gen_upfront_amount : null;
                            $employee_compensation_result[$data->self_gen_type]['upfront_sale_type'] = isset($data->self_gen_upfront_type) ? $data->self_gen_upfront_type : null;
                            $employee_compensation_result[$data->self_gen_type]['upfront_effective_date'] = isset($data->self_gen_upfront_effective_date) ? $data->self_gen_upfront_effective_date : dateToYMD($data->period_of_agreement_start_date);
                            $employee_compensation_result[$data->self_gen_type]['upfront_position_id'] = $data->self_gen_type;
                            $employee_compensation_result[$data->self_gen_type]['withheld_amount'] = isset($data->self_gen_withheld_amount) ? $data->self_gen_withheld_amount : null;
                            $employee_compensation_result[$data->self_gen_type]['withheld_type'] = isset($data->self_gen_withheld_type) ? $data->self_gen_withheld_type : null;
                            $employee_compensation_result[$data->self_gen_type]['withheld_effective_date'] = isset($data->self_gen_withheld_effective_date) ? $data->self_gen_withheld_effective_date : dateToYMD($data->period_of_agreement_start_date);
                            $employee_compensation_result[$data->self_gen_type]['withheld_position_id'] = $data->self_gen_type;
                            $employee_compensation_result[$data->self_gen_type]['redline_amount_type'] = isset($data->self_gen_redline_amount_type) ? $data->self_gen_redline_amount_type : null;
                            $employee_compensation_result[$data->self_gen_type]['redline'] = isset($data->self_gen_redline) ? $data->self_gen_redline : null;
                            $employee_compensation_result[$data->self_gen_type]['redline_type'] = isset($data->self_gen_redline_type) ? $data->self_gen_redline_type : null;
                            $employee_compensation_result[$data->self_gen_type]['redline_effective_date'] = isset($data->self_gen_redline_effective_date) ? $data->self_gen_redline_effective_date : dateToYMD($data->period_of_agreement_start_date);
                            $employee_compensation_result[$data->self_gen_type]['redline_position_id'] = $data->self_gen_type;
                        }

                        // }

                        $ecr = $employee_compensation_result;
                        $employee_compensation_result = [];
                        foreach ($ecr as $e) {
                            $employee_compensation_result[] = $e;
                        }

                        $Employee_Manager_Position = $Employee_Manager_Department = null;
                        if (isset($data->managerDetail)) {
                            $managerDetail_data = User::where('id', $data->managerDetail->id)->first();
                            if (! empty($managerDetail_data) && $managerDetail_data != null) {
                                $Employee_Manager_Position = isset($managerDetail_data->positionDetail) ? $managerDetail_data->positionDetail->position_name : null;
                                $Employee_Manager_Department = isset($managerDetail_data->departmentDetail) ? $managerDetail_data->departmentDetail->name : null;
                            }
                        }

                        if (isset($data->recruiter->first_name, $data->recruiter->last_name)) {

                            $recruiter_name = $data->recruiter->first_name.' '.$data->recruiter->last_name;
                        } else {
                            if ($data->recruiter) {

                                $recruiter_name = $data->recruiter->first_name;
                            } else {
                                $recruiter_name = null;
                            }
                        }

                        $data1 =
                            [
                                'id' => isset($data->id) ? $data->id : null,
                                'first_name' => isset($data->first_name) ? $data->first_name : null,
                                'middle_name' => isset($data->middle_name) ? $data->middle_name : null,
                                'last_name' => isset($data->last_name) ? $data->last_name : null,
                                'sex' => isset($data->sex) ? $data->sex : null,
                                'dob' => isset($data->dob) ? dateToYMD($data->dob) : null,
                                'image' => isset($data->image) ? $data->image : null,
                                'office_id' => isset($data->office_id) ? $data->office_id : null,
                                'zip_code' => isset($data->zip_code) ? $data->zip_code : null,
                                'email' => isset($data->email) ? $data->email : null,
                                'is_manager' => isset($data->is_manager) ? $data->is_manager : null,
                                'is_manager_effective_date' => isset($data->is_manager_effective_date) ? $data->is_manager_effective_date : null,
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
                                'manager_id_effective_date' => isset($data->manager_id_effective_date) ? $data->manager_id_effective_date : null,
                                'manager_name' => isset($data->managerDetail->id) ? $data->managerDetail->name : null,
                                'Employee_Manager_Position' => $Employee_Manager_Position,
                                'Employee_Manager_Department' => $Employee_Manager_Department,
                                'team_id' => isset($data->team_id) ? $data->team_id : null,
                                'team_id_effective_date' => isset($data->team_id_effective_date) ? $data->team_id_effective_date : null,
                                'team_name' => isset($data->teamsDetail->team_name) ? $data->teamsDetail->team_name : null,
                                'status_id' => isset($data->status_id) ? $data->status_id : null,
                                'status_name' => isset($data->statusDetail->status) ? $data->statusDetail->status : null,
                                'recruiter_id' => isset($data->recruiter_id) ? $data->recruiter_id : null,
                                // 'recruiter_name' =>isset($data->recruiter->first_name)?$data->recruiter->first_name:null,
                                'recruiter_name' => $recruiter_name,
                                'offer_include_bonus' => ($data->hiring_bonus_amount > 0) ? 1 : 0,
                                // 'additional_recruiter' => $additional,
                                'position_id' => isset($data->position_id) ? $data->position_id : null,
                                'position_name' => isset($data->positionDetail->position_name) ? $data->positionDetail->position_name : null,
                                'sub_position_id' => isset($data->sub_position_id) ? $data->sub_position_id : null,
                                'sub_position_name' => isset($data->subpositionDetail->position_name) ? $data->subpositionDetail->position_name : null,
                                'redline' => isset($data->redline_amount_type) ? $data->redline_amount_type : null,
                                'redline_amount' => isset($data->redline) ? $data->redline : null,
                                'redline_type' => isset($data->redline_type) ? $data->redline_type : null,
                                'self_gen_redline' => isset($data->self_gen_redline) ? $data->self_gen_redline : null,
                                'self_gen_redline_amount_type' => isset($data->self_gen_redline_amount_type) ? $data->self_gen_redline_amount_type : null,
                                'self_gen_redline_type' => isset($data->self_gen_redline_type) ? $data->self_gen_redline_type : null,
                                'self_gen_commission' => isset($data->self_gen_commission) ? $data->self_gen_commission : null,
                                // Only use custom_field_X format if feature enabled
                                'self_gen_commission_type' => ($isCustomFieldsEnabledForDisplay && $data->self_gen_commission_type === 'custom field' && $data->self_gen_commission_custom_sales_field_id) ? 'custom_field_' . $data->self_gen_commission_custom_sales_field_id : (isset($data->self_gen_commission_type) ? $data->self_gen_commission_type : null),
                                'self_gen_upfront_amount' => isset($data->self_gen_upfront_amount) ? $data->self_gen_upfront_amount : null,
                                'self_gen_upfront_type' => isset($data->self_gen_upfront_type) ? $data->self_gen_upfront_type : null,
                                'upfront_pay_amount' => isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null,
                                'upfront_sale_type' => ($isCustomFieldsEnabledForDisplay && $data->upfront_sale_type === 'custom field' && $data->upfront_custom_sales_field_id) ? 'custom_field_' . $data->upfront_custom_sales_field_id : (isset($data->upfront_sale_type) ? $data->upfront_sale_type : null),
                                'override_effective_date' => isset($data->override_effective_date) ? $data->override_effective_date : null,
                                'direct_overrides_amount' => isset($data->direct_overrides_amount) ? $data->direct_overrides_amount : null,
                                'direct_overrides_type' => ($isCustomFieldsEnabledForDisplay && $data->direct_overrides_type === 'custom field' && $data->direct_custom_sales_field_id) ? 'custom_field_' . $data->direct_custom_sales_field_id : (isset($data->direct_overrides_type) ? $data->direct_overrides_type : null),
                                'indirect_overrides_amount' => isset($data->indirect_overrides_amount) ? $data->indirect_overrides_amount : null,
                                'indirect_overrides_type' => ($isCustomFieldsEnabledForDisplay && $data->indirect_overrides_type === 'custom field' && $data->indirect_custom_sales_field_id) ? 'custom_field_' . $data->indirect_custom_sales_field_id : (isset($data->indirect_overrides_type) ? $data->indirect_overrides_type : null),
                                'office_overrides_amount' => isset($data->office_overrides_amount) ? $data->office_overrides_amount : null,
                                'office_overrides_type' => ($isCustomFieldsEnabledForDisplay && $data->office_overrides_type === 'custom field' && $data->office_custom_sales_field_id) ? 'custom_field_' . $data->office_custom_sales_field_id : (isset($data->office_overrides_type) ? $data->office_overrides_type : null),
                                'office_stack_overrides_amount' => isset($data->office_stack_overrides_amount) ? $data->office_stack_overrides_amount : null,
                                'withheld_amount' => isset($data->withheld_amount) ? $data->withheld_amount : null,
                                'withheld_type' => isset($data->withheld_type) ? $data->withheld_type : null,
                                'self_gen_withheld_amount' => isset($data->self_gen_withheld_amount) ? $data->self_gen_withheld_amount : null,
                                'self_gen_withheld_type' => isset($data->self_gen_withheld_type) ? $data->self_gen_withheld_type : null,
                                'probation_period' => isset($data->probation_period) && $data->probation_period != 'None' ? $data->probation_period : null,
                                'commission' => isset($data->commission) ? $data->commission : null,
                                'commission_type' => ($isCustomFieldsEnabledForDisplay && $data->commission_type === 'custom field' && $data->commission_custom_sales_field_id) ? 'custom_field_' . $data->commission_custom_sales_field_id : (isset($data->commission_type) ? $data->commission_type : null),
                                // Custom Sales Field IDs - only include if feature enabled AND type is 'custom field'
                                'commission_custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $data->commission_type === 'custom field') ? ($data->commission_custom_sales_field_id ?? null) : null,
                                'self_gen_commission_custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $data->self_gen_commission_type === 'custom field') ? ($data->self_gen_commission_custom_sales_field_id ?? null) : null,
                                'upfront_custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $data->upfront_sale_type === 'custom field') ? ($data->upfront_custom_sales_field_id ?? null) : null,
                                'direct_custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $data->direct_overrides_type === 'custom field') ? ($data->direct_custom_sales_field_id ?? null) : null,
                                'indirect_custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $data->indirect_overrides_type === 'custom field') ? ($data->indirect_custom_sales_field_id ?? null) : null,
                                'office_custom_sales_field_id' => ($isCustomFieldsEnabledForDisplay && $data->office_overrides_type === 'custom field') ? ($data->office_custom_sales_field_id ?? null) : null,
                                'hiring_bonus_amount' => isset($data->hiring_bonus_amount) ? $data->hiring_bonus_amount : null,
                                'date_to_be_paid' => isset($data->date_to_be_paid) ? dateToYMD($data->date_to_be_paid) : null,
                                'period_of_agreement_start_date' => isset($data->period_of_agreement_start_date) ? dateToYMD($data->period_of_agreement_start_date) : null,
                                'end_date' => isset($data->end_date) ? dateToYMD($data->end_date) : null,
                                'offer_expiry_date' => isset($data->offer_expiry_date) ? $data->offer_expiry_date : null,
                                'hired_date' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
                                'type' => isset($data->type) ? $data->type : null,
                                'office' => isset($data->office) ? $data->office : null,
                                'additional_recruter' => isset($additional) ? $additional : null,
                                'additional_locations' => isset($additional_location) ? $additional_location : null,
                                'redline_data' => isset($user_redline_data) ? $user_redline_data : null,
                                'self_gen_redline_data' => isset($self_gen_redline_data) ? $self_gen_redline_data : null,
                                'total_employee' => isset($totalMember) ? $totalMember : 0,
                                'employee_compensation' => $employee_compensation_result,
                                'commission_selfgen' => isset($data->userSelfGenCommission[0]->commission) ? $data->userSelfGenCommission[0]->commission : 0,
                                'commission_selfgen_type' => isset($data->userSelfGenCommission[0]->commission_type) ? $data->userSelfGenCommission[0]->commission_type : null,
                                'commission_selfgen_effective_date' => isset($data->userSelfGenCommission[0]->commission_effective_date) ? $data->userSelfGenCommission[0]->commission_effective_date : null,

                                // 'created_at' => $data->created_at,
                                // 'updated_at' => $data->updated_at,

                            ];

                        $company = CompanySetting::where('type', 'reconciliation')->first();

                        if ($company->status == 1) {
                            $withHeld = PositionReconciliations::where('position_id', $data->sub_position_id)->where('status', 1)->first();
                            if ($withHeld) {
                                $data1['withheld'] = $withHeld->commission_withheld;
                            } else {
                                $data1['withheld'] = null;
                            }
                        } else {
                            $data1['withheld'] = null;
                        }

                        $deduction = UserDeduction::where('user_id', $id)->get();

                        if (! empty($deduction) && $deduction != '[]') {

                            foreach ($deduction as $deductions) {
                                $data1['deduction'][] = [
                                    'deduction_id' => $deductions->id,
                                    'effective_date' => $deductions->effective_date,
                                    'deduction_type' => $deductions->deduction_type,
                                    'cost_center_name' => $deductions->cost_center_name,
                                    'cost_center_id' => $deductions->cost_center_id,
                                    'ammount_par_paycheck' => $deductions->ammount_par_paycheck,
                                    'deduction_setting_id' => isset($deductions->deduction_setting_id) ? $deductions->deduction_setting_id : null,
                                    'position_id' => $deductions->position_id,
                                    'user_id' => $deductions->user_id,
                                ];
                            }
                        } else {
                            $data1['deduction'] = [
                                // 'deduction_type' => '',
                                // 'cost_center_name' => '',
                                // 'cost_center_id' => '',
                                // 'ammount_par_paycheck' => '',
                                // 'deduction_setting_id' => '',
                                // 'position_id' => '',
                                // 'user_id' => ''

                            ];
                        }

                        return $data1;
                    }
                }
            }
        }
    }

    private function compareFields(&$data, $field, $oldValue, $newValue, $model, $selectField = 'first_name')
    {
        if ($oldValue != $newValue && $selectField != 'team_name') {
            $old = $model::select('first_name', 'last_name')->where('id', $oldValue)->first();
            $new = $model::select('first_name', 'last_name')->where('id', $newValue)->first();
            $data[$field] = [
                'old_value' => $old ? $old->first_name.' '.$old->last_name : null,
                'new_value' => $new ? $new->first_name.' '.$new->last_name : null,
            ];
        }
    }

    private function userwages($id, $data)
    {
        $empWages = [];
        $userWagesHistory = UserWagesHistory::where(['user_id' => $id])
            ->where('effective_date', '<=', date('Y-m-d'))
            ->orderBy('effective_date', 'desc')
            ->first();
        if ($userWagesHistory) {
            $employeeWages = $userWagesHistory;
        } else {
            $employeeWages = $data; // Fallback if no wage history
        }
        $empWages = [
            'pay_type' => $employeeWages->pay_type ?? null,
            'pay_rate' => $employeeWages->pay_rate ?? null,
            'pay_rate_type' => $employeeWages->pay_rate_type ?? null,
            'pto_hours' => $employeeWages->pto_hours ?? null,
            'unused_pto_expires' => $employeeWages->unused_pto_expires ?? null,
            'expected_weekly_hours' => $employeeWages->expected_weekly_hours ?? null,
            'overtime_rate' => $employeeWages->overtime_rate ?? null,
            'effective_date' => $employeeWages->effective_date ?? null,
            'pto_hours_effective_date' => $employeeWages->pto_hours_effective_date ?? null,
        ];

        return $empWages;
    }

    private function useragreement($id, $data)
    {
        $empagreement = [];
        $userAgreement = UserAgreementHistory::with('hiringby')->where(['user_id' => $id])->first();
        if ($userAgreement) {
            $employeeAgreement = $userAgreement;
        } else {
            $employeeAgreement = $data; // Fallback if no wage history
        }
        $empagreement = [
            'hired_date' => isset($data->created_at) ? date('Y-m-d', strtotime($data->created_at)) : null,
            'probation_period' => isset($employeeAgreement->probation_period) && $employeeAgreement->probation_period != 'None' ? $employeeAgreement->probation_period : null,
            'hiring_bonus_amount' => isset($employeeAgreement->hiring_bonus_amount) ? $employeeAgreement->hiring_bonus_amount : null,
            'date_to_be_paid' => isset($employeeAgreement->date_to_be_paid) ? dateToYMD($employeeAgreement->date_to_be_paid) : null,
            'period_of_agreement_start_date' => isset($employeeAgreement->period_of_agreement) ? dateToYMD($employeeAgreement->period_of_agreement) : null,
            'end_date' => isset($employeeAgreement->end_date) ? $employeeAgreement->end_date : null,
            'offer_include_bonus' => isset($employeeAgreement->offer_include_bonus) ? $employeeAgreement->offer_include_bonus : null,
            'offer_expiry_date' => isset($employeeAgreement->offer_expiry_date) ? $employeeAgreement->offer_expiry_date : null,
            'is_background_verificaton' => isset($employeeAgreement->is_background_verificaton) ? $employeeAgreement->is_background_verificaton : null,
            'hiring_signature' => isset($employeeAgreement->hiring_signature) ? $employeeAgreement->hiring_signature : null,
            'hired_by_uid' => isset($employeeAgreement->hired_by_uid) ? $employeeAgreement->hired_by_uid : null,
            'hiring_by' => isset($employeeAgreement->hiringby->first_name) ? $employeeAgreement->hiringby->first_name.' '.$employeeAgreement->hiringby->last_name : '',
        ];

        return $empagreement;
    }

    public function commissionCheckr($userId, $product_id = '', $core_position_id = '')
    {
        $currentDate = Carbon::today()->format('Y-m-d');
        // Find the closest effective date
        $closestDate = UserCommissionHistory::where('user_id', $userId)
            ->when($product_id, fn ($q) => $q->where('product_id', $product_id))
            ->when($core_position_id !== null, fn ($q) => $q->where('core_position_id', $core_position_id ?: null))
            ->where(function ($query) use ($currentDate) {
                $query->whereDate('commission_effective_date', '<=', $currentDate)
                    ->orWhereDate('commission_effective_date', '>', $currentDate);
            })
            ->orderByRaw('CASE WHEN commission_effective_date <= ? THEN 0 ELSE 1 END, commission_effective_date DESC', [$currentDate])
            ->value('commission_effective_date');

        $query = UserCommissionHistory::where('user_id', $userId)
            ->when($product_id != '', function ($q) use ($product_id) {
                $q->where('product_id', $product_id);
            })
            ->when($core_position_id !== null, function ($q) use ($core_position_id) {
                $q->where('core_position_id', $core_position_id == 0 ? null : $core_position_id);
            })
            ->whereDate('commission_effective_date', $closestDate)
            ->orderBy('commission_effective_date');
        if (($core_position_id != '' && $product_id != '')) {
            $rdata = $query->first();
            if (isset($rdata->id)) {
                $rdata->tiers_range = $this->getrange($rdata->id, 'commission');
            }
        } else {
            $rdata = $query->get();
        }

        return $rdata;
    }

    public function upfrontCheckr($userId, $product_id = '', $core_position_id = '')
    {
        $currentDate = Carbon::today()->format('Y-m-d');
        // Find the closest effective date
        $closestDate = UserUpfrontHistory::where('user_id', $userId)
            ->when($product_id, fn ($q) => $q->where('product_id', $product_id))
            ->when($core_position_id !== null, fn ($q) => $q->where('core_position_id', $core_position_id ?: null))
            ->where(function ($query) use ($currentDate) {
                $query->whereDate('upfront_effective_date', '<=', $currentDate)
                    ->orWhereDate('upfront_effective_date', '>', $currentDate);
            })
            ->orderByRaw('CASE WHEN upfront_effective_date <= ? THEN 0 ELSE 1 END, upfront_effective_date DESC', [$currentDate])
            ->value('upfront_effective_date');

        // Fetch records matching the closest date and map tiers range
        return UserUpfrontHistory::where('user_id', $userId)
            ->when($product_id, fn ($q) => $q->where('product_id', $product_id))
            ->when($core_position_id !== null, fn ($q) => $q->where('core_position_id', $core_position_id ?: null))
            ->whereDate('upfront_effective_date', $closestDate)
            ->get()
            ->map(function ($record) {
                $record->tiers_range = $this->getrange($record->id, 'upfront');

                return $record;
            });
    }

    public function withHeldCheckr($userId, $product_id = '', $core_position_id = '')
    {
        $query = UserWithheldHistory::where('user_id', $userId)
            ->when($product_id != '', function ($q) use ($product_id) {
                $q->where('product_id', $product_id);
            })
            /*->when($core_position_id != '', function($q) use ($core_position_id) {
                $q->where('core_position_id', $core_position_id);
            })*/
            ->where('withheld_effective_date', '<=', date('Y-m-d'))
            ->orderBy('withheld_effective_date');

        return ($product_id != '') ? $query->first() : $query->get();
    }

    public function redLineCheckr($userId, $product_id = '', $core_position_id = '')
    {
        $query = UserRedlines::select('*', 'start_date as redline_effective_date')->where('user_id', $userId)
            ->when($core_position_id !== null, function ($query) use ($core_position_id) {
                $query->where('core_position_id', $core_position_id == 0 ? null : $core_position_id);
            })
            ->where('start_date', '<=', date('Y-m-d'))
            ->orderBy('start_date');

        return ($core_position_id != '') ? $query->first() : $query->get();
    }

    public function overRideCheckr($userId, $product_id = '')
    {
        $overRides = UserOverrideHistory::where('user_id', $userId)
            ->when($product_id, function ($query) use ($product_id) {
                return $query->where('product_id', $product_id);
            })
            ->where('override_effective_date', '<=', date('Y-m-d'))
            ->orderBy('override_effective_date')
            ->first();
        if (isset($overRides->id)) {
            $overRides->direct_tiers = $this->getrange($overRides->id, 'direct');
            $overRides->indirect_tiers = $this->getrange($overRides->id, 'indirect');
            $overRides->office_tiers = $this->getrange($overRides->id, 'overrideoffice');
        }

        return $overRides;
    }

    public function selfGenCommissionCheckr($userId)
    {
        $selfGenCommissions = UserSelfGenCommmissionHistory::where(['user_id' => $userId])->where('commission_effective_date', '<=', date('Y-m-d'))->orderBy('commission_effective_date')->get();

        $com = '';
        foreach ($selfGenCommissions as $selfGenCommission) {
            if (! $com) {
                $com = $selfGenCommission;
            } else {
                if ($selfGenCommission->commission != $com->commission || $selfGenCommission->commission_type != $com->commission_type || $selfGenCommission->position_id != $com->position_id || $selfGenCommission->sub_position_id != $com->sub_position_id) {
                    $com = $selfGenCommission;
                }
            }
        }

        return $com;
    }

    public function additionalOfficeChecker($userId, $officeId)
    {
        $additionalOffices = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'office_id' => $officeId])->where('override_effective_date', '<=', date('Y-m-d'))->orderBy('override_effective_date')->get();

        $additional = '';
        foreach ($additionalOffices as $additionalOffice) {
            if (! $additional) {
                $additional = $additionalOffice;
            } else {
                if ($additionalOffice->state_id != $additional->state_id || $additionalOffice->office_overrides_amount != $additional->office_overrides_amount || $additionalOffice->office_overrides_type != $additional->office_overrides_type) {
                    $additional = $additionalOffice;
                }
            }
        }

        return $additional;
    }

    public function organizationCheckr($userId)
    {
        $organizations = UserOrganizationHistory::where(['user_id' => $userId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date')->get();

        $org = '';
        foreach ($organizations as $organization) {
            if (! $org) {
                $org = $organization;
            } else {
                if ($organization->manager_id != $org->manager_id || $organization->team_id != $org->team_id || $organization->position_id != $org->position_id || $organization->sub_position_id != $org->sub_position_id) {
                    $org = $organization;
                }
            }
        }

        return $org;
    }

    public function managerCheckr($userId)
    {
        $managers = UserManagerHistory::with('team')->where(['user_id' => $userId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date')->get();

        $man = '';
        foreach ($managers as $manager) {
            if (! $man) {
                $man = $manager;
            } else {
                if ($manager->manager_id != $man->manager_id) {
                    $man = $manager;
                }
            }
        }

        $team = '';
        foreach ($managers as $manager) {
            if (! $team) {
                $team = $manager;
            } else {
                if ($manager->team_id != $team->team_id) {
                    $team = $manager;
                }
            }
        }
        if ($man) {
            $man->team_id = @$team->team_id;
            $man->team_effetctive_date = @$team->effective_date;
            $man->team = @$team->team;
        }

        return $man;
    }

    public function isManagerCheckr($userId)
    {
        $managers = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date')->get();

        $man = '';
        foreach ($managers as $manager) {
            if (! $man) {
                $man = $manager;
            } else {
                if ($manager->is_manager != $man->is_manager) {
                    $man = $manager;
                }
            }
        }

        return $man;
    }

    public function overridesDataComp($udata, $reqdata)
    {
        $data = [];
        if (! empty($udata) && ! empty($reqdata)) {
            $fields = [
                'override_effective_date',
                'direct_overrides_amount',
                'direct_overrides_type',
                'indirect_overrides_amount',
                'indirect_overrides_type',
                'office_overrides_amount',
                'office_overrides_type',
                'office_stack_overrides_amount',
            ];

            foreach ($fields as $field) {
                if (isset($reqdata['employee_override'][$field]) && $udata[$field] != $reqdata['employee_override'][$field]) {
                    $data[$field] = [
                        'old_value' => ! empty($udata[$field]) ? ($field === 'override_effective_date' ? date('m-d-Y', strtotime($udata[$field])) : $udata[$field]) : '',
                        'new_value' => ! empty($reqdata['employee_override'][$field]) ? ($field === 'override_effective_date' ? date('m-d-Y', strtotime($reqdata['employee_override'][$field])) : $reqdata['employee_override'][$field]) : '',
                    ];
                }
            }
        }

        return $data;
    }

    private function getrange($id, $type)
    {
        if ($type == 'commission') {
            return UserCommissionHistoryTiersRange::where('user_commission_history_id', $id)->get();
        } elseif ($type == 'upfront') {
            return UserUpfrontHistoryTiersRange::where('user_upfront_history_id', $id)->get();
        } elseif ($type == 'direct') {
            return UserDirectOverrideHistoryTiersRange::where('user_override_history_id', $id)->get();
        } elseif ($type == 'indirect') {
            return UserIndirectOverrideHistoryTiersRange::where('user_override_history_id', $id)->get();
        } elseif ($type == 'overrideoffice') {
            return UserOfficeOverrideHistoryTiersRange::where('user_office_override_history_id', $id)->get();
        } elseif ($type == 'office') {
            return UserAdditionalOfficeOverrideHistoryTiersRange::where('user_add_office_override_history_id', $id)->get();
        }
    }

    /**
     * Pull sales from FieldRoutes
     * 1. php artisan fieldroutes:get-subscriptions 2025-01-01 2025-01-31 --office="Houston" --employee=67890 --all --save
     * 2. php artisan fieldroutes:sync-data
     */
    private function pullSalesFromFieldRoutes($userId, $newHireDate, $oldHireDate)
    {
        // Queue job to pull new sales from FieldRoutes for the new hire date
        PullFieldRoutesBackDateSalesJob::dispatch($userId, $newHireDate, $oldHireDate)->onQueue('sales-process');
    }

    /**
     * Parse custom_field_X format and extract custom sales field ID
     *
     * @param string|null $type The type value (e.g., "custom_field_3" or "per kw")
     * @param int|null $existingFieldId Existing custom sales field ID from request
     * @return array ['type' => string, 'custom_sales_field_id' => int|null]
     */
    private function parseCustomFieldType(?string $type, ?int $existingFieldId = null): array
    {
        $customSalesFieldId = $existingFieldId;

        // Only parse custom_field_X format if feature is enabled (using cached helper)
        if (CustomSalesFieldHelper::isFeatureEnabled()) {
            if ($type && preg_match('/^custom_field_(\d+)$/', $type, $matches)) {
                return [
                    'type' => 'custom field',
                    'custom_sales_field_id' => (int) $matches[1],
                ];
            }
        }

        return [
            'type' => $type,
            'custom_sales_field_id' => $customSalesFieldId,
        ];
    }

    /**
     * Get display value for commission type (with custom field support) for audit logs.
     * 
     * @param string|null $commissionType The commission type from database
     * @param int|null $customSalesFieldId The custom sales field ID if set
     * @return string The display value for the commission type
     */
    private function getCommissionTypeDisplayForAudit(?string $commissionType, ?int $customSalesFieldId): string
    {
        // Only look up custom field name if feature is enabled AND field ID is set
        // This prevents unnecessary database queries when feature is disabled
        if ($customSalesFieldId && CustomSalesFieldHelper::isFeatureEnabled()) {
            $customField = Crmcustomfields::find($customSalesFieldId);
            if ($customField) {
                return ' per ' . $customField->name;
            }
        }

        // Standard commission type display
        return match ($commissionType) {
            'per kw' => ' per kw',
            'per sale' => ' per sale',
            'custom field' => ' (custom field)',
            default => ' %',
        };
    }
}
