<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollAdjustmentDetailHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_adjustment_detail_histories';

    protected $fillable = [
        'payroll_id',
        'user_id',
        'pid',
        'payroll_type',
        'type',
        'amount',
        'comment',
        'cost_center_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(\App\Models\PayrollAdjustmentDetail::class, 'payroll_id', 'payroll_id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne('App\Models\user', 'id', 'user_id');
    }
}
