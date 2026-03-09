<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyPayFrequency extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'monthly_pay_frequencies';

    protected $fillable = [
        'pay_period_from',
        'pay_period_to',
        'closed_status',
        'open_status_from_bank',
        'w2_closed_status',
        'w2_open_status_from_bank',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
