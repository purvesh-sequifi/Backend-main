<?php

namespace App\Http\Controllers\API\V2\PositionSettlement;

use App\Http\Controllers\Controller;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class PositionSettlementController extends Controller
{
    public function index(): JsonResponse
    {
        $data = PositionReconciliations::all();

        return response()->json([
            'ApiName' => 'list-position-settlement',
            'status' => true,
            'message' => 'data found !!!',
            'data' => $data,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // $rules = [
            //    'position_id' => 'required|integer',
            //     'status' => 'required|integer',
            //     'settlement' => 'nullable|array',
            //     'settlement.*.product_id' => 'nullable|integer',
            //     'settlement.*.commission_withheld' => 'nullable|numeric',
            //     'settlement.*.commission_type' => 'nullable|string',
            //     'settlement.*.maximum_withheld' => 'nullable|numeric',
            //     'settlement.*.override_settlement' => 'nullable|integer',
            //     'settlement.*.clawback_settlement' => 'nullable|integer',
            //     'settlement.*.stack_settlement' => 'nullable|integer',
            // ];

            // // Custom validation
            // $validator = Validator::make($request->all(), $rules);

            // if ($validator->fails()) {
            //     return response()->json([
            //         'errors' => $validator->errors()
            //     ], 422);
            // }

            $position_id = $request->position_id;
            //  $status = $request->status;
            PositionReconciliations::where(['position_id' => $position_id])->delete();
            foreach ($request->settlement as $settle) {
                //  PositionReconciliations::where(["position_id"=>$position_id,"product_id"=>$settle['product_id']])->delete();

                $commission_withheld = null;
                if (isset($settle['commission_withheld']) && $settle['commission_withheld']) {
                    $commission_withheld = $settle['commission_withheld'];
                }
                $commission_type = null;
                if (isset($settle['commission_type']) && $settle['commission_type']) {
                    $commission_type = $settle['commission_type'];
                }
                $maximum_withheld = null;
                if (isset($settle['maximum_withheld']) && $settle['maximum_withheld']) {
                    $maximum_withheld = $settle['maximum_withheld'];
                }
                $override_settlement = null;
                if (isset($settle['override_settlement']) && $settle['override_settlement']) {
                    $override_settlement = $settle['override_settlement'];
                }
                $clawback_settlement = null;
                if (isset($settle['clawback_settlement']) && $settle['clawback_settlement']) {
                    $clawback_settlement = $settle['clawback_settlement'];
                }
                $stack_settlement = null;
                if (isset($settle['stack_settlement']) && $settle['stack_settlement']) {
                    $stack_settlement = $settle['stack_settlement'];
                }

                $settlestore = new PositionReconciliations;
                $settlestore->position_id = $position_id;
                $settlestore->product_id = $settle['product_id'];
                $settlestore->commission_withheld = $commission_withheld;
                $settlestore->commission_type = $commission_type;
                $settlestore->maximum_withheld = $maximum_withheld;
                $settlestore->override_settlement = $override_settlement;
                $settlestore->clawback_settlement = $clawback_settlement;
                $settlestore->stack_settlement = $stack_settlement;
                $settlestore->status = $settle['status'];

                $settlestore->save();

                // $settlestore->commission_withheld =

            }
            $position_status = 0;
            if (isset($request['position_status']) && $request['position_status']) {
                $position_status = $request['position_status'];
            }
            $posidata = Positions::where('id', $position_id)->update(['setup_status' => $position_status]); // update setup_status

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

    public function edit($id)
    {

        $data = Positions::withcount('peoples')->with('reconciliation')->where('id', $id)->first();
        $data = collect($data->reconciliation);
        $settlement = $data->map(function ($item) {
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
        if (count($settlement) > 0) {
            return response()->json([
                'ApiName' => 'get-position-settlement',
                'status' => true,
                'settlement' => $settlement,
                'message' => 'data found successfully',
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'get-position-settlement',
                'status' => true,

                'message' => 'data not found for this position id',
            ], 400);
        }

    }

    public function delete($id)
    {
        // return "ewtewe";
        $delete = PositionReconciliations::where(['position_id' => $id])->delete();

        if ($delete) {

            return response()->json([
                'ApiName' => 'delete-position-settlement',
                'status' => true,
                'data' => $delete,
                'message' => 'Record Deleted Successfully',
            ], 200);

        } else {
            return response()->json([
                'ApiName' => 'delete-position-settlement',
                'status' => false,
                'message' => 'Record Not Found For Deleted',
            ], 400);
        }
    }
}
