<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomField extends Model
{
    use HasFactory;

    protected $table = 'custom_field';

    protected $fillable = [
        'user_id',
        'payroll_id',
        'column_id',
        'value',
        'comment',
        'approved_by',
        'is_next_payroll',
        'is_mark_paid',
        'ref_id',
        'user_worker_type',
        'pay_frequency',
        'pay_period_from',
        'pay_period_to',
        'is_onetime_payment',
        'one_time_payment_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getColumn(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PayrollSsetup::class, 'column_id', 'id');
    }

    public function getApprovedBy(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'approved_by');
    }

    public function PayrollSsetup(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollSsetup::class, 'id', 'column_id');
    }
    
    public function customField()
    {
        return $this->hasOne('App\Models\PayrollSsetup','id','column_id');
    }
}
