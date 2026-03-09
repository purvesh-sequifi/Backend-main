<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SequiaiRequestHistory extends Model
{
    use HasFactory;

    public static function userRequestBillingData($sequiai_plan_id)
    {

        $sequiaiPlan = SequiaiPlan::find($sequiai_plan_id);
        $sequiAiPlanCrmSetting = CrmSetting::where('crm_id', 6)->first();

        $response['user_request_ids'] = [];
        $response['user_request_id_count'] = 0;
        $response['bill_amount'] = 0;

        if (isset($sequiaiPlan->status) && $sequiaiPlan->status == 1 && $sequiAiPlanCrmSetting->status == 1) {
            $response['bill_amount'] = $sequiaiPlan->price;
        }

        if ($sequiaiPlan != null) {
            $min_request = (int) $sequiaiPlan->min_request;
            $min_request_price = $sequiaiPlan->price;

            $currentMonth = Carbon::now()->month;
            $historyIds = SequiaiRequestHistory::where(['status' => 0])->pluck('id')->toArray();

            $records = count($historyIds) / $min_request;
            $records_roundup = (int) $records;
            if (is_float($records)) {
                $records_roundup++;
            }
            // $recordLimit = $records_roundup * $min_request;
            // $getRrecords = SequiaiRequestHistory::where(['status'=> 0])->pluck('id')->toArray();

            $response['user_request_ids'] = $historyIds;
            $response['user_request_id_count'] = count($historyIds);
            $response['crm_setting_status'] = $sequiAiPlanCrmSetting->status;
            if ($records_roundup != 0) {
                $response['bill_amount'] = $records_roundup * $min_request_price;
            }
        }

        return $response;
    }

    public function requestedUserDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'email');
    }

    public function requestedPlanDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SequiaiPlan::class, 'id', 'sequiai_plan_id');
    }
}
