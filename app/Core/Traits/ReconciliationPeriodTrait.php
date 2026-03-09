<?php

namespace App\Core\Traits;

use App\Models\ReconciliationSchedule;

trait ReconciliationPeriodTrait
{
    public function reconciliationPeriod($date)
    {
        $payFrequency = [];
        if ($date) {
            $payFrequency = ReconciliationSchedule::whereRaw('"'.$date.'" between `period_from` and `period_to`')->first();
            if ($payFrequency) {
                $payFrequency->pay_period_from = isset($payFrequency->period_from) ? $payFrequency->period_from : null;
                $payFrequency->pay_period_to = isset($payFrequency->period_to) ? $payFrequency->period_to : null;
            } else {
                $payFrequency['pay_period_from'] = null;
                $payFrequency['pay_period_to'] = null;
                $payFrequency = (object) $payFrequency;
            }
        }

        return $payFrequency;
    }
}
