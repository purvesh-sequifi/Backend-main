<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserReconciliationCommissionLock extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_reconciliation_commissions_lock';

    protected $fillable = [
        'id',
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
        'is_onetime_payment',
        'one_time_payment_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
