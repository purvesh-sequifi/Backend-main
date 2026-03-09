<?php

namespace App\Http\Controllers\API\ExternalHiring;

use App\Core\Traits\HubspotTrait;
use App\Core\Traits\JobNimbusTrait;
use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\Department;
use App\Models\EventCalendar;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\OnboardingEmployeeLocations;
use App\Models\OnboardingEmployeeOverride;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingUserRedline;
use App\Models\overrideSystemSetting;
use App\Models\Positions;
use App\Models\State;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

class ExternalEmployeeHiringController extends Controller
{
    use EmailNotificationTrait, HubspotTrait, JobNimbusTrait;

    public function allStateWithOffices(): JsonResponse
    {
        return response()->json([
            'ApiName' => 'all-state-with-offices',
            'status' => true,
            'message' => 'All States With Offices Dropdown!',
            'data' => State::with('office:id,state_id,office_name')->has('office')->get(),
        ]);
    }

    public function commissionsByPositions()
    {
        $data = Positions::with('positionDepartmentDetail', 'Override', 'group', 'payFrequency', 'reconciliation', 'deductionlimit', 'userDeduction')->where('id', '!=', 1)->where('position_name', '!=', 'Super Admin')->orderBy('id')->get();
        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'parent_id' => $data->parent_id,
                'org_parent_id' => $data->org_parent_id,
                'is_manager' => $data->is_manager,
                'position' => $data->position_name ?? null,
                'department_id' => $data->positionDepartmentDetail->id ?? null,
                'Department' => $data->positionDepartmentDetail->name ?? null,
                'override' => $data->reconciliation->override_settlement ?? null,
                'pay_frequency' => $data->payFrequency->frequencyType->name ?? null,
                'order_by' => $data->order_by ?? null,
                'status' => $data->setup_status,
                'group_id' => $data->group->id ?? null,
                'group_name' => $data->group->name ?? null,
                'limit_type' => $data->deductionlimit->limit_type ?? null,
                'limit_amount' => $data->deductionlimit->limit_ammount ?? null,
                'limit' => $data->deductionlimit->limit ?? null,
                'deduction_status' => $data->deductionlimit->status ?? null,
                'user_deduction' => $data->userDeduction,
            ];
        });

        return response()->json([
            'ApiName' => 'commissions-by-positions',
            'status' => true,
            'message' => 'Commissions By Positions List!',
            'data' => $data,
        ]);
    }

    public function overrideSettings(): JsonResponse
    {
        return response()->json([
            'ApiName' => 'override-settings',
            'status' => true,
            'message' => 'Override Settings!',
            'data' => overrideSystemSetting::first() ?? new stdClass,
        ]);
    }

    public function departmentsWisePositions(): JsonResponse
    {
        return response()->json([
            'ApiName' => 'departments-wise-positions',
            'status' => true,
            'message' => 'Departments Wise Positions List!',
            'data' => Department::select('id', 'name')->with('position:id,position_name,department_id')->orderby('id')->get() ?? [],
        ]);
    }

    public function officeWithRedLine(Request $request): JsonResponse
    {
        $validator = validator()->make($request->all(), [
            'office_id' => 'required|int',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        return response()->json([
            'ApiName' => 'office-with-red-line',
            'status' => true,
            'message' => 'Office With Red Line!',
            'data' => Locations::select('id', 'state_id', 'general_code', 'redline_min', 'redline_max', 'redline_standard', 'type', 'date_effective', 'office_name')->with('redline_data')->where(['id' => $request->office_id])->first() ?? new stdClass,
        ]);
    }

    public function officeTeamList(Request $request): JsonResponse
    {
        $validator = validator()->make($request->all(), [
            'office_id' => 'required|int',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        return response()->json([
            'ApiName' => 'office-team-list',
            'status' => true,
            'message' => 'Office Team List!',
            'data' => ManagementTeam::where(['office_id' => $request->office_id])->first() ?? new stdClass,
        ]);
    }

    public function managerListByOfficeEffectiveDate(Request $request): JsonResponse
    {
        $validator = validator()->make($request->all(), [
            'office_id' => 'required|int',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()], 422);
        }

        $additionalUser = AdditionalLocations::where('office_id', $request->input('office_id'))->pluck('user_id');
        $additionalManager = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id')->where('is_manager', 1)->where('dismiss', 0)->whereIn('id', $additionalUser);
        $managers = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id')->where('dismiss', 0)->where('is_manager', 1)->where('office_id', $request->input('office_id'));
        if ($request->manager_id) {
            $additionalManager->where('id', $request->manager_id);
            $managers->where('id', $request->manager_id);
        }
        $additionalManager = $additionalManager->get()->toArray();
        $managers = $managers->get()->toArray();
        $additionalManager = array_merge($additionalManager, $managers);

        return response()->json([
            'ApiName' => 'manager-list-by-office-effective-date',
            'status' => true,
            'message' => 'Manager List By Office!',
            'data' => $additionalManager,
        ]);
    }

    public function departmentsList(): JsonResponse
    {
        return response()->json([
            'ApiName' => 'departments-list',
            'status' => true,
            'message' => 'All Departments!',
            'data' => Department::select('id', 'name')->orderby('id')->get() ?? [],
        ]);
    }

    public function positionsList(): JsonResponse
    {
        return response()->json([
            'ApiName' => 'positions-list',
            'status' => true,
            'message' => 'All Positions!',
            'data' => Positions::select('id', 'position_name', 'department_id')->where('id', '!=', '1')->where('position_name', '!=', 'Super Admin')->orderby('id')->get() ?? [],
        ]);
    }

    public function managerList(Request $request): JsonResponse
    {
        $additionalUser = AdditionalLocations::pluck('user_id');
        $additionalManager = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id')->where('is_manager', 1)->where('dismiss', 0)->whereIn('id', $additionalUser);
        $managers = User::select('id', 'first_name', 'last_name', 'state_id', 'office_id')->where('dismiss', 0)->where('is_manager', 1);
        if ($request->manager_id) {
            $additionalManager->where(['id' => $request->manager_id, 'office_id' => $request->office_id]);
            $managers->where(['id' => $request->manager_id, 'office_id' => $request->office_id]);
        }
        $additionalManager = $additionalManager->union($managers)->get();

        return response()->json([
            'ApiName' => 'manager-list',
            'status' => true,
            'message' => 'All Managers!',
            'data' => $additionalManager,
        ]);
    }

    public function recruiterList(): JsonResponse
    {
        return response()->json([
            'ApiName' => 'recruiter-list',
            'status' => true,
            'message' => 'All Recruiters!',
            'data' => User::select('id', 'first_name', 'last_name', 'email', 'employee_id')->where('dismiss', 0)->get(),
        ]);
    }

    public function createEmployee(Request $request): JsonResponse
    {
        try {
            $validator = validator()->make($request->all(), [
                'first_name' => 'required|string|min:2|max:100',
                'last_name' => 'required|string|min:2|max:100',
                'personal_email' => 'required|email|unique:users,email|unique:onboarding_employees,email',
                'phone_number' => 'required|string|min:10|max:15|unique:users,mobile_no|unique:onboarding_employees,mobile_no',
                'worksite_id' => 'required|exists:locations,work_site_id',
                'department_id' => 'required|int|exists:departments,id',
                'position_id' => 'required|int|exists:positions,id',
                'is_manager' => 'required|in:0,1',
                'manager_id' => 'required_if:is_manager,0',
                'self_gen' => 'required|in:0,1',
                'self_gen_commission' => 'nullable|int|min:0|max:100',
                'recruiter_id' => 'nullable|int|exists:users,id,dismiss,0',

                'setter_commission_value' => 'nullable|int|min:1|max:100',
                'setter_commission_type' => 'nullable|in:percent',
                'setter_redline_type' => 'nullable|string|in:per watt',
                'setter_redline' => 'nullable|min:0|max:999',
                'setter_redline_amount_type' => 'nullable|in:Shift Based on Location,Fixed',
                'setter_upfront_value' => 'nullable|int|min:1',
                'setter_upfront_type' => 'nullable|string|in:per sale,per kw',
                'setter_withheld_value' => 'nullable|int|min:1',
                'setter_withheld_type' => 'nullable|string|in:per sale,per kw',

                'closer_commission_value' => 'nullable|int|min:1|max:100',
                'closer_commission_type' => 'nullable|in:percent',
                'closer_redline_type' => 'nullable|string|in:per watt',
                'closer_redline' => 'nullable|min:0|max:999',
                'closer_redline_amount_type' => 'nullable|in:Shift Based on Location,Fixed',
                'closer_upfront_value' => 'nullable|int|min:1',
                'closer_upfront_type' => 'nullable|string|in:per sale,per kw',
                'closer_withheld_value' => 'nullable|int|min:1',
                'closer_withheld_type' => 'nullable|string|in:per sale,per kw',

                'office_overrides_amount' => 'nullable|int',
                'office_overrides_type' => 'nullable|in:per sale,per kw,percent',
                'office_stack_overrides_amount' => 'nullable|int:min:0|max:100',

                'direct_override_value' => 'required|int',
                'direct_override_type' => 'required|in:per sale,per kw,percent',
                'indirect_override_value' => 'required|int',
                'indirect_override_type' => 'required|in:per sale,per kw,percent',

                'probation_period' => 'nullable|in:30,60',
                'period_of_agreement_start_date' => 'required|date|date_format:Y-m-d',
                'period_of_agreement_end_date' => 'required|date|date_format:Y-m-d|after_or_equal:period_of_agreement_start_date',
                'offer_expire_date' => 'required|date|date_format:Y-m-d|after_or_equal:'.now()->format('Y-m-d'),
                'date_to_be_paid' => 'required|date|date_format:Y-m-d|after_or_equal:'.now()->format('Y-m-d'),
                's_clearance_background_check' => 'required|in:0,1',
                'hiring_bonus_amount' => 'nullable',

                'additional_office' => 'nullable|array',
                'additional_office.*.worksite_id' => 'required|exists:locations,work_site_id',
                'additional_office.*.overrides_amount' => 'required|int',
                'additional_office.*.overrides_type' => 'required|in:per sale,per kw,percent',
                'team_id' => 'nullable|int',
                'is_offer_includes_bonus' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()], 422);
            }

            if ($request->additional_office && is_array($request->additional_office) && count($request->additional_office) != 0) {
                foreach ($request->additional_office as $key => $office) {
                    //                    $rules = [
                    //                        "additional_office.$key.office_id" => "required|int|exists:locations,id,state_id,$office[state_id]"
                    //                    ];
                    //
                    //                    $validator = validator()->make($request->all(), $rules);
                    //
                    //                    if ($validator->fails()) {
                    //                        return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                    //                    }
                    $rules = [
                        "additional_office.$key.overrides_amount" => 'required|int|min:0|max:100',
                    ];

                    $validator = validator()->make($request->all(), $rules);

                    if ($validator->fails()) {
                        return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                    }
                }
            }

            if ($request->office_overrides_type == 'percent') {
                $rules = [
                    'office_overrides_amount' => 'required|int|min:0|max:100',
                ];

                $validator = validator()->make($request->all(), $rules);

                if ($validator->fails()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                }
            }

            if ($request->direct_override_type == 'percent') {
                $rules = [
                    'direct_override_type' => 'required|int|min:0|max:100',
                ];

                $validator = validator()->make($request->all(), $rules);

                if ($validator->fails()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                }
            }

            if ($request->indirect_override_type == 'percent') {
                $rules = [
                    'indirect_override_value' => 'required|int|min:0|max:100',
                ];

                $validator = validator()->make($request->all(), $rules);

                if ($validator->fails()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                }
            }

            $workSite = Locations::where('work_site_id', $request->worksite_id)->first();
            if ($request->manager_id || $request->is_manager == '0') {
                $manager = $this->managerList(new Request(['manager_id' => $request->manager_id, 'office_id' => $workSite->id]));
                $manager = $manager->getOriginalContent();
                if (! isset($manager['data']) || count($manager['data']) == 0) {
                    return response()->json(['status' => false, 'message' => ['manager_id' => ['Selected manager id does not align with the selected work site.']]], 422);
                }
            }

            if ($request->position_id == '1') {
                return response()->json(['status' => false, 'message' => ['position_id' => ['Invalid Position Id.']]], 422);
            }

            $primaryPosition = $request->position_id;
            if ($primaryPosition != 2 && $primaryPosition != 3) {
                $selectedPosition = Positions::where('id', $primaryPosition)->first();
                $primaryPosition = $selectedPosition->parent_id;
            }

            if ($primaryPosition == 2) {
                $validator = validator()->make($request->all(), [
                    'closer_commission_value' => 'required|int|min:1|max:100',
                    'closer_commission_type' => 'required|in:percent',
                    'closer_redline_type' => 'required|string|in:per watt',
                    'closer_redline' => 'required|min:0|max:999',
                    'closer_redline_amount_type' => 'required|in:Shift Based on Location,Fixed',
                    'closer_upfront_value' => 'required|int|min:1',
                    'closer_upfront_type' => 'required|string|in:per sale,per kw',
                    'closer_withheld_value' => 'nullable|int|min:1',
                    'closer_withheld_type' => 'nullable|string|in:per sale,per kw',
                ]);

                if ($validator->fails()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                }
            } elseif ($primaryPosition == 3) {
                $validator = validator()->make($request->all(), [
                    'setter_commission_value' => 'required|int|min:1|max:100',
                    'setter_commission_type' => 'required|in:percent',
                    'setter_redline_type' => 'required|string|in:per watt',
                    'setter_redline' => 'required|min:0|max:999',
                    'setter_redline_amount_type' => 'required|in:Shift Based on Location,Fixed',
                    'setter_upfront_value' => 'required|int|min:1',
                    'setter_upfront_type' => 'required|string|in:per sale,per kw',
                    'setter_withheld_value' => 'nullable|int|min:1',
                    'setter_withheld_type' => 'nullable|string|in:per sale,per kw',
                ]);

                if ($validator->fails()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                }
            }

            if ($request->self_gen == '1') {
                $validator = validator()->make($request->all(), [
                    'closer_commission_value' => 'required|int|min:1|max:100',
                    'closer_commission_type' => 'required|in:percent',
                    'closer_redline_type' => 'required|string|in:per watt',
                    'closer_redline' => 'required|min:0|max:999',
                    'closer_redline_amount_type' => 'required|in:Shift Based on Location,Fixed',
                    'closer_upfront_value' => 'required|int|min:1',
                    'closer_upfront_type' => 'required|string|in:per sale,per kw',
                    'closer_withheld_value' => 'nullable|int|min:1',
                    'closer_withheld_type' => 'nullable|string|in:per sale,per kw',
                    'setter_commission_value' => 'required|int|min:1|max:100',
                    'setter_commission_type' => 'required|in:percent',
                    'setter_redline_type' => 'required|string|in:per watt',
                    'setter_redline' => 'required|min:0|max:999',
                    'setter_redline_amount_type' => 'required|in:Shift Based on Location,Fixed',
                    'setter_upfront_value' => 'required|int|min:1',
                    'setter_upfront_type' => 'required|string|in:per sale,per kw',
                    'setter_withheld_value' => 'nullable|int|min:1',
                    'setter_withheld_type' => 'nullable|string|in:per sale,per kw',
                ]);

                if ($validator->fails()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()], 422);
                }
            }

            DB::beginTransaction();
            $eId = OnboardingEmployees::query()->whereNotNull('employee_id')->orderBy('id', 'Desc')->pluck('employee_id')->first();
            if (empty($eId)) {
                $EmpId = '0000';
            } else {
                $lettersOnly = preg_replace("/\d+$/", '', $eId);
                $substr = str_replace($lettersOnly, '', $eId);
                $EmpId = str_pad(($substr + 1), strlen($substr), '0', STR_PAD_LEFT);
            }
            $employeeId = 'EXT'.$EmpId;

            $selfGenType = null;
            $selfGen = [];
            if ($request->self_gen == '1') {
                if ($primaryPosition == 2) {
                    $selfGenType = 3;
                } elseif ($primaryPosition == 3) {
                    $selfGenType = 2;
                }

                if ($selfGenType == 2) {
                    $selfGen = [
                        'self_gen_commission' => $request->closer_commission_value,
                        'self_gen_redline' => $request->closer_redline,
                        'self_gen_redline_amount_type' => $request->closer_redline_amount_type,
                        'self_gen_redline_type' => $request->closer_redline_type,
                        'self_gen_upfront_amount' => $request->closer_upfront_value,
                        'self_gen_upfront_type' => $request->closer_upfront_type,
                        'self_gen_withheld_amount' => $request->closer_withheld_value,
                        'self_gen_withheld_type' => $request->closer_withheld_type,
                    ];
                } elseif ($selfGenType == 3) {
                    $selfGen = [
                        'self_gen_commission' => $request->setter_commission_value,
                        'self_gen_redline' => $request->setter_redline,
                        'self_gen_redline_amount_type' => $request->setter_redline_amount_type,
                        'self_gen_redline_type' => $request->setter_redline_type,
                        'self_gen_upfront_amount' => $request->setter_upfront_value,
                        'self_gen_upfront_type' => $request->setter_upfront_type,
                        'self_gen_withheld_amount' => $request->setter_withheld_value,
                        'self_gen_withheld_type' => $request->setter_withheld_type,
                    ];
                }
            }

            $position = $request->position_id;
            if ($primaryPosition == 2) {
                $primary = [
                    'commission' => $request->closer_commission_value,
                    'redline' => $request->closer_redline,
                    'redline_amount_type' => $request->closer_redline_amount_type,
                    'redline_type' => $request->closer_redline_type,
                    'upfront_pay_amount' => $request->closer_upfront_value,
                    'upfront_sale_type' => $request->closer_upfront_type,
                    'withheld_amount' => $request->closer_withheld_value,
                    'withheld_type' => $request->closer_withheld_type,
                ];
            } elseif ($primaryPosition == 3) {
                $primary = [
                    'commission' => $request->setter_commission_value,
                    'redline' => $request->setter_redline,
                    'redline_amount_type' => $request->setter_redline_amount_type,
                    'redline_type' => $request->setter_redline_type,
                    'upfront_pay_amount' => $request->setter_upfront_value,
                    'upfront_sale_type' => $request->setter_upfront_type,
                    'withheld_amount' => $request->setter_withheld_value,
                    'withheld_type' => $request->setter_withheld_type,
                ];
            }

            $commissions = array_merge($primary, $selfGen);
            $data = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->personal_email,
                'mobile_no' => $request->phone_number,
                'state_id' => $workSite->state_id,
                'office_id' => $workSite->id,
                'recruiter_id' => $request->recruiter_id,
                'employee_id' => $employeeId,
                'status_id' => 8,
                'hiring_type' => 'Externally',
                'department_id' => $request->department_id,
                'position_id' => $position,
                'sub_position_id' => $request->position_id,
                'is_manager' => $request->is_manager,
                'self_gen_accounts' => $request->self_gen,
                'self_gen_type' => $selfGenType,
                'manager_id' => $request->manager_id,
                'team_id' => $request->team_id,
                'commission_selfgen' => $request->self_gen_commission ?? 0,
                'commission_selfgen_effective_date' => now(),
                'direct_overrides_amount' => $request->direct_override_value,
                'direct_overrides_type' => $request->direct_override_type,
                'indirect_overrides_amount' => $request->indirect_override_value,
                'indirect_overrides_type' => $request->indirect_override_type,
                'office_overrides_amount' => $request->office_overrides_amount,
                'office_overrides_type' => $request->office_overrides_type ?? '',
                'office_stack_overrides_amount' => $request->office_stack_overrides_amount ?? null,
                'probation_period' => $request->probation_period ?? null,
                'hiring_bonus_amount' => $request->hiring_bonus_amount ?? 0,
                'date_to_be_paid' => $request->date_to_be_paid ?? null,
                'period_of_agreement_start_date' => $request->period_of_agreement_start_date ?? null,
                'end_date' => $request->period_of_agreement_end_date ?? null,
                'offer_include_bonus' => $request->is_offer_includes_bonus ?? null,
                'offer_expiry_date' => $request->offer_expire_date ?? null,
                'is_background_verificaton' => $request->s_clearance_background_check ?? null,
            ];
            $final = array_merge($data, $commissions);

            $data = OnboardingEmployees::create($final);

            EventCalendar::create([
                'event_date' => $request->period_of_agreement_start_date,
                'type' => 'External Onboarding',
                'state_id' => $workSite->state_id,
                'user_id' => $data->id,
                'event_name' => 'Joining',
                'description' => null,
            ]);

            if ($request->additional_office && is_array($request->additional_office) && count($request->additional_office) != 0) {
                foreach ($request->additional_office as $additionalOffice) {
                    $workSite = Locations::where('work_site_id', $additionalOffice['worksite_id'])->first();
                    OnboardingEmployeeLocations::create([
                        'user_id' => $data->id,
                        'state_id' => $workSite->state_id,
                        'office_id' => $workSite->id,
                        //                        'state_id' => $additionalOffice['state_id'],
                        //                        'office_id' => $additionalOffice['office_id'] ?? null,
                        'overrides_amount' => $additionalOffice['overrides_amount'] ?? 0,
                        'overrides_type' => $additionalOffice['overrides_type'] ?? null,
                    ]);
                }
            }

            OnboardingEmployeeOverride::create([
                'user_id' => $data->id,
                'updater_id' => null,
                'override_effective_date' => now(),
                'direct_overrides_amount' => $request->direct_override_value,
                'direct_overrides_type' => $request->direct_overrides_type,
                'indirect_overrides_amount' => $request->indirect_override_value,
                'indirect_overrides_type' => $request->indirect_override_type,
                'office_overrides_amount' => $request->office_overrides_amount,
                'office_overrides_type' => $request->office_overrides_type,
                'office_stack_overrides_amount' => $request->office_stack_overrides_amount ?? null,
            ]);

            if ($primaryPosition == 2) {
                OnboardingUserRedline::create([
                    'user_id' => $data->id,
                    'updater_id' => null,
                    'commission_type' => $request->closer_commission_type,
                    'position_id' => $primaryPosition,
                    'redline' => $request->closer_redline,
                    'redline_type' => $request->closer_redline_type,
                    'redline_amount_type' => $request->closer_redline_amount_type,
                    'start_date' => now(),
                    'commission' => $request->closer_commission_value,
                    'commission_effective_date' => now(),
                    'upfront_pay_amount' => $request->closer_upfront_value,
                    'upfront_sale_type' => $request->closer_upfront_type,
                    'upfront_effective_date' => now(),
                    'withheld_amount' => $request->closer_withheld_value,
                    'withheld_type' => $request->closer_withheld_type,
                    'withheld_effective_date' => now(),
                ]);
            } elseif ($primaryPosition == 3) {
                OnboardingUserRedline::create([
                    'user_id' => $data->id,
                    'updater_id' => null,
                    'commission_type' => $request->setter_commission_type,
                    'position_id' => $primaryPosition,
                    'redline' => $request->setter_redline,
                    'redline_type' => $request->setter_redline_type,
                    'redline_amount_type' => $request->setter_redline_amount_type,
                    'start_date' => now(),
                    'commission' => $request->setter_commission_value,
                    'commission_effective_date' => now(),
                    'upfront_pay_amount' => $request->setter_upfront_value,
                    'upfront_sale_type' => $request->setter_upfront_type,
                    'upfront_effective_date' => now(),
                    'withheld_amount' => $request->setter_withheld_value,
                    'withheld_type' => $request->setter_withheld_type,
                    'withheld_effective_date' => now(),
                ]);
            }

            if ($request->self_gen == '1') {
                if ($selfGenType == 2) {
                    OnboardingUserRedline::create([
                        'user_id' => $data->id,
                        'updater_id' => null,
                        'commission_type' => $request->setter_commission_type,
                        'position_id' => $selfGenType,
                        'redline' => $request->closer_redline,
                        'redline_type' => $request->closer_redline_type,
                        'redline_amount_type' => $request->closer_redline_amount_type,
                        'start_date' => now(),
                        'commission' => $request->closer_commission_value,
                        'commission_effective_date' => now(),
                        'upfront_pay_amount' => $request->closer_upfront_value,
                        'upfront_sale_type' => $request->closer_upfront_type,
                        'upfront_effective_date' => now(),
                        'withheld_amount' => $request->closer_withheld_value,
                        'withheld_type' => $request->closer_withheld_type,
                        'withheld_effective_date' => now(),
                    ]);
                } elseif ($selfGenType == 3) {
                    OnboardingUserRedline::create([
                        'user_id' => $data->id,
                        'updater_id' => null,
                        'commission_type' => $request->setter_commission_type,
                        'position_id' => $selfGenType,
                        'redline' => $request->setter_redline,
                        'redline_type' => $request->setter_redline_type,
                        'redline_amount_type' => $request->setter_redline_amount_type,
                        'start_date' => now(),
                        'commission' => $request->setter_commission_value,
                        'commission_effective_date' => now(),
                        'upfront_pay_amount' => $request->setter_upfront_value,
                        'upfront_sale_type' => $request->setter_upfront_type,
                        'upfront_effective_date' => now(),
                        'withheld_amount' => $request->setter_withheld_value,
                        'withheld_type' => $request->setter_withheld_type,
                        'withheld_effective_date' => now(),
                    ]);
                }
            }

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                // No Need To Update Jobnimbus & Hubspot Data
            } else {
                $jobNimbusCrmData = Crms::whereHas('crmSetting')->with('crmSetting')->where('id', 4)->where('status', 1)->first();
                if (! empty($jobNimbusCrmData)) {
                    $jobNimbusCrmSetting = json_decode($jobNimbusCrmData->crmSetting->value);
                    $jobNimbusToken = $jobNimbusCrmSetting->api_key;
                    $postDataToJobNimbus = [
                        'display_name' => $data['first_name'].' '.$data['last_name'],
                        'email' => $data['email'],
                        'home_phone' => $data['mobile_no'],
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'record_type_name' => 'Subcontractor',
                        'status_name' => 'Solar Reps',
                        'external_id' => $data['employee_id'],
                    ];
                    $responseJobNimbusContacts = $this->storeJobNimbuscontats($postDataToJobNimbus, $jobNimbusToken);
                    if ($responseJobNimbusContacts['status'] === true) {
                        User::where('id', $data->id)->update([
                            'jobnimbus_jnid' => $responseJobNimbusContacts['data']['jnid'],
                            'jobnimbus_number' => $responseJobNimbusContacts['data']['number'],
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'create-employee',
                'status' => true,
                'message' => 'Employee Created Successfully!!',
                'data' => [],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'create-employee',
                'status' => false,
                'message' => 'Something happen while processing your request!!',
                'data' => [],
            ]);
        }
    }
}
