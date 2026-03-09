<?php

namespace Database\Seeders;

use App\Models\Plans;
use Illuminate\Database\Seeder;

class AddPlanForSequiAiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequiAiPlanName = 'SequiAI';
        $planData = Plans::where('product_name', $sequiAiPlanName)->first();
        if ($planData == null) {
            $planData = new Plans;
            $planData->id = 4;
            $planData->name = $sequiAiPlanName;
            $planData->product_name = $sequiAiPlanName;
            $planData->unique_pid_rack_price = 0;
            $planData->unique_pid_discount_price = 0;
            $planData->m2_rack_price = null;
            $planData->m2_discount_price = null;
            $planData->sclearance_plan_id = null;
            $planData->created_at = new \DateTime;
            $planData->updated_at = new \DateTime;
            $planData->save();
        } else {
            $planData->id = 4;
            $planData->save();
        }
    }
}
