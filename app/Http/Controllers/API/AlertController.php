<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CostCenterValidatedRequest;
use App\Models\Alerts;
use App\Models\CostCenter;
use App\Models\IncompleteAccountAlert;
use App\Models\MarketingDealAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    private $alerts;

    private $incompleteAccountAlert;

    private $marketingDealAlert;

    public function __construct(Alerts $alerts, MarketingDealAlert $marketingDealAlert, IncompleteAccountAlert $incompleteAccountAlert)
    {
        $this->alerts = $alerts;
        $this->incompleteAccountAlert = $incompleteAccountAlert;
        $this->marketingDealAlert = $marketingDealAlert;
    }

    /**
     * Display a listing of the resource.
     */
    public function updatestatus(Request $request): JsonResponse
    {
        if (! empty($request->id || $request->status)) {
            // if ($request->type == 'marking') {
            $status = alerts::where('id', $request->id)->first();
            $status->status = $request->status;
            $status->save();

            return response()->json([
                'status' => true,
                'message' => 'Status change successfully.',
            ], 200);
        } else {
            return response()->json(['Failed' => 'Type not exists.']);
        }
    }

    public function getMarketingDealAlert(): JsonResponse
    {
        $alerts_data = Alerts::where('id', 1)->orderBy('id', 'ASC')->get();
        $data = [];
        foreach ($alerts_data as $alert_data) {
            if ($alert_data->id == 1) {

                $details = $this->MarketingDealAlert($alert_data->id);

            } else {

                $details = [];
            }
            $data[] =
                     [
                         'id' => $alert_data->id,
                         'name' => $alert_data->name,
                         'status' => $alert_data->status,
                         'details' => $details,

                     ];
        }

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function getIncompleteAccountAlert(): JsonResponse
    {
        $alerts_data = Alerts::where('id', 2)->orderBy('id', 'ASC')->get();
        $data = [];
        foreach ($alerts_data as $alert_data) {
            if ($alert_data->id == 2) {
                $details = $this->IncompleteAccountAlert($alert_data->id);
            } else {
                $details = [];
            }
            $data[] =
                     [
                         'id' => $alert_data->id,
                         'name' => $alert_data->name,
                         'status' => $alert_data->status,
                         'details' => $details,

                     ];
        }

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $data], 200);
    }

    public function updateMarketingdeal(Request $request)
    {
        // dd($request);
        MarketingDealAlert::where('alert_id', 1)->delete();

        // dd($da;
        // return $request->MarketingD
        foreach ($request->data as $data_marketing) {
            MarketingDealAlert::create(
                [
                    'alert_id' => $request['id'],
                    'alert_type' => $data_marketing['alert_type'],
                    'department_id' => $data_marketing['department_id'],
                    'position_id' => $data_marketing['position_id'],
                    'personnel_id' => $data_marketing['personnel_id'],
                    'amount' => $data_marketing['amount'],
                ]
            );
        }

        return response()->json(['status' => true, 'message' => 'Marketing Deal Alert Add Successfully.'], 200);
    }

    public function updateIncompleteAccount(Request $request): JsonResponse
    {
        // dd($request);
        IncompleteAccountAlert::where('alert_id', 2)->delete();
        foreach ($request->data as $value) {

            IncompleteAccountAlert::create(
                [
                    'alert_id' => $request['id'],
                    'alert_type' => isset($value['alert_type']) ? $value['alert_type'] : null,
                    'number' => isset($value['number']) ? $value['number'] : null,
                    'type' => isset($value['type']) ? $value['type'] : 'day',
                    'department_id' => isset($value['department_id']) ? $value['department_id'] : null,
                    'status' => isset($value['status']) ? $value['status'] : null,
                    'position_id' => isset($value['position_id']) ? $value['position_id'] : null,
                ]
            );
        }

        return response()->json(['status' => true, 'message' => 'Incomplete  Account Add Successfully.'], 200);
    }

    public function show($id): JsonResponse
    {
        $costCenter = CostCenter::where(['id' => $id])->first();
        // if($costCenter->isEmpty())
        // return response()->json(['status' => 'error', 'message' => 'Sorry the Cost Center you are looking for is not found.'], 404);
        if (empty($costCenter)) {
            return response()->json(['status' => false, 'message' => 'Sorry the Cost Center you are looking for is not found.'], 400);
        }

        return response()->json(['status' => true, 'message' => 'Show Successfully.', 'costCenter' => $costCenter], 200);
    }

    public function update(CostCenterValidatedRequest $request, $id): JsonResponse
    {
        $this->costCenter = CostCenter::find($id);
        // dd($request->name);die;
        if (! $this->costCenter) {
            return response()->json(['status' => false, 'message' => 'Sorry the Cost Center you are looking for is not found.'], 400);
        }

        // if ($request->parent_id)
        $this->costCenter->parent_id = $request->parent_id;
        $this->costCenter->name = $request->name;
        $this->costCenter->code = $request->code;
        $this->costCenter->description = $request->description;
        $this->costCenter->status = $request->status;
        $this->costCenter->update();

        return response()->json(['status' => true, 'message' => 'Updated Successfully.', 'cost_center_id' => $this->costCenter->id], 200);
    }

    public function enableDisableAlert(Request $request): JsonResponse
    {

        $this->alerts = Alerts::find($request->id);
        if (! $this->alerts) {
            return response()->json(['status' => false, 'message' => 'Sorry the alert you are looking for is not found.'], 400);
        }

        $this->alerts->status = $request->status;
        $this->alerts->save();

        return response()->json(['status' => true, 'message' => 'Alert Successfully updated.', 'alert_id' => $this->alerts->id], 200);
    }

    private function IncompleteAccountAlert($alert_id)
    {
        $IncompleteAccountAlert = IncompleteAccountAlert::with('department', 'position')->where('alert_id', $alert_id)->get();
        $data = [];
        foreach ($IncompleteAccountAlert as $alert_data) {

            $data[] =
                     [
                         'id' => $alert_data->id,
                         'alert_id' => $alert_data->alert_id,
                         'alert_type' => $alert_data->alert_type,
                         'number' => $alert_data->number,
                         'type' => $alert_data->type,
                         'department_id' => $alert_data->department_id,
                         'department_name' => $alert_data->department->name,
                         'position_id' => $alert_data->position_id,
                         'status' => $alert_data->status,
                         'position_name' => $alert_data->position->position_name,
                         'amount' => $alert_data->amount,
                     ];
        }

        return $data;
    }

    private function MarketingDealAlert($alert_id)
    {
        $MarketingDealAlert = MarketingDealAlert::with('department', 'position', 'user')->where('alert_id', $alert_id)->get();
        $data = [];
        foreach ($MarketingDealAlert as $alert_data) {

            $data[] =
                     [
                         'id' => $alert_data->id,
                         'alert_id' => $alert_data->alert_id,
                         'alert_type' => $alert_data->alert_type,
                         'department_id' => $alert_data->department_id,
                         'department_name' => $alert_data->department->name,
                         'position_id' => $alert_data->position_id,
                         'position_name' => $alert_data->position->name,
                         'amount' => $alert_data->amount,
                         'name' => isset($alert_data->user->first_name) ? $alert_data->user->first_name.' '.$alert_data->user->last_name : null,
                         'image' => isset($alert_data->user->first_name) ? 'default-user.png' : null,

                     ];
        }

        return $data;
    }
}
