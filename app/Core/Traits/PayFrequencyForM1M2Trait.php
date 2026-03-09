<?php

namespace App\Core\Traits;

use App\Models\AdditionalPayFrequency;
use App\Models\FrequencyType;
use App\Models\MonthlyPayFrequency;
use App\Models\PositionPayFrequency;
use App\Models\WeeklyPayFrequency;

trait PayFrequencyForM1M2Trait
{
    public function payFrequencyDate($date, $position_id)
    {
        $positionPayFrequency = PositionPayFrequency::where(['position_id' => $position_id])->first();
        $payFrequency = [];
        if ($positionPayFrequency) {
            if ($positionPayFrequency->frequency_type_id == 2) {
                $current_data = $date;
                $payFrequency = WeeklyPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
            }
            if ($positionPayFrequency->frequency_type_id == 5) {
                $current_data = $date;
                $payFrequency = MonthlyPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->first();
            }
            if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $current_data = $date;
                $payFrequency = AdditionalPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->where('type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->first();
            }
            if ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $current_data = $date;
                $payFrequency = AdditionalPayFrequency::whereRaw('"'.$current_data.'" between `pay_period_from` and `pay_period_to`')->where('type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->first();
            }
        }

        return $payFrequency;
    }
}
