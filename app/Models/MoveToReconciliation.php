<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MoveToReconciliation extends Model
{
    use HasFactory;

    protected $table = 'move_to_reconciliations';

    protected $fillable = [
        'user_id',
        'payroll_id',
        'pid',
        'commission',
        'override',
        'clawback',
        'status',
        'start_date',
        'end_date',
        'pay_period_from',
        'pay_period_to',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }
}
