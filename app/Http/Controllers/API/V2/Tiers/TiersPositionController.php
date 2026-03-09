<?php

namespace App\Http\Controllers\API\V2\Tiers;

use App\Helpers\CustomSalesFieldHelper;
use App\Http\Controllers\API\V2\Sales\BaseController;
use App\Jobs\EmploymentPackage\ApplyHistoryOnUsersV2Job;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use Laravel\Pennant\Feature;
use App\Models\CostCenter;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\OnboardingEmployees;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionPayFrequency;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionsDeductionLimit;
use App\Models\PositionTier;
use App\Models\PositionTierOverride;
use App\Models\PositionWage;
use App\Models\TiersPositionCommission;
use App\Models\TiersPositionOverrides;
use App\Models\TiersPositionUpfront;
use App\Models\User;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use App\Models\Wage;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TiersPositionController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $positionId = [1];
            if (! in_array(config('app.domain_name'), config('global_vars.CORE_POSITION_DISPLAY'))) {
                $positionId = [1, 2, 3];
            }

            $query = Positions::with('childPositionsNew', 'group', 'payFrequency.frequencyType', 'positionDepartmentDetail')
                ->leftJoin('position_products', 'position_products.position_id', '=', 'positions.id')
                ->leftJoin('users', 'users.sub_position_id', '=', 'positions.id')
                ->leftJoin('products', 'products.id', '=', 'position_products.product_id')
                ->leftJoin('position_pay_frequencies', 'position_pay_frequencies.position_id', '=', 'positions.id')
                ->leftJoin('departments', 'departments.id', '=', 'positions.department_id')
                ->leftJoin('frequency_types', 'frequency_types.id', '=', 'position_pay_frequencies.frequency_type_id')
                ->leftJoin('group_masters', 'group_masters.id', '=', 'positions.group_id')
                ->select(
                    'positions.*',
                    'departments.name as department_name',
                    'frequency_types.name as freq_name',
                    'group_masters.name as group_name',
                    'position_pay_frequencies.frequency_type_id as freq_id',
                    DB::raw('COUNT(DISTINCT CASE WHEN position_products.deleted_at IS NULL THEN position_products.product_id END) as product_count'),
                    DB::raw('COUNT(CASE WHEN users.dismiss = 0 THEN users.position_id END) as peoples_count')
                );
            if ($request->filled('pay_frequency_filter')) {
                $query->where('frequency_types.id', $request->pay_frequency_filter);
            }
            if ($request->filled('department')) {
                // $query->where('departments.name', $request->department);
                $query->where('positions.department_id', $request->department);
            }
            if ($request->filled('override_settelement')) {
                $query->join('position_reconciliations', 'position_reconciliations.position_id', 'positions.id')
                    ->where('position_reconciliations.override_settlement', $request->override_settelement);
            }
            if ($request->filled('worker_type')) {
                $query->where('positions.worker_type', $request->worker_type);
            }
            if ($request->filled('permission_group')) {
                $query->where('group_masters.id', $request->permission_group);
            }
            if ($request->filled('eligible_products')) {
                $query->where('products.id', $request->eligible_products);
            }
            if ($request->filled('search_filter')) {
                $searchTerm = $request->input('search_filter');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('positions.position_name', 'LIKE', '%'.$searchTerm.'%');
                });
            }
            $query->whereNotIn('positions.id', $positionId);
            $query->groupBy('positions.id', 'departments.name', 'frequency_types.name', 'group_masters.name', 'position_pay_frequencies.frequency_type_id');
            $positionData = $query->paginate($request->input('per_page', $request->input('perpage', 10)));

            $response_data = [];
            $positions = $positionData->getCollection();
            foreach ($positions as $position) {
                $data = $this->recursionPosition($position);
                if (is_array($data) && count($data) != 0) {
                    foreach ($data as $data) {
                        $check = collect($response_data)->where('id', $data['id'])->values();
                        if (count($check) == 0) {
                            $response_data[] = $data;
                        }
                    }
                }
            }
            $data = $positionData->toArray();
            $data['data'] = $response_data;

            return response()->json([
                'ApiName' => 'position product api',
                'status' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'products',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    protected function recursionPosition($position, &$data = [])
    {
        $childPositions = $position->childPositionsNew;
        if (count($childPositions) != 0) {
            $data[] = $this->positionFormatting($position);
            foreach ($childPositions as $child) {
                $this->recursionPosition($child, $data);
            }
        } else {
            $data[] = $this->positionFormatting($position);
        }

        return $data;
    }

    protected function positionFormatting($position)
    {
        $data = PositionProduct::with('product')->where('position_id', $position->id)->get();
        $productData = [];

        foreach ($data as $products) {
            $productData[] = [
                'id' => $products->product_id,
                'name' => $products->product->name,
                'product_id' => $products->product->product_id,
                'milestone_schema_id' => $products->product->milestone_schema_id,
                'clawback_exempt_on_ms_trigger_id' => $products->product->clawback_exempt_on_ms_trigger_id,
                'effective_date' => $products->product->effective_date,
                'status' => $products->product->status,
            ];
        }

        $peoplesCount = User::where(['sub_position_id' => $position->id, 'dismiss' => 0])->count();

        return [
            'id' => $position->id,
            'status' => $position->setup_status,
            'position' => isset($position->position_name) ? $position->position_name : null,
            'worker_type' => isset($position->worker_type) ? $position->worker_type : null,
            // 'people' => $position->peoples_count,
            'people' => $peoplesCount,
            'group_id' => isset($position->group->id) ? $position->group->id : null,
            'group_name' => isset($position->group->name) ? $position->group->name : null,
            'frequency_type_id' => isset($position->payFrequency->frequencyType->id) ? $position->payFrequency->frequencyType->id : null,
            'pay_frequency' => isset($position->payFrequency->frequencyType->name) ? $position->payFrequency->frequencyType->name : null,
            'department_id' => isset($position->positionDepartmentDetail->id) ? $position->positionDepartmentDetail->id : null,
            'Department' => isset($position->positionDepartmentDetail->name) ? $position->positionDepartmentDetail->name : null,
            'Product_count' => isset($position->product_count) ? $position->product_count : 0,
            'product_details' => $productData,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $departmentId = $request->input('department_id');
            $validationArr = [
                'position_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('positions', 'position_name')
                        ->where(function ($query) use ($departmentId) {
                            $query->where('department_id', $departmentId);
                        })
                        ->whereNotIn('id', [1, 2, 3]),
                ],
                'product_id' => 'required|array|min:1',
                'worker_type' => 'required',
                'pay_frequency' => 'required',
                'permission_group_id' => 'required',
                'department_id' => 'required',
                'main_role' => 'required',
            ];

            if (in_array(config('app.domain_name'), ['hawx', 'sstage', 'milestone'])) {
                $validationArr['offer_letter_template_id'] = 'nullable|array';
            }

            $validator = validator::make($request->all(), $validationArr);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $parentId = 2;
            if ($request->main_role == 3) {
                $parentId = 3;
            }

            $positionDataForCreate = [
                'position_name' => $request->position_name,
                'worker_type' => $request->worker_type,
                'department_id' => $request->department_id,
                'parent_id' => $parentId,
                'org_parent_id' => $request->parent_position_id,
                'group_id' => $request->permission_group_id,
                'is_selfgen' => $request->main_role,
                // 'offer_letter_template_id' => $request->offer_letter_template_id,
                'can_act_as_both_setter_and_closer' => $request->main_role,
            ];
            if ($request->filled('offer_letter_template_id')) {
                if (isset($request->offer_letter_template_id[0]) && in_array(config('app.domain_name'), ['hawx', 'sstage', 'milestone'])) {
                    $positionDataForCreate['offer_letter_template_id'] = $request->offer_letter_template_id[0];
                } else {
                    $positionDataForCreate['offer_letter_template_id'] = $request->offer_letter_template_id;
                }
            }

            if (isset($positionDataForCreate['offer_letter_template_id']) && ! $positionDataForCreate['offer_letter_template_id']) {
                unset($positionDataForCreate['offer_letter_template_id']);
            }
            // dd($positionDataForCreate);
            $data = Positions::create($positionDataForCreate);
            $positionId = $data->id;
            Positions::where('id', $positionId)->update(['order_by' => $positionId]);

            foreach ($request->product_id as $product) {
                PositionProduct::create([
                    'position_id' => $positionId,
                    'product_id' => $product,
                ]);
            }

            Wage::create([
                'position_id' => $positionId,
            ]);

            PositionPayFrequency::create([
                'position_id' => $positionId,
                'frequency_type_id' => $request->pay_frequency,
            ]);

            if ($request->filled('offer_letter_template_id')) {

                if (in_array(config('app.domain_name'), ['hawx', 'sstage', 'milestone'])) {

                    NewSequiDocsTemplatePermission::where([
                        'position_id' => $positionId,
                        'position_type' => 'receipient',
                        'category_id' => 1,
                    ])->delete();

                    foreach ($request['offer_letter_template_id'] as $template_id) {
                        NewSequiDocsTemplatePermission::create([
                            'template_id' => $template_id,
                            'category_id' => 1,
                            'position_id' => $positionId,
                            'position_type' => 'receipient',
                        ]);
                    }
                } else {

                    NewSequiDocsTemplatePermission::Create([
                        'template_id' => $request->offer_letter_template_id,
                        'category_id' => 1,
                        'position_id' => $positionId,
                        'position_type' => 'receipient',
                    ]);
                }
            }

            // code add for tiers type //05-feb
            $companySettingTiers = CompanySetting::where('type', 'tier')->first();
            if ($companySettingTiers) {
                $statuses = [
                    'commission' => [
                        'status' => $request->commission_status,
                        'schema_id' => $request->tiers_commission_schema_id,
                        'advancement' => $request->tier_commission_advancement,
                        'type' => $request->tier_commission_type,
                    ],
                    'upfront' => [
                        'status' => $request->upfront_status,
                        'schema_id' => $request->tiers_upfront_schema_id,
                        'advancement' => $request->tier_upfront_advancement,
                        'type' => $request->tier_upfront_type,
                    ],
                    'override' => [
                        'status' => $request->override_status,
                        'schema_id' => $request->tiers_override_schema_id,
                        'advancement' => $request->tier_override_advancement,
                        'type' => $request->tier_override_type,
                    ],
                ];

                foreach ($statuses as $type => $data) {
                    if ($data['status']) {
                        PositionTier::create([
                            'position_id' => $positionId,
                            'tiers_schema_id' => $data['schema_id'],
                            'tier_advancement' => $data['advancement'],
                            'status' => 1,
                            'type' => $data['type'],
                        ]);
                    }
                }
            }

            // end here code for tiers type

            DB::commit();

            return response()->json([
                'ApiName' => 'add-position',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => [],
            ]);
        } catch (Exception $e) {
            dd($e);
            DB::rollBack();

            return response()->json([
                'ApiName' => 'add-position -products',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function edit($id): JsonResponse
    {
        try {
            $position = Positions::with('product', 'payFrequency', 'allAssociatedOfferLettersWithTemplate')->withCount('peoples')->find($id);
            if (! $position) {
                return response()->json([
                    'status' => false,
                    'ApiName' => 'edit-position -products',
                    'message' => 'Position not found!!',
                ], 400);
            }

            $data = [
                'id' => $position->id,
                'position_name' => $position->position_name,
                'worker_type' => $position->worker_type,
                'pay_frequency' => $position?->payFrequency?->frequency_type_id,
                'main_role' => $position->is_selfgen,
                'permission_group_id' => $position->group_id,
                'department_id' => $position->department_id,
                'parent_position_id' => $position->org_parent_id,
                'offer_letter_template_id' => $position->offer_letter_template_id,
                'product' => $position->product,
                'people' => $position->peoples_count,
                'offer_letter' => isset($position->allAssociatedOfferLettersWithTemplate) ? $position->allAssociatedOfferLettersWithTemplate : [],
            ];

            return response()->json([
                'ApiName' => 'edit-position -products',
                'status' => true,
                'data' => $data,
                'message' => 'Position data!!',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add-position -products',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $departmentId = $request->input('department_id');
            $validationArr = [
                'position_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('positions', 'position_name')
                        ->where(function ($query) use ($departmentId) {
                            $query->where('department_id', $departmentId);
                        })->whereNotIn('id', [1, 2, 3])->ignore($id),
                ],
                'product_id' => 'required|array|min:1',
                'permission_group_id' => 'required',
                'department_id' => 'required',
                'commission' => 'nullable|array',
                'commission.*.product_id' => 'required',
                'commission.*.to_all_users' => 'required|in:0,1',
                'commission.*.commission_status' => 'required|in:0,1',
                'commission.*.data' => 'required_if:commission.*.commission_status,1',
                'upfront' => 'nullable|array',
                'upfront.*.product_id' => 'required',
                'upfront.*.to_all_users' => 'required|in:0,1',
                'upfront.*.upfront_status' => 'required|in:0,1',
                'upfront.*.data' => 'required_if:upfront.*.upfront_status,1',
                'overrides' => 'nullable|array',
                'overrides.*.product_id' => 'required',
                'overrides.*.to_all_users' => 'required|in:0,1',
                'overrides.*.status' => 'required|in:0,1',
                'overrides.*.override' => 'required_if:overrides.*.override,1',
                'settlement' => 'nullable|array',
                'settlement.*.product_id' => 'required',
                'settlement.*.to_all_users' => 'required|in:0,1',
                'settlement.*.status' => 'required|in:0,1',
            ];

            if (in_array(config('app.domain_name'), ['hawx', 'sstage', 'milestone'])) {
                $validationArr['offer_letter_template_id'] = 'nullable|array';
            }

            $validator = validator::make($request->all(), $validationArr);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $position = Positions::where('id', $id)->first();
            if (! $position) {
                return response()->json([
                    'ApiName' => 'add-position',
                    'status' => false,
                    'message' => 'Position not found!!',
                    'data' => [],
                ], 400);
            }

            $currentProduct = PositionProduct::where(['position_id' => $id])->pluck('product_id')->toArray();
            $requestProduct = $request->product_id;
            $differences = array_diff($requestProduct, $currentProduct);
            $differences2 = array_diff($currentProduct, $requestProduct);
            if (count($differences) != 0) {
                PositionProduct::where('position_id', $id)->delete();
                foreach ($request->product_id as $product) {
                    PositionProduct::create([
                        'position_id' => $id,
                        'product_id' => $product,
                    ]);
                }
                $this->updatePosition($id, $request);
                $this->updateUserHistories($id, $request);
            } elseif ($differences2 != 0) {
                PositionProduct::where('position_id', $id)->delete();
                $this->updateOrganizationData($id);
                foreach ($request->product_id as $product) {
                    PositionProduct::create([
                        'position_id' => $id,
                        'product_id' => $product,
                    ]);
                }
            }

            $position->position_name = $request->position_name;
            $position->department_id = $request->department_id;
            $position->org_parent_id = $request->parent_position_id; // $request->org_parent_id changed
            $position->group_id = $request->permission_group_id;
            if (isset($request->offer_letter_template_id[0]) && ! in_array(config('app.domain_name'), ['hawx', 'sstage', 'milestone'])) {
                $position->offer_letter_template_id = $request->offer_letter_template_id[0];
            }
            $position->save();

            if ($request->filled('offer_letter_template_id')) {
                if (in_array(config('app.domain_name'), ['hawx', 'sstage', 'milestone'])) {

                    NewSequiDocsTemplatePermission::where([
                        'position_id' => $id,
                        'position_type' => 'receipient',
                        'category_id' => 1,
                    ])->delete();

                    foreach ($request['offer_letter_template_id'] as $template_id) {
                        NewSequiDocsTemplatePermission::create([
                            'template_id' => $template_id,
                            'category_id' => 1,
                            'position_id' => $id,
                            'position_type' => 'receipient',
                        ]);
                    }
                } else {
                    NewSequiDocsTemplatePermission::updateOrCreate(['position_id' => $id, 'position_type' => 'receipient', 'category_id' => 1], [
                        'template_id' => $request->offer_letter_template_id,
                        'category_id' => 1,
                        'position_id' => $id,
                        'position_type' => 'receipient',
                    ]);
                }
            } else {
                NewSequiDocsTemplatePermission::where(['position_id' => $id, 'position_type' => 'receipient', 'category_id' => 1])->delete();
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'add-position',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => [],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'add-position -products',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    protected function updatePosition($id, Request $request)
    {
        $position = Positions::find($id);
        $positionProductId = PositionProduct::where('position_id', $id)->pluck('product_id')->toArray();
        if (! empty($request->commission)) {
            foreach ($request->commission as $commissions) {
                foreach ($commissions['data'] as $commission) {
                    $createCommission = PositionCommission::create([
                        'position_id' => $id,
                        'core_position_id' => $commission['core_position_id'],
                        'product_id' => $commissions['product_id'],
                        'self_gen_user' => $commission['self_gen_user'],
                        'commission_parentage' => $commission['commission_parentage'],
                        'commission_amount_type' => $commission['commission_amount_type'],
                        'commission_status' => @$commissions['commission_status'] ?? 0,
                        'commission_parentag_hiring_locked' => @$commission['commission_parentag_hiring_locked'] ?? 0,
                        'commission_amount_type_locked' => @$commission['commission_amount_type_locked'] ?? 0,
                        'commission_structure_type' => @$commission['commission_structure_type'] ?? 0,
                        'commission_parentag_type_hiring_locked' => @$commission['commission_parentag_type_hiring_locked'] ?? 0,
                        'tiers_id' => @$commission['tiers_id'] ?? null,
                        'tiers_advancement' => @$commission['tiers_advancement'] ?? null,
                        'tiers_hiring_locked' => @$commission['tiers_hiring_locked'] ?? 0,
                    ]);

                    $lastid = $createCommission->id;
                    $tiers_id = isset($commission['tiers_id']) && $commission['tiers_id'] != '' ? $commission['tiers_id'] : 0;
                    $range = isset($commission['tiers_range']) && $commission['tiers_range'] != '' ? $commission['tiers_range'] : '';
                    if ($tiers_id > 0) {
                        if (is_array($range) && ! empty($range)) {
                            foreach ($range as $rang) {
                                TiersPositionCommission::create([
                                    'position_id' => $id,
                                    'position_commission_id' => $lastid,
                                    'product_id' => @$commissions['product_id'],
                                    'tiers_schema_id' => @$commission['tiers_id'] ?? null,
                                    'tiers_advancement' => @$commission['tiers_advancement'] ?? null,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'commission_value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }
            PositionCommission::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
            TiersPositionCommission::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
        }

        if (! empty($request->upfront)) {
            foreach ($request->upfront as $upfronts) {
                foreach ($upfronts['data'] as $upfront) {
                    foreach ($upfront['schemas'] as $schema) {
                        $createUpfront = PositionCommissionUpfronts::create([
                            'position_id' => $id,
                            'core_position_id' => $upfront['core_position_id'],
                            'product_id' => $upfronts['product_id'],
                            'milestone_schema_id' => $upfront['milestone_id'],
                            'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                            'self_gen_user' => $upfront['self_gen_user'],
                            'status_id' => $upfronts['upfront_status'],
                            'upfront_ammount' => $schema['upfront_ammount'],
                            'upfront_ammount_locked' => @$schema['upfront_ammount_locked'] ?? 0,
                            'calculated_by' => $schema['calculated_by'],
                            'calculated_locked' => @$schema['calculated_locked'] ?? 0,
                            'upfront_status' => @$upfronts['upfront_status'] ?? 0,
                            // 'upfront_system' => $schema['upfront_system'],
                            'upfront_system' => @$schema['upfront_system'] ?? 'Fixed',
                            'upfront_system_locked' => @$schema['upfront_system_locked'] ?? 0,
                            'upfront_limit' => null, // $schema['upfront_limit'],
                            'tiers_id' => @$schema['tiers_id'] ?? null,
                            'tiers_advancement' => @$schema['tiers_advancement'] ?? null,
                            'tiers_hiring_locked' => @$schema['tiers_hiring_locked'] ?? 0,
                        ]);

                        $lastid = $createUpfront->id;
                        $tiers_id = isset($schema['tiers_id']) && $schema['tiers_id'] != '' ? $schema['tiers_id'] : 0;
                        $range = isset($schema['tiers_range']) && $schema['tiers_range'] != '' ? $schema['tiers_range'] : '';
                        if ($tiers_id > 0) {
                            if (is_array($range) && ! empty($range)) {
                                foreach ($range as $rang) {
                                    TiersPositionUpfront::create([
                                        'position_id' => $id,
                                        'position_upfront_id' => $lastid,
                                        'product_id' => @$upfronts['product_id'],
                                        'tiers_schema_id' => @$schema['tiers_id'] ?? null,
                                        'tiers_advancement' => @$schema['tiers_advancement'] ?? null,
                                        'tiers_levels_id' => $rang['id'] ?? null,
                                        'upfront_value' => $rang['value'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            PositionCommissionUpfronts::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
            TiersPositionUpfront::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
        }

        if (! empty($request->overrides)) {
            foreach ($request->overrides as $overrides) {
                foreach ($overrides['override'] as $override) {
                    $createOverride = PositionOverride::create([
                        'position_id' => $id,
                        'product_id' => $overrides['product_id'],
                        'override_id' => $override['override_id'],
                        'settlement_id' => @$override['settlement_id'] ?? 0,
                        'override_ammount' => $override['override_ammount'],
                        'override_ammount_locked' => @$override['override_ammount_locked'] ?? 0,
                        'type' => $override['type'],
                        'override_type_locked' => $override['override_type_locked'],
                        'status' => $override['status'],
                        'tiers_id' => @$override['tiers_id'] ?? null,
                        'tiers_advancement' => @$override['tiers_advancement'] ?? null,
                        'tiers_hiring_locked' => @$override['tiers_hiring_locked'] ?? 0,
                    ]);

                    $lastid = $createOverride->id;
                    $tiers_id = isset($override['tiers_id']) && $override['tiers_id'] != '' ? $override['tiers_id'] : 0;
                    $range = isset($override['tiers_range']) && $override['tiers_range'] != '' ? $override['tiers_range'] : '';
                    if ($tiers_id > 0) {
                        if (is_array($range) && ! empty($range)) {
                            foreach ($range as $rang) {
                                TiersPositionOverrides::create([
                                    'position_id' => $id,
                                    'position_overrides_id' => $lastid,
                                    'product_id' => @$overrides['product_id'],
                                    'tiers_schema_id' => @$override['tiers_id'] ?? null,
                                    'tiers_advancement' => @$override['tiers_advancement'] ?? null,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'override_value' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }
            PositionOverride::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
            TiersPositionOverrides::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
        }

        if (! empty($request->settlement)) {
            foreach ($request->settlement as $settlement) {
                PositionReconciliations::create([
                    'position_id' => $id,
                    'product_id' => $settlement['product_id'],
                    'commission_withheld' => @$settlement['commission_withheld'],
                    'commission_type' => @$settlement['commission_type'],
                    'commission_withheld_locked' => @$settlement['commission_withheld_locked'] ?? 0,
                    'commission_type_locked' => @$settlement['commission_type_locked'] ?? 0,
                    'maximum_withheld' => @$settlement['maximum_withheld'] ? $settlement['maximum_withheld'] : 0,
                    'override_settlement' => @$settlement['override_settlement'],
                    'clawback_settlement' => @$settlement['clawback_settlement'],
                    'stack_settlement' => @$settlement['stack_settlement'],
                    'status' => $settlement['status'],
                ]);
            }
            PositionReconciliations::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
        }

        // $positionProductId = PositionProduct::where('position_id', $id)->pluck('product_id')->toArray();
        // PositionCommission::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
        // PositionCommissionUpfronts::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
        // PositionOverride::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
        // PositionReconciliations::where('position_id', $id)->whereNotIn('product_id', $positionProductId)->delete();
    }

    protected function updateUserHistories($id, Request $request)
    {
        $date = date('Y-m-d');
        $subQuery = UserOrganizationHistory::select(
            'id',
            'user_id',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
        )->where('effective_date', '<=', $date);

        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())
            ->select('user_id', 'effective_date')
            ->where('rn', 1)->get();

        $closestDates = $results->map(function ($result) {
            return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
        });

        $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
            foreach ($closestDates as $closestDate) {
                $query->orWhere(function ($q) use ($closestDate) {
                    $q->where('user_id', $closestDate['user_id'])
                        ->where('effective_date', $closestDate['effective_date']);
                });
            }
        })->where(function ($query) use ($id) {
            $query->where('sub_position_id', $id);
        })->groupBy('user_id')->pluck('user_id')->toArray();

        $position = Positions::find($id);
        $positionProducts = PositionProduct::where('position_id', $id)->get();
        if (count($userIdArr) > 0) {
            foreach ($userIdArr as $userId) {
                foreach ($positionProducts as $positionProduct) {
                    UserOrganizationHistory::create([
                        'user_id' => $userId,
                        'updater_id' => auth()->user()->id,
                        'position_id' => $position->parent_id,
                        'sub_position_id' => $id,
                        'product_id' => $positionProduct['product_id'],
                        'effective_date' => $date,
                    ]);
                }
                if (! empty($request->commission)) {
                    foreach ($request->commission as $commissions) {
                        if ($commissions['to_all_users'] == '1') {
                            foreach ($commissions['data'] as $commission) {
                                UserCommissionHistory::create([
                                    'user_id' => $userId,
                                    'updater_id' => auth()->user()->id,
                                    'product_id' => $commissions['product_id'],
                                    'self_gen_user' => $commission['self_gen_user'],
                                    'commission' => $commission['commission_parentage'],
                                    'commission_type' => $commission['commission_amount_type'],
                                    'commission_effective_date' => $commissions['effective_date'],
                                    'position_id' => $position->parent_id,
                                    'core_position_id' => $commission['core_position_id'],
                                    'sub_position_id' => $id,
                                ]);
                            }
                        }
                    }
                }
                if (! empty($request->upfront)) {
                    foreach ($request->upfront as $upfronts) {
                        if ($upfronts['to_all_users'] == '1') {
                            foreach ($upfronts['data'] as $upfront) {
                                foreach ($upfront['schemas'] as $schema) {
                                    UserUpfrontHistory::create([
                                        'user_id' => $userId,
                                        'updater_id' => auth()->user()->id,
                                        'product_id' => $upfronts['product_id'],
                                        'milestone_schema_id' => $upfront['milestone_id'],
                                        'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                                        'self_gen_user' => $upfront['self_gen_user'],
                                        'upfront_pay_amount' => $schema['upfront_ammount'],
                                        'upfront_sale_type' => $schema['calculated_by'],
                                        'upfront_effective_date' => $upfronts['effective_date'],
                                        'position_id' => $position->parent_id,
                                        'core_position_id' => $upfront['core_position_id'],
                                        'sub_position_id' => $id,
                                    ]);
                                }
                            }
                        }
                    }
                }
                if (! empty($request->overrides)) {
                    foreach ($request->overrides as $overrides) {
                        if ($overrides['to_all_users'] == '1') {
                            $direct = null;
                            $directType = null;
                            $inDirect = null;
                            $inDirectType = null;
                            $office = null;
                            $officeType = null;
                            $officeStack = 0;
                            foreach ($overrides['override'] as $override) {
                                if ($override['status']) {
                                    if ($override['override_id'] == '1') {
                                        $direct = $override['override_ammount'];
                                        $directType = $override['type'];
                                    } elseif ($override['override_id'] == '2') {
                                        $inDirect = $override['override_ammount'];
                                        $inDirectType = $override['type'];
                                    } elseif ($override['override_id'] == '3') {
                                        $office = $override['override_ammount'];
                                        $officeType = $override['type'];
                                    } elseif ($override['override_id'] == '4') {
                                        $officeStack = $override['override_ammount'];
                                    }
                                }
                            }

                            UserOverrideHistory::create([
                                'user_id' => $userId,
                                'updater_id' => auth()->user()->id,
                                'product_id' => $overrides['product_id'],
                                'direct_overrides_amount' => $direct,
                                'direct_overrides_type' => $directType,
                                'indirect_overrides_amount' => $inDirect,
                                'indirect_overrides_type' => $inDirectType,
                                'office_overrides_amount' => $office,
                                'office_overrides_type' => $officeType,
                                'office_stack_overrides_amount' => $officeStack,
                                'override_effective_date' => $overrides['effective_date'],
                                'position_id' => $position->parent_id,
                                'sub_position_id' => $id,
                            ]);
                        }
                    }
                }
                if (! empty($request->settlement)) {
                    foreach ($request->settlement as $settlement) {
                        if ($settlement['to_all_users'] == '1') {
                            UserWithheldHistory::create([
                                'user_id' => $userId,
                                'updater_id' => auth()->user()->id,
                                'product_id' => $settlement['product_id'],
                                'withheld_amount' => @$settlement['commission_withheld'] ?? 0,
                                'withheld_type' => @$settlement['commission_type'],
                                'withheld_effective_date' => $settlement['effective_date'],
                                'position_id' => $position->parent_id,
                                'sub_position_id' => $id,
                            ]);
                        }
                    }
                }
            }
        }
    }

    protected function updateOrganizationData($id)
    {
        $date = date('Y-m-d');
        $subQuery = UserOrganizationHistory::select(
            'id',
            'user_id',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
        )->where('effective_date', '<=', $date);

        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())
            ->select('user_id', 'effective_date')
            ->where('rn', 1)->get();

        $closestDates = $results->map(function ($result) {
            return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
        });

        $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
            foreach ($closestDates as $closestDate) {
                $query->orWhere(function ($q) use ($closestDate) {
                    $q->where('user_id', $closestDate['user_id'])
                        ->where('effective_date', $closestDate['effective_date']);
                });
            }
        })->where(function ($query) use ($id) {
            $query->where('sub_position_id', $id);
        })->pluck('user_id')->toArray();

        $position = Positions::find($id);
        $positionProducts = PositionProduct::where('position_id', $id)->get();
        foreach ($userIdArr as $userId) {
            foreach ($positionProducts as $positionProduct) {
                UserOrganizationHistory::create([
                    'user_id' => $userId,
                    'updater_id' => auth()->user()->id,
                    'position_id' => $position->parent_id,
                    'sub_position_id' => $id,
                    'product_id' => $positionProduct['product_id'],
                    'effective_date' => $date,
                ]);
            }
        }
    }

    public function delete($id): JsonResponse
    {
        if ($id == 1 || $id == 2 || $id == 3) {
            return response()->json(['status' => false, 'message' => 'Core position can not be removed!!'], 400);
        }

        if (Positions::where('id', $id)->delete()) {
            return response()->json(['status' => true, 'message' => 'Position deleted successfully!!']);
        }

        return response()->json(['status' => false, 'message' => 'Position not found!!'], 400);
    }

    public function editPositionAll($id)
    {
        try {
            $data = Positions::with([
                'product.productName',
                'Commission',
                'Upfront.milestoneHistory.milestone',
                'Upfront.milestoneTrigger',
                'deductionName.costCenter',
                'deductionLimit',
                'deductionSetting',
                'Override.overridesDetail',
                'reconciliation',
                'payFrequency.frequencyType',
                'position_wage',
            ])->where('id', $id)->first();

            if (! $data) {
                return response()->json(['status' => false, 'message' => 'Position id is not available.'], 400);
            }
            
            // Check if Custom Sales Fields feature is enabled (for display formatting, using cached helper)
            $isCustomFieldsEnabledForTiers = CustomSalesFieldHelper::isFeatureEnabled();

            $commissionData = [];
            if (count($data->Commission) != 0) {
                $commissionData = $data->Commission->groupBy('product_id')->map(
                    fn ($groupByProduct) => $groupByProduct->groupBy('commission_status')->map(fn ($groupByStatus) => [
                        'product_id' => $groupByStatus->first()->product_id,
                        'commission_status' => $groupByStatus->first()->commission_status,
                        'data' => $groupByStatus->map(fn ($item) => [
                            'core_position_id' => $item->core_position_id,
                            'self_gen_user' => $item->self_gen_user,
                            'commission_parentage' => $item->commission_parentage,
                            // Only use custom_field_X format when feature is enabled
                            'commission_amount_type' => ($isCustomFieldsEnabledForTiers && $item->commission_amount_type === 'custom field' && $item->custom_sales_field_id) ? 'custom_field_' . $item->custom_sales_field_id : $item->commission_amount_type,
                            'custom_sales_field_id' => ($isCustomFieldsEnabledForTiers && $item->commission_amount_type === 'custom field') ? $item->custom_sales_field_id : null,
                            'commission_parentag_hiring_locked' => $item->commission_parentag_hiring_locked,
                            'commission_amount_type_locked' => $item->commission_amount_type_locked,
                            'commission_structure_type' => $item->commission_structure_type,
                            'commission_parentag_type_hiring_locked' => $item->commission_parentag_type_hiring_locked,
                            'tiers_id' => $item->tiers_id,
                            'tiers_advancement' => $item->tiers_advancement,
                            'tiers_hiring_locked' => $item->tiers_hiring_locked,
                            'tiers_range' => $item->tiersRange->map(function ($range) {
                                return [
                                    'id' => $range->tiers_levels_id,
                                    'value' => $range->commission_value,
                                ];
                            })->values(),
                        ])->values(),
                    ])->values()
                )->flatten(1);
            }

            $upfrontData = [];
            if (count($data->Upfront) != 0) {
                $upfrontData = $data->Upfront->groupBy('product_id')->map(
                    fn ($groupByProduct) => $groupByProduct
                        ->groupBy('upfront_status')
                        ->map(fn ($groupByStatus) => [
                            'product_id' => $groupByStatus->first()->product_id,
                            'upfront_status' => $groupByStatus->first()->upfront_status,
                            'data' => $groupByStatus->groupBy('core_position_id')->map(fn ($groupByCorePosition) => [
                                'milestone_id' => $groupByCorePosition->first()->milestone_schema_id,
                                'core_position_id' => $groupByCorePosition->first()->core_position_id,
                                'self_gen_user' => $groupByCorePosition->first()->self_gen_user,
                                'schemas' => $groupByCorePosition->groupBy('milestone_schema_id')
                                    ->flatMap(fn ($groupByMilestone) => $groupByMilestone->map(fn ($item) => [
                                        'milestone_schema_trigger_id' => $item->milestone_schema_trigger_id,
                                        'upfront_ammount' => (string) $item->upfront_ammount,
                                        'upfront_ammount_locked' => (string) $item->upfront_ammount_locked,
                                        // Only use custom_field_X format when feature is enabled
                                        'calculated_by' => ($isCustomFieldsEnabledForTiers && $item->calculated_by === 'custom field' && $item->custom_sales_field_id) ? 'custom_field_' . $item->custom_sales_field_id : $item->calculated_by,
                                        'custom_sales_field_id' => ($isCustomFieldsEnabledForTiers && $item->calculated_by === 'custom field') ? $item->custom_sales_field_id : null,
                                        'calculated_locked' => (string) $item->calculated_locked,
                                        'upfront_system' => $item->upfront_system,
                                        'upfront_system_locked' => (string) $item->upfront_system_locked,
                                        'upfront_limit' => (string) $item->upfront_limit,
                                        'tiers_id' => $item->tiers_id,
                                        'tiers_advancement' => $item->tiers_advancement,
                                        'tiers_hiring_locked' => $item->tiers_hiring_locked,
                                        'tiers_range' => $item->tiersRange->map(function ($range) {
                                            return [
                                                'id' => $range->tiers_levels_id,
                                                'value' => $range->upfront_value,
                                            ];
                                        })->values(),
                                    ])->values())->values(),
                            ])->values(),
                        ])->values()
                )->values()->flatten(1);
            }

            $settlement = [];
            if (count($data->reconciliation) != 0) {
                $settlement = $data->reconciliation->map(function ($item) {
                    return [
                        'status' => $item['status'],
                        'product_id' => (string) $item['product_id'],
                        'commission_withheld' => (string) $item['commission_withheld'],
                        'commission_type' => $item['commission_type'],
                        'maximum_withheld' => (string) $item['maximum_withheld'],
                        'override_settlement' => $item['override_settlement'],
                        'clawback_settlement' => $item['clawback_settlement'],
                        'stack_settlement' => $item['stack_settlement'],
                    ];
                });
            }

            $overrideData = [];
            if (count($data->Override) != 0) {
                // $tierStatus = PositionTierOverride::where('position_id', $id)->first();
                // $tierCount = PositionTierOverride::where('position_id', $id)->count();
                foreach ($data->Override as $item) {
                    $productId = $item['product_id'];
                    if (! isset($groupedData[$productId])) {
                        $groupedData[$productId] = [
                            'product_id' => (string) $productId,
                            'status' => count(collect($data->Override)->where('product_id', $productId)->where('status', 1)->values()) != 0 ? 1 : 0,
                            'override' => [],
                        ];
                    }

                    $groupedData[$productId]['override'][] = [
                        'override_id' => $item['override_id'],
                        'status' => $item['status'],
                        'override_ammount' => $item['override_ammount'],
                        'override_ammount_locked' => $item['override_ammount_locked'],
                        // Only use custom_field_X format when feature is enabled
                        'type' => ($isCustomFieldsEnabledForTiers && ($item['custom_sales_field_id'] ?? null)) ? 'custom_field_' . $item['custom_sales_field_id'] : $item['type'],
                        'custom_sales_field_id' => $isCustomFieldsEnabledForTiers ? ($item['custom_sales_field_id'] ?? null) : null,
                        'direct_custom_sales_field_id' => $isCustomFieldsEnabledForTiers ? ($item['direct_custom_sales_field_id'] ?? null) : null,
                        'indirect_custom_sales_field_id' => $isCustomFieldsEnabledForTiers ? ($item['indirect_custom_sales_field_id'] ?? null) : null,
                        'office_custom_sales_field_id' => $isCustomFieldsEnabledForTiers ? ($item['office_custom_sales_field_id'] ?? null) : null,
                        'override_type_locked' => $item['override_type_locked'],
                        'tiers_id' => $item->tiers_id,
                        'tiers_advancement' => $item->tiers_advancement,
                        'tiers_hiring_locked' => $item->tiers_hiring_locked,
                        'tiers_range' => $item->tiersRange->map(function ($range) {
                            return [
                                'id' => $range->tiers_levels_id,
                                'value' => $range->override_value,
                            ];
                        })->values(),
                    ];
                }
                $overrideData = array_values($groupedData);
            }

            $deductionData = [];
            $positionDeductionLimit = $data->deductionLimit;
            if (count($data->deductionName) != 0) {
                $deductionData = [
                    'deduction_status' => $positionDeductionLimit->status,
                    'limit_ammount' => $positionDeductionLimit->limit_ammount,
                    'limit' => $positionDeductionLimit->limit,
                    'limit_type' => $positionDeductionLimit->limit_type,
                    'deduction' => $data->deductionName->map(function ($deductionName) {
                        return [
                            'id' => $deductionName->id,
                            'cost_center_id' => $deductionName->cost_center_id,
                            'deduction_type' => $deductionName->deduction_type,
                            'ammount_par_paycheck' => $deductionName->ammount_par_paycheck,
                            'changes_field' => ($deductionName->changes_field) ? $deductionName->changes_field : null,
                            'changes_type' => ($deductionName->changes_type) ? $deductionName->changes_type : null,
                        ];
                    }),
                ];
            }

            $positionCommissionDeductionSetting = $data->deductionSetting;
            $response = [
                'position_id' => $data->id,
                'parent_position_id' => $data->parent_position_id,
                'parent_id' => $data->parent_id,
                'main_role' => $data->is_selfgen,
                'position_name' => $data->position_name,
                'worker_type' => $data->worker_type,
                'frequency_name' => $data->payFrequency->frequencyType->name,
                'deduction_status' => isset($positionDeductionLimit->status) ? $positionDeductionLimit->status : 0,
                'deduction_locked' => isset($positionCommissionDeductionSetting->deducation_locked) ? $positionCommissionDeductionSetting->deducation_locked : null,
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
                'position_status' => $data->setup_status,
                'commission' => $commissionData,
                'upfront' => $upfrontData,
                'settlement' => $settlement,
                'overrides' => $overrideData,
                'deductions' => $deductionData,
                'offer_letters' => isset($data->allAssociatedOfferLettersWithTemplate) ? $data->allAssociatedOfferLettersWithTemplate : [],
            ];

            return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $response]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage().' '.$e->getLine()], 400);
        }
    }

    public function dropDownProductByPosition($id): JsonResponse
    {
        try {
            $positionId = $id;

            $products = PositionProduct::with('productDetails.currentProductMilestoneHistories.milestoneSchema.milestone_trigger')->where('position_id', $positionId)->get();
            $data = [];
            foreach ($products as $positionProduct) {
                $product = $positionProduct->productDetails;
                if ($product) {
                    $triggers = $product->currentProductMilestoneHistories->milestoneSchema->milestone_trigger->slice(0, $product->currentProductMilestoneHistories->milestoneSchema->milestone_trigger->count() - 1);
                    $data[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'product_id' => $product->product_id,
                        'description' => $product->description,
                        'milestone_schema' => [
                            'id' => $product->currentProductMilestoneHistories->id,
                            'prefix' => $product->currentProductMilestoneHistories->milestoneSchema->prefix,
                            'schema_name' => $product->currentProductMilestoneHistories->milestoneSchema->schema_name,
                            'schema_description' => $product->currentProductMilestoneHistories->milestoneSchema->schema_description,
                            'status' => $product->currentProductMilestoneHistories->milestoneSchema->status,
                            'milestone_trigger' => $triggers,
                        ],
                    ];
                }
            }

            return response()->json([
                'ApiName' => 'drop-down-product-by-position',
                'status' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'drop-down-product-by-position',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function positionProductWise(Request $request)
    {

        try {
            $id = $request->position_id;
            $product_wise_id = $request->product_id;
            $data = Positions::with([
                'product.productName',
                'Commission',
                'Upfront.milestoneHistory.milestone',
                'Upfront.milestoneTrigger',
                'deductionName.costCenter',
                'deductionLimit',
                'deductionSetting',
                'Override.overridesDetail',
                'reconciliation',
                'payFrequency.frequencyType',
                'position_wage',
            ])->where('id', $id)->first();

            if (! $data) {
                return response()->json(['status' => false, 'message' => 'Position id is not available.'], 400);
            }

            $commissionData = [];
            foreach ($data->Commission as $commission) {
                $commissionData[$commission->product_id]['product_id'] = $commission->product_id;
                $commissionData[$commission->product_id]['commission_status'] = $commission->commission_status;

                $commissionData[$commission->product_id]['data'][] = [
                    'commission_parentage' => isset($commission->commission_parentage) ? $commission->commission_parentage : null,
                    'commission_status' => isset($commission->commission_status) ? $commission->commission_status : null,
                    'commission_percentag_hiring_locked' => isset($commission->commission_parentag_hiring_locked) ? $commission->commission_parentag_hiring_locked : null,
                    'commission_amount_type' => isset($commission->commission_amount_type) ? $commission->commission_amount_type : null,
                    'commission_amount_type_locked' => isset($commission->commission_amount_type_locked) ? $commission->commission_amount_type_locked : null,
                    'commission_parentag_type_hiring_locked' => isset($commission->commission_parentag_type_hiring_locked) ? $commission->commission_parentag_type_hiring_locked : null,
                    'commission_structure_type' => isset($commission->commission_structure_type) ? $commission->commission_structure_type : null,
                    'self_gen_user' => isset($commission->self_gen_user) ? $commission->self_gen_user : null,
                ];
            }
            $commissionData = array_values($commissionData);
            $commissionData = array_filter($commissionData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $commissionData = array_values($commissionData);
            $productDetails = [];
            foreach ($data->product as $product) {
                $productDetails[$product->product_id]['product_id'] = $product->product_id;
                $productDetails[$product->product_id]['product_status'] = $product->productName->status;

                $productDetails[$product->product_id]['data'][] = [
                    'product_id_' => isset($product->productName->product_id) ? $product->productName->product_id : null,
                    'product_name' => isset($product->productName->name) ? $product->productName->name : null,
                    'description' => isset($product->productName->description) ? $product->productName->description : null,
                    'milestone_schema_id' => isset($product->productName->milestone_schema_id) ? $product->productName->milestone_schema_id : null,
                    'clawback_exempt_on_ms_trigger_id' => isset($product->productName->clawback_exempt_on_ms_trigger_id) ? $product->productName->clawback_exempt_on_ms_trigger_id : null,
                    'effective_date' => isset($product->productName->effective_date) ? $product->productName->effective_date : null,
                ];
            }
            $productDetails = array_values($productDetails);
            $productDetails = array_filter($productDetails, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $productDetails = array_values($productDetails);

            $upfrontData = [];
            foreach ($data->Upfront as $upfront) {
                $upfrontData[$upfront->product_id]['product_id'] = $upfront->product_id;
                $upfrontData[$upfront->product_id]['upfront_status'] = $upfront->upfront_status;

                $upfrontData[$upfront->product_id]['data'][] = [
                    'upfront_ammount' => isset($upfront->upfront_ammount) ? $upfront->upfront_ammount : null,
                    'upfront_ammount_locked' => isset($upfront->upfront_ammount_locked) ? $upfront->upfront_ammount_locked : 0,
                    'upfront_status' => isset($upfront->upfront_status) ? $upfront->upfront_status : null,
                    'calculated_by' => isset($upfront->calculated_by) ? $upfront->calculated_by : null,
                    'calculated_locked' => isset($upfront->calculated_locked) ? $upfront->calculated_locked : null,
                    'upfront_system' => isset($upfront->upfront_system) ? $upfront->upfront_system : null,
                    'upfront_system_locked' => isset($upfront->upfront_system_locked) ? $upfront->upfront_system_locked : null,
                    'upfront_limit' => isset($upfront->upfront_limit) ? $upfront->upfront_limit : null,
                    'self_gen_user' => isset($upfront->self_gen_user) ? $upfront->self_gen_user : null,
                    'milestone_name' => isset($upfront->milestoneHistory->milestone->schema_name) ? $upfront->milestoneHistory->milestone->prefix.'-'.$upfront->milestoneHistory->milestone->schema_name : null,
                    'milestone_trigger_name' => isset($upfront->milestoneTrigger->name) ? $upfront->milestoneTrigger->name : null,
                    'milestone_trigger_type' => isset($upfrontData[$upfront->product_id]) ? 'M'.count($upfrontData[$upfront->product_id]) + 1 : 'M1',
                ];
            }
            $upfrontData = array_values($upfrontData);
            $upfrontData = array_filter($upfrontData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $upfrontData = array_values($upfrontData);

            $overrideData = [];
            foreach ($data->Override as $override) {
                $overrideData[$override->product_id]['product_id'] = $override->product_id;
                if (@$overrideData[$override->product_id]['status']) {
                    //
                } else {
                    $overrideData[$override->product_id]['status'] = $override->status;
                }

                $overrideType = $override->overridesDetail;
                $overrideType = $overrideType ? $overrideType->overrides_type : 'Unknown';
                $overrideData[$override->product_id]['data'][] = [
                    'override_id' => $override['override_id'],
                    'status' => $override['status'],
                    'override_ammount' => $override['override_ammount'],
                    'override_ammount_locked' => $override['override_ammount_locked'],
                    'type' => $override['type'],
                    'override_type_locked' => $override['override_type_locked'],
                    'overrides_type' => $overrideType,
                ];
            }
            $overrideData = array_values($overrideData);
            $overrideData = array_filter($overrideData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $overrideData = array_values($overrideData);

            $settlementData = [];
            foreach ($data->reconciliation as $settlement) {
                $settlementData[$settlement->product_id]['product_id'] = $settlement->product_id;
                $settlementData[$settlement->product_id]['status'] = $settlement->status;

                $settlementData[$settlement->product_id]['data'][] = [
                    'commission_withheld' => isset($settlement->commission_withheld) ? $settlement->commission_withheld : null,
                    'commission_type' => isset($settlement->commission_type) ? $settlement->commission_type : null,
                    'maximum_withheld' => isset($settlement->maximum_withheld) ? $settlement->maximum_withheld : null,
                    'override_settlement' => isset($settlement->override_settlement) ? $settlement->override_settlement : null,
                    'clawback_settlement' => isset($settlement->clawback_settlement) ? $settlement->clawback_settlement : null,
                    'stack_settlement' => isset($settlement->stack_settlement) ? $settlement->stack_settlement : null,
                    'commission_type_locked' => isset($settlement->commission_type_locked) ? $settlement->commission_type_locked : null,
                    'commission_withheld_locked' => isset($settlement->commission_withheld_locked) ? $settlement->commission_withheld_locked : null,
                ];
            }

            $settlementData = array_values($settlementData);
            $settlementData = array_filter($settlementData, function ($item) use ($product_wise_id) {
                return $item['product_id'] == $product_wise_id;
            });
            $settlementData = array_values($settlementData);

            $deductionData = [];
            if (count($data->deductionName) != 0) {
                $deductionData = $data->deductionName->map(function ($deductionName) {
                    return [
                        'id' => $deductionName->id,
                        'deduction_setting_id' => $deductionName->deduction_setting_id,
                        'position_id' => $deductionName->position_id,
                        'cost_center_id' => $deductionName->cost_center_id,
                        'deduction_type' => $deductionName->deduction_type,
                        'ammount_par_paycheck' => $deductionName->ammount_par_paycheck,
                        'cost_center_name' => $deductionName->costCenter->name,
                    ];
                })->toArray();
            }
            $deductionData = array_values($deductionData);

            $positionDeductionLimit = $data->deductionLimit;
            $positionCommissionDeductionSetting = $data->deductionSetting;
            $response = [
                'id' => $data->id,
                'position_name' => $data->position_name,
                'worker_type' => $data->worker_type,
                'frequency_name' => $data->payFrequency->frequencyType->name,
                'deduction' => $deductionData,
                'deduction_status' => isset($positionDeductionLimit->status) ? $positionDeductionLimit->status : 0,
                'limit_type' => isset($positionDeductionLimit->limit_type) ? $positionDeductionLimit->limit_type : null,
                'limit_ammount' => isset($positionDeductionLimit->limit_ammount) ? $positionDeductionLimit->limit_ammount : null,
                'deduction_locked' => isset($positionCommissionDeductionSetting->deducation_locked) ? $positionCommissionDeductionSetting->deducation_locked : null,
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
                'commission' => $commissionData,
                'upfront' => $upfrontData,
                'override' => $overrideData,
                'settlement' => $settlementData,
                'products' => $productDetails,
            ];

            return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $response]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage().' '.$e->getLine()], 400);
        }
    }

    // POSITION SETUP

    public function wages(Request $request)
    {
        $this->checkValidations($request->all(), [
            'wizard_type' => 'required|in:only_new_users,all_users',
            'effective_date' => 'required_if:wizard_type,all_users',
        ]);

        $positionId = $request->position_id;
        if ($request->wages_status == '1') {
            $this->checkValidations($request->all(), [
                'position_id' => 'required',
                'pay_type' => 'required|in:Salary,Hourly',
                'pay_type_lock' => 'required|in:0,1',
                'pay_rate' => 'required|numeric',
                'pay_rate_lock' => 'required|in:0,1',
                'pto_hours' => 'required|numeric',
                'pto_hours_lock' => 'required|in:0,1',
                'unused_pto_expires' => 'required',
                'unused_pto_expires_lock' => 'required|in:0,1',
                'expected_weekly_hours' => 'required|numeric',
                'expected_weekly_hours_lock' => 'required|in:0,1',
                'overtime_rate' => 'required_if:pay_type,Hourly',
                'overtime_rate_lock' => 'required_if:pay_type,Hourly|in:0,1',
                'wages_status' => 'required|in:0,1',
            ]);
        } else {
            if (! PositionWage::where(['position_id' => $positionId, 'wages_status' => '0'])->first()) {
                $checkExist = OnboardingEmployees::select('sub_position_id')->where('sub_position_id', $positionId)->union(User::select('sub_position_id')->where('sub_position_id', $positionId))->distinct()->count('sub_position_id');
                if ($checkExist > 0) {
                    $this->errorResponse('This position is already onboarded or hired and cannot be disabled!!', 'add-position-wages', '', 400);
                }
            }
        }

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            PositionWage::updateOrCreate(['position_id' => $positionId], [
                'pay_type' => $request->pay_type ?? null,
                'pay_type_lock' => $request->pay_type_lock ?? 0,
                'pay_rate' => $request->pay_rate ?? null,
                'pay_rate_type' => $request->pay_rate_type ?? null,
                'pay_rate_lock' => $request->pay_rate_lock ?? 0,
                'pto_hours' => $request->pto_hours ?? null,
                'pto_hours_lock' => $request->pto_hours_lock ?? 0,
                'unused_pto_expires' => $request->unused_pto_expires ?? null,
                'unused_pto_expires_lock' => $request->unused_pto_expires_lock ?? 0,
                'expected_weekly_hours' => $request->expected_weekly_hours ?? null,
                'expected_weekly_hours_lock' => $request->expected_weekly_hours_lock ?? 0,
                'overtime_rate' => $request->overtime_rate ?? null,
                'overtime_rate_lock' => $request->overtime_rate_lock ?? 0,
                'wages_status' => $request->wages_status ?? 0,
            ]);

            if ($request->wizard_type == 'all_users') {
                $effectiveDate = $request->effective_date;
                $subQuery = UserOrganizationHistory::select(
                    'id',
                    'user_id',
                    'effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
                )->where('effective_date', '<=', $effectiveDate);

                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())
                    ->select('user_id', 'effective_date')
                    ->where('rn', 1)->get();

                $closestDates = $results->map(function ($result) {
                    return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
                });

                $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
                    foreach ($closestDates as $closestDate) {
                        $query->orWhere(function ($q) use ($closestDate) {
                            $q->where('user_id', $closestDate['user_id'])
                                ->where('effective_date', $closestDate['effective_date']);
                        });
                    }
                })->where('sub_position_id', $positionId)->pluck('user_id')->toArray();
                $users = User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
                foreach ($users as $user) {
                    $userId = $user->id;
                    UserWagesHistory::updateOrCreate([
                        'user_id' => $userId,
                        'effective_date' => $effectiveDate,
                    ], [
                        'updater_id' => auth()->user()->id,
                        'pay_type' => $request->pay_type ?? null,
                        'pay_rate' => $request->pay_rate ?? null,
                        'pay_rate_type' => $request->pay_rate_type ?? null,
                        'expected_weekly_hours' => $request->expected_weekly_hours ?? null,
                        'overtime_rate' => $request->overtime_rate ?? null,
                        'pto_hours' => $request->pto_hours ?? null,
                        'unused_pto_expires' => $request->unused_pto_expires ?? null,
                        'pto_hours_effective_date' => $effectiveDate,
                    ]);
                }

                // SYNC USER HISTORY DATA
                ApplyHistoryOnUsersV2Job::dispatch(implode(',', $users->pluck('id')->toArray()), auth()->user()->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            $this->successResponse('Add successfully!!', 'add-position-wages');
        } else {
            $this->errorResponse($response['message'], 'add-position-products', '', 500);
        }
    }

    public function commission(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users',
            'effective_date' => 'required_if:wizard_type,all_users',
            'commission' => 'required|array|min:1',
            'commission.*.product_id' => 'required|integer',
            'commission.*.commission_status' => 'required|integer|in:0,1',
            'commission.*.data' => 'required_if:commission.*.commission_status,1',
            'commission.*.data.*.self_gen_user' => 'required|boolean|in:0,1',
            'commission.*.data.*.commission_amount_type' => 'required|string',
            'commission.*.data.*.commission_amount_type_locked' => 'required|in:0,1',
            'commission.*.data.*.commission_parentage' => 'required',
            'commission.*.data.*.commission_parentag_hiring_locked' => 'required|in:0,1',
            'commission.*.data.*.commission_structure_type' => 'required|string',
            'commission.*.data.*.commission_parentag_type_hiring_locked' => 'required|in:0,1',
            'commission.*.data.*.commission_limit' => 'required|integer',
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            PositionCommission::where(['position_id' => $positionId])->delete();
            TiersPositionCommission::where(['position_id' => $positionId])->delete();
            if ($request->wizard_type == 'all_users') {
                $effectiveDate = $request->effective_date;
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }
            $companySettingTiers = CompanySetting::where('type', 'tier')->first();
            foreach ($request->commission as $commission) {
                if ($commission['commission_status'] == 1) {
                    foreach ($commission['data'] as $data) {
                        $tiersAdvancement = isset($data['tiers_advancement']) ? $data['tiers_advancement'] : null;
                        $tiersId = (isset($data['tiers_id']) && ! empty($data['tiers_id'])) ? $data['tiers_id'] : 0;
                        $positionCommission = PositionCommission::create([
                            'position_id' => $positionId,
                            'core_position_id' => $data['core_position_id'],
                            'product_id' => $commission['product_id'],
                            'self_gen_user' => $data['self_gen_user'],
                            'commission_parentage' => $data['commission_parentage'],
                            'commission_parentag_hiring_locked' => $data['commission_parentag_hiring_locked'],
                            'commission_amount_type' => $data['commission_amount_type'],
                            'commission_amount_type_locked' => $data['commission_amount_type_locked'],
                            'commission_structure_type' => $data['commission_structure_type'],
                            'commission_parentag_type_hiring_locked' => $data['commission_parentag_type_hiring_locked'],
                            'commission_status' => $commission['commission_status'],
                            'tiers_id' => $tiersId,
                            // 'tiers_advancement' => $tiersAdvancement,
                            'tiers_hiring_locked' => isset($data['tiers_hiring_locked']) ? $data['tiers_hiring_locked'] : 0,
                            'commission_limit' => $data['commission_limit'],
                        ]);

                        $positionCommissionId = $positionCommission->id;
                        $range = (isset($data['tiers_range']) && ! empty($data['tiers_range'])) ? $data['tiers_range'] : null;
                        if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                            foreach ($range as $rang) {
                                TiersPositionCommission::create([
                                    'position_id' => $positionId,
                                    'position_commission_id' => $positionCommissionId,
                                    'product_id' => $commission['product_id'],
                                    'tiers_schema_id' => $tiersId,
                                    'tiers_advancement' => $tiersAdvancement,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'commission_value' => $rang['value'] ?? null,
                                    'commission_type' => $data['commission_amount_type'],
                                ]);
                            }
                        }

                        // no changes currenlty as per tiers
                        if ($request->wizard_type == 'all_users') {
                            foreach ($users as $user) {
                                $userId = $user->id;
                                $commissionHistory = UserCommissionHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $commission['product_id'], 'core_position_id' => $data['core_position_id'], 'sub_position_id' => $positionId, 'self_gen_user' => $data['self_gen_user'], 'commission_effective_date' => $effectiveDate], [
                                    'position_id' => $positionId,
                                    'updater_id' => auth()->user()->id,
                                    'commission' => $data['commission_parentage'],
                                    'commission_type' => $data['commission_amount_type'],
                                    'tiers_id' => $tiersId,
                                ]);

                                $commissionId = $commissionHistory->id;
                                if ($companySettingTiers?->status) {
                                    UserCommissionHistoryTiersRange::where('user_commission_history_id', $commissionId)->delete();
                                    if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                                        foreach ($range as $rang) {
                                            UserCommissionHistoryTiersRange::create([
                                                'user_id' => $userId,
                                                'user_commission_history_id' => $commissionId,
                                                'tiers_levels_id' => $rang['id'] ?? null,
                                                'value' => $rang['value'] ?? null,
                                            ]);
                                        }
                                    }
                                }
                            }

                            // SYNC USER HISTORY DATA
                            ApplyHistoryOnUsersV2Job::dispatch(implode(',', $users->pluck('id')->toArray()), auth()->user()->id);
                        }
                    }
                } else {
                    PositionCommission::create([
                        'position_id' => $positionId,
                        'core_position_id' => null,
                        'product_id' => $commission['product_id'],
                        'self_gen_user' => 0,
                        'commission_parentage' => 0,
                        'commission_parentag_hiring_locked' => 0,
                        'commission_amount_type' => 'percent',
                        'commission_amount_type_locked' => 0,
                        'commission_structure_type' => null,
                        'commission_parentag_type_hiring_locked' => 0,
                        'commission_status' => $commission['commission_status'],
                        'tiers_id' => 0, // remove as per new tiers tiers_id
                        // 'tiers_advancement' => NULL, // remove tiers advancment as per new tiers schema
                        'tiers_hiring_locked' => 0,
                    ]);
                    PositionCommissionUpfronts::where(['position_id' => $positionId, 'product_id' => $commission['product_id']])->delete();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            $this->successResponse('Add successfully!!', 'add-position-commission');
        } else {
            $this->errorResponse($response['message'], 'add-position-commission', '', 500);
        }
    }

    public function upfront(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users',
            'effective_date' => 'required_if:wizard_type,all_users',
            'upfront' => 'required|array|min:1',
            'upfront.*.product_id' => 'required|integer',
            'upfront.*.upfront_status' => 'required|in:0,1',
            'upfront.*.data' => 'required_if:upfront.*.upfront_status,1',
            'upfront.*.data.*.milestone_id' => 'required|integer',
            'upfront.*.data.*.self_gen_user' => 'required|in:0,1',
            'upfront.*.data.*.schemas' => 'required|array|min:1',
            'upfront.*.data.*.schemas.*.milestone_schema_trigger_id' => 'required|integer',
            'upfront.*.data.*.schemas.*.upfront_ammount' => 'required|numeric',
            'upfront.*.data.*.schemas.*.upfront_ammount_locked' => 'required|in:0,1',
            'upfront.*.data.*.schemas.*.calculated_by' => 'required|string',
            'upfront.*.data.*.schemas.*.calculated_locked' => 'required|in:0,1',
            'upfront.*.data.*.schemas.*.upfront_limit' => 'required|integer',

        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            TiersPositionUpfront::where(['position_id' => $positionId])->delete();
            PositionCommissionUpfronts::where(['position_id' => $positionId])->delete();
            if ($request->wizard_type == 'all_users') {
                $effectiveDate = $request->effective_date;
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }
            $position = Positions::where('id', $positionId)->first();
            $corePositionId = $position->parent_id ? $position->parent_id : $position->id;
            $companySettingTiers = CompanySetting::where('type', 'tier')->first();
            foreach ($request->upfront as $upfront) {
                $productId = $upfront['product_id'];
                if ($upfront['upfront_status'] == 1) {
                    if (PositionCommission::where(['position_id' => $positionId, 'product_id' => $productId, 'commission_status' => '1'])->first()) {
                        foreach ($upfront['data'] as $data) {
                            foreach ($data['schemas'] as $schema) {
                                $tiersId = isset($schema['tiers_id']) ? $schema['tiers_id'] : null;
                                $tiersAdvancement = isset($schema['tiers_advancement']) ? $schema['tiers_advancement'] : null;

                                $positionUpFront = PositionCommissionUpfronts::create([
                                    'position_id' => $positionId,
                                    'core_position_id' => $data['core_position_id'],
                                    'product_id' => $productId,
                                    'self_gen_user' => $data['self_gen_user'],
                                    'milestone_schema_id' => $data['milestone_id'],
                                    'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'],
                                    'upfront_ammount' => $schema['upfront_ammount'],
                                    'upfront_ammount_locked' => $schema['upfront_ammount_locked'] ?? 0,
                                    'calculated_by' => $schema['calculated_by'],
                                    'calculated_locked' => $schema['calculated_locked'],
                                    'upfront_status' => $upfront['upfront_status'],
                                    'upfront_system' => @$schema['upfront_system'] ? $schema['upfront_system'] : 'Fixed',
                                    'upfront_system_locked' => @$schema['upfront_system_locked'] ? $schema['upfront_system_locked'] : 0,
                                    'upfront_limit' => $schema['upfront_limit'],
                                    'tiers_id' => $tiersId,
                                    // 'tiers_advancement' => $tiersAdvancement,
                                    'tiers_hiring_locked' => isset($schema['tiers_hiring_locked']) ? $schema['tiers_hiring_locked'] : 0,
                                ]);

                                $positionUpFrontId = $positionUpFront->id;
                                $range = isset($schema['tiers_range']) && ! empty($schema['tiers_range']) ? $schema['tiers_range'] : null;
                                if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                                    foreach ($range as $rang) {
                                        TiersPositionUpfront::create([
                                            'position_id' => $positionId,
                                            'position_upfront_id' => $positionUpFrontId,
                                            'product_id' => $productId,
                                            'tiers_schema_id' => $tiersId,
                                            'tiers_advancement' => $tiersAdvancement,
                                            'tiers_levels_id' => $rang['id'] ?? null,
                                            'upfront_value' => $rang['value'] ?? null,
                                            'upfront_type' => $schema['calculated_by'],
                                        ]);
                                    }
                                }
                                // this is pending to implement tiers system
                                if ($request->wizard_type == 'all_users') {
                                    foreach ($users as $user) {
                                        $userId = $user->id;
                                        $upFrontHistory = UserUpfrontHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $productId, 'milestone_schema_trigger_id' => $schema['milestone_schema_trigger_id'], 'core_position_id' => $data['core_position_id'], 'upfront_effective_date' => $effectiveDate], [
                                            'position_id' => $corePositionId,
                                            'sub_position_id' => $positionId,
                                            'milestone_schema_id' => $data['milestone_id'],
                                            'self_gen_user' => $data['self_gen_user'],
                                            'updater_id' => auth()->user()->id,
                                            'upfront_pay_amount' => $schema['upfront_ammount'],
                                            'upfront_sale_type' => $schema['calculated_by'],
                                            'tiers_id' => $schema['tiers_id'],
                                        ]);

                                        $upFrontId = $upFrontHistory->id;
                                        if ($companySettingTiers?->status) {
                                            UserUpfrontHistoryTiersRange::where('user_upfront_history_id', $upFrontId)->delete();
                                            if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                                                foreach ($range as $rang) {
                                                    UserUpfrontHistoryTiersRange::create([
                                                        'user_id' => $userId,
                                                        'user_upfront_history_id' => $upFrontId ?? null,
                                                        'tiers_schema_id' => $range['tiers_schema_id'] ?? null,
                                                        'tiers_levels_id' => $rang['id'] ?? null,
                                                        'value' => $range['value'] ?? null,
                                                        'value_type' => $upfront->upfront_sale_type ?? null,
                                                    ]);
                                                }
                                            }
                                        }
                                    }

                                    // SYNC USER HISTORY DATA
                                    ApplyHistoryOnUsersV2Job::dispatch(implode(',', $users->pluck('id')->toArray()), auth()->user()->id);
                                }
                            }
                        }
                    } else {
                        PositionCommissionUpfronts::create([
                            'position_id' => $positionId,
                            'core_position_id' => null,
                            'product_id' => $productId,
                            'self_gen_user' => 0,
                            'milestone_schema_id' => null,
                            'milestone_schema_trigger_id' => null,
                            'upfront_ammount' => 0,
                            'upfront_ammount_locked' => 0,
                            'calculated_by' => 'per kw',
                            'calculated_locked' => 0,
                            'upfront_status' => 0,
                            'upfront_system' => 'Fixed',
                            'upfront_system_locked' => 0,
                            'upfront_limit' => null,
                            'tiers_id' => 0,
                            // 'tiers_advancement' => NULL,
                            'tiers_hiring_locked' => 0,
                        ]);
                    }
                } else {
                    PositionCommissionUpfronts::create([
                        'position_id' => $positionId,
                        'core_position_id' => null,
                        'product_id' => $productId,
                        'self_gen_user' => 0,
                        'milestone_schema_id' => null,
                        'milestone_schema_trigger_id' => null,
                        'upfront_ammount' => 0,
                        'upfront_ammount_locked' => 0,
                        'calculated_by' => 'per kw',
                        'calculated_locked' => 0,
                        'upfront_status' => 0,
                        'upfront_system' => 'Fixed',
                        'upfront_system_locked' => 0,
                        'upfront_limit' => null,
                        'tiers_id' => 0,
                        // 'tiers_advancement' => NULL,
                        'tiers_hiring_locked' => 0,
                    ]);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            $this->successResponse('Add successfully!!', 'add-position-upfront');
        } else {
            $this->errorResponse($response['message'], 'add-position-upfront', '', 500);
        }
    }

    public function deduction(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users',
            'effective_date' => 'required_if:wizard_type,all_users',
            'deduction_status' => 'required|in:0,1',
            'deduction' => 'required_if:deduction_status,1',
            'deduction.*.cost_center_id' => 'required|integer',
            'deduction.*.deduction_type' => 'required',
            'deduction.*.ammount_par_paycheck' => 'required',
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            PositionCommissionDeduction::where('position_id', $positionId)->delete();
            PositionCommissionDeductionSetting::updateOrCreate(['position_id' => $positionId], [
                'status' => $request->deduction_status,
            ]);

            if ($request->deduction_status) {
                $effectiveDate = date('Y-m-d');
                if ($request->wizard_type == 'all_users') {
                    $effectiveDate = $request->effective_date;
                }
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
                foreach ($request->deduction as $deduction) {
                    PositionCommissionDeduction::create([
                        'position_id' => $request->position_id,
                        'deduction_setting_id' => 1,
                        'deduction_type' => $deduction['deduction_type'],
                        'cost_center_id' => $deduction['cost_center_id'],
                        'ammount_par_paycheck' => $deduction['ammount_par_paycheck'],
                        'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                        'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                    ]);

                    if ($request->wizard_type == 'only_new_users') {
                        $deduction['ammount_par_paycheck'] = 0;
                    }

                    foreach ($users as $users) {
                        $userId = $users->id;
                        $checkUserDeduction = UserDeduction::where(['user_id' => $userId, 'cost_center_id' => $deduction['cost_center_id']])->first();
                        if (! $checkUserDeduction) {
                            $costCenter = CostCenter::select('name')->where('id', $deduction['cost_center_id'])->first();
                            $dataInsert = [
                                'deduction_type' => $deduction['deduction_type'],
                                'cost_center_name' => isset($costCenter->name) ? $costCenter->name : null,
                                'cost_center_id' => $deduction['cost_center_id'],
                                'ammount_par_paycheck' => $deduction['ammount_par_paycheck'],
                                'deduction_setting_id' => isset($deduction['deduction_setting_id']) ? $deduction['deduction_setting_id'] : null,
                                'position_id' => isset($position->parent_id) ? $position->parent_id : $request->position_id,
                                'sub_position_id' => isset($request->position_id) ? $request->position_id : null,
                                'user_id' => $userId,
                                'effective_date' => $effectiveDate,
                            ];
                            UserDeduction::create($dataInsert);
                        }

                        if ($request->wizard_type == 'all_users') {
                            $checkData = UserDeductionHistory::where(['user_id' => $userId, 'cost_center_id' => $deduction['cost_center_id'], 'effective_date' => $effectiveDate])->first();
                            if ($checkData) {
                                $update = [
                                    'updater_id' => auth()->user()->id,
                                    'limit_value' => isset($request['limit_ammount']) ? $request['limit_ammount'] : null,
                                    'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                                    'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                                    'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                                ];
                                UserDeductionHistory::where('id', $checkData->id)->update($update);
                            } else {
                                UserDeductionHistory::create([
                                    'user_id' => $userId,
                                    'updater_id' => auth()->user()->id,
                                    'cost_center_id' => $deduction['cost_center_id'],
                                    'amount_par_paycheque' => $deduction['ammount_par_paycheck'],
                                    'old_amount_par_paycheque' => null,
                                    'sub_position_id' => isset($request->position_id) ? $request->position_id : null,
                                    'limit_value' => isset($request['limit_ammount']) ? $request['limit_ammount'] : null,
                                    'changes_type' => isset($deduction['changes_type']) ? $deduction['changes_type'] : null,
                                    'changes_field' => isset($deduction['changes_field']) ? $deduction['changes_field'] : null,
                                    'effective_date' => $effectiveDate,
                                ]);
                            }
                        }
                    }
                }
            }

            PositionsDeductionLimit::updateOrCreate(['position_id' => $request->position_id], [
                'limit_ammount' => $request->limit_ammount,
                'limit' => $request->limit,
                'status' => $request->deduction_status,
                'limit_type' => $request->limit_type,
            ]);

            if (isset($request->position_status) && $request->position_status) {
                Positions::where('id', $request->position_id)->update(['setup_status' => $request->position_status]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            $this->successResponse('Saved successfully!!', 'position-deduction');
        } else {
            $this->errorResponse($response['message'], 'position-deduction', '', 500);
        }
    }

    public function removeDeduction($id): JsonResponse
    {
        if (! PositionCommissionDeduction::find($id)) {
            return response()->json([
                'ApiName' => 'remove-deduction',
                'status' => false,
                'message' => 'Deduction not found!!',
            ], 400);
        }

        PositionCommissionDeduction::where('id', $id)->delete();

        return response()->json([
            'ApiName' => 'remove-deduction',
            'status' => true,
            'message' => 'Deduction removed successfully!!',
        ]);
    }

    public function override(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users',
            'effective_date' => 'required_if:wizard_type,all_users',
            'overrides' => 'required|array|min:1',
            'overrides.*.product_id' => 'required|integer',
            'overrides.*.status' => 'required|in:0,1',
            'overrides.*.override' => 'required_if:overrides.*.status,1',
            'overrides.*.override.*.override_id' => 'required|integer',
            'overrides.*.override.*.status' => 'required|in:0,1',
            'overrides.*.override.*.override_ammount' => 'required_if:overrides.*.override.*.status,1',
            'overrides.*.override.*.override_ammount_locked' => 'required|in:0,1',
            'overrides.*.override.*.override_type_locked' => 'required|in:0,1',
            'overrides.*.override.*.override_limit' => 'required|integer',
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            PositionOverride::where('position_id', $positionId)->delete();
            TiersPositionOverrides::where(['position_id' => $positionId])->delete();
            PositionTierOverride::where(['position_id' => $positionId])->delete();
            if ($request->wizard_type == 'all_users') {
                $effectiveDate = $request->effective_date;
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }
            $companySettingTiers = CompanySetting::where('type', 'tier')->first();
            foreach ($request->overrides as $overrides) {
                $overrideArray = [
                    'direct_overrides_amount' => null,
                    'direct_overrides_type' => null,
                    'direct_tiers_id' => 0,
                    'direct_tiers_range' => [],
                    'indirect_overrides_amount' => null,
                    'indirect_overrides_type' => null,
                    'indirect_tiers_id' => 0,
                    'indirect_tiers_range' => [],
                    'office_overrides_amount' => null,
                    'office_overrides_type' => null,
                    'office_tiers_id' => 0,
                    'office_tiers_range' => [],
                    'office_stack_overrides_amount' => null,
                ];
                foreach ($overrides['override'] as $override) {
                    $status = $override['status'];
                    if (! $overrides['status']) {
                        $status = 0;
                    }

                    $tiersId = isset($overrides['tiers_id']) ? $overrides['tiers_id'] : 0;
                    $tiersAdvancement = isset($overrides['tiers_advancement']) ? $overrides['tiers_advancement'] : null;
                    $positionOverride = PositionOverride::create([
                        'position_id' => $request->position_id,
                        'product_id' => $overrides['product_id'],
                        'override_id' => $override['override_id'],
                        'status' => $status,
                        'override_ammount' => $override['override_ammount'],
                        'override_ammount_locked' => $override['override_ammount_locked'],
                        'type' => @$override['type'] ? $override['type'] : null,
                        'override_type_locked' => $override['override_type_locked'],
                        'tiers_id' => $tiersId,
                        // 'tiers_advancement' => $tiersAdvancement,
                        'tiers_hiring_locked' => isset($overrides['tiers_hiring_locked']) ? $overrides['tiers_hiring_locked'] : 0,
                        'override_limit' => $override['override_limit'],
                    ]);

                    $positionOverrideId = $positionOverride->id;
                    $range = isset($override['tiers_range']) && ! empty($override['tiers_range']) ? $override['tiers_range'] : null;
                    if ($tiersId > 0 && is_array($range) && count($range) != 0) {
                        foreach ($range as $rang) {
                            TiersPositionOverrides::create([
                                'position_id' => $positionId,
                                'position_overrides_id' => $positionOverrideId,
                                'product_id' => $overrides['product_id'],
                                'tiers_schema_id' => $tiersId,
                                'tiers_advancement' => $tiersAdvancement,
                                'tiers_levels_id' => $rang['id'] ?? null,
                                'override_value' => $rang['value'] ?? null,
                                'override_type' => @$override['type'] ? $override['type'] : null,
                            ]);
                        }
                    }

                    if ($override['override_id'] == '1') {
                        $overrideArray['direct_overrides_amount'] = $override['override_ammount'];
                        $overrideArray['direct_overrides_type'] = @$override['type'] ? $override['type'] : null;
                        $overrideArray['direct_tiers_id'] = $tiersId;
                        $overrideArray['direct_tiers_range'] = $range;
                    } elseif ($override['override_id'] == '2') {
                        $overrideArray['indirect_overrides_amount'] = $override['override_ammount'];
                        $overrideArray['indirect_overrides_type'] = @$override['type'] ? $override['type'] : null;
                        $overrideArray['indirect_tiers_id'] = $tiersId;
                        $overrideArray['indirect_tiers_range'] = $range;
                    } elseif ($override['override_id'] == '3') {
                        $overrideArray['office_overrides_amount'] = $override['override_ammount'];
                        $overrideArray['office_overrides_type'] = @$override['type'] ? $override['type'] : null;
                        $overrideArray['office_tiers_id'] = $tiersId;
                        $overrideArray['office_tiers_range'] = $range;
                    } elseif ($override['override_id'] == '4') {
                        $overrideArray['office_stack_overrides_amount'] = $override['override_ammount'];
                    }
                }

                if ($overrides['status']) {
                    PositionTierOverride::create([
                        'position_id' => $positionId,
                        'tier_status' => $overrides['status'],
                    ]);
                }
                // pending for this whrn user postion update
                if ($request->wizard_type == 'all_users') {
                    foreach ($users as $user) {
                        $userId = $user->id;
                        $overrideHistory = UserOverrideHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $overrides['product_id'], 'override_effective_date' => $effectiveDate], [
                            'updater_id' => auth()->user()->id,
                            'direct_overrides_amount' => $overrideArray['direct_overrides_amount'],
                            'direct_overrides_type' => $overrideArray['direct_overrides_type'],
                            'indirect_overrides_amount' => $overrideArray['indirect_overrides_amount'],
                            'indirect_overrides_type' => $overrideArray['indirect_overrides_type'],
                            'office_overrides_amount' => $overrideArray['office_overrides_amount'],
                            'office_overrides_type' => $overrideArray['office_overrides_type'],
                            'office_stack_overrides_amount' => $overrideArray['office_stack_overrides_amount'],
                            'direct_tiers_id' => $overrideArray['direct_tiers_id'] ?? null,
                            'indirect_tiers_id' => $overrideArray['indirect_tiers_id'] ?? null,
                            'office_tiers_id' => $overrideArray['office_tiers_id'] ?? null,
                        ]);

                        $overrideId = $overrideHistory->id;
                        if ($companySettingTiers?->status) {
                            UserDirectOverrideHistoryTiersRange::where('user_override_history_id', $overrideId)->delete();
                            if ($overrideArray['direct_tiers_id'] > 0 && is_array($overrideArray['direct_tiers_range']) && count($overrideArray['direct_tiers_range']) != 0) {
                                foreach ($overrideArray['direct_tiers_range'] as $range) {
                                    UserDirectOverrideHistoryTiersRange::create([
                                        'user_id' => $userId,
                                        'user_override_history_id' => $overrideId,
                                        'tiers_levels_id' => $range['id'] ?? null,
                                        'value' => $range['value'] ?? null,
                                    ]);
                                }
                            }

                            UserIndirectOverrideHistoryTiersRange::where('user_override_history_id', $overrideId)->delete();
                            if ($overrideArray['indirect_tiers_id'] > 0 && is_array($overrideArray['indirect_tiers_range']) && count($overrideArray['indirect_tiers_range']) != 0) {
                                foreach ($overrideArray['indirect_tiers_range'] as $range) {
                                    UserIndirectOverrideHistoryTiersRange::create([
                                        'user_id' => $userId,
                                        'user_override_history_id' => $overrideId,
                                        'tiers_levels_id' => $range['id'] ?? null,
                                        'value' => $range['value'] ?? null,
                                    ]);
                                }
                            }

                            UserOfficeOverrideHistoryTiersRange::where('user_office_override_history_id', $overrideId)->delete();
                            if ($overrideArray['office_tiers_id'] > 0 && is_array($overrideArray['office_tiers_range']) && count($overrideArray['office_tiers_range']) != 0) {
                                foreach ($overrideArray['office_tiers_range'] as $range) {
                                    UserOfficeOverrideHistoryTiersRange::create([
                                        'user_id' => $userId,
                                        'user_office_override_history_id' => $overrideId,
                                        'tiers_levels_id' => $range['id'] ?? null,
                                        'value' => $range['value'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }

                    // SYNC USER HISTORY DATA
                    ApplyHistoryOnUsersV2Job::dispatch(implode(',', $users->pluck('id')->toArray()), auth()->user()->id);
                }
            }

            if (isset($request->position_status) && $request->position_status) {
                Positions::where('id', $request->position_id)->update(['setup_status' => $request->position_status]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            $this->successResponse('Add successfully!!', 'add-position-override');
        } else {
            $this->errorResponse($response['message'], 'add-position-override', '', 500);
        }
    }

    public function settlement(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'wizard_type' => 'required|in:only_new_users,all_users',
            'effective_date' => 'required_if:wizard_type,all_users',
            'settlement' => 'required|array|min:1',
            'settlement.*.product_id' => 'required|integer',
            'settlement.*.status' => 'required|in:0,1',
            'settlement.*.commission_withheld' => 'required_if:settlement.*.status,1',
            'settlement.*.commission_type' => 'required_if:settlement.*.status,1',
        ]);

        $response = [
            'status' => true,
            'message' => 'Add successfully!!',
        ];
        try {
            DB::beginTransaction();
            $positionId = $request->position_id;
            PositionReconciliations::where('position_id', $positionId)->delete();
            if ($request->wizard_type == 'all_users') {
                $effectiveDate = $request->effective_date;
                $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
            }
            $position = Positions::where('id', $positionId)->first();
            $corePositionId = $position->parent_id ? $position->parent_id : $position->id;
            foreach ($request->settlement as $settlement) {
                PositionReconciliations::create([
                    'position_id' => $positionId,
                    'product_id' => $settlement['product_id'],
                    'commission_withheld' => $settlement['commission_withheld'],
                    'commission_type' => $settlement['commission_type'],
                    'maximum_withheld' => $settlement['maximum_withheld'],
                    'override_settlement' => isset($settlement['override_settlement']) && ! empty($settlement['override_settlement']) ? $settlement['override_settlement'] : null,
                    'clawback_settlement' => isset($settlement['clawback_settlement']) && ! empty($settlement['clawback_settlement']) ? $settlement['clawback_settlement'] : null,
                    'stack_settlement' => isset($settlement['stack_settlement']) && ! empty($settlement['stack_settlement']) ? $settlement['stack_settlement'] : null,
                    'status' => $settlement['status'],
                ]);

                if ($request->wizard_type == 'all_users') {
                    foreach ($users as $user) {
                        $userId = $user->id;
                        UserWithheldHistory::updateOrCreate(['user_id' => $userId, 'product_id' => $settlement['product_id'], 'withheld_effective_date' => $effectiveDate], [
                            'updater_id' => auth()->user()->id,
                            'position_id' => $corePositionId,
                            'sub_position_id' => $positionId,
                            'withheld_amount' => $settlement['commission_withheld'],
                            'withheld_type' => $settlement['commission_type'],
                        ]);
                    }

                    // SYNC USER HISTORY DATA
                    ApplyHistoryOnUsersV2Job::dispatch(implode(',', $users->pluck('id')->toArray()), auth()->user()->id);
                }
            }

            if (isset($request->position_status) && $request->position_status) {
                Positions::where('id', $positionId)->update(['setup_status' => $request->position_status]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $response = [
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ];
        }

        if ($response['status']) {
            $this->successResponse('Add successfully!!', 'add-position-settlement');
        } else {
            $this->errorResponse($response['message'], 'add-position-settlement', '', 500);
        }
    }

    public function positionUserCount(Request $request)
    {
        $this->checkValidations($request->all(), [
            'position_id' => 'required',
            'effective_date' => 'required',
        ]);

        $positionId = $request->position_id;
        $effectiveDate = $request->effective_date;
        $users = $this->getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate);
        $this->successResponse('Successfully.', 'user-count', ['user_count' => count($users)]);
    }

    protected function getUsersBasedOnPositionEffectiveDate($positionId, $effectiveDate)
    {
        $subQuery = UserOrganizationHistory::select(
            'id',
            'user_id',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
        )->where('effective_date', '<=', $effectiveDate);

        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
            ->mergeBindings($subQuery->getQuery())
            ->select('user_id', 'effective_date')
            ->where('rn', 1)->get();

        $closestDates = $results->map(function ($result) {
            return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
        });

        $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
            foreach ($closestDates as $closestDate) {
                $query->orWhere(function ($q) use ($closestDate) {
                    $q->where('user_id', $closestDate['user_id'])
                        ->where('effective_date', $closestDate['effective_date']);
                });
            }
        })->where('sub_position_id', $positionId)->pluck('user_id')->toArray();

        return User::select('id', 'email', 'first_name', 'last_name', 'office_id', 'sub_position_id')->whereIn('id', $userIdArr)->where('dismiss', 0)->get();
    }
}
