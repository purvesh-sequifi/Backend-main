<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PositionPayFrequency extends Model
{
    use HasFactory;

    protected $table = 'position_pay_frequencies';

    protected $fillable = [
        'position_id',
        'frequency_type_id',
        'first_months',
        'first_day',
        'day_of_week',
        'day_of_months',
        'pay_period',
        'monthly_per_days',
        'first_day_pay_of_manths',
        'second_pay_day_of_month',
        'deadline_to_run_payroll',
        'first_pay_period_ends_on',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function frequencyType(): HasOne
    {
        return $this->hasOne(\App\Models\FrequencyType::class, 'id', 'frequency_type_id');
    }
}
