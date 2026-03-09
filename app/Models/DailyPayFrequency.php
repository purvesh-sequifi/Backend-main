<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyPayFrequency extends Model
{
    use HasFactory;
    use HasFactory;

    protected $table = 'daily_pay_frequencies';

    const OPENED_PAYROLL = '0';

    const CLOSED_PAYROLL = '1';

    protected $fillable = [
        'pay_period_from',
        'pay_period_to',
        'closed_status',
        'open_status_from_bank',
        'w2_closed_status',
        'w2_open_status_from_bank',
    ];
}
