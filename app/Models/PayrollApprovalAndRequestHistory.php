<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollApprovalAndRequestHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_approval_and_request_histories';

    protected $fillable = [
        'req_no',
        'user_id',
        'manager_id',
        'created_by',
        'approved_by',
        'adjustment_type_id',
        'pay_period',
        'state_id',
        'dispute_type',
        'customer_pid',
        'description',
        'cost_tracking_id',
        'emi',
        'request_date',
        'cost_date',
        'txn_id',
        'amount',
        'is_mark_paid',
        'is_next_payroll',
        'pay_period_from',
        'pay_period_to',
        'image',
        'status',
        'declined_at',
    ];

    // protected $hidden = [
    //     'created_at',
    //     'updated_at'
    // ];

    public function adjustment(): HasOne
    {
        return $this->hasOne(\App\Models\AdjustementType::class, 'id', 'adjustment_type_id');
    }

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_tracking_id');
    }

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id');
    }

    public function user()
    {
        return $this->hasone(\App\Models\User::class, 'id', 'user_id')->with('office');
    }

    public function approvedBy()
    {
        return $this->hasone(\App\Models\User::class, 'id', 'approved_by');
    }

    public function PID()
    {
        return $this->hasone(\App\Models\SalesMaster::class, 'pid', 'customer_pid');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(\App\Models\ApprovalAndRequestComment::class, 'request_id', 'id');
    }

    public function getPid(): HasMany
    {
        return $this->hasMany(\App\Models\RequestApprovelByPid::class, 'request_id', 'id');
    }

    public function userComment(): HasOne
    {
        return $this->hasOne(\App\Models\ApprovalAndRequestComment::class, 'user_id', 'id');
    }

    public function userData(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select(['id', 'first_name', 'last_name', 'image']);
    }
}
