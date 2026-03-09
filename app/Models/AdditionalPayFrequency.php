<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdditionalPayFrequency extends Model
{
    use HasFactory;

    protected $table = 'additional_pay_frequencies';

    const OPENED_PAYROLL = '0';

    const CLOSED_PAYROLL = '1';

    const BI_WEEKLY_TYPE = '1';

    const SEMI_MONTHLY_TYPE = '2';

    protected $fillable = [
        'pay_period_from',
        'pay_period_to',
        'closed_status',
        'open_status_from_bank',
        'w2_closed_status',
        'w2_open_status_from_bank',
        'type',
    ];

    public function payroll(): HasOne
    {
        return $this->hasOne(Payroll::class, 'pay_period_from', 'pay_period_from');
    }

    public function payrollHistory(): HasOne
    {
        return $this->hasOne(PayrollHistory::class, 'pay_period_from', 'pay_period_from');
    }

    public function payrollHistoryEvery(): HasOne
    {
        return $this->hasOne(PayrollHistory::class, 'pay_period_from', 'pay_period_from');
    }
}
