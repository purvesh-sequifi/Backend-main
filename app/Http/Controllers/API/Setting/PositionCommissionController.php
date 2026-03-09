<?php

namespace App\Http\Controllers\API\Setting;

use App\Core\Traits\CheckCompanySettingTrait;
use App\Helpers\CustomSalesFieldHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompensationPlanValidatedRequest;
use App\Http\Requests\PositionValidatedRequest;
use App\Models\CompanyProfile;
use App\Models\CostCenter;
use Laravel\Pennant\Feature;
use App\Models\Department;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\Payroll;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionHirePermission;
use App\Models\PositionOverride;
use App\Models\PositionOverrideSettlement;
use App\Models\PositionPayFrequency;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionsDeductionLimit;
use App\Models\PositionTierOverride;
use App\Models\PositionWage;
use App\Models\User;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use App\Models\UserReconciliationWithholding;
use App\Models\Wage;
use App\Services\PositionCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class PositionCommissionController extends Controller
{
    private $compensationPlan;

    private $incompleteAccountAlert;

    private $marketingDealAlert;

    use CheckCompanySettingTrait;

    public function __construct(Positions $position)
    {
        $this->Position = $position;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function position_relation_data($relation_data)
    {
        $relation_data->group;
        $relation_data->payFrequency;
        $relation_data->positionDepartmentDetail;
        $relation_data->userDeduction;
        $relation_data->deductionlimit;
        $relation_data->reconciliation;

        return $relation_data;
    }

    public function add_chiled_data_into_array($data, $Chield_Position_data)
    {
        foreach ($Chield_Position_data as $Chield_Position_row) {
            $Chield_Position_row = $this->position_relation_data($Chield_Position_row);
            $data[] = $Chield_Position_row;
            $sub_Chield_Position_data = $Chield_Position_row->ChieldPosition;
            if (! empty($sub_Chield_Position_data) && count($sub_Chield_Position_data) > 0) {
                return $data = $this->add_chiled_data_into_array($data, $sub_Chield_Position_data);
            }
        }

        return $data;
    }

    // New index function as per requirment. SEQ-3654
    public function index(Request $request): JsonResponse
    {
        $possition_ids = [1];
        if (! in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
            $possition_ids = [1, 2, 3];
        }

        $search = $request->input('search_filter');
        $payFrequency = $request->input('pay_frequency_filter');
        $department = $request->input('department');
        $overrideSettlement = $request->input('override_settelement');
        $permissionGroup = $request->input('permission_group');
        $per_page = isset($request->per_page) && $request->per_page > 0 ? $request->per_page : 50;

        $position_with_chiled_position = Positions::query()->with('group', 'payFrequency.frequencyType', 'positionDepartmentDetail', 'userDeduction', 'deductionlimit', 'reconciliation', 'offerLetter')
            ->when(isset($search), function ($q) {
                $q->where('position_name', 'like', '%'.\request()->input('search_filter').'%');
            })->when(isset($payFrequency), function ($q) {
                $q->whereHas('payFrequency.frequencyType', function ($q) {
                    $q->where('id', \request()->input('pay_frequency_filter'));
                });
            })->when(isset($department), function ($q) {
                $q->whereHas('positionDepartmentDetail', function ($q) {
                    $q->where('id', \request()->input('department'));
                });
            })->when(isset($overrideSettlement), function ($q) {
                $q->whereHas('reconciliation', function ($q) {
                    $q->where('override_settlement', \request()->input('override_settelement'));
                });
            })->when(isset($permissionGroup), function ($q) {
                $q->whereHas('group', function ($q) {
                    $q->where('id', \request()->input('permission_group'));
                });
            })->withcount('peoples')->with('childPositions', function ($q) use ($search, $payFrequency, $department, $overrideSettlement, $permissionGroup) {
                $q->when(isset($search), function ($q) {
                    $q->where('position_name', 'like', '%'.\request()->input('search_filter').'%');
                })->when(isset($payFrequency), function ($q) {
                    $q->whereHas('payFrequency.frequencyType', function ($q) {
                        $q->where('id', \request()->input('pay_frequency_filter'));
                    });
                })->when(isset($department), function ($q) {
                    $q->whereHas('positionDepartmentDetail', function ($q) {
                        $q->where('id', \request()->input('department'));
                    });
                })->when(isset($overrideSettlement), function ($q) {
                    $q->whereHas('reconciliation', function ($q) {
                        $q->where('override_settlement', \request()->input('override_settelement'));
                    });
                })->when(isset($permissionGroup), function ($q) {
                    $q->whereHas('group', function ($q) {
                        $q->where('id', \request()->input('permission_group'));
                    });
                });

            })->where('position_name', '!=', 'Super Admin')->whereNotIn('id', $possition_ids)->paginate($per_page);
        // })->where('id', '!=', 1)->paginate($per_page);

        $positions = $position_with_chiled_position->getCollection();
        $response_data = [];
        foreach ($positions as $position) {
            $datas = $this->recursionPosition($position);
            if (is_array($datas) && count($datas) != 0) {
                foreach ($datas as $data) {
                    $check = collect($response_data)->where('id', $data['id'])->values();
                    if (count($check) == 0) {
                        $response_data[] = $data;
                    }
                }
            } else {
                $check = collect($response_data)->where('id', $data['id'])->values();
                if (count($check) == 0) {
                    $response_data[] = $datas;
                }
            }
        }

        $data = $position_with_chiled_position->toArray();
        $data['data'] = $response_data;

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    protected function recursionPosition($position, &$data = [])
    {
        if (count($position->childPositions) != 0) {
            $data[] = $this->positionFormatting($position);
            foreach ($position->childPositions as $child) {
                $this->recursionPosition($child, $data);
            }
        } else {
            $data[] = $this->positionFormatting($position);
        }

        return $data;
    }

    protected function positionFormatting($position): array
    {
        $position_offer_letter = NewSequiDocsTemplatePermission::with('NewSequiDocsTemplate')->where(['position_id' => $position->id, 'position_type' => 'receipient', 'category_id' => 1])->get()->toArray();
        $arrayData = [
            'id' => $position->id,
            'parent_id' => $position->parent_id,
            'org_parent_id' => $position->org_parent_id,
            'is_manager' => $position->is_manager,
            'status' => $position->setup_status,
            'position' => isset($position->position_name) ? $position->position_name : null,
            'worker_type' => isset($position->worker_type) ? $position->worker_type : null,
            'people' => $position->peoples_count,
            'order_by' => isset($position->order_by) ? $position->order_by : null,
            'group_id' => isset($position->group->id) ? $position->group->id : null,
            'group_name' => isset($position->group->name) ? $position->group->name : null,
            'frequency_type_id' => isset($position->payFrequency->frequencyType->id) ? $position->payFrequency->frequencyType->id : null,
            'pay_frequency' => isset($position->payFrequency->frequencyType->name) ? $position->payFrequency->frequencyType->name : null,
            'department_id' => isset($position->positionDepartmentDetail->id) ? $position->positionDepartmentDetail->id : null,
            'Department' => isset($position->positionDepartmentDetail->name) ? $position->positionDepartmentDetail->name : null,
            'user_deduction' => $position->userDeduction,
            'limit_type' => isset($position->deductionLimit->limit_type) ? $position->deductionLimit->limit_type : null,
            'limit_ammount' => isset($position->deductionLimit->limit_ammount) ? $position->deductionLimit->limit_ammount : null,
            'limit' => isset($position->deductionLimit->limit) ? $position->deductionLimit->limit : null,
            'deduction_status' => isset($position->deductionLimit->status) ? $position->deductionLimit->status : null,
            'override' => isset($position->reconciliation->override_settlement) ? $position->reconciliation->override_settlement : null,
            'offer_letter_template_id' => isset($position_offer_letter[0]['template_id']) ? $position_offer_letter[0]['template_id'] : null,
            'offer_letter_name' => isset($position_offer_letter[0]['new_sequi_docs_template']['template_name']) ? $position_offer_letter[0]['new_sequi_docs_template']['template_name'] : null,
        ];
        if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {
            $arrayData['offer_letters'] = isset($position->allAssociatedOfferLettersWithTemplate) ? $position->allAssociatedOfferLettersWithTemplate : [];
        }

        return $arrayData;
    }

    // old index function for backup
    public function index_old_13_12_2023(Request $request)
    {
        // $checkSetting = $this->checkSetting();
        // dd($checkSetting);die;
        $search = $request->input('search_filter');
        $payFrequency = $request->input('pay_frequency_filter');
        $department = $request->input('department');
        $overrideSettelement = $request->input('override_settelement');
        $permissionGroup = $request->input('permission_group');

        $result = Positions::with('positionDepartmentDetail', 'Override', 'people', 'group', 'payFrequency', 'reconciliation', 'deductionlimit', 'userDeduction')->where('id', '!=', 1);

        if ($search && $search != '') {
            $result->where(function ($query) use ($search) {
                return $query->where('position_name', 'LIKE', '%'.$search.'%');
            });
        }
        if ($payFrequency && $payFrequency != '') {
            $result->whereHas(
                'payFrequency', function ($query) use ($payFrequency) {
                    return $query->where('frequency_type_id', $payFrequency);
                });
        }
        if ($department && $department != '') {
            $result->Where(function ($query) use ($department) {
                return $query->where('department_id', $department);
            });
        }
        if ($overrideSettelement && $overrideSettelement != '') {
            $result->whereHas(
                'reconciliation', function ($query) use ($overrideSettelement) {
                    return $query->where('override_settlement', $overrideSettelement);
                });
        }
        if ($permissionGroup && $permissionGroup != '') {
            $result->Where(function ($query) use ($permissionGroup) {
                return $query->where('group_id', $permissionGroup);
            });
        }
        // return  $data = $result->orderBy('id','asc')->get();

        // $data = $result->orderBy('id','asc')->paginate(env('PAGINATE'));
        $data = $result->orderBy('id', 'asc')->paginate(50);
        $data->transform(function ($data) {

            $position = User::where('position_id', $data->id)->where('dismiss', 0)->count();
            $subPosition = User::where('sub_position_id', $data->id)->where('dismiss', 0)->count();

            return [
                'id' => $data->id,
                'parent_id' => $data->parent_id,
                'org_parent_id' => $data->org_parent_id,
                'is_manager' => $data->is_manager,
                'position' => isset($data->position_name) ? $data->position_name : null,
                'department_id' => isset($data->positionDepartmentDetail->id) ? $data->positionDepartmentDetail->id : null,
                'Department' => isset($data->positionDepartmentDetail->name) ? $data->positionDepartmentDetail->name : null,
                'people' => isset($subPosition) && $subPosition != 0 ? $subPosition : 0,
                // 'override' => isset($data->override[0]->overridessattlement->sattlement_type) ? $data->override[0]->overridessattlement->sattlement_type : NULL,
                'override' => isset($data->reconciliation->override_settlement) ? $data->reconciliation->override_settlement : null,
                'pay_frequency' => isset($data->payFrequency->frequencyType->name) ? $data->payFrequency->frequencyType->name : null,
                'order_by' => isset($data->order_by) ? $data->order_by : null,
                'status' => $data->setup_status,
                'group_id' => isset($data->group->id) ? $data->group->id : null,
                'group_name' => isset($data->group->name) ? $data->group->name : null,
                'limit_type' => isset($data->deductionlimit->limit_type) ? $data->deductionlimit->limit_type : null,
                'limit_ammount' => isset($data->deductionlimit->limit_ammount) ? $data->deductionlimit->limit_ammount : null,
                'limit' => isset($data->deductionlimit->limit) ? $data->deductionlimit->limit : null,
                'deduction_status' => isset($data->deductionlimit->status) ? $data->deductionlimit->status : null,
                'user_deduction' => $data->userDeduction,
            ];
        });

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function getallpositioncommission(Request $request)
    {
        // optional param employeeId
        $search = $request->input('search_filter');
        $payFrequency = $request->input('pay_frequency_filter');
        $department = $request->input('department');
        $overrideSettelement = $request->input('override_settelement');
        $permissionGroup = $request->input('permission_group');
        $employeeId = $request->query('employeeId');

        if ($employeeId) {
            $userByEmployeeId = User::find($employeeId);
            if (! $userByEmployeeId) {
                abort(404, "User with ID {$employeeId} not found.");
            }
        }

        $result = Positions::with('positionDepartmentDetail', 'Override', 'people', 'group', 'payFrequency', 'reconciliation', 'deductionlimit', 'userDeduction')
            ->where('id', '!=', 1)->where('position_name', '!=', 'Super Admin');

        if (isset($userByEmployeeId)) {
            $result->where(function ($query) use ($userByEmployeeId) {
                return $query->where('worker_type', $userByEmployeeId->worker_type);
            });
        }

        if ($search && $search != '') {
            $result->where(function ($query) use ($search) {
                return $query->where('position_name', 'LIKE', '%'.$search.'%');
            });
        }
        if ($payFrequency && $payFrequency != '') {
            $result->whereHas(
                'payFrequency', function ($query) use ($payFrequency) {
                    return $query->where('frequency_type_id', $payFrequency);
                });
        }
        if ($department && $department != '') {
            $result->Where(function ($query) use ($department) {
                return $query->where('department_id', $department);
            });
        }
        if ($overrideSettelement && $overrideSettelement != '') {
            $result->whereHas(
                'reconciliation', function ($query) use ($overrideSettelement) {
                    return $query->where('override_settlement', $overrideSettelement);
                });
        }
        if ($permissionGroup && $permissionGroup != '') {
            $result->Where(function ($query) use ($permissionGroup) {
                return $query->where('group_id', $permissionGroup);
            });
        }

        $data = $result->orderBy('id', 'asc')->get(); // ->paginate(50);
        $data->transform(function ($data) {

            $position = User::where('position_id', $data->id)->where('dismiss', 0)->count();
            $subPosition = User::where('sub_position_id', $data->id)->where('dismiss', 0)->count();

            return [
                'id' => $data->id,
                'parent_id' => $data->parent_id,
                'org_parent_id' => $data->org_parent_id,
                'is_manager' => $data->is_manager,
                'position' => isset($data->position_name) ? $data->position_name : null,
                'department_id' => isset($data->positionDepartmentDetail->id) ? $data->positionDepartmentDetail->id : null,
                'Department' => isset($data->positionDepartmentDetail->name) ? $data->positionDepartmentDetail->name : null,
                'people' => isset($subPosition) && $subPosition != 0 ? $subPosition : 0,
                // 'override' => isset($data->override[0]->overridessattlement->sattlement_type) ? $data->override[0]->overridessattlement->sattlement_type : NULL,
                'override' => isset($data->reconciliation->override_settlement) ? $data->reconciliation->override_settlement : null,
                'pay_frequency' => isset($data->payFrequency->frequencyType->name) ? $data->payFrequency->frequencyType->name : null,
                'order_by' => isset($data->order_by) ? $data->order_by : null,
                'status' => $data->setup_status,
                'group_id' => isset($data->group->id) ? $data->group->id : null,
                'group_name' => isset($data->group->name) ? $data->group->name : null,
                'limit_type' => isset($data->deductionlimit->limit_type) ? $data->deductionlimit->limit_type : null,
                'limit_ammount' => isset($data->deductionlimit->limit_ammount) ? $data->deductionlimit->limit_ammount : null,
                'limit' => isset($data->deductionlimit->limit) ? $data->deductionlimit->limit : null,
                'deduction_status' => isset($data->deductionlimit->status) ? $data->deductionlimit->status : null,
                'user_deduction' => $data->userDeduction,
                //  'worker_type' =>  $data->worker_type,
            ];
        });

        return response()->json([
            'ApiName' => 'list-position',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function orgChart(): JsonResponse
    {
        $excludePosition = [1];
        if (! in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
            $excludePosition = [1, 2, 3];
        }

        $positionsOrgChart = Positions::with('orgChields')->where('id', '!=', 1)->where('position_name', '!=', 'Super Admin')->whereNotIn('id', $excludePosition)
            ->withcount('peoples')->where('org_parent_id', null)->orderBy('id', 'DESC')->get();

        return response()->json(['status' => true, 'message' => 'Successfully.', 'positionsOrgChart' => $positionsOrgChart], 200);
    }

    public function checkReconciliationSetting(Request $request)
    {
        return app(\App\Http\Controllers\API\V1\ReconController::class)->reconPositionStatus($request);
        $PositionReconciliations = UserReconciliationWithholding::where('status', 'unpaid')->first();
        if ($PositionReconciliations) {
            $status = 1;
        } else {
            $status = 0;
        }

        return response()->json(['status' => true, 'message' => 'Successfully.', 'checkStatus' => $status], 200);

    }

    public function Addposition(PositionValidatedRequest $request): JsonResponse
    {
        // dd('ee');
        if (! $request->all()) {
            return response()->json([
                'ApiName' => 'Add-position',
                'status' => false,
                'message' => 'Bad Request!',
                'data' => null,
            ], 400);
        }

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $request['parent_position'] = 2;
        }

        $data = Positions::create([
            'position_name' => $request['position_name'],
            'department_id' => $request['department_id'],
            'group_id' => $request['group_id'],
            'parent_id' => $request['parent_position'],
            'is_manager' => $request['is_manager'],
            'org_parent_id' => $request['org_parent_position'],
            // insert w2 or 1099
            'worker_type' => $request['worker_type'],
            // 'offer_letter_template_id' => @$request['offer_letter_template_id']
        ]);

        $position_id = $data->id;

        $updateData = Positions::where('id', $position_id)->first();
        $updateData->order_by = $position_id;
        $updateData->save();

        // insert wage
        Wage::create([
            'position_id' => $position_id,
        ]);

        // insert pay frequency
        PositionPayFrequency::create([
            'position_id' => $position_id,
            'frequency_type_id' => $request['frequency_type_id'],
        ]);

        if (isset($request['offer_letter_template_id']) && ! empty($request['offer_letter_template_id'])) {
            NewSequiDocsTemplatePermission::Create(
                [
                    'template_id' => $request['offer_letter_template_id'],
                    'category_id' => 1,
                    'position_id' => $data->id,
                    'position_type' => 'receipient',
                ]
            );
        }

        return response()->json([
            'ApiName' => 'add-position',
            'status' => true,
            'message' => 'add Successfully.',
            'data' => $data,
        ]);
    }

    public function Updateposition(Request $request, $id)
    {
        if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {

            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'position_name' => 'required',
                'department_id' => 'required',
                'offer_letter_template_id' => 'nullable|array|min:1',
            ]);

        } else {

            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'position_name' => 'required',
                'department_id' => 'required',
            ]);

        }

        // If validation fails, return error response
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Find the position by ID
        $position = Positions::find($id);

        // If position not found, return a 404 error
        if (! $position) {
            return response()->json([
                'ApiName' => 'update-position',
                'status' => false,
                'message' => 'Position not found.',
                'data' => null,
            ], 404);
        }

        // Check for potential hierarchy loop if org_parent_position is provided
        if ($request['org_parent_position']) {
            $subPosition = Positions::find($request['org_parent_position']);
            $ancestorIds = $subPosition->getAncestorIds();
            if (in_array($position->id, $ancestorIds)) {
                return response()->json([
                    'ApiName' => 'update-position',
                    'status' => false,
                    'message' => 'Setting '.$subPosition->position_name.' as a parent would create a hierarchy loop for '.$position->position_name.'.',
                    'data' => null,
                ], 400);
            }
        }

        // Check company profile and apply domain-specific logic
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $request['parent_position'] = 2;
        }

        // Update position details
        $position->position_name = $request['position_name'];
        $position->department_id = $request['department_id'];
        $position->is_manager = $request['is_manager'];
        $position->group_id = $request['group_id'];
        $position->parent_id = $request['parent_position'];
        $position->org_parent_id = $request['org_parent_position'];
        $position->worker_type = $request['worker_type'];
        // $position->offer_letter_template_id = @$request['offer_letter_template_id'];
        $position->save();

        // Handle offer letter template association
        if (isset($request['offer_letter_template_id']) && ! empty($request['offer_letter_template_id'])) {
            if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {

                NewSequiDocsTemplatePermission::where([
                    'position_id' => $id,
                    'position_type' => 'receipient',
                    'category_id' => 1,
                ])->delete();

                foreach ($request['offer_letter_template_id'] as $template_id) {

                    // dd($template_id);

                    NewSequiDocsTemplatePermission::create([
                        'template_id' => $template_id,
                        'category_id' => 1,
                        'position_id' => $id,
                        'position_type' => 'receipient',
                    ]);

                }

            } else {

                $new_sequi_docs_template_permissions = NewSequiDocsTemplatePermission::where(['template_id' => $request['offer_letter_template_id'], 'position_id' => $id, 'position_type' => 'receipient', 'category_id' => 1])->first();
                if (empty($new_sequi_docs_template_permissions)) {
                    NewSequiDocsTemplatePermission::where(['position_id' => $id, 'position_type' => 'receipient', 'category_id' => 1])->delete();
                    NewSequiDocsTemplatePermission::create([
                        'template_id' => $request['offer_letter_template_id'],
                        'category_id' => 1,
                        'position_id' => $id,
                        'position_type' => 'receipient',
                    ]);
                }

            }
        }

        // Update department_id for users with this sub_position_id
        User::where('sub_position_id', $id)->update(['department_id' => $position->department_id]);

        // update pay frequency
        PositionPayFrequency::where('position_id', $id)->update([
            'frequency_type_id' => $request['frequency_type_id'],
        ]);

        return response()->json([
            'ApiName' => 'update-position',
            'status' => true,
            'message' => 'Update Successfully.',
            'data' => $position,
        ]);
    }

    public function Updategroup(Request $request, $id)
    {
        // Check if the request is not empty
        if (! empty($request->all())) {
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'group_id' => 'required',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Find the position by ID
            $position = Positions::find($id);

            // Check if the position was found
            if ($position) {
                // Store the current group_id for comparison
                $position_group_id = $position->group_id;

                // Update the position's group_id
                $position->group_id = $request['group_id'];
                $position->save();

                // Update the group_id for users with the same sub_position_id
                User::where('sub_position_id', $id)->update(['group_id' => $request['group_id']]);

                // Clear position cache after group update
                PositionCacheService::clear();

                // Return success response
                return response()->json([
                    'ApiName' => 'update-position',
                    'status' => true,
                    'message' => 'Update Successfully.',
                    'data' => $position,
                ], 200);
            } else {
                // Return error response if position is not found
                return response()->json([
                    'ApiName' => 'update-position',
                    'status' => false,
                    'message' => 'Invalid Position ID.',
                    'data' => null,
                ], 400);
            }
        } else {
            // Return error response if the request is empty
            return response()->json([
                'ApiName' => 'update-position',
                'status' => false,
                'message' => 'Request data is empty.',
                'data' => null,
            ], 400);
        }
    }

    public function update_position_hierarchy(Request $request): JsonResponse
    {

        if (! null == $request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [
                    'position_ids' => 'required',
                    // 'order_by' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 200);
            }
            // $position_id = $request->position_id;
            // $order_by = $request->order_by;
            // $update_position_id = $request->update_position_id;
            // $update_order_by = $request->update_order_by;

            // $position = Positions::find($position_id);
            // $position->order_by = $update_order_by;
            // $position->save();

            // $position2 = Positions::find($update_position_id);
            // $position2->order_by = $order_by;
            // $position2->save();

            $aa = $request->position_ids;
            foreach ($aa as $key => $val) {
                // $UpdateData = Positions::where('id', $val)->first();
                $UpdateData = Positions::find($val);
                $UpdateData->order_by = $key + 1;
                $UpdateData->save();
            }

            return response()->json([
                'ApiName' => 'update-position-hierarchy',
                'status' => true,
                'message' => 'update Successfully.',
                // 'data' => $position,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'update-position-hierarchy',
                'status' => false,
                'message' => '',
                // 'data' => null,
            ], 400);
        }
    }

    public function positionsStatus($id): JsonResponse
    {
        $data = Positions::with('Upfront', 'Override', 'reconciliation')->where('id', $id)->first();
        $deduction = PositionCommissionDeductionSetting::first();
        $newData = [];
        $newData['position_id'] = isset($data->id) ? $data->id : null;
        $newData['position_name'] = isset($data->position_name) ? $data->position_name : null;
        $newData['upfront_status'] = data_get($data, 'Upfront.0.status_id');
        $newData['deduction_status'] = isset($deduction->status) ? $deduction->status : null;
        if ($data) {
            foreach ($data->override as $val) {
                $journalName = strtolower(str_replace(' ', '_', $val->overridesDetail->overrides_type));

                $newData[$journalName.'_status'] = isset($val->status) ? $val->status : null;
            }
        }
        $newData['reconciliation_status'] = isset($data->reconciliation->status) ? $data->reconciliation->status : null;

        return response()->json([
            'ApiName' => 'status api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $newData,
        ], 200);
    }

    public function store_old(CompensationPlanValidatedRequest $request): JsonResponse
    {
        $data = PositionCommission::where('position_id', $request['position_id'])->first();

        if ($data == null) {
            $position = Positions::find($request['position_id']);
            if ($position != null) {
                PositionCommission::create([
                    'position_id' => $request['position_id'],
                    'commission_parentage' => $request['commission_parentage'],
                    'commission_parentag_hiring_locked' => $request['commission_parentag_hiring_locked'],
                    'commission_structure_type' => $request['commission_structure_type'],
                    'commission_parentag_type_hiring_locked' => $request['commission_parentag_type_hiring_locked'],
                ]);
            } else {
                return response()->json(['status' => false, 'message' => 'Position id not Correct!'], 400);
            }
        }

        if ($request->upfront_status == 1) {
            $data1 = PositionCommissionUpfronts::where('position_id', $request['position_id'])->first();
            // dd($data1);
            if ($data1 == null) {
                // Parse custom field type from 'custom_field_X' format
                $calculatedBy = $request['calculated_by'];
                $upfrontCustomFieldId = $request['upfront_custom_sales_field_id'] ?? null;
                
                if ($calculatedBy && str_starts_with($calculatedBy, 'custom_field_')) {
                    $upfrontCustomFieldId = (int) str_replace('custom_field_', '', $calculatedBy);
                    $calculatedBy = 'custom field';
                }

                PositionCommissionUpfronts::create([
                    'position_id' => $request->position_id,
                    'status_id' => $request['upfront_status'],
                    'upfront_ammount' => $request['upfront_ammount'],
                    'upfront_ammount_locked' => $request['upfront_ammount_locked'],
                    'calculated_by' => $calculatedBy,
                    'custom_sales_field_id' => $upfrontCustomFieldId,
                    'calculated_locked' => $request['calculated_locked'],
                    'upfront_system' => $request['upfront_system'],
                    'upfront_system_locked' => $request['upfront_system_locked'],
                    'upfront_limit' => $request['upfront_limit'],
                    'upfront_limit_type' => $request['upfront_limit_type'] ?? null,

                ]);
            }
            $data2 = PositionCommissionDeduction::where('position_id', $request->position_id)->first();
            if ($data2 == null) {
                if ($request->deduction) {

                    $this->positionCommissionDeductionSetting = PositionCommissionDeductionSetting::find(1);
                    $this->positionCommissionDeductionSetting->status = $request->deduction_status;
                    $this->positionCommissionDeductionSetting->deducation_locked = $request->deduction_locked;
                    $this->positionCommissionDeductionSetting->update();

                }
                foreach ($request->deduction as $data1) {
                    if ($request->upfront_status == 1) {
                        PositionCommissionDeduction::create([
                            'deduction_setting_id' => 1,
                            'position_id' => $request->position_id,
                            'cost_center_id' => $data1['cost_center_id'],
                            'deduction_type' => $data1['deduction_type'],
                            'ammount_par_paycheck' => $data1['ammount_par_paycheck'],
                        ]);
                    } else {
                        return response()->json([
                            'ApiName' => 'Commission duducation',
                            'status' => false,
                            'message' => '',
                            'data' => null,
                        ], 400);
                    }
                }
            }
            $data4 = PositionsDeductionLimit::where('position_id', $request->position_id)->first();
            // dd($data4);
            if ($data4 == null) {
                PositionsDeductionLimit::create([
                    'deduction_setting_id' => 1,
                    'position_id' => $request->position_id,
                    'limit_type' => $request['limit_type'],
                    'limit_ammount' => $request['limit_ammount'],
                    'limit' => $request['limit'],
                ]);
            }
            $data5 = PositionOverride::where('position_id', $request->position_id)->first();
            // dd($data5);
            if ($data5 == null) {
                foreach ($request->override as $override) {
                    // Parse custom field type from 'custom_field_X' format for override
                    $overrideType = $override['type'];
                    $overrideCustomFieldId = null;

                    if ($overrideType && str_starts_with($overrideType, 'custom_field_')) {
                        $overrideCustomFieldId = (int) str_replace('custom_field_', '', $overrideType);
                        $overrideType = 'custom field';
                    }

                    // Determine which custom field column to use based on override_id
                    $directCustomFieldId = null;
                    $indirectCustomFieldId = null;
                    $officeCustomFieldId = null;

                    if ($overrideCustomFieldId) {
                        switch ($override['override_id']) {
                            case '1':
                            case 1:
                                $directCustomFieldId = $overrideCustomFieldId;
                                break;
                            case '2':
                            case 2:
                                $indirectCustomFieldId = $overrideCustomFieldId;
                                break;
                            case '3':
                            case 3:
                                $officeCustomFieldId = $overrideCustomFieldId;
                                break;
                        }
                    }

                    PositionOverride::create([
                        'position_id' => $request['position_id'],
                        'override_id' => $override['override_id'],
                        'settlement_id' => $request['settlement_id'],
                        'status' => $override['status'],
                        'override_ammount' => $override['override_ammount'],
                        'override_ammount_locked' => $override['override_ammount_locked'],
                        'type' => $overrideType,
                        'override_type_locked' => $override['override_type_locked'],
                        'direct_custom_sales_field_id' => $directCustomFieldId,
                        'indirect_custom_sales_field_id' => $indirectCustomFieldId,
                        'office_custom_sales_field_id' => $officeCustomFieldId,
                    ]);
                }
            }
            $data6 = PositionTierOverride::where('position_id', $request->position_id)->first();
            // dd($data6);
            if ($data6 == null) {
                if ($request->tier_override_status == 1) {
                    PositionTierOverride::create([
                        'position_id' => $request['position_id'],
                        // 'position_id'     =>  $request['position_id'],
                        'tier_status' => $request['tier_override_status'],
                        'sliding_scale' => $request['sliding_scale'],
                        'sliding_scale_locked' => $request['sliding_scale_locked'],
                        'levels' => $request['levels'],
                        'level_locked' => $request['level_locked'],
                    ]);
                } else {
                    // return response()->json([
                    //     'ApiName' => ' Tier Overrride',
                    //     'status' => false,
                    //     'message' => '',
                    //     'data' => null,
                    // ], 400);
                }
            }

            $data7 = PositionReconciliations::where('position_id', $request->position_id)->first();
            // dd($data7);
            if ($data7 == null) {
                PositionReconciliations::create(
                    [
                        'position_id' => $request['position_id'],
                        'commission_withheld' => isset($request['commission_withheld']) ? $request['commission_withheld'] : '0',
                        'commission_type' => $request['commission_type'],
                        'maximum_withheld' => isset($request['maximum_withheld']) ? $request['maximum_withheld'] : '0',
                        'override_settlement' => isset($request['override_settlement']) ? $request['override_settlement'] : 'During M2',
                        'clawback_settlement' => $request['clawback_settlement'],
                        'status' => $request['reconciliation_status'],
                    ]
                );
            }

            $data8 = PositionPayFrequency::where('position_id', $request->position_id)->first();
            // dd($data8);
            if ($data8 == null) {
                PositionPayFrequency::create(
                    [
                        'position_id' => $request['position_id'],
                        'frequency_type_id' => $request['frequency_type_id'],
                        'first_months' => $request['first_months'],
                        'day_of_week' => $request['day_of_week'],
                        'first_day' => $request['first_day'],
                        'day_of_months' => $request['day_of_months'],
                        'pay_period' => $request['pay_period'],
                        'monthly_per_days' => $request['monthly_per_days'],
                        'first_day_pay_of_manths' => $request['first_day_pay_of_manths'],
                        'second_pay_day_of_month' => $request['second_pay_day_of_month'],
                        'deadline_to_run_payroll' => $request['deadline_to_run_payroll'],
                        'first_pay_period_ends_on' => $request['first_pay_period_ends_on'],
                    ]
                );
            }
            // Commented by Gorakh
            // $Position =  Positions::where('id', $request['position_id'])->first();
            // $Position->setup_status = 1;
            // $Position->save();
        }
        $Position = Positions::where('id', $request['position_id'])->first();
        $Position->setup_status = 1;
        $Position->save();
        $data = Positions::with('departmentDetail', 'Commission', 'Upfront', 'deductionname', 'Override', 'deductionlimit', 'OverrideTier', 'reconciliation', 'payFrequency')->where('id', $request['position_id'])->first();

        return response()->json(['status' => true, 'message' => 'Add Successfully.', 'data' => $data], 200);

    }

    public function store(CompensationPlanValidatedRequest $request): JsonResponse
    {
        $data = PositionCommission::where('position_id', $request['position_id'])->first();

        if ($data == null) {
            $position = Positions::find($request['position_id']);
            if ($position != null) {
                // Parse custom field type from 'custom_field_X' format for commission
                $commissionAmountType = $request['commission_amount_type'];
                $commissionCustomFieldId = $request['custom_sales_field_id'] ?? null;

                if ($commissionAmountType && str_starts_with($commissionAmountType, 'custom_field_')) {
                    $commissionCustomFieldId = (int) str_replace('custom_field_', '', $commissionAmountType);
                    $commissionAmountType = 'custom field';
                }

                PositionCommission::create([
                    'position_id' => $request['position_id'],
                    'commission_parentage' => $request['commission_parentage'],
                    'commission_parentag_hiring_locked' => $request['commission_parentag_hiring_locked'],
                    'commission_amount_type' => $commissionAmountType,
                    'commission_amount_type_locked' => $request['commission_amount_type_locked'],
                    'commission_structure_type' => $request['commission_structure_type'],
                    'commission_parentag_type_hiring_locked' => $request['commission_parentag_type_hiring_locked'],
                    'custom_sales_field_id' => $commissionCustomFieldId,
                ]);
            } else {
                return response()->json(['status' => false, 'message' => 'Position id not Found!'], 400);
            }
        }

        if ($request->upfront_status == 1) {
            $data1 = PositionCommissionUpfronts::where('position_id', $request['position_id'])->first();
            // dd($data1);
            if ($data1 == null) {

                // Parse custom field type from 'custom_field_X' format
                $calculatedBy = $request['calculated_by'];
                $upfrontCustomFieldId = $request['upfront_custom_sales_field_id'] ?? null;
                
                if ($calculatedBy && str_starts_with($calculatedBy, 'custom_field_')) {
                    $upfrontCustomFieldId = (int) str_replace('custom_field_', '', $calculatedBy);
                    $calculatedBy = 'custom field';
                }
                
                PositionCommissionUpfronts::create([
                    'position_id' => $request->position_id,
                    'status_id' => $request['upfront_status'],
                    'upfront_ammount' => $request['upfront_ammount'],
                    'upfront_ammount_locked' => $request['upfront_ammount_locked'],
                    'calculated_by' => $calculatedBy,
                    'custom_sales_field_id' => $upfrontCustomFieldId,
                    'calculated_locked' => $request['calculated_locked'],
                    'upfront_system' => $request['upfront_system'],
                    'upfront_system_locked' => $request['upfront_system_locked'],
                    'upfront_limit' => $request['upfront_limit'],
                    'upfront_limit_type' => $request['upfront_limit_type'] ?? null,

                ]);
            }
        }
        if ($request->deduction_status == 1) {
            $data2 = PositionCommissionDeduction::where('position_id', $request->position_id)->first();
            if ($data2 == null) {
                if ($request->deduction) {
                    $this->positionCommissionDeductionSetting = PositionCommissionDeductionSetting::find(1);
                    $this->positionCommissionDeductionSetting->status = $request->deduction_status;
                    $this->positionCommissionDeductionSetting->deducation_locked = $request->deduction_locked;
                    $this->positionCommissionDeductionSetting->update();

                }
                foreach ($request->deduction as $data1) {
                    if ($request->upfront_status == 1) {
                        PositionCommissionDeduction::create([
                            'deduction_setting_id' => 1,
                            'position_id' => $request->position_id,
                            'cost_center_id' => $data1['cost_center_id'],
                            'deduction_type' => $data1['deduction_type'],
                            'ammount_par_paycheck' => $data1['ammount_par_paycheck'],
                        ]);
                    } else {
                        return response()->json([
                            'ApiName' => 'Commission duducation',
                            'status' => false,
                            'message' => '',
                            'data' => null,
                        ], 400);
                    }
                }
            }
        }
        $data4 = PositionsDeductionLimit::where('position_id', $request->position_id)->first();
        // dd($data4);
        if ($data4 == null) {
            PositionsDeductionLimit::create([
                'deduction_setting_id' => 1,
                'position_id' => $request->position_id,
                'status' => $request->deduction_status,
                'limit_type' => $request['limit_type'],
                'limit_ammount' => $request['limit_ammount'],
                'limit' => $request['limit'],
            ]);
        }
        $data5 = PositionOverride::where('position_id', $request->position_id)->first();
        // dd($data5);
        if ($data5 == null) {
            foreach ($request->override as $override) {
                // Parse custom field type from 'custom_field_X' format for override
                $overrideType = $override['type'];
                $overrideCustomFieldId = null;

                if ($overrideType && str_starts_with($overrideType, 'custom_field_')) {
                    $overrideCustomFieldId = (int) str_replace('custom_field_', '', $overrideType);
                    $overrideType = 'custom field';
                }

                // Determine which custom field column to use based on override_id
                $directCustomFieldId = null;
                $indirectCustomFieldId = null;
                $officeCustomFieldId = null;

                if ($overrideCustomFieldId) {
                    switch ($override['override_id']) {
                        case '1':
                        case 1:
                            $directCustomFieldId = $overrideCustomFieldId;
                            break;
                        case '2':
                        case 2:
                            $indirectCustomFieldId = $overrideCustomFieldId;
                            break;
                        case '3':
                        case 3:
                            $officeCustomFieldId = $overrideCustomFieldId;
                            break;
                    }
                }

                PositionOverride::create([
                    'position_id' => $request['position_id'],
                    'override_id' => $override['override_id'],
                    'settlement_id' => $request['settlement_id'],
                    'status' => $override['status'],
                    'override_ammount' => $override['override_ammount'],
                    'override_ammount_locked' => $override['override_ammount_locked'],
                    'type' => $overrideType,
                    'override_type_locked' => $override['override_type_locked'],
                    'direct_custom_sales_field_id' => $directCustomFieldId,
                    'indirect_custom_sales_field_id' => $indirectCustomFieldId,
                    'office_custom_sales_field_id' => $officeCustomFieldId,
                ]);
            }
        }
        $data6 = PositionTierOverride::where('position_id', $request->position_id)->first();
        // dd($data6);
        if ($data6 == null) {
            if ($request->tier_override_status == 1) {
                PositionTierOverride::create([
                    'position_id' => $request['position_id'],
                    // 'position_id'     =>  $request['position_id'],
                    'tier_status' => $request['tier_override_status'],
                    'sliding_scale' => $request['sliding_scale'],
                    'sliding_scale_locked' => $request['sliding_scale_locked'],
                    'levels' => $request['levels'],
                    'level_locked' => $request['level_locked'],
                ]);
            } else {
                // return response()->json([
                //     'ApiName' => ' Tier Overrride',
                //     'status' => false,
                //     'message' => '',
                //     'data' => null,
                // ], 400);
            }
        }

        $data7 = PositionReconciliations::where('position_id', $request->position_id)->first();

        if ($data7 == null) {
            PositionReconciliations::create(
                [
                    'position_id' => $request['position_id'],
                    'commission_withheld' => $request['commission_withheld'],
                    'commission_type' => $request['commission_type'],
                    'maximum_withheld' => $request['maximum_withheld'],
                    'override_settlement' => $request['override_settlement'],
                    'clawback_settlement' => $request['clawback_settlement'],
                    'status' => $request['reconciliation_status'],

                ]
            );
        }

        // echo"DSD";die;
        $data8 = PositionPayFrequency::where('position_id', $request->position_id)->first();
        // dd($data8);
        if ($data8 == null) {
            PositionPayFrequency::create(
                [
                    'position_id' => $request['position_id'],
                    'frequency_type_id' => $request['frequency_type_id'],
                    'first_months' => $request['first_months'],
                    'day_of_week' => $request['day_of_week'],
                    'first_day' => $request['first_day'],
                    'day_of_months' => $request['day_of_months'],
                    'pay_period' => $request['pay_period'],
                    'monthly_per_days' => $request['monthly_per_days'],
                    'first_day_pay_of_manths' => $request['first_day_pay_of_manths'],
                    'second_pay_day_of_month' => $request['second_pay_day_of_month'],
                    'deadline_to_run_payroll' => $request['deadline_to_run_payroll'],
                    'first_pay_period_ends_on' => $request['first_pay_period_ends_on'],
                ]
            );
        }
        // Commented by Gorakh
        // $Position =  Positions::where('id', $request['position_id'])->first();
        // $Position->setup_status = 1;
        // $Position->save();
        if (isset($request['frequency_type_id']) && $request['frequency_type_id'] != '') {
            $Position = Positions::where('id', $request['position_id'])->first();
            $Position->setup_status = 1;
            $Position->save();
        }

        $data = Positions::with('departmentDetail', 'Commission', 'Upfront', 'deductionname', 'Override', 'deductionlimit', 'OverrideTier', 'reconciliation', 'payFrequency')->where('id', $request['position_id'])->first();

        return response()->json(['status' => true, 'message' => 'Add Successfully.', 'data' => $data], 200);

    }

    public function update(CompensationPlanValidatedRequest $request, $id): JsonResponse
    {
        $position = Positions::withcount('peoples')->find($request->position_id);
        if ($position != null) {
            if (isset($request['frequency_type_id']) && $request->frequency_type_id != '') {
                $payFrequency = PositionPayFrequency::where('position_id', $id)->first();
                if ($payFrequency && $payFrequency->frequency_type_id != $request['frequency_type_id']) {
                    if ($position->peoples_count > 0) {
                        return response()->json(['status' => false, 'message' => 'This position is assigned to the other users, therefor Pay Frequency can not be changed.'], 400);
                    }

                    $payroll = Payroll::where('position_id', $payFrequency->position_id)->first();
                    if ($payroll) {
                        return response()->json(['status' => false, 'message' => 'As of payroll has data for this position, therefor Pay Frequency can not be changed.'], 400);
                    }
                }
            }

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $validator = Validator::make($request->all(), [
                    'commission_amount_type' => 'nullable|in:percent,per sale',
                    'calculated_by' => 'nullable|in:per sale,percent',
                    'override_settlement' => 'nullable|in:Initial Service,Reconciliation',
                    'stack_settlement' => 'nullable|in:Initial Service,Reconciliation',
                    'override.*.type' => 'nullable|in:per sale,percent',
                ], [
                    'override.*.type.in' => 'Override Type Per KW is not allowed to select.',
                ]);

                if ($validator->fails()) {
                    return response()->json(['status' => false, 'message' => $validator->errors()->first()], 400);
                }
            }

            $position->position_name = $request->position_name;
            $position->is_stack = $request->is_stack;
            $position->save();

            $wage = PositionWage::where('position_id', $id)->first();

            // dd($request->all());

            $pay_type_lock = 0;
            $pay_rate_lock = 0;
            $pto_hours_lock = 0;
            $unused_pto_expires_lock = 0;
            $expected_weekly_hours_lock = 0;
            $overtime_rate_lock = 0;
            $pay_rate_type = 'Weekly';

            if (isset($request->pay_type_lock)) {
                $pay_type_lock = $request->pay_type_lock;
            }
            if (isset($request->pay_rate_lock)) {
                $pay_rate_lock = $request->pay_rate_lock;
            }
            if (isset($request->pto_hours_lock)) {
                $pto_hours_lock = $request->pto_hours_lock;
            }
            if (isset($request->unused_pto_expires_lock)) {
                $unused_pto_expires_lock = $request->unused_pto_expires_lock;
            }
            if (isset($request->expected_weekly_hours_lock)) {
                $expected_weekly_hours_lock = $request->expected_weekly_hours_lock;
            }
            if (isset($request->overtime_rate_lock)) {
                $overtime_rate_lock = $request->overtime_rate_lock;
            }
            if (isset($request->pay_rate_type)) {
                $pay_rate_type = $request->pay_rate_type;
            }

            if ($wage) {

                $wage->position_id = $request->position_id;
                $wage->pay_type = $request->pay_type;
                $wage->pay_type_lock = $pay_type_lock;
                $wage->pay_rate = $request->pay_rate;
                $wage->pay_rate_type = $pay_rate_type;
                $wage->pay_rate_lock = $pay_rate_lock;
                $wage->pto_hours = $request->pto_hours;
                $wage->pto_hours_lock = $pto_hours_lock;
                $wage->unused_pto_expires = $request->unused_pto_expires;
                $wage->unused_pto_expires_lock = $unused_pto_expires_lock;
                $wage->expected_weekly_hours = $request->expected_weekly_hours;
                $wage->expected_weekly_hours_lock = $expected_weekly_hours_lock;
                $wage->overtime_rate = $request->overtime_rate;
                $wage->overtime_rate_lock = $overtime_rate_lock;
                $wage->wages_status = isset($request->wages_status) ? $request->wages_status : 0;

                $wage->save();

            } else {

                $wage = PositionWage::create([
                    'position_id' => $request->position_id,
                    'pay_type' => $request->pay_type,
                    'pay_type_lock' => $pay_type_lock,
                    'pay_rate' => $request->pay_rate,
                    'pay_rate_type' => $pay_rate_type,
                    'pay_rate_lock' => $pay_rate_lock,
                    'pto_hours' => $request->pto_hours,
                    'pto_hours_lock' => $pto_hours_lock,
                    'unused_pto_expires' => $request->unused_pto_expires,
                    'unused_pto_expires_lock' => $unused_pto_expires_lock,
                    'expected_weekly_hours' => $request->expected_weekly_hours,
                    'expected_weekly_hours_lock' => $expected_weekly_hours_lock,
                    'overtime_rate' => $request->overtime_rate,
                    'overtime_rate_lock' => $overtime_rate_lock,
                    'wages_status' => isset($request->wages_status) ? $request->wages_status : 0,
                ]);

            }

            $commissions = PositionCommission::where('position_id', $id)->first();

            $commission_amount_type_locked = 0;
            if ($request->commission_amount_type_locked) {
                $commission_amount_type_locked = 1;
            }
            $commission_parentag_type_hiring_locked = 0;
            if ($request->commission_parentag_type_hiring_locked) {
                $commission_parentag_type_hiring_locked = 1;
            }
            $commission_parentag_hiring_locked = 0;
            if ($request->commission_parentag_hiring_locked) {
                $commission_parentag_hiring_locked = 1;
            }

            // Parse custom field type from 'custom_field_X' format for commission
            $commissionAmountType = $request->commission_amount_type;
            $commissionCustomFieldId = $request->custom_sales_field_id ?? null;

            if ($commissionAmountType && str_starts_with($commissionAmountType, 'custom_field_')) {
                $commissionCustomFieldId = (int) str_replace('custom_field_', '', $commissionAmountType);
                $commissionAmountType = 'custom field';
            }

            if ($commissions) {

                $commissions->commission_parentage = isset($request->commission_parentage) ? $request->commission_parentage : null;

                $commissions->commission_parentag_hiring_locked = $commission_parentag_hiring_locked;
                $commissions->commission_amount_type = $commissionAmountType;
                $commissions->commission_amount_type_locked = $commission_amount_type_locked;
                $commissions->commission_structure_type = $request->commission_structure_type;
                $commissions->commission_parentag_type_hiring_locked = $commission_parentag_type_hiring_locked;
                // Custom Sales Field support
                $commissions->custom_sales_field_id = $commissionCustomFieldId;
                // position_commissions_locked
                // rename to commission_status
                // commission_status
                if (isset($request->commission_status)) {
                    $commissions->commission_status = $request->commission_status;
                }
                $commissions->save();
            } else {
                $data = [

                    'position_id' => $id,
                    'commission_parentage' => $request->commission_parentage,
                    'commission_parentag_hiring_locked' => $commission_parentag_hiring_locked,
                    'commission_amount_type' => $commissionAmountType,
                    'commission_amount_type_locked' => $commission_amount_type_locked,
                    'commission_structure_type' => $request->commission_structure_type,
                    'commission_parentag_type_hiring_locked' => $commission_parentag_type_hiring_locked,
                    'custom_sales_field_id' => $commissionCustomFieldId,
                    // position_commissions_locked
                    // rename to commission_status
                    // commission_status

                ];

                if (isset($request->commission_status)) {
                    $data['commission_status'] = $request->commission_status;
                }

                $commissions = PositionCommission::create($data);
            }

            $upfront = PositionCommissionUpfronts::where('position_id', $request->position_id)->first();
            $upfront_ammount_locked = 0;
            if ($request->upfront_ammount_locked) {
                $upfront_ammount_locked = 1;
            }
            $calculated_locked = 0;
            if ($request->calculated_locked) {
                $calculated_locked = 1;
            }
            $upfront_system_locked = 0;
            if ($request->upfront_system_locked) {
                $upfront_system_locked = 1;
            }
            if ($upfront) {
                if ($request->upfront_status == 1) {
                    $upfront->status_id = $request->upfront_status;
                }
                /*
                 * commission_status
                 * 1 for locked, 0 for unlocked
                */
                if ($commissions->commission_status == 1) {
                    // make upfront locked too
                    $upfront->status_id = 0;
                }

                // Parse custom field type from 'custom_field_X' format
                $calculatedBy = $request->calculated_by;
                $upfrontCustomFieldId = $request->upfront_custom_sales_field_id ?? null;
                
                if ($calculatedBy && str_starts_with($calculatedBy, 'custom_field_')) {
                    $upfrontCustomFieldId = (int) str_replace('custom_field_', '', $calculatedBy);
                    $calculatedBy = 'custom field';
                }
                
                $upfront->upfront_status = $request->upfront_status;
                $upfront->upfront_ammount = $request->upfront_ammount;
                $upfront->upfront_ammount_locked = $upfront_ammount_locked;
                $upfront->calculated_by = $calculatedBy;
                $upfront->custom_sales_field_id = $upfrontCustomFieldId;
                $upfront->calculated_locked = $calculated_locked;
                $upfront->upfront_system = $request->upfront_system;
                $upfront->upfront_system_locked = $upfront_system_locked;
                $upfront->upfront_limit = $request->upfront_limit;
                $upfront->upfront_limit_type = $request->upfront_limit_type ?? null;
                $upfront->save();
            } else {
                $data1 = PositionCommissionUpfronts::where('position_id', $request->position_id)->first();
                if ($data1 == null && $request->upfront_status == 1) {
                    // Parse custom field type from 'custom_field_X' format
                    $calculatedBy = $request['calculated_by'];
                    $upfrontCustomFieldId = $request['upfront_custom_sales_field_id'] ?? null;
                    
                    if ($calculatedBy && str_starts_with($calculatedBy, 'custom_field_')) {
                        $upfrontCustomFieldId = (int) str_replace('custom_field_', '', $calculatedBy);
                        $calculatedBy = 'custom field';
                    }
                    
                    PositionCommissionUpfronts::create([
                        'position_id' => $id,
                        'status_id' => $request['upfront_status'],
                        'upfront_ammount' => $request['upfront_ammount'],
                        'upfront_ammount_locked' => $upfront_ammount_locked,
                        'calculated_by' => $calculatedBy,
                        'custom_sales_field_id' => $upfrontCustomFieldId,
                        'calculated_locked' => $calculated_locked,
                        'upfront_system' => $request['upfront_system'],
                        'upfront_system_locked' => $upfront_system_locked,
                        'upfront_limit' => $request['upfront_limit'],
                        'upfront_limit_type' => $request['upfront_limit_type'] ?? null,
                    ]);
                }
            }

            $user = User::where('sub_position_id', $id)->get();
            foreach ($user as $users) {
                $userUpfront = User::where('id', $users->id)->first();
                $userUpfront->upfront_pay_amount = $request['upfront_ammount'];
                // Parse custom field type for user sync
                $userCalculatedBy = $request['calculated_by'];
                if ($userCalculatedBy && str_starts_with($userCalculatedBy, 'custom_field_')) {
                    $userCalculatedBy = 'custom field';
                }
                $userUpfront->upfront_sale_type = $userCalculatedBy;
                $userUpfront->save();
            }

            PositionCommissionDeduction::where('position_id', $request->position_id)->delete();
            $deduction_locked = 0;
            if ($request->deduction_locked) {
                $deduction_locked = 1;
            }
            if ($request->deduction) {
                $positionCommissionDeductionSetting = PositionCommissionDeductionSetting::where('position_id', $request->position_id)->first();
                if ($positionCommissionDeductionSetting) {
                    $positionCommissionDeductionSetting->status = $request->deduction_status;
                    $positionCommissionDeductionSetting->deducation_locked = $deduction_locked;
                    $positionCommissionDeductionSetting->save();
                } else {
                    PositionCommissionDeductionSetting::create([
                        'position_id' => $request->position_id,
                        'status' => $request->deduction_status,
                        'deducation_locked' => $deduction_locked,
                    ]);
                }
            }

            foreach ($request->deduction as $deduction) {
                if ($deduction['cost_center_id']) {
                    PositionCommissionDeduction::create([
                        'position_id' => $id,
                        'deduction_setting_id' => 1,
                        'deduction_type' => $deduction['deduction_type'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'ammount_par_paycheck' => $deduction['ammount_par_paycheck'],
                        'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                        'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                    ]);

                    if (! empty($deduction['changes_type']) || ! empty($deduction['changes_field'])) {

                        if (! empty($deduction['changes_type'])) {
                            if ($deduction['changes_type'] == 'new') {

                                $deduction['ammount_par_paycheck'] = 0;

                            }
                        }

                        // $usersdata = User::select('id','position_id','sub_position_id')->where('sub_position_id',$request->position_id)->where('is_super_admin','!=','1')->get();
                        $usersdata = User::select('id', 'position_id', 'sub_position_id')->where('sub_position_id', $request->position_id)->where('id', '!=', '1')->get();
                        if (count($usersdata) > 0) {
                            $date = date('Y-m-d');
                            foreach ($usersdata as $key => $user) {

                                $checkUserDeduction = UserDeduction::where(['user_id' => $user->id, 'cost_center_id' => $deduction['cost_center_id']])->first();
                                if (empty($checkUserDeduction)) {
                                    $costcenter = CostCenter::select('name')->where('id', $deduction['cost_center_id'])->first();

                                    $dataInsert = [
                                        'deduction_type' => $deduction['deduction_type'],
                                        'cost_center_name' => isset($costcenter->name) ? $costcenter->name : null,
                                        'cost_center_id' => $deduction['cost_center_id'],
                                        'ammount_par_paycheck' => $deduction['ammount_par_paycheck'],
                                        'deduction_setting_id' => isset($deduction['deduction_setting_id']) ? $deduction['deduction_setting_id'] : null,
                                        'position_id' => isset($position->parent_id) ? $position->parent_id : $request->position_id,
                                        'sub_position_id' => isset($request->position_id) ? $request->position_id : null,
                                        'user_id' => $user->id,
                                        'effective_date' => $date,
                                    ];

                                    UserDeduction::create($dataInsert);
                                }

                                if (empty($deduction['changes_field']) || $deduction['changes_field'] == 'all') {

                                    $check_data = UserDeductionHistory::where(['user_id' => $user->id, 'cost_center_id' => $deduction['cost_center_id'], 'effective_date' => $date])->first();
                                    if ($check_data) {
                                        $update = [
                                            'updater_id' => auth()->user()->id,
                                            'limit_value' => isset($request['limit_ammount']) ? $request['limit_ammount'] : null,
                                            'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                                            'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                                            'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                                        ];
                                        $history = UserDeductionHistory::where('id', $check_data->id)->update($update);

                                    } else {

                                        $history = UserDeductionHistory::create([
                                            'user_id' => $user->id,
                                            'updater_id' => auth()->user()->id,
                                            'cost_center_id' => $deduction['cost_center_id'],
                                            'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                                            'old_amount_par_paycheque' => null,
                                            'sub_position_id' => isset($request->position_id) ? $request->position_id : null,
                                            'limit_value' => isset($request['limit_ammount']) ? $request['limit_ammount'] : null,
                                            'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                                            'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                                            'effective_date' => $date,
                                        ]);
                                    }

                                }

                            }

                        }

                    }
                    $subjectId = $position->id;
                    $eventType = 'updated';

                    $oldPosition = DB::table('positions')->where('id', $subjectId)->first();

                    if ($oldPosition) {
                        $oldValues = [
                            'deduction_setting_id' => $oldPosition->deduction_setting_id ?? null,
                            'position' => $oldPosition->position ?? null,
                            'deduction_type' => $oldPosition->deduction_type ?? null,
                            'amount_per_paycheck' => $oldPosition->amount_per_paycheck ?? null,
                            'cost_center_name' => $oldPosition->cost_center_name ?? null,
                        ];

                        DB::table('activity_log')
                            ->where('subject_id', $subjectId)
                            ->where('event', $eventType)
                            ->delete();

                        $logName = 'PositionCommissionDeduction details have been updated';

                        activity()
                            ->performedOn($commissions)
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'attributes' => $deduction,
                                'old' => $oldValues,
                            ])
                            ->event($eventType)
                            ->log($logName);
                    }
                }

            }

            $deduction_limit = PositionsDeductionLimit::where('position_id', $id)->first();
            if ($deduction_limit) {
                $oldAttributes = $deduction_limit->getAttributes();

                $deduction_limit->limit_ammount = $request['limit_ammount'];
                $deduction_limit->limit = $request['limit'];
                $deduction_limit->status = $request['deduction_status'];
                $deduction_limit->limit_type = $request['limit_type'];
                $deduction_limit->update();
            } else {
                PositionsDeductionLimit::create([
                    'limit_ammount' => $request['limit_ammount'],
                    'limit' => $request['limit'],
                    'status' => $request['deduction_status'],
                    'limit_type' => $request['limit_type'],
                    'position_id' => $id,
                ]);
            }

            // $override = PositionOverride::where('position_id', $id)->get();
            // foreach ($override as $key =>  $override1) {
            //     $override2 = PositionOverride::where('id', $override1['id'])
            //         ->where('position_id', $id)->first();
            //         // dd($override2);
            //     $override2->override_ammount  =  $request['override'][$key]['override_ammount'];
            //     $override2->type = $request['override'][$key]['type'];
            //     $override2->save();
            // }

            $data5 = PositionOverride::where('position_id', $id)->first();
            if (isset($request['settlement_id']) && $request['settlement_id'] != '') {
                PositionOverride::where('position_id', $id)->delete();
                foreach ($request->override as $override) {
                    // Parse custom field type from 'custom_field_X' format for override
                    $overrideType = $override['type'];
                    $overrideCustomFieldId = null;

                    if ($overrideType && str_starts_with($overrideType, 'custom_field_')) {
                        $overrideCustomFieldId = (int) str_replace('custom_field_', '', $overrideType);
                        $overrideType = 'custom field';
                    }

                    // Determine which custom field column to use based on override_id
                    $directCustomFieldId = null;
                    $indirectCustomFieldId = null;
                    $officeCustomFieldId = null;

                    if ($overrideCustomFieldId) {
                        switch ($override['override_id']) {
                            case '1':
                            case 1:
                                $directCustomFieldId = $overrideCustomFieldId;
                                break;
                            case '2':
                            case 2:
                                $indirectCustomFieldId = $overrideCustomFieldId;
                                break;
                            case '3':
                            case 3:
                                $officeCustomFieldId = $overrideCustomFieldId;
                                break;
                        }
                    }

                    PositionOverride::create([
                        'position_id' => $id,
                        'override_id' => $override['override_id'],
                        'settlement_id' => $request['settlement_id'],
                        'status' => $override['status'],
                        'override_ammount' => $override['override_ammount'],
                        'override_ammount_locked' => $override['override_ammount_locked'],
                        'type' => $overrideType,
                        'override_type_locked' => $override['override_type_locked'],
                        'direct_custom_sales_field_id' => $directCustomFieldId,
                        'indirect_custom_sales_field_id' => $indirectCustomFieldId,
                        'office_custom_sales_field_id' => $officeCustomFieldId,
                    ]);
                }
            }

            // $tier =  PositionTierOverride::where('position_id', $id)->first();
            // $tier->sliding_scale   = $request->sliding_scale;
            // $tier->save();

            $positionReconciliation = PositionReconciliations::where('position_id', $id)->first();
            if ($positionReconciliation) {
                $positionReconciliation->commission_withheld = $request->commission_withheld;
                $positionReconciliation->commission_type = $request->commission_type;
                $positionReconciliation->maximum_withheld = $request->maximum_withheld;
                $positionReconciliation->override_settlement = $request->override_settlement;
                $positionReconciliation->clawback_settlement = $request->clawback_settlement;
                $positionReconciliation->stack_settlement = $request->stack_settlement;
                $positionReconciliation->status = $request->reconciliation_status;
                $positionReconciliation->commission_withheld_locked = isset($request->commission_withheld_locked) ? $request->commission_withheld_locked : 0;
                $positionReconciliation->commission_type_locked = isset($request->commission_type_locked) ? $request->commission_type_locked : 0;
                $positionReconciliation->save();
            } else {
                PositionReconciliations::create([
                    'commission_withheld' => $request->commission_withheld,
                    'commission_type' => $request->commission_type,
                    'maximum_withheld' => $request->maximum_withheld,
                    'override_settlement' => $request->override_settlement,
                    'clawback_settlement' => $request->clawback_settlement,
                    'stack_settlement' => $request->stack_settlement,
                    'status' => $request->reconciliation_status,
                    'commission_withheld_locked' => isset($request->commission_withheld_locked) ? $request->commission_withheld_locked : 0,
                    'commission_type_locked' => isset($request->commission_type_locked) ? $request->commission_type_locked : 0,
                    'position_id' => $id,
                ]);
            }

            if (isset($request['frequency_type_id']) && $request->frequency_type_id != '') {
                $payFrequency = PositionPayFrequency::where('position_id', $id)->first();
                if ($payFrequency) {
                    $payFrequency->frequency_type_id = $request->frequency_type_id;
                    $payFrequency->first_months = $request->first_months;
                    $payFrequency->day_of_week = $request->day_of_week;
                    $payFrequency->first_day = $request->first_day;
                    $payFrequency->day_of_months = $request->day_of_months;
                    $payFrequency->pay_period = $request->pay_period;
                    $payFrequency->monthly_per_days = $request->monthly_per_days;
                    $payFrequency->first_day_pay_of_manths = $request->first_day_pay_of_manths;
                    $payFrequency->second_pay_day_of_month = $request->second_pay_day_of_month;
                    $payFrequency->deadline_to_run_payroll = $request->deadline_to_run_payroll;
                    $payFrequency->first_pay_period_ends_on = $request->first_pay_period_ends_on;
                    $payFrequency->save();
                } else {
                    PositionPayFrequency::create([
                        'position_id' => $id,
                        'frequency_type_id' => $request->frequency_type_id,
                        'first_months' => $request->first_months,
                        'day_of_week' => $request->day_of_week,
                        'first_day' => $request->first_day,
                        'day_of_months' => $request->day_of_months,
                        'pay_period' => $request->pay_period,
                        'monthly_per_days' => $request->monthly_per_days,
                        'first_day_pay_of_manths' => $request->first_day_pay_of_manths,
                        'second_pay_day_of_month' => $request->second_pay_day_of_month,
                        'deadline_to_run_payroll' => $request->deadline_to_run_payroll,
                        'first_pay_period_ends_on' => $request->first_pay_period_ends_on,
                    ]);
                }

                $Position = Positions::where('id', $request['position_id'])->first();
                $Position->setup_status = 1;
                $Position->save();
            }

            return response()->json(['status' => true, 'message' => 'update Successfully.'], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Position is not Correct.'], 400);
        }
    }

    public function destroy($id)
    {
        // return $id;
        if (! null == $id) {

            if ($id == 1 || $id == 2 || $id == 3) {
                return response()->json(['status' => true, 'message' => 'compensation  plan are not deleted.'], 200);
            }

            $data = Positions::find($id);
            if ($data == null) {
                return response()->json(['status' => true, 'message' => 'compensation plan not find.'], 200);
            } else {
                $id = Positions::find($id);
                $id->delete();

                return response()->json([
                    'ApiName' => 'delete-compensation plan',
                    'status' => true,
                    'message' => 'delete Successfully.',
                    'data' => $id,
                ], 200);
            }
        }
    }

    public function show($id)
    {
        // echo"DASD";die;

        $data = Positions::withcount('peoples')->with('departmentDetail', 'Commission', 'Upfront', 'deductionname', 'Override', 'deductionlimit', 'OverrideTier', 'reconciliation', 'payFrequency', 'position_wage', 'allAssociatedOfferLettersWithTemplate')->where('id', $id)->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Position not found.'
            ], 404);
        }

        $positionCommissionDeductionSetting = PositionCommissionDeductionSetting::where('position_id', $id)->first();
        $positionDeductionLimit = PositionsDeductionLimit::where('position_id', $id)->first();
        $positionOverrideSettlement = PositionOverrideSettlement::where('position_id', $id)->first();
        // return $data->Override;
        $overrides = [];
        if ($data && $data->Override) {
            foreach ($data->Override as $override) {
                // dd($override->overridesDetail->id);
                $overrides[] =
                    [
                        // dd($override->overrides_detail),
                        'override_id' => $override->override_id,
                        'status' => $override->status,
                        'settlement_id' => $override->settlement_id,
                        'override_ammount' => $override->override_ammount,
                        'override_ammount_locked' => $override->override_ammount_locked,
                        'type' => $override->type,
                        'custom_sales_field_id' => $override->custom_sales_field_id ?? null,
                        'direct_custom_sales_field_id' => $override->direct_custom_sales_field_id ?? null,
                        'indirect_custom_sales_field_id' => $override->indirect_custom_sales_field_id ?? null,
                        'office_custom_sales_field_id' => $override->office_custom_sales_field_id ?? null,
                        'override_type_locked' => $override->override_type_locked,
                        'override_type_id' => isset($override->overridesDetail->id) ? $override->overridesDetail->id : null,
                        'overrides_type' => isset($override->overridesDetail->overrides_type) ? $override->overridesDetail->overrides_type : null,
                    ];
            }
        }

        $deductionname = [];
        if ($data && $data->deductionname) {
            foreach ($data->deductionname as $deduction) {
                $deductionname[] = [
                    'id' => $deduction->id,
                    'deduction_setting_id' => $deduction->deduction_setting_id,
                    'position_id' => $deduction->position_id,
                    // 'is_mananger' => $deduction->is_mananger,
                    'cost_center_id' => $deduction->cost_center_id,
                    'deduction_type' => $deduction->deduction_type,
                    'ammount_par_paycheck' => $deduction->ammount_par_paycheck,
                    'cost_center_name' => isset($deduction->costcenter->name) ? $deduction->costcenter->name : null,
                ];
            }
        }

        $isEnable = 1;
        if ($data) {
            $payFrequency = PositionPayFrequency::where('position_id', $id)->first();
            if ($payFrequency) {
                if ($data->peoples_count > 0) {
                    $isEnable = 0;
                }

                $payroll = Payroll::where('position_id', $id)->first();
                if ($payroll) {
                    $isEnable = 0;
                }
            }
        }

        // Check if Custom Sales Fields feature is enabled (for display formatting, using cached helper)
        $isCustomFieldsEnabledForCommission = CustomSalesFieldHelper::isFeatureEnabled();
        
        // return $data1;
        $data1[] =
            [
                'position_id' => $data->id,
                'parent_position_id' => $data->parent_id,
                'is_manager' => $data->is_manager,
                'is_stack' => $data->is_stack,
                'position_name' => isset($data->position_name) ? $data->position_name : null,
                'worker_type' => isset($data->positionDetail->worker_type) ? $data->positionDetail->worker_type : null,
                'commission_parentage' => data_get($data, 'Commission.0.commission_parentage'),
                'commission_status' => data_get($data, 'Commission.0.commission_status'),
                'commission_parentag_hiring_locked' => data_get($data, 'Commission.0.commission_parentag_hiring_locked'),
                // Only use custom_field_X format when feature is enabled
                'commission_amount_type' => ($isCustomFieldsEnabledForCommission && data_get($data, 'Commission.0.custom_sales_field_id')) ? 'custom_field_' . data_get($data, 'Commission.0.custom_sales_field_id') : data_get($data, 'Commission.0.commission_amount_type'),
                'commission_amount_type_locked' => data_get($data, 'Commission.0.commission_amount_type_locked'),
                'commission_parentag_type_hiring_locked' => data_get($data, 'Commission.0.commission_parentag_type_hiring_locked'),
                'commission_structure_type' => data_get($data, 'Commission.0.commission_structure_type'),
                'custom_sales_field_id' => $isCustomFieldsEnabledForCommission ? data_get($data, 'Commission.0.custom_sales_field_id') : null,
                'upfront_ammount' => data_get($data, 'Upfront.0.upfront_ammount'),
                'upfront_ammount_locked' => data_get($data, 'Upfront.0.upfront_ammount_locked', 0),
                'upfront_status' => data_get($data, 'Upfront.0.upfront_status'),
                // Only use custom_field_X format when feature is enabled
                'calculated_by' => ($isCustomFieldsEnabledForCommission && data_get($data, 'Upfront.0.custom_sales_field_id')) ? 'custom_field_' . data_get($data, 'Upfront.0.custom_sales_field_id') : data_get($data, 'Upfront.0.calculated_by'),
                'upfront_custom_sales_field_id' => $isCustomFieldsEnabledForCommission ? data_get($data, 'Upfront.0.custom_sales_field_id') : null,
                'calculated_locked' => data_get($data, 'Upfront.0.calculated_locked'),
                'upfront_system' => data_get($data, 'Upfront.0.upfront_system'),
                'upfront_system_locked' => data_get($data, 'Upfront.0.upfront_system_locked'),
                'upfront_limit' => data_get($data, 'Upfront.0.upfront_limit'),
                'upfront_limit_type' => data_get($data, 'Upfront.0.upfront_limit_type'),
                // 'deduction' => $deductions,// isset($data->deductionname) ? $data->deductionname : NULL,
                'deduction_status' => isset($positionDeductionLimit->status) ? $positionDeductionLimit->status : 0,
                'reconciliation_status' => isset($data->reconciliation->status) ? $data->reconciliation->status : null,
                'deduction_locked' => isset($positionCommissionDeductionSetting->deducation_locked) ? $positionCommissionDeductionSetting->deducation_locked : null,
                'deduction' => $deductionname,
                'limit_ammount' => isset($data->deductionlimit->limit_ammount) ? $data->deductionlimit->limit_ammount : null,
                'limit_type' => isset($data->deductionlimit->limit_type) ? $data->deductionlimit->limit_type : null,
                'limit' => isset($data->deductionlimit->limit) ? $data->deductionlimit->limit : null,
                'deduction_status' => isset($data->deductionlimit->status) ? $data->deductionlimit->status : null,
                'override' => $overrides, // isset($data->Override) ? $data->Override : NULL,
                'tier_override_status' => isset($data->OverrideTier->tier_status) ? $data->OverrideTier->tier_status : null,
                'sliding_scale' => isset($data->OverrideTier->sliding_scale) ? $data->OverrideTier->sliding_scale : null,
                'sliding_scale_locked' => isset($data->OverrideTier->sliding_scale_locked) ? $data->OverrideTier->sliding_scale_locked : null,
                'levels' => isset($data->OverrideTier->levels) ? $data->OverrideTier->levels : null,
                'level_locked' => isset($data->OverrideTier->level_locked) ? $data->OverrideTier->level_locked : null,

                'commission_withheld' => isset($data->reconciliation->commission_withheld) ? $data->reconciliation->commission_withheld : null,
                'commission_type' => isset($data->reconciliation->commission_type) ? $data->reconciliation->commission_type : null,
                'maximum_withheld' => isset($data->reconciliation->maximum_withheld) ? $data->reconciliation->maximum_withheld : null,
                'override_settlement' => isset($data->reconciliation->override_settlement) ? $data->reconciliation->override_settlement : null,
                'clawback_settlement' => isset($data->reconciliation->clawback_settlement) ? $data->reconciliation->clawback_settlement : null,
                'stack_settlement' => isset($data->reconciliation->stack_settlement) ? $data->reconciliation->stack_settlement : null,
                'settlement_id' => 1, // isset($positionOverrideSettlement->id) ? $positionOverrideSettlement->id : NULL,
                // 'pay_frequency' =>  $data->payFrequency,
                'frequency_type_id' => isset($data->payFrequency->frequency_type_id) ? $data->payFrequency->frequency_type_id : null,
                'frequency_type_name' => isset($data->payFrequency->frequencyType->name) ? $data->payFrequency->frequencyType->name : null,
                'first_months' => isset($data->payFrequency->first_months) ? $data->payFrequency->first_months : null,
                'first_day' => isset($data->payFrequency->first_day) ? $data->payFrequency->first_day : null,
                'day_of_week' => isset($data->payFrequency->day_of_week) ? $data->payFrequency->day_of_week : null,
                'day_of_months' => isset($data->payFrequency->day_of_months) ? $data->payFrequency->day_of_months : null,
                'pay_period' => isset($data->payFrequency->pay_period) ? $data->payFrequency->pay_period : null,
                'monthly_per_days' => isset($data->payFrequency->monthly_per_days) ? $data->payFrequency->monthly_per_days : null,
                'commission_type_locked' => isset($data->reconciliation->commission_type_locked) ? $data->reconciliation->commission_type_locked : null,
                'commission_withheld_locked' => isset($data->reconciliation->commission_withheld_locked) ? $data->reconciliation->commission_withheld_locked : null,
                'is_frequency_enable' => $isEnable,

                'wages_status' => isset($data->position_wage->wages_status) ? $data->position_wage->wages_status : 0,
                'pay_type' => isset($data->position_wage->pay_type) ? $data->position_wage->pay_type : null,
                'pay_type_lock' => isset($data->position_wage->pay_type_lock) ? $data->position_wage->pay_type_lock : null,
                'pay_rate' => isset($data->position_wage->pay_rate) ? $data->position_wage->pay_rate : null,
                'pay_rate_type' => isset($data->position_wage->pay_rate_type) ? $data->position_wage->pay_rate_type : null,
                'pay_rate_lock' => isset($data->position_wage->pay_rate_lock) ? $data->position_wage->pay_rate_lock : null,
                'pto_hours' => isset($data->position_wage->pto_hours) ? $data->position_wage->pto_hours : null,
                'pto_hours_lock' => isset($data->position_wage->pto_hours_lock) ? $data->position_wage->pto_hours_lock : null,
                'unused_pto_expires' => isset($data->position_wage->unused_pto_expires) ? $data->position_wage->unused_pto_expires : null,
                'unused_pto_expires_lock' => isset($data->position_wage->unused_pto_expires_lock) ? $data->position_wage->unused_pto_expires_lock : null,
                'expected_weekly_hours' => isset($data->position_wage->expected_weekly_hours) ? $data->position_wage->expected_weekly_hours : null,
                'expected_weekly_hours_lock' => isset($data->position_wage->expected_weekly_hours_lock) ? $data->position_wage->expected_weekly_hours_lock : null,
                'overtime_rate' => isset($data->position_wage->overtime_rate) ? $data->position_wage->overtime_rate : null,
                'overtime_rate_lock' => isset($data->position_wage->overtime_rate_lock) ? $data->position_wage->overtime_rate_lock : null,
                'offer_letters' => isset($data->allAssociatedOfferLettersWithTemplate) ? $data->allAssociatedOfferLettersWithTemplate : [],
            ];

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data1], 200);
    }

    public function positionOrgChartByID($id): JsonResponse
    {
        $parents = [];

        $costCenters = CostCenter::where('id', $id)->with('chields')->orderBy('id', 'DESC')->get();

        return response()->json(['status' => true, 'message' => 'Successfully.', 'costCenters' => $costCenters], 200);
    }

    public function search(Request $request): JsonResponse
    {
        // dd($request->all());
        $result = Positions::where('position_name', 'LIKE', '%'.$request->name.'%')->get();
        if (count($result)) {
            return response()->json([
                'ApiName' => 'search-compen',
                'status' => true,
                'message' => 'search Successfully.',
                'data' => $result,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'search-location',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function getDataPositionFromDepartment(Request $request): JsonResponse
    {
        $isSuperAdmin = auth()->user()->is_super_admin;
        if (! $isSuperAdmin) {
            $positionId = auth()->user()->sub_position_id;
            $hasHirePermission = PositionHirePermission::where('position_id', $positionId)->exists();
            $templateIds = NewSequiDocsTemplatePermission::where(['position_id' => $positionId, 'position_type' => 'permission'])->pluck('template_id');
            $recepientsPositionId = NewSequiDocsTemplatePermission::whereIn('template_id', $templateIds)->where(['position_type' => 'receipient'])->pluck('position_id');

            $dataObj = Department::with(['position' => function ($query) use ($recepientsPositionId, $request) {
                $query->where('setup_status', 1);
                if ($recepientsPositionId) {
                    $query->whereIn('id', $recepientsPositionId);
                }

                if (isset($request->rehire) && $request->rehire == 1 && isset($request->worker_type)) {
                    $query->where('worker_type', $request->worker_type);
                }
            }, 'position.allAssociatedOfferLettersWithTemplate']);
        } else {
            $dataObj = Department::with(['position' => function ($query) use ($request) {
                $query->where('setup_status', 1);
                if (isset($request->rehire) && $request->rehire == 1 && isset($request->worker_type)) {
                    $query->where(['worker_type' => $request->worker_type]);
                }
            }, 'position.allAssociatedOfferLettersWithTemplate']);
        }

        $data = $dataObj->orderby('id', 'asc')->get();
        if (! $isSuperAdmin && $hasHirePermission) {
            $recepientsPositionId = NewSequiDocsTemplatePermission::where(['position_type' => 'receipient'])->pluck('position_id');
            $allPositions = Positions::whereNotIn('id', $recepientsPositionId)->where('setup_status', 1)->where('position_name', '!=', 'Super Admin')->pluck('id');

            $positionsWithoutTemplates = Positions::whereIn('id', $allPositions)->with('allAssociatedOfferLettersWithTemplate')->get();
            $positionsByDept = $positionsWithoutTemplates->groupBy('department_id');
            foreach ($data as $department) {
                if (isset($positionsByDept[$department->id])) {
                    $existingPositions = $department->position ? $department->position : collect([]);
                    $newPositions = $positionsByDept[$department->id];

                    $mergedPositions = collect();
                    if ($existingPositions->count() > 0) {
                        $mergedPositions = $mergedPositions->merge($existingPositions);
                    }
                    if ($newPositions->count() > 0) {
                        $mergedPositions = $mergedPositions->merge($newPositions);
                    }

                    $department->setRelation('position', $mergedPositions);
                }
            }
        }

        if (count($data)) {
            return response()->json([
                'ApiName' => 'get data Position From Department ',
                'status' => true,
                'message' => 'search Successfully.',
                'data' => $data,
            ]);
        } else {
            return response()->json([
                'ApiName' => 'getDataDFromDepartment',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function getPositionFromDepartmentPackageUpdate(Request $request): JsonResponse
    {

        $data = Department::with('position.allAssociatedOfferLettersWithTemplate')->orderby('id', 'asc')->get();

        if (count($data)) {
            return response()->json([
                'ApiName' => 'get Position From Department Package Update',
                'status' => true,
                'message' => 'Department Position fetched Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'getPositionFromDepartmentPackageUpdate',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    // code by nikhil start
    public function positionCommissionCreate(Request $request): JsonResponse
    {

        $data = PositionCommission::where('position_id', $request['position_id'])->first();

        if ($data == null) {
            $position = Positions::find($request['position_id']);
            if ($position != null) {
                $create = PositionCommission::create([
                    'position_id' => $request['position_id'],
                    'commission_parentage' => $request['commission_parentage'],
                    'commission_parentag_hiring_locked' => $request['commission_parentag_hiring_locked'],
                    'commission_structure_type' => $request['commission_structure_type'],
                    'commission_parentag_type_hiring_locked' => $request['commission_parentag_type_hiring_locked'],
                ]);
            } else {
                return response()->json(['status' => false, 'message' => 'Position id not Found!'], 400);
            }
        }
        $Position = Positions::where('id', $request['position_id'])->first();
        $Position->setup_status = 1;
        $Position->save();

        if (! empty($create)) {
            return response()->json([
                'ApiName' => 'position commission create',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $create,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'position commission create',
                'status' => true,
                'message' => 'Successfully',
                'data' => [],
            ], 200);
        }
    }

    public function PositionCommissionUpfront(Request $request): JsonResponse
    {

        $data = PositionCommission::where('position_id', $request['position_id'])->first();

        if ($request->upfront_status == 1) {
            $data1 = PositionCommissionUpfronts::where('position_id', $request['position_id'])->first();
            // dd($data1);
            if ($data1 == null) {

                $create = PositionCommissionUpfronts::create([
                    'position_id' => $request->position_id,
                    'status_id' => $request['upfront_status'],
                    'upfront_ammount' => $request['upfront_ammount'],
                    'upfront_ammount_locked' => $request['upfront_ammount_locked'],
                    'calculated_by' => $request['calculated_by'],
                    'calculated_locked' => $request['calculated_locked'],
                    'upfront_system' => $request['upfront_system'],
                    'upfront_system_locked' => $request['upfront_system_locked'],
                    'upfront_limit' => $request['upfront_limit'],
                    'upfront_limit_type' => $request['upfront_limit_type'] ?? null,
                ]);
            }
        }
        if (! empty($create)) {
            return response()->json([
                'ApiName' => 'position commission upfront ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $create,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'position commission upfront',
                'status' => true,
                'message' => 'Successfully',
                'data' => [],
            ], 200);
        }
    }

    public function PositionCommissionDeduction(Request $request): JsonResponse
    {

        $data = PositionCommission::where('position_id', $request['position_id'])->first();

        if ($request->deduction_status == 1) {
            $data2 = PositionCommissionDeduction::where('position_id', $request->position_id)->first();
            if ($data2 == null) {
                if ($request->deduction) {
                    $this->positionCommissionDeductionSetting = PositionCommissionDeductionSetting::find(1);
                    $this->positionCommissionDeductionSetting->status = $request->deduction_status;
                    $this->positionCommissionDeductionSetting->deducation_locked = $request->deduction_locked;
                    $this->positionCommissionDeductionSetting->update();

                }
                foreach ($request->deduction as $data1) {
                    if ($request->upfront_status == 1) {
                        PositionCommissionDeduction::create([
                            'deduction_setting_id' => 1,
                            'position_id' => $request->position_id,
                            'cost_center_id' => $data1['cost_center_id'],
                            'deduction_type' => $data1['deduction_type'],
                            'ammount_par_paycheck' => $data1['ammount_par_paycheck'],
                        ]);
                    } else {
                        return response()->json([
                            'ApiName' => 'Commission duducation',
                            'status' => true,
                            'message' => 'Successfully',
                            'data' => [],
                        ], 400);
                    }
                }
            }
        }
        $data4 = PositionsDeductionLimit::where('position_id', $request->position_id)->first();
        // dd($data4);
        if ($data4 == null) {
            PositionsDeductionLimit::create([
                'deduction_setting_id' => 1,
                'position_id' => $request->position_id,
                'status' => $request->deduction_status,
                'limit_type' => $request['limit_type'],
                'limit_ammount' => $request['limit_ammount'],
                'limit' => $request['limit'],
            ]);
        }
        if (! empty($create)) {
            return response()->json([
                'ApiName' => 'position commission deduction ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $create,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'position commission deduction',
                'status' => true,
                'message' => 'Successfully',
                'data' => [],
            ], 200);
        }
    }

    public function PositionCommissionOverride(Request $request): JsonResponse
    {

        $data5 = PositionOverride::where('position_id', $request->position_id)->first();

        if ($data5 == null) {
            $overrides = $request->override;
            if (isset($overrides)) {
                foreach ($overrides as $override) {
                    // Parse custom field type from 'custom_field_X' format for override
                    $overrideType = $override['type'];
                    $overrideCustomFieldId = null;

                    if ($overrideType && str_starts_with($overrideType, 'custom_field_')) {
                        $overrideCustomFieldId = (int) str_replace('custom_field_', '', $overrideType);
                        $overrideType = 'custom field';
                    }

                    // Determine which custom field column to use based on override_id
                    $directCustomFieldId = null;
                    $indirectCustomFieldId = null;
                    $officeCustomFieldId = null;

                    if ($overrideCustomFieldId) {
                        switch ($override['override_id']) {
                            case '1':
                            case 1:
                                $directCustomFieldId = $overrideCustomFieldId;
                                break;
                            case '2':
                            case 2:
                                $indirectCustomFieldId = $overrideCustomFieldId;
                                break;
                            case '3':
                            case 3:
                                $officeCustomFieldId = $overrideCustomFieldId;
                                break;
                        }
                    }

                    $create = PositionOverride::create([
                        'position_id' => $request['position_id'],
                        'override_id' => $override['override_id'],
                        'settlement_id' => $request['settlement_id'],
                        'status' => $override['status'],
                        'override_ammount' => $override['override_ammount'],
                        'override_ammount_locked' => $override['override_ammount_locked'],
                        'type' => $overrideType,
                        'override_type_locked' => $override['override_type_locked'],
                        'direct_custom_sales_field_id' => $directCustomFieldId,
                        'indirect_custom_sales_field_id' => $indirectCustomFieldId,
                        'office_custom_sales_field_id' => $officeCustomFieldId,
                    ]);
                }
            }

        }
        $data6 = PositionTierOverride::where('position_id', $request->position_id)->first();
        // dd($data6);
        if ($data6 == null) {
            if ($request->tier_override_status == 1) {
                PositionTierOverride::create([
                    'position_id' => $request['position_id'],
                    // 'position_id'     =>  $request['position_id'],
                    'tier_status' => $request['tier_override_status'],
                    'sliding_scale' => $request['sliding_scale'],
                    'sliding_scale_locked' => $request['sliding_scale_locked'],
                    'levels' => $request['levels'],
                    'level_locked' => $request['level_locked'],
                ]);
            } else {
                // return response()->json([
                //     'ApiName' => ' Tier Overrride',
                //     'status' => false,
                //     'message' => '',
                //     'data' => null,
                // ], 400);
            }
        }

        if (! empty($create)) {
            return response()->json([
                'ApiName' => 'position commission override ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $create,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'position commission override',
                'status' => true,
                'message' => 'Successfully',
                'data' => [],
            ], 200);
        }
    }

    public function PositionCommissionSettelement(Request $request): JsonResponse
    {

        $data7 = PositionReconciliations::where('position_id', $request->position_id)->first();
        // dd($data7);
        if ($data7 == null) {
            $create = PositionReconciliations::create(
                [
                    'position_id' => $request['position_id'],
                    'commission_withheld' => $request['commission_withheld'],
                    'commission_type' => $request['commission_type'],
                    'maximum_withheld' => $request['maximum_withheld'],
                    'override_settlement' => $request['override_settlement'],
                    'clawback_settlement' => $request['clawback_settlement'],
                    'status' => $request['reconciliation_status'],
                ]
            );
        }

        if (! empty($create)) {
            return response()->json([
                'ApiName' => 'position commission settelement ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $create,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'position commission  settelement',
                'status' => true,
                'message' => 'Successfully',
                'data' => [],
            ], 200);
        }
    }

    public function PositionCommissionPayfrequency(Request $request): JsonResponse
    {

        $data8 = PositionPayFrequency::where('position_id', $request->position_id)->first();
        // dd($data8);
        if ($data8 == null) {
            $create = PositionPayFrequency::create(
                [
                    'position_id' => $request['position_id'],
                    'frequency_type_id' => $request['frequency_type_id'],
                    'first_months' => $request['first_months'],
                    'day_of_week' => $request['day_of_week'],
                    'first_day' => $request['first_day'],
                    'day_of_months' => $request['day_of_months'],
                    'pay_period' => $request['pay_period'],
                    'monthly_per_days' => $request['monthly_per_days'],
                    'first_day_pay_of_manths' => $request['first_day_pay_of_manths'],
                    'second_pay_day_of_month' => $request['second_pay_day_of_month'],
                    'deadline_to_run_payroll' => $request['deadline_to_run_payroll'],
                    'first_pay_period_ends_on' => $request['first_pay_period_ends_on'],
                ]
            );
        }

        if (! empty($create)) {
            return response()->json([
                'ApiName' => 'position commission Payfrequency ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $create,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'position commission Payfrequency',
                'status' => true,
                'message' => 'Successfully',
                'data' => [],
            ], 200);
        }
    }

    public function deletePositionDeduction($id): JsonResponse
    {
        $positionDeduction = PositionCommissionDeduction::find($id);

        if (! $positionDeduction) {
            return response()->json([
                'ApiName' => 'delete position deduction',
                'status' => false,
                'message' => 'Record not found.',
            ], 404);
        }

        // Capture attributes before deletion
        $positionDeductionAttributes = $positionDeduction->toArray();

        $delete = $positionDeduction->delete();

        if ($delete) {
            activity()
                ->performedOn($positionDeduction)
                ->causedBy(auth()->user())
                ->withProperties(['attributes' => $positionDeductionAttributes])
                ->event('deleted')
                ->log('Deleted position commission deduction');

            $duplicates = DB::table('activity_log')
                ->select(DB::raw('MIN(id) as id'))
                ->where('log_name', 'default')
                ->where('event', 'deleted')
                ->groupBy('log_name', 'event', 'subject_id', 'causer_id', 'created_at')
                ->pluck('id');

            DB::table('activity_log')
                ->where('log_name', 'default')
                ->where('event', 'deleted')
                ->whereNotIn('id', $duplicates)
                ->delete();

            return response()->json([
                'ApiName' => 'delete position deduction',
                'status' => true,
                'message' => 'Successfully deleted.',
                'data' => $id,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'delete position deduction',
                'status' => false,
                'message' => 'Failed to delete the record.',
            ], 500);
        }
    }
    // code by nikhil end
}
