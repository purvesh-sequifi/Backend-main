<?php

namespace App\Http\Controllers\API\V2\PositionWages;

use App\Http\Controllers\Controller;
use App\Models\OnboardingEmployees;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\PositionsDeductionLimit;
use App\Models\PositionTierOverride;
use App\Models\PositionWage;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class PositionWagesController extends Controller
{
    public function index(): JsonResponse
    {
        $data = PositionWage::all();

        return response()->json([
            'ApiName' => 'list-position-wages',
            'status' => true,
            'message' => 'data found !!!',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
            // Validate the incoming request data
            //  return $request['wages_status'];

            if ($request['wages_status'] == '1') {
                $validator = Validator::make($request->all(), [ // Validation rules
                    // 'pay_type' => 'required',
                    // 'pay_type_lock' => 'required',
                    'pay_rate' => 'required',
                    // 'pay_rate_lock' => 'required',
                    // 'pay_rate_type' => 'required',
                    'expected_weekly_hours' => 'required',
                    'expected_weekly_hours_lock' => 'required',
                    // 'overtime_rate' => 'required',
                    // 'overtime_rate_lock' => 'required',
                    // 'position_id' => 'required',
                ]);

                // Check if validation failed
                if ($validator->fails()) {
                    return response()->json(['error' => $validator->errors()], 400);
                }
            }
            if ($request['wages_status'] == 0) {
                if (! PositionWage::where(['position_id' => $request['position_id'], 'wages_status' => '0'])->first()) {
                    $checkexist = $this->positionassigncheck($request['position_id']);
                    if ($checkexist > 0) {
                        return response()->json([
                            'ApiName' => 'add-position-products',
                            'status' => false,
                            'message' => 'This position is already onboarded or hired and cannot be disabled',
                        ], 400);
                    }
                }
            }

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
            $posif = PositionWage::where('position_id', $request['position_id'])->first();
            if (empty($posif->position_id)) {
                $positionwages = new PositionWage;
                $positionwages->position_id = $request['position_id'];
                $positionwages->pay_type = $request['pay_type'];
                $positionwages->pay_type_lock = $pay_type_lock;
                $positionwages->pay_rate = $request['pay_rate'];
                $positionwages->pay_rate_type = $pay_rate_type;
                $positionwages->pay_rate_lock = $pay_rate_lock;
                $positionwages->pto_hours = $request['pto_hours'];
                $positionwages->pto_hours_lock = $pto_hours_lock;
                $positionwages->unused_pto_expires = $request['unused_pto_expires'];
                $positionwages->unused_pto_expires_lock = $unused_pto_expires_lock;
                $positionwages->expected_weekly_hours = $request['expected_weekly_hours'];
                $positionwages->expected_weekly_hours_lock = $expected_weekly_hours_lock;
                $positionwages->overtime_rate = $request['overtime_rate'];
                $positionwages->overtime_rate_lock = $overtime_rate_lock;
                $positionwages->wages_status = $request['wages_status'];
                $positionwages->save();
            } else {

                $positionwages['position_id'] = $request['position_id'];
                $positionwages['pay_type'] = $request['pay_type'];
                $positionwages['pay_type_lock'] = $pay_type_lock;
                $positionwages['pay_rate'] = $request['pay_rate'];
                $positionwages['pay_rate_type'] = $pay_rate_type;
                $positionwages['pay_rate_lock'] = $pay_rate_lock;
                $positionwages['pto_hours'] = $request['pto_hours'];
                $positionwages['pto_hours_lock'] = $pto_hours_lock;
                $positionwages['unused_pto_expires'] = $request['unused_pto_expires'];
                $positionwages['unused_pto_expires_lock'] = $unused_pto_expires_lock;
                $positionwages['expected_weekly_hours'] = $request['expected_weekly_hours'];
                $positionwages['expected_weekly_hours_lock'] = $expected_weekly_hours_lock;
                $positionwages['overtime_rate'] = $request['overtime_rate'];
                $positionwages['overtime_rate_lock'] = $overtime_rate_lock;
                $positionwages['wages_status'] = $request['wages_status'];

                PositionWage::where('position_id', $request['position_id'])->update($positionwages);

            }

            return response()->json([
                'ApiName' => 'add-position-wages',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => $positionwages,
            ], 200);

            // Your logic to handle the valid request data goes here
            // For example, saving the data to the database

            return response()->json(['success' => 'Data validated and processed successfully.'], 200);
        } catch (Exception $e) {
            // Handle any exceptions
            return response()->json([
                'ApiName' => 'add-position-products',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function edit($id): JsonResponse
    {
        try {
            $data = PositionWage::where('position_id', $id)->get();

            return response()->json([
                'ApiName' => 'position-wages-by-id',
                'status' => true,
                'data' => $data,
                'message' => 'Position Wage By ID Found !!!',
            ], 200);

        } catch (Exception $e) {
            // Handle any exceptions
            return response()->json([
                'ApiName' => 'position-wages-by-id',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

    }

    public function remove($id): JsonResponse
    {

        $delete = PositionWage::where('position_id', $id)->delete();

        return response()->json([
            'ApiName' => 'position-wage-by-id- deleted',
            'status' => true,
            'data' => $delete,
            'message' => 'Record Is Deleted Successfully !!!',
        ], 200);

    }

    public function commissionstoress(Request $request)
    {

        try {

            $positionid = $request->position_id;
            $validated = $request->validate([
                'commission' => 'required|array',
                'commission.*.product_id' => 'required|string',
                'commission.*.commission_status' => 'required|string',
                'commission.*.data' => 'required|array',
                'commission.*.data.*.position_id' => 'nullable|string',
                'commission.*.data.*.self_gen_user' => 'required|integer',
                'commission.*.data.*.commission_parentage' => 'required|integer',
                'commission.*.data.*.commission_amount_type' => 'required|string',
                'commission.*.data.*.commission_parentag_hiring_locked' => 'required|integer',
                'commission.*.data.*.commission_amount_type_locked' => 'required|integer',
                'commission.*.data.*.commission_structure_type' => 'required|string',
                'commission.*.data.*.commission_parentag_type_hiring_locked' => 'required|integer',
            ]);
            // return $request->commission;
            foreach ($request->commission as $commissiondata) {
                $product_id = $commissiondata['product_id'];
                $commission_status = $commissiondata['commission_status'];
                // $datata[] = '';
                foreach ($commissiondata['data'] as $data) {
                    PositionCommission::where(['position_id' => $positionid, 'product_id' => $product_id, 'self_gen_user' => $data['self_gen_user']])->delete();
                    $store = new PositionCommission;
                    $store->position_id = $positionid;
                    $store->core_position_id = $data['core_position_id'];
                    $store->product_id = $product_id;
                    $store->self_gen_user = $data['self_gen_user'];
                    $store->commission_parentage = $data['commission_parentage'];
                    $store->commission_amount_type = $data['commission_amount_type'];
                    $store->commission_status = $commission_status;
                    $store->commission_parentag_hiring_locked = $data['commission_parentag_hiring_locked'];
                    $store->commission_amount_type_locked = $data['commission_amount_type_locked'];
                    $store->commission_structure_type = $data['commission_structure_type'];
                    $store->commission_parentag_type_hiring_locked = $data['commission_parentag_type_hiring_locked'];
                    $datata[] = $store;
                    $store->save();
                }
                // seeter ---3
                // closer --2
                // selfgen -0

            }

            return response()->json([
                'ApiName' => 'add-position-commission',
                'status' => true,

                'message' => 'data added successfully',
            ], 200);

        } catch (Exception $e) {
            // Handle any exceptions
            return response()->json([
                'ApiName' => 'add-position-commission',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

    }

    public function upfrontstore(Request $request)
    {

        // return $request->all();
        try {
            $rules = [
                'upfront' => 'required|array',
                'upfront.*.product_id' => 'required|integer',
                'upfront.*.upfront_status' => 'required|integer',
                'upfront.*.data' => 'required|array',
                'upfront.*.data.*.milestone_id' => 'required|integer',
                'upfront.*.data.*.position_id' => 'required|string',
                'upfront.*.data.*.self_gen_user' => 'required|integer',
                'upfront.*.data.*.schemas' => 'required|array',
                'upfront.*.data.*.schemas.*.milestone_schema_trigger_id' => 'required|integer',
                'upfront.*.data.*.schemas.*.core_position_id' => 'nullable|string',
                'upfront.*.data.*.schemas.*.upfront_ammount' => 'nullable|numeric',
                'upfront.*.data.*.schemas.*.upfront_ammount_locked' => 'nullable|string',
                'upfront.*.data.*.schemas.*.calculated_by' => 'nullable|string',
                'upfront.*.data.*.schemas.*.calculated_locked' => 'nullable|string',
                'upfront.*.data.*.schemas.*.upfront_system' => 'nullable|string',
                'upfront.*.data.*.schemas.*.upfront_system_locked' => 'nullable|string',
                'upfront.*.data.*.schemas.*.upfront_limit' => 'nullable|numeric',
                'upfront.*.data.*.schemas.*.commission_parentage' => 'nullable|string',
                'upfront.*.data.*.schemas.*.commission_amount_type' => 'nullable|string',
                'upfront.*.data.*.schemas.*.commission_parentag_hiring_locked' => 'nullable|string',
                'upfront.*.data.*.schemas.*.commission_amount_type_locked' => 'nullable|string',
                'upfront.*.data.*.schemas.*.commission_structure_type' => 'nullable|string',
                'upfront.*.data.*.schemas.*.commission_parentag_type_hiring_locked' => 'nullable|string',
            ];

            // Custom validation
            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            foreach ($request->upfront as $upfront) {
                $product_id = $upfront['product_id'];
                $upfront_status = $upfront['upfront_status'];
                foreach ($upfront['data'] as $data) {
                    $milestone = $data['milestone_id'];
                    $positionid = $data['position_id'];
                    $self_gen_user = $data['self_gen_user'];
                    foreach ($data['schemas'] as $schema) {

                        // return $schema['milestone_schema_trigger_id'];
                        PositionCommissionUpfronts::where(['position_id' => $positionid, 'product_id' => $product_id, 'milestone_schema_id' => $milestone])->delete(); //  deleting previous row to insert again update
                        $PCUpfronts = new PositionCommissionUpfronts;
                        $PCUpfronts->position_id = $positionid;
                        $PCUpfronts->core_position_id = $schema['core_position_id'];
                        $PCUpfronts->product_id = $product_id;
                        $PCUpfronts->milestone_schema_id = $milestone;
                        $PCUpfronts->milestone_schema_trigger_id = $schema['milestone_schema_trigger_id'];
                        $PCUpfronts->self_gen_user = $self_gen_user;
                        $PCUpfronts->status_id = 1;
                        $PCUpfronts->upfront_ammount = $schema['upfront_ammount'];
                        $PCUpfronts->upfront_ammount_locked = $schema['upfront_ammount_locked'];
                        $PCUpfronts->calculated_by = $schema['calculated_by'];
                        $PCUpfronts->calculated_locked = $schema['calculated_locked'];
                        $PCUpfronts->upfront_status = $upfront_status;
                        $PCUpfronts->upfront_system = $schema['upfront_system'];
                        $PCUpfronts->upfront_system_locked = $schema['upfront_system_locked'];
                        $PCUpfronts->upfront_limit = $schema['upfront_limit'];
                        $PCUpfronts->save();
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

    public function PositionCommissionDeduction(Request $request)
    {

        // return $request->deduction;
        $data = PositionCommission::where('position_id', $request['position_id'])->first();
        PositionCommissionDeduction::where('position_id', $request->position_id)->delete();
        PositionsDeductionLimit::where('position_id', $request->position_id)->delete();
        //   return $request->deduction_status;
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

    public function PositionCommissionoverride(Request $request): JsonResponse
    {

        $having = PositionOverride::where('position_id', $request->position_id)->first();
        $status = 0;
        // if($having == null){
        if (count($request->override) > 0 && $request->override != null) {
            PositionOverride::where('position_id', $request->position_id)->delete();
            foreach ($request->override as $data) {
                $store = new PositionOverride;
                $store->position_id = $request->position_id;
                $store->product_id = $data['product_id'];
                $store->override_id = $data['override_id'];
                $store->settlement_id = $data['settlement_id'];
                $store->status = $data['status'];
                $store->override_ammount = $data['override_ammount'];
                $store->override_ammount_locked = $data['override_ammount_locked'];
                $store->type = $data['type'];
                $store->override_type_locked = $data['override_type_locked'];
                $store->save();
                if ($store) {
                    $status = 1;
                }

            }
        }
        // }
        $result = PositionTierOverride::where('position_id', $request->position_id)->first();
        // if($result == null){
        if ($request->tier_override_status == 1) {
            $result = PositionTierOverride::where('position_id', $request->position_id)->delete();
            $tieroveride = new PositionTierOverride;
            $tieroveride->position_id = $request['position_id'];
            $tieroveride->tier_status = $request['tier_status'];
            $tieroveride->sliding_scale = $request['sliding_scale'];
            $tieroveride->sliding_scale_locked = $request['sliding_scale_locked'];
            $tieroveride->levels = $request['levels'];
            $tieroveride->level_locked = $request['level_locked'];
            $tieroveride->save();
            if ($tieroveride) {
                $status = 2;
            }

        }

        // }

        if ($status != 0) {
            return response()->json([
                'ApiName' => 'position commission override ',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $store,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'position commission override ',
                'status' => false,
                'message' => 'please check you position that can not be duplicate !!!',
                'data' => '',
            ], 200);

        }

    }

    public function PositionCommissionSettelement(Request $request): JsonResponse
    {

        try {
            $rules = [
                'position_id' => 'required|integer',
                'status' => 'required|integer',
                'settlement' => 'nullable|array',
                'settlement.*.product_id' => 'nullable|integer',
                'settlement.*.commission_withheld' => 'nullable|numeric',
                'settlement.*.commission_type' => 'nullable|string',
                'settlement.*.maximum_withheld' => 'nullable|numeric',
                'settlement.*.override_settlement' => 'nullable|integer',
                'settlement.*.clawback_settlement' => 'nullable|integer',
                'settlement.*.stack_settlement' => 'nullable|integer',
            ];

            // Custom validation
            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors(),
                ], 422);
            }

            $position_id = $request->position_id;
            $status = $request->status;

            foreach ($request->settlement as $settle) {
                PositionReconciliations::where(['position_id' => $position_id, 'product_id' => $settle['product_id']])->delete();
                $settlestore = new PositionReconciliations;
                $settlestore->position_id = $position_id;
                $settlestore->product_id = $settle['product_id'];
                $settlestore->commission_withheld = $settle['commission_withheld'];
                $settlestore->commission_type = $settle['commission_type'];
                $settlestore->maximum_withheld = $settle['maximum_withheld'];
                $settlestore->override_settlement = $settle['override_settlement'];
                $settlestore->clawback_settlement = $settle['clawback_settlement'];
                $settlestore->stack_settlement = $settle['stack_settlement'];
                $settlestore->save();

                // $settlestore->commission_withheld =

            }

            return response()->json([
                'ApiName' => 'add-position-commission-settlement',
                'status' => true,
                'message' => 'data  store successfully',
            ], 200);
        } catch (Exception $e) {
            // Handle any exceptions
            return response()->json([
                'ApiName' => 'add-position-commission-settlement',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

    }

    protected function positionassigncheck($position_id)
    {
        $assignposition = OnboardingEmployees::select('sub_position_id')
            ->where('sub_position_id', $position_id)
            ->union(
                User::select('sub_position_id')->where('sub_position_id', $position_id)
            )
            ->distinct()
            ->count('sub_position_id');

        return $assignposition;
    }
}
