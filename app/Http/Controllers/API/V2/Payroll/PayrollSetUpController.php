<?php

namespace App\Http\Controllers\API\V2\Payroll;

use App\Models\Payroll;
use Illuminate\Http\Request;
use App\Models\ApprovalsAndRequest;
use App\Http\Controllers\Controller;
use App\Models\AdvancePaymentSetting;
use App\Core\Traits\PayFrequencyTrait;
use Illuminate\Support\Facades\Validator;

class PayrollSetUpController extends Controller
{
    use PayFrequencyTrait;

    public function getAdvancePaymentSetting()
    {
        return response()->json([
            'ApiName' => 'get-advance-payment-setting',
            'status' => true,
            'message' => 'Success.',
            'data' => AdvancePaymentSetting::first()
        ]);
    }

    public function advancePaymentSetting(Request $request)
    {
        // DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            "adwance_setting" => "required"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "advance-payment-setting",
                "error" => $validator->errors()
            ], 400);
        }

        $errors = [];
        $changedSetting = false;
        $advancePaymentSetting = AdvancePaymentSetting::first();
        if (!$advancePaymentSetting) {
            $advancePaymentSetting = AdvancePaymentSetting::create(['adwance_setting' => $request->adwance_setting]);
            $changedSetting = true;
        } else {
            if ($advancePaymentSetting->adwance_setting != $request->adwance_setting) {
                $changedSetting = true;
            }
            $advancePaymentSetting->adwance_setting = $request->adwance_setting;
            $advancePaymentSetting->save();
        }

        if ($changedSetting) {
            if ($advancePaymentSetting->adwance_setting == 'automatic') {
                $usersAdvanceRequests = ApprovalsAndRequest::with('user:id,sub_position_id')->where(['adjustment_type_id' => 4, 'status' => 'Approved'])->whereNull('req_no')->get();
                foreach ($usersAdvanceRequests as $usersAdvanceRequest) {
                    $payFrequency = $this->openPayFrequency($usersAdvanceRequest?->user?->sub_position_id, $usersAdvanceRequest->user_id);
                    if (!$payFrequency) {
                        $errors[] = 'Pay frequency not found for user ' . $usersAdvanceRequest->user_id;
                        continue;
                    }
                    if (!isset($payFrequency->next_pay_period_from)) {
                        $errors[] = 'Pay period not found for user ' . $usersAdvanceRequest->user_id;
                        continue;
                    }

                    $payPeriodFrom = $payFrequency?->next_pay_period_from;
                    $payPeriodTo = $payFrequency?->next_pay_period_to;
                    ApprovalsAndRequest::where('id', $usersAdvanceRequest->id)->update(['status' => 'Accept']);
                    $childRequestAmount = ApprovalsAndRequest::where('parent_id', $usersAdvanceRequest->id)->sum('amount');
                    $reqAmount = $usersAdvanceRequest->amount - $childRequestAmount;
                    $usersAdvanceRequest->amount = $reqAmount;
                    if ($reqAmount < 0) {
                        $newData = $usersAdvanceRequest->toArray();
                        $newData['amount'] = $reqAmount;
                        $newData['parent_id'] = $usersAdvanceRequest->id;
                        $newData['status'] = "Accept";
                        $newData['pay_period_from'] = $payPeriodFrom;
                        $newData['pay_period_to'] = $payPeriodTo;
                        unset($newData['id']);
                        ApprovalsAndRequest::create($newData);
                    }
                }
            } else {
                $usersAdvanceRequests = ApprovalsAndRequest::with('user:id,sub_position_id,worker_type')->where(['adjustment_type_id' => 4, 'status' => 'Accept'])->whereNull('req_no')->get();
                foreach ($usersAdvanceRequests as $usersAdvanceRequest) {
                    $payFrequency = $this->openPayFrequency($usersAdvanceRequest?->user?->sub_position_id, $usersAdvanceRequest->user_id);
                    if (!$payFrequency) {
                        $errors[] = 'Pay frequency not found for user ' . $usersAdvanceRequest->user_id;
                        continue;
                    }
                    if (!isset($payFrequency->next_pay_period_from)) {
                        $errors[] = 'Pay period not found for user ' . $usersAdvanceRequest->user_id;
                        continue;
                    }

                    $payPeriodFrom = $payFrequency?->next_pay_period_from;
                    $payPeriodTo = $payFrequency?->next_pay_period_to;

                    $param = [
                        "pay_frequency" => $payFrequency?->pay_frequency,
                        "worker_type" => $usersAdvanceRequest?->user?->worker_type,
                        "pay_period_from" => $payFrequency?->pay_period_from,
                        "pay_period_to" => $payFrequency?->pay_period_to
                    ];
                    $payroll = Payroll::applyFrequencyFilter($param, ['user_id' => $usersAdvanceRequest->user_id])->whereIn('finalize_status', [1, 2])->first();
                    if (!$payroll) {
                        ApprovalsAndRequest::where('id', $usersAdvanceRequest->id)->update(['status' => 'Approved']);
                        $childApprovalsAndRequests = ApprovalsAndRequest::where(['parent_id' => $usersAdvanceRequest->id, 'status' => 'Accept'])->get();
                        foreach ($childApprovalsAndRequests as $childApprovalsAndRequest) {
                            $childApprovalsAndRequest->delete();
                        }
                    }
                }
            }
        }

        if (count($errors) > 0) {
            return response()->json([
                "status" => false,
                "ApiName" => "advance-payment-setting",
                "message" => $errors
            ], 400);
        }

        return response()->json([
            "status" => true,
            "ApiName" => "advance-payment-setting",
            "message" => "Advance payment setting updated successfully"
        ]);
    }
}
