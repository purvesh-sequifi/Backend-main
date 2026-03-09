<?php

namespace Database\Seeders;

use App\Models\CrmSetting;
use App\Models\Plans;
use App\Models\Subscriptions;
use Illuminate\Database\Seeder;

class AddSequiAiSubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequiAiPlanName = 'SequiAI';
        $planData = Plans::where('product_name', $sequiAiPlanName)->first();
        if ($planData != null) {
            $planId = $planData->id;

            $lastSubscription = Subscriptions::orderBy('id', 'DESC')->first();
            $checkSequiAiSubscription = Subscriptions::where('plan_id', $planId)->first();

            $crmSetting = CrmSetting::where('crm_id', 6)->first();
            $status = $crmSetting->status ?? 0;

            if ($checkSequiAiSubscription == null) {
                $newSubsscription = new Subscriptions;
                $newSubsscription->plan_type_id = 1;
                $newSubsscription->plan_id = $planId;
                $newSubsscription->start_date = $lastSubscription->start_date;
                $newSubsscription->end_date = $lastSubscription->end_date;
                $newSubsscription->status = $status;
                $newSubsscription->paid_status = 0;
                $newSubsscription->total_pid = 0;
                $newSubsscription->total_m2 = 0;
                $newSubsscription->sales_tax_per = 0;
                $newSubsscription->sales_tax_amount = 0;
                $newSubsscription->amount = 0;
                $newSubsscription->credit_amount = 0;
                $newSubsscription->used_credit = 0;
                $newSubsscription->balance_credit = 0;
                $newSubsscription->taxable_amount = 0;
                $newSubsscription->grand_total = 0;
                $newSubsscription->save();
            }
        }
    }
}
