<?php

namespace App\Http\Controllers\API\V2\PositionUpfront;

use App\Helpers\CustomSalesFieldHelper;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\Positions;
use App\Models\TiersPositionUpfront;
use App\Models\User;
use Laravel\Pennant\Feature;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class PositionUpfrontController extends Controller
{
    public function index(): JsonResponse
    {
        $data = PositionCommissionUpfronts::all();

        return response()->json([
            'ApiName' => 'list-position-upfront',
            'status' => true,
            'message' => 'data found !!!',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            $rules = [
                // 'upfront' => 'required|array',
                // 'upfront.*.product_id' => 'required|integer',
                // 'upfront.*.upfront_status' => 'required|integer',
                // 'upfront.*.data' => 'required|array',
                // 'upfront.*.data.*.milestone_id' => 'required|integer',
                // 'upfront.*.data.*.position_id' => 'required|string',
                // 'upfront.*.data.*.self_gen_user' => 'required|integer',
                // 'upfront.*.data.*.schemas' => 'required|array',
                // 'upfront.*.data.*.schemas.*.milestone_schema_trigger_id' => 'required|integer',
                // 'upfront.*.data.*.schemas.*.core_position_id' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.upfront_ammount' => 'nullable|numeric',
                // 'upfront.*.data.*.schemas.*.upfront_ammount_locked' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.calculated_by' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.calculated_locked' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.upfront_system' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.upfront_system_locked' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.upfront_limit' => 'nullable|numeric',
                // 'upfront.*.data.*.schemas.*.commission_parentage' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.commission_amount_type' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.commission_parentag_hiring_locked' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.commission_amount_type_locked' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.commission_structure_type' => 'nullable|string',
                // 'upfront.*.data.*.schemas.*.commission_parentag_type_hiring_locked' => 'nullable|string',
            ];

            // Custom validation
            // $validator = Validator::make($request->all(), $rules);

            // if ($validator->fails()) {
            //     return response()->json([
            //         'errors' => $validator->errors()
            //     ], 422);
            // }
            // return $request->all();
            $positionid = $request->position_id;
            PositionCommissionUpfronts::where(['position_id' => $positionid])->delete();
            TiersPositionUpfront::where(['position_id' => $positionid])->delete();
            foreach ($request->upfront as $upfront) {
                //  PositionCommissionUpfronts::where(['position_id'=>$positionid,'product_id'=>$upfront['product_id']])->delete();
                $product_id = $upfront['product_id'];
                $upfront_status = $upfront['upfront_status'];
                if (count($upfront['data']) == 0) {
                    $core_position_id = null;
                    $milestone = null;
                    $milestone_schema_trigger_id = null;
                    $self_gen_user = 0;
                    $upfront_ammount = 0;
                    $upfront_ammount_locked = 0;
                    $calculated_by = 'per kw';
                    $calculated_locked = 0;
                    $upfront_status = 0;
                    $status_upfront = 0;
                    $upfront_system = 'Fixed';
                    $upfront_system_locked = 0;
                    $upfront_limit = 0;

                    $tiers_id = null;
                    $tiers_advancement = null;
                    $tiers_hiring_locked = 0;

                    // PositionCommissionUpfronts::where(['position_id'=>$positionid,'product_id'=>$product_id])->delete();
                    $PCUpfronts = new PositionCommissionUpfronts;
                    $PCUpfronts->position_id = $positionid;
                    $PCUpfronts->core_position_id = $core_position_id;
                    $PCUpfronts->product_id = $product_id;
                    $PCUpfronts->milestone_schema_id = $milestone;
                    $PCUpfronts->milestone_schema_trigger_id = $milestone_schema_trigger_id;
                    $PCUpfronts->self_gen_user = $self_gen_user;
                    $PCUpfronts->status_id = $status_upfront;
                    $PCUpfronts->upfront_ammount = $upfront_ammount;
                    $PCUpfronts->upfront_ammount_locked = $upfront_ammount_locked;
                    $PCUpfronts->calculated_by = $calculated_by;
                    $PCUpfronts->calculated_locked = $calculated_locked;
                    $PCUpfronts->upfront_status = $upfront_status;
                    $PCUpfronts->upfront_system = $upfront_system;
                    $PCUpfronts->upfront_system_locked = $upfront_system_locked;
                    $PCUpfronts->upfront_limit = $upfront_limit;
                    $PCUpfronts->tiers_id = $tiers_id;
                    $PCUpfronts->tiers_advancement = $tiers_advancement;
                    $PCUpfronts->tiers_hiring_locked = $tiers_hiring_locked;
                    $PCUpfronts->save();

                    $user = User::where('sub_position_id', $positionid)->get();
                    foreach ($user as $users) {
                        $userUpfront = User::where('id', $users->id)->first();
                        $userUpfront->upfront_pay_amount = $upfront_ammount;
                        $userUpfront->upfront_sale_type = $calculated_by;
                        $userUpfront->save();
                    }
                } elseif (count($upfront['data']) > 0) {
                    // return $upfront['data'];
                    foreach ($upfront['data'] as $data) {
                        $milestone = null;
                        if (isset($data['milestone_id']) && $data['milestone_id']) {
                            $milestone = $data['milestone_id'];
                        }
                        $core_position_id = null;
                        if (isset($data['core_position_id'])) {
                            $core_position_id = $data['core_position_id'];

                        }
                        $commision_status = PositionCommission::where(['position_id' => $positionid, 'product_id' => $product_id])->first();
                        $status_upfront = null;
                        if ($commision_status != null) {
                            if ($commision_status->commission_status == 1) {
                                $status_upfront = 0;
                            }
                        }
                        // PositionCommissionUpfronts::where(['position_id'=>$positionid,'product_id'=>$product_id,'milestone_schema_id'=>null,'core_position_id'=>null])->delete();
                        // PositionCommissionUpfronts::where(['position_id'=>$positionid,'product_id'=>$product_id,'milestone_schema_id'=>$milestone])->delete();
                        $self_gen_user = $data['self_gen_user'];
                        foreach ($data['schemas'] as $schema) {
                            //    return $schema;
                            //  deleting previous row to insert again update

                            $upfront_ammount_locked = 0;
                            if (isset($schema['upfront_ammount_locked']) && $schema['upfront_ammount_locked']) {
                                $upfront_ammount_locked = 1;
                            }

                            $calculated_locked = 0;
                            if (isset($schema['calculated_locked']) && $schema['calculated_locked']) {
                                $calculated_locked = 1;
                            }

                            $upfront_system_locked = 0;
                            if (isset($schema['upfront_system_locked']) && $schema['upfront_system_locked']) {
                                $upfront_system_locked = 1;
                            }
                            $calculated_by = 'per kw';
                            if (isset($schema['calculated_by']) && $schema['calculated_by']) {
                                $calculated_by = $schema['calculated_by'];
                            }
                            $upfront_ammount = null;
                            if (isset($schema['upfront_ammount']) && $schema['upfront_ammount']) {
                                $upfront_ammount = $schema['upfront_ammount'];
                            }
                            $upfront_system = 'Fixed';
                            if (isset($schema['upfront_system']) && $schema['upfront_system']) {
                                $upfront_system = $schema['upfront_system'];
                            }
                            $upfront_limit = null;
                            if (isset($schema['upfront_limit']) && $schema['upfront_limit']) {
                                $upfront_limit = $schema['upfront_limit'];
                            }
                            $milestone_schema_trigger_id = null;
                            if (isset($schema['milestone_schema_trigger_id']) && $schema['milestone_schema_trigger_id']) {
                                $milestone_schema_trigger_id = $schema['milestone_schema_trigger_id'];
                            }

                            $tiers_id = isset($schema['tiers_id']) ? $schema['tiers_id'] : null;
                            $tiers_advancement = isset($schema['tiers_advancement']) ? $schema['tiers_advancement'] : null;
                            $tiers_hiring_locked = isset($schema['tiers_hiring_locked']) ? $schema['tiers_hiring_locked'] : 0;

                            $PCUpfronts = new PositionCommissionUpfronts;
                            $PCUpfronts->position_id = $positionid;
                            $PCUpfronts->core_position_id = $core_position_id;
                            $PCUpfronts->product_id = $product_id;
                            $PCUpfronts->milestone_schema_id = $milestone;
                            $PCUpfronts->milestone_schema_trigger_id = $milestone_schema_trigger_id;
                            $PCUpfronts->self_gen_user = $self_gen_user;
                            $PCUpfronts->status_id = ($status_upfront === 0) ? $status_upfront : $upfront_status;  // $upfront_status;
                            $PCUpfronts->upfront_ammount = $upfront_ammount;
                            $PCUpfronts->upfront_ammount_locked = $upfront_ammount_locked;
                            $PCUpfronts->calculated_by = $calculated_by;
                            $PCUpfronts->calculated_locked = $calculated_locked;
                            $PCUpfronts->upfront_status = $upfront_status;
                            $PCUpfronts->upfront_system = $upfront_system;
                            $PCUpfronts->upfront_system_locked = $upfront_system_locked;
                            $PCUpfronts->upfront_limit = $upfront_limit;
                            $PCUpfronts->tiers_id = $tiers_id;
                            $PCUpfronts->tiers_advancement = $tiers_advancement;
                            $PCUpfronts->tiers_hiring_locked = $tiers_hiring_locked;

                            // Custom Sales Field support
                            if (isset($schema['custom_sales_field_id']) && $schema['custom_sales_field_id']) {
                                $PCUpfronts->custom_sales_field_id = $schema['custom_sales_field_id'];
                            }

                            $PCUpfronts->save();

                            $lastid = $PCUpfronts->id;
                            $tiers_id = isset($schema['tiers_id']) && $schema['tiers_id'] != '' ? $schema['tiers_id'] : 0;
                            $range = isset($schema['tiers_range']) && $schema['tiers_range'] != '' ? $schema['tiers_range'] : '';
                            if ($tiers_id > 0) {
                                if (is_array($range) && ! empty($range)) {
                                    foreach ($range as $rang) {
                                        TiersPositionUpfront::create([
                                            'position_id' => $positionid,
                                            'position_upfront_id' => $lastid,
                                            'product_id' => $product_id,
                                            'tiers_schema_id' => $tiers_id,
                                            'tiers_advancement' => $tiers_advancement,
                                            'tiers_levels_id' => $rang['id'] ?? null,
                                            'upfront_value' => $rang['value'] ?? null,
                                        ]);
                                    }
                                }
                            }

                            $user = User::where('sub_position_id', $positionid)->get();
                            foreach ($user as $users) {
                                $userUpfront = User::where('id', $users->id)->first();
                                $userUpfront->upfront_pay_amount = $upfront_ammount;
                                $userUpfront->upfront_sale_type = $calculated_by;
                                $userUpfront->save();
                            }
                        }
                    }
                }

            }

            return response()->json([
                'ApiName' => 'add-position-upfront',
                'status' => true,

                'message' => 'data added successfully',
            ], 200);
        } catch (Exception $e) {
            // Handle any exceptions
            return response()->json([
                'ApiName' => 'add-position-upfront',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

    }

    public function edit(Request $request, $id)
    {
        //  return $request->milestone_schema_id;
        // $id = position_id
        //   return "rteer";
        //  $getdata = PositionCommissionUpfronts::where(['position_id'=>$request->position_id,'product_id'=>$request->product_id,'milestone_schema_id'=>$request->milestone_schema_id])->get();
        $data = Positions::with('Upfront')->where('id', $id)->first();
        
        // Check if Custom Sales Fields feature is enabled (for display formatting, using cached helper)
        $isCustomFieldsEnabledForUpfront = CustomSalesFieldHelper::isFeatureEnabled();
        
        if ($data) {
            $collectionupfront = collect($data->upfront);

            // Group by product_id, then by upfront_status
            $groupedupfront = $collectionupfront->groupBy('product_id')->map(function ($groupByProduct) use ($isCustomFieldsEnabledForUpfront) {
                return $groupByProduct->groupBy('upfront_status')->map(function ($groupByStatus) use ($isCustomFieldsEnabledForUpfront) {
                    return [
                        'product_id' => $groupByStatus->first()->product_id,
                        'upfront_status' => $groupByStatus->first()->upfront_status,
                        'data' => $groupByStatus->groupBy('core_position_id')->map(function ($groupByCorePosition) use ($isCustomFieldsEnabledForUpfront) {
                            return [
                                'milestone_id' => $groupByCorePosition->first()->milestone_schema_id,
                                'core_position_id' => $groupByCorePosition->first()->core_position_id,
                                'self_gen_user' => $groupByCorePosition->first()->self_gen_user,
                                'schemas' => $groupByCorePosition->groupBy('milestone_schema_id')->flatMap(function ($groupByMilestone) use ($isCustomFieldsEnabledForUpfront) {
                                    return $groupByMilestone->map(function ($item) use ($isCustomFieldsEnabledForUpfront) {
                                        return [
                                            'milestone_schema_trigger_id' => $item->milestone_schema_trigger_id,
                                            'upfront_ammount' => (string) $item->upfront_ammount,
                                            'upfront_ammount_locked' => (string) $item->upfront_ammount_locked,
                                            // Only use custom_field_X format when feature is enabled
                                            'calculated_by' => ($isCustomFieldsEnabledForUpfront && $item->calculated_by === 'custom field' && $item->custom_sales_field_id) ? 'custom_field_' . $item->custom_sales_field_id : $item->calculated_by,
                                            'calculated_locked' => (string) $item->calculated_locked,
                                            'upfront_system' => $item->upfront_system,
                                            'upfront_system_locked' => (string) $item->upfront_system_locked,
                                            'upfront_limit' => (string) $item->upfront_limit,
                                            'tiers_id' => $item->tiers_id,
                                            'tiers_advancement' => $item->tiers_advancement,
                                            'tiers_hiring_locked' => $item->tiers_hiring_locked,
                                            'custom_sales_field_id' => ($isCustomFieldsEnabledForUpfront && $item->calculated_by === 'custom field') ? $item->custom_sales_field_id : null,
                                            'tiers_range' => $item->tiersRange->map(function ($range) {
                                                return [
                                                    'id' => $range->tiers_levels_id,
                                                    'value' => $range->upfront_value,
                                                ];
                                            })->values(),
                                        ];
                                    })->values();
                                })->values(),
                            ];
                        })->values(),
                    ];
                })->values();
            })->values()->flatten(1);

            $upfront_data = ['upfront' => $groupedupfront];
            if (count($upfront_data) > 0) {
                return response()->json([
                    'ApiName' => 'get-position-upfront',
                    'status' => true,
                    'data' => $upfront_data,
                    'message' => 'data found successfully',
                ], 200);

            } else {
                return response()->json([
                    'ApiName' => 'get-position-upfront',
                    'status' => false,

                    'message' => 'data not found for this position id && Product id',
                ], 404);
            }
        }

        return response()->json([
            'ApiName' => 'get-position-upfront',
            'status' => false,

            'message' => 'data not found for this position id',
        ], 404);

    }

    public function delete($id)
    {
        // return "ewtewe";
        // $delete = PositionCommissionUpfronts::where(['position_id'=>$request->position_id,'product_id'=>$request->product_id,'milestone_schema_id'=>$request->milestone_schema_id])->delete();
        $delete = PositionCommissionUpfronts::where(['position_id' => $id])->delete();
        TiersPositionUpfront::where(['position_id' => $id])->delete();
        if ($delete) {

            return response()->json([
                'ApiName' => 'delete-position-upfront',
                'status' => true,
                'data' => $delete,
                'message' => 'record deleted successfully',
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'delete-position-upfront',
                'status' => false,
                'message' => 'record not found for deleted',
            ], 404);
        }
    }
}
