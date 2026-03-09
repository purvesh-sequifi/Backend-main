<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class W2PayrollTaxDeduction extends Model
{
    use HasFactory;

    const TAXES = [
        "state_income_tax" => "State Income Tax",
        "federal_income_tax" => "Federal Income Tax",
        "medicare_tax" => "Medicare Tax",
        "social_security_tax" => "Social Security Tax",
        "additional_medicare_tax" => "Additional Medicare Tax"
    ];

    protected $table = 'w2_payroll_tax_deductions';

    protected $fillable = [
        'payroll_id',
        'user_id',
        'fica_tax',
        'medicare_withholding',
        'social_security_withholding',
        'state_income_tax',
        'federal_income_tax',
        'medicare_tax',
        'social_security_tax',
        'additional_medicare_tax',
        'pay_period_from',
        'pay_period_to',
        'pay_frequency',
        'user_worker_type',
        'status',
        'payment_id',
        'response',
        'is_onetime_payment',
        'one_time_payment_id'
    ];

    /**
     * Scope to apply pay period filter based on frequency type
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param array $additionalWhere Additional where conditions to apply
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApplyFrequencyFilter($query, $param = [], $additionalWhere = [])
    {
        // Apply pay period filter based on frequency
        $query->when($param['pay_frequency'] == FrequencyType::DAILY_PAY_ID, function ($query) use ($param) {
            $query->whereBetween('pay_period_from', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereBetween('pay_period_to', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereColumn('pay_period_from', 'pay_period_to');
        }, function ($query) use ($param) {
            $query->where([
                'pay_period_from' => $param['pay_period_from'],
                'pay_period_to' => $param['pay_period_to'],
            ]);
        });

        $query->where(['user_worker_type' => $param['worker_type'], 'pay_frequency' => $param['pay_frequency']]);

        // Apply additional where conditions if provided
        if (!empty($additionalWhere)) {
            $query->where($additionalWhere);
        }

        return $query;
    }
}
