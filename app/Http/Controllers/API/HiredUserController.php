<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Events\UserloginNotification;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAlertJob;
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
use App\Models\DomainSetting;
use App\Models\EmployeeIdSetting;
use App\Models\EventCalendar;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\ManagementTeamMember;
use App\Models\NewSequiDocsDocument;
use App\Models\Notification;
use App\Models\OnboardingEmployees;
use App\Models\Payroll;
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
use App\Models\UserAgreementHistory;
use App\Models\UserBankHistory;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use App\Models\UserDepartmentHistory;
use App\Models\UserEmploymentStatusHistory;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserPersonalInfoHistory;
use App\Models\UserReconciliationWithholding;
use App\Models\UserRedlines;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTaxHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWages;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use App\Models\W2UserTransferHistory;
use App\Services\SalesCalculationContext;
use App\Traits\EmailNotificationTrait;
use Carbon\Carbon;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use App\Helpers\CustomSalesFieldHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Pennant\Feature;
use Mail;
use Pdf;

class HiredUserController extends Controller
{
    use EmailNotificationTrait;
    use EvereeTrait;
    use SetterSubroutineListTrait;

    public function __construct()
    {
        //
    }

    public function addUser(Request $request): JsonResponse
    {

        $data = User::find($request->user_id);
        if ($data) {
            $data->first_name = $request->employee_deatils['first_name'];
            $data->last_name = $request->employee_deatils['last_name'];
            $data->email = $request->employee_deatils['email'];
            $data->mobile_no = $request->employee_deatils['mobile_no'];
            $data->state_id = $request->employee_deatils['state_id'];
            $data->city_id = isset($request->employee_deatils['city_id']) ? $request->employee_deatils['city_id'] : null;
            $data->recruiter_id = isset($request->employee_deatils['recruiter_id']) ? $request->employee_deatils['recruiter_id'] : null;
            $data->save();

            return response()->json([
                'ApiName' => 'update information for user',
                'status' => true,
                'message' => 'Updated Successfully.',
                // 'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'update information for user',
                'status' => false,
                'message' => 'Successfully.',

            ], 200);
        }
    }

    // new api as per risi sir requirment accept_decline_agreement
    public function accept_decline_agreement(Request $request): JsonResponse
    {

        $status_code = 400;
        $status = false;
        $message = 'User not found!!';
        $is_agreement_accepted = $request->is_agreement_accepted;
        $user_id = $request->user_id;

        $user_data = User::where('id', '=', $user_id)->first();

        if ($user_data != null) {
            $user_data->is_agreement_accepted = $is_agreement_accepted;
            $user_data->save();

            $status = true;
            $status_code = 200;
            $message = 'is_agreement_accepted key updated';
        }

        return response()->json([
            'ApiName' => 'accept_decline_agreement',
            'status' => $status,
            'message' => $message,
            'data' => $user_data,
        ], $status_code);
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

                        // if(empty($employee_compensation_result)){
                        $employee_compensation_result[$data->sub_position_id]['commission'] = isset($data->commission) ? $data->commission : null;
                        $employee_compensation_result[$data->sub_position_id]['commission_type'] = isset($data->commission_type) ? $data->commission_type : null;
                        $employee_compensation_result[$data->sub_position_id]['commission_effective_date'] = isset($data->commission_effective_date) ? $data->commission_effective_date : dateToYMD($data->period_of_agreement_start_date);
                        $employee_compensation_result[$data->sub_position_id]['commission_position_id'] = $data->sub_position_id;
                        $employee_compensation_result[$data->sub_position_id]['upfront_pay_amount'] = isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null;
                        $employee_compensation_result[$data->sub_position_id]['upfront_sale_type'] = isset($data->upfront_sale_type) ? $data->upfront_sale_type : null;
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
                            $employee_compensation_result[$data->self_gen_type]['commission_type'] = isset($data->self_gen_commission_type) ? $data->self_gen_commission_type : null;
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
                            'self_gen_commission_type' => isset($data->self_gen_commission_type) ? $data->self_gen_commission_type : null,
                            'self_gen_upfront_amount' => isset($data->self_gen_upfront_amount) ? $data->self_gen_upfront_amount : null,
                            'self_gen_upfront_type' => isset($data->self_gen_upfront_type) ? $data->self_gen_upfront_type : null,
                            'upfront_pay_amount' => isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null,
                            'upfront_sale_type' => isset($data->upfront_sale_type) ? $data->upfront_sale_type : null,
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
                            'self_gen_withheld_amount' => isset($data->self_gen_withheld_amount) ? $data->self_gen_withheld_amount : null,
                            'self_gen_withheld_type' => isset($data->self_gen_withheld_type) ? $data->self_gen_withheld_type : null,
                            'probation_period' => isset($data->probation_period) && $data->probation_period != 'None' ? $data->probation_period : null,
                            'commission' => isset($data->commission) ? $data->commission : null,
                            'commission_type' => isset($data->commission_type) ? $data->commission_type : null,
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

    /**
     * Method UserOrganization: Update user position data and create history
     *
     * @param  Request  $request  [explicit description]
     */
    public function UserOrganization(Request $request): JsonResponse
    {

        $uData = $this->userDataById($request->user_id);
        $reqData = $request;
        $result = $this->organizationDataComp($uData, $reqData);

        $data = User::find($request->user_id);
        if (! $data) {
            return response()->json([
                'ApiName' => 'Update User Organization',
                'status' => false,
                'message' => 'Bad Request.',
            ], 400);
        }

        $organization = $request->employee_originization;
        $is_manager = $organization['is_manager'];
        $manager_id = $organization['manager_id'];
        $team_id = $organization['team_id'];
        $position_id = $organization['position_id'];
        $sub_position_id = $organization['sub_position_id'];
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $self_gen_accounts = null;
        } else {
            $self_gen_accounts = $organization['self_gen_accounts'];
        }

        $effective_date = date('Y-m-d', strtotime($organization['effective_date']));
        $existing_employee_new_manager_id = isset($organization['existing_employee_new_manager_id']) ? $organization['existing_employee_new_manager_id'] : null;

        if (isset($effective_date)) {
            UserOrganizationHistory::updateOrCreate([
                'user_id' => $request->user_id,
                'effective_date' => $effective_date,
            ], [
                'user_id' => $request->user_id,
                'updater_id' => auth()->user()->id,
                'manager_id' => $manager_id,
                'team_id' => $team_id,
                'position_id' => $position_id,
                'sub_position_id' => $sub_position_id,
                'existing_employee_new_manager_id' => ! empty($existing_employee_new_manager_id) ? $existing_employee_new_manager_id : null,
                'effective_date' => $effective_date,
                'is_manager' => $is_manager,
                'self_gen_accounts' => $self_gen_accounts,
            ]);
        }

        if (isset($organization['manager_id_effective_date'])) {
            $prevManager = UserManagerHistory::where('user_id', $data->id)->where('effective_date', '<=', $organization['manager_id_effective_date'])->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($prevManager) {
                if ($prevManager->manager_id != $organization['manager_id'] || $prevManager->team_id != $organization['team_id']) {
                    UserManagerHistory::create([
                        'user_id' => $data->id,
                        'updater_id' => Auth()->user()->id,
                        'effective_date' => $organization['manager_id_effective_date'],
                        'old_manager_id' => $prevManager->manager_id,
                        'manager_id' => $organization['manager_id'],
                        'team_id' => $organization['team_id'],
                        'old_team_id' => $prevManager->team_id,
                        'position_id' => $organization['position_id'],
                        'old_position_id' => $prevManager->position_id,
                        'sub_position_id' => $organization['sub_position_id'],
                        'old_sub_position_id' => $prevManager->sub_position_id,
                    ]);
                }
            } else {
                UserManagerHistory::create([
                    'user_id' => $data->id,
                    'updater_id' => Auth()->user()->id,
                    'effective_date' => $organization['manager_id_effective_date'],
                    'manager_id' => $organization['manager_id'],
                    'team_id' => $organization['team_id'],
                    'position_id' => $organization['position_id'],
                    'sub_position_id' => $organization['sub_position_id'],
                ]);
            }
        }

        if (isset($organization['is_manager_effective_date'])) {
            $prevManager = UserIsManagerHistory::where('user_id', $data->id)->whereDate('effective_date', '<=', $organization['is_manager_effective_date'])->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($prevManager) {
                if ($prevManager->is_manager != $organization['is_manager']) {
                    UserIsManagerHistory::create([
                        'user_id' => $data->id,
                        'updater_id' => Auth()->user()->id,
                        'effective_date' => $organization['is_manager_effective_date'],
                        'old_is_manager' => $prevManager->is_manager,
                        'is_manager' => $organization['is_manager'],
                        'position_id' => $organization['position_id'],
                        'old_position_id' => $prevManager->position_id,
                        'sub_position_id' => $organization['sub_position_id'],
                        'old_sub_position_id' => $prevManager->sub_position_id,
                    ]);
                }
            } else {
                UserIsManagerHistory::create([
                    'user_id' => $data->id,
                    'updater_id' => Auth()->user()->id,
                    'effective_date' => $organization['is_manager_effective_date'],
                    'is_manager' => $organization['is_manager'],
                    'position_id' => $organization['position_id'],
                    'sub_position_id' => $organization['sub_position_id'],
                ]);
            }
        }

        $data->recruiter_id = $organization['recruiter_id'];
        $data->department_id = $organization['department_id'];
        $data->sub_position_id = $organization['sub_position_id'];
        $data->save();

        $recruiter_id = $organization['additional_recruiter_id'];
        $additional_locations = $organization['additional_locations'];

        AdditionalRecruiters::where('user_id', $data->id)->delete();
        foreach ($recruiter_id as $key => $value) {
            if ($key == 0) {
                User::where('id', $request->user_id)->update(['additional_recruiter_id1' => $value]);
            } else {
                User::where('id', $request->user_id)->update(['additional_recruiter_id2' => $value]);
            }

            AdditionalRecruiters::create([
                'user_id' => $data->id,
                'recruiter_id' => $value,
            ]);
        }

        if ($additional_locations) {
            $state_id_arr = [];
            $office_id_arr = [];
            $recordId = [];
            foreach ($additional_locations as $additional_location) {
                $state_id_arr[] = isset($additional_location['state_id']) ? $additional_location['state_id'] : '';
                $office_id_arr[] = isset($additional_location['office_id']) ? trim($additional_location['office_id']) : '';
                $record = AdditionalLocations::updateOrCreate([
                    'user_id' => $request->user_id,
                    'state_id' => isset($additional_location['state_id']) ? $additional_location['state_id'] : '',
                    'office_id' => isset($additional_location['office_id']) ? trim($additional_location['office_id']) : '',
                    'effective_date' => isset($additional_location['effective_date']) ? trim($additional_location['effective_date']) : null,
                ], [
                    'overrides_amount' => isset($additional_location['overrides_amount']) ? trim($additional_location['overrides_amount']) : 0,
                    'overrides_type' => isset($additional_location['overrides_type']) ? trim($additional_location['overrides_type']) : 'per kw',
                    'updater_id' => auth()->user()->id,
                ]);
                $recordId[] = $record->id;

                UserAdditionalOfficeOverrideHistory::updateOrCreate([
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
            AdditionalLocations::where('user_id', $request->user_id)->whereNotIn('id', $recordId)->update(['updater_id' => auth()->user()->id]);
            AdditionalLocations::where('user_id', $request->user_id)->whereNotIn('id', $recordId)->delete();
        } else {
            AdditionalLocations::where('user_id', $request->user_id)->delete();
        }

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
                    'position_id' => $organization['position_id'],
                    'user_id' => $request->user_id,
                ]);
            }
        }

        // UPDATE MANAGERS
        $userIds = [$request->user_id];
        if (Carbon::parse($effective_date)->lessThan(Carbon::today())) {
            $isManager = UserIsManagerHistory::where(['user_id' => $data->id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
            if ($isManager && $isManager->is_manager == '0' && $existing_employee_new_manager_id) {
                $userEmployeeIds = User::where('manager_id', $data->id)->get();
                foreach ($userEmployeeIds as $userEmployeeId) {
                    $organizationHistory = UserOrganizationHistory::where('user_id', $userEmployeeId->id)->where('effective_date', '<=', $effective_date)->orderBy('effective_date', 'DESC')->first();
                    $lastManager = UserManagerHistory::where(['user_id' => $userEmployeeId->id])->orderBy('effective_date', 'DESC')->first();
                    $date = $effective_date;
                    $system = 0;
                    if ($lastManager && Carbon::parse($effective_date)->lessThan(Carbon::parse($lastManager->effective_date))) {
                        $date = Carbon::parse($lastManager->effective_date)->addDay()->format('Y-m-d');
                        $system = 1;
                    }
                    UserManagerHistory::updateOrCreate([
                        'user_id' => $userEmployeeId->id,
                        'effective_date' => $date,
                    ], [
                        'user_id' => $userEmployeeId->id,
                        'updater_id' => Auth()->user()->id,
                        'effective_date' => $date,
                        'manager_id' => $existing_employee_new_manager_id,
                        'position_id' => @$organizationHistory->position_id ? $organizationHistory->position_id : $userEmployeeId->position_id,
                        'sub_position_id' => @$organizationHistory->sub_position_id ? $organizationHistory->sub_position_id : $userEmployeeId->sub_position_id,
                        'system_generated' => $system,
                    ]);
                    $userIds[] = $userEmployeeId->id;
                }

                $leadData = Lead::where('recruiter_id', $data->id)->pluck('id')->toArray();
                if (count($leadData) != 0) {
                    Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $existing_employee_new_manager_id]);
                }
            }
        }

        Artisan::call('ApplyHistoryOnUsers:update', ['user_id' => implode(',', $userIds)]);
        $data = User::find($request->user_id);

        // hubspot sale update code start
        $everee_worker_id = $data->everee_workerId;
        $aveyoid = $data->aveyo_hs_id;
        $recruiter = User::select('first_name', 'last_name')->where('id', $data->recruiter_id)->first();
        $manager = User::select('first_name', 'last_name')->where('id', $data->manager_id)->first();
        $team = ManagementTeam::select('team_name')->where('id', $data->team_id)->first();
        $office = Locations::select('office_name', 'work_site_id', 'everee_location_id', 'updated_at', 'general_code')->where('id', $data->office_id)->first();
        $positions = Positions::select('position_name')->where('id', $data->position_id)->first();
        $department = Department::where('id', $data->department_id)->first();
        $state = State::where('id', $data->state_id)->first();
        $additionalOfficeId = AdditionalLocations::where('user_id', $request->user_id)->pluck('office_id');
        $additionalOfficeName = Locations::whereNotNull('office_name')->whereIn('id', $additionalOfficeId)->pluck('office_name')->implode(',');
        $additionalWorkSiteId = Locations::whereNotNull('work_site_id')->whereIn('id', $additionalOfficeId)->pluck('work_site_id')->implode(',');

        if (! empty($office) && ! empty($state) && ! empty($everee_worker_id)) {
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {
                $this->update_emp_work_location($office, $state, $everee_worker_id);
            }
        }

