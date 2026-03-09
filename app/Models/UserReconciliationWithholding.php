<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserReconciliationWithholding extends Model
{
    use HasFactory;

    protected $table = 'user_reconciliation_withholds';

    protected $fillable = [
        'pid',
        'closer_id',
        'setter_id',
        'payroll_id',
        'withhold_amount',
        'adjustment_amount',
        'comment',
        'status',
        'finalize_status',
        'payroll_to_recon_status',
        'pay_period_from',
        'pay_period_to',
        'created_at',
        'updated_at',
    ];
    // protected $hidden = [
    //     'created_at',
    //     'updated_at'
    // ];

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function setter(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'setter_id');
    }

    public function closer(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'closer_id');
    }

    public function saleMasterProcess(): HasOne
    {
        return $this->hasOne(\App\Models\SaleMasterProcess::class, 'pid', 'pid');
    }
}
