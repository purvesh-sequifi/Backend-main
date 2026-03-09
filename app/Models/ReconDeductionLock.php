<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconDeductionLock extends Model
{
    use HasFactory;

    protected $table = 'recon_deduction_locks';

    protected $fillable = [
        'id',
        'payroll_id',
        'user_id',
        'cost_center_id',
        'amount',
        'limit',
        'total',
        'outstanding',
        'subtotal',
        'start_date',
        'end_date',
        'pay_period_from',
        'pay_period_to',
        'finalize_count',
        'payroll_status',
        'status',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'ref_id',
        'is_move_to_recon',
        'is_move_to_recon_paid',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_center_id');
    }
}
