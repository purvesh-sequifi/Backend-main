<?php

namespace App\Http\Controllers\API\V2\PositionOverride;

use App\Helpers\CustomSalesFieldHelper;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\OverridesType;
use App\Models\PositionOverride;
use App\Models\Positions;
use App\Models\PositionTierOverride;
use App\Models\TiersPositionOverrides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class PositionOverrideController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Positions::withcount('peoples')->with('product', 'departmentDetail', 'Commission', 'Upfront', 'deductionname', 'Override', 'deductionlimit', 'OverrideTier', 'reconciliation', 'payFrequency', 'position_wage')->get();

        return response()->json([
            'ApiName' => 'list-position-override',
            'status' => true,
            'message' => 'data found !!!',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {

        $having = PositionOverride::where('position_id', $request->position_id)->first();

        // if($having == null){
        if (count($request->overrides) > 0 && $request->overrides != null) {
            $position_id = $request->position_id;
            //  $status = $request->status;
            PositionOverride::where('position_id', $request->position_id)->delete();
            TiersPositionOverrides::where(['position_id' => $request->position_id])->delete();
            
            // Check if Custom Sales Fields feature is enabled ONCE before loops
            $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();
            
            foreach ($request->overrides as $data) {
                $product_id = $data['product_id'];
                $overide_status = $data['status'];
                //  PositionOverride::where(['position_id'=>$request->position_id,'product_id'=>$product_id])->delete();
                foreach ($data['override'] as $over) {
                    $store = new PositionOverride;
                    $store->position_id = $position_id;
                    $store->product_id = $product_id;
                    $override_id = null;
                    if (isset($over['override_id']) && $over['override_id']) {
                        $override_id = $over['override_id'];
                    }
                    $settlement_id = null;
                    if (isset($over['settlement_id']) && $over['settlement_id']) {
                        $settlement_id = $over['settlement_id'];
                    }
                    $override_amount = 0;
                    if (isset($over['override_ammount']) && $over['override_ammount']) {
                        $override_amount = $over['override_ammount'];
                    }
                    $override_amount_locked = 0;
                    if (isset($over['override_ammount_locked']) && $over['override_ammount_locked']) {
                        $override_amount_locked = $over['override_ammount_locked'];
                    }
                    $type = null;
                    if (isset($over['type']) && $over['type']) {
                        $type = $over['type'];
                    }
                    $override_type_locked = 0;
                    if (isset($over['override_type_locked']) && $over['override_type_locked']) {
                        $override_type_locked = $over['override_type_locked'];
                    }

                    $tiers_id = isset($over['tiers_id']) ? $over['tiers_id'] : null;
                    $tiers_advancement = isset($over['tiers_advancement']) ? $over['tiers_advancement'] : null;
                    $tiers_hiring_locked = isset($over['tiers_hiring_locked']) ? $over['tiers_hiring_locked'] : 0;

                    // Custom Sales Field support: Parse custom_field_X format (using cached check)
                    $customSalesFieldId = null;
                    
                    if ($isCustomFieldsEnabled && $type && preg_match('/^custom_field_(\d+)$/', $type, $matches)) {
                        $customSalesFieldId = (int) $matches[1];
                        $type = 'custom field';
                    }

                    $store->override_id = $override_id;
                    $store->status = $over['status'];
                    $store->override_ammount = $override_amount;
                    $store->override_ammount_locked = $override_amount_locked;
                    $store->type = $type;
                    $store->override_type_locked = $override_type_locked;
                    $store->tiers_id = $tiers_id;
                    $store->tiers_advancement = $tiers_advancement;
                    $store->tiers_hiring_locked = $tiers_hiring_locked;

                    // Custom Sales Field support: Save to the appropriate column based on override_id
                    if ($isCustomFieldsEnabled && $customSalesFieldId) {
                        switch ($override_id) {
                            case 1: // Direct override
                                $store->direct_custom_sales_field_id = $customSalesFieldId;
                                break;
                            case 2: // Indirect override
                                $store->indirect_custom_sales_field_id = $customSalesFieldId;
                                break;
                            case 3: // Office override
                                $store->office_custom_sales_field_id = $customSalesFieldId;
                                break;
                        }
                    }

                    $store->save();

                    $lastid = $store->id;
                    $tiers_id = isset($over['tiers_id']) && $over['tiers_id'] != '' ? $over['tiers_id'] : 0;
                    $range = isset($over['tiers_range']) && $over['tiers_range'] != '' ? $over['tiers_range'] : '';
                    if ($tiers_id > 0) {
                        if (is_array($range) && ! empty($range)) {
                            foreach ($range as $rang) {
                                TiersPositionOverrides::create([
                                    'position_id' => $position_id,
                                    'position_overrides_id' => $lastid,
                                    'product_id' => $product_id,
                                    'tiers_schema_id' => $tiers_id,
                                    'tiers_advancement' => $tiers_advancement,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'override_value' => $rang['value'] ?? null,
                                    // 'from_dealer_fee' => $rang['value'] ?? null,
                                ]);
                            }
                        }
                    }

                }
                $gettier = PositionTierOverride::where('position_id', $position_id)->first();
                if ($gettier == null) {
                    if ($overide_status == 1) {
                        PositionTierOverride::create([
                            'position_id' => $position_id,
                            'tier_status' => $overide_status,
                            // "sliding_scale"           =>  $request['sliding_scale'],
                            // "sliding_scale_locked"           =>  $request['sliding_scale_locked'],
                            // "levels"                   =>  $request['levels'],
                            // "level_locked"                   =>  $request['level_locked'],
                        ]);
                    }
                }

            }

            $position_status = 0;
            if (isset($request['position_status']) && $request['position_status']) {
                $position_status = $request['position_status'];
            }
            $posidata = Positions::where('id', $position_id)->update(['setup_status' => $position_status]);

            return response()->json([
                'ApiName' => 'add-position-override',
                'status' => true,
                'data' => $store,
                'message' => 'data found successfully',
            ], 200);
        }

    }

    public function edit($id)
    {
        //  return $request->milestone_schema_id;
        // $id = position_id
        //  return "rteer";
        //   $getdata = PositionOverride::where(['position_id'=>$id])->get();
        $data = Positions::withcount('peoples')->with('Override', 'OverrideTier')->where('id', $id)->first();
        if ($data) {
            if (isset($data->override) && $data->override !== null && $data->override !== '') {

                // $result = [
                //     "position_id" => $id,  // Set as required
                //     "overrides" => [] // This will be populated with grouped data
                // ];

                // Group data by product_id
                $groupedData = [];
                
                // Check if Custom Sales Fields feature is enabled ONCE before loop
                $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();

                foreach ($data->override as $item) {
                    $productId = $item['product_id'];

                    // Initialize product grouping if not already done
                    $tierstatus = PositionTierOverride::where('position_id', $id)->first()->tier_status;
                    if (! isset($groupedData[$productId])) {
                        $groupedData[$productId] = [
                            'product_id' => (string) $productId,
                            'status' => ($tierstatus) ? $tierstatus : null,  // Example; set according to your logic
                            'override' => [],
                        ];
                    }

                    // Fetch the overrides_type from the OverridesType model
                    $overrideType = OverridesType::where('id', $item['override_id'])->first();
                    $overridetype = $overrideType ? $overrideType->overrides_type : 'Unknown'; // Fallback if not found
                    
                    // Determine the custom field ID based on override_id (only when feature enabled, using cached check)
                    $customFieldId = null;
                    $displayType = $item['type'] ?? 'per kw';
                    
                    if ($isCustomFieldsEnabled && ($item['type'] ?? '') === 'custom field') {
                        switch ($item['override_id']) {
                            case 1:
                                $customFieldId = $item->direct_custom_sales_field_id;
                                break;
                            case 2:
                                $customFieldId = $item->indirect_custom_sales_field_id;
                                break;
                            case 3:
                                $customFieldId = $item->office_custom_sales_field_id;
                                break;
                        }
                        
                        // Format type as custom_field_X if applicable (only when feature enabled)
                        if ($customFieldId) {
                            $displayType = 'custom_field_' . $customFieldId;
                        }
                    }

                    // Build override data array
                    $overrideData = [
                        'override_id' => $item['override_id'],
                        'status' => $item['status'],
                        'override_ammount' => $item['override_ammount'],
                        'override_ammount_locked' => $item['override_ammount_locked'],
                        'type' => $displayType,
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
                    
                    // Only include custom field IDs when feature is enabled
                    if ($isCustomFieldsEnabled) {
                        $overrideData['custom_sales_field_id'] = $customFieldId;
                        $overrideData['direct_custom_sales_field_id'] = $item->direct_custom_sales_field_id ?? null;
                        $overrideData['indirect_custom_sales_field_id'] = $item->indirect_custom_sales_field_id ?? null;
                        $overrideData['office_custom_sales_field_id'] = $item->office_custom_sales_field_id ?? null;
                    }
                    
                    // Append the override data to the appropriate product_id entry
                    $groupedData[$productId]['override'][] = $overrideData;
                }

                // Convert the grouped data to a list format and assign it to the override key
                $overrides = array_values($groupedData);
                $override_data = $overrides;
            } else {
                $override_data = null;
            }

            if (count($override_data) > 0) {
                return response()->json([
                    'ApiName' => 'get-position-override',
                    'status' => true,
                    'overrides' => $override_data,
                    'message' => 'data found successfully',
                ], 200);

            }

        } else {
            return response()->json([
                'ApiName' => 'get-position-override',
                'status' => false,

                'message' => 'data not found for this position id',
            ], 400);
        }

    }

    public function delete($id)
    {
        // return "ewtewe";
        $delete = PositionOverride::where(['position_id' => $id])->delete();
        PositionTierOverride::where(['position_id' => $id])->delete();
        TiersPositionOverrides::where(['position_id' => $id])->delete();
        if ($delete) {

            return response()->json([
                'ApiName' => 'delete-position-deduction',
                'status' => true,
                'data' => $delete,
                'message' => 'Record Deleted Successfully',
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'delete-position-deduction',
                'status' => false,
                'message' => 'Record Not Found For Deleted',
            ], 400);
        }
    }
}
