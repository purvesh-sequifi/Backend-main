<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollUserReconciliationCommissionsHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_user_reconciliation_commissions_histories';

    protected $fillable = [
        'user_id',
        'amount',
        'overrides',
        'clawbacks',
        'period_from',
        'period_to',
        'status',
        'total_due',
        'pay_period_from',
        'pay_period_to',
        'payroll_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
