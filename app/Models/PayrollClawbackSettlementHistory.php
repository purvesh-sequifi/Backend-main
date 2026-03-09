<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollClawbackSettlementHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_clawback_settlement_histories';

    protected $fillable = [
        'user_id',
        'position_id',
        'sale_user_id',
        'pid',
        'clawback_amount',
        'clawback_type',
        'status',
        'action_status',
        'type',
        'adders_type',
        'pay_period_from',
        'pay_period_to',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'created_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function users(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'email', 'redline', 'position_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->with('recruiter');
    }

    public function userInfo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }
}
