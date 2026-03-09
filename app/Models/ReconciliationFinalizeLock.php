<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconciliationFinalizeLock extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'reconciliation_finalize_lock';

    protected $fillable = [
        'office_id',
        'position_id',
        'start_date',
        'end_date',
        'commissions',
        'overrides',
        'total_due',
        'clawbacks',
        'adjustments',
        'deductions',
        'remaining',
        'payout_percentage',
        'net_amount',
        'status',
        'pay_period_from',
        'pay_period_to',
    ];
}
