<?php

namespace App\Http\Controllers\API\V2\PositionDeduction;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\Positions;
use App\Models\PositionsDeductionLimit;
use App\Models\User;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionDeductionController extends Controller
{
    public function index(): JsonResponse
    {
        $data = PositionCommissionDeduction::all();

        return response()->json([
            'ApiName' => 'list-position-deduction',
            'status' => true,
            'message' => 'data found !!!',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {

        //    $deduction_locked = $request->deduction_locked;

        $deduction_status = $request->deduction_status;
        $limit_ammount = $request->limit_ammount;
        $limit_type = $request->limit_type;
        $limit = $request->limit;
        $position_id = $request->position_id;
        PositionCommissionDeduction::where(['position_id' => $position_id])->delete();
        foreach ($request->deduction as $deduction) {

            // PositionCommissionDeduction::where(['position_id'=>$position_id])->delete();
            $deduction_type = null;
            if (isset($deduction['deduction_type']) && $deduction['deduction_type']) {
                $deduction_type = $deduction['deduction_type'];
            }
            $changes_type = null;
            if (isset($deduction['changes_type']) && $deduction['changes_type']) {
                $changes_type = $deduction['changes_type'];
            }
            $changes_field = null;
            if (isset($deduction['changes_field']) && $deduction['changes_field']) {
                $changes_field = $deduction['changes_field'];
            }
            $ammount_par_paycheck = 0;
            if (isset($deduction['ammount_par_paycheck']) && $deduction['ammount_par_paycheck']) {
                $ammount_par_paycheck = $deduction['ammount_par_paycheck'];
            }
            $deduct_id = 0;
            if (isset($deduction['id']) && $deduction['id']) {
                $deduct_id = $deduction['id'];
            }

            if ($deduct_id == false) {
                //  PositionCommissionDeduction::where(['position_id'=>$position_id,'cost_center_id'=>$deduction['cost_center_id']])->delete();

                $deductionsave = new PositionCommissionDeduction;
                $deductionsave->deduction_setting_id = 1;
                $deductionsave->position_id = $position_id;
                $deductionsave->cost_center_id = $deduction['cost_center_id'];
                $deductionsave->deduction_type = $deduction_type;
                $deductionsave->ammount_par_paycheck = $ammount_par_paycheck;
                // $deductionsave->changes_field = $changes_field;
                // $deductionsave->changes_type = $changes_type;
                $deductionsave->save();
                $limitdata = PositionsDeductionLimit::where('position_id', $position_id)->count();
                $setting = PositionCommissionDeductionSetting::where('position_id', $position_id)->count();
                if ($setting > 0) {

                } else {
                    $deduction_locked = 0;
                    if (isset($deduction['deduction_locked']) && $deduction['deduction_locked']) {
                        $deduction_locked = $deduction['deduction_locked'];
                    }
                    PositionCommissionDeductionSetting::create(['position_id' => $position_id, 'status' => $deduction_status, 'deducation_locked' => $deduction_locked]);
                }
                if ($limitdata > 0) {
                    $update['deduction_setting_id'] = 1;
                    // $update['position_id']   = $position_id;
                    $update['status'] = $deduction_status;
                    $update['limit_type'] = $limit_type;
                    if ($limit_ammount == null) {
                        $update['limit_ammount'] = null;
                        $update['limit'] = null;
                    } else {
                        $update['limit_ammount'] = $limit_ammount;
                        $update['limit'] = 'per period';
                    }
                    PositionsDeductionLimit::where('position_id', $position_id)->update($update);

                } else {
                    if ($limit_ammount == null) {
                        $limit_ammount == null;
                        $limit = null;
                    } else {
                        $limit_ammount = $limit_ammount;
                        $limit = 'per period';
                    }
                    PositionsDeductionLimit::create([
                        'deduction_setting_id' => 1,
                        'position_id' => $position_id,
                        'status' => $deduction_status,
                        'limit_type' => $limit_type,
                        'limit_ammount' => $limit_ammount,
                        'limit' => 'per period',
                    ]);
                }
                if (! empty($deduction['changes_type']) && isset($deduction['deduction_type'])) {
                    if ($deduction['changes_type'] == 'new') {

                        $ammount_par_paycheck = 0;

                    }
                }
                $usersdata = User::select('id', 'position_id', 'sub_position_id')->where('sub_position_id', $position_id)->where('is_super_admin', '!=', '1')->get();
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
                                'ammount_par_paycheck' => $ammount_par_paycheck,
                                'deduction_setting_id' => 1,
                                'position_id' => $position_id,
                                'sub_position_id' => $position_id,
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
                                    'limit_value' => isset($limit_ammount) ? $limit_ammount : null,
                                    'amount_par_paycheque' => $ammount_par_paycheck,
                                ];
                                $history = UserDeductionHistory::where('id', $check_data->id)->update($update);

                            } else {

                                $history = UserDeductionHistory::create([
                                    'user_id' => $user->id,
                                    'updater_id' => auth()->user()->id,
                                    'cost_center_id' => $deduction['cost_center_id'],
                                    'amount_par_paycheque' => $ammount_par_paycheck,
                                    'old_amount_par_paycheque' => null,
                                    'sub_position_id' => $position_id,
                                    'limit_value' => $limit_ammount,
                                    'effective_date' => $date,
                                ]);
                            }

                        }

                    }

                }
            } else {
                // PositionCommissionDeduction::where(['position_id'=>$position_id,'cost_center_id'=>$deduction['cost_center_id']])->delete();

                // $deductionsave = new PositionCommissionDeduction();
                $deductionsave['deduction_setting_id'] = 1;
                $deductionsave['position_id'] = $position_id;
                $deductionsave['cost_center_id'] = $deduction['cost_center_id'];
                $deductionsave['deduction_type'] = $deduction_type;
                $deductionsave['ammount_par_paycheck'] = $ammount_par_paycheck;
                // $deductionsave['changes_field'] = $changes_field;
                // $deductionsave['changes_type'] = $changes_type;
                // return $deductionsave;
                PositionCommissionDeduction::where('id', $deduction['id'])->update($deductionsave);
                $limitdata = PositionsDeductionLimit::where('position_id', $position_id)->count();
                $setting = PositionCommissionDeductionSetting::where('position_id', $position_id)->count();
                if ($setting > 0) {

                } else {
                    PositionCommissionDeductionSetting::create(['position_id' => $position_id]);
                }
                if ($limitdata > 0) {

                } else {
                    PositionsDeductionLimit::create([
                        'deduction_setting_id' => 1,
                        'position_id' => $position_id,
                        'status' => $deduction_status,
                        'limit_type' => $limit_type,
                        'limit_ammount' => $limit_ammount,
                        'limit' => $limit,
                    ]);
                }
                if (! empty($deduction['changes_type']) && isset($deduction['deduction_type'])) {
                    if ($deduction['changes_type'] == 'new') {

                        $ammount_par_paycheck = 0;

                    }
                }
                $usersdata = User::select('id', 'position_id', 'sub_position_id')->where('sub_position_id', $position_id)->where('is_super_admin', '!=', '1')->get();
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
                                'ammount_par_paycheck' => $ammount_par_paycheck,
                                'deduction_setting_id' => 1,
                                'position_id' => $position_id,
                                'sub_position_id' => $position_id,
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
                                    'limit_value' => isset($limit_ammount) ? $limit_ammount : null,
                                    'amount_par_paycheque' => $ammount_par_paycheck,
                                ];
                                $history = UserDeductionHistory::where('id', $check_data->id)->update($update);

                            } else {

                                $history = UserDeductionHistory::create([
                                    'user_id' => $user->id,
                                    'updater_id' => auth()->user()->id,
                                    'cost_center_id' => $deduction['cost_center_id'],
                                    'amount_par_paycheque' => $ammount_par_paycheck,
                                    'old_amount_par_paycheque' => null,
                                    'sub_position_id' => $position_id,
                                    'limit_value' => $limit_ammount,
                                    'effective_date' => $date,
                                ]);
                            }

                        }

                    }

                }
            }

        }
        $position_status = 0;
        if (isset($request['position_status']) && $request['position_status']) {
            $position_status = $request['position_status'];
        }

        $posidata = Positions::where('id', $position_id)->update(['setup_status' => $position_status]);

        return response()->json([
            'ApiName' => 'position commission deduction added',
            'status' => true,
            'message' => 'Successfully.',
        ], 200);

    }

    public function edit($id)
    {
        //  return $request->milestone_schema_id;
        // $id = position_id
        //   return "rteer";
        // $getdata = PositionCommissionDeduction::where(['position_id'=>$id])->get();
        $data = Positions::withcount('peoples')->with('deductionsetting', 'deductionname', 'deductionlimit')->where('id', $id)->first();
        if (isset($data) && $data !== null && $data !== '') {

        } else {
            return response()->json(['status' => false, 'message' => 'Positions id is not available.'], 400);
        }
        $deduction = [
            'deduction_status' => $data->deductionlimit->status,
            // 'deduction_locked' => $data->deductionsetting->deducation_locked,
            'limit_ammount' => $data->deductionlimit->limit_ammount,
            'limit' => $data->deductionlimit->limit,
            'limit_type' => $data->deductionlimit->limit_type,

            'deduction' => $data->deductionname->map(function ($deductionname) {
                return [
                    'id' => $deductionname->id,
                    'cost_center_id' => $deductionname->cost_center_id,
                    'deduction_type' => $deductionname->deduction_type,
                    'ammount_par_paycheck' => $deductionname->ammount_par_paycheck,
                    'changes_field' => ($deductionname->changes_field) ? $deductionname->changes_field : null,
                    'changes_type' => ($deductionname->changes_type) ? $deductionname->changes_type : null,
                ];

            }),

        ];
        //    return $deduction = [
        //         'deduction_status' => $data->deductionlimit->status,
        //         'limit_ammount' => $data->deductionlimit->limit_ammount,
        //         'limit' => $data->deductionlimit->limit,
        //         'limit_type' => $data->deductionlimit->limit_type,
        //         'deduction' => $data->deductionname->map(function ($deductionname) {
        //             return [
        //                 'id' => $deductionname->id,
        //                 'cost_center_id' => $deductionname->cost_center_id,
        //                 'deduction_type' => $deductionname->deduction_type,
        //                 'ammount_par_paycheck' => $deductionname->ammount_par_paycheck,
        //             ];
        //         })->toArray(), // Convert the collection to an array
        //     ];

        if (count($deduction) > 0) {
            return response()->json([
                'ApiName' => 'get-position-deduction',
                'status' => true,
                'deductions' => $deduction,
                'message' => 'data found successfully',
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'get-position-deduction',
                'status' => true,

                'message' => 'data not found for this position id',
            ], 400);
        }

    }

    public function delete($id)
    {
        // return "ewtewe";
        $delete = PositionCommissionDeduction::where(['id' => $id])->delete();
        // PositionsDeductionLimit::where(['position_id'=>$id])->delete();
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