        if ($data['position_id'] == 2) {
            $payGroup = 'Closer';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $closer_redline = null;
                $setter_redline = null;
            } else {
                $closer_redline = $data['redline'];
                $setter_redline = $data['self_gen_redline'];
            }
        }
        if ($data['position_id'] == 3) {
            $payGroup = 'Setter';
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $closer_redline = null;
                $setter_redline = null;
            } else {
                $closer_redline = $data['self_gen_redline'];
                $setter_redline = $data['redline'];
            }
        }

        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $payGroup = 'Closer';
        } else {
            if ($data['self_gen_accounts'] == 1) {
                $payGroup = 'Setter&Closer';
            }
        }

        $uid = auth()->user()->id;
        $CrmData = Crms::where('id', 2)->where('status', 1)->first();
        $CrmSetting = CrmSetting::where('crm_id', 2)->first();
        if (! empty($CrmData) && ! empty($CrmSetting) && ! empty($aveyoid)) {
            $val = json_decode($CrmSetting['value']);
            $token = $val->api_key;

            $Hubspotdata['properties'] = [
                'state' => isset($state->name) ? $state->name : null,
                'department_id' => isset($data->department_id) ? $data->department_id : null,
                'department' => isset($department->name) ? $department->name : null,
                'position_id' => isset($data->position_id) ? $data->position_id : null,
                'position' => isset($positions->position_name) ? $positions->position_name : null,
                'redline' => $closer_redline, // in hubspot this is closer redline
                'setter_redline' => $setter_redline,
                'pay_group' => isset($payGroup) ? $payGroup : null,
                'manager' => isset($manager->first_name) ? $manager->first_name.' '.$manager->last_name : null,
                'manager_id' => isset($data->manager_id) ? $data->manager_id : null,
                'team_id' => isset($data->team_id) ? $data->team_id : null,
                'team' => isset($team->team_name) ? $team->team_name : null,
                'office_id' => isset($office->work_site_id) ? $office->work_site_id : null,
                'office' => isset($office->office_name) ? $office->office_name : null,
                'recruiter_id' => isset($data->recruiter_id) ? $data->recruiter_id : null,
                'recruiter' => isset($recruiter->first_name) ? $recruiter->first_name.' '.$recruiter->last_name : null,
                'installer_on_file' => isset($office->general_code) ? $office->general_code : null,
                'office_additional_id' => isset($additionalWorkSiteId) ? $additionalWorkSiteId : null,
                'office_additional' => isset($additionalOfficeName) ? $additionalOfficeName : null,
            ];

            $this->update_employees($Hubspotdata, $token, $uid, $aveyoid);
        }
        // hubspot sale update code end

        if ($request->recalculate) {
            $paidPid = UserCommission::where(['amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->pluck('pid');
            $pids = SalesMaster::whereHas('salesMasterProcess', function ($q) use ($data) {
                $q->where('closer1_id', $data->id)->orWhere('closer2_id', $data->id)->orWhere('setter1_id', $data->id)->orWhere('setter2_id', $data->id);
            })->whereNull('date_cancelled')->whereNotNull('customer_signoff')->where(function ($q) {
                $q->whereNotNull('m1_date')->orWhereNotNull('m2_date');
            })->whereNotIn('pid', $paidPid)->pluck('pid');

            if ($pids) {
                $dataForPusher = [
                    'user_id' => $data->id,
                ];
                ProcessRecalculatesOpenSales::dispatch($pids, $dataForPusher);
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
            $salesData['email'] = $data->email;

            if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                $this->sendEmailNotification($salesData);
            } else {
                // no default blade based email template here
            }
        }
        event(new UserloginNotification($user));

        return response()->json([
            'ApiName' => 'Updated Organization',
            'status' => true,
            'message' => 'Updated Successfully.',
        ]);
    }

    public function organizationDataComp($udata, $reqdata)
    {
        $data = [];
        if (! empty($udata) && ! empty($reqdata)) {

            $oldAdditional_recruiter1 = '';
            $oldAdditional_recruiter2 = '';
            $additional_recruiter1 = '';
            $additional_recruiter2 = '';

            if (! empty($udata['additional_recruter'])) {
                $additionalRecruiter = $udata['additional_recruter'];
                foreach ($additionalRecruiter as $key => $value) {
                    if ($key == 0) {
                        $oldAdditional_recruiter1 = $value['recruiter_id'];
                    }
                    if ($key == 1) {
                        $oldAdditional_recruiter2 = $value['recruiter_id'];
                    }
                }
                // return $oldAdditional_recruiter2;
            }

            if (! empty($reqdata['employee_originization']['additional_recruiter_id'])) {
                $additionalRecruiter = $reqdata['employee_originization']['additional_recruiter_id'];
                foreach ($additionalRecruiter as $key => $value) {
                    if ($key == 0) {
                        $additional_recruiter1 = $value;
                    }
                    if ($key == 1) {
                        $additional_recruiter2 = $value;
                    }
                }

            }

            if (isset($reqdata['employee_originization']['manager_id']) && $udata['manager_id'] != $reqdata['employee_originization']['manager_id']) {
                $olduser = User::select('first_name', 'last_name')->where('id', $udata['manager_id'])->first();
                $newuser = User::select('first_name', 'last_name')->where('id', $reqdata['employee_originization']['manager_id'])->first();
                $old_value = (! empty($olduser)) ? $olduser->first_name.' '.$olduser->last_name : null;
                $new_value = (! empty($newuser)) ? $newuser->first_name.' '.$newuser->last_name : null;

                $data['manager'] = [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            }

            if (isset($reqdata['employee_originization']['team_id']) && $udata['team_id'] != $reqdata['employee_originization']['team_id']) {
                $olduser = ManagementTeam::select('team_name', 'type')->where('id', $udata['team_id'])->first();
                $newuser = ManagementTeam::select('team_name', 'type')->where('id', $reqdata['employee_originization']['team_id'])->first();
                $old_value = (! empty($olduser)) ? $olduser->team_name : null;
                $new_value = (! empty($newuser)) ? $newuser->team_name : null;

                $data['team'] = [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            }

            if (isset($reqdata['employee_originization']['recruiter_id']) && $udata['recruiter_id'] != $reqdata['employee_originization']['recruiter_id']) {
                $olduser = User::select('first_name', 'last_name')->where('id', $udata['recruiter_id'])->first();
                $newuser = User::select('first_name', 'last_name')->where('id', $reqdata['employee_originization']['recruiter_id'])->first();
                $old_value = (! empty($olduser)) ? $olduser->first_name.' '.$olduser->last_name : null;
                $new_value = (! empty($newuser)) ? $newuser->first_name.' '.$newuser->last_name : null;

                $data['recruiter'] = [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            }

            if (isset($reqdata['employee_originization']['recruiter_id']) && $udata['recruiter_id'] != $reqdata['employee_originization']['recruiter_id']) {
                $olduser = User::select('first_name', 'last_name')->where('id', $udata['recruiter_id'])->first();
                $newuser = User::select('first_name', 'last_name')->where('id', $reqdata['employee_originization']['recruiter_id'])->first();
                $old_value = (! empty($olduser)) ? $olduser->first_name.' '.$olduser->last_name : null;
                $new_value = (! empty($newuser)) ? $newuser->first_name.' '.$newuser->last_name : null;

                $data['recruiter'] = [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            }

            if ($oldAdditional_recruiter1 != $additional_recruiter1) {
                $olduser = User::select('first_name', 'last_name')->where('id', $oldAdditional_recruiter1)->first();
                $newuser = User::select('first_name', 'last_name')->where('id', $additional_recruiter1)->first();
                $old_value = (! empty($olduser)) ? $olduser->first_name.' '.$olduser->last_name : null;
                $new_value = (! empty($newuser)) ? $newuser->first_name.' '.$newuser->last_name : null;

                $data['additional_recruiter1'] = [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            }

            if ($oldAdditional_recruiter2 != $additional_recruiter2) {
                $olduser = User::select('first_name', 'last_name')->where('id', $oldAdditional_recruiter2)->first();
                $newuser = User::select('first_name', 'last_name')->where('id', $additional_recruiter2)->first();
                $old_value = (! empty($olduser)) ? $olduser->first_name.' '.$olduser->last_name : null;
                $new_value = (! empty($newuser)) ? $newuser->first_name.' '.$newuser->last_name : null;

                $data['additional_recruiter2'] = [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            }

            if (isset($reqdata['employee_originization']['effective_date']) && $udata['manager_id_effective_date'] != $reqdata['employee_originization']['effective_date']) {

                $old_value = (! empty($udata['manager_id_effective_date'])) ? date('m-d-Y', strtotime($udata['manager_id_effective_date'])) : '';
                $new_value = (! empty($reqdata['employee_originization']['effective_date'])) ? date('m-d-Y', strtotime($reqdata['employee_originization']['effective_date'])) : '';

                $data['position_effective_date'] = [
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                ];
            }

        }

        return $data;

    }

    // public function UserCompensation(Request $request)
    // {
    //     $user_id = $request->user_id;
    //     $user = User::find($request->user_id);
    //     if(!empty($user->aveyo_hs_id)){
    //         $aveyoid = $user->aveyo_hs_id;
    //     }
    //     $uid = auth()->user()->id;

    //     if(!empty($user))
    //     {
    //         $employee_compensation = $request->employee_compensation;

    //         foreach($employee_compensation as $key => $ec){
    //             $data = (object)[];
    //             $commission = UserCommissionHistory::where('user_id', $request->user_id)
    //                     ->where('commission_effective_date','=', date( 'Y-m-d', strtotime($ec['commission_effective_date'])))
    //                     ->where('position_id',$ec['position_id'])->first();
    //             $prev_commission = UserCommissionHistory::where('user_id', $request->user_id)
    //                     ->where('commission_effective_date','<', date( 'Y-m-d', strtotime($ec['commission_effective_date'])))
    //                     ->orderBy('commission_effective_date', 'DESC')
    //                     ->where('position_id',$ec['position_id'])->first();
    //             $next_commission = UserCommissionHistory::where('user_id', $request->user_id)
    //                     ->where('commission_effective_date','>', date( 'Y-m-d', strtotime($ec['commission_effective_date'])))
    //                     ->where('position_id',$ec['position_id'])
    //                     ->orderBy('commission_effective_date', 'ASC')
    //                     ->first();
    //                 if(empty($prev_commission) && empty($next_commission)){
    //                     $data = $user;
    //                     if(empty($commission)){
    //                         UserCommissionHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => 0
    //                             ]
    //                         );
    //                     }else{
    //                         UserCommissionHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => isset($commission->old_commission)?$commission->old_commission:0
    //                             ]);
    //                     }
    //                 } elseif(!empty($prev_commission) && empty($next_commission)){
    //                     //$data->commission = $commission_data->commission;
    //                     if(empty($commission)){
    //                         UserCommissionHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => isset($prev_commission->commission)?$prev_commission->commission:0
    //                             ]
    //                         );
    //                     }else{
    //                         UserCommissionHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => isset($prev_commission->commission)?$prev_commission->commission:0
    //                             ]);
    //                     }

    //                 }elseif(!empty($prev_commission) && !empty($next_commission)){
    //                     if(empty($commission)){
    //                         UserCommissionHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => isset($prev_commission->commission)?$prev_commission->commission:0
    //                             ]
    //                         );
    //                     }else{
    //                         UserCommissionHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => isset($prev_commission->commission)?$prev_commission->commission:0
    //                             ]);
    //                     }
    //                     $next_commission->old_commission = $ec['commission'];
    //                     $next_commission->save();
    //                 }elseif(empty($prev_commission) && !empty($next_commission)){
    //                     if(empty($commission)){
    //                         UserCommissionHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => 0
    //                             ]
    //                         );
    //                     }else{
    //                         UserCommissionHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'commission_effective_date' => date( 'Y-m-d', strtotime($ec['commission_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'commission'  => $ec['commission'],
    //                                 'old_commission' => 0
    //                             ]);
    //                     }
    //                     $next_commission->old_commission = $ec['commission'];
    //                     $next_commission->save();
    //                 }

    //             if(isset($ec['upfront_pay_amount'])){
    //                 $user_upfront = User::find($request->user_id);
    //                 $upfront =UserUpfrontHistory::where('user_id', $request->user_id)
    //                     ->where('position_id',$ec['position_id'])
    //                     ->where('upfront_effective_date', '=', date( 'Y-m-d', strtotime($ec['upfront_effective_date'])))
    //                     ->first();
    //                 $upfront_prev =UserUpfrontHistory::where('user_id', $request->user_id)
    //                     ->where('position_id',$ec['position_id'])
    //                     ->where('upfront_effective_date', '<', date( 'Y-m-d', strtotime($ec['upfront_effective_date'])))
    //                     ->orderBy('upfront_effective_date','DESC')->first();
    //                 $upfront_next = UserUpfrontHistory::where('user_id', $request->user_id)
    //                     ->where('position_id',$ec['position_id'])
    //                     ->where('upfront_effective_date', '>', date( 'Y-m-d', strtotime($ec['upfront_effective_date'])))->orderBy('upfront_effective_date','ASC')->first();
    //                 $data = (object)[];
    //                 if(empty($upfront_prev) && empty($upfront_next)){
    //                     if(empty($upfront)){
    //                         $checkdata = UserUpfrontHistory::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                             'position_id'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                             'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                             'old_upfront_pay_amount' => 0,
    //                             'old_upfront_sale_type' => '',
    //                         ]);
    //                     }else{
    //                         $checkdata = UserUpfrontHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                                 'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                                 'old_upfront_pay_amount' => isset($upfront->old_upfront_pay_amount)?$upfront->old_upfront_pay_amount:0,
    //                                 'old_upfront_sale_type' => isset($upfront->old_upfront_sale_type)?$upfront->old_upfront_sale_type:'',
    //                             ]);
    //                     }
    //                 }elseif(empty($upfront_prev) && !empty($upfront_next)){
    //                     if(empty($upfront)){
    //                         $checkdata = UserUpfrontHistory::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                             'position_id'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                             'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                             'old_upfront_pay_amount' => 0,
    //                             'old_upfront_sale_type' => '',
    //                         ]);
    //                     }else{
    //                         $checkdata = UserUpfrontHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                                 'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                                 'old_upfront_pay_amount' => isset($upfront->old_upfront_pay_amount)?$upfront->old_upfront_pay_amount:0,
    //                                 'old_upfront_sale_type' => isset($upfront->old_upfront_sale_type)?$upfront->old_upfront_sale_type:'',
    //                             ]);
    //                     }
    //                     $upfront_next->old_upfront_pay_amount = $ec['upfront_pay_amount'];
    //                     $upfront_next->old_upfront_sale_type = $ec['upfront_sale_type'];
    //                     $upfront_next->save();

    //                 }elseif(!empty($upfront_prev) && empty($upfront_next)){
    //                     if(empty($upfront)){
    //                         $checkdata = UserUpfrontHistory::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                             'position_id'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                             'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                             'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount)?$upfront_prev->upfront_pay_amount:0,
    //                             'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type)?$upfront_prev->upfront_sale_type:'',
    //                         ]);
    //                     }else{
    //                         $checkdata = UserUpfrontHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                                 'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                                 'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount)?$upfront_prev->upfront_pay_amount:0,
    //                                 'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type)?$upfront_prev->upfront_sale_type:'',
    //                             ]);
    //                     }
    //                 }elseif(!empty($upfront_prev) && !empty($upfront_next)){
    //                     if(empty($upfront)){
    //                         $checkdata = UserUpfrontHistory::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                             'position_id'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                             'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                             'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount)?$upfront_prev->upfront_pay_amount:0,
    //                             'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type)?$upfront_prev->upfront_sale_type:'',
    //                         ]);
    //                     }else{
    //                         $checkdata = UserUpfrontHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'upfront_effective_date'=> date( 'Y-m-d', strtotime($ec['upfront_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update(
    //                             [
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'upfront_pay_amount'  => $ec['upfront_pay_amount'],
    //                                 'upfront_sale_type'  => $ec['upfront_sale_type'],
    //                                 'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount)?$upfront_prev->upfront_pay_amount:0,
    //                                 'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type)?$upfront_prev->upfront_sale_type:'',
    //                             ]);
    //                     }
    //                     $upfront_next->old_upfront_pay_amount = $ec['upfront_pay_amount'];
    //                     $upfront_next->old_upfront_sale_type = $ec['upfront_sale_type'];
    //                     $upfront_next->save();
    //                 }
    //             }
    //             if(isset($ec['withheld_amount'])){
    //                 $user_withheld = User::find($request->user_id);
    //                 $withheld = UserWithheldHistory::where('user_id', $request->user_id)
    //                     ->where('withheld_effective_date','=',date( 'Y-m-d', strtotime($ec['withheld_effective_date'])))
    //                     ->where('position_id',$ec['position_id'])
    //                     ->first();
    //                 $withheld_prev = UserWithheldHistory::where('user_id', $request->user_id)
    //                     ->where('withheld_effective_date','<',date( 'Y-m-d', strtotime($ec['withheld_effective_date'])))
    //                     ->where('position_id',$ec['position_id'])
    //                     ->orderBy('withheld_effective_date', 'DESC')->first();
    //                 $withheld_next = UserWithheldHistory::where('user_id', $request->user_id)
    //                     ->where('withheld_effective_date','>',date( 'Y-m-d', strtotime($ec['withheld_effective_date'])))
    //                     ->where('position_id',$ec['position_id'])
    //                     ->orderBy('withheld_effective_date', 'ASC')->first();
    //                     $data = (object)[];
    //                 if(empty($withheld_prev) && empty($withheld_next)){
    //                     if(empty($withheld)){
    //                         $checkdata = UserWithheldHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => 0,
    //                                 'old_withheld_type' => ''
    //                             ]
    //                         );
    //                     }else{
    //                         $checkdata = UserWithheldHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update([
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => isset($withheld->old_withheld_amount)?$withheld->old_withheld_amount:0,
    //                                 'old_withheld_type' => isset($withheld->old_withheld_type)?$withheld->old_withheld_type:''
    //                             ]
    //                         );
    //                     }
    //                 }elseif(empty($withheld_prev) && !empty($withheld_next)){
    //                     if(empty($withheld)){
    //                         $checkdata = UserWithheldHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => 0,
    //                                 'old_withheld_type' => ''
    //                             ]
    //                         );
    //                     }else{
    //                         $checkdata = UserWithheldHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update([
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => 0,
    //                                 'old_withheld_type' => ''
    //                             ]
    //                         );
    //                     }
    //                     $withheld_next->old_withheld_amount = $ec['withheld_amount'];
    //                     $withheld_next->old_withheld_type = $ec['withheld_type'];
    //                     $withheld_next->save();

    //                 }elseif(!empty($withheld_prev) && empty($withheld_next)){
    //                     if(empty($withheld)){
    //                         $checkdata = UserWithheldHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => isset($withheld_prev->withheld_amount)?$withheld_prev->withheld_amount:0,
    //                                 'old_withheld_type' => isset($withheld_prev->withheld_type)?$withheld_prev->withheld_type:''
    //                             ]
    //                         );
    //                     }else{
    //                         $checkdata = UserWithheldHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update([
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => isset($withheld_prev->withheld_amount)?$withheld_prev->withheld_amount:0,
    //                                 'old_withheld_type' => isset($withheld_prev->withheld_type)?$withheld_prev->withheld_type:''
    //                             ]
    //                         );
    //                     }
    //                 }elseif(!empty($withheld_prev) && !empty($withheld_next)){
    //                     if(empty($withheld)){
    //                         $checkdata = UserWithheldHistory::Create(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id'],
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => isset($withheld_prev->withheld_amount)?$withheld_prev->withheld_amount:0,
    //                                 'old_withheld_type' => isset($withheld_prev->withheld_type)?$withheld_prev->withheld_type:''
    //                             ]
    //                         );
    //                     }else{
    //                         $checkdata = UserWithheldHistory::where(
    //                             [
    //                                 'user_id'  => $request->user_id,
    //                                 'withheld_effective_date'=> date( 'Y-m-d', strtotime($ec['withheld_effective_date'])),
    //                                 'position_id'  => $ec['position_id']
    //                             ])->update([
    //                                 'updater_id'  => auth()->user()->id,
    //                                 'withheld_amount'  => $ec['withheld_amount'],
    //                                 'withheld_type'  => $ec['withheld_type'],
    //                                 'old_withheld_amount' => isset($withheld_prev->withheld_amount)?$withheld_prev->withheld_amount:0,
    //                                 'old_withheld_type' => isset($withheld_prev->withheld_type)?$withheld_prev->withheld_type:''
    //                             ]
    //                         );
    //                     }
    //                     $withheld_next->old_withheld_amount = $ec['withheld_amount'];
    //                     $withheld_next->old_withheld_type = $ec['withheld_type'];
    //                     $withheld_next->save();
    //                 }

    //             }
    //             if($key==0){
    //                 $self_gen_user = 0;
    //             }else{
    //                 $self_gen_user = 1;
    //             }
    //             $redline = UserRedlines::where('user_id', $request->user_id)
    //             ->where('start_date','=',date( 'Y-m-d', strtotime($ec['redline_effective_date'])))
    //             ->where('position_type',$ec['position_id'])
    //             ->first();
    //             $prev_redline = UserRedlines::where('user_id', $request->user_id)
    //                 ->where('start_date','<',date( 'Y-m-d', strtotime($ec['redline_effective_date'])))
    //                 ->where('position_type',$ec['position_id'])
    //                 ->orderBy('start_date', 'DESC')->first();
    //             $next_redline = UserRedlines::where('user_id', $request->user_id)
    //                 ->where('start_date','>',date( 'Y-m-d', strtotime($ec['redline_effective_date'])))
    //                 ->where('position_type',$ec['position_id'])
    //                 ->orderBy('start_date', 'ASC')->first();
    //                 $data = (object)[];
    //             if(empty($prev_redline) && empty($next_redline)){
    //                 if(empty($redline)){
    //                     $checkdata = UserRedlines::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => 0,
    //                             'old_redline_amount_type' => '',
    //                             'old_redline_type' => ''
    //                         ]);
    //                 }else{
    //                     $checkdata = UserRedlines::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id']
    //                         ])->update([
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => isset($redline->old_redline)?$redline->old_redline:0,
    //                             'old_redline_amount_type' => isset($redline->old_redline_amount_type)?$redline->old_redline_amount_type:'',
    //                             'old_redline_type' => isset($redline->old_redline_type)?$redline->old_redline_type:''
    //                         ]);
    //                 }
    //             }elseif(empty($prev_redline) && !empty($next_redline)){
    //                 if(empty($redline)){
    //                     $checkdata = UserRedlines::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => 0,
    //                             'old_redline_amount_type' => '',
    //                             'old_redline_type' => ''
    //                         ]);
    //                 }else{
    //                     $checkdata = UserRedlines::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id']
    //                         ])->update([
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => 0,
    //                             'old_redline_amount_type' => '',
    //                             'old_redline_type' => ''
    //                         ]);
    //                 }
    //                 $next_redline->old_redline = $ec['redline'];
    //                 $next_redline->old_redline_type = $ec['redline_type'];
    //                 $next_redline->old_redline_amount_type = $ec['redline_amount_type'];
    //                 $next_redline->save();
    //             }elseif(!empty($prev_redline) && empty($next_redline)){
    //                 if(empty($redline)){
    //                     $checkdata = UserRedlines::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => isset($prev_redline->redline)?$prev_redline->redline:0,
    //                             'old_redline_amount_type' => isset($prev_redline->redline_amount_type)?$prev_redline->redline_amount_type:'',
    //                             'old_redline_type' => isset($prev_redline->redline_type)?$prev_redline->redline_type:''
    //                         ]);
    //                 }else{
    //                     $checkdata = UserRedlines::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id']
    //                         ])->update([
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => isset($prev_redline->redline)?$prev_redline->redline:0,
    //                             'old_redline_amount_type' => isset($prev_redline->redline_amount_type)?$prev_redline->redline_amount_type:'',
    //                             'old_redline_type' => isset($prev_redline->redline_type)?$prev_redline->redline_type:''
    //                         ]);
    //                 }
    //             }elseif(!empty($prev_redline) && !empty($next_redline)){
    //                 if(empty($redline)){
    //                     $checkdata = UserRedlines::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id'],
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => isset($prev_redline->redline)?$prev_redline->redline:0,
    //                             'old_redline_amount_type' => isset($prev_redline->redline_amount_type)?$prev_redline->redline_amount_type:'',
    //                             'old_redline_type' => isset($prev_redline->redline_type)?$prev_redline->redline_type:''
    //                         ]);
    //                 }else{
    //                     $checkdata = UserRedlines::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'start_date'=> date( 'Y-m-d', strtotime($ec['redline_effective_date'])),
    //                             'position_type'  => $ec['position_id']
    //                         ])->update([
    //                             'updater_id'  => auth()->user()->id,
    //                             'redline_amount_type'  => $ec['redline_amount_type'],
    //                             'redline'  => $ec['redline'],
    //                             'self_gen_user' => $self_gen_user,
    //                             'redline_type' => $ec['redline_type'],
    //                             'old_redline' => isset($prev_redline->redline)?$prev_redline->redline:0,
    //                             'old_redline_amount_type' => isset($prev_redline->redline_amount_type)?$prev_redline->redline_amount_type:'',
    //                             'old_redline_type' => isset($prev_redline->redline_type)?$prev_redline->redline_type:''
    //                         ]);
    //                 }
    //                 $next_redline->old_redline = $ec['redline'];
    //                 $next_redline->old_redline_type = $ec['redline_type'];
    //                 $next_redline->old_redline_amount_type = $ec['redline_amount_type'];
    //                 $next_redline->save();
    //             }

    //                 $today = Date('Y-m-d');
    //                 $UserCommissionHistory = UserCommissionHistory::where('user_id', $user_id)
    //                 ->where('position_id',$ec['position_id'])
    //                 ->where('commission_effective_date', '<=', $today)
    //                 ->orderBy('commission_effective_date','DESC')
    //                 ->orderBy('id','ASC')
    //                 ->first();

    //                 $UserUpfrontHistory = UserUpfrontHistory::where('user_id', $user_id)
    //                 ->where('position_id',$ec['position_id'])
    //                 ->where('upfront_effective_date', '<=', $today)
    //                 ->orderBy('upfront_effective_date', 'DESC')
    //                 ->orderBy('id','ASC')
    //                 ->first();

    //                 $UserRedlines = UserRedlines::where('user_id', $user_id)
    //                 ->where('position_type',$ec['position_id'])
    //                 ->where('start_date', '<=', $today)
    //                 ->orderBy('start_date', 'DESC')
    //                 ->orderBy('id','ASC')
    //                 ->first();

    //                 $UserWithheldHistory = UserWithheldHistory::where('user_id', $user_id)
    //                 ->where('position_id',$ec['position_id'])
    //                 ->where('withheld_effective_date', '<=', $today)
    //                 ->orderBy('withheld_effective_date', 'DESC')
    //                 ->orderBy('id','ASC')
    //                 ->first();

    //                 if($key==0){
    //                     // commission
    //                     if(!empty($user->commission_effective_date) && strtotime($user->commission_effective_date) < strtotime($ec['commission_effective_date']) && strtotime($ec['commission_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                         $user->commission = $ec['commission'];
    //                         $user->commission_effective_date = $ec['commission_effective_date'];
    //                     }elseif(empty($user->commission_effective_date) || (isset($UserCommissionHistory->commission_effective_date) && strtotime($user->commission_effective_date) <= strtotime($UserCommissionHistory->commission_effective_date)) ){
    //                         $user->commission = isset($UserCommissionHistory->commission)?$UserCommissionHistory->commission:$user->commission;
    //                         $user->commission_effective_date = isset($UserCommissionHistory->commission_effective_date)?$UserCommissionHistory->commission_effective_date:$user->commission_effective_date;
    //                     }

    //                     // redline
    //                     if(!empty($user->redline_effective_date) && strtotime($user->redline_effective_date) < strtotime($ec['redline_effective_date']) && strtotime($ec['redline_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                         $user->redline = $ec['redline'];
    //                         $user->redline_amount_type = $ec['redline_amount_type'];
    //                         $user->redline_type = $ec['redline_type'];
    //                         $user->redline_effective_date = $ec['redline_effective_date'];
    //                     }elseif(empty($user->redline_effective_date) || (isset($UserRedlines->start_date) && strtotime($user->redline_effective_date) <= strtotime($UserRedlines->start_date)) ){
    //                         $user->redline = isset($UserRedlines->redline)?$UserRedlines->redline:$user->redline;
    //                         $user->redline_amount_type = isset($UserRedlines->redline_amount_type)?$UserRedlines->redline_amount_type:$user->redline_amount_type;
    //                         $user->redline_type = isset($UserRedlines->redline_type)?$UserRedlines->redline_type:$user->redline_type;
    //                         $user->redline_effective_date = isset($UserRedlines->start_date)?$UserRedlines->start_date:$user->redline_effective_date;
    //                     }

    //                     // upfront pay
    //                     if(!empty($user->upfront_effective_date) && strtotime($user->upfront_effective_date) < strtotime($ec['upfront_effective_date']) && strtotime($ec['upfront_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                         $user->upfront_pay_amount = $ec['upfront_pay_amount'];
    //                         $user->upfront_sale_type = $ec['upfront_sale_type'];
    //                         $user->upfront_effective_date = $ec['upfront_effective_date'];
    //                     }elseif(empty($user->upfront_effective_date) || (isset($UserUpfrontHistory->upfront_effective_date) && strtotime($user->upfront_effective_date) <= strtotime($UserUpfrontHistory->upfront_effective_date) )){
    //                         $user->upfront_pay_amount = isset($UserUpfrontHistory->upfront_pay_amount)?$UserUpfrontHistory->upfront_pay_amount:$user->upfront_pay_amount;
    //                         $user->upfront_sale_type = isset($UserUpfrontHistory->upfront_sale_type)?$UserUpfrontHistory->upfront_sale_type:$user->upfront_sale_type;
    //                         $user->upfront_effective_date = isset($UserUpfrontHistory->upfront_effective_date)?$UserUpfrontHistory->upfront_effective_date:$user->upfront_effective_date;
    //                     }

    //                     // withheld_amount
    //                     if(!empty($user->withheld_effective_date) &&  strtotime($user->withheld_effective_date) < strtotime($ec['withheld_effective_date']) && strtotime($ec['withheld_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                         $user->withheld_amount  = $ec['withheld_amount'];
    //                         $user->withheld_type  = $ec['withheld_type'];
    //                         $user->withheld_effective_date  = $ec['withheld_effective_date'];
    //                     }elseif(empty($user->withheld_effective_date) || (isset($UserWithheldHistory->withheld_effective_date) && strtotime($user->withheld_effective_date) <= strtotime($UserWithheldHistory->withheld_effective_date)) ){
    //                         $user->withheld_amount  = isset($UserWithheldHistory->withheld_amount)?$UserWithheldHistory->withheld_amount:$user->withheld_amount;
    //                         $user->withheld_type  = isset($UserWithheldHistory->withheld_type)?$UserWithheldHistory->withheld_type:$user->withheld_type;
    //                         $user->withheld_effective_date  = isset($UserWithheldHistory->withheld_effective_date)?$UserWithheldHistory->withheld_effective_date:$user->withheld_effective_date;
    //                     }
    //                 }else{
    //                     // self_gen_commission
    //                     if(!empty($user->self_gen_commission_effective_date) && strtotime($user->self_gen_commission_effective_date) < strtotime($ec['commission_effective_date']) && strtotime($ec['commission_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                         $user->self_gen_commission = $ec['commission'];
    //                         $user->self_gen_commission_effective_date = $ec['commission_effective_date'];
    //                     }elseif(empty($user->self_gen_commission_effective_date) || (isset($UserCommissionHistory->commission_effective_date) && strtotime($user->commission_effective_date) <= strtotime($UserCommissionHistory->commission_effective_date))){
    //                         $user->self_gen_commission = isset($UserCommissionHistory->commission)?$UserCommissionHistory->commission:$user->self_gen_commission;
    //                         $user->self_gen_commission_effective_date = isset($UserCommissionHistory->commission_effective_date)?$UserCommissionHistory->commission_effective_date:$user->self_gen_commission_effective_date;
    //                     }

    //                     // self_gen_redline
    //                     if(!empty($user->self_gen_redline_effective_date) && strtotime($user->self_gen_redline_effective_date) < strtotime($ec['redline_effective_date']) && strtotime($ec['redline_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                         $user->self_gen_redline = $ec['redline'];
    //                         $user->self_gen_redline_amount_type = $ec['redline_amount_type'];
    //                         $user->self_gen_redline_type = $ec['redline_type'];
    //                         $user->self_gen_redline_effective_date = $ec['redline_effective_date'];
    //                     }elseif(empty($user->self_gen_redline_effective_date) || (isset($UserRedlines->start_date) && strtotime($user->redline_effective_date) <= strtotime($UserRedlines->start_date)) ){
    //                         $user->self_gen_redline = isset($UserRedlines->redline)?$UserRedlines->redline:$user->self_gen_redline;
    //                         $user->self_gen_redline_amount_type = isset($UserRedlines->redline_amount_type)?$UserRedlines->redline_amount_type:$user->self_gen_redline_amount_type;
    //                         $user->self_gen_redline_type = isset($UserRedlines->redline_type)?$UserRedlines->redline_type:$user->self_gen_redline_type;
    //                         $user->self_gen_redline_effective_date = isset($UserRedlines->start_date)?$UserRedlines->start_date:$user->self_gen_redline_effective_date;
    //                     }

    //                     // self_gen_upfront
    //                     if(!empty($user->self_gen_upfront_effective_date) && strtotime($user->self_gen_upfront_effective_date) < strtotime($ec['upfront_effective_date']) && strtotime($ec['upfront_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                         $user->self_gen_upfront_amount = $ec['upfront_pay_amount'];
    //                         $user->self_gen_upfront_type = $ec['upfront_sale_type'];
    //                         $user->self_gen_upfront_effective_date = $ec['upfront_effective_date'];
    //                     }elseif(empty($user->self_gen_upfront_effective_date) || (isset($UserUpfrontHistory->upfront_effective_date) && strtotime($user->upfront_effective_date) <= strtotime($UserUpfrontHistory->upfront_effective_date)) ){
    //                         $user->self_gen_upfront_amount = isset($UserUpfrontHistory->upfront_pay_amount)?$UserUpfrontHistory->upfront_pay_amount:$user->self_gen_upfront_amount;
    //                         $user->self_gen_upfront_type = isset($UserUpfrontHistory->upfront_sale_type)?$UserUpfrontHistory->upfront_sale_type:$user->self_gen_upfront_type;
    //                         $user->self_gen_upfront_effective_date = isset($UserUpfrontHistory->upfront_effective_date)?$UserUpfrontHistory->upfront_effective_date:$user->self_gen_upfront_effective_date;
    //                     }

    //                     // self_gen_withheld
    //                     if(!empty($user->self_gen_withheld_effective_date) && strtotime($user->self_gen_withheld_effective_date) < strtotime($ec['withheld_effective_date']) && strtotime($ec['withheld_effective_date'])<= strtotime(date('Y-m-d')) ){
    //                     //if(strtotime($ec['withheld_effective_date']) == strtotime(date('Y-m-d')) ){
    //                         $user->self_gen_withheld_amount  = $ec['withheld_amount'];
    //                         $user->self_gen_withheld_type  = $ec['withheld_type'];
    //                         $user->self_gen_withheld_effective_date  = $ec['withheld_effective_date'];
    //                     }elseif(empty($user->self_gen_withheld_effective_date) || (isset($UserWithheldHistory->withheld_effective_date) && strtotime($user->withheld_effective_date) <= strtotime($UserWithheldHistory->withheld_effective_date)) ){
    //                         $user->self_gen_withheld_amount  = isset($UserWithheldHistory->withheld_amount)?$UserWithheldHistory->withheld_amount:$user->self_gen_withheld_amount;
    //                         $user->self_gen_withheld_type  = isset($UserWithheldHistory->withheld_type)?$UserWithheldHistory->withheld_type:$user->self_gen_withheld_type;
    //                         $user->self_gen_withheld_effective_date  = isset($UserWithheldHistory->withheld_effective_date)?$UserWithheldHistory->withheld_effective_date:$user->self_gen_withheld_effective_date;
    //                     }
    //                 }
    //                 $user->save();

    //         }
    //             if($user->upfront_sale_type == "per sale"){
    //                 $upfrontType = "Per Sale";
    //             }elseif($user->upfront_sale_type == "per kw"){
    //                 $upfrontType = "Per kw";
    //             }

    //             $CrmData = Crms::where('id',2)->where('status',1)->first();
    //             $CrmSetting = CrmSetting::where('crm_id',2)->first();
    //             if(!empty($CrmData) && !empty($CrmSetting) && !empty($aveyoid)){
    //             // $token ="pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641";
    //             $val = json_decode($CrmSetting['value']);
    //             $token = $val->api_key;

    //                 $Hubspotdata['properties'] = [

    //                     "upfront_pay_amount"=> isset($user->upfront_pay_amount)?$user->upfront_pay_amount:null,
    //                     "upfront_type" => isset($upfrontType)?$upfrontType:null,
    //                     "commission" =>isset($user->commission)?$user->commission:null,
    //                     "redline" =>isset($user->redline)?$user->redline:null,
    //                     "setter_redline" =>isset($user->self_gen_redline)?$user->self_gen_redline:null,

    //                 ];
    //                  $update_employees = $this->update_employees($Hubspotdata,$token,$uid,$aveyoid);

    //             }

    //             $user = array(
    //                 'user_id'      => $request['user_id'],
    //                 'description' => 'Updated Redline / Commission / Upfront Data by ' . auth()->user()->first_name,
    //                 'type'         => 'Redline / Commission / Upfront',
    //                 'is_read' => 0,
    //             );

    //         if(!empty($request['commission_selfgen']) && !empty($request['commission_selfgen_effective_date'])){
    //             $commissionSelfgen = $request['commission_selfgen'];
    //             $commissionSelfgenEffectiveDate = $request['commission_selfgen_effective_date'];
    //             $userPosition = User::where('id',$request->user_id)->first();

    //             $selfGenComm = UserSelfGenCommmissionHistory::where('user_id',$request->user_id)
    //             ->where('position_id',$userPosition->position_id)
    //             ->where('commission_effective_date',$commissionSelfgenEffectiveDate)
    //             ->first();
    //             $selfGenComm_prev = UserSelfGenCommmissionHistory::where('user_id',$request->user_id)
    //             ->where('position_id',$userPosition->position_id)
    //             ->where('commission_effective_date','<',$commissionSelfgenEffectiveDate)
    //             ->orderby('commission_effective_date','desc')
    //             ->first();
    //             $selfGenComm_next = UserSelfGenCommmissionHistory::where('user_id',$request->user_id)
    //             ->where('position_id',$userPosition->position_id)
    //             ->where('commission_effective_date','>',$commissionSelfgenEffectiveDate)
    //             ->orderby('commission_effective_date','asc')
    //             ->first();
    //             // Log::info([$selfGenComm->commission_effective_date,$selfGenComm_prev->commission_effective_date,$selfGenComm_next->commission_effective_date]);
    //             if(empty($selfGenComm_prev) && empty($selfGenComm_next)){
    //                 if(empty($selfGenComm)){
    //                     UserSelfGenCommmissionHistory::create([
    //                         'user_id' => $request->user_id,
    //                         'updater_id' => Auth()->user()->id,
    //                         'commission'=>$commissionSelfgen,
    //                         'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                         'old_commission' =>0,
    //                         'position_id' => $userPosition->position_id
    //                     ]);
    //                 }else{
    //                     UserSelfGenCommmissionHistory::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                             'position_id'  => $userPosition->position_id
    //                         ])->update(
    //                         [
    //                             'updater_id'  => auth()->user()->id,
    //                             'commission'  => $commissionSelfgen,
    //                             'old_commission' => isset($selfGenComm->old_commission)?$selfGenComm->old_commission:0
    //                         ]);
    //                 }
    //             } elseif(!empty($selfGenComm_prev) && empty($selfGenComm_next)){

    //                 if(empty($selfGenComm)){
    //                     UserSelfGenCommmissionHistory::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                             'position_id'  => $userPosition->position_id,
    //                             'updater_id'  => auth()->user()->id,
    //                             'commission'  => $commissionSelfgen,
    //                             'old_commission' => isset($selfGenComm_prev->commission)?$selfGenComm_prev->commission:0
    //                         ]
    //                     );
    //                 }else{
    //                     UserSelfGenCommmissionHistory::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                             'position_id'  => $userPosition->position_id
    //                         ])->update(
    //                         [
    //                             'updater_id'  => auth()->user()->id,
    //                             'commission'  => $commissionSelfgen,
    //                             'old_commission' => isset($selfGenComm_prev->commission)?$selfGenComm_prev->commission:0
    //                         ]);
    //                 }

    //             }elseif(!empty($selfGenComm_prev) && !empty($selfGenComm_next)){
    //                 if(empty($selfGenComm)){
    //                     UserSelfGenCommmissionHistory::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                             'position_id'  => $userPosition->position_id,
    //                             'updater_id'  => auth()->user()->id,
    //                             'commission'  => $commissionSelfgen,
    //                             'old_commission' => isset($selfGenComm_prev->commission)?$selfGenComm_prev->commission:0
    //                         ]
    //                     );
    //                 }else{
    //                     UserSelfGenCommmissionHistory::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                             'position_id'  => $userPosition->position_id
    //                         ])->update(
    //                         [
    //                             'updater_id'  => auth()->user()->id,
    //                             'commission'  => $commissionSelfgen,
    //                             'old_commission' => isset($selfGenComm_prev->commission)?$selfGenComm_prev->commission:0
    //                         ]);
    //                 }
    //                 $selfGenComm_next->old_commission = $commissionSelfgen;
    //                 $selfGenComm_next->save();
    //             }elseif(empty($selfGenComm_prev) && !empty($selfGenComm_next)){
    //                 if(empty($selfGenComm)){
    //                     UserSelfGenCommmissionHistory::Create(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                             'position_id'  => $userPosition->position_id,
    //                             'updater_id'  => auth()->user()->id,
    //                             'commission'  => $commissionSelfgen,
    //                             'old_commission' => 0
    //                         ]
    //                     );
    //                 }else{
    //                     UserSelfGenCommmissionHistory::where(
    //                         [
    //                             'user_id'  => $request->user_id,
    //                             'commission_effective_date' => date( 'Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
    //                             'position_id'  => $userPosition->position_id
    //                         ])->update(
    //                         [
    //                             'updater_id'  => auth()->user()->id,
    //                             'commission'  => $commissionSelfgen,
    //                             'old_commission' => 0
    //                         ]);
    //                 }
    //                 $selfGenComm_next->old_commission = $commissionSelfgen;
    //                 $selfGenComm_next->save();
    //             }
    //         }

    //             $notify =  event(new UserloginNotification($user));

    //        Artisan::call('generate:alert');
    //         return response()->json([
    //             'ApiName' => 'user_compensation',
    //             'status' => true,
    //             'message' => 'Saved Successfully.',
    //         ], 200);
    //     }else{
    //         return response()->json([
    //             'ApiName' => 'No User found',
    //             'status' => false,
    //             'message' => 'Bad Request',
    //         ], 400);
    //     }
    //     //$data2 = User::find($request->user_id);
    //     // if($data2)
    //     // {
    //     //     $data2->commission = $request->employee_compensation['commission'];
    //     //     $data2->redline = $request->employee_compensation['redline_amount'];
    //     //     $data2->redline_amount_type = $request->employee_compensation['redline'];
    //     //     $data2->redline_type = $request->employee_compensation['redline_type'];
    //     //     $data2->self_gen_redline = isset($request->employee_compensation['self_gen_redline'])?$request->employee_compensation['self_gen_redline']:null;
    //     //     $data2->self_gen_redline_amount_type = isset($request->employee_compensation['self_gen_redline_amount_type'])?$request->employee_compensation['self_gen_redline_amount_type']:null;
    //     //     $data2->self_gen_redline_type = isset($request->employee_compensation['self_gen_redline_type'])?$request->employee_compensation['self_gen_redline_type']:null;

    //     //     $data2->self_gen_commission = isset($request->employee_compensation['self_gen_commission'])?$request->employee_compensation['self_gen_commission']:null;
    //     //     $data2->self_gen_upfront_amount = isset($request->employee_compensation['self_gen_upfront_amount'])?$request->employee_compensation['self_gen_upfront_amount']:null;
    //     //     $data2->self_gen_upfront_type = isset($request->employee_compensation['self_gen_upfront_type'])?$request->employee_compensation['self_gen_upfront_type']:null;
    //     //     $data2->upfront_pay_amount = $request->employee_compensation['upfront_pay_amount'];
    //     //     $data2->upfront_sale_type = $request->employee_compensation['upfront_sale_type'];
    //     //     $data2->save();

    //     //     $redline_data = isset($request->employee_compensation['redline_data'])?$request->employee_compensation['redline_data']:[];
    //     //     if(count($redline_data) > 0){
    //     //         $updater_id = Auth()->user()->id;
    //     //         $data = UserRedlines::where('user_id',$request->user_id)->where('self_gen_user',0)->delete();
    //     //         foreach($redline_data as $key=> $value)
    //     //         {
    //     //             if(!empty( $value['redline_amount_type'])){
    //     //                 $redline_amount_type =  $value['redline_amount_type'];
    //     //             }else{
    //     //                 // $redline_amount_type =  'Fixed';
    //     //                 $redline_amount_type = $request->employee_compensation['redline'];
    //     //             }

    //     //             $udata = [
    //     //                 'user_id'  => $request['user_id'],
    //     //                 'updater_id'  => $updater_id,
    //     //                 'redline' => $value['redline'],
    //     //                 'redline_type' => $value['redline_type'],
    //     //                 'redline_amount_type' => $redline_amount_type,
    //     //                 'self_gen_user' => 0,
    //     //                 'start_date' => date( 'Y-m-d', strtotime($value['start_date'])),
    //     //                 'position_type'=>1

    //     //             ];
    //     //             UserRedlines::create($udata);

    //     //             // $pidData = UserCommission::whereRaw('"'.$udata['start_date'].'" between `pay_period_from` and `pay_period_to`')->where(['user_id'=> $udata['user_id'],'amount_type'=> 'm2'])->where('status','<>','3')->get();
    //     //             $pidData = UserCommission::where('customer_signoff','>=',$udata['start_date'])->where(['user_id'=> $udata['user_id'],'amount_type'=> 'm2'])->where('status','<>','3')->get();
    //     //             if (count($pidData) > 0) {
    //     //                 foreach ($pidData as $key => $value) {
    //     //                     $subroutineProcess = $this->subroutine_process($value->pid);
    //     //                     //return $subroutineProcess = $this->subroutine_process($value->pid);
    //     //                 }
    //     //             }
    //     //         }
    //     //     }
    //     //     // $notificationData =  Notification::create([
    //     //     //     'user_id' =>  auth()->user()->id,
    //     //     //     'type' => 'Redline / Commission / Upfront',
    //     //     //     'description' => 'Updated Redline / Commission / Upfront Data by ' . auth()->user()->first_name,
    //     //     //     'is_read' => 0,

    //     //     // ]);
    //     //     $self_gen_redline_data = isset($request->employee_compensation['self_gen_redline_data'])?$request->employee_compensation['self_gen_redline_data']:[];

    //     //     if(count($self_gen_redline_data) > 0){
    //     //         $updater_id = Auth()->user()->id;
    //     //         $data = UserRedlines::where('user_id',$request->user_id)->where('self_gen_user',1)->delete();
    //     //         foreach($self_gen_redline_data as $key=> $value1)
    //     //         {
    //     //             if(!empty( $value1['redline_amount_type'])){
    //     //                 $redline_amount_type =  $value1['redline_amount_type'];
    //     //             }else{
    //     //                 // $redline_amount_type =  'Fixed';
    //     //                 $redline_amount_type = $request->employee_compensation['redline'];
    //     //             }

    //     //             $udata = [
    //     //                 'user_id'  => $request['user_id'],
    //     //                 'updater_id'  => $updater_id,
    //     //                 'redline' => $value1['redline'],
    //     //                 'redline_type' => $value1['redline_type'],
    //     //                 'redline_amount_type' => $redline_amount_type,
    //     //                 'self_gen_user' => 0,
    //     //                 'start_date' => date( 'Y-m-d', strtotime($value1['start_date'])),
    //     //                 'position_type'=>2

    //     //             ];
    //     //             UserRedlines::create($udata);

    //     //             // $pidData = UserCommission::whereRaw('"'.$udata['start_date'].'" between `pay_period_from` and `pay_period_to`')->where(['user_id'=> $udata['user_id'],'amount_type'=> 'm2'])->where('status','<>','3')->get();
    //     //             $pidData = UserCommission::where('customer_signoff','>=',$udata['start_date'])->where(['user_id'=> $udata['user_id'],'amount_type'=> 'm2'])->where('status','<>','3')->get();
    //     //             if (count($pidData) > 0) {
    //     //                 foreach ($pidData as $key => $value) {
    //     //                     $subroutineProcess = $this->subroutine_process($value->pid);
    //     //                     //return $subroutineProcess = $this->subroutine_process($value->pid);
    //     //                 }
    //     //             }
    //     //         }
    //     //     }
    //     //     $user = array(

    //     //         'user_id'      => $request['user_id'],
    //     //         'description' => 'Updated Redline / Commission / Upfront Data by ' . auth()->user()->first_name,
    //     //         'type'         => 'Redline / Commission / Upfront',
    //     //         'is_read' => 0,
    //     //     );

    //     //    $notify =  event(new UserloginNotification($user));

    //     //     return response()->json([
    //     //         'ApiName' => 'Update Compensation',
    //     //         'status' => true,
    //     //         'message' => 'Updated Successfully.',
    //     //     ], 200);
    //     // }else{
    //     //     return response()->json([
    //     //         'ApiName' => 'Update Compensation',
    //     //         'status' => false,
    //     //         'message' => 'Bad Request',
    //     //     ], 400);
    //     // }

    // }

    public function UserCompensationOld(Request $request): JsonResponse
    {
        // this code is not change.
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $validator = Validator::make($request->all(), [
                'employee_compensation.0.commission_type' => 'nullable|in:percent',
                'employee_compensation.0.upfront_sale_type' => 'nullable|in:per sale',
                'employee_compensation.0.withheld_type' => 'nullable|in:per sale',
            ], [
                'employee_compensation.0.commission_type.in' => 'Invalid Commission Type.',
                'employee_compensation.0.upfront_sale_type.in' => 'Invalid Upfront Type.',
                'employee_compensation.0.withheld_type.in' => 'Invalid Withheld Type.',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 400);
            }
        }

        $user_id = $request->user_id;
        $user = User::find($request->user_id);
        if (! empty($user->aveyo_hs_id)) {
            $aveyoid = $user->aveyo_hs_id;
        }
        $uid = auth()->user()->id;

        if (! $user) {
            return response()->json([
                'ApiName' => 'No User found',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

        $udata = $this->userDataById($request->user_id);
        $reqdata = $request;
        $result = $this->compensationDataComp($udata, $reqdata);

        $user_id = $request->user_id;
        $user = User::find($request->user_id);
        if (! empty($user->aveyo_hs_id)) {
            $aveyoid = $user->aveyo_hs_id;
        }
        $uid = auth()->user()->id;

        if (! empty($user)) {
            $employee_compensation = $request->employee_compensation;

            foreach ($employee_compensation as $key => $ec) {
                if ($key == 1 && in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    // No Need To Save SelfGen Data
                } else {
                    if ($key == 0) {
                        $self_gen_user = 0;
                        $sub_position_id = (isset($ec['sub_position_id'])) ? $ec['sub_position_id'] : $user->sub_position_id;
                    } else {
                        $self_gen_user = 1;
                        $sub_position_id = (isset($ec['sub_position_id'])) ? $ec['sub_position_id'] : $ec['position_id'];
                    }

                    // $commission_date_history = UserOrganizationHistory::where('user_id',$user_id)->where('effective_date',$ec['commission_effective_date'])->first();

                    $commission = UserCommissionHistory::where('user_id', $request->user_id)
                        ->where('commission_effective_date', '=', date('Y-m-d', strtotime($ec['commission_effective_date'])))
                        ->where('position_id', $ec['position_id'])
                        ->where('sub_position_id', $sub_position_id)
                        ->first();
                    $prev_commission = UserCommissionHistory::where('user_id', $request->user_id)
                        ->where('commission_effective_date', '<', date('Y-m-d', strtotime($ec['commission_effective_date'])))
                        ->orderBy('commission_effective_date', 'DESC')
                        ->where('position_id', $ec['position_id'])
                        ->where('sub_position_id', $sub_position_id)
                        ->first();
                    $next_commission = UserCommissionHistory::where('user_id', $request->user_id)
                        ->where('commission_effective_date', '>', date('Y-m-d', strtotime($ec['commission_effective_date'])))
                        ->where('position_id', $ec['position_id'])
                        ->where('sub_position_id', $sub_position_id)
                        ->orderBy('commission_effective_date', 'ASC')
                        ->first();
                    if (empty($prev_commission) && empty($next_commission)) {
                        if (empty($commission)) {
                            UserCommissionHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $ec['commission'],
                                    'commission_type' => $ec['commission_type'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => 0,
                                    'old_commission' => 0,
                                ]
                            );
                        } else {
                            UserCommissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($commission->old_self_gen_user) ? $commission->old_self_gen_user : 0,
                                        'commission' => $ec['commission'],
                                        'commission_type' => $ec['commission_type'],
                                        'old_commission' => isset($commission->old_commission) ? $commission->old_commission : 0,
                                    ]);
                        }
                    } elseif (! empty($prev_commission) && empty($next_commission)) {
                        // $data->commission = $commission_data->commission;
                        if (empty($commission)) {
                            UserCommissionHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $ec['commission'],
                                    'commission_type' => $ec['commission_type'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => isset($prev_commission->self_gen_user) ? $prev_commission->self_gen_user : 0,
                                    'old_commission' => isset($prev_commission->commission) ? $prev_commission->commission : 0,
                                ]
                            );
                        } else {
                            UserCommissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'commission' => $ec['commission'],
                                        'commission_type' => $ec['commission_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($prev_commission->self_gen_user) ? $prev_commission->self_gen_user : 0,
                                        'old_commission' => isset($prev_commission->commission) ? $prev_commission->commission : 0,
                                    ]);
                        }

                    } elseif (! empty($prev_commission) && ! empty($next_commission)) {
                        if (empty($commission)) {
                            UserCommissionHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $ec['commission'],
                                    'commission_type' => $ec['commission_type'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => isset($prev_commission->self_gen_user) ? $prev_commission->self_gen_user : 0,
                                    'old_commission' => isset($prev_commission->commission) ? $prev_commission->commission : 0,
                                ]
                            );
                        } else {
                            UserCommissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'commission' => $ec['commission'],
                                        'commission_type' => $ec['commission_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($prev_commission->self_gen_user) ? $prev_commission->self_gen_user : 0,
                                        'old_commission' => isset($prev_commission->commission) ? $prev_commission->commission : 0,
                                    ]);
                        }
                        $next_commission->old_commission = $ec['commission'];
                        $next_commission->save();
                    } elseif (empty($prev_commission) && ! empty($next_commission)) {
                        if (empty($commission)) {
                            UserCommissionHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => 0,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $ec['commission'],
                                    'commission_type' => $ec['commission_type'],
                                    'old_commission' => 0,
                                ]
                            );
                        } else {
                            UserCommissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'commission' => $ec['commission'],
                                        'commission_type' => $ec['commission_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => 0,
                                        'old_commission' => 0,
                                    ]);
                        }
                        $next_commission->old_commission = $ec['commission'];
                        $next_commission->save();
                    }

                    if (isset($ec['upfront_pay_amount'])) {
                        // $upfront_date_history = UserOrganizationHistory::where('user_id',$user_id)->where('effective_date',$ec['upfront_effective_date'])->first();

                        $upfront = UserUpfrontHistory::where('user_id', $request->user_id)
                            ->where('position_id', $ec['position_id'])
                            ->where('sub_position_id', $sub_position_id)
                            ->where('upfront_effective_date', '=', date('Y-m-d', strtotime($ec['upfront_effective_date'])))
                            ->first();
                        $upfront_prev = UserUpfrontHistory::where('user_id', $request->user_id)
                            ->where('position_id', $ec['position_id'])
                            ->where('sub_position_id', $sub_position_id)
                            ->where('upfront_effective_date', '<', date('Y-m-d', strtotime($ec['upfront_effective_date'])))
                            ->orderBy('upfront_effective_date', 'DESC')->first();
                        $upfront_next = UserUpfrontHistory::where('user_id', $request->user_id)
                            ->where('position_id', $ec['position_id'])
                            ->where('sub_position_id', $sub_position_id)
                            ->where('upfront_effective_date', '>', date('Y-m-d', strtotime($ec['upfront_effective_date'])))->orderBy('upfront_effective_date', 'ASC')->first();
                        if (empty($upfront_prev) && empty($upfront_next)) {
                            if (empty($upfront)) {
                                $checkdata = UserUpfrontHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                        'upfront_sale_type' => $ec['upfront_sale_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => 0,
                                        'old_upfront_pay_amount' => 0,
                                        'old_upfront_sale_type' => '',
                                    ]);
                            } else {
                                $checkdata = UserUpfrontHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update(
                                        [
                                            'updater_id' => auth()->user()->id,
                                            'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                            'upfront_sale_type' => $ec['upfront_sale_type'],
                                            'self_gen_user' => $self_gen_user,
                                            'old_self_gen_user' => isset($upfront->old_self_gen_user) ? $upfront->old_self_gen_user : 0,
                                            'old_upfront_pay_amount' => isset($upfront->old_upfront_pay_amount) ? $upfront->old_upfront_pay_amount : 0,
                                            'old_upfront_sale_type' => isset($upfront->old_upfront_sale_type) ? $upfront->old_upfront_sale_type : '',
                                        ]);
                            }
                        } elseif (empty($upfront_prev) && ! empty($upfront_next)) {
                            if (empty($upfront)) {
                                $checkdata = UserUpfrontHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                        'upfront_sale_type' => $ec['upfront_sale_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => 0,
                                        'old_upfront_pay_amount' => 0,
                                        'old_upfront_sale_type' => '',
                                    ]);
                            } else {
                                $checkdata = UserUpfrontHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update(
                                        [
                                            'updater_id' => auth()->user()->id,
                                            'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                            'upfront_sale_type' => $ec['upfront_sale_type'],
                                            'self_gen_user' => $self_gen_user,
                                            'old_self_gen_user' => isset($upfront->old_self_gen_user) ? $upfront->old_self_gen_user : 0,
                                            'old_upfront_pay_amount' => isset($upfront->old_upfront_pay_amount) ? $upfront->old_upfront_pay_amount : 0,
                                            'old_upfront_sale_type' => isset($upfront->old_upfront_sale_type) ? $upfront->old_upfront_sale_type : '',
                                        ]);
                            }
                            $upfront_next->old_upfront_pay_amount = $ec['upfront_pay_amount'];
                            $upfront_next->old_upfront_sale_type = $ec['upfront_sale_type'];
                            $upfront_next->save();

                        } elseif (! empty($upfront_prev) && empty($upfront_next)) {
                            if (empty($upfront)) {
                                $checkdata = UserUpfrontHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                        'upfront_sale_type' => $ec['upfront_sale_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($upfront_prev->self_gen_user) ? $upfront_prev->self_gen_user : 0,
                                        'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount) ? $upfront_prev->upfront_pay_amount : 0,
                                        'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type) ? $upfront_prev->upfront_sale_type : '',
                                    ]);
                            } else {
                                $checkdata = UserUpfrontHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update(
                                        [
                                            'updater_id' => auth()->user()->id,
                                            'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                            'upfront_sale_type' => $ec['upfront_sale_type'],
                                            'self_gen_user' => $self_gen_user,
                                            'old_self_gen_user' => isset($upfront_prev->self_gen_user) ? $upfront_prev->self_gen_user : 0,
                                            'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount) ? $upfront_prev->upfront_pay_amount : 0,
                                            'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type) ? $upfront_prev->upfront_sale_type : '',
                                        ]);
                            }
                        } elseif (! empty($upfront_prev) && ! empty($upfront_next)) {
                            if (empty($upfront)) {
                                $checkdata = UserUpfrontHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                        'upfront_sale_type' => $ec['upfront_sale_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($upfront_prev->self_gen_user) ? $upfront_prev->self_gen_user : 0,
                                        'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount) ? $upfront_prev->upfront_pay_amount : 0,
                                        'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type) ? $upfront_prev->upfront_sale_type : '',
                                    ]);
                            } else {
                                $checkdata = UserUpfrontHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update(
                                        [
                                            'updater_id' => auth()->user()->id,
                                            'upfront_pay_amount' => $ec['upfront_pay_amount'],
                                            'upfront_sale_type' => $ec['upfront_sale_type'],
                                            'self_gen_user' => $self_gen_user,
                                            'old_self_gen_user' => isset($upfront_prev->self_gen_user) ? $upfront_prev->self_gen_user : 0,
                                            'old_upfront_pay_amount' => isset($upfront_prev->upfront_pay_amount) ? $upfront_prev->upfront_pay_amount : 0,
                                            'old_upfront_sale_type' => isset($upfront_prev->upfront_sale_type) ? $upfront_prev->upfront_sale_type : '',
                                        ]);
                            }
                            $upfront_next->old_upfront_pay_amount = $ec['upfront_pay_amount'];
                            $upfront_next->old_upfront_sale_type = $ec['upfront_sale_type'];
                            $upfront_next->save();
                        }
                    }
                    if (isset($ec['withheld_amount'])) {
                        // $withheld_date_history = UserOrganizationHistory::where('user_id',$user_id)->where('effective_date',$ec['withheld_effective_date'])->first();

                        $withheld = UserWithheldHistory::where('user_id', $request->user_id)
                            ->where('withheld_effective_date', '=', date('Y-m-d', strtotime($ec['withheld_effective_date'])))
                            ->where('position_id', $ec['position_id'])
                            ->where('sub_position_id', $sub_position_id)
                            ->first();
                        $withheld_prev = UserWithheldHistory::where('user_id', $request->user_id)
                            ->where('withheld_effective_date', '<', date('Y-m-d', strtotime($ec['withheld_effective_date'])))
                            ->where('position_id', $ec['position_id'])
                            ->where('sub_position_id', $sub_position_id)
                            ->orderBy('withheld_effective_date', 'DESC')->first();
                        $withheld_next = UserWithheldHistory::where('user_id', $request->user_id)
                            ->where('withheld_effective_date', '>', date('Y-m-d', strtotime($ec['withheld_effective_date'])))
                            ->where('position_id', $ec['position_id'])
                            ->where('sub_position_id', $sub_position_id)
                            ->orderBy('withheld_effective_date', 'ASC')->first();
                        if (empty($withheld_prev) && empty($withheld_next)) {
                            if (empty($withheld)) {
                                $checkdata = UserWithheldHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => 0,
                                        'old_withheld_amount' => 0,
                                        'old_withheld_type' => '',
                                    ]
                                );
                            } else {
                                $checkdata = UserWithheldHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update([
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($withheld->old_self_gen_user) ? $withheld->old_self_gen_user : 0,
                                        'old_withheld_amount' => isset($withheld->old_withheld_amount) ? $withheld->old_withheld_amount : 0,
                                        'old_withheld_type' => isset($withheld->old_withheld_type) ? $withheld->old_withheld_type : '',
                                    ]
                                    );
                            }
                        } elseif (empty($withheld_prev) && ! empty($withheld_next)) {
                            if (empty($withheld)) {
                                $checkdata = UserWithheldHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => 0,
                                        'old_withheld_amount' => 0,
                                        'old_withheld_type' => '',
                                    ]
                                );
                            } else {
                                $checkdata = UserWithheldHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update([
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => 0,
                                        'old_withheld_amount' => 0,
                                        'old_withheld_type' => '',
                                    ]
                                    );
                            }
                            $withheld_next->old_withheld_amount = $ec['withheld_amount'];
                            $withheld_next->old_withheld_type = $ec['withheld_type'];
                            $withheld_next->save();

                        } elseif (! empty($withheld_prev) && empty($withheld_next)) {
                            if (empty($withheld)) {
                                $checkdata = UserWithheldHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($withheld_prev->self_gen_user) ? $withheld_prev->self_gen_user : 0,
                                        'old_withheld_amount' => isset($withheld_prev->withheld_amount) ? $withheld_prev->withheld_amount : 0,
                                        'old_withheld_type' => isset($withheld_prev->withheld_type) ? $withheld_prev->withheld_type : '',
                                    ]
                                );
                            } else {
                                $checkdata = UserWithheldHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update([
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($withheld_prev->self_gen_user) ? $withheld_prev->self_gen_user : 0,
                                        'old_withheld_amount' => isset($withheld_prev->withheld_amount) ? $withheld_prev->withheld_amount : 0,
                                        'old_withheld_type' => isset($withheld_prev->withheld_type) ? $withheld_prev->withheld_type : '',
                                    ]
                                    );
                            }
                        } elseif (! empty($withheld_prev) && ! empty($withheld_next)) {
                            if (empty($withheld)) {
                                $checkdata = UserWithheldHistory::Create(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($withheld_prev->self_gen_user) ? $withheld_prev->self_gen_user : 0,
                                        'old_withheld_amount' => isset($withheld_prev->withheld_amount) ? $withheld_prev->withheld_amount : 0,
                                        'old_withheld_type' => isset($withheld_prev->withheld_type) ? $withheld_prev->withheld_type : '',
                                    ]
                                );
                            } else {
                                $checkdata = UserWithheldHistory::where(
                                    [
                                        'user_id' => $request->user_id,
                                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                                        'position_id' => $ec['position_id'],
                                        'sub_position_id' => $sub_position_id,
                                    ])->update([
                                        'updater_id' => auth()->user()->id,
                                        'withheld_amount' => $ec['withheld_amount'],
                                        'withheld_type' => $ec['withheld_type'],
                                        'self_gen_user' => $self_gen_user,
                                        'old_self_gen_user' => isset($withheld_prev->self_gen_user) ? $withheld_prev->self_gen_user : 0,
                                        'old_withheld_amount' => isset($withheld_prev->withheld_amount) ? $withheld_prev->withheld_amount : 0,
                                        'old_withheld_type' => isset($withheld_prev->withheld_type) ? $withheld_prev->withheld_type : '',
                                    ]
                                    );
                            }
                            $withheld_next->old_withheld_amount = $ec['withheld_amount'];
                            $withheld_next->old_withheld_type = $ec['withheld_type'];
                            $withheld_next->save();
                        }

                    }

                    $redline = UserRedlines::where('user_id', $request->user_id)
                        ->where('start_date', '=', date('Y-m-d', strtotime($ec['redline_effective_date'])))
                        ->where('position_type', $ec['position_id'])
                        ->where('sub_position_type', $sub_position_id)
                        ->first();
                    $prev_redline = UserRedlines::where('user_id', $request->user_id)
                        ->where('start_date', '<', date('Y-m-d', strtotime($ec['redline_effective_date'])))
                        ->where('position_type', $ec['position_id'])
                        ->where('sub_position_type', $sub_position_id)
                        ->orderBy('start_date', 'DESC')->first();
                    $next_redline = UserRedlines::where('user_id', $request->user_id)
                        ->where('start_date', '>', date('Y-m-d', strtotime($ec['redline_effective_date'])))
                        ->where('position_type', $ec['position_id'])
                        ->where('sub_position_type', $sub_position_id)
                        ->orderBy('start_date', 'ASC')->first();
                    if (empty($prev_redline) && empty($next_redline) && ! empty($ec['redline_effective_date'])) {
                        if (empty($redline)) {
                            $checkdata = UserRedlines::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => 0,
                                    'old_redline_amount_type' => '',
                                    'old_redline_type' => '',
                                ]);
                        } else {
                            $checkdata = UserRedlines::where(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => isset($redline->old_self_gen_user) ? $redline->old_self_gen_user : 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => isset($redline->old_redline) ? $redline->old_redline : 0,
                                    'old_redline_amount_type' => isset($redline->old_redline_amount_type) ? $redline->old_redline_amount_type : '',
                                    'old_redline_type' => isset($redline->old_redline_type) ? $redline->old_redline_type : '',
                                ]);
                        }
                    } elseif (empty($prev_redline) && ! empty($next_redline) && ! empty($ec['redline_effective_date'])) {
                        if (empty($redline)) {
                            $checkdata = UserRedlines::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => 0,
                                    'old_redline_amount_type' => '',
                                    'old_redline_type' => '',
                                ]);
                        } else {
                            $checkdata = UserRedlines::where(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => 0,
                                    'old_redline_amount_type' => '',
                                    'old_redline_type' => '',
                                ]);
                        }
                        $next_redline->old_redline = $ec['redline'];
                        $next_redline->old_redline_type = $ec['redline_type'];
                        $next_redline->old_redline_amount_type = $ec['redline_amount_type'];
                        $next_redline->save();
                    } elseif (! empty($prev_redline) && empty($next_redline) && ! empty($ec['redline_effective_date'])) {
                        if (empty($redline)) {
                            $checkdata = UserRedlines::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => isset($prev_redline->self_gen_user) ? $prev_redline->self_gen_user : 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => isset($prev_redline->redline) ? $prev_redline->redline : 0,
                                    'old_redline_amount_type' => isset($prev_redline->redline_amount_type) ? $prev_redline->redline_amount_type : '',
                                    'old_redline_type' => isset($prev_redline->redline_type) ? $prev_redline->redline_type : '',
                                ]);
                        } else {
                            $checkdata = UserRedlines::where(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => isset($prev_redline->self_gen_user) ? $prev_redline->self_gen_user : 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => isset($prev_redline->redline) ? $prev_redline->redline : 0,
                                    'old_redline_amount_type' => isset($prev_redline->redline_amount_type) ? $prev_redline->redline_amount_type : '',
                                    'old_redline_type' => isset($prev_redline->redline_type) ? $prev_redline->redline_type : '',
                                ]);
                        }
                    } elseif (! empty($prev_redline) && ! empty($next_redline) && ! empty($ec['redline_effective_date'])) {
                        if (empty($redline)) {
                            $checkdata = UserRedlines::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => isset($prev_redline->self_gen_user) ? $prev_redline->self_gen_user : 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => isset($prev_redline->redline) ? $prev_redline->redline : 0,
                                    'old_redline_amount_type' => isset($prev_redline->redline_amount_type) ? $prev_redline->redline_amount_type : '',
                                    'old_redline_type' => isset($prev_redline->redline_type) ? $prev_redline->redline_type : '',
                                ]);
                        } else {
                            $checkdata = UserRedlines::where(
                                [
                                    'user_id' => $request->user_id,
                                    'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                                    'position_type' => $ec['position_id'],
                                    'sub_position_type' => $sub_position_id,
                                ])->update([
                                    'updater_id' => auth()->user()->id,
                                    'redline_amount_type' => $ec['redline_amount_type'],
                                    'redline' => $ec['redline'],
                                    'self_gen_user' => $self_gen_user,
                                    'old_self_gen_user' => isset($prev_redline->self_gen_user) ? $prev_redline->self_gen_user : 0,
                                    'redline_type' => $ec['redline_type'],
                                    'old_redline' => isset($prev_redline->redline) ? $prev_redline->redline : 0,
                                    'old_redline_amount_type' => isset($prev_redline->redline_amount_type) ? $prev_redline->redline_amount_type : '',
                                    'old_redline_type' => isset($prev_redline->redline_type) ? $prev_redline->redline_type : '',
                                ]);
                        }
                        $next_redline->old_redline = $ec['redline'];
                        $next_redline->old_redline_type = $ec['redline_type'];
                        $next_redline->old_redline_amount_type = $ec['redline_amount_type'];
                        $next_redline->save();
                    }

                    $today = date('Y-m-d');
                    $UserCommissionHistory = UserCommissionHistory::where('user_id', $user_id)
                        ->where('position_id', $ec['position_id'])
                        ->where('sub_position_id', $sub_position_id)
                        ->where('commission_effective_date', '<=', $today)
                        ->orderBy('commission_effective_date', 'DESC')
                        ->orderBy('id', 'ASC')
                        ->first();

                    $UserUpfrontHistory = UserUpfrontHistory::where('user_id', $user_id)
                        ->where('position_id', $ec['position_id'])
                        ->where('sub_position_id', $sub_position_id)
                        ->where('upfront_effective_date', '<=', $today)
                        ->orderBy('upfront_effective_date', 'DESC')
                        ->orderBy('id', 'ASC')
                        ->first();

                    $UserRedlines = UserRedlines::where('user_id', $user_id)
                        ->where('position_type', $ec['position_id'])
                        ->where('sub_position_type', $sub_position_id)
                        ->where('start_date', '<=', $today)
                        ->orderBy('start_date', 'DESC')
                        ->orderBy('id', 'ASC')
                        ->first();

                    $UserWithheldHistory = UserWithheldHistory::where('user_id', $user_id)
                        ->where('position_id', $ec['position_id'])
                        ->where('sub_position_id', $sub_position_id)
                        ->where('withheld_effective_date', '<=', $today)
                        ->orderBy('withheld_effective_date', 'DESC')
                        ->orderBy('id', 'ASC')
                        ->first();

                    if ($key == 0) {
                        // commission

                        if ($user->commission != $ec['commission'] || $user->commission_type != $ec['commission_type']) {
                            if (! empty($user->commission_effective_date) && strtotime($user->commission_effective_date) < strtotime($ec['commission_effective_date']) && strtotime($ec['commission_effective_date']) <= strtotime(date('Y-m-d'))) {
                                $user->commission = $ec['commission'];
                                $user->commission_type = $ec['commission_type'];
                                $user->commission_effective_date = $ec['commission_effective_date'];
                            } elseif (empty($user->commission_effective_date) || (isset($UserCommissionHistory->commission_effective_date) && strtotime($user->commission_effective_date) <= strtotime($UserCommissionHistory->commission_effective_date))) {
                                $user->commission = isset($UserCommissionHistory->commission) ? $UserCommissionHistory->commission : $user->commission;
                                $user->commission_effective_date = isset($UserCommissionHistory->commission_effective_date) ? $UserCommissionHistory->commission_effective_date : $user->commission_effective_date;
                            }
                        }

                        // redline
                        if ($user->redline_amount_type != $ec['redline_amount_type'] || $user->redline != $ec['redline'] || $user->redline_type != $ec['redline_type']) {
                            if (! empty($user->redline_effective_date) && strtotime($user->redline_effective_date) < strtotime($ec['redline_effective_date']) && strtotime($ec['redline_effective_date']) <= strtotime(date('Y-m-d'))) {
                                $user->redline = isset($ec['redline']) ? $ec['redline'] : 0;
                                $user->redline_amount_type = isset($ec['redline_amount_type']) ? $ec['redline_amount_type'] : 'Fixed';
                                $user->redline_type = isset($ec['redline_type']) ? $ec['redline_type'] : 'per watt';
                                $user->redline_effective_date = isset($ec['redline_effective_date']) ? $ec['redline_effective_date'] : null;
                            } elseif (empty($user->redline_effective_date) || (isset($UserRedlines->start_date) && strtotime($user->redline_effective_date) <= strtotime($UserRedlines->start_date))) {
                                $user->redline = isset($UserRedlines->redline) ? $UserRedlines->redline : $user->redline;
                                $user->redline_amount_type = isset($UserRedlines->redline_amount_type) ? $UserRedlines->redline_amount_type : $user->redline_amount_type;
                                $user->redline_type = isset($UserRedlines->redline_type) ? $UserRedlines->redline_type : $user->redline_type;
                                $user->redline_effective_date = isset($UserRedlines->start_date) ? $UserRedlines->start_date : $user->redline_effective_date;
                            }
                        }

                        // upfront pay
                        if ($user->upfront_pay_amount != $ec['upfront_pay_amount'] || $user->upfront_sale_type != $ec['upfront_sale_type']) {
                            if (! empty($user->upfront_effective_date) && strtotime($user->upfront_effective_date) < strtotime($ec['upfront_effective_date']) && strtotime($ec['upfront_effective_date']) <= strtotime(date('Y-m-d'))) {
                                $user->upfront_pay_amount = $ec['upfront_pay_amount'];
                                $user->upfront_sale_type = $ec['upfront_sale_type'];
                                $user->upfront_effective_date = $ec['upfront_effective_date'];
                            } elseif (empty($user->upfront_effective_date) || (isset($UserUpfrontHistory->upfront_effective_date) && strtotime($user->upfront_effective_date) <= strtotime($UserUpfrontHistory->upfront_effective_date))) {
                                $user->upfront_pay_amount = isset($UserUpfrontHistory->upfront_pay_amount) ? $UserUpfrontHistory->upfront_pay_amount : $user->upfront_pay_amount;
                                $user->upfront_sale_type = isset($UserUpfrontHistory->upfront_sale_type) ? $UserUpfrontHistory->upfront_sale_type : $user->upfront_sale_type;
                                $user->upfront_effective_date = isset($UserUpfrontHistory->upfront_effective_date) ? $UserUpfrontHistory->upfront_effective_date : $user->upfront_effective_date;
                            }
                        }

                        // withheld_amount
                        if ($user->withheld_amount != $ec['withheld_amount'] || $user->withheld_type != $ec['withheld_type']) {
                            if (! empty($user->withheld_effective_date) && strtotime($user->withheld_effective_date) < strtotime($ec['withheld_effective_date']) && strtotime($ec['withheld_effective_date']) <= strtotime(date('Y-m-d'))) {
                                $user->withheld_amount = $ec['withheld_amount'];
                                $user->withheld_type = $ec['withheld_type'];
                                $user->withheld_effective_date = $ec['withheld_effective_date'];
                            } elseif (empty($user->withheld_effective_date) || (isset($UserWithheldHistory->withheld_effective_date) && strtotime($user->withheld_effective_date) <= strtotime($UserWithheldHistory->withheld_effective_date))) {
                                $user->withheld_amount = isset($UserWithheldHistory->withheld_amount) ? $UserWithheldHistory->withheld_amount : $user->withheld_amount;
                                $user->withheld_type = isset($UserWithheldHistory->withheld_type) ? $UserWithheldHistory->withheld_type : $user->withheld_type;
                                $user->withheld_effective_date = isset($UserWithheldHistory->withheld_effective_date) ? $UserWithheldHistory->withheld_effective_date : $user->withheld_effective_date;
                            }
                        }
                    } else {

                        // self_gen_commission
                        if ($user->self_gen_commission != $ec['commission'] || $user->self_gen_commission_type != $ec['commission_type']) {
                            if (! empty($user->self_gen_commission_effective_date) && strtotime($user->self_gen_commission_effective_date) < strtotime($ec['commission_effective_date']) && strtotime($ec['commission_effective_date']) <= strtotime(date('Y-m-d'))) {
                                $user->self_gen_commission = $ec['commission'];
                                $user->self_gen_commission_type = $ec['commission_type'];
                                $user->self_gen_commission_effective_date = $ec['commission_effective_date'];
                            } elseif (empty($user->self_gen_commission_effective_date) || (isset($UserCommissionHistory->commission_effective_date) && strtotime($user->commission_effective_date) <= strtotime($UserCommissionHistory->commission_effective_date))) {
                                $user->self_gen_commission = isset($UserCommissionHistory->commission) ? $UserCommissionHistory->commission : $user->self_gen_commission;
                                $user->self_gen_commission_effective_date = isset($UserCommissionHistory->commission_effective_date) ? $UserCommissionHistory->commission_effective_date : $user->self_gen_commission_effective_date;
                            }
                        }

                        // self_gen_redline
                        if ($user->self_gen_redline != $ec['redline'] || $user->self_gen_redline_amount_type != $ec['redline_amount_type'] || $user->self_gen_redline_type != $ec['redline_type']) {
                            if (! empty($user->self_gen_redline_effective_date) && strtotime($user->self_gen_redline_effective_date) < strtotime($ec['redline_effective_date']) && strtotime($ec['redline_effective_date']) <= strtotime(date('Y-m-d'))) {
                                $user->self_gen_redline = isset($ec['redline']) ? $ec['redline'] : 0;
                                $user->self_gen_redline_amount_type = isset($ec['redline_amount_type']) ? $ec['redline_amount_type'] : 'Fixed';
                                $user->self_gen_redline_type = isset($ec['redline_type']) ? $ec['redline_type'] : 'per watt';
                                $user->self_gen_redline_effective_date = isset($ec['redline_effective_date']) ? $ec['redline_effective_date'] : null;
                            } elseif (empty($user->self_gen_redline_effective_date) || (isset($UserRedlines->start_date) && strtotime($user->redline_effective_date) <= strtotime($UserRedlines->start_date))) {
                                $user->self_gen_redline = isset($UserRedlines->redline) ? $UserRedlines->redline : $user->self_gen_redline;
                                $user->self_gen_redline_amount_type = isset($UserRedlines->redline_amount_type) ? $UserRedlines->redline_amount_type : $user->self_gen_redline_amount_type;
                                $user->self_gen_redline_type = isset($UserRedlines->redline_type) ? $UserRedlines->redline_type : $user->self_gen_redline_type;
                                $user->self_gen_redline_effective_date = isset($UserRedlines->start_date) ? $UserRedlines->start_date : $user->self_gen_redline_effective_date;
                            }
                        }

                        // self_gen_upfront
                        if ($user->self_gen_upfront_amount != $ec['upfront_pay_amount'] || $user->self_gen_upfront_type != $ec['upfront_sale_type']) {
                            if (! empty($user->self_gen_upfront_effective_date) && strtotime($user->self_gen_upfront_effective_date) < strtotime($ec['upfront_effective_date']) && strtotime($ec['upfront_effective_date']) <= strtotime(date('Y-m-d'))) {
                                $user->self_gen_upfront_amount = $ec['upfront_pay_amount'];
                                $user->self_gen_upfront_type = $ec['upfront_sale_type'];
                                $user->self_gen_upfront_effective_date = $ec['upfront_effective_date'];
                            } elseif (empty($user->self_gen_upfront_effective_date) || (isset($UserUpfrontHistory->upfront_effective_date) && strtotime($user->upfront_effective_date) <= strtotime($UserUpfrontHistory->upfront_effective_date))) {
                                $user->self_gen_upfront_amount = isset($UserUpfrontHistory->upfront_pay_amount) ? $UserUpfrontHistory->upfront_pay_amount : $user->self_gen_upfront_amount;
                                $user->self_gen_upfront_type = isset($UserUpfrontHistory->upfront_sale_type) ? $UserUpfrontHistory->upfront_sale_type : $user->self_gen_upfront_type;
                                $user->self_gen_upfront_effective_date = isset($UserUpfrontHistory->upfront_effective_date) ? $UserUpfrontHistory->upfront_effective_date : $user->self_gen_upfront_effective_date;
                            }
                        }

                        // self_gen_withheld
                        if ($user->self_gen_withheld_amount != $ec['withheld_amount'] || $user->self_gen_withheld_type != $ec['withheld_type'] || $user->self_gen_withheld_effective_date) {
                            if (! empty($user->self_gen_withheld_effective_date) && strtotime($user->self_gen_withheld_effective_date) < strtotime($ec['withheld_effective_date']) && strtotime($ec['withheld_effective_date']) <= strtotime(date('Y-m-d'))) {
                                // if(strtotime($ec['withheld_effective_date']) == strtotime(date('Y-m-d')) ){
                                $user->self_gen_withheld_amount = $ec['withheld_amount'];
                                $user->self_gen_withheld_type = $ec['withheld_type'];
                                $user->self_gen_withheld_effective_date = $ec['withheld_effective_date'];
                            } elseif (empty($user->self_gen_withheld_effective_date) || (isset($UserWithheldHistory->withheld_effective_date) && strtotime($user->withheld_effective_date) <= strtotime($UserWithheldHistory->withheld_effective_date))) {
                                $user->self_gen_withheld_amount = isset($UserWithheldHistory->withheld_amount) ? $UserWithheldHistory->withheld_amount : $user->self_gen_withheld_amount;
                                $user->self_gen_withheld_type = isset($UserWithheldHistory->withheld_type) ? $UserWithheldHistory->withheld_type : $user->self_gen_withheld_type;
                                $user->self_gen_withheld_effective_date = isset($UserWithheldHistory->withheld_effective_date) ? $UserWithheldHistory->withheld_effective_date : $user->self_gen_withheld_effective_date;
                            }
                        }
                    }
                }
                $user->save();
            }
            if ($user->upfront_sale_type == 'per sale') {
                $upfrontType = 'Per Sale';
            } elseif ($user->upfront_sale_type == 'per kw') {
                $upfrontType = 'Per kw';
            }

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                // No Need To Update Hubspot Data
            } else {
                $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                if (! empty($CrmData) && ! empty($CrmSetting) && ! empty($aveyoid)) {
                    // $token ="pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641";
                    $val = json_decode($CrmSetting['value']);
                    $token = $val->api_key;

                    $Hubspotdata['properties'] = [

                        'upfront_pay_amount' => isset($user->upfront_pay_amount) ? $user->upfront_pay_amount : null,
                        'upfront_type' => isset($upfrontType) ? $upfrontType : null,
                        'commission' => isset($user->commission) ? $user->commission : null,
                        'redline' => isset($user->redline) ? $user->redline : null,
                        'setter_redline' => isset($user->self_gen_redline) ? $user->self_gen_redline : null,

                    ];
                    $update_employees = $this->update_employees($Hubspotdata, $token, $uid, $aveyoid);

                }
            }

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $description = 'Commission / Upfront Data by '.auth()->user()->first_name;
                $type = 'Commission / Upfront';
            } else {
                $description = 'Updated Redline / Commission / Upfront Data by '.auth()->user()->first_name;
                $type = 'Redline / Commission / Upfront';
            }
            $user = [
                'user_id' => $request['user_id'],
                'description' => $description,
                'type' => $type,
                'is_read' => 0,
            ];

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                UserSelfGenCommmissionHistory::where('user_id', $request->user_id)->delete();
            } else {
                if ($request['commission_selfgen'] != null && ! empty($request['commission_selfgen_effective_date'])) {
                    $commissionSelfgen = $request['commission_selfgen'];
                    $commissionSelfgenType = isset($request['commission_selfgen_type']) ? $request['commission_selfgen_type'] : null;
                    $commissionSelfgenEffectiveDate = $request['commission_selfgen_effective_date'];
                    $userPosition = User::where('id', $request->user_id)->first();

                    $selfGenComm = UserSelfGenCommmissionHistory::where('user_id', $request->user_id)
                        ->where('position_id', $userPosition->position_id)
                        ->where('sub_position_id', $userPosition->sub_position_id)
                        ->where('commission_effective_date', $commissionSelfgenEffectiveDate)
                        ->first();
                    $selfGenComm_prev = UserSelfGenCommmissionHistory::where('user_id', $request->user_id)
                        ->where('position_id', $userPosition->position_id)
                        ->where('sub_position_id', $userPosition->sub_position_id)
                        ->where('commission_effective_date', '<', $commissionSelfgenEffectiveDate)
                        ->orderby('commission_effective_date', 'desc')
                        ->first();
                    $selfGenComm_next = UserSelfGenCommmissionHistory::where('user_id', $request->user_id)
                        ->where('position_id', $userPosition->position_id)
                        ->where('sub_position_id', $userPosition->sub_position_id)
                        ->where('commission_effective_date', '>', $commissionSelfgenEffectiveDate)
                        ->orderby('commission_effective_date', 'asc')
                        ->first();
                    // Log::info([$selfGenComm->commission_effective_date,$selfGenComm_prev->commission_effective_date,$selfGenComm_next->commission_effective_date]);
                    if (empty($selfGenComm_prev) && empty($selfGenComm_next)) {
                        if (empty($selfGenComm)) {
                            UserSelfGenCommmissionHistory::create([
                                'user_id' => $request->user_id,
                                'updater_id' => Auth()->user()->id,
                                'commission' => $commissionSelfgen,
                                'commission_type' => $commissionSelfgenType,
                                'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                'old_commission' => 0,
                                'position_id' => $ec['position_id'],
                                'sub_position_id' => $sub_position_id,
                            ]);
                        } else {
                            UserSelfGenCommmissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'commission' => $commissionSelfgen,
                                        'commission_type' => $commissionSelfgenType,
                                        'old_commission' => isset($selfGenComm->old_commission) ? $selfGenComm->old_commission : 0,
                                        'old_commission_type' => isset($selfGenComm->old_commission_type) ? $selfGenComm->old_commission_type : null,
                                    ]);
                        }
                    } elseif (! empty($selfGenComm_prev) && empty($selfGenComm_next)) {

                        if (empty($selfGenComm)) {
                            UserSelfGenCommmissionHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $commissionSelfgen,
                                    'commission_type' => $commissionSelfgenType,
                                    'old_commission' => isset($selfGenComm_prev->commission) ? $selfGenComm_prev->commission : 0,
                                    'old_commission_type' => isset($selfGenComm_prev->old_commission_type) ? $selfGenComm_prev->old_commission_type : null,
                                ]
                            );
                        } else {
                            UserSelfGenCommmissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'commission' => $commissionSelfgen,
                                        'commission_type' => $commissionSelfgenType,
                                        'old_commission' => isset($selfGenComm_prev->commission) ? $selfGenComm_prev->commission : 0,
                                        'old_commission_type' => isset($selfGenComm_prev->old_commission_type) ? $selfGenComm_prev->old_commission_type : null,
                                    ]);
                        }

                    } elseif (! empty($selfGenComm_prev) && ! empty($selfGenComm_next)) {
                        if (empty($selfGenComm)) {
                            UserSelfGenCommmissionHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $commissionSelfgen,
                                    'commission_type' => $commissionSelfgenType,
                                    'old_commission' => isset($selfGenComm_prev->commission) ? $selfGenComm_prev->commission : 0,
                                    'old_commission_type' => isset($selfGenComm_prev->old_commission_type) ? $selfGenComm_prev->old_commission_type : null,
                                ]
                            );
                        } else {
                            UserSelfGenCommmissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'commission' => $commissionSelfgen,
                                        'commission_type' => $commissionSelfgenType,
                                        'old_commission' => isset($selfGenComm_prev->commission) ? $selfGenComm_prev->commission : 0,
                                        'old_commission_type' => isset($selfGenComm_prev->old_commission_type) ? $selfGenComm_prev->old_commission_type : null,
                                    ]);
                        }
                        $selfGenComm_next->old_commission = $commissionSelfgen;
                        $selfGenComm_next->save();
                    } elseif (empty($selfGenComm_prev) && ! empty($selfGenComm_next)) {
                        if (empty($selfGenComm)) {
                            UserSelfGenCommmissionHistory::Create(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $commissionSelfgen,
                                    'commission_type' => $commissionSelfgenType,
                                    'old_commission' => 0,
                                ]
                            );
                        } else {
                            UserSelfGenCommmissionHistory::where(
                                [
                                    'user_id' => $request->user_id,
                                    'commission_effective_date' => date('Y-m-d', strtotime($commissionSelfgenEffectiveDate)),
                                    'position_id' => $ec['position_id'],
                                    'sub_position_id' => $sub_position_id,
                                ])->update(
                                    [
                                        'updater_id' => auth()->user()->id,
                                        'commission' => $commissionSelfgen,
                                        'commission_type' => $commissionSelfgenType,
                                        'old_commission' => 0,
                                    ]);
                        }
                        $selfGenComm_next->old_commission = $commissionSelfgen;
                        $selfGenComm_next->save();
                    }
                }
            }

            // send mail here
            if (! empty($result)) {
                $check = User::where('id', $request->user_id)->first();
                $salesData = [];
                $salesData = SequiDocsEmailSettings::originization_employment_package_change_notification_email_content($check, $result);
                $salesData['email'] = $check->email;

                if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                    $this->sendEmailNotification($salesData);
                } else {
                    // no default blade based email template here
                }
            }

            $notify = event(new UserloginNotification($user));

            Artisan::call('generate:alert');

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

    public function UserCompensation(Request $request) // this code is use from demo branch
    {
        $udata = $this->userDataById($request->user_id);
        $reqdata = $request;
        $result = $this->compensationDataComp($udata, $reqdata);

        $user = User::find($request->user_id);
        $aveyoid = null;
        if (! empty($user->aveyo_hs_id)) {
            $aveyoid = $user->aveyo_hs_id;
        }
        $uid = auth()->user()->id;

        if (! empty($user)) {
            $employee_compensation = $request->employee_compensation;
            
            // Check if Custom Sales Fields feature is enabled ONCE before loop
            $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();
            
            foreach ($employee_compensation as $key => $ec) {
                if ($key == 0) {
                    $self_gen_user = 0;
                    $sub_position_id = (isset($ec['sub_position_id'])) ? $ec['sub_position_id'] : $user->sub_position_id;
                } else {
                    $self_gen_user = 1;
                    $sub_position_id = (isset($ec['sub_position_id'])) ? $ec['sub_position_id'] : $ec['position_id'];
                }

                // Parse custom_field_X format for commission_type (only if feature enabled, using cached check)
                $commissionType = $ec['commission_type'];
                $customSalesFieldId = $ec['custom_sales_field_id'] ?? null;
                if ($isCustomFieldsEnabled) {
                    if (preg_match('/^custom_field_(\d+)$/', $commissionType, $matches)) {
                        $commissionType = 'custom field';
                        $customSalesFieldId = (int) $matches[1];
                    }
                }

                UserCommissionHistory::updateOrCreate([
                    'user_id' => $request->user_id,
                    'commission_effective_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                    'self_gen_user' => $self_gen_user,
                    'sub_position_id' => $sub_position_id,
                ], [
                    'updater_id' => auth()->user()->id,
                    'commission' => $ec['commission'],
                    'commission_type' => $commissionType,
                    'custom_sales_field_id' => $customSalesFieldId,
                    'position_id' => $ec['position_id'],
                    'sub_position_id' => $sub_position_id,
                ]);

                if (isset($ec['upfront_pay_amount'])) {
                    UserUpfrontHistory::updateOrCreate([
                        'user_id' => $request->user_id,
                        'upfront_effective_date' => date('Y-m-d', strtotime($ec['upfront_effective_date'])),
                        'self_gen_user' => $self_gen_user,
                        'sub_position_id' => $sub_position_id,
                    ], [
                        'position_id' => $ec['position_id'],
                        'sub_position_id' => $sub_position_id,
                        'updater_id' => auth()->user()->id,
                        'upfront_pay_amount' => $ec['upfront_pay_amount'],
                        'upfront_sale_type' => $ec['upfront_sale_type'],
                    ]);
                }

                if (isset($ec['withheld_amount'])) {
                    UserWithheldHistory::updateOrCreate([
                        'user_id' => $request->user_id,
                        'withheld_effective_date' => date('Y-m-d', strtotime($ec['withheld_effective_date'])),
                        'self_gen_user' => $self_gen_user,
                        'sub_position_id' => $sub_position_id,
                    ], [
                        'position_id' => $ec['position_id'],
                        'sub_position_id' => $sub_position_id,
                        'updater_id' => auth()->user()->id,
                        'withheld_amount' => $ec['withheld_amount'],
                        'withheld_type' => $ec['withheld_type'],
                    ]);
                }

                if ($ec['commission_type'] == 'percent') {
                    UserRedlines::updateOrCreate([
                        'user_id' => $request->user_id,
                        'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                        'self_gen_user' => $self_gen_user,
                        'sub_position_type' => $sub_position_id,
                    ], [
                        'user_id' => $request->user_id,
                        'start_date' => date('Y-m-d', strtotime($ec['redline_effective_date'])),
                        'position_type' => $ec['position_id'],
                        'sub_position_type' => $sub_position_id,
                        'updater_id' => auth()->user()->id,
                        'redline_amount_type' => $ec['redline_amount_type'],
                        'redline' => $ec['redline'],
                        'redline_type' => $ec['redline_type'],
                    ]);
                } else {
                    UserRedlines::updateOrCreate([
                        'user_id' => $request->user_id,
                        'start_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                        'self_gen_user' => $self_gen_user,
                        'sub_position_type' => $sub_position_id,
                    ], [
                        'start_date' => date('Y-m-d', strtotime($ec['commission_effective_date'])),
                        'position_type' => $ec['position_id'],
                        'sub_position_type' => $sub_position_id,
                        'updater_id' => auth()->user()->id,
                        'redline_amount_type' => null,
                        'redline' => null,
                        'redline_type' => null,
                    ]);
                }
            }

            if ($request['commission_selfgen'] != null && ! empty($request['commission_selfgen_effective_date'])) {
                UserSelfGenCommmissionHistory::updateOrCreate([
                    'user_id' => $request->user_id,
                    'commission_effective_date' => date('Y-m-d', strtotime($request['commission_selfgen_effective_date'])),
                    'sub_position_id' => $sub_position_id,
                ], [
                    'user_id' => $request->user_id,
                    'updater_id' => Auth()->user()->id,
                    'commission' => $request['commission_selfgen'],
                    'commission_type' => isset($request['commission_selfgen_type']) ? $request['commission_selfgen_type'] : null,
                    'commission_effective_date' => date('Y-m-d', strtotime($request['commission_selfgen_effective_date'])),
                    'position_id' => $ec['position_id'],
                    'sub_position_id' => $sub_position_id,
                ]);
            }

            Artisan::call('ApplyHistoryOnUsers:update', ['user_id' => $request->user_id]);
            $user = User::where('id', $request->user_id)->first();
            if ($user->upfront_sale_type == 'per sale') {
                $upfrontType = 'Per Sale';
            } elseif ($user->upfront_sale_type == 'per kw') {
                $upfrontType = 'Per kw';
            }

            $CrmData = Crms::where('id', 2)->where('status', 1)->first();
            $CrmSetting = CrmSetting::where('crm_id', 2)->first();
            if (! empty($CrmData) && ! empty($CrmSetting) && ! empty($aveyoid)) {
                // $token ="pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641";
                $val = json_decode($CrmSetting['value']);
                $token = $val->api_key;

                $Hubspotdata['properties'] = [
                    'upfront_pay_amount' => isset($user->upfront_pay_amount) ? $user->upfront_pay_amount : null,
                    'upfront_type' => isset($upfrontType) ? $upfrontType : null,
                    'commission' => isset($user->commission) ? $user->commission : null,
                    'redline' => isset($user->redline) ? $user->redline : null,
                    'setter_redline' => isset($user->self_gen_redline) ? $user->self_gen_redline : null,
                ];
                $this->update_employees($Hubspotdata, $token, $uid, $aveyoid);
            }

            if ($request->recalculate) {
                $paidPid = UserCommission::where(['amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->pluck('pid');
                $pids = SalesMaster::whereHas('salesMasterProcess', function ($q) use ($user) {
                    $q->where('closer1_id', $user->id)->orWhere('closer2_id', $user->id)->orWhere('setter1_id', $user->id)->orWhere('setter2_id', $user->id);
                })->whereNull('date_cancelled')->whereNotNull('customer_signoff')->where(function ($q) {
                    $q->whereNotNull('m1_date')->orWhereNotNull('m2_date');
                })->whereNotIn('pid', $paidPid)->pluck('pid');

                if ($pids) {
                    $dataForPusher = [
                        'user_id' => $user->id,
                    ];
                    ProcessRecalculatesOpenSales::dispatch($pids, $dataForPusher);
                }
            }

            $user = [
                'user_id' => $request['user_id'],
                'description' => 'Updated Redline / Commission / Upfront Data by '.auth()->user()->first_name,
                'type' => 'Redline / Commission / Upfront',
                'is_read' => 0,
            ];

            // send mail here
            if (! empty($result)) {
                $check = User::where('id', $request->user_id)->first();
                $salesData = [];
                $salesData = SequiDocsEmailSettings::originization_employment_package_change_notification_email_content($check, $result);
                $salesData['email'] = $check->email;

                if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                    $this->sendEmailNotification($salesData);
                }
            }
            event(new UserloginNotification($user));
            dispatch(new GenerateAlertJob);

            return response()->json([
                'ApiName' => 'user_compensation',
                'status' => true,
                'message' => 'Saved Successfully.',
            ]);
        } else {
            return response()->json([
                'ApiName' => 'No User found',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }
    }

    public function UserCompensationSelfGen(Request $request)
    {
        $data2 = User::find($request->user_id);
        if ($data2) {
            $data2->commission = $request->employee_compensation['commission'];
            $data2->redline = $request->employee_compensation['redline_amount'];
            $data2->redline_amount_type = $request->employee_compensation['redline'];
            $data2->redline_type = $request->employee_compensation['redline_type'];
            $data2->upfront_pay_amount = $request->employee_compensation['upfront_pay_amount'];
            $data2->upfront_sale_type = $request->employee_compensation['upfront_sale_type'];
            $data2->save();

            $redline_data = $request->employee_compensation['redline_data'];
            if (count($redline_data) > 0) {
                $updater_id = Auth()->user()->id;
                $data = UserRedlines::where('user_id', $request->user_id)->where('self_gen_user', 1)->delete();
                foreach ($redline_data as $key => $value) {
                    if ($key == 0) {
                        $self_gen_user = 0;
                    } else {
                        $self_gen_user = 1;
                    }
                    if (! empty($value['redline_amount_type'])) {
                        $redline_amount_type = $value['redline_amount_type'];
                    } else {
                        // $redline_amount_type =  'Fixed';
                        $redline_amount_type = $request->employee_compensation['redline'];
                    }

                    $udata = [
                        'user_id' => $request['user_id'],
                        'updater_id' => $updater_id,
                        'redline' => $value['redline'],
                        'redline_type' => $value['redline_type'],
                        'redline_amount_type' => $redline_amount_type,
                        'self_gen_user' => $self_gen_user,
                        'start_date' => date('Y-m-d', strtotime($value['start_date'])),

                    ];
                    UserRedlines::create($udata);

                    // $pidData = UserCommission::whereRaw('"'.$udata['start_date'].'" between `pay_period_from` and `pay_period_to`')->where(['user_id'=> $udata['user_id'],'amount_type'=> 'm2'])->where('status','<>','3')->get();
                    $pidData = UserCommission::where('customer_signoff', '>=', $udata['start_date'])->where(['user_id' => $udata['user_id'], 'amount_type' => 'm2'])->where('status', '<>', '3')->get();
                    if (count($pidData) > 0) {
                        foreach ($pidData as $key => $value) {
                            (new ApiMissingDataController)->subroutine_process($value->pid);
                            // $subroutineProcess = $this->subroutine_process($value->pid);
                            // return $subroutineProcess = $this->subroutine_process($value->pid);
                        }
                    }
                }
            }

            return response()->json([
                'ApiName' => 'Update Compensation',
                'status' => true,
                'message' => 'Updated Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Update Compensation',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

    }

    public function compensationDataComp($udata, $reqdata)
    {
        $companyProfile = CompanyProfile::first();
        $data = [];
        if (! empty($udata) && ! empty($reqdata)) {

            if (! empty($reqdata['employee_compensation'])) {
                foreach ($reqdata['employee_compensation'] as $key => $value) {
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

                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
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

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
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

            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
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

    public function redlineSubroutines(Request $request)
    {
        $data = [
            'user_id' => 30,
            'updater_id' => 1,
            'redline' => '3.6',
            'redline_type' => 'per watt',
            'redline_amount_type' => 'Fixed',
            'start_date' => '2023-06-04',
        ];

        $pid = 'ADA102';

        return $subroutineProcess = $this->subroutine_process($pid);
        // $pidData = UserCommission::whereRaw('"'.$data['start_date'].'" between `pay_period_from` and `pay_period_to`')->where(['user_id'=> $data['user_id'],'amount_type'=> 'm2'])->where('status','<>','3')->get();
        // foreach ($pidData as $key => $value) {
        //     $subroutineProcess = $this->subroutine_process($value->pid);
        // }

    }

    public function subroutine_process($pid)
    {
        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();

        if (!$checked) {
            return;
        }

        // Set context for custom field conversion (Trick Subroutine approach)
        // This enables auto-conversion of 'custom field' to 'per sale' in model events
        $companyProfile = SalesCalculationContext::getCachedCompanyProfile() ?? CompanyProfile::first();

        // Check if Custom Sales Fields feature is enabled for this company
        $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

        try {
            // Only set context when Custom Sales Fields feature is enabled
            // This ensures zero impact on companies without the feature
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::set($checked, $companyProfile);
            }

            $dateCancelled = $checked->date_cancelled;
            $m1_date = $checked->m1_date;
            $m2_date = $checked->m2_date;
            $epc = $checked->epc;
            $netEpc = $checked->net_epc;
            $customerState = $checked->customer_state;
            $kw = $checked->kw;

            $m1_paid_status = $checked->salesMasterProcess->setter1_m1_paid_status;
            $m2_paid_status = $checked->salesMasterProcess->setter1_m2_paid_status;
            $approvedDate = $checked->customer_signoff;

            $closer1_id = $checked->salesMasterProcess->closer1_id;
            $closer2_id = $checked->salesMasterProcess->closer2_id;
            $setter1_id = $checked->salesMasterProcess->setter1_id;
            $setter2_id = $checked->salesMasterProcess->setter2_id;

            if ($approvedDate) {
            // check Is there an M1 Date?
            if ($m1_date) {

                // check  Has M1 already been paid?
                if ($m1_paid_status == 4) {

                    // check  Is there an M2 Date?
                    if ($m2_date != null) {

                        // Run Subroutine 6
                        $subroutineSix = $this->SubroutineSix($checked);
                        $subroutineEight = $this->SubroutineEight($checked);

                        if ($m2_paid_status == 8) {
                            // Does total paid match total from Subroutine #8?
                            $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                            $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                            // dd($pullTotalCommission);
                            $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                            // dd($totalPaid);
                            if (round($totalPaid) !== round($pullTotalCommission)) {
                                // Run Subroutine #12 (Sale Adjustments)
                                $subroutineTwelve = $this->SubroutineTwelve($checked);
                            }
                        } else {
                            // echo 'check';die;
                            if (isset($setter1_id) && $setter1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);

                                }
                            }

                            if (isset($setter2_id) && $setter2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($closer1_id) && $closer1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {

                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($closer2_id) && $closer2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {

                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                        }

                    } else {
                        // echo"asda";die;
                        // No Further Action Required
                    }
                } else {

                    if ($m2_date != null) {
                        // Run Subroutine 6
                        $subroutineSix = $this->SubroutineSix($checked);
                        // Run Subroutine #8 (Total Commission)
                        $subroutineEight = $this->SubroutineEight($checked);

                        // Has M2 already been paid?
                        if ($m2_paid_status == 8) {
                            // Does total paid match total from Subroutine #8?
                            $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                            $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                            $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                            if (round($totalPaid) != round($pullTotalCommission)) {
                                // Run Subroutine #12 (Sale Adjustments)
                                $subroutineTwelve = $this->SubroutineTwelve($checked);
                            }
                        } else {
                            // return $approvedDate;die;
                            if (isset($setter1_id) && $setter1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($setter2_id) && $setter2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($closer1_id) && $closer1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {

                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($closer2_id) && $closer2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {

                                    $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                        }

                    } else {

                        // Run Subroutine #3 (M1 Payment)

                        $subroutineThree = $this->SubroutineThree($checked);

                        // No Further Action Required

                    }

                }

            } else {
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                    if (isset($UpdateData) && $UpdateData != '') {
                        $UpdateData->mark_account_status_id = 2;
                        $UpdateData->save();
                    }
                }
            }
        } finally {
            // Only clear the context if it was set (feature is enabled)
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::clear();
            }
        }
    }

    public function UserCompensation_old(Request $request): JsonResponse
    {

        $data2 = User::find($request->user_id);
        if (! $data2 == null) {
            $data2->commission = $request->employee_compensation['commission'];
            $data2->redline = $request->employee_compensation['redline_amount'];
            $data2->redline_amount_type = $request->employee_compensation['redline'];
            $data2->redline_type = $request->employee_compensation['redline_type'];
            $data2->upfront_pay_amount = $request->employee_compensation['upfront_pay_amount'];
            $data2->upfront_sale_type = $request->employee_compensation['upfront_sale_type'];
            $data2->save();

            if ($data2->commission == null || $data2->redline == null || $data2->redline_amount == null || $data2->upfront_pay_amount == null) {
                //  $data5 =  OnboardingEmployees::find($data2->id);
                //  $data5->status_id = 8;
                //  $data5->save();
                //  }else{
                //  $data6 =  OnboardingEmployees::find($data2->id);
                //  $data6->status_id = 4;
                //  $data6->save();
            }

            $redline_data = $request->employee_compensation['redline_data'];

            if ($redline_data) {
                $updater_id = Auth()->user()->id;
                if ($request->user_id) {
                    $data = UserRedlines::where('user_id', $request->user_id)->delete();
                }
                foreach ($redline_data as $key => $value) {
                    if (! empty($value['redline_amount_type'])) {
                        $redline_amount_type = $value['redline_amount_type'];
                    } else {
                        $redline_amount_type = 'Fixed';
                    }
                    // function not in use
                    UserRedlines::create([
                        'user_id' => $request['user_id'],
                        'updater_id' => $updater_id,
                        'redline' => $value['redline'],
                        'redline_type' => $value['redline_type'],
                        'redline_amount_type' => $redline_amount_type,
                        'start_date' => $value['start_date'],

                    ]);
                }
            }

            return response()->json([
                'ApiName' => 'Update Compensation',
                'status' => true,
                'message' => 'Updated Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Update Compensation',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

    }

    public function UserCompensationNew(Request $request): JsonResponse
    {
        $create_redline = $request->create_redline;
        $updater_id = Auth()->user()->id;

        if ($request->user_id) {
            $data = UserRedlines::where('user_id', $request->user_id)->delete();
        }
        foreach ($create_redline as $key => $value) {
            // function not in use
            UserRedlines::create([
                'user_id' => $request['user_id'],
                'updater_id' => $updater_id,
                'redline' => $value['redline'],
                'redline_type' => $value['redline_type'],
                'redline_amount_type' => $value['redline_amount_type'],
                'start_date' => $value['start_date'],

            ]);

        }

        return response()->json([
            'ApiName' => 'Create Compensation',
            'status' => true,
            'message' => 'Create Successfully.',
            'date' => $create_redline,
        ], 200);

    }

    public function UserOverridesOld(Request $request): JsonResponse
    {
        $rules = [
            'user_id' => 'required',
        ];
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $rules['employee_override.direct_overrides_type'] = 'nullable|in:per sale,percent';
            $rules['employee_override.indirect_overrides_type'] = 'nullable|in:per sale,percent';
            $rules['employee_override.office_overrides_type'] = 'nullable|in:per sale,percent';
        }
        $validator = Validator::make($request->all(), $rules, [
            'employee_override.direct_overrides_type.in' => 'Invalid Direct Override Type.',
            'employee_override.indirect_overrides_type.in' => 'Invalid Indirect Override Type.',
            'employee_override.office_overrides_type.in' => 'Invalid Office Override Type.',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $udata = $this->userDataById($request->user_id);
        $reqdata = $request;
        $result = $this->overridesDataComp($udata, $reqdata);

        $user = User::where('id', $request->user_id)->first();
        if (! empty($user)) {
            $data = (object) [];
            $override = UserOverrideHistory::where('user_id', $request->user_id)
                ->where('override_effective_date', '=', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                ->first();
            $old_data = UserOverrideHistory::where('user_id', $request->user_id)
                ->where('override_effective_date', '<', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                ->orderBy('override_effective_date', 'DESC')
                ->first();
            $next_data = UserOverrideHistory::where('user_id', $request->user_id)
                ->where('override_effective_date', '>', date('Y-m-d', strtotime($request->employee_override['override_effective_date'])))
                ->orderBy('override_effective_date', 'ASC')
                ->first();

            if (empty($old_data) && empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
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
                    $checkdata = UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                                'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                                'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                                'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                                'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                                'office_overrides_type' => $request->employee_override['office_overrides_type'],
                                'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
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
                }
            } elseif (empty($old_data) && ! empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
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
                    $checkdata = UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                                'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                                'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                                'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                                'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                                'office_overrides_type' => $request->employee_override['office_overrides_type'],
                                'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
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
                }
                $next_data->old_direct_overrides_amount = $request->employee_override['direct_overrides_amount'];
                $next_data->old_direct_overrides_type = $request->employee_override['direct_overrides_type'];
                $next_data->old_indirect_overrides_amount = $request->employee_override['indirect_overrides_amount'];
                $next_data->old_indirect_overrides_type = $request->employee_override['indirect_overrides_type'];
                $next_data->old_office_overrides_amount = $request->employee_override['office_overrides_amount'];
                $next_data->old_office_overrides_type = $request->employee_override['office_overrides_type'];
                $next_data->old_office_stack_overrides_amount = $request->employee_override['office_stack_overrides_amount'];
                $next_data->action_item_status = 0;
                $next_data->save();
            } elseif (! empty($old_data) && empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'old_direct_overrides_amount' => isset($old_data->direct_overrides_amount) ? $old_data->direct_overrides_amount : 0,
                            'old_direct_overrides_type' => isset($old_data->direct_overrides_type) ? $old_data->direct_overrides_type : '',
                            'old_indirect_overrides_amount' => isset($old_data->indirect_overrides_amount) ? $old_data->indirect_overrides_amount : 0,
                            'old_indirect_overrides_type' => isset($old_data->indirect_overrides_type) ? $old_data->indirect_overrides_type : '',
                            'old_office_overrides_amount' => isset($old_data->office_overrides_amount) ? $old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($old_data->office_overrides_type) ? $old_data->office_overrides_type : '',
                            'old_office_stack_overrides_amount' => isset($old_data->office_stack_overrides_amount) ? $old_data->office_stack_overrides_amount : 0,
                        ]
                    );
                } else {
                    $checkdata = UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                                'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                                'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                                'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                                'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                                'office_overrides_type' => $request->employee_override['office_overrides_type'],
                                'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
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
                }
            } elseif (! empty($old_data) && ! empty($next_data)) {
                if (empty($override)) {
                    $checkdata = UserOverrideHistory::Create(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                            'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                            'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                            'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                            'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                            'office_overrides_type' => $request->employee_override['office_overrides_type'],
                            'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                            'old_direct_overrides_amount' => isset($old_data->direct_overrides_amount) ? $old_data->direct_overrides_amount : 0,
                            'old_direct_overrides_type' => isset($old_data->direct_overrides_type) ? $old_data->direct_overrides_type : '',
                            'old_indirect_overrides_amount' => isset($old_data->indirect_overrides_amount) ? $old_data->indirect_overrides_amount : 0,
                            'old_indirect_overrides_type' => isset($old_data->indirect_overrides_type) ? $old_data->indirect_overrides_type : '',
                            'old_office_overrides_amount' => isset($old_data->office_overrides_amount) ? $old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($old_data->office_overrides_type) ? $old_data->office_overrides_type : '',
                            'old_office_stack_overrides_amount' => isset($old_data->office_stack_overrides_amount) ? $old_data->office_stack_overrides_amount : 0,
                        ]
                    );
                } else {
                    $checkdata = UserOverrideHistory::where(
                        [
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update(
                            [
                                'updater_id' => auth()->user()->id,
                                'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                                'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                                'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                                'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                                'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                                'office_overrides_type' => $request->employee_override['office_overrides_type'],
                                'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
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
                }
                $next_data->old_direct_overrides_amount = $request->employee_override['direct_overrides_amount'];
                $next_data->old_direct_overrides_type = $request->employee_override['direct_overrides_type'];
                $next_data->old_indirect_overrides_amount = $request->employee_override['indirect_overrides_amount'];
                $next_data->old_indirect_overrides_type = $request->employee_override['indirect_overrides_type'];
                $next_data->old_office_overrides_amount = $request->employee_override['office_overrides_amount'];
                $next_data->old_office_overrides_type = $request->employee_override['office_overrides_type'];
                $next_data->old_office_stack_overrides_amount = $request->employee_override['office_stack_overrides_amount'];
                $next_data->action_item_status = 0;
                $next_data->save();
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
                            ])->update(
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
                            ])->update(
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
                    } else {
                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'office_id' => $additional['office_id'],
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            ])->update(
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

                    } else {

                        $additionalOfficeOverride = UserAdditionalOfficeOverrideHistory::where(
                            [
                                'user_id' => $request->user_id,
                                'office_id' => $additional['office_id'],
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            ])->update(
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
                ->orderBy('override_effective_date', 'DESC')
                ->first();

            if ($user->direct_overrides_amount != $request->employee_override['direct_overrides_amount'] || $user->direct_overrides_type != $request->employee_override['direct_overrides_type'] || $user->indirect_overrides_amount != $request->employee_override['indirect_overrides_amount'] || $user->indirect_overrides_type != $request->employee_override['indirect_overrides_type']
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

    public function UserOverrides(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        /* /* check additional office effective date validation */
        if ($request->additional_office_override && ! empty($request->additional_office_override)) {
            foreach ($request->additional_office_override as $value) {
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
            UserOverrideHistory::updateOrCreate([
                'user_id' => $request->user_id,
                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
            ], [
                'updater_id' => auth()->user()->id,
                'direct_overrides_amount' => $request->employee_override['direct_overrides_amount'],
                'direct_overrides_type' => $request->employee_override['direct_overrides_type'],
                'indirect_overrides_amount' => $request->employee_override['indirect_overrides_amount'],
                'indirect_overrides_type' => $request->employee_override['indirect_overrides_type'],
                'office_overrides_amount' => $request->employee_override['office_overrides_amount'],
                'office_overrides_type' => $request->employee_override['office_overrides_type'],
                'office_stack_overrides_amount' => $request->employee_override['office_stack_overrides_amount'],
                'old_direct_overrides_amount' => isset($old_data->direct_overrides_amount) ? $old_data->direct_overrides_amount : 0,
                'old_direct_overrides_type' => isset($old_data->direct_overrides_type) ? $old_data->direct_overrides_type : '',
                'old_indirect_overrides_amount' => isset($old_data->indirect_overrides_amount) ? $old_data->indirect_overrides_amount : 0,
                'old_indirect_overrides_type' => isset($old_data->indirect_overrides_type) ? $old_data->indirect_overrides_type : '',
                'old_office_overrides_amount' => isset($old_data->office_overrides_amount) ? $old_data->office_overrides_amount : 0,
                'old_office_overrides_type' => isset($old_data->office_overrides_type) ? $old_data->office_overrides_type : '',
                'old_office_stack_overrides_amount' => isset($old_data->office_stack_overrides_amount) ? $old_data->office_stack_overrides_amount : 0,
            ]);

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
                        UserAdditionalOfficeOverrideHistory::Create([
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'state_id' => $additional['state_id'],
                            'office_id' => $additional['office_id'],
                            'office_overrides_amount' => $additional['overrides_amount'],
                            'office_overrides_type' => $additional['overrides_type'],
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                        ]);
                    } else {
                        UserAdditionalOfficeOverrideHistory::where([
                            'user_id' => $request->user_id,
                            'office_id' => $additional['office_id'],
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'office_overrides_amount' => $additional['overrides_amount'],
                            'office_overrides_type' => $additional['overrides_type'],
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                        ]);
                    }
                } elseif (empty($additional_old_data) && ! empty($additional_next_data)) {
                    if (empty($additionalOverride)) {
                        UserAdditionalOfficeOverrideHistory::Create([
                            'user_id' => $request->user_id,
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                            'updater_id' => auth()->user()->id,
                            'state_id' => $additional['state_id'],
                            'office_id' => $additional['office_id'],
                            'office_overrides_amount' => $additional['overrides_amount'],
                            'office_overrides_type' => $additional['overrides_type'],
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                        ]);
                    } else {
                        UserAdditionalOfficeOverrideHistory::where([
                            'user_id' => $request->user_id,
                            'office_id' => $additional['office_id'],
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'office_overrides_amount' => $additional['overrides_amount'],
                            'office_overrides_type' => $additional['overrides_type'],
                            'old_office_overrides_amount' => 0,
                            'old_office_overrides_type' => '',
                        ]);
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
                            UserAdditionalOfficeOverrideHistory::Create([
                                'user_id' => $request->user_id,
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                                'updater_id' => auth()->user()->id,
                                'state_id' => $additional['state_id'],
                                'office_id' => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                                'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',
                            ]);
                        }
                    } else {
                        UserAdditionalOfficeOverrideHistory::where([
                            'user_id' => $request->user_id,
                            'office_id' => $additional['office_id'],
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'office_overrides_amount' => $additional['overrides_amount'],
                            'office_overrides_type' => $additional['overrides_type'],
                            'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',
                        ]);
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
                            UserAdditionalOfficeOverrideHistory::Create([
                                'user_id' => $request->user_id,
                                'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                                'updater_id' => auth()->user()->id,
                                'state_id' => $additional['state_id'],
                                'office_id' => $additional['office_id'],
                                'office_overrides_amount' => $additional['overrides_amount'],
                                'office_overrides_type' => $additional['overrides_type'],
                                'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                                'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',
                            ]);
                        }
                    } else {
                        UserAdditionalOfficeOverrideHistory::where([
                            'user_id' => $request->user_id,
                            'office_id' => $additional['office_id'],
                            'override_effective_date' => date('Y-m-d', strtotime($request->employee_override['override_effective_date'])),
                        ])->update([
                            'updater_id' => auth()->user()->id,
                            'office_overrides_amount' => $additional['overrides_amount'],
                            'office_overrides_type' => $additional['overrides_type'],
                            'old_office_overrides_amount' => isset($additional_old_data->office_overrides_amount) ? $additional_old_data->office_overrides_amount : 0,
                            'old_office_overrides_type' => isset($additional_old_data->office_overrides_type) ? $additional_old_data->office_overrides_type : '',
                        ]);
                    }
                    $additional_next_data->old_office_overrides_amount = $additional['overrides_amount'];
                    $additional_next_data->old_office_overrides_type = $additional['overrides_type'];
                    $additional_next_data->save();
                }
            }

            Artisan::call('ApplyHistoryOnUsers:update', ['user_id' => $request->user_id]);

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

                    AdditionalLocations::where($condition)->update($update);
                }
            }

            // send mail here
            if (! empty($result)) {
                $salesData = [];
                $salesData = SequiDocsEmailSettings::originization_employment_package_change_notification_email_content($user, $result);
                $salesData['email'] = $user->email;

                if ($salesData['is_active'] == 1 && $salesData['template'] != '') {
                    $this->sendEmailNotification($salesData);
                }
            }

            if ($request->recalculate) {
                $paidPid = UserCommission::where(['amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->pluck('pid');
                $pids = SalesMaster::whereNull('date_cancelled')->whereNotNull('customer_signoff')->whereNotIn('pid', $paidPid)->pluck('pid');
                if ($pids) {
                    $dataForPusher = [
                        'user_id' => $user->id,
                    ];
                    ProcessRecalculatesOpenSales::dispatch($pids, $dataForPusher);
                }
            }

            $user = [
                'user_id' => $request->user_id,
                'description' => 'Updated Overrides Data by'.auth()->user()->first_name,
                'type' => 'Overrides',
                'is_read' => 0,
            ];
            event(new UserloginNotification($user));

            return response()->json([
                'ApiName' => 'user_override',
                'status' => true,
                'message' => 'Saved Successfully.',
            ]);
        } else {
            return response()->json([
                'ApiName' => 'User not found',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }
    }

    public function overridesDataComp($udata, $reqdata)
    {
        $data = [];
        if (! empty($udata) && ! empty($reqdata)) {

            if (isset($reqdata['employee_override']['override_effective_date']) && $udata['override_effective_date'] != $reqdata['employee_override']['override_effective_date']) {

                $data['override_effective_date'] = [
                    'old_value' => (! empty($udata['override_effective_date'])) ? date('m-d-Y', strtotime($udata['override_effective_date'])) : '',
                    'new_value' => (! empty($reqdata['employee_override']['override_effective_date'])) ? date('m-d-Y', strtotime($reqdata['employee_override']['override_effective_date'])) : '',
                ];
            }

            if (isset($reqdata['employee_override']['direct_overrides_amount']) && $udata['direct_overrides_amount'] != $reqdata['employee_override']['direct_overrides_amount']) {

                $data['direct_overrides_amount'] = [
                    'old_value' => $udata['direct_overrides_amount'],
                    'new_value' => $reqdata['employee_override']['direct_overrides_amount'],
                ];
            }

            if (isset($reqdata['employee_override']['direct_overrides_type']) && $udata['direct_overrides_type'] != $reqdata['employee_override']['direct_overrides_type']) {

                $data['direct_overrides_type'] = [
                    'old_value' => $udata['direct_overrides_type'],
                    'new_value' => $reqdata['employee_override']['direct_overrides_type'],
                ];
            }

            if (isset($reqdata['employee_override']['indirect_overrides_amount']) && $udata['indirect_overrides_amount'] != $reqdata['employee_override']['indirect_overrides_amount']) {

                $data['indirect_overrides_amount'] = [
                    'old_value' => $udata['indirect_overrides_amount'],
                    'new_value' => $reqdata['employee_override']['indirect_overrides_amount'],
                ];
            }

            if (isset($reqdata['employee_override']['indirect_overrides_type']) && $udata['indirect_overrides_type'] != $reqdata['employee_override']['indirect_overrides_type']) {

                $data['indirect_overrides_type'] = [
                    'old_value' => $udata['indirect_overrides_type'],
                    'new_value' => $reqdata['employee_override']['indirect_overrides_type'],
                ];
            }

            if (isset($reqdata['employee_override']['office_overrides_amount']) && $udata['office_overrides_amount'] != $reqdata['employee_override']['office_overrides_amount']) {

                $data['office_overrides_amount'] = [
                    'old_value' => $udata['office_overrides_amount'],
                    'new_value' => $reqdata['employee_override']['office_overrides_amount'],
                ];
            }

            if (isset($reqdata['employee_override']['office_overrides_type']) && $udata['office_overrides_type'] != $reqdata['employee_override']['office_overrides_type']) {

                $data['office_overrides_type'] = [
                    'old_value' => $udata['office_overrides_type'],
                    'new_value' => $reqdata['employee_override']['office_overrides_type'],
                ];
            }

            if (isset($reqdata['employee_override']['office_stack_overrides_amount']) && $udata['office_stack_overrides_amount'] != $reqdata['employee_override']['office_stack_overrides_amount']) {

                $data['office_stack_overrides_amount'] = [
                    'old_value' => $udata['office_stack_overrides_amount'],
                    'new_value' => $reqdata['employee_override']['office_stack_overrides_amount'],
                ];
            }

        }

        return $data;

    }

    public function changeUserOfficeAndManager(Request $request): JsonResponse
    {
        $data = User::find($request->user_id);
        if (! $data == null) {
            $data->manager_id = $request['manager_id'];
            $data->office_id = $request['office_id'];
            $data->redline = $request['redline'];
            $data->redline_amount_type = $request['redline_amount_type'];
            $data->redline_type = $request['redline_type'];
            // $data->redline_effective = $request['redline_effective'];
            $data->save();

            $old_manager_id = $request->old_manager_id;
            $allData = $request->users;
            $count = 0;
            foreach ($allData as $key => $value) {
                $user = User::where('id', $value['user_id'])->update(['manager_id' => $value['manager_id']]);
                $is_manager = User::where('manager_id', $value['manager_id'])->update(['is_manager' => 1]);
                $count++;
            }
            $manager = User::where('manager_id', $old_manager_id)->update(['is_manager' => 0]);

            $redline_data = $request->redline_data;
            if ($redline_data) {
                $updater_id = Auth()->user()->id;
                if ($request->user_id) {
                    $data = UserRedlines::where('user_id', $request->user_id)->delete();
                }
                foreach ($redline_data as $key => $value) {
                    // function not in use
                    UserRedlines::create([
                        'user_id' => $request->user_id,
                        'updater_id' => $updater_id,
                        'redline' => $value['redline'],
                        'redline_type' => $value['redline_type'],
                        'redline_amount_type' => $value['redline_amount_type'],
                        'start_date' => $value['start_date'],
                    ]);

                }

            }

            return response()->json([
                'ApiName' => 'Update user manager and office',
                'status' => true,
                'message' => 'Update Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Update user manager and office',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

    }

    public function UserAgreement(Request $request): JsonResponse
    {
        $data = User::find($request->user_id);
        if (! $data == null) {
            $data->probation_period = isset($request->employee_agreement['probation_period']) ? $request->employee_agreement['probation_period'] : null;
            $data->hiring_bonus_amount = isset($request->employee_agreement['hiring_bonus_amount']) ? $request->employee_agreement['hiring_bonus_amount'] : null;
            $data->date_to_be_paid = isset($request->employee_agreement['date_to_be_paid']) ? $request->employee_agreement['date_to_be_paid'] : null;
            $data->period_of_agreement_start_date = isset($request->employee_agreement['period_of_agreement']) ? $request->employee_agreement['period_of_agreement'] : null;
            $data->end_date = isset($request->employee_agreement['end_date']) ? $request->employee_agreement['end_date'] : null;
            // $data->offer_include_bonus = isset($request->employee_agreement['offer_include_bonus'])?$request->employee_agreement['offer_include_bonus']:null;
            $data->offer_expiry_date = isset($request->employee_agreement['offer_expiry_date']) ? $request->employee_agreement['offer_expiry_date'] : null;

            $msg = '';
            if ($request->rehire) {

                if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {
                    $msg = 'The dates must lie between October 1st of the current year and September 30th of the next year.';
                } else {
                    $msg = 'Ivalid Period of agreement';
                }

                $startDate = isset($request->employee_agreement['period_of_agreement']) ? $request->employee_agreement['period_of_agreement'] : null;
                $endDate = isset($request->employee_agreement['end_date']) ? $request->employee_agreement['end_date'] : null;

                if (! $startDate && ! $endDate) {

                    return response()->json([
                        'ApiName' => 'hired_date_update',
                        'status' => false,
                        'message' => $msg,
                    ], 422);

                }

                if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {
                    /**
                     * $startDate, $endDate should valid date in string format
                     * like 2024-03-25
                     */
                    $inSeason = seasonValidator($startDate, $endDate);
                } else {
                    $inSeason = true;
                }

                if ($inSeason) {

                    $data->period_of_agreement_start_date = $startDate;
                    $data->end_date = $endDate;
                    $data->offer_expiry_date = isset($request->employee_agreement['offer_expiry_date']) ? $request->employee_agreement['offer_expiry_date'] : null;
                    $data->rehire = 1;

                    // set status offer letter sent

                } else {
                    return response()->json([
                        'ApiName' => 'hired_date_update',
                        'status' => false,
                        'message' => $msg,
                    ], 422);
                }

            }

            $data->save();

            $userStatus = User::find($data->id);
            $userStatus->status_id = 4; // Delete Status
            $userStatus->save();

            $ViewData = User::Select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'state_id')->where('id', $request->user_id)->first();
            $event = EventCalendar::create(
                [
                    'event_date' => $request->employee_agreement['period_of_agreement'],
                    'type' => 'Hired',
                    'state_id' => $ViewData->state_id,
                    'user_id' => $ViewData->id,
                    'event_name' => 'Joining',
                    'description' => null,
                ]
            );

            $userWithPosition = User::where('id', $data->id)
                ->whereNotNull('office_id')
                ->select(
                    'id',
                    'sub_position_id',
                    'office_id',
                )
                ->with(['positionDetail' => function ($query) {
                    $query->select('id', 'position_name');
                }])->first();

            //     $pdf = PDF::loadView('mail.pdf',[
            //         'title' => $ViewData->first_name.' '.$ViewData->last_name,
            //         'email' => $ViewData->email,
            //         'mobile_no' => $ViewData->mobile_no
            //     ]);
            //    $viewPdf = file_put_contents("template/".$ViewData->first_name.'-'.$ViewData->last_name."_offer_letter.pdf", $pdf->output());
            //    $pdfPath = $this->url->to('/')."/template/".$ViewData->first_name.'-'.$ViewData->last_name."_offer_letter.pdf";
            // $ViewData->status_id = 4;
            // $ViewData->save();

            // $notificationData =  Notification::create([
            //     'user_id' =>  auth()->user()->id,
            //     'type' => 'Agreement',
            //     'description' => 'Updated Agreement Data by ' . auth()->user()->first_name,
            //     'is_read' => 0,

            //  ]);

            $user = [
                'user_id' => $request->user_id,
                'description' => 'Updated Agreement Data by '.auth()->user()->first_name,
                'type' => 'Agreement',
                'is_read' => 0,
            ];
            $notify = event(new UserloginNotification($user));

            return response()->json([
                'ApiName' => 'Update User Agreement',
                'status' => true,
                'message' => 'Updated Successfully.',
                'api_data' => [
                    'category_id' => 1, // offer letter
                    'signing_screeen_url' => config('app.sign_screen_url'),
                    'user_array' => [
                        [
                            'id' => $userWithPosition->id,
                            'office_id' => $userWithPosition->office_id,
                            'position_name' => $userWithPosition->positionDetail->position_name,
                            'sub_position_id' => $userWithPosition->sub_position_id,
                        ],
                    ],
                ],

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Update User Agreement',
                'status' => false,
                'message' => 'Bad Request',

            ], 400);
        }
    }

    public function userDetailById($id)
    {
        $currentDate = Carbon::now()->toDateString();
        $user = User::orderBy('id', 'desc')->with(['office', 'userSelfGenCommission' => function ($query) use ($currentDate) {
            $query->whereDate('commission_effective_date', '<=', $currentDate)
                ->orderBy('commission_effective_date', 'DESC');
        }])->newQuery();

        $user->with('departmentDetail', 'positionDetail', 'state', 'city', 'managerDetail', 'statusDetail', 'recruiter', 'additionalDetail', 'subpositionDetail', 'teamsDetail', 'recruiter');
        $data = $user->where('id', $id)->first();
        $totalMember = User::where('manager_id', $id)->count();
        if (isset($data) && $data != '') {
            $data->additionalDetail;
            $additional = [];
            foreach ($data->additionalDetail as $deducationname) {
                $additional[] = [
                    'id' => isset($deducationname->id) ? $deducationname->id : null,
                    'recruiter_id' => isset($deducationname->recruiter_id) ? $deducationname->recruiter_id : null,
                    'recruiter_first_name' => isset($deducationname->additionalRecruiterDetail->first_name) ? $deducationname->additionalRecruiterDetail->first_name : null,
                    'recruiter_last_name' => isset($deducationname->additionalRecruiterDetail->last_name) ? $deducationname->additionalRecruiterDetail->last_name : null,
                    'system_per_kw_amount' => isset($deducationname->system_per_kw_amount) ? $deducationname->system_per_kw_amount : null,
                ];
            }

            $additional_location = '';
            $latest_effective_date = AdditionalLocations::select('effective_date')->where('effective_date', '<=', date('Y-m-d'))->where('user_id', $id)->orderBy('effective_date', 'desc')->groupBy('effective_date')->first();
            if (isset($latest_effective_date->effective_date)) {
                $additional_location = AdditionalLocations::with('state', 'office')->where('user_id', $id)->where('effective_date', $latest_effective_date->effective_date)->whereNull('archived_at')->get();
            }
            $currentDate = now()->toDateString();

            if ($additional_location) {
                $additional_location->transform(function ($data) {
                    if (isset($data->office->id) && isset($data->user_id)) {
                        $additionalOverRide = $this->additionalOfficeChecker($data->user_id, $data->office->id);

                        return [
                            'effective_date' => isset($data->effective_date) ? $data->effective_date : null,
                            'state_id' => isset($data->state_id) ? $data->state_id : null,
                            'state_name' => isset($data->state->name) ? $data->state->name : null,
                            'office_id' => isset($data->office->id) ? $data->office->id : null,
                            'office_name' => isset($data->office->office_name) ? $data->office->office_name : null,
                            'overrides_amount' => isset($additionalOverRide->office_overrides_amount) ? $additionalOverRide->office_overrides_amount : null,
                            'overrides_type' => isset($additionalOverRide->office_overrides_type) ? $additionalOverRide->office_overrides_type : null,
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

                        $commission = $this->commissionCheckr($data->id, '0');
                        $upfront = $this->upfrontCheckr($data->id, '0');
                        $withHeld = $this->withHeldCheckr($data->id, '0');
                        $redLine = $this->redLineCheckr($data->id, '0');

                        $employee_compensation_result = [];
                        $employee_compensation_result[$data->sub_position_id]['commission'] = isset($commission->commission) ? $commission->commission : (isset($data->commission) ? $data->commission : null);
                        $employee_compensation_result[$data->sub_position_id]['commission_type'] = isset($commission->commission_type) ? $commission->commission_type : (isset($data->commission_type) ? $data->commission_type : null);
                        $employee_compensation_result[$data->sub_position_id]['commission_effective_date'] = isset($commission->commission_effective_date) ? $commission->commission_effective_date : (isset($data->sub_position_id) ? dateToYMD($data->commission_effective_date) : null);
                        $employee_compensation_result[$data->sub_position_id]['commission_position_id'] = isset($commission->sub_position_id) ? $commission->sub_position_id : (isset($data->sub_position_id) ? $data->sub_position_id : null);
                        $employee_compensation_result[$data->sub_position_id]['upfront_pay_amount'] = isset($upfront->upfront_pay_amount) ? $upfront->upfront_pay_amount : (isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null);
                        $employee_compensation_result[$data->sub_position_id]['upfront_sale_type'] = isset($upfront->upfront_sale_type) ? $upfront->upfront_sale_type : (isset($data->upfront_sale_type) ? $data->upfront_sale_type : null);
                        $employee_compensation_result[$data->sub_position_id]['upfront_effective_date'] = isset($upfront->upfront_effective_date) ? $upfront->upfront_effective_date : (isset($data->upfront_effective_date) ? $data->upfront_effective_date : dateToYMD($data->period_of_agreement_start_date));
                        $employee_compensation_result[$data->sub_position_id]['upfront_position_id'] = isset($upfront->sub_position_id) ? $upfront->sub_position_id : $data->sub_position_id;
                        $employee_compensation_result[$data->sub_position_id]['withheld_amount'] = isset($withHeld->withheld_amount) ? $withHeld->withheld_amount : (isset($data->withheld_amount) ? $data->withheld_amount : null);
                        $employee_compensation_result[$data->sub_position_id]['withheld_type'] = isset($withHeld->withheld_type) ? $withHeld->withheld_type : (isset($data->withheld_type) ? $data->withheld_type : null);
                        $employee_compensation_result[$data->sub_position_id]['withheld_effective_date'] = isset($withHeld->withheld_effective_date) ? $withHeld->withheld_effective_date : (isset($data->withheld_effective_date) ? $data->withheld_effective_date : dateToYMD($data->period_of_agreement_start_date));
                        $employee_compensation_result[$data->sub_position_id]['withheld_position_id'] = isset($withHeld->sub_position_id) ? $withHeld->sub_position_id : $data->sub_position_id;
                        $employee_compensation_result[$data->sub_position_id]['redline_amount_type'] = isset($redLine->redline_amount_type) ? $redLine->redline_amount_type : (isset($data->redline_amount_type) ? $data->redline_amount_type : null);
                        $employee_compensation_result[$data->sub_position_id]['redline'] = isset($redLine->redline) ? $redLine->redline : (isset($data->redline) ? $data->redline : null);
                        $employee_compensation_result[$data->sub_position_id]['redline_type'] = isset($redLine->redline_type) ? $redLine->redline_type : (isset($data->redline_type) ? $data->redline_type : null);
                        $employee_compensation_result[$data->sub_position_id]['redline_effective_date'] = isset($redLine->start_date) ? $redLine->start_date : (isset($data->redline_effective_date) ? $data->redline_effective_date : dateToYMD($data->period_of_agreement_start_date));
                        $employee_compensation_result[$data->sub_position_id]['redline_position_id'] = isset($redLine->sub_position_type) ? $redLine->sub_position_type : $data->sub_position_id;
                        if (! empty($data->self_gen_type)) {
                            $commission = $this->commissionCheckr($data->id, '1');
                            $upfront = $this->upfrontCheckr($data->id, '1');
                            $withHeld = $this->withHeldCheckr($data->id, '1');
                            $redLine = $this->redLineCheckr($data->id, '1');

                            $employee_compensation_result[$data->self_gen_type]['commission'] = isset($commission->commission) ? $commission->commission : (isset($data->self_gen_commission) ? $data->self_gen_commission : null);
                            $employee_compensation_result[$data->self_gen_type]['commission_type'] = isset($commission->commission_type) ? $commission->commission_type : (isset($data->self_gen_commission_type) ? $data->self_gen_commission_type : null);
                            $employee_compensation_result[$data->self_gen_type]['commission_effective_date'] = isset($commission->commission_effective_date) ? $commission->commission_effective_date : (isset($data->self_gen_commission_effective_date) ? $data->self_gen_commission_effective_date : dateToYMD($data->period_of_agreement_start_date));
                            $employee_compensation_result[$data->self_gen_type]['commission_position_id'] = isset($commission->sub_position_id) ? $commission->sub_position_id : ($data->self_gen_type);
                            $employee_compensation_result[$data->self_gen_type]['upfront_pay_amount'] = isset($upfront->upfront_pay_amount) ? $upfront->upfront_pay_amount : (isset($data->self_gen_upfront_amount) ? $data->self_gen_upfront_amount : null);
                            $employee_compensation_result[$data->self_gen_type]['upfront_sale_type'] = isset($upfront->upfront_sale_type) ? $upfront->upfront_sale_type : (isset($data->self_gen_upfront_type) ? $data->self_gen_upfront_type : null);
                            $employee_compensation_result[$data->self_gen_type]['upfront_effective_date'] = isset($upfront->upfront_effective_date) ? $upfront->upfront_effective_date : (isset($data->self_gen_upfront_effective_date) ? $data->self_gen_upfront_effective_date : dateToYMD($data->period_of_agreement_start_date));
                            $employee_compensation_result[$data->self_gen_type]['upfront_position_id'] = isset($upfront->sub_position_id) ? $upfront->sub_position_id : $data->self_gen_type;
                            $employee_compensation_result[$data->self_gen_type]['withheld_amount'] = isset($withHeld->withheld_amount) ? $withHeld->withheld_amount : (isset($data->self_gen_withheld_amount) ? $data->self_gen_withheld_amount : null);
                            $employee_compensation_result[$data->self_gen_type]['withheld_type'] = isset($withHeld->withheld_type) ? $withHeld->withheld_type : (isset($data->self_gen_withheld_type) ? $data->self_gen_withheld_type : null);
                            $employee_compensation_result[$data->self_gen_type]['withheld_effective_date'] = isset($withHeld->withheld_effective_date) ? $withHeld->withheld_effective_date : (isset($data->self_gen_withheld_effective_date) ? $data->self_gen_withheld_effective_date : dateToYMD($data->period_of_agreement_start_date));
                            $employee_compensation_result[$data->self_gen_type]['withheld_position_id'] = isset($withHeld->sub_position_id) ? $withHeld->sub_position_id : $data->self_gen_type;
                            $employee_compensation_result[$data->self_gen_type]['redline_amount_type'] = isset($data->self_gen_redline_amount_type) ? $data->self_gen_redline_amount_type : null;
                            $employee_compensation_result[$data->self_gen_type]['redline'] = isset($redLine->redline) ? $redLine->redline : (isset($data->self_gen_redline) ? $data->self_gen_redline : null);
                            $employee_compensation_result[$data->self_gen_type]['redline_type'] = isset($redLine->redline_type) ? $redLine->redline_type : (isset($data->self_gen_redline_type) ? $data->self_gen_redline_type : null);
                            $employee_compensation_result[$data->self_gen_type]['redline_effective_date'] = isset($redLine->start_date) ? $redLine->start_date : (isset($data->self_gen_redline_effective_date) ? $data->self_gen_redline_effective_date : dateToYMD($data->period_of_agreement_start_date));
                            $employee_compensation_result[$data->self_gen_type]['redline_position_id'] = isset($redLine->sub_position_type) ? $redLine->sub_position_type : $data->self_gen_type;
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

                        $overRide = $this->overRideCheckr($data->id);
                        $selfGen = $this->selfGenCommissionCheckr($data->id);
                        // $organization = $this->organizationCheckr($data->id);

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
                        $manager_id_effective_date = (isset($manager->effective_date) && $manager->manager_id && ! empty($manager->manager_id)) ? $manager->effective_date : null;

                        $data1 = [
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
                            'is_manager' => isset($isManager->is_manager) ? $isManager->is_manager : null,
                            'is_manager_effective_date' => isset($isManager->effective_date) ? $isManager->effective_date : null,
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
                            'manager_id' => isset($manager->manager_id) ? $manager->manager_id : null,
                            'manager_id_effective_date' => $manager_id_effective_date,
                            'manager_name' => $managerName,
                            'Employee_Manager_Position' => $managerPosition,
                            'Employee_Manager_Department' => $managerDepartment,
                            'team_id' => $teamId,
                            'team_id_effective_date' => $teamEffectiveDate,
                            'team_name' => $teamName,
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
                            'self_gen_commission_type' => isset($data->self_gen_commission_type) ? $data->self_gen_commission_type : null,
                            'self_gen_upfront_amount' => isset($data->self_gen_upfront_amount) ? $data->self_gen_upfront_amount : null,
                            'self_gen_upfront_type' => isset($data->self_gen_upfront_type) ? $data->self_gen_upfront_type : null,
                            'upfront_pay_amount' => isset($data->upfront_pay_amount) ? $data->upfront_pay_amount : null,
                            'upfront_sale_type' => isset($data->upfront_sale_type) ? $data->upfront_sale_type : null,
                            'override_effective_date' => isset($overRide->override_effective_date) ? $overRide->override_effective_date : (isset($data->override_effective_date) ? $data->override_effective_date : null),
                            'direct_overrides_amount' => isset($overRide->direct_overrides_amount) ? $overRide->direct_overrides_amount : (isset($data->direct_overrides_amount) ? $data->direct_overrides_amount : null),
                            'direct_overrides_type' => isset($overRide->direct_overrides_type) ? $overRide->direct_overrides_type : (isset($data->direct_overrides_type) ? $data->direct_overrides_type : null),
                            'indirect_overrides_amount' => isset($overRide->indirect_overrides_amount) ? $overRide->indirect_overrides_amount : (isset($data->indirect_overrides_amount) ? $data->indirect_overrides_amount : null),
                            'indirect_overrides_type' => isset($overRide->indirect_overrides_type) ? $overRide->indirect_overrides_type : (isset($data->indirect_overrides_type) ? $data->indirect_overrides_type : null),
                            'office_overrides_amount' => isset($overRide->office_overrides_amount) ? $overRide->office_overrides_amount : (isset($data->office_overrides_amount) ? $data->office_overrides_amount : null),
                            'office_overrides_type' => isset($overRide->office_overrides_type) ? $overRide->office_overrides_type : (isset($data->office_overrides_type) ? $data->office_overrides_type : null),
                            'office_stack_overrides_amount' => isset($overRide->office_stack_overrides_amount) ? $overRide->office_stack_overrides_amount : (isset($data->office_stack_overrides_amount) ? $data->office_stack_overrides_amount : null),
                            'withheld_amount' => isset($data->withheld_amount) ? $data->withheld_amount : null,
                            'withheld_type' => isset($data->withheld_type) ? $data->withheld_type : null,
                            'self_gen_withheld_amount' => isset($data->self_gen_withheld_amount) ? $data->self_gen_withheld_amount : null,
                            'self_gen_withheld_type' => isset($data->self_gen_withheld_type) ? $data->self_gen_withheld_type : null,
                            'probation_period' => isset($data->probation_period) && $data->probation_period != 'None' ? $data->probation_period : null,
                            'commission' => isset($data->commission) ? $data->commission : null,
                            'commission_type' => isset($data->commission_type) ? $data->commission_type : null,
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
                            'commission_selfgen' => isset($selfGen->commission) ? $selfGen->commission : 0,
                            'commission_selfgen_type' => isset($selfGen->commission_type) ? $selfGen->commission_type : null,
                            'commission_selfgen_effective_date' => isset($selfGen->commission_effective_date) ? $selfGen->commission_effective_date : null,
                            'worker_type' => isset($data->worker_type) ? $data->worker_type : null,
                            'terminate' => isset($data->terminate) ? $data->terminate : 0,
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

                        // $employeeWages = UserWages::where(['user_id'=>$id])->first();
                        $empWages = [];
                        // if(!empty($employeeWages)){
                        $userWagesHistory = UserWagesHistory::where(['user_id' => $id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'desc')->first();
                        if ($userWagesHistory) {
                            $employeeWages = $userWagesHistory;
                            $empWages = [
                                'pay_type' => isset($employeeWages->pay_type) ? $employeeWages->pay_type : null,
                                'pay_rate' => isset($employeeWages->pay_rate) ? $employeeWages->pay_rate : null,
                                'pay_rate_type' => isset($employeeWages->pay_rate_type) ? $employeeWages->pay_rate_type : null,
                                'pto_hours' => isset($employeeWages->pto_hours) ? $employeeWages->pto_hours : null,
                                'unused_pto_expires' => isset($employeeWages->unused_pto_expires) ? $employeeWages->unused_pto_expires : null,
                                'expected_weekly_hours' => isset($employeeWages->expected_weekly_hours) ? $employeeWages->expected_weekly_hours : null,
                                'overtime_rate' => isset($employeeWages->overtime_rate) ? $employeeWages->overtime_rate : null,
                                'effective_date' => isset($employeeWages->effective_date) ? $employeeWages->effective_date : null,
                                'pto_hours_effective_date' => isset($employeeWages->pto_hours_effective_date) ? $employeeWages->pto_hours_effective_date : null,
                            ];
                        } else {

                            $empWages = [
                                'pay_type' => isset($data->pay_type) ? $data->pay_type : null,
                                'pay_rate' => isset($data->pay_rate) ? $data->pay_rate : null,
                                'pay_rate_type' => isset($data->pay_rate_type) ? $data->pay_rate_type : null,
                                'pto_hours' => isset($data->pto_hours) ? $data->pto_hours : null,
                                'unused_pto_expires' => isset($data->unused_pto_expires) ? $data->unused_pto_expires : null,
                                'expected_weekly_hours' => isset($data->unused_pto_expires) ? $data->unused_pto_expires : null,
                                'overtime_rate' => isset($data->overtime_rate) ? $data->overtime_rate : null,
                                'effective_date' => isset($data->effective_date) ? $data->effective_date : null,
                                'pto_hours_effective_date' => isset($data->pto_hours_effective_date) ? $data->pto_hours_effective_date : null,
                            ];

                        }

                        $empWages = [
                            'pay_type' => isset($employeeWages->pay_type) ? $employeeWages->pay_type : null,
                            'pay_rate' => isset($employeeWages->pay_rate) ? $employeeWages->pay_rate : null,
                            'pay_rate_type' => isset($employeeWages->pay_rate_type) ? $employeeWages->pay_rate_type : null,
                            'pto_hours' => isset($employeeWages->pto_hours) ? $employeeWages->pto_hours : null,
                            'unused_pto_expires' => isset($employeeWages->unused_pto_expires) ? $employeeWages->unused_pto_expires : null,
                            'expected_weekly_hours' => isset($employeeWages->expected_weekly_hours) ? $employeeWages->expected_weekly_hours : null,
                            'overtime_rate' => isset($employeeWages->overtime_rate) ? $employeeWages->overtime_rate : null,
                            'effective_date' => isset($employeeWages->effective_date) ? $employeeWages->effective_date : null,
                            'pto_hours_effective_date' => isset($employeeWages->pto_hours_effective_date) ? $employeeWages->pto_hours_effective_date : null,
                        ];
                        // }
                        $data1['user_wages'] = $empWages;

                        // $effectiveDate = UserDeductionHistory::select('user_id','effective_date')->where(['user_id' => $id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                        $effectiveDate = UserDeductionHistory::select('id', 'user_id', 'cost_center_id')->where(['user_id' => $id])->where('effective_date', '<=', date('Y-m-d'))->groupBy('cost_center_id')->get();
                        if (count($effectiveDate) > 0) {
                            $deductionIds = [];
                            foreach ($effectiveDate as $key => $val) {
                                $getdedution = UserDeductionHistory::select('id')->where(['user_id' => $id, 'cost_center_id' => $val['cost_center_id']])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
                                if ($getdedution) {
                                    $deductionIds[] = $getdedution->id;
                                }
                            }

                            // $deductions = UserDeductionHistory::with('costcenter')->where(['user_id' => $id, 'effective_date'=> $effectiveDate->effective_date])->get();
                            $deductions = UserDeductionHistory::with('costcenter')->where(['user_id' => $id])->whereIn('id', $deductionIds)->get();
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

                        return response()->json([
                            'ApiName' => 'Get User By Id',
                            'status' => true,
                            'message' => 'Successfully.',
                            'data' => $data1,
                        ], 200);
                    }
                }
            } else {
                return response()->json([
                    'ApiName' => 'Get User By Id',
                    'status' => false,
                    'message' => 'Invalid user id',
                ], 400);
            }
        }

    }

    public function commissionCheckr($userId, $isSelfGen)
    {
        if ($isSelfGen) {
            $commissions = UserCommissionHistory::where(['user_id' => $userId])->where('commission_effective_date', '<=', date('Y-m-d'))->orderBy('commission_effective_date')->get();
            $com = '';
            foreach ($commissions as $key => $commission) {
                if ($commission->self_gen_user == '0' && @$commissions[$key + 1]->self_gen_user == '0') {
                    $com = '';

                    continue;
                }
                if (! $com && $commission->self_gen_user == '1') {
                    $com = $commission;
                } else {
                    if ($com && $commission->self_gen_user == '1') {
                        if ($commission->commission != $com->commission || $commission->commission_type != $com->commission_type || $commission->position_id != $com->position_id || $commission->sub_position_id != $com->sub_position_id) {
                            $com = $commission;
                        }
                    }
                }
            }
        } else {
            $commissions = UserCommissionHistory::where(['user_id' => $userId, 'self_gen_user' => $isSelfGen])->where('commission_effective_date', '<=', date('Y-m-d'))->orderBy('commission_effective_date')->get();

            $com = '';
            foreach ($commissions as $commission) {
                if (! $com) {
                    $com = $commission;
                } else {
                    if ($commission->commission != $com->commission || $commission->commission_type != $com->commission_type || $commission->position_id != $com->position_id || $commission->sub_position_id != $com->sub_position_id) {
                        $com = $commission;
                    }
                }
            }
        }

        return $com;
    }

    public function upfrontCheckr($userId, $isSelfGen)
    {
        if ($isSelfGen) {
            $upFronts = UserUpfrontHistory::where(['user_id' => $userId])->where('upfront_effective_date', '<=', date('Y-m-d'))->orderBy('upfront_effective_date')->get();
            $up = '';
            foreach ($upFronts as $key => $upFront) {
                if ($upFront->self_gen_user == '0' && @$upFronts[$key + 1]->self_gen_user == '0') {
                    $up = '';

                    continue;
                }
                if (! $up && $upFront->self_gen_user == '1') {
                    $up = $upFront;
                } else {
                    if ($up && $upFront->self_gen_user == '1') {
                        if ($upFront->upfront_pay_amount != $up->upfront_pay_amount || $upFront->upfront_sale_type != $up->upfront_sale_type || $upFront->position_id != $up->position_id || $upFront->sub_position_id != $up->sub_position_id) {
                            $up = $upFront;
                        }
                    }
                }
            }
        } else {
            $upFronts = UserUpfrontHistory::where(['user_id' => $userId, 'self_gen_user' => $isSelfGen])->where('upfront_effective_date', '<=', date('Y-m-d'))->orderBy('upfront_effective_date')->get();

            $up = '';
            foreach ($upFronts as $upFront) {
                if (! $up) {
                    $up = $upFront;
                } else {
                    if ($upFront->upfront_pay_amount != $up->upfront_pay_amount || $upFront->upfront_sale_type != $up->upfront_sale_type || $upFront->position_id != $up->position_id || $upFront->sub_position_id != $up->sub_position_id) {
                        $up = $upFront;
                    }
                }
            }
        }

        return $up;
    }

    public function withHeldCheckr($userId, $isSelfGen)
    {
        if ($isSelfGen) {
            $withHelds = UserWithheldHistory::where(['user_id' => $userId])->where('withheld_effective_date', '<=', date('Y-m-d'))->orderBy('withheld_effective_date')->get();
            $with = '';
            foreach ($withHelds as $key => $withHeld) {
                if ($withHeld->self_gen_user == '0' && @$withHelds[$key + 1]->self_gen_user == '0') {
                    $with = '';

                    continue;
                }
                if (! $with && $withHeld->self_gen_user == '1') {
                    $with = $withHeld;
                } else {
                    if ($with && $withHeld->self_gen_user == '1') {
                        if ($withHeld->withheld_amount != $with->withheld_amount || $withHeld->withheld_type != $with->withheld_type || $withHeld->position_id != $with->position_id || $withHeld->sub_position_id != $with->sub_position_id) {
                            $with = $withHeld;
                        }
                    }
                }
            }
        } else {
            $withHelds = UserWithheldHistory::where(['user_id' => $userId, 'self_gen_user' => $isSelfGen])->where('withheld_effective_date', '<=', date('Y-m-d'))->orderBy('withheld_effective_date')->get();

            $with = '';
            foreach ($withHelds as $withHeld) {
                if (! $with) {
                    $with = $withHeld;
                } else {
                    if ($withHeld->withheld_amount != $with->withheld_amount || $withHeld->withheld_type != $with->withheld_type || $withHeld->position_id != $with->position_id || $withHeld->sub_position_id != $with->sub_position_id) {
                        $with = $withHeld;
                    }
                }
            }
        }

        return $with;
    }

    public function redLineCheckr($userId, $isSelfGen)
    {
        if ($isSelfGen) {
            $redLines = UserRedlines::where(['user_id' => $userId])->where('start_date', '<=', date('Y-m-d'))->orderBy('start_date')->get();
            $red = '';
            foreach ($redLines as $key => $redLine) {
                if ($redLine->self_gen_user == '0' && @$redLines[$key + 1]->self_gen_user == '0') {
                    $red = '';

                    continue;
                }
                if (! $red && $redLine->self_gen_user == '1') {
                    $red = $redLine;
                } else {
                    if ($red && $redLine->self_gen_user == '1') {
                        if ($redLine->redline != $red->redline || $redLine->redline_type != $red->redline_type || $redLine->redline_amount_type != $red->redline_amount_type || $redLine->position_type != $red->position_type || $redLine->sub_position_type != $red->sub_position_type) {
                            $red = $redLine;
                        }
                    }
                }
            }
        } else {
            $redLines = UserRedlines::where(['user_id' => $userId, 'self_gen_user' => $isSelfGen])->where('start_date', '<=', date('Y-m-d'))->orderBy('start_date')->get();

            $red = '';
            foreach ($redLines as $redLine) {
                if (! $red) {
                    $red = $redLine;
                } else {
                    if ($redLine->redline != $red->redline || $redLine->redline_type != $red->redline_type || $redLine->redline_amount_type != $red->redline_amount_type || $redLine->position_type != $red->position_type || $redLine->sub_position_type != $red->sub_position_type) {
                        $red = $redLine;
                    }
                }
            }
        }

        return $red;
    }

    public function overRideCheckr($userId)
    {
        $overRides = UserOverrideHistory::where(['user_id' => $userId])->where('override_effective_date', '<=', date('Y-m-d'))->orderBy('override_effective_date')->get();

        $over = '';
        foreach ($overRides as $overRide) {
            if (! $over) {
                $over = $overRide;
            } else {
                if ($overRide->direct_overrides_amount != $over->direct_overrides_amount || $overRide->direct_overrides_type != $over->direct_overrides_type || $overRide->indirect_overrides_amount != $over->indirect_overrides_amount || $overRide->indirect_overrides_type != $over->indirect_overrides_type || $overRide->office_overrides_amount != $over->office_overrides_amount || $overRide->office_overrides_type != $over->office_overrides_type || $overRide->office_stack_overrides_amount != $over->office_stack_overrides_amount) {
                    $over = $overRide;
                }
            }
        }

        return $over;
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

    public function updateManagerByNewManerID(Request $request): JsonResponse
    {
        $old_id = $request->old_id;
        $new_id = $request->new_id;

        // $data = user::where('manager_id',$old_id)->get();
        $data = user::where('manager_id', $old_id)->update(['manager_id' => $new_id]);
        $newUser = user::where('id', $new_id)->update(['is_manager' => 1]);
        $user = user::where('id', $old_id)->update(['is_manager' => 0]);
        $teamManager = ManagementTeamMember::where('team_lead_id', $old_id)->update(['team_lead_id' => $new_id]);

        return response()->json([
            'ApiName' => 'Update User Manager Id',
            'status' => true,
            'message' => 'Updated Successfully.',
            // 'data' => $data,
        ], 200);
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

                if ($UserDeduction && ($UserDeduction->ammount_par_paycheck != $deduction['ammount_par_paycheck'] || $UserDeduction->cost_center_id != $deduction['cost_center_id'])) {
                    if (! empty($UserDeduction->effective_date) && strtotime($UserDeduction->effective_date) < strtotime($deduction['effective_date']) && strtotime($deduction['effective_date']) <= strtotime(date('Y-m-d'))) {
                        $UserDeduction->ammount_par_paycheck = $deduction['ammount_par_paycheck'];
                        $UserDeduction->cost_center_id = $deduction['cost_center_id'];
                        $UserDeduction->effective_date = $deduction['effective_date'];
                    } elseif (empty($UserDeduction->effective_date) || (isset($UserDeductionHistory->effective_date) && strtotime($UserDeduction->effective_date) <= strtotime($UserDeductionHistory->effective_date))) {
                        $UserDeduction->ammount_par_paycheck = isset($UserDeductionHistory->amount_par_paycheque) ? $UserDeductionHistory->amount_par_paycheque : $UserDeduction->ammount_par_paycheck;
                        $UserDeduction->effective_date = isset($UserDeductionHistory->effective_date) ? $UserDeductionHistory->effective_date : $UserDeduction->effective_date;
                    }

                    $UserDeduction->save();
                }

            }
        }

        // create history code

        return response()->json([
            'ApiName' => 'Update User Deduction API',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function deleteEmploymentPackageHistory(Request $request): JsonResponse
    {

        $Validator = Validator::make(
            $request->all(),
            [
                'type' => 'required',
                'id' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $data = [];

        if ($request->type == 'Override') {
            $sub_type = isset($request->sub_type) ? $request->sub_type : null;
            $override = UserOverrideHistory::where('id', $request->id)->first();
            if (! empty($override)) {
                $userid = $override->user_id;
                $old_override = UserOverrideHistory::where('user_id', $userid)
                    ->where('override_effective_date', '<', $override->override_effective_date)
                    ->orderBy('override_effective_date', 'DESC')->first();

                $next_override = UserOverrideHistory::where('user_id', $userid)
                    ->where('override_effective_date', '>', $override->override_effective_date)
                    ->orderBy('override_effective_date', 'ASC')
                    ->first();

                if (! empty($old_override) && ! empty($next_override)) {
                    $next_override->old_direct_overrides_amount = $old_override->direct_overrides_amount;
                    $next_override->old_direct_overrides_type = $old_override->direct_overrides_type;
                    $next_override->old_indirect_overrides_amount = $old_override->indirect_overrides_amount;
                    $next_override->old_indirect_overrides_type = $old_override->indirect_overrides_type;
                    $next_override->old_office_overrides_amount = $old_override->office_overrides_amount;
                    $next_override->old_office_overrides_type = $old_override->office_overrides_type;
                    $next_override->old_office_stack_overrides_amount = $old_override->office_stack_overrides_amount;
                    $next_override->save();

                } elseif (empty($old_override) && ! empty($next_override)) {
                    $next_override->old_direct_overrides_amount = 0.00;
                    $next_override->old_direct_overrides_type = null;
                    $next_override->old_indirect_overrides_amount = 0.00;
                    $next_override->old_indirect_overrides_type = null;
                    $next_override->old_office_overrides_amount = 0.00;
                    $next_override->old_office_overrides_type = null;
                    $next_override->old_office_stack_overrides_amount = 0.00;
                    $next_override->save();
                }
                $additionalOldEffectiveDate = ''; // "2023-06-14";
                $additionalNextEffectiveDate = '';

                if (! empty($old_override)) {
                    $additionalOldEffectiveDate = $old_override->override_effective_date;
                }

                if (! empty($next_override)) {
                    $additionalNextEffectiveDate = $next_override->override_effective_date;
                }
                $addi_old_override = UserAdditionalOfficeOverrideHistory::where('user_id', $userid)
                    ->where('override_effective_date', $additionalOldEffectiveDate)
                    ->orderBy('override_effective_date', 'DESC')->get();

                $addi_next_override = UserAdditionalOfficeOverrideHistory::where('user_id', $userid)
                    ->where('override_effective_date', $additionalNextEffectiveDate)
                    ->orderBy('override_effective_date', 'ASC')
                    ->get();

                if (! empty($addi_next_override)) {
                    $updData = [];
                    foreach ($addi_next_override as $key => $value) {
                        // $updData[] = array(
                        //     'office_overrides_amount' => $value['old_office_overrides_amount'],
                        //     'office_overrides_type' => $value['old_office_overrides_type'],
                        //     'old_office_overrides_amount' =>0,
                        //     'old_office_overrides_type' =>null,
                        //     'office_id' => $value['office_id'],
                        //     'id' => $value['id']
                        // );

                        foreach ($addi_old_override as $oldvalue) {
                            if ($value['office_id'] == $oldvalue['office_id']) {
                                $updData[] = [
                                    'old_office_overrides_amount' => $oldvalue['office_overrides_amount'],
                                    'old_office_overrides_type' => $oldvalue['office_overrides_type'],
                                    'office_id' => $oldvalue['office_id'],
                                    'id' => $value['id'],
                                ];
                            }

                        }
                    }
                }
                foreach ($updData as $key => $value) {
                    UserAdditionalOfficeOverrideHistory::where('id', $value['id'])->update($value);
                }
                if ($sub_type == 'Direct') {
                    UserOverrideHistory::where('id', $request->id)->update([
                        'direct_overrides_amount' => null, 'direct_overrides_type' => null,
                    ]);
                }
                if ($sub_type == 'Indirect') {
                    UserOverrideHistory::where('id', $request->id)->update([
                        'indirect_overrides_amount' => null, 'indirect_overrides_type' => null,
                    ]);
                }
                if ($sub_type == 'Office') {
                    UserOverrideHistory::where('id', $request->id)->update([
                        'office_overrides_amount' => null, 'office_overrides_type' => null,
                    ]);
                }
                if ($sub_type == 'Office_stack') {
                    UserOverrideHistory::where('id', $request->id)->update([
                        'office_stack_overrides_amount' => null,
                    ]);
                }
                // $userDelete = UserOverrideHistory::where('id',$request->id)->delete();
                $overrideAdditional = UserAdditionalOfficeOverrideHistory::where('user_id', $userid)->where('override_effective_date', $override->override_effective_date)->delete();
            }

        }

        if ($request->type == 'Redline') {
            $redlines = UserRedlines::where('id', $request->id)->first();
            if (! empty($redlines)) {
                $userid = $redlines->user_id;
                $old_redlines = UserRedlines::where('user_id', $userid)
                    ->where('position_type', $redlines->position_type)
                    ->where('start_date', '<', $redlines->start_date)
                    ->orderBy('start_date', 'DESC')->first();
                $next_redlines = UserRedlines::where('user_id', $userid)
                    ->where('position_type', $redlines->position_type)
                    ->where('start_date', '>', $redlines->start_date)
                    ->orderBy('start_date', 'ASC')
                    ->first();
                if (! empty($old_redlines) && ! empty($next_redlines)) {
                    $next_redlines->old_redline = $old_redlines->redline;
                    $next_redlines->old_redline_type = $old_redlines->redline_type;
                    $next_redlines->old_redline_amount_type = $old_redlines->redline_amount_type;
                    $next_redlines->save();
                } elseif (empty($old_redlines) && ! empty($next_redlines)) {
                    $next_redlines->old_redline = 0.00;
                    $next_redlines->old_redline_type = null;
                    $next_redlines->old_redline_amount_type = null;
                    $next_redlines->save();
                }
                $userDelete = UserRedlines::where('id', $request->id)->delete();
            }
        }

        if ($request->type == 'Commission') {
            $commission = UserCommissionHistory::where('id', $request->id)->first();
            if (! empty($commission)) {
                $userid = $commission->user_id;
                $old_commission = UserCommissionHistory::where('user_id', $userid)
                    ->where('position_id', $commission->position_id)
                    ->where('commission_effective_date', '<', $commission->commission_effective_date)
                    ->orderBy('commission_effective_date', 'DESC')->first();
                $next_commission = UserCommissionHistory::where('user_id', $userid)
                    ->where('position_id', $commission->position_id)
                    ->where('commission_effective_date', '>', $commission->commission_effective_date)
                    ->orderBy('commission_effective_date', 'ASC')
                    ->first();
                if (! empty($old_commission) && ! empty($next_commission)) {
                    $next_commission->old_commission = $old_commission->commission;
                    $next_commission->save();
                } elseif (empty($old_commission) && ! empty($next_commission)) {
                    $next_commission->old_commission = 0.00;
                    $next_commission->save();
                }
                $userDelete = UserCommissionHistory::where('id', $request->id)->delete();
            }

        }

        if ($request->type == 'Upfront') {
            $upfront = UserUpfrontHistory::where('id', $request->id)->first();
            if (! empty($upfront)) {
                $userid = $upfront->user_id;
                $old_upfront = UserUpfrontHistory::where('user_id', $userid)
                    ->where('position_id', $upfront->position_id)
                    ->where('upfront_effective_date', '<', $upfront->upfront_effective_date)
                    ->orderBy('upfront_effective_date', 'DESC')->first();
                $next_upfront = UserUpfrontHistory::where('user_id', $userid)
                    ->where('position_id', $upfront->position_id)
                    ->where('upfront_effective_date', '>', $upfront->upfront_effective_date)
                    ->orderBy('upfront_effective_date', 'ASC')
                    ->first();
                if (! empty($old_upfront) && ! empty($next_upfront)) {
                    $next_upfront->old_upfront_pay_amount = $old_upfront->upfront_pay_amount;
                    $next_upfront->old_upfront_sale_type = $old_upfront->upfront_sale_type;
                    $next_upfront->save();
                } elseif (empty($old_upfront) && ! empty($next_upfront)) {
                    $next_upfront->old_upfront_pay_amount = 0.00;
                    $next_upfront->old_upfront_sale_type = null;
                    $next_upfront->save();
                }
                $userDelete = UserUpfrontHistory::where('id', $request->id)->delete();
            }
        }

        if ($request->type == 'Withheld') {
            $withheld = UserWithheldHistory::where('id', $request->id)->first();
            if (! empty($withheld)) {
                $userid = $withheld->user_id;
                $old_withheld = UserWithheldHistory::where('user_id', $userid)
                    ->where('position_id', $withheld->position_id)
                    ->where('withheld_effective_date', '<', $withheld->withheld_effective_date)
                    ->orderBy('withheld_effective_date', 'DESC')->first();
                $next_withheld = UserWithheldHistory::where('user_id', $userid)
                    ->where('position_id', $withheld->position_id)
                    ->where('withheld_effective_date', '>', $withheld->withheld_effective_date)
                    ->orderBy('withheld_effective_date', 'ASC')
                    ->first();
                if (! empty($old_withheld) && ! empty($next_withheld)) {
                    $next_withheld->old_withheld_amount = $old_withheld->withheld_amount;
                    $next_withheld->old_withheld_type = $old_withheld->withheld_type;
                    $next_withheld->save();
                } elseif (empty($old_withheld) && ! empty($next_withheld)) {
                    $next_withheld->old_withheld_amount = 0.00;
                    $next_withheld->old_withheld_type = null;
                    $next_withheld->save();
                }
                $userDelete = UserWithheldHistory::where('id', $request->id)->delete();
            }
        }

        if ($request->type == 'Transfer') {
            $transfer = UserTransferHistory::where('id', $request->id)->first();
            if (! empty($transfer)) {
                $userid = $transfer->user_id;
                $old_transfer = UserTransferHistory::where('user_id', $userid)
                    ->where('transfer_effective_date', '<', $transfer->transfer_effective_date)
                    ->orderBy('transfer_effective_date', 'DESC')->first();
                $next_transfer = UserTransferHistory::where('user_id', $userid)
                    ->where('transfer_effective_date', '>', $transfer->transfer_effective_date)
                    ->orderBy('transfer_effective_date', 'ASC')
                    ->first();
                if (! empty($old_transfer) && ! empty($next_transfer)) {
                    $next_transfer->old_state_id = $old_transfer->state_id;
                    $next_transfer->old_office_id = $old_transfer->office_id;
                    $next_transfer->old_department_id = $old_transfer->department_id;
                    $next_transfer->old_position_id = $old_transfer->position_id;
                    $next_transfer->old_is_manager = $old_transfer->is_manager;
                    $next_transfer->old_self_gen_accounts = $old_transfer->self_gen_accounts;
                    $next_transfer->old_manager_id = $old_transfer->manager_id;
                    $next_transfer->old_team_id = $old_transfer->team_id;
                    $next_transfer->old_redline_amount_type = $old_transfer->redline_amount_type;
                    $next_transfer->old_redline = $old_transfer->redline;
                    $next_transfer->old_redline_type = $old_transfer->redline_type;
                    $next_transfer->old_self_gen_redline_amount_type = $old_transfer->self_gen_redline_amount_type;
                    $next_transfer->old_self_gen_redline = $old_transfer->self_gen_redline;
                    $next_transfer->old_self_gen_redline_type = $old_transfer->self_gen_redline_type;
                    $next_transfer->existing_employee_old_manager_id = $old_transfer->existing_employee_new_manager_id;
                    $next_transfer->save();
                } elseif (empty($old_transfer) && ! empty($next_transfer)) {
                    $next_transfer->old_state_id = 0;
                    $next_transfer->old_office_id = 0;
                    $next_transfer->old_department_id = 0;
                    $next_transfer->old_position_id = 0;
                    $next_transfer->old_is_manager = 0;
                    $next_transfer->old_self_gen_accounts = 0;
                    $next_transfer->old_manager_id = 0;
                    $next_transfer->old_team_id = 0;
                    $next_transfer->old_redline_amount_type = 0;
                    $next_transfer->old_redline = 0;
                    $next_transfer->old_redline_type = 0;
                    $next_transfer->old_self_gen_redline_amount_type = 0;
                    $next_transfer->old_self_gen_redline = 0;
                    $next_transfer->old_self_gen_redline_type = $old_transfer->self_gen_redline_type;
                    $next_transfer->existing_employee_old_manager_id = 0;
                    $next_transfer->save();
                }
                $data = UserTransferHistory::where('id', $request->id)->delete();
            }

        }

        if ($request->type == 'userSelfGenCommission') {
            $SelfGenComm = UserSelfGenCommmissionHistory::where('id', $request->id)->first();
            if (! empty($SelfGenComm)) {
                $userid = $SelfGenComm->user_id;
                $old_SelfGenComm = UserSelfGenCommmissionHistory::where('user_id', $userid)
                    ->where('position_id', $SelfGenComm->position_id)
                    ->where('commission_effective_date', '<', $SelfGenComm->commission_effective_date)
                    ->orderBy('commission_effective_date', 'DESC')->first();
                $next_SelfGenComm = UserSelfGenCommmissionHistory::where('user_id', $userid)
                    ->where('position_id', $SelfGenComm->position_id)
                    ->where('commission_effective_date', '>', $SelfGenComm->commission_effective_date)
                    ->orderBy('commission_effective_date', 'ASC')
                    ->first();
                if (! empty($old_SelfGenComm) && ! empty($next_SelfGenComm)) {
                    $next_SelfGenComm->old_commission = $old_SelfGenComm->commission;
                    $next_SelfGenComm->save();
                } elseif (empty($old_SelfGenComm) && ! empty($next_SelfGenComm)) {
                    $next_SelfGenComm->old_commission = 0.00;
                    $next_SelfGenComm->save();
                }
                $userDelete = UserSelfGenCommmissionHistory::where('id', $request->id)->delete();
            }

        }
        if ($request->type == 'Organization') {
            $organization = UserOrganizationHistory::where('id', $request->id)->first();
            if (! empty($organization)) {
                $userid = $organization->user_id;
                $old_organization = UserOrganizationHistory::where('user_id', $userid)
                    ->where('position_id', $organization->position_id)
                    ->where('effective_date', '<', $organization->effective_date)
                    ->orderBy('effective_date', 'DESC')->first();
                $next_organization = UserOrganizationHistory::where('user_id', $userid)
                    ->where('position_id', $organization->position_id)
                    ->where('effective_date', '>', $organization->effective_date)
                    ->orderBy('effective_date', 'ASC')
                    ->first();
                if (! empty($old_organization) && ! empty($next_organization)) {
                    $next_organization->old_manager_id = $old_organization->manager_id;
                    $next_organization->old_team_id = $old_organization->team_id;
                    $next_organization->save();
                } elseif (empty($old_organization) && ! empty($next_organization)) {
                    $next_organization->old_manager_id = null;
                    $next_organization->old_team_id = null;
                    $next_organization->save();
                }
                $userDelete = UserOrganizationHistory::where('id', $request->id)->delete();
            }

        }

        if ($request->type == 'Deduction') {
            $deduction = UserDeductionHistory::where('id', $request->id)->first();
            if (! empty($deduction)) {
                $userid = $deduction->user_id;
                $old_deduction = UserDeductionHistory::where('user_id', $userid)
                    ->where('effective_date', '<', $deduction->effective_date)
                    ->orderBy('effective_date', 'DESC')->first();
                $next_deduction = UserDeductionHistory::where('user_id', $userid)
                    ->where('effective_date', '>', $deduction->effective_date)
                    ->orderBy('effective_date', 'ASC')
                    ->first();
                if (! empty($old_deduction) && ! empty($next_deduction)) {
                    $next_deduction->old_amount_par_paycheque = $old_deduction->amount_par_paycheque;
                    $next_deduction->save();
                } elseif (empty($old_deduction) && ! empty($next_deduction)) {
                    $next_deduction->old_amount_par_paycheque = 0;
                    $next_deduction->save();
                }
                $userDelete = UserDeductionHistory::where('id', $request->id)->delete();
            }
        }

        return response()->json([
            'ApiName' => 'delete Employment Package History',
            'status' => true,
            'message' => $request->type.' record deleted Successfully.',

        ], 200);

    }

    public function get_employment_package_history(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'type' => 'required',
                'user_id' => 'required',
                // 'position_id' => 'required'
            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $data = [];
        $response = [];
        if ($request->type == 'Override') {
            $user = User::where('id', $request->user_id)->first();
            $result = UserOverrideHistory::with('useroverridehistory', 'userAdditionalOfficeHistory')->where(['user_id' => $request->user_id])->orderby('override_effective_date', 'DESC')->get();
            $current = true;
            $additionalCurrent = [];
            if (count($result) > 0) {
                foreach ($result as $key => $res) {

                    $additionalOfficeHistory = [];

                    if ($res->userAdditionalOfficeHistory) {
                        // return $res->userAdditionalOfficeHistory->count();
                        foreach ($res->userAdditionalOfficeHistory as $val1) {

                            $additionalOfficeName = Locations::where('id', $val1->office_id)->first();
                            $additionalStateName = State::where('id', $val1->state_id)->first();

                            if ($res->override_effective_date == $val1->override_effective_date) {
                                $additionalOfficeHistory[] =
                                [
                                    'id' => $val1->id,
                                    'effective_date' => $val1->override_effective_date,
                                    'type' => 'additional_office',
                                    'old_amount' => $val1->old_office_overrides_amount,
                                    'old_type' => $val1->old_office_overrides_type,
                                    'new_amount' => $val1->office_overrides_amount,
                                    'new_type' => $val1->office_overrides_type,
                                    'office_name' => isset($additionalOfficeName->office_name) ? $additionalOfficeName->office_name : null,
                                    'state_name' => isset($additionalStateName->name) ? $additionalStateName->name : null,
                                ];
                            }

                        }
                    }

                    $currentDate = now()->toDateString();
                    if ($key == 0) {

                        $additional_location_id = AdditionalLocations::with('state', 'office')->where('user_id', $res->user_id)->pluck('office_id')->toArray();

                        $overrideEffectiveDate = UserAdditionalOfficeOverrideHistory::where('override_effective_date', '<=', $currentDate)->where('user_id', $res->user_id)->whereIn('office_id', $additional_location_id)->orderBy('override_effective_date', 'desc')->first();

                        if ($overrideEffectiveDate != null && $overrideEffectiveDate != '') {
                            $additional_location_current = UserAdditionalOfficeOverrideHistory::where('override_effective_date', $overrideEffectiveDate->override_effective_date)->where('user_id', $res->user_id)->whereIn('office_id', $additional_location_id)->orderBy('override_effective_date', 'desc')->get();
                        } else {
                            $additional_location_current = null;
                        }

                        if ($additional_location_current) {
                            foreach ($additional_location_current as $data) {

                                $response['current_additional'][] = [
                                    'effective_date' => isset($data->override_effective_date) ? $data->override_effective_date : null,
                                    'state_id' => isset($data->state_id) ? $data->state_id : null,
                                    'state_name' => isset($data->state->name) ? $data->state->name : null,
                                    'office_id' => isset($data->office->id) ? $data->office->id : null,
                                    'office_name' => isset($data->office->office_name) ? $data->office->office_name : null,
                                    'overrides_amount' => isset($data->office_overrides_amount) ? $data->office_overrides_amount : null,
                                    'overrides_type' => isset($data->office_overrides_type) ? $data->office_overrides_type : null,
                                ];
                            }

                        } else {
                            $response['current_additional'] = [];
                        }
                    }

                    $officeName = Locations::where('id', $res->useroverridehistory->office_id)->first();
                    $stateName = State::where('id', $res->useroverridehistory->state_id)->first();
                    // if(!isset($response['effective_date']) || empty($response['effective_date'])){
                    //     $response['effective_date'] = $d->override_effective_date;
                    // }
                    if (strtotime($res->override_effective_date) <= strtotime(date('Y-m-d')) && $current) {
                        $response['current'] = [
                            'direct_amount' => $res->direct_overrides_amount,
                            'direct_type' => $res->direct_overrides_type,
                            'indirect_amount' => $res->indirect_overrides_amount,
                            'indirect_type' => $res->indirect_overrides_type,
                            'office_amount' => $res->office_overrides_amount,
                            'office_type' => $res->office_overrides_type,
                            'effective_date' => $res->override_effective_date,
                            'office_stack_overrides_amount' => $res->office_stack_overrides_amount,

                        ];

                        $current = false;
                    }

                    $response['history'][] = [
                        'id' => $res->id,
                        'effective_date' => $res->override_effective_date,
                        'updater_by' => $res->useroverridehistory,
                        'updated_on' => date('Y-m-d', strtotime($res->updated_at)),
                        'overrides' => [
                            [
                                'type' => 'direct',
                                'old_amount' => $res->old_direct_overrides_amount,
                                'old_type' => $res->old_direct_overrides_type,
                                'new_amount' => $res->direct_overrides_amount,
                                'new_type' => $res->direct_overrides_type,
                            ],
                            [
                                'type' => 'indirect',
                                'old_amount' => $res->old_indirect_overrides_amount,
                                'old_type' => $res->old_indirect_overrides_type,
                                'new_amount' => $res->indirect_overrides_amount,
                                'new_type' => $res->indirect_overrides_type,
                            ],
                            [
                                'type' => 'office',
                                'old_amount' => $res->old_office_overrides_amount,
                                'old_type' => $res->old_office_overrides_type,
                                'new_amount' => $res->office_overrides_amount,
                                'new_type' => $res->office_overrides_type,
                                'office_name' => isset($officeName->office_name) ? $officeName->office_name : null,
                                'state_name' => isset($stateName->name) ? $stateName->name : null,
                            ],
                            [
                                'type' => 'office_stack',
                                'old_amount' => $res->old_office_stack_overrides_amount,
                                'old_type' => null,
                                'new_amount' => $res->office_stack_overrides_amount,
                                'new_type' => null,
                            ],
                        ],
                        'additional_office_hitory' => $additionalOfficeHistory,

                    ];
                }
            } else {
                $response['current'] = [
                    'direct_amount' => $user->direct_overrides_amount,
                    'direct_type' => $user->direct_overrides_type,
                    'indirect_amount' => $user->indirect_overrides_amount,
                    'indirect_type' => $user->indirect_overrides_type,
                    'office_amount' => $user->office_overrides_amount,
                    'office_type' => $user->office_overrides_type,
                    'effective_date' => $user->override_effective_date,
                    'office_stack_overrides_amount' => $user->office_stack_overrides_amount,
                ];
                $response['history'][] = [
                    'id' => $user->id,
                    'effective_date' => $user->override_effective_date,
                    'updater_by' => $user->useroverridehistory,
                    'updated_on' => date('Y-m-d', strtotime($user->updated_at)),
                    'overrides' => [
                        [
                            'type' => 'direct',
                            'old_amount' => 0.00,
                            'old_type' => null,
                            'new_amount' => $user->direct_overrides_amount,
                            'new_type' => $user->direct_overrides_type,
                        ],
                        [
                            'type' => 'indirect',
                            'old_amount' => 0.00,
                            'old_type' => null,
                            'new_amount' => $user->indirect_overrides_amount,
                            'new_type' => $user->indirect_overrides_type,
                        ],
                        [
                            'type' => 'office',
                            'old_amount' => 0.00,
                            'old_type' => null,
                            'new_amount' => $user->office_overrides_amount,
                            'new_type' => $user->office_overrides_type,
                        ],
                        [
                            'type' => 'office_stack',
                            'old_amount' => 0.00,
                            'old_type' => null,
                            'new_amount' => $user->office_stack_overrides_amount,
                            'new_type' => null,
                        ],
                    ],
                ];
            }
            if ($request->has('sort')) {
                $responses = isset($response['history']) ? $response['history'] : [];
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($responses, 'updated_on'), SORT_DESC, $responses);
                } else {
                    array_multisort(array_column($responses, 'updated_on'), SORT_ASC, $responses);
                }
                $response['history'] = $responses;

            }
            $data = $response;
        }

        if ($request->type == 'Redline') {
            $current = true;
            $data = UserRedlines::with('updater')->where(['user_id' => $request->user_id, 'position_type' => $request->position_id])->orderby('start_date', 'DESC')->get();
            foreach ($data as $key => $res) {

                if (strtotime($res->start_date) <= strtotime(date('Y-m-d')) && $current) {
                    $response['current'] = [
                        'position_type' => $res->position_type,
                        'sub_position_type' => $res->sub_position_type,
                        'redline' => $res->redline,
                        'redline_amount_type' => $res->redline_amount_type,
                        'self_gen_user' => $res->self_gen_user,
                        'redline_type' => $res->redline_type,
                        'effective_date' => $res->start_date,
                    ];
                    $current = false;
                }

                $response['history'][] = [
                    'id' => $res->id,
                    'effective_date' => $res->start_date,
                    'updater_by' => isset($res->updater->first_name) ? $res->updater->first_name : null,
                    'updated_on' => date('Y-m-d', strtotime($res->updated_at)),
                    'Redline' => [
                        [
                            'position_type' => $res->position_type,
                            'sub_position_type' => $res->sub_position_type,
                            'redline_amount_type' => $res->redline_amount_type,
                            'old_redline_amount_type' => $res->old_redline_amount_type,
                            'self_gen_user' => $res->self_gen_user,
                            'old_self_gen_user' => $res->old_self_gen_user,
                            'redline' => $res->redline,
                            'old_redline' => $res->old_redline,
                            'redline_type' => $res->redline_type,
                            'old_redline_type' => $res->old_redline_type,
                        ],
                    ],
                ];
            }
            if ($request->has('sort')) {
                $val = $request->input('sort_val');
                $responses = isset($response['history']) ? $response['history'] : [];
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($responses, 'updated_on'), SORT_DESC, $responses);
                } else {
                    array_multisort(array_column($responses, 'updated_on'), SORT_ASC, $responses);
                }
                $response['history'] = $responses;
            }
            $data = $response;

        }

        if ($request->type == 'Commission') {
            $data = UserCommissionHistory::with('updater')->where(['user_id' => $request->user_id, 'position_id' => $request->position_id])->orderby('commission_effective_date', 'DESC')->get();
            $current = true;
            foreach ($data as $key => $res) {

                if (strtotime($res->commission_effective_date) <= strtotime(date('Y-m-d')) && $current) {
                    $response['current'] = [
                        'position_id' => $res->position_id,
                        'sub_position_id' => $res->sub_position_id,
                        'commission' => $res->commission,
                        'commission_effective_date' => $res->commission_effective_date,
                    ];
                    $current = false;
                }

                $response['history'][] = [
                    'id' => $res->id,
                    'effective_date' => $res->commission_effective_date,
                    'updater_by' => isset($res->updater->first_name) ? $res->updater->first_name : null,
                    'updated_on' => date('Y-m-d', strtotime($res->updated_at)),
                    'Commission' => [
                        [
                            'position_id' => $res->position_id,
                            'sub_position_id' => $res->sub_position_id,
                            'commission' => $res->commission,
                            'old_commission' => $res->old_commission,

                        ],
                    ],
                ];
            }
            if ($request->has('sort')) {
                $responses = isset($response['history']) ? $response['history'] : [];
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($responses, 'updated_on'), SORT_DESC, $responses);
                } else {
                    array_multisort(array_column($responses, 'updated_on'), SORT_ASC, $responses);
                }
                $response['history'] = $responses;
            }
            $data = $response;
        }

        if ($request->type == 'Upfront') {
            $data = UserUpfrontHistory::with('updater')->where(['user_id' => $request->user_id, 'position_id' => $request->position_id])->orderby('upfront_effective_date', 'DESC')->get();
            $current = true;
            foreach ($data as $key => $res) {

                if (strtotime($res->upfront_effective_date) <= strtotime(date('Y-m-d')) && $current) {
                    $response['current'] = [
                        'position_id' => $res->position_id,
                        'sub_position_id' => $res->sub_position_id,
                        'upfront_pay_amount' => $res->upfront_pay_amount,
                        'upfront_sale_type' => $res->upfront_sale_type,
                        'upfront_effective_date' => $res->upfront_effective_date,
                    ];
                    $current = false;
                }

                $response['history'][] = [
                    'id' => $res->id,
                    'effective_date' => $res->upfront_effective_date,
                    'updater_by' => isset($res->updater->first_name) ? $res->updater->first_name : null,
                    'updated_on' => date('Y-m-d', strtotime($res->updated_at)),
                    'Upfront' => [
                        [
                            'position_id' => $res->position_id,
                            'sub_position_id' => $res->sub_position_id,
                            'upfront_pay_amount' => $res->upfront_pay_amount,
                            'upfront_sale_type' => $res->upfront_sale_type,
                            'old_upfront_pay_amount' => $res->old_upfront_pay_amount,
                            'old_upfront_sale_type' => $res->old_upfront_sale_type,
                        ],
                    ],
                ];
            }
            if ($request->has('sort')) {
                $responses = isset($response['history']) ? $response['history'] : [];
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($responses, 'updated_on'), SORT_DESC, $responses);
                } else {
                    array_multisort(array_column($responses, 'updated_on'), SORT_ASC, $responses);
                }
                $response['history'] = $responses;
            }
            $data = $response;

        }

        if ($request->type == 'Withheld') {
            $data = UserWithheldHistory::with('updater')->where(['user_id' => $request->user_id, 'position_id' => $request->position_id])->orderby('withheld_effective_date', 'DESC')->get();
            $current = true;
            foreach ($data as $key => $res) {

                if (strtotime($res->withheld_effective_date) <= strtotime(date('Y-m-d')) && $current) {
                    $response['current'] = [
                        'position_id' => $res->position_id,
                        'sub_position_id' => $res->sub_position_id,
                        'withheld_amount' => $res->withheld_amount,
                        'withheld_type' => $res->withheld_type,
                        'withheld_effective_date' => $res->withheld_effective_date,
                    ];
                    $current = false;
                }

                $response['history'][] = [
                    'id' => $res->id,
                    'effective_date' => $res->withheld_effective_date,
                    'updater_by' => isset($res->updater->first_name) ? $res->updater->first_name : null,
                    'updated_on' => date('Y-m-d', strtotime($res->updated_at)),
                    'Withheld' => [
                        [
                            'position_id' => $res->position_id,
                            'sub_position_id' => $res->sub_position_id,
                            'withheld_amount' => $res->withheld_amount,
                            'withheld_type' => $res->withheld_type,
                            'old_withheld_amount' => $res->old_withheld_amount,
                            'old_withheld_type' => $res->old_withheld_type,
                        ],
                    ],
                ];
            }
            if ($request->has('sort')) {
                $responses = isset($response['history']) ? $response['history'] : [];
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($responses, 'updated_on'), SORT_DESC, $responses);
                } else {
                    array_multisort(array_column($responses, 'updated_on'), SORT_ASC, $responses);
                }
                $response['history'] = $responses;
            }
            $data = $response;

        }
        if ($request->type == 'Transfer') {
            $data = UserTransferHistory::with('user', 'updater', 'positions', 'oldPositions', 'office', 'oldOffice', 'state', 'oldState')
                ->where(['user_id' => $request->user_id])
                ->orderby('transfer_effective_date', 'DESC')
                ->get();
            $current = true;
            foreach ($data as $key => $res) {
                if (strtotime($res->transfer_effective_date) <= strtotime(date('Y-m-d')) && $current) {
                    $response['current'] = [
                        'effective_date' => $res->transfer_effective_date,
                        'state_id' => $res->state_id,
                        'office_id' => $res->office_id,
                        'department_id' => $res->department_id,
                        'position_id' => $res->position_id,
                        'sub_position_id' => $res->sub_position_id,
                        'is_manager' => $res->is_manager,
                        'self_gen_accounts' => $res->self_gen_accounts,
                        'manager_id' => $res->manager_id,
                        'team_id' => $res->team_id,
                        'redline_amount_type' => $res->redline_amount_type,
                        'redline' => $res->redline,
                        'redline_type' => $res->redline_type,
                        'self_gen_redline_amount_type' => $res->self_gen_redline_amount_type,
                        'self_gen_redline' => $res->self_gen_redline,
                        'self_gen_redline_type' => $res->self_gen_redline_type,
                        'sub_position_name' => isset($res->positions->position_name) ? $res->positions->position_name : null,
                        'state_name' => isset($res->state->name) ? $res->state->name : null,
                        'office_name' => isset($res->office->office_name) ? $res->office->office_name : null,
                        'updater_name' => $res->updater->first_name.' '.$res->updater->last_name,
                        'old_sub_position_name' => isset($res->oldPositions->position_name) ? $res->oldPositions->position_name : null,
                        'old_state_name' => isset($res->oldState->name) ? $res->oldState->name : null,
                        'old_office_name' => isset($res->oldOffice->office_name) ? $res->oldOffice->office_name : null,
                        'manager_name' => isset($res->manager->first_name) ? $res->manager->first_name.' '.$res->manager->last_name : null,
                        'old_manager_name' => isset($res->oldManager->first_name) ? $res->oldManager->first_name.' '.$res->oldManager->last_name : null,
                        'user_details' => $res->user,
                    ];
                    $current = false;
                }

                $response['history'][] = [
                    'id' => $res->id,
                    'effective_date' => $res->transfer_effective_date,
                    'updater_by' => $res->updater->first_name.' '.$res->updater->last_name,
                    'updated_on' => date('Y-m-d', strtotime($res->updated_at)),
                    'state_id' => $res->state_id,
                    'office_id' => $res->office_id,
                    'department_id' => $res->department_id,
                    'position_id' => $res->position_id,
                    'sub_position_id' => $res->sub_position_id,
                    'is_manager' => $res->is_manager,
                    'self_gen_accounts' => $res->self_gen_accounts,
                    'manager_id' => $res->manager_id,
                    'team_id' => $res->team_id,
                    'redline_amount_type' => $res->redline_amount_type,
                    'redline' => $res->redline,
                    'redline_type' => $res->redline_type,
                    'self_gen_redline_amount_type' => $res->self_gen_redline_amount_type,
                    'self_gen_redline' => $res->self_gen_redline,
                    'self_gen_redline_type' => $res->self_gen_redline_type,
                    'existing_employee_new_manager_id' => $res->existing_employee_new_manager_id,
                    'sub_position_name' => isset($res->positions->position_name) ? $res->positions->position_name : null,
                    'state_name' => isset($res->state->name) ? $res->state->name : null,
                    'office_name' => isset($res->office->office_name) ? $res->office->office_name : null,
                    'updater_name' => $res->updater->first_name.' '.$res->updater->last_name,
                    'old_sub_position_name' => isset($res->oldPositions->position_name) ? $res->oldPositions->position_name : null,
                    'old_state_name' => isset($res->oldState->name) ? $res->oldState->name : null,
                    'old_office_name' => isset($res->oldOffice->office_name) ? $res->oldOffice->office_name : null,
                    'manager_name' => isset($res->manager->first_name) ? $res->manager->first_name.' '.$res->manager->last_name : null,
                    'old_manager_name' => isset($res->oldManager->first_name) ? $res->oldManager->first_name.' '.$res->oldManager->last_name : null,
                    'user_details' => $res->user,
                ];
            }

            if ($request->has('sort')) {
                $responses = isset($response['history']) ? $response['history'] : [];
                if ($request->input('sort_val') == 'desc') {
                    array_multisort(array_column($responses, 'updated_on'), SORT_DESC, $responses);
                } else {
                    array_multisort(array_column($responses, 'updated_on'), SORT_ASC, $responses);
                }
                $response['history'] = $responses;
            }
            $data = $response;

        }

        if ($request->type == 'userSelfGenCommission') {
            $data = UserSelfGenCommmissionHistory::with('updater')->where(['user_id' => $request->user_id])->orderby('commission_effective_date', 'DESC')->get();
            $current = true;
            if ($data != '[]') {
                foreach ($data as $key => $res) {
                    if (strtotime($res->commission_effective_date) <= strtotime(date('Y-m-d')) && $current) {
                        $response['current'] = [
                            'commission' => $res->commission,
                            'commission_effective_date' => $res->commission_effective_date,
                        ];
                        $current = false;
                    }

                    $response['history'][] = [
                        'id' => $res->id,
                        'effective_date' => $res->commission_effective_date,
                        'updater_by' => isset($res->updater->first_name) ? $res->updater->first_name : null,
                        'updated_on' => date('Y-m-d', strtotime($res->updated_at)),
                        'Commission' => [
                            [
                                'commission' => $res->commission,
                                'old_commission' => $res->old_commission,

                            ],
                        ],
                    ];
                }
                if ($request->has('sort')) {
                    $responses = isset($response['history']) ? $response['history'] : [];
                    if ($request->input('sort_val') == 'desc') {
                        array_multisort(array_column($responses, 'updated_on'), SORT_DESC, $responses);
                    } else {
                        array_multisort(array_column($responses, 'updated_on'), SORT_ASC, $responses);
                    }
                    $response['history'] = $responses;
                }
                $data = $response;
            } else {
                $data = [];
            }
        }

        return response()->json([
            'ApiName' => 'get Employement Package History',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function employee_transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'effective_date' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $effectiveDate = date('Y-m-d', strtotime($request->effective_date));
        $userInfo = User::where('id', $request->user_id)->first();
        $data = UserTransferHistory::updateOrCreate([
            'user_id' => $request->user_id,
            'transfer_effective_date' => $effectiveDate,
        ], [
            'updater_id' => auth()->user()->id,
            'state_id' => $request->state_id,
            'office_id' => $request->office_id,
            'department_id' => $userInfo->department_id,
            'position_id' => $userInfo->position_id,
            'sub_position_id' => $userInfo->sub_position_id,
            'is_manager' => $userInfo->is_manager,
            'self_gen_accounts' => $userInfo->self_gen_accounts,
            'manager_id' => isset($request->manager_id) ? $request->manager_id : null,
            'team_id' => $request->team_id,
            'redline_amount_type' => $request->redline_amount_type,
            'redline' => $request->redline,
            'redline_type' => $request->redline_type,
            'self_gen_redline_amount_type' => $request->self_gen_redline_amount_type,
            'self_gen_redline' => $request->self_gen_redline,
            'self_gen_redline_type' => $request->self_gen_redline_type,
            'existing_employee_new_manager_id' => $request->existing_employee_new_manager_id,
        ]);

        if (isset($request->redline)) {
            UserRedlines::updateOrCreate([
                'user_id' => $request->user_id,
                'start_date' => $effectiveDate,
                'position_type' => $userInfo->position_id,
            ], [
                'updater_id' => auth()->user()->id,
                'redline' => $request->redline,
                'redline_type' => $request->redline_type,
                'redline_amount_type' => $request->redline_amount_type,
                'old_redline_amount_type' => isset($userInfo->redline_amount_type) ? $userInfo->redline_amount_type : null,
                'old_redline' => isset($userInfo->redline) ? $userInfo->redline : null,
                'old_redline_type' => isset($userInfo->redline_type) ? $userInfo->redline_type : null,
                'state_id' => $request->state_id,
                'self_gen_user' => 0,
            ]);
        }

        if (isset($request->self_gen_redline)) {
            UserRedlines::updateOrCreate([
                'user_id' => $request->user_id,
                'start_date' => $effectiveDate,
                'position_type' => $userInfo->self_gen_type,
            ], [
                'updater_id' => auth()->user()->id,
                'redline' => $request->self_gen_redline,
                'redline_type' => $request->self_gen_redline_type,
                'redline_amount_type' => $request->self_gen_redline_amount_type,
                'old_redline_amount_type' => isset($userInfo->self_gen_redline_amount_type) ? $userInfo->self_gen_redline_amount_type : null,
                'old_redline' => isset($userInfo->self_gen_redline) ? $userInfo->self_gen_redline : null,
                'old_redline_type' => isset($userInfo->self_gen_redline_type) ? $userInfo->self_gen_redline_type : null,
                'state_id' => $request->state_id,
                'self_gen_user' => 1,
            ]);
        }

        if (! $request->manager_id) {
            UserManagerHistory::create([
                'user_id' => $request->user_id,
                'updater_id' => Auth()->user()->id,
                'effective_date' => $effectiveDate,
            ]);
        }

        if ($request->manager_id || $request->team_id) {
            $positionId = $userInfo->position_id;
            $subPositionId = $userInfo->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $request->user_id)->whereDate('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $positionId = $organizationHistory->position_id;
                $subPositionId = $organizationHistory->sub_position_id;
            }
            UserManagerHistory::create([
                'user_id' => $request->user_id,
                'updater_id' => Auth()->user()->id,
                'effective_date' => $effectiveDate,
                'manager_id' => $request->manager_id,
                'team_id' => $request->team_id,
                'position_id' => $positionId,
                'sub_position_id' => $subPositionId,
            ]);
        }

        // UPDATE MANAGERS
        $userIds = [$request->user_id];
        if (Carbon::parse($effectiveDate)->lessThan(Carbon::today())) {
            if ($request->existing_employee_new_manager_id) {
                $userEmployeeIds = User::where('manager_id', $data->user_id)->get();
                foreach ($userEmployeeIds as $userEmployeeId) {
                    $organizationHistory = UserOrganizationHistory::where('user_id', $userEmployeeId->id)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->first();
                    $lastManager = UserManagerHistory::where(['user_id' => $userEmployeeId->id])->orderBy('effective_date', 'DESC')->first();
                    $date = $effectiveDate;
                    $system = 0;
                    if ($lastManager && Carbon::parse($effectiveDate)->lessThan(Carbon::parse($lastManager->effective_date))) {
                        $date = Carbon::parse($lastManager->effective_date)->addDay()->format('Y-m-d');
                        $system = 1;
                    }
                    UserManagerHistory::updateOrCreate([
                        'user_id' => $userEmployeeId->id,
                        'effective_date' => $date,
                    ], [
                        'user_id' => $userEmployeeId->id,
                        'updater_id' => Auth()->user()->id,
                        'effective_date' => $date,
                        'manager_id' => $request->existing_employee_new_manager_id,
                        'position_id' => @$organizationHistory->position_id ? $organizationHistory->position_id : $userEmployeeId->position_id,
                        'sub_position_id' => @$organizationHistory->sub_position_id ? $organizationHistory->sub_position_id : $userEmployeeId->sub_position_id,
                        'system_generated' => $system,
                    ]);
                    $userIds[] = $userEmployeeId->id;
                }

                $leadData = Lead::where('recruiter_id', $data->user_id)->pluck('id')->toArray();
                if (count($leadData) != 0) {
                    Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $request->existing_employee_new_manager_id]);
                }
            }
        }
        Artisan::call('ApplyHistoryOnUsers:update', ['user_id' => implode(',', $userIds)]);

        if ($effectiveDate <= now()->format('Y-m-d')) {
            $paidPid = UserCommission::where(['amount_type' => 'm2', 'status' => '3', 'is_displayed' => '1'])->pluck('pid');
            $pids = SalesMaster::whereHas('salesMasterProcess', function ($q) use ($request) {
                $q->where('closer1_id', $request->user_id)->orWhere('closer2_id', $request->user_id)->orWhere('setter1_id', $request->user_id)->orWhere('setter2_id', $request->user_id);
            })->whereNull('date_cancelled')->whereNotNull('customer_signoff')->where(function ($q) {
                $q->whereNotNull('m1_date')->orWhereNotNull('m2_date');
            })->whereNotIn('pid', $paidPid)->pluck('pid');

            if ($pids) {
                $dataForPusher = [
                    'user_id' => $request->user_id,
                ];
                ProcessRecalculatesOpenSales::dispatch($pids, $dataForPusher);
            }
        }

        return response()->json([
            'ApiName' => 'Add Employement Transfer History',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function addAdmin(Request $request): JsonResponse
    {
        $position = Positions::select('id', 'parent_id', 'department_id')->where('position_name', 'Super Admin')->first();

        $create = [
            'first_name' => isset($request->first_name) ? $request->first_name : null,
            'last_name' => isset($request->last_name) ? $request->last_name : null,
            'email' => isset($request->email) ? $request->email : null,
            'is_super_admin' => 1,
            'onboardProcess' => 1,
            'group_id' => isset($request->permission) ? $request->permission : null,
            'mobile_no' => isset($request->phone_number) ? $request->phone_number : null,
            'password' => Hash::make('Sequified#12'),
            'position_id' => isset($position->parent_id) ? $position->parent_id : 0,
            'sub_position_id' => isset($position->id) ? $position->id : 0,
            'department_id' => isset($position->department_id) ? $position->department_id : null,
            'direct_overrides_type' => '',
            'indirect_overrides_type' => '',
            'office_overrides_type' => '',
            'redline_type' => '',
            'period_of_agreement_start_date' => date('Y-m-d'),
        ];
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $create['direct_overrides_type'] = '';
            $create['indirect_overrides_type'] = '';
            $create['office_overrides_type'] = '';
            $create['redline_type'] = '';
        }
        $user_mobile_no = User::where('mobile_no', $request->phone_number)->first();
        if ($user_mobile_no && ! $user_mobile_no->isTodayTerminated()) {
            return response()->json([
                'ApiName' => 'Send Credentials',
                'status' => false,
                'message' => 'Mobile no is already exist',
            ], 400);
        }
        $user_email = User::where('email', $request->email)->first();
        if ($user_email && ! $user_email->isTodayTerminated()) {
            return response()->json([
                'ApiName' => 'Send Credentials',
                'status' => false,
                'message' => 'email is already exist',
            ], 400);
        }

        $data = User::Create($create);
        $new_created_user_id = $data->id;

        UserAgreementHistory::create([
            'user_id' => $new_created_user_id,
            'updater_id' => isset(auth()->user()->id) ? auth()->user()->id : 0,
            'probation_period' => null,
            'offer_include_bonus' => 0,
            'hiring_bonus_amount' => null,
            'date_to_be_paid' => null,
            'period_of_agreement' => date('Y-m-d'),
            'end_date' => null,
            'offer_expiry_date' => null,
            'hired_by_uid' => null,
            'hiring_signature' => null,
        ]);

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

            UserOrganizationHistory::create([
                'user_id' => $data->id,
                'updater_id' => isset(auth()->user()->id) ? auth()->user()->id : 0,
                'product_id' => 1,
                'effective_date' => date('Y-m-d'),
                'position_id' => isset($position->parent_id) ? $position->parent_id : null,
                'sub_position_id' => isset($position->id) ? $position->id : null,
                'is_manager' => 0,
                'deleted_at' => null,
            ]);

            if (in_array(config('app.domain_name'), ['hawxw2', 'sstage'])) {
                W2UserTransferHistory::create([
                    'user_id' => $data->id,
                    'updater_id' => isset(auth()->user()->id) ? auth()->user()->id : 0,
                    'period_of_agreement' => date('Y-m-d'),
                    'type' => 'w2',
                ]);
            }
        }

        $check = User::where('id', $data->id)->first();

        $check->new_password = 'Sequified#12';
        $salesData = [];
        $salesData['email'] = $check->email;
        $salesData['subject'] = 'Login Credentials';
        $salesData['template'] = view('mail.credentials', compact('check'));

        /* Welcome email from template setting */
        $otherData = [];
        $otherData['new_password'] = 'Sequified#12';
        $welcomeEmailContent = SequiDocsEmailSettings::welcome_email_content($check, $otherData);
        $emailContent['email'] = $check->email;
        $emailContent['subject'] = $welcomeEmailContent['subject'];
        $emailContent['template'] = $welcomeEmailContent['template'];
        $checkDomainSetting = DomainSetting::check_domain_setting($check->email);
        if ($checkDomainSetting['status'] == true) {
            if ($welcomeEmailContent['is_active'] == 1 && $welcomeEmailContent['template'] != '') {
                $this->sendEmailNotification($emailContent);
            } else {
                $this->sendEmailNotification($salesData);
            }
        } else {
            $this->sendEmailNotification($salesData);
        }

        return response()->json([
            'ApiName' => 'addAdmin',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // update hubspot data start
    public function update_employees($Hubspotdata, $token, $user_id, $aveyoid, $table = 'user')
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
            $hs_object_id = $resp['properties']['hs_object_id'];
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

    public function hireDateUpdate(Request $request): JsonResponse
    {

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
                        Log::info('Debug - newHireDate < oldHireDate');
                        $integration = Integration::where(['name' => 'FieldRoutes', 'status' => 1])->first();
                        if ($integration) {
                            $this->pullSalesFromFieldRoutes($request->user_id, $newHireDate, $oldHireDate);
                        }
                    } else {
                        Log::info('Debug - newHireDate >= oldHireDate');
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

    public function check_selfgen_user($history, $table = '')
    {
        $user_id = $history->user_id;
        // $effective_date = $history->commission_effective_date;
        $id = $history->id;
        $main_position_name = isset($history->subposition->position_name) ? $history->subposition->position_name : null;
        $selfgen_position_name = isset($history->position->position_name) ? $history->position->position_name : null;
        $position_name = null;
        $data = [];
        if ($table == 'UserCommissionHistory') {
            $data = UserCommissionHistory::where(['user_id' => $user_id, 'commission_effective_date' => $history->commission_effective_date])->get();
        }
        if ($table == 'UserRedlines') {
            $data = UserRedlines::where(['user_id' => $user_id, 'start_date' => $history->start_date])->get();
        }
        if ($table == 'UserUpfrontHistory') {
            $data = UserUpfrontHistory::where(['user_id' => $user_id, 'upfront_effective_date' => $history->upfront_effective_date])->get();
        }
        if ($table == 'UserWithheldHistory') {
            $data = UserWithheldHistory::where(['user_id' => $user_id, 'withheld_effective_date' => $history->withheld_effective_date])->get();
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

    public function combine_redline_commission_upfront_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';
        $history_array = [];

        if (empty($filter) || ($filter == 'Commission')) {
            // UserCommissionHistory
            $user_commission_history = UserCommissionHistory::with('updater', 'subposition', 'position')->where('user_id', $user_id);
            if ($future_only) {
                $user_commission_history = $user_commission_history->where('commission_effective_date', '>', date('Y-m-d'));
            }
            $user_commission_history = $user_commission_history->get();
            foreach ($user_commission_history as $commission_history) {
                if (($commission_history->old_commission != null && $commission_history->old_commission != '') || ($commission_history->commission != null && $commission_history->commission != '')) {
                    $position_name = $this->check_selfgen_user($commission_history, 'UserCommissionHistory');
                    // Get commission type display value (with custom field support)
                    $commissionType = $this->getCommissionTypeDisplayForAudit($commission_history->commission_type, $commission_history->custom_sales_field_id ?? null);
                    $oldCommissionType = $this->getCommissionTypeDisplayForAudit($commission_history->old_commission_type, $commission_history->old_custom_sales_field_id ?? null);

                    $history_array[] = [
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
        $user_redline_history = UserRedlines::with('updater', 'subposition', 'position')->where('user_id', $user_id);
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
            $user_upfront_history = UserUpfrontHistory::with('updater', 'subposition', 'position')->where('user_id', $user_id);
            if ($future_only) {
                $user_upfront_history = $user_upfront_history->where('upfront_effective_date', '>', date('Y-m-d'));
            }
            $user_upfront_history = $user_upfront_history->get();
            foreach ($user_upfront_history as $upfront_history) {
                if (($upfront_history->old_upfront_pay_amount != null && $upfront_history->old_upfront_pay_amount != '') || ($upfront_history->upfront_pay_amount != null && $upfront_history->upfront_pay_amount != '')) {
                    $position_name = $this->check_selfgen_user($upfront_history, 'UserUpfrontHistory');
                    $history_array[] = [
                        'id' => $upfront_history->id,
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
            $user_withheld_history = UserWithheldHistory::with('updater', 'subposition', 'position')->where('user_id', $user_id);
            if ($future_only) {
                $user_withheld_history = $user_withheld_history->where('withheld_effective_date', '>', date('Y-m-d'));
            }
            $user_withheld_history = $user_withheld_history->get();
            foreach ($user_withheld_history as $withheld_history) {
                if (($withheld_history->old_withheld_amount != null && $withheld_history->old_withheld_amount != '') || ($withheld_history->withheld_amount != null && $withheld_history->withheld_amount != '')) {
                    $position_name = $this->check_selfgen_user($withheld_history, 'UserWithheldHistory');
                    $history_array[] = [
                        'id' => $withheld_history->id,
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
        if (empty($filter) || ($filter == 'Self Gen Commission')) {
            // UserSelfGenCommmissionHistory
            $user_self_gen_history = UserSelfGenCommmissionHistory::with('updater', 'subposition', 'position')->where('user_id', $user_id);
            if ($future_only) {
                $user_self_gen_history = $user_self_gen_history->where('commission_effective_date', '>', date('Y-m-d'));
            }
            $user_self_gen_history = $user_self_gen_history->get();
            foreach ($user_self_gen_history as $self_gen_history) {
                if ($self_gen_history->old_commission != null || $self_gen_history->commission != null) {
                    $history_array[] = [
                        'id' => $self_gen_history->id,
                        'effective_date' => $self_gen_history->commission_effective_date,
                        'type' => 'Self Gen Commission',
                        'position_id' => $self_gen_history->position_id,
                        'sub_position_id' => isset($self_gen_history->sub_position_id) ? $self_gen_history->sub_position_id : null,
                        'sub_position_name' => isset($self_gen_history->subposition->position_name) ? $self_gen_history->subposition->position_name : null,
                        'old_value' => $self_gen_history->old_commission,
                        'new_value' => $self_gen_history->commission,
                        'position_role' => isset($self_gen_history->subposition->position_name) ? $self_gen_history->subposition->position_name : null,

                        'old_amount' => $self_gen_history->old_commission,
                        'new_amount' => $self_gen_history->commission,

                        'updated_on' => $self_gen_history->updated_at,
                        'updater' => $self_gen_history->updater,
                        'percentage' => get_growth_percentage($self_gen_history->old_commission, $self_gen_history->commission),
                    ];
                }
            }
        }

        // Personal Information History
        if (empty($filter) || ($filter == 'Personal')) {
            $personal_history = UserPersonalInfoHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $personal_history = $personal_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $personal_history = $personal_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $personal_history = $personal_history->where('effective_date', $effective_date);
            }
            $personal_history = $personal_history->get();

            foreach ($personal_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        $oldValue = $history->old_values[$field] ?? 'N/A';
                        $newValue = $history->new_values[$field] ?? 'N/A';

                        // Format field name for display
                        $fieldName = ucwords(str_replace('_', ' ', $field));

                        $changes = "Personal Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date,
                            'type' => 'Personal Information',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
                }
            }
        }

        // Banking Information History
        if (empty($filter) || ($filter == 'Banking')) {
            $banking_history = UserBankHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $banking_history = $banking_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $banking_history = $banking_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $banking_history = $banking_history->where('effective_date', $effective_date);
            }
            $banking_history = $banking_history->get();

            foreach ($banking_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        // Use the model's getOldValue and getNewValue methods to handle decryption
                        $oldValue = $history->getOldValue($field) ?? 'N/A';
                        $newValue = $history->getNewValue($field) ?? 'N/A';

                        // Mask sensitive banking info
                        if (in_array($field, ['account_no', 'routing_no', 'confirm_account_no'])) {
                            if ($oldValue !== 'N/A' && strlen($oldValue) > 4) {
                                $oldValue = str_repeat('*', strlen($oldValue) - 4) . substr($oldValue, -4);
                            }
                            if ($newValue !== 'N/A' && strlen($newValue) > 4) {
                                $newValue = str_repeat('*', strlen($newValue) - 4) . substr($newValue, -4);
                            }
                        }

                        // Format field name for display
                        $fieldName = ucwords(str_replace('_', ' ', $field));

                        $changes = "Banking Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date,
                            'type' => 'Banking Information',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
                }
            }
        }

        // Tax Information History
        if (empty($filter) || ($filter == 'Tax')) {
            $tax_history = UserTaxHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $tax_history = $tax_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $tax_history = $tax_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $tax_history = $tax_history->where('effective_date', $effective_date);
            }
            $tax_history = $tax_history->get();

            foreach ($tax_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        // Use the model's getOldValue and getNewValue methods to handle decryption
                        $oldValue = $history->getOldValue($field) ?? 'N/A';
                        $newValue = $history->getNewValue($field) ?? 'N/A';

                        // Mask sensitive tax info (SSN, EIN)
                        if (in_array($field, ['social_sequrity_no', 'business_ein'])) {
                            if ($oldValue !== 'N/A' && strlen($oldValue) > 4) {
                                $oldValue = str_repeat('*', strlen($oldValue) - 4) . substr($oldValue, -4);
                            }
                            if ($newValue !== 'N/A' && strlen($newValue) > 4) {
                                $newValue = str_repeat('*', strlen($newValue) - 4) . substr($newValue, -4);
                            }
                        }

                        // Format field name for display
                        $fieldName = ucwords(str_replace('_', ' ', $field));

                        $changes = "Tax Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date,
                            'type' => 'Tax Information',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
                }
            }
        }

        // Employment Status History
        if (empty($filter) || ($filter == 'Employment')) {
            $employment_history = UserEmploymentStatusHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $employment_history = $employment_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $employment_history = $employment_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $employment_history = $employment_history->where('effective_date', $effective_date);
            }
            $employment_history = $employment_history->get();

            // Status ID to name mapping
            $statusMapping = [
                1 => 'Active',
                2 => 'Inactive',
                3 => 'Stop Payroll',
                4 => 'Delete',
                5 => 'Reset Password',
                6 => 'Disable Login',
                7 => 'Terminate',
            ];

            // Field-specific value mappings
            $fieldValueMappings = [
                'stop_payroll' => [
                    0 => 'Start Payroll',
                    1 => 'Stop Payroll',
                ],
                'disable_login' => [
                    0 => 'Grant Access',
                    1 => 'Suspend Access',
                ],
                'terminate' => [
                    0 => 'Not Terminated',
                    1 => 'Terminate with Effective Date',
                ],
                'contract_ended' => [
                    0 => 'Active Contract',
                    1 => 'Contract Ended',
                ],
                'dismiss' => [
                    0 => 'Not Dismissed',
                    1 => 'Dismissed',
                ],
                'rehire' => [
                    0 => 'Not Rehired',
                    1 => 'Rehired',
                ],
                'action_item_status' => [
                    0 => 'Inactive',
                    1 => 'Active',
                ],
                'onboardProcess' => [
                    0 => 'Not Onboarding',
                    1 => 'Onboarding',
                ],
            ];

            foreach ($employment_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        $oldValue = $history->getOldValue($field);
                        $newValue = $history->getNewValue($field);

                        // Handle status_id field specially
                        if ($field === 'status_id') {
                            $oldValue = $statusMapping[$oldValue] ?? $oldValue ?? 'N/A';
                            $newValue = $statusMapping[$newValue] ?? $newValue ?? 'N/A';
                            $fieldName = 'Status';
                        }
                        // Handle fields with specific value mappings
                        elseif (isset($fieldValueMappings[$field])) {
                            $oldValue = $fieldValueMappings[$field][$oldValue] ?? ($oldValue ?? 'N/A');
                            $newValue = $fieldValueMappings[$field][$newValue] ?? ($newValue ?? 'N/A');
                            $fieldName = ucwords(str_replace('_', ' ', $field));
                        }
                        // Handle date fields
                        elseif ($field === 'end_date') {
                            $oldValue = $oldValue ? date('M d, Y', strtotime($oldValue)) : 'N/A';
                            $newValue = $newValue ? date('M d, Y', strtotime($newValue)) : 'N/A';
                            $fieldName = 'End Date';
                        }
                        // Handle text fields
                        elseif (in_array($field, ['termination_reason', 'notes'])) {
                            $oldValue = $oldValue ?? 'N/A';
                            $newValue = $newValue ?? 'N/A';
                            $fieldName = ucwords(str_replace('_', ' ', $field));
                        }
                        // Default handling
                        else {
                            $oldValue = $oldValue ?? 'N/A';
                            $newValue = $newValue ?? 'N/A';
                            $fieldName = ucwords(str_replace('_', ' ', $field));
                        }

                        $changes = "Employment Status: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date,
                            'type' => 'Employment Status',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
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
            'ApiName' => 'combine_redline_commission_upfront_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $data,
        ], 200);
    }

    public function combine_override_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';

        // UserOverrideHistory
        $user_override_history = UserOverrideHistory::with('updater')->where('user_id', $user_id);
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

    public function combine_organization_history(Request $request): JsonResponse
    {
        $user_id = $request->user_id;
        $history_array = [];
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';
        $history_type = isset($request->history_type) ? $request->history_type : null;

        if ($history_type == '') {
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
                    } elseif (! empty($locations->archived_at)) {
                        $history_array[] = [
                            'id' => $locations->id,
                            'type' => 'Additional Location',
                            'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                            'old_value' => $state.' | '.$office,
                            'new_value' => 'Archived',
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

            if (empty($filter) || ($filter == 'Department')) {
                $departmentHistories = UserDepartmentHistory::with('department', 'oldDepartment', 'updater')->where('user_id', $user_id);
                if ($future_only) {
                    $departmentHistories = $departmentHistories->where('effective_date', '>', date('Y-m-d'));
                }
                $departmentHistories = $departmentHistories->get();

                if ($departmentHistories) {
                    foreach ($departmentHistories as $history) {
                        $history_array[] = [
                            'id' => $history->id,
                            'type' => 'Department',
                            'effective_date' => isset($history->effective_date) ? $history->effective_date : null,
                            'old_value' => isset($history->oldDepartment) ? $history->oldDepartment->name : null,
                            'new_value' => isset($history->department) ? $history->department->name : null,
                            'updated_at' => $history->updated_at,
                            'updater' => isset($history->updater) ? $history->updater : null,
                        ];
                    }
                }
            }
        }

        // user wages history

        if ($history_type == 'UserWages') {
            $userWageses = UserWagesHistory::with('updater')->where('user_id', $user_id);
            if ($future_only) {
                $userWageses = $userWageses->where('effective_date', '>', date('Y-m-d'));
            }
            $userWageses = $userWageses->get();

            if ($userWageses) {
                foreach ($userWageses as $userWages) {
                    if (empty($filter) || ($filter == 'Pay_Rate')) {
                        $history_array[] = [
                            'id' => $userWages->id,
                            'type' => 'Pay Rate',
                            'effective_date' => isset($userWages->effective_date) ? $userWages->effective_date : null,
                            'old_value' => isset($userWages->old_pay_rate) ? $userWages->old_pay_rate.'/'.(isset($userWages->old_pay_type) ? $userWages->old_pay_type : '') : null,
                            'new_value' => isset($userWages->pay_rate) ? $userWages->pay_rate.'/'.(isset($userWages->pay_type) ? $userWages->pay_type : '') : null,
                            'updated_at' => $userWages->updated_at,
                            'updater' => isset($userWages->updater) ? $userWages->updater : null,
                        ];
                    }
                    if (empty($filter) || ($filter == 'PTO_Hours')) {
                        $history_array[] = [
                            'id' => $userWages->id,
                            'type' => 'PTO Hours',
                            'effective_date' => isset($userWages->pto_hours_effective_date) ? $userWages->pto_hours_effective_date : null,
                            'old_value' => isset($userWages->old_pto_hours) ? $userWages->old_pto_hours : null,
                            'new_value' => isset($userWages->pto_hours) ? $userWages->pto_hours : null,
                            'updated_at' => $userWages->updated_at,
                            'updater' => isset($userWages->updater) ? $userWages->updater : null,
                        ];
                    }
                    if (empty($filter) || ($filter == 'Expected_Weekly_Hours')) {
                        $history_array[] = [
                            'id' => $userWages->id,
                            'type' => 'Expected Weekly Hours',
                            'effective_date' => isset($userWages->pto_hours_effective_date) ? $userWages->pto_hours_effective_date : null,
                            'old_value' => isset($userWages->old_expected_weekly_hours) ? $userWages->old_expected_weekly_hours : null,
                            'new_value' => isset($userWages->expected_weekly_hours) ? $userWages->expected_weekly_hours : null,
                            'updated_at' => $userWages->updated_at,
                            'updater' => isset($userWages->updater) ? $userWages->updater : null,
                        ];
                    }
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

    public function combine_commission_upfront_history_log(Request $request)
    {
        $user_id = $request->user_id;
        $product_id = $request->product_id;
        $sort_by = isset($request->sort_by) ? $request->sort_by : 'effective_date';
        $sort_type = isset($request->sort_type) ? $request->sort_type : 'asc';
        $perpage = isset($request->perpage) ? $request->perpage : 10;
        $future_only = isset($request->future_only) ? $request->future_only : false;
        $filter = isset($request->filter) ? $request->filter : '';
        $updater_id = $request->updater_id;
        $effective_date = $request->effective_date;
        $history_array = [];

        if (empty($filter) || ($filter == 'Commissions')) {
            // UserCommissionHistory
            $user_commission_history = UserCommissionHistory::with('product:id,name', 'updater', 'subposition', 'position')->where(['user_id' => $user_id, 'product_id' => $product_id]);
            if ($future_only) {
                $user_commission_history = $user_commission_history->where('commission_effective_date', '>', date('Y-m-d'));
            }
            if (! empty($updater_id)) {
                $user_commission_history = $user_commission_history->where('updater_id', $updater_id);
            }
            if (! empty($effective_date)) {
                $user_commission_history = $user_commission_history->where('commission_effective_date', $effective_date);
            }
            $user_commission_history = $user_commission_history->get();
            foreach ($user_commission_history as $commission_history) {
                if (($commission_history->old_commission != null && $commission_history->old_commission != '') || ($commission_history->commission != null && $commission_history->commission != '')) {
                    // $position_name = $this->check_selfgen_user($commission_history,'UserCommissionHistory');
                    // Get commission type display value (with custom field support)
                    $commissionType = $this->getCommissionTypeDisplayForAudit($commission_history->commission_type, $commission_history->custom_sales_field_id ?? null);
                    $oldCommissionType = $this->getCommissionTypeDisplayForAudit($commission_history->old_commission_type, $commission_history->old_custom_sales_field_id ?? null);

                    $changes = 'Commissions changed from '.(! empty($commission_history->old_commission) ? $commission_history->old_commission : 0)."{$oldCommissionType} to {$commission_history->commission}{$commissionType} for {$commission_history->position->position_name}";

                    $history_array[] = [
                        'id' => $commission_history->id,
                        'effective_date' => $commission_history->commission_effective_date,
                        'type' => 'Commission',
                        'product' => $commission_history->product->name,
                        'product_id' => $commission_history->product->id,
                        'updated_on' => $commission_history->updated_at,
                        'description' => $changes,
                        'updater' => $commission_history->updater,
                    ];
                }
            }
            // return $history_array;
        }
        if (empty($filter) || ($filter == 'Redlines')) {
            // UserRedlines
            // $user_redline_history = UserRedlines::with('product:id,name','updater','subposition','position')->where(['user_id' => $user_id, 'product_id' => $product_id]);
            $user_redline_history = UserRedlines::with('product:id,name', 'updater', 'subposition', 'position')->where(['user_id' => $user_id]);
            if ($future_only) {
                $user_redline_history = $user_redline_history->where('start_date', '>', date('Y-m-d'));
            }
            if (! empty($updater_id)) {
                $user_redline_history = $user_redline_history->where('updater_id', $updater_id);
            }
            if (! empty($effective_date)) {
                $user_redline_history = $user_redline_history->where('start_date', $effective_date);
            }
            $user_redline_history = $user_redline_history->get();
            foreach ($user_redline_history as $redline_history) {
                if (($redline_history->old_redline != null && $redline_history->old_redline != '') || ($redline_history->redline != null && $redline_history->redline != '')) {
                    // $position_name = $this->check_selfgen_user($redline_history,'UserRedlines');
                    if ($redline_history->redline_amount_type == 'Fixed') {
                        $changes = 'Fixed Redline changed from '.(! empty($redline_history->old_redline) ? $redline_history->old_redline : 0)."{$redline_history->old_redline_type} to {$redline_history->redline} {$redline_history->redline_type} for {$redline_history->position->position_name}";
                        $history_array[] = [
                            'id' => $redline_history->id,
                            'effective_date' => $redline_history->start_date,
                            'type' => 'Fixed Redline',
                            'product' => $redline_history->product->name ?? null,
                            'product_id' => $redline_history->product->id ?? null,
                            'updated_on' => $redline_history->updated_at,
                            'description' => $changes,
                            'updater' => $redline_history->updater,
                        ];
                    } else {
                        $changes = 'Location Redline changed from '.(! empty($redline_history->old_redline) ? $redline_history->old_redline : 0)."{$redline_history->old_redline_type} to {$redline_history->redline} {$redline_history->redline_type} for {$redline_history->position->position_name}";
                        $history_array[] = [
                            'id' => $redline_history->id,
                            'effective_date' => $redline_history->start_date,
                            'type' => 'Location Redline',
                            'product' => $redline_history->product->name ?? null,
                            'product_id' => $redline_history->product->id ?? null,
                            'updated_on' => $redline_history->updated_at,
                            'description' => $changes,
                            'updater' => $redline_history->updater,
                        ];
                    }
                }
            }
        }
        if (empty($filter) || ($filter == 'Upfronts')) {
            // UserUpfrontHistory
            $user_upfront_history = UserUpfrontHistory::with('product:id,name', 'updater', 'subposition', 'position', 'schema')->where(['user_id' => $user_id, 'product_id' => $product_id]);
            if ($future_only) {
                $user_upfront_history = $user_upfront_history->where('upfront_effective_date', '>', date('Y-m-d'));
            }
            if (! empty($updater_id)) {
                $user_upfront_history = $user_upfront_history->where('updater_id', $updater_id);
            }
            if (! empty($effective_date)) {
                $user_upfront_history = $user_upfront_history->where('upfront_effective_date', $effective_date);
            }
            $user_upfront_history = $user_upfront_history->get();
            foreach ($user_upfront_history as $upfront_history) {
                if (($upfront_history->old_upfront_pay_amount != null && $upfront_history->old_upfront_pay_amount != '') || ($upfront_history->upfront_pay_amount != null && $upfront_history->upfront_pay_amount != '')) {
                    // Determine position name based on self_gen_user and core_position_id
                    $position_display_name = $upfront_history->position_display_name;

                    // Get upfront sale type display value (with custom field support)
                    $upfrontSaleType = $this->getTypeDisplayForAudit($upfront_history->upfront_sale_type, $upfront_history->custom_sales_field_id ?? null);
                    $oldUpfrontSaleType = $this->getTypeDisplayForAudit($upfront_history->old_upfront_sale_type, null);

                    $changes = 'Upfront changed from '.(! empty($upfront_history->old_upfront_pay_amount) ? $upfront_history->old_upfront_pay_amount : 0)."{$oldUpfrontSaleType} to {$upfront_history->upfront_pay_amount}{$upfrontSaleType} for {$position_display_name} on milestone {$upfront_history->schema->name}";
                    $history_array[] = [
                        'id' => $upfront_history->id,
                        'effective_date' => $upfront_history->upfront_effective_date,
                        'type' => 'Upfront',
                        'product' => $upfront_history->product->name,
                        'product_id' => $upfront_history->product->id,
                        'updated_on' => $upfront_history->updated_at,
                        'description' => $changes,
                        'updater' => $upfront_history->updater,
                    ];
                }
            }
        }
        if (empty($filter) || ($filter == 'Withholdings')) {
            // UserWithheldHistory
            $user_withheld_history = UserWithheldHistory::with('product:id,name', 'updater', 'subposition', 'position')->where(['user_id' => $user_id, 'product_id' => $product_id]);
            if ($future_only) {
                $user_withheld_history = $user_withheld_history->where('withheld_effective_date', '>', date('Y-m-d'));
            }
            if (! empty($updater_id)) {
                $user_withheld_history = $user_withheld_history->where('updater_id', $updater_id);
            }
            if (! empty($effective_date)) {
                $user_withheld_history = $user_withheld_history->where('withheld_effective_date', $effective_date);
            }
            $user_withheld_history = $user_withheld_history->get();
            foreach ($user_withheld_history as $withheld_history) {
                if (($withheld_history->old_withheld_amount != null && $withheld_history->old_withheld_amount != '') || ($withheld_history->withheld_amount != null && $withheld_history->withheld_amount != '')) {
                    $changes = 'Withheld changed from '.(! empty($withheld_history->old_withheld_amount) ? $withheld_history->old_withheld_amount : 0)."{$withheld_history->old_withheld_type} to {$withheld_history->withheld_amount} {$withheld_history->withheld_type} for {$withheld_history->position->position_name}";
                    $history_array[] = [
                        'id' => $withheld_history->id,
                        'effective_date' => $withheld_history->withheld_effective_date,
                        'type' => 'Withheld',
                        'product' => $withheld_history->product->name,
                        'product_id' => $withheld_history->product->id,
                        'updated_on' => $withheld_history->updated_at,
                        'description' => $changes,
                        'updater' => $withheld_history->updater,
                    ];
                }
            }
        }
        if (empty($filter) || ($filter == 'Deductions')) {
            $user_deduction_history = UserDeductionHistory::with('updater', 'costcenter')->where('user_id', $user_id);
            if ($future_only) {
                $user_deduction_history = $user_deduction_history->where('effective_date', '>', date('Y-m-d'));
            }
            $user_deduction_history = $user_deduction_history->get();
            $user = User::select('id', 'sub_position_id')->where('id', $user_id)->first();
            $costCenterIds = PositionCommissionDeduction::where('position_id', $user->sub_position_id)->pluck('cost_center_id')->toArray();
            foreach ($user_deduction_history as $deduction_history) {
                $isDelete = in_array($deduction_history->cost_center_id, $costCenterIds) ? 0 : 1;
                if ($deduction_history->old_amount_par_paycheque != $deduction_history->amount_par_paycheque) {
                    $costCenterType = isset($deduction_history->costcenter->name) ? $deduction_history->costcenter->name : null;
                    $oldAmount = ! empty($deduction_history->old_amount_par_paycheque) ? $deduction_history->old_amount_par_paycheque : 0;

                    $changes = $isDelete == 1 ? "{$costCenterType} deleted from {$oldAmount}" : "{$costCenterType} changed from {$oldAmount} to {$deduction_history->amount_par_paycheque}";

                    $history_array[] = [
                        'id' => $deduction_history->id,
                        'effective_date' => $deduction_history->effective_date,
                        'type' => $costCenterType,
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $deduction_history->updated_at,
                        'description' => $changes,
                        'updater' => $deduction_history->updater,
                    ];
                }

            }
        }
        if (empty($filter) || ($filter == 'Overrides')) {
            $user_override_history = UserOverrideHistory::with('product:id,name', 'updater')->where('user_id', $user_id);
            if ($future_only) {
                $user_override_history = $user_override_history->where('override_effective_date', '>', date('Y-m-d'));
            }

            foreach ($user_override_history->get() as $override_history) {
                // Get override type display values (with custom field support)
                // Each override type has its own custom sales field ID column
                $directType = $this->getTypeDisplayForAudit($override_history->direct_overrides_type, $override_history->direct_custom_sales_field_id ?? null);
                $oldDirectType = $this->getTypeDisplayForAudit($override_history->old_direct_overrides_type, null);
                $indirectType = $this->getTypeDisplayForAudit($override_history->indirect_overrides_type, $override_history->indirect_custom_sales_field_id ?? null);
                $oldIndirectType = $this->getTypeDisplayForAudit($override_history->old_indirect_overrides_type, null);
                $officeType = $this->getTypeDisplayForAudit($override_history->office_overrides_type, $override_history->office_custom_sales_field_id ?? null);
                $oldOfficeType = $this->getTypeDisplayForAudit($override_history->old_office_overrides_type, null);

                if ($override_history->old_direct_overrides_amount.' '.$override_history->old_direct_overrides_type != $override_history->direct_overrides_amount.' '.$override_history->direct_overrides_type) {
                    $changes = 'Direct Override changed from '.(! empty($override_history->old_direct_overrides_amount) ? $override_history->old_direct_overrides_amount : 0)."{$oldDirectType} to {$override_history->direct_overrides_amount}{$directType}";
                    $history_array[] = [
                        'id' => $override_history->id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Direct Override',
                        'product' => $override_history->product->name,
                        'product_id' => $override_history->product->id,
                        'updated_on' => $override_history->updated_at,
                        'description' => $changes,
                        'updater' => $override_history->updater,
                    ];
                }
                if ($override_history->old_indirect_overrides_amount.' '.$override_history->old_indirect_overrides_type != $override_history->indirect_overrides_amount.' '.$override_history->indirect_overrides_type) {
                    $changes = 'Indirect Override changed from '.(! empty($override_history->old_indirect_overrides_amount) ? $override_history->old_indirect_overrides_amount : 0)."{$oldIndirectType} to {$override_history->indirect_overrides_amount}{$indirectType}";
                    $history_array[] = [
                        'id' => $override_history->id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Indirect Override',
                        'product' => $override_history->product->name,
                        'product_id' => $override_history->product->id,
                        'updated_on' => $override_history->updated_at,
                        'description' => $changes,
                        'updater' => $override_history->updater,
                    ];
                }
                if ($override_history->old_office_overrides_amount.' '.$override_history->old_office_overrides_type != $override_history->office_overrides_amount.' '.$override_history->office_overrides_type) {
                    $changes = 'Office Override changed from '.(! empty($override_history->old_office_overrides_amount) ? $override_history->old_office_overrides_amount : 0)."{$oldOfficeType} to {$override_history->office_overrides_amount}{$officeType}";
                    $history_array[] = [
                        'id' => $override_history->id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Office Override',
                        'product' => $override_history->product->name,
                        'product_id' => $override_history->product->id,
                        'updated_on' => $override_history->updated_at,
                        'description' => $changes,
                        'updater' => $override_history->updater,
                    ];
                }
                if ($override_history->old_office_stack_overrides_amount != $override_history->office_stack_overrides_amount) {
                    $changes = 'Office Stack Override changed from '.(! empty($override_history->old_office_stack_overrides_amount) ? $override_history->old_office_stack_overrides_amount : 0)." percent to {$override_history->office_stack_overrides_amount} percent";
                    $history_array[] = [
                        'id' => $override_history->id,
                        'effective_date' => $override_history->override_effective_date,
                        'type' => 'Office Stack Override',
                        'product' => $override_history->product->name,
                        'product_id' => $override_history->product->id,
                        'updated_on' => $override_history->updated_at,
                        'description' => $changes,
                        'updater' => $override_history->updater,
                    ];
                }

            }
            $userAdditionnOffHistoryData = UserAdditionalOfficeOverrideHistory::with('product:id,name', 'updater')->where('user_id', $user_id)->get();
            foreach ($userAdditionnOffHistoryData as $key => $value) {
                // Get additional office override type display values (with custom field support)
                $additionalOfficeType = $this->getTypeDisplayForAudit($value->office_overrides_type, $value->custom_sales_field_id ?? null);
                $oldAdditionalOfficeType = $this->getTypeDisplayForAudit($value->old_office_overrides_type, null);
                $changes = 'Additional Office Override changed from '.(! empty($value->old_office_overrides_amount) ? $value->old_office_overrides_amount : 0)."{$oldAdditionalOfficeType} to {$value->office_overrides_amount}{$additionalOfficeType}";
                $history_array[] = [
                    'id' => $value->id,
                    'effective_date' => $value->override_effective_date,
                    'type' => 'Additional Office Override',
                    'product' => $value->product->name,
                    'product_id' => $value->product->id,
                    'updated_on' => $value->updated_at,
                    'description' => $changes,
                    'updater' => $value->updater,
                ];
            }
        }

        if (empty($filter) || ($filter == 'Organizations')) {
            $oraganization_history = UserOrganizationHistory::with('product:id,name', 'updater', 'oldManager', 'manager', 'oldTeam', 'team', 'position', 'oldPosition', 'subPositionId', 'oldSubPositionId')->where('user_id', $user_id);
            if ($future_only) {
                $oraganization_history = $oraganization_history->where('effective_date', '>', date('Y-m-d'));
            }
            $oraganization_history = $oraganization_history->orderBy('effective_date')->get();
            // dd($oraganization_history);
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
                        $changes = 'Self Gen changed from '.(! empty($org_history->old_self_gen_accounts) ? 'YES' : 'NO').' to '.(! empty($org_history->self_gen_accounts) ? 'YES' : 'NO');
                        $history_array[] = [
                            'id' => $org_history->id,
                            'effective_date' => isset($org_history->effective_date) ? $org_history->effective_date : null,
                            'type' => 'Self Gen',
                            'product' => $org_history->product->name,
                            'product_id' => $org_history->product->id,
                            'updated_on' => $org_history->updated_at,
                            'description' => $changes,
                            'updater' => isset($org_history->updater) ? $org_history->updater : null,
                        ];
                    }
                }
                $oldSelf = $org_history->self_gen_accounts;

                if (! empty($org_history->old_sub_position_id) || ! empty($org_history->sub_position_id)) {
                    if (empty($filter) || ($filter == 'Position')) {
                        $changes = 'Position changed from '.(! empty($org_history->oldSubPositionId->position_name) ? $org_history->oldSubPositionId->position_name : '')." to {$org_history->subPositionId->position_name}";
                        $history_array[] = [
                            'id' => $org_history->id,
                            'effective_date' => isset($org_history->effective_date) ? $org_history->effective_date : null,
                            'type' => 'Position',
                            'product' => $org_history->product->name,
                            'product_id' => $org_history->product->id,
                            'updated_on' => $org_history->updated_at,
                            'description' => $changes,
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

                    $changes = 'is manager changed from '.(! empty($isManager->old_is_manager) ? 'YES' : 'NO').' to '.(! empty($isManager->is_manager) ? 'YES' : 'NO');
                    $history_array[] = [
                        'id' => $isManager->id,
                        'effective_date' => isset($isManager->effective_date) ? $isManager->effective_date : null,
                        'type' => 'is manager',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $isManager->updated_at,
                        'description' => $changes,
                        'updater' => isset($isManager->updater) ? $isManager->updater : null,
                    ];
                }
            }

            $managers = UserManagerHistory::with('updater', 'manager', 'oldManager', 'team', 'oldTeam')->where('user_id', $user_id);
            if ($future_only) {
                $managers = $managers->where('effective_date', '>', date('Y-m-d'));
            }
            $managers = $managers->get();

            if ($managers) {
                foreach ($managers as $manager) {
                    $changes = 'Manager changed from '.(! empty($manager->oldManager->first_name) ? $manager->oldManager->first_name.' '.$manager->oldManager->last_name : '').' to '.(! empty($manager->manager->first_name) ? $manager->manager->first_name.' '.$manager->manager->last_name : '');
                    $history_array[] = [
                        'id' => $manager->id,
                        'effective_date' => isset($manager->effective_date) ? $manager->effective_date : null,
                        'type' => 'Manager',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $manager->updated_at,
                        'description' => $changes,
                        'updater' => isset($manager->updater) ? $manager->updater : null,
                    ];

                    if ($manager->team_id) {
                        $changes = 'Team changed from '.(! empty($manager->oldTeam->team_name) ? $manager->oldTeam->team_name.' '.$manager->oldTeam->last_name : '').' to '.(! empty($manager->team->first_name) ? $manager->team->first_name.' '.$manager->team->last_name : '');
                        $history_array[] = [
                            'id' => $manager->id,
                            'effective_date' => isset($manager->effective_date) ? $manager->effective_date : null,
                            'type' => 'Team',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $manager->updated_at,
                            'description' => $changes,
                            'updater' => isset($manager->updater) ? $manager->updater : null,
                        ];
                    }
                }
            }

            $additional_locations = AdditionalLocations::with('state', 'office', 'updater')->where('user_id', $user_id)->withTrashed();
            if ($future_only) {
                $additional_locations = $additional_locations->where('effective_date', '>', date('Y-m-d'));
            }
            $additional_locations = $additional_locations->get();
            foreach ($additional_locations as $locations) {
                $state = isset($locations->state->name) ? $locations->state->name : '';
                $office = isset($locations->office->office_name) ? $locations->office->office_name : '';
                if (empty($locations->deleted_at)) {
                    $changes = 'Additional Location changed from - '." to {$state} | {$office}";
                    $history_array[] = [
                        'id' => $locations->id,
                        'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                        'type' => 'Additional Location',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $locations->updated_at,
                        'description' => $changes,
                        'updater' => isset($locations->updater) ? $locations->updater : null,
                    ];
                } elseif (! empty($locations->archived_at)) {
                    $changes = "Additional Location Archived from {$state} | {$office} ";
                    $history_array[] = [
                        'id' => $locations->id,
                        'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                        'type' => 'Additional Location',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $locations->updated_at,
                        'description' => $changes,
                        'updater' => isset($locations->updater) ? $locations->updater : null,
                    ];
                } else {
                    $changes = "Additional Location Deleted from {$state} | {$office} ";
                    $history_array[] = [
                        'id' => $locations->id,
                        'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                        'type' => 'Additional Location',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $locations->updated_at,
                        'description' => $changes,
                        'updater' => isset($locations->updater) ? $locations->updater : null,
                    ];
                }
            }
        }

        if (empty($filter) || ($filter == 'Transfers')) {
            $user_transfer_history = UserTransferHistory::with('user', 'department', 'oldDepartment', 'updater', 'position', 'oldPosition', 'subposition', 'oldSubPosition', 'office', 'oldOffice', 'state', 'oldState')
                ->where(['user_id' => $request->user_id])
                ->orderby('transfer_effective_date', 'DESC');
            if ($future_only) {
                $user_transfer_history = $user_transfer_history->where('transfer_effective_date', '>', date('Y-m-d'));
            }
            $user_transfer_history = $user_transfer_history->get();

            foreach ($user_transfer_history as $key => $res) {
                $oldOffice = $res->oldOffice->office_name ?? 'NA';
                $oldState = $res->oldState->name ?? 'NA';
                $newOffice = $res->office->office_name ?? 'NA';
                $newState = $res->state->name ?? 'NA';

                $changes = "Transfer from {$oldOffice} | {$oldState} to {$newOffice} | {$newState}";
                $history_array[] = [
                    'id' => $res->id,
                    'effective_date' => $res->transfer_effective_date,
                    'type' => 'Transfers',
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $res->updated_at,
                    'description' => $changes,
                    'updater' => isset($res->updater) ? $res->updater : null,
                ];
            }
        }
        if (empty($filter) || ($filter == 'Wages')) {
            $user_wages_history = UserWagesHistory::with('updater')->where('user_id', $user_id);
            if ($future_only) {
                $user_wages_history = $user_wages_history->where('effective_date', '>', date('Y-m-d'));
            }
            $user_wages_history = $user_wages_history->get();

            foreach ($user_wages_history as $wages) {
                $fields = [
                    'pay_type' => 'Wages pay type',
                    'pay_rate' => 'Wages pay rate',
                    'expected_weekly_hours' => 'Wages expected weekly hours',
                    'overtime_rate' => 'Wages overtime rate',
                    'pto_hours' => 'Wages pto hours',
                    'unused_pto_expires' => 'Wages unused pto expires',
                ];

                foreach ($fields as $field => $label) {
                    $oldField = "old_{$field}";
                    $oldValue = ! empty($wages->$oldField) ? $wages->$oldField : 0;
                    $newValue = $wages->$field;
                    $changes = "$label Change from {$oldValue} to {$newValue}";

                    $history_array[] = [
                        'id' => $wages->id,
                        'effective_date' => in_array($field, ['pto_hours', 'unused_pto_expires']) ? $wages->pto_hours_effective_date : $wages->effective_date,
                        'type' => $label,
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $wages->updated_at,
                        'description' => $changes,
                        'updater' => $wages->updater,
                    ];
                }

                // Special case: pay_rate with rate_type
                $oldPayRate = ! empty($wages->old_pay_rate) ? $wages->old_pay_rate : 0;
                $changes = "Wages pay rate Change from {$oldPayRate} {$wages->old_pay_rate_type} to {$wages->pay_rate} {$wages->pay_rate_type}";
                $history_array[] = [
                    'id' => $wages->id,
                    'effective_date' => $wages->effective_date,
                    'type' => 'Wages pay rate',
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $wages->updated_at,
                    'description' => $changes,
                    'updater' => $wages->updater,
                ];
            }

        }

        // Personal Information History
        if (empty($filter) || ($filter == 'Personal')) {
            $personal_history = UserPersonalInfoHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $personal_history = $personal_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $personal_history = $personal_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $personal_history = $personal_history->where('effective_date', $effective_date);
            }
            $personal_history = $personal_history->get();

            foreach ($personal_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        $oldValue = $history->old_values[$field] ?? 'N/A';
                        $newValue = $history->new_values[$field] ?? 'N/A';
                        $fieldName = ucwords(str_replace('_', ' ', $field));
                        $changes = "Personal Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date ?? $history->created_at?->format('Y-m-d'),
                            'type' => 'Personal Information',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
                }
            }
        }

        // Banking Information History
        if (empty($filter) || ($filter == 'Banking')) {
            $banking_history = UserBankHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $banking_history = $banking_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $banking_history = $banking_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $banking_history = $banking_history->where('effective_date', $effective_date);
            }
            $banking_history = $banking_history->get();

            foreach ($banking_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        $oldValue = $history->getOldValue($field) ?? 'N/A';
                        $newValue = $history->getNewValue($field) ?? 'N/A';
                        // Mask sensitive banking info
                        if (in_array($field, ['account_no', 'routing_no', 'confirm_account_no'])) {
                            if ($oldValue !== 'N/A' && strlen($oldValue) > 4) {
                                $oldValue = str_repeat('*', strlen($oldValue) - 4) . substr($oldValue, -4);
                            }
                            if ($newValue !== 'N/A' && strlen($newValue) > 4) {
                                $newValue = str_repeat('*', strlen($newValue) - 4) . substr($newValue, -4);
                            }
                        }
                        $fieldName = ucwords(str_replace('_', ' ', $field));
                        $changes = "Banking Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date ?? $history->created_at?->format('Y-m-d'),
                            'type' => 'Banking Information',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
                }
            }
        }

        // Tax Information History
        if (empty($filter) || ($filter == 'Tax')) {
            $tax_history = UserTaxHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $tax_history = $tax_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $tax_history = $tax_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $tax_history = $tax_history->where('effective_date', $effective_date);
            }
            $tax_history = $tax_history->get();

            foreach ($tax_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        $oldValue = $history->getOldValue($field) ?? 'N/A';
                        $newValue = $history->getNewValue($field) ?? 'N/A';
                        // Mask sensitive tax info (SSN, EIN)
                        if (in_array($field, ['social_sequrity_no', 'business_ein'])) {
                            if ($oldValue !== 'N/A' && strlen($oldValue) > 4) {
                                $oldValue = str_repeat('*', strlen($oldValue) - 4) . substr($oldValue, -4);
                            }
                            if ($newValue !== 'N/A' && strlen($newValue) > 4) {
                                $newValue = str_repeat('*', strlen($newValue) - 4) . substr($newValue, -4);
                            }
                        }
                        $fieldName = ucwords(str_replace('_', ' ', $field));
                        $changes = "Tax Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date ?? $history->created_at?->format('Y-m-d'),
                            'type' => 'Tax Information',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
                }
            }
        }

        // Employment Status History
        if (empty($filter) || ($filter == 'Employment')) {
            $employment_history = UserEmploymentStatusHistory::with('changedBy')->where('user_id', $user_id);
            if ($future_only) {
                $employment_history = $employment_history->where('effective_date', '>', date('Y-m-d'));
            }
            if (!empty($updater_id)) {
                $employment_history = $employment_history->where('changed_by', $updater_id);
            }
            if (!empty($effective_date)) {
                $employment_history = $employment_history->where('effective_date', $effective_date);
            }
            $employment_history = $employment_history->get();

            $statusMapping = [
                1 => 'Active', 2 => 'Inactive', 3 => 'Stop Payroll',
                4 => 'Delete', 5 => 'Reset Password', 6 => 'Disable Login', 7 => 'Terminate',
            ];
            $fieldValueMappings = [
                'stop_payroll' => [0 => 'Start Payroll', 1 => 'Stop Payroll'],
                'disable_login' => [0 => 'Grant Access', 1 => 'Suspend Access'],
                'terminate' => [0 => 'Not Terminated', 1 => 'Terminate with Effective Date'],
                'contract_ended' => [0 => 'Active Contract', 1 => 'Contract Ended'],
                'dismiss' => [0 => 'Not Dismissed', 1 => 'Dismissed'],
                'rehire' => [0 => 'Not Rehired', 1 => 'Rehired'],
            ];

            foreach ($employment_history as $history) {
                if (!empty($history->changed_fields)) {
                    foreach ($history->changed_fields as $field) {
                        $oldValue = $history->getOldValue($field);
                        $newValue = $history->getNewValue($field);

                        if ($field === 'status_id') {
                            $oldValue = $statusMapping[$oldValue] ?? $oldValue ?? 'N/A';
                            $newValue = $statusMapping[$newValue] ?? $newValue ?? 'N/A';
                            $fieldName = 'Status';
                        } elseif (isset($fieldValueMappings[$field])) {
                            $oldValue = $fieldValueMappings[$field][$oldValue] ?? ($oldValue ?? 'N/A');
                            $newValue = $fieldValueMappings[$field][$newValue] ?? ($newValue ?? 'N/A');
                            $fieldName = ucwords(str_replace('_', ' ', $field));
                        } elseif ($field === 'end_date') {
                            $oldValue = $oldValue ? date('M d, Y', strtotime($oldValue)) : 'N/A';
                            $newValue = $newValue ? date('M d, Y', strtotime($newValue)) : 'N/A';
                            $fieldName = 'End Date';
                        } else {
                            $oldValue = $oldValue ?? 'N/A';
                            $newValue = $newValue ?? 'N/A';
                            $fieldName = ucwords(str_replace('_', ' ', $field));
                        }

                        $changes = "Employment Status: {$fieldName} changed from {$oldValue} to {$newValue}";
                        $history_array[] = [
                            'id' => $history->id,
                            'effective_date' => $history->effective_date ?? $history->created_at?->format('Y-m-d'),
                            'type' => 'Employment Status',
                            'product' => null,
                            'product_id' => null,
                            'updated_on' => $history->updated_at,
                            'description' => $changes,
                            'updater' => $history->changedBy,
                        ];
                    }
                }
            }
        }

        if ($sort_type == 'asc') {
            $history_array = collect($history_array)->sortBy($sort_by)->toArray();
        } else {
            $history_array = collect($history_array)->sortByDesc($sort_by)->toArray();
        }

        $history_array = collect($history_array)->sortByDesc('effective_date')->groupBy(function ($item) {
            return date('F Y', strtotime($item['effective_date']));
        });

        return response()->json([
            'ApiName' => 'combine_redline_commission_upfront_history',
            'status' => true,
            'message' => 'Successfully',
            'data' => $history_array,
        ], 200);
        // Group by month

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

        $user = User::select('id', 'sub_position_id')->where('id', $user_id)->first();
        $costCenterIds = PositionCommissionDeduction::where('position_id', $user->sub_position_id)->pluck('cost_center_id')->toArray();
        foreach ($user_deduction_history as $deduction_history) {
            if (in_array($deduction_history->cost_center_id, $costCenterIds)) {
                $isDelete = 0;
            } else {
                $isDelete = 1;
            }

            if ($deduction_history->old_amount_par_paycheque != $deduction_history->amount_par_paycheque) {
                $history_array[] = [
                    'id' => $deduction_history->id,
                    'effective_date' => $deduction_history->effective_date,
                    'type' => isset($deduction_history->costcenter->name) ? $deduction_history->costcenter->name : null,
                    'old_value' => $deduction_history->old_amount_par_paycheque,
                    'new_value' => $deduction_history->amount_par_paycheque,
                    'updated_on' => $deduction_history->updated_at,
                    'updater' => $deduction_history->updater,
                    'is_deleted' => $isDelete,
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
                            ])->update(
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

                                ]);
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
                            ])->update(
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
                                ]);
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
                            ])->update(
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
                                ]);
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
                            ])->update(
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
                                ]);
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
                            ])->update(
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

                                ]);
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
                            ])->update(
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
                                ]);
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
                            ])->update(
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
                                ]);
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
                            ])->update(
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
                                ]);
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
    /* public function customSmartFieldsDetailByUserId($id, $type){
        //$type == Onboarding_employee
        //$type == users
        $user_id = '';

        // dd($type);

        if($type == 'users'){

            if(auth()->user()->is_super_admin){
                $data = User::withoutGlobalScope('notTerminated')->find($id);
            } else {
                $data = User::find($id);
            }
            $user_id = $data->id;

        } else {

            $data = OnboardingEmployees::find($id);
            if(isset($data) && $data != ''){
                if(isset($data->user_id) && $data->user_id!=""){
                    $type='users';
                    $user_id = $data->user_id;
                }
                else{
                    $type='onboarding_employees';
                    $user_id = $id;
                }
            }
            else{
                $user_id = $id;
                $type='onboarding_employees';
            }
        }

        if (isset($data) && $data != '') {

            // if($type=='Onboarding_employee'){
            //     $type='onboarding_employees';
            // }else{
            //     $type='users';
            // }
            //dd($type);
            $documents = NewSequiDocsDocument::where([
                'category_id' => 101,
                'user_id' => $user_id,
                'is_active' => 1,
                'user_id_from'=>$type
            ])->groupBy('user_id')->get();

            if($document->smart_text_template_fied_keyval){
                $documents->transform(function ($document) {
                    return [
                        'smart_text_template_fied_keyval' => json_decode($document->smart_text_template_fied_keyval),
                    ];
                });
            }else{
                $dataCusto = OnboardingEmployees::find($id);
                if(isset($dataCusto) && $dataCusto != ''){
                    $dataCusto->transform(function ($dataCusto) {
                        return [
                            'smart_text_template_fied_keyval' => json_decode($dataCusto->custom_fields),
                        ];
                    });
                }
            }


            return response()->json([
                'ApiName' => 'customSmartFieldsDetailByUserId',
                'status' => true,
                'message' => '',
                'data' => $documents
            ], 200);



        } else {

            return response()->json([
                'ApiName' => 'customSmartFieldsDetailByUserId',
                'status' => false,
                'message' => 'Invalid user id',
                'data' => []
            ], 400);

        }


    } */

    public function updateCustomSmartFieldsDetails(Request $request): JsonResponse
    {

        $this->validate($request, [
            'document_id' => 'required|exists:new_sequi_docs_documents,id',
            'updated_data' => 'required',
        ]);

        $document = NewSequiDocsDocument::find($request->document_id);
        $updated_data = $request->updated_data;

        if (! $document_id) {

            return response()->json([
                'ApiName' => 'updateCustomSmartFieldsDetails',
                'status' => false,
                'message' => 'Invalid NewSequiDocsDocument id',
                'data' => [],
            ], 400);

        }

        $document->smart_text_template_fied_keyval = json_encode($updated_data);
        $document->save();

        return response()->json([
            'ApiName' => 'updateCustomSmartFieldsDetails',
            'status' => true,
            'message' => 'Data Updated.',
            'data' => $document,
        ], 200);

    }

    public function allocateManagerToUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prev_manager_id' => 'required|integer|exists:users,id',
            'existing_employee_new_manager_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'apiName' => 'Allocate Manager to User',
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $prev_manager_id = $request->prev_manager_id;
        $manager_id = $request->existing_employee_new_manager_id;
        /* $hasActivePayroll = Payroll::query()
        ->where('user_id', $prev_manager_id)
        ->where('status', 1)
        ->exists();
        if ($hasActivePayroll) {
            return response()->json([
                'ApiName' => 'Allocate Manager to User',
                'success' => false,
                'message' => 'The employee has active payroll records and cannot be terminated.',
                'errors' => 'ACTIVE_PAYROLL_RESTRICTION'
            ], 400);
        } */
        try {
            DB::beginTransaction();

            $managerUsers = User::where('manager_id', $prev_manager_id)->get();
            foreach ($managerUsers as $user) {
                $effective_date = date('Y-m-d', strtotime($user->manager_id_effective_date));

                if (isset($effective_date)) {
                    UserOrganizationHistory::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'effective_date' => $effective_date,
                        ],
                        [
                            'user_id' => $user->id,
                            'updater_id' => auth()->user()->id,
                            'manager_id' => $manager_id,
                        ]
                    );
                }

                $prevManager = UserManagerHistory::where('user_id', $user->id)
                    ->where('effective_date', '<=', $effective_date)
                    ->orderBy('effective_date', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($prevManager) {
                    if ($prevManager->manager_id != $manager_id) {
                        UserManagerHistory::create([
                            'user_id' => $user->id,
                            'updater_id' => auth()->user()->id,
                            'effective_date' => date('Y-m-d'),
                            'old_manager_id' => $prevManager->manager_id,
                            'manager_id' => $manager_id,
                        ]);
                    }
                } else {
                    UserManagerHistory::create([
                        'user_id' => $user->id,
                        'updater_id' => auth()->user()->id,
                        'effective_date' => date('Y-m-d'),
                        'manager_id' => $manager_id,
                    ]);
                }

                $leadData = Lead::where('recruiter_id', $user->id)->pluck('id')->toArray();
                if (count($leadData) > 0) {
                    Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $manager_id]);
                }
            }

            DB::commit();

            return response()->json([
                'apiName' => 'Allocate Manager to User',
                'status' => true,
                'message' => 'Manager allocation updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'apiName' => 'Allocate Manager to User',
                'status' => false,
                'message' => 'An error occurred while updating manager allocation.',
                'error' => $e->getMessage(),
            ], 500);
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

    /**
     * Get display value for any type (commission, upfront, override) with custom field support.
     * This is a more generic version that handles all type fields.
     * 
     * @param string|null $type The type value from database (e.g., 'per kw', 'per sale', 'custom field', '%')
     * @param int|null $customSalesFieldId The custom sales field ID if set
     * @return string The display value for the type
     */
    private function getTypeDisplayForAudit(?string $type, ?int $customSalesFieldId): string
    {
        // Only look up custom field name if feature is enabled AND field ID is set
        // This prevents unnecessary database queries when feature is disabled
        if ($customSalesFieldId && CustomSalesFieldHelper::isFeatureEnabled()) {
            $customField = Crmcustomfields::find($customSalesFieldId);
            if ($customField) {
                return ' per ' . $customField->name;
            }
        }

        // If type is 'custom field' but we don't have the ID or feature is disabled, show generic
        if ($type === 'custom field') {
            return ' (custom field)';
        }

        // Standard type display - handle both with and without leading space
        $normalizedType = ltrim($type ?? '', ' ');
        
        return match ($normalizedType) {
            'per kw' => ' per kw',
            'per sale' => ' per sale',
            '%' => ' %',
            '' => ' %',
            default => ' ' . $normalizedType,
        };
    }
}
